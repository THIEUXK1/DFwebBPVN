<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Machine;
use App\Models\ProductionBatch;
use App\Models\Material;
use App\Models\WeighingJob;
use App\Models\WeighingJobItem;
use App\Models\FeedOperation;
use App\Models\Problem;
use App\Models\Cause;
use App\Models\TroubleshootingCase;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportsTest extends TestCase
{
    use DatabaseTransactions;

    private User $operator;
    private User $supervisor;
    private Machine $machine;
    private ProductionBatch $batch;
    private Material $material;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = $this->makeUser('report_operator');
        $this->operator->roles()->attach(Role::firstOrCreate(['code' => 'OPERATOR'], ['name' => 'Operator'])->id);

        $this->supervisor = $this->makeUser('report_supervisor');
        $this->supervisor->roles()->attach(Role::firstOrCreate(['code' => 'SUPERVISOR'], ['name' => 'Supervisor'])->id);

        $this->material = Material::create([
            'code' => 'DYE-REP-01',
            'name' => 'Report Test Dye',
            'type' => 'DYE',
            'is_active' => true,
        ]);

        $this->machine = Machine::create([
            'code' => 'VD-REP',
            'name' => 'Report Test Machine',
            'is_active' => true,
        ]);

        $this->batch = ProductionBatch::create([
            'legacy_batch_id' => 'B-REP-001',
            'color' => 'REP-COLOR',
            'product_code' => 'P-REP',
            'machine_id' => $this->machine->id,
            'cloth_weight' => 150.5,
            'status' => 'NEW',
        ]);
    }

    private function makeUser(string $username): User
    {
        $user = new User();
        $user->id = (string) Str::uuid();
        $user->username = $username;
        $user->display_name = $username;
        $user->password_hash = password_hash('password', PASSWORD_BCRYPT);
        $user->save();
        return $user;
    }

    private function makeCompletedWeighingItem(float $planned, float $actual, float $toleranceMinus, float $tolerancePlus, bool $override, ?Carbon $completedAt = null): WeighingJobItem
    {
        $job = WeighingJob::create([
            'production_batch_id' => $this->batch->id,
            'job_type' => 'DYE',
            'workstation_type' => 'DYE_WEIGHING',
            'sequence_no' => 1,
            'status' => 'COMPLETED',
            'completed_at' => $completedAt ?? now(),
        ]);

        return WeighingJobItem::create([
            'weighing_job_id' => $job->id,
            'material_code' => $this->material->code,
            'planned_weight' => $planned,
            'tolerance_minus' => $toleranceMinus,
            'tolerance_plus' => $tolerancePlus,
            'sequence_no' => 1,
            'actual_weight' => $actual,
            'status' => 'COMPLETED',
            'completed_at' => $completedAt ?? now(),
            'override_approved' => $override,
            'override_reason' => $override ? 'Sai số máy cân do rung động, đã kiểm tra lại' : null,
            'override_by' => $override ? $this->supervisor->id : null,
        ]);
    }

    public function test_dye_consumption_report_returns_planned_vs_actual_totals(): void
    {
        $this->makeCompletedWeighingItem(100.0, 102.0, 5.0, 5.0, false);
        $this->makeCompletedWeighingItem(50.0, 49.0, 5.0, 5.0, false);

        $response = $this->actingAs($this->operator)
            ->getJson('/api/reports/dye-consumption?from=' . now()->subDay()->toDateString() . '&to=' . now()->addDay()->toDateString());

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'SUCCESS');

        $rows = $response->json('data.rows');
        $this->assertCount(1, $rows);
        $this->assertEquals('DYE-REP-01', $rows[0]['material_code']);
        $this->assertEquals(150.0, $rows[0]['planned_total']);
        $this->assertEquals(151.0, $rows[0]['actual_total']);
        $this->assertEquals(1.0, $rows[0]['variance']);

        $this->assertEquals(150.0, $response->json('data.totals.planned_total'));
        $this->assertEquals(151.0, $response->json('data.totals.actual_total'));
    }

    public function test_tolerance_stats_report_computes_override_rate(): void
    {
        // In-tolerance, no override
        $this->makeCompletedWeighingItem(100.0, 101.0, 5.0, 5.0, false);
        // Out of band but approved via override
        $this->makeCompletedWeighingItem(100.0, 120.0, 5.0, 5.0, true);

        $response = $this->actingAs($this->operator)
            ->getJson('/api/reports/tolerance-stats?from=' . now()->subDay()->toDateString() . '&to=' . now()->addDay()->toDateString());

        $response->assertStatus(200);

        $summary = $response->json('data.summary');
        $this->assertEquals(2, $summary['total_weighed']);
        $this->assertEquals(1, $summary['total_override']);
        $this->assertEquals(50.0, $summary['override_rate_pct']);

        $byMaterial = $response->json('data.by_material');
        $this->assertEquals('DYE-REP-01', $byMaterial[0]['material_code']);
        $this->assertEquals(1, $byMaterial[0]['override_count']);
    }

    public function test_tolerance_stats_counts_pending_out_of_tolerance_items(): void
    {
        $job = WeighingJob::create([
            'production_batch_id' => $this->batch->id,
            'job_type' => 'DYE',
            'workstation_type' => 'DYE_WEIGHING',
            'sequence_no' => 1,
            'status' => 'IN_PROGRESS',
        ]);

        WeighingJobItem::create([
            'weighing_job_id' => $job->id,
            'material_code' => $this->material->code,
            'planned_weight' => 100.0,
            'tolerance_minus' => 5.0,
            'tolerance_plus' => 5.0,
            'sequence_no' => 1,
            'status' => 'OUT_OF_TOLERANCE',
        ]);

        $response = $this->actingAs($this->operator)->getJson('/api/reports/tolerance-stats');
        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.summary.pending_resolution_count'));
    }

    public function test_weigh_item_persists_override_and_writes_audit_log(): void
    {
        $job = WeighingJob::create([
            'production_batch_id' => $this->batch->id,
            'job_type' => 'DYE',
            'workstation_type' => 'DYE_WEIGHING',
            'sequence_no' => 1,
            'status' => 'IN_PROGRESS',
        ]);

        $item = WeighingJobItem::create([
            'weighing_job_id' => $job->id,
            'material_code' => $this->material->code,
            'planned_weight' => 100.0,
            'tolerance_minus' => 5.0,
            'tolerance_plus' => 5.0,
            'sequence_no' => 1,
            'status' => 'PENDING',
        ]);

        // Operator (non-supervisor) attempting override must be rejected
        $forbidden = $this->actingAs($this->operator)->postJson("/api/weighing-jobs/items/{$item->id}/weigh", [
            'weight' => 130.0,
            'scale_device_id' => 'SCALE-TEST',
            'stable' => true,
            'override_approved' => true,
            'override_reason' => 'Lệch do rung động máy cân',
        ]);
        $forbidden->assertStatus(403);

        // Supervisor can approve the override, reason gets persisted + audited
        $ok = $this->actingAs($this->supervisor)->postJson("/api/weighing-jobs/items/{$item->id}/weigh", [
            'weight' => 130.0,
            'scale_device_id' => 'SCALE-TEST',
            'stable' => true,
            'override_approved' => true,
            'override_reason' => 'Lệch do rung động máy cân',
        ]);
        $ok->assertStatus(200);

        $item->refresh();
        $this->assertTrue($item->override_approved);
        $this->assertEquals('Lệch do rung động máy cân', $item->override_reason);
        $this->assertEquals($this->supervisor->id, $item->override_by);

        $this->assertDatabaseHas('app.audit_logs', [
            'action' => 'WEIGH_TOLERANCE_OVERRIDE',
            'entity_type' => 'WeighingJobItem',
            'entity_id' => $item->id,
        ]);
    }

    public function test_machine_output_report_groups_by_day(): void
    {
        FeedOperation::create([
            'batch_id' => $this->batch->id,
            'operator_id' => $this->operator->id,
            'water_verified' => true,
            'materials_verified' => true,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($this->operator)
            ->getJson('/api/reports/machine-output?group_by=day&from=' . now()->subDay()->toDateString() . '&to=' . now()->addDay()->toDateString());

        $response->assertStatus(200);
        $rows = $response->json('data.rows');
        $this->assertCount(1, $rows);
        $this->assertEquals('VD-REP', $rows[0]['machine_code']);
        $this->assertEquals(1, $rows[0]['batch_count']);
        $this->assertEquals(150.5, $rows[0]['total_cloth_weight']);
    }

    public function test_troubleshooting_pareto_report_computes_cumulative_percentage(): void
    {
        $problem = Problem::create(['id' => 'PRB-REP-01', 'problem_name' => 'Lệch màu vải', 'severity' => 'HIGH', 'is_active' => true]);
        $causeA = Cause::create(['id' => 'CAU-REP-A', 'cause_name' => 'Sai định lượng thuốc nhuộm', 'is_active' => true]);
        $causeB = Cause::create(['id' => 'CAU-REP-B', 'cause_name' => 'Nhiệt độ không ổn định', 'is_active' => true]);

        for ($i = 0; $i < 3; $i++) {
            $case = TroubleshootingCase::create([
                'id' => (string) Str::uuid(),
                'batch_id' => $this->batch->id,
                'status' => 'CLOSED',
                'reporter_id' => $this->operator->id,
                'actual_cause_id' => $causeA->id,
                'resolved_at' => now(),
            ]);
            DB::table('app.case_problems')->insert(['case_id' => $case->id, 'problem_id' => $problem->id]);
        }

        $case = TroubleshootingCase::create([
            'id' => (string) Str::uuid(),
            'batch_id' => $this->batch->id,
            'status' => 'CLOSED',
            'reporter_id' => $this->operator->id,
            'actual_cause_id' => $causeB->id,
            'resolved_at' => now(),
        ]);
        DB::table('app.case_problems')->insert(['case_id' => $case->id, 'problem_id' => $problem->id]);

        $response = $this->actingAs($this->operator)
            ->getJson('/api/reports/troubleshooting-pareto?from=' . now()->subDay()->toDateString() . '&to=' . now()->addDay()->toDateString());

        $response->assertStatus(200);
        $pareto = $response->json('data.pareto_causes');

        $this->assertEquals('CAU-REP-A', $pareto[0]['cause_id']);
        $this->assertEquals(3, $pareto[0]['case_count']);
        $this->assertEquals(75.0, $pareto[0]['pct']);
        $this->assertEquals(75.0, $pareto[0]['cumulative_pct']);
        $this->assertEquals(100.0, $pareto[1]['cumulative_pct']);

        $this->assertEquals(4, $response->json('data.summary.total_cases'));
        $this->assertEquals(4, $response->json('data.summary.resolved_cases'));
        $this->assertEquals(100.0, $response->json('data.summary.resolution_rate_pct'));
    }

    public function test_audit_log_explorer_filters_by_action(): void
    {
        AuditLog::create([
            'user_id' => $this->supervisor->id,
            'action' => 'REPORT_TEST_ACTION',
            'entity_type' => 'Test',
            'entity_id' => 'abc',
            'after_data' => ['foo' => 'bar'],
        ]);

        $response = $this->actingAs($this->operator)
            ->getJson('/api/audit-logs?action=REPORT_TEST_ACTION');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $response->json('data.total'));
        $this->assertEquals('REPORT_TEST_ACTION', $response->json('data.data.0.action'));
    }

    public function test_reports_require_authentication(): void
    {
        $response = $this->getJson('/api/reports/dye-consumption');
        $response->assertStatus(401);
    }

    public function test_dye_consumption_excel_export_downloads_file(): void
    {
        $this->makeCompletedWeighingItem(100.0, 101.0, 5.0, 5.0, false);

        $response = $this->actingAs($this->operator)
            ->get('/api/reports/dye-consumption?format=xlsx&from=' . now()->subDay()->toDateString() . '&to=' . now()->addDay()->toDateString());

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
