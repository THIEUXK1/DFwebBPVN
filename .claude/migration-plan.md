# Database Migration Plan — Kế Hoạch Migration 5 Wave (migration-plan.md)

Lập 2026-07-17 — Phase C/D. Bản đề xuất thiết kế thứ tự di trú và tạo cấu trúc database đích theo từng đợt (waves). Tài liệu thiết kế — KHÔNG chạy migration thật trên production ở phase này.

---

## WAVE 1: FOUNDATION (Nền tảng hệ thống)

### 1. Mục tiêu
Thiết lập các bảng danh mục cốt lõi, quản trị máy trạm (workstation), thiết bị (device), thông tin xác thực Agent, bảng lưu feature flag và nền tảng ghi log kiểm toán chung.

### 2. Chi tiết Table / Column

> **Cập nhật 2026-07-17** (đối chiếu `erd-target.md` Mục 2.1 sau khi bổ sung kiến trúc Menu/Device — `menu-workstation-device-architecture.md`): Wave 1 phải gồm ĐẦY ĐỦ các bảng dưới, không chỉ 5 bảng gốc — nếu thiếu `workstation_devices`/`printer_profiles`/`workstation_printers`/`scale_devices`, Wave 3 (in tem) và Wave 4 (cân) sẽ thiếu phụ thuộc.

- **`app.workstation_types`**: `id` (smallint PK), `code` (varchar UNIQUE), `display_name` (varchar), `is_active` (boolean).
- **`app.workstations`**: `id` (bigint PK), `code` (varchar UNIQUE), `name` (varchar), `workstation_type_id` (references `workstation_types`), `location` (varchar), `status` (varchar), `created_at`, `updated_at`. *(IP/hostname KHÔNG đặt ở đây — thuộc `app.devices`, theo nguyên tắc không hard-code IP ở `menu-workstation-device-architecture.md` Mục 3)*
- **`app.devices`**: `id` (uuid PK), `device_type` (varchar: `PC`/`PRINTER`/`SCALE`/`SCANNER`/`DISPLAY`/`PLC`/`LOCAL_AGENT`), `name`, `serial_number` (UNIQUE, nullable), `ip_address` (inet), `hostname` (varchar), `agent_id` (uuid NULL), `driver_protocol` (varchar), `status` (varchar), `last_heartbeat_at`, `configuration` (jsonb), `agent_version` (varchar), `is_enabled` (boolean), `created_at`, `updated_at`.
- **`app.workstation_devices`** (MỚI trong đợt này — mapping N-N, dùng chung cho scale/printer/scanner/agent, KHÔNG tạo bảng mapping song song riêng theo yêu cầu mục 11): `workstation_id` (references `workstations`), `device_id` (references `devices`), `role` (varchar: `PRIMARY`/`BACKUP`/`PC`/`SCANNER`/`AGENT`), PK composite `(workstation_id, device_id)`.
- **`app.device_credentials`** (MỚI — tách khỏi `devices` để hỗ trợ rotate độc lập, theo `local-agent-architecture.md` Mục 4.2): `id` (uuid PK), `device_id` (references `devices`), `credential_hash` (text), `issued_at`, `expires_at`, `revoked_at` (NULL), `rotated_from_id` (uuid NULL, self-reference lịch sử rotate).
- **`app.device_heartbeats`**: `id` (bigserial PK), `device_id` (references `devices`), `received_at`, `agent_timestamp`, `status` (varchar), `payload` (jsonb).
- **`app.device_events`**: `id` (bigserial PK), `device_id` (references `devices`), `event_type` (varchar), `occurred_at`, `detail` (jsonb).
- **`app.printers`** (MỚI): `id` (uuid PK), `device_id` (references `devices`), `printer_name`, `printer_type`, `driver`, `connection` (varchar), `status` (varchar).
- **`app.printer_profiles`** (MỚI): `id` (uuid PK), `profile_code` (varchar UNIQUE), `template_key` (varchar), `paper_size` (varchar), `orientation` (varchar CHECK IN `PORTRAIT`/`LANDSCAPE`), `dpi` (smallint).
- **`app.workstation_printers`** (MỚI — giữ lại có lý do: cần `priority`/`default_printer_profile_id` mà `workstation_devices` không biểu diễn hợp lý, theo yêu cầu mục 11-12): `workstation_id`, `printer_id`, `default_printer_profile_id`, `priority` (smallint), PK composite.
- **`app.scale_devices`** (MỚI, thay hard-code COM/baud): `id` (uuid PK), `device_id` (references `devices`), `protocol` (varchar), `port` (varchar), `baud_rate` (integer), `unit` (varchar), `precision` (smallint).
- **`app.feature_flags`**: `id` (bigserial PK), `key` (varchar), `scope_type` (varchar: `GLOBAL`/`WORKSTATION_TYPE`/`WORKSTATION_INSTANCE`/`DEVICE`), `scope_ref_id` (nullable, trỏ theo `scope_type`), `value` (jsonb), `description` (text), `updated_at`, `updated_by` (uuid). *(Cập nhật so với bản gốc `key PK` đơn giản — cần `scope_type`+`scope_ref_id` để hỗ trợ 4 cấp theo `feature-flags.md` Mục 2.1)*
- **`app.audit_logs`**: `id` (bigserial PK), `workstation_id` (references `workstations` NULL), `user_id` (uuid NULL), `event_type` (varchar), `detail` (jsonb), `occurred_at` (timestamptz).
- **`app.correlation_links`/`app.legacy_exception_queue_items`** (nền tảng — schema đầy đủ đặt ở Wave 5, chỉ tạo khung rỗng ở Wave 1 nếu cần FK sớm; đề xuất **để nguyên ở Wave 5** vì chưa domain nào cần tới trước đó — không tạo sớm không cần thiết).

### 3. Chỉ mục (Indexes)
- Index tìm kiếm nhanh thiết bị theo trạm: `idx_workstation_devices_ws ON app.workstation_devices(workstation_id)`, `idx_workstation_devices_dev ON app.workstation_devices(device_id)`
- Index audit logs theo thời gian: `idx_audit_logs_occurred ON app.audit_logs(occurred_at DESC)`
- Unique feature flag scope: `CREATE UNIQUE INDEX uq_flag_scope ON app.feature_flags(key, scope_type, scope_ref_id)`
- `CREATE UNIQUE INDEX uq_printer_profile_code ON app.printer_profiles(profile_code)`

### 4. Kế hoạch Backfill
- Seed dữ liệu tĩnh cho 5 workstation type chuẩn.
- Seed dữ liệu cho 6 máy trạm nghiệp vụ thực tế dựa trên `workstation-matrix.md` (gán sẵn IP/hostname nội bộ nếu có).
- Khởi tạo giá trị mặc định cho các Feature Flag (tất cả unconfirmed = OFF).

### 5. Lệnh / Truy vấn Kiểm tra (Validation Query)
```sql
SELECT code, (SELECT count(*) FROM app.workstations w WHERE w.workstation_type_id = wt.id) as qty 
FROM app.workstation_types wt;
-- Phải trả ra đủ 5 loại và tổng workstation = 6.
```

### 6. Kịch bản Quay lui (Rollback Plan)
```sql
DROP TABLE IF EXISTS app.audit_logs;
DROP TABLE IF EXISTS app.feature_flags;
DROP TABLE IF EXISTS app.devices;
DROP TABLE IF EXISTS app.workstations;
DROP TABLE IF EXISTS app.workstation_types;
```

### 7. Rủi ro & Phụ thuộc (Risk & Dependency)
- **Dependency:** Không. Đây là đợt di trú đầu tiên.
- **Risk:** Cấu hình IP/hostname của 6 máy trạm thực tế có thể thay đổi sau khi triển khai mạng LAN mới → Giải pháp: Dùng cơ chế Token-based Registration làm dự phòng thay vì chỉ định cứng IP.

---

## WAVE 2: CHEMICAL CALL (Gọi hóa chất) — [TẠM HOÃN - ON HOLD]

> [!WARNING]
> Wave 2 tạm thời bị hoãn và cô lập khỏi luồng di trú chính do trạng thái **`BLOCKED_BY_BUSINESS_CONFIRMATION`** (Blocker **`CH-BUS-015`**). Các bảng và cấu hình dưới đây sẽ không được chạy migration trên môi trường production cho đến khi có xác nhận chính thức từ IT/Nghiệp vụ.

### 1. Mục tiêu
Triển khai cấu hình các kênh van hóa chất và lưu trữ yêu cầu gọi hóa chất động kèm lịch sử sự kiện (sau khi gỡ bỏ Blocker).

### 2. Chi tiết Table / Column
- **`app.machine_chemical_channels`**: `id` (bigint PK), `machine_id` (bigint references `app.machines`), `channel_number` (int), `chemical_code` (varchar), `legacy_id` (bigint).
- **`app.chemical_call_requests`**: `id` (uuid PK), `channel_id` (references `machine_chemical_channels`), `machine_id` (references `app.machines`), `status` (varchar), `requested_at` (timestamptz), `requested_by_user_id` (uuid), `requested_by_workstation_id` (references `workstations`), `acknowledged_at` (timestamptz), `confirmed_at` (timestamptz), `cancelled_at` (timestamptz), `idempotency_key` (text), `row_version` (int), `legacy_source` (varchar), `legacy_id` (bigint), `created_at`, `updated_at`.
- **`app.chemical_call_request_events`**: `id` (bigserial PK), `request_id` (references `chemical_call_requests`), `event_type` (varchar), `occurred_at` (timestamptz), `actor_user_id` (uuid), `actor_workstation_id` (bigint), `before_status` (varchar), `after_status` (varchar), `note` (text).

### 3. Chỉ mục (Indexes)
- Partial unique index chặn gọi trùng kênh: `CREATE UNIQUE INDEX uq_channel_active_order ON app.chemical_call_requests(channel_id) WHERE status IN ('CREATED', 'ORDERED', 'ACKNOWLEDGED')`
- Index tra cứu idempotency: `CREATE UNIQUE INDEX uq_chem_idempotency ON app.chemical_call_requests(idempotency_key)`

### 4. Kế hoạch Backfill
- Import cấu hình tĩnh các kênh hóa chất từ bảng `tbl_status` của database `CHEM_ORDER` (`chem_order.accdb`) qua staging schema.

### 5. Lệnh / Truy vấn Kiểm tra (Validation Query)
```sql
SELECT count(*) FROM app.machine_chemical_channels;
-- Phải khớp đúng số dòng trong tbl_status nguồn (~40 dòng).
```

### 6. Kịch bản Quay lui (Rollback Plan)
```sql
DROP TABLE IF EXISTS app.chemical_call_request_events;
DROP TABLE IF EXISTS app.chemical_call_requests;
DROP TABLE IF EXISTS app.machine_chemical_channels;
```

### 7. Rủi ro & Phụ thuộc (Risk & Dependency)
- **Dependency:** Phụ thuộc vào Wave 1 (Workstation) và bảng `app.machines` đã có sẵn.
- **Risk:** Trùng lặp cấu hình kênh van nếu file `chem_order.accdb` chứa dữ liệu rác → Giải pháp: Lọc sạch trùng lặp ở tầng staging trước khi chạy migration script.

---

## WAVE 3: DISPATCH / QR / PRINT (Điều phối & In tem)

### 1. Mục tiêu
Thiết kế cấu trúc hàng chờ điều phối máy nhuộm, thông tin nhãn in QR, các job in tem nhãn gửi xuống Agent.

### 2. Chi tiết Table / Column
- **`app.machine_dispatches`**: (Đã có sẵn — bổ sung cột): `routing_decision_id` (uuid references `routing_decisions` NULL).
- **`app.routing_decisions`**: `id` (uuid PK), `dispatch_id` (references `machine_dispatches`), `mode` (varchar), `route` (text), `matched_rule` (varchar), `rule_version` (varchar), `input_snapshot` (jsonb), `warnings` (jsonb), `needs_manual_review` (boolean), `decided_at` (timestamptz).
- **`app.qr_payloads`**: `id` (uuid PK), `dispatch_id` (references `machine_dispatches`), `payload_version` (varchar), `payload_type` (varchar), `raw_payload` (text), `payload_hash` (varchar), `created_at` (timestamptz).
- **`app.dispatch_events`**: `id` (uuid PK), `dispatch_id` (references `machine_dispatches`), `event_type` (varchar), `color` (text), `code` (text), `machine_id` (bigint), `tank` (text), `level` (text), `confirm_1` (text), `confirm_2` (text), `is_sent` (boolean), `scale_checked` (boolean), `raw_qr_dye` (text), `raw_qr_chemical` (text), `occurred_at` (timestamptz), `actor_user_id` (uuid), `legacy_source` (varchar), `legacy_id` (bigint).
- **`app.print_jobs`**: `id` (uuid PK), `dispatch_id` (references `machine_dispatches` NULL), `idempotency_key` (text UNIQUE), `correlation_id` (uuid), `status` (varchar), `created_at`, `expiry` (timestamptz).
- **`app.print_attempts`**: `id` (bigserial PK), `print_job_id` (references `print_jobs`), `attempt_no` (smallint), `status` (varchar), `device_id` (references `devices`), `started_at` (timestamptz), `finished_at` (timestamptz), `error_detail` (text).

### 3. Chỉ mục (Indexes)
- Index tìm kiếm QR theo hash: `CREATE UNIQUE INDEX uq_qr_payload_hash ON app.qr_payloads(payload_hash)`
- Index composite cho QR unique version: `CREATE UNIQUE INDEX uq_dispatch_qr_ver ON app.qr_payloads(dispatch_id, payload_type, payload_version)`
- Index tìm print attempt: `CREATE UNIQUE INDEX uq_print_attempt_seq ON app.print_attempts(print_job_id, attempt_no)`

### 4. Kế hoạch Backfill
- Nạp dữ liệu lịch sử từ bảng `tbl_SentLog` (RECORD_A, **27.024 dòng thật đã xác nhận qua `database-inventory.md`** — không còn cần Compact & Repair như lo ngại R-01 cũ, vì `RECORD.accdb` hiện có đọc được đầy đủ, không lỗi) vào bảng `app.dispatch_events`.

### 5. Lệnh / Truy vấn Kiểm tra (Validation Query)
```sql
SELECT count(*) FROM app.dispatch_events;
-- Phải khớp chính xác 27.024 dòng (tbl_SentLog, RECORD_A, xác nhận 2026-07-17 — xem database-inventory.md).
```

### 6. Kịch bản Quay lui (Rollback Plan)
```sql
DROP TABLE IF EXISTS app.print_attempts;
DROP TABLE IF EXISTS app.print_jobs;
DROP TABLE IF EXISTS app.qr_payloads;
DROP TABLE IF EXISTS app.routing_decisions;
DROP TABLE IF EXISTS app.dispatch_events;
-- Bỏ cột bổ sung trong machine_dispatches
ALTER TABLE app.machine_dispatches DROP COLUMN IF EXISTS routing_decision_id;
```

### 7. Rủi ro & Phụ thuộc (Risk & Dependency)
- **Dependency:** Yêu cầu bảng `app.devices` (Wave 1) và `app.machine_dispatches` đã có sẵn.
- **Risk:** Bảng `tbl_SentLog` bị hỏng nặng không đọc hết được số dòng → Giải pháp: Dùng bản phục hồi qua công cụ Compact & Repair của Access trước khi chạy import.

---

## WAVE 4: WEIGHING (Cân nguyên liệu)

### 1. Mục tiêu
Định nghĩa cấu trúc ghi nhận mẫu đọc thô từ cân (raw samples), kết quả cân chính thức (business results) và các sự kiện đổi trạng thái trạm cân.

### 2. Chi tiết Table / Column
- **`app.weighing_samples`**: `id` (bigserial PK), `job_item_id` (references `app.weighing_job_items`), `device_id` (references `app.devices`), `sequence_no` (bigint), `device_timestamp` (timestamptz), `agent_timestamp` (timestamptz), `server_received_at` (timestamptz), `raw_value` (text), `cleaned_value` (numeric), `unit` (varchar), `is_stable` (boolean), `quality_code` (varchar), `scale_algorithm_version` (varchar).
- **`app.weighing_results`**: `id` (uuid PK), `job_item_id` (references `app.weighing_job_items`), `stable_reading_id` (references `weighing_samples` NULL), `final_value` (numeric), `tolerance_status` (varchar), `is_override` (boolean), `override_reason` (text), `override_by_user_id` (uuid NULL), `posted_at` (timestamptz), `policy_version` (varchar).
- **`app.weighing_events`**: `id` (bigserial PK), `job_item_id` (references `app.weighing_job_items`), `event_type` (varchar), `occurred_at` (timestamptz), `actor_user_id` (uuid).

### 3. Chỉ mục (Indexes)
- Chặn duplicate sample từ Agent gửi lại: `CREATE UNIQUE INDEX uq_device_sequence ON app.weighing_samples(device_id, sequence_no)`
- Index tìm kết quả cân theo job item: `CREATE INDEX idx_weighing_result_item ON app.weighing_results(job_item_id)`

### 4. Kế hoạch Backfill
- Di trú toàn bộ **140.655 dòng** `tblRECORD` (RECORD_B, `RECORD1.accdb` — số chính xác theo `database-inventory.md`, đính chính từ số cũ 140.660 của đợt audit đầu chưa phân biệt RECORD_A/RECORD_B) và 5.061 dòng `tblRECORD_chem` (RECORD_B) vào bảng `app.weighing_results`.

### 5. Lệnh / Truy vấn Kiểm tra (Validation Query)
```sql
SELECT count(*) FROM app.weighing_results;
-- Phải khớp chính xác: 140.655 (Dye) + 5.061 (Chem) = 145.716 dòng.
```

### 6. Kịch bản Quay lui (Rollback Plan)
```sql
DROP TABLE IF EXISTS app.weighing_events;
DROP TABLE IF EXISTS app.weighing_results;
DROP TABLE IF EXISTS app.weighing_samples;
```

### 7. Rủi ro & Phụ thuộc (Risk & Dependency)
- **Dependency:** Yêu cầu `app.devices` (Wave 1) và `app.weighing_job_items` đã có sẵn.
- **Risk:** Cột chứa timestamp cân trong Access (`WH_TIME` hoặc `RECORDTIME`) rỗng hoặc sai định dạng ngày → Giải pháp: Dùng giá trị dự phòng là ngày tạo mẻ hoặc null.

---

## WAVE 5: CORRELATION (Đối soát chéo)

### 1. Mục tiêu
Tạo bảng liên kết suy luận và xử lý ngoại lệ đối soát chéo dữ liệu độc lập giữa `RECORD_A` và `RECORD_B` (sườn nghiệp vụ).

### 2. Chi tiết Table / Column
- **`app.correlation_links`**: `id` (uuid PK), `dispatch_id` (references `machine_dispatches`), `weighing_job_id` (references `weighing_jobs`), `match_method` (varchar), `confidence` (numeric), `matched_on` (jsonb), `status` (varchar), `created_at` (timestamptz).
- **`app.legacy_exception_queue_items`**: `id` (uuid PK), `entity_type` (varchar), `entity_id` (uuid), `reason` (text), `created_at` (timestamptz), `resolved_at` (timestamptz), `resolution` (text).

### 3. Chỉ mục (Indexes)
- Index tìm kiếm correlation theo dispatch: `CREATE INDEX idx_corr_dispatch ON app.correlation_links(dispatch_id)`
- Index tìm kiếm correlation theo weighing job: `CREATE INDEX idx_corr_weigh ON app.correlation_links(weighing_job_id)`

### 4. Kế hoạch Backfill
- Chạy thuật toán đối soát lịch sử (xem `record-a-record-b-correlation.md`) để điền dữ liệu ban đầu cho bảng `app.correlation_links` với các mẻ cũ.

### 5. Lệnh / Truy vấn Kiểm tra (Validation Query)
```sql
SELECT match_method, count(*), avg(confidence) 
FROM app.correlation_links 
GROUP BY match_method;
-- Xuất báo cáo tỷ lệ khớp theo từng phương pháp đối soát.
```

### 6. Kịch bản Quay lui (Rollback Plan)
```sql
DROP TABLE IF EXISTS app.legacy_exception_queue_items;
DROP TABLE IF EXISTS app.correlation_links;
```

### 7. Rủi ro & Phụ thuộc (Risk & Dependency)
- **Dependency:** Yêu cầu Wave 3 (Dispatch) và Wave 4 (Weighing) đã hoàn thành.
- **Risk:** Tỷ lệ đối soát tự động không khớp cao do sai lệch thông tin nhập tay → Giải pháp: Các dòng không thể đối soát tự động sẽ được đẩy vào `legacy_exception_queue_items` để duyệt thủ công bằng tay, không tự gán bừa bãi.
