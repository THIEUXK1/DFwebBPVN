<?php
// backend/app/Services/PrintJobEventService.php
//
// Nhật ký bất biến từng bước vòng đời PrintJob (yêu cầu 2026-07-18: phân biệt rõ
// "B. Lịch sử print job" khỏi "A. Lịch sử lệnh chuyển tới trạm in" và "C. Lịch sử in
// thực tế"). 10 loại event cố định — xem PrintJobEvent::TYPES. Ghi chú về các điểm
// gộp so với đặc tả gốc (do kiến trúc hiện tại là 1 bước "xác nhận = tạo lệnh in",
// không có bước riêng "đưa vào hàng chờ" rồi "yêu cầu in" tách biệt):
//   - JOB_CREATED, JOB_VISIBLE_AT_STATION, PRINTER_SELECTED, PRINT_REQUESTED: cùng
//     xảy ra tại 1 thời điểm (ConfirmDispatchService::createPrintJob) vì confirm()
//     hiện là hành động atomic duy nhất tạo ra PrintJob — không có pha "đã hiện ở
//     trạm nhưng chưa yêu cầu in" thật sự tồn tại trong luồng nghiệp vụ hiện tại.
//   - SENT_TO_PRINTER: Agent không có round-trip báo "tôi sắp gửi xuống máy in" trước
//     khi in (để tránh thêm 1 lượt gọi mạng mỗi lần in) — log cùng lúc với kết quả ack
//     (PRINT_SUCCEEDED/PRINT_FAILED), ngay trước event kết quả.

namespace App\Services;

class PrintJobEventService
{
    public function log(
        string $printJobId,
        string $eventType,
        array $context = []
    ): \App\Models\PrintJobEvent {
        return \App\Models\PrintJobEvent::create([
            'print_job_id' => $printJobId,
            'dispatch_id' => $context['dispatch_id'] ?? null,
            'production_job_id' => $context['production_job_id'] ?? null,
            'station_id' => $context['station_id'] ?? null,
            'agent_id' => $context['agent_id'] ?? null,
            'printer_name' => $context['printer_name'] ?? null,
            'event_type' => $eventType,
            'event_time' => $context['event_time'] ?? now(),
            'error_message' => $context['error_message'] ?? null,
            'correlation_id' => $context['correlation_id'] ?? null,
        ]);
    }

    /**
     * true nếu event_type này đã từng được ghi cho print job — dùng để chống ghi
     * trùng AGENT_CLAIMED mỗi vòng poll 500ms của Worker.cs khi job vẫn PENDING
     * (in lỗi, đang chờ Agent retry) — chỉ log lần "claim" đầu tiên.
     */
    public function hasLogged(string $printJobId, string $eventType): bool
    {
        return \App\Models\PrintJobEvent::where('print_job_id', $printJobId)
            ->where('event_type', $eventType)
            ->exists();
    }
}
