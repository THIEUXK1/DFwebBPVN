# Architectural Decisions - Các Quyết định Kiến trúc (ADR)

Tài liệu này ghi nhận các Quyết định Kiến trúc (ADR - Architectural Decision Records) chính của dự án DF, làm định hướng cho việc triển khai mã nguồn và cơ sở hạ tầng.

---

## ADR-001: Sử dụng PostgreSQL 15+ thay thế Microsoft Access
- **Bối cảnh:** Cơ sở dữ liệu Microsoft Access cũ lưu dưới dạng tệp tin phân tán qua ổ đĩa mạng chia sẻ (Z:\), có độ tin cậy thấp, dễ bị hỏng (như bảng `tbl_SentLog` bị lỗi overflow page), và cơ chế khóa dòng đồng thời yếu gây treo hệ thống khi nhiều máy cùng truy cập.
- **Quyết định:** Chuyển đổi toàn bộ dữ liệu lịch sử và giao dịch mới sang hệ quản trị cơ sở dữ liệu quan hệ tập trung PostgreSQL 15+.
- **Lý do:** PostgreSQL có độ tin cậy rất cao, hỗ trợ transaction mạnh mẽ, hỗ trợ lưu trữ kiểu JSONB để kiểm toán dữ liệu và xử lý tranh chấp đồng thời cực tốt thông qua Pessimistic/Optimistic Locking.
- **Hệ quả:** Hệ thống yêu cầu máy chủ cơ sở dữ liệu chạy ổn định và hạ tầng mạng LAN thông suốt từ các máy trạm đến máy chủ. Cần thiết lập cơ chế tự động backup cơ sở dữ liệu hàng ngày.

---

## ADR-002: Xây dựng Local Device Agent để tích hợp Cân và Máy in TSC
- **Bối cảnh:** 
  - Trình duyệt Web (Chrome, Firefox, Edge) vì lý do bảo mật không được phép truy cập trực tiếp vào phần cứng cổng COM (Serial Port) của cân hoặc tự động gửi lệnh in thô (RAW spooling) ra máy in nhãn TSC mà không hiển thị hộp thoại xác nhận của hệ điều hành.
  - Ứng dụng VBA cũ tích hợp thiết bị bằng cách gọi WinAPI và đọc file log Putty cục bộ (`putty_log.txt`) rất thiếu ổn định.
- **Quyết định:** Xây dựng một ứng dụng Local Device Agent (chạy ngầm dạng Windows Service viết bằng .NET Core hoặc Go/Rust) cài đặt trực tiếp tại các máy trạm có kết nối cân và máy in.
- **Lý do:** Agent chạy độc lập với trình duyệt, có toàn quyền giao tiếp API của hệ điều hành Windows để đọc serial port ổn định và điều khiển máy in TSC qua lệnh in thô (TSPL/ZPL). Agent giao tiếp với Backend API qua WebSocket/HTTPS để đẩy dữ liệu cân thời gian thực và nhận job in.
- **Hệ quả:** Lập trình viên Backend cần thiết lập API đăng ký thiết bị (Device Registration) và API nhận/spool công việc bất đồng bộ. Phải quản lý cài đặt thêm 1 ứng dụng Agent tại các máy trạm.

---

## ADR-003: Tự sinh mã QR nội bộ (Internal QR Code Generation)
- **Bối cảnh:** Hệ thống Excel cũ gửi dữ liệu thô sang dịch vụ bên ngoài (`api.qrserver.com`) để sinh ảnh QR. Việc này gây rò rỉ công thức nhuộm (dữ liệu độc quyền của nhà máy) ra internet và làm hệ thống tê liệt nếu mất mạng Internet, mặc dù máy in và máy cân đều nằm trong mạng nội bộ LAN.
- **Quyết định:** Tự động sinh mã QR trực tiếp trên Backend API hoặc Web Frontend sử dụng các thư viện mã nguồn mở nội bộ (e.g. `php-qrcode` hoặc thư viện Node.js).
- **Lý do:** Đảm bảo dữ liệu công nghệ 100% nằm trong mạng nội bộ của nhà máy nhuộm, không phụ thuộc vào kết nối Internet để in tem.
- **Hệ quả:** Backend API chịu thêm một phần tải nhỏ khi render ảnh QR, tuy nhiên không đáng kể so với hiệu quả bảo mật mang lại.

---

## ADR-004: Sử dụng UUID làm khóa chính ứng dụng và giữ trường Traceability
- **Bối cảnh:** Dữ liệu di trú từ Access có chất lượng khóa chính (ID) rất kém: ID chứa số nguyên âm/dương ngẫu nhiên, nhiều bản ghi trong bảng hàng chờ có ID trùng nhau hoặc bị rỗng hoàn toàn, không thể dùng làm khóa ngoại (Foreign Key) đáng tin cậy.
- **Quyết định:** 
  - Toàn bộ các bảng trong schema ứng dụng (`app`) sử dụng `UUID` sinh ngẫu nhiên làm khóa chính kỹ thuật (`id uuid PRIMARY KEY DEFAULT gen_random_uuid()`).
  - Lưu trữ bổ sung các trường truy vết nguồn gốc bao gồm: `legacy_source` (tên bảng thô), `legacy_id` (ID Access cũ nếu có) và `legacy_row_no` (số dòng vật lý của Access nguồn).
- **Lý do:** UUID đảm bảo tính duy nhất tuyệt đối trên toàn hệ thống và độc lập với dữ liệu cũ. Các trường truy vết đảm bảo có thể chạy lại script di trú nhiều lần (Idempotent) mà không bị xung đột khóa, đồng thời cho phép đối soát chéo số lượng bản ghi giữa Access và PostgreSQL dễ dàng.
- **Hệ quả:** Dung lượng lưu trữ khóa ngoại tăng lên một phần nhỏ so với kiểu số nguyên `integer`, nhưng hoàn toàn chấp nhận được với năng lực của PostgreSQL hiện nay.

---

## ADR-005: Sử dụng Staging Schema trong quá trình Migration
- **Bối cảnh:** Chuyển đổi trực tiếp dữ liệu thô từ Access sang các bảng chuẩn hóa của ứng dụng web là rất phức tạp và dễ phát sinh lỗi do kiểu dữ liệu không khớp và lỗi lệch cột dữ liệu.
- **Quyết định:** Import dữ liệu Access nguyên trạng vào hai schema đệm độc lập (`legacy_df_data` và `legacy_df_scale`) trong PostgreSQL trước, sau đó mới thực thi các script SQL chuyển đổi dữ liệu (`03_transform_legacy_to_target.sql`) để đưa dữ liệu vào schema ứng dụng (`app`).
- **Lý do:** Tách biệt giai đoạn nạp dữ liệu và giai đoạn chuẩn hóa giúp lập trình viên có thể kiểm tra dữ liệu thô tại chỗ, dễ dàng tinh chỉnh logic transform và chạy lại nhiều lần (idempotent) mà không phải import lại từ tệp nguồn Access ban đầu.
- **Hệ quả:** Dung lượng database ban đầu sẽ tăng gấp đôi do chứa cả dữ liệu thô và dữ liệu chuẩn hóa, tuy nhiên staging schema sẽ được chuyển sang chế độ Read-Only và lưu trữ vĩnh viễn làm bằng chứng đối soát lịch sử.
---

## ADR-006: Định vị Ứng dụng Web làm Cầu nối trung gian giữa MES và Hệ thống Nhuộm/Pha màu tự động
- **Bối cảnh:** Dữ liệu chuẩn về màu sắc, mã hàng, đơn hàng sản xuất nằm trên hệ thống MES của nhà máy nhuộm. Tuy nhiên, MES chưa có kết nối trực tiếp với hệ thống máy nhuộm đai và máy pha màu tự động.
- **Quyết định:** Thiết kế hệ thống Web mới đóng vai trò là lớp cầu nối trung gian (Middleware Bridge). Web sẽ nhập/đồng bộ dữ liệu từ MES và chuyển tiếp cấu hình, lệnh điều phối, thông số màu sắc vào PostgreSQL - nơi hệ thống máy nhuộm và pha màu tự động (Dosing/Dyeing system) có thể đọc và đồng bộ thông qua các bảng lệnh (như `tbl_status`, `tbl_tosend`).
- **Lý do:** Tránh nhập liệu thủ công hai lần, đảm bảo tính nhất quán của công thức nhuộm từ thiết kế đến thực thi vật lý, và tự động hóa luồng dữ liệu sản xuất khép kín.
- **Hệ quả:** Hệ thống Backend cần xây dựng các API/Service để tiếp nhận dữ liệu từ MES (qua API, Webhook hoặc chia sẻ DB) và cập nhật trạng thái ngược lại cho MES khi mẻ nhuộm hoàn thành.

---

## ADR-007: Cấu hình Kích thước Nhãn in (Label Size) Động trên Hệ thống Web
- **Bối cảnh:** Nhà máy sử dụng nhiều dòng máy in nhãn (TSC TE200 kết nối USB máy trạm hoặc kết nối qua mạng LAN) với các kích thước giấy in/nhãn in khác nhau tùy theo đợt cấp hoặc chủng loại sản phẩm. Việc ghi cứng (hardcode) kích thước giấy trong Local Agent sẽ làm giảm tính linh hoạt và mất thời gian sửa code khi đổi giấy.
- **Quyết định:** Lưu trữ cấu hình kích thước nhãn in (chiều rộng, chiều cao, khoảng cách - gap) động trên cơ sở dữ liệu và cho phép điều chỉnh trực tiếp trên giao diện Web Admin.
- **Lý do:** Backend API sẽ tự động tính toán tọa độ in, sinh mã lệnh in TSPL động dựa theo kích thước giấy đã cấu hình trước khi gửi Job in xuống Local Agent. Local Agent chỉ làm nhiệm vụ spool chuỗi lệnh in thô nhận được từ server mà không cần quan tâm đến logic bố cục.
- **Hệ quả:** Cho phép nhà máy dễ dàng thay đổi loại giấy nhãn in mà không cần cài đặt lại phần mềm Agent tại máy trạm.

---

## ADR-008: Lựa chọn Server-Sent Events (SSE) cho Kết nối Realtime
- **Bối cảnh:** Giao diện điều phối nhà máy cần cập nhật trạng thái gần thời gian thực (1-3s). WebSockets yêu cầu cài đặt và vận hành dịch vụ WebSocket Server riêng biệt (Pusher/Soketi/Node.js) và cấu hình proxy phức tạp, dễ bị tường lửa Windows chặn cổng.
- **Quyết định:** Sử dụng giao thức Server-Sent Events (SSE) qua cổng HTTP chuẩn (`text/event-stream`).
- **Lý do:** SSE là một phần của tiêu chuẩn HTML5, hoạt động trực tiếp trên máy chủ PHP/Laravel hiện có mà không cần cài đặt thêm phần mềm dịch vụ nào. Hỗ trợ tự động kết nối lại (reconnect) và gửi `Last-Event-ID` mặc định bởi trình duyệt.
- **Hệ quả:** SSE chỉ hỗ trợ truyền tải một chiều từ Server xuống Client. Đối với các hành động từ Client lên Server, chúng tôi sử dụng các cuộc gọi HTTP POST/PUT tiêu chuẩn. Điều này hoàn toàn đáp ứng tốt nhu cầu của hệ thống MES/Dyeing.

---

## ADR-009: Transactional Outbox Pattern cho Sự kiện Realtime
- **Bối cảnh:** Nếu phát trực tiếp sự kiện realtime từ các luồng Controller trước khi database hoàn tất commit, hệ thống có thể gửi thông tin sai lệch nếu transaction bị rollback (quay lui do lỗi ghi DB).
- **Quyết định:** Áp dụng mô hình Transactional Outbox. Mọi sự kiện nghiệp vụ được ghi trực tiếp vào bảng `app.realtime_events` (Outbox) trong cùng một database transaction hiện hành.
- **Lý do:** Đảm bảo tính nhất quán dữ liệu tuyệt đối (Atomicity). SSE Stream endpoint sẽ định kỳ đọc từ bảng này để đẩy về cho client.
- **Hệ quả:** Cơ sở dữ liệu sẽ gánh thêm một phần tải nhỏ khi ghi bản ghi sự kiện, nhưng được giải phóng ngay lập tức nhờ cơ chế rollback tự động khi lỗi và dọn dẹp định kỳ.

---

## ADR-010: Cơ chế Fallback Polling và Snapshot Sync
- **Bối cảnh:** Kết nối mạng LAN tại phân xưởng nhuộm thường chập chờn do nhiễu sóng hoặc hỏng hóc vật lý, gây gián đoạn kết nối SSE kéo dài.
- **Quyết định:** 
  - Khi mất kết nối SSE quá 10 giây, Frontend tự động chuyển sang chế độ Polling dự phòng (gọi API lấy snapshot mỗi 10 giây).
  - Khi kết nối mạng phục hồi, Frontend tự động ngắt polling, kết nối lại SSE với tham số `last_event_id` gần nhất và gọi Snapshot API đồng bộ toàn diện để bù đắp các event bị bỏ lỡ (reconciliation).
- **Lý do:** Đảm bảo Dashboard không bao giờ bị đóng băng dữ liệu hoặc sai lệch trạng thái khi mạng không ổn định.
- **Hệ quả:** Frontend cần quản lý trạng thái kết nối chặt chẽ và xử lý hủy các kết nối thừa để tránh rò rỉ bộ nhớ (memory leak).

---

## ADR-011: Cấu hình Quy tắc Cảnh báo động (Alert Rules Engine)
- **Bối cảnh:** Các ngưỡng thời gian chờ cân, SLA vận chuyển, sai số dung sai cân đo biến động linh hoạt theo từng loại đơn hàng hoặc thay đổi kế hoạch sản xuất.
- **Quyết định:** Lưu trữ toàn bộ ngưỡng và cấu hình quy tắc trong bảng `app.alert_rules` thay vì viết cứng (hardcode) trong mã nguồn PHP.
- **Lý do:** Giúp Quản lý hoặc Shift Leader dễ dàng điều chỉnh cấu hình ngưỡng cảnh báo trực tiếp trên giao diện Admin mà không cần thay đổi hay triển khai lại mã nguồn backend.
- **Hệ quả:** Backend chạy scheduled task/command định kỳ (hoặc trigger tự động) để đối chiếu thông số thực tế với cấu hình quy tắc và tự sinh cảnh báo.

