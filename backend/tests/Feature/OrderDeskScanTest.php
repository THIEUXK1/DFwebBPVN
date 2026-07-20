<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Machine;
use App\Models\ProductionBatch;
use App\Models\Workstation;
use App\Models\WeighingJob;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;

/**
 * WS-005: the Order Desk (ORDER_DESK) scan is a read-only preview + explicit confirm, distinct
 * from handleOrderScan at weighing stations (which creates a weighing job immediately on scan).
 */
class OrderDeskScanTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private Machine $machine;
    private ProductionBatch $batch;
    private Workstation $orderDesk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'WorkstationsSeeder']);

        $user = new User();
        $user->id = (string) Str::uuid();
        $user->username = 'order_desk_tester';
        $user->display_name = 'Order Desk Tester';
        $user->password_hash = password_hash('password', PASSWORD_BCRYPT);
        $user->save();
        $user->roles()->attach(Role::firstOrCreate(['code' => 'OPERATOR'], ['name' => 'Operator'])->id);
        $this->user = $user;

        $this->machine = Machine::create(['code' => 'VD-ORDDESK', 'name' => 'Order Desk Test Machine', 'is_active' => true]);

        $this->batch = ProductionBatch::create([
            'legacy_batch_id' => 'B-ORDDESK-001',
            'color' => 'ORD-COLOR',
            'product_code' => 'P-ORD',
            'machine_id' => $this->machine->id,
            'cloth_weight' => 100,
            'status' => 'NEW',
        ]);

        $this->orderDesk = Workstation::where('code', 'ORD-01')->firstOrFail();
    }

    public function test_scanning_order_at_order_desk_previews_without_mutating_or_creating_a_weighing_job(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/scanner/scan', [
            'qr_token' => "DF:ORDER:{$this->batch->id}",
            'workstation_code' => $this->orderDesk->code,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.batch.legacy_batch_id', 'B-ORDDESK-001');
        $response->assertJsonPath('data.already_acknowledged', false);

        $this->batch->refresh();
        $this->assertEquals('NEW', $this->batch->status);
        $this->assertEquals(0, WeighingJob::where('production_batch_id', $this->batch->id)->count());
    }

    public function test_acknowledge_order_transitions_new_to_ready_to_weigh_and_writes_audit_log(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/scanner/acknowledge-order', [
            'batch_id' => $this->batch->id,
            'workstation_code' => $this->orderDesk->code,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'READY_TO_WEIGH');

        $this->batch->refresh();
        $this->assertEquals('READY_TO_WEIGH', $this->batch->status);

        $this->assertDatabaseHas('app.audit_logs', [
            'action' => 'ORDER_RECEIVED_ACK',
            'entity_id' => $this->batch->id,
        ]);
    }

    public function test_acknowledging_twice_is_idempotent(): void
    {
        $this->actingAs($this->user)->postJson('/api/scanner/acknowledge-order', [
            'batch_id' => $this->batch->id,
            'workstation_code' => $this->orderDesk->code,
        ])->assertStatus(200);

        $second = $this->actingAs($this->user)->postJson('/api/scanner/acknowledge-order', [
            'batch_id' => $this->batch->id,
            'workstation_code' => $this->orderDesk->code,
        ]);

        $second->assertStatus(200);
        $second->assertJsonPath('data.status', 'READY_TO_WEIGH');

        $ackCount = \App\Models\AuditLog::where('action', 'ORDER_RECEIVED_ACK')
            ->where('entity_id', $this->batch->id)
            ->count();
        $this->assertEquals(1, $ackCount);
    }

    public function test_acknowledge_order_rejected_at_wrong_workstation_type(): void
    {
        $wsDye = Workstation::where('code', 'WS-DYE')->firstOrFail();

        $response = $this->actingAs($this->user)->postJson('/api/scanner/acknowledge-order', [
            'batch_id' => $this->batch->id,
            'workstation_code' => $wsDye->code,
        ]);

        $response->assertStatus(403);

        $this->batch->refresh();
        $this->assertEquals('NEW', $this->batch->status);
    }

    public function test_preview_reflects_already_acknowledged_state(): void
    {
        $this->batch->status = 'READY_TO_WEIGH';
        $this->batch->save();

        $response = $this->actingAs($this->user)->postJson('/api/scanner/scan', [
            'qr_token' => "DF:ORDER:{$this->batch->id}",
            'workstation_code' => $this->orderDesk->code,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.already_acknowledged', true);
    }
}
