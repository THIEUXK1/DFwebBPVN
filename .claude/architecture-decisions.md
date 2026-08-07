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

## ADR-008: Lựa chọn Server-Sent Events (SSE) cho Kết nối Realtime — ĐÃ THAY THẾ, xem cập nhật 2026-07-30
- **Bối cảnh:** Giao diện điều phối nhà máy cần cập nhật trạng thái gần thời gian thực (1-3s). WebSockets yêu cầu cài đặt và vận hành dịch vụ WebSocket Server riêng biệt (Pusher/Soketi/Node.js) và cấu hình proxy phức tạp, dễ bị tường lửa Windows chặn cổng.
- **Quyết định (LỊCH SỬ, không còn đúng với code hiện tại):** Sử dụng giao thức Server-Sent Events (SSE) qua cổng HTTP chuẩn (`text/event-stream`).
- **Lý do:** SSE là một phần của tiêu chuẩn HTML5, hoạt động trực tiếp trên máy chủ PHP/Laravel hiện có mà không cần cài đặt thêm phần mềm dịch vụ nào. Hỗ trợ tự động kết nối lại (reconnect) và gửi `Last-Event-ID` mặc định bởi trình duyệt.
- **Hệ quả:** SSE chỉ hỗ trợ truyền tải một chiều từ Server xuống Client. Đối với các hành động từ Client lên Server, chúng tôi sử dụng các cuộc gọi HTTP POST/PUT tiêu chuẩn. Điều này hoàn toàn đáp ứng tốt nhu cầu của hệ thống MES/Dyeing.

### Cập nhật 2026-07-30 — Chuyển sang Laravel Reverb (WebSocket)
- **Phát hiện 2026-07-25:** Cài đặt SSE gốc (`/api/realtime/stream`, vòng lặp `while(true)` giữ 1 HTTP request sống mãi) gây treo toàn bộ server khi chạy bằng `php artisan serve` trên Windows — môi trường này không có concurrency thật (không `fork()`), nên chỉ cần 1 tab trình duyệt mở Dashboard là chiếm request-handling thread vĩnh viễn, mọi request khác (kể cả API khác) bị treo theo.
- **Quyết định thực tế đang chạy:** Thay bằng Laravel Reverb (`laravel/reverb`, WebSocket server tương thích giao thức Pusher, tự host — không phải dịch vụ SaaS bên thứ ba) — xem `app/Events/RealtimeEventBroadcast.php` (kênh `dashboard-events`, `ShouldBroadcastNow`), cấu hình `BROADCAST_CONNECTION=reverb` trong `.env`.
- **Hệ quả vận hành quan trọng:** Reverb là 1 tiến trình nền RIÊNG BIỆT, luôn phải chạy song song với `php artisan serve` (`php artisan reverb:start`) — nếu Reverb không chạy, mọi broadcast realtime lỗi "Pusher error: cURL error 7 ... port 8080" (đã gặp thực tế 2026-07-30) dù các API HTTP khác vẫn hoạt động bình thường. Cần thêm Reverb vào quy trình khởi động dev/production tương tự các tiến trình nền khác (không tự khởi động cùng `artisan serve`).
- **Chưa xác nhận lại:** ADR-009 (Transactional Outbox) và ADR-010 (Fallback Polling) mô tả cơ chế gắn với SSE stream cũ — cần rà soát riêng xem còn áp dụng nguyên vẹn với Reverb hay cũng cần cập nhật, chưa xác minh trong lần sửa này.

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


---

## ADR-012: Mô phỏng chuột (RPA) cho "SEND OVER 6" — đảo ngược DEPRECATED_CONFIRMED
- **Ngày:** 2026-08-03
- **Bối cảnh:** Trạm cân lớn (`/weighing-station-large`, port từ `5.Semiauto- lockmove SEND OVER6 - delta-stable-final-221.xlsm`) cần gửi mã rack sang **hệ pha màu**. Bản VBA gốc làm bằng `Mod_sendRackauto.FireRackBatch`: đặt clipboard rồi `ClickAt` vào 6 toạ độ MÀN HÌNH cố định + `SendKeys "^v"`, cuối cùng click nút xác nhận. Các đợt audit trước xếp toàn bộ cụm `ModAPI_mouse`/`Mod_clickAT`/`SendTextToApp` là **`DEPRECATED_CONFIRMED`**, với lập luận "kiến trúc Local Agent mới thay thế đúng bằng giao tiếp trực tiếp Serial Port + HTTP API, không cần giả lập chuột" (xem `vba-migration-matrix.md`, `menu-workstation-device-architecture.md`).
- **Vấn đề với quyết định cũ:** Lập luận đó giả định hệ pha màu **có API để tích hợp thật**. Chủ dự án xác nhận (2026-08-03) **hệ pha màu KHÔNG có API**. Vì vậy giữ nguyên DEPRECATED đồng nghĩa với việc chức năng đặc trưng nhất của workbook (nằm ngay trong tên file) vĩnh viễn không hoạt động — thực tế trước ADR này frontend gọi `POST /api/rack-dispatch` nhưng endpoint không tồn tại, thợ phải bấm COPY rồi dán tay.
- **Quyết định:** Tái lập kỹ thuật mô phỏng chuột + clipboard, nhưng **đặt trong Local Agent**, không phải trình duyệt:
  - Web chỉ ghi lệnh vào `app.rack_dispatch_commands` (`POST /api/rack-dispatch`).
  - Local Agent poll `GET /agents/{ws}/rack-commands`, thực thi bằng Win32 `SetCursorPos`/`mouse_event`/`keybd_event` + clipboard API thuần (`agent/RackSender.cs`), rồi ack về `POST .../ack`.
  - Toạ độ + mốc trễ nằm trong `appsettings.json` mục `Rack`, **không hard-code** (`coding-standards` mục 3). Mặc định = đúng bản VBA gốc.
- **Trình tự thao tác gốc (bắt buộc giữ nguyên cả thứ tự lẫn mốc trễ):**
  - `OUT`: chờ 150 → click (10,100) kích hoạt cửa sổ → chờ 220 → click (365,680) mở vùng nhập → chờ 200 → [6 lần: đặt clipboard, click ô tại X=345 Y={200,250,300,345,390,440}, Ctrl+V, chờ 200] → click (750,215) xác nhận → chờ 200.
  - `IN`: chờ 150 → click (10,100) → chờ 220 → click **(750,430)** → chờ 200.
  - `SendTextToApp` = SetClipboardText → ClickAt → Ctrl+V; `ClickAt` **không** có độ trễ xen giữa (mọi độ trễ nằm ở `SmartDelay`).
- **Hai chi tiết cực dễ làm sai (bản đầu của Agent này đã sai cả hai, phát hiện khi đọc được mã nguồn thật):**
  - `FireRackBatch` gửi **đủ 6 ô, kể cả ô rỗng** — dán chuỗi rỗng chính là để XOÁ giá trị còn sót của lô trước bên ứng dụng đích. Bỏ qua ô rỗng sẽ để lại rác của lô cũ.
  - Nút xác nhận của `IN` là **(750,430)**, KHÁC nút xác nhận của `OUT` (750,215).
- **Nguồn mã đối chiếu:** VBA project của workbook bị khoá mật khẩu nên không mở được qua Excel COM. Mã nguồn lấy được bằng cách giải nén trực tiếp `xl/vbaProject.bin` theo MS-OVBA (mật khẩu chỉ khoá giao diện VBE, không mã hoá module) — script tại scratchpad phiên 2026-08-03. Đã đối chiếu `scaleform.btn_Out_Click`/`btn_In_Click`, `Mod_sendRackauto`, `ModAPI_mouse`, `ModDelay_paste`.
- ~~**Không port:** `SetTopMost Me, False/True` — thao tác gỡ/bật always-on-top của chính UserForm VBA, Agent không có form nên không có gì để gỡ.~~ **ĐÃ ĐẢO NGƯỢC 07/08/2026 — xem mục cập nhật cuối ADR này.**
  - `Rack:Enabled` mặc định **false**: chỉ trạm được bật tường minh mới chiếm chuột.
- **Lý do vẫn tôn trọng ADR-002:** Trình duyệt tuyệt đối không chạm ứng dụng cục bộ; toàn bộ phần "bẩn" nằm trong Agent — đúng ranh giới phân lớp đã chốt.
- **Hệ quả / rủi ro phải chấp nhận:**
  - Đây là **RPA mù**: click theo toạ độ tuyệt đối, KHÔNG kiểm tra cửa sổ nào đang ở đó. Nếu ứng dụng pha màu không mở đúng vị trí/độ phân giải như lúc hiệu chỉnh, mã rack sẽ bị dán vào cửa sổ bất kỳ. Đã cân nhắc phương án tìm cửa sổ theo tiêu đề rồi click tương đối (an toàn hơn) nhưng chủ dự án chọn giữ đúng hành vi bản gốc.
  - Agent chiếm chuột thật của máy trong lúc bắn — thợ không được thao tác cùng lúc.
  - Bắt buộc `idempotency_key` UNIQUE (`database-safety` mục 4): Agent đồng bộ lại sau khi mất mạng mà bắn trùng nghĩa là **cấp thừa vật tư**.
  - Lệnh xử lý **tuần tự**, không song song — cả máy chỉ có một con chuột.
- **Chưa làm:** chưa hiệu chỉnh toạ độ trên máy trạm thật, chưa chạy thử đầu-cuối với hệ pha màu. `Rack:Enabled=false` cho tới khi hiệu chỉnh xong.

### Cập nhật 07/08/2026 (Agent 4.5.0.0) — PORT `SetTopMost Me, False/True`, bỏ "RPA mù"
- **Yêu cầu gốc:** *"xem có phương án nào để tôi có thể dùng in out giống 5.Semiauto- lockmove SEND OVER6... 100%"*.
- **Cái sai của quyết định cũ:** dòng "Không port `SetTopMost Me, False/True`" đúng về câu chữ (Agent không có UserForm) nhưng **sai về hệ quả**. `SetTopMost Me, False` trong bản gốc không chỉ gỡ form Excel — nó là bước **làm lộ cửa sổ ứng dụng pha màu ra mặt trước**, nhờ vậy `ClickAt 10, 100` ngay sau đó mới rơi đúng vào nó. Trên bản web, cái đang che ứng dụng pha màu là **trình duyệt chạy toàn màn hình (F11/kiosk)** của màn cân. Không gỡ ra thì cú click (10,100) trúng trình duyệt và cả 6 lần `Ctrl+V` đổ vào trang web — trong khi mọi lệnh Win32 (`SetCursorPos`, clipboard) đều trả về thành công nên Agent **ack DONE**. Đúng kiểu báo thành công giả mà chính ADR này cấm ở chỗ khác.
- **Quyết định mới:** Agent **phải xác định được cửa sổ đích trước khi bắn** — nhưng **tự dò, không bắt khai báo** (bản 4.5.0.0 bắt điền tiêu đề cửa sổ; 4.6.0.0 bỏ yêu cầu đó sau phản hồi *"sao lại cài đặt nhiều thế, không có cách nào bấm và nó hoạt động như bình thường à"*):
  - Chính bản VBA gốc **cũng không biết ứng dụng pha màu tên gì**: `SetTopMost Me, False` chỉ đẩy cái đang che xuống rồi click thẳng vào toạ độ, cái gì nằm ở đó thì nhận. Agent làm đúng như vậy: `WindowFromPoint` tại toàn bộ toạ độ sắp click (bỏ phiếu theo đa số, bỏ nền desktop/thanh tác vụ) → thấy **trình duyệt** thì `SetWindowPos(HWND_BOTTOM)` đẩy xuống đáy (= `SetTopMost Me, False`) → nhìn lại → cái lộ ra chính là ứng dụng đích.
  - Cố ý **không thu nhỏ** trình duyệt: bản gốc cũng chỉ bỏ always-on-top chứ không thu nhỏ form, và thu nhỏ thì có nguy cơ trình duyệt bung khỏi toàn màn hình lúc khôi phục (đúng cái phiền mà mục 125/127/130 session-log đã mất công dẹp).
  - Đưa cửa sổ lên trước bằng `AttachThreadInput` + `SetForegroundWindow`, rồi **chờ có xác minh** (`Rack:ForegroundTimeoutMs`, mặc định 1500ms) cho tới khi nó thật sự ở trước — không chờ mù bằng `SmartDelay`. Tính cả trường hợp tiêu điểm nằm ở cửa sổ con cùng tiến trình (hộp thoại của ứng dụng đích).
  - Bắn xong **trả tiêu điểm về cửa sổ đứng trước đó** (trình duyệt của thợ) — đúng cặp `SetTopMost Me, True`. Tắt được bằng `Rack:RestoreForeground=false`.
  - `Rack:TargetWindowTitle` / `Rack:TargetProcessName` **vẫn còn nhưng là TUỲ CHỌN**, chỉ dùng khi vùng toạ độ có nhiều cửa sổ chồng nhau và bước tự dò chọn nhầm (log ghi rõ mỗi lượt nó chọn cửa sổ nào).
  - `agent/WindowFocus.cs` là nơi chứa toàn bộ phần này.
- **Không tìm thấy cửa sổ đích ⇒ FAILED ngay, KHÔNG bắn mù** (`Rack:RequireTargetWindow`, mặc định `true`). Lý do cụ thể ("chưa mở ứng dụng pha màu" / "chưa cấu hình cửa sổ đích" / "clipboard bị chiếm") đi ngược lên qua `RackSender.LoiCuoi` → ack `error_message` → `GET /api/rack-dispatch/{id}` → **hiện nguyên văn trên màn cân**; thợ đứng ở xưởng không mở được log máy trạm. Khi Agent chưa xác định được cửa sổ, log in ra **danh sách toàn bộ cửa sổ đang mở** để lấy tiêu đề mà cấu hình.
- **Vẫn giữ nguyên (không đổi hành vi bản gốc):** toạ độ là **toạ độ màn hình tuyệt đối**, thứ tự bước và mọi mốc trễ y như cũ. Chỉ thêm cảnh báo (không chặn) khi toạ độ cấu hình nằm **ngoài khung cửa sổ đích** — dấu hiệu chưa đo lại toạ độ trên máy trạm này. Phương án click **tương đối theo cửa sổ** vẫn chưa làm, đúng như chủ dự án đã chốt 2026-08-03.
- **Rủi ro còn lại đã thu hẹp:** không còn "dán vào cửa sổ bất kỳ" ở mức *sai cửa sổ*; còn lại là *sai ô trong đúng cửa sổ* nếu ứng dụng pha màu mở khác vị trí/độ phân giải so với lúc hiệu chỉnh.
- **Ảnh hưởng khi nâng cấp:** cài đè MSI là xong, **không phải sửa cấu hình gì**. Muốn quay lại hành vi bắn mù của 4.4: `Rack:RequireTargetWindow=false`.

---
## ADR-013: Agent phục vụ số cân ngay tại máy trạm (đường cục bộ 127.0.0.1), backend làm đường dự phòng
- **Ngày:** 2026-08-07. **Yêu cầu gốc:** *"RAW / Agent có cách nào để nó có thể nhanh như ở 4.semiauto-small scale - delta-stable-final_DF026-027.xlsm không"*.
- **Bối cảnh — đo thật, không đoán:**
  - Agent đọc `putty_log.txt` mỗi **10ms** (`Scale:ReadIntervalMs`), tức đã ngang nhịp `StartFastLoop` của VBA. Số cân có mặt trong tiến trình Agent gần như tức thì.
  - Nhưng để tới được màn hình, con số phải đi **hai chặng mạng và hai lần chờ nhịp**: Agent đợi tới `Scale:PushIntervalMs` = **200ms** rồi POST lên CS-SERVER; trình duyệt lại hỏi backend mỗi **200ms** (`POLL_MS_WEIGHING`). Trung bình mất 100ms + 100ms chỉ để chờ nhịp.
  - Backend chạy `php artisan serve` — **một tiến trình, xử lý tuần tự**. Đo trên máy dev, endpoint `/api/devices/readings`: 1 request 18-23ms, nhưng **6 request đồng thời mất 110ms** ≈ đúng 6 lần một request, tức xếp hàng nối đuôi. Trên CS-SERVER, `session-log.md` mục 100 đo được 550ms/request và 1482ms khi 6 request chồng nhau.
  - Cộng lại: mỗi số RAW mất khoảng **400-900ms** mới lên tới mắt thợ. VBA thì đọc cùng file đó, cùng máy, ghi thẳng vào form — không có chặng nào cả.
- **Quyết định:** Agent mở một endpoint HTTP **chỉ nghe trên `127.0.0.1`** (`GET /weight`), trình duyệt trên CHÍNH máy trạm đọc thẳng ở đó với nhịp nhanh (60ms). Đường cũ (Agent → backend → trình duyệt) **giữ nguyên không đổi**, làm đường dự phòng tự động.
  - Cổng theo loại cân, cố định bằng quy ước hai bên đều biết (frontend không đọc được `appsettings.json` của Agent): **SMALL = 8770, LARGE = 8771**.
  - Agent **vẫn đẩy số lên backend y như cũ** — audit, các màn hình khác, hàng đợi offline, cặp máy→trạm đều không đổi.
  - Frontend thử cục bộ trước; hỏng thì rơi về backend và **thử lại đường cục bộ mỗi 30 giây** (Agent khởi động lại là tự dùng lại đường nhanh).
- **Lý do vẫn tôn trọng ADR-002:** trình duyệt vẫn **chỉ nói chuyện với Local Agent**, không bao giờ chạm cân/cổng COM. Ranh giới phân lớp không đổi; cái đổi là *hướng* đi của số cân giữa Agent và trình duyệt (kéo từ Agent thay vì vòng qua backend).
- **Phương án đã cân nhắc và loại:**
  - *Giảm nhịp đẩy/hỏi xuống 80ms:* rẻ nhưng nhân 2.5 lần số request đổ vào chính cái backend đang xếp hàng — làm chậm mọi màn khác để cứu một màn.
  - *Đẩy qua Reverb (WebSocket):* bỏ được lần chờ nhịp phía trình duyệt nhưng vẫn còn chặng Agent → backend, và mỗi số cân thành một broadcast qua backend một tiến trình.
  - *Bỏ `php artisan serve`, chạy web server thật:* **vẫn nên làm** (xem `session-log.md` mục 38) nhưng là việc hạ tầng riêng — nó không xoá được hai lần chờ nhịp nên một mình không đạt tốc độ VBA.
- **Hệ quả / rủi ro phải chấp nhận:**
  - **Bắt buộc cài lại MSI** trên máy trạm — không có cách nào cập nhật Agent mà không đụng vào máy.
  - **Mixed content nếu sau này chạy HTTPS:** trang `https://` gọi `http://127.0.0.1` bị Chrome chặn. Hiện web chạy HTTP nên chưa vướng; chuyển HTTPS thì phải cấp chứng thư cho Agent hoặc quay về đường backend.
  - **Private Network Access:** mở màn bằng `http://10.0.60.209:3001` (IP riêng) mà gọi xuống loopback là request "xuyên vùng mạng" — Chrome gửi preflight. Agent phải trả `Access-Control-Allow-Private-Network: true`, thiếu là đường cục bộ chết câm và tụt về backend mà không ai biết vì sao.
  - Endpoint không có xác thực. Chấp nhận được vì chỉ nghe loopback (không có ai ngoài mạng tới được) và chỉ trả đúng một con số cân đọc-được. **Không** được mở ra `0.0.0.0` với lý do gì.
  - Hai bộ cài trên cùng một máy phải khác cổng — đã tách sẵn theo `ScaleKind`.
