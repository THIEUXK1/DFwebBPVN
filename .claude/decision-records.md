# Business Decision Records — Nhật Ký Quyết Định Nghiệp Vụ (decision-records.md)

Lập 2026-07-17 — Phase C/D. Tài liệu ghi nhận các Quyết định Nghiệp vụ (ADR - Business Decision Records) cho 4 blocker then chốt từ `CH-BUS-011` đến `CH-BUS-014`. Tài liệu thiết kế — không sửa code sản xuất.

---

## ADR-BUS-011: Giải quyết trường hợp "15L Special" (CH-BUS-011)

- **Bối cảnh:** Tên file workbook điều phối cũ có chứa hậu tố "15l special". Ban đầu có giả định rằng mẻ nhuộm 15L có thể có quy trình hoặc bố cục tem nhãn in hoàn toàn khác biệt.
- **Bằng chứng đã biết:** Audit mã nguồn Excel VBA của file `3.DF028...` không phát hiện bất kỳ nhánh code rẽ nhánh (`If/Else`) hoặc hàm riêng nào xử lý riêng cho mẻ "15L". Tất cả các mẻ đều đi qua một logic tính QR và in tem như nhau.
- **Điều chưa biết:** "15L" thể hiện ở đâu trên thực tế sản xuất: nhãn in có kích thước khác không, có yêu cầu in nhiều bản sao hơn không, hay đây chỉ là tên phiên bản file workbook cũ do kỹ sư vận hành lưu trữ.
- **Ảnh hưởng:** Nếu tự ý viết code riêng cho 15L mà không có nghiệp vụ xác nhận sẽ gây phức tạp hóa mã nguồn vô ích và có nguy cơ lỗi in tem.
- **Các lựa chọn:**
  - **Lựa chọn A:** Viết code hard-code logic in riêng cho mẻ 15L.
  - **Lựa chọn B:** Giữ nguyên logic in tiêu chuẩn, không hard-code 15L. Đánh dấu blocker và ghi warning log.
- **Khuyến nghị kỹ thuật (Đã chọn):** Lựa chọn B. Đánh dấu trạng thái blocker: `BLOCKED_BY_BUSINESS_CONFIRMATION` (mặc định chưa kích hoạt rule 15L).
- **Default an toàn nếu chưa có quyết định:** Áp dụng template và luồng in tiêu chuẩn của trạm `QR_LABEL_PRINTING`. Ghi warning audit khi phát hiện mẻ có nhãn "15L".
- **Người cần xác nhận:** Shift Leader / Trưởng ca Sản xuất.

---

## ADR-BUS-012: Quy tắc D1 cho máy VD14-VD16 và bồn 3C/4D (CH-BUS-012) — **RESOLVED 2026-07-17**

- **Bối cảnh:** Lộ trình tính toán phân vùng kho B24 từ Excel cũ cho máy VD14 - VD16 kết hợp với bồn nhuộm 3C/4D **từng bị nghi ngờ** thiếu quy tắc kho D1 (nghi vấn "lỗ hổng logic nghiệp vụ" trong code VBA cũ, ghi nhận ở đợt audit 2026-07-17 lần 4).
- **CẬP NHẬT — Bằng chứng mới khi review code Phase E (2026-07-17, cùng ngày):** Đọc lại trực tiếp VBA gốc (`Mod_printslip.bas`, hàm `PrintSlip_70x100`) lần thứ 2 để đối chiếu với code `WarehouseRoutingService.php` đã sinh — phát hiện đợt audit B24 trước đó ghi SAI dải nhánh cuối là "VD10-VD13" (đúng ra `f3Val >= "VD10" And f3Val <= "VD16"`). **Không hề có lỗ hổng** — VBA gốc gán đúng `D1="JIT1"` cho MỌI tổ hợp VD10-VD16 + 3C/4D, bao gồm cả VD14-16. Đây là lỗi transcription của đợt audit trước (tự mâu thuẫn với chính bảng trong `b24-warehouse-routing.md` Mục 6, vốn đã ghi đúng "VD10–VD16" nhưng đoạn cảnh báo phía dưới lại tính sai).
- **Ảnh hưởng của phát hiện mới:** Không còn rủi ro sai lệch vật tư kho cho tổ hợp này — hành vi migrate đúng là **sao chép chính xác `D1="JIT1"`**, không cần chế độ đặc biệt nào.
- **Quyết định cuối cùng (đã chọn, KHÔNG còn là LEGACY_EXACT/FIXED_D1/MANUAL_REVIEW):** Áp dụng logic `f3Val >= "VD10" And f3Val <= "VD16"` (khớp 100% VBA gốc) làm hành vi DUY NHẤT và MẶC ĐỊNH — không cần feature flag riêng cho tổ hợp này. `manual_routing_review_enabled` vẫn giữ nguyên cho các trường hợp thật sự không khớp nhánh nào (mã máy ngoài VD01-VD18), không liên quan tổ hợp VD14-16+3C/4D nữa.
- **Trạng thái:** `CH-BUS-012` **ĐÃ ĐÓNG (RESOLVED)** — đóng bằng bằng chứng trực tiếp từ mã nguồn (đọc lại 2 lần, khớp tuyệt đối), không phải bằng suy đoán hay xác nhận nghiệp vụ (không cần xác nhận nghiệp vụ vì đây là lỗi transcription thuần kỹ thuật, không phải điểm mơ hồ nghiệp vụ thật).
- **Hành động đã thực hiện:** Sửa `WarehouseRoutingService.php` (dải `VD10,'VD16'` thay vì `VD10,'VD13'`, bỏ nhánh xử lý "gap" giả), sửa `WarehouseRoutingServiceTest.php` (thay `test_routing_gap_behavior` bằng `test_vd14_to_vd16_with_tank_3c_4d_resolves_to_jit1_no_gap`), sửa `b24-warehouse-routing.md` Mục 6.
- **Người đã xác nhận:** Tự đóng bằng bằng chứng kỹ thuật trực tiếp (không cần Quản lý Kho bãi xác nhận cho riêng điểm này) — 2026-07-17.

---

## ADR-BUS-013: Khóa đối chiếu dữ liệu RECORD_A và RECORD_B (CH-BUS-013)

- **Bối cảnh:** Database điều phối `RECORD_A` (`RECORD.accdb`) và database cân `RECORD_B` (`RECORD1.accdb`) chạy độc lập, không có khóa ngoại (Foreign Key) cứng liên kết giữa hai hệ thống.
- **Bằng chứng đã biết:** Audit không thấy bất kỳ cơ chế đồng bộ hoặc ID chung nào giữa 2 database. Liên kết duy nhất là các composite business keys (ví dụ: Machine + Color + Code + Date) nhưng có sai lệch lớn do nhập liệu thủ công hoặc lệch múi giờ.
- **Điều chưa biết:** Cơ chế đối chiếu hiện tại của nhà máy (nếu có) là gì, hoặc làm sao để khẳng định một mẻ cân ở RECORD_B thuộc về dòng điều phối nào ở RECORD_A một cách chính xác 100%.
- **Ảnh hưởng:** Nếu tự ý gán bừa bãi dữ liệu cân vào dispatch dựa trên thời gian gần nhau sẽ làm sai lệch dữ liệu traceability và báo cáo kiểm toán vật tư.
- **Các lựa chọn:**
  - **Lựa chọn A:** Tạo liên kết cứng dựa trên thuật toán heuristic đoán ngày/giờ gần nhau.
  - **Lựa chọn B:** Giữ trạng thái blocker. Sử dụng bảng liên kết mềm `app.correlation_links` với cờ trạng thái `correlation_status` và độ tin cậy `confidence`. Đẩy các trường hợp nghi ngờ vào Exception Queue.
- **Khuyến nghị kỹ thuật (Đã chọn):** Lựa chọn B. Tiếp tục coi `CH-BUS-013` là blocker cho đến khi có cơ chế correlation được duyệt chính thức.
- **Default an toàn:** Chỉ map tự động khi khớp hoàn toàn Business Key chính xác (`EXACT` match). Các trường hợp khác lưu trạng thái `EXCEPTION_QUEUE` và yêu cầu QA đối chiếu thủ công.
- **Người cần xác nhận:** Trưởng bộ phận QA & IT Nhà máy.

---

## ADR-BUS-014: Vai trò của database chem_order.accdb.tblRECORD (CH-BUS-014)

- **Bối cảnh:** File database `chem_order.accdb` có chứa bảng `tblRECORD` và `tblRECORD_chem` với cấu trúc giống hệt database cân `RECORD_B` nhưng dữ liệu chỉ dừng lại ở ngày 31/03/2026.
- **Bằng chứng đã biết:** Audit mã nguồn VBA của form `chem_order.frm` khẳng định không có bất kỳ dòng code nào đọc hoặc ghi vào hai bảng này. Dữ liệu mới nhất dừng lại ở thời điểm bảo trì tháng 3.
- **Điều chưa biết:** Đây có phải là tệp lưu tạm, backup cũ hay là dữ liệu staging của một trạm cân cũ không còn hoạt động.
- **Ảnh hưởng:** Nếu đưa hai bảng này vào target model sẽ làm loãng database, tăng dung lượng lưu trữ vô ích và gây nhiễu dữ liệu báo cáo mẻ cân.
- **Các lựa chọn:**
  - **ACTIVE_SOURCE:** Coi là nguồn dữ liệu hoạt động.
  - **REFERENCE_ONLY:** Chỉ dùng để đọc tham chiếu.
  - **LEGACY_ARCHIVE (Khuyến nghị):** Bản sao lưu tĩnh cũ, chỉ lưu trữ lịch sử, không đưa vào target model nghiệp vụ.
  - **DUPLICATE_CONFIRMED:** Xác nhận là trùng lặp 100% với RECORD_B.
  - **UNKNOWN_BLOCKED:** Giữ trạng thái blocked chờ IT xác nhận.
- **Khuyến nghị kỹ thuật (đề xuất, CHƯA đóng blocker):** Phân loại tạm thời **`LEGACY_ARCHIVE`** dựa trên bằng chứng kỹ thuật hiện có (không có writer/reader trong VBA đã audit, dữ liệu dừng cập nhật từ 2026-03-31). **SỬA LẠI (đối chiếu chéo với `legacy-database-mapping.md` và nguyên tắc "không tự đóng blocker khi chưa đủ bằng chứng"):** đây chỉ là phân loại kỹ thuật tạm thời — **`CH-BUS-014` VẪN GIỮ TRẠNG THÁI `UNKNOWN_BLOCKED`** cho tới khi IT/nghiệp vụ xác nhận chính thức bằng văn bản rằng đây đúng là bản sao lưu bị bỏ quên (không phải staging của 1 trạm/quy trình khác đang/từng hoạt động ngoài phạm vi 5 workbook đã audit). Không tạo bảng target tương đương trong schema `app` cho tới khi `UNKNOWN_BLOCKED` được đóng bằng xác nhận thật.
- **Default an toàn:** Không đồng bộ hoặc tạo bảng target tương đương cho bảng này trong postgres `app` schema; giữ nguyên trong staging thô (chỉ đọc) nếu cần tham chiếu.
- **Người cần xác nhận:** Quản lý IT vận hành hệ thống Access cũ.

> **Ghi chú đối chiếu chéo (2026-07-17):** `legacy-database-mapping.md` (cập nhật cùng ngày) ghi đúng 2 tầng: phân loại kỹ thuật tạm `LEGACY_ARCHIVE` + trạng thái blocker `UNKNOWN_BLOCKED` chưa đóng — ADR này đã điều chỉnh để khớp, tránh mâu thuẫn "đã đóng blocker" ở bản nháp ADR trước đó.
