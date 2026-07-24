<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Machine;
use App\Models\ProductionBatch;
use App\Models\MaterialTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class MaterialTransferTest extends TestCase
{
    private User $operator;
    private Machine $machine;
    private ProductionBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = User::firstOrCreate(
            ['username' => 'operator_transfer'],
            [
                'display_name' => 'Transfer Operator',
                'password_hash' => password_hash('op123', PASSWORD_BCRYPT),
                'roles' => ['OPERATOR']
            ]
        );

        $this->machine = Machine::firstOrCreate(
            ['code' => 'T5-01'], // starts with T5 -> SLA will be 25 minutes
            ['name' => 'Tank Machine 01']
        );

        $this->batch = ProductionBatch::create([
            'legacy_batch_id' => 'B-TRSF-' . uniqid(),
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
     * Test the entire material transfer process including SLA and QR verification.
     */
    public function test_material_transfer_flow(): void
    {
        // 1. Create transport task
        $response = $this->actingAs($this->operator)
            ->postJson('/api/material-transports', [
                'batch_id' => $this->batch->id,
                'workstation_id' => 'WS-01'
            ]);

        $response->assertStatus(201);
        $transportId = $response->json('data.id');
        $this->assertNotNull($transportId);
        $this->assertEquals(25, $response->json('data.sla_minutes')); // Dynamic SLA verification

        // 2. Start transit
        $response = $this->actingAs($this->operator)
            ->postJson("/api/material-transports/{$transportId}/transit");

        $response->assertStatus(200);
        $this->assertEquals('IN_TRANSIT', $response->json('data.status'));

        // 3. Arrive with mismatching QR data
        $response = $this->actingAs($this->operator)
            ->postJson("/api/material-transports/{$transportId}/arrive", [
                'scan_data' => 'LOT:WRONG-BATCH|COLOR:A+110293|WATER:210L'
            ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('Quét sai nhãn', $response->json('message'));

        // 4. Arrive with SLA breach (Mock by backdating started_at)
        $transport = MaterialTransport::find($transportId);
        $transport->started_at = Carbon::now()->subMinutes(30); // 30 mins elapsed > 25 mins SLA
        $transport->save();

        // Arrive without delay reason -> should fail with SLA_BREACH
        $response = $this->actingAs($this->operator)
            ->postJson("/api/material-transports/{$transportId}/arrive", [
                'scan_data' => "LOT:{$this->batch->legacy_batch_id}|COLOR:A+110293|WATER:210L"
            ]);

        $response->assertStatus(422);
        $this->assertEquals('SLA_BREACH', $response->json('status'));

        // Arrive with delay reason -> should succeed
        $response = $this->actingAs($this->operator)
            ->postJson("/api/material-transports/{$transportId}/arrive", [
                'scan_data' => "LOT:{$this->batch->legacy_batch_id}|COLOR:A+110293|WATER:210L",
                'delay_reason' => 'Thiếu nhân lực vận chuyển'
            ]);

        $response->assertStatus(200);
        $this->assertEquals('ARRIVED_AT_TANK', $response->json('data.status'));
        $this->assertEquals('Thiếu nhân lực vận chuyển', $response->json('data.delay_reason'));

        // Verify batch status updated to WEIGHED
        $this->batch->refresh();
        $this->assertEquals('WEIGHED', $this->batch->status);

        // 5. Accept at tank
        $response = $this->actingAs($this->operator)
            ->postJson("/api/material-transports/{$transportId}/accept", [
                'status' => 'ACCEPTED',
                'notes' => 'Nguyên liệu đạt chuẩn'
            ]);

        $response->assertStatus(200);
        $this->assertEquals('ACCEPTED', $response->json('data.status'));

        // Clean up
        $transport->delete();
    }
}
