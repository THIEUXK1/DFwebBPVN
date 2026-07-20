<?php
// backend/app/Services/QrPayloadService.php
//
// Sinh payload QR theo ĐÚNG định dạng chuỗi thô VBA gốc (Mod_printslip.PrintSlip_70x100,
// đã trích xuất verbatim tại .claude/b24-warehouse-routing.md Mục 4) — bắt buộc theo
// CLAUDE.md mục 5/C-04: "QR code sinh ra từ hệ thống mới phải có định dạng chuỗi thô
// giống hoàn toàn định dạng cũ để các thiết bị đầu cuối máy quét công đoạn hiện tại đọc
// được mà không phải lập trình lại phần cứng máy quét." Được tách thành service riêng
// theo domain-architecture.md Mục 1.4 (không nhét logic này vào Controller).
//
// Phát hiện khi review code Phase E (2026-07-17): bản triển khai ban đầu của
// ConfirmDispatchService::generateQrPayloads() dùng định dạng tự chế "DF:DYE:<uuid>:<color>",
// vi phạm trực tiếp C-04. File này thay thế bằng định dạng đúng VBA.

namespace App\Services;

use App\Models\MachineDispatch;
use App\Models\ProductionBatch;

class QrPayloadService
{
    const CRLF = "\r\n";

    /**
     * qrDye = "#" & color & "-" & code & "-" & machine & "-" & level & "-" & rawDye
     * (b24-warehouse-routing.md Mục 4, dòng "qrDye" — luôn tạo, mọi mode).
     */
    public function buildDyePayload(MachineDispatch $dispatch, ?ProductionBatch $batch): string
    {
        $color = $batch->color ?? '';
        $code = $batch->product_code ?? '';
        $machine = $batch && $batch->machine ? $batch->machine->code : '';
        $level = $batch->level_code ?? '';
        $rawDye = $dispatch->raw_qr_dye ?? '';

        return "#{$color}-{$code}-{$machine}-{$level}-{$rawDye}";
    }

    /**
     * qrChem = VD### & CRLF & tank_ký_tự_đầu & CRLF & "#"&color&"-"&code & CRLF &
     *          random(1-9) & CRLF & level & (lặp CRLF+mã hóa chất+CRLF+khối lượng
     *          cho mỗi dòng có dữ liệu, tối đa 9 dòng, parse từ raw_qr_chemical
     *          theo cùng quy tắc ParseQR của VBA: tách chuỗi theo "-", bước nhảy 3) & CRLF & "#"
     */
    public function buildChemPayload(MachineDispatch $dispatch, ?ProductionBatch $batch): string
    {
        $machine = $batch && $batch->machine ? $this->normalizeVdCode($batch->machine->code) : '';
        $tank = $batch->tank ? strtoupper(trim($batch->tank->code)) : '';
        $tankFirstChar = $tank !== '' ? mb_substr($tank, 0, 1) : '';
        $color = $batch->color ?? '';
        $code = $batch->product_code ?? '';
        $level = $batch->level_code ?? '';
        $rnd = random_int(1, 9);

        $lines = [
            $machine,
            $tankFirstChar,
            "#{$color}-{$code}",
            (string) $rnd,
            $level,
        ];

        foreach ($this->parseChemLines($dispatch->raw_qr_chemical ?? '') as [$chemCode, $chemWeight]) {
            $lines[] = $chemCode;
            $lines[] = str_replace(',', '.', $chemWeight);
        }

        return implode(self::CRLF, $lines) . self::CRLF . '#';
    }

    /**
     * ParseQR (VBA) tách chuỗi rawChem theo dấu "-", bước nhảy 3 (rack-code-weight lặp lại),
     * lấy tối đa 9 bộ. Với payload hóa chất chỉ cần (code, weight) — bỏ phần rack.
     */
    protected function parseChemLines(string $raw): array
    {
        return array_map(fn ($r) => [$r['code'], $r['weight']], $this->parseWeighingLines($raw));
    }

    /**
     * Như parseChemLines() nhưng GIỮ rack — cần cho bảng cân trên tem 70x100 (VBA ghi 3 cột
     * B/C/D và F/G/H = rack/mã/khối lượng, còn nội dung QR (buildDyePayload/buildChemPayload)
     * không nhúng rack, chỉ mã+khối lượng — 2 nhu cầu khác nhau từ CÙNG 1 chuỗi raw_qr_*).
     * @return array<int,array{rack:string,code:string,weight:string}>
     */
    public function parseWeighingLines(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        $parts = explode('-', $raw);
        $result = [];
        for ($i = 0; $i + 2 < count($parts) && count($result) < 9; $i += 3) {
            $result[] = ['rack' => $parts[$i], 'code' => $parts[$i + 1], 'weight' => $parts[$i + 2]];
        }
        return $result;
    }

    /**
     * Port ĐÚNG NGUYÊN VĂN `txt_color_AfterUpdate` (trích xuất lại bằng olevba trực tiếp
     * từ `4.semiauto-small scale - delta-stable-final_DF026-027.xlsm`, dòng 973-1045,
     * 2026-07-17) — đây là hàm THẬT trạm cân dùng để đọc chuỗi QR quét được (máy quét gõ
     * thẳng nội dung QR vào textbox `txt_COLOR`, không có bước tra UUID nào ở phía VBA).
     *
     * Thứ tự xử lý y hệt VBA:
     * 1. Trim, cắt rác đầu chuỗi (CleanLeadingGarbage — ở đây tương đương bỏ ký tự "#" đầu).
     * 2. Đổi "," -> "." (fix decimal).
     * 3. Lặp xóa mọi cụm "-dye-" (không phân biệt hoa/thường).
     * 4. Nếu có "chem" (không phân biệt hoa/thường) -> cắt bỏ phần từ đó về sau.
     * 5. Tách theo "-", bỏ phần tử rỗng.
     * 6. 4 phần tử đầu -> color, code, machine, level. Từ phần tử thứ 5 trở đi, đọc theo
     *    bộ ba (rack, dye, weight) tối đa 9 bộ.
     *
     * @return array{color:string,code:string,machine:string,level:string,rack_lines:array<int,array{rack:string,dye:string,weight:string}>}
     */
    public function parseDyeScan(string $rawScanned): array
    {
        $s = trim($rawScanned);
        // CleanLeadingGarbage (VBA) — QR do QrPayloadService sinh luôn có "#" đứng đầu
        // (xem buildDyePayload); bỏ ký tự không phải chữ/số ở đầu chuỗi trước khi tách.
        $s = ltrim($s, "#");
        $s = str_replace(',', '.', $s);

        $sLower = strtolower($s);
        while (($pos = strpos($sLower, '-dye-')) !== false) {
            $s = substr($s, 0, $pos) . '-' . substr($s, $pos + 5);
            $sLower = strtolower($s);
        }

        $chemPos = stripos($s, 'chem');
        if ($chemPos !== false) {
            $s = substr($s, 0, $chemPos);
        }

        if ($s === '') {
            return ['color' => '', 'code' => '', 'machine' => '', 'level' => '', 'rack_lines' => []];
        }

        $parts = array_values(array_filter(explode('-', $s), fn ($p) => trim($p) !== ''));

        $result = [
            'color' => $parts[0] ?? '',
            'code' => $parts[1] ?? '',
            'machine' => $parts[2] ?? '',
            'level' => $parts[3] ?? '',
            'rack_lines' => [],
        ];

        $idx = 4;
        for ($i = 0; $i < 9 && $idx + 2 < count($parts) + 1; $i++) {
            if (!isset($parts[$idx])) break;
            $rack = $parts[$idx++];
            $dye = $parts[$idx++] ?? '';
            $weight = $parts[$idx++] ?? '';
            $result['rack_lines'][] = ['rack' => $rack, 'dye' => $dye, 'weight' => $weight];
        }

        return $result;
    }

    /**
     * Port ĐÚNG NGUYÊN VĂN `Box1_AfterUpdate` + `CleanLeadingGarbage` (mainform.frm,
     * checkform.frm — trích xuất bằng olevba trực tiếp từ
     * "2.C3 grid load row lock id FB -192(QR).xlsm", 2026-07-18) — đây là màn hình
     * Nhập đơn sản xuất THẬT: người vận hành quét 1 mã (Box1), KHÔNG gõ tay từng ô.
     *
     * Thứ tự xử lý y hệt VBA:
     * 1. CleanLeadingGarbage: bỏ ký tự đầu chuỗi cho tới ký tự hợp lệ đầu tiên
     *    (chỉ chấp nhận 0-9 và A-Z HOA — chữ thường không được coi là hợp lệ, đúng
     *    y nguyên giới hạn gốc của VBA, không phải lỗi cần "sửa cho hợp lý").
     * 2. Đổi "/" -> "-".
     * 3. Rút gọn mọi cụm "--" liên tiếp thành "-".
     * 4. Tách theo "-": 4 phần tử đầu -> color, code, machine, level.
     * 5. Tìm "-dye-"/"-chem-" (không phân biệt hoa/thường) TRONG CHUỖI ĐÃ CHUẨN HÓA
     *    (không phải trong kết quả tách ở bước 4) để cắt raw_qr_dye/raw_qr_chemical.
     *
     * @return array{color:string,code:string,machine:string,level:string,raw_qr_dye:string,raw_qr_chemical:string}
     */
    public function parseOrderEntryScan(string $rawScanned): array
    {
        $cleaned = '';
        $len = strlen($rawScanned);
        for ($i = 0; $i < $len; $i++) {
            $ord = ord($rawScanned[$i]);
            if (($ord >= 48 && $ord <= 57) || ($ord >= 65 && $ord <= 90)) {
                $cleaned = substr($rawScanned, $i);
                break;
            }
        }

        $cleaned = str_replace('/', '-', $cleaned);
        while (str_contains($cleaned, '--')) {
            $cleaned = str_replace('--', '-', $cleaned);
        }

        $parts = explode('-', $cleaned);

        $result = [
            'color' => $parts[0] ?? '',
            'code' => $parts[1] ?? '',
            'machine' => $parts[2] ?? '',
            'level' => $parts[3] ?? '',
            'raw_qr_dye' => '',
            'raw_qr_chemical' => '',
        ];

        $lower = strtolower($cleaned);
        $pDye = strpos($lower, '-dye-');
        $pChem = strpos($lower, '-chem-');

        if ($pDye !== false) {
            if ($pChem !== false && $pChem > $pDye) {
                $result['raw_qr_dye'] = substr($cleaned, $pDye + 5, $pChem - ($pDye + 5));
                $result['raw_qr_chemical'] = substr($cleaned, $pChem + 6);
            } else {
                $result['raw_qr_dye'] = substr($cleaned, $pDye + 5);
            }
        }

        // Phát hiện quét bị rớt ký tự giữa chừng (2026-07-19): mọi mã "QR ALL DATA"
        // thật đều có cả 2 đoạn -dye- và -chem-; thiếu 1 trong 2, hoặc "level" lẫn
        // dấu "." (dấu hiệu 2 số bị dính do mất đoạn ở giữa, vd "50"+"0.69"->"50.69")
        // gần như chắc chắn là do máy quét kiểu bàn phím bắn ký tự quá nhanh làm
        // tràn bộ đệm bàn phím, KHÔNG phải do người dùng hay do đơn hàng thật vậy.
        $result['scan_looks_incomplete'] = $pDye === false
            || $pChem === false
            || str_contains($result['level'], '.');

        return $result;
    }

    /**
     * "VD" & Format(Val(Mid(s,3)),"000") — chuẩn hóa mã máy về 3 chữ số (VD6 -> VD006).
     */
    public function normalizeVdCode(string $code): string
    {
        $code = strtoupper(trim($code));
        if (str_starts_with($code, 'VD')) {
            $num = (int) substr($code, 2);
            return 'VD' . str_pad((string) $num, 3, '0', STR_PAD_LEFT);
        }
        return $code;
    }

    /**
     * qrProcess (mode=PROCESS): color-code timestamp \n machine-tank-newLevel \n dyesProcess
     * newLevel = 450 nếu tank=1A, 250 nếu tank=2B, ngược lại giữ nguyên level.
     */
    public function buildProcessPayload(MachineDispatch $dispatch, ?ProductionBatch $batch, string $mode): string
    {
        $color = $batch->color ?? '';
        $code = $batch->product_code ?? '';
        $machine = $batch && $batch->machine ? $batch->machine->code : '';
        $tank = $batch->tank ? strtoupper(trim($batch->tank->code)) : '';
        $level = $batch->level_code ?? '';

        if ($mode === 'PROCESS') {
            $dyesProcess = $this->computeDyesProcess($dispatch->raw_qr_dye ?? '', $dispatch->raw_qr_chemical ?? '');
            $newLevel = $tank === '1A' ? '450' : ($tank === '2B' ? '250' : $level);
            $timestamp = now()->format('YmdHi');
            return "{$color}-{$code} {$timestamp}" . self::CRLF . "{$machine}-{$tank}-{$newLevel}" . self::CRLF . $dyesProcess;
        }

        if ($mode === 'EXTRA') {
            $vdCode = $batch && $batch->machine ? $this->normalizeVdCode($batch->machine->code) : '';
            $tankFirstChar = $tank !== '' ? mb_substr($tank, 0, 1) : '';
            $rnd = random_int(1, 9);
            $totalD = $this->computeTotalD($dispatch->raw_qr_dye ?? '');
            return implode(self::CRLF, [$vdCode, $tankFirstChar, "{$color} {$code}", (string) $rnd, $level, '1', $this->formatVbaDecimal($totalD)]);
        }

        // mode FB (mặc định)
        $timestampShort = now()->format('Hi');
        $lines = ["{$color}-{$code} {$timestampShort}"];
        foreach ($this->parseChemLines($dispatch->raw_qr_dye ?? '') as [$dyeCode, $dyeWeight]) {
            $lines[] = $dyeCode;
            $lines[] = str_replace(',', '.', $dyeWeight);
        }
        foreach ($this->parseChemLines($dispatch->raw_qr_chemical ?? '') as [$chemCode, $chemWeight]) {
            $lines[] = $chemCode;
            $lines[] = str_replace(',', '.', $chemWeight);
        }
        return implode(self::CRLF, $lines);
    }

    /**
     * dyesProcess (b24-warehouse-routing.md Mục 5) — chép đúng thuật toán VBA:
     * 1. Mặc định "Nylon Dyes".
     * 2. Quét tối đa 9 dòng dye: nếu bất kỳ mã kết thúc bằng "C" -> "Cation Dyes".
     * 3. Cùng vòng quét: nếu mã kết thúc bằng "D" HOẶC bắt đầu bằng "Y13"/"R23"/"B33"
     *    -> cờ isDisperse.
     * 4. Quét tối đa 9 dòng chem: nếu chứa chuỗi con "0574" hoặc "0507" -> cờ hasChemKey.
     * 5. Nếu dyesProcess chưa phải "Cation Dyes" VÀ isDisperse VÀ hasChemKey
     *    -> đổi thành "Disperse Dyes".
     */
    protected function computeDyesProcess(string $rawDye, string $rawChem): string
    {
        $dyesProcess = 'Nylon Dyes';
        $isDisperse = false;

        foreach ($this->parseChemLines($rawDye) as [$dyeCode]) {
            $dyeCode = strtoupper(trim($dyeCode));
            if ($dyeCode === '') {
                continue;
            }
            if (str_ends_with($dyeCode, 'C')) {
                $dyesProcess = 'Cation Dyes';
            }
            if (str_ends_with($dyeCode, 'D')
                || str_starts_with($dyeCode, 'Y13')
                || str_starts_with($dyeCode, 'R23')
                || str_starts_with($dyeCode, 'B33')) {
                $isDisperse = true;
            }
        }

        $hasChemKey = false;
        foreach ($this->parseChemLines($rawChem) as [$chemCode]) {
            $chemCode = strtoupper(trim($chemCode));
            if (str_contains($chemCode, '0574') || str_contains($chemCode, '0507')) {
                $hasChemKey = true;
                break;
            }
        }

        if ($dyesProcess !== 'Cation Dyes' && $isDisperse && $hasChemKey) {
            $dyesProcess = 'Disperse Dyes';
        }

        return $dyesProcess;
    }

    /**
     * totalD — tổng khối lượng tối đa 9 dòng dye (b24-warehouse-routing.md Mục 4, qrExtra).
     */
    protected function computeTotalD(string $rawDye): float
    {
        $total = 0.0;
        foreach ($this->parseChemLines($rawDye) as [, $dyeWeight]) {
            $total += (float) str_replace(',', '.', $dyeWeight);
        }
        return $total;
    }

    /**
     * Tương đương VBA Format(value, "0.###") — tối đa 3 chữ số thập phân, TRIM số 0 thừa
     * (khác number_format cố định luôn 3 chữ số). VD: 4.25 -> "4.25" (không phải "4.250"),
     * 0 -> "0" (không phải "0.000").
     */
    protected function formatVbaDecimal(float $value): string
    {
        $formatted = number_format($value, 3, '.', '');
        $formatted = rtrim($formatted, '0');
        $formatted = rtrim($formatted, '.');
        return $formatted === '' ? '0' : $formatted;
    }

    /**
     * Dựng lệnh TSPL cho tem 70x100mm — đối chiếu NGUYÊN VĂN nội dung hiển thị trên
     * sheet "DF_WEIGHING_SLIP" mà Mod_printslip.PrintSlip_70x100 điền vào rồi in
     * (trích olevba trực tiếp, dòng 1385-1775, xác nhận 2026-07-19):
     *   B3=color, B4=product_code, F3=machine, G3=tank, H3=level,
     *   B5:D13=bảng dye (rack/mã/khối lượng, tối đa 9 dòng), F5:H13=bảng chem (tương tự),
     *   B14:D14="QR CAN THUOC NHUOM", F14:H14="QR CAN CHAT TRO",
     *   B16:D22=ảnh QR dye, F16:H22=ảnh QR chem, G1:H1=ảnh QR thứ 3 (PROCESS/EXTRA/FB tùy B24),
     *   B24=chuỗi định tuyến kho (vd "THUNG SAT CAO, MAY E13, MAY A11"),
     *   D1=mã khu vực ngắn (vd "E13"/"JIT1").
     *
     * LƯU Ý QUAN TRỌNG (báo người dùng, KHÔNG tự suy đoán thêm): đây là nội dung/thứ tự
     * trường ĐÚNG theo VBA, nhưng vị trí pixel/độ rộng cột/font chữ chính xác của sheet
     * Excel gốc KHÔNG có trong mã VBA (chỉ có trong thiết kế visual của sheet, chưa mở
     * được để đo) — tọa độ TEXT/QRCODE bên dưới là bố cục HỢP LÝ tự thiết kế lại cho vừa
     * khổ 70x100mm, không phải sao chép pixel-chính-xác từ file gốc. Cần xem qua
     * LabelPreview rồi phản hồi để chỉnh lại vị trí nếu chưa đúng ý.
     */
    public function buildTsplLabel70x100(
        \App\Models\MachineDispatch $dispatch,
        ?\App\Models\ProductionBatch $batch,
        ?\App\Models\RoutingDecision $decision,
        string $qrDyePayload,
        string $qrChemPayload,
        string $qrModePayload,
        string $qrModeType
    ): string {
        $esc = fn ($v) => str_replace('"', '', (string) $v);

        $color = $esc($batch->color ?? '');
        $productCode = $esc($batch->product_code ?? '');
        $machine = $esc($batch && $batch->machine ? $batch->machine->code : '');
        $tank = $esc($batch && $batch->tank ? $batch->tank->code : '');
        $level = $esc($batch->level_code ?? '');
        $b24Route = $esc($decision->route ?? '');
        $d1Zone = $esc($decision->area_label ?? '');

        $dyeRows = $this->parseWeighingLines($dispatch->raw_qr_dye ?? '');
        $chemRows = $this->parseWeighingLines($dispatch->raw_qr_chemical ?? '');

        $lines = [];
        $lines[] = 'SIZE 70 mm, 100 mm';
        $lines[] = 'GAP 3 mm, 0 mm';
        $lines[] = 'DIRECTION 1,0';
        $lines[] = 'REFERENCE 0,0';
        $lines[] = 'CLS';

        // Khu vực định tuyến kho (D1/B24) + QR mode — đặt TRÊN CÙNG, đúng vị trí D1/G1:H1
        // trong VBA (row 1 = trên cùng sheet).
        if ($d1Zone !== '') {
            $lines[] = "TEXT 10,10,\"3\",0,1,1,\"KHU: {$d1Zone}\"";
        }
        $lines[] = $this->tsplQrCommand(440, 10, 3, $qrModePayload);
        $lines[] = "TEXT 440,120,\"1\",0,1,1,\"{$qrModeType}\"";

        // Header: màu / mã hàng / máy / thùng / mức
        $lines[] = "TEXT 10,90,\"3\",0,1,1,\"MAU: {$color}\"";
        $lines[] = "TEXT 10,115,\"2\",0,1,1,\"MA HANG: {$productCode}\"";
        $lines[] = "TEXT 10,140,\"2\",0,1,1,\"MAY: {$machine}  THUNG: {$tank}  MUC: {$level}\"";

        // Bảng cân — dye bên trái, chem bên phải, tối đa 9 dòng mỗi bên (đúng VBA rows 5-13)
        $lines[] = 'TEXT 10,168,"1",0,1,1,"RACK  MA HOA CHAT  KL(g)"';
        $lines[] = 'TEXT 290,168,"1",0,1,1,"RACK  MA HOA CHAT  KL(g)"';
        $rowY = 188;
        for ($i = 0; $i < 9; $i++) {
            if (isset($dyeRows[$i])) {
                $r = $dyeRows[$i];
                $txt = $esc("{$r['rack']}  {$r['code']}  {$r['weight']}");
                $lines[] = "TEXT 10,{$rowY},\"1\",0,1,1,\"{$txt}\"";
            }
            if (isset($chemRows[$i])) {
                $r = $chemRows[$i];
                $txt = $esc("{$r['rack']}  {$r['code']}  {$r['weight']}");
                $lines[] = "TEXT 290,{$rowY},\"1\",0,1,1,\"{$txt}\"";
            }
            $rowY += 20;
        }

        // Tiêu đề + ảnh QR dye/chem (đúng VBA: title ngay trên ảnh QR, không phải trên bảng)
        $titleY = $rowY + 10;
        $lines[] = "TEXT 10,{$titleY},\"2\",0,1,1,\"QR CAN THUOC NHUOM\"";
        $lines[] = "TEXT 290,{$titleY},\"2\",0,1,1,\"QR CAN CHAT TRO\"";

        $qrY = $titleY + 25;
        $lines[] = $this->tsplQrCommand(10, $qrY, 5, $qrDyePayload);
        $lines[] = $this->tsplQrCommand(290, $qrY, 5, $qrChemPayload);

        // Chuỗi định tuyến kho đầy đủ (B24) — dòng cuối, đúng vị trí cuối sheet trong VBA
        $routeY = $qrY + 180;
        if ($b24Route !== '') {
            $lines[] = "TEXT 10,{$routeY},\"2\",0,1,1,\"{$b24Route}\"";
        }

        $lines[] = 'PRINT 1,1';

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * QRCODE x,y,ECC,cell_width,mode,rotation,"content" — nội dung QR có thể chứa CRLF
     * (buildChemPayload/buildProcessPayload dùng CRLF phân dòng) nên KHÔNG lọc bỏ CRLF,
     * chỉ lọc dấu ngoặc kép để không phá cú pháp lệnh TSPL.
     */
    protected function tsplQrCommand(int $x, int $y, int $cellWidth, string $content): string
    {
        $safe = str_replace('"', '', $content);
        return "QRCODE {$x},{$y},H,{$cellWidth},A,0,\"{$safe}\"";
    }
}
