<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Machine;
use App\Models\ProductionBatch;
use App\Models\ScaleMeasurement;
use App\Models\Workstation;
use App\Models\WeighingJob;
use App\Models\WeighingJobItem;
use App\Models\PrintJob;
use App\Models\Material;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Tra cứu bán thành phẩm (checker) + Phiếu cân tổng hợp (print slip) — port từ
 * VBA `checkform`/`scaleform.btnPrint_Click` (workbook semiauto-small-scale, mới unlock
 * 2026-07-22). Xem .claude/pilot-blockers.md hạng mục "Cân — tra cứu checker" (FIX-009).
 */
class ScaleCheckerAndPrintSlipTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private Machine $machine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'WorkstationsSeeder']);

        $user = new User();
        $user->id = (string) Str::uuid();
        $user->username = 'checker_tester_' . uniqid();
        $user->display_name = 'Checker Tester';
        $user->password_hash = password_hash('password', PASSWORD_BCRYPT);
        $user->save();
        $this->user = $user;

        $this->machine = Machine::create([
            'code' => 'VD-CHK-' . uniqid(),
            'name' => 'Machine Checker Test',
            'is_active' => true,
        ]);
    }

    public function test_checker_groups_measurements_by_batch_and_filters_by_color_code(): void
    {
        Sanctum::actingAs($this->user);

        $color = 'CHK-RED-' . uniqid();
        $code = 'CHK-CODE-01';

        ScaleMeasurement::create([
            'legacy_source' => 'WEB_WEIGH',
            'legacy_id' => random_int(1, 999999),
            'legacy_batch_id' => 'BATCH-CHK-A',
            'color' => $color,
            'product_code' => $code,
            'machine_code' => $this->machine->code,
            'level_code' => 'L1',
            'rack_code' => 'R1',
            'dye_code' => 'DYE-A',
            'weight' => 50.123456,
            'process_code' => 'HS',
            'material_type' => 'DYE',
            'measured_at' => Carbon::now(),
        ]);

        ScaleMeasurement::create([
            'legacy_source' => 'WEB_WEIGH',
            'legacy_id' => random_int(1, 999999),
            'legacy_batch_id' => 'BATCH-CHK-A',
            'color' => $color,
            'product_code' => $code,
            'machine_code' => $this->machine->code,
            'level_code' => 'L1',
            'rack_code' => 'R2',
            'dye_code' => 'DYE-B',
            'weight' => 12.5,
            'process_code' => 'HS',
            'material_type' => 'DYE',
            'measured_at' => Carbon::now(),
        ]);

        // Different color — must NOT show up in the result.
        ScaleMeasurement::create([
            'legacy_source' => 'WEB_WEIGH',
            'legacy_id' => random_int(1, 999999),
            'legacy_batch_id' => 'BATCH-CHK-OTHER',
            'color' => 'UNRELATED-COLOR',
            'product_code' => $code,
            'machine_code' => $this->machine->code,
            'weight' => 1.0,
            'material_type' => 'DYE',
            'measured_at' => Carbon::now(),
        ]);

        $response = $this->getJson('/api/scale-measurements/checker?' . http_build_query([
            'color' => $color,
            'code' => $code,
        ]));

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'SUCCESS');
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.batch_id', 'BATCH-CHK-A');
        $response->assertJsonCount(2, 'data.0.items');
    }

    public function test_checker_days_back_filters_out_older_measurements(): void
    {
        Sanctum::actingAs($this->user);

        $color = 'CHK-OLD-' . uniqid();
        $code = 'CHK-CODE-OLD';

        ScaleMeasurement::create([
            'legacy_source' => 'WEB_WEIGH',
            'legacy_id' => random_int(1, 999999),
            'legacy_batch_id' => 'BATCH-CHK-OLD',
            'color' => $color,
            'product_code' => $code,
            'weight' => 10.0,
            'material_type' => 'DYE',
            'measured_at' => Carbon::now()->subDays(30),
        ]);

        $response = $this->getJson('/api/scale-measurements/checker?' . http_build_query([
            'color' => $color,
            'code' => $code,
            'days_back' => 5,
        ]));

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'SUCCESS');
        $response->assertJsonCount(0, 'data');
        $response->assertJsonPath('message', 'KHONG CO DU LIEU');
    }

    public function test_checker_flags_items_weighed_outside_tolerance_as_rejected(): void
    {
        Sanctum::actingAs($this->user);

        $batch = ProductionBatch::create([
            'legacy_batch_id' => 'BATCH-CHK-OVR-' . uniqid(),
            'color' => 'CHK-OVR-COLOR',
            'product_code' => 'CHK-OVR-CODE',
            'machine_id' => $this->machine->id,
            'status' => 'WEIGHED',
        ]);

        $job = WeighingJob::create([
            'production_batch_id' => $batch->id,
            'job_type' => 'DYE',
            'workstation_type' => 'SMALL_SCALE',
            'status' => 'COMPLETED',
        ]);

        Material::firstOrCreate(
            ['code' => 'DYE-OVR'],
            ['name' => 'Dye Override Test', 'type' => 'DYE', 'is_active' => true]
        );

        $item = WeighingJobItem::create([
            'weighing_job_id' => $job->id,
            'material_code' => 'DYE-OVR',
            'planned_weight' => 100,
            'tolerance_minus' => 1,
            'tolerance_plus' => 1,
            'sequence_no' => 1,
            'actual_weight' => 110,
            'status' => 'COMPLETED',
            'override_approved' => true,
            'override_reason' => 'Test override',
        ]);

        ScaleMeasurement::create([
            'legacy_source' => 'WEB_WEIGH',
            'legacy_id' => random_int(1, 999999),
            'legacy_batch_id' => $batch->legacy_batch_id,
            'color' => $batch->color,
            'product_code' => $batch->product_code,
            'weight' => 110,
            'material_type' => 'DYE',
            'measured_at' => Carbon::now(),
            'weighing_job_item_id' => $item->id,
        ]);

        $response = $this->getJson('/api/scale-measurements/checker?' . http_build_query([
            'color' => $batch->color,
            'code' => $batch->product_code,
        ]));

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.0.items');
        // 110g so với mục tiêu 100 ± 1 -> KHÔNG ĐẠT (nhãn suy từ WeighingJobItem::process_status,
        // thay cho cờ override_approved đã bỏ cùng luồng override 2026-07-30).
        $response->assertJsonPath('data.0.items.0.process_status', 'REJECTED');
    }

    public function test_print_slip_creates_browser_print_job_with_item_status(): void
    {
        Sanctum::actingAs($this->user);

        $wsDye = Workstation::where('code', 'WS-DYE')->firstOrFail();

        $batch = ProductionBatch::create([
            'legacy_batch_id' => 'BATCH-SLIP-' . uniqid(),
            'color' => 'SLIP-COLOR',
            'product_code' => 'SLIP-CODE',
            'machine_id' => $this->machine->id,
            'status' => 'WEIGHED',
        ]);

        $job = WeighingJob::create([
            'production_batch_id' => $batch->id,
            'job_type' => 'DYE',
            'workstation_type' => 'SMALL_SCALE',
            'status' => 'IN_PROGRESS',
        ]);

        Material::firstOrCreate(
            ['code' => 'DYE-DONE'],
            ['name' => 'Dye Done Test', 'type' => 'DYE', 'is_active' => true]
        );
        Material::firstOrCreate(
            ['code' => 'DYE-PENDING'],
            ['name' => 'Dye Pending Test', 'type' => 'DYE', 'is_active' => true]
        );

        WeighingJobItem::create([
            'weighing_job_id' => $job->id,
            'material_code' => 'DYE-DONE',
            'planned_weight' => 50,
            'tolerance_minus' => 0.5,
            'tolerance_plus' => 0.5,
            'sequence_no' => 1,
            'actual_weight' => 50.1,
            'status' => 'COMPLETED',
            'override_approved' => false,
        ]);

        WeighingJobItem::create([
            'weighing_job_id' => $job->id,
            'material_code' => 'DYE-PENDING',
            'planned_weight' => 30,
            'tolerance_minus' => 0.5,
            'tolerance_plus' => 0.5,
            'sequence_no' => 2,
            'status' => 'PENDING',
        ]);

        $response = $this->postJson("/api/weighing-jobs/{$job->id}/print-slip", [
            'workstation_code' => $wsDye->code,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'SUCCESS');

        $printJobId = $response->json('data.id');
        $this->assertNotNull($printJobId);

        $printJob = PrintJob::find($printJobId);
        $this->assertNotNull($printJob);
        // In qua trình duyệt (2026-07-30) — PrintJob đánh dấu PRINTED ngay để Local Agent
        // không lấy job PENDING này gửi TSPL xuống máy in vật lý lần nữa.
        $this->assertEquals('PRINTED', $printJob->status);
        $this->assertEquals('BROWSER', $printJob->printer_connection_type);
        $this->assertStringContainsString('DF_WEIGHING_SLIP', $printJob->label_payload);
        $this->assertStringContainsString('<td>COLOR:</td><td>SLIP-COLOR</td>', $printJob->label_payload);

        // Từ 06/08/2026 phiếu dựng 1:1 theo sheet của VBA: cột STATUS in NGUYÊN chữ
        // ACCEPTED/REJECTED đúng `Mod_print_tsc224.GetProcessStatus`, không còn nhãn rút gọn
        // DAT/LECH. So CẢ HÀNG chứ không chỉ ô STATUS: từ nay dòng trống nào cũng mang chữ
        // REJECTED (đúng bản gốc), nên assert một mình '<td>REJECTED</td>' sẽ luôn đúng bất kể
        // dòng dữ liệu ra sao — tức không chứng minh được gì.
        $this->assertStringContainsString(
            '<td></td><td>DYE-DONE</td><td>50.00</td><td>50.10</td><td>ACCEPTED</td>',
            $printJob->label_payload
        );
        $this->assertStringContainsString(
            '<td></td><td>DYE-PENDING</td><td>30.00</td><td></td><td>REJECTED</td>',
            $printJob->label_payload
        );

        $printJob->delete();
    }
}
