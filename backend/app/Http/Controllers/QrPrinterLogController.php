<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Nhật ký GỬI / IN của màn hình /qr-printer (dựng lại UserForm `scaleform` của workbook
 * "QR PRINTER-send to access- NEW 9ROWS BIG QR.xlsm").
 *
 * Bản VBA gốc KHÔNG có màn hình lịch sử nào — đây là phần bổ sung cho bản web (yêu cầu
 * 07/08/2026): sau khi bấm SEND/print, thao tác viên cần tra lại "vừa nãy đã gửi/in cái gì".
 *
 * Lưu vào `app.audit_logs` chứ không tạo bảng mới: bảng này vốn đã là nơi lưu vết append-only
 * của hệ thống (CLAUDE.md mục 5 — in tem phải ghi Audit Log), có sẵn `before_data`/`after_data`
 * kiểu JSONB và cột `user_id` nullable nên trang công khai (không đăng nhập) ghi được. Đổi lại
 * không cần migration trên Production ở giữa Phase 12.
 *
 * Cùng đánh đổi như các endpoint /public khác: `auth()->id()` trả null nên bản ghi không có
 * định danh người thực hiện, chỉ có IP máy trạm (`client_ip` do AuditLog tự điền).
 */
class QrPrinterLogController extends Controller
{
    /** Ánh xạ `kind` của client sang giá trị cột `action` trong audit_logs. */
    private const ACTIONS = [
        'SEND' => 'QR_PRINTER_SEND',
        'PRINT' => 'QR_PRINTER_PRINT',
    ];

    private const ENTITY_TYPE = 'qr_printer_form';

    /** Trần số dòng trả về một lần — màn lịch sử chỉ để tra cứu nhanh, không phải báo cáo. */
    private const MAX_ROWS = 200;

    /**
     * Ghi một lần bấm SEND hoặc print.
     *
     * Frontend gọi kiểu "bắn rồi quên": lỗi ghi nhật ký KHÔNG được làm hỏng việc gửi đơn/in tem
     * đã thành công, nên mọi phản hồi ở đây chỉ mang tính thông báo.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'kind' => 'required|string|in:SEND,PRINT',
            'color' => 'nullable|string|max:100',
            'code' => 'nullable|string|max:100',
            'machine' => 'nullable|string|max:50',
            'tank' => 'nullable|string|max:50',
            'lv' => 'nullable|string|max:50',
            'batch_id' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:255',
            'dye' => 'nullable|array|max:9',
            'dye.*.rack' => 'nullable|string|max:50',
            'dye.*.code' => 'nullable|string|max:100',
            'dye.*.weight' => 'nullable|string|max:50',
            'chem' => 'nullable|array|max:9',
            'chem.*.rack' => 'nullable|string|max:50',
            'chem.*.code' => 'nullable|string|max:100',
            'chem.*.weight' => 'nullable|string|max:50',
        ]);

        // Bỏ các dòng trống trước khi lưu: form luôn có đúng 9 dòng mỗi khối, đơn thật thường
        // chỉ dùng 2-4 dòng nên lưu cả 9 sẽ phình JSON mà không thêm thông tin gì.
        $rows = fn (string $key) => array_values(array_filter(
            $data[$key] ?? [],
            fn ($r) => trim((string) ($r['code'] ?? '')) !== '' || trim((string) ($r['weight'] ?? '')) !== ''
        ));

        $payload = [
            'color' => $data['color'] ?? '',
            'code' => $data['code'] ?? '',
            'machine' => $data['machine'] ?? '',
            'tank' => $data['tank'] ?? '',
            'lv' => $data['lv'] ?? '',
            'batch_id' => $data['batch_id'] ?? null,
            'note' => $data['note'] ?? null,
            'dye' => $rows('dye'),
            'chem' => $rows('chem'),
        ];

        $log = AuditLog::create([
            'action' => self::ACTIONS[$data['kind']],
            'entity_type' => self::ENTITY_TYPE,
            // Ưu tiên id lô thật để lần ngược được sang production_batches; chưa gửi (chỉ in)
            // thì dùng cặp COLOR-CODE cho dễ đối chiếu bằng mắt.
            'entity_id' => $data['batch_id'] ?? trim(($payload['color'] ?? '') . '-' . ($payload['code'] ?? ''), '-'),
            'after_data' => $payload,
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'data' => ['id' => $log->id],
        ], 201);
    }

    /**
     * Danh sách nhật ký, mới nhất lên đầu.
     *
     * Cố ý KHÔNG lọc theo từ khoá ở đây: nội dung nằm trong cột JSON nên lọc phía DB sẽ phải
     * viết SQL riêng cho từng loại DB. Trang lịch sử lọc phía trình duyệt trên tập đã trả về,
     * giống cách /print-sent-log đang làm.
     */
    public function index(Request $request)
    {
        $request->validate([
            'kind' => 'sometimes|string|in:SEND,PRINT',
            'days' => 'sometimes|integer|min:1|max:90',
        ]);

        $days = (int) $request->input('days', 7);

        $query = AuditLog::query()
            ->where('entity_type', self::ENTITY_TYPE)
            ->where('created_at', '>=', Carbon::now()->subDays($days));

        if ($request->filled('kind')) {
            $query->where('action', self::ACTIONS[$request->input('kind')]);
        } else {
            $query->whereIn('action', array_values(self::ACTIONS));
        }

        $logs = $query->orderByDesc('id')->limit(self::MAX_ROWS)->get();

        return response()->json([
            'status' => 'SUCCESS',
            'data' => $logs->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'kind' => $log->action === self::ACTIONS['PRINT'] ? 'PRINT' : 'SEND',
                'logged_at' => $log->created_at,
                'client_ip' => $log->client_ip,
                'entity_id' => $log->entity_id,
                ...($log->after_data ?? []),
            ]),
        ]);
    }
}
