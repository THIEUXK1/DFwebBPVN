<?php

// backend/tests/Feature/WeighFromQrIdempotencyTest.php
//
// Chống ghi trùng cho POST /api/scanner/weigh-from-qr — endpoint "một lệnh làm tất cả" của
// /weighing-station-v2 (mở lô + tạo vòng cân + ghi số cân + dựng phiếu, trong 1 transaction).
//
// Vì sao phải có: từ 2026-08-02 trình duyệt có HÀNG ĐỢI (frontend/src/services/saveQueue.ts) —
// mất mạng lúc SAVE thì mẻ nằm lại localStorage và tự gửi lại khi có mạng. Ca hiểm nhất KHÔNG
// phải mất mạng hẳn, mà là: request ĐÃ tới server và ghi xong, nhưng phản hồi rớt giữa đường.
// Hàng đợi coi như thất bại và gửi lại — không có khoá thì mẻ vào DB hai lần.

namespace Tests\Feature;

use App\Models\Capability;
use App\Models\Machine;
use App\Models\ScaleMeasurement;
use App\Models\User;
use App\Models\WeighingJob;
use App\Models\WeighingJobItem;
use App\Models\Workstation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeighFromQrIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workstation $workstation;

    private string $rawQr = '#IDEM-TC001-VD10-220-R1-DYE001-12.50-R2-DYE002-8.30-R3-DYE003-25.00';

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['username' => 'op_idem_'.uniqid()]);
        Machine::firstOrCreate(['code' => 'VD10'], ['name' => 'VD10']);
        $this->workstation = Workstation::create([
            'code' => 'WS-IDEM-'.substr(uniqid(), -6),
            'name' => 'Tram can idempotency',
            // `handleOrderScan` chỉ nhận 4 loại trạm cân sản xuất (DYE/CHEMICAL/A11/DLG); QR mẻ
            // nhuộm đi đường DYE_WEIGHING.
            'type' => 'DYE_WEIGHING',
            'status' => 'ACTIVE',
        ]);

        // Route đi qua `workstation.guard:SCAN_ORDER` -> đòi capability SCAN_QR. Payload có
        // `workstation_code` nên middleware phân giải ra được trạm và KHÔNG rơi vào nhánh bỏ qua
        // của môi trường test — phải gắn quyền như trạm thật ngoài xưởng.
        $scanCap = Capability::firstOrCreate(['code' => 'SCAN_QR'], ['name' => 'SCAN_QR', 'category' => 'DEVICE']);
        $this->workstation->capabilities()->attach($scanCap->id, ['enabled' => true]);
    }

    private function payload(?string $key): array
    {
        return array_filter([
            'raw_qr' => $this->rawQr,
            'workstation_code' => $this->workstation->code,
            'scale_device_id' => 'MOCK_SCALE',
            'stable' => true,
            'idempotency_key' => $key,
            'rows' => [
                ['sequence_no' => 1, 'weight' => 12.50, 'tare_weight' => 3.0, 'gross_weight' => 15.50],
                ['sequence_no' => 2, 'weight' => 8.30, 'tare_weight' => 3.0, 'gross_weight' => 11.30],
                ['sequence_no' => 3, 'weight' => 25.00, 'tare_weight' => 3.0, 'gross_weight' => 28.00],
            ],
        ], fn ($v) => $v !== null);
    }

    private function gui(?string $key)
    {
        return $this->actingAs($this->user)
            ->postJson('/api/scanner/weigh-from-qr', $this->payload($key));
    }

    /** Gửi 2 lần cùng khoá = đúng cảnh hàng đợi gửi lại sau khi phản hồi lần đầu bị rớt. */
    public function test_same_idempotency_key_does_not_write_twice()
    {
        $key = 'ws2-TEST-'.uniqid();

        $lan1 = $this->gui($key);
        $lan1->assertStatus(200);
        $lan1->assertJsonPath('data.reused', false);
        $jobId = $lan1->json('data.job_id');

        $lan2 = $this->gui($key);
        $lan2->assertStatus(200);

        // Phải trả 200 chứ không phải lỗi: với hàng đợi thì đây là kết quả ĐÚNG (mẻ đã nằm dưới
        // DB). Trả lỗi sẽ khiến nó thử lại mãi không thôi.
        $lan2->assertJsonPath('data.reused', true);
        $lan2->assertJsonPath('data.job_id', $jobId);

        // Chỉ MỘT vòng cân được tạo cho khoá này.
        $this->assertEquals(1, WeighingJob::where('idempotency_key', $key)->count());

        // Và quan trọng nhất: KHÔNG có dòng cân nào bị ghi hai lần.
        $items = WeighingJobItem::where('weighing_job_id', $jobId)->get();
        $this->assertCount(3, $items);
        foreach ($items as $item) {
            $this->assertEquals(
                1,
                ScaleMeasurement::where('weighing_job_item_id', $item->id)->count(),
                "Dòng {$item->sequence_no} bị ghi trùng lịch sử cân"
            );
        }
    }

    /** Lần gửi lại vẫn phải trả về phiếu in — thợ bấm PRINT sau đó vẫn ra đúng phiếu. */
    public function test_replayed_request_still_returns_a_printable_slip()
    {
        $key = 'ws2-TEST-'.uniqid();
        $this->gui($key)->assertStatus(200);

        $lan2 = $this->gui($key);
        $lan2->assertStatus(200);

        $payload = $lan2->json('data.slip.label_payload');
        $this->assertNotEmpty($payload, 'Lần gửi lại phải kèm nội dung phiếu');
        $this->assertStringContainsString('DF_WEIGHING_SLIP', $payload);
        // Từ 06/08/2026 phiếu dựng 1:1 theo sheet của `scaleform.btnPrint_Click`: màu và mã hàng
        // nằm ở HAI hàng riêng có nhãn "COLOR:"/"CODE:" ở cột A — xem buildSlipHtml.
        $this->assertStringContainsString('<td>COLOR:</td><td>IDEM</td>', $payload);
        $this->assertStringContainsString('<td>CODE:</td><td>TC001</td>', $payload);
    }

    /**
     * Trình duyệt IN NGAY từ dữ liệu trên màn (không chờ server) rồi gửi kèm mốc giờ đã in.
     * `print_jobs` phải lưu đúng mốc đó — nếu server tự lấy giờ của mình thì bản ghi sẽ lệch với
     * tờ phiếu thật đang nằm trên hàng, tức mất khả năng đối chiếu.
     */
    public function test_printed_at_from_browser_is_used_in_the_stored_slip()
    {
        $moc = '02/08/2026 14:30:05';

        $res = $this->actingAs($this->user)->postJson(
            '/api/scanner/weigh-from-qr',
            array_merge($this->payload('ws2-TEST-'.uniqid()), ['printed_at' => $moc])
        );

        $res->assertStatus(200);
        // Mốc giờ nằm ở hàng CUỐI phiếu sau nhãn "Print time:" (đúng chỗ VBA đặt) — vẫn phải là
        // ĐÚNG mốc trình duyệt gửi lên.
        $this->assertStringContainsString(
            "<td>Print time:</td><td>{$moc}</td>",
            $res->json('data.slip.label_payload')
        );
    }

    /** Không gửi mốc giờ thì server tự lấy giờ của mình, không được vỡ. */
    public function test_slip_falls_back_to_server_time_without_printed_at()
    {
        $res = $this->gui('ws2-TEST-'.uniqid());

        $res->assertStatus(200);
        $this->assertMatchesRegularExpression(
            '#<td>Print time:</td><td>\d{2}/\d{2}/\d{4} \d{2}:\d{2}:\d{2}</td>#',
            $res->json('data.slip.label_payload')
        );
    }

    /** Hai mẻ khác nhau (khoá khác nhau) vẫn phải ghi thành hai vòng cân riêng. */
    public function test_different_keys_create_separate_rounds()
    {
        $job1 = $this->gui('ws2-TEST-'.uniqid())->json('data.job_id');
        $job2 = $this->gui('ws2-TEST-'.uniqid())->json('data.job_id');

        $this->assertNotEquals($job1, $job2, 'Hai khoá khác nhau phải ra hai vòng cân khác nhau');
    }

    /**
     * Ping của hàng đợi phải trả lời khi CHƯA đăng nhập.
     *
     * Không phải chuyện vụn: hàng đợi ping mỗi 15 giây để biết đường đã thông chưa. Nếu ai đó
     * đẩy route này vào sau middleware auth thì phiên hết hạn sẽ trả 401, mà interceptor ở
     * frontend/src/main.ts bắt 401 bằng `logout()` + `reload()` — tức một nhịp chạy ngầm sẽ tự
     * đá thợ ra khỏi màn hình cân giữa lúc đang cân.
     */
    public function test_ping_answers_without_authentication()
    {
        $this->getJson('/api/ping')
            ->assertStatus(200)
            ->assertJsonPath('status', 'OK');
    }

    /** Payload cân tay: không có `raw_qr`, chỉ có rack + số cân. */
    private function payloadCanTay(?string $key): array
    {
        return array_filter([
            'manual' => true,
            'workstation_code' => $this->workstation->code,
            'scale_device_id' => 'MOCK_SCALE',
            'stable' => true,
            'idempotency_key' => $key,
            'rows' => [
                ['sequence_no' => 1, 'weight' => 8.30, 'rack_code' => 'A1', 'tare_weight' => 2.0, 'gross_weight' => 10.30],
                ['sequence_no' => 2, 'weight' => 15.00, 'rack_code' => null, 'tare_weight' => 2.0, 'gross_weight' => 17.00],
            ],
        ], fn ($v) => $v !== null);
    }

    /**
     * CÂN TAY — cân không quét đơn vẫn lưu được (yêu cầu 2026-08-02).
     *
     * Chốt luôn quyết định "cái gì trống thì trống": lô sinh ra KHÔNG được bịa màu/mã hàng, vì
     * bịa là làm bẩn master data và mọi báo cáo lọc theo màu.
     */
    public function test_manual_weighing_saves_without_a_qr()
    {
        $res = $this->actingAs($this->user)
            ->postJson('/api/scanner/weigh-from-qr', $this->payloadCanTay('ws2-TAY-'.uniqid()));

        $res->assertStatus(200);
        $res->assertJsonPath('data.manual', true);

        $job = WeighingJob::with('batch')->find($res->json('data.job_id'));
        $this->assertNotNull($job);

        // Trống thì để TRỐNG.
        $this->assertNull($job->batch->color);
        $this->assertNull($job->batch->product_code);
        $this->assertNull($job->batch->machine_id);
        // ...nhưng phải nhận diện được để báo cáo loại ra.
        $this->assertStringStartsWith('CANTAY-', $job->batch->legacy_batch_id);

        $items = WeighingJobItem::where('weighing_job_id', $job->id)->orderBy('sequence_no')->get();
        $this->assertCount(2, $items);
        $this->assertEquals('A1', $items[0]->rack_code);
        $this->assertEqualsWithDelta(8.30, (float) $items[0]->actual_weight, 0.000001);
    }

    /**
     * Dòng cân tay KHÔNG được gắn nhãn KHÔNG ĐẠT.
     *
     * `planned_weight` = 0 nên nhánh so dung sai sẽ cho ra REJECTED — mà gắn "không đạt" cho một
     * con số không có gì để đối chiếu là nói sai, không phải để trống.
     */
    public function test_manual_rows_are_not_labelled_rejected()
    {
        $res = $this->actingAs($this->user)
            ->postJson('/api/scanner/weigh-from-qr', $this->payloadCanTay('ws2-TAY-'.uniqid()));

        $items = WeighingJobItem::where('weighing_job_id', $res->json('data.job_id'))->get();
        foreach ($items as $item) {
            $this->assertSame('MANUAL', $item->process_status);
        }
    }

    /** Phiếu cân tay vẫn phải in được, và phần đầu để trống chứ không bịa. */
    public function test_manual_weighing_still_returns_a_slip()
    {
        $res = $this->actingAs($this->user)
            ->postJson('/api/scanner/weigh-from-qr', $this->payloadCanTay('ws2-TAY-'.uniqid()));

        $payload = $res->json('data.slip.label_payload');
        $this->assertStringContainsString('DF_WEIGHING_SLIP', $payload);
        // Không quét đơn thì không có màu/mã hàng: dòng tiêu đề ghi thẳng "CAN TAY" thay vì để
        // trống (bố cục 05/08/2026). Cột kết quả in nhãn rút gọn "TAY" của trạng thái MANUAL.
        $this->assertStringContainsString('"CAN TAY"', $payload);
        $this->assertStringContainsString('"TAY"', $payload);
    }

    /** Hàng đợi gửi lại mẻ cân tay: mỗi lần gửi lại KHÔNG được đẻ thêm một lô mới. */
    public function test_manual_weighing_is_idempotent()
    {
        $key = 'ws2-TAY-'.uniqid();

        $lan1 = $this->actingAs($this->user)->postJson('/api/scanner/weigh-from-qr', $this->payloadCanTay($key));
        $lan2 = $this->actingAs($this->user)->postJson('/api/scanner/weigh-from-qr', $this->payloadCanTay($key));

        $lan2->assertStatus(200);
        $lan2->assertJsonPath('data.reused', true);
        $lan2->assertJsonPath('data.job_id', $lan1->json('data.job_id'));
        $this->assertEquals(1, WeighingJob::where('idempotency_key', $key)->count());
    }

    /**
     * Cân tay KHÔNG bấm NEXT: đặt vật tư lên cân, thấy số RAW rồi SAVE luôn (yêu cầu 2026-08-02).
     *
     * Payload khác hẳn nhánh bấm NEXT: đúng MỘT dòng và `tare_weight` = null, vì chưa hề chốt bì
     * lần nào — số lưu chính là số cân gộp. Server phải nhận được, không được đòi có bì.
     */
    public function test_manual_weighing_accepts_a_single_untared_row()
    {
        $res = $this->actingAs($this->user)->postJson('/api/scanner/weigh-from-qr', [
            'manual' => true,
            'workstation_code' => $this->workstation->code,
            'scale_device_id' => 'MOCK_SCALE',
            'stable' => true,
            'idempotency_key' => 'ws2-TAY-'.uniqid(),
            'rows' => [
                ['sequence_no' => 1, 'weight' => 4.56, 'rack_code' => null, 'tare_weight' => null, 'gross_weight' => 4.56],
            ],
        ]);

        $res->assertStatus(200);

        $items = WeighingJobItem::where('weighing_job_id', $res->json('data.job_id'))->get();
        $this->assertCount(1, $items);
        $this->assertEqualsWithDelta(4.56, (float) $items[0]->actual_weight, 0.000001);
        $this->assertSame('MANUAL', $items[0]->process_status);
    }

    /** Cân tay mà chưa cân ô nào thì phải chối tử tế, không tạo lô rỗng. */
    public function test_manual_weighing_rejects_when_nothing_was_weighed()
    {
        $payload = $this->payloadCanTay('ws2-TAY-'.uniqid());
        $payload['rows'] = [['sequence_no' => 1, 'weight' => null]];

        $truoc = WeighingJob::count();
        $this->actingAs($this->user)
            ->postJson('/api/scanner/weigh-from-qr', $payload)
            ->assertStatus(422);

        $this->assertEquals($truoc, WeighingJob::count(), 'Không được tạo vòng cân rỗng');
    }

    /**
     * Lô CÂN TAY phải bị loại khỏi báo cáo tiêu hao, còn lô quét đơn thì KHÔNG.
     *
     * Cân tay có `planned_weight` = 0 nên nếu lọt vào, mọi dòng thành "thực cân X, định mức 0" —
     * bịa ra một khoản vượt định mức 100% không có thật.
     */
    public function test_manual_batches_are_excluded_from_the_consumption_report()
    {
        $this->actingAs($this->user)
            ->postJson('/api/scanner/weigh-from-qr', $this->payloadCanTay('ws2-TAY-'.uniqid()))
            ->assertStatus(200);
        $this->gui('ws2-TEST-'.uniqid())->assertStatus(200);

        $res = $this->actingAs($this->user)->getJson('/api/reports/dye-consumption?'.http_build_query([
            'from' => now()->subDay()->toDateString(),
            'to' => now()->addDay()->toDateString(),
        ]));

        $res->assertStatus(200);
        $ma = collect($res->json('data.rows') ?? $res->json('data') ?? [])
            ->pluck('material_code')
            ->filter()
            ->all();

        $this->assertNotContains('CANTAY', $ma, 'Lô cân tay không được lọt vào báo cáo tiêu hao');
        $this->assertContains('DYE001', $ma, 'Lô quét đơn vẫn phải được tính');
    }

    /**
     * Bộ lọc cân tay KHÔNG được nuốt luôn lô có `legacy_batch_id` NULL.
     *
     * Trong SQL `NULL NOT LIKE '...'` cho ra NULL chứ không ra TRUE, nên viết `not like` trần là
     * ném sạch mọi lô không có mã cũ ra khỏi báo cáo — hỏng âm thầm, số chỉ nhỏ đi chứ không lỗi.
     */
    public function test_batches_without_a_legacy_id_are_still_counted()
    {
        $batch = \App\Models\ProductionBatch::create([
            'legacy_batch_id' => null,
            'color' => 'NULLID', 'product_code' => 'P1',
            'machine_id' => null, 'cloth_weight' => 0, 'status' => 'NEW',
        ]);

        $con = \App\Models\ProductionBatch::khongPhaiCanTay()->where('id', $batch->id)->count();
        $this->assertEquals(1, $con, 'Lô không có legacy_batch_id vẫn phải được giữ lại');
    }

    /** Client cũ / gọi tay không gửi khoá thì vẫn phải chạy như trước, không vỡ. */
    public function test_works_without_idempotency_key()
    {
        $res = $this->gui(null);

        $res->assertStatus(200);
        $res->assertJsonPath('data.reused', false);
        $this->assertNotEmpty($res->json('data.job_id'));
    }
}
