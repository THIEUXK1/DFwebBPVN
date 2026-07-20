# Development Roadmap - Lộ trình Triển khai Dự án DF (14 Phases)

Lộ trình triển khai dự án chuyển đổi Excel VBA + Access sang Web PostgreSQL được tối ưu hóa thành 14 Phase chi tiết theo chỉ thị của tài liệu `phase.docx` nhằm kiểm soát rủi ro vận hành và đảm bảo an toàn sản xuất.

---

## Trạng thái các Phase Hiện tại

| Phase | Tên Phase | Trạng thái | Ghi chú |
|---|---|---|---|
| **0** | Khảo sát và `.claude` | **ĐÃ HOÀN THÀNH** | Khóa bối cảnh, phạm vi, kiến trúc và roadmap. |
| **1** | Nền tảng dự án | **ĐÃ HOÀN THÀNH** | Dựng repo, stack Laravel + Vue + .NET Agent, Docker PostgreSQL, health check. |
| **2** | Database & Migration | **ĐÃ HOÀN THÀNH** | Sửa lệch cột WAITING/ToSend2, import 145k dòng lịch sử, validation đối soát khớp 100%. |
| **3** | Auth / RBAC / Audit | **ĐÃ HOÀN THÀNH** | Tài khoản admin, vai trò, Laravel Sanctum token, audit log JSONB bất biến. |
| **4** | Danh mục & Công thức | **ĐÃ HOÀN THÀNH** | Số hóa máy, thùng, vật tư, cấu hình nước nóng/lạnh, công thức có version. |
| **5** | Lệnh & Điều phối | **ĐÃ HOÀN THÀNH** | Tạo lô nhuộm (Batch), hàng chờ điều phối (Queue), logic locking, dispatch máy. |
| **6** | Cân & Scale Agent | **ĐÃ HOÀN THÀNH** | Nhiệm vụ cân, stable filter, Local Agent (.NET) trạm cân, offline buffer SQLite. |
| **7** | QR & Print Agent | **ĐÃ HOÀN THÀNH** | Sinh QR nội bộ, tem TSPL máy in TSC TE200, Print Agent, hàng đợi in, reprint audit. |
| **8** | Vận chuyển | **ĐÃ HOÀN THÀNH** | Theo dõi nguyên liệu từ cân tới thùng 1A/2B, cấu hình SLA thời gian, cảnh báo muộn. |
| **9** | Cấp máy | **ĐÃ HOÀN THÀNH** | Kiểm soát điều kiện đủ nước/nguyên liệu, ghi nhận cấp vào máy nhuộm, manual override. |
| **10** | Troubleshooting & Realtime | **ĐÃ HOÀN THÀNH** | Khởi tạo tri thức chẩn đoán lỗi nhuộm, xây dựng Realtime Dashboard (SSE) và Rule Engine cảnh báo trễ hạn. |
| **11** | Báo cáo | **ĐÃ HOÀN THÀNH** | Thống kê tiêu hao bột màu/hóa chất, sai số dung sai/override, sản lượng máy nhuộm, Pareto sự cố, xuất Excel/PDF, Audit Log Explorer. |
| **12** | UAT & Chạy song song | *CHƯA BẮT ĐẦU* | Vận hành song song hệ thống Web và Excel cũ tại phân xưởng pilot trong 7 ngày. |
| **13** | Cutover & Go-Live | *CHƯA BẮT ĐẦU* | Backup Access lần cuối, khóa Read-only, go-live 100% hệ thống Web, Hypercare. |

---

## Chi tiết các Phase

### PHASE 0 – Khảo sát và `.claude`
- **Mục tiêu:** Đồng thuận thuật ngữ, cấu trúc dữ liệu bị lỗi lệch cột, xác minh quy trình hiện tại và phê duyệt stack công nghệ.
- **Trạng thái:** **Đã hoàn thành**. Môi trường tri thức `.claude` được khởi tạo mới hoàn toàn với 16 file markdown lưu trữ tri thức dự án DF.

### PHASE 1 – Nền tảng Dự án (Project Foundation)
- **Mục tiêu:** Khởi dựng hạ tầng phát triển độc lập cho phân hệ Backend, Frontend, Database và Local Agent.
- **Trạng thái:** **Đã hoàn thành**. Khởi tạo mã nguồn backend (Laravel 12), frontend (Vue 3 Vite 5 trên cổng 3001) và agent (C# Worker Service). Setup Docker Compose Postgres 15 chạy cổng 5433 (tránh xung đột với Postgres bản địa cổng 5432).

### PHASE 2 – Database & Migration (Xử lý Staging và Transform)
- **Mục tiêu:** Hoàn thiện và chạy thử quy trình di trú dữ liệu Access sang PostgreSQL, sửa đổi hoàn toàn lỗi lệch cột.
- **Trạng thái:** **Đã hoàn thành**. Chạy transform dữ liệu từ Access sang Postgres app schema thành công, bảo toàn khóa tự nhiên và sửa lệch cột cho 145,721 dòng cân dyes/chems lịch sử (Reconciliation khớp 100%).

### PHASE 3 – Authentication, RBAC & Audit Log
- **Mục tiêu:** Thiết lập tài khoản và cơ chế bảo vệ phân quyền theo vai trò (RBAC) cùng ghi nhật ký audit.
- **Trạng thái:** **Đã hoàn thành**. Đăng nhập JWT qua Laravel Sanctum, mã hóa mật khẩu admin/admin123 bằng BCrypt. Sửa đổi bảng Sanctum `tokenable_id` tương thích UUID. Cài đặt Audit Log Service tự động lưu vết JSONB vào `app.audit_logs`.

### PHASE 4 – Danh mục & Công thức sản xuất (Master Data & Formula)
- **Mục tiêu:** Số hóa danh mục thiết bị, vật tư và bộ công thức sản xuất, tra hệ số công nghệ.
- **Công việc:**
  1. Xây dựng danh mục máy nhuộm (VD01-VD18), nhóm máy, line, thùng 1A/2B, thuốc nhuộm, hóa chất/trợ chất, rack và quy trình.
  2. Xây dựng cấu hình nước nóng/lạnh, nước trợ chất và tổng nước theo nhóm máy/thùng.
  3. Seed dữ liệu thực tế: nhóm VD06-VD13 và nhóm VD01-VD05, VD14-VD16.
  4. Quản lý công thức sản xuất có phiên bản (`recipe`, `recipe_version`, `recipe_material`, `process_parameter`). Không ghi đè công thức đang dùng (tạo version mới).
  5. Quy trình phê duyệt công thức: `draft` -> `submitted` -> `approved` -> `obsolete`.
  6. Import danh mục/công thức từ Excel có tính năng validation và preview.
- **Exit criteria:** Kết quả tính lượng nước và công thức định lượng bột màu/hóa chất khớp 100% với Excel VBA cũ (chạy Golden Master Test cho 50 mẻ mẫu).
- **Test bắt buộc:** Golden Master Test, Water calculations unit test, Formula versioning validation.

### PHASE 5 – Lệnh sản xuất & Điều phối máy (Production Orders & Dispatch)
- **Mục tiêu:** Thay thế logic hàng chờ/khóa dòng của VBA bằng workflow và locking ở server.
- **Công việc:**
  1. Xây dựng các bảng `production_order`, `production_batch`, `machine_assignment`, `dispatch_order`, `machine_queue`, `send_job`, `send_job_log`.
  2. Quản lý trạng thái lô nhuộm: `draft` -> `released` -> `queued` -> `assigned` -> `dispatched` -> `in_progress` -> `completed/cancelled`.
  3. Cơ chế claim/lock lệnh điều phối máy nhuộm có thời hạn (owner, version) để tránh hai người xử lý cùng lúc.
  4. Đảm bảo idempotency khi gửi lệnh sang hệ nhuộm tự động qua bảng nạp `tbl_status`.
- **Exit criteria:** Tự động giải phóng khóa (unlock) khi hết hạn và lưu vết audit log khi force unlock thủ công.
- **Test bắt buộc:** Concurrency Claim Lock test, Lock expiration test, Dispatch idempotency check.

### PHASE 6 – Module Cân sản xuất & Local Scale Agent
- **Mục tiêu:** Quản lý nhiệm vụ cân thực tế và agent đọc cân an toàn, có offline buffer.
- **Công việc:**
  1. Xây dựng bảng nhiệm vụ cân (`weighing_task`, `weighing_measurement`, `scale_device`).
  2. Hoàn thiện Local Scale Agent (C#) đọc số cân thực tế từ cổng COM (SerialPort) hoặc putty log giả lập.
  3. Thuật toán lọc số ổn định (Stable Filter), chống gửi số cân trùng lặp.
  4. API nhận cân có xác thực thiết bị, khóa idempotency, và tự động kiểm tra dung sai (Tolerance).
  5. Quy trình xác nhận cân ngoài dung sai (bắt buộc nhập lý do override và có tài khoản giám sát ký duyệt).
  6. Cơ chế Offline Queue SQLite cục bộ của Agent tự đồng bộ khi có mạng trở lại.
- **Exit criteria:** Khóa mẻ cân khi vượt dung sai, chặn gửi trùng và đồng bộ offline thành công.
- **Test bắt buộc:** Scale stability threshold test, tolerance violation flow test, offline queue synchronization test.

### PHASE 7 – Tem & QR Code (Printing Integration)
- **Mục tiêu:** Tạo và in nhãn dán chứa QR Code nội bộ từ máy in TSC TE200.
- **Công việc:**
  1. Sinh QR Code hoàn toàn nội bộ trên Web Server (không gọi API bên thứ ba).
  2. Tạo template nhãn TSPL (nhãn dyes, nhãn hóa chất, nhãn phụ gia) cho phép điều chỉnh kích thước trên web.
  3. Hoàn thiện Print Agent (C#) nhận lệnh, chọn máy in TSC (USB/LAN) và trả trạng thái.
  4. Kiểm soát việc in lại (Reprint): bắt buộc phân quyền, yêu cầu nhập lý do và ghi audit log.
- **Exit criteria:** Tem in ra chứa QR quét giải mã đúng ID lô của hệ thống, chặn in trùng bằng idempotency key.
- **Test bắt buộc:** TSPL command validation, QR scanner readability test, reprint authorization check.

### PHASE 8 – Vận chuyển và Xác nhận tới thùng (Material Transfer)
- **Mục tiêu:** Bổ sung phần lưu trình theo dõi nguyên liệu từ cân tới thùng phụ trợ 1A/2B của máy nhuộm.
- **Công việc:**
  1. Xây dựng bảng theo dõi vận chuyển (`material_transfer`, `material_transfer_event`).
  2. Trạng thái vận chuyển: `ready_for_transfer` -> `in_transit` -> `arrived_at_tank` -> `accepted/rejected`.
  3. Cấu hình thời gian vận chuyển định mức (SLA) theo loại nguyên liệu, line và nhóm máy nhuộm.
  4. Phát cảnh báo và ghi nhận lý do trễ hạn (`delay_reason`) nếu vượt quá SLA.
  5. Xác nhận tới thùng bằng cách quét mã QR trên nhãn tem dán thùng hoặc thao tác phân quyền thủ công.
- **Exit criteria:** Cảnh báo trễ hạn hoạt động chính xác theo SLA cấu hình, xác nhận arrived đổi trạng thái mẻ cân.
- **Test bắt buộc:** SLA timer trigger test, QR container destination match test.

### PHASE 9 – Sẵn sàng cấp và Giám sát cấp vào máy (Feeding Integration)
- **Mục tiêu:** Kiểm soát điều kiện đủ nước, đủ nguyên liệu và ghi nhận cấp vào máy nhuộm.
- **Công việc:**
  1. Xây dựng logic kiểm tra mức sẵn sàng cấp (`feed_readiness_check`, `feed_operation`).
  2. Chỉ cho phép cấp nước và đổ thuốc nhuộm (`ready_to_feed`) khi: Đã quét đủ nguyên liệu bắt buộc, đúng máy/thùng, và cấu hình nước (nóng/lạnh/phụ trợ/tổng) hợp lệ.
  3. Ghi nhận thời gian cấp, người thực hiện, máy, thùng, lô nhuộm.
  4. Hỗ trợ ghi nhận override cấp máy nếu có phê duyệt từ giám sát (ghi rõ lý do và lưu audit log).
- **Exit criteria:** Chặn cấp máy khi thiếu nguyên liệu cân hoặc cấu hình nước sai lệch mà không có override.
- **Test bắt buộc:** Feed readiness rule evaluation test, override verification check.

### PHASE 10 – Troubleshooting (Hỗ trợ Sự cố)
- **Mục tiêu:** Chuyển đổi bộ tri thức sự cố Excel VBA sang công cụ suy luận nguyên nhân lỗi trên ứng dụng web.
- **Công việc:**
  1. Thiết lập các bảng tri thức sự cố (`problem`, `cause`, `problem_cause_rule`, `troubleshooting_case`, `case_evidence`, `recommendation`).
  2. Lập trình bộ suy luận điểm số nguyên nhân (`InferenceService`) dựa trên các hiện tượng lỗi kỹ thuật viên nhập vào.
  3. Giao diện chẩn đoán, xếp hạng nguyên nhân và truy vết giải thích thuật toán suy luận.
  4. Cho phép kỹ thuật viên cập nhật nguyên nhân thực tế, biện pháp xử lý và đánh giá hiệu lực để tối ưu điểm số rules.
- **Exit criteria:** Điểm số suy luận nguyên nhân lỗi khớp 100% với logic bảng ENGINE của Excel VBA cũ.
- **Test bắt buộc:** Troubleshooting rules regression test.

### PHASE 11 – Báo cáo & Dashboard (Reports)
- **Mục tiêu:** Xây dựng hệ thống báo cáo sản lượng, tiêu hao bột màu/hóa chất và kiểm soát chất lượng cân.
- **Công việc:**
  1. Viết các query thống kê PostgreSQL, thiết kế các bảng báo cáo tiêu hao, sai lệch dung sai và năng suất máy.
  2. Màn hình Dashboard biểu đồ trực quan, hỗ trợ xuất báo cáo Excel/PDF.
  3. Thiết lập giao diện tra cứu nhật ký Audit Log Explorer theo người dùng/thời gian.
- **Exit criteria:** Báo cáo xuất ra khớp số liệu giao dịch thực tế, query phản hồi < 2 giây dưới tải lớn.
- **Test bắt buộc:** Query explain plan performance check, Excel export layout check.
- **Trạng thái:** **Đã hoàn thành.**
  - Vá lỗ hổng phát hiện trong lúc rà soát: `WeighingJobController::weighItem` trước đây không lưu vết việc ghi đè (override) dung sai cân — đã bổ sung cột `override_approved/override_reason/override_by` trên `app.weighing_job_items`, bắt buộc vai trò SUPERVISOR/ADMIN và ghi Audit Log `WEIGH_TOLERANCE_OVERRIDE`, đồng nhất với luồng override của `FeedOperationController`.
  - Lập trình `ReportController` với 4 báo cáo: tiêu hao thực tế vs định mức (theo vật tư), sai số dung sai & tỉ lệ override, sản lượng máy nhuộm theo ngày/tháng/**ca kíp** (giả định 3 ca 8h do dữ liệu nguồn không có cột ca — xem `open-questions.md`), và Pareto nguyên nhân sự cố hàng đầu.
  - Xuất Excel (`maatwebsite/excel`) và PDF (`barryvdh/laravel-dompdf`) cho cả 4 báo cáo qua tham số `format=xlsx|pdf`.
  - Xây dựng `Audit Log Explorer` (`GET /api/audit-logs`, phân trang + lọc theo user/action/entity/thời gian).
  - Giao diện Vue.js `Reports.vue` (4 tab, biểu đồ SVG tự viết theo chuẩn thiết kế dataviz nội bộ, tuân thủ nguyên tắc "one axis" cho biểu đồ Pareto) và `AuditLogExplorer.vue`.
  - Vượt qua kiểm thử **Integration Test (`ReportsTest.php`, 45 assertions)**: tổng hợp tiêu hao, tỉ lệ override, override bị chặn khi không phải Supervisor, sản lượng theo ngày, Pareto tích lũy, lọc Audit Log, chặn truy cập chưa đăng nhập, và xuất Excel thực tế. Toàn bộ 28 test backend (216 assertions) pass sau thay đổi.
  - **Phát hiện môi trường và đã khắc phục dứt điểm:** database dev cục bộ (`df-postgres`, DB `production_web`) từng thiếu bảng `public.personal_access_tokens` do migration Sanctum gốc dùng `tokenable_id` kiểu `bigint` không tương thích User UUID; bảng từng bị xóa thủ công ngoài migration để sửa lỗi này nhưng chưa từng được ghi lại, gây trôi dạt migration tracking. Đã thêm migration phục hồi `2026_07_16_000007_restore_missing_personal_access_tokens_table` (idempotent, dùng `uuidMorphs`, `down()` no-op để bảo vệ dữ liệu), verify đăng nhập thật qua HTTP (không dùng `Sanctum::actingAs()`) thành công 100%, không mất dữ liệu nghiệp vụ. Thêm `AuthenticationFlowTest.php` (6 test) chống tái diễn. Chi tiết đầy đủ tại `session-log.md` mục 11.

### PHASE 12 – UAT & Chạy song song (Parallel Run Pilot)
- **Mục tiêu:** Chạy thử nghiệm thực tế tại một phân xưởng pilot song song với Excel VBA cũ để nghiệm thu toàn bộ tính năng.
- **Công việc:** Đào tạo nhân viên, triển khai Agent tại 2 trạm làm việc mẫu, vận hành song song mẻ cân và in tem thực tế, ghi lỗi phát sinh và hiệu chỉnh hệ thống.
- **Exit criteria:** Chạy song song liên tục 7 ngày không gặp lỗi nghiêm trọng (mất dữ liệu, đình trệ thiết bị) và được ký biên bản nghiệm thu UAT.
- **Test bắt buộc:** Network disconnection injection test, concurrent stress test.

### PHASE 13 – Cutover & Go-Live (Bàn giao hệ thống)
- **Mục tiêu:** Ngừng sử dụng Excel VBA và chuyển đổi hoàn toàn sang vận hành trên Web PostgreSQL.
- **Công việc:** Backup dữ liệu Access lần cuối, đổi quyền file Access sang Read-only, thực thi đồng bộ delta dữ liệu cuối cùng, chuyển cấu hình Agent sang API production, bàn giao tài liệu vận hành.
- **Exit criteria:** Vận hành chính thức hệ thống Web 100% trong 3 ngày liên tục, kích hoạt cơ chế backup tự động.
- **Test bắt buộc:** Production backup verification test, rollback trigger execution test.
