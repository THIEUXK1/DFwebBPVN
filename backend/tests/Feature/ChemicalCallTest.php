<?php
// backend/tests/Feature/ChemicalCallTest.php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\MachineChemicalChannel;
use App\Models\ChemicalCallRequest;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class ChemicalCallTest extends TestCase
{
    use RefreshDatabase;

    protected $operatorUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operatorUser = User::factory()->create([
            'username' => 'operator_cc',
            'display_name' => 'Operator'
        ]);
    }

    /**
     * Test chemical call lifecycle (Order -> Acknowledge -> Complete).
     */
    public function test_chemical_call_lifecycle()
    {
        $machine = Machine::create(['code' => 'VD10', 'name' => 'Machine VD10']);
        $channel = MachineChemicalChannel::create([
            'machine_id' => $machine->id,
            'channel_number' => 1,
            'chemical_code' => 'CHEM_01',
            'is_active' => true,
        ]);

        $idempotencyKey = 'cc_' . Str::uuid();

        // 1. Order Chemical Call
        $response = $this->actingAs($this->operatorUser)
            ->postJson('/api/chemical-call-requests', [
                'channel_id' => $channel->id,
                'idempotency_key' => $idempotencyKey,
            ]);

        $response->assertStatus(201);
        $requestId = $response->json('id');
        
        $this->assertDatabaseHas('app.chemical_call_requests', [
            'id' => $requestId,
            'status' => 'ORDERED',
            'idempotency_key' => $idempotencyKey,
        ]);

        $this->assertDatabaseHas('app.chemical_call_request_events', [
            'request_id' => $requestId,
            'event_type' => 'CHEMICAL_CALL_ORDERED',
        ]);

        // Try ordering same channel again (should block, 409 Conflict)
        $responseDuplicate = $this->actingAs($this->operatorUser)
            ->postJson('/api/chemical-call-requests', [
                'channel_id' => $channel->id,
                'idempotency_key' => 'cc_' . Str::uuid(),
            ]);
        $responseDuplicate->assertStatus(409);

        // 2. Acknowledge Request
        $responseAck = $this->actingAs($this->operatorUser)
            ->patchJson("/api/chemical-call-requests/{$requestId}/acknowledge");
        
        $responseAck->assertStatus(200);
        $this->assertEquals('ACKNOWLEDGED', $responseAck->json('status'));

        // 3. Complete Request
        $responseComplete = $this->actingAs($this->operatorUser)
            ->patchJson("/api/chemical-call-requests/{$requestId}/complete");
        
        $responseComplete->assertStatus(200);
        $this->assertEquals('DONE', $responseComplete->json('status'));

        // 4. Reset Request (DONE -> RESET)
        $responseReset = $this->actingAs($this->operatorUser)
            ->patchJson("/api/chemical-call-requests/{$requestId}/reset");
            
        $responseReset->assertStatus(200);
        $this->assertEquals('RESET', $responseReset->json('status'));
        
        $this->assertDatabaseHas('app.chemical_call_requests', [
            'id' => $requestId,
            'status' => 'RESET'
        ]);
        
        $this->assertDatabaseHas('app.chemical_call_request_events', [
            'request_id' => $requestId,
            'event_type' => 'CHEMICAL_CALL_RESET',
        ]);

        // Verify channel is free for a new order now
        $responseNewOrder = $this->actingAs($this->operatorUser)
            ->postJson('/api/chemical-call-requests', [
                'channel_id' => $channel->id,
                'idempotency_key' => 'cc_' . Str::uuid(),
            ]);
        $responseNewOrder->assertStatus(201);
    }
}
