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
     * Số cân cũ còn nằm trong cache (TTL 15s) phải phân biệt được với số vừa đọc xong. Thiếu
     * thông tin này thì /weighing-station-v2 chốt BÌ tự động vào một số đọc từ nhiều giây
     * trước, và màn hình hiển thị số đứng yên như thể cân vẫn đang chạy khi Agent đã chết.
     */
    public function test_get_reading_reports_age_of_last_push(): void
    {
        Cache::forget('scale_live_weight_WS-AGE');
        Cache::forget('scale_live_weight_stable_WS-AGE');
        Cache::forget('scale_live_weight_timestamp_WS-AGE');

        $this->postJson('/api/devices/readings', [
            'workstation_id' => 'WS-AGE',
            'weight' => 7.5,
            'is_stable' => true,
        ])->assertStatus(200);

        $fresh = $this->actingAs($this->operator)->getJson('/api/devices/readings/WS-AGE');
        $fresh->assertStatus(200);
        $fresh->assertJsonPath('has_reading', true);
        $this->assertLessThan(1500, $fresh->json('age_ms'), 'Số vừa đẩy lên phải được coi là còn tươi');

        // Mô phỏng Agent im lặng 10 giây: giá trị cân vẫn trong cache (TTL 15s) nhưng đã cũ.
        Cache::put('scale_live_weight_timestamp_WS-AGE', microtime(true) - 10, 3600);

        $stale = $this->actingAs($this->operator)->getJson('/api/devices/readings/WS-AGE');
        $stale->assertJsonPath('has_reading', true);
        $this->assertGreaterThan(9000, $stale->json('age_ms'));
    }

    /**
     * Cache hết hạn hoàn toàn: `weight` vẫn trả 0.0 cho tương thích ngược với V1, nhưng
     * `has_reading` phải là false — nếu không, "mất tín hiệu cân" và "cân đang rỗng" hiển thị
     * y hệt nhau (đúng lớp lỗi TV6 đã vá ở Agent nhưng backend lại tự tái tạo).
     */
    public function test_get_reading_flags_missing_reading_instead_of_faking_zero(): void
    {
        Cache::forget('scale_live_weight_WS-GONE');
        Cache::forget('scale_live_weight_stable_WS-GONE');
        Cache::forget('scale_live_weight_timestamp_WS-GONE');

        $response = $this->actingAs($this->operator)->getJson('/api/devices/readings/WS-GONE');

        $response->assertStatus(200);
        $response->assertJsonPath('weight', 0.0);
        $response->assertJsonPath('has_reading', false);
        $response->assertJsonPath('age_ms', null);
    }

    /**
     * Agent đẩy số cân theo MÃ trạm ("WS-WEIGH-SCALE"), còn frontend gọi
     * /api/devices/readings/{id} với KHÓA CHÍNH DẠNG SỐ — trước bản vá 2026-08-01 hai khóa cache
     * này không bao giờ gặp nhau, nên màn hình cân KHÔNG BAO GIỜ nhận được số từ cân thật. Lỗi bị
     * che khuất vì getReading trả mặc định 0.0 khi cache trống, nhìn y hệt một cái cân rỗng.
     */
    public function test_get_reading_accepts_numeric_workstation_id_not_only_code(): void
    {
        Cache::forget('scale_live_weight_WS-BY-ID');
        Cache::forget('scale_live_weight_stable_WS-BY-ID');
        Cache::forget('scale_live_weight_timestamp_WS-BY-ID');

        $station = Workstation::create([
            'code' => 'WS-BY-ID',
            'name' => 'Tram can tra theo id',
            'type' => 'SMALL_SCALE',
            'active' => true,
        ]);

        // Agent đẩy theo MÃ trạm — đúng như appsettings.json của Agent thật.
        $this->postJson('/api/devices/readings', [
            'workstation_id' => 'WS-BY-ID',
            'weight' => 12.75,
            'is_stable' => true,
        ])->assertStatus(200);

        // Frontend hỏi theo ID SỐ.
        $response = $this->actingAs($this->operator)
            ->getJson("/api/devices/readings/{$station->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('weight', 12.75);
        $response->assertJsonPath('is_stable', true);
        $response->assertJsonPath('has_reading', true);
    }

    /** Trạm không tồn tại thì vẫn phải trả 200 + has_reading=false, không được lỗi 500. */
    public function test_get_reading_with_unknown_numeric_id_reports_no_reading(): void
    {
        $response = $this->actingAs($this->operator)->getJson('/api/devices/readings/99999999');

        $response->assertStatus(200);
        $response->assertJsonPath('has_reading', false);
    }

    /**
     * Bộ cài MSI đóng cứng Workstation:Id cho MỌI máy, nên hai trạm cân chạy cùng lúc ghi đè lên
     * đúng một khóa cache và mỗi màn hình đọc phải số của trạm kia — cân sai mà vẫn tô xanh ĐẠT.
     * `?local=1` ghép cặp theo IP nguồn (Agent và trình duyệt chạy cùng máy trạm, cùng gọi thẳng
     * backend không qua proxy) để cài y hệt nhau lên bao nhiêu máy cũng đúng, khỏi sửa tay từng máy.
     */
    public function test_local_flag_isolates_two_machines_sharing_one_workstation_code(): void
    {
        foreach (['scale_live_weight_', 'scale_live_weight_stable_', 'scale_live_weight_timestamp_'] as $p) {
            Cache::forget($p.'WS-SHARED');
            Cache::forget($p.'machine_10_0_0_55');
            Cache::forget($p.'machine_10_0_0_66');
        }

        $this->postJson('/api/devices/readings',
            ['workstation_id' => 'WS-SHARED', 'weight' => 12.34, 'is_stable' => true],
            ['REMOTE_ADDR' => '10.0.0.55'])->assertStatus(200);

        // Máy B đẩy SAU => khóa chung theo mã trạm mang số của máy B và luôn "tươi" hơn. Đây đúng
        // là lý do không thể chọn theo "bản nào tươi hơn" — máy đang ngồi phải thắng tuyệt đối.
        $this->postJson('/api/devices/readings',
            ['workstation_id' => 'WS-SHARED', 'weight' => 98.76, 'is_stable' => true],
            ['REMOTE_ADDR' => '10.0.0.66'])->assertStatus(200);

        $this->actingAs($this->operator)
            ->getJson('/api/devices/readings/WS-SHARED?local=1', ['REMOTE_ADDR' => '10.0.0.55'])
            ->assertJsonPath('weight', 12.34)
            ->assertJsonPath('source', 'MACHINE');

        $this->actingAs($this->operator)
            ->getJson('/api/devices/readings/WS-SHARED?local=1', ['REMOTE_ADDR' => '10.0.0.66'])
            ->assertJsonPath('weight', 98.76)
            ->assertJsonPath('source', 'MACHINE');

        // Máy không có Agent: không có bản theo IP nên lui về khóa mã trạm như trước bản vá —
        // các trạm đã cấu hình tay từ trước không bị ảnh hưởng.
        $this->actingAs($this->operator)
            ->getJson('/api/devices/readings/WS-SHARED?local=1', ['REMOTE_ADDR' => '10.0.0.77'])
            ->assertJsonPath('weight', 98.76)
            ->assertJsonPath('source', 'WORKSTATION');

        // Dashboard KHÔNG gửi local=1 (nó xem nhiều trạm từ xa) — hành vi phải y như cũ.
        $this->actingAs($this->operator)
            ->getJson('/api/devices/readings/WS-SHARED', ['REMOTE_ADDR' => '10.0.0.55'])
            ->assertJsonPath('weight', 98.76)
            ->assertJsonPath('source', 'WORKSTATION');
    }

    /**
     * Agent/PuTTY chết ở máy này: số cân hết TTL 15s nhưng mốc thời gian (TTL 1 giờ) vẫn còn, đủ để
     * biết "máy này CÓ cân". Phải báo mất tín hiệu, TUYỆT ĐỐI không âm thầm tụt về khóa mã trạm và
     * hiển thị số cân của trạm khác — cân sai mà vẫn tô xanh ĐẠT nguy hiểm hơn hẳn mất số.
     */
    public function test_local_machine_with_dead_agent_reports_no_reading_instead_of_other_scale(): void
    {
        foreach (['scale_live_weight_', 'scale_live_weight_stable_', 'scale_live_weight_timestamp_'] as $p) {
            Cache::forget($p.'WS-DEAD');
            Cache::forget($p.'machine_10_0_0_88');
        }

        $this->postJson('/api/devices/readings',
            ['workstation_id' => 'WS-DEAD', 'weight' => 55.5, 'is_stable' => true],
            ['REMOTE_ADDR' => '10.0.0.99'])->assertStatus(200);

        $this->postJson('/api/devices/readings',
            ['workstation_id' => 'WS-DEAD', 'weight' => 11.1, 'is_stable' => true],
            ['REMOTE_ADDR' => '10.0.0.88'])->assertStatus(200);

        // TTL 15s của giá trị cân trôi qua, mốc thời gian TTL 3600s thì chưa.
        Cache::forget('scale_live_weight_machine_10_0_0_88');
        Cache::forget('scale_live_weight_stable_machine_10_0_0_88');

        $this->actingAs($this->operator)
            ->getJson('/api/devices/readings/WS-DEAD?local=1', ['REMOTE_ADDR' => '10.0.0.88'])
            ->assertJsonPath('has_reading', false)
            ->assertJsonPath('source', 'MACHINE');
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
