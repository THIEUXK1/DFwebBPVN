# Business Modules - Các Phân hệ Nghiệp vụ

Tài liệu này chi tiết hóa 12 phân hệ nghiệp vụ chính của hệ thống DF, làm cơ sở để phân rã các tính năng và phát triển phần mềm.

---

## 1. M01 - Master Data (Phân hệ Danh mục)
- **Mục tiêu:** Quản lý tập trung toàn bộ danh mục cốt lõi của nhà máy nhuộm.
- **Actor:** System Admin, Technologist.
- **Đầu vào:** Biểu mẫu thêm/sửa Máy, Bồn, Rack, Mã hàng, Mã màu, Thuốc nhuộm, Hóa chất, Phụ gia, Cân, Máy in.
- **Đầu ra:** Danh mục sẵn sàng phục vụ các phân hệ khác.
- **Trạng thái:** Active, Inactive (Soft delete).
- **Quy tắc nghiệp vụ:** Mã máy, mã màu, mã hàng phải là duy nhất. Không cho phép xóa danh mục nếu đã được tham chiếu bởi công thức hoặc giao dịch (Batch/Session).
- **Quan hệ:** Cung cấp thông tin nền cho M02, M03, M04, M05, M06, M07.
- **Phần đã xác minh:** Đã có cấu trúc bảng máy, bồn, vật tư từ dữ liệu Access.
- **Phần cần xác nhận:** Danh sách đầy đủ các loại máy in TSC và cấu hình IP/USB tại các trạm.

---

## 2. M02 - Formula & Technology (Công thức & Thông số Công nghệ)
- **Mục tiêu:** Định nghĩa công thức nhuộm, các thông số công nghệ (lực căng, mực nước, tỷ lệ dung dịch), cách tra hệ số kỹ thuật.
- **Actor:** Technologist, Approver/QA.
- **Đầu vào:** Dữ liệu nhập tay mã màu, mã hàng; quy tắc tra hệ số (TraHeSo).
- **Đầu ra:** Công thức hoàn chỉnh gồm các công đoạn và lượng thuốc nhuộm/hóa chất chuẩn cần cân.
- **Trạng thái:** DRAFT (Nháp) -> ACTIVE (Có hiệu lực) -> SUPERSEDED (Bị thay thế) / ARCHIVED (Lưu trữ).
- **Quy tắc nghiệp vụ:** 
  - Hàm `TraHeSo` phải cho kết quả chính xác tuyệt đối như Excel VBA (Golden Master).
  - Công thức đã được sử dụng trong sản xuất thực tế (lệnh đã phát hành) thì **KHÔNG** được sửa đổi trực tiếp. Thay đổi phải tạo phiên bản mới (Version control).
- **Quan hệ:** Đọc Master Data (M01), cung cấp dữ liệu định mức cho Lệnh sản xuất (M03) và Cân (M04, M05).
- **Phần đã xác minh:** Logic tra hệ số trong VBA của file `CÔNG THỨC SẢN XUẤT CHUNG - new.xlsm` và `张力表-NEW VERSION.xlsm`.
- **Phần cần xác nhận:** Cơ chế phê duyệt công thức (1 cấp hay nhiều cấp, có cần chữ ký điện tử không).

---

## 3. M03 - Production Orders (Lệnh & Lô sản xuất - Batch)
- **Mục tiêu:** Tiếp nhận và quản lý các lô sản xuất (Batch) cần nhuộm.
- **Actor:** Operator, Shift Leader, Production Manager.
- **Đầu vào:** Yêu cầu sản xuất (Mã hàng, mã màu, khối lượng vải, máy dự kiến, bồn dự kiến).
- **Đầu ra:** Lô sản xuất (Batch) với mã batch định dạng duy nhất (ví dụ: `YYYYMMDD_HHMMSS`).
- **Trạng thái:** NEW -> WAITING_CONFIRM -> READY_TO_WEIGH -> WEIGHING -> WEIGHED -> READY_TO_SEND -> SENT -> DONE.
- **Quy tắc nghiệp vụ:** Mỗi lô sản xuất phải gắn liền với một phiên bản công thức hoạt động (Active Formula Version) tại thời điểm phát hành.
- **Quan hệ:** Nhận dữ liệu định mức từ M02, chuyển sang M04/M05 để cân, và M07 để điều phối máy.
- **Phần đã xác minh:** Cấu trúc dữ liệu batch từ `tblRECORD` và `tblRECORD_chem`.
- **Phần cần xác nhận:** Quy tắc đặt mã lô tự động có thể cấu hình theo ngày/ca làm việc hay không.

---

## 4. M04 - Dye Weighing (Cân Thuốc nhuộm)
- **Mục tiêu:** Ghi nhận khối lượng bột thuốc nhuộm thực tế qua cân điện tử, so khớp với khối lượng chuẩn và dung sai cho phép.
- **Actor:** Operator, Shift Leader.
- **Đầu vào:** Lựa chọn lô cân, công đoạn nhuộm, số cân thời gian thực từ Local Agent.
- **Đầu ra:** Weighing Session được xác nhận hợp lệ.
- **Trạng thái:** PENDING -> WEIGHING -> OVERRIDE_REQUIRED -> CONFIRMED -> PRINTED.
- **Quy tắc nghiệp vụ:**
  - Sai số cân thực tế phải nằm trong khoảng dung sai cho phép (Tolerance Rule) định nghĩa trong công thức công nghệ.
  - Nếu số cân vượt dung sai, bắt buộc phải có tài khoản QA hoặc Shift Leader nhập lý do override và phê duyệt thì mới cho phép xác nhận.
- **Quan hệ:** Lấy thông số từ M03, gọi in tem từ M09, ghi nhận dữ liệu vào PostgreSQL qua API.
- **Phần đã xác minh:** Đọc dữ liệu cân thô qua log putty hoặc cổng COM, hàm `StableFilter` lọc số ổn định của cân.
- **Phần cần xác nhận:** Quy định dung sai mặc định cho từng nhóm thuốc nhuộm (ví dụ: cân lượng nhỏ < 10g yêu cầu dung sai chặt chẽ hơn lượng lớn > 1kg).

---

## 5. M05 - Automatic Chemical Dosing (Cấp Hóa chất Tự động)
- **Mục tiêu:** Quản lý và kích hoạt lệnh cấp hóa chất phụ trợ tự động cho bồn nhuộm đai theo công thức.
- **Actor:** Operator, Shift Leader, Automatic Dosing System (Hệ cấp tự động Copower/Lawer).
- **Đầu vào:** Lệnh kích hoạt cấp hóa chất cho bồn nhuộm, bảng cấu hình ánh xạ kênh van hóa chất (`app.machine_chemical_channels` chuyển đổi từ `tbl_status`).
- **Đầu ra:** Lệnh cấp thành công gửi sang hệ pha màu tự động.
- **Trạng thái:** PENDING -> CONFIRMED / DISPENSED.
- **Quy tắc nghiệp vụ:** 
  - Thay vì cân thủ công, hệ thống web mới sẽ **kích hoạt lệnh pha/cấp tự động** thông qua việc ghi nhận bản ghi vào bảng lệnh/trạng thái (được đồng bộ xuống hệ thống pha màu tự động).
  - Tự động tra cứu số hiệu van/kênh (`channel_number`) tương ứng với loại hóa chất và máy nhuộm đích trước khi kích hoạt.
- **Quan hệ:** Nhận công thức định mức từ M02/M03, tương tác với hệ thống tự động pha màu thô.
- **Phần đã xác minh:** Đã làm rõ lý do `tblRECORD_chem` legacy bị trống (không cân tay) và tìm thấy bảng cấu hình kênh van `tbl_status` trong `chem_order.accdb`.
- **Phần cần xác nhận:** Cơ chế giao tiếp vật lý cụ thể giữa database Postgres của web với ứng dụng điều khiển pha màu của Copower (đọc bảng mapping hay gọi API).


---

## 6. M06 - Semi Checker (Kiểm tra Bán thành phẩm)
- **Mục tiêu:** Kiểm tra và xác nhận chất lượng bán thành phẩm của mẻ nhuộm trước khi chuyển công đoạn hoặc gửi máy.
- **Actor:** Operator, QA/QC.
- **Đầu vào:** Dữ liệu quét tem QR mẻ bán thành phẩm, kết quả đo/thử nghiệm ngoại quan hoặc thông số kỹ thuật.
- **Đầu ra:** Trạng thái QC PASS hoặc QC FAIL cho lô bán thành phẩm.
- **Trạng thái:** WAITING_CHECK -> PASSED -> FAILED.
- **Quy tắc nghiệp vụ:** Chỉ các lô bán thành phẩm đạt trạng thái QC PASSED mới được xếp vào hàng chờ gửi máy (M07).
- **Quan hệ:** Sử dụng dữ liệu mẻ cân từ M04/M05, cấp trạng thái hợp lệ cho M07.
- **Phần đã xác minh:** Workbook `SEMI CHECKER.xlsm` có UserForm thực hiện đọc/kiểm tra.
- **Phần cần xác nhận:** Quy chuẩn các chỉ tiêu kiểm tra ngoại quan cụ thể cho bán thành phẩm.

---

## 7. M07 - Machine Dispatch (Điều phối Lệnh đến Máy)
- **Mục tiêu:** Lập lịch, điều phối và nạp lệnh sản xuất xuống máy nhuộm cụ thể.
- **Actor:** Dispatcher, Shift Leader.
- **Đầu vào:** Chọn lô bán thành phẩm sẵn sàng, chọn máy nhuộm đích.
- **Đầu ra:** Lệnh gửi thành công (SENT), ghi nhận nhật ký gửi (`sent_logs`).
- **Trạng thái:** READY -> claim -> SENDING -> SENT / ERROR.
- **Quy tắc nghiệp vụ:** Việc duyệt gửi máy phải thực hiện trong một Transaction nguyên tử (Atomic Transaction) gồm: trừ hàng chờ, gửi lệnh qua Agent và ghi Sent Log. Nếu một bước lỗi phải Rollback toàn bộ.
- **Quan hệ:** Đọc hàng chờ (M08), gọi Local Agent để thực hiện gửi lệnh ngầm sang máy nhuộm.
- **Phần đã xác minh:** Logic luồng di chuyển lệnh trong các bảng waiting, tosend, sentlog của Access.
- **Phần cần xác nhận:** Phương thức nạp lệnh thực tế vào máy nhuộm của VBA là gì (WinAPI giả lập click chuột, nạp file nhị phân vào thư mục chia sẻ, hay giao thức TCP/IP)?

---

## 8. M08 - Waiting Queue & Logic Locks (Hàng chờ & Khóa logic)
- **Mục tiêu:** Quản lý hàng chờ điều phối tập trung và tránh xung đột khi nhiều người vận hành cùng tương tác trên một lô.
- **Actor:** System (Tự động), Dispatcher.
- **Đầu vào:** Danh sách mẻ đã QC Pass sẵn sàng gửi máy.
- **Đầu ra:** Cơ chế khóa logic thành công.
- **Trạng thái:** UNLOCKED -> LOCKED.
- **Quy tắc nghiệp vụ:**
  - Khóa logic (Logic Lock) phải có thông tin: `locked_by` (người khóa), `locked_at` (thời điểm khóa), `expires_at` (hết hạn, ví dụ sau 5 phút).
  - Trình duyệt tự động giải phóng khóa khi hết hạn. Dispatcher có quyền mở khóa thủ công (Force Unlock) nhưng hành động này phải ghi Audit Log kèm lý do.
- **Quan hệ:** Hỗ trợ trực tiếp cho M07 để bảo đảm an toàn đồng thời.
- **Phần đã xác minh:** workbook `MACHINE_ID_LOCKED.xlsm` và `C3 grid load row lock id FB -.xlsm` dùng để hiển thị lưới khóa.
- **Phần cần xác nhận:** Thời gian hết hạn khóa mặc định phù hợp cho vận hành nhà xưởng là bao nhiêu.

---

## 9. M09 - Print Labels & QR (In tem và QR Code)
- **Mục tiêu:** Sinh dữ liệu QR Code chứa thông tin sản xuất thô và in tem dán lên thùng/lô liệu thông qua máy in TSC (TE200 hoặc các model khác).
- **Actor:** Operator, Agent (Tự động).
- **Đầu vào:** Dữ liệu mẻ cân thô, mã hàng, mã màu, cấu hình kích thước tem in động từ hệ thống Web.
- **Đầu ra:** Tem in vật lý chứa QR Code từ máy in TSC kết nối USB cục bộ hoặc qua mạng LAN.
- **Trạng thái:** QUEUED -> SENDING -> PRINTED / FAILED.
- **Quy tắc nghiệp vụ:**
  - QR Code phải được sinh nội bộ bằng thư viện server (e.g. PHP QR Code hoặc thư viện tương đương của NestJS), tuyệt đối không gọi API ngoài mạng LAN.
  - **Kích thước nhãn tem in (Label Size - width, height, gap) được cấu hình động trên Web Admin.** Lệnh in TSPL gửi xuống Agent được tạo động theo kích thước cấu hình này.
  - In lại tem (Reprint) bắt buộc phải chọn/nhập lý do in lại và ghi Audit Log.
- **Quan hệ:** Được gọi bởi M04/M05 khi hoàn tất cân, hoặc gọi độc lập qua giao diện quản trị.
- **Phần đã xác minh:** Logic cấu trúc tem in của máy TSC trong VBA, nội dung QR thô. Đã thống nhất cho phép cấu hình kích thước nhãn linh hoạt.


---

## 10. M10 - Troubleshooting (Hệ Chẩn đoán Sự cố)
- **Mục tiêu:** Số hóa kho tri thức chẩn đoán sự cố lỗi nhuộm sợi/vải và cung cấp bộ suy luận tự động gợi ý nguyên nhân và giải pháp.
- **Actor:** Troubleshooting Engineer, Operator.
- **Đầu vào:** Khai báo một Case sự cố (Mã lô nhuộm, lỗi gặp phải, điểm đánh giá các hiện tượng).
- **Đầu ra:** Bảng xếp hạng các nguyên nhân tiềm ẩn kèm bằng chứng suy luận và các hành động đề xuất khắc phục.
- **Trạng thái:** OPEN -> ANALYZING -> RECTIFYING -> CLOSED.
- **Quy tắc nghiệp vụ:**
  - Logic tính toán điểm số nguyên nhân dựa trên quan hệ trọng số giữa Vấn đề (Problem) - Nguyên nhân (Cause) phải khớp chính xác với thuật toán suy luận `modInferenceEngine` trong VBA.
  - Chỉ kỹ sư có quyền mới được cập nhật kho tri thức (Knowledge Base).
- **Quan hệ:** Liên kết với M01 (Master data máy, màu), M03 (Batch lịch sử).
- **Phần đã xác minh:** Thuật toán tính điểm và các bảng tri thức lỗi nhuộm từ hai workbook `troubleshooting_support engine`.
- **Phần cần xác nhận:** Quy trình lấy feedback ngược từ thực tế (khi kết luận nguyên nhân thật) để tự động hiệu chỉnh trọng số KB (Learning loop).

---

## 11. M11 - Reports & Dashboards (Báo cáo)
- **Mục tiêu:** Cung cấp thông tin tổng hợp cho quản lý nhà máy về hiệu suất, tiêu hao và lỗi.
- **Actor:** Production Manager, QA/QC, Technologist.
- **Đầu vào:** Dữ liệu giao dịch từ PostgreSQL.
- **Đầu ra:** Biểu đồ trực quan và báo cáo xuất Excel/PDF.
- **Các báo cáo chính:**
  1. Báo cáo tiêu hao thuốc nhuộm thực tế vs định mức.
  2. Báo cáo sai lệch cân bột màu (phát hiện operator cân ẩu hoặc lệch cân).
  3. Báo cáo sản lượng máy nhuộm theo ngày/tháng/ca.
  4. Báo cáo tỷ lệ gửi lỗi, hàng chờ gửi bị trễ.
  5. Thống kê lỗi sự cố và nguyên nhân hàng đầu (Troubleshooting Pareto).
- **Quan hệ:** Đọc dữ liệu từ toàn bộ các phân hệ giao dịch.
- **Phần đã xác minh:** Các nhu cầu báo cáo mô tả sơ bộ trong Word phân tích.
- **Phần cần xác nhận:** Các biểu mẫu báo cáo tiêu chuẩn hiện có của nhà máy.

---

## 12. M12 - Users, RBAC & Audit Log (Người dùng và Phân quyền)
- **Mục tiêu:** Quản lý tài khoản, nhóm vai trò, cấp quyền truy cập và lưu vết nhật ký hoạt động bất biến.
- **Actor:** System Admin, Auditor.
- **Đầu vào:** Tạo tài khoản, cấu hình phân quyền vai trò (RBAC), nhật ký hành động hệ thống.
- **Đầu ra:** Hệ thống bảo mật, báo cáo kiểm toán (Audit report).
- **Quy tắc nghiệp vụ:** 
  - Mật khẩu phải được băm an toàn (BCrypt/Argon2).
  - Không lưu Connection String hay Secret Key vào mã nguồn.
  - Audit log là bất biến (chỉ ghi chèn - Append only, không cho phép cập nhật hay xóa).
- **Quan hệ:** Bảo vệ bảo mật cho toàn bộ các phân hệ API của hệ thống.
- **Phần đã xác minh:** Đề xuất phân quyền 7 vai trò trong báo cáo Word.
- **Phần cần xác nhận:** Có tích hợp hệ thống xác thực tập trung (Active Directory/LDAP) của nhà máy hay không.

---

## 13. M13 - Realtime Dashboard & Alerting Rules (Dashboard Giám sát Realtime & Cảnh báo)
- **Mục tiêu:** Giám sát tiến độ sản xuất thời gian thực, tự động phát hiện và cảnh báo các sự cố lệch dung sai, trễ hạn SLA hoặc thiết bị mất kết nối.
- **Actor:** Dispatcher, Operator, Shift Leader, Plant Manager.
- **Đầu vào:** Trạng thái lô hàng từ PostgreSQL, dữ liệu cân thô từ Cache, tình trạng nhịp tim của Agent, bộ quy tắc cấu hình cảnh báo.
- **Đầu ra:** Giao diện điều khiển hợp nhất 5 màn hình giám sát, thông báo cảnh báo tức thời, biểu đồ KPIs quản lý ca, timeline chi tiết mẻ nhuộm.
- **Trạng thái Cảnh báo:** OPEN -> ACKNOWLEDGED -> RESOLVED.
- **Quy tắc nghiệp vụ:**
  - Quy tắc chẩn đoán lỗi trễ hạn được quản lý động qua database (`app.alert_rules`) với các mã sự kiện: `WEIGH_START_DELAY`, `WEIGH_COMP_DELAY`, `OUT_OF_TOLERANCE`, `TRANS_SLA_BREACH`, `SCALE_AGENT_OFFLINE`.
  - Cảnh báo mới sinh ra phải kích hoạt sự kiện đẩy về client qua SSE Stream và hiển thị âm thanh/banner đỏ.
  - Khi đóng cảnh báo (Resolve), nhân viên bắt buộc phải nhập biện pháp khắc phục thực tế.
- **Quan hệ:** Tương tác với Cân (M04), Máy nhuộm (M07), Hàng chờ (M08), In ấn (M09), và Nhật ký (M12).

