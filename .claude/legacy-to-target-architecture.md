# Kiến trúc Chuyển đổi từ Hệ thống cũ sang Hệ thống Đích (legacy-to-target-architecture.md)

Tài liệu này chi tiết hóa ma trận chuyển đổi từ hệ thống Excel VBA / Access cũ sang Mô hình Hệ thống đích Web PostgreSQL theo luồng nghiệp vụ 9 bước thống nhất.

> [!IMPORTANT]
> **Cập nhật 2026-07-17 (đợt duyệt lần 4):** Đã hoàn tất Phase A (database discovery) và Phase B (domain gap analysis) — xem `database-inventory.md`, `legacy-database-mapping.md`, `chemical-call-domain.md`, `qr-label-printing-domain.md`, `b24-warehouse-routing.md`. Bước 4 (cân hóa chất) bên dưới cần đọc cùng `chemical-call-domain.md` vì đã xác nhận CHEMICAL_CALL là nghiệp vụ "gọi/thông báo", không phải "cân tay hóa chất" như giả định gốc của Bước 4.
>
> **Cập nhật 2026-07-17:** Cơ cấu vận hành thật đã được xác nhận gồm **6 máy nghiệp vụ / 5 workstation type** (CHEMICAL_CALL×1, PRODUCTION_ORDER×1, QR_LABEL_PRINTING×1, SMALL_SCALE×2, LARGE_SCALE×1) — xem chi tiết `workstation-matrix.md`. Các trường "Workstation" bên dưới đã được sửa theo cơ cấu này; những Bước không có bằng chứng gắn máy vật lý xác nhận (Bước 1 Recipe, Bước 6/7/8 Vận chuyển/Tới thùng/Cấp máy, Bước 9 Troubleshooting) được ghi chú rõ là **KHÔNG xác nhận là workstation shop-floor riêng** — không tự gán.
>
> **Bổ sung phân loại A/B/C bắt buộc từ đợt duyệt này:** mỗi mục "Trạng thái hoàn thiện" bên dưới nay được gắn thêm nhãn **A. MIGRATION PARITY** (bắt buộc vì VBA có), **B. UX IMPROVEMENT** (cải tiến giao diện, không đổi hành vi), hoặc **C. OPTIONAL EXTENSION** (tính năng mới, không có trong VBA). Nhãn C không được dùng để che giấu việc thiếu A.

## Nguyên tắc Phân biệt Trạng thái Chức năng

1. **[MIGRATED]**: Chức năng có trong VBA và đã được di trú thành công sang hệ thống mới.
2. **[MISSING]**: Chức năng có trong VBA nhưng hiện tại còn thiếu ở hệ thống mới.
3. **[REPLACED]**: Chức năng được thay thế bằng cơ chế mới tương đương của Web/API.
4. **[NEW]**: Chức năng mới bổ sung từ lưu trình thực tế, không có trong VBA gốc.
5. **[DEPRECATED]**: Chức năng legacy không còn cần thiết hoặc bị bãi bỏ.

---

## Ma trận 9 Bước Nghiệp vụ Chi tiết

### Bước 1: Khai đơn công thức
- **VBA nguồn**: 
  - `Workbook_BeforePrint`, `TaoQR_chemical`, `TaoQR_Y20` trong `CÔNG THỨC SẢN XUẤT CHUNG - new.xlsm` (VBA-RECIPE-001, 003, 010, 011).
  - Hàm `TraHeSo` tra cứu hệ số 3 chiều (mã × khổ vải × tiêu) (VBA-RECIPE-012, 013).
- **Access tables**: Không trực tiếp, đọc dữ liệu ô Excel.
- **Chức năng web hiện có**: 
  - Màn hình Biên tập Công thức (`Recipes.vue`), API tính nước và làm tròn (`FormulaCalculationService`).
- **Workstation**:
  - **KHÔNG xác nhận là workstation shop-floor riêng** (cập nhật 2026-07-17). Danh sách cũ (`WS-ORDER-01/02/03` = 3 IP) là suy diễn từ lịch sử mạng, không có bằng chứng nghiệp vụ. Cơ cấu 6 máy đã xác nhận với người dùng KHÔNG có máy "RECIPE" riêng — công thức nhiều khả năng là công cụ văn phòng/kỹ thuật dùng trên máy tính bất kỳ, không phải kiosk khóa công đoạn. Cần Admin xác nhận (xem `open-questions.md` CH-BUS-010).
- **Database mới**: `app.recipes`, `app.recipe_versions`, `app.recipe_materials`, `app.water_configs`.
- **Agent**: Không yêu cầu Agent phần cứng cho khâu này.
- **Event**: `recipe.created`, `recipe.updated`.
- **Test**: `FormulaCalculationServiceTest.php` (tính nước), `RecipesTest` (CRUD).
- **Trạng thái hoàn thiện**:
  - **[MISSING] (A. MIGRATION PARITY)**: Hàm tra hệ số `TraHeSo` 3 chiều (mã × khổ × tiêu) chưa được migrate (Service mới dùng 2 khóa `machine_line` + `process_code`) — xem `p0-analysis/p0-a-traheso.md`, pilot blocker PB liên quan Recipe.
  - **[MISSING] (A. MIGRATION PARITY)**: Sinh QR phiếu công thức nội bộ (code cũ gọi API qrserver.com bên thứ 3 đã bị cấm theo CLAUDE.md mục 5).
  - **[MIGRATED] (A. MIGRATION PARITY — đã đạt)**: Định lượng công thức cơ bản và lưu trữ phiên bản.

---

### Bước 2: Điều phối / sản xuất
- **VBA nguồn**: 
  - `mainform.UserForm_Initialize`, `btnSAVE_Click`, `MoveToSend` trong `2.C3 grid load row lock id FB -192(QR).xlsm` & `MACHINE_ID_LOCKED.xlsm` (VBA-DISPATCH-011, 019, 043, 070).
- **Access tables**: `tbl_Waiting` (Wait queue), `tbl_ToSend2` (Send queue), `WAITING`.
- **Chức năng web hiện có**: 
  - Màn hình Hàng chờ Điều phối (`MachineQueue.vue`), CRUD Lô sản xuất (`ProductionBatches.vue`).
- **Workstation**: **PRODUCTION_ORDER (1 máy đã xác nhận, cập nhật 2026-07-17)** — xem `workstation-matrix.md` Mục 1-3. Binding IP thật vẫn `TO_CONFIRM`.
- **Database mới**: `app.production_batches`, `app.machine_dispatches`.
- **Agent**: Không yêu cầu.
- **Event**: `batch.created`, `dispatch.claimed`, `dispatch.sent`.
- **Test**: `MachineDispatchConcurrencyTest.php` (pessimistic lock, claim/release/send).
- **Trạng thái hoàn thiện**:
  - **[MISSING] (A. MIGRATION PARITY)**: Chưa có endpoint `POST /api/machine-dispatches` (chức năng tạo mới lệnh điều phối từ web, hiện dữ liệu chỉ có từ SQL di trú thô).
  - **[MISSING] (A. MIGRATION PARITY — cần xác nhận trước, có thể là NEEDS_BUSINESS_CONFIRMATION)**: Cơ chế đồng bộ `tblSync` (heartbeat đa máy trạm) — lưu ý `tblSync` thực tế **rỗng hoàn toàn (0 dòng)**, chưa rõ tính năng có từng chạy thật không (xem R-11 trong `risks-and-assumptions.md`).
  - **[MIGRATED] (A. MIGRATION PARITY — đã đạt)**: Cơ chế khóa dòng (claim/release) và thay đổi trạng thái gửi lệnh nhuộm. Lưu ý: đây là **thiết kế viết mới** (B. UX IMPROVEMENT / kiến trúc mới), không kế thừa VBA — VBA gốc chạy đơn máy, không có khóa tranh chấp đa người dùng thật (`LockAllTextboxes` bị comment trong workbook C3 — xem VBA-DISPATCH-011).

---

### Bước 3: Cân thuốc nhuộm
- **VBA nguồn**: 
  - `scaleform.frm` (6 dòng ở bản A, 9 dòng ở bản B/C), `StableFilter`, `ExtractLastNumber` trong `SEMI CHECKER.xlsm` & các file `semiauto-*` (VBA-SCALE-022, 048, 055, 120, 121).
- **Access tables**: `tblRECORD` (Access `RECORD.accdb`).
- **Chức năng web hiện có**: 
  - Màn hình Trạm cân (`WeighingStation.vue`), API ghi nhận kết quả cân (`WeighingJobController::weighItem`).
- **Workstation (cập nhật 2026-07-17)**: 
  - **SMALL_SCALE** — 2 máy client, nguồn `4.semiauto-small scale - delta-stable-final_DF026-027.xlsm`.
  - **LARGE_SCALE** — 1 máy client, nguồn `5.Semiauto- lockmove SEND OVER6 - delta-stable-final-221.xlsm`.
  - Binding IP thật của từng máy vẫn `TO_CONFIRM` — xem `workstation-matrix.md` Mục 4. Nhóm SCALE trước đây còn audit thêm `SEMI CHECKER.xlsm` (không nằm trong 6 máy đã xác nhận — chưa rõ có còn vận hành hay không).
- **Database mới**: `app.weighing_jobs`, `app.weighing_job_items`, `app.scale_measurements`.
- **Agent**: Local Device Agent (`ScaleReader.cs`).
- **Event**: `weigh.completed`, `weigh.override_approved`.
- **Test**: `ScaleLiveWeightTest.php`, `ReportsTest::test_weigh_item_persists_override_and_writes_audit_log`.
- **Trạng thái hoàn thiện**:
  - **[MISSING] (A. MIGRATION PARITY — Pilot Blocker PB-1)**: Thuật toán `StableFilter` (ổn định số 2 lần đọc giống hệt) chưa được code ở Agent (hiện frontend đang hard-code `stable: true`).
  - **[MISSING] (A. MIGRATION PARITY — Pilot Blocker PB-2)**: Thuật toán tự trừ bì (tare) theo slot cân (`AutoFlow_OnWeight` lấy số đọc đầu tiên làm base) chưa có; `ScaleReader.CleanWeight` hiện lấy số **đầu tiên** thay vì **số cuối cùng** như VBA (`ExtractLastNumber`).
  - **[MIGRATED] (A. MIGRATION PARITY — đã đạt)**: Phân quyền Trưởng ca duyệt override dung sai lệch và lưu log audit bất biến.
  - **[NEEDS_BUSINESS_CONFIRMATION] (chỉ riêng LARGE_SCALE)**: Dữ liệu lịch sử cân từ workbook 5 (SEND OVER6) có bug khiến `GetProcessStatus` luôn trả "REJECTED" — xem R-10. Không được copy bug này sang hệ thống mới; cần xác nhận trạm nào từng dùng bản lỗi trước khi dùng dữ liệu lịch sử.

---

### Bước 4: Cân hóa chất / A11 / DLG
- **VBA nguồn**: Tương tự Bước 3 (cơ chế cân bán tự động dùng chung code Excel).
- **Access tables**: `tblRECORD_chem` (`RECORD.accdb`).
- **Chức năng web hiện có**: Màn hình Trạm cân (`WeighingStation.vue`), API ghi nhận cân hóa chất.
- **Workstation (cập nhật 2026-07-17)**: **KHÔNG phải là Bước cân hóa chất trên máy SMALL_SCALE/LARGE_SCALE** — người dùng xác nhận có máy **CHEMICAL_CALL** riêng biệt (`1.báo phát AC XƯỞNG -193.xlsm`, `chem_order.frm`) mà chức năng thật là **gọi/thông báo phát hóa chất tới xưởng**, không phải cân tay hóa chất. Bước 4 trong tài liệu này (dựa trên giả định "cân hóa chất dùng chung code cân thuốc nhuộm") cần audit lại — xem `vba-migration-matrix.md` NHÓM 0 (đang bổ sung). `tblRECORD_chem` (0 dữ liệu, do A-02 trong `risks-and-assumptions.md`) và bảng `tbl_status` (cấu hình kênh van, trong `chem_order.accdb`) là 2 nguồn dữ liệu khác nhau — chưa xác nhận rõ mối quan hệ.
- **Database mới**: `app.weighing_jobs`, `app.weighing_job_items`, `app.scale_measurements` (cho phần cân, nếu còn áp dụng) — **CHƯA CÓ** bảng nào cho luồng "gọi hóa chất" (`chem_order`).
- **Agent**: Local Device Agent (`ScaleReader.cs`) — chỉ áp dụng nếu Bước 4 thật sự có cân; cần xác nhận lại.
- **Event**: `weigh.completed` (nếu còn áp dụng).
- **Test**: `ScaleLiveWeightTest.php` (nếu còn áp dụng).
- **Trạng thái hoàn thiện**:
  - **[MISSING] (chưa phân loại A/B/C — chờ audit NHÓM 0 hoàn tất)**: Toàn bộ luồng CHEMICAL_CALL — sơ bộ xác nhận **không có Controller nào** liên quan "Chemical"/"chem_order" trong `backend/app/Http/Controllers` hiện tại.

---

### Bước 5: In tem
- **VBA nguồn**: 
  - `scaleform.btnPrint_Click` in sheet tạm qua driver Windows TSC (VBA-SCALE-054) — đây là in tem TẠI TRẠM CÂN (SMALL_SCALE/LARGE_SCALE), khác với máy in tem QR trung tâm.
  - **Máy in tem QR trung tâm đã xác nhận (workstation QR_LABEL_PRINTING)**: `3.DF028  formulas - PRINTER LANDSCAPE - jit qr sending - 15l special.xlsm` (`TO_SEND.frm`, `printform.frm`, `wait_printform.frm`).
  - **CẢNH BÁO (2026-07-17)**: `in tem Copower.xlsm` và `QR PRINTER-send to access- NEW 9ROWS BIG QR.xlsm` (2 file dùng làm nguồn cho NHÓM 4 trước đây) **không nằm trong 5 workbook đã xác nhận** — module hoàn toàn khác DF028 (không có `TO_SEND.frm`/`ModBackEndDB.bas`). 83 dòng `VBA-PRINT-*` hiện có trong `vba-migration-matrix.md` audit đúng 2 file này, KHÔNG đại diện máy in tem QR sản xuất thật. Đang audit bổ sung DF028 — xem NHÓM 4-DF028 (mới).
- **Access tables**: `tblRECORD` (lấy dữ liệu in tại trạm cân); DF028 dùng bảng khác — đang xác định (khả năng liên quan `tbl_SentLog`/`tbl_ToSend2` — xem `p0-analysis/p0-d-legacy-tables-inventory.md`).
- **Chức năng web hiện có**: 
  - API sinh lệnh in TSPL động (`PrintJobController`), Màn hình In tem (`PrintStation.vue`) — **cần đối chiếu lại có khớp đúng luồng `TO_SEND`/`wait_printform` của DF028 hay không**, chưa xác nhận.
- **Workstation (cập nhật 2026-07-17)**: **QR_LABEL_PRINTING** — 1 máy đã xác nhận, nguồn DF028. Binding IP thật vẫn `TO_CONFIRM` (xem `workstation-matrix.md` Mục 4 — IP `10.0.19.79` khớp số lượng nhưng chưa xác nhận gắn đúng workbook).
- **Database mới**: `app.material_labels`, `app.print_jobs` — cần đối chiếu lại theo kết quả audit DF028.
- **Agent**: Local Device Agent (`LabelPrinter.cs` tích hợp raw print qua USB/LAN port 9100).
- **Event**: `label.printed`, `label.reprinted`.
- **Test**: `PrintJobPipelineTest.php`.
- **Trạng thái hoàn thiện**:
  - **[MIGRATED] (A. MIGRATION PARITY — đã đạt, nhưng dựa trên audit workbook CHƯA xác nhận đúng — cần audit lại DF028)**: Chuyển đổi thành công từ in driver Excel chậm chạp sang in thô TSPL qua Agent.
  - **[NEW] (C. OPTIONAL EXTENSION)**: Chức năng khai báo kích thước nhãn in động trên Web UI.
  - **[NEW] (C. OPTIONAL EXTENSION)**: Theo dõi hàng chờ in tập trung, tự động retry và ghi nhận số lần in lại (reprint audit log) — **lưu ý**: nếu DF028 (`wait_printform`, `Mod_time3min`) có cơ chế chờ/retry riêng thì phần này có thể thực ra là A. MIGRATION PARITY chứ không phải hoàn toàn mới — chờ kết quả audit để phân loại lại chính xác.

---

### Bước 6: Vận chuyển
- **VBA nguồn**: Không có trong VBA cũ (trước đây giao nhận thủ công, không ghi nhận hệ thống).
- **Access tables**: Không có.
- **Chức năng web hiện có**: 
  - Màn hình Vận chuyển (`MaterialTransfer.vue`), API cập nhật vận chuyển (`MaterialTransportController`).
- **Workstation**: `WS-TRANS-01` (Trạm di động / Tablet) — **KHÔNG nằm trong 6 máy nghiệp vụ đã xác nhận với người dùng (2026-07-17)** — không có bằng chứng vận hành thật, không tự gán là workstation vật lý cố định. Xem `workstation-matrix.md` Mục 5.
- **Database mới**: `app.material_transports`.
- **Agent**: Máy quét QR gắn trạm di động.
- **Event**: `transport.started` (`IN_TRANSIT`), `transport.completed` (`ARRIVED`).
- **Test**: `ScannerWorkflowTest.php::test_full_scan_driven_weighing_and_transport_flow`.
- **Trạng thái hoàn thiện**:
  - **[NEW] (C. OPTIONAL EXTENSION — không phải Migration Parity vì không có VBA nguồn)**: Luồng quét mã tem để chuyển trạng thái vận chuyển từ trạm cân tới khu vực máy nhuộm. "Hoàn thành 100%" chỉ đúng với nghĩa code đã viết xong cho tính năng MỚI này — không được dùng để suy luận rằng Bước 6 là một workstation vật lý đã xác nhận vận hành.

---

### Bước 7: Xác nhận tới thùng
- **VBA nguồn**: Không có trong VBA cũ (thao tác viên tự đổ, dễ nhầm thùng/máy).
- **Access tables**: Không có.
- **Chức năng web hiện có**: 
  - Màn hình Cấp máy (`FeedingMonitor.vue`), API verify đối chiếu kép (`ScannerController::verifyTank`).
- **Workstation**: `WS-TANK-01` (Khu vực bồn máy nhuộm) — **KHÔNG nằm trong 6 máy nghiệp vụ đã xác nhận** — không có bằng chứng vận hành thật. Xem `workstation-matrix.md` Mục 5.
- **Database mới**: `app.production_batches` (cập nhật trạng thái), `app.audit_logs`.
- **Agent**: Scanner của máy trạm tại cụm bồn.
- **Event**: `tank.verified`.
- **Test**: `ScannerWorkflowTest.php::test_verify_tank_matching`.
- **Trạng thái hoàn thiện**:
  - **[NEW] (C. OPTIONAL EXTENSION — không phải Migration Parity)**: Quét đối chiếu kép mã thùng (Tank QR) và mã nhãn tem vật tư (Label QR) để chống cấp sai bể. Tính năng mới hợp lệ nhưng cần Admin xác nhận có máy vật lý cố định tương ứng đang/sẽ vận hành trước khi coi Bước 7 là một workstation trong pilot.

---

### Bước 8: Cấp vào máy
- **VBA nguồn**: Không có trong VBA cũ.
- **Access tables**: Không có.
- **Chức năng web hiện có**: 
  - Màn hình Cấp máy (`FeedingMonitor.vue`), API cấp máy (`FeedOperationController`).
- **Workstation**: `WS-FEED-01` (Bảng điều khiển máy nhuộm) — **KHÔNG nằm trong 6 máy nghiệp vụ đã xác nhận** — không có bằng chứng vận hành thật. Lưu ý: máy CHEMICAL_CALL (`chem_order.frm`) có thể liên quan gián tiếp tới việc "cấp hóa chất" nhưng đây là chức năng GỌI/THÔNG BÁO, khác với "cấp vào máy" qua PLC mô tả ở đây — không gộp 2 khái niệm này khi chưa có bằng chứng. Xem `workstation-matrix.md` Mục 5.
- **Database mới**: `app.feed_operations`, `app.machine_chemical_channels`.
- **Agent**: Kết nối PLC máy nhuộm (nếu có, hiện tại đang quét nhận diện).
- **Event**: `feed.started`, `feed.completed`.
- **Test**: `FeedOperationControllerTest.php`.
- **Trạng thái hoàn thiện**:
  - **[NEW] (C. OPTIONAL EXTENSION — không phải Migration Parity)**: Cho phép ghi nhận thời điểm mở van cấp liệu vào máy nhuộm, hỗ trợ kiểm soát kênh dẫn hóa chất (`machine_chemical_channels`). Cần Admin xác nhận có PLC/máy vật lý tương ứng đang vận hành trước khi coi Bước 8 là workstation trong pilot.

---

### Bước 9: Giám sát và báo cáo
- **VBA nguồn**: 
  - `frmCheck.btnCHECK/SUM/SUM2_Click` trong `df lượng dùng thuốc nhuộm.xlsm` (VBA-RECIPE-015, 016, 017).
  - Kho tri thức sự cố `troubleshooting_support engine_DF.xlsm` (VBA-TROUBLE-001 đến 053).
- **Access tables**: `tblRECORD` (đọc dữ liệu cân lịch sử).
- **Chức năng web hiện có**: 
  - Màn hình Giám sát tổng thể (`Dashboard.vue`), Phân hệ Chẩn đoán sự cố (`Troubleshooting.vue`), Báo cáo (`Reports.vue`), Nhật ký kiểm toán (`AuditLogExplorer.vue`).
- **Workstation**: 
  - `WS-MONITOR-01` - MONITORING (Màn hình lớn phòng điều hành) — **KHÔNG nằm trong 6 máy nghiệp vụ đã xác nhận**, tương tự Troubleshooting (`troubleshooting_support engine_DF.xlsm`) không có bằng chứng gắn máy nhà xưởng cố định — nhiều khả năng là công cụ văn phòng/kỹ thuật. Cần Admin xác nhận (xem `open-questions.md` CH-BUS-010). Xem `workstation-matrix.md` Mục 5.
- **Database mới**: `app.problem_cause_rules`, `app.audit_logs`, `app.alerts`.
- **Agent**: Không có.
- **Event**: `alert.created`, `alert.resolved`.
- **Test**: `TroubleshootingTest.php`, `ReportsTest.php`.
- **Trạng thái hoàn thiện**:
  - **[MIGRATED] (A. MIGRATION PARITY — đã đạt)**: Lõi chẩn đoán sự cố (Inference Service) và công thức tính score.
  - **[MISSING] (A. MIGRATION PARITY)**: UI biên tập dữ liệu KB sự cố trực tiếp (kỹ sư tự thêm/sửa problem-cause, hiện đang sửa qua seeder tĩnh).
  - **[NEW] (C. OPTIONAL EXTENSION)**: 4 báo cáo trực quan hóa (Pareto sự cố, tiêu hao bột màu, dung sai override, sản lượng ca kíp) và Audit log explorer.
