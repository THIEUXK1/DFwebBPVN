# Kịch bản Pilot End-to-End (pilot-end-to-end-scenarios.md)

Lập 2026-07-17. Theo yêu cầu mục 11: pilot phải kiểm chứng được **toàn bộ chuỗi nghiệp vụ**, không loại trừ CHEMICAL_CALL/QR_LABEL_PRINTING chỉ vì đang thiếu nhiều — nếu loại trừ sẽ không phát hiện được lỗi tại 2 module thiếu lớn nhất. Phạm vi bật/tắt từng workstation qua feature flag (`local-agent-architecture.md` Mục 4), KHÔNG hard-code.

---

## Chuỗi nghiệp vụ 7 bước (theo đúng mục 2 yêu cầu)

1. Gọi và báo yêu cầu hóa chất — CHEMICAL_CALL.
2. Tạo đơn sản xuất — PRODUCTION_ORDER.
3. Nhận đơn và in tem QR — QR_LABEL_PRINTING.
4. Quét QR và cân khối lượng nhỏ — SMALL_SCALE.
5. Quét QR và cân khối lượng lớn — LARGE_SCALE.
6. Ghi nhận hoàn thành, lịch sử gửi, trạng thái cân — RECORD_A (`tbl_SentLog`)/RECORD_B (`tblRECORD`)/WAREHOUSE (`tblWH_LOG`).
7. Truy vết xuyên suốt từ lúc tạo yêu cầu tới khi hoàn tất cân.

## Kịch bản E2E-01: Chu trình đầy đủ 1 mẻ nhuộm (happy path)

| Bước | Workstation | Hành động | Dữ liệu ghi | Điều kiện PASS |
|---|---|---|---|---|
| 1 | CHEMICAL_CALL | Operator bấm gọi hóa chất cho máy X kênh Y | `chemical_call_requests` status=ORDER | Trạng thái hiển thị ORDER ngay trên UI khác cũng đang mở |
| 2 | CHEMICAL_CALL | Xưởng cấp xong, operator bấm xác nhận | `chemical_call_requests` status=DONE | `confirmed_at` được ghi, audit log có entry |
| 3 | PRODUCTION_ORDER | Tạo đơn mới (màu/mã/máy/tank/level) | `app.machine_dispatches` queue_state=INPUT/WAITING | Kiểm tra trùng color+code hoạt động đúng |
| 4 | PRODUCTION_ORDER | Duyệt, chuyển sang hàng chờ gửi | queue_state=TO_SEND | Row lock/ID lock không cho 2 người sửa cùng lúc |
| 5 | QR_LABEL_PRINTING | Operator xác nhận scale_check, xác định B24/mode QR, tạo QR, gửi lệnh in | `scale_checked=true`, `raw_qr_dye/chemical` lưu, print job tạo | QR sinh nội bộ, không gọi ra ngoài |
| 6 | QR_LABEL_PRINTING (Print Agent) | Nhận job, in tem thật | `print_jobs.status` chuyển queued→printing→printed | Không in trùng nếu bấm 2 lần liên tiếp |
| 7 | QR_LABEL_PRINTING | Bấm OK xác nhận hoàn tất | `machine_dispatch_sent_log_entries` tạo (đề xuất), queue_state=SENT | Không ghi trùng log nếu bấm 2 lần |
| 8 | SMALL_SCALE hoặc LARGE_SCALE | Operator quét QR tem, hệ thống đối chiếu đơn | Job cân được xác định đúng | QR đọc được từ tem in thật (không phải tem giả lập) |
| 9 | SMALL_SCALE/LARGE_SCALE | Cân, StableFilter xác nhận ổn định, tolerance kiểm tra | `app.scale_measurements`/`tblRECORD` tương đương ghi | Dùng đúng ScaleCore chung (không có bug ACCEPTED/REJECTED của workbook B) |
| 10 | SMALL_SCALE/LARGE_SCALE | Xác nhận hoàn tất | `WH_DONE=true`, ghi log kho tương đương `tblWH_LOG` | — |
| 11 | Toàn chuỗi | Truy vết ngược từ kết quả cân → tem in → đơn → yêu cầu hóa chất | 1 truy vấn/1 màn hình hiển thị đủ 5 bước | correlation_id xuyên suốt (đề xuất mới, VBA gốc không có) |

## Kịch bản E2E-02: 2 workstation/2 phiên trình duyệt sửa cùng 1 đơn (PRODUCTION_ORDER)

- Mở đơn tại 2 phiên → cả 2 cùng bấm Duyệt gần như đồng thời.
- **PASS:** chỉ 1 phiên thành công, phiên còn lại nhận lỗi rõ ràng (409/lock-timeout), không có trạng thái nửa chừng nếu transaction thất bại. Cơ chế cụ thể (transaction lock/optimistic version/lease lock/idempotency key) — xem câu hỏi mục 7.1, cần chọn 1 và mô tả rõ trường hợp lock hết hạn trước khi code.

## Kịch bản E2E-03: Agent mất mạng rồi kết nối lại (Scale + Print)

- Rút mạng LAN tại trạm SMALL_SCALE giữa lúc đang cân.
- **PASS:** dữ liệu cân được buffer cục bộ (SQLite), không mất; khi có mạng lại, đồng bộ tự động với Idempotency Key, không tạo bản ghi trùng ở backend.

## Kịch bản E2E-04: Printer fail và retry

- Máy in hết giấy/mất kết nối giữa lúc in.
- **PASS:** print job chuyển `failed`, có retry giới hạn số lần, KHÔNG tự động in lại vô hạn, không tạo 2 bản ghi `tbl_SentLog`-tương-đương cho cùng 1 lần Confirm.

## Kịch bản E2E-05: Scan QR hai lần

- Operator quét lại QR đã quét trước đó tại SMALL_SCALE.
- **PASS:** hệ thống nhận diện đã xử lý, không tạo job cân trùng; hành vi cụ thể (từ chối/hiển thị lại job cũ) — cần xác nhận nghiệp vụ, VBA gốc không có cơ chế này rõ ràng.

## Kịch bản E2E-06: Chemical Call ORDER→DONE với 2 thao tác gần nhau

- 2 operator cùng bấm nút cho cùng 1 kênh trong vòng <1 giây.
- **PASS:** không tạo 2 request ORDER trùng cho cùng 1 kênh (partial unique index đề xuất ở `chemical-call-domain.md` Mục 3).

## Kịch bản E2E-07: Shadow mode đối soát VBA vs Web (Phase F)

- Chạy song song, KHÔNG tắt VBA — mọi giao dịch thật vẫn qua VBA + Access như hiện tại.
- Web nhận cùng input (qua quét QR song song hoặc nhập tay đối chiếu) và tự tính toán độc lập.
- **PASS:** kết quả web khớp VBA trong dung sai ±0.000001 (theo CLAUDE.md) cho các phép tính cân; khớp chính xác cho B24/mode QR (không có sai số cho phép vì là logic rẽ nhánh, không phải phép đo).
- Ghi nhận sai lệch vào báo cáo riêng — **không cutover cho tới khi đạt tiêu chí nghiệm thu** (chưa định nghĩa cụ thể, cần thống nhất với người dùng ở bước sau).

---

## Điều kiện đủ để pilot bắt đầu (đối chiếu `pilot-blockers.md`, cập nhật theo cơ cấu 6 máy)

- PB-1 → PB-9 (đã liệt kê trong `pilot-blockers.md`) phải ở trạng thái PASS hoặc có kế hoạch xử lý bằng văn bản.
- CHEMICAL_CALL và QR_LABEL_PRINTING **phải có tối thiểu 1 phiên bản chạy được** (không cần đầy đủ 100% tính năng B/C) trước khi pilot bắt đầu — theo đúng lý do nêu ở mục 11: loại trừ 2 module này khỏi pilot sẽ không kiểm chứng được end-to-end.
- Feature flag cho phép tắt riêng từng workstation nếu phát sinh sự cố nghiêm trọng giữa chừng pilot, không cần rollback toàn bộ.
