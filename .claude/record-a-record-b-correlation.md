# Record A & Record B Correlation — Cơ Chế Đối Chiếu Dữ Liệu (record-a-record-b-correlation.md)

Lập 2026-07-17 — Phase C/D. Thiết kế chi tiết cho việc liên kết dữ liệu lịch sử và vận hành giữa hai cơ sở dữ liệu độc lập `RECORD_A` (điều phối) và `RECORD_B` (cân). Tài liệu thiết kế — không sửa code sản xuất.

> [!WARNING]
> Phân hệ `CHEMICAL_CALL` tạm thời bị loại trừ khỏi phạm vi đối chiếu tự động và kiểm soát tương quan trong đợt triển khai này. Toàn bộ thiết kế dưới đây chỉ áp dụng cho luồng in tem và cân của các phân hệ còn lại (`PRODUCTION_ORDER`, `QR_LABEL_PRINTING`, `SMALL_SCALE`, `LARGE_SCALE`).

---

## 1. Bản Chất Vấn Đề
Database `RECORD_A` và `RECORD_B` được vận hành độc lập bởi các ứng dụng VBA Excel khác nhau tại các phân xưởng khác nhau. Trong dữ liệu legacy:
- Không có khóa ngoại (Foreign Key) vật lý hay UUID chung nào liên kết trực tiếp giữa hàng chờ gửi đi (dispatch) và kết quả cân (weighing results).
- Các trường nhập liệu thủ công (như mã màu, mã hàng, tên máy) thường xuyên bị sai lệch ký tự (khoảng trắng, chữ hoa/thường, ký tự tiếng Việt có dấu/không dấu).
- Sai lệch múi giờ hoặc độ trễ thời gian (đặc biệt khi mất mạng LAN) làm cho việc so khớp chỉ bằng timestamp là cực kỳ rủi ro và dễ dẫn đến gán sai mẻ cân của đơn hàng này cho đơn hàng khác.

Để giải quyết, hệ thống đích áp dụng **thiết kế liên kết mềm thông qua bảng trung gian `app.correlation_links`**, phân loại các phương thức khớp theo mức độ tin cậy từ cao xuống thấp.

---

## 2. Phân Loại Phương Thức Đối Chiếu (Correlation Methods)

Mỗi liên kết đối chiếu giữa `app.machine_dispatches` (hoặc `app.dispatch_events`) và `app.weighing_results` phải được phân loại vào một trong bốn nhóm sau:

### 2.1. Exact Match (Khớp Tuyệt Đối — Độ tin cậy: 1.00)
- **Cơ chế:** Khớp thông qua mã QR duy nhất được sinh ra từ Web mới. Khi Operator quét mã QR tại trạm cân, payload của QR có chứa `dispatch_id` (UUID). Trạm cân gửi `dispatch_id` này kèm theo kết quả cân.
- **Ánh xạ khóa:** `weighing_results.job_item_id` liên kết trực tiếp `dispatch_jobs.id` (thông qua QR).
- **Ứng dụng:** Áp dụng cho 100% dữ liệu phát sinh mới sau khi hệ thống Web mới đi vào vận hành.

### 2.2. Deterministic Composite Match (Khớp Khóa Ghép Xác Định — Độ tin cậy: 0.90)
- **Cơ chế:** Khớp dữ liệu lịch sử bằng cách kết hợp nhiều trường thông tin nghiệp vụ trùng khớp hoàn toàn.
- **Quy tắc khớp:**
  - `dispatch_events.color = weighing_results.color` (sau khi trích xuất và chuẩn hóa chuỗi viết thường, xóa khoảng trắng).
  - `dispatch_events.code = weighing_results.code`.
  - `dispatch_events.machine_id = weighing_results.machine_id`.
  - Khoảng chênh lệch thời gian giữa confirm gửi (`dispatch_events.occurred_at`) và thời gian cân (`weighing_results.posted_at`) nhỏ hơn 4 tiếng.
- **Ứng dụng:** Dùng để chạy backfill tự động cho phần lớn dữ liệu lịch sử.

### 2.3. Ambiguous (Nhiều Candidate Cùng Phù Hợp — bổ sung 2026-07-17, tách riêng khỏi Probabilistic theo yêu cầu mục 9.2)
- **Cơ chế:** ≥2 bản ghi RECORD_B (hoặc RECORD_A) cùng thỏa mãn quy tắc Deterministic Composite (2.2) cho cùng 1 bản ghi phía đối diện — KHÔNG có tiêu chí nào phân biệt được candidate nào đúng (vd. 2 mẻ cân cùng `color`+`code`+`machine`, cách nhau vài phút, cùng đơn dispatch).
- **Hệ quả:** **Không tự động tạo liên kết** dù confidence tính được có thể cao — bắt buộc đẩy cả nhóm candidate vào Exception Queue (`status='EXCEPTION_QUEUE'`, `matched_on` lưu TOÀN BỘ danh sách candidate, không chỉ 1) để người xác nhận chọn thủ công.

### 2.4. Probabilistic / Manual Match (Khớp Xác Suất / QA Duyệt — Độ tin cậy: 0.50 - 0.80)
- **Cơ chế:** Khớp khi các khóa ghép nghiệp vụ bị lệch nhẹ (ví dụ: viết sai chính tả mã màu `"Vang Chanh"` vs `"Vang-Chanh"`, máy `"VD14"` viết thành `"vd 14"`) nhưng có khoảng thời gian rất gần nhau (< 1 tiếng) và **chỉ có đúng 1 candidate** (khác Mục 2.3 — nếu có ≥2 candidate dù mức tin cậy thấp, đây vẫn là `AMBIGUOUS`, không phải `PROBABILISTIC`).
- **Quy tắc:** Sử dụng thuật toán so khớp chuỗi Levenshtein Distance cho `color` và `code` kết hợp phân tích cửa sổ thời gian.
- **Hệ quả:** Hệ thống tự động ghi nhận liên kết vào `app.correlation_links` với trạng thái `status = 'EXCEPTION_QUEUE'` và gán mức độ tin cậy `confidence` (ví dụ: `0.75`). QA phải vào màn hình Admin để bấm nút phê duyệt (Approve) hoặc từ chối (Reject) liên kết này — kết quả duyệt map vào `match_method='MANUAL'` (không phải `PROBABILISTIC` nữa) khi đã có người xác nhận.

### 2.5. Unmatched (Không Thể Đối Chiếu)
- **Cơ chế:** Dữ liệu mồ côi (chỉ có mẻ cân mà không thấy lệnh điều phối tương ứng, hoặc ngược lại) — 0 candidate nào thỏa dù chỉ Deterministic hay Probabilistic.
- **Hệ quả:** Tuyệt đối không tự ý gán bừa bãi chỉ dựa vào thời gian gần nhau để tránh làm sai lệch báo cáo kiểm toán. Bản ghi được lưu ở trạng thái mồ côi, `match_method='UNMATCHED'`, đẩy thông tin cảnh báo vào Exception Queue.

### 2.6. Rejected (Đã Từ Chối)
- **Cơ chế:** QA đã xem xét 1 candidate (từ 2.3 hoặc 2.4) và xác nhận đây KHÔNG phải liên kết đúng.
- **Hệ quả:** `match_method`/`status='REJECTED'`, giữ lại lịch sử (không xóa) kèm `review_reason` — để tránh hệ thống đề xuất lại đúng candidate đã bị từ chối trong lần chạy correlation kế tiếp.

**Tổng kết 6 giá trị `match_method`/phân loại chính thức (đúng yêu cầu mục 9.2):** `EXACT`, `DETERMINISTIC_COMPOSITE`, `AMBIGUOUS`, `MANUAL` (kết quả sau khi QA duyệt candidate ở 2.3/2.4), `UNMATCHED`, `REJECTED`. Cột `match_method` ban đầu ghi `PROBABILISTIC` (tính toán tự động, khoảng tin cậy 0.50–0.80) chuyển thành `MANUAL` sau khi có xác nhận người — 2 nhãn này đại diện 2 giai đoạn của cùng 1 luồng (2.4), không phải 2 giá trị độc lập cùng tồn tại vĩnh viễn.

---

## 3. Cấu Trúc Bảng Liên Kết `app.correlation_links`

Bảng lưu trữ thông tin đối chiếu có cấu trúc như sau:

```sql
CREATE TABLE app.correlation_links (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    dispatch_id uuid NOT NULL REFERENCES app.machine_dispatches(id),   -- Source A ID
    weighing_job_id uuid NOT NULL REFERENCES app.weighing_jobs(id),    -- Source B ID
    match_method varchar(30) NOT NULL, -- 'EXACT'|'DETERMINISTIC_COMPOSITE'|'AMBIGUOUS'|'MANUAL'|'UNMATCHED'|'REJECTED'
    confidence numeric(3,2),           -- 0.00 đến 1.00 (EXACT = 1.00, NULL cho UNMATCHED/AMBIGUOUS chưa xử lý)
    matched_on jsonb NOT NULL,         -- Fields matched: snapshot color/code/machine/time_delta; với AMBIGUOUS lưu TOÀN BỘ danh sách candidate
    status varchar(20) NOT NULL DEFAULT 'LINKED', -- 'LINKED', 'EXCEPTION_QUEUE', 'REJECTED'
    created_by varchar(30) NOT NULL,   -- 'SYSTEM_BACKFILL'|'SYSTEM_REALTIME'|user_id nếu tạo tay
    created_at timestamptz NOT NULL DEFAULT now(),
    reviewed_by uuid REFERENCES app.users(id),   -- NULL nếu chưa qua review (EXACT/DETERMINISTIC_COMPOSITE tự động không cần review)
    reviewed_at timestamptz,
    review_reason text,                -- Bắt buộc nếu status chuyển sang REJECTED hoặc AMBIGUOUS→MANUAL
    updated_at timestamptz NOT NULL DEFAULT now()
);
-- Audit trail: dùng chung app.audit_logs (entity_type='correlation_links'), KHÔNG tạo bảng audit riêng thứ 2
```
- Khi chạy script backfill hoặc API đối chiếu phát hiện sai lệch, bản ghi được chèn vào bảng này.
- QA có thể dùng quyền `audit.view` để tra cứu và thay đổi trạng thái liên kết từ `EXCEPTION_QUEUE` sang `LINKED` hoặc `REJECTED`.

---

## 4. Exception Queue — Giao diện & API sơ bộ (mục 9.4)

| Màn hình/API | Mô tả |
|---|---|
| `GET /api/correlation/exceptions?status=EXCEPTION_QUEUE` | Danh sách record chưa match/nhiều candidate/chờ duyệt — trả kèm `matched_on` để hiển thị so sánh |
| `GET /api/correlation/exceptions/{id}/candidates` | Với record `AMBIGUOUS`: trả toàn bộ danh sách candidate đủ điều kiện (không chỉ 1) để người dùng chọn |
| `POST /api/correlation/links/manual` | Tạo liên kết thủ công — request `{dispatch_id, weighing_job_id, reason}`, `match_method` set cứng `MANUAL`, `reviewed_by`=actor |
| `DELETE /api/correlation/links/{id}` (unlink) | Hủy liên kết đã tạo (kể cả EXACT nếu phát hiện sai) — **bắt buộc `reason`**, ghi `audit_logs`, KHÔNG xóa cứng record (soft — set `status='REJECTED'`, giữ lịch sử) |
| `POST /api/correlation/exceptions/{id}/reject` | Từ chối 1 candidate cụ thể — `review_reason` bắt buộc |
| `POST /api/correlation/exceptions/{id}/re-review` | Đưa 1 liên kết đã `REJECTED`/`LINKED` quay lại hàng đợi xem xét (vd. phát hiện thêm thông tin mới) |
| Permission | `audit.view` để xem, cần thêm `correlation.manual_link`/`correlation.reject` riêng (bổ sung vào `permission-matrix.md` — xem Mục "Đối chiếu chéo" trong `session-log.md` cập nhật) |
