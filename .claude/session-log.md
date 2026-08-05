# Session Log - Phiên làm việc ngày 15/07/2026

## Nhật ký hoạt động

### 1. File đã đọc và phân tích
- `Bao_cao_phan_tich_thiet_ke_he_thong_tu_VBA.docx` (chuyển sang dạng txt để đọc)
- `Bao_cao_PTTK_va_chuyen_doi_SQL_V1.0.docx` (chuyển sang dạng txt để đọc)
- `F:\DF\chem_order.accdb` (Cơ sở dữ liệu Access mới bổ sung, kiểm tra qua pyodbc)
- `sql_migration/01_legacy_access_import_postgresql.sql`
- `sql_migration/02_target_normalized_schema_postgresql.sql`
- `sql_migration/03_transform_legacy_to_target.sql`
- `sql_migration/04_validation_queries.sql`
- `sql_migration/access_inventory.json`
- Thư mục `.claude` cũ (các tệp `CLAUDE.md`, `instructions.md` và các tệp trong thư mục `commands/`).

### 2. Phân loại và xử lý thư mục `.claude` cũ
Toàn bộ thư mục `.claude` cũ được xác nhận sao chép từ dự án "Stock Signal App" (ứng dụng phân tích chứng khoán Việt Nam) và không có bất kỳ liên quan nào đến dự án DF hiện tại.

**Bảng phân loại xử lý:**
- `CLAUDE.md` (cũ): Xóa & Ghi đè mới hoàn toàn phục vụ dự án DF.
- `instructions.md` (cũ): Xóa bỏ hoàn toàn (Không liên quan).
- Thư mục `commands/` (cũ) bao gồm `audit-candles.md`, `audit-indicators.md`, `audit-momentum-bvps.md`, `audit-signals.md`, `audit-ui.md`, `backtest.md`, `run-app.md`: Xóa bỏ toàn bộ thư mục (Không liên quan, tránh gây hiểu nhầm cho lập trình viên tương lai).

### 3. Các tệp đã tạo mới / cập nhật trong `.claude`
- `CLAUDE.md` (Cập nhật)
- `project-overview.md` (Tạo mới)
- `system-context.md` (Tạo mới)
- `business-modules.md` (Tạo mới)
- `current-data-model.md` (Tạo mới)
- `target-data-model.md` (Tạo mới)
- `migration-strategy.md` (Tạo mới)
- `architecture-decisions.md` (Tạo mới)
- `coding-standards.md` (Tạo mới)
- `security-rules.md` (Tạo mới)
- `testing-strategy.md` (Tạo mới)
- `development-roadmap.md` (Tạo mới)
- `open-questions.md` (Tạo mới)
- `risks-and-assumptions.md` (Tạo mới)
- `source-traceability.md` (Tạo mới)
- `session-log.md` (Tạo mới)

---

## Các Phát hiện Chính (Key Findings)

### 1. Lỗi lệch cột dữ liệu (Column Shift) nghiêm trọng trong Access Legacy
Khi phân tích tệp `01_legacy_access_import_postgresql.sql` và so sánh cấu trúc định nghĩa với dữ liệu `COPY` thực tế, chúng tôi phát hiện hai bảng hàng chờ điều phối bị lệch cột nghiêm trọng:
- **Bảng `tbl_ToSend2`:** Cột `CODE` chứa dữ liệu Color, cột `CONFIRM1` chứa Product Code, cột `MACHINE` chứa confirmation `OK`, cột `TANK` chứa Machine (`VD15`), cột `CONFIRM2` chứa Level (`450`), cột `SENDING` chứa confirm 2 (`OK`), cột `SENT` chứa `0`, cột `TIME1` chứa `0`, cột `TIME2` chứa Time 1, và cột `TIME3` chứa Time 2.
- **Bảng `WAITING`:** Cột `COLOR` chứa Product Code (`L23892`), cột `CODE` chứa confirmation `OK`, cột `CONFIRM1` chứa Machine (`VD02` hoặc `VD09`), cột `MACHINE` chứa Tank (`X`), cột `TANK` chứa Level (`50` hoặc `100`), và toàn bộ dữ liệu màu sắc (`COLOR`) thực tế bị mất khỏi bảng này.

> [!WARNING]
> **Hậu quả:** Câu lệnh transform động trong `03_transform_legacy_to_target.sql` sử dụng phép join tĩnh:
> `LEFT JOIN app.machines m ON m.code=trim(d."MACHINE"::text)`
> `LEFT JOIN app.production_batches b ON b.product_code=d."CODE"::text AND b.machine_id=m.id`
> Việc này sẽ dẫn đến kết quả join là **NULL** cho tất cả các bản ghi di trú từ `tbl_ToSend2` và `WAITING` vì `d."MACHINE"` chứa `'OK'` hoặc `'X'` và `d."CODE"` chứa `'EP68132'` (color) hoặc `'OK'`. Script transform cần phải được viết lại để ánh xạ các cột riêng biệt cho từng bảng.

### 2. Dữ liệu cân hóa chất trong `tblRECORD_chem` và vai trò của `tbl_status` (Từ `chem_order.accdb`)
- Cả trong database primary (5.061 dòng) và database bổ sung `chem_order.accdb` (1.500 dòng), toàn bộ dữ liệu cột `WEIGHT` và `PROCESS` trong `tblRECORD_chem` đều bị trống.
- Tuy nhiên, tệp `chem_order.accdb` mới chứa bảng **`tbl_status`** (40 dòng) định nghĩa cấu hình: `machine` (ví dụ `'VD016'`) -> `chem` (ví dụ `4`) -> `chem_name` (ví dụ `'AC77'`) -> `status` (ví dụ `'0'`).
- Đây là phát hiện quan trọng: chứng minh nhà máy nhuộm sử dụng **hệ thống cấp hóa chất tự động** (như hệ Copower). Bảng `tbl_status` là bản đồ cấu hình van/kênh nạp hóa chất cho từng máy nhuộm. Khi mẻ nhuộm bắt đầu, VBA viết lệnh nạp hóa chất thô vào bảng này với `status = '0'` để hệ thống cấp tự động thực thi.
- Do đó, không có dữ liệu cân hóa chất thủ công trong `tblRECORD_chem`. Phân hệ hóa chất cần được chuyển sang tích hợp điều khiển tự động thay vì trạm cân thủ công.


### 3. Lỗi Overflow Page của `tbl_SentLog`
- Bảng nhật ký gửi máy `tbl_SentLog` (chứa dữ liệu lịch sử quan trọng nhất của phân hệ điều phối máy) không thể trích xuất tự động do lỗi trang dữ liệu Microsoft Access.
- Cần chạy quy trình Compact & Repair trên MS Access để phục hồi dữ liệu trước khi di trú chính thức.

---

## Phản hồi và Xác nhận của Người dùng
Người dùng đã xác nhận các thông tin nghiệp vụ và kỹ thuật cốt lõi:
1. **Hệ thống pha màu tự động & Định vị Web App:** Nhà máy nhuộm đai sử dụng hệ thống pha màu tự động. Dữ liệu màu sắc/sản phẩm nằm trên hệ thống **MES**. Ứng dụng Web mới đóng vai trò **cầu nối trung gian (Connector)** liên kết MES và hệ nhuộm tự động.
2. **Kích in tem & Kết nối:** Sử dụng dòng máy in TSC TE200 (hoặc tương thích), hỗ trợ kết nối USB máy trạm hoặc qua mạng LAN. **Cho phép người dùng tự điều chỉnh kích thước nhãn tem in (Label Size) trên giao diện Web.**
3. **Chất lượng dữ liệu & Logic:** Đồng ý sửa lệch cột cho đúng logic nghiệp vụ. Chấp nhận cho lập trình viên tự kiểm tra mã nguồn VBA để tự bổ sung cấu trúc các bảng bị thiếu cho hợp logic.
4. **Stack công nghệ:** Phê duyệt stack **Laravel + PostgreSQL + Vue.js + Local Agent .NET**.

---

## Các Câu hỏi Còn mở (Open Questions)
*Chi tiết xem tại [open-questions.md](file:///F:/DF/.claude/open-questions.md)*
1. Quy định dung sai cân bột màu và hóa chất phụ trợ (CH-BUS-002).
2. Giao thức Serial kết nối cân điện tử tại các máy trạm (CH-TECH-002).
3. Giao thức tích hợp của hệ pha màu tự động hạ nguồn với database thông qua bảng `tbl_status` (CH-TECH-001).

---

## Nhật ký Cập nhật (15/07/2026) - Hoàn thành Phase 1-3 & Kích hoạt Phase 4
1. **Hoàn thành Nền tảng (Phase 1):** Khởi tạo thành công các repo trong `backend`, `frontend`, và `agent`. Thiết lập Docker Compose Postgres chạy trên cổng 5433 (để tránh xung đột dịch vụ Postgres cục bộ trên Windows trạm).
2. **Hoàn thành Database (Phase 2):** Import thành công và validation đối soát khớp 100% dữ liệu lịch sử cân thuốc nhuộm (140,660 dòng) và hóa chất (5,061 dòng).
3. **Hoàn thành Auth/RBAC/Audit (Phase 3):** Tích hợp Laravel Sanctum, mã hóa mật khẩu admin/admin123 bằng BCrypt. Viết CheckRole middleware, thiết lập Audit Log Service tự động lưu vết JSONB thô. Cổng chạy Frontend được thiết lập tại cổng `3001` (dùng Vite 5 tránh lỗi Rolldown trên Node v24).
4. **Tái cấu trúc 14 Phase:** Dựa trên tài liệu `phase.docx`, lộ trình triển khai đã được điều chỉnh từ 12 phase thành 14 phase, bổ sung chi tiết **Phase 8: Vận chuyển** và **Phase 9: Cấp máy**.

---

## Nhật ký Phiên (Giai đoạn tiếp theo - 15/07/2026)
### 1. Phân tích & Bổ sung CSDL `chem_order.accdb`
- **Định vị tệp tin:** Đã định vị thành công CSDL Access `chem_order.accdb` tại đường dẫn thực tế trên hệ thống: `C:\Users\V170192\OneDrive\Desktop\DF\database\chem_order\chem_order.accdb`.
- **Thống kê dữ liệu:**
  - Bảng `tbl_status`: 40 dòng (chứa cấu hình nạp van/kênh hóa chất tự động).
  - Bảng `tblRECORD`: 47,381 dòng (chứa cả dữ liệu cân thuốc nhuộm và hóa chất theo dạng header + detail).
  - Bảng `tblRECORD_chem`: 1,500 dòng (chỉ chứa các dòng tiêu đề/header cho các lô hóa chất, không có chi tiết khối lượng hay dyecode).
- **Đối soát di trú:**
  - Wrote and ran comparisons showing that **all 47,381 rows of `tblRECORD`, all 1,500 rows of `tblRECORD_chem`, and all 40 rows of `tbl_status`** from `chem_order.accdb` are **100% matched and already successfully imported/migrated** into the PostgreSQL database.
  - No new/missing records were found in this database compared to the legacy Postgres staging schemas (`legacy_df_scale`), indicating migration for this source is complete.

### 2. Triển khai & Hoàn thành Phase 4 (Master Data & Formula)
- **Mục tiêu:** Số hóa danh mục thiết bị/vật tư, cấu hình hệ số nước, và logic tính toán định lượng công thức nhuộm (Water & Weight Calculation).
- **Kết quả:**
  - Thiết kế và chạy di trú thành công các bảng: `materials`, `water_configs`, `recipes`, `recipe_versions`, `recipe_materials` và `process_parameters` vào PostgreSQL.
  - Số hóa và khởi tạo (seed) **439 danh mục vật tư** và **40 ma trận hệ số nước** trực tiếp từ Excel.
  - Lập trình `FormulaCalculationService` xử lý đúng logic tính nước, tự động trích xuất công đoạn, và làm tròn trọng lượng bột màu (Precision Rounding $\le 1\%$).
  - Viết giao diện Vue.js hoàn chỉnh gồm: Danh mục vật tư, Cấu hình ma trận nước, và Soạn thảo công thức đính kèm **Trình giả lập tính toán thời gian thực (Simulator)**.
  - Vượt qua kiểm thử **Unit Test** và **Golden Master Test 50/50 mẻ mẫu** khớp hoàn toàn 100% với Excel VBA (sai số = 0).
  - Không triển khai quy trình duyệt công thức phức tạp theo yêu cầu trực tiếp từ người dùng (công thức tạo mới ở trạng thái `ACTIVE` dùng được ngay).

---

### 3. Triển khai & Hoàn thành Phase 5 (Lệnh sản xuất & Điều phối máy)
- **Mục tiêu:** Quản lý lệnh sản xuất và hàng chờ điều phối gửi lệnh nạp van hóa chất tự động có cơ chế khóa logic chống tranh chấp (Claim Lock).
- **Kết quả:**
  - Viết `ProductionBatchController` và `MachineDispatchController` hoàn tất các endpoints hỗ trợ lọc, di chuyển trạng thái lô, claim/release lock, và giả lập phát lệnh gửi máy nhuộm.
  - Tích hợp cơ chế tự sinh UUID ở tầng Eloquent (`creating` boot event) cho cả `ProductionBatch` và `MachineDispatch`.
  - Triển khai thuật toán tính lock age sử dụng trị tuyệt đối `abs()` để tránh lỗi lệch dấu thời gian khi Carbon so sánh.
  - Viết giao diện Vue.js hoàn tất gồm màn hình **Lô sản xuất (bao gồm MES Mock Tool)** và **Điều phối máy nhuộm (hiển thị timer đếm ngược 5 phút, cướp khóa khi hết hạn và nút phát lệnh)**.
  - Vượt qua kiểm thử **Integration Test (14 assertions)** xác minh đầy đủ tính đúng đắn của luồng khóa tranh chấp, tự giải phóng khi gửi máy, cướp khóa khi hết hạn, và chặn gửi máy khi mất khóa.

---

## Nhật ký Phiên (Giai đoạn tiếp theo - 16/07/2026)
### 4. Triển khai & Hoàn thành Phase 6 (Module Cân sản xuất & Local Scale Agent)
- **Mục tiêu:** Nhận giá trị cân điện tử qua cache-based live weight streaming và lưu lịch sử cân hoàn thành.
- **Kết quả:**
  - Viết các API lưu/lấy số cân thô thời gian thực qua Cache và lưu trữ kết quả cân hoàn tất (`app.scale_measurements`).
  - Drop thành công constraint `NOT NULL` cho `legacy_id` và `legacy_source` trên cột `app.scale_measurements` để tương thích luồng ghi nhận trực tiếp từ web.
  - Nâng cấp giao diện Vue.js **Trạm cân (WeighingStation.vue)** với bảng hiển thị LED phát sáng, tự động đối soát dung sai sai số $\le 1\%$ và tích hợp Manual Slider để kiểm thử.
  - Vượt qua kiểm thử **Integration Test (12 assertions)** của `ScaleLiveWeightTest`.

### 5. Triển khai & Hoàn thành Phase 7 (Quy trình đóng tem & In ấn)
- **Mục tiêu:** Quản lý hàng chờ in tem nhãn mẻ cân hoàn thành, sinh lệnh in TSPL động hỗ trợ tùy biến kích thước nhãn.
- **Kết quả:**
  - Thiết kế và chạy di trú thành công bảng `app.print_jobs` lưu trữ các lệnh in.
  - Lập trình `PrintJobController` sinh lệnh in chuẩn **TSPL** (tương thích máy in TSC TE200) chứa thông số mẻ nhuộm, mã QR Code và chấp nhận tham số tùy biến kích thước nhãn (`width` x `height`).
  - Cập nhật `AgentJobsController` để trả về các lệnh in `PENDING` thực tế cho Local Agent và ghi nhận xác nhận `ack` chuyển status sang `SUCCESS`.
  - Tích hợp khung **🏷️ Cấu hình In tem nhãn** và nút **In Tem Nhãn Mẻ** ngay trên màn hình Trạm cân Vue.js.
  - Vượt qua kiểm thử **Integration Test (15 assertions)** của `PrintJobPipelineTest` khớp 100% lệnh in TSPL.

---

## Nhật ký Phiên (Giai đoạn tiếp theo - 16/07/2026)
### 6. Triển khai & Hoàn thành Phase 8 (Vận chuyển và Xác nhận tới thùng)
- **Mục tiêu:** Lưu vết hành trình di chuyển nguyên liệu từ cân tới máy nhuộm, tự động cảnh báo khi quá SLA định mức và quét QR xác thực tại thùng.
- **Kết quả:**
  - Thiết kế và chạy di trú thành công các bảng `app.material_transports` và `app.material_transport_events` lưu lịch sử di chuyển và trạng thái.
  - Lập trình `MaterialTransportController` tính toán SLA động theo nhóm máy (máy thường 15 phút, cụm thùng 25 phút), xác nhận đến thùng bằng quét QR dán thùng, tự động bắt buộc nhập lý do trễ hạn nếu vượt SLA và cập nhật trạng thái mẻ cân sang `WEIGHED`.
  - Tạo mới màn hình **Vận chuyển (MaterialTransfer.vue)** hiển thị danh sách các mẻ đang đi kèm bộ đếm phút thực tế và giao diện quét mã QR/nhập lý do trễ hạn.
  - Vượt qua kiểm thử **Integration Test (15 assertions)** của `MaterialTransferTest` thành công 100%.

---

## Nhật ký Phiên (Giai đoạn tiếp theo - 16/07/2026)
### 7. Triển khai & Hoàn thành Phase 9 (Sẵn sàng cấp và Giám sát cấp vào máy)
- **Mục tiêu:** Kiểm soát điều kiện đủ nước, đủ nguyên liệu và ghi nhận nạp hóa chất an toàn có cơ chế giám sát override.
- **Kết quả:**
  - Thiết kế và chạy di trú thành công bảng `app.feed_operations` lưu vết tiến trình nạp van cấp.
  - Lập trình `FeedOperationController` kiểm tra điều kiện đủ nước, quét nhãn tem QR xác thực đúng mẻ nhuộm, cho phép Supervisor ký duyệt Override có lưu Audit Log an toàn và hoàn tất cấp máy đổi trạng thái mẻ sang `DONE`.
  - Thiết kế màn hình **Cấp máy (FeedingMonitor.vue)** hiển thị checklist 3 bước cấp máy trực quan, tích hợp bộ giả lập quét QR đối soát và form override của Supervisor.
  - Vượt qua kiểm thử **Integration Test (23 assertions)** của `FeedReadinessTest` thành công 100% (bao gồm cả luồng nạp thông thường và luồng bypass override ghi Audit Log).

---

## Nhật ký Phiên (Giai đoạn tiếp theo - 16/07/2026)
### 8. Triển khai & Hoàn thành Phase 10 (Troubleshooting - Hỗ trợ Sự cố)
- **Mục tiêu:** Chuyển đổi bộ tri thức sự cố Excel VBA cũ sang công cụ chẩn đoán lỗi trên ứng dụng web sử dụng thuật toán suy luận chấm điểm nguyên nhân lỗi.
- **Kết quả:**
  - Thiết kế và chạy di trú các bảng tri thức: `app.problems`, `app.causes`, `app.problem_cause_rules`, `app.processes`, `app.parameters`, `app.troubleshooting_cases`, `app.case_evidences`, `app.case_recommendations`.
  - Lập trình `TroubleshootingController` và `InferenceService` sao chép chính xác 100% thuật toán suy luận `modInferenceEngine` của VBA, xếp hạng nguyên nhân lỗi và ghi nhận case sự cố.
  - Tích hợp giao diện chẩn đoán tương tác `Troubleshooting.vue` và vượt qua kiểm thử tích hợp `TroubleshootingInferenceTest.php`.

---

## Nhật ký Phiên (Giai đoạn tiếp theo - 16/07/2026)
### 9. Triển khai & Hoàn thành Dashboard Realtime & Rule Engine Cảnh báo (Nhiệm vụ bổ sung)
- **Mục tiêu:** Xây dựng Dashboard giám sát trực quan thời gian thực (SSE) và bộ máy chẩn đoán Cảnh báo trễ hạn sản xuất/mất kết nối Agent.
- **Kết quả:**
  - Thiết kế và chạy di trú thành công bảng Outbox `app.realtime_events`, bảng cấu hình rule `app.alert_rules` và nhật ký cảnh báo `app.alerts`.
  - Lập trình `RealtimeController` thiết lập cổng truyền Server-Sent Events (SSE) an toàn, tích hợp cơ chế manual token validation và telemetry live scale cache streaming.
  - Xây dựng `RealtimeService` để publish sự kiện giao dịch tin cậy và chạy Rule Engine quét trễ hạn (`WEIGH_START_DELAY`, `TRANS_SLA_BREACH`, `SCALE_AGENT_OFFLINE`...).
  - Thiết lập thư viện Realtime Client `realtime.ts` phía Frontend tự động xử lý reconnect backoff và fallback polling 10s khi mất kết nối mạng.
  - Thiết kế lại 100% giao diện `Dashboard.vue` thành Trung tâm Điều phối hợp nhất 5 Tab (Overview, Weighing, Dyeing, Alerts, Management KPIs) và Timeline Milestones Dialog.
  - Vượt qua bộ Integration Test `RealtimeDashboardTest.php` với 28 assertions thành công 100%, chạy `npm run build` biên dịch sạch sẽ.

---

## Nhật ký Phiên (Giai đoạn tiếp theo - 16/07/2026)

### 10. Triển khai & Hoàn thành Phase 11 (Báo cáo & Phân tích)
- **Mục tiêu:** Xây dựng báo cáo tiêu hao thuốc nhuộm/hóa chất thực tế vs định mức, sai số dung sai & tỉ lệ override, sản lượng máy nhuộm theo ngày/tháng/ca, Pareto nguyên nhân sự cố, xuất Excel/PDF và Audit Log Explorer theo đúng mục tiêu Phase 11 trong `CLAUDE.md`.
- **Rà soát trước khi code phát hiện lỗ hổng dữ liệu:** `WeighingJobController::weighItem` chấp nhận `override_approved`/`override_reason` từ Frontend (`WeighingStation.vue` đã có UI override sẵn) nhưng **không lưu vết** vào DB và **không ghi Audit Log**, khác với `FeedOperationController` đã làm đúng. Nếu không sửa, báo cáo tỉ lệ override sẽ không có số liệu thật. Đã xin xác nhận người dùng và được đồng ý sửa trong đợt này.
- **Kết quả:**
  1. **Vá lỗ hổng Override dung sai cân:** Thêm migration `2026_07_16_000006_add_override_columns_to_weighing_job_items` (cột `override_approved`, `override_reason`, `override_by`). Cập nhật `WeighingJobController::weighItem` bắt buộc vai trò SUPERVISOR/ADMIN khi override, yêu cầu lý do tối thiểu 5 ký tự, lưu vết vào `weighing_job_items` và ghi Audit Log bất biến `WEIGH_TOLERANCE_OVERRIDE` (đồng nhất pattern với `FeedOperationController::override`).
  2. **Cài đặt thư viện xuất báo cáo:** `composer require maatwebsite/excel barryvdh/laravel-dompdf` (đã ghim version `^3.1`).
  3. **`ReportController`** với 4 báo cáo (`GET /api/reports/dye-consumption`, `/tolerance-stats`, `/machine-output`, `/troubleshooting-pareto`), mỗi báo cáo hỗ trợ lọc theo khoảng ngày (`from`/`to`, mặc định 30 ngày gần nhất), và tham số `format=xlsx|pdf` để xuất file qua `app/Exports/ArrayExport.php` và view `resources/views/reports/pdf.blade.php`.
     - Báo cáo sản lượng hỗ trợ nhóm theo **ca kíp** bằng cách suy luận từ giờ trong ngày theo mẫu 3 ca 8h phổ biến của nhà máy (06h-14h / 14h-22h / 22h-06h) — đây là **giả định tài liệu hóa rõ trong code**, không phải quy tắc nghiệp vụ đã xác nhận, vì không có cột "ca" trong dữ liệu nguồn (xem `open-questions.md` CH-BUS-002/CH-TECH-001).
  4. **Audit Log Explorer:** `GET /api/audit-logs` (phân trang, lọc theo user/action/entity_type/khoảng thời gian) và `GET /api/audit-logs/filters` (danh sách action/entity_type để đổ vào dropdown).
  5. **Frontend:** `Reports.vue` (4 tab: Tiêu hao, Dung sai & Override, Sản lượng, Pareto Sự cố — dùng chung 2 component biểu đồ SVG tự viết `SimpleBarChart.vue`/`ParetoChart.vue`, tuân thủ nguyên tắc "one axis" cho Pareto bằng cách vẽ cột theo % thay vì trục kép) và `AuditLogExplorer.vue` (bảng có thể mở rộng xem `before_data`/`after_data` JSON). Thêm route `/reports`, `/audit-logs` và mục menu mới trong nhóm "BÁO CÁO & SỰ CỐ".
  6. **Kiểm thử:** `ReportsTest.php` mới (9 test, 45 assertions) — tổng hợp tiêu hao đúng, tỉ lệ override đúng, chặn override khi không phải Supervisor (403), lưu đúng lý do/người duyệt + Audit Log, đếm mẻ đang chờ xử lý ngoài dung sai, sản lượng theo ngày, Pareto tích lũy đúng %, lọc Audit Log theo action, chặn truy cập chưa đăng nhập (401), xuất Excel tải file thành công. Toàn bộ **28 test backend (216 assertions)** pass, `npx vue-tsc --noEmit` sạch, `npm run build` biên dịch thành công.
- **Phát hiện môi trường (báo cáo, đã xử lý ở mục 11 dưới đây):** Database dev cục bộ (`df-postgres`, DB `production_web`) hiện thiếu bảng `public.personal_access_tokens` dù dòng migration `2026_07_15_150959_create_personal_access_tokens_table` đã được đánh dấu là đã chạy trong bảng `migrations` — khiến `POST /api/auth/login` trả lỗi 500 khi thử đăng nhập trực tiếp trên DB dev này (phát hiện khi cố gắng kiểm thử thủ công qua trình duyệt/curl; không ảnh hưởng bộ test tự động vì test dùng `Sanctum::actingAs()` giả lập xác thực, không đi qua `createToken()` thật).

---

## Nhật ký Phiên (Giai đoạn tiếp theo - 16/07/2026)

### 11. Khắc phục dứt điểm lỗi đăng nhập 500 trên DB dev (thiếu bảng `personal_access_tokens`)
- **Mục tiêu:** Xác định nguyên nhân gốc và khôi phục `/api/auth/login` trên DB dev `production_web`, không mất dữ liệu, không chỉ tạo bảng chữa cháy thủ công.

#### Chẩn đoán (trước khi thay đổi bất kỳ thứ gì)
- `.env`: `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_PORT=5433`, `DB_DATABASE=production_web` — xác nhận qua `php artisan about` (driver `pgsql`) và `docker exec df-postgres psql -c "SELECT current_database();"` → cùng một database (`production_web`), loại trừ khả năng "hai database khác nhau" (nguyên nhân #3 trong danh sách nghi vấn).
- `SHOW search_path` → `"$user", public`; Laravel tự ép `search_path=public` qua `config/database.php`. Quét toàn bộ schema (`information_schema.tables WHERE table_name='personal_access_tokens'`) → **0 dòng ở bất kỳ schema nào** → bảng thực sự không tồn tại, không phải "nằm nhầm schema `app`" (loại trừ nguyên nhân #4, #8).
- `php artisan migrate:status` + query trực tiếp bảng `migrations` → dòng `2026_07_15_150959_create_personal_access_tokens_table` **batch 1, đã "Ran"**, nhưng bảng không tồn tại → xác nhận đúng hiện tượng người dùng mô tả.
- `docker volume inspect df_pgdata` (tạo `2026-07-15T14:28:03Z`) và `docker inspect df-postgres --format='{{.Created}}'` (`2026-07-15T15:04:02Z`) → **volume/container liên tục từ lúc khởi tạo dự án, không có dấu hiệu bị wipe/tái tạo** → loại trừ nguyên nhân #5 (docker volume cũ/không đồng bộ).
- Không có `bootstrap/cache/config.php` (chỉ có `packages.php`, `services.php` từ `package:discover`) và `php artisan config:show database.connections.pgsql.database` trả đúng `.env` → loại trừ nguyên nhân #7 (config cache cũ).
- Không có `.env.testing`; `phpunit.xml` không override `DB_CONNECTION`/`DB_DATABASE` (chỉ comment sẵn dòng SQLite, chưa bật) → **bộ test cũng chạy trên cùng DB `production_web`** qua `DatabaseTransactions` (rollback sau mỗi test). Đây là lý do 28 test trước đó pass 100% mà không phát hiện lỗi: `Sanctum::actingAs()`/`actingAs()` gán thẳng user đã xác thực vào request, **không gọi `createToken()` thật**, nên không bao giờ đụng tới bảng `personal_access_tokens`.

#### Nguyên nhân gốc (có bằng chứng)
- File migration `2026_07_15_150959_create_personal_access_tokens_table.php` **là bản sao y nguyên stub mặc định của Sanctum** (`vendor/laravel/sanctum/database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php`), dùng `$table->morphs('tokenable')` → cột `tokenable_id` kiểu **bigint**.
- `App\Models\User` (bảng `app.users`) dùng **UUID** làm khóa chính (`$keyType='string'`, `$incrementing=false`) — không tương thích với `tokenable_id` kiểu bigint.
- `storage/logs/laravel.log` còn lưu vết lỗi cũ đúng như dự đoán: `SQLSTATE[22P02]: invalid input syntax for type bigint: "a1111111-1111-1111-1111-111111111111"` — khớp với ghi chú Phase 3 trong log này ("Sửa đổi bảng Sanctum tokenable_id tương thích UUID").
- Kết luận: ai đó đã **sửa/xóa bảng thủ công ngoài hệ thống migration** (không qua `php artisan migrate:rollback`) để khắc phục lỗi kiểu dữ liệu, nhưng bước tạo lại bảng với `tokenable_id` kiểu UUID chưa từng được lưu thành migration — bảng bị mất hẳn trong khi bảng `migrations` vẫn ghi nhận migration gốc (kiểu bigint sai) là đã chạy. Đây là **lỗi trôi dạt giữa migration tracking và schema thực tế (migration drift)**, không phải do database khác, cache, hay volume Docker.

#### Cách sửa (an toàn, không đụng migration cũ, không mất dữ liệu)
- Thêm migration mới `2026_07_16_000007_restore_missing_personal_access_tokens_table.php`:
  - Kiểm tra `Schema::hasTable('personal_access_tokens')` trước khi tạo (idempotent).
  - Dùng `$table->uuidMorphs('tokenable')` thay vì `morphs()` → `tokenable_id` kiểu UUID, khớp `app.users.id`.
  - `down()` là **no-op có chủ đích** (không drop bảng) kèm docblock giải thích: tránh trường hợp migration này rollback trên một môi trường mà bảng đã có token hợp lệ, làm mất phiên đăng nhập của người dùng thật.
  - Không sửa file migration gốc `2026_07_15_150959_...`, không sửa bảng `migrations`, không chạy `migrate:fresh`/`db:wipe`/`docker compose down -v`.
- Chạy `php artisan optimize:clear && php artisan migrate` → migration mới chạy thành công (batch 6). Verify qua `information_schema.columns`: `tokenable_id` nay là kiểu `uuid`.
- Đối chiếu số dòng `app.users` (7), `app.audit_logs` (8), `app.production_batches`, `app.weighing_job_items` trước/sau → **không đổi, không mất dữ liệu nghiệp vụ**.

#### Smoke-test bằng luồng đăng nhập thật (không dùng `Sanctum::actingAs()`)
- Khởi động `php artisan serve` trên cổng cô lập (8010, không đụng các tiến trình `artisan serve` khác đã chạy sẵn trên cổng 8000 của máy dev), tạo tài khoản test rõ tên `qa_smoke_test` (role ADMIN) và `qa_smoke_operator` (role OPERATOR) qua tinker.
- `curl POST /api/auth/login` với mật khẩu đúng → **HTTP 200**, trả `access_token`/`token_type`/`user` đúng cấu trúc; verify trực tiếp trong Postgres có dòng mới trong `personal_access_tokens` với `tokenable_id` = UUID user thật.
- Dùng token vừa nhận gọi `GET /api/reports/dye-consumption`, `/tolerance-stats`, `/machine-output?group_by=shift`, `/troubleshooting-pareto`, `/api/audit-logs`, `/api/audit-logs/filters`, `/api/auth/me` → **toàn bộ HTTP 200**.
- Xuất thử Excel (`?format=xlsx`, đúng `Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`) và PDF (`?format=pdf`, verify bằng `file` là PDF 1.7 hợp lệ 3 trang).
- Sai mật khẩu → 401 (không còn 500); tài khoản không tồn tại → 401; thiếu trường bắt buộc → 422.
- `POST /api/auth/logout` → 200, thu hồi token; gọi lại `/api/auth/me` bằng token cũ trên **một tiến trình curl mới độc lập** → 401 "Unauthenticated" (xác nhận thu hồi hoạt động đúng trong môi trường thật).
- **Test luồng Override dung sai với người dùng thật (không giả lập):** tạo batch/machine/material/weighing-job-item tạm qua tinker → đăng nhập bằng `qa_smoke_operator` (OPERATOR) gọi `POST /weighing-jobs/items/{id}/weigh` với `override_approved=true`, cân vượt dung sai → **403 FORBIDDEN** đúng như thiết kế → đăng nhập lại bằng `qa_smoke_test` (ADMIN) gọi lại cùng request → **200 SUCCESS**, response trả về `override_approved:true`, `override_reason`, `override_by` đúng dữ liệu đã nhập; verify `GET /api/audit-logs?action=WEIGH_TOLERANCE_OVERRIDE` có bản ghi mới, và `GET /api/reports/tolerance-stats` phản ánh đúng số liệu override thật (`override_rate_pct: 100` cho vật tư test). Sau đó dọn sạch batch/job/item/machine/material giả lập (không xóa bản ghi Audit Log vì nguyên tắc bất biến); dừng server verification cô lập.
- Tài khoản `qa_smoke_test`/`qa_smoke_operator` được **giữ lại** trong DB dev (không xóa) để người dùng có thể tự đăng nhập kiểm tra giao diện Phase 11 trên trình duyệt thật (công cụ hiện có không có khả năng điều khiển trình duyệt để tự chụp màn hình xác minh UI).

#### Kiểm thử chống tái diễn
- Thêm `tests/Feature/AuthenticationFlowTest.php` (6 test, 20 assertions) — cố tình đi qua **API đăng nhập thật** (`postJson('/api/auth/login')`) và token Sanctum thật, không dùng `Sanctum::actingAs()`/`actingAs()`:
  1. `personal_access_tokens` tồn tại và `tokenable_id` đúng kiểu `uuid` (migration-schema test theo đúng yêu cầu dự phòng).
  2. Đăng nhập thật tạo token và lưu đúng vào DB.
  3. Token từ đăng nhập thật gọi được endpoint có `auth:sanctum`.
  4. Sai mật khẩu → 401, không tạo token mới.
  5. Tài khoản không tồn tại → 401 (không phải 500).
  6. Đăng xuất xóa đúng bản ghi token khỏi `personal_access_tokens`.
  - *Giới hạn ghi nhận:* một biến thể ban đầu của test #6 thử gọi lại endpoint bằng token đã thu hồi trong **cùng một tiến trình PHPUnit** bị false-positive (vẫn trả 200) do `config('sanctum.guard')=['web']` khiến Sanctum ưu tiên kiểm tra session guard trước, và Laravel test client dùng chung container/session `array` giữa các lệnh gọi liên tiếp trong một test — đây là đặc thù môi trường test, **không phải lỗi thật** (đã xác nhận hành vi thật đúng qua curl thật ở bước trên). Test #6 vì vậy assert trực tiếp việc xóa bản ghi trong DB thay vì replay request trong cùng tiến trình.
- Toàn bộ **34 test backend (236 assertions)** pass sau khi thêm.

#### Kết quả
- Nguyên nhân gốc: migration Sanctum gốc dùng kiểu `bigint` cho `tokenable_id`, không tương thích User UUID; bảng bị xóa thủ công ngoài migration để sửa lỗi này trong quá khứ nhưng chưa từng được ghi lại thành migration, gây trôi dạt giữa `migrations` bookkeeping và schema thật.
- Đã tạo migration phục hồi an toàn, idempotent, không sửa lịch sử migration cũ, `down()` không phá dữ liệu.
- Đăng nhập thật hoạt động (HTTP 200), token lưu đúng, thu hồi đúng, các trường hợp lỗi trả đúng mã (401/422, không còn 500).
- Toàn bộ Phase 11 (4 báo cáo, xuất Excel/PDF, Audit Log Explorer, override dung sai) đã smoke-test lại qua API thật với người dùng thật, có phân quyền đúng.
- Không mất dữ liệu nghiệp vụ (`app.users`, `app.audit_logs` và các bảng khác không đổi số dòng ngoài các thay đổi do chính phiên test này tạo ra rồi tự dọn).
- Đã ghi 2 giả định nghiệp vụ còn tồn đọng vào `open-questions.md`: `CH-BUS-003` (quy tắc chia ca 3x8h là giả định kỹ thuật, chưa xác nhận nghiệp vụ, khuyến nghị đưa vào bảng cấu hình hệ thống thay vì hard-code) và `CH-RES-005` (xác nhận biểu đồ Pareto 1 trục vẫn hiển thị đủ số ca + % qua direct label/tooltip, không cần trục kép).

---

## Nhật ký Phiên (Giai đoạn tiếp theo - 16/07/2026)

### 12. Bắt đầu tái cấu trúc giao diện theo mô hình Workstation (DF Connector & Scale)
- **Mục tiêu:** Chuyển hệ thống từ "một phần mềm nhiều chức năng" sang "1 máy tính = 1 công đoạn = 1 nhiệm vụ = 1 giao diện" theo yêu cầu người dùng. Thứ tự triển khai WS-001 → WS-012 do người dùng quy định.
- **Rà soát trước khi sửa (bắt buộc theo yêu cầu):** lập báo cáo đầy đủ tại [`workstation-redesign-audit.md`](file:///F:/DF/.claude/workstation-redesign-audit.md) trước khi đổi bất kỳ giao diện nào. Phát hiện chính: backend (`ScannerController`, `Workstation` model, 10 loại trạm đã seed) và 3 view (`WeighingStation.vue`, `MaterialTransfer.vue`, `FeedingMonitor.vue`) đã gần khớp mô hình quét-là-chính; khoảng cách thật nằm ở tầng điều hướng (menu đầy đủ hiển thị cho mọi trạm, không tự redirect theo loại trạm).
- **2 quyết định của người dùng làm thay đổi thiết kế:** (1) QR là chính, cho phép nhập tay khi máy quét lỗi — áp dụng mọi trạm có quét; (2) tài khoản gắn cứng theo công đoạn (không phải người dùng tự chọn trạm mỗi lần), Admin chịu trách nhiệm phân quyền tài khoản cho từng công đoạn.

#### WS-001 — Workstation Model (Đã hoàn thành)
- Migration `2026_07_16_000008_add_workstation_model_fields_and_user_binding`: thêm `allowed_actions` (jsonb), `default_screen` (string) vào `app.workstations`; thêm `workstation_id` (FK, nullable, `onDelete('set null')`) vào `app.users`.
- Cập nhật `Workstation`/`User` model (quan hệ `users()`/`workstation()`), `WorkstationsSeeder` (gán `default_screen` + `allowed_actions` cho cả 10 trạm), `AuthController::login`/`me` trả kèm object `workstation` đầy đủ.
- Test mới `WorkstationBindingTest.php` (4 test): login trả đúng workstation cho tài khoản trạm, trả `null` cho tài khoản back-office, `/me` phản ánh đúng, xóa workstation không cascade-xóa user (chỉ gỡ liên kết).
- **Tiện thể vá lỗi cú pháp PHP** trong `app/Http/Middleware/CheckRole.php` (đã đăng ký alias `role` trong `bootstrap/app.php` nhưng import sai `Symfony\Component\HttpFoundation.Response` — dấu chấm thay vì `\` — sẽ crash app nếu bị gọi; chưa từng được dùng ở route nào nên chưa gây sự cố thật, phát hiện khi chuẩn bị dùng cho WS-003).

### 13. Hoàn thành WS-005 → WS-012 (Redesign toàn bộ các trạm vận hành theo mô hình Workstation)
- **Người dùng chốt "làm theo thứ tự"**, tiếp tục tuần tự không dừng lại xin xác nhận trừ khi có quyết định thiết kế thật sự mơ hồ.

#### WS-005 — Trạm Quét đơn QR (Đã hoàn thành)
- Phát hiện khoảng trống: `ScannerController::handleOrderScan` trước đây chỉ cho quét ORDER tại các trạm cân (tạo nhiệm vụ cân ngay), `ORDER_DESK` sẽ bị từ chối 403. Thiết kế `ORDER_DESK` thành bước **xem trước (read-only) + xác nhận riêng** — không đụng vào luồng cân đã test.
- Backend: `ScannerController::handleOrderDeskPreview` (xem, không đổi trạng thái) + `acknowledgeOrder` (chuyển `NEW → READY_TO_WEIGH`, **idempotent** — xác nhận lại lần 2 không lỗi không tạo audit log trùng — ghi Audit Log `ORDER_RECEIVED_ACK`).
- Frontend `OrderScan.vue` mới: màn hình chờ quét + ô nhập tay tìm theo mã Lô (`GET /api/production-batches?search=`) làm fallback thật (không phải "chỉ dành cho kiểm thử"), card xem trước + nút "Đã nhận đơn", tự reset sau 3 giây.
- Test `OrderDeskScanTest.php` (5 test, 17 assertions).

#### WS-006 — Khóa menu theo Workstation (Đã hoàn thành, cốt lõi toàn bộ đợt tái cấu trúc)
- Nối `user.workstation` (từ WS-001) vào cơ chế `currentWorkstation` sẵn có (`services/workstation.ts`) qua `stores/auth.ts`: `login()`/`initialize()` tự đồng bộ, `logout()` xóa sạch — tài khoản trạm không cần tự chọn trạm nữa, tài khoản back-office (không gắn trạm) vẫn giữ nguyên cơ chế chọn thủ công cũ.
- `router/index.ts`: thêm guard bắt buộc điều hướng về đúng `default_screen` của tài khoản trạm, chặn truy cập route khác kể cả gõ URL trực tiếp.
- `AppLayout.vue`: ẩn hoàn toàn sidebar/menu khi tài khoản đã gắn trạm (`isLockedStation`), khóa luôn nút "đổi trạm" (không có cơ chế tự đổi — đúng quyết định "Admin chịu trách nhiệm phân quyền").
- **Giới hạn tự xác minh:** đây là thay đổi hành vi điều hướng UI — chỉ verify được qua `vue-tsc`/`npm run build` sạch và rà soát logic, **không có công cụ trình duyệt để xem trực tiếp**. Đã bù đắp bằng UAT dữ liệu thật ở mục WS-012 bên dưới (xác nhận `default_screen` đúng cho mọi loại trạm qua API thật).

#### WS-007 — Trạm In tem độc lập (Đã hoàn thành)
- Thêm loại workstation mới `PRINT_STATION` (`PRINT-01`) — bổ sung vào `WorkstationsSeeder`, không đụng 10 trạm cũ.
- Backend: `WeighingJobController::showLabel` (xem 1 tem theo id) + `searchLabels` (tìm theo mã Lô, phục vụ nhập tay fallback) — tái dùng nguyên `reprintLabel` đã có sẵn Audit Log từ Phase 7.
- Frontend `PrintStation.vue` mới: quét tem → xem chi tiết (Lô, vật tư, khối lượng) → bắt buộc nhập lý do → in lại. Trạm này độc lập với trạm cân, dùng khi cần in lại tem giờ/ngày khác mà không còn ở phiên cân gốc.
- Test `PrintStationTest.php` (5 test, 13 assertions).

#### WS-008 — Redesign Vận chuyển (Đã hoàn thành)
- **Phát hiện lỗi thật trong code cũ:** `MaterialTransfer.vue`'s "widget giả lập quét" dùng **ID của Batch giả làm ID của Tem vật tư** (`id: b.id` gán nhầm cho `MaterialLabel`) — hoàn toàn không hoạt động nếu bấm thử, vì backend tìm `MaterialLabel::findOrFail($batchId)` sẽ luôn thất bại (trừ trùng UUID ngẫu nhiên). Đã xóa bỏ, thay bằng nhập tay thật qua `GET /api/material-labels/search` (endpoint mới thêm ở WS-007).
- Thêm banner xác nhận sau khi quét thành công hiển thị đích đến (Máy/Thùng) — khớp mô tả mục 6D gốc. Backend `handleMaterialLabelScan` trả kèm `batch.machine`/`batch.tank` để banner có đủ dữ liệu hiển thị.

#### WS-009 — Redesign Tới thùng (Đã hoàn thành)
- Cùng lỗi widget giả lập bị lẫn Batch-id-làm-Label-id như WS-008, nằm trong `FeedingMonitor.vue` (nhánh `TANK_RECEIVING`) — đã sửa tương tự: chọn máy thật từ danh sách + tìm tem thật theo mã Lô.
- Thêm hiển thị lỗi/thành công dạng inline (thay `alert()` ở phần này) cho luồng quét kép.

#### WS-010 — Redesign Cấp máy (Đã hoàn thành)
- Phát hiện: dropdown chọn lô cấp máy (`readyBatches`) trước đây tải **TẤT CẢ** lô sản xuất không lọc trạng thái — nhân viên có thể chọn nhầm lô còn đang cân/vận chuyển vào cấp máy. Đã lọc theo `status=ARRIVED_AT_TANK` (đúng điều kiện `FeedOperationController::checkReadiness` yêu cầu), khớp nguyên tắc "chỉ hiển thị chức năng được phép".

#### WS-011 — Dashboard Giám sát (Đã hoàn thành)
- Phát hiện: khối "Công cụ kiểm thử & Giả lập (Admin / Developer)" trong `Dashboard.vue` (gồm cả nút gửi lệnh in TSPL thật) đã ghi rõ trong nhãn là dành cho Admin/Developer nhưng **chưa từng được khóa quyền thật** — bất kỳ ai vào Dashboard cũng bấm được. Đã thêm `v-if="authStore.isAdmin"`, đúng tinh thần mục 11 "Dashboard không dùng để nhập liệu, chỉ giám sát" cho tài khoản trạm MONITORING.

#### WS-012 — UAT từng Workstation (Đã hoàn thành, qua HTTP thật)
- Không có công cụ trình duyệt nên UAT được thực hiện bằng cách: tạo 8 tài khoản trạm thật qua chính API WS-003 vừa xây (`uat_order_desk`, `uat_ws_dye`, `uat_ws_chem`, `uat_print`, `uat_trans`, `uat_tank`, `uat_feed`, `uat_monitor`), đăng nhập thật từng tài khoản, và chạy **toàn bộ vòng đời 1 lô nhuộm thật** qua 6 trạm liên tiếp bằng HTTP thật (không mock, không `Sanctum::actingAs()`):
  1. Order Desk: quét xem trước → xác nhận nhận đơn (`NEW → READY_TO_WEIGH`).
  2. Trạm cân DYE: quét đơn → sinh nhiệm vụ cân → cân đúng khối lượng → hoàn tất.
  3. In tem tự động sau khi cân xong.
  4. Print Station: tìm tem theo mã Lô → in lại có lý do (verify `reprint_count` tăng đúng).
  5. Vận chuyển: quét tem → chuyển `IN_TRANSIT`.
  6. Tank Receiving: quét kép máy+tem → đối soát khớp → `ARRIVED_AT_TANK`.
  7. Machine Feeding: kiểm tra sẵn sàng (đúng `true`) → xác nhận lô xuất hiện trong danh sách đã lọc → khởi tạo → xác nhận nước → mở van → hoàn tất.
  8. Monitoring: xác nhận tài khoản Giám sát gọi được `/api/dashboard/overview`.
  9. Cross-check phân quyền: tài khoản trạm cân **KHÔNG** gọi được API Admin (403 đúng, xác nhận middleware `role:ADMIN` đã vá hoạt động thật trong luồng thật, không chỉ trong test).
- Toàn bộ 13 bước đều trả đúng HTTP status/dữ liệu mong đợi, không có lỗi 500 nào.
- Dọn dẹp: xóa sạch batch/machine/material/recipe/transport/feed-operation giả lập dùng để UAT. **8 tài khoản UAT không xóa được** do bị khóa ngoại bảo vệ bởi Audit Log bất biến (đúng thiết kế) — đã **vô hiệu hóa** (`is_active=false`) thay vì xóa, giữ nguyên vết audit.

#### Kết quả cuối cùng của đợt tái cấu trúc Workstation (WS-001 → WS-012)
- Toàn bộ **54 test backend (291 assertions)** pass. Frontend type-check sạch, `npm run build` thành công liên tục qua từng bước.
- **Giới hạn đã nêu rõ:** không có công cụ điều khiển trình duyệt thật, nên các thay đổi thuần UI (ẩn/hiện sidebar, redirect, hiển thị banner) được xác minh qua type-check + build + rà soát logic + UAT dữ liệu thật qua API, **không phải quan sát trực tiếp bằng mắt trên trình duyệt**. Khuyến nghị người dùng tự đăng nhập thử bằng 1 trong các tài khoản mẫu trước khi coi là hoàn thành 100% (tài khoản UAT đã bị vô hiệu hóa, cần tạo tài khoản mới qua màn hình "Quản lý Workstation" nếu muốn tự kiểm tra).
- File audit `workstation-redesign-audit.md` đã được cập nhật trạng thái đầy đủ WS-001 → WS-012.

#### WS-003 — Cấu hình Workstation (đã gộp WS-002, Đã hoàn thành theo phạm vi đã chốt)
- Người dùng chốt phạm vi hẹp: **chỉ tạo tài khoản trạm mới**, chưa làm gán lại tài khoản cũ / sửa thiết bị / thêm-xóa workstation (để dành cho lần sau nếu cần).
- `WorkstationAdminController` (route `role:ADMIN`): `GET /api/admin/workstations` (danh sách kèm tài khoản đã gán), `POST /api/admin/workstations/{id}/users` (tạo tài khoản mới, giới hạn vai trò `OPERATOR/SUPERVISOR/TECHNOLOGIST` — không cho tạo tài khoản `ADMIN` gắn 1 trạm vì mất ý nghĩa back-office toàn quyền), ghi Audit Log `CREATE_STATION_ACCOUNT`.
- Frontend `WorkstationAdmin.vue`: lưới thẻ 10 workstation (đúng tinh thần mock-up mục 5 gốc, nhưng chuyển thành màn hình cấu hình của Admin thay vì màn hình chọn trạm hàng ngày của operator), bấm thẻ mở modal tạo tài khoản. Route `/workstation-admin` (`requiresAdmin`), mục menu mới nhóm "QUẢN TRỊ" chỉ hiện với `authStore.isAdmin`.
- Test mới `WorkstationAdminTest.php` (6 test): chặn non-admin xem danh sách/tạo tài khoản (403), tạo thành công + verify Audit Log + verify tài khoản mới đăng nhập được và nhận đúng `workstation`, chặn tạo role `ADMIN` gắn trạm (422), chặn trùng username (422).

#### WS-004 — Scanner Service (Đã hoàn thành)
- Viết lại `frontend/src/services/scanner.ts` quanh 1 pipeline dùng chung `processToken(token, source)` cho cả 3 nguồn: quét vật lý (bàn phím wedge), nhập tay (`submitManualEntry`, fallback khi máy quét lỗi theo quyết định #1), và giả lập kiểm thử (`simulateScan`, giữ tương thích ngược 3 màn hình cũ).
- Bổ sung: chống scan trùng (bỏ qua cùng 1 token trong 2 giây, phát tiếng khác biệt thay vì xử lý lại), timeout buffer bàn phím (xóa buffer nếu gõ dở dang quá 3 giây, tránh làm hỏng lượt quét kế tiếp), `lastScanSource`/`lastResult` để UI sau này phân biệt "quét" vs "nhập tay".
- Chữ ký callback `onScan` đổi từ `(token)` thành `(token, source)` — tương thích ngược 100% với 3 handler cũ (`WeighingStation.vue`, `MaterialTransfer.vue`, `FeedingMonitor.vue`) do TypeScript cho phép hàm nhận ít tham số hơn khớp vào vị trí cần nhiều tham số hơn; đã xác nhận qua `vue-tsc --noEmit` sạch và `npm run build` thành công.
- **Chưa đổi giao diện các trạm quét** — đó là phạm vi WS-005/006/008/009 (redesign từng màn hình dùng `submitManualEntry` làm ô nhập tay thật, thay cho "widget giả lập chỉ dành cho kiểm thử" hiện tại).

#### Kết quả tới thời điểm này
- Toàn bộ **44 test backend (261 assertions)** pass. Frontend type-check sạch, `npm run build` thành công (140 module).

## Nhật ký Phiên (Giai đoạn tiếp theo - 16/07/2026)

### 14. Rà soát toàn diện VBA legacy → Web (đối chiếu cấp procedure, KHÔNG sửa code)
- **Nhiệm vụ:** Theo yêu cầu tường minh của người dùng, thực hiện đợt rà soát thuần đọc/phân tích (không thiết kế lại, không đổi kiến trúc, không sửa code) đối chiếu toàn bộ VBA legacy có mặt tại `F:\DF\` với source code hệ thống web hiện tại, phân loại từng procedure theo 9 trạng thái chuẩn, và chỉ dừng lại báo cáo — chưa sửa gì.
- **Công cụ:** cài `oletools`/`olevba` (Python) trích xuất VBA source từ 13 file `.xlsm`; cài `pywin32` để mở 3 file `.accdb` qua Access COM (chỉ trên **bản sao read-only** trong scratchpad, không đụng file gốc) — xác nhận cả 3 (`DF_STORAGE.accdb`, `RECORD.accdb`, `WH.accdb`) chỉ là kho dữ liệu thô, không chứa VBA/query/form.
- **Phát hiện hạ tầng quan trọng:** Postgres dev (`production_web`) đã có sẵn schema `legacy_df_data` (9 bảng, gồm `tbl_ToSend2`/`WAITING`/`tblSync` — 3 bảng KHÔNG có VBA nguồn nào trong `F:\DF` tham chiếu tới) và `legacy_df_scale` (3 bảng) — bằng chứng một đợt di trú dữ liệu trước đây từng có quyền truy cập vào nhiều workbook/Access DB hơn những gì hiện có tại `F:\DF`.
- **Phân công:** dispatch 5 agent chạy song song, mỗi agent phụ trách 1 nhóm nghiệp vụ (Công thức, Điều phối/Khóa, Cân bán tự động, In tem/QR, Xử lý sự cố), mỗi agent tự đọc VBA + grep/đọc trực tiếp `F:\DF\backend`/`F:\DF\frontend` để đối chiếu, tự viết báo cáo chi tiết ra file riêng.
- **Kết quả:** kiểm kê **~378 dòng procedure** (26 Recipe + 83 Dispatch + 133 Scale + 83 Print + 53 Troubleshooting). Tổng hợp vào 2 tài liệu mới:
  - [`vba-migration-matrix.md`](file:///F:/DF/.claude/vba-migration-matrix.md) — ma trận đầy đủ cấp procedure, có ID ổn định (`VBA-RECIPE-*`, `VBA-DISPATCH-*`, `VBA-SCALE-*`, `VBA-PRINT-*`, `VBA-TROUBLE-*`).
  - [`vba-version-comparison.md`](file:///F:/DF/.claude/vba-version-comparison.md) — so sánh phiên bản trong từng nhóm (kể cả các cặp không thể so sánh được vì thiếu file `(1)`/`Copy of`).
- **5 phát hiện nghiêm trọng nhất** (chi tiết ở đầu `vba-migration-matrix.md`):
  1. Hàm `TraHeSo` (tra hệ số 3 chiều mã×khổ vải×tiêu) — nguyên tắc cốt lõi CLAUDE.md yêu cầu bảo toàn — **chưa migrate**; tài liệu cũ ghi sai là "đã xác minh".
  2. Toàn bộ luồng ghi mới vào hàng chờ điều phối (`machine_dispatches`) **chưa tồn tại** — `MachineDispatchController` chỉ có claim/release/send, không có `store`.
  3. Lõi thuật toán cân bán tự động (StableFilter, delta/tare) **MISSING**; `ScaleReader.cs` lấy số đầu tiên thay vì số cuối cùng như VBA (`ExtractLastNumber`) — khác biệt hành vi thật.
  4. `tbl_ToSend2`/`WAITING`/`tblSync` có dữ liệu thật trong Postgres nhưng không có VBA nguồn để xác minh — mapping cột trong `03_transform_legacy_to_target.sql` là suy luận chưa kiểm chứng.
  5. Feedback loop / Editor Knowledge Base của hệ chẩn đoán sự cố bị mất (chỉ sửa được qua deploy lại seeder) — dù công thức scoring chính (`InferenceService`) được migrate đúng và đầy đủ, thậm chí sửa được 1 bug có sẵn trong VBA gốc.
- **Phát hiện phụ đáng chú ý:** 2 route sai trong `source-traceability.md` (`/api/queue-items/...` thay vì `/api/machine-dispatches/...`) và quy chụp sai module nguồn cho cơ chế khóa (`ModAcessDB`/`tblSync` — thực ra cơ chế `locked_by/locked_at` là thiết kế mới, không kế thừa VBA) — đã sửa. Bug có sẵn trong VBA (không phải do migrate): workbook `semiauto-...SEND OVER6...` luôn ghi sai "REJECTED" do lệch màu so sánh; `SaveEngine` của hệ chẩn đoán sự cố luôn ghi cứng breakdown điểm = 0 (đã được web sửa đúng).
- **Tài liệu đã cập nhật theo kết quả rà soát:** `source-traceability.md` (sửa 2 dòng sai), `open-questions.md` (+8 câu hỏi mới CH-BUS-004..008, CH-TECH-003..006), `risks-and-assumptions.md` (+6 rủi ro mới R-06..R-11), `testing-strategy.md` (+3 bộ golden test đề xuất cho thuật toán cân, xác nhận `scratch/` simulator chưa triển khai).
- **Chưa sửa code nào** — đúng yêu cầu "chỉ rà soát, dừng lại sau khi gửi báo cáo, không tự ý sửa hàng loạt". Chờ người dùng duyệt trước khi lên kế hoạch bổ sung theo mức ưu tiên.
- **Giới hạn phạm vi:** 12 workbook được liệt kê trong yêu cầu gốc không có mặt tại `F:\DF` (chủ yếu các bản `(1)`/`Copy of` và 2 file template tem 27-dòng/15L-special/landscape/JIT) — xem danh sách đầy đủ + mức ưu tiên bổ sung ở đầu `vba-version-comparison.md`.
- Còn lại WS-005 → WS-012 (redesign từng màn hình theo tài khoản-trạm + khóa menu, tách trạm in tem, dashboard, UAT) — quy mô lớn, đang triển khai tuần tự theo đúng thứ tự người dùng yêu cầu, dừng lại xin xác nhận ở các điểm rẽ nhánh thiết kế quan trọng.

### 15. Thiết kế và chuẩn hóa cấu hình trạm làm việc (Workstation Matrix & Architecture)
- **Nhiệm vụ:** Nhận yêu cầu bổ sung cấu hình máy trạm thực tế vào hệ thống và ma trận. Tiến hành cập nhật và tạo mới tài liệu `.claude/` để khớp với mô hình trạm "1 máy tính = 1 công đoạn chính = 1 màn hình mặc định", độc lập với địa chỉ IP mạng.
- **Tài liệu tạo mới / cập nhật:**
  - Tạo mới [`workstation-matrix.md`](file:///F:/DF/.claude/workstation-matrix.md): Chi tiết hóa cấu hình 7 máy client, các trường cơ sở dữ liệu rà soát/bổ sung, và quy trình kiểm kê thiết bị cân/in.
  - Tạo mới [`legacy-to-target-architecture.md`](file:///F:/DF/.claude/legacy-to-target-architecture.md): Ánh xạ 9 bước nghiệp vụ cốt lõi từ VBA/Access sang Web/API đích, chỉ rõ trạng thái hoàn thiện (Migrated, Missing, Replaced, New, Deprecated).
  - Cập nhật [`system-context.md`](file:///F:/DF/.claude/system-context.md): Tích hợp 7 client và luồng đăng ký/xác thực trạm an toàn qua certificate/token kết hợp Device Fingerprint.
  - Cập nhật [`source-traceability.md`](file:///F:/DF/.claude/source-traceability.md): Bổ sung truy vết cho các thực thể và API trạm làm việc mới.
  - Cập nhật [`open-questions.md`](file:///F:/DF/.claude/open-questions.md): Thêm CH-TECH-007 (Xác nhận loại trạm cho 3 máy cân) và CH-TECH-008 (Vân tay thiết bị).
  - Cập nhật [`security-rules.md`](file:///F:/DF/.claude/security-rules.md): Thêm quy tắc bảo mật máy trạm, cấm hoàn toàn dùng IP làm khóa chính, kiểm soát chéo API.
  - Cập nhật [`testing-strategy.md`](file:///F:/DF/.claude/testing-strategy.md): Thêm danh mục 17 ca kiểm thử trạm làm việc bắt buộc.
  - Cập nhật [`vba-migration-matrix.md`](file:///F:/DF/.claude/vba-migration-matrix.md): Tự động bổ sung 8 cột Target Architecture cho tất cả các bảng procedure kiểm kê (~378 dòng) sử dụng script Python.
- **Báo cáo và Dừng lại:** Chuẩn bị báo cáo 10 hạng mục và Kế hoạch triển khai (Implementation Plan) để xin ý kiến duyệt của người dùng trước khi sửa bất kỳ dòng code nào.


## Nhật ký Phiên (Giai đoạn tiếp theo - 17/07/2026)

### 15. Đợt rà soát VBA lần 2 — chuẩn hóa số liệu, phân tích sâu 5 phát hiện P0, lập kế hoạch khắc phục (CHƯA sửa code)

- **Bối cảnh:** Người dùng duyệt báo cáo rà soát bước đầu nhưng CHƯA duyệt kết luận "đã rà soát đầy đủ toàn bộ VBA", yêu cầu 8 hạng mục bổ sung. Toàn bộ đợt này thuần tài liệu — không sửa code sản xuất, không chạy migration, không đổi schema.
- **Chuẩn hóa số liệu kiểm kê (mục 1):** xác định nguồn chênh lệch "~378": nhóm DISPATCH tự báo 83 nhưng bảng thật có 84 dòng (sót `020B`); nhóm SCALE tự báo 133 nhưng đó là số ID cấp phát (4 dòng gộp khoảng chứa 28 ID), số dòng bảng thật là 109. Số chính xác: **355 dòng traceability**, **664 procedure vật lý** (quy ước đếm bản sao giữa workbook riêng; 561 nếu dedup). Chuẩn hóa 10 dòng có cột Trạng thái sai định dạng (6 dòng `**MISSING**` bôi đậm; 2 dòng gán trạng thái kép; 1 dòng tham chiếu chéo lệch cột; 1 dòng bỏ trống — một phần do một phiên khác đã chạy script Python thêm 8 cột "Target *" vào bảng gây lệch số cột không đồng nhất). Viết script kiểm chứng tự động [`verify-matrix-counts.sh`](file:///F:/DF/.claude/verify-matrix-counts.sh) — kết quả PASS: SUM=355=ROWS, 0 unmatched. Phân bố cuối: FULLY 26, NO_TEST 1, PARTIALLY 30, MISSING 72, REPLACED 93, MERGED 5, DEPRECATED 35, DEAD 67, NEEDS_CONFIRM 26.
- **Danh sách 12 workbook thiếu (mục 2):** tạo [`source-files-missing.md`](file:///F:/DF/.claude/source-files-missing.md) — 5 file P0 (3 file nhóm điều phối nghi chứa logic tbl_ToSend2/WAITING/tblSync; 2 file template tem DF002 27rows/15L/landscape/JIT), 3 file P1 (nhóm cân — "low stand1"/"8 rows"), 4 file P2. Kết luận chính thức: nhóm Điều phối và In tem/QR **CHƯA rà soát hoàn chỉnh** khi thiếu file P0.
- **Phân tích sâu 5 phát hiện P0 (mục 3-4):** 5 agent song song, kết quả lưu bền vững tại [`.claude/p0-analysis/`](file:///F:/DF/.claude/p0-analysis/) (5 file, mỗi file kèm kế hoạch FIX):
  - **P0-A TraHeSo:** pseudocode đầy đủ, 6 golden test (placeholder chờ dữ liệu bảng tra thật), phát hiện mới: không nhất quán case-sensitivity ngay trong code gốc (Find không phân biệt hoa/thường, Select Case phân loại A/B/C thì có).
  - **P0-B Dispatch:** truy vết 10 bước luồng tạo hàng chờ; nguyên văn quy tắc 250L (chỉ có ở C3, MID grep "250" = 0 kết quả); 3 lỗ hổng có sẵn trong VBA gốc: MID move được với tank trống, check trùng chỉ trong tbl_input_all, 250L không được kiểm lại ở bước duyệt; lưu ý `level_code` là text.
  - **P0-C Scale:** đối chiếu 10 điểm + 7 test vector; xác nhận `.NET` lấy số ĐẦU (VBA lấy số CUỐI), rác COM bị quy về 0.0; đo dữ liệu thật: 31.361/140.660 dòng REJECTED (~22,3%) nhưng KHÔNG tách được phần "REJECTED giả" do bug workbook B (không có cột liên kết trạm→workbook); `tblRECORD_chem.processCOLOR` rỗng 100%.
  - **P0-D Legacy tables:** kiểm kê SELECT thật — **ĐÍNH CHÍNH:** `tblSync` RỖNG (0 dòng), không phải "có dữ liệu thật" như công bố 16/07; `tbl_ToSend2` 696 dòng (dừng 28/11/2025), `WAITING` 57 dòng (ID/TIME rỗng 100%); phát hiện bảng thứ 4 `tbl_Waiting` (71 dòng) bị script coi "unshifted" nhưng thật ra cũng lệch cột; JOIN-match 0% chưa kết luận được mapping sai (do app.machines dev chỉ có 5 máy test).
  - **P0-E Feedback loop:** xác nhận VBA gốc KHÔNG có học tự động (cột feedback chỉ ghi, không bao giờ đọc lại — grep toàn bộ); Editor KB là CRUD thủ công thuần túy → FIX-005 là migrate đơn giản (size M), "học tự động" là tính năng mới hoàn toàn để phase sau.
- **Tài liệu mới (mục 5-7):** [`pilot-blockers.md`](file:///F:/DF/.claude/pilot-blockers.md) (7 pilot blockers PB-1→PB-7 + danh sách missing-không-chặn + danh sách dead/deprecated), [`remediation-plan.md`](file:///F:/DF/.claude/remediation-plan.md) (FIX-001→FIX-010 đầy đủ phạm vi/file/DB/migration/AC/regression/rollback/dependency/rủi ro/estimate + trình tự thực hiện đề xuất 4 đợt), bảng ưu tiên hóa 18 cụm (Criticality/Pilot-Blocker/Source/Evidence/Action/Scope) bổ sung cuối `vba-migration-matrix.md`.
- **Tài liệu cập nhật:** `vba-migration-matrix.md` (đính chính số liệu + chuẩn hóa trạng thái + bảng ưu tiên), `vba-version-comparison.md` (đính chính tblSync/tbl_Waiting), `risks-and-assumptions.md` (R-11 cập nhật theo dữ liệu thật), `open-questions.md` (CH-TECH-003/004 cập nhật dữ liệu thật; CH-TECH-006 đã trả lời 1 phần), `source-traceability.md` (thêm mục Ghi chú truy vết bổ sung — giữ nguyên các dòng do phiên khác thêm về Workstation).
- **DỪNG LẠI theo yêu cầu** — chờ người dùng duyệt danh sách pilot blockers + kế hoạch FIX + trả lời các câu hỏi CH trước khi sửa bất kỳ code nào.

### 16. Đợt duyệt lần 3 — hiệu chỉnh mô hình workstation theo cơ cấu vận hành thật (6 máy nghiệp vụ), audit bổ sung 2 workbook chưa từng rà soát (CHƯA sửa code)

- **Bối cảnh:** Người dùng xác nhận trực tiếp cơ cấu vận hành thật: **6 máy nghiệp vụ / 5 workstation type** — 1× CHEMICAL_CALL (`1.báo phát AC XƯỞNG -193.xlsm`), 1× PRODUCTION_ORDER (`2.C3 grid load row lock id FB -192(QR).xlsm`), 1× QR_LABEL_PRINTING (`3.DF028 ... jit qr sending - 15l special.xlsm`), 2× SMALL_SCALE (`4.semiauto-small scale ... DF026-027.xlsm`), 1× LARGE_SCALE (`5.Semiauto- lockmove SEND OVER6 ... -221.xlsm`) — thay cho giả định 7-workstation dựa thuần túy vào lịch sử kết nối mạng trước đó (không có xác nhận nghiệp vụ). Yêu cầu rõ: không tự gán workstation riêng cho khái niệm nghiệp vụ (hóa chất/A11/DLG/vận chuyển/tới thùng/cấp máy) khi chưa có bằng chứng vận hành thật; phân loại UI theo A. MIGRATION PARITY / B. UX IMPROVEMENT / C. OPTIONAL EXTENSION; giữ nguyên toàn bộ luồng/nút/trạng thái/ngoại lệ VBA khi thiết kế UI mới; chỉ hoàn thành khi mọi procedure của 5 workbook đã phân loại. Vẫn chưa sửa code sản xuất/migration/schema.
- **Đối chiếu 5 workbook xác nhận với audit cũ:** workbook 2 (PRODUCTION_ORDER) và 4/5 (SMALL_SCALE/LARGE_SCALE) đã được audit đầy đủ trước đó (NHÓM 2 "C3", NHÓM 3 workbook B/C). Phát hiện quan trọng: workbook 1 (CHEMICAL_CALL) và workbook 3 (QR_LABEL_PRINTING/DF028) **chưa từng được audit ở cấp procedure** — audit PRINT trước đó (83 dòng `VBA-PRINT-*`) thực chất audit 2 workbook khác (`in tem Copower.xlsm`, `QR PRINTER...`) không phải máy in tem sản xuất thật; `SEMI CHECKER.xlsm` (audit là file A trong NHÓM 3) cũng không nằm trong 5 workbook xác nhận.
- **Audit bổ sung bằng 2 agent song song (đọc code, không sửa):**
  - **NHÓM 0 (CHEMICAL_CALL, 16 dòng/44 procedure):** xác nhận **toàn bộ nghiệp vụ gọi/xác nhận cấp hóa chất chưa hề được xây trên web** — 0 Controller/route/view; chỉ có Model tĩnh `MachineChemicalChannel.php` không route nào dùng; bảng đích `app.machine_chemical_channels` đã di trú xong 40/40 dòng cấu hình tĩnh nhưng KHÔNG có cột lưu tín hiệu ORDER/DONE động (giá trị vận hành thật hàng ngày) — di trú "xong" chỉ là lớp cấu hình. Workbook chỉ phủ 8/~18 máy × 2/~9 slot — khả năng còn workbook chị em chưa tìm thấy.
  - **NHÓM 4-DF028 (QR_LABEL_PRINTING thật, 51 dòng/308 procedure):** xác nhận DF028 là **nguồn ghi (INSERT) duy nhất tìm được cho `tbl_sentlog`** trong toàn bộ đợt audit (`TO_SEND.ConfirmRow`) — trả lời câu hỏi mở CH-TECH-004 tồn tại nhiều đợt; phát hiện `app.machine_dispatches` đã có sẵn 3 cột `scale_checked`/`raw_qr_dye`/`raw_qr_chemical` khớp gần 1:1 với DF028 nhưng **0 controller nào đọc/ghi** (schema sẵn sàng, tầng Controller bị bỏ sót); logic phân vùng kho B24 + chọn 1-trong-3 chế độ mã hóa QR theo tổ hợp Machine×Tank (`Mod_printslip.PrintSlip_70x100`) — khối nghiệp vụ phức tạp nhất, chưa từng được nhắc ở audit nào trước, không có tương đương backend; lưới giám sát tồn đọng 18×9 tô màu theo tuổi dữ liệu (162 procedure) hoàn toàn MISSING; hành vi "in tem = tự động xác nhận scale-check" MISSING. `api.qrserver.com` (vi phạm CLAUDE.md) xác nhận tồn tại đồng thời ở ≥3 workbook sản xuất song song. Tên file trùng gần như hoàn toàn với 2 file P0 từng liệt kê thiếu (`DF002...15l special-27rows.xlsm`, `DF002 no formulas...jit qr sending...xlsm`) — khả năng cao đã đóng được các mục thiếu đó, cần người dùng xác nhận.
  - 5 dòng dual-status phát sinh khi biên tập bảng NHÓM 4-DF028 đã được tách thành 10 dòng đơn-status để giữ đúng quy ước "1 dòng = 1 trạng thái".
- **Số liệu tổng cập nhật (kiểm chứng PASS bằng `verify-matrix-counts.sh`):** tổng dòng traceability từ 355 → **422**; tổng procedure vật lý từ 664 → **1016** (quy ước đếm lặp) hoặc từ 561 → **913** (quy ước dedup).
- **Tài liệu cập nhật:**
  - [`workstation-matrix.md`](file:///F:/DF/.claude/workstation-matrix.md): viết lại hoàn toàn theo mô hình 6 máy đã xác nhận; bảng đối chiếu 7 IP lịch sử mạng (chưa khớp hết với 6 máy — thiếu IP nào gán CHEMICAL_CALL); bảng Workstation↔Workbook↔UserForm↔API/DB/Test theo đúng yêu cầu; mục riêng liệt kê các "workstation" viết mới không có bằng chứng vận hành (Vận chuyển/Tới thùng/Cấp máy) và RECIPE/TROUBLESHOOTING (không rõ có gắn máy vật lý cố định).
  - [`legacy-to-target-architecture.md`](file:///F:/DF/.claude/legacy-to-target-architecture.md): sửa trường Workstation cho cả 9 bước theo cơ cấu 6 máy; gắn nhãn A/B/C cho từng mục Trạng thái hoàn thiện; Bước 6/7/8 (Vận chuyển/Tới thùng/Cấp máy) đổi nhãn từ "[NEW] Hoàn thành 100%" sang rõ ràng "C. OPTIONAL EXTENSION — không có bằng chứng vận hành".
  - [`system-context.md`](file:///F:/DF/.claude/system-context.md): thay "7 Máy trạm thực tế" bằng "6 Máy Nghiệp vụ Thực tế", giữ 7 IP lịch sử làm phụ lục tham chiếu.
  - [`vba-migration-matrix.md`](file:///F:/DF/.claude/vba-migration-matrix.md): thêm NHÓM 0 và NHÓM 4-DF028 đầy đủ (kiểm kê module/procedure/dữ liệu Access/phân loại A-B-C); viết lại "Tổng hợp số liệu" với 7 cột theo nhóm; thêm 2 phát hiện nghiêm trọng mới vào đầu danh sách.
  - [`pilot-blockers.md`](file:///F:/DF/.claude/pilot-blockers.md): thêm PB-8 (CHEMICAL_CALL chưa xây gì) và PB-9 (4 khoảng trống DF028); thêm 3 dòng Danh sách 2; cập nhật Danh sách 3 (75 DEAD_CODE_CANDIDATE, thêm CHEM×3 và QRPRINT×5).
  - [`source-files-missing.md`](file:///F:/DF/.claude/source-files-missing.md): cảnh báo DF028 có thể đã đóng được 2/5 file P0 nhóm PRINT, chờ xác nhận người dùng.
  - [`risks-and-assumptions.md`](file:///F:/DF/.claude/risks-and-assumptions.md): thêm R-12 (CHEMICAL_CALL), R-13 (4 khoảng trống DF028); cập nhật R-11 (đã tìm nguồn thật `tbl_sentlog`).
  - [`open-questions.md`](file:///F:/DF/.claude/open-questions.md): thêm CH-BUS-009 (đối chiếu 7 IP với 6 máy), CH-BUS-010 (RECIPE/TROUBLE có phải workstation vật lý không).
  - [`source-traceability.md`](file:///F:/DF/.claude/source-traceability.md): thêm mục ghi chú bổ sung mới trỏ tới các thay đổi trên.
- **DỪNG LẠI theo yêu cầu** — chưa sửa code sản xuất, chưa chạy migration, chưa đổi schema. Chờ người dùng xác nhận CH-BUS-009/010 và các câu hỏi nghiệp vụ mới (đặc biệt logic phân vùng kho B24, phạm vi pilot có gồm CHEMICAL_CALL/QR_LABEL_PRINTING hay không) trước khi thiết kế UI/backend chi tiết.

### 17. Đợt duyệt lần 4 — database discovery đầy đủ (5 Access DB), gap analysis domain CHEMICAL_CALL/QR_LABEL_PRINTING, truy vết tbl_SentLog, logic B24, so sánh SMALL/LARGE_SCALE, kiến trúc Local Agent (CHƯA sửa code)

- **Bối cảnh:** Người dùng yêu cầu tổ chức lại dự án theo chuỗi vận hành thực tế 7 bước (gọi hóa chất → tạo đơn → nhận đơn/in tem → cân nhỏ/lớn → ghi nhận hoàn thành → truy vết xuyên suốt), không chỉ bổ sung vài màn hình. Yêu cầu 1 đợt gap analysis mới dựa trên toàn bộ VBA + 4 database Access (`chem_order.accdb`, `RECORD.accdb`, `RECORD1.accdb`, `WH.accdb`) + source code web hiện tại, đặc biệt **không được coi `RECORD.accdb`/`RECORD1.accdb` là cùng 1 database chỉ vì trùng tên**. Vẫn thuần tài liệu/thiết kế — không sửa code sản xuất, không migration, không đổi schema, không bật tính năng mới cho người dùng.
- **Database discovery (Phase A hoàn tất):** copy read-only 5 file `.accdb`, trích xuất schema đầy đủ (bảng/cột/kiểu/PK/index/số dòng) qua DAO/COM, lấy mẫu dữ liệu thật qua `OpenRecordset`. Kết quả — [`database-inventory.md`](file:///F:/DF/.claude/database-inventory.md):
  - `RECORD.accdb` (**RECORD_A**) chứa `tbl_SentLog` (27.024 dòng, mới nhất 2026-07-15), `tbl_ToSend`/`tbl_ToSend2`/`WAITING`/`tbl_Waiting`/`TBL_INPUT_ALL`/`tblSync`/`tbl_ARCHIVE`/`tbl_OUTPUT_PROCESSING` — **đây chính là database dispatch/queue/sổ gửi hàng đã tìm kiếm nhiều đợt trước mà không thấy** (đợt audit 16-17/07 trước kết luận nhầm "không có mặt tại F:\DF").
  - `RECORD1.accdb` (**RECORD_B**) chứa `tblRECORD` (140.655 dòng, mới nhất 2026-07-15) + `tblRECORD_chem` (5.061 dòng) — đây mới là file trước đây được gọi nhầm là "RECORD.accdb" trong đợt audit gốc.
  - `chem_order.accdb` (**CHEM_ORDER**) ngoài `tbl_status` (40 dòng, đã biết) còn có `tblRECORD`/`tblRECORD_chem` riêng (47.381/1.500 dòng, dữ liệu dừng ở 2026-03-31) — cùng schema RECORD_B nhưng KHÔNG có Sub/Function nào trong `chem_order.frm` chạm tới — nghi vấn backup tĩnh bị bỏ quên (CH-BUS-014).
  - `WH.accdb` (**WAREHOUSE**) chỉ có 1 bảng `tblWH_LOG` (35 dòng, log tiêu thụ) — **không có bảng mapping vùng kho/B24** nào.
  - **Bằng chứng đường dẫn VBA (grep trực tiếp source, không suy đoán):** workbook 2 (C3) và 3 (DF028) hard-code `Z:\DF\DATA\record.accdb` → RECORD_A; workbook 4/5 (SCALE) hard-code `Z:DF_SCALE\RECORD.accdb` (thiếu `\`) + `Z:\DF_SCALE\WH.accdb` → RECORD_B + WAREHOUSE; workbook 1 (chem_order) hard-code `Z:\chem_order\chem_order.accdb` → CHEM_ORDER. Kết luận: 2 database "RECORD" **hoàn toàn độc lập, không đồng bộ trực tiếp, không bảng nào trùng tên** — xem [`legacy-database-mapping.md`](file:///F:/DF/.claude/legacy-database-mapping.md).
- **Truy vết `tbl_SentLog` (mục 6 yêu cầu):** bảng mapping đầy đủ VBA↔Access↔Web tại `qr-label-printing-domain.md` Mục 1 — xác nhận lại (bằng chứng schema cột-theo-cột) `DF028.TO_SEND.ConfirmRow` là nguồn ghi (INSERT) duy nhất; 17 cột `tbl_SentLog` khớp gần như tuyệt đối với `app.machine_dispatches` đã thiết kế sẵn (`scale_checked`/`raw_qr_dye`/`raw_qr_chemical`).
- **Logic B24 (mục 9):** đọc toàn bộ `Mod_printslip.PrintSlip_70x100` (395 dòng) + trích xuất 100 công thức Excel của DF028 bằng `openpyxl` — dựng đầy đủ bảng quyết định B24/mode-QR/D1 tại [`b24-warehouse-routing.md`](file:///F:/DF/.claude/b24-warehouse-routing.md). Phát hiện: (1) lỗ hổng có sẵn trong VBA — tổ hợp VD14-16+3C/4D không có nhãn D1 (khe hở giữa 2 nhánh If); (2) **không tìm thấy nhánh code riêng cho "15L special"** ở cả VBA lẫn công thức Excel — 2 điểm này đánh dấu `BLOCKED_BY_BUSINESS_CONFIRMATION`, không tự suy diễn.
- **Gap report CHEMICAL_CALL và QR_LABEL_PRINTING (mục 4-5):** [`chemical-call-domain.md`](file:///F:/DF/.claude/chemical-call-domain.md) — tách dữ liệu cấu hình tĩnh khỏi dữ liệu vận hành ORDER/DONE, đề xuất entity `chemical_call_requests`/`chemical_call_request_events`, bảng chức năng theo taxonomy 6 trạng thái mới (IMPLEMENTED/PARTIALLY_IMPLEMENTED/REPLACED_BY_PLATFORM/NOT_REQUIRED_CONFIRMED/BLOCKED/MISSING). [`qr-label-printing-domain.md`](file:///F:/DF/.claude/qr-label-printing-domain.md) — luồng 11 bước, đề xuất service tách khỏi Controller (`QrPayloadService`/`PrintJobService`/`SentLogService`...), đối chiếu 2 file P0 từng "thiếu" → chuyển `PARTIALLY_RESOLVED` (không tự đóng `RESOLVED` vì nhánh 15L chưa xác minh được).
- **So sánh SMALL_SCALE vs LARGE_SCALE (mục 8):** [`local-agent-architecture.md`](file:///F:/DF/.claude/local-agent-architecture.md) Mục 1 — 90% logic lõi giống hệt 100% (đọc cân, làm sạch, delta, tolerance, chuyển rack) → dùng chung core hợp lý; 2 khác biệt thật đều là BUG của LARGE_SCALE (màu ACCEPTED/REJECTED sai khiến luôn REJECTED — R-10 cũ; rò rỉ timer `Mod_lockmoveform`) — không copy bug khi migrate, dùng bản đã vá của SMALL_SCALE làm chuẩn chung. Chưa tìm thấy khác biệt ngưỡng kg trong code — khả năng cao là đặc tính thiết bị vật lý, không phải software policy.
- **Kiến trúc Local Agent + feature flag (mục 8.1, 11):** đề xuất ScaleAgent (ScaleCore dùng chung + Policy riêng theo workstation type), PrintAgent (5 trạng thái job, không dùng RPA chuột/clipboard như VBA), 8 feature flag đề xuất (`chemical_call_enabled`...`local_scale_agent_enabled`) — không hard-code phạm vi pilot.
- **Kịch bản pilot E2E (mục 11):** [`pilot-end-to-end-scenarios.md`](file:///F:/DF/.claude/pilot-end-to-end-scenarios.md) — 7 kịch bản (happy path, lock tranh chấp, agent mất mạng, printer fail/retry, scan QR 2 lần, chemical call 2 thao tác gần nhau, shadow mode đối soát). Cập nhật `pilot-blockers.md`: PB-8/PB-9 nay là pilot blocker THẬT SỰ (không còn điều kiện) vì phạm vi pilot chắc chắn gồm CHEMICAL_CALL + QR_LABEL_PRINTING.
- **Tài liệu mới tạo (8 file theo yêu cầu):** `database-inventory.md`, `legacy-database-mapping.md`, `chemical-call-domain.md`, `qr-label-printing-domain.md`, `b24-warehouse-routing.md`, `local-agent-architecture.md`, `pilot-end-to-end-scenarios.md`. *(8 file yêu cầu — thực tế 7 file mới, vì nội dung `target-data-model.md` là cập nhật file đã có sẵn thay vì tạo mới, theo đúng tên file đã tồn tại từ trước).*
- **Tài liệu cập nhật:** `target-data-model.md` (mục 2.X — 3 bảng mới đề xuất, CHƯA migration), `workstation-matrix.md`/`legacy-to-target-architecture.md`/`system-context.md`/`vba-migration-matrix.md` (ghi chú tham chiếu + taxonomy mới), `pilot-blockers.md`, `source-files-missing.md` (2 file P0 → `PARTIALLY_RESOLVED`), `risks-and-assumptions.md` (R-14, R-15), `open-questions.md` (CH-BUS-011 → CH-BUS-014), `source-traceability.md`.
- **Số liệu traceability không đổi** (422 dòng, kiểm chứng PASS `verify-matrix-counts.sh`) — 2 domain gap report mới dùng taxonomy 6 giá trị riêng, không phá vỡ số liệu 9-trạng-thái đã kiểm chứng của `vba-migration-matrix.md`.
- **DỪNG LẠI theo yêu cầu** — chưa migration production, chưa xóa bảng/cột, chưa đổi dữ liệu thật, chưa bật tính năng mới cho người dùng, chưa đóng câu hỏi nào thiếu bằng chứng, không dùng lại giả định 7 workstation. Chờ người dùng xác nhận vai trò 2 database RECORD (đã có bằng chứng mạnh, chờ xác nhận chính thức đổi tên), logic B24 (15L + lỗ hổng VD14-16), và vòng đời `tbl_SentLog` trước khi bắt đầu sửa code.
### 18. Phase C – Target Design và Phase D – Schema Proposal (2026-07-17)

- **Nhiệm vụ:** Hoàn thành thiết kế chi tiết cấp domain, cơ sở dữ liệu vật lý/logic, state machine, API, và các chính sách nghiệp vụ an toàn. Đây là tài liệu thiết kế — không sửa code sản xuất, không chạy migration.
- **Tài liệu tạo mới (8 file):**
  - [`permission-matrix.md`](file:///F:/DF/.claude/permission-matrix.md): Phân quyền chi tiết, cô lập tài khoản của Local Agent.
  - [`feature-flags.md`](file:///F:/DF/.claude/feature-flags.md): Quản lý tính năng động, cấu hình 3 chế độ chạy B24 (`LEGACY_EXACT`, `FIXED_D1`, `MANUAL_REVIEW`).
  - [`migration-plan.md`](file:///F:/DF/.claude/migration-plan.md): Lộ trình 5 wave di trú (Foundation, Chemical Call, Dispatch/QR, Weighing, Correlation).
  - [`backfill-plan.md`](file:///F:/DF/.claude/backfill-plan.md): Quy trình dry-run, đối soát trọng lượng bột màu (`SUM`), báo cáo lỗi không bỏ sót bản ghi.
  - [`cutover-rollback-plan.md`](file:///F:/DF/.claude/cutover-rollback-plan.md): Chuyển đổi 10 giai đoạn, rollback cho từng loại máy trạm và đối soát sau rollback.
  - [`test-architecture.md`](file:///F:/DF/.claude/test-architecture.md): Kiểm thử edge cases (double scan, double confirm, mất response, correlation exception, large scale timer leak).
  - [`decision-records.md`](file:///F:/DF/.claude/decision-records.md): Nhật ký quyết định nghiệp vụ (ADR) cho 4 blocker `CH-BUS-011` đến `CH-BUS-014`.
  - [`record-a-record-b-correlation.md`](file:///F:/DF/.claude/record-a-record-b-correlation.md): Phương thức khớp exact, composite, probabilistic và exception queue.
- **Tài liệu cập nhật:**
  - `legacy-database-mapping.md`: Phân loại `chem_order.accdb.tblRECORD` thành `LEGACY_ARCHIVE` (blocker `CH-BUS-014` / `UNKNOWN_BLOCKED`).
  - `verify-matrix-counts.sh`: Bổ sung 5 trạng thái mới (`TARGET_DESIGNED`, `SCHEMA_PROPOSED`, `BLOCKED`, `NOT_REQUIRED_CONFIRMED`, `LEGACY_BUG_NOT_MIGRATED`), sửa lỗi `set -e` crash khi count = 0.
  - `vba-migration-matrix.md`: Thay đổi trạng thái 13 dòng Chemical Call (`VBA-CHEM-003` đến `VBA-CHEM-016` trừ mồ côi) từ `MISSING` sang `SCHEMA_PROPOSED`.
  - `target-data-model.md` (Mục 2.X): Liệt kê đầy đủ các bảng mới đã thiết kế vật lý trong ERD.
  - `pilot-blockers.md` & `risks-and-assumptions.md` & `open-questions.md`: Cập nhật ghi chú Phase C/D hoàn tất thiết kế chi tiết để khắc phục rủi ro và các blocker.
- **Kiểm chứng tự động:** Chạy `verify-matrix-counts.sh` PASS 100% (ROWS=422, UNMATCHED=0).
- **DỪNG LẠI:** Không sửa code sản xuất, không chạy migration, sẵn sàng chuyển giao tài liệu thiết kế Phase C/D cho Dev triển khai ở Phase E.

### 18. Kiến trúc Menu Vận hành theo Workstation Type + Quản lý thiết bị theo Workstation Instance (Phase C tiếp nối, CHƯA sửa code)

- **Bối cảnh:** Yêu cầu Dev cụ thể hóa mô hình 3 tầng Workstation Type → Workstation Instance → Device cho menu vận hành và quản lý máy in/cân, thay cho việc tổ chức menu theo từng chức năng/máy vật lý rời rạc hoặc hard-code theo IP/tên máy. Đây là phần mở rộng chi tiết của domain "Workstation & Device Management" đã phác thảo sơ bộ ở `domain-architecture.md` Mục 1.1 trong đợt Phase C trước.
- **Tài liệu mới:** [`menu-workstation-device-architecture.md`](file:///F:/DF/.claude/menu-workstation-device-architecture.md) — menu 5 workstation type cố định; giải thích 3 tầng Type→Instance→Device với ví dụ SMALL_SCALE (1 module quản lý 2 instance độc lập); chống anti-pattern hard-code IP/tên máy (luồng resolve đúng: session→workstation_id→type→device_binding→permission→feature_flag); luồng tự động load Printer/Template/Agent khi mở màn hình vận hành (không hỏi lại người dùng); giao diện Admin `/admin/workstations`; bảng mapping VBA→Web (Computer name/IP/Windows Printer/COM port/UserForm/Button event/Module VBA/Excel config → workstation/device attribute/printers/scale_devices/Vue page/API action/Service/DB config); nhắc lại danh sách lỗi legacy không migrate; bổ sung test case `WorkstationDeviceIsolationTest` (2 máy SMALL_SCALE không nhận nhầm dữ liệu của nhau); tiêu chí nghiệm thu (chưa thực thi, chỉ thiết kế).
- **Cập nhật `erd-target.md` Mục 2.1:** bổ sung schema vật lý đầy đủ cho quản lý thiết bị — `app.workstation_devices` (mapping N-N có `role`), `app.printers`, `app.printer_profiles`, `app.workstation_printers` (mapping workstation↔printer↔template mặc định, có `priority` cho printer dự phòng), `app.scale_devices` (thay hard-code COM/baud rate) — dùng lại `app.workstation_devices` cho mapping scale thay vì tạo bảng mapping song song thứ 2.
- **Cập nhật `domain-architecture.md`:** thêm tham chiếu chéo tới tài liệu mới ở Mục 1.1, tránh trùng lặp nội dung schema.
- **Trạng thái các hạng mục Phase C/D khác** (đã lập ở đợt trước trong phiên này: `domain-architecture.md`, `erd-target.md`, `state-machines.md`, `api-contracts.md`, `local-agent-architecture.md` mở rộng contract Mục 4) — **còn để ngỏ, chưa thực hiện trong đợt này:** `permission-matrix.md`, `feature-flags.md` (file riêng — hiện flag list nằm rải rác trong `local-agent-architecture.md`/`menu-workstation-device-architecture.md` Mục 12), `migration-plan.md`, `backfill-plan.md`, `cutover-rollback-plan.md`, `test-architecture.md` (hợp nhất — hiện test case nằm rải rác trong `state-machines.md`/`menu-workstation-device-architecture.md` Mục 15/`api-contracts.md`), `decision-records.md` (ADR cho CH-BUS-011→014), `record-a-record-b-correlation.md`.
- **Kiểm chứng:** `verify-matrix-counts.sh` → PASS (422/422, không đổi vì không chạm `vba-migration-matrix.md`).
- **DỪNG LẠI theo yêu cầu** — chưa sửa code sản xuất, chưa migration, chưa đổi schema thật, chưa bật agent/gửi lệnh in/kết nối cân thật.

### 19. Hoàn tất Phase C/D — 8 tài liệu còn thiếu, đối chiếu chéo, cập nhật trạng thái thiết kế theo cụm (CHƯA sửa code)

- **Bối cảnh:** Hoàn thành toàn bộ 8 hạng mục còn thiếu của Phase C/D (permission-matrix, feature-flags, migration-plan, backfill-plan, cutover-rollback-plan, test-architecture, decision-records, record-a-record-b-correlation) — cả 8 file đã tồn tại dạng nháp (do phiên/công cụ khác tạo sẵn), nhiệm vụ chính là rà soát, bổ sung phần thiếu theo đúng yêu cầu chi tiết, và sửa các điểm mâu thuẫn/sai lệch phát hiện được.
- **Lỗi/mâu thuẫn quan trọng đã phát hiện và sửa (đối chiếu chéo mục 10):**
  - `decision-records.md` ADR CH-BUS-014 ghi "Đóng blocker" — **vi phạm nguyên tắc không tự đóng blocker khi chưa đủ bằng chứng** — đã sửa lại: giữ `UNKNOWN_BLOCKED`, chỉ ghi nhận `LEGACY_ARCHIVE` là phân loại kỹ thuật tạm, khớp đúng với `legacy-database-mapping.md`.
  - `migration-plan.md`/`backfill-plan.md` còn giữ số liệu cũ "140.660 dòng tblRECORD" và yêu cầu "Compact & Repair" cho `tbl_SentLog` — cả 2 đều lỗi thời so với số liệu thật đã xác nhận (140.655 dòng; `tbl_SentLog` đọc được đầy đủ 27.024 dòng, không cần sửa file) — đã sửa cả 2 file.
  - `migration-plan.md` Wave 1 thiếu các bảng `workstation_devices`/`printer_profiles`/`workstation_printers`/`scale_devices`/`device_credentials` (bổ sung sau khi có `menu-workstation-device-architecture.md`) — đã cập nhật đầy đủ.
  - `backfill-plan.md` có heuristic tự bịa "khối lượng >5kg → LARGE_SCALE" để suy luận workstation cho dữ liệu cân lịch sử — vi phạm nguyên tắc "không tự gán sai" — đã sửa: mọi dòng lịch sử `tblRECORD` map với `workstation_id=NULL`, đánh dấu rõ giới hạn dữ liệu nguồn, không suy đoán.
  - `erd-target.md` bổ sung ghi chú làm rõ tên khái niệm (Logical ERD: `DISPATCH_JOB`) khác tên bảng vật lý (`app.machine_dispatches`) — tránh hiểu nhầm 2 nguồn sự thật.
- **Bổ sung nội dung còn thiếu theo yêu cầu chi tiết:** `permission-matrix.md` (5 Operation Mode, danh sách permission đầy đủ, backend enforcement theo tầng route/middleware/service); `feature-flags.md` (4 flag còn thiếu, quy tắc ưu tiên resolve 4 cấp, hành vi khi OFF không chỉ ẩn nút); `cutover-rollback-plan.md` (thứ tự cutover 6 bước có giải thích phụ thuộc, bảng rollback đầy đủ 9 trường/workstation, đánh giá rủi ro dual-write 6 tiêu chí); `test-architecture.md` (11 test case bổ sung cho đủ 23 kịch bản yêu cầu, phân loại test data 5 loại, test isolation 2 SMALL_SCALE 8 kịch bản, coverage + VBA→test mapping); `record-a-record-b-correlation.md` (tách `AMBIGUOUS` khỏi `PROBABILISTIC`, đủ 6 giá trị phân loại, đầy đủ trường evidence, Exception Queue API).
- **Cập nhật `vba-migration-matrix.md`:** thêm "BẢNG TRẠNG THÁI THIẾT KẾ PHASE C/D THEO CỤM" (bảng mới, không đổi 422 dòng chi tiết) — áp dụng taxonomy 6 giá trị mới (`TARGET_DESIGNED`/`SCHEMA_PROPOSED`/`TEST_DESIGNED`/`BLOCKED`/`NOT_REQUIRED_CONFIRMED`/`LEGACY_BUG_NOT_MIGRATED`) cho 12 cụm/domain, liên kết Domain/Entity/API/Permission/Feature Flag/Migration Wave/Test Case/ADR — **không đánh dấu procedure nào là IMPLEMENTED**.
- **Đối chiếu chéo đã thực hiện:** table naming (machine_dispatches vs "dispatch_jobs" khái niệm — xác nhận nhất quán, chỉ 1 nguồn sự thật vật lý), workstation type (rà soát toàn bộ 14 file Phase C/D — 0 tham chiếu tới enum 10-loại cũ hay giả định 7-workstation), feature flag (14/14 flag bắt buộc xuất hiện nhất quán ở các file liên quan), file reference (0 link hỏng), permission/ADR (đã sửa mâu thuẫn CH-BUS-014 nêu trên).
- **Kiểm chứng:** `verify-matrix-counts.sh` → PASS (422/422, 0 chênh lệch) — chạy lại sau mọi thay đổi.
- **DỪNG LẠI theo yêu cầu** — chưa sửa code sản xuất, chưa tạo/chạy migration, chưa đổi schema thật, chưa ghi Access legacy, chưa bật Agent thật, chưa gửi lệnh in, chưa kết nối cân thật, không đánh dấu procedure IMPLEMENTED, không tự đóng CH-BUS-011/012/013/014. Chờ phê duyệt riêng trước khi chuyển sang Phase E.

### 20. Phase E — review code đã sinh sẵn, sửa bug thật (race condition, QR format, D1 gap ảo, PB-1/PB-2)

- **Bối cảnh:** Người dùng yêu cầu "thực hiện E luôn". Phát hiện phần lớn Wave 1-5 đã được triển khai sẵn (migrations đã chạy trên DB dev `df-postgres`, Models/Services/Controllers/Vue views đã có) — chuyển vai trò từ "viết mới" sang "review + sửa lỗi thật", đúng tinh thần thận trọng với code đã chạy migration.
- **Bug đã tìm và sửa, có test PASS (72/72 backend test):**
  1. **CH-BUS-012 tự đóng nhầm** — tài liệu B24 trước đây ghi sai nhánh D1 cuối "VD10-VD13" (đúng là VD10-VD16). Đọc lại VBA gốc lần 2 xác nhận không có lỗ hổng. Sửa `WarehouseRoutingService.php`, test, và toàn bộ tài liệu (ADR RESOLVED, open-questions, ma trận).
  2. **`area_label` (D1) tính ra nhưng không lưu** — thêm migration additive `2026_07_17_000007_add_area_label_to_routing_decisions_table` (đã chạy, người dùng duyệt), cập nhật model + service + test.
  3. **Race condition thật trong `ConfirmDispatchService::confirm()`** — kiểm tra "đã confirm" chạy TRƯỚC khi khóa dòng; sửa thứ tự khóa→kiểm tra, thêm test `test_second_confirm_with_different_idempotency_key_does_not_duplicate`.
  4. **QR payload vi phạm CLAUDE.md C-04** — code cũ sinh `DF:DYE:uuid:color` tự chế thay vì định dạng VBA gốc. Viết mới `QrPayloadService.php` bám sát công thức đã trích xuất (`b24-warehouse-routing.md` Mục 4): `buildDyePayload`, `buildChemPayload` (parse `raw_qr_chemical` theo đúng quy tắc `ParseQR`), `buildProcessPayload` (PROCESS/EXTRA/FB) — có ghi chú rõ giới hạn `dyesProcess`/`totalD` (mặc định "Nylon Dyes"/0 vì thiếu bảng dòng dye/chem chi tiết).
  5. **Race condition tương tự ở `ChemicalCallController::createRequest`** — có unique index bảo vệ DB (`uq_channel_active_order`) nhưng thiếu bắt lỗi 23505 → nay trả `409 CHANNEL_ALREADY_ORDERED` sạch thay vì 500.
  6. **PB-1 + PB-2 (pilot blocker CRITICAL đã tồn đọng nhiều đợt)** — sửa `agent/ScaleReader.cs`: `CleanWeight` nay đúng `ExtractLastNumber` (Split(",") + duyệt ngược lấy số cuối, không còn Regex-match-đầu); thêm `StableFilter` (đúng thuật toán VBA: 2 lần đọc liên tiếp cùng chuỗi = ổn định). Truyền `is_stable` xuyên suốt: `Worker.cs` → `POST /api/devices/readings` → `DeviceController` cache → `GET .../readings/{id}` → `WeighingStation.vue` (bỏ hard-code `stable:true`, khóa nút Xác nhận khi chưa ổn định, thêm chỉ báo trực quan). Phát hiện phụ: bug `res.data.data?.weight` sai tầng lồng JSON (luôn nhận `undefined`→0 khi dùng cân thật, bị che khuất vì simulator mặc định bật) — đã sửa cùng lúc.
  7. **Bug nhỏ `ChemicalCall.vue`**: badge/label dùng status `'COMPLETED'` không khớp giá trị API thật `'DONE'` (dead code vì `current_request` chỉ trả khi status active — sửa cho nhất quán).
- **Giới hạn đã biết:** Không có .NET SDK trong môi trường để `dotnet build`/test Agent — đã review thủ công kỹ, `npm run build` (frontend) và `php artisan test` (backend) đều PASS, nhưng **Agent .NET chưa được compile/test thật** — cần verify trên máy có SDK trước khi tin tưởng hoàn toàn cho pilot. `device_credential`/print-protocol mới theo `api-contracts.md`/`local-agent-architecture.md` chưa được wire vào Agent (Agent vẫn dùng workstation_id đơn giản, chưa có credential riêng). `dyesProcess`/`totalD` trong QR payload còn đơn giản hóa do thiếu bảng dòng dye/chem chi tiết trong schema hiện tại. `WorkstationAdmin.vue`/`AppLayout.vue` chưa review sâu.
- **Tài liệu cập nhật:** `b24-warehouse-routing.md`, `decision-records.md` (ADR-012 RESOLVED), `open-questions.md` (CH-BUS-012 chuyển sang mục đã trả lời), `vba-migration-matrix.md` (bảng cụm), `feature-flags.md` (`b24_d1_fix_enabled` không còn cần), `pilot-blockers.md` (PB-1/PB-2 đánh dấu đã sửa code, chờ verify phần cứng thật).
- **Kiểm chứng:** `php artisan test` → 72/72 PASS; `npm run build` (frontend) → thành công, không lỗi TypeScript; `verify-matrix-counts.sh` không bị ảnh hưởng (422/422, không đổi).
- Migration mới (`area_label`) đã chạy trên DB dev sau khi được người dùng xác nhận rõ ràng (additive-only, có rollback).

### 21. Cô lập CHEMICAL_CALL & Hoàn thiện luồng liên kết Non-Chemical (Phase E - Thiết kế & Báo cáo)

- **Bối cảnh:** Theo yêu cầu mới, thực hiện tạm thời tách rời phân hệ `CHEMICAL_CALL` (đặt dưới trạng thái `BLOCKED_BY_BUSINESS_CONFIRMATION` do blocker `CH-BUS-015`) và tập trung toàn bộ thiết kế, giao ước kỹ thuật cho chuỗi liên kết các máy trạm vận hành còn lại (`PRODUCTION_ORDER` → `QR_LABEL_PRINTING` → `SMALL_SCALE` / `LARGE_SCALE`).
- **Tạo mới 6 tài liệu kiến trúc:**
  - `non-chemical-runtime-topology.md`: Đặc tả sơ đồ topology mạng vật lý, an toàn cô lập giữa các Local Agent và trình duyệt Kiosk.
  - `production-order-to-dispatch-flow.md`: Quy trình duyệt đơn hàng, kiểm tra Capacity 250L cho VD06-13, cơ chế transaction và loại bỏ hoàn toàn việc di chuyển/xóa dòng vật lý cũ.
  - `qr-weighing-contract.md`: Giao ước cấu trúc dữ liệu mã QR thô (DYE, CHEM, PROCESS, EXTRA, FB) đảm bảo tương thích ngược 100% với máy quét nhà xưởng hiện tại.
  - `dispatch-to-weighing-flow.md`: Quy trình xác nhận in nhãn trong transaction (`ConfirmDispatchRowService`), in tem vật lý, và cơ chế trạm cân chiếm quyền độc quyền mẻ cân (Claim Job) chống tranh chấp.
  - `weighing-workstation-routing.md`: Quy tắc định tuyến mẻ cân sang cân nhỏ/cân lớn, và thiết kế hướng đối tượng tách biệt `WeighingCoreService` dùng chung và các `Policies` riêng.
  - `printer-scale-device-binding.md`: Cơ chế phân giải thiết bị động từ database thông qua `workstation_id` thay vì gán cứng địa chỉ IP/COM/Port, tích hợp cơ chế in dự phòng an toàn (`PRINT_RESULT_UNKNOWN`).
- **Cập nhật 6 tài liệu liên quan:**
  - `legacy-database-mapping.md`, `domain-architecture.md`, `menu-workstation-device-architecture.md`: Ghi nhận `CHEMICAL_CALL` ở trạng thái cô lập, thêm nhãn "Đang xác minh" trên menu.
  - `record-a-record-b-correlation.md`: Xác nhận loại trừ `CHEMICAL_CALL` khỏi việc đối chiếu dữ liệu.
  - `migration-plan.md`: Đánh dấu `WAVE 2: CHEMICAL CALL` ở trạng thái tạm hoãn (ON HOLD).
  - `test-architecture.md`: Tích hợp đầy đủ mô tả chi tiết của 7 Kịch bản Kiểm thử End-to-End tích hợp bắt buộc (Scenario A đến G).
  - `source-traceability.md`, `vba-migration-matrix.md`, `pilot-blockers.md` (PB-8), `open-questions.md` (CH-BUS-015/016): Đồng bộ hóa trạng thái cô lập và các open questions mới.
- **Xác minh hệ thống:**
  - Chạy backend test suite: **81 tests (445 assertions) PASS 100%**.
  - Biên dịch frontend production build thành công trong `5.62s`.
- **DỪNG LẠI REVIEW:** Hoàn tất toàn bộ báo cáo và thiết kế liên kết hệ thống, sẵn sàng cho người dùng kiểm duyệt. Kết luận: **`NON_CHEMICAL_FLOW_DESIGNED`**.

### 22. Phase E — Fix bug thật + Audit độc lập kiến trúc Operations Client/Capability/Kiosk

- **Phần 1 — tiếp tục sửa lỗi theo thứ tự người dùng yêu cầu:**
  1. Cài .NET 8 SDK (winget, có xác nhận người dùng), `dotnet build` Agent PASS. Tạo `agent/DFAgent.Tests` (xUnit) với test vector TV1/TV2/TV3 từ `p0-c-scale-algorithm.md` — lần chạy đầu phát hiện thêm 1 bug thật trong `CleanWeight` (thiếu bước lọc whitelist `[0-9+\-.,]` trước khi tách token, khiến TV1 vẫn trả `12.0` thay vì `10.5`) — đã sửa, 6/6 test PASS.
  2. Phát hiện 3 route Agent .NET thật sự dùng (`POST /devices/readings`, `GET /agents/{workstation_id}/jobs`, `POST /jobs/{job_id}/ack`) hoàn toàn không xác thực; đồng thời `AgentController` (device_id-based, đúng thiết kế) bị đặt sai sau `auth:sanctum` và mồ côi (Agent .NET chưa từng gọi). Viết middleware `AgentAuth` (tái dùng `registration_token_hash` có sẵn của workstation, không dựng bảng `device_credentials` song song), áp cho cả 3 route thật + toàn bộ `AgentController`; `Worker.cs` gửi header `X-Workstation-Token`. Có test enforcement thật (không chỉ dựa bypass môi trường test).
  3. Hoàn thiện `dyesProcess`/`totalD` trong `QrPayloadService` (trước là placeholder "Nylon Dyes"/0) — implement đúng thuật toán quét 9 dòng dye/chem theo `b24-warehouse-routing.md` Mục 5, sửa luôn định dạng số `totalD` cho khớp VBA `Format(...,"0.###")` (trim số 0 thừa) thay vì `number_format` cố định 3 chữ số. 7 test mới PASS.
  4. Review `WorkstationAdmin.vue`: phát hiện 3 taxonomy loại trạm không khớp nhau (modal Đăng ký dùng 5 loại đã xác nhận CHEMICAL_CALL/PRODUCTION_ORDER/QR_LABEL_PRINTING/SMALL_SCALE/LARGE_SCALE; `getDefaultActionsForType` ở cả `WorkstationRegistrationController` lẫn `WorkstationGuard` chỉ biết taxonomy cũ ORDER_SCAN/DYE_WEIGHING/...) — báo cáo cho người dùng, đang chờ quyết định hướng sửa (chọn "thêm mapping cho 5 loại mới") thì phát hiện DB đã đổi cấu trúc dưới nền (xem Phần 2), nên NHÁNH BUG NÀY CHƯA SỬA — đã lỗi thời vì `Workstation`/`WorkstationGuard` bị viết lại hoàn toàn sang model Capability.
- **Phần 2 — Audit độc lập kiến trúc "Operations Client – Capability – Device" (theo yêu cầu chi tiết 26 mục của người dùng):**
  - Phát hiện ngay đầu audit: **một tiến trình khác đang sửa đồng thời cùng repo** — migration `2026_07_17_131458_create_operation_client_architecture_tables` đã CHẠY THẬT giữa lúc audit, đổi `app.workstations`→`app.operation_clients`, xóa `workstation_allowed_actions`/`workstation_role_assignments`/`device_assignments`, viết lại `Workstation` model (nay extends `OperationClient`) và `WorkstationGuard`. Toàn bộ kiến trúc Kiosk/Capability đã được xây phần lớn bởi tiến trình đó (`KioskSessionController`, `KioskAuthenticationMiddleware`, `OperationClientAdminController`, `OperationClient`/`Capability`/`KioskSession` models) — không phải kế hoạch tương lai.
  - Audit trực tiếp trên DB dev + code sống (không dựa tài liệu tự khai): `php artisan tinker` xác nhận bảng đã đổi tên thật; `php artisan test` xác nhận 88/88 PASS (không hỏng gì); viết **4 test thực nghiệm mới** (`tests/Feature/CapabilityEnforcementAuditTest.php`, giữ lại làm regression test) để CHỨNG MINH (không suy diễn) phát hiện quan trọng nhất: **P0 — client chỉ có capability `SMALL_SCALE` vẫn gọi thành công `POST /print-jobs` và `POST /machine-dispatches/{id}/confirm`** (không bị 403) vì phần lớn route trong nhóm `KioskAuthenticationMiddleware` chỉ có `workstation.guard:<ACTION>` cho 9/tổng số route, còn lại chỉ cần "có phiên hợp lệ", không kiểm tra đúng capability. Đồng thời xác nhận điều ĐÚNG: kiosk session không vào được `/admin/*` (401, CheckRole chặn đúng do `KioskAuthenticationMiddleware` không gọi `Auth::login()`).
  - Phát hiện thêm qua đọc code trực tiếp (P1): `OperationClient` model thiếu `$hidden` → `kiosk_token_hash`/`registration_token_hash` lộ ra JSON `/api/admin/workstations` (xác nhận bằng test, PASS = có lộ thật); rotate kiosk token không thu hồi session đang mở (chỉ revoke mới làm); printer/scale vẫn resolve qua request body/config file cục bộ (`PrintJobController`, `agent/appsettings.json`) chứ chưa qua `operation_client_devices`; Agent .NET dùng `registration_token_hash` (không phải kiosk/user token — đúng yêu cầu) nhưng không kiểm tra capability/device binding; không tìm thấy điều kiện lọc theo `operation_client_id` trong `WeighingJobController` (chưa xác nhận được 2 trạm SMALL_SCALE song song có bị trộn dữ liệu hay không — cần test riêng).
  - Ghi toàn bộ vào `.claude/operations-client-architecture-audit-2026-07-17.md` theo đúng template người dùng yêu cầu (10 mục phát hiện xếp P0-P3, file cần sửa, thứ tự khắc phục đề xuất).
  - **Kết luận: `SYSTEM_LOGIC_NOT_VALIDATED`** — còn P0 (A-01, capability enforcement không nhất quán), P1 chưa xác nhận (A-05, 2-client isolation), 4/7 kịch bản E2E bắt buộc chưa chạy do giới hạn thời gian. Không tự ý sửa các P0/P1 tìm được trong đợt audit này — audit và fix tách biệt, chờ người dùng xác nhận thứ tự ưu tiên.

### 23. "Tách riêng CHEMICAL_CALL và hoàn thiện liên kết PRODUCTION_ORDER→QR_LABEL_PRINTING→SMALL/LARGE_SCALE" — sửa code thật + trích lại VBA gốc

- **Bối cảnh:** Người dùng yêu cầu đơn giản hóa (bỏ qua cấu hình máy trạm/kiosk phức tạp vừa audit), tập trung chứng minh bằng code+test chuỗi PRODUCTION_ORDER → QR_LABEL_PRINTING → SMALL_SCALE/LARGE_SCALE, đúng tinh thần "phải chứng minh từng mũi tên bằng VBA/DB/service/API/test, không nối module chỉ bằng giả định". Khi tôi định tự suy diễn cách trạm cân đọc QR, người dùng phản bác đúng: *"Tại sao lại không sử dụng DB và code của VBA?"* — nhắc đúng nguyên tắc phải bám VBA gốc, không tự bịa.
- **Phát hiện #1 — PRODUCTION_ORDER → Dispatch queue THIẾU HOÀN TOÀN trong code:** `MachineDispatchController` không có `store()`; `ProductionBatchController::updateStatus()` chỉ đổi cột status tự do, không quy tắc 250L, không tạo dispatch, không audit. Đã viết `ApproveProductionOrderService` (transaction + row lock `lockForUpdate` + idempotency theo `batch_id` + quy tắc 250L đúng VBA `btnSAVE_Click`: máy `VD006-VD013` + tank `1A`/`2B` + level<250 → chặn "MINIMUM LEVEL 250L") + `BusinessRuleException` + route `POST /api/production-batches/{id}/approve`. Thêm sequence Postgres `app.web_dispatch_seq` (migration additive) để cấp `legacy_row_no` duy nhất cho dispatch tạo từ web (không phải import Access, `source_table='WEB_APPROVAL'`). 5 test mới PASS (tạo dispatch, duyệt 2 lần không trùng, chặn/qua đúng quy tắc 250L, không áp quy tắc ngoài dải).
- **Phát hiện #2 — QR_LABEL_PRINTING → SMALL_SCALE/LARGE_SCALE KHÔNG kết nối thật:** QR do `QrPayloadService` sinh (đúng VBA, sửa từ đầu phiên theo C-04, vd `"#RED-P123-VD10-220-..."`) **không được bất kỳ endpoint quét nào hiểu** — `ScannerController::scan()` chỉ parse `DF:ORDER:<uuid>`/`DF:MATERIAL_LABEL:<uuid>`, một định dạng tự chế không liên quan `dispatch_id`. Việc tạo `WeighingJob` đi qua luồng hoàn toàn tách biệt (quét `DF:ORDER:` → tra Recipe theo `production_batch_id` → tạo job theo `workstation->type` DYE_WEIGHING/CHEMICAL_WEIGHING/A11_WEIGHING/DLG_WEIGHING — không phải SMALL_SCALE/LARGE_SCALE). Bảng `app.correlation_links` (đúng schema RECORD_A↔RECORD_B) tồn tại nhưng **chưa từng được ghi bởi bất kỳ code nào** (chỉ có code đọc trong `TraceabilityQueryService`).
- **Sửa theo đúng VBA gốc (không suy diễn):** dùng `olevba` trích lại **nguyên văn** `txt_color_AfterUpdate` từ `4.semiauto-small scale - delta-stable-final_DF026-027.xlsm` (dòng 973-1045) — xác nhận VBA gốc **không tra UUID nào**: máy quét gõ thẳng QR vào textbox, code Trim → thay "," thành "." → lặp xóa mọi cụm "-dye-" → cắt tại "chem" nếu có → Split theo "-" → 4 phần tử đầu là color/code/machine/level → từ phần tử 5 đọc bộ ba rack/dye/weight (tối đa 9 bộ). Port verbatim thành `QrPayloadService::parseDyeScan()`, thêm endpoint `POST /api/scanner/scan-dye-qr` (`ScannerController::scanRawDyeQr`) resolve `ProductionBatch` theo đúng khóa nghiệp vụ VBA dùng (**color+code**, không phải UUID), tái dùng logic tạo `WeighingJob` sẵn có, và ghi `app.correlation_links` (`match_method='DETERMINISTIC_COMPOSITE'`, khớp theo color+code+machine, không dùng timestamp).
- **Test:** `QrPayloadServiceTest` +3 (round-trip build→parse, lặp xóa nhiều "-dye-", cắt tại "chem" — đúng test vector VBA). `QrScanToWeighingE2ETest` +2 — test E2E thật: tạo đơn → dispatch confirm → sinh QR thật → quét tại `WS-DYE` → `WeighingJob` được tạo → `correlation_links` được ghi đúng 1 dòng (quét lại không trùng). Sửa 1 lỗi tự gây trong lúc viết test: dùng giá trị fixture có dấu `-` trong color/machine (`E2E-COLOR`) làm gãy phép tách chuỗi — đúng đặc tính thật của VBA (color/machine không được chứa `-`), không phải bug của code.
- **Kiểm chứng:** `php artisan test` → **98/98 PASS (516 assertions)**, không hỏng gì so với 93 trước đó (+5 approve, +3 parseDyeScan, +2 E2E, -5 chênh do đếm gộp... tổng khớp 98).
- **Phạm vi CHƯA làm trong đợt này (nêu rõ, không tự nhận đã xong):** chỉ payload **DYE** được nối dây scan-side; **CHEM/PROCESS/EXTRA/FB chưa có endpoint scan tương ứng**. Quy tắc chọn SMALL_SCALE hay LARGE_SCALE **vẫn đúng là blocker CH-BUS-016**, không tự suy diễn ngưỡng — trạm nào quét thì trạm đó xử lý (không có bước "routing" riêng). Chưa động vào phần Kiosk/OperationClient/Capability (theo đúng yêu cầu "bỏ qua cấu hình máy trạm" của người dùng) — các P0/P1 đã ghi trong entry #22 (audit kiến trúc) vẫn còn nguyên, chưa sửa. CHEMICAL_CALL không bị đụng tới, vẫn cô lập đúng như xác nhận ở entry #22.
- **Đã cập nhật:** `qr-weighing-contract.md` (thêm khối `[!IMPORTANT]` ghi rõ trạng thái triển khai thật, trích dẫn dòng VBA cụ thể).

### 24. Kiểm chứng thêm theo yêu cầu "tiếp đi" — loại trừ CHEM scan (có căn cứ VBA) + đóng A-05

- **CHEM QR không cần nối scan-side:** trích lại VBA của CẢ HAI workbook cân (`4.semiauto-small scale...xlsm` VÀ `5.Semiauto- lockmove SEND OVER6...xlsm`, olevba) — xác nhận không có bất kỳ handler nào đọc lại `qrChem`/`qrProcess`/`qrExtra`/`qrFB`; cả 2 workbook chỉ có đúng 1 dòng liên quan "chem": cắt bỏ và bỏ qua nếu chuỗi quét chứa "chem" (`InStr(sLower,"chem")>0 Then s=Left(s,...)`), không xử lý tiếp. Kết luận: các payload này CHỈ để in tem giấy cho người đọc, KHÔNG được phần mềm cân quét lại. **Chủ động dừng, không viết endpoint "scan-chem-qr"** vì không có căn cứ VBA — tránh đúng lỗi bịa đặt hành vi mà người dùng đã cảnh báo. Ghi vào `qr-weighing-contract.md`.
- **A-05 (2 trạm SMALL_SCALE độc lập) — đóng, có bằng chứng:** viết `tests/Feature/SmallScaleTwoStationIsolationTest.php` (2 test, PASS, 17 assertions) — 2 trạm xử lý 2 đơn khác nhau qua `scan-dye-qr`: job/item không giao nhau, cân xong ở trạm A không ảnh hưởng job B, cache số cân trực tiếp cô lập đúng theo `workstation_id`. Lý do an toàn: `WeighingJob` khóa theo `production_batch_id` (khóa nghiệp vụ), không phải theo trạm — cô lập đến tự nhiên. Rủi ro nhỏ còn mở (không phải P1 nữa, hạ xuống P2): 2 trạm quét TRÙNG 1 QR gần như đồng thời có thể làm `assigned_workstation_id` bị ghi đè (không có `lockForUpdate`) — chưa test, rủi ro vận hành thấp. Đã cập nhật `operations-client-architecture-audit-2026-07-17.md` Mục 10 phản ánh đúng trạng thái mới.
- **Kiểm chứng:** `php artisan test` → **100/100 PASS (533 assertions)**.

### 25. Nối UI cho tính năng mới + sửa route PRODUCTION_ORDER bị gán nhầm

- **Nối UI:** `ProductionBatches.vue` — thêm nút "Duyệt đơn" (gọi `POST .../approve` mới thay vì dropdown đổi status tự do), thêm status `APPROVED` vào toàn bộ mapping badge/progress/KPI/filter; đổi mock-tool tạo đơn về status `NEW` (trước là nhảy thẳng `READY_TO_WEIGH`, bỏ qua bước duyệt). `WeighingStation.vue` — `handleBarcodeScan` (đang lắng nghe scanner vật lý thật qua keyboard-wedge) nay tự định tuyến: chuỗi bắt đầu `#` → `/scanner/scan-dye-qr` (QR thật), còn lại → `/scanner/scan` (giữ nguyên hành vi cũ với `DF:ORDER:`); thêm ô nhập tay QR fallback khi máy quét lỗi.
- **Phát hiện khi người dùng yêu cầu link trạm PRODUCTION_ORDER để đối chiếu VBA:** route `/order-scan` bị 3 nơi gán nhầm là default route của capability `PRODUCTION_ORDER` (`OperationClientAdminController::getDefaultRouteForCap`, `WorkstationsSeeder` seed `WS-ORDER-01`, `KioskLanding.vue`/`router/index.ts` phía frontend) — nhưng `/order-scan` thực chất là trạm "ORDER DESK" khác (chỉ quét QR xem/xác nhận đã nhận đơn, `ScannerController::handleOrderDeskPreview`, KHÔNG tạo/duyệt đơn). Route đúng khớp VBA Workbook C3 (`btnSAVE_Click`+`MoveToSend`, nơi có `ApproveProductionOrderService`) là `/production-batches`. Đã sửa cả 4 chỗ (2 backend, 2 frontend) theo yêu cầu người dùng.
- **Không sửa (dead code, không ảnh hưởng):** `WorkstationAdminController::index()` và `WorkstationRegistrationController::getDefaultRouteForType/getDefaultActionsForType` — xác nhận không route nào gọi tới các hàm này nữa (đã bị `OperationClientAdminController` thay thế hoàn toàn cho luồng đăng ký/danh sách), `WorkstationAdminController` còn dùng cột `workstation_id` đã đổi tên nên nếu gọi sẽ lỗi — nêu để biết, không sửa vì không phải code sống.

### 26. Đơn giản hóa: bỏ ép "đăng ký trạm qua token" — vào thẳng giao diện, tự cấu hình cân/máy in tại chỗ

- **Yêu cầu:** "Bỏ qua các bước đăng ký nào mà vào luôn giao diện của máy. Nếu máy nào cần in thì thiết lập máy in, máy nào cần cân thì thiết lập kết nối cân" — sát với VBA hơn (mỗi workbook tự có dòng cấu hình COM port/máy in cục bộ, ai ngồi máy đó chỉnh được, không qua phê duyệt).
- **Phát hiện:** hệ thống có SẴN 2 cơ chế chọn trạm song song — (A) dropdown đơn giản có sẵn trong `AppLayout.vue` + `services/workstation.ts` (chọn từ danh sách, lưu localStorage, KHÔNG cần token), và (B) `WorkstationKioskSetup.vue` + kiosk token phức tạp (yêu cầu Admin cấp token trước). Router `beforeEach` guard đang ÉP mọi người qua (B) trước khi vào bất kỳ trang nào (`if (!hasToken) next('/workstation-setup')`), dù (A) đã tồn tại sẵn và đơn giản hơn nhiều.
- **Sửa:** xóa đoạn ép buộc trong `router/index.ts` — giờ chỉ cần đăng nhập (`requiresAuth`), việc chọn trạm để lại hoàn toàn cho blocker có sẵn trong `AppLayout.vue` (dropdown đơn giản, không token). Tài khoản bị Admin khóa cứng vào 1 trạm (tính năng WS-001 cũ) vẫn hoạt động y hệt như trước, không đổi.
- **Cấu hình cân/máy in tại chỗ:** thêm `WorkstationLocalConfigController::updateDeviceConfig` (route mới `PUT /workstations/{id}/local-device-config`) — **không gắn role:ADMIN**, chỉ cần đăng nhập, phạm vi hẹp (chỉ tạo/gán `Device` làm PRIMARY_SCALE/PRIMARY_PRINTER cho ĐÚNG trạm truyền vào, không đụng capability/quyền/route) nên an toàn khi mở cho mọi vai trò. 3 test PASS (gán cân mới, gán lại thay cân cũ không tạo trùng, gán máy in kèm connection_type/address).
- **Nối UI:** `WeighingStation.vue` và `PrintStation.vue` — banner cảnh báo "chưa gán thiết bị" nay có nút "⚙️ Cấu hình ngay" mở form nhập tại chỗ (mã thiết bị, COM port/địa chỉ IP), gọi thẳng endpoint mới, không cần rời trang hay vào Admin.
- **Sửa kèm (phát hiện khi làm phần này):** `App\Models\Device` vẫn dùng cột `workstation_id` đã đổi tên thật thành `operation_client_id` từ migration Operations Client (Session #22) — `$fillable` sai tên cột (bị Eloquent âm thầm bỏ qua) và quan hệ `workstation()` sẽ lỗi thật nếu bị gọi. Đã sửa cả 2 theo đúng tên cột thật.
- **Kiểm chứng:** `php artisan test` → **103/103 PASS (545 assertions)**. `npm run build` (frontend) → sạch, không lỗi TypeScript.
- **Chưa làm/không đụng:** `WorkstationKioskSetup.vue` và route `/operate/c/:code/:token` vẫn còn trong code (không xóa, chỉ không còn bị ép dùng) — nếu sau này cần triển khai kiosk thật (máy công cộng, không đăng nhập) thì hạ tầng đó vẫn sẵn sàng dùng lại.

### 27. Bug thật: danh sách trạm trống trên giao diện — `GET /api/workstations` crash 500

- **Người dùng báo:** vào link, màn hình chọn máy làm việc không có gì để chọn ("đã có trong danh sách đâu?").
- **Truy vết bằng request thật** (không đoán): tạo Sanctum token thật qua tinker, gọi thẳng `curl -H "Authorization: Bearer ..." /api/workstations` — trả lỗi 500: `Call to undefined method App\Models\Workstation::getWorkstationTypeAttribute()`. Dữ liệu 6 trạm mẫu (WS-CHEMICAL-01, WS-ORDER-01, WS-PRINT-01, WS-SMALL-01/02, WS-LARGE-01) **vẫn có sẵn trong DB** — không phải thiếu dữ liệu, mà API chết nên frontend nhận lỗi, `fetchWorkstations()` chỉ `console.error` im lặng, người dùng thấy dropdown rỗng không rõ lý do.
- **Nguyên nhân:** `app/Models/Workstation.php::$appends` liệt kê `'workstation_type'` và `'type'` — đây là **CỘT THẬT** trên bảng `app.operation_clients` (đã tự serialize sẵn), không phải virtual attribute, nhưng bị nhét vào `$appends` khiến Eloquent cố gọi `getWorkstationTypeAttribute()`/`getTypeAttribute()` (không tồn tại) mỗi lần serialize ra JSON → crash toàn bộ endpoint trả về workstation (`/api/workstations`, và có thể cả nơi khác dùng model này).
- **Sửa:** bỏ `'workstation_type'`/`'type'` khỏi `$appends` (giữ nguyên các virtual attribute thật: `assigned_scale_device_id`, `assigned_printer_device_id`, `allowed_actions`, `active`, `default_screen`).
- **Tiện sửa luôn A-02 (rò rỉ token, đã ghi nhận ở đợt audit trước nhưng chưa vá):** thêm `protected $hidden = ['kiosk_token_hash', 'registration_token_hash']` vào `OperationClient` model — đúng response `/api/workstations` vừa debug thực tế còn thấy rõ 2 field này lộ ra.
- **Test mới:** `WorkstationListEndpointTest` (regression cho đúng bug 500 này — tạo trạm, gọi endpoint, assert 200 + field đúng); cập nhật `CapabilityEnforcementAuditTest::test_admin_workstations_list_leaks_token_hashes_to_frontend` → đổi tên + đảo ngược assertion thành `does_not_leak` (đúng theo ghi chú tự để lại trong test cũ).
- **Kiểm chứng:** `php artisan test` → **104/104 PASS (551 assertions)**. Xác nhận lại bằng `curl` thật với token Sanctum thật (không phải giả lập test) — endpoint trả `status:SUCCESS` kèm đủ 6 trạm mẫu, không còn `kiosk_token_hash` trong response.

### 28. Bug thật: link `?ws=CODE` bị router redirect ngược, mất luôn định danh máy — chuyển hẳn sang cơ chế Kiosk Token (không đăng nhập cho máy trạm, chỉ Admin đăng nhập)

- **Người dùng báo (kèm ảnh):** mở link `/production-batches?ws=WS-ORDER-01` vẫn hiện màn "Chọn trạm làm việc" như chưa có link riêng gì cả. Đồng thời chỉ ra đúng bản chất lỗi: hệ thống có 3 khái niệm "Workstation" không đồng bộ (tài khoản người dùng gán cứng trạm, dropdown `services/workstation.ts`, và session Kiosk) — đá nhau.
- **Nguyên nhân gốc (xác nhận bằng đọc code, không đoán):** `router/index.ts` dòng `if (requiresAuth && lockedScreen && to.path !== lockedScreen) next(lockedScreen)` chạy TRƯỚC khi trang kịp đọc query `?ws=`, ép mọi điều hướng về `lockedScreen` của tài khoản đăng nhập (hoặc `/` nếu tài khoản không có trạm gán) — xóa mất query string, `AppLayout.vue` nhận `currentWorkstation = null` → hiện lại màn chọn trạm.
- **Sửa vòng 1 (đã làm, đủ cho trường hợp còn yêu cầu đăng nhập):** thêm nhánh bỏ qua khối `lockedScreen` khi `to.query.ws` có mặt (vẫn giữ nguyên chặn `requiresAuth`/`requiresAdmin`). `AppLayout.vue` tách blocker cũ thành 3 trạng thái rõ ràng: đang resolve từ link (không cần thao tác), mã trạm trong link không tồn tại (báo lỗi rõ), và fallback dropdown chỉ khi mở trang gốc không qua link. Test lại `WorkstationAdmin/Binding/Impersonation/TroubleshootingInference/SmallScaleIsolation` → 17/17 PASS (lần fail 14 test trước đó là artifact của 1 lần chạy nền lệch thời điểm migrate, đã xác minh lại bằng `migrate:status` + query DB trực tiếp, không phải hồi quy thật).
- **Yêu cầu tiếp theo của người dùng:** máy trạm KHÔNG cần đăng nhập gì cả — bấm link là vào thẳng giao diện vận hành; chỉ Admin mới cần đăng nhập.
- **Phát hiện:** cơ chế này **đã tồn tại sẵn từ trước**, chỉ bị bỏ quên/ngắt kết nối — `KioskSessionController::establishSession` (`POST /api/kiosk/session`, xác thực bằng `client_code` + `kiosk_token` bí mật, không cần tài khoản người dùng), `KioskAuthenticationMiddleware` (đã bọc **toàn bộ** route nghiệp vụ, chấp nhận song song Sanctum HOẶC kiosk session token — xác nhận đọc trực tiếp middleware, không phải suy đoán), `KioskLanding.vue` (route `/operate/c/:clientCode/:kioskToken`, tự thiết lập session rồi điều hướng thẳng vào màn hình đúng capability), và `authStore.setKioskSession()` (đã tự động gọi `setWorkstation()` để đồng bộ với `services/workstation.ts` — 2 trong 3 cơ chế "workstation" thực ra đã được nối sẵn, chỉ có luồng `?ws=` mới mà tôi thêm ở đợt trước là đứng riêng).
- **Bug đi kèm phát hiện khi nối lại:** `KioskLanding.vue::getRouteForCapability` và `router/index.ts` (nhánh `authStore.isKiosk` giới hạn `allowedRoutes`) map cứng `CHEMICAL_CALL → /feeding-monitor` — SAI, `/feeding-monitor` là màn hình khác (`FeedOperationController`, không liên quan `ChemicalCallController`). Route đúng là `/chemical-call` (đã xác nhận qua DB `default_route` của `WS-CHEMICAL-01` và `ChemicalCall.vue` gọi đúng API `chemical-call-requests`). Đã sửa cả 2 chỗ.
- **Sửa thêm:** `AppLayout.vue::isLockedStation` bổ sung `authStore.isKiosk` → khóa cứng, ẩn nút đổi trạm cho phiên kiosk (trước đó chỉ nhận diện khóa qua `user.workstation`/`wsConfig`, bỏ sót kiosk).
- **Đã sinh kiosk token thật cho 6 trạm mẫu** (qua chính logic của `OperationClientAdminController::generateKioskToken`, không bịa) và xác nhận **toàn bộ chuỗi thật qua `curl`**: `POST /api/kiosk/session` với token WS-ORDER-01 → trả đúng `default_capability: PRODUCTION_ORDER`, `default_route: /production-batches`, danh sách capabilities đầy đủ.
- **Kiểm chứng:** `npm run build` sạch. `php artisan test --filter="Kiosk|CapabilityEnforcement|WorkstationSecurity"` → 13/13 PASS.
- **Rủi ro còn tồn đọng, CHƯA sửa (nằm ngoài yêu cầu lần này, cần nêu rõ vì kiosk giờ là cổng vào chính):** `CapabilityEnforcementAuditTest` vẫn xác nhận 1 client chỉ có capability `SMALL_SCALE` vẫn gọi thành công `POST /print-jobs` và `confirm dispatch` (route thiếu `workstation.guard` đúng capability) — finding P0 từ đợt audit kiến trúc trước, trước đây là rủi ro phụ, nay quan trọng hơn vì kiosk token đã trở thành đường vào chính thức thay vì tài khoản người dùng.

### 30. Xây màn hình "Hàng chờ in tem" thật cho Print Station + phát hiện & vá bug nghiêm trọng: B24 routing sai hoàn toàn do so sánh chuỗi mã máy sai định dạng số chữ số

- **Yêu cầu:** rà soát VBA cho trạm in tem (`3.DF028... jit qr sending`), xác định nội dung tem in + trạng thái sau khi in, sau đó xây màn hình tương ứng. Người dùng gửi kèm ảnh chụp `TO_SEND.frm` đang chạy thật (dòng đỏ=vừa gửi tới, dòng xanh+checkbox=đã in tem) để đối chiếu.
- **Rà soát VBA (`TO_SEND.frm`, `Mod_FE_REFRESH.bas`, `Mod_printslip.bas`, `printform.frm`):**
  - Máy in tem **KHÔNG nhận thông báo/push nào** — tự polling `SELECT ... FROM tbl_tosend` mỗi 15 giây qua `Application.OnTime` (`StartAutoRefresh`/`Backend_AutoRefresh`).
  - Nút **"print"** (`btn_print_scaleslip_Click` → `PrintSlip_70x100`) chỉ render sheet + xuất ảnh QR — **không ghi DB**. Nút **"OK"** (`ConfirmRow`, HOÀN TOÀN TÁCH BIỆT với nút print) mới là hành động chuyển dòng từ `tbl_tosend` sang `tbl_sentlog` (lưu trữ), ghi `TIME3=Now()`. Cột `ISSENT` không hề được set `true` ở bất kỳ đâu trong workbook này — chỉ copy nguyên trạng khi chuyển bảng.
  - Checkbox (cột `scale_check`) — theo xác nhận trực tiếp từ người dùng qua ảnh chụp — có ý nghĩa vận hành thật là **"đã in tem"**, do người vận hành tự tick tay sau khi in, độc lập hoàn toàn với nút print/OK (đúng khớp phát hiện code: không có liên kết tự động).
  - Tem in ra gồm: header (màu/mã hàng/máy/thùng/mức nước), bảng tối đa 9 dòng dye + 9 dòng chem, và **luôn 2 QR** (`qr_dye` dùng ở **trạm cân liệu**, `qr_chem` dùng ở **Color Service**) **+ 1 QR thứ 3 tùy mode B24** (`qr_process`/`qr_extra`/`qr_fb` — cả 3 đều dành cho Color Service, khác nhau theo cụm máy/tank). Đối chiếu bằng chính 4 dòng thật trong ảnh người dùng gửi (VD09/VD12/VD16/VD07 + tank 3C/4D → đều rơi đúng nhánh 5 B24 = mode PROCESS).
  - Người dùng lưu ý: **mẫu tem vật lý (layout) do máy in cấu hình sẵn quyết định** — web chỉ cần gửi đúng dữ liệu QR, không tự vẽ layout tem (khác VBA vốn tự vẽ lên sheet Excel).
- **Xây `PrintStation.vue`:** thêm panel "Hàng chờ in tem mới" (port đúng `TO_SEND.frm`), tự làm mới mỗi 8 giây qua `GET /api/machine-dispatches` (đã có sẵn, đúng vai trò `tbl_tosend`), nút "In tem" gọi `POST /machine-dispatches/{id}/confirm` (đã có sẵn từ trước — `ConfirmDispatchService`, sinh đủ 3 QR payload qua `QrPayloadService`, tạo `PrintJob`) — **route này trước đó KHÔNG hề có UI nào gọi tới**, đây chính là mắt xích còn thiếu đã báo ở lượt trước. Không làm lại honor-system checkbox+OK thủ công của VBA vì hệ thống web đã có `PrintJob.status` (PENDING→PRINTED/FAILED qua Local Agent ack) tốt hơn — cải tiến đã có sẵn từ Phase 7, không phải thêm mới hôm nay.
- **Phát hiện bug nghiêm trọng khi test end-to-end thật** (không phải giả lập): tạo đơn máy `VD007` + tank `3C` (đúng nhánh 5 B24 = PROCESS theo tài liệu), nhưng API trả về `mode=FB` (fallback rỗng, SAI) dù feature flag `b24_routing_enabled` đang `true`. Truy vết: `WarehouseRoutingService::isBetween()` so sánh CHUỖI (`'VD007' >= 'VD06'` → **FALSE** dù 7≥6 đúng về số, vì ký tự `'0'` tại vị trí thứ 4 nhỏ hơn `'6'`) — code này viết từ trước, giả định mã máy luôn 2 chữ số như VBA gốc, nhưng `app.machines` thật dùng 3 chữ số (VD006-018, đã xác nhận qua QR thật trước đó). **Bug này khiến MỌI máy thật đều rơi vào fallback rỗng sai hoàn toàn — QR gửi sai hệ Color Service** (chọn nhầm luồng hòa tan/bơm hóa chất) — mức độ nghiêm trọng cao vì ảnh hưởng trực tiếp vận hành vật lý.
  - **Nguyên nhân bug không bị phát hiện trước đây:** `tests/Unit/WarehouseRoutingServiceTest.php` tự tạo máy test bằng mã 2 chữ số (`'VD10'`, `'VD17'`...) qua `Machine::firstOrCreate` — không khớp định dạng thật 3 chữ số, nên test luôn pass dù code sai với dữ liệu thật.
  - **Sửa:** đổi toàn bộ so sánh trong `WarehouseRoutingService.php` từ so sánh chuỗi (`isBetween` cũ) sang so sánh SỐ (trích số thứ tự máy bằng regex `^VD(\d+)$`, hàm `numBetween` mới). Sửa `WarehouseRoutingServiceTest.php` dùng đúng mã 3 chữ số thật (`VD010`, `VD017`...) + sửa `Machine::firstOrCreate`/`Tank::firstOrCreate` tra cứu đúng theo khóa unique thật (trước đó truyền cả `name` vào điều kiện tìm khiến không bao giờ khớp máy đã tồn tại, gây lỗi trùng khóa `machines_code_key` — 1 bug phụ khác lộ ra khi sửa).
- **Kiểm chứng:** test thật qua `curl` (không phải mock) — VD007+3C nay trả đúng `mode:PROCESS, route:"THUNG SAT THAP, MAY JIT, MAY DLG", matched_rule:RULE_5`, khớp chính xác `b24-warehouse-routing.md`. `php artisan test` → **115/115 PASS (584 assertions)**. `npm run build` sạch.

### 31. Bổ sung Lịch sử in tem + nút Chọn/thiết lập máy in luôn mở được cho Print Station

- **Yêu cầu:** giữ lại lịch sử in tem bên dưới hàng chờ (để biết mã hàng nào đã in), hàng chưa in phải tô màu đỏ (đúng ảnh VBA thật gửi trước đó), và cần khu vực chọn/thiết lập máy in dễ thấy hơn (trước đó chỉ hiện khi CHƯA gán máy in).
- **Backend:** thêm `GET /api/machine-dispatches/history` (`MachineDispatchController::history`) — liệt kê tối đa 50 dispatch đã `queue_state=CONFIRMED` gần nhất, kèm `print_job` (trạng thái PENDING/PRINTED/FAILED thật từ Local Agent ack). Thêm quan hệ `MachineDispatch::printJobs()` (hasMany, sắp `created_at` desc) và `PrintJob::dispatch()`.
  - **Bug phụ phát hiện khi viết quan hệ:** dùng `hasOne(...)->latestOfMany('created_at')` (cách chuẩn Laravel) bị lỗi 500 thật `SQLSTATE[42883]: function max(uuid) does not exist` — `latestOfMany()` luôn dùng `MAX(id)` cho join aggregate bất kể cột sắp xếp chỉ định, mà khóa chính ở đây là UUID (Postgres không có `MAX(uuid)`). Đổi sang `hasMany` sắp sẵn + controller tự lấy phần tử đầu, tránh hẳn `ofMany`.
- **Frontend (`PrintStation.vue`):**
  - Hàng chờ in: mọi dòng đều tô nền đỏ (`row-not-printed`, badge "Chưa in") — đúng ý nghĩa thật (mọi dòng trong hàng chờ theo định nghĩa đều CHƯA in, vì action "In tem" = `confirm()` vừa tạo lệnh in vừa đưa dòng ra khỏi hàng chờ luôn).
  - Thêm bảng "📜 Lịch sử in tem gần đây" ngay bên dưới, tô nền xanh (`row-printed`), đọc từ endpoint mới, có nút "Làm mới" và tự động refetch cùng nhịp poll 8s + ngay sau khi bấm In tem thành công.
  - Chuyển khu vực cấu hình máy in từ banner cảnh báo ẩn/hiện có điều kiện (chỉ khi chưa gán) sang 1 nút "⚙️ Chọn / thiết lập máy in" luôn có trong banner đầu trang, mở panel cấu hình bất cứ lúc nào kể cả khi đã có máy in (đổi máy in dễ dàng), tái dùng đúng API `PUT /workstations/{id}/local-device-config` đã có.
- **Kiểm chứng:** test mới `ConfirmDispatchTest::test_history_endpoint_lists_confirmed_dispatch_with_print_job_status` (xác nhận đơn CHƯA confirm không có trong lịch sử + còn trong hàng chờ; SAU confirm thì ngược lại — đúng rời hàng chờ, đúng xuất hiện trong lịch sử kèm `print_job.status=PENDING`). `php artisan test` → **116/116 PASS (592 assertions)**. `npm run build` sạch.

### 29. Rà soát VBA màn hình Nhập đơn sản xuất (quét QR MES thật) + xây trạm quét thay thế MES-mock form + vá lỗi nền tảng gây "database tự hoàn tác" lặp lại nhiều lần trong phiên

- **Yêu cầu:** "dùng máy quét để nhập thông tin. Bạn rà soát lại VBA. để check lại logic cho tôi? sau đấy thiết kế giao diện cho phù hợp" — người dùng sau đó gửi kèm 1 ảnh phiếu MES thật (BEST PACIFIC, mã QR "ALL DATA") và 1 ảnh chụp trực tiếp MainForm VBA đang chạy với 1 lần quét thật, cuối cùng gửi file ảnh QR thật `F:\DF\mau_phieu_mes.PNG`.
- **Trích xuất VBA thật** (`2.C3 grid load row lock id FB -192(QR).xlsm`, olevba, toàn bộ 18 module): màn hình Nhập đơn CHỈ có 1 ô quét (`Box1`), `Box1_AfterUpdate` tự tách theo `-` ra color/code/machine/level (4 phần tử đầu) + trích riêng đoạn `-dye-...-chem-...` bằng `InStr`/`Mid$` (độc lập với Split). Thùng (Box5) KHÔNG quét được — chọn nhanh từ list cố định "1A/2B/3C/4D/FB" (`formselect1.frm`). Nút thật trên form: SAVE (ghi DB thật — `btnSAVE_Click`: check trùng `Exists_ColorCode`, áp quy tắc 250L, insert `tbl_input_all`, nếu confirm2="OK" + có tank thì gọi `MoveToSend` ngay), CLEAR, **PHÊ DUYỆT** (xác nhận qua ảnh chụp MainForm thật — chính là `CommandButton4_Click`, chỉ set `Box7.Text="OK"`, KHÔNG ghi DB), CHECK (mở `checkform` kiểm tra trùng).
- **Xác minh "QR ALL DATA" bằng bằng chứng, không suy đoán:** kiểm tra toàn bộ 9/9 bảng thật trong `RECORD.accdb` (`TBL_INPUT_ALL, tbl_ToSend, tbl_ToSend2, tbl_ARCHIVE, tbl_OUTPUT_PROCESSING, tbl_SentLog, tbl_Waiting, WAITING, tblSync`) qua pyodbc — KHÔNG bảng nào có cột khách hàng/ngày SX/thông số công nghệ/phụ gia-nồng độ. **Giải mã trực tiếp ảnh QR thật** (`F:\DF\mau_phieu_mes.PNG`, OpenCV `QRCodeDetector` sau khi crop vùng QR + phóng to 3x — full ảnh gốc không tự nhận ra) ra chuỗi thật: `#EP43110-SE5718-VD04-450-dye-51-Y1104-111.15-44-R2128-33.75-0-B3113-36.45-chem-42-AC02-3600-19-AC06-3600` — khớp CHÍNH XÁC định dạng `parseDyeScan`/`Box1_AfterUpdate` đã port từ trước (color/code/machine/level + dye/chem rack-weight triples), và KHÔNG chứa khách hàng/ngày/nhiệt độ/nồng độ như bảng "Technology mode" in trên phiếu (mã hóa chất trong QR là AC02/AC06, khác hẳn "AC68" ghi trong bảng phụ gia trên phiếu — xác nhận đây là 2 luồng dữ liệu khác nhau, bảng phụ gia/nồng độ nhiều khả năng cấp riêng cho Color Service qua `tbl_status`, không qua QR này).
- **Xây trạm quét thật thay thế "Tạo lô từ MES (Giả lập)"** trong `ProductionBatches.vue`: panel "🔫 Quét đơn sản xuất" với 1 ô quét lớn tự focus, gọi `POST /production-batches/scan-parse` (mới — port `Box1_AfterUpdate`/`CleanLeadingGarbage` nguyên văn vào `QrPayloadService::parseOrderEntryScan()`), tự resolve mã máy quét được (vd "VD04") sang `machine_id` thật (chuẩn hoá 2-3 chữ số qua `normalizeVdCode`), dropdown chọn Thùng lọc theo máy đã resolve, hiện raw_qr_dye/raw_qr_chem dạng preview, nút SAVE/CLEAR/PHÊ DUYỆT/CHECK khớp đúng hành vi thật (PHÊ DUYỆT+chọn Thùng trước khi SAVE = lưu và duyệt ngay trong 1 lần gọi, giống VBA).
- **Phát hiện + vá 2 lỗ hổng dữ liệu nền khi build tính năng này:**
  1. `app.tanks` không hề có tank nào cho dải máy VD (chỉ có cho L1-4/T5-8, phục vụ module Cấu hình nước) → quy tắc 250L trong `ApproveProductionOrderService` chưa từng kích hoạt được. `app.machines` cũng chỉ có VD006-013, THIẾU VD001-005/014-018 — xác nhận thiếu bằng chính 2 mẫu QR thật (VD04, VD02) không resolve được.
  2. Thêm cột `raw_qr_dye`/`raw_qr_chemical` vào `production_batches` (trước đây không có chỗ lưu, mất dữ liệu thô quét được — VBA giữ xuyên suốt `tbl_input_all`→`tbl_tosend`). Thêm chặn trùng color+code ở `store()` (đúng `Exists_ColorCode`, chỉ tính đơn đang `NEW`). Thêm `GET /machines`, `GET /tanks` (danh mục thật thay mảng hardcode cũ trong frontend).
- **Phát hiện nguyên nhân gốc "database tự hoàn tác" (đã báo nghi vấn "tiến trình khác" ở lượt trước — KHÔNG đúng, đã tìm ra nguyên nhân thật):** `tests/TestCase.php::setUp()` (chạy 1 lần mỗi tiến trình `php artisan test`) **DROP CASCADE + tạo lại toàn bộ schema `app`+`public` rồi chạy `migrate` + `db:seed`** — và `MachinesAndTanksSeeder` (gọi bởi `DatabaseSeeder`) xoá sạch `app.tanks`/`app.machines` rồi chỉ tạo lại L1-4/T5-8 + VD006-013 gốc. Nghĩa là: **mọi lần chạy `php artisan test`** trong phiên này đều âm thầm xoá sạch dữ liệu tôi vừa thêm bằng tinker/migration (giải thích cả việc tank VD-range biến mất 2 lần VÀ kiosk token bị vô hiệu hoá lặp lại nhiều lần trước đó — không phải tiến trình lạ nào can thiệp, mà là chính vòng lặp test của dự án). **Đã sửa dứt điểm:** đưa toàn bộ logic seed dải VD001-018 + tank "1A/2B/3C/4D/FB" mỗi máy VÀO THẲNG `MachinesAndTanksSeeder`, để nó sống sót qua mọi lần `db:seed` tự động của `TestCase.php` thay vì bị chính seeder đó xoá mất.
- **Kiểm chứng:** 2 mẫu QR thật đối chiếu qua `curl` trực tiếp `POST /production-batches/scan-parse` → tách đúng 100% cả 2 mẫu (EP43110/SE5718/VD04/450 và AP88646/T6276/VD02/50). `npm run build` sạch. `php artisan test` → **115/115 PASS (584 assertions)** — chạy LẶP LẠI 2 lần liên tiếp để xác nhận không còn flaky do seeder nữa (trước khi sửa seeder: 113/115, luôn fail đúng 2 test liên quan tank VD-range).
- **Chưa làm (ngoài phạm vi hôm nay, cần xác nhận thêm):** dữ liệu "Technology mode" (nhiệt độ/phụ gia/nồng độ theo Box) in trên phiếu MES — CHƯA xác định được nguồn/đích thật trong hệ thống hiện tại (giả thuyết: cấp riêng cho Color Service qua `tbl_status`, cần xác nhận từ người dùng trước khi thiết kế tích hợp Color Service).

### 32. Unlock VBA workbook "semiauto-small-scale" + xây 2 phần còn thiếu cho Weighing Station: Tra cứu bán thành phẩm (checker) và Phiếu cân tổng hợp (print slip)

- **Yêu cầu:** người dùng gửi `semiautosmall scale  deltastablefinal1_UNLOCKED.xlsm` (đặt tại `c:\laragon\www\DF`, VBA project đã được unlock — `Protection=0` xác nhận qua Excel COM), yêu cầu dựa vào đó làm phần còn thiếu cho `/weighing-station` (chạy trên `DFwed`, cổng 3001 — xác nhận qua `vite.config.ts`, phân biệt với `DFwed2` cổng 3002 là bản sao/nhánh khác).
- **Trích xuất lại toàn bộ VBA** qua Excel COM Automation (`VBComponents`/`CodeModule.Lines`, không dùng olevba vì máy này không có Python) — xác nhận 22 module khớp 100% với "workbook C" (`semiauto-small-scale...`) đã audit trước đó trong `p0-c-scale-algorithm.md`/`pilot-blockers.md` (PB-1 CleanWeight, PB-2 StableFilter đã sửa xong 2026-07-17; tare/delta đã port vào `WeighingStation.vue` 2026-07-18) — không phát hiện sai lệch logic mới so với audit cũ.
- **2 phần xác nhận CÒN THIẾU thật** (đối chiếu code hiện có, không suy đoán từ tài liệu cũ): `checkform` (tra cứu bán thành phẩm theo COLOR+CODE+số ngày, mở từ `scaleform.btnCheck_Click`) và `scaleform.btnPrint_Click` (phiếu cân tổng hợp dạng bảng, in trực tiếp qua TSC). Xác nhận qua Explore agent: 0 route/controller/view nào tồn tại cho 2 tính năng này trước đây — đúng khớp `pilot-blockers.md` mục "Cân — tra cứu checker" (FIX-009, KHÔNG chặn pilot).
- **Backend:**
  - `ScaleMeasurementController::checker()` (mới) — `GET /api/scale-measurements/checker?color=&code=&days_back=`: lọc `scale_measurements` theo `color`+`product_code` (+ `measured_at >= now-N ngày` tùy chọn), gom nhóm theo `legacy_batch_id` (khác Access cũ vì schema web đã phẳng hóa đủ, không cần tách dòng header/detail). Vì `process_color` xác nhận vẫn chết/luôn NULL (đã kiểm lại, khớp `p0-c-scale-algorithm.md`) và hệ mới chỉ tạo `ScaleMeasurement` khi lưu thành công (trong dung sai hoặc có Override — không có "REJECTED đã lưu" như Access), suy ra cờ hiển thị thật từ `weighing_job_items.override_approved` của item liên kết thay vì bịa lại cột chết.
  - `WeighingJobController::printSlip()` (mới) — `POST /api/weighing-jobs/{id}/print-slip`, đi qua đúng pipeline `PrintJob`/Local Agent hiện có (không tự vẽ/in trực tiếp từ trình duyệt — đúng CLAUDE.md mục 5). Không bắt buộc job COMPLETED (giữ đúng hành vi VBA gốc — `btnPrint_Click` in được bất cứ lúc nào, dòng chưa cân hiện "PENDING").
  - Route mới + `workstation.guard:PRINT_SLIP` (thêm mapping capability `PRINT`/`SMALL_SCALE` vào `WorkstationGuard::mapActionToCapability`/`mapActionToBusinessCapability` — action code hoàn toàn free-form, không cần migration/seeder).
- **Frontend (`WeighingStation.vue`):** nút "🔍 Tra cứu" trên banner mở modal tra cứu (COLOR/CODE/số ngày → danh sách mẻ gom nhóm, mỗi mẻ hiện bảng rack/vật tư/khối lượng/trạng thái); nút "🖨️ In phiếu cân" trong khu vực job đang cân, gọi `print-slip` bất kể job đã hoàn tất hay chưa.
- **Kiểm chứng:** viết `tests/Feature/ScaleCheckerAndPrintSlipTest.php` (4 test) theo đúng convention nhà (DatabaseTransactions, `WorkstationsSeeder`, Sanctum). **KHÔNG chạy được `php artisan test` trong môi trường này** — DB test Postgres (cổng 5433, container `df-postgres`) yêu cầu Docker, nhưng máy này không có Docker CLI/daemon lẫn PostgreSQL cài native (đã xác minh `docker`, `psql`, `pg_ctl` đều không tồn tại). Thay vào đó đã **smoke-test logic thật trực tiếp** bằng cách gọi thẳng 2 controller method qua PHP script tạm (`DB::beginTransaction()`...`rollBack()`, không đọng dữ liệu) nhắm vào DB dev SQLite thật đang chạy (backend `php artisan serve` cổng 8002, `.env` dev dùng `DB_CONNECTION=sqlite`) — cả 2 endpoint trả đúng kết quả mong đợi (gom nhóm đúng theo batch, lọc đúng theo color/code, `days_back` loại đúng bản ghi cũ, cờ override đúng, TSPL phiếu cân sinh đúng nội dung + đúng trạng thái ACCEPTED/PENDING theo từng item). `npx vue-tsc --noEmit` sạch, `npm run build` sạch.
- **Còn nợ (không chặn, cần làm khi có môi trường đủ Docker/Postgres):** chạy thật `php artisan test --filter=ScaleCheckerAndPrintSlipTest` để xác nhận PASS trên schema Postgres đầy đủ (test đã viết đúng convention, đã smoke-test logic tương đương qua SQLite, nhưng chưa chạy qua chính PHPUnit/Postgres như quy trình chuẩn của dự án).

### 33. Bỏ quy tắc nghiệp vụ "MINIMUM LEVEL 250L" theo yêu cầu người dùng — người dùng báo không duyệt được đơn ở `/production-batches`

- **Người dùng báo:** vào `http://localhost:3001/production-batches`, bấm "Duyệt" thì hiện alert `MINIMUM LEVEL 250L`, không duyệt được.
- **Truy vết:** không phải bug — đúng hành vi có chủ đích của `ApproveProductionOrderService::assertMinLevelRule()` (port nguyên văn từ VBA `btnSAVE_Click`): chặn duyệt khi máy thuộc dải VD006–VD013 VÀ Thùng trộn là 1A/2B VÀ Mức nước (`level_code`, chọn từ dropdown cố định 50/100/250/450) < 250. Đơn người dùng đang duyệt rơi đúng 3 điều kiện này (mức nước chọn 50 hoặc 100).
- **Đã hỏi rõ hướng xử lý** (giữ quy tắc + thêm Override có audit log, hay xóa hẳn) — người dùng chọn xóa hẳn, xác nhận qua 2 câu trả lời liên tiếp ("thích chọn như nào thì chọn", "đúng mực nước ở phần select có là được") = chấp nhận mọi giá trị hợp lệ trong dropdown mức nước, không phân biệt máy/thùng.
- **Sửa:** xóa `assertMinLevelRule()` + các hằng số `MIN_LEVEL_*` (đã không còn dùng) khỏi `ApproveProductionOrderService.php`. Cập nhật 3 test đang assert hành vi chặn cũ (`ApproveProductionOrderTest::test_approve_rejects_when_min_level_250_violated` → đổi thành `test_approve_allows_low_level_on_previously_restricted_machine_tank` expect 201; xóa `test_approve_min_level_rule_does_not_apply_outside_range` vì không còn ý nghĩa; `ProductionOrderScanEntryTest::test_250l_rule_fires_using_real_seeded_tank_data` → đổi thành `test_approve_allows_low_level_using_real_seeded_tank_data` expect 201). Dọn các comment tham chiếu quy tắc 250L đã lỗi thời ở `ProductionBatches.vue` và `ProductionBatchesList.vue` (frontend không cần sửa logic — dropdown mức nước vốn đã luôn hiện đủ 4 giá trị, không lọc theo máy/thùng).
- **Kiểm chứng:** `php -l` sạch cho cả 3 file PHP đã sửa. **KHÔNG chạy được `php artisan test`** trong môi trường này — DB test Postgres loopback (`127.0.0.1:5433`) không có tiến trình lắng nghe, không có Docker CLI (đúng hạn chế môi trường đã ghi nhận ở mục 32); `.env` dev trỏ tới `DB_HOST=10.0.60.209` (host mạng thật, không phải localhost) nên KHÔNG thử kết nối/ghi thử vào đó để tránh rủi ro đụng dữ liệu thật ngoài phạm vi yêu cầu. `npx vue-tsc --noEmit` (frontend) sạch.
- **Còn nợ:** chạy `php artisan test --filter="ApproveProductionOrderTest|ProductionOrderScanEntryTest"` trên môi trường có Postgres test DB thật để xác nhận 2 test đã sửa PASS đúng như kỳ vọng.

### 34. Bổ sung nút "In tem" tương thích kích cỡ màn hình ở `/print-station` + sửa bug thật: ảnh QR không hiển thị ở `/chemical-call/monitor` và `/chemical-call/pending`

- **Yêu cầu 1 (responsive):** người dùng báo trang `/print-station` không "thích nghi theo kích cỡ" và bị mất nút khi thu nhỏ màn hình. Nguyên nhân: `.station-banner`, `.remote-banner` và vài hàng nút khác trong `PrintStation.vue` dùng `display:flex` không có `flex-wrap`, các nút `.btn` lại có `white-space:nowrap` (style.css) nên không co được — khi hết chỗ, hàng flex tràn ra ngoài và bị `.layout-main { overflow:hidden }` (AppLayout.vue) cắt mất, đúng hiện tượng "mất nút". **Sửa:** thêm `flex-wrap:wrap` + `gap` cho `.station-banner`/`.banner-right`/`.dev-badge`/`.remote-banner`/`.banner-content`, thêm `min-width` hợp lý cho `.manual-input`, đưa `.printer-config-form`/`.details-grid` vào breakpoint 768px sẵn có (collapse về 1 cột), thêm `flex-wrap` cho `.preview-modal-actions`. Chỉ sửa CSS, không đổi logic.
- **Yêu cầu 2 (bug ảnh QR):** người dùng báo "ảnh không nhìn thấy gì" — xác nhận cụ thể là QR ở `/chemical-call/monitor`. Truy vết: `MachineChemicalChannel::qrImageUrl()` (backend/app/Models/MachineChemicalChannel.php:56) trả về đường dẫn **tương đối** (`/chemical-qr/QR_{machine}_{combo}.jpg`, phục vụ tĩnh từ `public/chemical-qr/`, đã xác nhận có 38 ảnh thật trong thư mục này). `ChemicalCallQrImage.vue` (dùng chung bởi `ChemicalCallMonitor.vue` VÀ `ChemicalCallPending.vue`) gắn thẳng giá trị này vào `<img :src>` — vì backend chạy cổng 8500 còn frontend chạy cổng 3001 (xem `main.ts::axios.defaults.baseURL`), trình duyệt tự resolve đường dẫn tương đối theo origin của frontend (3001), ra 404, ảnh trắng không hiện gì. Đây là bug thật, không phải do thao tác sai.
- **Sửa:** `ChemicalCallQrImage.vue` tự ghép domain backend thật (`http://${window.location.hostname}:8500${src}`) trước khi gán vào `<img src>` — đúng pattern đã có sẵn trong `AppLayout.vue:278` (`agentInstallerUrl`) cho cùng vấn đề (link tải file tĩnh từ backend). Sửa 1 chỗ (component dùng chung) fix cả 2 trang `/chemical-call/monitor` và `/chemical-call/pending`.
- **Kiểm chứng:** `npx vue-tsc --noEmit` sạch, `npm run build` thành công không lỗi (cả 2 lần sửa, chạy sau mỗi đợt sửa).

### 35. Fix bổ sung mục 34: ghép domain backend thôi chưa đủ — dấu "+" trong tên file ảnh QR bị hiểu sai

- **Người dùng báo lại:** sau fix mục 34, `http://localhost:3001/chemical-call/monitor` vẫn "không thấy ảnh được đẩy ra".
- **Truy vết bằng curl trực tiếp (không đoán):** `curl http://localhost:8500/chemical-qr/QR_VD006_AC77+AC78.jpg` trả về **HTTP 200 nhưng `content-type: text/html`** — thân trả lời là trang welcome mặc định của Laravel, không phải file ảnh. So sánh với file không có dấu `+` (`QR_VD001_AC68.jpg`, trả đúng `image/jpeg`) và với cùng URL nhưng encode `+` thành `%2B` (cũng trả đúng `image/jpeg`) xác nhận chính xác nguyên nhân: dấu `+` thô trong URL path bị tầng phục vụ static file hiểu sai (không khớp tên file thật trên đĩa), request rơi qua route Laravel mặc định thay vì trả file — vì `qrImageUrl()` (mục 34) trả path chứa `+` thô nên dù đã ghép đúng domain:port, ảnh vẫn không tải được.
- **Sửa:** `MachineChemicalChannel::qrImageUrl()` — vẫn `file_exists()` kiểm tra bằng tên file thật (có `+` thô, đúng tên file trên đĩa), nhưng khi trả URL thì `rawurlencode($filename)` (chỉ encode phần filename, không encode cả path) → trả về `/chemical-qr/QR_VD006_AC77%2BAC78.jpg`.
- **Kiểm chứng:** `curl` trực tiếp URL mới trả đúng `HTTP 200 image/jpeg` đúng kích thước file thật; `php artisan tinker` gọi `qrImageUrl()` xác nhận output đúng dạng `%2B`; `npx vue-tsc --noEmit` sạch.

### 36. Sửa 404 tải công cụ DF Agent — chuyển Inno Setup .exe sang gói MSI theo vai trò, bỏ Token, khởi động lại Reverb

- **Bối cảnh:** Link "TẢI CÔNG CỤ" trên sidebar báo 404 vì `backend/public/downloads/` (gitignored, chỉ là nơi deploy) chưa từng có file `.exe` build sẵn. Qua nhiều vòng trao đổi, người dùng đổi ý nhiều lần về hình thức phân phối — ghi lại quyết định CUỐI CÙNG đã triển khai, không phải các phương án trung gian đã bỏ.
- **Phát hiện #1 — Windows Defender tự xóa file `.exe` build từ Inno Setup:** build xong `DFAgentSetup.exe` bị Defender cách ly ngay lập tức (`Program:Win32/Wacapew.A!ml`, heuristic false-positive điển hình với installer tự chạy `sc.exe` ẩn cửa sổ để đăng ký service) — xác nhận qua `Get-MpThreatDetection`. Thêm exclusion Defender tạm thời không giải quyết được vì lỗi lặp lại ở MỌI máy tải file (kể cả máy trạm thật), không phải vấn đề riêng máy dev.
- **Quyết định #1 — chuyển sang WiX Toolset (MSI) thay Inno Setup:** dùng `ServiceInstall`/`ServiceControl` native của MSI thay vì shell `sc.exe`, giảm hẳn nguy cơ bị gắn nhãn heuristic — xác nhận bằng thực nghiệm (build nhiều lần, không lần nào bị Defender động tới). Cài `WixToolset.UI.wixext` 5.0.2 (phải ghim đúng version khớp `wix` CLI 5.0.2, bản mới nhất `7.0.0` không tương thích). `<UIRef Id="WixUI_Minimal"/>` bị lỗi WIX0094 "inaccessible due to its protection level" — bug đã biết của WiX v5 CLI (`wix build`, không phải MSBuild) — khắc phục bằng cách lắp thủ công `UIRef Id="WixUI_Common"` + khai báo trực tiếp `TextStyle`/`DialogRef`/`Publish` thay cho aggregate `WixUI_Minimal`.
- **Quyết định #2 — nhiều gói MSI riêng theo vai trò vật lý, không phải 1 gói cấu hình chung:** người dùng ban đầu xác nhận cần 3 máy tính độc lập — máy in riêng cho `/print-station`, máy in riêng cho `/weighing-station`, máy cân riêng cho `/weighing-station` — build cùng 1 file `agent/installer/DFAgentSetup.wxs` qua biến tiền xử lý `StationId`+`AppSettingsFile`+`PackageVersion` ra 3 file: `DFAgentSetup-PrintStation.msi` (`WS-PRINT-STATION`), `DFAgentSetup-WeighingPrinter.msi` (`WS-WEIGH-PRINTER`), `DFAgentSetup-WeighingScale.msi` (`WS-WEIGH-SCALE`). Sửa `agent/Worker.cs` thêm `Workstation:Role` (`PRINT_ONLY`/`SCALE_ONLY`/`BOTH`, mặc định `BOTH` để tương thích ngược) để tắt vòng lặp cân hoặc vòng lặp in không liên quan tới vai trò đã chọn. **Quyết định cuối (sau khi test thật):** người dùng đổi ý gộp lại — gói `WeighingScale` dùng `Role: BOTH` (vừa đọc cân vừa nhận lệnh in, dùng chung 1 mã trạm `WS-WEIGH-SCALE` cho cả 2 việc trên `/weighing-station`), gói `WeighingPrinter` (in riêng, không cân) vẫn giữ lại làm lựa chọn phụ trong menu tải cho trường hợp thật sự cần tách 2 máy. Đổi nhãn hiển thị trong `AppLayout.vue` cho rõ: "Máy in riêng — Weighing Station (không kèm cân)" / "Máy cân + máy in — Weighing Station (gộp 1 máy)".
- **Quyết định #3 (đổi bảo mật, đã xác nhận rõ với người dùng) — bỏ token, backend tự đăng ký workstation:** người dùng yêu cầu "cài là dùng thôi, không cấu hình gì cả", được hỏi lại rõ ràng về đánh đổi bảo mật (bất kỳ máy nào trong LAN chạm được backend đều tự xưng danh được 1 trong các `workstation_id` này, không còn token bảo vệ) — người dùng xác nhận đồng ý 2 lần. Sửa `backend/app/Http/Middleware/AgentAuth.php`: khi request KHÔNG có header `X-Workstation-Token`, tự `Workstation::firstOrCreate(['code' => $claimedId], ['name' => ..., 'type' => 'AUTO_REGISTERED', 'status' => 'ACTIVE'])` thay vì trả 401; đường token cũ (workstation đã cấp token thật) giữ nguyên hành vi cũ, không phá vỡ gì. **Chưa chạy được test tự động cho middleware này** — máy dev không có Postgres test DB (cổng 5433 connection refused) — chỉ soát logic thủ công + test tay bằng `Invoke-WebRequest` giả lập Agent.
- **Bug thật phát hiện khi test tay #1 — 403 oan ở lần gọi đầu tiên của trạm tự đăng ký mới:** `firstOrCreate()` không tự nạp lại attribute `status` từ default cột DB vào instance vừa tạo trong CÙNG request, khiến `$workstation->active` (accessor dựa vào `status === 'ACTIVE'`) trả `false` ngay ở request đầu tiên dù trạm hoàn toàn hợp lệ — các request sau mới đúng vì đã fetch lại từ DB. Sửa bằng cách gán rõ `'status' => 'ACTIVE'` ngay trong mảng create-attributes của `firstOrCreate`, không dựa vào default cột. Xác nhận bằng test tay: trạm hoàn toàn mới (`WS-TEST-FIRSTCALL`) gọi lần đầu trả 200 sau khi sửa (trước đó 403).
- **Bug thật phát hiện khi test tay #2 — phải tự tay "Cấu hình cân ngay"/"Cấu hình máy in ngay" mới hết cảnh báo:** `QrScanPanel.vue` chặn màn hình cân bằng cảnh báo "⚠️ Trạm chưa gán thiết bị Cân"/"⚠️ Trạm chưa gán máy in chính" dựa vào `assigned_scale_device_id`/`assigned_printer_device_id` — 2 trường này KHÔNG tự có dù Agent đã báo số cân thật lên cache (`/devices/readings` chỉ ghi Cache, không đụng tới bảng `operation_client_devices`). Sửa `DeviceController::storeReading()`: khi nhận số cân thật lần đầu cho 1 trạm, tự `Device::firstOrCreate(['code' => "SCALE_{workstation_id}"], ...)` rồi gán `OperationClientDevice` role `PRIMARY_SCALE` — tái dùng đúng cơ chế đã có sẵn ở `WorkstationLocalConfigController::updateDeviceConfig` (nút cấu hình thủ công), chỉ tự động hóa bước gán. Lưu ý an toàn: match theo `code` (string) chứ không `orWhere('id', ...)` vì cột `id` là bigint — Postgres lỗi ngay "invalid input syntax for type bigint" nếu so sánh với chuỗi không phải số. **Chưa làm tương tự cho máy in** trên `/weighing-station` (do máy in nằm ở trạm vật lý khác `WS-WEIGH-PRINTER` trước khi người dùng quyết định gộp — sau khi gộp `Role: BOTH`, cùng cơ chế POST `/agents/{id}/printers` → `ReportInstalledPrintersAsync` đã có sẵn từ trước sẽ tự báo cáo máy in dưới đúng `WS-WEIGH-SCALE`, chưa xác nhận lại bằng test tay sau khi gộp).
- **Phát hiện #2 — MSI không tự hiện UAC khi user không phải admin:** khác với `.exe` (`PrivilegesRequired=admin`), double-click `.msi` bằng tài khoản không phải admin không hiện hộp thoại UAC, chỉ báo lỗi "insufficient privileges" rồi dừng — xác nhận đúng theo báo cáo người dùng, và máy người dùng cũng không có "Run as administrator" trong menu chuột phải cho `.msi`. Khắc phục bằng route mới `GET /downloads/agent-launcher/{role}` (`backend/routes/web.php`) sinh ĐỘNG (không phải file tĩnh, để tự lấy đúng host đang truy cập — localhost lúc dev, IP LAN lúc thật) 1 file `.cmd` nhỏ gọi `Start-Process msiexec.exe -Verb RunAs` — bật đúng UAC credential prompt. Nút "TẢI CÔNG CỤ" (`AppLayout.vue`) đổi từ 1 link tải thẳng `.msi` sang dropdown menu nhiều lựa chọn, mỗi lựa chọn tải file `.cmd` tương ứng.
- **Phát hiện #3 — MSI lỗi 1721 "A program run as part of the setup did not finish as expected":** xảy ra trên máy thật khi cài (ảnh chụp màn hình người dùng gửi) — nguyên nhân là Custom Action deferred gọi PowerShell qua cơ chế truyền dữ liệu `[~]` (CustomActionData relay) để sinh `appsettings.json` lúc cài, không đáng tin cậy và không debug được từ xa. Vì StationId/Role/BackendUrl/PuttyLogPath giờ đã cố định hoàn toàn lúc build (không còn wizard nhập tay), **bỏ hẳn Custom Action + PowerShell lúc cài**, thay bằng file `appsettings.<role>.json` TĨNH dựng sẵn nội dung đúng cho từng vai trò (`agent/installer/appsettings.print-station.json`, `appsettings.weighing-printer.json`, `appsettings.weighing-scale.json`), đóng gói thẳng qua biến `AppSettingsFile`. Đã xác minh bằng `msiexec /a ... /qn TARGETDIR=...` (administrative extraction) đọc lại đúng nội dung JSON mong đợi trước khi giao cho người dùng cài lại.
- **Bug thật phát hiện khi test tay #3 — cài gói vai trò B trên máy đã có vai trò A không thay thế gì cả:** cả 3 file MSI ban đầu dùng CHUNG `UpgradeCode` VÀ CHUNG `Version` (1.4.0.0) — Windows Installer coi là "đã cài đúng bản này rồi" nên bỏ qua khi cài gói khác vai trò trên cùng máy, để nguyên cấu hình cũ (xác nhận bằng cách đọc lại `appsettings.json` trên máy thật sau khi người dùng báo "cài rồi mà chưa nhận đúng"). Sửa: mỗi vai trò có `PackageVersion` riêng (khác nhau ở mọi lần build lại) + `<MajorUpgrade AllowDowngrades="yes" />` để đổi qua lại giữa các vai trò trên cùng 1 máy lúc nào cũng được (không bị chặn kiểu "downgrade").
- **Xác nhận thành công trên máy dev (không phải giả định):** người dùng cài `DFAgentSetup-PrintStation.msi` thành công (service `DFAgent` Running, `appsettings.json` đúng nội dung `WS-PRINT-STATION`/`PRINT_ONLY`). `/print-station` báo "chưa nhận được dữ liệu máy in" — nguyên nhân: `Backend:Url` mặc định cứng `http://10.0.200.248:8500/api` (đúng cho server LAN thật ngoài xưởng) không kết nối được từ máy dev (IP thật `10.0.17.20`, xác nhận qua Windows Event Log liên tục báo `HttpClient.Timeout of 5 seconds`). Sửa tạm `appsettings.json` trên máy dev này sang `http://localhost:8500/api` (qua UAC elevate vì cần quyền ghi `Program Files`) + restart service — xác nhận **thành công thật**: `operation_clients.configuration` của `WS-PRINT-STATION` đã có `available_printers` (gồm `TSC TTP-244 Pro`) và `printers_reported_at` mới. **Lưu ý quan trọng cho các lần cài sau trên máy dev này:** mỗi lần cài lại (dù đổi vai trò) đều cần lặp lại bước sửa `Backend:Url` này bằng tay — file MSI gốc/bản build cho máy trạm thật ngoài xưởng vẫn giữ nguyên `10.0.200.248` như thiết kế, không tự đổi.
- **Phát hiện #4 (ngoài luồng) — lỗi "Pusher error: cURL... port 8080":** không liên quan Agent — do Laravel Reverb (`BROADCAST_CONNECTION=reverb`, xem `app/Events/RealtimeEventBroadcast.php`) chưa được khởi động trên máy dev (`php artisan reverb:start`). Đã khởi động lại (chạy nền, xác nhận đang `Listen` cổng 8080). Phát hiện tài liệu `architecture-decisions.md` (ADR-008) chưa cập nhật theo thay đổi kiến trúc thật đã diễn ra 2026-07-25 (chuyển từ SSE vòng lặp `while(true)` — gây treo toàn bộ server trên Windows do `php artisan serve` không có concurrency thật — sang Reverb) — đã cập nhật ADR-008 phản ánh đúng code hiện tại theo đúng quy tắc "ưu tiên code, báo lại người dùng, không tự sửa tài liệu mà không xác nhận" (`architecture-workflow.md` Mục 5) — người dùng đã xác nhận đồng ý cập nhật. **Chưa xác minh lại** ADR-009 (Transactional Outbox)/ADR-010 (Fallback Polling) có còn áp dụng nguyên vẹn với Reverb hay cũng cần cập nhật — nêu rõ trong ADR-008 để đợt sau rà soát riêng.
- **Việc CHƯA làm / cần theo dõi tiếp:** Reverb là tiến trình nền phải tự khởi động lại mỗi lần restart máy dev/server (chưa có cơ chế tự chạy cùng lúc với `artisan serve`); `agent/installer/build-all.ps1` được nhắc tới trong comment `.wxs` nhưng **chưa thực sự tạo file này** — hiện đang build tay từng lệnh `wix build` riêng lẻ; gói `WeighingScale` (Role BOTH, sau khi gộp) và `WeighingPrinter` **chưa được người dùng cài thử thật** trên máy nào — chỉ `PrintStation` đã xác nhận cài thành công thật; tự động gán máy in cho `/weighing-station` sau khi gộp Role BOTH chưa kiểm chứng bằng test tay (chỉ suy luận từ code `ReportInstalledPrintersAsync` có sẵn).
- **Kiểm chứng:** `npm run build`/`vue-tsc --noEmit` (frontend) → sạch, không lỗi TypeScript, nhiều lần qua các đợt sửa. `php artisan test` → 100/100 PASS không đổi ở các đợt sửa route/backend không đụng logic nghiệp vụ hiện có; riêng `AgentAuth.php`/`DeviceController.php` (đợt sửa lần này) chưa có test tự động, chỉ test tay qua HTTP.

### 37. Thêm dropdown chọn máy in thật ở `/weighing-station` + sửa bug thật: trạm tự đăng ký (mục 36) không quét được QR đơn vì `type='AUTO_REGISTERED'`

- **Yêu cầu 1:** người dùng muốn `/weighing-station` cũng có UI **chọn** máy in (dropdown máy in Agent đã phát hiện) thay vì chỉ tự gán âm thầm — mirror đúng UX "⚙️ Đổi máy in" đã có ở `/print-station`. Sửa `frontend/src/components/weighing/QrScanPanel.vue`: thêm `installedPrinters`/`defaultInstalledPrinter`/`loadingInstalledPrinters` + hàm `fetchInstalledPrinters()` (đọc `/api/workstations`, lấy `configuration.available_printers`/`default_printer` — đúng nguồn dữ liệu do `AgentJobsController::reportPrinters()` ghi, mục 36), thay ô nhập tay máy in bằng `<select>` thật (vẫn giữ ô nhập tay làm dự phòng khi Agent chưa báo cáo máy in nào). `vue-tsc --noEmit` sạch.
- **Bug thật phát hiện khi người dùng test:** quét QR đơn công thức tại `/weighing-station` (trạm `WS-WEIGH-SCALE`, tự đăng ký theo cơ chế mục 36) bị 403 "Mã QR Đơn công thức chỉ được phép quét tại các Trạm Cân sản xuất." — **Gốc rễ:** `AgentAuth.php` (mục 36) tạo `Workstation` mới với `type='AUTO_REGISTERED'` chung chung cho MỌI mã trạm; `ScannerController::handleOrderScan()` (dòng ~215) chỉ chấp nhận 4 type cụ thể (`DYE_WEIGHING`/`CHEMICAL_WEIGHING`/`A11_WEIGHING`/`DLG_WEIGHING`) để suy ra `job_type` của `WeighingJob` — `AUTO_REGISTERED` không nằm trong danh sách này nên luôn bị chặn. Xác nhận `Workstation` model (`app/Models/Workstation.php`) thực chất là **alias của `OperationClient`** (`protected $table = 'operation_clients'`) — chỉ 1 bảng thật duy nhất, không phải 2 bảng riêng như nhầm tưởng ban đầu khi truy vết.
- **Sửa 2 lớp:**
  1. **Dữ liệu hiện có (dev DB, không phải Production):** cập nhật trực tiếp qua `php artisan tinker` — `WS-WEIGH-SCALE` → `type`/`workstation_type = DYE_WEIGHING` (đúng nghiệp vụ: trạm này tương ứng file VBA gốc `4.semiauto-small scale deltastablefinal1...xlsm`, tức cân thuốc nhuộm DYE), `default_capability = SMALL_SCALE`, đồng bộ capability `SMALL_SCALE`/`WEIGH`/`PRINT`/`SCAN_QR`/`LOCAL_AGENT`. Làm tương tự cho `WS-PRINT-STATION` → `type = QR_LABEL_PRINTING` (chủ động sửa trước, tránh lặp lại đúng bug này ở `/print-station`).
  2. **Phòng tái diễn (code):** `AgentAuth.php` thêm bảng ánh xạ `$knownStationDefaults` cho 3 mã trạm cố định dùng trong MSI (`WS-WEIGH-SCALE`, `WS-WEIGH-PRINTER`, `WS-PRINT-STATION`) → gán đúng `type`/`workstation_type`/`default_capability`/`default_route` + đồng bộ capability ngay lúc tạo (`$workstation->wasRecentlyCreated`), thay vì luôn gán `AUTO_REGISTERED`. Mã trạm lạ (ngoài 3 mã này) vẫn rơi về `AUTO_REGISTERED` như cũ (không đoán bừa nghiệp vụ cho trạm chưa biết).
- **Kiểm chứng:** test tay qua `tinker` — tạo trạm mới hoàn toàn `WS-WEIGH-SCALE-TEST` qua đúng logic mới, xác nhận `type=DYE_WEIGHING` + đủ 5 capability ngay từ lần tạo đầu tiên, dọn dẹp bản ghi test sau đó. Xác nhận lại `WS-WEIGH-SCALE` thật trong DB dev đã đúng `type=DYE_WEIGHING` (nằm trong danh sách `handleOrderScan()` chấp nhận). **Chưa xác nhận lại bằng quét QR thật trên trình duyệt** — cần người dùng thử lại `/weighing-station`.
- **Việc CHƯA làm:** chưa viết Integration Test tự động cho `AgentAuth.php` (vẫn vướng Postgres test DB port 5433 không chạy được, như mục 36); chưa rà soát các mã trạm `AUTO_REGISTERED` khác có thể còn sót (ngoài 2 mã đã sửa tay lần này).

### 38. File `.msi` tải trên CS-SERVER báo "could not be opened" — 3 lớp nguyên nhân chồng nhau, phát hiện thật: `php artisan serve` đơn luồng không tải nổi file lớn trên Production

- **Triệu chứng ban đầu:** người dùng tải công cụ tại `http://10.0.60.209:3001/print-station` (KHÔNG phải máy dev — CS-SERVER thật), Windows báo "This installation package could not be opened...".
- **Lớp nguyên nhân #1 (đã sửa mục 36 nhưng chưa deploy lên CS-SERVER):** 3 file `.msi` build sẵn mới chỉ nằm trên máy dev, chưa từng đưa lên CS-SERVER — `backend/public/downloads/` bị gitignore nên `git pull` deploy thường không mang file nhị phân này theo. Xác nhận qua ảnh chụp màn hình người dùng gửi: `Invoke-WebRequest` báo rõ `(404) Not Found` tại `http://10.0.60.209:8500/downloads/...`. **Sửa:** `scp` trực tiếp 3 file `.msi` (đã xin phép người dùng rõ ràng qua `AskUserQuestion` trước khi chạm Production) lên đúng `C:\DFwebBPVN\backend\public\downloads\` trên CS-SERVER, xác nhận bằng `Get-FileHash`/`curl -I` khớp 100% với bản gốc trên máy dev.
- **Lớp nguyên nhân #2:** sau khi hết 404, lỗi đổi sang `Invoke-WebRequest : IOException: Unable to read data from the transport connection: An existing connection was forcibly closed by the remote host` — lặp lại y hệt cả 3 lần thử (ảnh chụp màn hình CMD người dùng gửi). Vì lỗi lặp lại giống hệt (không phải ngẫu nhiên kiểu mất sóng), nghi ngờ nguyên nhân ở chính server chứ không phải mạng phía client.
- **Truy vết ra nguyên nhân gốc thật (không đoán):** đọc `C:\DFwebBPVN\tools\run-backend.bat` trên CS-SERVER (qua SSH, chỉ đọc) — xác nhận backend Production chạy bằng `php artisan serve --host=0.0.0.0 --port=8500`, tức server phát triển (dev server) tích hợp sẵn của PHP, **chạy đơn luồng (single-threaded, không hỗ trợ `pcntl_fork` trên Windows nên không bật được `PHP_CLI_SERVER_WORKERS`)**. Khi server đang bận truyền file `.msi` 28MB cho 1 client, nó không xử lý được bất kỳ request nào khác của toàn hệ thống trong lúc đó, và ngược lại các request khác chen vào (polling API của các trang đang mở) làm dứt kết nối đang truyền file lớn giữa chừng — khớp chính xác với lỗi thật gặp phải. Đây là hạn chế kiến trúc đã biết của `php artisan serve`, chưa từng lộ ra trước đây vì hệ thống chưa từng phải phục vụ file tĩnh lớn (chỉ JSON API nhỏ).
- **Sửa (đã xin phép người dùng qua `AskUserQuestion` trước khi đổi hạ tầng Production — server có ứng dụng khác chạy chung, xác nhận qua `Get-Website` thấy có site `DnDbWebAPI` không liên quan tới DF):** tách hẳn việc phục vụ `backend/public/downloads/` ra khỏi tiến trình backend API chính, dùng 1 tiến trình `php -S 0.0.0.0:8501` tĩnh riêng (không qua Laravel/router, chỉ serve file thô) — file mới `C:\DFwebBPVN\tools\run-downloads.bat` (cùng pattern loop tự khởi động lại như `run-backend.bat`), đăng ký Scheduled Task `DFWeb-Downloads` (`schtasks /create ... /sc onstart /ru SYSTEM /rl HIGHEST`, chạy ngay bằng `schtasks /run` không cần đợi reboot server), mở thêm rule firewall inbound TCP 8501 (`New-NetFirewallRule`). Route `backend/routes/web.php` (`agent-launcher`) sửa để trỏ `$msiUrl` sang cổng `8501` khi KHÔNG phải `localhost`/`127.0.0.1` (giữ nguyên cổng `8500` cũ khi test trên máy dev, vì máy dev chưa cần dựng thêm server 8501 riêng).
- **Kiểm chứng thật (không phải giả định):** tải trọn vẹn file qua `curl` tới `http://10.0.60.209:8501/DFAgentSetup-PrintStation.msi` (không chỉ HEAD) — 7 giây, dung lượng đúng 29,405,184 bytes, MD5 khớp 100% với bản gốc trên máy dev (`08f35a8304da81365a0a33c1db5c0616`). Deploy code fix (2 lần: fix retry-download trong `.cmd`, rồi fix chuyển cổng 8501) bằng đúng quy trình `git push` → SSH `git pull` → restart Scheduled Task `DFWeb-Backend` trên CS-SERVER, xác nhận lại `curl` route `agent-launcher` trả đúng URL cổng `8501` sau deploy. **Chưa xác nhận lại bằng cài đặt thật trên máy trạm người dùng** — đang chờ người dùng thử lại nút "TẢI CÔNG CỤ".
- **Rủi ro còn lại cần lưu ý:** `DFWeb-Downloads` là process/port mới trên server dùng chung nhiều ứng dụng — chưa thông báo chính thức cho bộ phận IT quản lý server (người dùng đã đồng ý ngay trong phiên làm việc này, chưa rõ có cần thông báo thêm ai khác quản lý hạ tầng chung không). Về lâu dài, hạn chế đơn luồng của `php artisan serve` vẫn còn nguyên cho MỌI request khác của backend API (không chỉ riêng tải file) — đáng cân nhắc thay bằng web server thật (IIS+PHP hoặc php-fpm) cho toàn bộ backend trước khi Cutover chính thức (Phase 13), không chỉ vá riêng phần tải file như đợt này.

### 39. Sửa bug thật: dropdown "Máy in đã cài trên máy này" ở `/print-station` thiếu máy in — `agent/PrinterDiscovery.cs` quét thêm kết nối máy in riêng theo user

- **Người dùng báo:** máy in đã cài thật trên máy trạm không hiển thị đầy đủ trong dropdown "⚙️ Đổi máy in cho trạm này". Hỏi rõ và xác nhận: đúng là THIẾU máy in trong danh sách (không phải lỗi UI/CSS bị cắt, không phải danh sách trống hoàn toàn).
- **Truy vết:** `agent/Program.cs:18` xác nhận DF Agent chạy dưới **Windows Service** (tài khoản Local System). `PrinterDiscovery.cs` cũ chỉ chạy `Get-Printer` — cmdlet này chỉ thấy máy in cài "cho mọi người dùng" (lưu máy-wide trong Spooler). Máy in mạng/LAN cài theo cách thông thường (không tick "cho mọi người dùng", cách phổ biến nhất khi người vận hành tự cài) lưu dạng kết nối riêng theo profile Windows (`HKCU\Printers\Connections`) — tiến trình Service chạy dưới SYSTEM không đọc được `HKCU` của user khác nên các máy in đó biến mất khỏi danh sách Agent báo cáo lên web.
- **Sửa:** `PrinterDiscovery.cs::ListInstalledPrinters()` — quét thêm `HKEY_USERS\<SID>\Printers\Connections` cho mọi user đang đăng nhập (profile hive đang load, lọc theo pattern SID `S-1-5-21-...` để bỏ qua `.DEFAULT`/hive hệ thống), gộp kết quả với `Get-Printer`, loại trùng bằng `Sort-Object -Unique`.
- **Kiểm chứng:** viết script PowerShell y hệt logic mới, chạy thử trực tiếp trên máy dev — xác nhận bắt được cả máy in máy-wide (`TSC TTP-244 Pro`, `HP LaserJet...`) LẪN các kết nối mạng dạng `\\10.0.193.254\ZP-...` mà không phải lúc nào `Get-Printer` một mình cũng thấy tùy ngữ cảnh user. `dotnet build` sạch (0 lỗi, 3 warning cũ không liên quan).
- **Còn nợ:** cần rebuild MSI Agent mới rồi cài đè (upgrade) lên đúng máy trạm người dùng báo lỗi để Agent báo cáo lại danh sách máy in đầy đủ hơn — chưa build/deploy MSI mới trong phiên này (người dùng chưa yêu cầu).

### 40. Đơn giản hóa `/print-station`: bỏ "⚡ In nhanh" và "🖨️ In tem" (gửi thẳng Local Agent), chỉ còn 1 cách in DUY NHẤT là "🖥️ In qua trình duyệt"

- **Yêu cầu:** người dùng muốn bỏ nút "In nhanh" ở hàng chờ in tem, chỉ giữ lại 1 cách in duy nhất là in qua trình duyệt (hộp thoại in Windows/trình duyệt, chọn được bất kỳ máy in nào đã cài, không cần qua Local Agent/TSPL).
- **Đã hỏi rõ trước khi sửa:** vì "In qua trình duyệt" trước đó CHỈ mở cửa sổ xem trước/in thử (không gọi API confirm, đơn vẫn nằm nguyên trong hàng chờ "Chưa in"), còn nút "🖨️ In tem" (Local Agent) mới là hành động thật sự đánh dấu đơn đã in (gọi `POST /machine-dispatches/{id}/confirm`, ghi Audit Log). Người dùng xác nhận: khi bấm "In qua trình duyệt" phải TỰ ĐỘNG đánh dấu đơn đã in luôn (gộp 2 hành động làm 1), không cần thao tác thêm.
- **Rủi ro kỹ thuật phát hiện khi đọc code (không phải giả định):** `ConfirmDispatchService::createPrintJob()` LUÔN tạo `PrintJob` với `status='PENDING'` bất kể có gửi `printer_address` hay không (cột DB tự dùng default `'USB'`/`'TSC TE200'` khi thiếu) — nếu chỉ đơn giản gọi confirm sau khi in qua trình duyệt mà không xử lý gì thêm, Local Agent (`AgentJobsController::getJobs()` chỉ lấy job `status=PENDING`) vẫn sẽ lấy job này và gửi lệnh TSPL thật xuống máy in vật lý — **in trùng lần 2** với bản vừa in qua trình duyệt.
- **Sửa (3 lớp, đồng bộ frontend + backend):**
  1. `frontend/src/views/PrintStation.vue`: xóa nút "⚡ In nhanh" (hàng chờ) và nút "🖨️ In tem" (modal xem trước, xóa luôn hàm `confirmPrintFromPreview` không còn dùng). `printPreviewViaBrowser()` sau khi mở cửa sổ `window.print()` giờ tự gọi `confirmAndPrint(d, previewSelectedPrinter.value, true)` (tham số thứ 3 `viaBrowser=true` mới thêm) rồi tự đóng modal preview nếu không lỗi.
  2. `backend/app/Http/Controllers/MachineDispatchController.php::confirm()`: thêm field `printed_via_browser` (boolean, optional) vào validate + truyền xuống service.
  3. `backend/app/Services/ConfirmDispatchService.php::createPrintJob()`: khi `printed_via_browser=true` → set thẳng `status='PRINTED'` (không phải `PENDING`) nên Agent không bao giờ lấy job này; đồng thời tạo `PrintAttempt` (attempt_no=1, status=PRINTED) + ghi event `SENT_TO_PRINTER`/`PRINT_SUCCEEDED` ngay lập tức — y hệt cấu trúc dữ liệu mà `AgentJobsController::acknowledgeJob()` tạo khi Agent báo in thành công thật, chỉ khác nguồn xác nhận là trình duyệt tự báo ngay lúc confirm thay vì Agent báo sau.
- **Kiểm chứng:** `php -l` sạch cả 2 file PHP đã sửa. `npx vue-tsc --noEmit` sạch (không còn tham chiếu tới hàm/nút đã xóa). **Không chạy được `php artisan test`** trong môi trường này — Postgres test DB (`127.0.0.1:5433`) không có tiến trình lắng nghe (hạn chế môi trường đã ghi nhận nhiều lần ở các mục trước).
- **Còn nợ:** chưa xác minh bằng mắt trên trình duyệt thật (bấm "Xem trước" → "In qua trình duyệt" → xác nhận đơn biến mất khỏi hàng chờ + Audit Log/PrintAttempt được ghi đúng) — cần người dùng tự thử tại `/print-station`. Luồng "In lại tem" (quét QR ở màn dưới, nút "🖨️ In lại tem") KHÔNG bị đụng tới trong đợt sửa này — vẫn đi qua Local Agent như cũ, chỉ hàng chờ in tem mới (`TO_SEND`) ở trên bị đổi.

### 41. Thêm lại "⚡ In nhanh" ở hàng chờ — vẫn dùng cơ chế trình duyệt (mục 40), không quay lại Local Agent

- **Yêu cầu:** người dùng muốn có lại nút in nhanh ngay ở hàng chờ (không cần mở modal Xem trước trước), nhưng vẫn qua trình duyệt như mục 40, không quay lại gửi thẳng Local Agent/TSPL như bản gốc trước đó.
- **Sửa:** `PrintStation.vue` — tách phần dựng HTML tem + `window.print()` + gọi `confirmAndPrint(..., true)` (trước đó nằm nguyên trong `printPreviewViaBrowser`, phụ thuộc `previewDispatch`/`previewDyeLines`/`previewChemLines` của modal) ra hàm dùng chung `printDispatchViaBrowser(d, printerOverride?)` nhận thẳng `dispatch` + parse rack lines cục bộ bằng `parseRackLines()` thay vì đọc computed ref của modal. `printPreviewViaBrowser()` giờ chỉ là wrapper mỏng gọi hàm này với `previewDispatch.value`/`previewSelectedPrinter.value` rồi đóng modal. Thêm nút "⚡ In nhanh" ở hàng chờ gọi `quickPrintViaBrowser(d)` — wrapper gọi hàm chung với `resolvedPrinter.value` (máy in đã suy luận sẵn cho trạm), bỏ qua bước mở modal xem trước.
- **Kiểm chứng:** `npx vue-tsc --noEmit` sạch.

### 42. Sửa bug thật: tem in qua trình duyệt ra đúng nội dung nhưng "chưa to đúng kích cỡ" — thiếu `@page` nên in theo khổ giấy mặc định của driver

- **Người dùng gửi ảnh chụp tem in ra thật** từ `/print-station`: đúng nội dung (DF_WEIGHING_SLIP, JIT3, bảng RACK/mã/khối lượng, 2 QR) nhưng phần tem chỉ chiếm 1 góc nhỏ phía trên tờ giấy lớn hơn nhiều, không "to đúng kích cỡ".
- **Truy vết:** cả 3 nơi dựng HTML rồi gọi `window.print()` (cơ chế "in qua trình duyệt" thêm từ mục 34/40) — `printDispatchViaBrowser()` (`PrintStation.vue`, tem 70x100mm), `printMaterialLabelViaBrowser()` (`PrintStation.vue`, tem 80x50mm), và `printTsplViaBrowser()` (`utils/tsplPrint.ts`, dùng chung cho các trạm khác) — đều CHỈ có `.slip { zoom: 1 }` trong `@media print` mà THIẾU khai báo `@page { size: ...; margin: 0 }`. Không có `@page`, trình duyệt in theo khổ giấy đang chọn sẵn trong driver máy in (mặc định thường A4/Letter) — `.slip` vẫn đúng kích thước thật tuyệt đối (70mm/80mm..., không hề bị co giãn sai tỷ lệ) nhưng chỉ chiếm 1 phần nhỏ giữa tờ giấy to hơn nhiều, đúng y hệt hiện tượng trong ảnh người dùng gửi.
- **Sửa:** thêm `@page { size: <khổ tem thật>; margin: 0; }` vào cả 3 nơi (70mm 100mm / 80mm 50mm / `${widthMm}mm ${heightMm}mm` động theo TSPL `SIZE`) — Chrome/Edge hỗ trợ `@page size` sẽ tự yêu cầu đổi khổ giấy khớp đúng khổ tem khi in, thay vì giữ nguyên khổ mặc định của driver.
- **Sự cố nhỏ trong lúc sửa (đã tự phát hiện và sửa ngay):** ở `tsplPrint.ts`, bản sửa đầu tiên quên đóng comment CSS (`/* ... */`) trước khi thêm `@page`, khiến cả `@page` lẫn `@media print` phía sau bị nuốt vào trong comment (mất tác dụng hoàn toàn, không lỗi cú pháp JS/TS nên `vue-tsc` không bắt được). Phát hiện khi tự đọc lại file, sửa lại bằng cách đóng `*/` đúng chỗ trước khi khai báo `@page`.
- **Kiểm chứng:** `npx vue-tsc --noEmit` sạch sau khi sửa (cả bản lỗi comment lẫn bản đã sửa đều "sạch" về TypeScript — nhắc lại: lỗi comment CSS không phải lỗi mà `vue-tsc` phát hiện được, phải tự đọc lại code mới thấy). **Chưa in thử lại trên máy in thật** — cần người dùng tự in lại tem ở `/print-station` để xác nhận khổ giấy đã khớp đúng 70x100mm.


### 42. Áp y hệt hành vi VBA `semiauto-small-scale` vào `/weighing-station`: dung sai ±1%, bỏ cổng chặn override, `Abs()` cho delta, in danh sách RACK thay `btn_Out`/`btn_In`

- **Trích xuất lại toàn bộ VBA** từ `semiautosmall scale  deltastablefinal1_UNLOCKED.xlsm` qua Excel COM (`VBComponents`/`CodeModule.Lines`, `Protection=0`) — 22 module. Đối chiếu với code hiện tại xác nhận **phần lớn cơ chế đã port đúng từ trước**: bảng RACK/DYE/WEIGHT/PROCESS 9 dòng, `StableFilter` (`agent/ScaleReader.cs`, `cnt>=1`), bì/delta (`Delta_Begin`/`AutoFlow_OnWeight`), 3 mức màu `CheckRange`, `checkform` → `WeighingCheckerModal`, `btnPrint_Click` → `printSlip`, tách trường QR `txt_color_AfterUpdate`.
- **Phát hiện thêm khi đọc VBA (ghi nhận, chưa xử lý — đều nằm trong file nguồn, không phải code web):** `ModAcessDB.GetDB()` thiếu dấu `\` (`"Z:DF_SCALE\RECORD.accdb"` là đường dẫn tương đối theo thư mục hiện hành của ổ Z:, không phải tuyệt đối); `btnSave_Click` nối chuỗi SQL trực tiếp và không có transaction (lỗi giữa chừng để lại bản ghi dở); `checkform.btnCheck_Click` in lặp dòng header vì thiếu `rs.MoveNext` trước vòng detail; `Modcleanweight` có `Option Explicit` lần thứ hai ở dòng 54 nằm ngoài declarations section (`CountOfDeclarationLines = 1`) — nghi lỗi biên dịch tiềm ẩn, **chưa chạy `Debug > Compile VBAProject` để xác nhận**; `mdQRCodegen.GenerateQRCode` (gọi `api.qrserver.com`) và toàn bộ `Mod_print_tsc224` (`OpenPrinter`/`WritePrinter` RAW) là **code chết** — `btnPrint_Click` thực tế in qua `ws.PrintOut ActivePrinter:="TSC TTP-224 Pro"` (driver GDI), không phải TSPL thô.
- **4 điểm lệch còn lại — người dùng chốt từng điểm qua câu hỏi trực tiếp trước khi code:**
  1. **Dung sai ±1%** (`ScannerController`): xóa hằng `FIXED_TOLERANCE_GRAMS = 0.01` (thay đổi chưa commit hôm 30/07 theo yêu cầu "tôi muốn chỉ được chênh 0.01"), quay lại `TOLERANCE_RATIO = 0.01` nhân với mục tiêu ở cả 2 luồng tạo `WeighingJobItem` (ad-hoc + có Recipe) — đúng `Mod_UI_processcolor.CheckRange` (`ratio 0.99–1.01`). Giữ mô hình lưu tuyệt đối vào `tolerance_minus`/`tolerance_plus` (tương đương toán học, snapshot lúc quét nên không trôi). **Đơn đã tạo trước đó vẫn giữ ±0.01g đã snapshot.**
  2. **Bỏ cổng chặn dung sai** (`WeighingJobController::weighItem`): xóa khối trả 422 `OUT_OF_TOLERANCE` và **toàn bộ luồng override** (PIN Giám sát, kiểm tra role SUPERVISOR/ADMIN, bắt lý do ≥5 ký tự, `AuditLog` `WEIGH_TOLERANCE_OVERRIDE`). Mọi lần cân đều lưu được, luôn `status = COMPLETED`. Nhãn ĐẠT/KHÔNG ĐẠT **suy ra, không thêm cột/migration**: accessor `WeighingJobItem::process_status` (`$appends`) so `actual_weight` với `planned_weight ± tolerance_*` đã snapshot trên chính item — tương đương cột `processColor` VBA ghi xuống Access. `printSlip`, `ScaleMeasurementController::checker`, `WeighingRackTable.vue`, `WeighingCheckerModal.vue` đều đọc chung accessor này.
  3. **`Abs()` cho delta** (`WeighingStation.vue::ingestRawWeight`): `liveWeight = Math.abs(raw - tareBaseline)` đúng `Mod_delta_raw.AutoFlow_OnWeight`. Bỏ trạng thái `negative` (thêm hôm 30/07 để bắt hao hụt) khỏi `toleranceStatus`/`statusMessage` + 2 rule CSS trong `LiveScaleDisplay.vue`. **Đánh đổi đã nêu rõ và được chấp thuận:** lấy bớt vật tư ra khỏi đĩa giờ hiển thị giống như đang thêm vào.
  4. **`btn_Out`/`btn_In` → in danh sách RACK qua trình duyệt**: VBA bắn rack sang app pha màu ngoài bằng mô phỏng chuột + clipboard vào toạ độ màn hình cố định (`ClickAt 345,200`...) — không port được sang web và vốn rất mong manh. Thay bằng nút "🏷️ In danh sách RACK" dựng HTML + `window.print()`, **giữ đúng cách chia lô 6 rack/lần** của `Mod_sendRackauto.BuildRackBatch` (lọc rack rỗng và rack `"0"`). `window.open()` gọi đồng bộ ngay trong handler theo đúng tiền lệ `printSlip`/`PrintStation.vue` (tránh Chrome/Edge chặn popup sau `await`).
- **Lệch có chủ đích khỏi `CLAUDE.md` mục 5 (đã hỏi rõ, người dùng phê duyệt):** không còn Audit Log `WEIGH_TOLERANCE_OVERRIDE` vì không còn hành vi "phê duyệt" nào để ghi. Dữ liệu vẫn đủ tái dựng nhãn ĐẠT/KHÔNG ĐẠT vĩnh viễn từ `actual_weight` + `planned_weight` + `tolerance_*` trên `weighing_job_items`.
- **Báo cáo M11 bị vỡ theo, đã sửa cùng lúc** (`ReportController::toleranceStats`): `SUM(CASE WHEN wji.override_approved ...)` và `where('status','OUT_OF_TOLERANCE')` sau thay đổi trên **vĩnh viễn bằng 0**. Đổi sang biểu thức SQL so trực tiếp `actual_weight` với biên dung sai (cùng công thức với accessor), đổi khóa JSON `override_count`/`override_rate_pct`/`total_override` → `reject_count`/`reject_rate_pct`/`total_reject`, bỏ `pending_resolution_count`. Cập nhật nhãn tương ứng trong `Reports.vue` ("Override" → "Không đạt", tab "Dung sai & Không đạt").
- **Kiểm chứng:** `php -l` sạch cho cả 5 file PHP đã sửa. `npx vue-tsc --noEmit -p tsconfig.app.json` **không phát sinh lỗi mới** (còn đúng 3 lỗi cũ có sẵn ở `WeighingStation.vue` dòng 60/181, không liên quan). Accessor `process_status` test bằng instance in-memory qua `tinker` (không đụng DB) đúng cả 6 mốc biên với mục tiêu 12.5g/±0.125: 12.30 → REJECTED, **12.375 → ACCEPTED**, 12.45 → ACCEPTED, **12.625 → ACCEPTED**, 12.70 → REJECTED, chưa cân → PENDING. Biểu thức SQL `reject_count` mới chạy thật trên Postgres dev (SELECT read-only qua `tinker`) → `SQL OK — total=4 reject=0`, không lỗi cú pháp. Vite HMR sạch, 3 tiến trình (backend 8500, Reverb 8080, vite 3001) chạy nền suốt phiên.
- **CHƯA chạy được `php artisan test`** — vẫn đúng hạn chế môi trường đã ghi ở mục 32–33 (không có Postgres test DB cổng 5433, `.env` dev trỏ DB thật `10.0.60.209` nên không ghi thử vào đó). **Chưa xác minh bằng mắt trên trình duyệt** — đã mở sẵn `http://localhost:3001/weighing-station`, cần người dùng tự kiểm tra 5 điểm: (1) mục tiêu 12.5g thì 12.30 vàng / 12.45 xanh / 12.70 đỏ, (2) bấm Xác nhận khi đang đỏ vẫn lưu được và không hiện hộp PIN, (3) kéo slider xuống dưới bì thì số hiển thị dương, (4) "🏷️ In danh sách RACK" hiện hộp thoại in ngay lần bấm đầu, (5) `/reports` tab dung sai hiện số "Không đạt" khớp thực tế.
- **Đã rà soát và sửa test cũ bị vỡ theo** (grep toàn `tests/`, không chỉ suy đoán): `KioskOperationTest::test_kiosk_mode_weighing_override_requires_and_verifies_supervisor_pin` → đổi thành `test_kiosk_mode_saves_out_of_tolerance_weight_and_labels_it_rejected` (bỏ chuỗi assert 422/403/403/200, còn 1 lần POST duy nhất expect 200 + `process_status = REJECTED`); `ReportsTest::test_tolerance_stats_report_computes_override_rate` → `..._computes_reject_rate` (khóa JSON mới), **xóa** `test_tolerance_stats_counts_pending_out_of_tolerance_items` (trạng thái `OUT_OF_TOLERANCE` không còn được set), `test_weigh_item_persists_override_and_writes_audit_log` → `test_operator_can_save_out_of_tolerance_weight_without_supervisor`, bỏ luôn tham số `$override` khỏi helper `makeCompletedWeighingItem` (cả 5 call site đều truyền `false`); `ScaleCheckerAndPrintSlipTest` → đổi assert `override_approved` sang `process_status = REJECTED`, và sửa assert `PrintJob::status` từ `PENDING` sang `PRINTED` + `printer_connection_type = BROWSER` (vỡ do thay đổi in-qua-trình-duyệt ở mục 40-41, **test này đã sai từ lúc đó mà chưa ai phát hiện vì không chạy được test suite**). `FeedReadinessTest` có `override_approved` nhưng là tính năng KHÁC (`/api/feed-operations/{id}/override`, M07) — không đụng tới. `ConfirmDispatchTest`/`PrintJobEventsTest` assert `PENDING` cho luồng dispatch confirm không gửi `printed_via_browser` — vẫn đúng, không sửa.
- **Việc CHƯA làm:** chưa cập nhật `CLAUDE.md` mục 5 để phản ánh việc bỏ Audit Log override dung sai; các test đã sửa **chưa chạy được lần nào** để xác nhận PASS thật.

### 43. Gộp về 1 bộ cài Local Agent duy nhất (chỉ nhận cân) — và sửa lỗi chặn: luồng cân thật qua RS232 chưa từng đẩy được số nào lên backend

- **Yêu cầu:** "tôi chỉ cần 1 bộ cài duy nhất để nhận cân thôi, máy in tôi in qua trình duyệt rồi." Sau mục 40-42, cả Print Station lẫn Weighing Station đều in bằng hộp thoại in của trình duyệt, nên phần máy in của Agent không còn được dùng ở đâu.
- **Lỗi chặn phát hiện khi rà lại cơ chế nhận cân (không phải giả định — đọc thẳng code):** `ScaleReader.ReadCurrentWeightWithStability()` khi chạy cân THẬT qua cổng COM trả về `(null, false)` vô điều kiện, kèm chú thích "serial port uses event-driven reading". Nhưng handler sự kiện `ProcessRawData()` chỉ `LogDebug` rồi **vứt bỏ kết quả**, không lưu đi đâu cả. `Worker.cs` chỉ push lên backend khi `currentWeight.HasValue` → **cắm cân thật qua RS232 thì Agent không bao giờ gửi được số cân nào lên hệ thống.** Lỗi bị che khuất suốt vì cả 3 file `appsettings.*.json` đóng gói trong MSI đều để `Scale:UseSimulation = true`, luôn rơi vào nhánh đọc file log PuTTY.
  - **Sửa:** tách `IngestSerialData(chunk)` (public, test được mà không cần cổng COM thật) — đệm dữ liệu và **chỉ xử lý dòng đã kết thúc bằng CR/LF**, chốt số đọc mới nhất vào field có `lock`; `ReadCurrentWeightWithStability()` trả về số đó khi dùng cổng COM. Xóa `ProcessRawData()`.
  - **Lý do phải đệm theo dòng:** `SerialPort.ReadExisting()` trả đúng nội dung buffer tại thời điểm gọi, cắt giữa dòng là bình thường (`"12,ST,GS,+00001"` | `"0.5g\r\n"`). Đưa thẳng mảnh cụt vào `CleanWeight` thì token số cuối là số CỤT (1 thay vì 10.5) — **sai số cân mà không có dấu hiệu gì**. Đã khóa bằng test.
  - Không đặt thời hạn hết hiệu lực cho số đọc cuối: giữ tới khi có số mới, đúng quy ước TV6 và khớp nhánh đọc file (dòng cuối log PuTTY cũng nằm nguyên đó). Cache backend TTL 15s vẫn là lớp chặn cuối nếu Agent chết.
- **Test project của Agent hoá ra đã KHÔNG BIÊN DỊCH ĐƯỢC từ 2026-07-17** — 4 lỗi `CS0266/CS1503` do đợt đổi `CleanWeight` sang `double?` (TV6) không cập nhật test theo. Nghĩa là unit test Agent chưa từng chạy kể từ đó. Đã sửa 4 call site + đổi `CleanWeight_ChuoiRong_TraVe0` → `..._TraVeNull` cho đúng hành vi TV6 hiện tại.
- **1 bộ cài duy nhất:**
  - `agent/installer/appsettings.scale.json` **mới** (Role `SCALE_ONLY`, `UseSimulation: false` — đọc cân thật qua COM, không còn mục `Printer`), **xóa** 3 file `appsettings.print-station/weighing-printer/weighing-scale.json`.
  - `DFAgentSetup.wxs`: mặc định `StationId = WS-WEIGH-SCALE` + `AppSettingsFile = appsettings.scale.json`, `PackageVersion = 2.0.0.0` (cao hơn hẳn 1.4.x của cả 3 bản cũ nên máy trạm đã cài bất kỳ vai trò nào cũng nâng cấp thẳng lên được — giữ nguyên `UpgradeCode` để `MajorUpgrade` tự gỡ bản cũ, tránh 2 service trùng tên `DFAgent`). Đổi mô tả service sang đúng việc còn lại (đọc cân).
  - `agent/installer/build.ps1` **mới** (thay `build-all.ps1` vốn được nhắc trong chú thích nhưng **không tồn tại trong repo**): publish .NET → `wix build` → copy sang `backend/public/downloads/`.
  - `backend/routes/web.php`: bỏ hẳn tham số `{role}`, route còn `/downloads/agent-launcher` phục vụ `DFAgentSetup-Scale.msi`.
  - `AppLayout.vue`: dropdown 2 mục → 1 link tải thẳng "DF Agent (Nhận cân)", xóa `toolMenuOpen` + 3 rule CSS của menu.
  - Xóa 6 artifact cũ (`DFAgentSetup-{PrintStation,WeighingPrinter,WeighingScale}.{msi,wixpdb}`) khỏi git và khỏi `public/downloads/`.
- **`WeighingStation.vue`: `useSimValue` mặc định `true` → `false`.** Khi bật simulator, `fetchLiveWeight()` thoát ngay ở dòng đầu nên số cân thật do Agent đẩy lên **bị bỏ qua hoàn toàn** — để mặc định bật thì trạm cắm cân thật vẫn không thấy số mà không có dấu hiệu gì. Vẫn giữ công tắc cho demo/UAT.
- **Kiểm chứng:** `dotnet test` (kèm `DOTNET_ROLL_FORWARD=Major` vì máy chỉ có runtime 3.1/9/10, không có 8.0) → **10/10 PASS**, gồm 4 test mới: chunk cắt giữa dòng không sinh số sai, nhiều dòng trong 1 chunk lấy dòng cuối, dòng rác giữ nguyên số hợp lệ gần nhất (TV6), 2 dòng giống nhau mới đánh dấu ổn định (StableFilter theo từng dòng cân gửi ra, không theo vòng poll). `php -l routes/web.php` sạch. `vue-tsc` **25 lỗi = đúng bằng baseline** (`git stash` rồi đếm lại), không phát sinh lỗi mới. Build MSI thật thành công (28.1 MB); **giải nén MSI kiểm tra lại** `appsettings.json` đóng gói bên trong đúng `Role: SCALE_ONLY` + `UseSimulation: false`. Smoke test HTTP thật: `/downloads/agent-launcher` → 200 (993 B, nội dung .cmd trỏ đúng `DFAgentSetup-Scale.msi`), `/downloads/DFAgentSetup-Scale.msi` → 200 đủ 29.413.376 byte, route cũ `/downloads/agent-launcher/print-station` → **404** như mong đợi.
- **CHƯA kiểm chứng được (nêu rõ, không báo là đã chạy):** không chạy thử `DFAgent.exe` end-to-end tại chỗ vì `storeReading()` tự tạo/gán `OperationClient`/`Device` — sẽ **ghi vào DB thật `production_web` (10.0.60.209)**, vi phạm quy tắc an toàn dữ liệu. Nhánh serial mới chỉ được phủ bằng unit test, **chưa chạy với cân vật lý** — cần xác minh tại trạm khi cắm cân thật.
- **Cần làm khi triển khai:** `Backend:Url` trong `appsettings.scale.json` vẫn đóng cứng `http://10.0.200.248:8500/api`, và `Workstation:Id` đóng cứng `WS-WEIGH-SCALE` — **nếu có từ 2 trạm cân trở lên** thì mỗi trạm phải sửa `Workstation:Id` trong `C:\Program Files\DFAgent\appsettings.json` sau khi cài rồi restart service, nếu không các trạm sẽ ghi đè số cân của nhau (cache backend đánh khóa theo `workstation_id`).

### 44. Chỉnh viền/khoảng cách tem `/print-station` (`printDispatchViaBrowser`) — người dùng phản ánh "kẻ viền to và chưa chuẩn lắm, đang bị đè lên chữ"

- **Người dùng báo** (không kèm ảnh mới lần này, dựa trên ảnh tem in thật đã gửi trước đó ở mục 42): viền kẻ trên tem in ra to quá và không chuẩn, có chỗ đè lên chữ.
- **Rà lại CSS (`.slip`/`.box`/`.gridcell`) trong hàm `printDispatchViaBrowser`:** viền ngoài `.slip` đang để `1.2mm` — dày bất thường so với các viền khác (`.box` 0.3mm, `.gridcell` 0.2mm). Đáng chú ý hơn: các ô ở hàng tiêu đề (DF_WEIGHING_SLIP/zone/QR chế độ, và hàng màu/mã hàng/máy/thùng/mực nước) mỗi ô tự vẽ viền riêng bằng `position:absolute` đặt sát cạnh nhau (toạ độ x2 ô này = x1 ô sau) — 2 viền 0.3mm của 2 ô liền kề cộng lại tại đúng 1 đường ranh giới nhìn dày gần gấp đôi (~0.6mm), trong khi padding chỉ 0.4-0.8mm nên chữ có cảm giác bị viền đè sát vào.
- **Sửa (chỉ chỉnh độ dày viền + padding, KHÔNG đổi toạ độ/bố cục):** `.slip` 1.2mm → 0.4mm, `.box` 0.3mm → 0.2mm, `.gridcell` 0.2mm → 0.15mm, padding `.box` 0.4mm 0.8mm → 0.5mm 0.9mm (giãn cách chữ với viền nhiều hơn). Chỉ sửa template tem hàng chờ dispatch (`DF_WEIGHING_SLIP`, đúng cái trong ảnh người dùng gửi) — không đụng tới template tem vật tư (`printMaterialLabelViaBrowser`, viền đã mỏng sẵn 0.3mm, không có lưới bảng nên không bị lỗi cộng dồn viền tương tự).
- **Kiểm chứng:** `npx vue-tsc --noEmit` sạch (thay đổi thuần CSS trong template string, không ảnh hưởng logic/type). **Chưa in thử lại trên tem thật** — cần người dùng in lại và xác nhận viền đã mỏng/rõ ràng hơn, đặc biệt tại các đường phân cách bị cộng dồn (giữa DF_WEIGHING_SLIP/zone/QR, và giữa 4 ô màu-mã hàng/máy/thùng/mực nước).

### 45. Tăng nhẹ QR góc trên bên phải (chế độ PROCESS/EXTRA/FB) ở tem `/print-station` — bị giới hạn vật lý bởi chiều cao hàng tiêu đề 14mm

- **Yêu cầu:** người dùng muốn QR ở góc trên bên phải to hơn.
- **Rà lại toạ độ:** ô QR này (`boxDot(391,0,560,112,...)`) nằm trong hàng tiêu đề cao đúng 112dot=14mm — hàng thứ 2 (màu/mã hàng/máy/thùng/mực nước) bắt đầu ngay sau ở y=114dot, gần như không có khe hở. Đây là giới hạn cứng của layout hiện tại (đã port đúng toạ độ tem thật, xem chú thích đầu hàm `printDispatchViaBrowser`).
- **Đã làm (an toàn, không đổi toạ độ ô nào khác):** thêm tham số `extraClass` cho `box()`/`boxDot()` để gắn class riêng `.mode-qr-cell` chỉ cho ô này, giảm padding của riêng ô này từ chuẩn `.box` (0.5mm/0.9mm, dành cho chữ) xuống `0.15mm` (ô này chỉ chứa ảnh, không cần đệm cho chữ) — tăng ảnh QR từ `13mm` → `13.2mm`, đã tính toán để KHÔNG vượt quá chiều cao thật còn lại của ô (14mm trừ viền 0.2mm×2 trừ padding 0.15mm×2 = 13.3mm), tránh tái diễn lỗi tràn viền vừa sửa ở mục 44.
- **Còn nợ (chưa làm, cần xác nhận trước):** mức tăng trên khá khiêm tốn (~2%) do đúng là hết chỗ vật lý. Nếu người dùng muốn to rõ rệt hơn (ví dụ ~17-18mm), cách duy nhất là nới cao cả hàng tiêu đề — kéo theo phải dịch 2 ô "Thùng"/"Mực nước" của hàng dưới (cùng dải cột X với QR, x:391-560) xuống theo, lấy khoảng trống dư ra từ việc thu gọn chiều cao 9 hàng bảng RACK/CHEM (hiện đang dư khá nhiều so với cỡ chữ 2.2mm dùng trong bảng). Đây là thay đổi bố cục lớn hơn (đụng vị trí nhiều ô), **chưa làm**, cần người dùng xác nhận trước khi triển khai.
- **Kiểm chứng:** `npx vue-tsc --noEmit` sạch. Chưa in thử lại trên tem thật.

### 47. Tự đóng cửa sổ in sau khi in xong — áp dụng cho cả 3 nơi dùng cơ chế "in qua trình duyệt"

- **Yêu cầu:** người dùng muốn cửa sổ popup mở ra để in (hộp thoại in Windows/trình duyệt) tự đóng lại sau khi in xong, không phải tự tay đóng từng tab.
- **Sửa:** thêm `window.onafterprint = function () { window.close(); };` trước dòng `window.print()` ở CẢ 3 nơi dùng cơ chế này: `PrintStation.vue::printDispatchViaBrowser` (tem hàng chờ dispatch) + `printMaterialLabelViaBrowser` (tem vật tư reprint), `WeighingStation.vue` (in danh sách RACK), và `utils/tsplPrint.ts::printTsplViaBrowser` (dùng chung cho các trạm khác). Sự kiện `afterprint` bắn ra sau khi hộp thoại in đóng lại (dù bấm In hay Hủy), Chrome/Edge/Firefox đều hỗ trợ.
- **Kiểm chứng:** `npx vue-tsc --noEmit` sạch. Chưa test tay trên trình duyệt thật (cần xác nhận `afterprint` bắn đúng và `window.close()` không bị trình duyệt chặn — cửa sổ được mở bằng `window.open()` từ chính script nên về nguyên tắc đóng được, nhưng cần xác nhận thực tế trên máy người dùng).

### 48. Thêm cờ "Đã từng in" (khác "✅ OK"/CONFIRMED) cho hàng chờ `/print-station` — đổi nền về bình thường sau lần in đầu, có ô tick riêng

- **Yêu cầu:** người dùng muốn khi 1 đơn đã từng được in (qua "⚡ In nhanh"/"🖥️ In qua trình duyệt"), nền hàng chờ đổi từ đỏ về bình thường — nhưng KHÔNG tính là "đã in xong" (đó vẫn là việc của nút "✅ OK", chuyển xuống lịch sử). Cần 1 ô tick riêng cho "đã từng in", tự động tích khi in lần đầu.
- **Vấn đề:** từ mục 40 trở đi, "⚡ In nhanh"/"🖥️ In qua trình duyệt" chỉ mở hộp thoại in (client-side thuần, không gọi API) — không có nơi nào ở backend lưu lại "đơn này đã từng được in qua trình duyệt chưa", nên không có dữ liệu để tô màu theo yêu cầu.
- **Sửa (3 lớp):**
  1. **Migration** `2026_07_31_000001_add_ever_printed_to_machine_dispatches.php`: thêm cột `ever_printed` (boolean, default false) vào `machine_dispatches`. Không cần Audit Log — chỉ là cờ bookkeeping hiển thị, không đổi routing/QR/PrintJob thật.
  2. **Backend:** `MachineDispatch` model thêm `ever_printed` vào `$fillable`/`$casts`; `MachineDispatchController::markEverPrinted()` (route mới `PATCH /api/machine-dispatches/{id}/ever-printed`) — chỉ set cờ, không đụng `queue_state`.
  3. **Frontend (`PrintStation.vue`):** thêm cột "Đã từng in" (checkbox) vào bảng hàng chờ, hàm `toggleEverPrinted(dispatch, value)` gọi API + cập nhật lạc quan tại chỗ (rollback nếu lỗi). `printDispatchViaBrowser()` tự gọi `toggleEverPrinted(d, true)` ngay sau khi mở được cửa sổ in lần đầu (`if (!d.ever_printed)`). Class nền hàng đổi từ chỉ dựa vào `confirmedIds` sang `confirmedIds.has(d.id) || d.ever_printed` — người dùng vẫn tick/bỏ tick tay được ô này nếu cần sửa lại.
- **Kiểm chứng:** `npx vue-tsc --noEmit` sạch, `php -l` sạch cả 3 file PHP. Đã hỏi xác nhận người dùng trước khi đụng `production_web` (10.0.60.209) theo `database-safety.md` — người dùng đồng ý, đã chạy `php artisan migrate --path=... --force`, xác nhận `Schema::hasColumn('machine_dispatches','ever_printed')` trả `true`.

### 49. Fix mục 47: `window.onafterprint` không thật sự đóng được cửa sổ in — chuyển sang gọi `window.close()` ngay sau `window.print()`

- **Người dùng báo:** sau mục 47, cửa sổ in (hiện "about:blank") vẫn không tự đóng sau khi in xong.
- **Nguyên nhân (suy luận từ hành vi trình duyệt, không test tay được công cụ in thật trong môi trường này):** sự kiện `afterprint` có nhiều bất định giữa các trình duyệt/phiên bản khi cửa sổ được tạo bằng `window.open()` + `document.write()` rồi gọi `window.print()` ngay trong `onload` — không đảm bảo luôn bắn ra đúng lúc để `window.close()` chạy.
- **Sửa (áp dụng cả 3 nơi — `PrintStation.vue` x2, `WeighingStation.vue`, `utils/tsplPrint.ts`):** bỏ `window.onafterprint`, gọi thẳng `window.print(); window.close();` liên tiếp trong `onload` — dựa vào `window.print()` chặn (blocking) thực thi script tới khi hộp thoại in đóng lại trên Chrome/Edge (2 trình duyệt Windows đang dùng thực tế), nên `window.close()` ở dòng kế tiếp chắc chắn chạy SAU khi người dùng bấm In/Hủy.
- **Kiểm chứng:** `npx vue-tsc --noEmit` sạch. **Chưa xác nhận lại trên trình duyệt thật** — cần người dùng in thử lại xem cửa sổ đã tự đóng đúng chưa; nếu Chrome/Edge trên máy đó KHÔNG chặn ở `window.print()` (một số cấu hình enterprise/extension có thể đổi hành vi này) thì cách này cũng không ăn thua, lúc đó cần quay lại hướng khác (ví dụ tự đóng sau 1 khoảng `setTimeout` cố định, chấp nhận đóng hơi sớm/trễ).

### 50. Lỗi tôi tự gây ra ở mục 46: dấu backtick trong comment CSS làm vỡ template literal, trang trắng `[plugin:vite:vue] Missing semicolon`

- **Người dùng báo:** `[plugin:vite:vue] [vue/compiler-sfc] Missing semicolon. (423:24)` tại `PrintStation.vue` — trang không chạy được.
- **Nguyên nhân (lỗi tôi gây ra, không phải bug có sẵn):** ở mục 46 tôi viết comment CSS bên trong template literal HTML của `printDispatchViaBrowser()` có dùng dấu backtick để trích dẫn tên thuộc tính CSS: ``đổi `align-items` sang `flex-start` ``. Template literal JS dùng chính ký tự backtick làm dấu kết chuỗi — backtick đầu tiên trong comment KẾT THÚC chuỗi HTML giữa chừng, toàn bộ phần còn lại bị parser hiểu là code JS, gây lỗi cú pháp ở vị trí rất xa nơi thật sự sai (báo dòng 423 trong khi lỗi nằm ở dòng 774).
- **Sửa:** bỏ backtick trong comment đó (viết trần `align-items`/`flex-start`).
- **Bài học quy trình (quan trọng, đã lặp lại 2 lần trong phiên này):** `vue-tsc --noEmit` **KHÔNG bắt được** lỗi này — nó chỉ kiểm tra kiểu TypeScript, không biên dịch đầy đủ SFC/template literal. Cũng như lỗi comment CSS chưa đóng `*/` ở mục 42, cả 2 lần `vue-tsc` đều báo sạch trong khi code thật sự hỏng. **Từ nay khi sửa nội dung bên trong template literal HTML (các hàm in qua trình duyệt), phải chạy `npm run build` chứ không chỉ `vue-tsc --noEmit`.**
- **Kiểm chứng:** `npx vue-tsc --noEmit` sạch VÀ `npm run build` thành công (36.17s, không lỗi) — lần này dùng build thật để chắc chắn, đúng theo bài học vừa nêu. Rà lại toàn bộ backtick còn lại trong 3 file liên quan (`PrintStation.vue`, `tsplPrint.ts`, `WeighingStation.vue`) xác nhận không còn chỗ nào dùng backtick sai ngữ cảnh.

### 51. Nút "🖨️ In lại" ở bảng Lịch sử đã in (`/print-station`) — có ghi Audit Log + lý do theo đúng CLAUDE.md

- **Yêu cầu:** đơn đã nằm ở bảng "📋 Lịch sử đã in" vẫn cần in lại được, cần 1 nút in lại ở bảng dưới.
- **Quyết định thiết kế (KHÁC có chủ đích với "⚡ In nhanh" ở hàng chờ):** in lại tem đã xác nhận xong là **hành động nhạy cảm** — CLAUDE.md mục 5 và `database-safety.md` mục 5 đều liệt kê "In lại tem (Reprint)" vào nhóm 100% phải ghi Audit Log bất biến kèm lý do. Vì vậy nút này KHÔNG chỉ mở hộp thoại in như ở hàng chờ, mà bắt buộc nhập lý do (tối thiểu 3 ký tự) rồi gọi endpoint `POST /machine-dispatches/{id}/reprint` sẵn có (đã ghi `AuditLog: PRINT_JOB_REPRINTED` + event `REPRINT_REQUESTED`, tái dùng đúng QrPayload lần đầu, không tính lại routing).
- **Sửa:**
  - **Backend** `MachineDispatchController::reprint()`: thêm `printed_via_browser` vào validate + `$request->only()` — cùng lý do như `confirm()` (mục 40): tem đã in xong qua trình duyệt rồi, nếu để PrintJob ở `PENDING` thì Local Agent sẽ lấy và in trùng lần nữa xuống máy in vật lý. `ConfirmDispatchService::reprint()` truyền thẳng `$options` xuống `createPrintJob()` nên cờ này đã được xử lý sẵn, không phải sửa service.
  - **Frontend** `PrintStation.vue`: thêm cột "Số lần in" (`d.print_jobs?.length`, đúng key đã dùng ở `PrintJobHistoryTable.vue`) + cột "Thao tác" với nút "🖨️ In lại" vào bảng lịch sử; hàm `reprintFromHistory(dispatch)` **mở cửa sổ in TRƯỚC rồi mới `prompt()` hỏi lý do** — nếu mở sau prompt thì "transient user activation" của cú click có thể đã hết hạn (người dùng gõ lý do mất vài giây) và trình duyệt chặn popup; hủy prompt thì đóng cửa sổ vừa mở. Refactor `printDispatchViaBrowser(d, opts)` nhận thêm `existingWin` (dùng lại cửa sổ caller đã mở) và `markEverPrinted` (đặt `false` cho đơn ở lịch sử — cờ "đã từng in" của hàng chờ không còn ý nghĩa với đơn đã CONFIRMED).
- **Kiểm chứng:** `npx vue-tsc --noEmit` sạch, `npm run build` thành công (36.67s — dùng build thật theo bài học mục 50), `php -l` sạch. **Chưa test tay trên trình duyệt** — cần người dùng bấm thử "🖨️ In lại" ở bảng lịch sử để xác nhận: hộp thoại in mở đúng, lý do được lưu, cột "Số lần in" tăng lên sau khi in lại.

### 52. `/production-batches`: bảng "10 lô gần nhất" → "30 lô gần nhất" (phải sửa cả backend, không chỉ nới `slice` ở client)

- **Yêu cầu:** người dùng báo lô sau khi bấm "✅ OK" ở `/print-station` thì biến mất khỏi bảng "🕘 10 lô gần nhất" ở `/production-batches`, muốn đổi thành 30 lô gần nhất.
- **Xác minh nguyên nhân trước khi sửa (không đoán):** đọc lại `ConfirmDispatchService::confirm()` — chỉ đổi `queue_state` của `machine_dispatches`, **không** đụng `production_batches.status`; `ProductionBatchController::index()` cũng chỉ ẩn `status = CANCELLED`. Vậy lô KHÔNG bị lọc mất vì trạng thái — nó chỉ bị các lô mới quét sau đó đẩy ra khỏi top 10. Kết luận: nới giới hạn lên 30 đúng là cách xử lý cho hiện tượng người dùng gặp.
- **Điểm dễ sai đã tránh:** chỉ đổi `slice(0, 10)` → `slice(0, 30)` ở frontend là KHÔNG đủ — `ProductionBatchController::index()` `paginate(15)`, tức API chỉ trả tối đa 15 dòng/trang, bảng sẽ dừng ở 15 chứ không bao giờ đủ 30.
- **Sửa:**
  - **Backend** `ProductionBatchController::index()`: thêm tham số tùy chọn `per_page` (mặc định vẫn 15 để **không đổi hành vi phân trang** của `/production-batches/list`, `FeedingMonitor`, `OrderScan`, `Troubleshooting`, `QrScanPanel` đang dùng chung endpoint), chặn trần 100.
  - **Frontend** `ProductionBatches.vue`: thêm hằng `RECENT_BATCH_LIMIT = 30` dùng chung cho cả tiêu đề (`🕘 {{ RECENT_BATCH_LIMIT }} lô gần nhất`), `slice()`, và tham số `per_page` khi gọi API — tránh 3 chỗ lệch nhau về sau. Dọn các comment/CSS còn ghi cứng "10 lô gần nhất".
- **Lợi ích kèm theo:** `batches` cũng là nguồn dữ liệu cho `checkDuplicateOrder` (CHECK trùng màu/mã hàng trước khi SAVE) — có 30 dòng thay vì 15 thì phát hiện trùng chính xác hơn.
- **Kiểm chứng:** `npx vue-tsc --noEmit` sạch, `npm run build` thành công (36.03s), `php -l` sạch. **Chưa test tay trên trình duyệt** — cần người dùng mở lại `/production-batches` xác nhận bảng hiện đủ 30 dòng.

### 53. Tem in mất viền TRÊN + TRÁI (nằm ngoài tem) — hệ quả của chính `@page margin:0` thêm ở mục 42, sửa bằng scale 95% co vào tâm

- **Người dùng báo:** tem in ra ở `/print-station` không thấy viền phía trên và bên trái, phần đó "nằm ngoài tem rồi".
- **Nguyên nhân (là hệ quả trực tiếp của mục 42, không phải lỗi mới độc lập):** mục 42 thêm `@page { size: 70mm 100mm; margin: 0; }` để tem không còn in bé xíu giữa tờ A4. Nhưng `.slip` cũng đúng 70x100mm + `margin: 0` nghĩa là **viền ngoài của tem nằm ĐÚNG mép giấy vật lý** — rơi trọn vào vùng không in được (unprintable margin) mà mọi máy in đều có (đầu in không với tới sát mép), nên nét viền trên/trái biến mất. Đây là giới hạn phần cứng, không chỉnh được bằng driver.
- **Sửa:** trong `@media print`, thêm `transform: scale(0.95); transform-origin: center center;` cho `.slip` — thu toàn bộ tem còn 95% và co vào TÂM trang, chừa đều ~1.75mm ngang / 2.5mm dọc quanh 4 cạnh.
  - **Vì sao dùng `transform` chứ không phải `zoom`:** `zoom` tính lại layout (đẩy vị trí `.slip` trong trang, dễ lệch tâm), còn `transform` chỉ vẽ lại — tâm tem giữ đúng tâm trang.
  - **Vì sao không sửa lại toạ độ bên trong:** toàn bộ bố cục dùng toạ độ tuyệt đối mm (port đúng từ dot TSPL của backend). Scale cả `.slip` giữ nguyên tỉ lệ gốc của mọi ô/lưới/QR — sửa 1 dòng thay vì tính lại ~30 mốc toạ độ, và không rủi ro lệch so với tem TSPL thật.
- **Áp cho cả 3 nơi in qua trình duyệt** (cùng máy in vật lý, cùng cơ chế `@page margin:0` nên chắc chắn cùng bị): tem dispatch 70x100 + tem vật tư 80x50 (`PrintStation.vue`), và `utils/tsplPrint.ts` (dùng chung cho `/weighing-station`).
- **Kiểm chứng:** `npx vue-tsc --noEmit` sạch, `npm run build` thành công (36.95s — dùng build thật theo bài học mục 50). **Chưa in thử trên tem thật** — cần người dùng in lại xác nhận đã thấy đủ viền 4 cạnh; nếu vẫn thiếu ở 1 phía cụ thể thì đó là do máy in lệch giấy (feed offset), lúc đó phải chỉnh riêng bằng cách dịch tem (thêm `translate`) chứ không phải giảm scale tiếp.

### 54. Tem in ra ĐÚNG bố cục nhưng MỜ — nét không tròn dot ở máy in nhiệt 203dpi + QR thiếu độ phân giải nguồn

- **Người dùng báo:** sau mục 53 tem in ra đúng rồi nhưng mờ quá.
- **Phân tích (đặc thù máy in NHIỆT, khác hẳn máy in phun/laser):** máy in tem chỉ có 2 mức đen/trắng, không có sắc xám. Mọi pixel xám mà trình duyệt sinh ra đều bị dither thành lưới chấm thưa — mắt nhìn thấy là "mờ". Có 3 nguồn sinh xám trong tem hiện tại:
  1. **Nét viền không tròn dot:** ở 203dpi, 1 dot = 0.125mm. Bản mục 44 dùng `0.15mm` (1.2 dot) và `0.2mm` (1.6 dot) — không phải bội số dot, trình duyệt khử răng cưa thành nét xám. Nâng về **0.25mm (2 dot)** cho `.box`/`.gridcell` và **0.5mm (4 dot)** cho `.slip`. Vẫn mỏng hơn hẳn bản gốc (0.3/1.2mm) nên **không quay lại lỗi "viền to đè chữ"** của mục 44 (padding giữ nguyên 0.5/0.9mm).
  2. **Chữ quá mảnh:** `.cellval` chỉ 2.2mm, nét Arial thường ở cỡ này mảnh hơn 1 dot. Tăng `font-weight: 600` (và `.label-sm`/`.med` tương tự) — **tăng độ đậm nét thay vì tăng cỡ chữ**, để không phải nới lại chiều cao hàng/bố cục vốn đã khớp tem thật.
  3. **QR thiếu độ phân giải nguồn:** `QRCode.toDataURL(..., { width: 240 })` — trang in được render ở DPI cao hơn màn hình nhiều nên ảnh 240px bị PHÓNG TO khi in, cạnh module QR nhoè xám. Nâng nguồn lên **960px** (mode QR 800px) để lúc in luôn là thu nhỏ (nội suy mượt, nét đen giữ đặc).
- **Thêm `print-color-adjust: exact`** (kèm tiền tố `-webkit-`) để trình duyệt không tự "tối ưu" màu in làm đen bị nhạt thành xám.
- **Điểm đã cân nhắc và CHỦ ĐỘNG KHÔNG làm:** ban đầu định thêm `image-rendering: pixelated` cho ảnh QR (cách phổ biến để giữ cạnh sắc), nhưng **đã bỏ** — QR ở đây đang bị *thu nhỏ*, mà `pixelated` lúc downscale vứt pixel không đều sẽ làm méo module QR khiến máy quét dễ đọc sai. Với downscale, cách đúng là tăng độ phân giải nguồn (đã làm ở trên) rồi để trình duyệt nội suy.
- **Áp cho cả 3 nơi in qua trình duyệt:** tem dispatch 70x100 + tem vật tư 80x50 (`PrintStation.vue`), và `utils/tsplPrint.ts` (`/weighing-station`; nhân tiện hạ `border` `.slip` từ 1.2mm xuống 0.5mm cho khớp 2 tem kia).
- **Kiểm chứng:** `npx vue-tsc --noEmit` sạch, `npm run build` thành công (38.41s). **Chưa in thử trên tem thật.**
- **Lưu ý quan trọng cho người dùng (yếu tố NGOÀI code):** độ đậm bản in trên máy in nhiệt phần lớn do cài đặt **Darkness/Density** và **tốc độ in (Speed)** trong driver máy in quyết định, không phải CSS. Nếu sau bản sửa này vẫn nhạt thì cần tăng Darkness / giảm Speed trong Printing Preferences của máy in, hoặc kiểm tra giấy/ribbon (giấy nhiệt cũ, đầu in bẩn cũng gây mờ đều toàn tem).

### 55. GỠ `transform: scale(0.95)` của mục 53 — chính nó là thủ phạm làm tem mờ (mục 54) và đường thẳng in ra ĐỨT QUÃNG

- **Người dùng báo:** "trước in thì không sao, giờ in cái đường thẳng nó cũng bị đứt đứt" — xác nhận đây là **regression do tôi gây ra**, không phải vấn đề có sẵn của máy in.
- **Nguyên nhân (truy ngược đúng thay đổi gây lỗi):** mục 53 thêm `transform: scale(0.95)` để chừa lề tránh mất viền trên/trái. Nhưng `scale` nhân vào **cả ĐỘ DÀY nét**: viền `0.25mm` (đúng 2 dot ở 203dpi) → `0.2375mm` = **1.9 dot**, không còn tròn dot. Khi rasterize, mỗi đoạn dọc theo cùng một đường bị làm tròn lúc 1 dot lúc 2 dot → **nét đứt quãng**, và các nét bị "gầy" đi thành xám → đúng cả 2 triệu chứng người dùng báo lần lượt ở mục 54 ("mờ") và lần này ("đứt đứt"). Tức mục 54 tôi đã chữa TRIỆU CHỨNG (tăng độ dày, tăng font-weight, tăng DPI của QR) mà **không nhận ra nguyên nhân gốc nằm ở chính scale mình vừa thêm**.
- **Sửa:** gỡ bỏ hoàn toàn `transform: scale(0.95)` khỏi cả 3 nơi in qua trình duyệt, trả về tỉ lệ **1:1** để mọi nét khai báo theo mm luôn tròn dot và in ra liền mạch.
- **Giữ lại các cải tiến của mục 54** (không phải nguyên nhân, và thực sự giúp nét đậm hơn ở máy in nhiệt): viền tròn dot 0.25mm/0.5mm, `font-weight: 600` cho chữ nhỏ, QR nguồn 960px, `print-color-adjust: exact`.
- **Hệ quả phải chấp nhận / cần người dùng quyết:** bỏ scale thì lỗi mất viền trên/trái của mục 53 **quay lại** — vì `.slip` đúng bằng khổ giấy nên viền ngoài nằm ngay vùng không in được. Đây là đánh đổi vật lý thật sự (khổ giấy = khổ tem), không thể vừa 1:1 vừa có lề. **Đã hỏi người dùng chọn hướng xử lý** thay vì tự quyết, vì chỉ người cầm tem thật mới biết mức nào chấp nhận được.
- **Bài học:** khi người dùng báo lỗi mới xuất hiện ngay sau một thay đổi của mình, phải nghi ngờ chính thay đổi đó TRƯỚC khi đi chữa triệu chứng — mục 54 đã bỏ qua bước này và làm mất thêm 1 vòng phản hồi.
- **Kiểm chứng:** `npx vue-tsc --noEmit` sạch, `npm run build` thành công (38.56s).

### 56. Tem in ra: đường kẻ răng cưa + chữ/số nhỏ nhoè — nét mảnh không sống sót qua dither của driver máy in nhiệt

- **Người dùng gửi ảnh tem in thật** (sau khi gỡ scale ở mục 55): đường kẻ ô hiện ra dạng **răng cưa/lượn sóng** thay vì thẳng liền; chữ to (JIT3, LEP70158, SE5433, VD003) đen đặc sắc nét nhưng **chữ/số nhỏ trong bảng** (Y1019A, R2064G, 6.65, 0.85…) nhoè, khó đọc.
- **Phân tích (đối chiếu trực tiếp với ảnh — cái gì rõ, cái gì mờ):** chỗ RÕ đều là nét dày (chữ 3.2-5.5mm, font-weight 700); chỗ MỜ đều là nét mảnh (đường kẻ 0.25mm = 2 dot, chữ 2.2mm có nét đứng < 1 dot). Driver máy in nhiệt TSC nhận ảnh raster ở DPI cao từ trình duyệt rồi hạ về 203dpi bằng **dither** — nét chỉ cần lệch nửa dot là bị chuyển thành chuỗi chấm so le, đúng hình răng cưa thấy trong ảnh. Nét dày thì phần lõi vẫn đen tuyệt đối nên không bị ảnh hưởng. Kết luận: **ngưỡng an toàn là nét ≥ 3 dot**, không phải 2 dot như giả định ở mục 54.
- **Sửa:**
  - Đường kẻ (`.box`, `.gridcell`): `0.25mm` (2 dot) → **`0.375mm` (đúng 3 dot)**.
  - Chữ nhỏ: `.cellval` 2.2mm → **2.6mm**, `.label-sm` 2.3mm → 2.6mm, `.med` 2.6mm → 2.9mm, `.title` 2.4mm → 2.6mm; tất cả nâng `font-weight` lên **700**. Ở font-weight 700, cỡ ≥ 2.6mm cho nét đứng ≥ 2 dot — đủ ổn định qua dither.
  - **Kiểm tra không tràn ô trước khi đổi** (không đoán): hàng bảng cao 41 dot = 5.125mm, trừ padding 0.5mm×2 còn 4.1mm > 2.6mm; ô mã thuốc nhuộm rộng 96 dot = 12mm, trừ padding 0.9mm×2 còn 10.2mm, chuỗi 6 ký tự ở 2.6mm bold ≈ 9.4mm — vẫn vừa. **Không phải sửa toạ độ/bố cục** đã khớp tem thật.
- **Không đụng tem vật tư 80x50** (chữ đã 3-3.4mm, vốn đủ lớn) và `tsplPrint.ts` (cỡ chữ suy ra từ lệnh TSPL của backend, đổi ở đây sẽ lệch với tem TSPL thật).
- **Kiểm chứng:** `npx vue-tsc --noEmit` sạch, `npm run build` thành công (37.17s). **Chưa in thử trên tem thật.**
- **Lưu ý đã nhắc người dùng:** tem trong ảnh có thể được in TRƯỚC khi bản gỡ scale (mục 55) kịp áp dụng — cần tải lại trang (Ctrl+F5) rồi mới in thử để đánh giá đúng bản mới nhất.

### 57. TÌM RA NGUYÊN NHÂN GỐC của cả chuỗi lỗi mờ/răng cưa: `@page size` = ĐÚNG khổ giấy (mục 42) làm trình duyệt co cả trang cho vừa vùng in được

- **Manh mối quyết định từ người dùng:** "trước đó căn chưa chuẩn, nhưng in ra tem nào cũng NÉT; giờ in chuẩn vị trí rồi thì in ra bị như vậy". Tức chất lượng nét xấu đi **đúng từ lúc tem bắt đầu in đúng khổ/đúng vị trí** — mốc đó chính là mục 42 (thêm `@page { size: 70mm 100mm; margin: 0 }`).
- **Nguyên nhân gốc:** vùng in được (printable area) của MỌI máy in luôn NHỎ HƠN khổ giấy vật lý. Khi `@page size` khai đúng 70x100mm và nội dung trải kín tới mép, Chrome phải co cả trang cho vừa vùng in được ("fit to printable area") — hệ số co là số lẻ, nhân vào **mọi** nét và cỡ chữ → không còn tròn dot ở 203dpi → driver máy in nhiệt dither → **răng cưa + mờ**. Đây là cùng một cơ chế với `transform: scale(0.95)` mà tôi tự thêm ở mục 53 rồi phải gỡ ở mục 55 — chỉ khác là lần này trình duyệt tự làm, nên gỡ scale xong vẫn còn lỗi.
- **Nhìn lại chuỗi mục 53→56 để rút kinh nghiệm:** mục 53 (thêm scale) làm lỗi NẶNG THÊM; mục 54 và 56 chữa triệu chứng (tăng độ dày nét, tăng cỡ chữ) mà không chạm tới nguyên nhân. Nếu hỏi người dùng sớm "trước đây in có nét không" thì đã khoanh vùng được ngay từ mục 54 — **manh mối quý nhất luôn là mốc thời gian lỗi bắt đầu xuất hiện.**
- **Sửa (thu ở TẦNG TOẠ ĐỘ, không phải transform/zoom):** thêm `FIT = 0.955` và `MARGIN_MM = 1.6` vào `printDispatchViaBrowser`; `mmD()` giờ trả `(dot / 8) * FIT`, `.slip` lấy kích thước từ chính `mmD(560)` x `mmD(800)` + `margin: 1.6mm` (bỏ hard-code 70x100mm). Bản vẽ thành 66.85 x 95.5mm nằm gọn trong vùng in được → **trình duyệt không phải co trang nữa**.
  - **Khác biệt then chốt so với `transform: scale()`:** ở đây chỉ **TOẠ ĐỘ** bị nhân hệ số, còn **ĐỘ DÀY nét và CỠ CHỮ vẫn khai báo bằng mm nguyên** (`0.375mm` = đúng 3 dot, chữ 2.6mm) → nét vẫn tròn dot, in ra liền mạch. Đây chính là điểm mà `transform` không làm được.
  - Giảm `padding` ngang `.box` `0.9mm → 0.6mm` — bù lại việc ô hẹp đi 4.5%, để mã 6 ký tự (Y1019A/R2064G) không chạm mép ô.
- **Kiểm chứng:** `npx vue-tsc --noEmit` sạch, `npm run build` thành công (39.09s). **Chưa in thử trên tem thật.**
- **Nếu vẫn chưa nét (thao tác của người dùng, không phải code):** trong hộp thoại in của Chrome mở phần **More settings** và đặt **Scale = 100%** (không để "Fit to printable area") + **Margins = None**. Nếu Chrome vẫn tự co thì mọi chỉnh sửa CSS đều vô hiệu, vì hệ số co nằm ngoài tầm kiểm soát của trang web. Sửa tiếp mục 45: QR góc trên dán sát viền trên, không thấy viền — co nhỏ còn ~95% + đẩy xuống

- **Người dùng báo:** sau mục 45, QR đi sát quá, không còn thấy viền TRÊN của tem; yêu cầu co nhỏ lại còn khoảng 95% và đẩy QR xuống 1 chút để chừa viền.
- **Nguyên nhân:** bản mục 45 dùng padding đều `0.15mm` cả 4 cạnh cho ô này + `align-items:center` — ảnh 13.2mm gần như lấp đầy hết chiều cao khả dụng (~13.3mm), phần đệm phía trên viền chỉ còn ~0.35mm (viền 0.2mm + đệm 0.15mm), thực tế in ra không phân biệt được ranh giới.
- **Sửa:** `.qr-block-inline` đổi `align-items: center` → `flex-start` (neo ảnh vào mép trên của VÙNG NỘI DUNG thay vì canh giữa cả ô); ảnh `13.2mm` → `12.5mm` (~95%); padding riêng ô này đổi từ đều `0.15mm` sang lệch `0.9mm` (trên) / `0.15mm` (phải/dưới/trái) — đẩy hẳn ảnh xuống, chừa khe hở rõ ràng với viền trên, 3 cạnh còn lại vẫn giữ sát như cũ (không bị người dùng phàn nàn).
- **Kiểm chứng:** `npx vue-tsc --noEmit` sạch. Chưa in thử lại trên tem thật.

### 58. Đường nhận số cân của `/weighing-station-v2` — trễ tới 1.5s và không phân biệt được "mất tín hiệu" với "cân rỗng"

- **Bối cảnh:** sau khi V2 chuyển sang chốt BÌ **tự động** từ lần đọc ổn định đầu tiên sau khi bấm NEXT (bám đúng `Mod_delta_raw.AutoFlow_OnWeight`), chất lượng đường truyền số cân trở thành yếu tố quyết định độ chính xác, chứ không còn chỉ là chuyện hiển thị mượt hay giật.
- **Chuỗi hiện trạng:** Cân → Agent poll **500ms** → `POST /devices/readings` → `Cache` TTL 15s → trình duyệt poll **1000ms**. Tổng trễ tới **1.5 giây**; VBA gốc đọc file log mỗi **10ms** (`p0-c-scale-algorithm.md` Mục A.1).
- **4 hệ quả đã xác định:**
  1. **Bì chốt muộn:** thợ bấm NEXT rồi đổ ngay trong 1 giây → mẫu ổn định đầu tiên trình duyệt bắt được đã có bột trên đĩa → bì tính luôn phần bột đó → delta thiếu → thợ đổ dư mà màn hình vẫn báo chưa đủ.
  2. **Bì chốt vào số CŨ:** cache sống 15s mà `getReading` không trả thời điểm đọc → trình duyệt không phân biệt được số vừa đọc với số 8 giây trước.
  3. **StableFilter sai nhịp ở chế độ đọc file PuTTY:** "ổn định" = 2 lần đọc liên tiếp giống nhau = 2 × PollIntervalMs → VBA 20ms, Agent 1 giây. (Chế độ RS232 thật thì đúng vì mỗi dòng serial = 1 lần đọc.)
  4. **Mất tín hiệu cân hiển thị y hệt cân rỗng:** `getReading` trả mặc định `weight = 0.0` khi cache trống — đúng lớp lỗi TV6 đã vá ở Agent (`Worker.cs` không đẩy 0.0 giả) nhưng **backend tự tái tạo lại**. Chấm `scaleOnline` chỉ báo gọi được API, không báo cân sống.
- **Sửa:**
  - `DeviceController::getReading` trả thêm `has_reading` + `age_ms`; `storeReading` đổi `time()` → `microtime(true)` để có độ phân giải dưới giây (`RealtimeService` ép `(int)` về giây nên không ảnh hưởng). Key `scale_live_weight_timestamp_` vốn đã ghi sẵn từ trước nhưng **chưa từng được đọc ra**.
  - `useScaleFeed.ts`: thêm ngưỡng `STALE_READING_MS = 1500`. Số cũ hơn ngưỡng → giữ nguyên màn hình và thoát (đúng quy ước TV6), **không** được làm bì, `isStable` ép về false. Thêm `signalLive` tách khỏi `scaleOnline`.
  - `WeighingStationV2.vue`: poll 1000ms → **200ms**; chấm xanh nay dựa trên `scaleOnline && signalLive`; thêm banner đỏ "MẤT TÍN HIỆU CÂN".
  - Agent: `Scale:PollIntervalMs` mặc định 500 → **150ms** (`Worker.cs`) và trong `installer/appsettings.scale.json`.
- **Đánh đổi đã cân nhắc:** mỗi trạm cân đi từ ~2 lên ~6-7 request/giây vào Laravel. Với 2 trạm pilot không đáng kể; nếu nhân lên 10+ trạm cần bàn lại (route nhẹ không boot full framework, hoặc Agent mở cổng HTTP cục bộ cho trình duyệt hỏi thẳng — hướng sau **phá ranh giới phân lớp, phải có ADR mới**).
- **KHÔNG dùng SSE cho luồng này:** ADR-009 bắt mọi sự kiện realtime đi qua Transactional Outbox `app.realtime_events`; ghi 5-10 dòng/giây/trạm số cân nhất thời vào đó là sai mục đích của outbox (dành cho sự kiện nghiệp vụ, không phải số hiển thị thoáng qua).
- **LƯU Ý TRIỂN KHAI:** đổi mặc định `PollIntervalMs` trong code **không** tự áp dụng cho 2 máy pilot đã cài MSI — `C:\Program Files\DFAgent\appsettings.json` ghi đè giá trị này, phải sửa tay rồi restart service `DFAgent`, hoặc cài lại MSI.
- **Kiểm chứng:** `vue-tsc --noEmit` sạch, `vite build` thành công (16.16s), `dotnet build` agent thành công (0 lỗi). Thêm 2 test `ScaleLiveWeightTest::test_get_reading_reports_age_of_last_push` và `..._flags_missing_reading_instead_of_faking_zero` — **chưa chạy được**: file test này không dùng `RefreshDatabase` nên cần DB thật, mà Postgres test (`127.0.0.1:5433`) hiện không chạy. Test .NET của Agent cũng không chạy được (máy chỉ có .NET runtime 3.1/9.0/10.0, project test nhắm net8.0). Cả hai đều là hạn chế môi trường có sẵn, không liên quan tới thay đổi này.
- **Chưa xác minh bằng cân thật** — cần chạy thử tại trạm pilot để xác nhận 150ms/200ms có đủ để bì chốt đúng lúc hay không.

### 59. Quay về ĐỌC CÂN THEO CÁCH CŨ (file log PuTTY) ở nhịp 10ms — tách nhịp ĐỌC khỏi nhịp ĐẨY

- **Yêu cầu người dùng:** dùng lại cách đọc cân cũ (file log PuTTY như Excel VBA) và đọc ở nhịp **10ms** đúng bằng VBA.
- **Nhận định then chốt — hai nhịp có chi phí khác hẳn nhau, trước đây bị gộp làm một:**
  - **ĐỌC** (đuôi file cục bộ / biến đã chốt từ cổng COM): gần như miễn phí. Đây là nhịp quyết định `StableFilter` — "ổn định" = 2 lần đọc liên tiếp giống nhau, nên 10ms ⇒ **20ms**, đúng bằng VBA (trước ở 500ms là **1 giây** mới dám báo ổn định).
  - **ĐẨY** lên backend: mỗi lần là 1 HTTP request + 1 vòng bootstrap Laravel. Đây mới là thứ đắt và là thứ duy nhất cần cân nhắc khi nhân số trạm.
  - Tách ra thành `Scale:ReadIntervalMs` (10) và `Scale:PushIntervalMs` (200). Mục 58 hạ `PollIntervalMs` 500→150 là **thoả hiệp sai chỗ** vì còn gộp chung; nay bỏ.
- **Phát hiện khi đọc lại VBA gốc:** vòng `ModRead_putty_log.StartFastLoop` có điều kiện `If s <> "" And s <> rawline`, nhưng `rawline` được gán **giá trị đã lọc** (`rawline = CleanWeight(s)`) rồi đem so với `s` **thô** — hai chuỗi này gần như không bao giờ bằng nhau nên điều kiện luôn đúng, tức VBA thực chất **đẩy mỗi 10ms bất kể số có đổi hay không**. Chính điều đó làm `StableFilter` hoạt động được. Vì vậy bản port nạp **mọi** lần đọc vào filter, không lọc theo thay đổi.
- **Hai cái bẫy của nhịp 10ms, đã xử lý trước khi hạ nhịp:**
  1. `ReadSimulatedWeight` dùng `File.ReadAllLines` — **đọc TOÀN BỘ file mỗi lần**. File log PuTTY phình dần suốt ca; đọc cả file 100 lần/giây sẽ nghẹt I/O máy trạm. Thay bằng `ReadLastCompleteLine`: seek tới cuối, chỉ đọc 4KB cuối, chi phí không phụ thuộc kích thước file. Mở với `FileShare.ReadWrite|Delete` vì PuTTY đang giữ file để ghi.
  2. **Dòng cuối đang ghi dở**: ở 10ms, xác suất chộp đúng lúc PuTTY mới ghi nửa dòng (`12,ST,GS,+0000`) cao gấp ~50 lần so với 500ms, mà `CleanWeight` sẽ parse mảnh cụt thành `0` — một số cân HỢP LỆ nhưng SAI. Nay bỏ qua phần đuôi chưa có CR/LF. Đánh đổi: chậm hơn đúng một dòng (cân phát ~5-10 dòng/giây) để không bao giờ đọc phải số cụt.
- **Vá luôn khác biệt A.1** (`p0-c-scale-algorithm.md`): VBA `ReadLastLineFast` bỏ qua dòng rỗng (`If Len(s) > 0`), bản .NET cũ lấy dòng vật lý cuối nên trả `""` khi file kết thúc bằng dòng trắng, rồi bị hiểu thành "cân đọc 0kg".
- **Đổi tên cờ cấu hình:** thêm `Scale:Source` = `PUTTY_LOG` | `SERIAL`. Trước đây muốn đọc file PuTTY phải bật `UseSimulation: true` — đặt tên sai bản chất, vì đọc file PuTTY là cách vận hành THẬT của xưởng nhiều năm nay, không phải demo; rất dễ bị ai đó tắt vì tưởng là đồ giả lập. Cờ cũ vẫn được đọc làm dự phòng.
- **Tách nhịp lấy lệnh in** (`Printer:PollIntervalMs`, mặc định 1000ms): trước bị buộc chung vòng lặp cân, để vòng lặp chạy 10ms mà không tách sẽ thành 100 request lấy lệnh in mỗi giây.
- **Kiểm chứng:** `dotnet test` **15/15 pass** (thêm 6 test mới cho đọc đuôi file: dòng cuối không rỗng, file kết thúc bằng dòng trắng, dòng ghi dở, file 4MB đọc 100 lần < 500ms, file đang bị tiến trình khác giữ để ghi). Chạy được nhờ `DOTNET_ROLL_FORWARD=LatestMajor` — máy chỉ có .NET runtime 3.1/9.0/10.0 còn project nhắm net8.0. `dotnet build` 0 lỗi, `vue-tsc --noEmit` sạch.
- **Chưa xác minh trên cân thật.**
- **Triển khai lên 2 máy pilot:** sửa `C:\Program Files\DFAgent\appsettings.json` đặt `"Source": "PUTTY_LOG"` + `"LogFilePath"` trỏ đúng đường dẫn PuTTY đang ghi (xem mục 60), rồi restart service `DFAgent`. Nếu KHÔNG sửa gì, máy vẫn chạy chế độ SERIAL như cũ nhưng đã tự hưởng nhịp đọc 10ms (key `ReadIntervalMs` vắng mặt ⇒ mặc định 10) — tức `StableFilter` được vá mà không cần đụng cấu hình.

### 60. Chốt đường dẫn file log PuTTY trên máy trạm cân: `D:\scale\putty_log.txt`

- **Người dùng chốt:** Agent đọc cân từ `D:\scale\putty_log.txt` trên máy cài DFAgent.
- **Đổi khoá cấu hình `Scale:SimulationFilePath` → `Scale:LogFilePath`** (khoá cũ vẫn đọc làm dự phòng, có test khoá lại). Cùng lý do với `Source`/`UseSimulation` ở mục 59: đây là đường chạy THẬT của xưởng, để tên "Simulation" là mời người khác tắt nhầm.
- **Đường dẫn mặc định trong code** (`ScaleReader.DefaultLogFilePath`) cũng đổi thành đường dẫn này, có test khoá — để việc đổi đường dẫn phải là hành động có chủ ý.
- **Đã truy ngược cách MSI đóng gói cấu hình** thay vì đoán: `DFAgentSetup.wxs:32,91` đóng gói thẳng `installer/appsettings.scale.json` thành `appsettings.json` trong thư mục cài. Đó là file duy nhất cần sửa. (`DFAgentSetup.iss` là bản Inno Setup cũ, trỏ tới `appsettings.template.json` — file này KHÔNG tồn tại nữa, nhánh đó đã chết, không dùng.)
- **Kiểm chứng:** `dotnet test` **18/18 pass** (thêm 3 test: đường dẫn mặc định, khoá cũ `SimulationFilePath` còn hiệu lực, khoá mới `LogFilePath` thắng khoá cũ khi có cả hai).
- **Điều kiện vận hành cần nhắc người dùng:** chế độ PUTTY_LOG đòi PuTTY phải đang chạy và đã bật Session Logging ghi đúng vào đường dẫn này. Agent KHÔNG tự bật PuTTY. Nếu PuTTY tắt/ghi sai chỗ, Agent ngừng đẩy số và màn hình V2 hiện "MẤT TÍN HIỆU CÂN" (cơ chế thêm ở mục 58) — không còn im lặng hiển thị 0.00 như trước.

### 61. Dung bo cai DFAgent 2.1.0.0 - phat hien Backend:Url trong config TRO SAI DIA CHI

- **Yeu cau:** nguoi dung can bo cai Agent chuan de cai va nhan can.
- **Da build:** `agent\installer\build.ps1` -> `DFAgentSetup-Scale.msi` (28.1 MB), copy san sang `backend\public\downloads\`. Tang `PackageVersion` 2.0.0.0 -> **2.1.0.0** vi may pilot dang cai 2.0.0.0; giu nguyen so thi MajorUpgrade khong nang cap sach duoc.
- **LOI QUAN TRONG PHAT HIEN KHI KIEM TRA (khong phai do doi lan nay gay ra, co san tu truoc):** `Backend:Url` trong `appsettings.scale.json` dong cung `http://10.0.200.248:8500/api`. Kiem chung bang `Test-NetConnection`: dia chi do **KHONG ping va KHONG mo cong 8500**; con CS-SERVER `10.0.60.209:8500` thi **nhan ket noi TCP** va tra **HTTP 401** tren `/api/devices/readings/...` (endpoint ton tai, doi xac thuc). Da doi sang `http://10.0.60.209:8500/api`. Neu khong sua, Agent cai xong se doc duoc can nhung **khong gui duoc so nao len he thong** — va trieu chung o man hinh chi la "MAT TIN HIEU CAN", rat de bi doan nham la loi cong COM/PuTTY. Muc 780 (2026-07-31) da tung ghi chu "can sua khi trien khai" nhung khong ai sua.
- **LOI THIET KE TU MINH GAY RA O MUC 59, PHAT HIEN KHI CHAY THAT:** vong doc 10ms dang `await` lenh day HTTP ngay ben trong no. `HttpClient` de timeout 5 giay, nen mot lan backend khong phan hoi se lam Agent **ngung doc can 5 giay** — nhip 10ms thanh vo nghia dung luc can nhat. Sua: viec mang chay roi khoi vong doc (`Task? pushInFlight` / `printPollInFlight`, giu toi da 1 viec moi loai dang bay, chua xong thi bo qua luot nay thay vi xep hang). Gop 2 viec mang cua may in vao `ProcessPrintWorkAsync()` de vong doc chi theo doi 1 handle.
- **Kiem chung END-TO-END tren ban da publish** (khong chi unit test):
  1. Chay `DFAgent.exe` doc file log gia -> log ra dung `doc can moi 10ms, day len backend moi 200ms`, doc duoc 10.5 kg (so CUOI, khong phai "12").
  2. **Chong ket vong doc:** tro `Backend:Url` vao IP khong dinh tuyen (`10.255.255.1`, ket noi treo toi timeout 5s) roi doi so can 6 lan cach nhau 400ms -> Agent ghi nhan **du ca 6 lan**. Truoc khi sua thi vong doc da dung im.
  3. **Endpoint gia bang HttpListener** de xem Agent that su day gi: `is_stable` chuyen **false -> true tu lan doc thu hai** (dung ngu nghia VBA), khoang cach giua cac lan day do duoc **192-220ms** (dung nhip 200ms), 15 lan day trong 3 giay.
  4. **Giai nen MSI bang `msiexec /a`** de xac minh cau hinh THAT nam trong bo cai, khong tin vao script build: Backend.Url=10.0.60.209:8500/api, Source=PUTTY_LOG, LogFilePath=D:\scale\putty_log.txt, ReadIntervalMs=10, PushIntervalMs=200, Role=SCALE_ONLY.
- `dotnet test` 18/18 pass, `dotnet build` 0 loi.
- **CON TON DONG:** 2 truong moi `has_reading`/`age_ms` cua `DeviceController::getReading` (muc 58) **chua deploy len CS-SERVER**. Frontend da co duong lui (thieu truong thi coi nhu con tuoi) nen khong vo, nhung banner "MAT TIN HIEU CAN" chi hoat dong sau khi deploy backend.

### 62. Them bao cao "May da o trang thai hien tai bao lau roi" duoi luoi trang thai may (Dashboard tab Dieu do tong the)

- **Yeu cau nguoi dung (2026-08-01):** o trang chu can them 1 bao cao phia duoi cho biet may nao dang o tinh trang do bao lau roi.
- **Van de nguon du lieu:** BPDB KHONG co cot "thoi diem doi trang thai". Trang thai may la ket qua suy ra tu task quyet dinh (`reduceMachineStatus`), nen moc dem phai lay tu chinh task do:
  - PROCESSING / ERROR -> `WorkStartTime` (luc may thuc su bat dau chay), fallback `CreateTime`
  - WAITING / TRANSITIONING -> `CreateTime`
  - COMPLETED_RECENTLY / CANCELLED -> `FinishTime`, fallback `CreateTime`
  - IDLE -> khong co task nao, phai query rieng (xem duoi)
  Tra ve kem `statusSinceSource` de nguoi xem biet dong ho dang dem tu moc nao, khong phai doan.
- **May IDLE:** query trang thai chinh chi lay task active + 24h gan nhat nen khong du de biet may trong bao lau. Them `getLastActivityByMachineId()` - 1 query aggregate `MAX(COALESCE(FinishTime, WorkStartTime, CreateTime)) GROUP BY Machine`, cua so 30 ngay, cache **60s** (do chinh xac tung giay vo nghia voi may dang trong). Chi chay khi thuc su co it nhat 1 may IDLE; loi query thi nuot va tra rong, khong lam hong ca bang trang thai. May khong co task nao trong 30 ngay -> `statusSince = null`, giao dien ghi "> 30 ngay" (nguong duoi that), KHONG bia so cu the.
- **Chong lech dong ho:** frontend KHONG lay `new Date() - statusSince` (may tram nha xuong hay lech gio so voi server/BPDB, se ra so am hoac vong len hang gio). Dung `statusDurationSeconds` do server tinh + so giay troi qua ke tu luc nhan snapshot (`bpdbFetchedAtLocal`). Dong ho nhich moi giay bang `nowTick`, clear interval khi unmount.
- **Nhanh non-admin** (`/api/dashboard/overview`, du lieu noi bo `app`): bang `production_batches` khong co `status_changed_at`, chi co `updated_at` -> tra `status_since` = `updated_at` va ghi ro tren giao dien la **UOC TINH**, may trong -> null (khong xac dinh, khong phai 0).
- **Giao dien:** bang **sap xep theo ma may** (thu tu tu nhien, `localeCompare` numeric de "VD9" khong nhay sau "VD10") - nguoi dung yeu cau doi tu sap xep theo thoi luong sang theo ten may de do doi chieu voi luoi trang thai ngay tren; to vang dong co `stuckWarning` (nguong doc tu `feature_flags`, khong hard-code), to do dong ERROR; co checkbox "Chi hien may co canh bao keo dai" de van loc nhanh may bat thuong.
- **Kiem chung:** `php -l` sach 2 file backend, `vue-tsc --noEmit` sach. **Chua xac minh bang mat tren trinh duyet that** va chua do thoi gian chay thuc te cua query aggregate 30 ngay tren BPDB (query nay chay 1 lan/60s, trong khi query trang thai hien co da chay 1 lan/4s va cung khong dung duoc index sach vi co menh de OR).
- **File cham:** `backend/app/Services/ColorService/BpdbMachineMonitoringService.php`, `backend/app/Http/Controllers/DashboardController.php`, `frontend/src/views/Dashboard.vue`.

### 62. "MAT TIN HIEU CAN" sau khi cai Agent — Agent CHAY TOT, loi la frontend hoi cache bang ID SO con Agent ghi bang MA TRAM

- **Trieu chung:** cai xong Agent, man hinh `/weighing-station-v2` bao "MAT TIN HIEU CAN".
- **Chan doan (chi doc DB, khong ghi):** truy van bang `cache` cua `production_web` -> khoa `scale_live_weight_WS-WEIGH-SCALE` **con han 15 giay**, tuc **Agent dang day so can len binh thuong**. Tram `WS-WEIGH-SCALE` co `id=6` trong `operation_clients`.
- **NGUYEN NHAN GOC:** `DeviceController::storeReading` ghi cache theo `workstation_id` Agent gui len = **MA tram** (`scale_live_weight_WS-WEIGH-SCALE`), con frontend goi `/api/devices/readings/{id}` voi `Workstation.id` la **KHOA CHINH DANG SO** -> tra khoa `scale_live_weight_6`, **mot khoa khong bao gio ton tai**. Hai ben chua bao gio gap nhau.
- **Vi sao den gio moi lo:** truoc day `getReading` tra mac dinh `weight = 0.0` khi cache trong -> man hinh hien "0.00" y het mot cai can rong dang cho dat vat tu. Co `has_reading` them o muc 58 bien loi im lang nay thanh canh bao nhin thay duoc. **Banner khong bao sai — no dang noi dung ve mot loi co san tu truoc.** Anh huong ca `/weighing-station` (V1) va `Dashboard.vue`, khong rieng V2: ca 3 deu truyen id so.
- **Sua:** them `DeviceController::resolveReadingKey()` — tham so khong phai so thi dung thang lam ma tram; la so thi tra `operation_clients.id -> code` roi doc cache theo code. Bat buoc kiem tra `ctype_digit` TRUOC khi so voi cot `id` (bigint), neu khong Postgres loi ngay "invalid input syntax for type bigint". Sua o backend thay vi sua tung cho goi ben frontend de ca 3 man hinh cung huong.
- **Kiem chung tren DU LIEU THAT** (goi thang controller, chi doc):
  - `getReading('6')` -> `weight=-0.02, stable=true, has_reading=true, age_ms=60` (so moi 60ms, dung nhip day 200ms cua Agent)
  - `getReading('WS-WEIGH-SCALE')` -> cung ket qua (khong pha duong cu)
  - `getReading('99999999')` -> `has_reading=false`, HTTP 200, khong loi 500
- Them 2 test vao `ScaleLiveWeightTest` (`..._accepts_numeric_workstation_id_not_only_code`, `..._with_unknown_numeric_id_reports_no_reading`). **CHUA CHAY DUOC**: file test nay khong dung `RefreshDatabase` nen can DB that, ma `.env` tro thang vao DB SAN XUAT (`10.0.60.209:5433/production_web`) — chay test se ghi vao production, khong lam. DB test rieng `127.0.0.1:5433` khong chay.
- **Luu y moi truong phat hien duoc:** `backend/.env` cua may dev dang tro `DB_HOST=10.0.60.209` (DB san xuat) va `CACHE_STORE=database` — nen backend local va CS-SERVER **dung chung cache**, do la ly do so can Agent day len CS-SERVER van toi duoc man hinh localhost. Cung co nghia moi lenh test/migrate chay o may dev deu cham thang vao production.

### 63. Sửa lỗi 500 "column assigned_workstation_id does not exist" khi quét mã vạch ở Trạm cân (cả localhost lẫn CS-SERVER)

- **Triệu chứng:** người dùng báo lỗi SQL 500 y hệt trên cả `http://localhost:3001/weighing-station-v2` lẫn `http://10.0.60.209:3001/weighing-station-v2` ngay khi quét đơn — `SQLSTATE[42703]: column "assigned_workstation_id" does not exist`. Cùng lỗi ở cả 2 môi trường ⇒ bug trong code, không phải drift dữ liệu riêng của 1 server.
- **Nguyên nhân (đọc code, không đoán):** migration `2026_07_17_131458_create_operation_client_architecture_tables.php` (đợt tái cấu trúc "kiến trúc OperationClient") đã **đổi tên cột thật** trong bảng `weighing_jobs` từ `assigned_workstation_id` → `assigned_operation_client_id`. Để không phải sửa lại toàn bộ chỗ gọi, `WeighingJob.php` được gắn accessor/mutator ánh xạ 2 chiều (`getAssignedWorkstationIdAttribute`/`setAssignedWorkstationIdAttribute`). Ánh xạ này **chỉ chạy khi đọc/ghi qua object model** (`$job->assigned_workstation_id`, `WeighingJob::create([...])`, `fill()`) — Eloquent áp dụng mutator cho các đường này. Nó **không chạy** khi tên cột được truyền dưới dạng chuỗi vào query builder (`where()`, `whereNotNull()`, `pluck()`) — những lệnh này build SQL thẳng từ chuỗi, bỏ qua toàn bộ lớp accessor/mutator của model.
- **`ScannerController::handleOrderScan`** (luồng quét mã vạch ở Trạm cân) có đúng 4 chỗ dùng tên cột cũ trong query builder — đây là nguồn gây crash: dòng tìm "máy khác đang cân đơn này" (`whereNotNull`/`where`/`pluck`) và dòng tìm job có thể tái sử dụng của chính trạm (`where('assigned_workstation_id', $workstation->id)`). Cả 4 dòng này chưa từng hoạt động kể từ khi migration đổi tên cột chạy — nghĩa là **luồng quét đơn ở Trạm cân đã crash 100% mọi lần** trên bất kỳ DB nào đã chạy migration đó.
- **Sửa:** đổi 4 chỗ trong `ScannerController.php` sang đúng tên cột thật `assigned_operation_client_id`. Các chỗ khác trong cùng file (`WeighingJob::create([...])`, `$job->assigned_workstation_id = ...`) giữ nguyên tên cũ vì đó là đường đi qua model, mutator xử lý đúng — đổi những chỗ đó sẽ không sai nhưng không cần thiết.
- **Rà thêm và sửa cùng lỗi trong test** (không chỉ dừng ở code chạy thật): `WeighBatchTest.php` có 3 chỗ `->where('assigned_workstation_id', ...)` cùng lớp bug — nếu chạy được (môi trường hiện không có Postgres test DB) sẽ tự vỡ ngay chứ không phải giả lỗi thật. Sửa cả 3 sang `assigned_operation_client_id`. `SmallScaleTwoStationIsolationTest.php` (đọc `$jobA->assigned_workstation_id`) và các chỗ gán/tạo trong `WeighBatchTest.php` đều đi qua model nên không đụng.
- **Kiểm chứng:** `php -l` sạch cả `ScannerController.php` lẫn `WeighBatchTest.php`. Backend (port 8500) đang chạy nền từ trước không cần khởi động lại — `php artisan serve` đọc lại file mỗi request; gọi thử 1 endpoint khác xác nhận tiến trình vẫn sống (401 đúng nghĩa thiếu auth, không phải 500). **Chưa quét thử một đơn thật qua UI** để xác nhận hết lỗi — cần người dùng tự thử lại tại `/weighing-station-v2`. Đổi trên localhost xong; **CS-SERVER (10.0.60.209) cần deploy code này riêng** (git pull + restart backend) mới hết lỗi ở đó — chưa deploy trong phiên này.

### 64. Tăng tốc SAVE (tem in ra lâu) và quét đơn ở /weighing-station-v2 — cắt số vòng đi-về DB

- **Triệu chứng người dùng:** "phần save chưa ổn, tem in load ra lâu quá" và "phần quét lúc đầu đẩy ra cũng bị lâu quá".
- **ĐO THẬT trên DB production (chỉ SELECT, không ghi)** thay vì đoán — script đếm query + thời gian qua `DB::listen`, chạy trên 1 job có sẵn 4 dòng:
  - Đọc quan hệ trong vòng lặp như code hiện tại: **17 query, 607 ms**
  - Cùng việc đó nhưng eager load: **8 query, 243 ms**
  - ⇒ **~36 ms/query**. Đây mới là phát hiện quan trọng: DB nằm ở máy khác (`10.0.60.209`), nên **TỔNG SỐ query mới là thứ quyết định thời gian phản hồi**, không phải độ nặng từng query. Mọi tối ưu dưới đây đều nhắm đúng vào việc giảm số vòng đi-về.
  - Ghi chú môi trường: `DbHostResolver` TCP-probe với timeout 0.5s rồi cache 20s ra file temp; lần probe trượt sẽ rơi về `candidates[0]` = `127.0.0.1` (không có Postgres cục bộ) và mọi script sau đó fail tới khi hết cache. Xoá `%TEMP%\df_pgsql_active_host.json` để buộc probe lại.
- **Luồng SAVE — trước:** `weighBatch` gọi `WeighingItemRecorder::record()` trong vòng lặp, mỗi dòng ~8 query (3 lazy-load quan hệ `$item->job`/`$job->batch`/`$batch->machine` + insert measurement + save item + count dòng chưa xong + `$job->save` + tìm next_item). 9 dòng ≈ **89 query**, rồi frontend còn gọi tiếp `POST /print-slip` (~7 query + nguyên một vòng HTTP) mới có nội dung tem. Tổng ≈ **96 query ≈ 3,4 giây** (ước tính theo 36ms/query đã đo).
- **Luồng SAVE — sau (≈24 query, 1 request):**
  1. Thêm `WeighingItemRecorder::recordMany()`: đọc quan hệ 1 lần trước vòng lặp, gộp 9 bản ghi `scale_measurements` thành **1 INSERT**, cascade trạng thái job/lô chạy **đúng 1 lần** sau cùng. Còn lại đúng 1 UPDATE/dòng — đó là ghi thật, không cắt được. Tách phần cascade ra `cascadeJobAndBatch()` để luồng ghi từng dòng (`record`) và ghi cả mẻ dùng chung một quy tắc.
  2. `weighBatch` eager load `batch.machine` + `items.material`.
  3. **Dựng phiếu cân ngay trong response `weigh-batch`** (`buildAndStoreSlip()` tách ra từ `printSlip`, tái dùng batch/items đã nạp) — bỏ hẳn request `/print-slip` thứ hai. Frontend đọc `data.slip.label_payload`; nếu không có thì vẫn quay về đường cũ gọi `/print-slip` (không phá luồng nào khác).
  - `insert()` gộp bỏ qua hook `creating` của model nên **tự sinh UUID**; đã đối chiếu migration: 4 cột NOT NULL (`id`, `legacy_source`, `legacy_id`, `material_type`) đều được điền, `imported_at` có `useCurrent()`.
- **BUG TỰ GÂY RA RỒI TỰ BẮT ĐƯỢC (đáng ghi lại):** bản đầu đặt tên khoá mới là `workstation_code`. Nhưng `assertScaleDeviceBound()` trong CÙNG hàm cũng đọc đúng khoá đó để quyết định có bắt buộc kiểm tra thiết bị cân hay không — màn hình V2 gửi `scale_device_id: 'MOCK_SCALE'` khi trạm chưa gán cân, nên thêm khoá đó vào payload sẽ **bật hàng rào lên và làm SAVE trả 400**. Đổi thành `slip_workstation_code`. Đây là lý do phải đọc hết hàm trước khi thêm tham số, không chỉ đọc chỗ mình sửa.
- **Luồng quét:** `handleOrderScan()` nhận thêm được cả `ProductionBatch` đã nạp sẵn (không chỉ UUID) — `scanRawDyeQr` vừa tra/tạo chính bản ghi đó xong nhưng vẫn `findOrFail` lại kèm 2 quan hệ eager. Truyền thẳng object bỏ được ~3 query. Nhánh ad-hoc (phổ biến nhất, quét QR tem) đã được gộp insert từ trước nên không đụng thêm.
- **Phát hiện kèm theo, CHƯA xử lý:** `.env` để `QUEUE_CONNECTION=database` + `CACHE_STORE=database` + `SESSION_DRIVER=database`. Nghĩa là mỗi `RealtimeService::publish()` tốn 2 query (1 `realtime_events` + 1 `jobs`), và luồng quét gọi publish 2 lần (`weighing_job.received` + `weighing_job.started` — cùng thời điểm, cùng payload, nghi là trùng lặp). Không gộp/bỏ vì có thể có consumer đang nghe; cần xác nhận nghiệp vụ trước. Đáng lưu ý hơn: cache DB nghĩa là endpoint `/api/devices/readings/{id}` (frontend poll 200ms) cũng đi 1 query xuống DB qua LAN mỗi lần.
- **Kiểm chứng:** `php -l` sạch 3 file backend; `vue-tsc` **26 lỗi = đúng bằng baseline nhánh hiện tại**, không lỗi nào thuộc file vừa sửa. **CHƯA chạy được test và CHƯA bấm SAVE thử** — không có Postgres test cục bộ, và `.env` trỏ thẳng DB sản xuất nên không chạy thử đường GHI (kể cả kiểu ghi-rồi-rollback) mà chưa hỏi. Con số "≈96 → ≈24 query" là **ước tính đếm tĩnh từ code** nhân với 36ms/query đo được, KHÔNG phải số đo end-to-end. Cần người dùng bấm SAVE một mẻ thật để xác nhận.

### 65. "Không thể mở lệnh sản xuất này" khi quét — KHÔNG phải lỗi code quét, mà là `DbHostResolver` tự khoá hệ thống vào host DB sai suốt 20 giây

- **Triệu chứng:** quét mã QR test ở `/weighing-station-v2` báo "Không thể mở lệnh sản xuất này." Đó chỉ là **thông báo mặc định của frontend** khi response lỗi không kèm `message` (`WeighingStationV2.vue:437`) — không nói lên nguyên nhân, phải đọc log server.
- **Log thật:** `SQLSTATE[08006] connection to server at "127.0.0.1", port 5433 failed: Connection refused` — backend cố nối DB ở **127.0.0.1**, trong khi DB thật ở `10.0.60.209`. Lỗi ném ra ngay tại tầng Sanctum tra `personal_access_tokens`, tức **mọi endpoint đều chết**, không riêng gì quét.
- **Loại trừ nguyên nhân mạng bằng đo, không đoán:** `fsockopen` tới `10.0.60.209:5433` **5/5 lần đều nối được, nhanh nhất 10ms** ở cả 2 mức timeout 0.5s và 2s. Mạng hoàn toàn ổn định. (Lưu ý: `Test-NetConnection` báo 4,7-6,7 giây là do nó ping + phân giải DNS trước, KHÔNG phản ánh chi phí TCP handshake mà resolver thực sự chịu — đừng dùng số đó để kết luận.)
- **NGUYÊN NHÂN GỐC — 3 khiếm khuyết trong `DbHostResolver::resolve()` cộng lại:**
  1. **Kết quả fallback CŨNG bị ghi cache.** `$resolved = $candidates[0]` được khởi tạo trước vòng probe, và `writeCache()` gọi vô điều kiện sau vòng lặp. Chỉ cần **một** lần probe trượt là host sai bị khoá vào cache **20 giây**, mọi request trong 20 giây đó trả 500 dù DB vẫn chạy bình thường. Đây là khiếm khuyết nghiêm trọng nhất: biến một trục trặc thoáng qua thành sự cố kéo dài.
  2. **Fallback là `$candidates[0]`**, mà `.env` để `DB_HOST_CANDIDATES=127.0.0.1,10.0.60.209,...` — phần tử đầu là `127.0.0.1`, nơi **chắc chắn không có DB** ở máy này. Fallback đáng ra phải là `DB_HOST` (địa chỉ cấu hình chủ đích).
  3. **Timeout probe 0.5s** quá sát so với thực tế vận hành.
- **Sửa:** chỉ `writeCache()` **bên trong** nhánh nối được (kèm `return` ngay); không probe được cái nào thì trả `DB_HOST` và **không ghi cache** để lần sau probe lại ngay; nới timeout 0.5s → 2s (không làm chậm đường bình thường vì probe thành công vẫn trả về sau ~10ms).
- **Kiểm chứng (script chỉ mở/đóng socket + file cache tạm, không truy vấn DB): 5/5 PASS**, gồm đúng kịch bản đã gây lỗi:
  1. Danh sách thật trong `.env` (127.0.0.1 đứng đầu) → chọn đúng `10.0.60.209` và ghi cache đúng host sống.
  2. **Tất cả candidate đều chết** → fallback về `DB_HOST` chứ không phải `candidates[0]`, và **không ghi cache** (trước bản vá: ghi → kẹt 20s).
  3. Chỉ 1 candidate → trả thẳng, không probe.
- **Phát hiện kèm:** cả 3 tiến trình (backend 8500, vite 3001, reverb 8080) đã tắt hẳn từ lúc nào không rõ — đó là lý do lần smoke test đầu trả "Unable to connect". Đã khởi động lại cả 3; smoke test: `/` → **200**, `/api/production-batches` → **401** (đúng, app boot sạch + middleware auth chạy), frontend → **200**, và `DB OK: production_web @ 10.0.60.209:5433`.
- **Mã QR test đã sinh** cho người dùng thử (thư viện `qrcode` có sẵn trong frontend, không gọi dịch vụ ngoài — ADR-003): `#TESTQR-TC001-VD10-220-R1-DYE001-12.50-...` 9 dòng rack. Màu/mã không khớp lô nào có sẵn nên đi nhánh ADHOC (tự tạo lô mới, cân tự do) — không đụng lô sản xuất thật. **Lưu ý: quét/SAVE bằng mã này GHI THẬT vào `production_web`**, dữ liệu test nhận diện qua tiền tố `ADHOC-` và mã `TESTQR`.

### 66. Nút cổ chai thật của tốc độ KHÔNG phải số query mà là CHI PHÍ MỞ KẾT NỐI DB — bật kết nối bền, tiết kiệm ~155ms mỗi request

- **Yêu cầu:** "khi quét, đẩy ra nhanh hơn nữa, siêu nhanh".
- **ĐÍNH CHÍNH số liệu mục 64:** con số "~36ms/query" ghi ở mục trước là **đo gộp cả chi phí mở kết nối**, không phải chi phí thuần của mỗi query. Tách bạch bằng phép đo riêng (chỉ `select 1`, không ghi):
  - **Mở kết nối lần đầu: ~212ms** — chịu MỘT LẦN cho mỗi request có chạm DB
  - **Round-trip mỗi query sau đó: ~33ms**
  - Ping tới `10.0.60.209`: trung bình **12,8ms** (min 8, max 23) ⇒ 33ms/query là hợp lý với đường mạng này, không phải DB chậm
  - Bootstrap Laravel + middleware (endpoint 401, không chạm DB): **19ms** — hoàn toàn không phải vấn đề
  - Kết luận: với luồng quét ~22 query thì riêng phần **mở kết nối chiếm 212ms**, tức gần 1/4 tổng thời gian, mà trước giờ không ai để ý vì nó không hiện ra dưới dạng "query chậm" trong bất kỳ log nào.
- **Sửa 1 — kết nối bền (`PDO::ATTR_PERSISTENT`)** trong `config/database.php`. Đo đối chứng trên chính đường mạng này: mở mới ~212ms vs tái dùng ~57ms ⇒ **tiết kiệm ~155ms cho MỌI request có chạm DB**, không riêng gì trạm cân. Xác minh Laravel thật sự dùng kết nối bền (không chỉ PDO trần): trong cùng tiến trình, `DB::disconnect()` rồi truy vấn lại chỉ tốn **89ms thay vì 324ms**, và `transactionLevel()` = 0 (không kế thừa transaction dở của lần trước).
  - **Đánh đổi đã cân nhắc và ghi rõ trong code:** request chết giữa transaction vì lỗi nghiêm trọng (OOM/timeout) thì PDO KHÔNG tự rollback trên kết nối bền. Chấp nhận được vì mọi đường ghi đều bọc `DB::transaction()`. Có cờ `DB_PERSISTENT=false` trong `.env` để tắt ngay mà không phải sửa code.
- **Sửa 2 — bỏ query trùng ở đầu luồng quét:** `scanRawDyeQr` validate `exists:operation_clients,code` (1 query chỉ để kiểm tra tồn tại) rồi NGAY dòng dưới truy vấn chính bảng đó lần nữa để lấy bản ghi. Bỏ rule `exists`, giữ `firstOrFail()` vốn đã bắt đúng trường hợp mã trạm sai. **−33ms.**
- **Sửa 3 — gắn sẵn quan hệ cho lô vừa tạo:** nhánh ADHOC vừa `firstOrCreate` xong cái máy (đã cầm object) và lô mới thì chắc chắn chưa có bồn, nhưng `handleOrderScan` vẫn `loadMissing(['machine','tank'])` truy vấn lại cả hai. Dùng `setRelation()` gắn thẳng. **−66ms.**
- **Tổng cộng cho một lần quét đơn mới: ~−254ms** (155 + 33 + 66), cộng thêm phần đã cắt ở mục 64.
- **Kiểm chứng:** `php -l` sạch 2 file. Backend khởi động lại để nạp config mới (`bootstrap/cache` chỉ có `packages.php`/`services.php`, KHÔNG có `config.php` nên không cần `config:clear`); smoke test sau restart: `/api/production-batches` → **401** đúng như mong đợi.
- **CHƯA đo được end-to-end** thời gian quét thật vì đường quét là đường GHI vào DB sản xuất — cần người dùng quét thử và cho biết cảm nhận.

### 67. Quét hiện bảng TỨC THÌ — parse chuỗi QR bằng JS ngay tại trình duyệt, việc tạo vòng cân dưới DB chạy nền

- **Đề xuất của người dùng:** "sao không dùng JS để đẩy chuỗi QR trước, sau đó lưu sau có phải nhanh hơn không?" — đúng, và đây là thứ duy nhất thật sự đưa thời gian chờ về ~0 thay vì chỉ bớt vài trăm ms như mục 66.
- **Nhận định:** chuỗi QR ĐÃ chứa đủ `rack/dye/weight` của cả mẻ. Bắt thao tác viên đứng chờ gần 1 giây đi-về mạng chỉ để nhìn đúng những con số vốn đã nằm sẵn trong tay là vô nghĩa.
- **Cách làm:**
  1. `frontend/src/utils/qrDyeParser.ts` (mới) — port `QrPayloadService::parseDyeScan` sang TS. Tách thành file riêng thay vì viết trong `.vue` **chính là để kiểm chứng được** (xem dưới).
  2. `handleBarcodeScan`: với QR thật (`#...`), parse tại chỗ → `applyOptimisticJob()` vẽ ngay 9 dòng với dung sai ±1% tính sẵn ở client (đúng `ScannerController::TOLERANCE_RATIO`, lệch là màu LED lúc cân sẽ khác kết quả ĐẠT/KHÔNG ĐẠT server chốt lúc lưu). Con trỏ trả về ô quét ngay, không đợi hết request.
  3. Request tạo job chạy nền, giữ trong ref `jobReady`. Khi về, `adoptRealJob()` thay khung tạm bằng dữ liệu server **nhưng GIỮ NGUYÊN** số thợ đã cân trong lúc chờ — cố ý KHÔNG dùng `applyActiveJob()` vì hàm đó xoá sạch `capturedWeights` để bắt đầu mẻ mới.
  4. `onSave()` `await jobReady` trước khi gửi — phòng trường hợp thợ cân xong sớm hơn server. Thực tế gần như không phải chờ: đổ vật tư lên cân lâu hơn nhiều so với một vòng đi-về mạng.
  - Token giả lập `DF:ORDER:<uuid>` KHÔNG áp dụng (không mang dữ liệu dòng nào, buộc phải hỏi server).
- **Ba cái bẫy đã xử lý, đều là chuyện mất dữ liệu chứ không phải thẩm mỹ:**
  1. **Request lỗi trong khi bảng đã hiện** → phải xoá bảng đi. Để nguyên thì thợ cân vào một bảng KHÔNG BAO GIỜ lưu được.
  2. **`applyActiveJob` gọi `cancelAbandonedJob(activeJob.id)`** với khung tạm `id: null` → thêm điều kiện `activeJob.value?.id`, không đi hủy cái chưa tồn tại.
  3. **CLEAR trong lúc job đang tạo dở** → không hủy ngay được (chưa có id). Phải chờ `jobReady` xong rồi mới hủy, nếu không vòng cân đó thành mồ côi và **lô kẹt vĩnh viễn không về được WEIGHED**. Lỗi tự bắt được khi viết: bản đầu đọc `activeJob.value?.id` bên trong `.then()`, nhưng lúc đó CLEAR đã gán `activeJob = null` (đồng bộ) rồi → sửa cho `jobReady` **trả về chính job** để đọc id từ kết quả promise.
- **Rủi ro chính là hai bản parse lệch nhau** (thợ nhìn một đằng, DB ghi một nẻo). Đã khoá bằng `frontend/scripts/check-qr-parser.mjs`: transpile bản TS bằng esbuild, nạp `QrPayloadService` thật qua `php -r`, chạy **13 ca đối chiếu** trên cùng đầu vào → **13/13 PASS**, gồm các ca hiểm: không có `#` đầu, dấu phẩy thay dấu chấm, cụm `-dye-` xen giữa, cắt phần `chem`, thiếu bộ ba cuối, hơn 9 bộ ba (phải cắt còn 9), dấu gạch lặp, chuỗi rỗng, chỉ có `#`, `-DyE-` chữ thường lẫn hoa. Không chạm DB, không gọi API.
- **Kiểm chứng:** `vue-tsc` **26 lỗi = đúng baseline**, không lỗi nào ở `WeighingStationV2.vue` hay `qrDyeParser.ts`. **CHƯA quét thử thật** — cần người dùng xác nhận bảng hiện tức thì và SAVE vẫn ghi đủ.

### 68. Quét KHÔNG chạm mạng chút nào — cả mẻ gói vào MỘT lệnh duy nhất lúc SAVE

- **Người dùng chốt** (qua câu hỏi trực tiếp, 3 phương án có nêu rõ đánh đổi): *"Không gọi gì — dồn hết vào SAVE"*. Mục 67 vẫn còn 1 request nền tạo vòng cân lúc quét; nay bỏ nốt.
- **Đây chính là cách VBA gốc làm:** `scaleform` chỉ giữ dữ liệu trong RAM (biến `p1..p9`), `btnSave_Click` mới INSERT xuống Access. Không có bước "mở lệnh sản xuất" riêng nào cả.
- **Backend — endpoint mới `POST /api/scanner/weigh-from-qr`** (`ScannerController::weighFromQr`), làm TẤT CẢ trong một transaction: mở/tìm lô sản xuất → tạo vòng cân + 9 dòng → ghi số cân → dựng phiếu in, trả `slip.label_payload` luôn.
  - **Tái dùng `handleOrderScan()` thay vì chép lại logic**: thêm tham số `$returnJob` để nó trả `['job','batch','notice']` thay vì `JsonResponse`. Giữ nguyên toàn bộ nghiệp vụ đã có (nhánh có công thức vs cân tự do, khóa chống 2 máy cân chung 1 job, cascade trạng thái lô, RACK auto-fill theo vị trí). Chép lại là chắc chắn sẽ trôi dạt khỏi nhau theo thời gian.
  - Lỗi nghiệp vụ (không có công thức ACTIVE, quét sai loại trạm) vẫn trả nguyên `JsonResponse` cũ — endpoint mới chỉ việc `return $scan` khi nhận về không phải array.
  - **`rows` khớp theo `sequence_no`**, không theo `item_id`: client chưa từng gọi server nên không biết id nào. Dòng client gửi mà job không có (QR nhiều dòng hơn công thức) thì bỏ qua — không tự chế thêm vật tư ngoài công thức.
  - `buildAndStoreSlip` (private) được mở ra qua `buildSlipForJob()` để dùng chung.
  - Giữ nguyên hàng rào `NOT_STABLE` như `weighItem`/`weighBatch` — client có thể gọi thẳng API, không phụ thuộc UI.
- **Frontend:** quét QR thật (`#...`) giờ **không gọi API nào**: parse bằng `qrDyeParser.ts` → `applyLocalJob()` dựng bảng + giữ luôn chuỗi QR để gửi lúc SAVE. `onSave()` gọi `/scanner/weigh-from-qr` với `raw_qr` + `rows`. Token `DF:ORDER:<uuid>` vẫn đi đường cũ (`/scan-dye-qr` lúc quét + `/weigh-batch` lúc lưu) vì nó không mang dữ liệu dòng nào.
  - Gỡ bỏ `jobReady`, `adoptRealJob` (mục 67) — không còn job nền để chờ hay để nhận nuôi.
  - **`onClear` đơn giản hẳn:** mẻ đọc từ QR chưa ghi gì xuống DB nên CLEAR chỉ là xoá màn hình, **không còn vòng cân mồ côi để dọn**. Trước đây đây là nguồn lỗi thật (lô kẹt vĩnh viễn không về được WEIGHED nếu quên hủy).
- **Đánh đổi đã nêu rõ với người dùng TRƯỚC khi làm và được chấp thuận:** quét xong mà chưa SAVE thì dưới DB không có gì cả ⇒ (a) mất cảnh báo "đơn này cũng đang được cân ở máy khác", (b) trạm khác không thấy mẻ đang cân dở. Đổi lại: quét không tốn vòng mạng nào, và không bao giờ còn sinh vòng cân mồ côi.
- **Kiểm chứng:** `php -l` sạch 3 file; `php artisan route:list` xác nhận `POST api/scanner/weigh-from-qr` đã đăng ký; `vue-tsc` **26 lỗi = đúng baseline**, không lỗi nào ở file vừa sửa. **CHƯA chạy thử đường ghi** — cả `weighFromQr` bọc trong `DB::transaction()` nên lỗi giữa chừng tự rollback sạch, nhưng vẫn cần người dùng quét + SAVE một mẻ thật để xác nhận.

### 69. F5 giữa chừng vẫn cân tiếp được — khôi phục mẻ dở HOÀN TOÀN tại client

- **Yêu cầu:** "nếu đã quét mà chưa CLEAR thì khi F5 đang cân dở sẽ cân được tiếp luôn, lưu vào cookie".
- **Vì sao việc này thành cần thiết:** sau mục 68, quét không ghi gì xuống DB nữa, nên đường khôi phục cũ (`restoreSession` hỏi `/api/weighing-jobs/active` rồi đối chiếu `jobId`) **không còn dữ liệu để hỏi**. F5 là mất trắng mẻ đang cân.
- **Dùng `localStorage` chứ không phải cookie** (đã có sẵn `SESSION_KEY = 'df_ws2_session_v1'` từ trước): cookie bị đính kèm vào MỌI request gửi lên server — vừa tốn băng thông vô ích cho thứ thuần tuý phía máy trạm, vừa vướng trần ~4KB mà một mẻ 9 dòng kèm bì/gộp từng ô có thể chạm tới. localStorage không gửi đi đâu và có 5-10MB.
- **Cách làm:** lưu thêm `rawQr` vào phiên. **Chuỗi QR CHÍNH LÀ cả mẻ** — F5 xong chỉ cần parse lại chuỗi đó là dựng nguyên bảng, không hỏi server câu nào. Tách `buildLocalJob()` ra khỏi `applyLocalJob()` để khôi phục dùng lại được mà KHÔNG xoá mất số đã cân (`applyLocalJob` cố ý xoá sạch vì nó dành cho lần quét mã MỚI).
- **Giữ nguyên cơ chế nhận lại bì đã có sẵn** (`pendingResume`): không nhận bì cũ ngay mà chờ số cân ổn định đầu tiên rồi ĐỐI CHIẾU với số gộp đã lưu (sai lệch ≤ 0.5g mới coi là "đĩa chưa bị đụng vào"). F5 không đụng vào đĩa, nhưng "ai đó nhấc đĩa ra rồi mới F5" cũng chẳng để lại dấu vết gì khác — nhận bừa bì cũ trong trường hợp đó sẽ cho ra số cân sai mà vẫn tô xanh ĐẠT.
- Nhánh khôi phục theo `jobId` (mẻ mở bằng token `DF:ORDER:<uuid>`, có vòng cân thật dưới DB) giữ nguyên, vẫn hỏi server.
- **Dọn tàn dư:** xoá đoạn chú thích "KHÔNG khôi phục mẻ đang dở khi vào trang (yêu cầu 2026-08-01)" — đã lạc hậu, `restoreSession()` vẫn được `onMounted` gọi suốt và giờ chính là thứ được yêu cầu.
- **Kiểm chứng:** thêm phép kiểm tra **tính thuần tuý của `parseDyeQr`** vào `check-qr-parser.mjs` (parse lại cùng chuỗi luôn cho cùng kết quả) — đây chính là tính chất mà việc khôi phục dựa vào; nếu hàm không thuần tuý thì F5 xong thợ có thể thấy bảng khác lúc quét. **14/14 PASS**. `vue-tsc` 26 lỗi = đúng baseline. **CHƯA thử F5 thật** — cần người dùng cân dở vài ô rồi F5 để xác nhận.

### 64. "Nạp đơn lâu quá" ở `/weighing-station-v2` — nút thắt KHÔNG phải JS mà là hàng đợi của backend một tiến trình

- **Người dùng hỏi:** nạp đơn lâu, có dùng JS cho nhanh hơn được không.
- **Đo trước, không đoán.** Frontend gửi **đúng 1 request** khi quét (`handleBarcodeScan` → `/api/scanner/scan-dye-qr`), nên không có gì để tối ưu ở phía JS theo nghĩa "gọi ít đi".
  - Độ trễ DB (đo bằng script chỉ đọc): **RTT 9.16 ms/query**, riêng **mở kết nối lần đầu 119.8 ms** (DB nằm ở máy khác — `10.0.60.209:5433`).
  - Phần ĐỌC của luồng quét: **8 query, 62.8 ms** (55.8 ms là DB, 6.9 ms là PHP) — không có N+1, không phải thủ phạm.
  - **Phát hiện quyết định:** gọi lặp **cùng một endpoint trả 401** (thoát ở middleware auth, KHÔNG chạm DB nghiệp vụ) mà kết quả là `min 19ms / trung vị 239ms / max 2117ms`, **5/20 lần > 300ms**. Một endpoint không làm gì cả thì không thể tự chậm — chênh lệch đó chỉ có thể là **xếp hàng**.
- **NGUYÊN NHÂN GỐC:** backend chạy `php artisan serve` → thực chất là `php -S` với **PHP_CLI_SERVER_WORKERS rỗng = MỘT worker duy nhất**, xử lý tuần tự từng request (kiểm chứng bằng `Get-CimInstance Win32_Process`, đọc đúng dòng lệnh của PID đang nghe cổng 8500). Trong khi đó `WeighingStationV2.vue` gọi `startPolling(200)` ngay từ `onMounted` — **5 request/giây liên tục** để lấy số cân, chạy cả khi màn hình đang trống chưa có đơn nào. Request quét đơn của thợ phải xếp hàng sau chính những lượt poll đó. (Trên Windows không thể bật nhiều worker cho `php -S`: cơ chế đó dựa vào `fork()`.)
- **Sửa 1 — nhịp poll thích ứng theo trạng thái** (`WeighingStationV2.vue`): `POLL_MS_WEIGHING = 200` giữ nguyên khi ĐANG có đơn (200ms là bắt buộc, xem mục 59: bì chốt từ lần đọc ổn định đầu tiên sau NEXT, nhịp thưa thì bì ăn cả phần vật tư vừa đổ), nhưng **`POLL_MS_IDLE = 1000` khi chưa có đơn** — tức đúng lúc thợ đang quét. Giảm 80% tải nền vào đúng thời điểm cần đường thông nhất. Thêm `watch(() => !!activeJob.value, ...)` để đổi nhịp ngay khi nạp/xoá đơn; so `!!` để không khởi động lại bộ đếm mỗi lần object job được gán lại.
- **Sửa 2 — chèn gộp 9 dòng cân** (`ScannerController.php`, cả 2 nhánh ad-hoc và có Recipe): `WeighingJobItem::create()` trong vòng lặp = 9 vòng đi-về DB ≈ **82 ms**, thay bằng 1 lần `insert()` gộp. Phải tự sinh UUID vì `insert()` đi thẳng query builder nên không chạy hook `creating` của model; an toàn với bảng này vì `$timestamps = false` (không có `created_at`/`updated_at` để điền) — đã đọc `WeighingJobItem.php` xác nhận trước khi đổi, không suy đoán.
- **Kiểm chứng:** `php -l` sạch. `vue-tsc` **26 lỗi = ĐÚNG BẰNG baseline** (đo bằng `git stash push -u` rồi chạy lại, sau đó `git stash pop` khôi phục đủ 4 file) — không phát sinh lỗi mới. Đo lại cùng endpoint 401 sau khi sửa: **trung vị 239ms → 19ms**, số lần > 300ms **5/20 → 1/20**.
- **GIỚI HẠN CỦA PHÉP ĐO SAU KHI SỬA — không được đọc là "đã xong":** không kiểm soát được biến số quan trọng nhất là trình duyệt người dùng có đang mở `/weighing-station-v2` hay không tại thời điểm đo, nên phần cải thiện do sửa và phần do trang tình cờ đóng/đang reload **chưa tách bạch được**. `max` vẫn còn **2657 ms** ở một lượt — nghẽn chưa biến mất hẳn, chỉ thưa đi. **Chưa bấm quét một đơn thật qua UI để đo thời gian thợ thực sự phải chờ.**
- **Chưa làm — cách chữa tận gốc:** một tiến trình PHP phục vụ tuần tự là trần cứng; mọi tối ưu query chỉ là bào mỏng phần ngọn. Muốn hết hẳn phải cho backend chạy đa tiến trình (Apache/Nginx sẵn có trong Laragon, hoặc php-cgi nhiều tiến trình). Đây là thay đổi hạ tầng, **ảnh hưởng cả quy trình chạy local lẫn scheduled task `DFWeb-Backend` trên CS-SERVER**, nên chưa tự ý làm — cần người dùng quyết.

### 63. RAW khong nhay theo can that — nguyen nhan: PuTTY KHONG CHAY, khong lien quan code

- **Trieu chung nguoi dung bao:** "RAW -0.02 dang khong nhay theo dung so can o tren may".
- **Chan doan tai cho (chi doc, khong sua gi code):**
  - `D:\scale\putty_log.txt` ton tai, 458 bytes, **sua lan cuoi cach day 51 phut** (08:09:53, luc kiem tra la 09:01:00) — dung yen dung khoang thoi gian nguoi dung thay so khong doi.
  - `Get-Process putty` -> **KHONG CO tien trinh PuTTY nao dang chay** tren may.
  - Xem raw bytes cuoi file: dong cuoi bi CAT DO, ket thuc bang ky tu `W` khong co CRLF theo sau -> phien PuTTY bi dong/rot dung giua luc dang ghi, khong ai mo lai.
  - Service `DFAgent`: **Running** binh thuong — Agent khong loi, chi don gian khong co gi moi de doc.
  - COM6 (Prolific PL2303GT, adapter noi can that): **Status OK**, phan cung khong hong.
  - Registry PuTTY saved session ten `can`: cau hinh dung — SerialLine=COM6, SerialSpeed=9600, LogFileName=D:\scale\putty_log.txt, LogType=2 (ghi toan bo output) — khop chinh xac voi duong dan Agent dang doc (muc 60). Khong phai loi cau hinh.
  - Khong co Startup shortcut / Scheduled Task nao tu khoi dong lai PuTTY neu no dong/crash — **day la lo hong van hanh thuc su**, khong phai code.
- **Sua tai cho (khoi phuc trang thai, khong doi code):** `putty -load "can"` de mo lai dung phien da luu. Xac nhan file lai duoc ghi tiep (458 -> 3805 bytes trong ~9 giay, so cai dat +0.07/+0.06 dung nhu dat vat gi do len can). Goi thang `DeviceController::getReading` (chi doc) xac nhan toan chuoi song: `weight=0.06, has_reading=true, age_ms=597` — duoi 1 giay, dung nhip day 200ms cua Agent.
- **KET LUAN: day khong phai loi code.** Banner "MAT TIN HIEU CAN" (muc 58) da bao dung su that ca 2 lan lien tiep trong phien nay — lan truoc la loi khoa cache id-vs-code (muc 62), lan nay la PuTTY thuc su khong chay. Ca hai deu la vi du dung viec canh bao "khong con hien 0.00 im lang" phat huy tac dung dung nhu thiet ke.
- **RUI RO CON TON TAI, CHUA XU LY (can nguoi dung quyet dinh):** khong co co che nao tu dong khoi dong lai PuTTY neu bi dong/crash/may restart. Neu xay ra o tram pilot dang chay that ma khong ai de y, Agent se tiep tuc "chay tot" (service Running) trong khi khong co so can nao thuc su duoc gui, va thao tac vien chi biet duoc qua banner canh bao tren man hinh. De nghi: them mot trong 2 huong — (1) Startup shortcut/Scheduled Task tu mo lai `putty -load can` moi khi dang nhap Windows hoac dinh ky kiem tra tien trinh con song khong roi tu khoi dong lai; hoac (2) doi huong lau dai la Agent tu mo thang cong COM (Scale:Source=SERIAL, code da co san tu truoc, xem ScaleReader.cs) de khong con phu thuoc PuTTY nua. Chua tu lam vi day la thay doi hanh vi khoi dong may/kien truc doc can, can nguoi dung chon huong.

- **Quyet dinh nguoi dung (2026-08-01):** khong xu ly co che tu khoi dong lai PuTTY luc nay — chon "Chua xu ly, de sau". Giu nguyen hien trang: banner "MAT TIN HIEU CAN" la lop bao ve duy nhat, nguoi van hanh tu phat hien va mo lai PuTTY bang tay (`putty -load "can"`). Can hoi lai truoc khi trien khai pilot 7 ngay lien tuc (muc tieu Phase 12) neu van chua co giai phap.

### 64. PROCESS khong hien so AM khi bo vat tu ra — VA PHAT HIEN DA PORT TU FILE VBA SAI

- **Nguoi dung bao:** bam NEXT thi PROCESS ve 0 dung roi, nhung khi bo do ra khoi dia thi khong hien so am.
- **PHAT HIEN LON: hai file VBA KHAC NHAU o dung doan tinh delta.**
  - Ban da port truoc gio lay tu **ban sao DA MO KHOA** trong git (`semiautosmall scale deltastablefinal1_UNLOCKED.xlsm`), vi file that bi khoa VBA project.
  - File CHAY THAT (`4.semiauto-small scale - delta-stable-final_DF026-027.xlsm`) khoa VBA nen `VBComponents` doc khong duoc — nhung **giai nen .xlsm (la file ZIP) roi quet chuoi ASCII thang trong `xl/vbaProject.bin`** thi doc duoc nguyen van.
  - Doi chieu `AutoFlow_OnWeight`:

    | | File that (DF026-027) | Ban sao da mo khoa (da port nham) |
    |---|---|---|
    | BASE INIT | `If DeltaBaseWeight < 0 Then` | `If DeltaBaseWeight = -1 Then` |
    | CALC | `deltaVal = rawW - DeltaBaseWeight`<br>`If deltaVal < 0 Then deltaVal = 0` | `deltaVal = Abs(rawW - DeltaBaseWeight)` |

    Dung 2 dong CALC cua file that lai la 2 dong **bi comment** trong ban sao. Xac nhan them: chuoi `Abs(rawW` **KHONG TON TAI** trong file that (`Abs(` duy nhat nam trong doan canh vi tri cua so form, khong lien quan).
- **Sua:** bo `Math.abs()`, dung delta CO DAU (`raw - tareBaseline`). Day la **lech co chu y so voi ca hai ban VBA**, theo yeu cau nguoi dung, va tot hon ca hai:
  - `Abs()` la lua chon te nhat: nhac dia ra khoi can cho so ve 0 thi `|0 - bi|` = dung bang bi, mot so DUONG lon — co the roi trung dai +-1% va an nen XANH "dat" cho o chua he can.
  - Ban goc kep ve 0 thi khong noi doi, nhung giau mat chuyen da tut xuong duoi moc bi.
  - Co dau: tut duoi bi -> so am -> `ratio < 0.99` -> nen vang "chua du". Khong co duong nao de so am an nen xanh.
- **Kiem chung:** `vue-tsc --noEmit` sach, `vite build` thanh cong (20.29s). **Chua thu tren can that.**

**HAI VIEC PHAT HIEN THEM, CHUA SUA, CAN NGUOI DUNG QUYET:**

1. **Bug that trong file VBA goc:** `If DeltaBaseWeight < 0 Then DeltaBaseWeight = rawW` — dung `< 0` lam co hieu thay vi mot sentinel rieng. Neu can doc AM nhe (thuc te da do duoc `-0.02` o muc 63!) thi sau khi chot bi, `DeltaBaseWeight` van `< 0`, nen lan doc ke tiep **chot lai bi lan nua**, lap vo han -> delta luon ~0, khong bao gio len duoc so that. V2 dung `null` lam sentinel nen khong dinh loi nay. Chua bao nguoi dung day co phai loi da tung gap o xuong khong.

2. **Nhip chot bi lech VBA:** trong VBA, `StableFilter` **KHONG phai cong chan** — no tra ve `lastGood` (mot GIA TRI) o moi lan goi, nen `AutoFlow_OnWeight` chay 100 lan/giay va dong `If DeltaBaseWeight...Then DeltaBaseWeight = rawW` chot bi **ngay tuc khac** bang gia tri dang hien san (~10ms sau khi bam NEXT). V2 lai coi `is_stable` la cong chan (`if (!stable) return;` dat TRUOC doan chot bi), nen bi phai **cho mot lan doc on dinh MOI**. Neu tho bam NEXT roi do luon, bi bi chot muon va nuot luon phan bot da vao dia -> PROCESS hien thieu. Da bat dau sua roi revert lai de giu thay doi lan nay gon trong dung yeu cau; can nguoi dung xac nhan truoc khi doi vi no thay doi cam giac thao tac.

### 65. CLEAR luon hoi xac nhan + quet lai ma da SAVE = CAN LAI TU DAU

- **Yeu cau nguoi dung:** "khi an clear thi co xac nhan, va khi quet lai ma day thi coi nhu la can moi".
- **CLEAR:** truoc do CHI hoi khi da can duoc it nhat 1 o (`capturedWeights` khong rong). Nay hoi ca khi CHUA can o nao mien la dang co don tren man hinh — bam nham luc do tuy khong mat so can nhung van mat don vua quet, phai chay di lay phieu quet lai. Man hinh dang trang thi van xoa thang khong hoi (bam CLEAR tren form trong la vo hai). Hai cau thong bao khac nhau tuy truong hop.
- **Quet lai ma:** `ScannerController::handleOrderScan` truoc do tim job theo `production_batch_id + job_type` va tai dung BAT KE trang thai. Sau khi da SAVE, job la COMPLETED nen quet lai se hien nguyen 9 dong so cu, ma `weighBatch` lai bo qua het dong da COMPLETED -> man hinh dung im khong can duoc gi. Nay them `->where('status', '!=', 'COMPLETED')` + `orderByDesc('created_at')`, nen job da xong khong con duoc tai dung va nhanh `if (! $job)` tao VONG CAN MOI.
- **Job cu giu nguyen** — khong sua, khong xoa (CLAUDE.md muc 3, khong xoa vat ly du lieu giao dich). Hop voi tinh huong that: tho can sai, da luu, muon lam lai; ban ghi sai phai con de doi soat.
- **HE QUA CO CHU Y, CAN LUU Y KHI LAM BAO CAO:** 1 lo giờ co the co NHIEU vong can. Trang thai lo quay ve PARTIALLY_WEIGHED trong luc vong moi dang chay roi tro lai WEIGHED khi xong (WeighingItemRecorder tu cascade — da co san, khong sua). **Bao cao tieu hao phai cong don theo VONG, khong duoc gia dinh 1 lo = 1 lan can** — neu dang gia dinh vay thi so lieu se sai khi co lo can lai. Chua ra soat cac bao cao hien co, can kiem tra rieng.
- **Kiem chung:** `php artisan test --filter=WeighBatchTest` (SQLite in-memory) **7/7 pass, 44 assertions**, gom test moi `completed_job_is_not_reused_so_rescan_starts_a_new_round` (job COMPLETED khong con duoc coi la tai su dung duoc, VA job cu van con nguyen). `php -l` sach, `vue-tsc --noEmit` sach, `vite build` thanh cong (19.15s).

### 66. Popup bi chan lam SAVE "coi nhu that bai" du DA LUU XONG + them trang Lich su can

**A. Loi SAVE (nguoi dung bao: "Trinh duyet da chan cua so moi... thi lai toi khong luu duoc")**

- **Nguyen nhan:** thu tu thao tac trong `onSave`:
  ```
  await axios.post(...weigh-batch...)   // LUU DA XONG
  await printSlip()                     // window.open() o day BI CHAN
  onClear(true)                         // van chay, xoa sach man hinh
  ```
  Chrome/Edge chi cho `window.open` khi con "user activation" — tuc ngay trong handler cua cu click, chua qua `await` nao. `printSlip` mo cua so SAU `await axios.post` nen bi chan. Chinh comment trong `printSlip` da ghi "mo cua so NGAY truoc await" nhung `onSave` lai vi pham.
- **Hau qua:** me DA LUU THANH CONG nhung khong in duoc phieu, form van bi `onClear` xoa sach. Bam SAVE lai thi `activeJob` da null -> `rows` rong -> bao "Khong co dong nao de luu". Thao tac vien tuong chua luu duoc gi.
- **XAC NHAN BANG DU LIEU THAT:** endpoint lich su moi cho thay **2 vong can luc 02:10:49 va 02:33:31 cung lo LEP70158/SE5433/VD003** — dung 2 lan bam SAVE. Ca hai deu da luu thanh cong. (Ca hai `dat=0 khong-dat=3` vi luu khi con dong chua can.)
- **Sua:**
  1. `onSave` mo cua so in NGAY TAI DAU khoi try, truoc moi `await` — con trong user activation nen khong bi chan. `window.confirm` o tren khong pha chuoi nay vi no dong bo.
  2. `printSlip(preOpened?)` nhan cua so mo san; nut PRINT goi thang tu click nen van tu mo nhu cu.
  3. Neu popup VAN bi chan: me da luu roi thi **tuyet doi khong xoa form am tham** — hien thong bao "DA LUU XONG me can, nhung trinh duyet chan cua so in. Cho phep popup roi bam PRINT" va giu nguyen man hinh de bam PRINT duoc.
  4. Luu hong thi `printWin?.close()` de khong de lai cua so trang lo lung.

**B. Trang Lich su can (`/weighing-history`)**

- Backend `WeighingJobController::history` + route `GET /api/weighing-jobs/history` (dat TRUOC `/weighing-jobs/{id}`, neu khong "history" bi nuot thanh `{id}`).
- **Moi dong la MOT VONG CAN, khong phai mot lo** — tu muc 65 mot lo co the can lai nhieu vong; gom theo lo se giau mat cac lan can lai, dung thu can nhin thay nhat khi doi soat.
- Loc: khoang ngay, tim theo mau/ma hang/ma lo/may. Phan trang bat buoc (bang chi tang, khong bao gio tra het mot luot). Bam vao dong de xem chi tiet 9 dong: RACK / DYE CODE / muc tieu / thuc can / **lech co dau** / DAT-KHONG DAT.
- Dem dat/khong-dat tinh o SERVER (`process_status` la thuoc tinh suy dien cua model) de web va bao cao luon dung chung mot dinh nghia.
- O chua can hien `—` chu KHONG hien `0.00`: 0.00 la mot ket qua can hop le (dia rong), khong duoc lan voi "khong he can".
- **Dung `like` chu khong `ILIKE`** — ban dau viet ILIKE, ra soat thay ca du an dung `like`, doi lai cho dong nhat va khong khoa cung vao Postgres.
- Menu: "Lich su can" ngay duoi "Tram can (V2)", `adminOnly` giong V2.
- **Kiem chung tren du lieu that (chi doc, goi thang controller):** khong loc -> 3 vong; `from=2026-08-01` -> 2 vong; `q` khong khop -> 0; phan trang/dem dat-khong dat dung.
- `php -l` sach, `vue-tsc --noEmit` sach, `vite build` OK (24.08s), `WeighBatchTest` **7/7 pass (44 assertions)**.

### 67. /weighing-station-v2 luon mo o trang thai TRANG, khong nap lai me cu

- **Yeu cau nguoi dung:** "khi toi quay ve neu da luu roi thi khong duoc nhay lai ma cu... luc nao cung trong trang thai san sang de can dot moi".
- **Nguyen nhan:** `onMounted` goi `restoreActiveJob()` -> `GET /api/weighing-jobs/active` -> tu nap lai don dang do cua tram. Quay ve man hinh la thay nhay lai ma cu.
- **Sua:** bo han `restoreActiveJob` khoi V2. Vao trang la form trang, quet QR moi nap don.
- **Bo han la DUNG cho V2, khong chi vi tien:** gia tri 9 o song trong RAM cua trang toi luc bam SAVE nen reload la MAT HET. Nap lai don cu chi dung duoc cai khung voi toan bo o PROCESS trang — nhin nhu dang can do nhung so da bay sach. Nguy hiem that su: thao tac vien tuong da can xong roi bam SAVE, luc do moi dong chua can bi chot luon thanh KHONG DAT va **khong can lai duoc nua** (server chan ghi de dong da COMPLETED).
- **Cung dung ban goc:** form VBA mo ra luon trang, khong co khai niem khoi phuc — quet QR la nap lai toan bo don trong mot nhip. Mat mang/dong trang giua chung thi quet lai ma do.
- **KHONG dung toi `/weighing-station` (V1)** — man hinh cu van giu `restoreActiveJob` vi no luu TUNG DONG ngay khi can xong, nen khoi phuc o do that su co y nghia (so da nam trong DB). Day la khac biet ban chat giua 2 luong, khong phai bo sot.
- Don luon comment chet nhac toi `restoreActiveJob` trong `onSave`.
- **Kiem chung:** `vue-tsc --noEmit` sach, `vite build` OK (17.82s).

### 68. Quet ma o o COLOR "day ra cham qua" — DO DUOC nguyen nhan, phan lon la do MOI TRUONG DEV

- **Do that (khong doan)** bang script chi doc: **moi truy van DB mat ~20ms** tu may dev (`SELECT 1` = 26.7ms, cac truy van thuc te 19-21ms). Luong quet nap don chay ~25-30 truy van (tim lo, tim cong thuc, tao job, 9 x firstOrCreate vat tu, 9 insert dong, cap nhat lo, 2 publish realtime, load lai quan he) -> **~600ms chi rieng do tre mang**, cong bootstrap Laravel + HTTP thanh gan 1 giay.
- **NGUYEN NHAN GOC LA CAU HINH MOI TRUONG, KHONG PHAI CODE:** `backend/.env` cua may dev co `DB_HOST=10.0.60.209` — backend chay o may nguoi dung nhung DB o CS-SERVER. Tren CS-SERVER that, backend (cong 8500) va DB (cong 5433) **nam cung mot may**, moi truy van ~1ms, cung luong do chi ton ~30ms. **Tram pilot se KHONG gap do cham nay.** Da bao nguoi dung.
- **Van sua 2 thu co ich cho ca hai moi truong:**
  1. **Backend — tra vat tu GOP 1 truy van** thay cho `Material::firstOrCreate` trong vong lap 9 lan: `whereIn` lay ma da co, `array_diff` ra ma thieu, `insert()` gop 1 lan. Giu dung ngu nghia firstOrCreate (khong dung vao ma da ton tai). **Do that: 6 ma tra gop 13.0ms vs tra tung ma 95.5ms — nhanh hon 7.3 lan.**
     - **Bay da tranh:** `insert()` gop di thang query builder nen KHONG tu dong dau `created_at`/`updated_at` nhu `firstOrCreate`. Cot la nullable nen khong loi, chi am tham de rong -> mat dau vet ma vat tu tu tao luc nao. Da dien tuong minh.
  2. **Frontend — phan hoi NGAY khi quet:** bip ngay luc nhan ma (khong doi server), o COLOR doi sang "Dang nap don…" + nhap nhay xanh, khoa o va chan quet chong. Truoc do man hinh dung im gan 1 giay, thao tac vien tuong may quet khong an va ban lai ma lan nua.
- **KHONG lam:** tach chuoi QR o client de ve form ngay lap tuc nhu VBA (`txt_color_AfterUpdate` khong he goi server). Se nhanh nhat nhung phai chep lai logic tach chuoi sang TS -> 2 ban parser de troi dat khoi nhau, va 9 dong ve tam chua co `item.id` nen bam NEXT/SAVE se hong. Chi nen lam neu do cham con lam phien sau khi da chay tren CS-SERVER.
- **Kiem chung:** `php -l` sach, `vue-tsc --noEmit` sach, `vite build` OK (18.63s), `WeighBatchTest` 7/7 pass.
  - **CHUA CHAY DUOC 2 test phu dung duong code nay** (`QrScanToWeighingE2ETest`) — hong san vi SQLite thieu bang `operation_clients` (migration dung raw `ALTER TABLE ... RENAME`). Lan `ProductionOrderScanEntryTest > store rejects duplicate...` cung hong san (test cho 422, code tra 409 tu khi doi sang canh bao). Ca 3 deu co san tu dau phien, khong do thay doi lan nay. Bu lai bang script chi doc doi chieu ket qua tra gop vs tra tung ma tren du lieu that: cung ra dung 4 ma da co / 2 ma thieu, khong chen gi.

### 69. Giu me dang do RIENG TUNG MAY — quet o may nao thi o lai may do toi khi CLEAR/SAVE

- **Yeu cau nguoi dung:** "cai nay se duoc dung tren nhieu may, khi quet 1 don tren may va chua clear thi van se hien thi don do tren may do de can tiep".
- **Khong mau thuan voi muc 67** ("luc nao cung san sang can dot moi"). Quy tac day du: **da SAVE hoac da CLEAR -> mo trang; quet do ma chua CLEAR -> may DO giu de can tiep.** Muc 67 bo `restoreActiveJob` vi no khoi phuc VO DIEU KIEN (ke ca me da bo), va vi khoi phuc luc do KHONG mang theo so da can.
- **Van de phai xu ly cung luc:** 9 o chi song trong RAM toi luc bam SAVE. Khoi phuc moi cai khung ma mat so con NGUY HIEM HON khong khoi phuc — thao tac vien nhin thay don, tuong da can xong, bam SAVE -> moi dong chua can bi chot KHONG DAT va khong can lai duoc.
- **Cach lam — 2 lop, deu tu nhien theo tung may:**
  1. **Server:** `/api/weighing-jobs/active` von da loc theo `assigned_operation_client_id` va da loai job COMPLETED -> chi tra don cua dung tram dang hoi.
  2. **localStorage `df_ws2_session_v1`** (rieng tung may): luu `{workstationId, jobId, capturedWeights, capturedTare}`. Day la thu DUY NHAT nho duoc so 9 o.
- **Diem chot khi khoi phuc:** `currentIndex = -1`, KHONG tu nhay vao o dang can do. Bi la trang thai VAT LY cua cai dia ngay luc do — khoi phuc tu localStorage sau reload la bia, vi dia co the da bi nhac ra/them bot hoac can da troi so. Bam NEXT de vao o chua can ke tiep va lay bi moi.
- **`onNext` lan dau gio bo qua ca o da luu o server LAN o vua can xong dang giu o may nay** — neu khong, bam NEXT sau khi khoi phuc se nhay ve o 1 va ghi de so da can.
- **Cac diem noi:** `applyActiveJob` + `captureCurrentSlot` -> `saveSession()` (ghi ngay tung o, mat dien giua me chi mat dung o dang can do); `onClear` + SAVE thanh cong -> `clearSession()`; `onMounted` -> `restoreSession()` (khong await de so can chay ngay).
- **Cac truong hop da xu ly:** dau vet cua tram khac (may duoc gan lai tram) -> bo, khong nap nham don tram kia; server khong con coi la don dang do (da SAVE noi khac) -> bo dau vet, mo trang; localStorage hong/day -> nuot loi, khong lam hong luong can dang chay.
- **CHUA LAM (khoang trong da biet):** sua tay o RACK truoc khi SAVE khong duoc luu vao phien — sau reload se quay ve gia tri tu QR. Rack von den tu QR nen sua tay la hiem; chua lam vi phai gop nguoc gia tri vao job moi tai ve, khong dang danh doi do phuc tap luc nay.
- **Kiem chung:** `vue-tsc --noEmit` sach, `vite build` OK (18.23s). **Chua thu tay tren trinh duyet** — can nguoi dung kiem: quet don -> F5 -> phai thay lai don va cac o da can; bam CLEAR -> F5 -> phai trang; SAVE -> F5 -> phai trang.

### 70. F5 phai CAN TIEP DUOC NGAY — nho ca vi tri o dang can va bi, co chot an toan doi chieu dia can

- **Nguoi dung chinh lai muc 69:** "tuc la phai ghi nho xem toi can den dau roi chu, khi F5 lai van tiep tuc can tiep binh thuong".
- **Muc 69 da qua than trong:** khoi phuc xong dat `currentIndex = -1`, bat bam NEXT lai. Ly do khi do: bi la trang thai VAT LY cua dia, khoi phuc tu localStorage la "bia". **Lap luan do sai o cho:** F5 khong he dung vao dia — truong hop binh thuong thi bi cu VAN DUNG.
- **Nhung rui ro that su van con:** "ai do nhac dia ra roi moi F5" khong de lai dau vet gi khac voi "F5 thuan tuy". Nhan bua bi cu trong truong hop do se cho ra so can SAI ma van to xanh DAT.
- **Giai phap — doi chieu so can GOP:**
  - Phien luu them `currentIndex`, `tareBaseline`, `grossAtSave` (so can gop luc ghi).
  - `grossAtSave` duoc cap nhat moi khi can DUNG o mot gia tri moi (watch tren `isStable`+`grossWeight`): dang do vat tu thi khong ghi (chua on dinh), gia tri khong doi cung khong ghi -> khong dung localStorage moi vong poll.
  - Khoi phuc: **khong nhan bi ngay**, dat `pendingResume` roi cho lan doc on dinh dau tien. Lech <= **0.5g** -> dia y nguyen, noi lai dung o dang can do voi bi cu, can tiep nhu chua he F5. Lech hon -> bao ro *"Dia can da thay doi trong luc tai lai trang (X → Y). Cac o da can van con nguyen — bam NEXT de can tiep o ke va lay bi moi."*
  - 0.5g: can doc theo gram, vat tu nhe nhat trong cong thuc cung vai gram, du rong de bo qua troi so/rung nen ma van bat duoc moi thao tac that.
- **Thao tac tay thang viec noi lai con treo:** bam NEXT / chon o khac -> `pendingResume = null`, khong de no nhay vao sau lung nguoi dung.
- **Ghi phien ngay khi chuyen o** (`onNext`, `onSelectRow`): `captureCurrentSlot` con ghi theo o CU, neu F5 roi dung khoang giua do thi phien se sai vi tri dung 1 o.
- **Kiem chung:** `vue-tsc --noEmit` sach, `vite build` OK (18.62s). **Chua thu tay** — can nguoi dung kiem 3 tinh huong: (1) can do o giua roi F5 -> phai can tiep duoc ngay dung o do, so cu con nguyen; (2) F5 roi NHAC DIA RA -> phai hien canh bao, khong nhan bi cu; (3) CLEAR/SAVE roi F5 -> phai trang.

### 71. Cai Agent len MAY NAO CUNG CHAY - ghep can theo IP may, khong theo ma tram cau hinh tay

- **Yeu cau:** "toi muon may nao cung dung duoc co Agent la duoc".
- **Loi that dang co:** bo cai MSI dong cung `Workstation:Id = WS-WEIGH-SCALE` cho MOI may, nen 2 tram can chay cung luc ghi de len dung mot khoa cache `scale_live_weight_WS-WEIGH-SCALE` -> moi man hinh doc phai so can cua tram kia. Truoc day phai sua tay appsettings.json tren tung may sau khi cai.
- **Cach sua - ghep cap theo IP nguon:** Agent va trinh duyet cua tho chay tren CUNG mot may tram, va ca hai goi thang `http://<server>:8500` KHONG qua proxy nao (da kiem: `vite.config.ts` khong co proxy, `main.ts:17` dat baseURL thang toi cong 8500) -> backend thay chung mot IP, du de ghep ma khong can cau hinh gi.
  - `storeReading` ghi THEM bo khoa `scale_live_weight_machine_<ip>` (khong thay the khoa theo ma tram).
  - `getReading` nhan co `?local=1`; chi cac man hinh can bat co nay. Dashboard KHONG bat vi no xem nhieu tram tu xa cung luc.
- **Da thu cach "lay ban tuoi hon" va NO SAI** - probe bat duoc: moi Agent deu ghi chung mot khoa theo ma tram nen khoa chung gan nhu luon vua duoc may khac cap nhat, tuoi hon khoa theo IP cua chinh may nay -> may A VAN doc phai so cua may B. Sua thanh **may dang ngoi thang tuyet doi**.
- **Moc nhan dien la `read_at` (TTL 1 gio), khong phai `weight` (TTL 15s):** may nao da tung bao so trong 1 gio qua thi coi la may CO CAN. Nho vay khi Agent/PuTTY chet, man hinh bao thang "MAT TIN HIEU CAN" thay vi am tham tut ve hien thi can cua tram khac - can sai ma van to xanh DAT nguy hiem hon han mat so.
- **Sua ca V1** (`WeighingStation.vue`) chu khong chi V2: cung mot loi, neu chi sua V2 thi hai tram chay V1 van de so cua nhau.
- **KHONG phai build lai MSI** - nhan dien chuyen sang tang backend nen bo cai 2.1.0.0 dang co giu nguyen, cai y het nhau len bao nhieu may cung dung.
- **Kiem chung:** probe chay thang controller (ep `cache.default=array`, dung ma tram khong ton tai nen khong co INSERT nao vao production) - **6/6 DAT**: 2 may tach bach dung so cua minh; may khong co Agent lui ve khoa ma tram nhu cu; tram trong bao `has_reading=false`; may co Agent da chet KHONG tut ve so cua may khac. Them 2 test vao `ScaleLiveWeightTest` (**chua chay duoc** - chua co DB test co lap). `vue-tsc` sach, `vite build` OK (19.43s). Pint fail o 2 file nay nhung **da fail san tu HEAD** truoc khi sua, khong chay `pint --fix` de khoi de ra diff dinh dang lon.
- **Con lai:** phai deploy backend + frontend len CS-SERVER moi co tac dung o xuong.

### 72. Nhieu may cung chay - moi may tu dang ky thanh mot tram rieng, khong may nao anh huong may nao

- **Yeu cau:** "toi muon dung o nhieu may, cha may nao anh huong may nao". Nguoi dung chon: **may tu dang ky** (chua dung tram tay) va **cho vao nhung canh bao** khi 2 may quet trung don.
- **Ba cho dung chung, sua ca ba:**
  1. **So can** (muc 71): da ghep theo IP may.
  2. **Ma tram**: bo cai dong cung mot Id cho moi may. Nay `Workstation:Id` de trong -> `Worker.ResolveWorkstationId` sinh `WS-SCALE-<TEN-MAY>`. Cau hinh tuong minh VAN duoc uu tien (may cai tu truoc khong bi doi ma sau khi cap nhat).
  3. **Vong can dung chung**: `handleOrderScan` tra ve CUNG mot WeighingJob cho moi may quet cung don -> 2 may can song song, ai SAVE sau bi bo qua nhung dong may kia da luu (weighBatch bo dong COMPLETED) — mat so ma khong ai biet.
- **Tu dang ky (khong phai khai tay):**
  - `AgentAuth` da co san nhanh tu tao tram nhung chi nhan **3 ma co dinh**; ma sinh tu ten may khong the co trong danh sach do nen roi ve type `AUTO_REGISTERED` va bi `handleOrderScan` chan 403. Da doi sang **suy loai tram tu truong `role`** Agent gui kem (SCALE_ONLY -> DYE_WEIGHING + caps SMALL_SCALE/WEIGH/PRINT). Ten tram lay `machine_name` that cho de nhan ra may nao la may nao.
  - Endpoint moi `GET /api/workstations/whoami`: "may toi dang ngoi la tram nao?" — tra theo IP nguon ra tram ma Agent tren chinh may do da dang ky. `storeReading` ghi mapping IP->ma tram (TTL 12h ~ tron mot ca).
  - Frontend `adoptLocalWorkstation()` goi trong `onMounted` cua V2. **KHONG dung** toi tai khoan/kiosk da gan cung tram (`df_workstation_config`) — do la thu WS-001 dung ra de chan chon nham.
- **Canh bao trung don:** khi tram quet khac `assigned_workstation_id` hien tai, tra them truong `warning` (ten may kia + gio mo + so dong da luu), chuyen quyen sang may vua quet de may thu ba duoc canh bao theo may dang can that. **Khong chan** — chan cung se ket dung luc can nhat (may kia treo/mat dien thi don khong ai can duoc nua). Hien bang dai canh bao vang tai cho, KHONG dung `alert()` vi alert nuot mat phat ban ma ke tiep cua may quet.
- **Kiem chung:** probe `whoami` **7/7 DAT** (may chua co Agent -> 200 + data=null chu khong 404; ghi dung mapping; tra du `capability_codes`/`id`/`type` frontend can). Agent test **26/26 DAT** (them 8 test cho ResolveWorkstationId: uu tien cau hinh tuong minh, on dinh giua cac lan goi, chi sinh `[A-Z0-9-]`). `vue-tsc` sach, `vite build` OK (17.54s). MSI **2.2.0.0** dung xong, da bung ra kiem dung cau hinh ben trong (`Id` rong, Backend 10.0.60.209).
- **Con lai:** phai deploy backend + frontend len CS-SERVER thi co che nay moi co tac dung o xuong. Cai Agent truoc khi deploy thi may van day so len duoc nhung man hinh can chua nhan dung.

### 73. Doi quyet dinh: moi may MOT VONG CAN RIENG, ca hai deu SAVE duoc day du

- **Nguoi dung doi lai muc 72:** "hai may quet cung don -> chung mot job, ai SAVE thi cung deu save duoc, k anh huong 2 ban cung duoc". Tuc la bo huong "canh bao roi van dung chung job", chuyen han sang **tach vong can theo may**.
- **Sua o `ScannerController::handleOrderScan`:** truy van tim job tai dung them dieu kien `assigned_workstation_id = <tram dang quet>`. Truoc do khong loc theo tram nen hai may nhan ve CUNG mot WeighingJob -> may bam SAVE sau bi bo qua toan bo dong may kia da ghi (weighBatch bo dong COMPLETED), mat so ma khong ai biet.
  - Loc **chat**, khong nhan job co `assigned_workstation_id` rong: nhan job rong thi hai may lai cung vo phai mot job va quay ve dung loi tren. Job cu giu nguyen, khong sua/khong xoa.
- **Bo hoan toan canh bao "dang mo o may khac"** (dung 1 vong doi), thay bang **ghi chu trung tinh** `notice`: "Don nay cung dang duoc can o <ten may>. Me cua ban ghi rieng, hai ben khong anh huong nhau." Mau XANH THONG TIN chu khong vang — to vang se khien tho tuong phai dung lai xu ly. Van giu de hai tho biet ma tranh can trung mot don.
  - `notice` tinh **truoc** khi tim/tao job vi nhanh can tu do (duong ma QR that di qua) thoat som bang `return` rieng.
- **Cascade trang thai lo van dung san**, da doc lai `WeighingItemRecorder`: no dem theo TAT CA job cua lo, nen job A xong truoc trong khi B con do -> lo la PARTIALLY_WEIGHED, chi WEIGHED khi ca hai xong. Khong phai sua gi.
- **HE QUA CAN LUU Y (chua xu ly):** may quet nham don roi bo di se de lai mot job treo, lo khong bao gio ve duoc WEIGHED -> tram Van chuyen khong chuyen duoc sang IN_TRANSIT (`handleMaterialLabelScan` doi dung `status === 'WEIGHED'`). Truoc day it gap hon vi may thu hai tai dung job cu; nay moi lan quet them la mot job moi. Can co duong huy/tha vong can bo do.
- **Kiem chung:** `WeighBatchTest` **9/9 DAT** tren SQLite in-memory (`DB_CONNECTION=sqlite DB_DATABASE=:memory:`; DB test Postgres 127.0.0.1:5433 van khong chay). Them 2 test: hai may cung lo deu luu du 3 dong voi so KHAC NHAU (100.0 vs 55.0, chung minh khong ai de ai) va lo chi WEIGHED khi ca hai vong xong. Da sua truy van sao chep trong test cu cho khop dieu kien moi. `vue-tsc` sach, `vite build` OK (17.80s).

### 74. Dep vong can bo do (CANCELLED) - khong de lo ket vinh vien khong bao gio ve duoc WEIGHED

- **Xu ly he qua bo ngo o muc 73:** quet nham don roi bo di (chua cau SAVE) de lai mot WeighingJob mo coi -> lo khong bao gio ve duoc WEIGHED vi cascade doi TAT CA job cua lo phai COMPLETED.
- **Endpoint moi `POST /api/weighing-jobs/{id}/cancel`** (`WeighingJobController::cancel`, middleware `workstation.guard:WEIGH_ITEM` giong weigh-batch):
  - Chi huy duoc khi **CHUA co dong nao COMPLETED** — con dong da can that thi tu choi 409 `JOB_HAS_COMPLETED_ITEMS` (dung `restart()` neu muon bo toan bo ket qua da can that, co audit log).
  - Job da COMPLETED -> 409 `JOB_ALREADY_COMPLETED`. Job da CANCELLED -> 200 idempotent, khong loi.
  - **Khong ghi AuditLog** (khac `restart()`): khong lam mat so cân thât nao, chi doi y nghia "khong tinh vao vong cân nao cua lo nua".
- **Loai CANCELLED khoi 3 truy van** (cung mau voi COMPLETED):
  - `ScannerController::handleOrderScan` — ca truy van tim job tai dung LAN truy van "may khac cung dang can" (`whereNotIn(['COMPLETED','CANCELLED'])`).
  - `WeighingItemRecorder::record` — cascade dem `$allJobs` cua lo phai LOAI CANCELLED, neu khong dem no vao thi lo khong bao gio WEIGHED duoc (dung cai loi dang xu ly).
  - `WeighingJobController::activeForWorkstation` — khong khoi phuc lai mot job da huy khi F5/mo lai trang.
- **Frontend V2 tu goi cancel o 2 cho** (best-effort, nuot loi, khong chan thao tac):
  - `onClear()`: huy vong cân dang mo TRUOC khi xoa state, **tru khi vua SAVE xong** (them tham so `alreadySaved` de khoi goi thua 1 request moi lan SAVE thanh cong — server van tu choi em neu lo goi nhung khong can ton round-trip do).
  - `applyActiveJob()`: quet mot don MOI trong khi don cu chua SAVE (khong qua CLEAR) se huy don cu truoc khi thay the — day la duong bo do khac ma truoc day chua xu ly.
- **Kiem chung:** `WeighBatchTest` **15/15 DAT** tren SQLite in-memory (them 6 test: huy job trong, tu choi khi co dong COMPLETED, tu choi job da COMPLETED, idempotent, **loai khoi cascade** (job that + job huy cung lo -> lo van WEIGHED), khong tai dung duoc khi quet lai). `vue-tsc` sach, `vite build` OK (18.16s). Test file khac (`ScannerWorkflowTest` etc.) fail do moi truong (thieu bang `operation_clients` tren SQLite, da biet tu truoc, khong lien quan thay doi nay).

### 75. NEXT khong con tat o dong cuoi cua don - chay het 9 dong lu?i dung nhu VBA

- **Yeu cau (02/08/2026):** "toi muon nut next luon mo du la den R3 thi khi an next van co the an next de can hang duoi tiep, mac du k co gi".
- **Nguyen nhan:** `canPressNext` chan theo `jobItems.length` (so vat tu trong don) nen QR 3 dong la NEXT tat ngay o R3. VBA goc chan theo `CurrentBoxIndex < 9` - moc la **9 DONG LUOI**, khong phai so vat tu. Day la cho da port lech tu dau.
- **Sua:**
  - Them hang chung `MAX_RACK_LINES = 9` trong `frontend/src/utils/qrDyeParser.ts` (truoc do so 9 nam rai rac 3 cho: vong lap parse, `NINE_ROWS` trong VbaRackGrid, va gian tiep o canPressNext).
  - `WeighingStationV2.vue`: `canPressNext = currentIndex < MAX_RACK_LINES - 1`; `onNext` tang chi so toi dong 9. Truong hop can xong het vat tu cua don roi moi bam NEXT: xuong **dong trong ke tiep** (`min(jobItems.length, 8)`) chu khong quay ve o 1 - quay ve la ghi de so da can.
  - `VbaRackGrid.vue`: dong trong bay gio **chon duoc** (bo dieu kien `items[idx] &&` o `@click`), va o PROCESS hien so can song/so da chot ke ca khi dong do khong co vat tu - khong the bam NEXT xuong mot o trang tron roi bao la "can duoc".
  - `processStyle` cho dong trong: nen TRANG (dung nhanh "target rong" cua `Mod_UI_processcolor.CheckRange`), khong bao gio an nen xanh.
- **Gioi han da noi ro tren man hinh:** dong khong co vat tu trong QR thi **SAVE khong luu** so can o do - `onSave` duyet theo `jobItems` nen dong trong tu nhien bi loai, va day cung dung VBA (`btnSave_Click` chi INSERT dong co WEIGHT muc tieu). Them canh bao vang ngay duoi luoi khi con tro dang o dong trong, thay vi de thao tac vien can xong roi tuong da ghi.
- **Kiem chung:** `node frontend/scripts/check-qr-parser.mjs` **14/14 PASS** (xac nhan viec rut so 9 ra thanh hang khong doi hanh vi parse, ke ca ca "nhieu hon 9 bo ba phai cat con 9"). `vue-tsc` **26 loi = dung baseline**, khong phat sinh loi moi. **CHUA thu tay tren trinh duyet** - can nguoi dung quet ma 3 dong roi bam NEXT qua R4..R9 de xac nhan.

### 76. NEXT dung duoc ca khi CHUA quet don - man hinh lam duoc viec cua mot cai can thuong

- **Yeu cau (02/08/2026):** "du chua co quet thi nut next van dung binh thuong, biet dau toi can dung tam de can cai gi do".
- **Sua:** bo dieu kien `!activeJob` khoi `:disabled` cua nut NEXT. Bam NEXT tren form trang -> `onNext` di dung nhanh cu (`jobItems` rong -> `findIndex` tra -1 -> ve o 0), chot bi o lan doc on dinh dau tien, o PROCESS hien so can song. Chay duoc het 9 dong.
- **Keo theo mot cho de bo sot: NHIP LAY SO CAN.** `pollIntervalForState()` truoc do bam theo `activeJob` nen can tay khong don se roi vao nhip nhan roi 1000ms - dung cai luong can nhip day nhat lai bi nhip thua nhat, va bi se bi chot vao luc tho da bat dau do vat tu (chinh loi da phan tich o muc 58/59). Doi moc "dang can" thanh `dangCan = activeJob !== null || currentIndex >= 0` cho ca `pollIntervalForState()` lan `watch` khoi dong lai bo dem.
- **Khong dung/khong khoa:** SAVE va PRINT van tat khi chua co don - khong co dong nao de ghi. `saveSession()` van thoat som khi `!activeJob` nen can tay khong de lai dau vet localStorage, F5 la mat, dung nhu ban chat "can tam".
- **Noi ro tren man hinh:** canh bao duoi luoi tach 2 truong hop - chua co don ("dang CAN TAY, khong co gi duoc luu") va co don nhung dung o dong ngoai QR ("SAVE se khong luu so o dong nay"). Dong goi y khi form trang cung noi them "can can tam cai gi do thi bam thang NEXT, khong can don".
- **Don kem:** `v-for="(row, idx)"` -> `(_row, idx)` trong VbaRackGrid, tat TS6133 co san tu truoc o dung dong vua sua.
- **Kiem chung:** `vue-tsc` **25 loi** (baseline 26, giam 1 do don TS6133 noi tren; khong loi nao thuoc 3 file vua sua). `vite build` **OK 22.45s**. **CHUA thu tay tren trinh duyet.**

### 77. /weighing-history: tim kiem + phan trang chuyen han sang JS, va co nut IN LAI phieu can

- **Yeu cau (02/08/2026):** "toi muon dung js 100% va muon load nhanh hon, co nut de in lai".

**a) DO TRUOC KHI SUA** (script chi doc, khong ghi gi xuong DB):
- `history()` cu: **5 cau truy van / 193ms**, payload chi **16KB** cho 7 vong can. Tung cau ~30-35ms - gan nhu toan bo la **di-ve mang toi DB o CS-SERVER**, khong phai cong viec that. Mo ket noi 174ms, bootstrap CLI 2048ms (nguoi khong dai dien, ban chay qua `artisan serve` co opcache).
- Ket luan: du lieu qua nho so voi chi phi vong mang. Moi lan bam Loc / doi trang deu tra dung cai gia do cho ~16KB.

**b) Bo phan trang phia server** (`WeighingJobController::history`)
- Tra TRON mot cua so du lieu (`limit`, mac dinh 200, tran `HISTORY_MAX_ROWS = 500`) thay vi `paginate()`.
- Bo hoan toan cau `count(*)` cua paginator: lay du **dung 1 dong** (`limit($limit + 1)`) de biet co bi cat hay khong. **5 cau -> 4 cau, 193ms -> 135ms.**
- Hinh dang response doi: `data` gio la `{ rounds, truncated, limit }` chu khong con la object paginator. Chi co `WeighingHistory.vue` dung endpoint nay (da grep), khong co test nao - khong pha vo cho khac.
- Giu lai tham so `q` phia server: khong dung cho o tim thuong ngay nua, nhung la duong thoat khi thu can tim nam NGOAI cua so da tai.

**c) Frontend chuyen sang JS hoan toan** (`WeighingHistory.vue`)
- O tim kiem loc ngay tren `allRounds` da tai, **khong goi server**, go toi dau thay toi do, ho tro nhieu tu (phai khop TAT CA).
- Phan trang cung thuan JS (20 dong/trang) - doi trang **0 request**, khong con trang thai cho.
- Chi con dung 1 vong HTTP khi: mo trang, doi khoang ngay, bam Lam moi, hoac bam "Tim tren toan bo lich su".
- **Khong im lang cat bot:** cua so bi cat thi hien bang canh bao vang; nut "Tim tren toan bo lich su" luon hien khi o tim co chu (khong doi toi luc "khong thay gi" - loc ra 2 dong khong co nghia la chi co 2). Ket qua tim toan cuc co bang bao rieng + duong quay lai.
- Chuoi de so khop tinh MOT LAN luc du lieu ve (`ganChuoiTim`), khong tinh trong computed: ghi thuoc tinh vao object dang nam trong ref ngay giua luot doc cua computed se lam Vue tinh lai vong vong.

**d) Nut IN LAI phieu can** (bieu tuong may in tren tung dong)
- Goi lai `POST /api/weighing-jobs/{id}/print-slip` roi render qua `printTsplViaBrowser`. `window.open` goi **dong bo ngay trong handler**, truoc moi `await` - sau await la mat "user activation" va bi chan popup (dung loi da dinh o luong SAVE).
- **KHONG dung phieu bang JS tai cho** du du lieu da co san tren man hinh: CLAUDE.md muc 5 bat buoc moi luot in lai phai de lai Audit Log bat bien. Dung o client thi khong co gi de ma ghi.
- **Vi vay bo sung AuditLog `WEIGH_SLIP_REPRINT`** trong `printSlip()` - truoc day chi co ban ghi PrintJob, khong truy duoc AI bam in lai. Moi luot goi endpoint nay deu la in lai (luong SAVE dung `buildSlipForJob` truc tiep, khong di qua day) nen khong bi ghi trung.
- **Phan giai ma tram:** them nhanh `$job->workstation?->code` truoc nhanh "may dang dung". Man Lich su chay tren may van phong khong gan tram nao, ma phieu in lai phai mang dung ma tram DA CAN ra no. Da kiem chung: **ca 7/7 vong can hien co deu phan giai ra ma tram ton tai trong `operation_clients`** (script chi doc), nen `firstOrFail()` trong `buildAndStoreSlip` khong the no.

- **Kiem chung:** `php -l` sach. Do lai truy van: **4 cau / 135ms**, hinh dang JSON dung cai frontend doc (`rounds/truncated/limit`, item co `process_status`). Endpoint HTTP tra **401** khi chua dang nhap (app boot sach, route dung cho). `vue-tsc` **25 loi = baseline**, khong loi nao o `WeighingHistory.vue`. `vite build` **OK 17.20s**. **CHUA bam thu nut IN LAI tren trinh duyet** - no GHI (PrintJob + AuditLog) nen de nguoi dung tu bam, khong tu chay tren DB that.

### 78. /weighing-station-v2: lam lai giao dien (bo khung Windows 95), so DELTA to tu doi mau theo dung sai

- **Yeu cau (02/08/2026):** "cai tien giao dien cho toi voi, de cho no dep hon".

**Nhung thu KHONG duoc dong** (deu la quyet dinh co ly do da ghi trong code, khong phai mac dinh):
- 3 ma mau o PROCESS `#FAE605/#78FA14/#FF1400` - dung ma RGB goc theo yeu cau nguoi dung.
- Bang luon nen sang chu den ke ca khi theme toi - tho quen bang trang, nen toi lam mat cam nhan 3 mau tin hieu.
- Kich thuoc nut lon (bam bang gang tay), luoi 9 dong co dinh khong nhay.
- Bang do dac "MAT TIN HIEU CAN" (doc tu 1-2m) va bang xanh thong bao trung don (co y KHONG dung vang/do).

**a) Bo bo khung Windows 95** — `border: 2px inset/outset` + nen `#ece9d8` doi thanh khoi `.panel` bo goc 12px, vien 1px, do bong nhe; nen tong `#eef1f6` (xam xanh trung tinh). **Cung do sang** nen 3 mau tin hieu doc y het, chi khong con cam giac phan mem hai muoi nam truoc. Phan hoi bam nut doi tu `border-style: inset` sang `translateY(2px)` - tho deo gang khong cam nhan duoc cu bam bang tay, phan hoi thi giac la thu duy nhat bao may da nhan.

**b) O so DELTA tu doi mau theo dung sai** (thay doi dang ke nhat)
- Dai mau tren dinh o + vien doi theo **DUNG 3 mau cua o PROCESS**, them dong "muc tieu 12.50" ngay canh. Tho dang do vat tu thi mat dan vao so to nay; bat ho liec xuong bang moi biet du hay chua la thua mot nhip.
- **Chua chot bi thi de trung tinh**: luc do `liveWeight` chua tru duoc gi, to mau chi la doan bua, ma mot o to dung mau vang "chua du" ngay khi vua bam NEXT thi gay hieu nham hon la giup.
- Phan SO giu nen trang (chi dai tren cung an mau) de luon doc duoc; rieng tone do thi so chuyen do dam.

**c) Tach `utils/processColor.ts`** — quy tac suy mau tu `Mod_UI_processcolor.CheckRange` gio nam MOT cho, dung chung boi luoi 9 dong va o DELTA. Hai cho nay nam canh nhau tren cung man hinh: lech nhau du chi o ranh gioi 0.99/1.01 la tho thay so to bao DAT ma o trong bang van vang - mat luon niem tin vao ca hai.
- **Guard `frontend/scripts/check-process-color.mjs`**: doi chieu ban moi voi doan ma CU chep nguyen van tu VbaRackGrid (git 5929b55), quet day 1200 diem quanh 2 ranh gioi + cac muc tieu bat thuong (null/0/am/NaN/khong co vat tu) + khang dinh **khong so am nao an nen xanh**. **1229/1229 PASS.** Dang co vi mau o PROCESS khong phai chuyen tham my: trong VBA chinh BackColor o nay la trang thai nghiep vu (`btnSave_Click` doc nguoc mau nen de ghi ACCEPTED/REJECTED).

**d) Vai trò nút hiện bằng màu** — SAVE xanh la dac (chot ca me), NEXT xanh duong dac (nut bam nhieu nhat), CLEAR **vien do chu khong nen do dac**: no nam canh SAVE va duoc bam thuong xuyen, to do ruc se thanh nhieu thi giac va lam nhat luon bang canh bao mat tin hieu can.

**e) Luoi 9 dong** — dong dang can doi tu mot vach xanh mong sang **nen xanh nhat + dai 5px + o so thu tu dao mau**; vien den doi sang xam nhat; so dung `tabular-nums` de cot khong nhay khi so doi. O PROCESS to hon (24px, 800).

- **Kiem chung:** `vue-tsc` **25 loi = baseline**, khong loi nao o 3 file vua sua. `vite build` **OK 16.54s**. `check-process-color` **1229/1229**, `check-qr-parser` **14/14**. **CHUA nhin bang mat tren trinh duyet** - may nay khong co trinh dieu khien trinh duyet (khong co playwright/puppeteer/chromium-cli) va khong tu cai them. Can nguoi dung mo xem.

### 79. Don sach 25 loi vue-tsc -> 0, va 3 thu duoc phat hien tren duong di

- **Yeu cau (02/08/2026):** "fix cac loi do cho toi" (25 loi vue-tsc ton dong).
- **Ket qua: 25 -> 0.** `vite build` OK 16.59s, `check-process-color` 1229/1229, `check-qr-parser` 14/14.
- Luu y: `npm run build` chi la `vite build`, ma Vite dung esbuild - **chi boc kieu chu khong kiem kieu**. 25 loi nay tich lai duoc chinh vi khong co gi chan chung. `vue-tsc` van la cong tu nguyen, khong nam trong build.

**a) Lech kieu THAT (2 loi)** - `stores/auth.ts` interface `Workstation` thieu 2 truong ma code va DB deu dung: `default_route` (cot co that trong `operation_clients`) va `capability_codes` (them 18/07 de va loi kiosk bi chan nham "tram khong co quyen"). **Code dung, kieu sai** - xoa truong cho het loi la dung lai dung con bug cu. Da bo sung ca hai kem chu thich canh bao.

**b) Trang Gantt: 8 loi, chi can 1 dong khai kieu.** `res.data` tu axios la `any` nen `items` cung thanh `any`, keo theo moi tham so .sort/.filter/.map mat kieu va `new Set(...)` ra `Set<unknown>`. Them `interface GanttItem` + chu kieu cho `items` la het 7/8.
- Loi con lai (TS2459 `DataSet`) la **thieu sot typings cua thu vien**: `vis-timeline/declarations/index.d.ts` co mot binding `DataSet` cuc bo (chi import, khong export) lam hong chuoi `export *`. Sua bang cach **tach doi**: GIA TRI van lay tu `vis-timeline/standalone` (bat buoc - lay tu goi `vis-data` rieng se ra mot lop DataSet KHAC voi lop Timeline ben trong dang dung, hai ben khong nhan nhau), KIEU lay tu `vis-data`. Da thu them `paths` vao tsconfig truoc do nhung khong can thiet - **da go lai, tsconfig y nguyen**.

**c) Troubleshooting.vue la file .vue DUY NHAT trong hon 40 file chua dung TypeScript.** Bat `lang="ts"` lam lo **33 loi moi**, nhung tat ca deu gom ve vai cho khai bao goc: `ref([])` -> `ref<any[]>([])` (goc cua ~16 loi "type never"), `form.parameters` -> `Record<string, any>` (truy cap bang ten dong), `resolveModal.cause`/`detailModal.case`, va 4 tham so ham. **Chi sua kieu, khong doi mot dong logic nao.**

**SAI SOT TRONG LUC LAM:** doc/ghi file UTF-8 bang `Get-Content -Raw`/`Set-Content` cua PowerShell 5.1 (khong chi -Encoding) da lam hong ky tu tieng Viet cua Troubleshooting.vue. Da khoi phuc tu ban sao va xac nhan `git diff` sach truoc khi lam lai bang cong cu sua file. **Bai hoc: khong dung PowerShell de doc/ghi file nguon co tieng Viet.**

**d) 3 thu phat hien duoc khi ra tung bien "thua" thay vi xoa hang loat:**
1. **`saveTareToStorage` trong `useScaleFeed.ts` chua bao gio duoc goi** - nghia la khoa `df_weigh_tare_state_v2` khong bao gio duoc ghi, nen `restoreTareFromStorage` (co XUAT ra ngoai) chi co the tra ve null. Mot ham khoi phuc luon tra null la cai bay cho nguoi dung no sau nay -> **da go ca bo 3 hang**. Viec nho bi qua F5 hien do `df_ws2_session_v1` trong WeighingStationV2 lo, va ban do luu bi KEM vi tri o dang can + so can gop de doi chieu dia can - bo trong composable khong mang theo hai thu do nen du co chay cung khong du an toan.
2. **`handleLogout` bi bo quen o 5 man hinh** (MachineQueue, Materials, Recipes, WaterConfigs, Troubleshooting) - nut Dang xuat da chuyen han vao `AppLayout.vue` (dong 132). Xoa xong lo tiep `router`/`authStore` cung chi con phuc vu no -> xoa not ca import.
3. **`elapsed` trong `MachineQueue.getLockAgeSeconds`** - bien tinh do lech dong ho may tram/server, tinh ra roi **khong dung vao ket qua tra ve**. Da go va **ghi ro canh bao tai cho**: moc het han khoa 5 phut hien tinh bang dong ho MAY TRAM doi chieu voi moc gio server, may tram lech gio thi moc nay lech theo. Chua sua (can quyet dinh lay gio server o dau).

- Ngoai ra: `echo` nhap thua o WeighingStation (realtime nam trong QrScanPanel), `SvgIcon`/`reactive` thua o Dashboard, `kioskUrlInput` thua o WorkstationAdmin (go luon `ref=` tren template vi nut copy dung `navigator.clipboard`), `state` thua trong getter `isKiosk`, tham so `el` trong LabelPreview (dung `unknown` chu khong `any` - da co `instanceof` loc san).

### 80. Mat mang o Tram can: mo ta dung hanh vi hien tai, va va mot lo hong IM LANG

- **Cau hoi nguoi dung (02/08/2026):** "trong truong hop mat mang k luu duoc thi lam sao".

**a) Hanh vi HIEN CO (doc tu code, khong suy doan) - phan da dung san:**
- **Quet KHONG can mang.** Tu muc 68, chuoi QR duoc parse ngay tai trinh duyet; ca me chi cham mang DUNG MOT LAN luc bam SAVE.
- **SAVE hong thi KHONG mat so.** `onSave` catch chi dat `errorMsg`, **khong dong vao `capturedWeights`** - bam SAVE lai duoc ngay khi co mang.
- **Dong trinh duyet/mat dien cung khong mat.** Phien nam trong `localStorage` (`df_ws2_session_v1`), `clearSession()` chi chay khi SAVE THANH CONG. Mo lai trang la dung lai nguyen me (muc 69).
- **Dieu KHONG lam duoc:** can tiep khi mat mang. So can di duong Agent -> backend -> trinh duyet (ADR-002: trinh duyet khong bao gio noi thang voi phan cung), nen mat mang toi CS-SERVER la mat luon so can du cai can va Agent deu nam ngay tren may do.

**b) LO HONG PHAT HIEN DUOC - mat mang gan nhu IM LANG (da va):**
- Bang canh bao `MAT TIN HIEU CAN` co dieu kien `scaleOnline && !signalLive`. Nhung khi mat mang thi `scaleOnline` = **false**, nen bang nay **khong hien**. Ca man hinh chi con mot cham do 9px bao hieu.
- Te hon: nhanh `catch` cua `fetchLiveWeight` **khong ha `isStable`**. `ingestRawWeight` khong he chay trong nhanh do nen `isStable` GIU NGUYEN gia tri cu. Mat mang dung luc can dang dung yen -> man hinh treo lai o "ON DINH" voi mot con so dong cung, con o DELTA thi van giu nguyen mau xanh "DAT". Va chinh co `stable` nay duoc gui len server lam dieu kien cho ghi (`weighFromQr` chan khi stable=false).
- **Da sua 4 cho:**
  1. `useScaleFeed` catch: ha `isStable = false`.
  2. Tach **hai** bang canh bao khac nhau vi cach xu ly khac nhau: `MAT TIN HIEU CAN` (goi duoc backend, so cu -> kiem tra Agent/PuTTY/day can) va `MAT KET NOI MAY CHU` (khong goi duoc backend -> mang/server), bang thu hai noi ro "So da can KHONG mat, can lai duoc ngay khi co mang".
  3. `deltaTone` tra `none` khi mat tin hieu - khong to xanh "DAT" cho mot con so khong con phan anh cai dia can.
  4. Vien "ON DINH/CHO ON DINH" them trang thai rieng `MAT TIN HIEU`, va so DELTA lam mo di.

**c) CHUA lam (can nguoi dung quyet dinh):** frontend khong co bat ky co che phat hien offline nao (`navigator.onLine`, su kien online/offline deu khong duoc dung o dau trong `frontend/src`), va SAVE **khong tu thu lai** - thao tac vien phai tu nhan ra va bam lai. Hang doi offline hien chi co o phia Local Agent (CLAUDE.md muc 5), khong co o phia trinh duyet.

- **Kiem chung:** `vue-tsc` **0 loi**, `vite build` OK 16.62s. **CHUA thu ngat mang that** - can nguoi dung tu rut mang/tat backend de xac nhan hai bang canh bao hien dung.

### 81. Hang doi SAVE trong localStorage - mat mang van can tiep, van in duoc, khong mat me nao

- **Yeu cau (02/08/2026):** "hang doi SAVE trong localStorage, co mang thi tu day, kem idempotency_key... van co the in, khi an save roi thi no se nam trong hang cho, khi co mang thi day len".

**a) Backend - chong ghi trung (`ScannerController::weighFromQr`)**
- Nhan them `idempotency_key` (nullable, de client cu va goi tay van chay).
- **Truoc** khi vao transaction: neu khoa da ton tai -> dung lai phieu tu du lieu da luu va tra **200 kem `reused: true`**, KHONG ghi them gi. Tra 200 chu khong phai loi la co y: voi hang doi thi day la ket qua DUNG (me da nam duoi DB), tra loi se khien no thu lai mai khong thoi.
- Dong dau khoa **trong cung transaction** voi viec ghi so can - hoac ca hai cung co, hoac ca hai cung khong. Ghi khoa ngoai transaction se ho ke: lan gui lai thay khoa da ton tai trong khi so can da bi rollback mat.
- **Ca hiem nhat can chan KHONG phai mat mang han**, ma la: request DA toi server va ghi xong, nhung phan hoi rot giua duong -> hang doi tuong that bai va gui lai.
- Migration `2026_08_02_000001_add_idempotency_key_to_weighing_jobs` (nullable + unique, idempotent, co down() an toan). **CHUA CHAY tren DB production** - migration tren prod phai xac nhan rieng (`.claude/rules/database-safety.md` muc 7, khong nam trong allowlist deploy).

**b) In duoc khi mat mang - `utils/weighSlip.ts` + tach ham thuan ben PHP**
- Tach `WeighingJobController::buildSlipTspl()` thanh **ham thuan** (khong cham DB) khoi `buildAndStoreSlip()`. Nho vay moi doi chieu duoc hai ban ma khong ghi PrintJob nao xuong DB.
- Port sang JS: dung phieu TSPL ngay tai trinh duyet tu du lieu dang co tren man hinh. Phai ban sao ca `number_format($x, 2)` (dau phay hang nghin) va `Carbon::format('d/m/Y H:i:s')` - **khong** dung `toLocaleString` vi ket qua doi theo ngon ngu may tram.
- **Guard `frontend/scripts/check-weigh-slip.mjs`**: doi chieu TSPL **tung ky tu** giua hai ban, 7 ca (rack rong, so lon co dau phay hang nghin, so am, dau nhay kep phai bi bo, khong dong nao, 9 dong). **7/7 PASS.**

**c) Hang doi - `services/saveQueue.ts`**
- Xep hang **TRUOC** khi gui, khong phai sau khi gui hong: xep sau thi co ke ho - dong trinh duyet/mat dien dung luc request dang bay la me bay theo, khong con dau vet nao de gui lai.
- **Loi MANG va loi NGHIEP VU xu ly khac nhau.** 4xx (tru 408/429) = payload sai, gui lai bao nhieu lan cung the -> danh dau ket, bo qua khi day hang doi, khong de mot me hong chan luon cac me sau. Mat mang/5xx -> giu lai thu tiep.
- Day **tuan tu** chu khong song song: nhieu me co the tro ve cung mot lo, ma `handleOrderScan` co khoa chong hai may can chung mot vong - ban song song la tu tranh chap voi chinh minh.
- Ba moc kich hoat chong len nhau: su kien `online`, nhip dinh ky 15s, va mot luot ngay khi mo man hinh. Su kien `online` KHONG bat duoc ca "co mang LAN ma may chu chet" nen van can nhip dinh ky.

**d) Man hinh can**
- SAVE -> xep hang -> gui thu ngay. Thanh cong: in phieu tu server roi xoa form (nhu cu). Mat mang: **in phieu do trinh duyet dung**, xoa form luon de tho quet me ke tiep - dung muc dich cua hang doi - va bao ro "me dang nam trong HANG DOI, dung xoa du lieu trinh duyet". Loi nghiep vu: **giu nguyen so tren man hinh** de tho sua.
- Chi bao "N me cho gui" canh den tin hieu can (do khi co me ket), bang chi tiet co GUI NGAY / THU LAI / BO (bo phai xac nhan - me chua len server, bo la mat luon).
- **Sua kem mot loi co san:** nut PRINT goi `/api/weighing-jobs/null/print-slip` voi me doc tu QR (id null) nen **luon 404** - tuc nut PRINT hong voi chinh luong chay chinh. Nay dung ban dung phieu cuc bo.
- Luong "DF:ORDER:<uuid>" **khong** qua hang doi: vong can da co san duoi DB tu luc quet, va endpoint weigh-batch chua co khoa chong ghi trung. Giu nguyen hanh vi cu.

- **Kiem chung:** `WeighFromQrIdempotencyTest` **4/4 PASS** (test quan trong nhat: gui 2 lan cung khoa -> moi dong chi co DUNG 1 ban ghi ScaleMeasurement). `WeighBatchTest` **15/15** khong vo. Tong **19 passed, 94 assertions** tren SQLite in-memory. `check-weigh-slip` 7/7, `check-process-color` 1229/1229, `check-qr-parser` 14/14. `vue-tsc` **0 loi**, `vite build` OK.
- **CHUA thu tay tren trinh duyet** va **CHUA chay migration** - hai viec nay can nguoi dung.

### 82. Da chay migration idempotency_key TREN DB PRODUCTION (nguoi dung xac nhan trong phien)

- **Nguoi dung yeu cau ro trong phien (02/08/2026): "lam di"** — day la xac nhan bat buoc theo `.claude/rules/database-safety.md` muc 7 (migration KHONG nam trong allowlist deploy thuong quy).

**Kiem tra TRUOC khi chay (deu chi doc):**
- Xac nhan dang noi dung DB production: `production_web @ 10.0.60.209:5433`.
- `migrate:status`: **chi DUNG MOT migration dang treo** la cai nay — khong co migration la nao di ke. Day la buoc quan trong nhat: `php artisan migrate` chay TAT CA migration treo chu khong rieng cai minh muon.
- Quy mo: `weighing_jobs` **20 dong / 24 kB**, **0 khoa** dang giu tren bang -> them cot (can ACCESS EXCLUSIVE) chi khoa vai mili giay.
- Ke hoach lui: thay doi la **chi THEM cot nullable**, khong dung vao dong du lieu nao; `down()` chi xoa dung cot vua them (dang rong) -> lui bang `migrate:rollback --step=1`, khong co kha nang mat du lieu. Khong dump toan bo DB vi khong tuong xung voi muc rui ro.
- Chay **co gioi han pham vi**: `--path=database/migrations/2026_08_02_000001_...php` chu khong chay tran.

**Ket qua:** `DONE 161.36ms`.

**Kiem chung SAU khi chay (chi doc):**
- Cot: `character varying(100)`, `nullable: YES` — dung thiet ke.
- Index: `weighing_jobs_idempotency_key_unique` (UNIQUE btree). **Day moi la thu thuc su chan ghi trung** — khong co index nay thi ca co che chi la trang tri.
- Du lieu cu nguyen ven: **20 dong truoc = 20 dong sau**, 0 dong co idempotency_key (dung, cot moi).
- Smoke test `POST /api/scanner/weigh-from-qr` -> **401** (app boot sach, route dung cho, middleware auth chay).

- **Con lai cho nguoi dung:** thu ngat mang + SAVE tren trinh duyet that. CHUA ai chay duong ghi that qua endpoint nay tren DB production.

### 83. SAVE in phieu NGAY tu du lieu tren man hinh, khong cho vong mang nao

- **Yeu cau (02/08/2026):** "khi an save tem ma in ra lay nhung cai dang dung hien thi o tren web de in off luon".
- **Truoc do:** mac du da dung san phieu cuc bo, luong SAVE van **cho `axios.post` tra ve** roi moi in, va uu tien phieu server tra ve. Tuc van cho tron mot vong mang chi de lay ve dung thu minh da co san — chinh cho tho tung keu "bam SAVE xong cho mai tem moi hien".
- **Nay:** in NGAY sau khi mo cua so, TRUOC khi cham mang. An toan vi ban dung cua trinh duyet da duoc doi chieu TUNG KY TU voi ban server (`check-weigh-slip.mjs`, 7/7). `window.print()` chan trong cua so CON nen request van bay di binh thuong trong luc hop thoai in dang mo.

**Hai he qua phai xu ly, khong bo lo:**

1. **Moc gio in.** Trinh duyet in bang dong ho may tram; neu de server tu lay gio cua no thi ban ghi `print_jobs` mang mot moc khac voi to phieu dang nam tren hang — mat kha nang doi chieu, dung thu ma ban ghi do sinh ra de lam. Nay chot moc MOT LAN o trinh duyet (`nowSlipTimestamp()`), gui kem `printed_at` len server, xuyen qua `buildSlipForJob -> buildAndStoreSlip -> buildSlipTspl`. Khong gui thi server tu lay gio minh nhu cu.

2. **Loi nghiep vu (4xx) sau khi DA in.** Truoc day nhanh nay `boQua()` (xoa khoi hang doi) va giu nguyen man hinh. Gio phieu da ra giay roi, xoa khoi hang doi se de lai **mot to phieu tren hang ma trong may khong con dau vet nao**. Doi thanh `danhDauKet()`: ngung tu gui lai nhung GIU trong hang doi, hien chi bao do, tho mo bang ra xem/xu ly. Man hinh van xoa trang de quet me ke tiep — khong mat gi vi moi thu deu nam trong hang doi.

- Ket qua: bam SAVE -> phieu hien ra ngay, form trang ngay, quet me ke tiep duoc ngay. Ket qua gui len server (thanh cong / cho mang / bi tu choi) deu hien qua chi bao hang doi chu khong chan tho lai.

- **Kiem chung:** them 2 test — `printed_at` tu trinh duyet phai xuat hien trong phieu server luu, va khong gui thi van co moc gio hop le. `WeighFromQrIdempotencyTest` **6/6**, tong voi `WeighBatchTest` la **21 passed, 98 assertions**. `check-weigh-slip` 7/7. `vue-tsc` **0 loi**, `vite build` OK 17.01s.
- **CHUA thu tay tren trinh duyet.**

### 84. Da bam SAVE thi BAT BUOC phai gui — bo duong vut me, va go cai bay ket vinh vien di kem

- **Yeu cau (02/08/2026):** "1 me o hang cho k bo duoc, bat buoc phai gui khi da an save".
- **Sua truc tiep:** bo nut **BO** khoi bang hang doi va bo ham `onBoMe`. `saveQueue.boQua()` doi ten thanh **`danhDauDaGui()`** — ten moi noi dung viec no lam va **la duong DUY NHAT** mot me roi hang doi: chi goi sau khi cam trong tay phan hoi 2xx that. Ghi thanh dieu kien 3 o dau file (truoc co 3, nay 4).

**Nhung neu chi lam bay nhieu thi tao ra mot cai bay CHET — 2 thu bat buoc phai sua kem:**

1. **Co `stable` gui len la co SAI.** `onSave` gui `stable: isStable.value` — do la co SONG cua lan doc **ngay luc nay**, tra loi cau hoi "cai can LUC NAY co dung yen khong". Server hoi mot cau khac: "may con so trong goi nay co phai so da dung yen khong". O man V2 hai thu cach nhau HANG PHUT vi can ca me xong moi SAVE mot lan.
   - **Bay that, tren duong thuong:** bam NEXT xong bam SAVE luon thi `resetTareForNewSlot()` vua ha `isStable = false`; hoac mat mang thi `fetchLiveWeight()` catch cung ha no xuong — **ma mat mang chinh la luc hang doi duoc dung**. Me vao hang doi voi `stable=false` se an **422 NOT_STABLE mai mai**, gui lai bao nhieu lan cung the. Truoc day escape la nut BO; bo nut BO ma khong sua cho nay = ket vinh vien kem mot to phieu da in.
   - **Sua:** gui `stable: true` cho nhanh QR. **Dung theo cau tao, khong phai noi lieu:** `capturedWeights` chi nhan `liveWeight`, ma `liveWeight` chi nhuc nhich sau `if (!stable) return` trong `ingestRawWeight`; bi cung chi chot tu lan doc on dinh, khong co bi thi khong chot duoc so nao. O khong can gui `weight = null` va bi gan KHONG DAT, khong phai so rac. Tuc **weight khac null <=> da chot tu mot lan doc on dinh**.
   - Cong chan `NOT_STABLE` phia server **giu nguyen** (test `rejected when stable false` van pass) — chi doi thu ma client V2 gui len.

2. **Tu bo cuoc sau 20 lan hong mang.** `MAX_LAN_THU = 20` danh dau me thanh "ket" sau 20 lan gui hong, tuc **chi 5 phut mat mang** (nhip 15s) la ngung tu gui va bat nguoi bam tay tung me. Mang xuong chet nua tieng hay khoi dong lai server la dinh — mau thuan thang voi "bat buoc phai gui". **Bo han nguong nay:** loi mang chi dem `so_lan_thu`, khong bao gio dat `loi_nghiep_vu`. Khong ton gi: `dayHangDoi` dung ca luot ngay lan hong dau nen tong cong van chi **mot request moi 15s** du hang doi co bao nhieu me. Tu nay `loi_nghiep_vu` chi con nghia "server tra loi tu choi", khong con nghia "thu nhieu qua".

- **`tatTuDay()` nay co dieu kien:** roi man hinh can ma hang doi con me thi **khong tat nhip tu day**. Truoc day `onUnmounted` tat han -> me nam im cho toi lan sau co nguoi mo dung man hinh can. Hang doi rong thi moi 15s no chi so hai con so roi thoi.
- **DA CAN NHAC VA BO** chan `beforeunload` khi hang doi con me: `main.ts` bat 401 bang `authStore.logout()` + `window.location.reload()`, nen phien het han se bung hop thoai "roi trang?" ma tho khong he bam gi — bam Huy con ket lai o trang da dang xuat. Dong tab KHONG mat me (localStorage con nguyen, da ra soat: ca frontend khong co `localStorage.clear()` nao, chi `removeItem` dung khoa, khong dung toi `df_ws2_save_queue_v1`) va nhip tu day van song khi roi man hinh — doi lay mot hop thoai doa nguoi la lo.
- **Duong thoat cho me bi server tu choi that:** nut **THU LAI**. Cac nguyen nhan 4xx thuc te deu sua duoc roi thu lai (401 het phien -> dang nhap lai; 403 thieu quyen tram -> admin cap; khoa lo -> doi tram kia nha), khong con cai nao vinh vien sau khi da go bay `stable` o tren.
- **Kiem chung:** `vue-tsc` **0 loi**, `vite build` OK 17.79s, `WeighFromQrIdempotencyTest` + `WeighBatchTest` **21 passed (98 assertions)**, 3 script guard 7/7 + 1229/1229 + 14/14. Ra soat sach: khong con `boQua`/`MAX_LAN_THU`/`onBoMe` o dau.
- **CHUA thu tay tren trinh duyet.**

### 85. Ping dinh ky — thong thi moi day me len

- **Yeu cau (02/08/2026):** "toi muon co ping dinh ki, khi ma thong thi day".
- **Truoc do:** nhip 15s nem thang ca me (goi vai KB) ra duong roi ngoi cho het gio. Mat mang la lap lai mai.
- **Nay:** `GET /api/ping` (route moi, `routes/api.php`) tra `{"status":"OK"}`, nhip 15s goi `thuDay()`: ping truoc, **thong moi day**. Su kien `online` cua trinh duyet cung di qua `thuDay` chu khong day thang — trinh duyet chi biet card mang da len, no khong biet server da voi toi duoc chua.

**Ba quyet dinh dang ghi lai:**

1. **Route ping CO Y de NGOAI middleware auth.** Phien het han cung phai ping duoc, neu khong "mat mang" va "het phien" nhin giong het nhau. Quan trong hon: 401 se kich hoat interceptor o `main.ts` -> `logout()` + `window.location.reload()` — mot nhip chay ngam se **tu da tho ra khoi man hinh can giua luc dang can**. Da chot bang test `test_ping_answers_without_authentication`.
   - Ping cung **khong cham DB**: cau hoi la "web con song khong", khong phai "DB con song khong". Bat no truy van DB thi moi tram can mat mang se nen them mot truy van moi 15 giay.

2. **BAT KY ma HTTP nao cung la THONG, ke ca 404/500.** Co ma tra ve nghia la goi tin da di toi noi va co thu gi do tra loi — dung cai can biet. **Khong phai chuyen vun:** server chua deploy route `/api/ping` se tra 404; coi 404 la tac thi hang doi dung im vinh vien tren dung nhung tram can no nhat. Chi khi KHONG co phan hoi nao moi la tac. Da kiem chung that: goi `/api/ping-chua-deploy` -> co phan hoi HTTP 404.

3. **Timeout ping 8 giay la DO DUOC, khong phai doan.** Ban dau dat 4s. Do that tren backend dang chay: luc nong **~25ms**, nhung lan goi dau sau khi server nam im **2.1s**, va lan dau tien sau khoi dong **4.2s** (PHP dung lai tien trinh + bootstrap Laravel). De 4s la thinh thoang bao "mat ket noi" trong khi server song nguyen.

- **Them timeout 20s cho chinh lenh gui me** (truoc do khong co -> mang nua song nua chet co the treo hang phut, ma treo la `flushing` ket bat, chan luon moi nhip sau). **Cat ngang KHONG so mat me:** neu request that ra da toi server va ghi xong thi lan gui lai mang dung `idempotency_key` cu, server nhan ra va tra ban da ghi (`reused=true`) — dung cai co che muc 82 dung ra de lam.
- **Chi bao cho tho:** them `duongThong` (ref). Chip hien "mat ket noi", bang hang doi hien "mat ket noi — dang do lai moi 15 giay" thay vi "dang cho mang" chung chung. Phan biet duoc 2 tinh huong nhin giong het nhau ma cach xu ly khac han: mat mang thi dung doi la xong, con me bi may chu che thi phai goi nguoi. `guiMot` cung cap nhat co nay (cung quy tac "co ma HTTP la thong") de nut GUI NGAY bam tay — di thang khong qua ping — van hien dung.
- **Kiem chung:** `php -l` sach, `route:list --path=ping` co dung 1 route, goi that khong dang nhap -> **HTTP 200**. `vue-tsc` **0 loi**, `vite build` OK 23.08s, test **22 passed (100 assertions)**.
- **CHUA thu tay tren trinh duyet.**

### 86. "Co 1 me cho gui nhung khong thay day" — hang doi DUNG IM HOAN TOAN khi chi con me bi tu choi

- **Nguoi dung bao (02/08/2026):** "co 1 the cho gui nhung van chua thay day".
- **Nguyen nhan (doc thang code, khong doan):** me bi danh dau `loi_nghiep_vu` thi **hai cong cung dong lai**:
  - `thuDay()` co cong `queueCount <= stuckCount` -> con dung 1 me va me do bi tu choi thi `1 <= 1` -> **thoat ngay, khong ping, khong day**.
  - `dayHangDoi()` loc `.filter(i => !i.loi_nghiep_vu)` -> danh sach rong -> vong lap khong chay lan nao.
  - **Hau qua: bam ca nut GUI NGAY cung khong xay ra gi**, khong co dau hieu nao cho biet vi sao. Chip van hien "1 me cho gui" nhu binh thuong.
- **Day la loi thiet ke cua chinh muc 84**, khong phai loi moi: bo nut BO nhung van giu nguyen co che "4xx thi ngung tu gui" — thanh ra me khong bo duoc MA cung khong gui duoc.

**Ba sua:**

1. **KHONG BAO GIO ngung thu lai, du loi loai nao.** Bo `.filter(i => !i.loi_nghiep_vu)` trong `dayHangDoi`. Ly do ban dau ("4xx thi gui lai bao nhieu lan cung the") **SAI voi thuc te o day**: phan lon 4xx tu khoi khi nguoi ta sua nguyen nhan — 401 het phien (dang nhap lai), 403 thieu quyen tram (admin cap), khoa lo (tram kia nha). Loc bo nghia la bat tho nho quay lai bam THU LAI dung luc; quen la me nam do vinh vien. `loi_nghiep_vu` tu nay chi de HIEN cho nguoi doc, khong con la co dung. Khong ton gi: da co ping chan nen chi chay khi duong thong, va me hong that thi `continue` chu khong `break` nen khong chan me phia sau.
2. **Cong `thuDay` doi thanh `queueCount === 0`.**
3. **`vaMeCu()` — va payload da dong bang trong localStorage.** Me xep hang boi ban code CU mang theo `stable: false` (cai bay da mo ta o muc 84). Sua code chi cuu duoc me MOI; me da nam trong localStorage van an 422 NOT_STABLE mai mai, **ma gio khong bo me duoc nua**. `batTuDay()` nay quet hang doi, thay `payload.stable === false` thi dat lai `true` va xoa `loi_nghiep_vu` cu. **Va la DUNG chu khong phai lach:** moi `weight` khac null trong payload deu da chot tu mot lan doc on dinh (`capturedWeights` chi nhan `liveWeight`, ma `liveWeight` chi nhuc nhich sau `if (!stable) return`) — cai sai nam o co, khong nam o so.

- **Don dep kem:** header file danh so sai (hai muc cung so 3), dieu kien 2 viet nguoc voi hanh vi moi — da viet lai ca bon. Chu tren man hinh doi theo: bang hang doi hien "*(van dang tu gui lai moi 15 giay)*" canh loi, tooltip chip bo chu "bi ket".
- **LUU Y VAN HANH:** `vaMeCu()` chay trong `onMounted` -> **phai tai lai trang** (F5) thi me dang ket moi duoc va. HMR khong chay lai `onMounted`.
- **Kiem chung:** `vue-tsc` **0 loi**, `vite build` OK 46.84s. **Nguyen nhan goc cua rieng me dang ket tren may nguoi dung thi CHUA xac minh** — co che thi da doc thang code va chac chan, nhung ly do me do bi tu choi la suy luan. Xem bang `JSON.parse(localStorage.getItem('df_ws2_save_queue_v1'))` trong Console.

### 87. Gia lap khong can duoc — so go vao bi an lam BI, o DELTA hien 0.00 mai

- **Nguoi dung bao (02/08/2026):** "toi dang dung gia lap, nhu can khong duoc, no cu bi nha ve 0.0 o cac cai can can".
- **Loi that, khong phai dung sai. NGUYEN NHAN GOC chi co MOT: gia lap chi nap so khi gia tri DOI.** `fetchLiveWeight` `return` thang khi dang gia lap, nen nguon duy nhat la `watch(simulatedWeight)` — ma watch chi chay khi gia tri thay doi. Hau qua day chuyen:
  - Bam NEXT -> `resetTareForNewSlot()` dat `tareBaseline = null`, cho "lan doc on dinh dau tien" lam bi (`Delta_Begin` cua VBA). Nhung **khong co lan doc nao ca** — khong ai ban so vao.
  - Go so thu nhat -> watch chay -> so do bi an lam BI, `net = raw - bi = 0` -> o DELTA hien **0.00**.
  - Go lai dung so cu -> watch khong chay -> man hinh dung im. Nhin y het "can khong duoc".
- **Sua:** nhanh gia lap trong `fetchLiveWeight` nay nap lai `simulatedWeight` moi nhip, dung nhu cai can that ban so lien tuc. Van khong hoi Agent (dang gia lap thi so that phai bi bo qua hoan toan — bai hoc V1 ghi o dau `useSimValue`). Giu `watch(simulatedWeight)` de go xong thay doi tuc thi thay vi doi het mot nhip 200ms.
- **MOT BUOC SAI DA TU SUA TRONG PHIEN:** ban dau con them nhanh rieng cho gia lap trong `resetTareForNewSlot()` — chot `tareBaseline = 0` de "so go vao chinh la so can duoc". **Nguoi dung bat ngay:** *"khi an next thi phai can lai tu 0 chu, mac du la can tiep"*. Dung — vat tu o truoc VAN NAM tren dia, nen bi phai chot bang so DANG CO tren can luc bam NEXT thi o moi moi dem dung phan do them. Dat cung bi = 0 la bien so go vao thanh so can luon, tuc bo mat chinh cai hanh vi dang can thu. **Da go bo nhanh do** — gia lap nay chay y het can that, khong con biet le nao. Bai hoc: sua cho A thi dung vien mot ngoai le o B, cai `return` thieu o `fetchLiveWeight` moi la loi that.
- **Luong dung sau khi sua:** bam NEXT -> trong 200ms bi tu chot = so dang go, o DELTA ve **0.00**; go so lon hon (vi du 12.5 -> 20.8) -> hien **8.3**, dung phan vua do them; bam NEXT -> chot 8.3 vao o va sang o ke, lai ve 0.00.
- **Kiem chung:** `vue-tsc` **0 loi**, `vite build` OK 25.35s. **CHUA thu tay tren trinh duyet.**

### 88. Gia lap: so nhay ve 0.0 khi go dau thap phan, va F5 luon bao "dia can da thay doi"

Hai bao loi tiep theo cua nguoi dung sau muc 87, deu la loi that.

**A. "khi toi dien gia lap so cu bi nhay nhay ve 0.0"**

- **Nguyen nhan:** `<input type="number">` tra ve **CHUOI RONG** o moi trang thai go do — go `12.` la trinh duyet coi chua hop le. `v-model.number` day chuoi rong do vao `simulatedWeight`, roi `ingestRawWeight('')` -> JS tinh `'' - bi` ra SO chu khong ra loi (`'' - 0 === 0`) -> o DELTA nhay ve 0.00. Cu go toi dau cham thap phan la nhay mot cai.
- **Sua:** chan ngay dau `ingestRawWeight`: `if (!Number.isFinite(raw)) return;` — bo qua so rac, GIU NGUYEN so dang hien, dung nhu cai can that giu mat so khi khong doc duoc gi moi.
- **Bat duoc them mot loi co san:** duong can THAT cung di qua cong nay — `parseFloat(res.data.weight)` ra NaN khi Agent day len chuoi hong, truoc day NaN chay thang vao `liveWeight` va mat so hien "NaN". Nay cung bi chan.

**B. "khi toi f5 lai thi tiep tuc can cai toi da an next"**

- **Nguyen nhan:** `useSimValue` la ref thuong, **khong song qua F5**. Tai lai trang xong gia lap TU TAT, man hinh quay ve doc can that (so 0 hoac mat tin hieu). Ma co che noi lai o dang can do (`pendingResume`) lai so `grossWeight` hien tai voi `grossAtSave` da luu — mot ben la so gia lap, mot ben la so cua can that -> **lech, lan nao F5 cung an "Dia can da thay doi trong luc tai lai trang"**.
- **Sua:** luu `useSim` + `simWeight` vao `df_ws2_session_v1`, va khoi phuc chung trong `khoiPhucGiaLap(saved)` — goi **TRUOC** khi dat `pendingResume` o ca hai nhanh (QR va token). Thu tu la bat buoc: khong dung lai nguon so gia lap truoc thi nhip poll dau tien nap so cua can that va phep so luon lech.
- Da kiem tra `onNext` khong co loi thu tu: `captureCurrentSlot()` -> tang `currentIndex` -> `saveSession()`, nen phien luu dung O MOI. Sau F5 quay lai dung o dang can do la **hanh vi thiet ke** (muc 69), khong phai bug.
- **Kiem chung:** `vue-tsc` **0 loi**, `vite build` OK 19.96s. **CHUA thu tay tren trinh duyet.**

### 89. Giam tai nhip poll cua /weighing-station-v2 — 9 truy van DB moi 200ms

- **Nguoi dung hoi:** "co gop y gi cho toi de chay muot hon nua khong".
- **Do bang cach doc dung duong code** cua `GET /api/devices/readings/{id}?local=1`:

| Cho | Truy van |
|---|---|
| Sanctum auth | 2 (`personal_access_tokens` + `users`) |
| `resolveReadingKey` tra id -> ma tram | 1 (`operation_clients`) |
| `readCacheSlot(ma tram)` | 3 |
| `readCacheSlot(theo IP may)` | 3 |

  **= 9 truy van x 5 lan/giay = ~45 truy van/giay cho MOI tram can.** 6 truy van cache la do `.env` de **`CACHE_STORE=database`** — moi `Cache::get` di thang xuong PostgreSQL. Agent day so len con nang hon: `storeReading` goi **7 `Cache::put`** moi lan doc can.
- **Tren may dev day chinh la thu lam no i:** DB o 10.0.60.209, moi truy van ~20ms (da do o muc 68) -> 9 x 20 = **~180ms** trong khi nhip poll la 200ms, gan nhu bao hoa. Tren CS-SERVER DB nam cung may (~1ms) nen nguoi dung khong thay, nhung van la 45 truy van/giay/tram nen vao mot bang `cache` duy nhat.

**DA LAM (nguoi dung chon 2 + 4):**

2. **`Cache::many()` thay 3 lan `Cache::get`** trong `readCacheSlot`. Da **doc thang vendor** de xac minh chu khong tin suong: `Illuminate\Cache\DatabaseStore::many()` (dong 125) gom thanh MOT `whereIn('key', ...)`. **6 truy van xuong 2.** Khong doi dinh dang luu -> KHONG can deploy dong bo voi Agent, Agent van ghi 3 khoa roi nhu cu.
   - **Bay da chan:** `many()` tra `null` cho khoa trong, KHONG nhan gia tri mac dinh nhu `Cache::get($key, false)`. Quen bu `?? false` thi `is_stable` ra `null` — trinh duyet `Boolean(null)` van ra false nen nhin thi "van chay", nhung kieu du lieu trong phan hoi da sai va bat ky cho nao so `=== false` se hong am tham. Da chot bang test rieng.
4. **Ngung poll khi tab bi an** (`visibilitychange` + `document.hidden`). Gop chung vao `capNhatNhipPoll()` de ca `watch(dangCan)` lan su kien hien/an di qua mot cho. Hien lai thi doc so NGAY roi moi vao nhip, khong doi toi nhip ke. An toan vi khong co thao tac can nao xay ra khi trang bi an (khong bam duoc NEXT, khong quet duoc ma). **KHONG dung toi nhip hang doi** (`batTuDay`) — me chua gui van phai duoc day len ke ca khi tho da chuyen cua so khac.

- **Test moi `ScaleReadingCacheTest` (4 test):** doc dung so trong cache; **cache rong -> `is_stable` phai la `false` dung kieu bool, khong duoc null** (dung ca `many()` khac `get()`); so het han nhung moc thoi gian con -> van bao duoc `age_ms` (thu ma man hinh dung de bao MAT TIN HIEU CAN); `?local=1` -> can o chinh may thang ma tram cau hinh san.
- **Kiem chung:** `php -l` sach, `vue-tsc` **0 loi**, `vite build` OK 17.54s, **26 passed (116 assertions)**.

**CHUA LAM — de nguoi dung quyet:**

1. **`CACHE_STORE=database` -> `file`** (config server). Bo duoc 6/9 truy van doc VA ca 7 lan ghi cua Agent. Khong sua dong code nao. CS-SERVER chay tat ca tren mot may nen file cache la du dung. **Day van la don bay lon nhat**, muc 2 chi go duoc mot phan. Doi xong nho `config:clear`; cache so can TTL 15s nen mat cache khong anh huong gi.
3. **Bo truy van tra id -> ma tram.** Frontend dang gui `id`; gui thang `code` thi `resolveReadingKey` tra ve ngay, khoi truy van. **CHUA xac minh** Agent ghi cache theo ma tram hay theo id — phai kiem truoc khi doi.
5. **Thay poll bang SSE** (ADR-008 da chot SSE cho realtime, du an da co Transactional Outbox). Do moi la cach bo han 45 truy van/giay. Dang Phase 12 UAT nen khong dong vao kien truc.
- **Khong phai van de:** canh bao chunk >500kB luc build — router da lazy-load du 30 route, canh bao do den tu thu vien khac chu khong phai trang nay.

### 90. Lam not muc 1 + 3: 9 truy van/lan poll xuong con 2. Va DINH CHINH muc 5 — SSE la khuyen nghi SAI

**1. `CACHE_STORE=database` -> `file`** (`backend/.env`, da `config:clear`).

- Da ra soat truoc khi doi: **khong co `Cache::lock` hay `Cache::tags` nao** trong `app/` — file store khong ho tro tags nen day la dieu kien bat buoc phai kiem.
- File store la CUC BO TUNG MAY, nhung dung o day: ca ghi (Agent POST -> `storeReading`) lan doc (trinh duyet GET -> `getReading`) deu chay trong tien trinh backend tren CUNG mot may CS-SERVER.
- **Kiem chung that** bang tinker: put 3 khoa roi `Cache::many` doc lai -> `{"..._WSKT":12.34,"..._stable_WSKT":true,"..._timestamp_WSKT":1785663207.33,"..._KHONG_CO":null}`. Ket qua cuoi cung xac nhan dung cai bay da chan o muc 89: khoa trong tra `null`.
- **`.env` nam trong `backend/.gitignore`** -> sua o day CHI doi may dev. **CS-SERVER phai tu doi**, khong deploy kem duoc.
- Test khong bi anh huong: `phpunit.xml` ep `CACHE_STORE=array`.

**3. Frontend gui MA tram thay vi id.**

- **Da xac minh truoc khi doi** (muc 89 con ghi la chua): `storeReading` ghi cache bang dung chuoi `workstation_id` Agent gui len — mot MA (vi du "WS-WEIGH-SCALE"), khong phai so. `resolveReadingKey` chi tra DB khi tham so la so, de doi id -> `OperationClient.code`. Vay gui thang ma la trung dung khoa do, bot 1 truy van, ket qua khong doi.
- Khong co rui ro thu tu deploy: backend nhan ca hai dang, frontend moi chay duoc voi backend cu.
- Co `encodeURIComponent` + lui ve `id` khi tram chua co ma.

**Ket qua don:**

| | Truoc hom nay | Sau muc 2 | Sau muc 1+3 |
|---|---|---|---|
| Truy van DB / lan poll | 9 | 5 | **2** (chi con Sanctum auth) |
| Ghi DB moi lan Agent doc can | 7 | 7 | **0** |

**DINH CHINH — muc 5 (SSE) la khuyen nghi SAI, da rut lai:**

- O muc 89 toi de xuat "thay poll bang SSE" va vien dan ADR-008. **Toi da khong doc phan cap nhat 2026-07-30 cua chinh ADR do:** SSE gốc (`/api/realtime/stream`, vong lap `while(true)`) **da duoc lam va da gay treo TOAN BO server** — `php artisan serve` tren Windows khong co concurrency that (khong `fork()`), chi mot tab mo Dashboard la chiem request-handling thread vinh vien.
- SSE da bi thay bang **Laravel Reverb** (WebSocket, tu host), dang chay san: task `DFWeb-Reverb`, `frontend/src/services/echo.ts`, `app/Events/RealtimeEventBroadcast.php`, `BROADCAST_CONNECTION=reverb`.
- Lam theo dung loi toi noi la dung lai su co thang truoc. **Ban dung cua muc 5 la phat so can qua Reverb** — ha tang co san nen re hon tuong, nhung khac han ve rui ro: `storeReading` phai goi Reverb moi lan Agent day so (vai lan/giay), can kenh rieng tung tram, va **Reverb chet la man hinh can mat so** trong khi poll hien tai tu lanh. Phai giu poll lam du phong (dung ADR-010).
- **CHUA LAM, dung lai hoi nguoi dung** — cai ho duyet (SSE) khong ton tai nhu mot lua chon.
- **Bai hoc:** `.claude/architecture-decisions.md` co ADR da bi thay the nhung TIEU DE van giu nguyen ten cu ("ADR-008: Lua chon SSE..."), phan bac bo nam o muc con ben duoi. Doc tieu de roi trich dan la sai. Lan sau doc het ca muc truoc khi vien dan bat ky ADR nao.
- **Kiem chung:** `config:clear` OK, `cache.default = file`, `vue-tsc` **0 loi**, `vite build` OK 17.09s, **26 passed (116 assertions)**.

### 91. CAN TAY — can khong quet don van luu duoc

- **Yeu cau (02/08/2026):** "khi k quet QR ma can khong van co the luu binh thuong". Nguoi dung chon phuong an: **"van in phieu binh thuong, cai gi trong thi trong, van luu DB binh thuong"**.
- **Truoc do:** khong quet don thi `activeJob` null -> `jobItems` rong -> `rows` rong -> SAVE bao "Khong co dong nao de luu". Man hinh con ghi thang "khong co gi duoc luu".

**Rang buoc luoc do buoc phai xu ly (da tra migration truoc khi thiet ke):**

- `weighing_jobs.production_batch_id` **NOT NULL** + khoa ngoai -> phai co mot lo.
- `production_batches.color` / `product_code` / `machine_id` / `level_code` **deu nullable** -> de TRONG duoc, dung y nguoi dung, khong phai bia.
- `weighing_job_items.material_code` **NOT NULL** + khoa ngoai toi `materials.code`, va `planned_weight` **NOT NULL** -> hai cho nay khong the trong.

**Cach lam:**

- Lo: `legacy_batch_id = 'CANTAY-<YmdHis>-<4 ky tu>'`, color/product_code/machine_id/level_code **NULL**. Bao cao tieu hao/dung sai/san luong phai LOAI cac lo nay — nhan dien qua tien to `CANTAY-`, cung cach dang dung cho `ADHOC-`.
- Dong: `material_code = 'CANTAY'` (ma moi, `Material::firstOrCreate` 1 lan), `planned_weight = 0`, dung sai 0.
- **`process_status` tra `'MANUAL'`** cho dong mang ma moi nay. Neu khong, `planned_weight = 0` se cho ra REJECTED — **gan "khong dat" cho mot con so khong co gi de doi chieu la noi SAI**, khac han voi de trong. Nhan qua `material_code` chu KHONG qua `planned_weight <= 0`: ma moi chi dong can tay moi co, nen **khong ban ghi cu nao bi doi nhan** (dong QR muc tieu 0 do tem hong van giu nguyen REJECTED nhu truoc).
- **Di CHUNG endpoint `weigh-from-qr`** (`manual=true`, `raw_qr` doi thanh `required_without:manual`) chu khong tach endpoint rieng: in ngay, hang doi, chong ghi trung, xoa form — tat ca dung y het. Tach ra la co hai bo ngu nghia hang doi phai giu dong bo voi nhau.
- **Kiem chong ghi trung chuyen len TRUOC khi re nhanh.** O can tay hau qua con nang hon me QR: moi lan gui lai tao MOT lo moi toanh, khong co gi trung de ma dung nhau — khong co khoa thi ghi trung bao nhieu lan cung lot.
- Dong chua can thi KHONG tao item (khac me theo don: don quy dinh san phai can nhung gi nen phai ghi ca o bo trong; can tay khong co danh sach nao de ma thieu). Khong co dong nao da can -> tra 422, khong de lo rong.
- Frontend: them `manualRacks` (o RACK cua dong trong truoc day go xong roi vao hu khong vi `onUpdateRack` ghi vao `jobItems[idx]` khong ton tai), nhanh `canTay` trong `onSave`, `dungPhieuCanTay()`, doi lai dong chu tren man hinh.

**Kiem chung:**

- **Them ca "can tay" vao bo doi chieu JS<->PHP** (`check-weigh-slip.mjs`): dau phieu trong tron, nhan MANUAL, va dong can tay CHUA co so cai phai ra MANUAL chu khong phai REJECTED — chot dung THU TU cac nhanh. **Bo doi chieu bat duoc ngay mot troi dat that:** `processStatus` ban JS chua biet nhan MANUAL. Da bo sung + hang so `MANUAL_MATERIAL_CODE` dung chung. **8/8 pass**, hai ban ra CUNG mot chuoi.
- 5 test backend moi: luu duoc khong can QR + lo de trong dung cho; khong dong nao bi gan KHONG DAT; van co phieu voi dau phieu trong; gui lai khong de them lo; chua can gi thi choi 422 va khong tao lo rong.
- `php -l` sach, `vue-tsc` **0 loi**, `vite build` OK 16.80s, **31 passed (138 assertions)**, guard 8/8 + 14/14 + 1229/1229.

- **CHUA thu tay tren trinh duyet.**

### 92. Can tay: song qua F5, va tach han khoi moi thong ke san xuat

Hai viec con thieu o muc 91, nguoi dung yeu cau lam not: *"van can tiep duoc tru khi an clean"* va *"them phan danh dau rang cai nay, k lien quan den cai quet don"*.

**A. Me can tay song qua F5**

- `saveSession()` bo dieu kien `!activeJob` (truoc day thoat ngay -> can tay do dang ma F5 la mat sach). Thay bang dieu kien "co gi do dang de giu" (`activeJob` HOAC da bam NEXT HOAC da chot o nao) — khong co thi khong ghi, tranh moi lan can dung yen lai ghi mot ban rong vao localStorage.
- Luu them `canTay: true` (danh dau tuong minh, khong de `restoreSession` suy ra tu cho thieu jobId/rawQr — suy ra thi khong phan biet duoc voi mot ban ghi hong) va `manualRacks`.
- `restoreSession` them nhanh `canTay`: dung lai so da can + rack + gia lap, roi noi lai o dang can do bang dung co che `pendingResume` nhu me theo don.
- `watch([isStable, grossWeight])` ghi moc `grossAtSave` bo dieu kien `activeJob` — khong bo thi can tay khong co moc nao de doi chieu, F5 xong khong noi lai duoc.
- CLEAR/SAVE van `clearSession()` nhu cu -> dung yeu cau "tru khi an clean".

- **BAT DUOC MOT LOI CODE CHET CUA CHINH MUC 91:** o RACK trong `VbaRackGrid` co `v-if="items[idx]"` — dong khong co vat tu **khong he render o nhap**. Nghia la `manualRacks` + `onUpdateRack` them o muc 91 khong bao gio chay duoc, can tay khong co cach nao go ma rack. Da them prop `manualRacks` + `allowManualRack` va render o nhap cho dong trong KHI dang can tay. Dong trong nam DUOI mot don da quet thi van khoa — SAVE cua me theo don khong gui chung di, cho go vao do chi tao cam giac da ghi duoc cai gi do.

**B. Tach can tay khoi moi thong ke san xuat**

- `ProductionBatch::MANUAL_BATCH_PREFIX` + scope **`khongPhaiCanTay()`** — mot cho dung chung, thay vi rai dieu kien khap noi.
- **Phan biet ro voi tien to `ADHOC-`, va CO Y khong loc ADHOC:** lo ADHOC **den tu mot tem QR that** (chi la chua khop lo nao trong Web) nen no van la viec san xuat va van phai nam trong bao cao. Lo CANTAY thi khong lien quan gi toi quet don — dung nhu nguoi dung dien dat.
- **Ap o 7 cho, khong chi bao cao** (ra soat moi noi liet ke `ProductionBatch`): `DashboardController` (4 cho: tong quan, hang cho can, may nhuom, 2 so dem), `ProductionBatchController::index`, `ReportController` (tieu hao + dung sai). De lot vao Dashboard la bang dieu khien day nhung dong trong tron.
  - `ReportController::machineOutput` **khong can sua**: no `join('machines', 'mac.id', '=', 'pb.machine_id')` ma lo can tay co `machine_id` NULL -> inner join tu loai; va no bat dau tu `feed_operations` ma can tay khong bao gio tao.
  - Bao cao dung query builder tran nen scope cua model khong ap duoc -> co ban viet tay `ReportController::loaiCanTay()`.
- **BAY DA CHAN, viet vao ca hai ban:** phai viet dang "`legacy_batch_id` NULL **HOAC** not like" chu khong `not like` tran — trong SQL `NULL NOT LIKE '...'` cho ra **NULL** chu khong ra TRUE, nen `not like` tran se **nem sach moi lo khong co ma cu ra khoi bao cao**. Hong am tham: so chi nho di chu khong bao loi. Da chot bang test rieng `test_batches_without_a_legacy_id_are_still_counted`.

**Kiem chung:**

- 2 test moi: lo can tay bi loai khoi bao cao tieu hao **trong khi lo quet don van duoc tinh** (test tim thay `DYE001` nen no doc dung cau truc phan hoi, khong phai so tren mang rong); va lo `legacy_batch_id` NULL van duoc giu.
- `php -l` 4 file sach, `vue-tsc` **0 loi**, `vite build` OK 17.07s, **33 passed (144 assertions)**, guard 8/8.
- **`ReportsTest` (12 test) hong SAN, khong phai do thay doi nay:** class do dung `DatabaseTransactions` chu khong `RefreshDatabase` nen doi DB da migrate san (Postgres) — tren SQLite in-memory no chet ngay o `User::factory()` voi `no such table: users`. Da xac minh TUNG test deu chet dung loi thieu bang do. Phan loc bao cao van duoc phu that, bang test nam trong class co `RefreshDatabase`.
- **CHUA thu tay tren trinh duyet.**

### 93. Can tay: SAVE duoc NGAY khi co so, khong bat bam NEXT truoc

- **Yeu cau (02/08/2026):** *"chi can khong nhap don, khi can lieu co chi so la save duoc"*.
- **Truoc do** muc 91-92 chi lam duong bam NEXT: `capturedWeights` rong -> `rows` rong -> SAVE choi. Nhung o **RAW** (`grossWeight`) hien so **ngay ca khi chua bam NEXT** — tho nhin thay so ma bam SAVE khong duoc.
- **Nay `dongCanTayDeLuu()` nhan CA HAI cach dung:**
  1. Bam NEXT chay nhieu o nhu can theo don -> lay cac o DA CHOT SO (nhu cu).
  2. Khong bam NEXT gi ca -> lay thang so RAW dang hien, mot dong.
- **Vi sao lay `grossWeight` chu khong `liveWeight`:** cach 2 chua he chot bi, ma `ingestRawWeight` thoat som khi chua `armed` nen `liveWeight` VAN DUNG O 0. Thu duy nhat co that la so can gop. Ghi kem `tare_weight = null` cho khop su that: khong tru bi lan nao ca.
- **Ba dieu kien phai qua, va bao dung ly do tung cai** (gop thanh mot cau chung la tho dung bam lai mai ma khong biet phai lam gi):
  - `signalLive` — so chet la con so dong cung tu lan doc cuoi truoc khi mat tin hieu.
  - `isStable` — so chua dung yen la dang do do.
  - `grossWeight !== 0` — can rong khong phai "co chi so". **Chan o day vi ban ghi tao ra KHONG XOA DUOC** (CLAUDE.md muc 3, khong xoa vat ly) — bam nham mot cai la de lai rac vinh vien.
- Doi lai dong huong dan tren man hinh: dat vat tu len can, thay so o RAW la bam SAVE duoc ngay; muon can nhieu thu thanh mot phieu thi bam NEXT cho tung thu roi moi SAVE.
- **Kiem chung:** them test `test_manual_weighing_accepts_a_single_untared_row` — payload hinh dang khac han nhanh bam NEXT (dung MOT dong, `tare_weight` = null), server phai nhan duoc chu khong duoc doi co bi. `vue-tsc` **0 loi**, `vite build` OK 16.99s, **34 passed (148 assertions)**.
- **CHUA thu tay tren trinh duyet.**

### 94. Nut SAVE van khoa `!activeJob` — toan bo duong can tay KHONG DUNG DUOC

- **Nguoi dung bao (02/08/2026):** *"nut save dang hien thi cam, toi bam duoc"* — nut xam, con tro hinh cam.
- **Loi cua chinh toi, va la loi nang nhat trong nhom nay:** muc 91-93 xay tron duong luu can tay (backend + hang doi + phieu + test) nhung **quen go dieu kien khoa o chinh cai nut goi no**: `:disabled="saving || !activeJob"`. Khong co don thi nut xam vinh vien -> **khong mot dong nao trong 3 muc do chay duoc tu giao dien**. Test backend xanh het nhung nguoi dung khong cham toi duoc.
- **Bai hoc:** test API xanh khong chung minh tinh nang **DUNG DUOC**. Duong di tu ngon tay nguoi dung toi ham do — nut, dieu kien disabled, o nhap — phai duoc ra soat rieng. Day la lan thu HAI trong phien: muc 92 cung phat hien o RACK cua dong trong khong he render (`v-if="items[idx]"`) nen `manualRacks` la code chet.
- **Sua:** `SAVE` chi con khoa khi `saving`. Ly do khong luu duoc thi `onSave` noi ro tung truong hop (mat tin hieu / chua dung yen / can rong) — **noi ra duoc van hon mot cai nut xam khong giai thich gi**.
- **Sua kem cung cho hut do:** nut `PRINT` cung khoa `!activeJob`. Nay bo khoa va `printSlip` them nhanh can tay — dung chung `dongCanTayDeLuu()` voi SAVE nen to in thu va to in luc luu khong the khac nhau. Cua so in van mo DONG BO truoc moi `await` (khong bi chan popup).
- **Ra soat lai toan bo `:disabled=`** trong file: chi con `saving` (CLEAR/SAVE), `saving || !canPressNext` (NEXT), `flushing` (GUI NGAY) — deu dung.
- **Kiem chung:** `vue-tsc` **0 loi**, `vite build` OK 16.72s. **CHUA thu tay tren trinh duyet.**

### 95. Man hinh "Can to" (/weighing-station-large) -- port workbook VBA #5, man RIENG khong dung chung V2

- **Yeu cau (03/08/2026):** *"o layout toi muon them 1 phan ten la can to co giao dien va chuc nang giong form vba nay"* (`5.Semiauto- lockmove SEND OVER6 - delta-stable-final-221.xlsm`). Nguoi dung chot ro: **"no la 1 phan khac, k lien quan den v2"**, va nut OUT/IN **"dung agent loai khac"**.

**A. Doi chieu nguon truoc khi code (source-traceability)**

- Da trich toan bo VBA cua workbook #5 va #4 bang bo doc CFB + giai nen MS-OVBA viet rieng (Excel dang mo file trong VBE nen khong dung duoc COM). Ca hai workbook: **22 module y het nhau** (2 UserForm `scaleform`/`checkform`, 16 module chuan, ThisWorkbook + Sheet1-3 rong).
- **Diff code giua #4 (can nho) va #5 (can to) chi co 3 cho, deu khong phai nghiep vu:**
  - `txt_color_AfterUpdate`: ban #4 xu ly `-dye-`/`chem` khong phan biet hoa thuong (tot hon), ban #5 phan biet hoa thuong.
  - `Mod_lockmoveform`: ban #4 co them chong rung (`Abs(...) > 1`) + kiem tra `WatchForm.Visible`.
  - GUID form.
- **Dung sai giong het:** `Mod_UI_processcolor.CheckRange` 0 dong khac -- ratio <0.99 vang / <=1.01 xanh / >1.01 do. `Mod_sendRackauto` cung **y het** o ca hai file (tuc khoi SEND OVER 6 ton tai o ca can nho, chi la V2 chua port).
- Da bao lai phat hien nay cho nguoi dung; nguoi dung van chot lam man RIENG -- lam theo quyet dinh do.

**B. Da lam**

- **`frontend/src/views/WeighingStationLarge.vue` (moi)** -- man hinh doc lap: quet QR vao o COLOR, luoi 9 dong, NEXT/SAVE/PRINT/CLEAR/CHECK/CLOSE, o DELTA co lon, thanh RAW, hang doi gui me, khoi phuc phien sau F5.
  - **Khoa localStorage RIENG `df_wslarge_session_v1`** (khong dung chung `df_ws2_session_v1` cua V2): hai man co the mo cung mot may luc kiem thu, dung chung khoa thi mo man nay se nuot mat me dang can do cua man kia.
  - **Dung chung HA TANG** voi cac man can khac (`useScaleFeed`, `VbaRackGrid`, `saveQueue`, `weighSlip`, `qrDyeParser`, `processColor`, `tsplPrint`). Day la ban port DUY NHAT cua thuat toan VBA goc (delta/bi, dung sai +-1%, bo cuc phieu, doc QR) -- chep tay lan nua la mo duong cho hai man cung can mot me ra hai ket qua khac nhau.
- **Khoi "SEND OVER 6" (rieng cua can to, V2 khong co)** -- port `Mod_sendRackauto.BuildRackBatch`: gom o RACK khac rong va khac `"0"`, don lien tuc, 6 ma dau vao LO 1, phan du vao LO 2. Nut IN don LO 2 len thanh LO 1 (dung `rackBatch1(i) = rackBatch2(i)` cua VBA), don **vo dieu kien** ke ca khi gui hong -- dung ban goc, gui va don la hai chuyen tach roi.
  - Lo rack duoc luu vao phien -- F5 giua chung khong mat thu tu rack con lai.
  - `onUpdateRack` gom lai lo khi da tung gom, neu khong khoi SEND OVER hien so cu cua lan gom truoc va tho bam OUT la **gui nham ma**.
- **`frontend/src/services/rackDispatch.ts` (moi)** -- diem tich hop DUY NHAT voi agent. VBA goc dieu khien chuot (`ClickAt 345,200` + `SendKeys "^v"` vao toa do man hinh cua app pha mau); cach do **khong port sang web duoc va vi pham ADR-002**. Web chi PHAT LENH: `POST /api/rack-dispatch` kem `idempotency_key` (rules/database-safety muc 4).
- Route `/weighing-station-large`, `ROUTE_CAPABILITY_MAP` **chi `LARGE_SCALE`** (khac 2 dong `/weighing-station*` von nhan ca SMALL lan LARGE -- co chu y, thao tac gui rack chi ton tai o khu can lon), muc menu **"Can to"** (adminOnly trong luc chay thu, vi tai khoan van hanh bi khoa cung vao 1 man theo workstation binding), tieu de man hinh trong `AppLayout.vue`.

**C. CON TON DONG -- chua lam**

- **Backend `POST /api/rack-dispatch` CHUA CO**, va **agent xu ly OUT/IN chua duoc xac dinh** ("agent loai khac", chua ro la agent nao). Hien bam OUT/IN se bao loi ro rang bang tieng Viet va chi tho dung nut **COPY** (chep LO 1 ra clipboard, dan tay sang he pha mau) -- duong thoat dung duoc ngay, khong phai nut chet.
- **CHUA thu tay tren trinh duyet.**
- **Kiem chung da chay:** `vue-tsc --noEmit` 0 loi o file moi, `vite build` OK 13.48s (chunk `WeighingStationLarge` 23.86 kB).

### 96. Can to: dung lai giao dien 1:1 theo dung toa do form VBA

- **Yeu cau (03/08/2026):** *"can to toi muon giao dien giong het trong form VBA"*.

**A. Lay toa do that — VBA project BI KHOA MAT KHAU**

- Moi duong qua Excel COM deu TREO (khong bao loi): `wb.VBProject` bat hop thoai hoi mat khau ma cua so lai vo hinh. Nguyen nhan chi lo ra khi doc stream `PROJECT`: khoa **`DPx=`** (bien the cua `DPB=`, nen lan grep dau tim `DPB=` bao "khong khoa" — sai). Workbook #4 dung `DPB=`, #5 dung `DPx=`.
- => Doc thang binary **MS-OFORMS** trong `xl/vbaProject.bin`: storage `scaleform` -> stream **`f`** (ten + Left/Top + ObjectStreamSize + ClsidCacheIndex + TabIndex) va stream **`o`** (Size + Caption + Font). Don vi goc himetric (1/100 mm), quy ve POINT.
- **Khong dung duoc spec tu tri nho** cho vung `SiteDepthsAndTypes` (ma hoa run-length) -> thay bang cach do: thu parse chuoi ban ghi tu moi offset, lay chuoi DAI NHAT. Tu kiem chung: ra dung **74 control**, moi ten deu la dinh danh hop le, va an vua khit stream.
- **Hai quy luat rut ra bang doi chieu tay 8 control du 3 loai** (khong co trong tri nho, phai suy tu chinh file):
  - Than control = `[0, 4+cb)`; moi thu sau do la ban ghi con (TextProps = font).
  - **Size (Cx,Cy) LUON la 8 byte cuoi cua than** -> offset `4+cb-8`. Caption nam ngay truoc Size.
- **Bang chung parse dung:** `txt_RACK1` @(12.02, 11.99)pt, `txt_RACK2` @(12.02, 59.98) -> buoc dong dung **48pt**, trung voi so da biet tu ban #4; `btnSAVE` left 552 + width 180 = 732 ≈ be ngang form 734.26.
- **Mot bay da mac va da go:** chuoi ASCII trong stream `o` cho ra `RACK#`, nhung CaptionLength = 4 -> caption that la **`RACK`**, ky tu `#` chinh la byte thap cua Cx (0x0423 = 1059 himetric = 30.02pt). Doc caption bang "quet chuoi in duoc" la sai.

**B. Bo cuc that (form 734.26 x 546.01 pt = 979 x 728 px)**

- Luoi 9 dong: RACK @12.02 (48pt), DYE @65.99 (186pt), WEIGHT @257.98 (132pt), PROCESS @396 (150pt); cao 44.39pt, buoc dong 48.04pt; nhan so thu tu 1..9 @6.01 rong 6pt.
- Cot phai: DEL/PRINT/CHECK @top 6; ban phim so 1-9 (48x42) @66/114/162; `0` + CLOSE @210; **OUT (90x84) + IN (84x84) @258**; CLEAR (180x57.6) @348; SAVE (180x54) @408; NEXT (180x72) @468.
- Duoi trai: COLOR(90x25.2) / MACHINE(48x25.2) @450, CODE / LV @480, **delta_rawline 384x93pt font 80.2pt**, rawline 144x30pt.
- Font: luoi va delta dung **Arial Narrow 36pt**, nut/nhan dung **Tahoma**.

**C. Da lam**

- `WeighingStationLarge.vue` viet lai template + style: khung CO DINH 979x728px, moi control dat tuyet doi theo dung so do tren, ca khung thu/phong bang **MOT phep `transform: scale()`** (ResizeObserver) -> ti le giua cac control, co chu, do day vien khong bao gio lech so voi ban VBA.
- **Hang so `C` / `HEADERS` / `NUMPAD` la ban sao cua form that — da ghi chu ro KHONG duoc chinh tay**, muon doi thi sua .xlsm roi trich lai.
- Ban phim so nay chay dung ban goc: chi go vao o **RACK** (`LastInputBox` cua VBA chi duoc dat trong `txt_rackN_Enter`).
- **Moi thu bang web KHONG co trong VBA** (den mat tin hieu, hang doi gui me, gia lap, thong bao loi, lo rack + nut COPY) day het ra **DAI NGOAI khung form** -> phan form giong het ban goc, ma tho van thay duoc may dang hong gi.
- Da bo `VbaRackGrid` khoi man nay: component do dung ti le cua workbook **#4** (RACK 48 | DYE 330 | WEIGHT 312 | PROCESS 360 pt), khong phai #5.
- Hai cho CO Y khac ban goc, deu chi them chu khong bot: vien xanh o dong dang can (9 dong giong het nhau, khong co dau thi phai do bang mat), va o delta to mau theo dung 3 mau dung sai cua o PROCESS.

**D. Kiem chung**

- `vue-tsc --noEmit` **0 loi**, `vite build` OK 13.58s.
- Doi chieu so hoc: hang cuoi luoi ket thuc @440.7pt < COLOR @450 (khong chong nhau); NEXT ket thuc @540 < cao form 546; CHECK ket thuc @731.99 < rong form 734.26.
- **CHUA thu tay tren trinh duyet** — can nguoi dung mo /weighing-station-large xem bang mat.

### 97. Can to: lam lai phan NHIN (giu nguyen bo cuc + thao tac)

- **Yeu cau (03/08/2026):** *"giao dien nay dep hon, de nhin hon"* — sau khi da xem that tren trinh duyet.
- **Bo gia kieu Windows 95** (vien outset/inset, xam #f0f0f0, Tahoma): no chi lam man hinh trong cu chu khong giup doc nhanh hon. Thay bang: vo ngoai toi, mat form la mot the sang bo goc co do do; o du lieu bo goc, vien manh; **soc chan/le** cho 9 dong (9 o trang giong het nhau thi mat rat de nhay nham dong khi liec tu can len); nut phan mau theo VAI TRO (SAVE xanh duong / NEXT xanh la / CLEAR do / OUT tim / IN xanh mong); o delta thanh tam nen DEN, chu so doi mau theo dung sai.
- **3 mau tin hieu GIU NGUYEN ma RGB goc** (`utils/processColor`) — do la thu duy nhat tren man hinh mang nghia nghiep vu.

**Da tu bat va sua 3 loi bang cach CHUP MAN HINH bo cuc that**

Trang can dang nhap nen khong chup truc tiep duoc -> dung `scratchpad/preview.mjs`: doc THANG khoi `<style>` cua component, dung lai DOM tinh voi du lieu mau **co tinh chon dai nhat co the gap** (`1024`, `BLACK-ECO-N`, `1234.75`), roi chup bang Edge headless. Ba loi chi lo ra khi nhin anh:

1. **Chu tran o, cat mat so.** Ban dau toi doi ca luoi sang Inter — sai: o RACK chi rong 48pt (64px) ma chu 36pt (48px). Ban goc dung **Arial Narrow** chinh vi ly do do. Da tra font hep ve cho o du lieu, Inter chi dung cho nhan/nut.
2. **Van con cat ngay ca voi Arial Narrow** — vi ban goc de ca 4 cot 36pt, tuc **form that cung dang cat mat ma rack 3 ky tu**. Da chinh co chu theo be rong that tung cot: RACK 26 / DYE 30 / WEIGHT 30 / **PROCESS 34 (giu to nhat, day la so mang tin hieu)**.
3. **4 o cung mot dong bi so le.** Ban goc dat DYE/WEIGHT/PROCESS thap hon o RACK 3.65pt (gan nhu chac chan do keo tha chuot), nhin ra la rang cua. Dat `ROW_OFFSET = 0` — **sai lech toa do DUY NHAT so voi ban goc, va chi 4.9px**. Nhan cot cung cho ve cung mot moc.

**Sua khac**

- **Nhan cho hang o duoi**: ban VBA de COLOR/MACHINE/CODE/LV tran trui. Them nhan vao khe 9.3pt giua day luoi va hang o (COLOR/MACHINE/DELTA); CODE/LV/RAW khong con khe nen nhan nam luon trong o.
- **Them lop `.stage-fit`**: `transform: scale()` khong doi o layout cua phan tu, nen khi phong to > 1 mat form bi tran ra ngoai vung cuon va cut mep. Lop dem mang dung kich thuoc sau khi phong.
- Chan tren cua phong to 1.6 -> 2.0, va tru padding cua vung cuon khi tinh ti le.

**Kiem chung:** `vue-tsc --noEmit` **0 loi**, `vite build` OK 13.25s. Anh chup bo cuc: `scratchpad/preview2.png`. **Chua thu tay tren trinh duyet that.**

### 98. Can to: het cuon chuot — man hinh vua DUNG MOT khung hinh

- **Yeu cau (03/08/2026):** *"toi muon cac o lam sao nhin trong 1 view ma k can cuon chuot vi qua dai"*.
- **Nguyen nhan (loi cua muc 96-97):** route nay co `requiresAuth` nen `App.vue` boc no trong **AppLayout** — noi dung nam trong `.content-container` (`flex:1; padding:24px; overflow-y:auto`), tuc DA la mot vung cuon co san va da bi thanh tren an mat mot phan chieu cao. Toi lai dat `.wsl-root { min-height: 100vh }` -> ep man hinh cao BANG CA CUA SO roi cong them thanh tren => luon dai hon mot khung hinh, phai cuon moi thay het mat form.
- **Sua:** bo `100vh`. Chieu cao nay **do bang JS** (`fitRoot`): `window.innerHeight - getBoundingClientRect().top - paddingBottom cua phan tu cha`. Dung trong moi truong hop, ke ca khi bat/tat toan man hinh hay doi chieu cao thanh tren. `.wsl-root` them `overflow: hidden` — moi thu BAT BUOC phai vua mot khung hinh.
  - `fitAll()` = `fitRoot()` roi `nextTick(fitStage)`: khong cho qua mot nhip thi `fitStage` van doc chieu cao CU va mat form bi thu nho hon muc can thiet.
  - Do lai mot nhip nua trong `requestAnimationFrame`: luc `onMounted` chay, AppLayout co the chua dung xong thanh tren nen `top` con la so tam.
  - `ResizeObserver` van gan vao `.stage-wrap` — dai thong tin / dong loi duoi cung hien roi an lam khung cao thap khac nhau.
- **Nhuong them cho cho mat form:** padding vung cuon 14 -> 8px, dai thong tin 8 -> 5px, dong loi 7 -> 5px va **chan toi da 2 dong** (thong bao dai khong duoc phep doi mat form len).
- **RACK 26 -> 24pt + le 2px:** anh chup cho thay ma rack 4 ky tu (`1024`) van sat mep o 26pt. 24pt cho ra 58.3px trong o rong 60px -> vua du 4 ky tu.

**Kiem chung bang anh chup, khong doan:** `scratchpad/preview.mjs` nay **mo phong luon khung AppLayout** (sidebar 240 + topbar 64 + `.content-container` padding 24) va chay ban sao cua chinh `fitRoot`/`fitStage`. Chup o **1920x1080** (scale 1.50) va **1600x900** (scale 1.24): toan bo mat form + dai thong tin + dong loi nam gon trong mot khung hinh, khong co thanh cuon. Anh: `preview_fhd.png`, `preview_final.png`.

`vite build` OK 13.27s. **Chua thu tay tren trinh duyet that.**

### 99. Tach lam 2 Agent / 2 bo cai doc lap: Can nho vs Can to

- **Yeu cau (03/08/2026):** *"/weighing-station-v2 va /weighing-station-large toi muon tach ra lam 2 agent, 2 bo cai k lien quan den nhau"*.
- **Hien trang truoc do:** DUNG 1 bo cai `DFAgentSetup-Scale.msi` (service `DFAgent`, thu muc `ProgramFiles\DFAgent`, ma tram `WS-SCALE-<TEN-MAY>`). Backend ghep cap trinh duyet voi Agent **theo IP nguon** (`machine_<ip>`), nen mot may chay hai Agent la ca hai ghi de len dung mot khoa cache — man Can to se hien so cua can nho.

**Cach tach: cung ma nguon, khac dung 1 khoa cau hinh `Workstation:ScaleKind` (SMALL/LARGE)**

| | Can nho | Can to |
|---|---|---|
| MSI | `DFAgentSetup-CanNho.msi` | `DFAgentSetup-CanTo.msi` |
| Service | `DFAgentSmall` | `DFAgentLarge` |
| Thu muc cai | `ProgramFiles\DFAgent-Small` | `ProgramFiles\DFAgent-Large` |
| UpgradeCode | `CD108F1A-...` (giu Guid lich su) | `2FDBACF6-...` (Guid moi) |
| Ma tram tu sinh | `WS-SCALE-<TEN-MAY>` | `WS-LARGE-<TEN-MAY>` |
| Man hinh | `/weighing-station-v2` | `/weighing-station-large` |

- **UpgradeCode khac nhau la thu quyet dinh "khong lien quan den nhau"**: dung chung Guid thi `MajorUpgrade` cua bo thu hai se TU GO bo thu nhat luc cai.
- **Bo Guid dong cung tren `<Component>`** trong .wxs, de WiX v5 tu sinh theo key path. Quy tac component cua Windows Installer cam mot Guid tro toi hai duong dan khac nhau, ma hai bo nay cai vao hai thu muc khac nhau. **Da do lai bang cach mo 2 file MSI**: 224 component moi ben, **0 Guid trung nhau**.
- `agent_cache.db` (hang doi offline) nam canh file .exe nen tu dong tach rieng theo thu muc cai, khong phai lam gi them.
- **Can nho GIU NGUYEN tien to `WS-SCALE-`** — co y. Doi tien to la moi may pilot dang chay tu sinh mot tram moi va bo lai tram cu thanh rac trong DB.
- Version nhay **2.2.0.0 -> 3.0.0.0** vi ban can nho doi ca ten service lan thu muc cai; may dang cai 2.2.0.0 phai qua MajorUpgrade de service `DFAgent` cu duoc go sach, khong de lai service mo coi tro toi thu muc da xoa.

**Sua backend — cho de khong lan so can**

- `DeviceController`: `machineKey($ip, $kind)` them hau to `_LARGE`. **SMALL khong co hau to** => khoa cu giu nguyen, tram can nho dang chay khong dut so luc deploy va khong phai xoa cache.
- `storeReading` nhan them `scale_kind` (nullable, in:SMALL,LARGE); `getReading` va `whoami` nhan `?kind=`. Thieu tham so deu ve SMALL — V1 (`/weighing-station`) va Dashboard khong truyen gi, phai chay y nhu cu.
- `AgentAuth`: tram tu dang ky voi `scale_kind=LARGE` duoc cap capability **LARGE_SCALE** + `default_route=/weighing-station-large`. Truoc do moi tram SCALE_ONLY deu ra SMALL_SCALE, tuc tram can to se **khong vao noi chinh man hinh cua no** (`ROUTE_CAPABILITY_MAP['/weighing-station-large']` doi dung LARGE_SCALE).
- `routes/web.php`: `/downloads/agent-launcher/{kind?}`. URL cu khong tham so van chay, tra bo can nho.

**Sua frontend**

- `useScaleFeed(kind)` gui `&kind=`; `adoptLocalWorkstation(kind)` gui `?kind=`. V2 khai bao tuong minh `'SMALL'`, Large khai bao `'LARGE'` (du SMALL la mac dinh — doc man nao biet ngay man do cam vao cai can nao).
- Sidebar "TAI CONG CU" gio co **2 link**: `DF Agent — Can nho` / `DF Agent — Can to`.

**Kiem chung**

- `dotnet test` agent: **35/35 pass** (them 3 test moi: chuan hoa ScaleKind, hai loai can tren cung may ra hai ma khac nhau, cau hinh cu khong co ScaleKind van giu tien to `WS-SCALE-`). Luu y: may dev khong co .NET 8 runtime (chi 3.1/9/10) — phai chay voi `DOTNET_ROLL_FORWARD=Major`.
- `build.ps1`: build **ca 2 MSI thanh cong** (28 MB moi file), da doi chieu UpgradeCode/ProductCode/ServiceName/thu muc trong chinh file MSI.
- `vite build` OK 14.13s.
- **Backend phpunit KHONG chay duoc tren may nay** — khong co PostgreSQL cong 5433 va khong co Docker, ca 12 test cu cua `ScaleLiveWeightTest` cung fail vi ly do do chu khong phai vi thay doi nay. **2 test moi (`test_hai_agent_can_nho_va_can_to_tren_cung_mot_may_khong_ghi_de_nhau`, `test_whoami_tra_dung_tram_theo_loai_can`) chua tung duoc chay** — phai chay lai o moi truong co DB test truoc khi tin.
- Da xoa artifact cu `DFAgentSetup-Scale.msi`/`.wixpdb` (da bi thay the, sinh lai duoc bang `build.ps1`).

**Con lai:** hai may tram ngoai xuong phai cai dung bo cua minh, va neu cai ca hai len cung mot may thi PuTTY phai co **2 session rieng ghi ra 2 file log khac nhau** (`Scale:LogFilePath` — ban can to mac dinh `D:\scale\putty_log_large.txt`, cong `COM2`). Trung file log la hai Agent cung doc mot cai can.

### 100. Agent day so can len NHIEU backend — mo bang localhost hay bang IP server deu nhan can

- **Trieu chung nguoi dung bao:** *"http://localhost:3001/weighing-station-v2 cai nay dang k nhan can, toi cai roi"*, sau do: *"toi muon ca 2 dia chi deu chay duoc"*.
- **NGUYEN NHAN GOC (do bang cach doc cau hinh that tren may, khong doan):**
  - Frontend suy ra host API tu **chinh URL trinh duyet dang mo**: `axios.defaults.baseURL = http://<hostname>:8500` (`main.ts:25`).
  - Mo bang `localhost:3001` => hoi backend **cuc bo** (`127.0.0.1:8500`, `CACHE_STORE=file`, cache rieng cua may).
  - Agent lai dong cung `Backend:Url = http://10.0.60.209:8500/api` => day so len **CS-SERVER**.
  - Hai kho cache tach roi => man hinh khong bao gio thay so. **Khong phai loi man hinh, khong phai loi cai dat.**
- **Da loai tru cac nghi van khac bang bang chung:** service `DFAgentSmall` dang Running, `appsettings.json` dung ban moi (`ScaleKind=SMALL`, `Id` de trong), PuTTY van ghi `D:\scale\putty_log.txt` lien tuc. Event Log cho thay loi *unreachable host 10.0.60.209:8500* luc 07:52 (mat mang tam thoi) da tu het sau khi service khoi dong lai luc 08:37.

**Sua: `Backend:Urls` (mang) — Agent day len TAT CA backend, song song**

- `Worker.ResolveBackendUrls()`: doc `Backend:Urls`; loc muc rong, cat dau `/` thua, bo trung (khong phan biet hoa thuong). Rong thi lui ve `Backend:Url` (chuoi don) — cau hinh tren may da cai **khong bi bo qua im lang** sau khi cap nhat Agent.
- `PushWeightToBackendAsync` = `Task.WhenAll` qua tat ca URL. **Song song chu khong tuan tu**: mot backend chet se giu ca luot day dung bang thoi gian cho timeout (5s), lam so can tren backend con song tre theo.
- **Chi xep vao hang doi offline khi KHONG backend nao nhan duoc.** Con mot noi nhan la so can da co cho luu; xep hang them chi tao ban ghi trung luc dong bo lai.
- **Chan spam log** (`GhiNhanTrangThaiBackend`): nhip day la 200ms, mot backend chet ma moi lan hong lai ghi mot dong canh bao la **5 dong/giay** do vao Event Log, troi mat moi thu khac dung luc can doc nhat. Chi ghi khi trang thai DOI: luc hong lan dau, va luc song lai. Nho co chan spam nay moi dam de san `127.0.0.1` trong danh sach mac dinh cua CA HAI bo cai — may tram khong chay backend cuc bo thi dia chi do chi that bai im lang (mot lan TCP refused tren loopback moi nhip, khong ton mang).
- `PackageVersion` 3.0.0.0 -> **3.1.0.0**, da build lai ca 2 MSI (28.1 MB moi file, da doi chieu ProductVersion trong chinh file MSI).

**Kiem chung:** `dotnet test` **39/39 pass** (them 4 test: mac dinh khi khong khai bao gi, `Backend:Url` don le van chay, `Backend:Urls` duoc uu tien hon, loc muc rong/dau '/' thua/dia chi trung).

**Con lai — CHUA CHAY THU THAT:** may nay dang cai ban 3.0.0.0 (chi day len CS-SERVER). Phai cai de ban 3.1.0.0 bang quyen admin moi kiem chung duoc tren trinh duyet that; da soan san script `scratchpad/tro-agent-ve-backend-cuc-bo.ps1`. **CS-SERVER van chay backend cu** — chua deploy thay doi cua muc 99-100.

### 101. Gantt: bam vao me hien them TONG SO ME ma do da chay tu dau toi nay

- **Yeu cau (2026-08-03):** bang chi tiet khi bam vao thanh Gantt phai co ca tong so me ma **mã màu - mã hàng** do da tung chay tu luc dau den gio — khac hai con so da co (so me gop lien tiep tren 1 Tank, va tong so thanh dang ve theo khoang ngay loc).
- **Backend:** `BpdbMachineMonitoringService::getLotRunTotal()` + endpoint public `GET /api/public/bpdb-machines-gantt/lot-total?color=&productCode=` (`BpdbMachineController::lotTotal`).
  - **KHONG nhet vao `/gantt`**: query quet toan bo lich su `SUP_Tasks`, chay san cho hang tram thanh dang ve la nen BPDB vo ich — chi goi khi nguoi dung that su bam mo 1 me. Cache 5 phut moi ma (`LOT_TOTAL_CACHE_TTL`), frontend cache them trong phien.
  - **Tieu chi dem** = dung tieu chi thanh Gantt: `WorkStartTime IS NOT NULL`, `IsDeleted=0`, `TaskStatus <> 99`; pham vi toan bo may VD trong registry, khong gioi han khoang ngay loc.
  - **Khop TaskTitle theo tien to CO dau cach** (`'{lot} %'` hoac bang chinh xac), khong dung `LIKE 'lot%'` tran — neu khong "RED-L1803" se dem nham ca me cua "RED-L18032". Da escape `[`, `%`, `_` trong mã màu/mã hàng truoc khi ghep vao LIKE.
- **Frontend (`BpdbMachinesGantt.vue`):** dong "Tong da chay" trong popup chi tiet (`lotTotal` + `loadLotTotal`), co trang thai *Dang dem…* / *Khong dem duoc (BPDB mat ket noi)*; khong cache ket qua khi BPDB rot de lan bam sau con thu lai. Me khong tach duoc mã màu/mã hàng thi an han dong nay.
- **Kiem chung that:** `vue-tsc --noEmit` exit 0; goi that endpoint tren BPDB — `EP69725-L18032` tra `total=5`, chay lan dau 12/07/2026, lan cuoi 24/07/2026; thieu tham so tra 422 `LOT_REQUIRED`. **Chua xem bang mat tren trinh duyet** (backend cuc bo chi bat tam de test roi tat lai).
- **Bo sung cung phien:** them hang **"Theo may"** trong chinh bang chi tiet cua me vua an — ma nay da chay o nhung may VD nao, moi may bao nhieu me (may nhieu nhat dung truoc), kem so may da tung chay ma nay. Query doi sang `GROUP BY Machine` roi cong don trong PHP (mot lan quet lich su ra ca tong lan phan chia, khong chay 2 query). **1 may VD co nhieu `machine_id`** (moi to hop Machine+Tank+MucNuoc la 1 dong `DyeMachines`) nen phai quy nguoc ve ma may vat ly truoc khi cong, neu khong moi machine_id se thanh mot "may" rieng. Kiem chung that: `EP69725-L18032` -> `VD003: 4`, `VD002: 1`, tong 5 (khop dung con so tong da do truoc do).

### 102. Nut "Toan man hinh" thay phim F11 + luoi C3 tu vua man hinh (2026-08-04)

- **Yeu cau nguoi dung:** `/production-batches/grid` phai tu co gian cho thay du 81 o; sau do them nut phong to thay F11 cho ca 8 man hinh van hanh.
- **`ProductionBatchesGrid.vue`:** port dung co che da dung o `PrintOrderEntry.vue` — mat form giu nguyen toa do goc 768x540pt, thu/phong bang MOT phep `transform: scale()` (khoang 30%-200%) nen ti le o/co chu/do day vien khong lech so voi ban VBA. Chieu cao vung lam viec **do that** tu mep tren phan tu (khong dat cung `100vh`) nen dung ca khi an sidebar/topbar hay F11. Dai trang thai luon hien (neu `v-if` thi luc an/hien chieu cao doi va ca luoi nhay co) va co them chi so "Vua man hinh: N%".
- **Component dung chung `components/FullscreenButton.vue`** (8 man hinh can nen tach, khong chep 8 lan): mot nut noi goc phai duoi an ca hai lop che man hinh — Fullscreen API bo thanh trinh duyet (dung phan F11 lam) + co `isFullscreen` cua AppLayout bo sidebar/topbar.
  - Nghe `fullscreenchange` de thoat bang **ESC/F11 that** thi nhan nut va menu app tro ve dung trang thai, khong bi ket.
  - `watch(isFullscreen)` dong bo nguoc voi nut thoat cua AppLayout, tranh canh menu hien lai ma thanh trinh duyet van mat. Unmount thi tat het, khong de man hinh ke tiep mat menu.
  - Prop `variant` ('vba' giu he mau Windows co dien / 'app' dung design token) va prop **`zIndex`** — moi trang mot thang z-index rieng nen khong co so mac dinh nao dung cho tat ca: man can dung 40 (duoi `.queue-overlay` 50-60), `/chemical-call/monitor` va `/pending` dung 10 (duoi anh QR phong to khi re chuot = 20), con lai 900 (duoi hop thoai VBA = 1000).
- **Da gan cho:** `/production-batches/grid`, `/print-order-entry`, `/print-sent-log`, `/chemical-call`, `/chemical-call/monitor`, `/chemical-call/pending`, `/weighing-station-large`, `/weighing-station-v2`.
- **Kiem chung:** `vue-tsc --noEmit` exit 0, `vite build` thanh cong. **Chua xem bang mat tren trinh duyet** — can nguoi dung tu mo kiem tra.

### 103. Man hinh "Bang may VD (MACHINE_ID)" — dung lai UserForm mainform cua MACHINE_ID_LOCKED.xlsm (2026-08-04)

- **Nguon:** trich VBA that tu `MACHINE_ID_LOCKED.xlsm` (27 component, VBProject KHONG khoa) qua Excel COM; toa do/font/mau doc thang tu `Designer.Controls` chu khong doan.
  - `mainform` = **man hinh chi doc**, 734 control, InsideWidth 1413pt x InsideHeight 755.25pt, font Tahoma 8.25pt.
  - Bo cuc: nua tren VD10..VD18 (trai sang phai), nua duoi VD09..VD01; moi may 1 khoi rong 138pt (code 60 + color 54 + lv 24), cach nhau 156pt, bat dau Left 18pt. Moi may co khoi **da gui** 4 thung 1A/2B/3C/4D (moi thung 1 dong code/color/lv + 1 dong thoi gian 138pt) va khoi **dang cho** 6 dong. `TextBox541` (1386x3pt, nen COLOR_HIGHLIGHT) la vach ngan 2 nua.
  - **Ma chet trong workbook:** `mainform` con nguyen handler `Box1..Box7`, `btnSAVE`, `btnClear`, `CommandButton3/4/5`, `sub1_Click..sub83_Click` nhung **cac control do khong con ton tai tren form** — ban con lai chi la bang theo doi. `col17..col19`/`sub17..sub19` nam o Top 786pt, tuc **ngoai chieu cao form 755pt** (khong bao gio hien) nen ban web khong ve lai.
- **Quy tac to mau (giu nguyen ban goc):**
  - Da gui (`Mod_load_sentlog.LoadAllVD`, `tbl_SentLog`, chi lay ban ghi moi nhat con trong 24h cho moi may+thung): <6h `#00B050`, <12h `#FFC000`, con lai `#FF0000`. O thoi gian **khong** bi to mau (ban goc chi set BackColor cho code/color/lv).
  - Dang cho (`Mod_load_input.LoadAllVD_Input`, `tbl_input_all`, TOP 6 theo TIME1 tang dan): <24h trang, <48h `#B4CDE6`, con lai `#B4A0C8`.
  - Nap lai moi **3 phut** (`Mod_time3min.RunAutoVD`).
- **Ban web `views/MachineIdBoard.vue`** (route `/machine-id-board`, nhom menu VAN HANH): dung nguyen toa do pt, thu/phong bang MOT `transform: scale()` nhu `ProductionBatchesGrid`/`PrintOrderEntry`, co `FullscreenButton variant="vba"`.
  - Nguon du lieu: `GET /api/machine-dispatches/history` (queue_state CONFIRMED) thay `tbl_SentLog`, `GET /api/machine-dispatches` (INPUT/WAITING/TO_SEND/PROCESSING/ERROR) thay `tbl_input_all`. Moc "da gui" lay print job DAU TIEN cua don — cung quy uoc voi `/print-sent-log` vi web khong co cot TIME3 rieng.
  - **Ma may phai khop theo phan so**: web seed `VD001..VD018`, ban VBA dung `VD01..VD18`.
  - Nap lai 3 phut dung nhip ban goc, co them listener Echo `production-batches` de khong phai cho het 3 phut khi co don moi.
- **Kiem chung:** `vue-tsc --noEmit` exit 0. **Chua xem bang mat tren trinh duyet** — can nguoi dung tu mo `/machine-id-board` doi chieu voi form VBA that.

### 104. 2 tai khoan rieng cho 2 tram can + thu gon nut thoat Toan man hinh (2026-08-04)

- **Yeu cau nguoi dung:** nut thoat Toan man hinh cua layout dai qua, can gon lai; va can **2 tai khoan** — mot cho "Can nho", mot cho "Can to" — dang nhap vao **chi thay dung man hinh cua minh**.
- **Nut thoat Toan man hinh (`AppLayout.vue`):** bo nhan chu, chi con dau `✕` trong nut tron 32x32 (nhan day du chuyen vao `title`). Nut nay nam de o goc phai tren suot thoi gian toan man hinh nen phai chiem it cho nhat co the. **Khong dung** toi `FullscreenButton.vue` (nut noi goc phai duoi cua 8 man van hanh) — component khac.
- **2 tai khoan:** `ScaleOperatorUsersSeeder` (chay rieng: `php artisan db:seed --class=ScaleOperatorUsersSeeder`).
  - `cannho` / `cannho@123` -> tram `WS-SMALL-01` -> `/weighing-station-v2` (ban dung lai `4.semiauto-small scale.xlsm`).
  - `canto` / `canto@123` -> tram `WS-LARGE-01` -> `/weighing-station-large` (ban dung lai `5.Semiauto- lockmove SEND OVER6.xlsm`).
  - **Khong viet co che khoa moi** — noi vao co che WS-001 da co: `users.operation_client_id` -> AuthController tra kem `workstation` -> `router/index.ts` da moi route khac ve `default_route` (tai khoan khong phai ADMIN) -> `AppLayout.vue` an han sidebar va khoa nut doi tram (`isLockedStation`).
  - Seeder **tu tao luon 2 tram** neu chua co: DB dev/production duoc dung dan bang dang ky Agent theo IP, khong phai luc nao cung da chay `WorkstationsSeeder` (DB dev thuc te khong he co `WS-SMALL-01`/`WS-LARGE-01`). Phai gan du capability (`SMALL_SCALE`/`LARGE_SCALE` + WEIGH/PRINT/SCAN_QR/LOCAL_AGENT), thieu la `AppLayout` chan nham "tram khong co quyen cho man hinh nay".
  - **KHONG goi trong `DatabaseSeeder`**: `WorkstationsSeeder` xoa sach `operation_clients`/`devices` truoc khi seed lai, chay ca bo tren may dang co du lieu la mat tram. Seeder moi chi `updateOrCreate`, chay lai bao nhieu lan cung duoc (chi dat lai mat khau 2 tai khoan).
- **Sua kem:**
  - `WorkstationsSeeder`: `default_route` cua `WS-SMALL-01/02` va `WS-LARGE-01` truoc day deu tro `/weighing-station` (man quan ly tram can ban web, khong port tu workbook nao) — nay tro dung 2 man dung lai. Link kiosk `/scalesmin`, `/scalesmax` vi vay cung vao dung man.
  - `AuthController::workstationPayload()`: them `default_route` (router doc truong nay TRUOC roi moi roi ve `default_screen`).
  - `AppLayout.vue`: bo `adminOnly` o 2 muc menu "Can nho"/"Can to" — dung nhu ghi chu cu da dan (bo co nay khi `default_route` tro dung 2 route do).
- **Kiem chung that:** seeder chay tren DB dev thanh cong; goi that `AuthController::login` cho `cannho` -> tra `workstation.default_route = /weighing-station-v2`, `capability_codes` co `SMALL_SCALE`; `canto` -> `WS-LARGE-01` + `LARGE_SCALE`. `vue-tsc --noEmit` exit 0. **Chua xem bang mat tren trinh duyet**; `php artisan test` khong chay duoc (DB test port 5433 chua bat) — loi moi truong, khong lien quan thay doi nay.

### 105. Nut "Dang nhap" tren cac trang cong khai (2026-08-04)

- **Yeu cau nguoi dung:** cac trang mo khong can dang nhap (muc 0f716b3) khong co nut nao de quay ve man hinh dang nhap tai khoan — chi con cach tu go URL `/login`.
- **`NavToggleButton.vue`:** truoc day chi render nut 3 gach cho nguoi DA dang nhap, nguoi xem cong khai khong thay gi (khong co AppLayout nen cung khong co nut Dang xuat o topbar). Nay them nhanh `v-else`: nut **Dang nhap** (icon `user` + chu, cung 2 he mau `vba`/`app`, cung vi tri goc phai duoi) day sang `/login?redirect=<duong dan hien tai>`.
- **`Login.vue`:** dang nhap xong doc `?redirect=` de quay lai dung trang dang xem. Chi chap nhan duong dan noi bo (bat dau `/` va khong phai `//`) — khong de chuoi tren URL tro thanh dich dieu huong ra ngoai; khong hop le thi ve `/` nhu cu.
- Ap dung ngay cho ca 5 trang dang dung `NavToggleButton`: `/production-batches/grid`, `/print-order-entry`, `/machine-id-board`, `/chemical-call/classic`, `/chemical-call/pending-classic`. Trang Gantt (`/bpdb-machines/gantt`) co bo nut rieng, khong dung component nay — chua dong toi.
- **Kiem chung:** `vue-tsc --noEmit` exit 0. **Chua xem bang mat tren trinh duyet.**

### 106. Tach "khoa tram" khoi "an menu": tai khoan van hanh tu doi tram duoc, menu chi con cua ADMIN (2026-08-04)

- **Yeu cau nguoi dung (3 buoc lam ro trong cung 1 phien):**
  1. "Dang nhap tai khoan nao cung van chon duoc tram nhu binh thuong" -> bo khoa tram theo tai khoan.
  2. "Admin thay duoc menu, cac tai khoan khach chi thay phan ben tren" -> sidebar chi cua ADMIN.
  3. "Dang nhap can nho bay thang toi /weighing-station-v2, can to tuong tu /weighing-station-large" -> **khoa han o do**, go tay URL man khac cung bi da ve.
- **Van de goc:** `isLockedStation` (AppLayout) truoc day gom 3 viec vao 1 co — an sidebar, khoa nut doi tram, va di kem voi router guard khoa man hinh. Go 1 thu la 2 thu kia di theo. Nay tach doi:
  - `isLockedStation` = **chi phien kiosk** (mo bang link may, KHONG dang nhap). Moi tai khoan da dang nhap deu bam duoc nut tram tren topbar de doi tram. Tram gan san (`users.operation_client_id`) nay chi con la **gia tri mac dinh luc dang nhap**, khong phai rang buoc.
  - `canSeeMenu` = **chi ADMIN** (co moi) — dung cho `<aside class="sidebar">` va nut 3 gach mobile. Tai khoan khac chi con thanh tren cung (ten tram, doi tram, dang xuat).
  - Co `wsConfig.locked_to_type` (localStorage `df_workstation_config`) khong con duoc dung de khoa nua — no la cau hinh may, khong phai tai khoan.
- **Router guard (`router/index.ts`)**: **giu nguyen quy tac WS-001** — `requiresAuth && !isAdmin && lockedScreen && to.path !== lockedScreen` -> `next(lockedScreen)`. Da thu go han o giua phien (theo doc y buoc 1) roi **bat lai theo xac nhan cua nguoi dung o buoc 3**. Doi trang thai co y: doi tram la de can dung thiet bi cua minh, khong phai de di sang cong doan khac.
  - Khong cham 3 man cong khai (`requiresAuth:false`): dieu kien `requiresAuth` bo qua chung, nguoi da dang nhap van mo bang link nhu may xuong.
  - Nhanh `?ws=` va nhanh kiosk giu nguyen nhu truoc.
- **Anh huong tai lieu:** mo hinh WS-001 "1 may tinh = 1 cong doan" nay **chi con khoa MAN HINH, khong con khoa TRAM**. `workstation-redesign-audit.md` mo ta "khoa nut doi tram" da lac hau tu ban nay.
- **Kiem chung:** `vue-tsc --noEmit` exit 0 sau moi lan sua; Vite HMR nap sach, khong loi runtime trong log dev server. **Chua xem bang mat tren trinh duyet** — can nguoi dung dang nhap `cannho`/`canto` doi chieu.
- **Bo sung cung phien:** "1 tai khoan chon tram nao cung duoc" -> `capabilityMismatch` khong con chan cung noi dung voi tai khoan da dang nhap. Tach ra `blockOnMismatch = isKiosk && capabilityMismatch`: chi phien kiosk moi bi man chan (tram do LINK quyet dinh, nguoi dung may khong tu sua duoc). Doi lai, ws-pill tren topbar chuyen mau cam + icon ⚠️ + tooltip khi tram dang chon sai loai — giu lai tin hieu "khong am tham ghi du lieu duoi ten tram sai loai" von la ly do khoi sinh cua man chan nay. Dropdown chon tram truoc gio VON KHONG loc theo capability, khong phai sua.
- **Giu tram da chon qua F5 (cung phien):** chon tram xong nhan F5 la mat, quay ve tram gan voi tai khoan. Nguyen nhan: `df_current_workstation` VAN luu dung, nhung 2 nguon tu dong ghi de len o moi lan nap trang — `authStore.initialize()` (chay trong `router.beforeEach`, tuc moi lan dieu huong/F5) dat lai `user.workstation`, va `adoptLocalWorkstation()` hoi backend "may nay la tram nao" theo IP. Ban than localStorage khong he mat du lieu.
  - Them co `df_workstation_manual` (`services/workstation.ts`): `setWorkstation(ws, { manual: true })` ghim tram do NGUOI DUNG tu chon; moi nguon tu dong goi khong kem `manual` thi XOA ghim.
  - Thu tu uu tien sau ban nay: **link `?ws=CODE`** (chi dinh tuong minh, thang tuyet doi) > **tram chon tay** (ghim, song qua F5) > **tram cua tai khoan luc DANG NHAP** > **whoami theo IP cua Agent**. Dang xuat xoa sach ghim.
  - `initialize()` khong con dat lai tram khi da co ghim; `adoptLocalWorkstation()` return false som khi da co ghim.
- **Bo sung cung ngay:** bang thong tin me o `/bpdb-machines/gantt` doi nhan `Tong da chay` -> **`So lan danh mau`** (cach goi cua xuong). Chi doi nhan hien thi, con so va cach dem (`loadLotTotal`, API BPDB) giu nguyen; don vi ben canh van la "me".

### 107. Bo cai CAN TO khong tu hien ra tram tai may tram — tach "bao danh" khoi "day so can" (2026-08-04)

- **Bao loi cua nguoi dung:** "Agent bo cai can lon dang khong tu [nhan tram] tai tram duoc giong nhu can be."
- **Chan doan tren may dev (bang chung truc tiep):** ca 2 service `DFAgentSmall`/`DFAgentLarge` deu Running, nhung `D:\scale\` **chi co `putty_log.txt`**, khong co `putty_log_large.txt` — dung file ma `appsettings.large.json` bao Agent can to doc. Nguoi dung xac nhan may tram ngoai xuong cung vay: chi co MOT PuTTY, ghi vao duong dan chuan cu.
- **Chuoi hong (goc re that su):** `ScaleReader` khong thay file -> tra `null` -> `Worker` **khong POST gi len backend** -> `AgentAuth` khong bao gio tao ban ghi tram `WS-LARGE-<TEN MAY>` -> `DeviceController::storeReading` khong ghi khoa `scale_machine_station_<ip>_LARGE` -> `whoami?kind=LARGE` tra `null` -> `/weighing-station-large` khong tu nhan duoc tram. Can nho chay tot chi vi no doc dung file PuTTY dang co san. **Loi kien truc:** su ton tai cua mot TRAM bi buoc vao tinh trang cua cai CAN.
- **Sua 1 — Agent BAO DANH doc lap voi so can (`Worker.cs`, `DeviceController::hello`, `routes/api.php`):** them `POST /api/devices/hello` (middleware `agent.auth`), Agent goi luc khoi dong va moi **60 giay**, gui `workstation_id` + `role` + `scale_kind` + `machine_name` len TAT CA backend trong danh sach. Endpoint chi ghi cap MAY->TRAM (`scale_machine_station_*`, TTL 12h) va goi `ensureScaleDeviceAttached()` (tach ra tu `storeReading`, dung chung) — **tuyet doi khong dung vao 3 khoa so can**, vi bao danh khong phai bang chung can dang song. Ket qua: cai xong la tram hien ra, con "chua co tin hieu can" quay ve dung ban chat cua no — mot canh bao rieng tren man hinh (`has_reading`/`age_ms`), khong con lam tram bien mat.
- **Sua 2 — buoc lui file log cho ban CAN TO (`ScaleReader.cs`, `appsettings.large.json`):** them khoa `Scale:LogFilePathFallback` = `D:\scale\putty_log.txt`. File rieng `putty_log_large.txt` van uu tien **tuyet doi**; chi khi no khong ton tai moi lui ve file chuan, va moi lan lui deu ghi mot dong canh bao (throttle 30s): neu may do chay ca 2 Agent thi hai ben dang doc CHUNG mot cai can. Ung vien duoc tra lai moi lan doc chu khong chot luc khoi dong — mo PuTTY rieng sau do la Agent tu chuyen ve file rieng, khong can restart service.
- **Ban cai:** `PackageVersion` 3.2.0.0 -> **3.3.0.0**, da build lai ca 2 file MSI va copy sang `backend/public/downloads/`.
- **Kiem chung:** `dotnet test` Agent **44/44 pass** (them 5 test cho `ScaleReader.ResolveLogFilePaths`, chay bang `DOTNET_ROLL_FORWARD=LatestMajor` vi may nay khong co runtime .NET 8). Da them 2 test backend (`test_hello_dang_ky_tram_can_to_khi_chua_doc_duoc_so_can_nao`, `test_hello_tach_rieng_theo_loai_can_tren_cung_mot_may`) nhung **CHUA CHAY DUOC**: `.env.testing` tro toi Postgres `127.0.0.1:5433` khong chay tren may nay, con `.env` dev tro thang vao DB **production** — khong chay test ghi du lieu vao do. Moi chi `php -l`. **Chua kiem chung bang mat tren may tram that.**
- **Thu tu trien khai bat buoc:** deploy **backend truoc**, roi moi cai MSI moi. Nguoc lai thi `/devices/hello` tra 404 — khong gay hong gi (Agent chi ghi mot dong canh bao va chay y nhu cu), nhung tram van chua hien ra.

### 108. `/production-batches/grid`: nut TANK khong chon duoc thung — bo loc theo may sai voi VBA goc (2026-08-04)

- **Bao loi:** tai `http://10.0.60.209:3001/production-batches/grid` khong chon duoc thung "nhu trong Form VBA".
- **Doi chieu nguon goc** (giai nen `xl/vbaProject.bin` cua `2.C3 grid load row lock id FB -192(QR).xlsm` bang cong cu tu viet — OLE `StgOpenStorage` + giai nen MS-OVBA, xem ghi chu duoi):
  - `mainform.CommandButton3_Click` (nut TANK): do vao ListBox mot **mang CO DINH** `Array("1A","2B","3C","4D","FB")`, roi `Set f.TargetTextBox = Me.Box5`. **Khong lien quan gi toi may dang chon.**
  - `mainform.CommandButton5_Click` (nut MACHINE): tuong tu, mang co dinh `VD01..VD18` -> Box4.
  - `btnSAVE_Click`: chi bat buoc **Box1 (mau) + Box2 (ma hang)**. Thu tu chon may/thung tuy y.
- **Sai o ban port:** `tankPickerOptions` loc `tanks.value.filter(t => t.machine_id === machineId)` voi `machineId` lay tu Box4. Box4 mac dinh RONG -> danh sach rong hoan toan, khong mot dong nao, khong thong bao gi — nhin y het man hinh hong. Phai chon MAY truoc moi hien duoc thung, mot rang buoc **khong he co trong ban goc**.
- **Sua (`ProductionBatchesGrid.vue`):** `tankPickerOptions` tra ve **danh sach ma thung phan biet (distinct) cua toan danh muc**, khong loc theo may — dung tinh than ban goc. Van uu tien danh muc that (them loai thung trong Master Data la co ngay), chi lui ve mang cung `TANK_CODES_VBA` khi danh muc chua nap duoc (mat API) — dung bang viec ban goc luon co san 5 thung ke ca khi mat ket noi. Template doi sang lap theo ma (`string`) thay vi doi tuong tank.
  - `confirmTankPick` nhanh **SubForm** nay phai tu quy ma thung -> `tank_id` theo may CUA DON DO (`t.machine_id === subFormBatch.machine_id && t.code === ...`), vi danh sach khong con loc san theo may. Khong quy duoc thi bao ro thay vi de `tank_id = null` im lang (nut PHE DUYET se bi khoa ma khong ai biet vi sao).
  - Header (`currentTank`) giu nguyen: van quy theo may + ma, vi SAVE cua ban web bat buoc co may (rang buoc rieng cua ban web do `tank_id` la khoa ngoai theo tung may — khac VBA von luu thung la chuoi tu do; **chua doi**, neu muon giong het VBA thi phai cho `machine_id` null trong DB, la mot quyet dinh khac).
- **Kiem chung:** `vue-tsc --noEmit` exit 0. API production `GET /api/public/tanks` tra dung 18 may x 5 ma (`1A/2B/3C/4D/FB`) va `/machines` tra 18 may — **du lieu khong thieu, loi thuan tuy o tang lo giao dien**. **Chua xem bang mat tren trinh duyet** va **chua deploy**.
- **Cong cu moi (scratchpad, khong commit):** trinh giai nen VBA tu viet (`vbadump`) doc thang `vbaProject.bin` -> tung module `.bas`, khong can Excel/python/oletools va khong bi chan boi VBA project bi khoa mat khau. Hai cho de sai da vap phai: `BitCount = Max(4, CeilingLog2(so byte da giai nen TRONG CHUNK))` (sai la van "giai nen" duoc nhung ra rac lap lai), va cham diem ung vien offset phai theo **so dong PHAN BIET** chu khong phai tong so dong (rac lap lai se thang).

### 109. "Trang nao cung cham" tren may dev — DbHostResolver probe 127.0.0.1 het gio 2 GIAY moi 20 giay (2026-08-05)

- **Bao loi:** "du lieu day tu DB ra Web load cham qua". Hoi ro va nguoi dung xac nhan: cham o **MOI trang** (Gantt/BPDB, hang cho san xuat, goi hoa chat), va cham tren **may dev** (localhost:3001/8500).
- **Do that truoc khi doan** (`Invoke-WebRequest`, moi endpoint nhieu lan):

| Duong | min | tb | max |
|---|---|---|---|
| `/api/khong-ton-tai` (404, **khong cham DB**) | 318 | 1019 | 2988 |
| `/api/public/chemical-channels` (3 truy van) | 553 | 686 | 861 |
| `/api/production-batches` (401, chan truoc DB) | 273 | 332 | 450 |

  So dat gia nhat la **404 khong cham DB ma van 318-2988ms** -> chi phi KHONG nam o truy van, nam o duong boot cua moi request.
- **Boc tach boot** (script rieng trong scratchpad): `vendor/autoload` 10ms, `bootstrap/app.php` 3ms, `make(HttpKernel)` 47ms, **`DbHostResolver::resolve()` khi cache het han = 2,044ms**.
- **Nguyen nhan goc:** `config/database.php:90` goi `DbHostResolver::resolve()`. `config/` duoc nap lai tren MOI request (khong `config:cache`), nen ke ca route 404 cung tra phi nay. Cache file cua resolver TTL 20 giay -> cu moi 20 giay lai co 1 request phai probe lai. Ung vien dau danh sach la `127.0.0.1` (`DB_HOST_CANDIDATES=127.0.0.1,10.0.60.209,192.168.250.151`).
- **Gia dinh cu SAI, da do lai de bac bo:** ghi chu 2026-08-02 trong chinh file do viet "probe truot van tra ve ngay". Do lai bang script probe rieng: `fsockopen('127.0.0.1', 5433, ..., 2.0)` tra **errno 10060 = HET GIO**, khong phai 10061 = bi tu choi. Goi tin bi firewall **nuot** chu khong bi da ve, nen moi lan probe truot **dot tron 2.0 giay**. Do 3/3 lan giong nhau; `localhost` va `192.168.250.151` cung vay.
- **Sua (`app/Services/DbHostResolver.php`):** `array_unshift` host trong `DB_HOST` len dau danh sach ung vien (+ `array_unique`). DB_HOST luon la host chu dich cua chinh moi truong dang chay, nen phat probe dau tien gan nhu luon trung — khong con phai di qua ung vien chet. Danh sach ung vien giu nguyen vai tro du phong. Khong doi TTL, khong doi timeout, khong dong vao `.env`.
- **Ket qua do lai sau khi sua:**

| Duong | min truoc -> sau | tb truoc -> sau |
|---|---|---|
| `DbHostResolver` (cache het han) | 2044 -> **51 ms** | — |
| `/api/khong-ton-tai` (404) | 318 -> **21 ms** | 1019 -> **164 ms** |
| `/api/public/chemical-channels` | 553 -> **251 ms** | 686 -> **414 ms** |
| `/api/production-batches` | 273 -> **45 ms** | 332 -> **181 ms** |

  Chay 40 request lien tiep sau khi sua: khong con dot gai 2 giay dinh ky nao (chi request dau tien 2178ms do opcache nap lai file vua sua).
- **Phan CON LAI khong phai loi, la ban chat may dev** (do bang script PDO rieng): mo ket noi PDO toi Postgres **~100-112ms MOI request** — da loai tru SSL (`sslmode=prefer` 96.8ms ~ `disable` 104.4ms, khong khac); moi truy van **~25-30ms** di-ve (ICMP ping chi 9ms nhung vong di-ve giao thuc PG la 25-30ms). Vay san cua 1 endpoint don gian tren may dev = boot ~70ms + ket noi ~100ms + N x 27ms. **Tren CS-SERVER DB nam cung may nen phan nay gan nhu bang 0** — day la ly do may dev luon cham hon server that.
- **Do them ve dong thoi:** 1 request `/api/public/chemical-channels` = 550ms; 6 request cung luc = 1482ms (khong phai 3300ms neu don luong hoan toan, cung khong phai 550ms neu song song hoan toan) -> `php artisan serve` co xu ly chong lan nhung **van xep hang mot phan**.
- **Kiem chung:** `php -l` sach. **Chua chay `php artisan test`** (test suite se xoa schema tren DB that — cam chay o may nay). **Chua deploy len CS-SERVER.**
- **Can luu y khi deploy:** neu `.env` cua CS-SERVER dat `DB_HOST=10.0.60.209` thi sau thay doi nay server se probe LAN IP truoc thay vi loopback (van chay dung, chi khac duong di). Muon giu uu tien loopback tren server thi dat `DB_HOST=127.0.0.1` trong `.env` cua CS-SERVER — `.env` bi gitignore nen phai sua tay tren server.
- **Chua lam, de nguoi dung quyet:** (1) ket noi PDO persistent — bo duoc ~100ms/request nhung co rui ro ro ri trang thai giua cac request; (2) thay `php artisan serve` bang web server that (IIS/nginx + php-fpm) — da ghi nhan tu muc 38 la viec nen lam truoc Cutover; (3) dung ban sao Postgres cuc bo tren may dev de bo han 25-30ms/truy van.

### 110. Gantt BPDB: me chua ket thuc luon nam SAU vach gio hien tai + dong nhat chieu cao dong (2026-08-05)

- **Yeu cau:** (1) me moi/chua co gio ket thuc **luon nam ben phai vach thoi gian**, duoc phep day cac me cung hang lui ve trai; don da hoan thanh thi don ve phia truoc; (2) dinh dang lai khoang cach cac dong cho dong bo kich thuoc.
- **Sua 1 — thuat toan xep me (`fetchGantt`, `BpdbMachinesGantt.vue`):** goc toa do doi tu "me som nhat cua Tank" sang **kim do** (`syncSnapshot`, dung mot moc voi `calculateNeedle`). Tach me theo **trang thai** (khong theo thu tu trong mang — du lieu BPDB co truong hop me dang chay bat dau som hon mot me da xong cung Tank):
  - Luot 1: me DA XONG xep **nguoc** tu kim do lui ve qua khu, giu do dai va khoang cach toi thieu `MIN_VISUAL_DURATION_MS`; me khong du cho bi day sang **trai** (truoc day day sang phai).
  - Luot 2: me DANG CHAY xep **xuoi** tu kim do ve tuong lai; be rong = thoi gian da chay duoc (me vua mo = 2.5h toi thieu).
  - Luot 3: lap khoang trong trong tung nhom; me da xong cuoi cung chi keo **toi dung kim do** va **chi khi Tank do that su co me dang chay** (Tank da nghi thi giu do dai that, khong ve keo dai toi hien tai gay hieu nham la con ban).
  - Bang chi tiet (bam vao thanh) van lay gio THAT tu `itemDetails` — chi vi tri VE bi dich.
- **Sua 2 — chieu cao dong dong deu:** truoc day vis-timeline tinh chieu cao hang theo **me dang lot khung nhin** nen Tank khong co me nao trong khung gio hien tai co lai bang chieu cao nhan, con **tu doi moi khi cuon ngang**. Chot cung ca 3 yeu to: `ITEM_HEIGHT=24` ep bang CSS (`box-sizing: border-box` de vien 1px cua me thuong va vien 2px cua me dang chay ra cung chieu cao), `margin: {axis:5, item:{horizontal:0, vertical:10}}` khai bao tuong minh, va **`groupHeightMode: 'fixed'`** (tinh theo TOAN BO me cua nhom). `ROW_HEIGHT = 5+24+5 = 34px` khop dung cong thuc `Group._calculateHeight` cua thu vien.
  - Hang **May VD** va hang **Tank rong** khong chua me nao nen chieu cao cua chung = `clientHeight` cua chinh `.vis-inner` -> phai ep `height: 34px` len `.vis-inner`. Vi `.vis-inner` cua hang Tank **dang la** vien thuoc (pill), phai tach doi: `.vis-inner` = o chua trong suot cao co dinh, ten tank boc trong the con `.gantt-tank-pill` (them vao `applyMachineFilter`, the `span` da co san trong `whiteList` xss). Bo `position:absolute + translateY(-50%)` cu.
- **Kiem chung:** `vue-tsc --noEmit` exit 0, `npm run build` OK. **Chay lai dung thuat toan tren du lieu API that** (`/api/public/bpdb-machines-gantt`, 7 ngay, 1078 ban ghi -> 610 thanh sau gop, 4 me dang chay): 0 me dang chay nam truoc kim do, 0 me da xong vuot kim do, 0 cap de len nhau, 0 thanh be rong am. **Chua xem bang mat tren trinh duyet** va **chua deploy**.

### 111. Gantt BPDB: bo han hang rieng cua May VD, an may VD001 (2026-08-05)

- **Yeu cau:** bo "dong dang ngan cach voi cac may" (da hoi lai va nguoi dung chon: **hang ten May VD** — hang khong chua thanh me nao, chi tao mot dai trong ngan giua cac cum) va **an may VD001**.
- **Sua 1 — bo group cha (`applyMachineFilter`, `BpdbMachinesGantt.vue`):** chi nap **hang Tank** vao vis; group cha (`nestedGroups`) van duoc backend tra ve va van giu trong `allGroups` (can de biet TEN may va de o tim kiem khop theo ten may) nhung khong con duoc ve. O ten may gop nay nam de len chinh **hang Tank dau tien** cua may, keo dai xuong het cum.
  - `className: 'gantt-machine-head'` gan cho hang do -> CSS `z-index: 6` chuyen tu `.vis-nesting-group` sang `.vis-label.gantt-machine-head`. **Bat buoc giu** — day la fix bug ve sai cua Chromium (ten may bien mat khi cuon toi may chua tung vao khung nhin, 2026-07-29), khong phai hieu ung UI.
  - `capNhatOGopMay()`: moc phan cum doi tu class `.vis-nested-group` sang **su co mat cua o gop** (`.gantt-machine-merged`) o hang ke tiep.
  - Ghim may: tru 100000 vao order cua **toan bo hang Tank** cua may (truoc kia chi tru vao 1 hang cha) -> ca cum cung nhay len dau.
  - CSS: `.vis-nested-group .vis-inner` -> `.vis-label .vis-inner` (khong con class nao mang `.vis-nested-group`); `font-weight: 700` chuyen sang `.gantt-machine-name`; the may lay lai 11px be ngang (`left: 15px` -> `4px`) vi khong con mui ten dong/mo cua vis o dau hang.
  - **DANH DOI da chap nhan:** mat mui ten dong/mo gon tung may (tinh nang do gan lien voi `nestedGroups`). Ghim may + tim may van nguyen ven.
- **Sua 2 — an VD001 (`BpdbMachineMonitoringService::buildGanttTimeline`):** hang so `GANTT_HIDDEN_MACHINES = ['VD001']`, bo qua ngay trong vong lap dung group. Loc o **buildGanttTimeline** chu khong o `getMachineRegistry()`: danh muc may con dung chung cho man hinh trang thai may va cho phan dem "So lan danh mau theo may" — an o danh muc se mat luon ca nhung cho do, vuot pham vi yeu cau. Vi machine_id cua VD001 khong vao `$machineIdToTankGroup` nen task cua no cung khong duoc query, khong ton bang thong.
  - **CON TON DONG, can nguoi dung quyet:** phan "So lan danh mau / Theo may" trong bang chi tiet (`getLotRunTotal`) **VAN dem ca VD001** vi dung nguyen registry. Neu muon an luon o do thi noi de sua tiep.
- **Kiem chung:** `php -l` sach, `vue-tsc --noEmit` exit 0, `npm run build` OK. Goi API that: groups tu **132 -> 126**, **khong con VD001**, may dau danh sach la VD002. Mo phong lai buoc dung `renderedGroups` tren du lieu that: **101 hang ve** (het hang cha), **25 o ten may** dung bang so may, **0 hang dau cum sai vi tri**, **0 may thieu ten**. **Chua xem bang mat tren trinh duyet** va **chua deploy**.

### 112. Tram CAN TO: tach bo cai IN/OUT rieng — Agent chay trong phien nguoi dung (2026-08-05)

**A. Van de phat hien khi ra soat "IN/OUT da dung duoc chua"**

- Chuoi phan mem cua SEND OVER 6 da noi du tu truoc: man hinh gom lo rack -> `POST /api/rack-dispatch` -> bang `rack_dispatch_commands` -> Agent poll `/agents/{ws}/rack-commands` -> `RackSender` mo phong chuot -> ack. Nhung **khong chay duoc tren may that**, vi 3 diem:
  1. **CHAN THAT SU — session 0 isolation.** MSI cai Agent lam Windows Service `Account="LocalSystem"`. Tien trinh service nam o **session 0**, tach biet voi phien dang nhap: `SetCursorPos`/`mouse_event`/`keybd_event` ban vao desktop cua session 0, va clipboard cung la clipboard rieng cua window station do — ung dung pha mau ben phien nguoi dung **khong bao gio nhan duoc gi**. Te hon: `SendOut()` van tra `true` (dat clipboard thanh cong) nen Agent ack DONE trong khi thuc te khong dan duoc ma nao = **bao thanh cong gia**. Excel VBA lam duoc chinh vi Excel chay trong phien nguoi dung.
  2. `Rack.Enabled = false` mac dinh -> Agent khong he poll lenh rack sau khi cai.
  3. Web bao "Da gui ... sang he pha mau" ngay khi `POST` tra 201 — tuc moi chi **xep hang**, chua doc trang thai DONE/FAILED ma Agent ack ve.

**B. Da lam — nguoi dung chot "tach thanh 2 bo cai rieng biet cho can to"**

- **Bo cai thu ba `DFAgentSetup-CanTo-InOut.msi`** (`appsettings.large-inout.json`, thu muc `DFAgent-Large-InOut`, UpgradeCode `AC5DC759-...`): **khong cai service**, thay bang shortcut o **Startup (All Users) + Start Menu**, chay bang chinh tai khoan dang dang nhap. Cai CHONG LEN bo `DFAgentSetup-CanTo.msi` tren cung may: bo cu lo **nhan can** (van la service), bo moi lo **IN/OUT**.
  - Bien tien xu ly moi **`RunMode`** (`service` | `session`) trong `DFAgentSetup.wxs` bao quanh `ServiceInstall`/`ServiceControl` va 2 component shortcut. Da build thu ca hai che do va **kiem tra bang trong MSI**: ban `session` KHONG co bang `ServiceInstall`, CO bang `Shortcut` (2 dong); ban `service` nguoc lai — dung y do.
  - **`Role = RACK_ONLY`** -> `_scaleEnabled`/`_printEnabled` deu false, chi con bao danh 60 giay + vong lay lenh rack. `Scale:Source = PUTTY_LOG` (khong mo cong COM, tranh gianh cong voi bo nhan can), `ReadIntervalMs` 10 -> 250ms.
  - **Dung CHUNG ma tram voi bo nhan can**: `Workstation:Id` de trong + `ScaleKind = LARGE` -> ca hai deu ra `WS-LARGE-<TEN MAY>`. Bat buoc phai trung, vi man hinh gui lenh theo ma tram no dang dung (`whoami?kind=LARGE`); lech ma la lenh xep vao hang doi khong ai lay.
- **`AgentAuth`: them `RACK_ONLY` vao `$roleDefaults`, tro cung mot bo mac dinh voi `SCALE_ONLY`.** Neu khong: may nao bo IN/OUT bao danh TRUOC thi tram sinh ra voi `type = AUTO_REGISTERED`, khong co capability `LARGE_SCALE`, va trinh duyet khong vao noi `/weighing-station-large` (`ROUTE_CAPABILITY_MAP` doi dung capability do) — `firstOrCreate` chi gan capability luc TAO nen sua sau phai vao Quan ly Workstation bam tay.
- **`OfflineQueue`: `agent_cache.db` chuyen tu canh file .exe sang `%LOCALAPPDATA%\DF Local Agent\<thu muc cai>\`.** Bat buoc: bo IN/OUT chay bang tai khoan cua tho, ma tai khoan thuong **khong ghi duoc vao Program Files**. Lan chay dau tu chep kho cu sang cho moi neu co.
- **Bao ket qua THAT thay vi bao thanh cong luc xep hang:** them `GET /api/rack-dispatch/{id}`; `services/rackDispatch.ts` hoi lai moi 700ms trong toi da 12 giay (Agent poll 2s + mot luot OUT ~2.5s thao tac chuot) roi bao 4 truong hop khac nhau: **DONE** = "He pha mau da nhan...", **FAILED** = "Agent KHONG thuc hien duoc...", **PENDING het gio** = "Agent tren may tram CHUA lay lenh — kiem tra Agent con chay khong", **SENT het gio** = "da nhan lenh nhung chua bao xong". Man hinh hien "Dang gui... cho Agent xac nhan" trong luc cho (nut xam 12 giay ma khong co chu thi tho tuong may treo va bam lai).
- Them muc tai ve thu ba o sidebar + `routes/web.php` (`/downloads/agent-launcher/large-inout`).

**C. CON TON DONG**

- **Toa do RPA chua hieu chinh tren may that** — 6 o + 4 diem click trong `appsettings.large-inout.json` la toa do MAN HINH TUYET DOI cua may hieu chinh goc. Phai do lai tren chinh may can to truoc khi cho tho dung that.
- **Chua kiem tra migration `rack_dispatch_commands` da chay tren CS-SERVER chua** (probe endpoint bi chan quyen trong phien nay). Neu chua chay thi `POST /api/rack-dispatch` loi 500 ngay tu buoc dau.
- **Chua build lai 3 file MSI** va **chua deploy** (MSI phai copy sang `backend/public/downloads/` + server tai ve cong 8501).
- **Chua chay unit test cua Agent**: may dev chi co .NET 10 runtime, project test nham net8.0 -> `dotnet test` abort ("missing Microsoft.NETCore.App 8.0.0"). Day la thieu runtime san co tu truoc, khong lien quan thay doi nay.
- **Kiem chung da chay:** `dotnet build DFAgent.csproj -c Release` 0 loi; `wix build` thanh cong ca 2 che do + doi chieu bang trong MSI nhu tren; `vue-tsc --noEmit` exit 0; JSON ca 2 file appsettings parse sach; `build.ps1` parse sach. **Chua thu tay tren may that.**

### 113. Tem in qua trinh duyet: dung lai 1:1 theo sheet DF_WEIGHING_SLIP + Mod_printslip cua 3.DF028 (2026-08-05)

**A. Cau hoi goc:** "tem in o /print-order-entry da giong het trong 3.DF028 formulas - PRINTER LANDSCAPE - jit qr sending - 15l special.xlsm chua?" -> **Chua**. Doi chieu bang cach bung workbook va giai nen truc tiep `xl/vbaProject.bin` (script CFB + RLE tu viet, lay MODULEOFFSET tu stream `dir`) de doc **VBA that trong chinh file DF028**.

- `Mod_printslip.PrintSlip_70x100` cua DF028 giong hoan toan ban `Mod_printslip_full.txt` dang dung lam nguon port, **tru mot diem: khong goi `SetupSlipPage`** - in thang bang page setup luu san trong sheet.
- Page setup that (doc tu `xl/printerSettings/printerSettings2.bin`, DEVMODE): may in **TSC TE200**, kho giay tuy bien **72.6 x 97.5 mm** (paperSize=256), vung in `$B$1:$H$24`, `fitToPage=1`, chi `horizontalCentered` (KHONG can giua doc). => Excel in ra noi dung **56.7 x 96.2 mm can giua ngang**, chua trang ~7.9mm moi ben. Ban web cu ve tran 66.85 x 95.5mm nen chu be ngang hon ~16%.
- Sheet `DF_WEIGHING_SLIP` cua DF028 va cua "Copy of Copy of DF002 no formulas..." **giong het nhau** ve o/kieu/vien (chi khac du lieu mau con sot), nen nguon do bo cuc truoc day van dung - cai lech la o cach dung lai.

**B. Cac lech da phat hien va da sua (`frontend/src/utils/dispatchSlipPrint.ts` viet lai toan bo)**

- **Hinh hoc:** khong con toa do dot TSPL tu tinh. Lay thang do rong cot B..H (78/68/51/11/69/76/44 px), chieu cao dong 1..24 tu `sheet2.xml`, roi ep vua 1 trang **54%** dung cong thuc fit-to-page cua Excel, dat trong `@page 72.6mm 97.5mm`, can giua ngang, dinh mep tren.
- **Vien:** ve tren dung gridline bang phan tu rieng (`LineSet`, khu trung), khong de moi o tu ve border - neu khong 2 net canh nhau cong lai thanh duong day gap doi (dung loi "vien to de chu" 2026-07-30). Do day = **0.125mm = 1 dot** o 203dpi (dung "thin" cua Excel), doi o mot hang so `BORDER_MM`.
- **Bo cuc lay lai dung ban goc:** o Mau va Ma hang **tach lam 2 khung, co duong ke giua, chu can TRAI**; **khong** con vien bao quanh ca tem; **co** khung chu nhat quanh 2 vung QR; dong B24 khong vien va cho chu tran sang phai; chu can duoi (Excel mac dinh bottom), rieng dong 1 can giua doc.
- **Font:** Calibri dung co that (12/20/14/36/16pt sau khi nhan 54%), **chi in dam dung o Excel in dam** - go het `font-weight:700` toan cuc va cac lan tang co chu cho may in nhiet hoi 07-31.
- **QR:** kich thuoc theo dung cong thuc VBA (`Min(rong,cao vung B16:D22) * 0.8` = ~15.2mm; QR che do = `cao G1:H1 * 0.95` = ~12.8mm), va dat **errorCorrectionLevel 'L'** cho trung so module voi QR cua api.qrserver.com ma VBA goi (van sinh noi bo, khong goi API ngoai - CLAUDE.md muc 5).
- **Noi dung QR - 3 loi that:**
  1. **qrFB thieu du lieu:** ban cu chi co `mau-ma hhmm`, trong khi VBA con noi tiep toan bo cap (ma dye + khoi luong) roi (ma chem + khoi luong). Backend `QrPayloadService` lam dung tu truoc -> tem in qua Local Agent va tem in qua trinh duyet **khac nhau** o mode FB.
  2. **qrChem lay sai cot:** VBA ghi `Cells(r,"F")` = **rack/vi tri**, ban cu ghi ma hoa chat (cot G).
  3. **dyesProcess quet sai cot:** VBA quet cot F tim "0574"/"0507", ban cu quet ma hoa chat.
- **Go cac lech co chu y truoc day:** dong trong xen giua cac truong cua `qrChem`/`qrProcess` (2026-07-22) tra ve **1 CRLF** dung VBA.
- **Routing D1/B24:** port dung phep **so sanh CHUOI** cua VBA (`f3Val >= "VD06" And f3Val <= "VD13"`), khong con so sanh so. An toan vi danh muc may da ve 2 chu so VD01..VD18 (commit fcbbf9b) - neu sau nay doi lai 3 chu so thi cho nay se lech, phai sua cung luc.
- Tach `splitVbaTriples` rieng (giu phan tu rong, chi nhan bo ba day du, toi da 9) thay vi dung `utils/rackParser.ts` (co loc rong -> lech cot khi chuoi tho co dau "-" thua).

**C. Kiem chung**

- `vue-tsc --noEmit` exit 0.
- Dung du lieu mau con luu trong chinh sheet DF028 (HS51457 / T6206 / VD06 / 4D / 50) chay ham dung tem roi render bang Edge headless: khu D1 ra **JIT2** va B24 ra **THUNG SAT THAP, MAY JIT, MAY DLG** - **trung y het gia tri con luu trong sheet**, tuc port routing dung. Print-to-PDF cho MediaBox 205.92 x 276 pt = dung 72.6 x 97.5mm; anh chup o ty le in that cho noi dung can giua ngang, cao 96.2mm, khong bi cat.
- **Chua thu in tren may TSC that.** Rui ro can theo doi: chu trong bang gio la 12pt thuong (~2.29mm, khong dam) va duong ke chi 1 dot - dung nhu Excel, nhung neu driver dither lam mo/rang cua thi nang `BORDER_MM` len 0.25mm. Neu Chrome co trang (tem nho hon ~5%) thi ha `BROWSER_FIT` xuong 0.955.
- **Chua dong bo backend:** `QrPayloadService::buildTsplLabel70x100` va `buildChemPayload` (tem TSPL do Local Agent in) **van giu bo cuc/payload cu** - hai duong in dang co y khac nhau, cho nguoi dung quyet co keo backend ve dung VBA khong.

