<?php
// backend/tests/Feature/QrPayloadServiceTest.php
//
// Test dyesProcess/totalD (b24-warehouse-routing.md Mục 5) — trước 2026-07-17 các giá trị
// này là placeholder cứng ("Nylon Dyes" / 0). Đã implement đúng thuật toán VBA (quét dòng
// dye/chem parse từ raw_qr_dye/raw_qr_chemical qua parseChemLines()), test này khóa lại
// hành vi để chống regression về placeholder.

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Machine;
use App\Models\Tank;
use App\Models\ProductionBatch;
use App\Models\MachineDispatch;
use App\Services\QrPayloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class QrPayloadServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeDispatch(string $rawDye = '', string $rawChem = ''): MachineDispatch
    {
        $machine = Machine::create(['code' => 'VD07', 'name' => 'Machine VD07']);
        $tank = Tank::create(['code' => '3C', 'name' => 'Tank 3C']);

        $batch = ProductionBatch::create([
            'legacy_batch_id' => 'B' . time() . rand(1, 99999),
            'color' => 'BLUE',
            'product_code' => 'P999',
            'machine_id' => $machine->id,
            'tank_id' => $tank->id,
            'level_code' => '50',
            'status' => 'APPROVED',
        ]);

        return MachineDispatch::create([
            'batch_id' => $batch->id,
            'queue_state' => 'WAITING',
            'source_table' => 'tbl_ToSend2',
            'legacy_id' => rand(100, 999),
            'legacy_row_no' => rand(1000, 9999),
            'raw_qr_dye' => $rawDye,
            'raw_qr_chemical' => $rawChem,
        ]);
    }

    public function test_dyes_process_defaults_to_nylon_when_no_special_code(): void
    {
        $dispatch = $this->makeDispatch(rawDye: 'R-ABC123-1.500');
        $service = new QrPayloadService();

        $payload = $service->buildProcessPayload($dispatch, $dispatch->batch, 'PROCESS');

        $this->assertStringContainsString("\r\nNylon Dyes", $payload);
    }

    public function test_dyes_process_is_cation_when_dye_code_ends_with_c(): void
    {
        $dispatch = $this->makeDispatch(rawDye: 'R-DYE100C-2.000');
        $service = new QrPayloadService();

        $payload = $service->buildProcessPayload($dispatch, $dispatch->batch, 'PROCESS');

        $this->assertStringContainsString("\r\nCation Dyes", $payload);
    }

    public function test_dyes_process_is_disperse_when_isdisperse_and_chemkey_both_true(): void
    {
        // Dye kết thúc "D" -> isDisperse; chem chứa "0574" -> hasChemKey; không phải Cation.
        $dispatch = $this->makeDispatch(
            rawDye: 'R-DYE200D-3.000',
            rawChem: 'R-CHEM0574X-0.500'
        );
        $service = new QrPayloadService();

        $payload = $service->buildProcessPayload($dispatch, $dispatch->batch, 'PROCESS');

        $this->assertStringContainsString("\r\nDisperse Dyes", $payload);
    }

    public function test_dyes_process_stays_nylon_when_isdisperse_true_but_no_chemkey(): void
    {
        // Y13 prefix -> isDisperse, nhưng chem không chứa 0574/0507 -> không đổi thành Disperse.
        $dispatch = $this->makeDispatch(
            rawDye: 'R-Y13FOO-1.000',
            rawChem: 'R-CHEM9999-0.500'
        );
        $service = new QrPayloadService();

        $payload = $service->buildProcessPayload($dispatch, $dispatch->batch, 'PROCESS');

        $this->assertStringContainsString("\r\nNylon Dyes", $payload);
    }

    public function test_dyes_process_cation_takes_priority_over_disperse(): void
    {
        // Cùng lúc có dòng kết thúc "C" (Cation) và điều kiện Disperse đủ -> vẫn phải là Cation
        // (rule 5 chỉ áp dụng "nếu CHƯA phải Cation Dyes").
        $dispatch = $this->makeDispatch(
            rawDye: 'R-DYE100C-1.000-R-DYE200D-2.000',
            rawChem: 'R-CHEM0507X-0.500'
        );
        $service = new QrPayloadService();

        $payload = $service->buildProcessPayload($dispatch, $dispatch->batch, 'PROCESS');

        $this->assertStringContainsString("\r\nCation Dyes", $payload);
    }

    public function test_total_d_sums_all_dye_line_weights(): void
    {
        $dispatch = $this->makeDispatch(rawDye: 'R-A-1.000-R-B-2.500-R-C-0.750');
        $service = new QrPayloadService();

        $payload = $service->buildProcessPayload($dispatch, $dispatch->batch, 'EXTRA');
        $lastLine = trim(collect(explode("\r\n", $payload))->last());

        $this->assertEquals('4.25', $lastLine);
    }

    public function test_total_d_is_zero_when_no_dye_lines(): void
    {
        $dispatch = $this->makeDispatch(rawDye: '');
        $service = new QrPayloadService();

        $payload = $service->buildProcessPayload($dispatch, $dispatch->batch, 'EXTRA');
        $lastLine = trim(collect(explode("\r\n", $payload))->last());

        $this->assertEquals('0', $lastLine);
    }

    /**
     * parseDyeScan là port nguyên văn txt_color_AfterUpdate (trích olevba trực tiếp từ
     * "4.semiauto-small scale - delta-stable-final_DF026-027.xlsm" dòng 973-1045,
     * 2026-07-17). Round-trip test: build rồi parse phải khôi phục đúng color/code/machine/level.
     */
    public function test_parse_dye_scan_round_trips_with_build_dye_payload(): void
    {
        $dispatch = $this->makeDispatch(rawDye: 'RACK1-DYE001-1.500-RACK2-DYE002-2.300');
        $service = new QrPayloadService();

        $payload = $service->buildDyePayload($dispatch, $dispatch->batch);
        $parsed = $service->parseDyeScan($payload);

        $this->assertEquals('BLUE', $parsed['color']);
        $this->assertEquals('P999', $parsed['code']);
        $this->assertEquals('VD07', $parsed['machine']);
        $this->assertEquals('50', $parsed['level']);
        $this->assertCount(2, $parsed['rack_lines']);
        $this->assertEquals(['rack' => 'RACK1', 'dye' => 'DYE001', 'weight' => '1.500'], $parsed['rack_lines'][0]);
        $this->assertEquals(['rack' => 'RACK2', 'dye' => 'DYE002', 'weight' => '2.300'], $parsed['rack_lines'][1]);
    }

    /**
     * Test vector đúng theo VBA: chuỗi có nhiều cụm "-dye-" xen giữa (mô phỏng máy quét
     * quét đè QR mới lên form đang có dữ liệu cũ — VBA loại bỏ MỌI cụm "-dye-" trước khi
     * tách, bản SMALL_SCALE dùng Do While (lặp), không phải Replace 1 lần như LARGE_SCALE.
     */
    public function test_parse_dye_scan_strips_all_dye_markers_repeated(): void
    {
        $service = new QrPayloadService();
        $parsed = $service->parseDyeScan('#RED-dye-P123-dye-VD10-220-R1-D1-1.0');

        $this->assertEquals('RED', $parsed['color']);
        $this->assertEquals('P123', $parsed['code']);
        $this->assertEquals('VD10', $parsed['machine']);
        $this->assertEquals('220', $parsed['level']);
    }

    /** Chuỗi có "chem" (không phân biệt hoa/thường) -> cắt bỏ phần sau, đúng VBA. */
    public function test_parse_dye_scan_cuts_at_chem_marker(): void
    {
        $service = new QrPayloadService();
        $parsed = $service->parseDyeScan('#RED-P123-VD10-220-R1-D1-1.0-CHEM-junk-data');

        $this->assertEquals('RED', $parsed['color']);
        $this->assertCount(1, $parsed['rack_lines']);
    }

    /**
     * parseOrderEntryScan = port Box1_AfterUpdate (màn hình Nhập đơn sản xuất,
     * "2.C3 grid load row lock id FB -192(QR).xlsm", trích olevba 2026-07-18).
     * Vector cơ bản: 4 phần tử đầu -> color/code/machine/level, không có dye/chem.
     */
    public function test_parse_order_entry_scan_basic_four_fields(): void
    {
        $service = new QrPayloadService();
        $parsed = $service->parseOrderEntryScan('RED-P123-VD010-220');

        $this->assertEquals('RED', $parsed['color']);
        $this->assertEquals('P123', $parsed['code']);
        $this->assertEquals('VD010', $parsed['machine']);
        $this->assertEquals('220', $parsed['level']);
        $this->assertEquals('', $parsed['raw_qr_dye']);
        $this->assertEquals('', $parsed['raw_qr_chemical']);
    }

    /** CleanLeadingGarbage: bỏ mọi ký tự đầu chuỗi cho tới ký tự 0-9/A-Z hợp lệ đầu tiên. */
    public function test_parse_order_entry_scan_strips_leading_garbage(): void
    {
        $service = new QrPayloadService();
        $parsed = $service->parseOrderEntryScan("\x02\x03*RED-P123-VD010-220");

        $this->assertEquals('RED', $parsed['color']);
    }

    /** "/" được chuẩn hoá thành "-" và "--" liên tiếp được rút gọn về 1 "-", đúng VBA. */
    public function test_parse_order_entry_scan_normalizes_slash_and_collapses_double_dash(): void
    {
        $service = new QrPayloadService();
        $parsed = $service->parseOrderEntryScan('RED/P123--VD010-220');

        $this->assertEquals('RED', $parsed['color']);
        $this->assertEquals('P123', $parsed['code']);
        $this->assertEquals('VD010', $parsed['machine']);
        $this->assertEquals('220', $parsed['level']);
    }

    /**
     * raw_qr_dye/raw_qr_chemical được cắt từ chuỗi ĐÃ chuẩn hoá bằng InStr("-dye-")/
     * InStr("-chem-") — độc lập với 4 trường color/code/machine/level (không bị ảnh
     * hưởng bởi nội dung phía sau vị trí thứ 4).
     */
    public function test_parse_order_entry_scan_extracts_dye_and_chem_payload(): void
    {
        $service = new QrPayloadService();
        $parsed = $service->parseOrderEntryScan('RED-P123-VD010-220-dye-RACK1-Y1008A-1.5-chem-AC77-2.0');

        $this->assertEquals('RED', $parsed['color']);
        $this->assertEquals('P123', $parsed['code']);
        $this->assertEquals('VD010', $parsed['machine']);
        $this->assertEquals('220', $parsed['level']);
        $this->assertEquals('RACK1-Y1008A-1.5', $parsed['raw_qr_dye']);
        $this->assertEquals('AC77-2.0', $parsed['raw_qr_chemical']);
    }

    /** Có "-dye-" nhưng không có "-chem-" -> raw_qr_dye lấy hết phần còn lại tới cuối chuỗi. */
    public function test_parse_order_entry_scan_dye_without_chem_takes_rest_of_string(): void
    {
        $service = new QrPayloadService();
        $parsed = $service->parseOrderEntryScan('RED-P123-VD010-220-dye-RACK1-Y1008A-1.5');

        $this->assertEquals('RACK1-Y1008A-1.5', $parsed['raw_qr_dye']);
        $this->assertEquals('', $parsed['raw_qr_chemical']);
    }
}
