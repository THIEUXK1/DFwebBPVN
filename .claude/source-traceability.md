# Source Traceability - Ma Trận Truy Vết Mã Nguồn

Tài liệu này thiết lập ma trận truy vết (Traceability Matrix) liên kết giữa các thành phần mã nguồn Excel VBA + Access cũ với các thành phần dữ liệu, API, giao diện Web và các ca kiểm thử mới.

> [!IMPORTANT]
> **Cập nhật 2026-07-17:** Đã bổ sung chi tiết truy vết cho cấu hình Workstation, nhận diện an toàn qua Token, và lịch sử kết nối mạng của 7 client thực tế.
> 
> **Cô lập CHEMICAL_CALL:** Phân hệ `CHEMICAL_CALL` tạm thời được cô lập và đánh dấu **`BLOCKED_BY_BUSINESS_CONFIRMATION`** (Blocker **`CH-BUS-015`**). Luồng nghiệp vụ di trú trong đợt này tập trung 100% vào chuỗi vận hành Non-Chemical (`PRODUCTION_ORDER` → `QR_LABEL_PRINTING` → `SMALL_SCALE` / `LARGE_SCALE`).

---

## Ma Trận Truy Vết Tổng hợp

| Thành phần Legacy (Excel/VBA/Access) | Phân hệ Nghiệp vụ | Bảng Staging (Postgres Staging) | Bảng Đích (Postgres App) | API Endpoint Đề xuất / Hiện có | Giao diện Web (Frontend) | Ca Kiểm thử tương ứng (Test Cases) |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| Thủ tục `TraHeSo` / `CÔNG THỨC SẢN XUẤT CHUNG.xlsm` | Quản lý Công thức & Quy trình | Không có | **CHƯA có** — `app.water_configs` hiện dùng mô hình tra cứu khác hẳn (2 khóa machine_line+process_code) so với `TraHeSo` gốc (3 khóa: mã×khổ vải×tiêu) | `POST /api/calculations/preview` (endpoint tồn tại nhưng KHÔNG chạy logic `TraHeSo`) | Màn hình Biên tập Công thức nhuộm | **KHÔNG có** — đã grep toàn bộ backend, 0 kết quả cho "TraHeSo"/"Golden Master". Xem VBA-RECIPE-012/013 trong `vba-migration-matrix.md` |
| Module `ModRead_putty_log` & thủ tục `CleanWeight` | Ghi nhận số cân | `legacy_df_scale.tblRECORD` | `app.scale_measurements` (loại DYE) | `POST /api/devices/{id}/readings` | Màn hình Trạm Cân Bột màu | Scale Simulator Test, Stable Filter Test |
| Module `mdQRCodegen` & API `api.qrserver.com` | Sinh mã QR Code | `legacy_df_scale.tblRECORD` | `app.scale_measurements` | Tự động sinh nội bộ trên Backend | Nhãn tem in vật lý | QR Reader verification test |
| Sự kiện nút `btnPrint` & thủ tục `TaoQR_*` | In tem nhãn TSC | `legacy_df_scale.tblRECORD` | `app.scale_measurements` & print job | `POST /api/labels/{id}/print`, `GET /api/agents/{id}/jobs` | Giao diện Trạm Cân | Print Agent Spooler Test, Reprint Audit test |
| Workbook `SEMI CHECKER.xlsm` | Kiểm tra Bán thành phẩm | `legacy_df_scale.tblRECORD` (trạng thái) | `app.production_batches.status` | `POST /api/weighing-items/{id}/confirm` | Màn hình Kiểm tra Bán thành phẩm | QA/QC Approval flow test |
| Form lưới `mainform` & các nút `sub1...subN` | Lưới hàng chờ điều phối | `legacy_df_data.tbl_Waiting`, `tbl_ToSend2`, `WAITING` | `app.machine_dispatches` | `GET /api/dispatch-queue` | Màn hình Hàng chờ Điều phối | Dispatch Queue Load performance test |
| Không có (Viết mới, thay cho setup thủ công bằng localStorage) | Xác thực & Cấu hình Trạm làm việc an toàn | Không có | `app.workstations`, `app.workstation_allowed_actions`, `app.workstation_role_assignments` | `POST /api/workstations/register`, `POST /api/workstations/handshake`, `GET /api/workstations/{code}` | Màn hình Chọn công đoạn của Workstation, Màn hình Thiết lập Workstation | - Đăng ký workstation lần đầu.<br>- Reload trình duyệt vẫn nhận trạm.<br>- Xóa local storage không mất nhận diện.<br>- Token sai / Trạm Inactive. |
| Không có (Viết mới) | Quản lý Lịch sử mạng & Session của trạm | Không có | `app.workstation_sessions`, `app.workstation_network_history` | Tự động ghi nhận khi trạm kết nối API | Tab Mạng trong Màn hình Thiết lập Workstation | - Đổi IP nhưng giữ đúng trạm.<br>- Audit lưu IP & trạm. |
| Không có (Viết mới) | Quản lý thiết bị ngoại vi gắn trạm | Không có | `app.device_assignments` | `POST /api/workstations/{id}/devices`, `POST /api/devices/{id}/test-connection` | Danh mục thiết bị trong Màn hình Thiết lập | - Cân/In offline.<br>- Thiết bị gán sai trạm.<br>- Cân/In gán trùng. |
| Thủ tục `SendTextToApp` / Mouse click WinAPI (kỹ thuật RPA giả lập chuột — DEPRECATED, không migrate) | Gửi lệnh nạp máy nhuộm | `legacy_df_data.tbl_tosend` | `app.machine_dispatches` (sent_value/at) | `POST /api/machine-dispatches/{id}/send` | Màn hình Hàng chờ Điều phối | `MachineDispatchConcurrencyTest.php`. |
| Bảng `tbl_SentLog` Access | Nhật ký gửi máy lịch sử | `legacy_df_data.tbl_SentLog` | `app.machine_dispatches` (Sent state) | Không có (API nội bộ ghi log) | Báo cáo Lịch sử gửi lệnh | SentLog migration reconciliation |
| Module `modInferenceEngine` & các sheet `KB_*` | Hệ chuyên gia xử lý sự cố | Không có | `problems`, `causes`, `problem_cause_rules` | `POST /api/troubleshooting/cases/{id}/analyze` | Màn hình Khảo sát Sự cố & Chẩn đoán | Troubleshooting inference regression test |
| UserForm tạo case sự cố & nút `Submit` | Quản lý vụ việc lỗi nhuộm | `legacy_df_data.Submit` / `Submited` | `troubleshooting_cases`, `case_problems`, `case_actions` | `POST /api/troubleshooting/cases` | Giao diện Tạo Case Sự cố | Troubleshooting case flow test |
| Không có (Viết mới) | Realtime Control Center & Rule Engine | Không có | `app.realtime_events`, `app.alert_rules`, `app.alerts` | `GET /api/realtime/stream`, `GET /api/dashboard/*` | 5 unified sub-dashboards trong `Dashboard.vue` | `RealtimeDashboardTest.php` |
| Không có (Viết mới) | Quản trị & Nhật ký kiểm toán | Không có | `app.users`, `app.roles`, `app.audit_logs` | `POST /api/auth/login`, `GET /api/reports/audit` | Màn hình Quản trị & Nhật ký kiểm toán | RBAC API Security Test, SQL injection prevention test |

## Ghi chú truy vết bổ sung — đợt duyệt lần 4 (2026-07-17): database discovery + domain gap analysis

- **Xác nhận dứt điểm 2 database "RECORD"**: `RECORD.accdb` (RECORD_A, dispatch/sổ gửi hàng — `tbl_SentLog` 27.024 dòng, `tbl_ToSend`/`tbl_ToSend2`/`WAITING`/`tbl_Waiting`/`TBL_INPUT_ALL`/`tblSync`) và `RECORD1.accdb` (RECORD_B, dữ liệu cân — `tblRECORD` 140.655 dòng) là **2 hệ hoàn toàn độc lập**, bằng chứng schema + path VBA hard-code + dữ liệu mẫu. Chi tiết: `database-inventory.md`, `legacy-database-mapping.md`.
- **`tbl_SentLog` đã truy vết đầy đủ vòng đời** — nguồn ghi duy nhất là `DF028.TO_SEND.ConfirmRow`; bảng mapping VBA↔Access↔Web đầy đủ tại `qr-label-printing-domain.md` Mục 1.
- **Logic B24 (phân vùng kho) đã trích xuất 100% từ VBA gốc** (không suy diễn) — `b24-warehouse-routing.md`. Phát hiện 1 lỗ hổng có sẵn trong VBA (VD14-16+3C/4D không có nhãn D1) và xác nhận "15L special" không có nhánh code riêng — cả 2 điểm `BLOCKED_BY_BUSINESS_CONFIRMATION`.
- **Thiết kế đề xuất domain CHEMICAL_CALL** (tách dữ liệu cấu hình tĩnh khỏi dữ liệu vận hành ORDER/DONE) — `chemical-call-domain.md`. **Thiết kế đề xuất domain QR_LABEL_PRINTING** (service tách khỏi Controller, quản lý hàng đợi in) — `qr-label-printing-domain.md`.
- **So sánh SMALL_SCALE vs LARGE_SCALE đầy đủ** — 90% logic lõi giống hệt (dùng chung core hợp lý), 2 khác biệt thật đều là BUG của LARGE_SCALE (màu ACCEPTED/REJECTED sai, rò rỉ timer form-watch) — không copy bug khi migrate. `local-agent-architecture.md` Mục 1.
- 2 file P0 từng ghi "thiếu" (`source-files-missing.md` #4/#5) chuyển `PARTIALLY_RESOLVED` — DF028 khớp mạnh về tên/kiến trúc nhưng chưa xác minh được nhánh "15L".

## Ghi chú truy vết bổ sung từ đợt rà soát VBA (2026-07-17, đợt duyệt lần 3 — cơ cấu 6 máy nghiệp vụ)

- **Cơ cấu vận hành thật đã xác nhận với người dùng: 6 máy nghiệp vụ / 5 workstation type** (CHEMICAL_CALL×1, PRODUCTION_ORDER×1, QR_LABEL_PRINTING×1, SMALL_SCALE×2, LARGE_SCALE×1), thay cho giả định 7-workstation dựa trên lịch sử mạng trước đây. Chi tiết đầy đủ và bảng Workstation↔Workbook↔UserForm↔API/DB/Test: [workstation-matrix.md](file:///F:/DF/.claude/workstation-matrix.md).
- **2 khoảng trống audit lớn vừa được lấp:** workbook `1.báo phát AC XƯỞNG -193.xlsm` (CHEMICAL_CALL, 44 procedure, chưa từng audit trước) và workbook `3.DF028 ... jit qr sending - 15l special.xlsm` (QR_LABEL_PRINTING thật, 308 procedure) — audit PRINT trước đó (83 dòng `VBA-PRINT-*`) audit nhầm 2 workbook khác không phải máy in tem sản xuất thật. Xem NHÓM 0 và NHÓM 4-DF028 trong `vba-migration-matrix.md`.
- **Dòng "Module `mdQRCodegen` & API `api.qrserver.com`" ở bảng trên:** xác nhận thêm — vi phạm gọi API bên thứ 3 này tồn tại đồng thời ở **≥3 workbook sản xuất song song** (2 file PRINT cũ + DF028), cùng gốc code.
- **Dòng "Không có (Viết mới) — Quản lý thiết bị ngoại vi gắn trạm":** cần bổ sung loại workstation `CHEMICAL_CALL` vào enum hiện có của `app.workstations` (hiện chỉ có 10 loại, không có CHEMICAL_CALL — dễ nhầm với `CHEMICAL_WEIGHING` đã có, 2 nghiệp vụ khác nhau hoàn toàn).
- Tổng dòng traceability cấp procedure nay là **422** (từ 355), tổng procedure vật lý là **1016** (quy ước đếm lặp) hoặc **913** (quy ước dedup) — kiểm chứng tự động PASS bằng `verify-matrix-counts.sh`.

## Ghi chú truy vết bổ sung từ đợt rà soát VBA (2026-07-17)

- Bảng trên là mức TỔNG QUAN; truy vết cấp procedure (355 dòng, có kiểm chứng tự động) nằm ở [vba-migration-matrix.md](file:///F:/DF/.claude/vba-migration-matrix.md); phân tích sâu 5 phát hiện P0 nằm ở [.claude/p0-analysis/](file:///F:/DF/.claude/p0-analysis/); kế hoạch khắc phục ở [remediation-plan.md](file:///F:/DF/.claude/remediation-plan.md).
- **Cơ chế khóa tranh chấp điều phối (`locked_by`/`locked_at`/claim/release, timeout 300s):** là thiết kế viết mới hoàn toàn, KHÔNG kế thừa VBA nào — VBA gốc chạy đơn máy, module `Mod_LOCK_handFill` chỉ là khóa UI-readonly (đang bị comment/tắt); không tồn tại module tên "ModAcessDB" trong 2 workbook điều phối (tên đúng: `modACCESS_CORE`/`ModACCESS_SEND`), và không VBA nào hiện có tham chiếu `tblSync`. Route thật: `POST /api/machine-dispatches/{id}/claim`. Test: `MachineDispatchConcurrencyTest.php`.
- **Dòng "Form lưới mainform" (staging `tbl_Waiting`/`tbl_ToSend2`/`WAITING`):** mapping cột cho các bảng này trong `sql_migration/03_transform_legacy_to_target.sql` là suy luận CHƯA xác minh bằng VBA nguồn (không có workbook nào tại F:\DF ghi/đọc chúng); `tblSync` rỗng hoàn toàn (0 dòng); `tbl_Waiting` bị script coi "unshifted" nhưng dữ liệu thật cho thấy cũng lệch cột — xem [p0-d-legacy-tables-inventory.md](file:///F:/DF/.claude/p0-analysis/p0-d-legacy-tables-inventory.md) và FIX-004.
- **Dòng "ModRead_putty_log & CleanWeight":** ca kiểm thử "Stable Filter Test" ghi trong bảng hiện CHƯA tồn tại — hệ mới chưa có StableFilter (hard-code `stable:true`), và `ScaleReader.CleanWeight` lấy số đầu tiên thay vì số cuối như VBA — xem [p0-c-scale-algorithm.md](file:///F:/DF/.claude/p0-analysis/p0-c-scale-algorithm.md) và FIX-002 (pilot blocker PB-1/PB-2).
