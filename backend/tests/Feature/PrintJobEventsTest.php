<?php
// backend/tests/Feature/PrintJobEventsTest.php
//
// Kiểm tra 3 việc bổ sung theo yêu cầu 2026-07-18: reprint (REPRINT_REQUESTED), cancel
// (CANCELLED), và bộ lọc station_code/print_status/from/to/q trên history() cho Admin.

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\MachineDispatch;
use App\Models\ProductionBatch;
use App\Models\Machine;
use App\Models\Tank;
use App\Models\User;
use App\Models\FeatureFlag;
use App\Models\PrintJob;
use App\Models\PrintJobEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class PrintJobEventsTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['username' => 'admin_pje', 'display_name' => 'Admin']);

        FeatureFlag::updateOrCreate(['key' => 'b24_routing_enabled'], ['value' => true, 'description' => 'x']);
        FeatureFlag::updateOrCreate(['key' => 'b24_d1_fix_enabled'], ['value' => true, 'description' => 'x']);
        FeatureFlag::updateOrCreate(['key' => 'manual_routing_review_enabled'], ['value' => true, 'description' => 'x']);
    }

    private function makeConfirmedDispatch(string $stationCode = 'WS-PRINT-01'): MachineDispatch
    {
        $machine = Machine::firstOrCreate(['code' => 'VD-PJE-TEST'], ['name' => 'Machine VD-PJE-TEST']);
        $tank = Tank::firstOrCreate(['machine_id' => $machine->id, 'code' => '1A'], ['name' => 'Tank 1A']);

        $batch = ProductionBatch::create([
            'legacy_batch_id' => 'PJE' . time() . rand(1, 999),
            'color' => 'PJECOLOR', 'product_code' => 'PJECODE',
            'machine_id' => $machine->id, 'tank_id' => $tank->id,
            'level_code' => '220', 'status' => 'APPROVED',
        ]);
        $dispatch = MachineDispatch::create([
            'batch_id' => $batch->id, 'queue_state' => 'WAITING',
            'source_table' => 'tbl_ToSend2', 'originating_station_code' => 'WS-ORDER-01',
            'legacy_id' => rand(100, 999), 'legacy_row_no' => rand(1000, 9999),
        ]);

        app(\App\Services\ConfirmDispatchService::class)->confirm($dispatch->id, 'key_pje_' . Str::uuid(), [
            'workstation_id' => $stationCode,
            'printer_address' => 'ZDesigner ZT230',
        ]);

        return $dispatch->fresh();
    }

    public function test_reprint_creates_new_print_job_and_logs_reprint_requested_event()
    {
        $dispatch = $this->makeConfirmedDispatch();
        $firstJob = PrintJob::where('dispatch_id', $dispatch->id)->first();

        $response = $this->actingAs($this->adminUser)->postJson("/api/machine-dispatches/{$dispatch->id}/reprint", [
            'reason' => 'Tem bị rách',
        ]);

        $response->assertStatus(200);
        $newJobId = $response->json('data.id');
        $this->assertNotEquals($firstJob->id, $newJobId, 'In lại phải tạo PrintJob MỚI, không sửa job cũ');

        $this->assertDatabaseHas('app.print_job_events', [
            'print_job_id' => $newJobId,
            'event_type' => 'REPRINT_REQUESTED',
            'error_message' => 'Tem bị rách',
        ]);

        // Job đầu tiên không bị đụng vào
        $this->assertEquals('PENDING', PrintJob::find($firstJob->id)->status);
    }

    public function test_reprint_rejected_when_dispatch_not_yet_printed()
    {
        $machine = Machine::firstOrCreate(['code' => 'VD-PJE-TEST2'], ['name' => 'x']);
        $tank = Tank::firstOrCreate(['machine_id' => $machine->id, 'code' => '1A'], ['name' => 'x']);
        $batch = ProductionBatch::create([
            'legacy_batch_id' => 'PJE2' . time(), 'color' => 'C', 'product_code' => 'P',
            'machine_id' => $machine->id, 'tank_id' => $tank->id, 'status' => 'APPROVED',
        ]);
        $dispatch = MachineDispatch::create([
            'batch_id' => $batch->id, 'queue_state' => 'WAITING', 'source_table' => 'x',
            'legacy_id' => rand(1, 999), 'legacy_row_no' => rand(10000, 99999),
        ]);

        $response = $this->actingAs($this->adminUser)->postJson("/api/machine-dispatches/{$dispatch->id}/reprint", [
            'reason' => 'x',
        ]);

        $response->assertStatus(400);
    }

    public function test_cancel_pending_print_job_logs_cancelled_event()
    {
        $dispatch = $this->makeConfirmedDispatch();
        $job = PrintJob::where('dispatch_id', $dispatch->id)->first();
        $this->assertEquals('PENDING', $job->status);

        $response = $this->actingAs($this->adminUser)->postJson("/api/print-jobs/{$job->id}/cancel", [
            'reason' => 'Nhầm mã hàng',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('CANCELLED', PrintJob::find($job->id)->status);
        $this->assertDatabaseHas('app.print_job_events', [
            'print_job_id' => $job->id,
            'event_type' => 'CANCELLED',
            'error_message' => 'Nhầm mã hàng',
        ]);
    }

    public function test_cancel_rejected_when_job_already_printed()
    {
        $dispatch = $this->makeConfirmedDispatch();
        $job = PrintJob::where('dispatch_id', $dispatch->id)->first();
        $job->status = 'PRINTED';
        $job->save();

        $response = $this->actingAs($this->adminUser)->postJson("/api/print-jobs/{$job->id}/cancel", [
            'reason' => 'x',
        ]);

        $response->assertStatus(422);
        $this->assertEquals('PRINTED', PrintJob::find($job->id)->status);
    }

    public function test_history_nests_print_jobs_and_attempts_tiers()
    {
        $dispatch = $this->makeConfirmedDispatch();

        $response = $this->actingAs($this->adminUser)->getJson('/api/machine-dispatches/history');
        $response->assertStatus(200);

        $row = collect($response->json('data'))->firstWhere('id', $dispatch->id);
        $this->assertNotNull($row);
        $this->assertEquals('WS-ORDER-01', $row['originating_station_code']);
        $this->assertIsArray($row['print_jobs']);
        $this->assertCount(1, $row['print_jobs']);
        $this->assertArrayHasKey('attempts', $row['print_jobs'][0]);
    }

    public function test_history_filters_by_station_code_and_print_status()
    {
        $dispatchA = $this->makeConfirmedDispatch('WS-PRINT-01');
        $dispatchB = $this->makeConfirmedDispatch('WS-CHEM');

        // Lọc theo trạm in (print_jobs.workstation_id)
        $byStation = $this->actingAs($this->adminUser)->getJson('/api/machine-dispatches/history?station_code=WS-CHEM');
        $ids = collect($byStation->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($dispatchB->id));
        $this->assertFalse($ids->contains($dispatchA->id));

        // Lọc theo trạng thái in — set 1 job thành FAILED rồi lọc lại
        $jobA = PrintJob::where('dispatch_id', $dispatchA->id)->first();
        $jobA->status = 'FAILED';
        $jobA->save();

        $byStatus = $this->actingAs($this->adminUser)->getJson('/api/machine-dispatches/history?print_status=FAILED');
        $statusIds = collect($byStatus->json('data'))->pluck('id');
        $this->assertTrue($statusIds->contains($dispatchA->id));
        $this->assertFalse($statusIds->contains($dispatchB->id));
    }

    public function test_history_filters_by_search_query()
    {
        $dispatch = $this->makeConfirmedDispatch();

        $found = $this->actingAs($this->adminUser)->getJson('/api/machine-dispatches/history?q=PJECOLOR');
        $this->assertTrue(collect($found->json('data'))->pluck('id')->contains($dispatch->id));

        $notFound = $this->actingAs($this->adminUser)->getJson('/api/machine-dispatches/history?q=NOPE_NOT_A_MATCH');
        $this->assertFalse(collect($notFound->json('data'))->pluck('id')->contains($dispatch->id));
    }
}
