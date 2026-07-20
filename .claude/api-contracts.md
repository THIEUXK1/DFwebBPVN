# API Contract Mục tiêu (api-contracts.md)

Lập 2026-07-17 — Phase C. Thiết kế trước implementation, theo `state-machines.md`/`domain-architecture.md`. Mỗi API mô tả Request/Response/Error/Permission/Idempotency/Concurrency/Audit theo yêu cầu mục 17. Đường dẫn đề xuất — có thể điều chỉnh theo convention Laravel hiện có (`routes/api.php`).

---

## Chemical Call

### `GET /api/chemical-channels` — danh sách channel + status hiện tại
- **Response:** `[{channel_id, machine_code, chemical_code, is_active, current_request: {id,status,requested_at}|null}]`
- **Permission:** `chemical_call.view`
- **Idempotency:** N/A (read-only)
- **Concurrency:** N/A
- **Audit:** Không ghi (read-only)

### `POST /api/chemical-call-requests` — tạo yêu cầu (order)
- **Request:** `{channel_id, idempotency_key}`
- **Response 201:** `{id, status:'ORDERED', requested_at}` | **200** nếu idempotency_key đã tồn tại (trả bản ghi cũ)
- **Error:** `409 CHANNEL_ALREADY_ORDERED` (vi phạm `uq_channel_active_order`), `404 CHANNEL_NOT_FOUND`, `403 CHANNEL_INACTIVE`
- **Permission:** `chemical_call.create`
- **Idempotency:** Bắt buộc `idempotency_key` trong request
- **Concurrency:** Partial unique index `uq_channel_active_order` chặn ở tầng DB, không chỉ tầng app
- **Audit:** `CHEMICAL_CALL_ORDERED`

### `PATCH /api/chemical-call-requests/{id}/acknowledge`
- **Permission:** `chemical_call.acknowledge` (feature-flag)
- **Error:** `409 INVALID_STATE_TRANSITION` nếu không đang ORDERED

### `PATCH /api/chemical-call-requests/{id}/complete`
- **Response:** `{id, status:'DONE', confirmed_at}`
- **Permission:** `chemical_call.complete`
- **Idempotency:** Gọi lại khi đã DONE → 200 no-op
- **Audit:** `CHEMICAL_CALL_DONE`

### `PATCH /api/chemical-call-requests/{id}/cancel`, `/reset`
- **Permission:** `chemical_call.reset` (mặc định feature-flag TẮT — xem `feature-flags.md`)

### `GET /api/chemical-call-requests/{id}/events` — lịch sử
- **Permission:** `chemical_call.view` (hoặc `audit.view` nếu tách riêng)

---

## Production Order

### `POST /api/production-orders`
- **Request:** `{color, product_code, machine_id, tank_id, level_code, client_request_id}`
- **Error:** `409 DUPLICATE_COLOR_CODE` (chưa gửi lần đầu tại đơn khác đang mở)
- **Permission:** `production_order.create`
- **Idempotency:** `client_request_id`

### `POST /api/production-orders/{id}/submit`, `/approve`, `/reject`
- **Permission:** `approve` cần `production_order.approve`
- **Error `approve`:** `422 CAPACITY_RULE_VIOLATION` nếu `MinimumCapacityPolicy` bật và không đạt 250L (chờ CH-BUS-005)

### `POST /api/production-orders/{id}/lock`
- **Response 200:** `{lock_owner_user_id, lock_expires_at}`
- **Error:** `409 LOCK_HELD_BY_OTHER {owner, expires_at}`
- **Permission:** `production_order.approve` (hoặc quyền thao tác đơn tương ứng)
- **Concurrency:** Optimistic version check + DB transaction, xem Mục 6.2 gốc

### `POST /api/production-orders/{id}/lock/renew`, `/release`, `/force-release`
- **`force-release` Permission:** `lock.override` — **bắt buộc audit** (`ORDER_LOCK_OVERRIDDEN`, ghi rõ actor)

### `POST /api/production-orders/{id}/dispatch`
- **Response:** `{dispatch_id}` — idempotent nếu đã dispatch trước đó (trả `dispatch_id` cũ)
- **Permission:** `production_order.approve` hoặc quyền dispatch riêng

---

## QR / Print (Dispatch domain)

### `GET /api/dispatch-queue` — đã có (`MachineDispatchController::index`), giữ nguyên, mở rộng filter `queue_state`

### `GET /api/dispatch-jobs/{id}/preview-payload` — xem trước QR/routing TRƯỚC khi confirm (MỚI, không có trong VBA — B. UX IMPROVEMENT)
- **Response:** `{routing_decision: {mode, route, warnings, needs_manual_review}, qr_preview: {...}}`
- **Permission:** `dispatch.confirm` (cùng quyền xem trước khi làm)

### `POST /api/dispatch-jobs/{id}/confirm` — tương đương `ConfirmRow`
- **Request:** `{idempotency_key}`
- **Response 200:** `{dispatch_id, status:'CONFIRMED', qr_payloads:[...], print_job_id, routing_decision}`
- **Error:** `409 ALREADY_CONFIRMED` (trả kết quả cũ thay vì lỗi cứng — idempotent), `422 ROUTING_MANUAL_REVIEW_REQUIRED` (nếu mode=MANUAL_REVIEW và policy bắt buộc dừng lại chờ người duyệt thay vì tự in)
- **Permission:** `dispatch.confirm`
- **Idempotency:** Bắt buộc — đúng yêu cầu mục 24 test "ConfirmRow gọi hai lần"
- **Concurrency:** `SELECT FOR UPDATE` hoặc `row_version` (bước 3 trong `state-machines.md` Mục 3)
- **Audit:** `DISPATCH_CONFIRMED`

### `PATCH /api/dispatch-jobs/{id}/scale-check`
- **Request:** `{value: boolean}`
- **Permission:** `dispatch.confirm` (cùng nhóm quyền thao tác dòng)
- **Idempotency:** Set lại giá trị hiện tại → no-op

### `POST /api/print-jobs` (tạo thủ công nếu cần in lại — reprint)
- **Request:** `{dispatch_id, reason}` — **`reason` bắt buộc cho reprint** (đúng yêu cầu mục 17 "Reprint có reason và permission")
- **Permission:** `print.reprint` (khác `print.execute` — 2 quyền riêng)
- **Audit:** `PRINT_JOB_CREATED` kèm `reason`

### `POST /api/print-jobs/{id}/retry`, `/cancel`
- **`cancel` Permission:** `print.cancel`

### `GET /api/print-jobs/{id}` — trạng thái
- **Response:** `{status, attempts:[...]}`

---

## Weighing

### `POST /api/scanner/scan` (đã có `ScannerController`) — mở rộng: trả `weighing_job` nếu QR hợp lệ
- **Error:** `409 QR_ALREADY_PROCESSED` nếu job đã COMPLETED (mục 24 test "Scan QR hai lần") — hành vi cụ thể (từ chối cứng hay hiển thị lại job cũ) **BLOCKED_BY_BUSINESS_CONFIRMATION**, đề xuất mặc định: hiển thị lại job cũ ở chế độ read-only

### `POST /api/weighing-jobs/{job_id}/samples` — Agent gửi mẫu đọc thô
- **Request:** `{device_id, sequence_no, device_timestamp, raw_value, unit}` (xem `local-agent-architecture.md` Mục 11.4 đầy đủ)
- **Response:** `{sample_id, is_stable, cleaned_value}`
- **Permission:** Device credential riêng (KHÔNG dùng tài khoản người dùng — mục 11.2), scope theo `device_id`
- **Idempotency:** `UNIQUE(device_id, sequence_no)` — gửi lại sequence cũ → 200 no-op, KHÔNG lỗi (để Agent an toàn khi retry sau mất mạng)
- **Concurrency:** N/A (append-only)

### `GET /api/weighing-jobs/{job_id}/stable-reading`
- **Response:** `{value, is_stable, tolerance_status}`

### `POST /api/weighing-job-items/{id}/accept`, `/reject`, `/override`
- **`override` Request:** `{reason}` bắt buộc
- **Permission:** `weighing.small_scale` hoặc `weighing.large_scale` theo workstation; `override` cần `weighing.override_tolerance`
- **Audit:** `WEIGH_ACCEPTED`/`WEIGH_REJECTED`/`WEIGH_OVERRIDDEN`

### `POST /api/weighing-job-items/{id}/complete`
- **Idempotency:** theo `job_item_id`

### `GET /api/weighing-jobs/{job_id}/history`

---

## Agent (Device credential, KHÔNG dùng token người dùng)

### `POST /api/agent/register` — đổi registration token → credential dài hạn
- **Request:** `{registration_token, device_fingerprint}`
- **Response:** `{device_credential, device_id}`

### `POST /api/agent/heartbeat`
- **Request:** `{device_id, agent_version, status}` (ký bằng device credential)

### `GET /api/agent/print-jobs/pending` hoặc WebSocket push — Agent nhận lệnh in
- **Response:** `[{job_id, idempotency_key, template, payload, copies, expiry}]`

### `POST /api/agent/print-jobs/{id}/report`
- **Request:** `{status: 'RECEIVED'|'PRINTING'|'PRINTED'|'FAILED'|'REJECTED', error_detail?}`

### `POST /api/agent/scale-samples` — xem Weighing Mục trên (cùng endpoint, phân theo device_type)

### `POST /api/agent/device-errors`
- **Request:** `{device_id, error_code, detail}`
- **Audit:** `DEVICE_ERROR_REPORTED`

**Nguyên tắc bảo mật xuyên suốt nhóm Agent (mục 11.2):** mọi endpoint `/api/agent/*` xác thực bằng **device credential** (không phải user token), request phải có chữ ký/signing hoặc Bearer token riêng cho device, whitelist theo `workstation_id`+`device_id`, và **Agent không được gọi bất kỳ endpoint nghiệp vụ nào ngoài phạm vi thiết bị của chính nó** (ví dụ ScaleAgent không gọi được `/api/chemical-call-requests`).
