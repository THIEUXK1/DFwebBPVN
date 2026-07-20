<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Machine;
use App\Models\Tank;
use App\Models\ProductionBatch;
use App\Models\MachineDispatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class MachineDispatchConcurrencyTest extends TestCase
{
    // We don't use RefreshDatabase here because we have historical and seeded data 
    // that we want to keep, but we can wrap tests in transactions or clean up manually.
    
    private User $operatorA;
    private User $operatorB;
    private Machine $machine;
    private ProductionBatch $batch;
    private MachineDispatch $dispatch;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Operators
        $this->operatorA = User::firstOrCreate(
            ['username' => 'operator_a'],
            [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'display_name' => 'Operator A',
                'password_hash' => password_hash('op123', PASSWORD_BCRYPT),
                'roles' => ['OPERATOR']
            ]
        );

        $this->operatorB = User::firstOrCreate(
            ['username' => 'operator_b'],
            [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'display_name' => 'Operator B',
                'password_hash' => password_hash('op123', PASSWORD_BCRYPT),
                'roles' => ['OPERATOR']
            ]
        );

        // Create Machine
        $this->machine = Machine::firstOrCreate(
            ['code' => 'TEST-M01'],
            ['name' => 'Test Machine 01']
        );

        // Create Batch
        $this->batch = ProductionBatch::create([
            'legacy_batch_id' => 'BATCH-' . uniqid(),
            'color' => 'RED-101',
            'product_code' => 'PROD-101',
            'machine_id' => $this->machine->id,
            'status' => 'NEW'
        ]);
        $this->batch->refresh();

        // Create Dispatch
        $this->dispatch = MachineDispatch::create([
            'legacy_row_no' => rand(100000, 999999),
            'legacy_id' => rand(1000, 9999),
            'batch_id' => $this->batch->id,
            'is_sent' => false,
            'queue_state' => 'INPUT',
            'source_table' => 'tbl_Waiting'
        ]);
        $this->dispatch->refresh();
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->dispatch->delete();
        $this->batch->delete();
        
        parent::tearDown();
    }

    /**
     * Test lock claim, lock conflict, lock override, and send constraints.
     */
    public function test_machine_dispatch_locking_concurrency(): void
    {
        // 1. Operator A claims lock
        $response = $this->actingAs($this->operatorA)
            ->postJson("/api/machine-dispatches/{$this->dispatch->id}/claim");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'SUCCESS');
        
        $this->dispatch->refresh();
        $this->assertEquals($this->operatorA->id, $this->dispatch->locked_by);
        $this->assertNotNull($this->dispatch->locked_at);

        // 2. Operator B tries to claim lock -> Conflict
        $response = $this->actingAs($this->operatorB)
            ->postJson("/api/machine-dispatches/{$this->dispatch->id}/claim");

        $response->assertStatus(409);
        $response->assertJsonPath('status', 'ERROR');

        // 3. Travel 6 minutes in the future to simulate lock expiry
        $this->travel(6)->minutes();

        // 4. Operator B tries to claim lock -> Success (Lock Override)
        $response = $this->actingAs($this->operatorB)
            ->postJson("/api/machine-dispatches/{$this->dispatch->id}/claim");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'SUCCESS');
        
        $this->dispatch->refresh();
        $this->assertEquals($this->operatorB->id, $this->dispatch->locked_by);

        // 5. Operator A tries to send to machine -> Forbidden (Operator A lost the lock)
        $response = $this->actingAs($this->operatorA)
            ->postJson("/api/machine-dispatches/{$this->dispatch->id}/send");

        $response->assertStatus(403);

        // 6. Operator B sends to machine -> Success
        $response = $this->actingAs($this->operatorB)
            ->postJson("/api/machine-dispatches/{$this->dispatch->id}/send");

        $response->assertStatus(200);
        
        $this->dispatch->refresh();
        $this->assertEquals('SENT', $this->dispatch->queue_state);
        $this->assertTrue($this->dispatch->is_sent);
        $this->assertNull($this->dispatch->locked_by);
        
        // Restore time
        $this->travelBack();
    }
}
