<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Machine;
use App\Models\ProductionBatch;
use App\Models\WeighingJob;
use App\Models\WeighingJobItem;
use App\Models\Material;
use App\Models\MaterialLabel;
use App\Models\Workstation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;

/**
 * WS-007: standalone Print Station — scan/search an existing material label independently of the
 * weighing station session that created it, and reprint it with an audited reason.
 */
class PrintStationTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private MaterialLabel $label;
    private Workstation $printStation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'WorkstationsSeeder']);

        $user = new User();
        $user->id = (string) Str::uuid();
        $user->username = 'print_station_tester';
        $user->display_name = 'Print Station Tester';
        $user->password_hash = password_hash('password', PASSWORD_BCRYPT);
        $user->save();
        $user->roles()->attach(Role::firstOrCreate(['code' => 'OPERATOR'], ['name' => 'Operator'])->id);
        $this->user = $user;

        $machine = Machine::create(['code' => 'VD-PRINTST', 'name' => 'Print Station Test Machine', 'is_active' => true]);
        $material = Material::create(['code' => 'DYE-PRINTST-01', 'name' => 'Print Station Test Dye', 'type' => 'DYE', 'is_active' => true]);

        $batch = ProductionBatch::create([
            'legacy_batch_id' => 'B-PRINTST-001',
            'color' => 'PRINTST-COLOR',
            'product_code' => 'P-PRINTST',
            'machine_id' => $machine->id,
            'cloth_weight' => 100,
            'status' => 'WEIGHED',
        ]);

        $job = WeighingJob::create([
            'production_batch_id' => $batch->id,
            'job_type' => 'DYE',
            'workstation_type' => 'DYE_WEIGHING',
            'sequence_no' => 1,
            'status' => 'COMPLETED',
            'completed_at' => now(),
        ]);

        WeighingJobItem::create([
            'weighing_job_id' => $job->id,
            'material_code' => $material->code,
            'planned_weight' => 100.0,
            'tolerance_minus' => 5.0,
            'tolerance_plus' => 5.0,
            'sequence_no' => 1,
            'actual_weight' => 100.0,
            'status' => 'COMPLETED',
            'completed_at' => now(),
        ]);

        $this->label = MaterialLabel::create([
            'production_batch_id' => $batch->id,
            'weighing_job_id' => $job->id,
            'material_type' => 'DYE',
            'weight' => 100.0,
            'reprint_count' => 0,
        ]);

        $this->printStation = Workstation::where('code', 'PRINT-01')->firstOrFail();
    }

    public function test_show_label_returns_full_context_for_scanning(): void
    {
        $response = $this->actingAs($this->user)->getJson("/api/material-labels/{$this->label->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.batch.legacy_batch_id', 'B-PRINTST-001');
        $response->assertJsonPath('data.job.items.0.material_code', 'DYE-PRINTST-01');
    }

    public function test_search_labels_by_batch_code(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/material-labels/search?q=B-PRINTST');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($this->label->id));
    }

    public function test_search_labels_no_match_returns_empty(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/material-labels/search?q=NO-SUCH-BATCH');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_reprint_from_print_station_increments_count_and_audits(): void
    {
        $response = $this->actingAs($this->user)->postJson("/api/material-labels/{$this->label->id}/reprint", [
            'reason' => 'Tem gốc bị rách khi vận chuyển',
            'workstation_code' => $this->printStation->code,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.reprint_count', 1);

        $this->assertDatabaseHas('app.audit_logs', [
            'action' => 'REPRINT_MATERIAL_LABEL',
            'entity_id' => $this->label->id,
        ]);

        $this->label->refresh();
        $this->assertEquals(1, $this->label->reprint_count);
        $this->assertEquals('Tem gốc bị rách khi vận chuyển', $this->label->reprint_reason);
    }

    public function test_reprint_requires_a_reason(): void
    {
        $response = $this->actingAs($this->user)->postJson("/api/material-labels/{$this->label->id}/reprint", [
            'reason' => 'ab',
            'workstation_code' => $this->printStation->code,
        ]);

        $response->assertStatus(422);
    }
}
