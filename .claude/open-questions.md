# Open Questions - Các Câu hỏi nghiệp vụ và Kỹ thuật còn mở

Tài liệu này tổng hợp toàn bộ các câu hỏi nghiệp vụ, tích hợp phần cứng và thiết kế hệ thống. Dưới đây là các câu hỏi còn lại cần làm rõ và các câu hỏi đã được **Người dùng xác nhận trực tiếp**.

> [!NOTE]
> **Cập nhật Phase C/D (2026-07-17):** Các câu hỏi `CH-BUS-011` đến `CH-BUS-014` đã được phân tích sâu và đề xuất phương án giải quyết kỹ thuật kèm an toàn dự phòng dưới dạng các quyết định kiến trúc trong [decision-records.md](file:///F:/DF/.claude/decision-records.md). Mặc dù các blocker này vẫn mở (đứng chờ nghiệp vụ duyệt), hệ thống đích đã có thiết kế an toàn tương ứng để sẵn sàng triển khai.

---

## 1. Các Câu hỏi Còn mở (Remaining Open Questions)

### CH-BUS-015: Xác định máy/chương trình thực tế đọc và xử lý chem_order.accdb.tbl_status
- **Nội dung:** Bảng `tbl_status` trong database Access `chem_order.accdb` lưu trạng thái của các van hóa chất. Cần làm rõ hệ thống PLC, thiết bị xưởng hay phần mềm pha màu tự động nào ngoài 5 workbook đã audit đang trực tiếp đọc và ghi trạng thái vào bảng này để hoàn thiện thiết kế.
- **Trạng thái:** **`BLOCKED_BY_BUSINESS_CONFIRMATION`** (Cô lập hoàn toàn phân hệ `CHEMICAL_CALL`).

### CH-BUS-016: Quy tắc nghiệp vụ tự động chọn SMALL_SCALE hay LARGE_SCALE
- **Nội dung:** Xác định xem có quy tắc phân bổ tự động dựa trên ngưỡng khối lượng (ví dụ: mẻ cân > 6kg bắt buộc chuyển sang cân sàn lớn) hoặc dựa trên loại vật tư (hóa chất phụ trợ vs thuốc nhuộm dạng bột) hay không, hay hệ thống cho phép thao tác viên tự lựa chọn trạm cân trên giao diện.
- **Trạng thái:** Chờ xác nhận nghiệp vụ.

### CH-BUS-011: "15L special" trong tên workbook DF028 — nhánh nghiệp vụ nào?
- **Nội dung:** Đã đọc toàn bộ VBA (`Mod_printslip.PrintSlip_70x100`, 395 dòng) và toàn bộ 100 công thức Excel trên `Sheet1` của DF028 — **không tìm thấy bất kỳ nhánh code nào riêng cho "15L"**. Dữ liệu thật (`RECORD1.accdb.tblRECORD`) xác nhận `LEVEL="15"` là giá trị hợp lệ đang dùng, nhưng đi qua logic chung, không có xử lý đặc biệt. Chi tiết: [b24-warehouse-routing.md](file:///F:/DF/.claude/b24-warehouse-routing.md) Mục 7.
- **Câu hỏi:** "15L special" là quy tắc nghiệp vụ ẩn (nằm ở đâu nếu không phải VBA/công thức Excel — layout khổ giấy in? quy trình thủ công riêng?) hay chỉ là tên gọi lịch sử không còn phản ánh code hiện tại? **BLOCKED_BY_BUSINESS_CONFIRMATION** — không code phần này cho tới khi rõ.


### CH-BUS-013: Cơ chế đối chiếu giữa RECORD_A (điều phối/sổ gửi hàng) và RECORD_B (cân) có tồn tại không?
- **Nội dung:** Xác nhận `RECORD.accdb` (RECORD_A) và `RECORD1.accdb` (RECORD_B) là 2 database Access hoàn toàn độc lập, không bảng nào trùng tên, không khóa ngoại nào liên kết trực tiếp — chỉ liên kết gián tiếp qua nghiệp vụ (MACHINE+COLOR+CODE xuất hiện ở cả 2 phía). Chi tiết: [legacy-database-mapping.md](file:///F:/DF/.claude/legacy-database-mapping.md).
- **Câu hỏi:** Có quy trình đối chiếu thủ công/tự động nào giữa "đã gửi lệnh nhuộm" (RECORD_A) và "đã cân xong" (RECORD_B) không? Nếu có, quy trình đó chạy ở đâu (không nằm trong 5 workbook đã audit)? Ảnh hưởng trực tiếp tới việc có cần thiết kế khóa ngoại thật giữa 2 domain ở schema đích hay không.

### CH-BUS-014: `chem_order.accdb.tblRECORD`/`tblRECORD_chem` (47.381/1.500 dòng, dừng ở 2026-03-31) còn ý nghĩa gì không?
- **Nội dung:** 2 bảng này có schema giống hệt `RECORD1.accdb.tblRECORD`/`tblRECORD_chem` (RECORD_B) nhưng dữ liệu cũ hơn ~3.5 tháng và **không có Sub/Function nào trong `chem_order.frm`** (đã audit đầy đủ 44 procedure) đọc/ghi chúng.
- **Câu hỏi:** Đây có phải bản backup tĩnh từ 1 lần bảo trì (~cuối tháng 3/2026) rồi bỏ quên, hay có ứng dụng/quy trình khác (ngoài 5 workbook đã audit) vẫn đang dùng? Nếu là backup bỏ quên, xác nhận để loại khỏi phạm vi di trú.

### CH-BUS-004: Hàm `TraHeSo` (tra hệ số 3 chiều mã×khổ vải×tiêu) có còn cần dùng không?
- **Nội dung:** Đợt rà soát VBA→Web ngày 2026-07-16 phát hiện `FormulaCalculationService::calculateWater()` dùng mô hình tra cứu 2 khóa (machine_line+process_code), khác hẳn hàm `TraHeSo(Code, Width, Tiao)` gốc (3 khóa) từng chạy trong `CÔNG THỨC SẢN XUẤT CHUNG - new.xlsm` và `张力表-NEW VERSION.xlsm`. Tài liệu `.claude` trước đây ghi nhầm là "đã xác minh" — đã sửa lại, xem [source-traceability.md](file:///F:/DF/.claude/source-traceability.md) và VBA-RECIPE-012/013 trong [vba-migration-matrix.md](file:///F:/DF/.claude/vba-migration-matrix.md).
- **Câu hỏi:** Mô hình tra cứu 3 chiều (mã hàng × khổ vải × tiêu chuẩn) có còn được dùng trong vận hành thực tế không, hay đã được thay thế có chủ đích bằng mô hình khác? Nếu vẫn cần, phải xây bảng dữ liệu và logic tra cứu mới trước khi coi phân hệ Công thức là hoàn tất.

### CH-BUS-005: Quy tắc dung tích tối thiểu 250L khi điều phối máy nhuộm
- **Nội dung:** `C3 grid load row lock id FB -.xlsm` chặn lưu nếu máy ∈ {VD06..VD13} và thùng ∈ {1A,2B} và mực nước (level) < 250L; `MACHINE_ID_LOCKED.xlsm` (cùng chức năng, workbook khác) **không kiểm tra quy tắc này**.
- **Câu hỏi:** Quy tắc dung tích tối thiểu 250L có còn đúng và bắt buộc không? Áp dụng cho những máy/thùng nào? Cần xác nhận trước khi thiết kế API tạo lệnh điều phối mới (hiện `MachineDispatchController` chưa có action `store`).

### CH-TECH-003: Cơ chế `tblSync` (round-robin đa máy trạm) có đang thực sự vận hành không?
- **Nội dung:** Bảng `legacy_df_data.tblSync` (cột `NextFE`, `FE1_Alive`..`FE5_Alive`) có cấu trúc trong Postgres dev nhưng **không có VBA nguồn nào tại F:\DF tham chiếu tới nó**. **[Cập nhật 2026-07-17 — kiểm kê dữ liệu thật]:** bảng này **RỖNG hoàn toàn (0 dòng)** — chưa phân định được là "tính năng chưa từng chạy thật" hay "bản export Access bị thiếu dữ liệu".
- **Câu hỏi:** Nhà máy có đang vận hành nhiều máy trạm điều phối đồng thời cần đồng bộ (round-robin) không? Nếu KHÔNG (và tblSync rỗng vì tính năng chưa từng dùng), xác nhận bằng văn bản để đóng rủi ro R-11. Nếu CÓ, cần bổ sung workbook nguồn (nghi vấn `C3 grid load row lock id FB -(1).xlsm` hoặc `Copy of MACHINE_ID_LOCKED.xlsm`) trước khi triển khai Local Agent tại >1 máy trạm (Phase 12).

### CH-TECH-004: Mapping cột `tbl_ToSend2`/`WAITING`/`tbl_Waiting` trong script transform SQL — đã đúng chưa?
- **Nội dung:** `sql_migration/03_transform_legacy_to_target.sql` có ánh xạ cột thủ công cho `tbl_ToSend2` (696 dòng thật) và `WAITING` (57 dòng, ID/TIME rỗng toàn bộ) nhưng đây là suy luận dựa trên so sánh cấu trúc cột, **chưa được xác minh bằng VBA thật**. **[Cập nhật 2026-07-17]:** phát hiện thêm bảng thứ 4 `tbl_Waiting` (71 dòng) — script hiện coi là "unshifted" (không lệch cột) nhưng dữ liệu thật cho thấy CŨNG lệch cột cùng kiểu với `WAITING` (CONFIRM1 luôn ="OK", MACHINE chứa mã VDxx) — giả định "unshifted" trong script **có khả năng SAI**. Chi tiết: [p0-d-legacy-tables-inventory.md](file:///F:/DF/.claude/p0-analysis/p0-d-legacy-tables-inventory.md).
- **Câu hỏi:** Có thể bổ sung workbook nguồn ghi/đọc các bảng này để xác minh đúng ánh xạ cột (cả 3 bảng nghi lệch) trước khi tin tưởng dữ liệu lịch sử đã di trú từ đây không?

### CH-TECH-005: Ý nghĩa "low stand1" và "8 rows" (khác 9 rows đã xác nhận)
- **Nội dung:** Tên file gốc `semiauto-Checker plus-accept_reject semi 9rows lockmove SEND OVER6 - low stand1.xlsm` và `semiauto-chem-deltaRaw 8rows.xlsm` gợi ý 2 khái niệm này, nhưng cả 2 file đều **không có mặt tại F:\DF** nên chưa audit được. Chuỗi "low stand1" không xuất hiện trong bất kỳ VBA nào hiện có (đã grep toàn bộ 3 workbook nhóm Cân).
- **Câu hỏi:** "low stand1" và "8 rows" nghĩa là gì trong vận hành? Có khác biệt nghiệp vụ so với "9 rows"/"OVER6" đã xác nhận không (OVER6 đã xác nhận: cơ chế chia batch >6 rack thành 2 đợt gửi vì màn hình app đích chỉ có 6 ô cố định, không liên quan ngưỡng 6kg)?

### CH-BUS-006: Quy trình trừ bì (tare) khi cân — thao tác viên tự làm hay hệ thống tự tính?
- **Nội dung:** VBA gốc (`AutoFlow_OnWeight`/`Delta_Begin`) tự động trừ bì (lấy lần đọc đầu làm baseline, các lần sau tính delta). `WeighingJobController::weighItem` hiện **không có bước trừ bì** — so trực tiếp giá trị `weight` gửi lên với định mức.
- **Câu hỏi:** Quy trình cân mới có yêu cầu thao tác viên tự bấm nút TARE vật lý trên cân trước khi đọc (trừ bì bằng phần cứng), hay hệ thống phần mềm phải tự trừ bì như VBA cũ? Đây là rủi ro nghiệp vụ cần làm rõ bằng văn bản trước UAT — nếu client gửi `weight` gộp cả bì (gross) thay vì đã trừ (net), kết quả so dung sai sẽ sai.

### CH-BUS-007: Cơ chế tồn kho phòng liệu (nhập/xuất/cảnh báo) có cần khôi phục không?
- **Nội dung:** `DF料房-染料存.xlsm` có cơ chế tự động trừ kho theo hàng đợi tiêu thụ (`tblWH_LOG` → trừ `DF_STORAGE`), quy đổi kiện hàng theo mã thuốc nhuộm (bảng PCS, 6 mức trọng lượng chuẩn), và cảnh báo tồn kho thấp theo màu (đỏ <1000g, cam <5000g, xanh ≥5000g). Hệ mới (`app.materials.stock_qty`) chỉ là 1 cột số dư sửa tay qua modal, không có log giao dịch, không quy đổi kiện, không cảnh báo ngưỡng.
- **Câu hỏi:** Kho vận hiện có còn cần các cơ chế trên không? Ai đang ghi vào `tblWH_LOG` (hệ thống nào ghi log tiêu thụ này — không nằm trong 4 workbook công thức đã audit)?

### CH-TECH-006: Feedback loop / Editor cho kỹ sư tự cập nhật luật chẩn đoán sự cố
- **Nội dung:** VBA (`frmcheck.btn_insert/btn_load/btn_renew`) cho phép kỹ sư tự thêm/sửa rule Problem-Cause (điểm 0-5) qua giao diện. Hệ mới không có UI này — chỉ sửa được qua deploy lại seeder JSON tĩnh (`TroubleshootingKnowledgeBaseSeeder`).
- **[Đã trả lời 1 phần bằng phân tích kỹ thuật 2026-07-17 — xem [p0-e-troubleshooting-feedback.md](file:///F:/DF/.claude/p0-analysis/p0-e-troubleshooting-feedback.md)]:** ý nghĩa checkbox `check1..check24` đã được làm rõ ở mức kỹ thuật: giá trị 5/0 chỉ được GHI vào cột Y của sheet `Submited` và **không bao giờ được đọc lại ở bất kỳ đâu trong toàn bộ VBA** (đã grep cả 2 workbook) — tức VBA gốc KHÔNG có "học tự động", chỉ có dữ liệu tiềm năng chưa từng dùng. Việc "học tự động" nếu muốn sẽ là tính năng mới hoàn toàn (phase sau, cần PM/QA quyết định riêng).
- **Câu hỏi còn lại:** (1) Có cần xây UI quản trị Knowledge Base (CRUD `problem_cause_rules` — tương đương VBA Editor, kèm audit log; kế hoạch FIX-005 đã sẵn) không? (2) Về nghiệp vụ, khi kỹ sư tick checkbox lúc Report, họ ĐỊNH ghi nhận điều gì (QA duyệt từng dòng cause? đánh dấu "đã kiểm tra"?) — cần biết để quyết định có thêm trường per-cause feedback ở web không (tùy chọn, không chặn FIX-005).

### CH-BUS-008: Bản nào của công cụ chẩn đoán sự cố đang chạy sản xuất thực tế?
- **Nội dung:** `troubleshooting_support engine_DF.xlsm` và `troubleshooting_support engine - 染纱-缸染.xlsm` có code giống hệt 100% (xem [vba-version-comparison.md](file:///F:/DF/.claude/vba-version-comparison.md) mục 6) — không thể phân biệt bằng code bản nào là chính.
- **Câu hỏi:** Phòng QA/nhuộm xác nhận: 1 trong 2 bản có phải triển khai riêng cho 1 xưởng/quy trình cụ thể không, hay chỉ là bản sao dự phòng? Có cần đối chiếu dữ liệu Knowledge Base (sheet `KB_*`) giữa 2 file để đảm bảo không mất rule nào không (audit này mới chỉ so sánh code, chưa so sánh dữ liệu KB)?

### CH-BUS-002: Quy định dung sai cân bột màu và hóa chất phụ trợ
- **Nội dung:** Quy trình cân thuốc nhuộm (bột màu) yêu cầu kiểm soát dung sai nghiêm ngặt để tránh lệch màu nhuộm vải.
- **Câu hỏi:** 
  - Quy định dung sai cân hiện tại được tính toán tĩnh hay động? 
  - Có phân chia dải cân hay không (ví dụ: lượng bột màu nhỏ < 10g yêu cầu dung sai chặt chẽ hơn ±1%, lượng bột màu lớn > 1kg cho phép dung sai nới lỏng hơn ±2%)?
  - Có quy định dung sai riêng cho hóa chất phụ trợ không?

### CH-TECH-002: Giao thức Serial kết nối Cân điện tử
- **Nội dung:** Local Agent cần đọc số cân trực tiếp từ cân điện tử qua cổng COM (Serial Port) của máy trạm.
- **Câu hỏi:** 
  - Model cụ thể của các cân điện tử đang sử dụng tại nhà máy là gì?
  - Giao thức truyền thông cổng Serial của cân là gì (Baud rate, Data bits, Parity, Stop bits)?
  - Chuỗi ký tự thô cân gửi ra có định dạng như thế nào (ví dụ: `ST,GS,+   1.245 kg\r\n` hay chỉ có số thô)?

### CH-BUS-003: Quy tắc chia ca kíp sản xuất (phát sinh từ Phase 11 - Báo cáo sản lượng)
- **Nội dung:** Báo cáo sản lượng máy nhuộm (`ReportController::machineOutput`, `group_by=shift`) hiện đang **suy luận ca kíp từ giờ trong ngày** theo mẫu 3 ca 8 tiếng phổ biến (Ca 1: 06h-14h, Ca 2: 14h-22h, Ca 3: 22h-06h), vì không có cột "ca" trong bất kỳ bảng dữ liệu nguồn nào (Access legacy lẫn schema `app` hiện tại).
- **Đây là giả định kỹ thuật, KHÔNG PHẢI quy tắc nghiệp vụ đã xác nhận.** Cần người dùng xác nhận:
  1. Nhà máy có thực sự chia 3 ca 8 tiếng theo khung giờ trên không, hay có mẫu ca khác (ví dụ 2 ca 12 tiếng, ca gãy, ca cuối tuần khác ngày thường)?
  2. Giờ bắt đầu/kết thúc ca chính xác là bao nhiêu?
- **Khuyến nghị kỹ thuật:** Khi đã có câu trả lời, nên đưa cấu hình khung giờ ca vào một bảng cấu hình hệ thống (ví dụ `app.shift_configs`) thay vì hard-code trong `ReportController::shiftCaseSql()`, để thay đổi ca kíp không cần sửa code. Chưa triển khai vì chưa có xác nhận nghiệp vụ - việc này cần làm **trước khi dùng số liệu "sản lượng theo ca" cho quyết định vận hành**, nhưng không chặn các nhóm báo cáo khác (theo ngày/tháng) của Phase 11.

### CH-BUS-009: Đối chiếu 7 IP lịch sử mạng với 6 máy nghiệp vụ đã xác nhận (mới, 2026-07-17)
- **Nội dung:** Người dùng đã xác nhận trực tiếp cơ cấu vận hành thật gồm **6 máy nghiệp vụ**: 1× CHEMICAL_CALL, 1× PRODUCTION_ORDER, 1× QR_LABEL_PRINTING, 2× SMALL_SCALE, 1× LARGE_SCALE (xem `workstation-matrix.md`). Danh sách lịch sử kết nối mạng trước đó ghi nhận **7 IP** (3× gán "ORDER_SCAN", 3× gán "WEIGH TO_CONFIRM", 1× gán "LABEL_PRINTING"). Nhóm WEIGH khớp số lượng (3 IP = 2 SMALL_SCALE + 1 LARGE_SCALE); nhóm PRINT khớp số lượng (1 IP) nhưng workbook liên kết trước đó sai (xem CH-TECH-004-mới bên dưới); nhóm ORDER_SCAN có 3 IP nhưng chỉ có 1 máy PRODUCTION_ORDER được xác nhận, và **không có IP lịch sử nào từng gán cho CHEMICAL_CALL**.
- **Câu hỏi:** (1) Trong 3 IP "ORDER_SCAN" (192.168.250.192, 10.0.3.95, 192.168.250.196), IP nào là máy PRODUCTION_ORDER thật, IP nào là máy CHEMICAL_CALL (nếu đúng giả thuyết 1 trong 3 IP này chính là CHEMICAL_CALL), và IP còn lại là gì (máy dự phòng/thử nghiệm/không còn dùng, hay 1 vai trò khác chưa xác định)? (2) Trong 3 IP "WEIGH", IP nào là SMALL_SCALE #1, SMALL_SCALE #2, và IP nào là LARGE_SCALE? (3) IP `10.0.19.79` (PRINT) có đúng là máy chạy workbook DF028 (`3.DF028 ... jit qr sending - 15l special.xlsm`) hay đang chạy 1 trong 2 workbook cũ (`in tem Copower.xlsm`/`QR PRINTER...`)?

### CH-BUS-010: RECIPE (Công thức/TraHeSo) và TROUBLESHOOTING (Chẩn đoán sự cố) có phải workstation nhà xưởng cố định không? (mới, 2026-07-17)
- **Nội dung:** Cơ cấu 6 máy nghiệp vụ đã xác nhận với người dùng KHÔNG có máy nào cho RECIPE hay TROUBLESHOOTING — 2 nhóm nghiệp vụ này có VBA nguồn hợp lệ và đã audit đầy đủ procedure (NHÓM 1 và NHÓM 5 trong `vba-migration-matrix.md`) nhưng không rõ có gắn với 1 máy nhà xưởng vật lý cố định nào không.
- **Câu hỏi:** RECIPE và TROUBLESHOOTING có phải công cụ dùng trên máy tính văn phòng/kỹ thuật (bất kỳ trình duyệt nào, không khóa công đoạn) hay cũng cần thiết kế như 1 kiosk khóa cứng giống 6 máy nhà xưởng? Câu trả lời ảnh hưởng trực tiếp tới việc có cần thêm 2 loại "workstation" mới vào `app.workstations` hay không.

### CH-TECH-001: Giao thức tích hợp của Hệ thống Nhuộm & Pha màu tự động
- **Nội dung:** Ứng dụng đóng vai trò cầu nối dữ liệu MES sang hệ thống Nhuộm tự động & Pha màu tự động.
- **Câu hỏi:** 
  - Giao thức tích hợp hiện tại của hệ pha màu tự động (Dosing/Dispensing) là gì?
  - Hệ thống đó có đọc trực tiếp từ bảng cấu hình/lệnh `tbl_status` của MS Access cũ không? Trạng thái `status` sẽ được phần mềm tự động thay đổi như thế nào sau khi pha xong (ví dụ chuyển từ `'0'` sang `'1'`)?

---

## 2. Các Câu hỏi Đã được Làm rõ & Xác nhận (Resolved Questions)

### CH-RES-001: Cơ chế Cấp Hóa chất Tự động & Kết nối MES (Đã xác nhận)
- **Xác nhận từ người dùng:** 
  - Nhà máy nhuộm sử dụng **hệ thống tự động pha màu cho hệ thống nhuộm đai**. Do đó dữ liệu cân hóa chất phụ trợ thủ công không được ghi nhận.
  - Thông tin về màu sắc và sản phẩm gốc nằm trên **hệ thống MES** của nhà máy.
  - Hiện tại **chưa có hệ thống kết nối trực tiếp** giữa MES và hệ thống Nhuộm. 
  - **Quyết định kiến trúc:** Ứng dụng Web mới này sẽ đóng vai trò là **cầu nối trung gian (Connector/Bridge)** kết nối dữ liệu từ MES sang hệ thống Nhuộm tự động & Pha màu tự động.

### CH-RES-002: Tích hợp Máy in TSC & Size Tem Động (Đã xác nhận)
- **Xác nhận từ người dùng:** 
  - Máy in sử dụng dòng **TSC TE200** hoặc các dòng tương thích khác.
  - Kết nối: Hỗ trợ cả **kết nối trực tiếp với máy tính (USB)** hoặc **kết nối qua mạng LAN**.
  - **Quyết định thiết kế:** Kích thước nhãn tem in (Label Size) **cho phép điều chỉnh động trực tiếp trên giao diện của ứng dụng Web**, Backend tự sinh lệnh in TSPL động theo kích thước cấu hình trước khi đẩy xuống Agent.

### CH-RES-003: Xử lý Lệch cột & Bổ dung Bảng Thiếu (Đã xác nhận)
- **Xác nhận từ người dùng:** 
  - Đồng ý việc sửa chữa logic ánh xạ của các cột bị lệch (như trong `tbl_ToSend2` và `WAITING`) để khớp đúng với thông tin nghiệp vụ thực tế.
  - Đối với các bảng bị thiếu trong file Access, lập trình viên chủ động **kiểm tra mã nguồn VBA** của các workbook Excel để bổ sung cấu trúc bảng và dữ liệu cấu hình cho hợp logic.

### CH-RES-004: Phê duyệt Stack Công nghệ (Đã xác nhận)
- **Xác nhận từ người dùng:** Đồng ý phê duyệt Stack công nghệ đề xuất: **Laravel (Backend API) + PostgreSQL + Vue.js (Frontend Web) + Local Agent .NET (Windows Service cục bộ)**.

### CH-RES-005: Biểu đồ Pareto sự cố có che khuất số lượng ca hay tỉ lệ % không? (Đã xác minh bằng thiết kế)
- **Bối cảnh:** Chuẩn thiết kế trực quan hóa nội bộ cấm biểu đồ 2 trục (dual-axis). Biểu đồ Pareto truyền thống dùng 2 trục (số ca ở trục trái, % tích lũy ở trục phải) nên `ParetoChart.vue` (Phase 11) được vẽ lại theo 1 trục 0-100%: cột thể hiện tỉ lệ % theo nguyên nhân, đường thể hiện % tích lũy.
- **Xác minh:** Số ca thực tế (case_count) không bị mất - được hiển thị trực tiếp trên đầu mỗi cột (direct label) và trong tooltip hover, đồng thời cột dữ liệu chi tiết bên dưới biểu đồ liệt kê đầy đủ số ca + % + % tích lũy. Vì vậy biểu đồ vẫn thể hiện rõ cả số lượng và tỉ lệ, không cần trục kép.

### CH-BUS-012: Lỗ hổng D1 cho tổ hợp máy VD14-VD16 + tank 3C/4D (Đã đóng — RESOLVED 2026-07-17)
- **Nội dung ban đầu:** Nghi ngờ `Mod_printslip.PrintSlip_70x100` có "lỗ hổng" khiến D1 rỗng cho tổ hợp VD14-16+3C/4D.
- **Kết quả xác nhận:** Đọc lại VBA gốc lần 2 khi review code Phase E — xác nhận nhánh cuối thực tế là `VD10 ≤ machine ≤ VD16` (không phải VD13 như ghi nhầm ở đợt audit trước) → **không có lỗ hổng**, D1="JIT1" luôn được gán đúng. Đóng bằng bằng chứng kỹ thuật trực tiếp, không cần xác nhận nghiệp vụ. Chi tiết: `decision-records.md` ADR-BUS-012, `b24-warehouse-routing.md` Mục 6.

### CH-TECH-007: Xác nhận loại trạm cho 3 máy cân thực tế (WS-WEIGH-01, WS-WEIGH-02, WS-WEIGH-03)
- **Nội dung:** Ba máy cân có IP `10.0.19.74`, `10.0.19.171`, `192.168.100.221` được đề xuất mã `WS-WEIGH-01/02/03`.
- **Câu hỏi:** Cần xác nhận thực tế tại xưởng mỗi máy trạm này thuộc loại trạm cân nào (cân thuốc nhuộm DYE, cân hóa chất CHEM, cân trợ chất A11, cân trợ chất DLG, hay cân nhỏ) và gán model cân tương ứng. Trong giai đoạn setup, các máy này tạm để trạng thái `TO_CONFIRM` cho Admin tự cấu hình.

### CH-TECH-008: Phương án thu thập Device Fingerprint trên trình duyệt Kiosk
- **Nội dung:** Để nhận diện trạm làm việc mà không dùng IP tĩnh, hệ thống sử dụng Certificate/Token kết hợp vân tay thiết bị (Device Fingerprint).
- **Câu hỏi:** Do trình duyệt web thông thường có giới hạn bảo mật khi đọc thông tin phần cứng (như CPU ID, MAC Address), phương án thu thập fingerprint sẽ là: (a) Local Agent .NET chạy ngầm thu thập phần cứng rồi đẩy lên qua API, hay (b) Sử dụng thư viện fingerprint JS tiêu chuẩn (như FingerprintJS) trên trình duyệt? Khuyến nghị sử dụng kết hợp cả hai: Agent xác thực phần cứng cho kết nối Agent, trình duyệt lưu Token an toàn trong cookie/localStorage.

