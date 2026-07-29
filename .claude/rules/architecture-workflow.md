# Architecture Workflow - Quy trình Làm việc & Kiến trúc Chung

Tài liệu này bổ sung chi tiết vận hành cho mục 4 "Quy trình Phát triển" trong `.claude/CLAUDE.md`, dùng làm checklist thao tác cho mọi Dev/Agent trước và sau khi sửa code.

---

## 1. Quy trình bắt buộc trước khi viết code

1. Xác định tính năng thuộc phân hệ nào trong 12 phân hệ (`.claude/business-modules.md`, M01-M12).
2. Tra `.claude/source-traceability.md` để biết mã VBA/Access gốc tương ứng (tên workbook `.xlsm`, bảng Access, hàm cụ thể) — không viết lại logic nghiệp vụ mà không đối chiếu nguồn.
3. Kiểm tra `.claude/architecture-decisions.md` xem có ADR nào chi phối phần việc này không (ví dụ: chạm tới realtime → đọc ADR-008/009/010; chạm tới thiết bị → đọc ADR-002).
4. Nếu thay đổi liên quan tới bảng dữ liệu hoặc migration, tham khảo vai trò [[database-auditor]] (`.claude/agents/database-auditor.md`) và `.claude/rules/database-safety.md`.
5. Nếu thay đổi có tính chất kiến trúc mới (thêm service, đổi giao thức, đổi luồng dữ liệu), tham khảo vai trò [[system-architect]] (`.claude/agents/system-architect.md`) trước khi triển khai.

## 2. Ranh giới kiến trúc phân lớp (Layered Architecture) — bắt buộc tuân thủ

```
Browser/Tablet --HTTPS/JWT--> Backend API (Laravel) --SQL/Transaction--> PostgreSQL 15
Local Device Agent (.NET) --WebSocket/HTTPS--> Backend API
Local Device Agent --Serial Port--> Cân điện tử
Local Device Agent --TSPL/USB/Network--> Máy in TSC
```

- Trình duyệt Web **không bao giờ** giao tiếp trực tiếp với cân hoặc máy in — mọi thao tác đi qua Local Agent (ADR-002).
- Giao tiếp Backend → Frontend thời gian thực dùng **SSE** (không dùng WebSocket server riêng — ADR-008), có Fallback Polling khi mất kết nối quá 10 giây (ADR-010).
- Mọi sự kiện realtime phát ra phải đi qua **Transactional Outbox** (`app.realtime_events`) trong cùng transaction với thay đổi dữ liệu nghiệp vụ (ADR-009) — tuyệt đối không phát sự kiện trước khi transaction commit.

## 3. Quy trình sau khi sửa code

1. Chạy toàn bộ Unit Test và Integration Test của phân hệ liên quan (`df-backend/tests`, xem `.claude/testing-strategy.md`).
2. Nếu thay đổi liên quan tới logic tính toán công thức/dung sai/hệ số: bắt buộc chạy đối soát **Golden Master Test** (sai số cân cho phép ±0.000001).
3. Nếu thay đổi liên quan tới migration dữ liệu: chạy `04_validation_queries.sql` hoặc script tương đương để đối soát số dòng/tổng khối lượng giữa staging và `app`.
4. Cập nhật nhật ký phiên vào `.claude/session-log.md` theo cấu trúc quy định của tài liệu đó.
5. Nếu tính năng chạm tới hành động nhạy cảm (duyệt công thức, override dung sai, reprint, force unlock, sửa Troubleshooting KB), xác nhận Audit Log JSONB (`before_data`/`after_data`) đã được ghi đúng — xem `.claude/rules/database-safety.md` mục Audit Log.

## 4. Quy tắc trạng thái nghiệp vụ (State Machine)
Mọi thực thể có vòng đời (Batch, Recipe Version, Weighing Session, Material Transfer, Feed Operation...) phải mô hình hóa bằng state machine tường minh, không dùng cờ boolean rời rạc thay thế. Tham chiếu các state machine đã chốt:
- Recipe: `draft → submitted → approved → obsolete`
- Batch: `NEW → WAITING_CONFIRM → READY_TO_WEIGH → WEIGHING → WEIGHED → READY_TO_SEND → SENT → DONE`
- Weighing Session: `PENDING → WEIGHING → OVERRIDE_REQUIRED → CONFIRMED → PRINTED`
- Material Transfer: `ready_for_transfer → in_transit → arrived_at_tank → accepted/rejected`

Khi thêm state mới hoặc transition mới, cập nhật `.claude/state-machines.md` và thông báo trong `session-log.md`.

## 5. Khi phát hiện xung đột giữa tài liệu và code thực tế
Nếu code hiện tại không khớp với tài liệu trong `.claude/` (ví dụ tài liệu ghi "đã hoàn thành" nhưng chức năng không tồn tại), **ưu tiên tin vào code/git log hiện tại**, báo lại cho người dùng, và đề xuất cập nhật tài liệu — không tự ý sửa tài liệu để "khớp" mà không xác nhận.
