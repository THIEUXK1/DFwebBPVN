<?php
// backend/database/migrations/2026_08_04_140000_rename_vd_machines_to_two_digit_codes.php
//
// Đưa danh mục máy về ĐÚNG quy ước của VBA gốc: VD01-VD18 (2 chữ số).
//
// Bằng chứng từ workbook "C3 grid load row lock id FB -.xlsm" (đối chiếu 2026-08-04):
//   mainform.CommandButton5_Click / subform.CommandButton3_Click:
//       arrVD = Array("VD01","VD02",...,"VD18")
// Còn dạng 3 chữ số chỉ là ĐỊNH DẠNG ĐƯỜNG TRUYỀN khi dựng chuỗi QR gửi máy pha màu —
// chính VBA cũng tự quy đổi lúc in: "VD" & Format(Val(Mid(s,3)),"000")
// (xem QrPayloadService::normalizeVdCode). Danh mục web trước đây seed nhầm mã 3 chữ số
// làm mã hiển thị, nên khi người vận hành thêm tay máy "VD04" theo đúng thói quen ngoài
// xưởng thì hệ thống đẻ ra bản ghi máy THỨ HAI cho cùng một máy vật lý: số liệu bị chia
// đôi giữa VD004 và VD04, và mọi lần quét QR "VD04" từ đó chuyển hướng sang bản ghi rỗng.
//
// KHÔNG đổi id máy — mọi khóa ngoại (production_batches, machine_chemical_channels,
// chemical_call_requests, tanks...) giữ nguyên. Chỉ đổi cột `code`.
//
// Các chỗ đã kiểm tra là KHÔNG vỡ vì đều so theo SỐ hoặc tự chuẩn hóa lại:
//   - WarehouseRoutingService (so sánh số thứ tự máy), PrintOrderEntry.vue,
//     MachineIdBoard.vue, ChemicalCall*.vue (sắp xếp theo số).
//   - QrPayloadService::normalizeVdCode + dispatchSlipPrint.ts: tự pad về 3 chữ số khi
//     dựng QR/phiếu, không phụ thuộc mã trong danh mục.
//   - jit_routing_rules.machine_code khớp với MachineNo của hệ BPDB (SQL Server), là cột
//     ĐỘC LẬP với machines.code — không đụng tới.
//   - MachineChemicalChannel::qrImageUrl() ĐÃ được sửa cùng lúc để chuẩn hóa về 3 chữ số
//     khi tra tên file ảnh QR thật trong public/chemical-qr/ (38 file đặt tên VD001-VD018).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Máy thêm tay bị trùng => máy gốc tương ứng (theo số thứ tự máy). */
    private const GOP = [
        'VD04' => 'VD004',
        'VD10' => 'VD010',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            foreach (self::GOP as $maTrung => $maGoc) {
                $trung = DB::table('machines')->where('code', $maTrung)->first();
                $goc = DB::table('machines')->where('code', $maGoc)->first();

                if (!$trung || !$goc) {
                    continue;
                }

                // Đơn sản xuất đang treo ở bản ghi trùng -> chuyển về máy gốc. Thùng của bản
                // ghi trùng chưa từng được đơn nào tham chiếu (đã kiểm tra trước khi chạy),
                // nhưng vẫn quy đổi tank_id theo MÃ thùng để an toàn nếu có phát sinh.
                $thungTrung = DB::table('tanks')->where('machine_id', $trung->id)->get();
                foreach ($thungTrung as $t) {
                    $thungGoc = DB::table('tanks')
                        ->where('machine_id', $goc->id)
                        ->where('code', $t->code)
                        ->first();

                    if ($thungGoc) {
                        DB::table('production_batches')
                            ->where('tank_id', $t->id)
                            ->update(['tank_id' => $thungGoc->id]);
                    }
                }

                DB::table('production_batches')
                    ->where('machine_id', $trung->id)
                    ->update(['machine_id' => $goc->id]);

                // Thùng của bản ghi trùng: chỉ xóa khi KHÔNG còn đơn nào tham chiếu. Đây là
                // master data sinh ra do chính lỗi trùng máy, không phải dữ liệu lịch sử.
                $conThamChieu = DB::table('production_batches')
                    ->whereIn('tank_id', $thungTrung->pluck('id'))
                    ->exists();

                if (!$conThamChieu) {
                    DB::table('tanks')->where('machine_id', $trung->id)->delete();
                }

                // Không xóa bản ghi máy trùng (realtime_events còn trỏ tới): đổi mã để nhả
                // chỗ cho mã chuẩn, và tắt hoạt động theo quy tắc soft delete của dự án.
                DB::table('machines')
                    ->where('id', $trung->id)
                    ->update(['code' => $maTrung . '-GOP', 'is_active' => false]);
            }

            // Đổi VD001-VD018 -> VD01-VD18. Chỉ đụng mã đúng dạng VD + 3 chữ số để không
            // chạm tới VDG01-VDG08 hay bất kỳ mã máy nào khác.
            $machines = DB::table('machines')
                ->where('code', 'like', 'VD___')
                ->get(['id', 'code']);

            foreach ($machines as $m) {
                $so = substr($m->code, 2);
                if (!ctype_digit($so)) {
                    continue;
                }

                $moi = 'VD' . str_pad((string) (int) $so, 2, '0', STR_PAD_LEFT);
                if ($moi === $m->code) {
                    continue;
                }

                DB::table('machines')->where('id', $m->id)->update(['code' => $moi]);
            }
        });
    }

    // Không rollback tự động: bước gộp đã dời đơn sang máy gốc và không lưu lại được ánh xạ
    // ngược (đơn nào vốn thuộc bản ghi trùng). Muốn quay lại phải phục hồi từ backup.
    public function down(): void
    {
        // no-op
    }
};
