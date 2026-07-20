# Risks, Assumptions, Constraints & Dependencies

Tài liệu này phân loại các Rủi ro, Giả định, Ràng buộc, Phụ thuộc và Điểm chưa rõ của dự án DF. 

> [!NOTE]
> **Cập nhật Phase C/D (2026-07-17):** Đã bổ sung thiết kế chi tiết để giải quyết các rủi ro kỹ thuật (như correlation đối chiếu RECORD_A/B, cơ chế offline buffer cho Local Agent, và kiểm soát đồng thời qua transaction row lock). Các giải pháp này đã được tài liệu hóa thành ERD, API contracts và State Machines.

---

## 1. Risks (Rủi ro & Biện pháp kiểm soát)

- **R-01: Thất bại khi khôi phục bảng nhật ký gửi máy `tbl_SentLog`**
  - *Tác động:* Cao (Mất mát dữ liệu lịch sử điều phối máy quan trọng nhất).
  - *Kiểm soát:* Chạy Compact & Repair trên bản sao Access; nếu thất bại, viết script cứu hộ riêng để đọc từng bản ghi từ file raw hex hoặc sử dụng thư viện Access độc lập khác để cứu tối đa dữ liệu.
- **R-02: Lỗi parse ngày giờ thô dạng TEXT sang TIMESTAMP**
  - *Tác động:* Trung bình (Làm dừng quá trình di trú dữ liệu).
  - *Kiểm soát:* Chỉ định rõ `datestyle = 'ISO, MDY'` trong session import của Postgres. Viết hàm Regex lọc/chuẩn hóa chuỗi ngày giờ trước khi import.
- **R-03: Sự cố mất kết nối mạng LAN tại nhà xưởng**
  - *Tác động:* Cao (Nhân viên không cân được, không in được tem gây dừng dây chuyền sản xuất).
  - *Kiểm soát:* Thiết kế Local Device Agent có cơ chế Offline Queue ghi đệm SQLite cục bộ, cho phép nhân viên tiếp tục cân/in tem bình thường và tự động sync lên server khi có mạng lại.
- **R-04: Tranh chấp khóa đồng thời trạm điều phối**
  - *Tác động:* Trung bình (Dẫn đến gửi trùng lệnh xuống máy nhuộm hoặc treo khóa).
  - *Kiểm soát:* Áp dụng cơ chế khóa logic (Logic Lock) có thời hạn và Pessimistic Locking (`SELECT ... FOR UPDATE`) ở tầng Database để đảm bảo chỉ có duy nhất 1 dispatcher claim thành công bản ghi hàng chờ tại một thời điểm.
- **R-05: Sai số tính toán định lượng công thức nhuộm**
  - *Tác động:* Cao (Làm sai màu nhuộm của vải, gây thiệt hại kinh tế lớn).
  - *Kiểm soát:* Thực hiện kiểm thử Golden Master so sánh kết quả tự động giữa Web và VBA trên 50 mẻ mẫu trước khi chạy pilot.
- **R-06: Hàm `TraHeSo` (tra hệ số 3 chiều mã×khổ vải×tiêu) chưa được migrate** *(phát hiện đợt rà soát VBA 2026-07-16)*
  - *Tác động:* Cao nếu vẫn còn dùng trong sản xuất (sai định lượng công thức mà không có cảnh báo, vì `FormulaCalculationService` hiện chạy 1 mô hình khác hoàn toàn không báo lỗi).
  - *Kiểm soát:* Xác nhận nghiệp vụ (xem `open-questions.md` mục CH-BUS-004) trước khi coi phân hệ Công thức là hoàn tất; không dùng dữ liệu tính nước/tension hiện tại làm căn cứ Golden Master cho tới khi rõ ràng.
- **R-07: Toàn bộ luồng ghi mới vào hàng chờ điều phối (`machine_dispatches`) chưa tồn tại**
  - *Tác động:* Cao cho Phase 12 (chạy song song) — nếu trạm điều phối web cần hoạt động thật (không chỉ xử lý dữ liệu di trú sẵn có), tính năng sẽ không dùng được.
  - *Kiểm soát:* Bổ sung API `store` cho `MachineDispatchController` (kèm quy tắc chặn trùng color+code và dung tích tối thiểu — xem R-08) trước khi đưa trạm điều phối vào pilot thực tế.
- **R-08: 2 workbook điều phối có quy tắc nghiệp vụ KHÔNG đồng nhất (dung tích tối thiểu 250L)**
  - *Tác động:* Trung bình — nếu implement sai quy tắc, có thể cho phép/từ chối sai các lệnh gửi máy.
  - *Kiểm soát:* Xác nhận với nghiệp vụ trước khi code (xem `open-questions.md` mục CH-BUS-005).
- **R-09: Lõi thuật toán cân bán tự động (ổn định/StableFilter, delta/tare) chưa migrate + khác biệt hành vi thật ở tầng đọc số cân**
  - *Tác động:* Cao — `stable:true` bị hard-code ở `WeighingStation.vue` (không có bộ lọc ổn định thật); `ScaleReader.cs` lấy số **đầu tiên** trong chuỗi log thay vì **số cuối cùng** như VBA (`ExtractLastNumber`), có thể gây sai số cân trong 1 số định dạng chuỗi log cân.
  - *Kiểm soát:* Viết golden test cho `StableFilter`/`ExtractLastNumber` (bộ input mẫu đã đề xuất trong `vba-migration-matrix.md` nhóm SCALE, mục XI) và sửa `ScaleReader.CleanWeight` trước khi tin tưởng số liệu cân từ Local Agent trong pilot.
- **R-10: Dữ liệu lịch sử cân từ 1 trạm VBA (workbook B — `semiauto-...SEND OVER6...`) có bug làm sai lệch cột ACCEPTED/REJECTED**
  - *Tác động:* Trung bình — nếu dùng dữ liệu `tblRECORD`/`processColor` lịch sử từ trạm dùng workbook B làm Golden Master hoặc để đối soát tiêu hao, kết quả "REJECTED" của trạm đó không đáng tin (luôn bị ghi REJECTED do lỗi so màu, xem `vba-version-comparison.md` mục 4).
  - *Kiểm soát:* Xác định trạm nào từng chạy workbook B trước khi dùng dữ liệu lịch sử accept/reject của trạm đó cho phân tích/Golden Master.
- **R-11: `tbl_ToSend2`/`WAITING`/`tbl_Waiting`/`tblSync` không có VBA nguồn để xác minh mapping** *(cập nhật 2026-07-17 sau kiểm kê dữ liệu thật — xem `p0-analysis/p0-d-legacy-tables-inventory.md`)*
  - *Hiện trạng dữ liệu đã đo:* `tbl_ToSend2` 696 dòng (dừng ghi từ 28/11/2025); `WAITING` 57 dòng (ID/TIME 100% rỗng); `tblSync` **rỗng hoàn toàn (0 dòng)** — chưa phân định được là "tính năng chưa từng chạy" hay "export thiếu"; phát hiện thêm `tbl_Waiting` (71 dòng) bị script transform coi là "unshifted" nhưng dữ liệu thật cho thấy CŨNG lệch cột.
  - *Tác động:* Cao nếu cơ chế round-robin đa máy trạm (`tblSync`) thực sự từng vận hành — chưa có thiết kế thay thế nào ở web. Trung bình cho 3 bảng còn lại — mapping cột hiện có là suy luận chưa xác minh, và riêng `tbl_Waiting` khả năng mapping hiện tại **đang sai** (coi nhầm là không lệch cột).
  - *Kiểm soát:* Bổ sung workbook nguồn thiếu (xem `source-files-missing.md` mức P0) trước khi triển khai Local Agent tại nhiều máy trạm đồng thời (Phase 12); thực hiện FIX-004 trong `remediation-plan.md` khi có file; không dùng dữ liệu đã di trú từ 4 bảng này cho báo cáo/đối soát cho tới khi xác minh xong.
  - **[Cập nhật 2026-07-17 — CÂU HỎI ĐÃ ĐƯỢC TRẢ LỜI 1 PHẦN]:** audit workbook `3.DF028 ... jit qr sending - 15l special.xlsm` (workstation QR_LABEL_PRINTING thật) xác nhận `TO_SEND.ConfirmRow` là **nguồn ghi (INSERT) duy nhất tìm được cho `tbl_sentlog`** trong toàn bộ đợt audit — không cần tìm workbook khác cho riêng bảng này. Vẫn cần workbook nguồn của giai đoạn ghi `tbl_input_all` (nghi vấn thuộc C3/MID, chưa audit sâu phần INSERT) để đóng hoàn toàn câu hỏi mapping 4 bảng.

- **R-12 (mới 2026-07-17): Nghiệp vụ CHEMICAL_CALL — 1 trong 6 máy nghiệp vụ đang chạy sản xuất thật — hoàn toàn chưa được xây dựng trên web**
  - *Tác động:* Cao — nếu phạm vi pilot/cutover bao gồm máy này, trạm sẽ không có bất kỳ chức năng thay thế nào (0 Controller/route/view; bảng đích `app.machine_chemical_channels` thiếu cột lưu tín hiệu ORDER/DONE động).
  - *Kiểm soát:* Xác nhận phạm vi pilot 7 ngày có bao gồm máy CHEMICAL_CALL không (xem `open-questions.md` CH-BUS-009); nếu có, cần thiết kế + build tối thiểu trước ngày pilot đầu tiên — xem `pilot-blockers.md` PB-8.

- **R-13 (mới 2026-07-17): Máy in tem QR sản xuất thật (DF028) có 4 khoảng trống nghiệp vụ lớn chưa từng được phát hiện ở các đợt audit trước** (vì đợt audit PRINT trước đó audit nhầm 2 workbook không phải máy thật)
  - *Tác động:* Cao — logic phân vùng kho B24 + chọn chế độ mã hóa QR theo tổ hợp Machine×Tank (`Mod_printslip.PrintSlip_70x100`) quyết định thùng thuốc nhuộm/hóa chất luân chuyển tới đúng khu vực nhà xưởng nào; không có tương đương ở backend hiện tại. Ngoài ra hành vi "in tem = tự động xác nhận scale-check" và lưới giám sát tồn đọng 18×9 (tô màu theo tuổi dữ liệu 24h/48h) cũng MISSING hoàn toàn.
  - *Kiểm soát:* Xác nhận nghiệp vụ khẩn cấp về logic B24 trước UAT; ưu tiên nối dây 3 field đã có sẵn trong schema (`scale_checked`/`raw_qr_dye`/`raw_qr_chemical` — đã có migration + Model nhưng 0 controller dùng, effort thấp hơn thiết kế mới) — xem `pilot-blockers.md` PB-9, `vba-migration-matrix.md` NHÓM 4-DF028.

---

- **R-14 (mới 2026-07-17, đợt duyệt lần 4): 2 database cùng tên gốc "RECORD" (`RECORD.accdb`/`RECORD1.accdb`) là 2 hệ thống độc lập, không đồng bộ trực tiếp**
  - *Tác động:* Trung bình-Cao — nếu thiết kế schema đích giả định sai (coi 2 hệ là 1, hoặc bỏ sót vì tưởng là bản sao), sẽ mất khả năng truy vết xuyên suốt "đã gửi lệnh nhuộm" (RECORD_A) ↔ "đã cân xong" (RECORD_B) — đúng yêu cầu truy vết end-to-end của dự án.
  - *Kiểm soát:* Đã lập `legacy-database-mapping.md` với bằng chứng đầy đủ (schema, path VBA, dữ liệu mẫu). Không tự thiết kế khóa ngoại giả định giữa 2 domain cho tới khi CH-BUS-013 được trả lời.
- **R-15 (mới 2026-07-17): Logic phân vùng kho B24 hoàn toàn hard-code trong VBA, có ít nhất 1 lỗ hổng đã biết (VD14-16+3C/4D) và 1 điểm chưa xác định ("15L special")**
  - *Tác động:* Cao — nếu migrate sai/thiếu logic B24, thuốc nhuộm/hóa chất có thể được điều hướng sai khu vực nhà xưởng.
  - *Kiểm soát:* Đã trích xuất đầy đủ decision table từ code gốc (`b24-warehouse-routing.md`). Không code phần B24 cho tới khi CH-BUS-011/CH-BUS-012 được trả lời — feature flag `b24_routing_enabled` mặc định `false`.

## 2. Assumptions (Giả định)
- **A-01: Tính nhất quán của cơ sở dữ liệu nguồn:** Giả định hai tệp Access nhận được phản ánh chính xác cấu trúc và toàn bộ dữ liệu lịch sử đang vận hành trên xưởng.
- **A-02: Dữ liệu cân hóa chất phụ trợ:** Giả định dữ liệu cân hóa chất thô thực tế vẫn được nhân viên thực hiện (không bị bỏ qua), và việc trống dữ liệu trong bảng `tblRECORD_chem` chỉ là do cơ chế lưu của VBA cũ bị lỗi hoặc lưu ở tệp tin khác.
- **A-03: Môi trường máy tính trạm:** Giả định các máy trạm tại xưởng cân và điều phối đều chạy hệ điều hành Windows (Windows 10/11) để cài đặt Windows Service Agent thuận lợi.

---

## 3. Constraints (Ràng buộc kỹ thuật & Nghiệp vụ)
- **C-01: Bảo toàn dữ liệu gốc:** Tuyệt đối không được phép chỉnh sửa, ghi đè hoặc xóa các tệp Access nguồn và staging schema.
- **C-02: An toàn Production:** Không chạy trực tiếp database migrations hoặc script transform dữ liệu trên database sản xuất thực tế khi chưa test kỹ trên môi trường Staging/Test.
- **C-03: Sinh QR nội bộ:** Không sử dụng các API sinh QR trực tuyến bên ngoài mạng LAN để bảo vệ công thức công nghệ độc quyền.
- **C-04: Tương thích máy quét:** QR code sinh ra từ hệ thống mới phải có định dạng chuỗi thô giống hoàn toàn định dạng cũ để các thiết bị đầu cuối máy quét công đoạn hiện tại đọc được mà không phải lập trình lại phần cứng máy quét.

---

## 4. Dependencies (Phụ thuộc)
- **D-01: Phục hồi bảng `tbl_SentLog`:** Phân hệ điều phối chỉ có thể hoàn tất di trú lịch sử sau khi cứu hộ thành công bảng này.
- **D-02: Phản hồi từ Người dùng:** Việc lập trình logic thiết bị và nạp máy nhuộm phụ thuộc vào việc người dùng làm rõ các câu hỏi kỹ thuật trong [open-questions.md](file:///F:/DF/.claude/open-questions.md).

---

## 5. Unknowns (Các điểm chưa rõ)
- **U-01: Ý nghĩa nghiệp vụ của các mã trạng thái:** Ý nghĩa cụ thể của `CONFIRM1`, `CONFIRM2`, `LEVEL`, `processCOLOR` chưa được đặc tả rõ trong code VBA, cần UAT thực tế để khóa định nghĩa.
- **U-02: Giao thức gửi máy nhuộm:** Chưa rõ VBA cũ gửi dữ liệu lệnh bằng cách click chuột giả lập hay nạp tệp tin cấu hình.
