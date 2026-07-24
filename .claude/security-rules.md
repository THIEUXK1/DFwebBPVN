# Security Rules - Các Quy tắc Bảo mật

Tài liệu này đặc tả các yêu cầu bảo mật, chính sách phân quyền và cơ chế bảo vệ an toàn thông tin cho hệ thống DF.

---

## 1. Xác thực và Phân quyền (Authentication & RBAC)
- **Xác thực:** Sử dụng cơ chế token JWT (JSON Web Token) ngắn hạn cho các yêu cầu API từ Client, hoặc Session Cookie an toàn.
- **Băm mật khẩu:** 100% mật khẩu người dùng phải được băm bằng thuật toán `BCrypt` hoặc `Argon2ID` trước khi lưu vào cơ sở dữ liệu. Tuyệt đối không lưu mật khẩu dạng plain text hoặc mã hóa hai chiều.
- **Vai trò tối thiểu (Role-Based Access Control - RBAC):** Hệ thống phân quyền chặt chẽ theo vai trò của người dùng:
  - **Operator (Vận hành viên):** Xem danh sách lệnh sản xuất được chỉ định, thực hiện cân bột màu/hóa chất trong ca, xác nhận cân đạt chuẩn, in nhãn nhả lần đầu. Không có quyền duyệt công thức hoặc override dung sai lệch.
  - **Shift Leader (Trưởng ca):** Quyền của Operator cộng thêm: duyệt override dung sai mẻ cân lệch, thực hiện in lại tem (reprint) và giải phóng khóa logic trạm điều phối thủ công.
  - **Technologist (Kỹ sư Công nghệ):** Tạo, chỉnh sửa bản nháp công thức, tra cứu hệ số quy trình, cập nhật kho tri thức sự cố (Troubleshooting KB).
  - **Approver/QA (Phê duyệt/Đảm bảo chất lượng):** Duyệt phát hành các phiên bản công thức sản xuất, phê duyệt các mẻ cân lỗi nghiêm trọng được override bởi Shift Leader.
  - **Dispatcher (Điều phối viên):** Quản lý hàng chờ điều phối, gán máy nhuộm, kích hoạt gửi máy và xử lý lỗi nạp lệnh.
  - **Auditor (Giám sát viên):** Quyền chỉ đọc (Read-only) toàn bộ báo cáo, nhật ký cân, lịch sử gửi máy và audit log kiểm toán.
  - **System Admin (Quản trị hệ thống):** Quản lý tài khoản người dùng, cấu hình phần cứng Agent, máy in, trạm cân trạm. Không được cấp quyền duyệt công thức kỹ thuật hoặc thay đổi công thức nếu không có vai trò bổ sung.

---

## 2. Bảo vệ API và Tích hợp Thiết bị (Agent Security)
- **API Key cho Agent:** Mỗi Local Device Agent chạy tại máy trạm phải sử dụng một API Key/Token độc lập được mã hóa để xác thực với Backend API.
- **Đăng ký Thiết bị (Device Registration):** 
  - Mọi trạm cân và máy in TSC cục bộ khi kết nối hệ thống phải thực hiện quy trình đăng ký (Workstation Registration) thông qua Agent.
  - Admin phải phê duyệt thiết bị trên trang quản trị Web và gán vào trạm làm việc cụ thể trước khi Agent trạm đó có quyền gửi dữ liệu số cân lên API.
- **Bảo vệ SSE (Server-Sent Events):** Do trình duyệt không hỗ trợ custom headers trực tiếp trong đối tượng `EventSource`, token xác thực Sanctum được truyền qua tham số query `?token=...`. Máy chủ Backend phải giải mã, hash-check khớp với token trong bảng `personal_access_tokens` trước khi cho phép thiết lập kết nối stream dài hạn.
- **Xác thực Phê duyệt Cảnh báo:** Thao tác "Nhận xử lý" (Acknowledge) và "Đóng cảnh báo" (Resolve) bắt buộc phải có tài khoản đăng nhập có vai trò Shift Leader trở lên. Khi đóng cảnh báo, hệ thống bắt buộc kiểm tra độ dài và làm sạch (sanitization) văn bản nhập mô tả biện pháp khắc phục sự cố.

---

## 3. An toàn Cơ sở Dữ liệu & Ứng dụng
- **Chống SQL Injection:** 100% các câu lệnh SQL truy vấn cơ sở dữ liệu phải được thực thi thông qua ORM (Eloquent/Prisma) hoặc sử dụng Parameterized Queries (nạp tham số an toàn). Tuyệt đối cấm viết SQL nối chuỗi trực tiếp từ input đầu vào của người dùng.
- **Bảo vệ Secrets:**
  - Không bao giờ được đưa chuỗi kết nối Database (Connection String), API key, mật khẩu, hoặc token JWT bí mật vào mã nguồn của dự án (Git repository).
  - Sử dụng tệp tin cấu hình môi trường `.env` hoặc hệ thống quản lý bí mật cục bộ trên máy chủ. Tệp tin `.env` phải được khai báo trong `.gitignore`.
- **Validation & Sanitization:** 
  - Thực hiện kiểm tra XSS và làm sạch (sanitize) toàn bộ dữ liệu đầu vào dạng văn bản nhận được từ người dùng để tránh mã độc chèn vào giao diện.
  - Bắt buộc kiểm tra độ dài, kiểu dữ liệu và giới hạn miền của số cân (chống tràn số decimal).
- **QR Code An toàn:** Nhãn QR in ra dán lên thùng bột màu chỉ chứa mã định danh mẻ cân/mã lô nội bộ và các thông tin tối thiểu cần thiết cho sản xuất. Tuyệt đối không nhúng các dữ liệu nhạy cảm của hệ thống như mật khẩu hay API token vào chuỗi QR.

---

## 4. Lịch ký Kiểm toán Bất biến (Immutable Audit Trail)
- Bảng nhật ký kiểm toán `app.audit_logs` được thiết kế theo cơ chế **chỉ ghi chèn** (Append-only).
- Backend API không cung cấp bất kỳ API endpoint nào có quyền sửa (UPDATE) hoặc xóa (DELETE) các bản ghi trong bảng `app.audit_logs`.
- Quyền ghi vào bảng này chỉ được cấp duy nhất cho ứng dụng Backend thông qua các triggers tự động hoặc Repository nội bộ của server.

---

## 5. Quy tắc Bảo mật Máy trạm (Workstation Security)
- **Cấm định danh bằng IP:** Tuyệt đối không dùng IP làm định danh duy nhất hay khóa chính của máy trạm. IP chỉ dùng cho kiểm tra mạng, ghi nhận lịch sử kết nối (`workstation_network_history`) và audit trail.
- **Xác thực dựa trên Token & Fingerprint:** Trạm phải tự nhận diện thông qua một mã định danh bất biến (Workstation UUID), registration token bí mật và vân tay thiết bị (Device Fingerprint) thu thập bởi Local Agent.
- **Khóa cứng giao diện (Frontend Kiosk Lock):** Các trạm kiosk vận hành đơn chức năng (`locked_to_type = true`) phải ẩn hoàn toàn sidebar điều hướng và bị chặn chuyển đổi route.
- **Kiểm soát API ở Backend (Backend Workstation Guard):** Backend bắt buộc kiểm tra chéo `workstation_type` của trạm gửi request cùng với `allowed_actions` và vai trò người dùng (`roles`). Một tài khoản hợp lệ đăng nhập sai trạm hoặc trạm không có `allowed_action` tương ứng sẽ bị từ chối truy cập (HTTP 403 Forbidden).

