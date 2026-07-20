# Target Data Model - Mô hình Dữ liệu Đích (PostgreSQL)

Tài liệu này đặc tả chi tiết mô hình dữ liệu vật lý được chuẩn hóa trên PostgreSQL 15+ phục vụ cho ứng dụng Web.

---

## 1. Nguyên tắc Thiết kế
1. **Khóa kỹ thuật:** Sử dụng `UUID` (sinh tự động qua `gen_random_uuid()`) hoặc `BIGINT GENERATED ALWAYS AS IDENTITY` làm khóa chính. Không sử dụng các mã nghiệp vụ hoặc ID từ hệ thống cũ làm khóa chính của hệ thống mới.
2. **Khả năng truy vết lịch sử (Traceability):** Mọi bảng dữ liệu chuyển đổi từ Access sang PostgreSQL phải lưu giữ nguồn gốc thông qua các trường:
   - `legacy_source`: Tên bảng Access nguồn (ví dụ: `'tblRECORD'`, `'tbl_ToSend2'`).
   - `legacy_id`: Giá trị cột ID gốc trong bảng Access.
   - `legacy_row_no`: Số thứ tự dòng vật lý trong bảng Access (đặc biệt quan trọng đối với các bảng hàng chờ có ID trùng hoặc rỗng).
3. **Audit Log:** Mọi bảng giao dịch quan trọng (công thức, cân, điều phối) đều có cột kiểm toán:
   - `created_at timestamptz DEFAULT now()`, `created_by uuid`
   - `updated_at timestamptz DEFAULT now()`, `updated_by uuid`
   - `deleted_at timestamptz` (Soft delete - Không hard delete dữ liệu sản xuất).
4. **Kiểm soát đồng thời (Concurrency Control):** Các bảng có rủi ro tranh chấp cao (lệnh sản xuất, hàng chờ điều phối, công thức) sử dụng trường `row_version integer DEFAULT 1` phục vụ Optimistic Locking ở tầng ứng dụng Backend.

---

## 2. Chi tiết các Bảng trong Schema `app`

### 2.1. Phân hệ Tài khoản và Phân quyền (Security & RBAC)
- **`app.users`:** Lưu thông tin tài khoản nhân viên. Khóa chính `id uuid`. Trường `password_hash` bắt buộc băm bằng BCrypt/Argon2.
- **`app.roles`:** Danh mục vai trò (Operator, Shift Leader, Technologist, QA/QC, Admin, Auditor). Khóa chính `id bigserial`.
- **`app.user_roles`:** Bảng trung gian thể hiện quan hệ nhiều-nhiều giữa người dùng và vai trò.

### 2.2. Phân hệ Thiết bị & Định vị (Master Data)
- **`app.machines`:** Danh mục máy nhuộm. Khóa chính `id bigserial`, `code varchar(100) UNIQUE` (ví dụ: `'VD01'`, `'VD02'`).
- **`app.tanks`:** Danh mục bồn/rack vật tư chứa bột màu. Khóa chính `id bigserial`, khóa ngoại `machine_id REFERENCES app.machines(id)`. Unique constraint trên cặp `(machine_id, code)`.
- **`app.machine_chemical_channels` (Mới):** Cấu hình các kênh/van hóa chất tự động cho từng máy nhuộm (chuyển đổi từ `tbl_status`).
  - Khóa chính `id bigserial`.
  - `machine_id bigint REFERENCES app.machines(id)`: Khóa ngoại trỏ đến máy nhuộm.
  - `channel_number smallint NOT NULL`: Số hiệu kênh/van hóa chất (tương ứng trường `chem` trong Access).
  - `chemical_code varchar(100) NOT NULL`: Mã hóa chất nạp trong kênh (tương ứng trường `chem_name` trong Access).
  - `is_active boolean DEFAULT true`: Trạng thái hoạt động.
  - `legacy_id bigint`: Dùng để lưu vết khóa ngoại từ `tbl_status.ID`.
  - Ràng buộc duy nhất `UNIQUE(machine_id, channel_number)`.


### 2.3. Phân hệ Lô sản xuất (Production Batches)
- **`app.production_batches`:** Lưu thông tin đầu mẻ nhuộm (tương ứng dòng Header phân tách từ `tblRECORD` và `tblRECORD_chem`).
  - Khóa chính `id uuid DEFAULT gen_random_uuid()`.
  - `legacy_batch_id text`: Mã lô cũ (ví dụ: `'20251130_101145'`).
  - `color text`, `product_code text`: Mã màu và mã sản phẩm.
  - `machine_id bigint`, `tank_id bigint`: Tham chiếu máy nhuộm và bồn thực tế.
  - `level_code text`: Mực nước hoặc thông số kỹ thuật liên quan.
  - `status varchar(30)`: Trạng thái sản xuất (`'NEW'`, `'WAITING_CONFIRM'`, `'READY_TO_WEIGH'`, `'WEIGHING'`, `'WEIGHED'`, `'READY_TO_SEND'`, `'SENT'`, `'DONE'`).
  - `row_version integer DEFAULT 1`.
  - Ràng buộc duy nhất `UNIQUE(legacy_batch_id, product_code, machine_id)`.

### 2.4. Phân hệ Điều phối máy (Machine Dispatches)
- **`app.machine_dispatches`:** Lưu trữ lịch sử điều phối và trạng thái hàng chờ gửi máy.
  - Khóa chính `id uuid DEFAULT gen_random_uuid()`.
  - `legacy_row_no bigint NOT NULL`, `legacy_id bigint`: Dùng để lưu vết dòng thô từ Access.
  - `batch_id uuid REFERENCES app.production_batches(id)`: Khóa ngoại nối với mẻ sản xuất.
  - `confirm_1 text`, `confirm_2 text`: Xác nhận duyệt cấp 1 và cấp 2.
  - `sending_value text`, `sent_value text`: Giá trị truyền đi và nhận về.
  - `confirmed_at_1 timestamp`, `confirmed_at_2 timestamp`, `sent_at timestamp`: Các mốc thời gian.
  - `is_sent boolean`, `scale_checked boolean`: Trạng thái gửi và kiểm tra cân.
  - `raw_qr_dye text`, `raw_qr_chemical text`: Chuỗi QR bột màu và hóa chất thô.
  - `queue_state varchar(30)`: Trạng thái hàng chờ (`'INPUT'`, `'WAITING'`, `'TO_SEND'`, `'PROCESSING'`, `'SENT'`, `'ERROR'`, `'CANCELLED'`).
  - `source_table varchar(100)`: Bảng nguồn Access (`'tbl_Waiting'`, `'tbl_ToSend2'`, `'WAITING'`).
  - `locked_by uuid REFERENCES app.users(id)`: Người giữ khóa logic.
  - `locked_at timestamptz`, `expires_at timestamptz`: Thời gian khóa và hết hạn khóa logic.
  - Ràng buộc duy nhất `UNIQUE(source_table, legacy_row_no)`.

### 2.5. Phân hệ Nhật ký Cân (Scale Measurements)
- **`app.scale_measurements`:** Lưu chi tiết toàn bộ các dòng cân nguyên liệu bột màu thuốc nhuộm và hóa chất phụ trợ (phân tách từ Detail của `tblRECORD` và `tblRECORD_chem`).
  - Khóa chính `id uuid DEFAULT gen_random_uuid()`.
  - `legacy_source varchar(30) NOT NULL`: Bảng nguồn (`'tblRECORD'` hoặc `'tblRECORD_chem'`).
  - `legacy_id bigint NOT NULL`: ID bản ghi cân cũ.
  - `legacy_batch_id text`: Liên kết lô cũ.
  - `color text`, `product_code text`, `machine_code text`, `level_code text`: Bản sao thông số nghiệp vụ tại thời điểm cân.
  - `rack_code text`, `dye_code text`: Vị trí rack và mã bột màu/hóa chất cần cân.
  - `weight numeric(18,6)`: Khối lượng cân thực tế.
  - `process_code text`: Mã công đoạn công nghệ.
  - `measured_at timestamp`: Thời điểm cân.
  - `process_color text`: Mã màu của công đoạn.
  - `warehouse_done boolean`, `warehouse_time timestamp`: Xác nhận hoàn tất nhập kho phụ trợ.
  - `material_type varchar(20) CHECK (material_type IN ('DYE', 'CHEMICAL'))`: Phân biệt bột màu nhuộm (`'DYE'`) và hóa chất (`'CHEMICAL'`).
  - Ràng buộc duy nhất `UNIQUE(legacy_source, legacy_id)`.

### 2.6. Phân hệ Kiểm toán (Audit Logs)
- **`app.audit_logs`:** Bảng nhật ký kiểm toán hệ thống.
  - Khóa chính `id bigserial`.
  - `user_id uuid REFERENCES app.users(id)`: Tài khoản thực hiện hành động.
  - `action varchar(100) NOT NULL`: Hành động thực hiện (ví dụ: `'APPROVE_FORMULA'`, `'OVERRIDE_WEIGHING'`, `'REPRINT_LABEL'`, `'FORCE_UNLOCK'`).
  - `entity_type varchar(100) NOT NULL`: Loại thực thể tác động (ví dụ: `'formulas'`, `'scale_measurements'`, `'machine_dispatches'`).
  - `entity_id text`: Khóa chính của thực thể bị tác động.
  - `before_data jsonb`: Dữ liệu JSON trước khi thay đổi (chỉ dùng cho sửa/xóa).
  - `after_data jsonb`: Dữ liệu JSON sau khi thay đổi (hoặc dữ liệu tạo mới).
  - `created_at timestamptz DEFAULT now()`.
  - `client_ip inet`: Địa chỉ IP trạm làm việc của nhân viên.

### 2.7. Phân hệ Realtime & Cảnh báo (Realtime & Alerts)
- **`app.realtime_events`:** Lưu outbox event bất đồng bộ (Transactional Outbox Pattern).
  - Khóa chính `id bigserial`.
  - `event_type varchar(100) NOT NULL`: Loại sự kiện (ví dụ: `batch.created`, `batch.status_changed`, `weight.confirmed`).
  - `entity_type varchar(100) NOT NULL`, `entity_id varchar(100) NOT NULL`: Loại thực thể và ID tham chiếu.
  - `payload jsonb`: Tham số tối thiểu của sự kiện thay đổi.
  - `actor_id uuid REFERENCES app.users(id)`: Tác nhân thực thi.
  - `machine_id bigint REFERENCES app.machines(id)`, `batch_id uuid REFERENCES app.production_batches(id)`: Hỗ trợ lọc sự kiện.
  - `occurred_at timestamptz DEFAULT now()`.
- **`app.alert_rules`:** Danh mục các quy tắc và ngưỡng chẩn đoán sự cố trễ hạn.
  - Khóa chính `id bigserial`.
  - `rule_code varchar(50) UNIQUE`: Mã quy tắc (ví dụ: `WEIGH_START_DELAY`, `TRANS_SLA_BREACH`, `SCALE_AGENT_OFFLINE`).
  - `name varchar(200) NOT NULL`: Tên quy tắc hiển thị.
  - `severity varchar(20)`: Mức độ nghiêm trọng (`INFO`, `WARNING`, `CRITICAL`).
  - `threshold_seconds integer`: Ngưỡng thời gian định mức (giây).
  - `is_enabled boolean DEFAULT true`: Trạng thái bật/tắt rule.
- **`app.alerts`:** Nhật ký các cảnh báo vận hành đang mở hoặc đã xử lý.
  - Khóa chính `id bigserial`.
  - `rule_code varchar(50) REFERENCES app.alert_rules(rule_code)`: Khóa ngoại trỏ đến mã quy tắc.
  - `severity varchar(20)`, `message text`: Nội dung chi tiết sự cố cảnh báo.
  - `batch_id uuid REFERENCES app.production_batches(id)`, `machine_id bigint REFERENCES app.machines(id)`: Liên kết mẻ/máy gặp lỗi.
  - `status varchar(30) DEFAULT 'OPEN'`: Trạng thái xử lý (`OPEN`, `ACKNOWLEDGED`, `RESOLVED`).
  - `assigned_to uuid REFERENCES app.users(id)`: Người nhận xử lý.
  - `resolved_by uuid REFERENCES app.users(id)`: Người xác nhận đóng.
  - `reason text`, `resolution text`: Ghi chú nguyên nhân và biện pháp khắc phục.
  - `created_at timestamptz DEFAULT now()`, `acknowledged_at timestamptz`, `resolved_at timestamptz`.

---

## 2.X Phân hệ mới đề xuất (cập nhật 2026-07-17, Phase C/D — ĐÃ THIẾT KẾ PHẬT LÝ)

Dưới đây là các bảng mới đã được thiết kế chi tiết trong `erd-target.md` và đưa vào kế hoạch di trú (5 waves) trong `migration-plan.md`:

- **`app.workstation_types`**: Danh mục 5 loại máy trạm chuẩn nghiệp vụ (`CHEMICAL_CALL`, `PRODUCTION_ORDER`, `QR_LABEL_PRINTING`, `SMALL_SCALE`, `LARGE_SCALE`).
- **`app.devices`**: Quản lý thông tin thiết bị ngoại vi gắn với trạm (máy in, cân, đầu đọc barcode, agent ngầm) và driver/protocol của chúng.
- **`app.device_heartbeats`**: Nhật ký thời gian thực nhận tín hiệu sống từ các Agent của thiết bị.
- **`app.device_events`**: Audit history riêng cho các hành động trên thiết bị (đăng ký, bật/tắt, lỗi).
- **`app.chemical_call_requests`** + **`app.chemical_call_request_events`**: Lưu lịch sử vòng đời yêu cầu cấp hóa chất (`CREATED`, `ORDERED`, `ACKNOWLEDGED`, `DONE`, `CANCELLED`, `FAILED`, `RESET`). Tách biệt phần vận hành động khỏi cấu hình tĩnh kênh van.
- **`app.routing_decisions`**: Snapshot kết quả tính toán phân vùng kho B24 tại thời điểm confirm, lưu vết mode chạy (`LEGACY_EXACT`, `FIXED_D1`, `MANUAL_REVIEW`), rule khớp và các cảnh báo.
- **`app.qr_payloads`**: Lưu trữ versioned payload QR được in ra tem (dye, chemical, process, extra, fb), giúp tái tạo nhãn cũ chính xác khi reprint.
- **`app.print_jobs`** + **`app.print_attempts`**: Hàng đợi in tem nhãn và nhật ký các lần thử in của Local Print Agent.
- **`app.weighing_samples`**: Ghi nhận chuỗi dữ liệu thô (raw sample) nhận được từ cân vật lý qua Agent, lưu vết cờ ổn định và sequence number chống trùng lắp khi truyền lại.
- **`app.weighing_results`**: Lưu kết quả cân chính thức sau khi đạt dung sai hoặc được Trưởng ca duyệt override (không ghi đè raw sample).
- **`app.weighing_events`**: Nhật ký sự kiện đổi trạng thái của mẻ cân (quét QR, ổn định, chấp nhận, override, hoàn tất).
- **`app.correlation_links`**: Bảng đối chiếu suy luận liên kết mềm giữa `RECORD_A` (dispatch) và `RECORD_B` (cân) kèm phương thức khớp và độ tin cậy.
- **`app.legacy_exception_queue_items`**: Hàng đợi lưu giữ các mẻ cân hoặc lệnh điều phối bị lỗi, rác, hoặc không thể đối chiếu tự động, chờ QA xử lý bằng tay.

## 3. Bản đồ Index Đề xuất (Index Optimization)
Để tối ưu hóa tốc độ truy vấn trên tập dữ liệu lịch sử lớn (hơn 140.000 dòng):
- **`ix_dispatch_queue_state`:** Trên `app.machine_dispatches(queue_state)` để tải nhanh lưới hàng chờ điều phối.
- **`ix_scale_batch`:** Trên `app.scale_measurements(legacy_batch_id)` phục vụ tìm kiếm nhanh lịch sử cân của một lô.
- **`ix_scale_time`:** Trên `app.scale_measurements(measured_at)` tối ưu hóa báo cáo lượng dùng vật tư theo khoảng thời gian.
- **`ix_scale_material`:** Trên `app.scale_measurements(material_type, dye_code)` tối ưu hóa thống kê tiêu hao bột màu nhuộm / hóa chất.
- **`ix_audit_entity`:** Trên `app.audit_logs(entity_type, entity_id)` hỗ trợ QA truy vết nhanh vòng đời của một công thức hoặc một mẻ cân.
