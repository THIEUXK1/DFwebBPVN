<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Machine;
use App\Models\ProductionBatch;
use App\Models\MaterialTransport;
use App\Models\FeedOperation;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class FeedReadinessTest extends TestCase
{
    private User $operator;
    private User $supervisor;
    private Machine $machine;
    private ProductionBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = User::firstOrCreate(
            ['username' => 'operator_feed'],
            [
                'display_name' => 'Feed Operator',
                'password_hash' => password_hash('op123', PASSWORD_BCRYPT),
            ]
        );
        $roleOp = \App\Models\Role::firstOrCreate(['code' => 'OPERATOR'], ['name' => 'Operator']);
        if (!$this->operator->hasRole('OPERATOR')) {
            $this->operator->roles()->attach($roleOp->id);
        }

        $this->supervisor = User::firstOrCreate(
            ['username' => 'supervisor_feed'],
            [
                'display_name' => 'Dyehouse Supervisor',
                'password_hash' => password_hash('sup123', PASSWORD_BCRYPT),
            ]
        );
        $roleSup = \App\Models\Role::firstOrCreate(['code' => 'SUPERVISOR'], ['name' => 'Supervisor']);
        if (!$this->supervisor->hasRole('SUPERVISOR')) {
            $this->supervisor->roles()->attach($roleSup->id);
        }

        $this->machine = Machine::firstOrCreate(
            ['code' => 'M-FED01'],
            ['name' => 'Feed Test Machine']
        );

        $this->batch = ProductionBatch::create([
            'legacy_batch_id' => 'B-FED-' . uniqid(),
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
     * Test readiness evaluation, verification, and completion workflow.
     */
    public function test_feed_verification_and_completion(): void
    {
        // 1. Initial check: not ready
        $response = $this->actingAs($this->operator)
            ->getJson("/api/feed-operations/readiness/{$this->batch->id}");

        $response->assertStatus(200);
        $this->assertFalse($response->json('data.ready_to_feed'));

        // 2. Prepare conditions: Weigh the batch and accept transport
        $this->batch->status = 'WEIGHED';
        $this->batch->save();

        $transport = MaterialTransport::create([
            'batch_id' => $this->batch->id,
            'workstation_id' => 'WS-01',
            'status' => 'ACCEPTED'
        ]);

        // Re-evaluate readiness -> should be ready
        $response = $this->actingAs($this->operator)
            ->getJson("/api/feed-operations/readiness/{$this->batch->id}");

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.ready_to_feed'));

        // 3. Start feed operation
        $response = $this->actingAs($this->operator)
            ->postJson('/api/feed-operations', [
                'batch_id' => $this->batch->id
            ]);

        $response->assertStatus(201);
        $opId = $response->json('data.id');
        $this->assertNotNull($opId);

        // 4. Try complete feed without verification -> should fail
        $response = $this->actingAs($this->operator)
            ->postJson("/api/feed-operations/{$opId}/complete");

        $response->assertStatus(400);
        $this->assertStringContainsString('Chưa đủ điều kiện', $response->json('message'));

        // 5. Verify water
        $response = $this->actingAs($this->operator)
            ->postJson("/api/feed-operations/{$opId}/verify-water");

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.water_verified'));

        // 6. Verify materials with wrong QR -> should fail
        $response = $this->actingAs($this->operator)
            ->postJson("/api/feed-operations/{$opId}/verify-materials", [
                'scan_data' => 'LOT:WRONG-BATCH-CODE'
            ]);

        $response->assertStatus(400);

        // Verify materials with correct QR -> should succeed
        $response = $this->actingAs($this->operator)
            ->postJson("/api/feed-operations/{$opId}/verify-materials", [
                'scan_data' => "LOT:{$this->batch->legacy_batch_id}"
            ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.materials_verified'));

        // 7. Complete feed -> should succeed, batch status changes to DONE
        $response = $this->actingAs($this->operator)
            ->postJson("/api/feed-operations/{$opId}/complete");

        $response->assertStatus(200);
        $this->assertNotNull($response->json('data.completed_at'));

        $this->batch->refresh();
        $this->assertEquals('DONE', $this->batch->status);

        // Clean up
        $transport->delete();
        FeedOperation::find($opId)->delete();
    }

    /**
     * Test supervisor override flow.
     */
    public function test_supervisor_override_flow(): void
    {
        $op = FeedOperation::create([
            'batch_id' => $this->batch->id,
            'operator_id' => $this->operator->id,
            'started_at' => Carbon::now()
        ]);

        // 1. Try to override as operator -> should be forbidden 403
        $response = $this->actingAs($this->operator)
            ->postJson("/api/feed-operations/{$op->id}/override", [
                'override_reason' => 'Bỏ qua định mức nước do van hỏng'
            ]);

        $response->assertStatus(403);

        // 2. Override as supervisor -> should succeed 200
        $response = $this->actingAs($this->supervisor)
            ->postJson("/api/feed-operations/{$op->id}/override", [
                'override_reason' => 'Bỏ qua định mức nước do van hỏng'
            ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.override_approved'));
        $this->assertEquals($this->supervisor->id, $response->json('data.override_approved_by'));

        // Verify audit log exists
        $log = AuditLog::where('action', 'FEED_OVERRIDE_APPROVED')
            ->where('user_id', $this->supervisor->id)
            ->first();
        $this->assertNotNull($log);

        // 3. Complete feed using override -> should succeed directly
        $response = $this->actingAs($this->operator)
            ->postJson("/api/feed-operations/{$op->id}/complete");

        $response->assertStatus(200);
        
        $this->batch->refresh();
        $this->assertEquals('DONE', $this->batch->status);

        // Clean up
        $op->delete();
        if ($log) $log->delete();
    }
}
