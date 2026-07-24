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
     * qrChem = VD### & CRLFx2 & tank_ký_tự_đầu & CRLFx2 & "#"&color&"-"&code & CRLFx2 &
     *          random(1-9) & CRLFx2 & level & (lặp CRLFx2+mã hóa chất+CRLFx2+khối lượng
     *          cho mỗi dòng có dữ liệu, tối đa 9 dòng, parse từ raw_qr_chemical
     *          theo cùng quy tắc ParseQR của VBA: tách chuỗi theo "-", bước nhảy 3) & "#"
     * (CRLFx2 = 1 dòng trống giữa mỗi trường — lệch có chủ đích so với VBA gốc chỉ 1
     * CRLF, xem ghi chú trong thân hàm; "#" cuối KHÔNG có CRLF trước nó.)
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

        // Yêu cầu 2026-07-22: giống qrProcess (buildProcessPayload) — tem thật đang dùng
        // có dòng trống xen giữa MỌI trường (không phải 1 vbCrLf đơn như mã VBA gốc trích
        // xuất được). Chủ động lệch so với nguồn cho khớp thực tế đang vận hành. Riêng "#"
        // cuối vẫn nối TRỰC TIẾP vào dòng khối lượng cuối, KHÔNG có dòng trống trước nó
        // (đúng `qrChem = qrChem & "#"`, xác nhận qua ví dụ tem thật "...250#" liền nhau).
        return implode(self::CRLF . self::CRLF, $lines) . '#';
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
            // Yêu cầu 2026-07-22: người dùng đối chiếu tem thật đang dùng thấy có dòng
            // trống xen giữa 3 phần (khác VBA gốc chỉ dùng 1 vbCrLf — đã xác nhận lại qua
            // trích xuất trực tiếp Mod_printslip dòng 248-250). Chủ động chọn LỆCH so với
            // mã nguồn để khớp đúng cách hiển thị tem thật đang vận hành — chỉ áp dụng cho
            // mode PROCESS (không đổi qrDye/qrChem/qrExtra/qrFB, không có yêu cầu tương tự).
            $crlf2 = self::CRLF . self::CRLF;
            return "{$color}-{$code} {$timestamp}" . $crlf2 . "{$machine}-{$tank}-{$newLevel}" . $crlf2 . $dyesProcess;
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
     * Toạ độ dưới đây (2026-07-22, thay bản trước đo qua Excel COM và có lề trái/phải dư
     * do quy đổi Fit-to-page) đã được ĐỐI CHIẾU TRỰC TIẾP với ẢNH TEM IN THẬT từ VBA do
     * người dùng cung cấp (2026-07-21) — sửa 2 lỗi lệch so với bản đo Excel:
     *   1. Bảng cân mỗi hàng đúng ~41 dot (5.1mm) — bản đo Excel ban đầu tính nhầm 20 dot,
     *      dồn toàn bộ phần dưới bảng (tiêu đề, QR, dòng định tuyến) lên sai vị trí.
     *   2. Layout dùng ĐỦ chiều rộng tem 0-560 dot (70mm x 8dot/mm), KHÔNG có lề trái/phải
     *      dư 42/43 dot như bản đo qua Fit-to-page — khớp đúng ảnh in thật không có viền
     *      trắng 2 bên.
     * Ô Màu (B3) và Mã hàng (B4) gộp chung 1 khung KHÔNG có đường kẻ ngang giữa (ảnh thật
     * xác nhận không có đường kẻ đó dù là 2 dòng dữ liệu khác nhau). QR cell_width tăng
     * 3->6 để khớp đúng kích thước QR to trên ảnh thật (bản cũ QR nhỏ hơn nhiều so với
     * khoảng trống dành cho nó).
     *
     * Ánh xạ ô gốc (không đổi so với ghi chú cũ):
     *   B1="DF_WEIGHING_SLIP" | D1=khu vực (font to nhất tem) | G1:H1=QR chế độ
     *   B3:D3=MÀU | F3=MÁY | G3=THÙNG | H3=MỨC (nhỏ hơn)
     *   B4:D4=MÃ HÀNG (cùng khung với B3, không có đường kẻ giữa)
     *   B5:D13=bảng dye (rack/mã/KL, 9 dòng) | F5:H13=bảng chem (tương tự)
     *   B14:D14="QR CAN THUOC NHUOM" | F14:H14="QR CAN CHAT TRO"
     *   B16:D22=QR dye | F16:H22=QR chem | B24=chuỗi định tuyến kho B24
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

        // Khung kẻ ô — đúng ảnh tem in thật (mọi trường đều có viền). BOX x1,y1,x2,y2,dot
        // = khung ngoài từng ô; BAR x,y,w,h = mảng đặc 2 dot dùng làm đường kẻ mảnh (TSPL
        // không có lệnh LINE riêng).
        $lines[] = 'BOX 0,0,206,112,2';      // ô watermark DF_WEIGHING_SLIP
        $lines[] = 'BOX 206,0,391,112,2';    // ô mã khu vực (D1)
        $lines[] = 'BOX 391,0,560,112,2';    // ô QR mode
        $lines[] = 'BOX 0,114,278,200,2';    // ô màu + mã hàng (color/code gộp, không có đường ngang giữa — đúng ảnh)
        $lines[] = 'BOX 293,114,391,200,2';  // ô máy
        $lines[] = 'BOX 391,114,498,200,2';  // ô thùng
        $lines[] = 'BOX 498,114,560,200,2';  // ô mức
        $lines[] = 'BOX 0,200,278,569,2';    // khung ngoài bảng dye
        $lines[] = 'BOX 293,200,560,569,2';  // khung ngoài bảng chem
        for ($i = 1; $i < 9; $i++) {
            $y = 200 + $i * 41;
            $lines[] = "BAR 0,{$y},278,2";   // kẻ ngang giữa các dòng — dye
            $lines[] = "BAR 293,{$y},267,2"; // kẻ ngang giữa các dòng — chem
        }
        $lines[] = 'BAR 110,200,2,369';  // kẻ dọc dye: rack | ma | kl
        $lines[] = 'BAR 206,200,2,369';
        $lines[] = 'BAR 391,200,2,369';  // kẻ dọc chem: rack | ma | kl
        $lines[] = 'BAR 498,200,2,369';

        // Hàng 1 (y=0-112): B1 = watermark tên sheet gốc (in trên MỌI tem thật, xác nhận
        // qua ảnh — không phải dữ liệu nghiệp vụ nhưng VBA luôn in nên giữ nguyên).
        // D1 (merged D1:F1, x=206-391) = mã khu vực, chữ TO NHẤT tem (font "4" — khớp
        // size 36 trong Excel). G1:H1 (x=391-560) = ảnh QR thứ 3 (PROCESS/EXTRA/FB tùy B24).
        $lines[] = 'TEXT 15,40,"2",0,1,1,"DF_WEIGHING_SLIP"';
        if ($d1Zone !== '') {
            $lines[] = "TEXT 220,20,\"4\",0,1,1,\"{$d1Zone}\"";
        }
        $lines[] = $this->tsplQrCommand(400, 5, 3, $qrModePayload);
        $lines[] = "TEXT 400,100,\"1\",0,1,1,\"{$qrModeType}\"";

        // Hàng 3 (y=114-156): color(B3, x=0-206) + machine(F3, x=293-391) +
        // tank(G3, x=391-498) + level(H3, x=498-560) — CÙNG 1 HÀNG trong VBA (không
        // phải 3 dòng riêng như bản cũ). color/machine/tank font "3" (size 20 bold),
        // level font "2" (size 14, nhỏ hơn — đúng khác biệt trong Excel gốc).
        $lines[] = "TEXT 5,120,\"3\",0,1,1,\"{$color}\"";
        $lines[] = "TEXT 300,120,\"3\",0,1,1,\"{$machine}\"";
        $lines[] = "TEXT 400,120,\"3\",0,1,1,\"{$tank}\"";
        $lines[] = "TEXT 500,125,\"2\",0,1,1,\"{$level}\"";

        // Hàng 4 (y=156-200): code (B4, merged B4:D4, x=0-278) — riêng 1 hàng, chỉ nửa
        // trái tem (khác bản cũ ghép chung "MA HANG" vào dòng máy/thùng/mức).
        $lines[] = "TEXT 5,165,\"3\",0,1,1,\"{$productCode}\"";

        // Bảng cân — dye bên trái (B5:D13, x=0-278), chem bên phải (F5:H13, x=293-560),
        // 9 hàng, MỖI HÀNG 41 dot (~5.1mm, đo thật — không phải 20 dot như bản cũ).
        // KHÔNG có dòng tiêu đề cột kiểu "RACK/MA/KL" phía trên bảng — ảnh tem thật xác
        // nhận bảng đi thẳng vào dữ liệu ngay dưới ô mã máy/thùng/mức (bản trước tự thêm
        // dòng tiêu đề này, không có thật — đã bỏ).
        // 3 cột riêng mỗi bên (rack | mã | khối lượng) — KHÔNG gộp 1 chuỗi như bản trước,
        // vì đã có đường kẻ dọc BAR ở x=110/206 (dye) và x=391/498 (chem) cắt ngang đúng
        // ranh giới cột; gộp chuỗi sẽ khiến chữ tràn qua đường kẻ sai chỗ. Cột khối lượng
        // canh phải trong ảnh thật — TSPL không có right-align dựng sẵn theo độ rộng biến
        // đổi, dùng str_pad canh phải theo số ký tự (đủ tốt cho font đơn cách monospace
        // của máy in TSPL, không cần đo pixel chính xác).
        $tableTop = 200;
        $rowHeight = 41;
        $padWeight = fn ($w) => str_pad((string) $w, 6, ' ', STR_PAD_LEFT);
        for ($i = 0; $i < 9; $i++) {
            $rowY = $tableTop + $i * $rowHeight + 12;
            if (isset($dyeRows[$i])) {
                $r = $dyeRows[$i];
                $lines[] = "TEXT 5,{$rowY},\"1\",0,1,1,\"{$esc($r['rack'])}\"";
                $lines[] = "TEXT 115,{$rowY},\"1\",0,1,1,\"{$esc($r['code'])}\"";
                $lines[] = "TEXT 211,{$rowY},\"1\",0,1,1,\"{$esc($padWeight($r['weight']))}\"";
            }
            if (isset($chemRows[$i])) {
                $r = $chemRows[$i];
                $lines[] = "TEXT 298,{$rowY},\"1\",0,1,1,\"{$esc($r['rack'])}\"";
                $lines[] = "TEXT 396,{$rowY},\"1\",0,1,1,\"{$esc($r['code'])}\"";
                $lines[] = "TEXT 503,{$rowY},\"1\",0,1,1,\"{$esc($padWeight($r['weight']))}\"";
            }
        }
        $tableBottom = $tableTop + 9 * $rowHeight; // = 569, khớp đúng row14 top đo được

        // Tiêu đề (row14, y=569-598) ngay trên ảnh QR — đúng VBA (title SAU bảng, KHÔNG
        // ngay sau bảng cách 10 dot như bản cũ).
        $lines[] = "TEXT 5,{$tableBottom},\"2\",0,1,1,\"QR CAN THUOC NHUOM\"";
        $titleY2 = $tableBottom;
        $lines[] = "TEXT 298,{$titleY2},\"2\",0,1,1,\"QR CAN CHAT TRO\"";

        // Ảnh QR dye/chem (row16:22, y=605-763, cao 158 dot ~19.8mm) — cell_width tăng
        // từ 5 lên 6 để lấp gần hết chiều cao thật của vùng (bản cũ 5 để nhỏ hơn nhiều
        // so với khoảng trống thật, do cả khối bị tính sai vị trí phía trên).
        $qrTop = 605;
        $lines[] = $this->tsplQrCommand(5, $qrTop, 6, $qrDyePayload);
        $lines[] = $this->tsplQrCommand(298, $qrTop, 6, $qrChemPayload);

        // Chuỗi định tuyến kho đầy đủ (B24, row24, y=767-800) — dòng cuối cùng của tem,
        // đúng vị trí cuối sheet đo được (không phải "qrY+180" ước lượng như bản cũ).
        if ($b24Route !== '') {
            $lines[] = "TEXT 5,772,\"2\",0,1,1,\"{$b24Route}\"";
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
