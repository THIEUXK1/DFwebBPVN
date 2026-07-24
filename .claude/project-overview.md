# Project Overview - Tổng quan Dự án DF

## 1. Bối cảnh Hệ thống Hiện tại
Hệ thống quản lý sản xuất hiện tại của nhà máy nhuộm DF được xây dựng trên bộ công cụ phân tán bao gồm:
- **12 workbook Excel (.xlsm):** Chứa giao diện tương tác, logic tính toán công thức sản xuất, quy tắc tra hệ số kỹ thuật, và giao chuyên gia hỗ trợ sự cố. Trong đó có 10 workbook sử dụng Macro VBA rất phức tạp.
- **2 Cơ sở dữ liệu Microsoft Access (.accdb):**
  - `RECORD.accdb` (nằm ở ổ mạng `Z:\DF\DATA\`): Quản lý hàng chờ, phân phối lệnh và điều phối máy.
  - `RECORD(1).accdb` (hay `RECORD.accdb` ở `Z:\DF_SCALE\`): Ghi nhận chi tiết kết quả cân thuốc nhuộm và hóa chất.
- **Tệp văn bản cục bộ:** `D:\SCALE\putty_log.txt` dùng làm file đệm trung gian để VBA đọc số cân từ cổng Serial.
- **Máy in tem TSC:** In tem chứa QR công đoạn dán lên thùng liệu cân.

## 2. Các Vấn đề của Giải pháp Excel VBA + Access
- **Phụ thuộc phần cứng và môi trường cục bộ:** Đường dẫn tệp tin, cổng COM cân, tên máy in TSC đều bị ghi cứng (hardcode) trong code VBA. Việc nâng cấp máy tính trạm hoặc cấu hình lại mạng sẽ làm treo toàn bộ hệ thống.
- **Dữ liệu phân tán và rủi ro mất mát:** Dữ liệu phân mảnh trên nhiều máy trạm và file Access qua ổ mạng chia sẻ. Không có cơ chế sao lưu (backup) tập trung, dễ dẫn đến lỗi hỏng file (như lỗi trang dữ liệu overflow phát hiện ở bảng `tbl_SentLog`).
- **Tranh chấp đồng thời (Concurrency):** Cơ chế khóa dòng (row locking) trong Access rất yếu, dễ bị treo cứng hoặc xung đột khi nhiều nhân viên vận hành cùng xác nhận lệnh hoặc cân cùng lúc.
- **Tích hợp phần cứng thiếu độ tin cậy:** VBA sử dụng WinAPI để mô phỏng nhấn chuột (mouse click) ngầm và gửi phím (SendKeys) vào ứng dụng Windows khác để giao tiếp thiết bị. Thao tác này rất dễ lỗi khi thay đổi độ phân giải màn hình hoặc khi cửa sổ bị mất focus.
- **Bảo mật và Kiểm vết kém:** Mã VBA gọi dịch vụ internet công cộng (`api.qrserver.com`) để sinh ảnh QR, gây rò rỉ thông tin sản xuất ra ngoài mạng nội bộ và phụ thuộc vào kết nối Internet. Không có nhật ký (Audit Log) đáng tin cậy để ghi nhận ai đã phê duyệt công thức, in lại tem hoặc override dung sai cân.

## 3. Mục tiêu Chuyển đổi (TO-BE)
- **Hệ thống Cầu nối MES - Nhuộm tự động:** Ứng dụng Web này đóng vai trò là **cầu nối trung gian (Connector/Bridge)** kết nối hệ thống quản lý sản xuất **MES** (nơi lưu giữ thông tin màu sắc và sản phẩm gốc) với **Hệ thống Nhuộm tự động & Pha màu tự động** (cho hệ thống nhuộm đai). Hiện tại hai hệ thống này đang hoàn toàn bị cô lập và không có cơ chế giao tiếp.
- **Tập trung hóa dữ liệu:** Di trú toàn bộ dữ liệu lịch sử Access vào PostgreSQL 15+ tập trung, thiết lập cơ chế sao lưu tự động và an toàn.
- **Web hóa quy trình:** Thay thế các workbook Excel bằng ứng dụng Web/PWA hiện đại, giao diện trực quan, nút nhấn lớn tối ưu hóa cho môi trường nhà xưởng và máy tính bảng.
- **Tách biệt kiến trúc thiết bị:** Sử dụng mô hình Backend API kết nối với một ứng dụng Agent cục bộ (Local Device Agent) để đọc cân và in tem. Đảm bảo tính ổn định và khả năng hoạt động offline tạm thời khi mạng chập chờn.
- **Sinh QR nội bộ:** Tự động tạo mã QR trực tiếp trong hệ thống web nội bộ, nâng cao tính bảo mật và tốc độ xử lý.
- **Kiểm soát chặt chẽ:** Ghi nhật ký audit log bất biến cho mọi hành động nhạy cảm (duyệt công thức, in lại tem, ghi nhận dung sai cân lệch).

## 4. Phạm vi Dự án (MVP)
- **Tích hợp MES:** Tiếp nhận thông tin mã màu, mã hàng từ hệ thống MES làm dữ liệu đầu vào chuẩn.
- **Di trú dữ liệu:** Import staging nguyên trạng và chuẩn hóa dữ liệu từ hai database Access (`RECORD.accdb`, `RECORD(1).accdb`) và database bổ sung `chem_order.accdb` (chứa bảng cấu hình kênh hóa chất `tbl_status`).
- **Phân hệ Danh mục:** Quản lý máy nhuộm, bồn/rack, mã hàng, mã màu, hóa chất, phụ gia, máy in và cân trạm.
- **Phân hệ Công thức & Công nghệ:** Số hóa các quy tắc tra hệ số (TraHeSo), tính mực nước, lực căng, định lượng hóa chất theo các phiên bản có phê duyệt.
- **Phân hệ Lệnh & Điều phối:** Quản lý hàng chờ sản xuất, cơ chế khóa logic tránh xử lý trùng, gửi lệnh sang máy nhuộm.
- **Phân hệ Cân sản xuất:** Thu nhận số cân thời gian thực từ trạm cân qua Agent, validation dung sai, phê duyệt override và lưu vết session cân.
- **Phân hệ In tem & QR:** Tự động sinh QR sản xuất và in trực tiếp ra máy TSC (TE200 hoặc các model khác kết nối cổng USB máy trạm hoặc kết nối qua mạng LAN). **Cho phép điều chỉnh kích thước nhãn tem in (Label Size) động trực tiếp trên hệ thống Web.**
- **Phân hệ Troubleshooting:** Chuyển đổi bộ tri thức xử lý sự cố, engine suy luận tính điểm nguyên nhân sang dịch vụ web.
- **Phân hệ Báo cáo:** Năng suất máy, tiêu hao nguyên liệu, sai lệch dung sai cân và log sự cố.
- **Phân hệ Quản trị:** RBAC (Phân quyền theo vai trò) và Audit Log chi tiết.


## 5. Ngoài Phạm vi Giai đoạn 1 (Out of Scope)
- Điều khiển trực tiếp PLC/SCADA của máy nhuộm nếu Macro hiện tại chỉ làm nhiệm vụ xuất tệp tin hoặc gửi dữ liệu qua ứng dụng trung gian.
- Tự động thay đổi quy trình sản xuất vật lý của nhà máy mà chưa có sự đồng ý bằng văn bản của ban giám đốc.
- Xóa bỏ hoặc tắt các Macro Excel cũ trước khi giai đoạn chạy song song đạt tiêu chuẩn nghiệm thu và có biên bản chính thức.
