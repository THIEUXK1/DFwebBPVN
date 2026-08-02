<?php

// backend/tests/Feature/ScaleReadingCacheTest.php
//
// Chốt hành vi của GET /api/devices/readings/{id} sau khi `readCacheSlot` đổi từ 3 lần
// `Cache::get` sang MỘT lần `Cache::many` (2026-08-02, để giảm truy vấn DB cho nhịp poll
// 5 lần/giây của /weighing-station-v2).
//
// Rủi ro cụ thể của việc đổi: `Cache::many()` trả `null` cho khoá trống chứ KHÔNG nhận giá trị
// mặc định như `Cache::get($key, false)`. Quên bù `false` thì `is_stable` ra `null` -> phía
// trình duyệt `Boolean(null)` vẫn ra false nên nhìn thì "vẫn chạy", nhưng kiểu dữ liệu trong
// phản hồi đã sai và bất kỳ chỗ nào so `=== false` sẽ hỏng âm thầm.

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ScaleReadingCacheTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /** Mã trạm KHÔNG phải số -> `resolveReadingKey` dùng thẳng, không tra DB. */
    private string $code = 'WS-CACHE-TEST';

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['username' => 'op_cache_'.uniqid()]);
    }

    private function ghiCache(string $key, ?float $weight, bool $stable, ?float $readAt): void
    {
        if ($weight !== null) {
            Cache::put("scale_live_weight_{$key}", $weight, 15);
            Cache::put("scale_live_weight_stable_{$key}", $stable, 15);
        }
        if ($readAt !== null) {
            Cache::put("scale_live_weight_timestamp_{$key}", $readAt, 3600);
        }
    }

    public function test_returns_the_cached_reading()
    {
        $this->ghiCache($this->code, 12.34, true, microtime(true));

        $res = $this->actingAs($this->user)->getJson("/api/devices/readings/{$this->code}");

        $res->assertStatus(200);
        $res->assertJsonPath('weight', 12.34);
        $res->assertJsonPath('is_stable', true);
        $res->assertJsonPath('has_reading', true);
        $this->assertLessThan(5000, $res->json('age_ms'));
    }

    /**
     * Cache RỖNG — đây là ca mà `many()` khác `get()`, và cũng là trạng thái thật khi Agent/PuTTY
     * chết. `is_stable` phải là `false` ĐÚNG KIỂU BOOL, không được là null.
     */
    public function test_empty_cache_reports_no_reading_with_stable_false()
    {
        $res = $this->actingAs($this->user)->getJson('/api/devices/readings/WS-KHONG-CO-GI');

        $res->assertStatus(200);
        $res->assertJsonPath('has_reading', false);
        $res->assertJsonPath('age_ms', null);
        $this->assertSame(false, $res->json('is_stable'), 'is_stable phải là false, không phải null');
        // `assertEquals` chứ không `assertSame`: 0.0 qua JSON về thành int 0, đó là chuyện của
        // định dạng truyền chứ không phải của controller.
        $this->assertEquals(0.0, $res->json('weight'));
    }

    /**
     * Số cân hết hạn (TTL 15s) nhưng mốc thời gian còn (TTL 1h): phải nói được "cân im bao lâu
     * rồi" chứ không phải im lặng. Đây là thứ /weighing-station-v2 dùng để báo MẤT TÍN HIỆU CÂN
     * thay vì hiển thị 0.0 như một số cân thật.
     */
    public function test_expired_weight_still_reports_age()
    {
        $this->ghiCache($this->code, null, false, microtime(true) - 60);

        $res = $this->actingAs($this->user)->getJson("/api/devices/readings/{$this->code}");

        $res->assertStatus(200);
        $res->assertJsonPath('has_reading', false);
        $this->assertGreaterThan(59000, $res->json('age_ms'));
    }

    /** `?local=1`: cân cắm ở CHÍNH máy đang mở màn hình thắng tuyệt đối mã trạm cấu hình sẵn. */
    public function test_local_flag_prefers_the_scale_on_this_machine()
    {
        $this->ghiCache($this->code, 11.11, true, microtime(true));
        // Test chạy với IP 127.0.0.1 -> khoá theo máy là machine_127_0_0_1.
        $this->ghiCache('machine_127_0_0_1', 22.22, true, microtime(true));

        $res = $this->actingAs($this->user)->getJson("/api/devices/readings/{$this->code}?local=1");

        $res->assertStatus(200);
        $res->assertJsonPath('weight', 22.22);
        $res->assertJsonPath('source', 'MACHINE');
    }
}
