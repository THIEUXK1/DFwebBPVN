<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Machine;
use App\Models\ProductionBatch;
use App\Models\PrintJob;
use App\Models\Workstation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintJobPipelineTest extends TestCase
{
    private User $operator;
    private Machine $machine;
    private ProductionBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = User::firstOrCreate(
            ['username' => 'operator_print'],
            [
                'display_name' => 'Print Operator',
                'password_hash' => password_hash('op123', PASSWORD_BCRYPT),
                'roles' => ['OPERATOR']
            ]
        );

        $this->machine = Machine::firstOrCreate(
            ['code' => 'M-PRT01'],
            ['name' => 'Print Test Machine']
        );

        $this->batch = ProductionBatch::create([
            'legacy_batch_id' => 'B-PRT-' . uniqid(),
            'color' => 'A+110293',
            'product_code' => 'T7400',
            'machine_id' => $this->machine->id,
            'status' => 'NEW'
        ]);
        $this->batch->refresh();
    }

    protected function tearDown(): void
    {
        $this->batch->delete();
        parent::tearDown();
    }

    /**
     * Test print job generation, local agent fetching, and acknowledgement.
     */
    public function test_print_job_pipeline(): void
    {
        // 1. Create a print job
        $payload = [
            'batch_id' => $this->batch->id,
            'workstation_id' => 'WS-PRT-01',
            'width' => 60,
            'height' => 45,
            'printer_connection_type' => 'USB',
            'printer_address' => 'TSC-TEST'
        ];

        $response = $this->actingAs($this->operator)
            ->postJson('/api/print-jobs', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'SUCCESS');
        
        $jobId = $response->json('data.id');
        $this->assertNotNull($jobId);

        // 2. Fetch jobs as Local Agent
        $response = $this->getJson('/api/agents/WS-PRT-01/jobs');
        
        $response->assertStatus(200);
        $response->assertJsonCount(1);
        
        // Assert mapped TSPL content
        $jobData = $response->json(0);
        $this->assertEquals($jobId, $jobData['Id']);
        $this->assertStringContainsString('SIZE 60 mm, 45 mm', $jobData['LabelPayload']);
        $this->assertStringContainsString('QRCODE', $jobData['LabelPayload']);
        $this->assertEquals('USB', $jobData['PrinterConnectionType']);
        $this->assertEquals('TSC-TEST', $jobData['PrinterAddress']);

        // 3. Acknowledge job completion
        $response = $this->postJson("/api/jobs/{$jobId}/ack", [
            'status' => 'SUCCESS'
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'SUCCESS');

        // 4. Verify DB state — "SUCCESS" (Worker.cs cũ) được chuẩn hóa về đúng quy ước
        // PENDING/PRINTED/FAILED dùng chung toàn hệ thống (badge trạng thái, tier C).
        $job = PrintJob::find($jobId);
        $this->assertNotNull($job);
        $this->assertEquals('PRINTED', $job->status);
        $this->assertNotNull($job->processed_at);

        // 5. Tier C — print_attempts phải được ghi lại (trước đây route ack không tạo
        // attempt nào dù Agent đã in thật).
        $this->assertDatabaseHas('app.print_attempts', [
            'print_job_id' => $jobId,
            'attempt_no' => 1,
            'status' => 'PRINTED',
        ]);

        // 6. print_job_events phải có đủ các mốc chính trong vòng đời (yêu cầu 2026-07-18)
        $eventTypes = \App\Models\PrintJobEvent::where('print_job_id', $jobId)->pluck('event_type')->all();
        foreach (['JOB_CREATED', 'PRINT_REQUESTED', 'AGENT_CLAIMED', 'SENT_TO_PRINTER', 'PRINT_SUCCEEDED'] as $expected) {
            $this->assertContains($expected, $eventTypes, "Thiếu event {$expected} trong print_job_events");
        }

        // Clean up
        $job->delete();
    }

    /**
     * agent.auth (gap #2, Phase E): khi ép buộc enforcement, poll job qua
     * GET /agents/{workstation_id}/jobs mà không có token hợp lệ phải bị từ chối,
     * và có token hợp lệ của ĐÚNG workstation thì vẫn lấy job bình thường.
     */
    public function test_get_jobs_requires_valid_workstation_token_when_enforced(): void
    {
        $payload = [
            'batch_id' => $this->batch->id,
            'workstation_id' => 'WS-PRT-SECURE',
            'width' => 60,
            'height' => 45,
            'printer_connection_type' => 'USB',
            'printer_address' => 'TSC-TEST'
        ];
        $created = $this->actingAs($this->operator)->postJson('/api/print-jobs', $payload);
        $jobId = $created->json('data.id');

        // Không token -> 401
        $this->getJson('/api/agents/WS-PRT-SECURE/jobs', ['X-Enforce-Workstation-Guard' => '1'])
            ->assertStatus(401);

        $plainToken = 'TEST-TOKEN-' . uniqid();
        Workstation::create([
            'code' => 'WS-PRT-SECURE',
            'name' => 'Secure Print Station',
            'type' => 'QR_LABEL_PRINTING',
            'active' => true,
            'registration_token_hash' => hash('sha256', $plainToken),
        ]);

        // Token đúng workstation -> lấy job thành công
        $response = $this->getJson('/api/agents/WS-PRT-SECURE/jobs', [
            'X-Enforce-Workstation-Guard' => '1',
            'X-Workstation-Token' => $plainToken,
        ]);
        $response->assertStatus(200);
        $response->assertJsonCount(1);

        PrintJob::find($jobId)?->delete();
    }

    /**
     * Local Agent báo cáo danh sách máy in cài sẵn trên máy (Windows Print Spooler)
     * qua POST /agents/{workstation_id}/printers — web đọc lại từ
     * Workstation.configuration để hiển thị dropdown chọn máy in thật, thay gõ tay.
     */
    public function test_agent_reports_installed_printers_and_web_can_read_them_back(): void
    {
        $plainToken = 'TEST-TOKEN-' . uniqid();
        Workstation::create([
            'code' => 'WS-PRT-PRINTERS',
            'name' => 'Print Station Printers Test',
            'type' => 'QR_LABEL_PRINTING',
            'active' => true,
            'registration_token_hash' => hash('sha256', $plainToken),
        ]);

        $response = $this->postJson('/api/agents/WS-PRT-PRINTERS/printers', [
            'printers' => ['TSC TE200', 'Microsoft Print to PDF'],
            'default_printer' => 'TSC TE200',
        ], [
            'X-Enforce-Workstation-Guard' => '1',
            'X-Workstation-Token' => $plainToken,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'SUCCESS');

        $ws = Workstation::where('code', 'WS-PRT-PRINTERS')->first();
        $this->assertEquals(['TSC TE200', 'Microsoft Print to PDF'], $ws->configuration['available_printers']);
        $this->assertEquals('TSC TE200', $ws->configuration['default_printer']);

        // Xác nhận thông tin này thật sự lộ ra qua GET /api/workstations (nơi frontend đọc).
        $listResponse = $this->actingAs($this->operator)->getJson('/api/workstations');
        $found = collect($listResponse->json('data'))->firstWhere('code', 'WS-PRT-PRINTERS');
        $this->assertEquals('TSC TE200', $found['configuration']['default_printer']);
    }
}
