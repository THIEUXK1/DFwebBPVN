<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Machine;
use App\Models\ProductionBatch;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\RealtimeEvent;
use App\Services\RealtimeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;

class RealtimeDashboardTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed basic rules
        $this->artisan('db:seed', ['--class' => 'AlertRulesSeeder']);

        // Create user manually to match database custom schema
        $user = new User();
        $user->id = (string) \Illuminate\Support\Str::uuid();
        $user->username = 'realtime_admin';
        $user->display_name = 'Realtime System Administrator';
        $user->password_hash = password_hash('admin123', PASSWORD_BCRYPT);
        $user->save();

        $this->user = $user;
    }

    /**
     * Test outbox event writing when ProductionBatch status updates.
     */
    public function test_production_batch_status_triggers_outbox_event()
    {
        Sanctum::actingAs($this->user);

        $machine = Machine::create(['code' => 'VD99', 'name' => 'Machine VD99']);
        $batch = ProductionBatch::create([
            'legacy_batch_id' => 'TEST-BATCH-01',
            'color' => 'RED-99',
            'product_code' => 'COTTON-01',
            'machine_id' => $machine->id,
            'status' => 'NEW'
        ]);

        // Event: batch.created should be in the outbox
        $this->assertDatabaseHas('app.realtime_events', [
            'event_type' => 'batch.created',
            'entity_type' => 'ProductionBatch',
            'entity_id' => $batch->id,
        ]);

        // Update status
        $response = $this->putJson("/api/production-batches/{$batch->id}/status", [
            'status' => 'READY_TO_WEIGH'
        ]);

        $response->assertStatus(200);

        // Event: batch.status_changed should be in the outbox
        $this->assertDatabaseHas('app.realtime_events', [
            'event_type' => 'batch.status_changed',
            'entity_type' => 'ProductionBatch',
            'entity_id' => $batch->id,
        ]);
    }

    /**
     * Test Rule Engine triggers alerts for overdue items.
     */
    public function test_rule_engine_detects_overdue_batches()
    {
        $machine = Machine::create(['code' => 'VD98', 'name' => 'Machine VD98']);
        
        // Create batch created 2 hours ago (exceeds start delay threshold of 30 mins)
        $batch = ProductionBatch::create([
            'legacy_batch_id' => 'OVERDUE-BATCH-01',
            'color' => 'BLUE-98',
            'product_code' => 'POLY-02',
            'machine_id' => $machine->id,
            'status' => 'READY_TO_WEIGH',
        ]);
        
        // Force backdate created_at to trigger alarm
        \DB::table('app.production_batches')
            ->where('id', $batch->id)
            ->update(['created_at' => now()->subHours(2)]);

        // Run Rule Engine check
        $triggered = RealtimeService::checkAlertRules();

        $this->assertNotEmpty($triggered);
        $this->assertDatabaseHas('app.alerts', [
            'rule_code' => 'WEIGH_START_DELAY',
            'batch_id' => $batch->id,
            'status' => 'OPEN',
        ]);
    }

    /**
     * Test Acknowledge and Resolve workflow of Alerts via API.
     */
    public function test_alert_acknowledge_and_resolve_flow()
    {
        Sanctum::actingAs($this->user);

        $machine = Machine::create(['code' => 'VD97', 'name' => 'Machine VD97']);
        $batch = ProductionBatch::create([
            'legacy_batch_id' => 'ALERT-BATCH-01',
            'color' => 'GREEN-97',
            'product_code' => 'SILK-03',
            'machine_id' => $machine->id,
            'status' => 'NEW',
        ]);

        // Trigger alert manually
        $alert = RealtimeService::triggerAlert(
            'WEIGH_START_DELAY',
            'Cảnh báo trễ hạn cân thử nghiệm.',
            $batch->id,
            $machine->id
        );

        $this->assertNotNull($alert);
        $this->assertEquals('OPEN', $alert->status);

        // 1. Acknowledge alert
        $ackRes = $this->postJson("/api/alerts/{$alert->id}/handle", [
            'action' => 'ACKNOWLEDGE',
            'notes' => 'Tôi đang kiểm tra lại trạm cân.'
        ]);

        $ackRes->assertStatus(200);
        $this->assertDatabaseHas('app.alerts', [
            'id' => $alert->id,
            'status' => 'ACKNOWLEDGED',
            'assigned_to' => $this->user->id,
        ]);

        // 2. Resolve alert
        $resRes = $this->postJson("/api/alerts/{$alert->id}/handle", [
            'action' => 'RESOLVE',
            'notes' => 'Đã mở khóa cân và bắt đầu nạp.'
        ]);

        $resRes->assertStatus(200);
        $this->assertDatabaseHas('app.alerts', [
            'id' => $alert->id,
            'status' => 'RESOLVED',
            'resolved_by' => $this->user->id,
        ]);
    }

    /**
     * Test dashboard snapshot endpoints.
     */
    public function test_dashboard_snapshots_return_correct_structures()
    {
        Sanctum::actingAs($this->user);

        // Test overview snapshot
        $overviewRes = $this->getJson('/api/dashboard/overview');
        $overviewRes->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'overview',
                    'open_alerts_count'
                ]
            ]);

        // Test weighing snapshot
        $weighingRes = $this->getJson('/api/dashboard/weighing');
        $weighingRes->assertStatus(200)
            ->assertJsonStructure(['status', 'data']);

        // Test management KPIs snapshot
        $mgmtRes = $this->getJson('/api/dashboard/management');
        $mgmtRes->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'completed_today',
                    'active_batches',
                    'machines_running',
                    'machines_waiting',
                    'overdue_weighing_count',
                    'average_transport_minutes',
                ]
            ]);
    }
}
