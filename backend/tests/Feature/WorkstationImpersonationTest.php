<?php
// backend/tests/Feature/WorkstationImpersonationTest.php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Workstation;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\MachineDispatch;
use App\Models\ProductionBatch;
use App\Models\Machine;
use App\Models\Tank;
use App\Models\FeatureFlag;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class WorkstationImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $operatorUser;
    protected $workstation;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['code' => 'ADMIN'], ['name' => 'Admin']);
        $operatorRole = Role::firstOrCreate(['code' => 'OPERATOR'], ['name' => 'Operator']);

        $this->adminUser = User::factory()->create([
            'username' => 'admin_test',
            'display_name' => 'Admin Test'
        ]);
        $this->adminUser->roles()->attach($adminRole->id);

        $this->operatorUser = User::factory()->create([
            'username' => 'operator_test',
            'display_name' => 'Operator Test'
        ]);
        $this->operatorUser->roles()->attach($operatorRole->id);

        // Fetch types from seeders or fallback
        $this->workstation = Workstation::where('code', 'WS-CHEMICAL-01')->first();
        if (!$this->workstation) {
            $this->workstation = Workstation::create([
                'code' => 'WS-CHEMICAL-01',
                'name' => 'Chemical Call 1',
                'workstation_type' => 'CHEMICAL_CALL',
                'type' => 'CHEMICAL_CALL',
                'location' => 'Pilot',
                'active' => true,
            ]);
        }
    }

    /**
     * Test heartbeat endpoint.
     */
    public function test_workstation_heartbeat()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/workstations/{$this->workstation->id}/heartbeat", [
                'agent_version' => 'v2.2.5',
                'active_errors' => ['PLC_COMM_ERROR', 'PRINTER_OFFLINE']
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'SUCCESS');

        $this->assertDatabaseHas('app.operation_clients', [
            'id' => $this->workstation->id,
            'agent_version' => 'v2.2.5'
        ]);

        $this->workstation->refresh();
        $this->assertEquals(['PLC_COMM_ERROR', 'PRINTER_OFFLINE'], $this->workstation->active_errors);
    }

    /**
     * Test suspend and resume workstations.
     */
    public function test_suspend_and_resume_workstation()
    {
        // 1. Suspend by Admin
        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/workstations/{$this->workstation->id}/suspend");

        $response->assertStatus(200);
        $this->assertDatabaseHas('app.operation_clients', [
            'id' => $this->workstation->id,
            'suspended' => true
        ]);

        $this->assertDatabaseHas('app.audit_logs', [
            'user_id' => $this->adminUser->id,
            'action' => 'SUSPEND_WORKSTATION',
            'entity_id' => $this->workstation->id
        ]);

        // 2. Suspend by Operator should fail (restricted to ADMIN only in routes)
        $response = $this->actingAs($this->operatorUser)
            ->postJson("/api/admin/workstations/{$this->workstation->id}/suspend");
        $response->assertStatus(403);

        // 3. Resume by Admin
        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/workstations/{$this->workstation->id}/resume");

        $response->assertStatus(200);
        $this->assertDatabaseHas('app.operation_clients', [
            'id' => $this->workstation->id,
            'suspended' => false
        ]);

        $this->assertDatabaseHas('app.audit_logs', [
            'user_id' => $this->adminUser->id,
            'action' => 'RESUME_WORKSTATION',
            'entity_id' => $this->workstation->id
        ]);
    }

    /**
     * Test remote impersonation audit logging for confirming a dispatch.
     */
    public function test_remote_confirm_dispatch_audit_logging()
    {
        // Enable flags
        FeatureFlag::updateOrCreate(['key' => 'b24_routing_enabled'], ['value' => true]);
        FeatureFlag::updateOrCreate(['key' => 'b24_d1_fix_enabled'], ['value' => true]);
        FeatureFlag::updateOrCreate(['key' => 'manual_routing_review_enabled'], ['value' => true]);

        $machine = Machine::create(['code' => 'VD15', 'name' => 'Machine VD15', 'active' => true]);
        $tank = Tank::create(['code' => 'T101', 'name' => 'Tank 101', 'active' => true]);

        $batch = ProductionBatch::create([
            'legacy_batch_id' => 'B999',
            'color' => 'RED',
            'product_code' => 'P999',
            'machine_id' => $machine->id,
            'tank_id' => $tank->id,
            'level_code' => 'L1',
            'status' => 'NEW',
        ]);

        $dispatch = MachineDispatch::create([
            'legacy_row_no' => 12345,
            'legacy_id' => 99999,
            'batch_id' => $batch->id,
            'queue_state' => 'INPUT',
            'source_table' => 'tbl_ToSend_Dye',
        ]);

        $idempotencyKey = 'key_test_remote_confirm';

        // Admin confirms remotely
        $response = $this->actingAs($this->adminUser)
            ->withHeaders([
                'X-Remote-Operation' => 'true',
                'X-Target-Workstation' => $this->workstation->id,
                'X-Remote-Reason' => 'Test remote action'
            ])
            ->postJson("/api/machine-dispatches/{$dispatch->id}/confirm", [
                'idempotency_key' => $idempotencyKey,
                'workstation_id' => 'WS-CHEMICAL-01',
            ]);

        $response->assertStatus(200);

        // Verify audit log has REMOTE_CONFIRM_DISPATCH
        $this->assertDatabaseHas('app.audit_logs', [
            'user_id' => $this->adminUser->id,
            'action' => 'REMOTE_CONFIRM_DISPATCH',
            'entity_id' => $dispatch->id
        ]);

        $audit = AuditLog::where('user_id', $this->adminUser->id)
            ->where('action', 'REMOTE_CONFIRM_DISPATCH')
            ->firstOrFail();

        $this->assertEquals('Test remote action', $audit->after_data['reason']);
        $this->assertTrue($audit->after_data['remote']);
    }
}
