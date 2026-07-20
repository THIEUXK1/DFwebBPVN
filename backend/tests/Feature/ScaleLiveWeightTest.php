<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ScaleMeasurement;
use App\Models\Workstation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class ScaleLiveWeightTest extends TestCase
{
    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = User::firstOrCreate(
            ['username' => 'operator_scale'],
            [
                'display_name' => 'Scale Operator',
                'password_hash' => password_hash('op123', PASSWORD_BCRYPT),
                'roles' => ['OPERATOR']
            ]
        );
    }

    /**
     * Test scale live weight streaming via Cache.
     */
    public function test_scale_live_weight_caching_and_streaming(): void
    {
        // Clear potential historical cache
        Cache::forget('scale_live_weight_WS-TEST');

        // 1. Local Agent posts reading
        $response = $this->postJson('/api/devices/readings', [
            'workstation_id' => 'WS-TEST',
            'weight' => 12.345
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'SUCCESS');

        // Verify it was saved to Cache
        $this->assertEquals(12.345, Cache::get('scale_live_weight_WS-TEST'));

        // 2. Web App gets reading
        $response = $this->actingAs($this->operator)
            ->getJson('/api/devices/readings/WS-TEST');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'SUCCESS',
            'workstation_id' => 'WS-TEST',
            'weight' => 12.345
        ]);
    }

    /**
     * PB-2 (đã sửa 2026-07-17): Agent phải gửi được is_stable thật (từ ScaleReader.StableFilter)
     * và Web App phải đọc lại đúng giá trị đó qua GET, thay vì luôn ngầm định false/true.
     * Không gửi is_stable (Agent cũ chưa cập nhật) phải mặc định false — KHÔNG mặc định true,
     * để tránh lặp lại đúng bug hard-code true trước đây.
     */
    public function test_scale_reading_propagates_real_stability_flag(): void
    {
        Cache::forget('scale_live_weight_WS-STABLE');
        Cache::forget('scale_live_weight_stable_WS-STABLE');

        // Agent gửi is_stable=true tường minh
        $this->postJson('/api/devices/readings', [
            'workstation_id' => 'WS-STABLE',
            'weight' => 5.0,
            'is_stable' => true,
        ])->assertStatus(200);

        $response = $this->actingAs($this->operator)->getJson('/api/devices/readings/WS-STABLE');
        $response->assertStatus(200);
        $response->assertJsonPath('is_stable', true);

        // Agent gửi is_stable=false (đang dao động)
        $this->postJson('/api/devices/readings', [
            'workstation_id' => 'WS-STABLE',
            'weight' => 5.2,
            'is_stable' => false,
        ])->assertStatus(200);

        $response2 = $this->actingAs($this->operator)->getJson('/api/devices/readings/WS-STABLE');
        $response2->assertJsonPath('is_stable', false);

        // Agent cũ không gửi is_stable -> mặc định false, KHÔNG mặc định true
        Cache::forget('scale_live_weight_WS-LEGACY');
        Cache::forget('scale_live_weight_stable_WS-LEGACY');
        $this->postJson('/api/devices/readings', [
            'workstation_id' => 'WS-LEGACY',
            'weight' => 3.0,
        ])->assertStatus(200);

        $response3 = $this->actingAs($this->operator)->getJson('/api/devices/readings/WS-LEGACY');
        $response3->assertJsonPath('is_stable', false);
    }

    /**
     * agent.auth (gap #2, Phase E): khi ép buộc enforcement (header X-Enforce-Workstation-Guard,
     * mô phỏng môi trường production nơi middleware LUÔN bật), request KHÔNG có
     * X-Workstation-Token hợp lệ phải bị từ chối 401 — không còn nhận readings vô danh.
     */
    public function test_devices_readings_requires_valid_workstation_token_when_enforced(): void
    {
        Cache::forget('scale_live_weight_WS-SECURE');

        // Không có token nào -> 401
        $this->postJson('/api/devices/readings', [
            'workstation_id' => 'WS-SECURE',
            'weight' => 9.0,
        ], ['X-Enforce-Workstation-Guard' => '1'])->assertStatus(401);

        // Token sai/không tồn tại -> 401
        $this->postJson('/api/devices/readings', [
            'workstation_id' => 'WS-SECURE',
            'weight' => 9.0,
        ], [
            'X-Enforce-Workstation-Guard' => '1',
            'X-Workstation-Token' => 'token-khong-ton-tai',
        ])->assertStatus(401);

        // Token hợp lệ nhưng khai báo workstation_id KHÁC với workstation sở hữu token -> 403
        $plainToken = 'TEST-TOKEN-' . uniqid();
        Workstation::create([
            'code' => 'WS-SECURE',
            'name' => 'Secure Test Station',
            'type' => 'SMALL_SCALE',
            'active' => true,
            'registration_token_hash' => hash('sha256', $plainToken),
        ]);

        $this->postJson('/api/devices/readings', [
            'workstation_id' => 'WS-OTHER-STATION',
            'weight' => 9.0,
        ], [
            'X-Enforce-Workstation-Guard' => '1',
            'X-Workstation-Token' => $plainToken,
        ])->assertStatus(403);

        // Token hợp lệ + workstation_id khớp đúng -> 200, ghi nhận bình thường
        $this->postJson('/api/devices/readings', [
            'workstation_id' => 'WS-SECURE',
            'weight' => 9.0,
        ], [
            'X-Enforce-Workstation-Guard' => '1',
            'X-Workstation-Token' => $plainToken,
        ])->assertStatus(200);

        $this->assertEquals(9.0, Cache::get('scale_live_weight_WS-SECURE'));
    }

    /**
     * Test storing completed scale measurements.
     */
    public function test_store_completed_scale_measurement(): void
    {
        $payload = [
            'legacy_batch_id' => 'BATCH-TEST-SCALE',
            'color' => 'A+110293',
            'product_code' => 'T7400',
            'machine_code' => 'VD01',
            'dye_code' => 'Y1008A',
            'weight' => 6.5432,
            'process_code' => 'P',
            'material_type' => 'DYE',
        ];

        // Post completed measurement
        $response = $this->actingAs($this->operator)
            ->postJson('/api/scale-measurements', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'SUCCESS');
        
        $id = $response->json('data.id');
        $this->assertNotNull($id);

        // Verify it exists in PostgreSQL database
        $measurement = ScaleMeasurement::find($id);
        $this->assertNotNull($measurement);
        $this->assertEquals('BATCH-TEST-SCALE', $measurement->legacy_batch_id);
        $this->assertEquals('Y1008A', $measurement->dye_code);
        $this->assertEquals(6.5432, (float)$measurement->weight);

        // Clean up
        $measurement->delete();
    }
}
