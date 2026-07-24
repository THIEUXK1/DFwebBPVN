# Legacy Data Backfill Plan — Kế Hoạch Nạp Dữ Liệu Lịch Sử (backfill-plan.md)

Lập 2026-07-17 — Phase C/D. Thiết kế chi tiết cho việc nạp dữ liệu lịch sử từ các cơ sở dữ liệu Microsoft Access legacy (`RECORD_A`, `RECORD_B`) sang database PostgreSQL mục tiêu. Tài liệu thiết kế — không chạy migration sản xuất.

---

## 1. Mục Tiêu và Phạm Vi
- **Mục tiêu:** Di trú toàn bộ dữ liệu lịch sử hoạt động từ năm 2025 đến nay sang PostgreSQL, đảm bảo tính nhất quán 100% về số lượng bản ghi và giá trị định lượng, không làm thất thoát thông tin.
- **Nguyên tắc:** 
  - Thực hiện nạp thử nghiệm (Dry-Run) trên cơ sở dữ liệu staging trước.
  - Không được bỏ qua các bản ghi lỗi; các bản ghi không hợp lệ hoặc thiếu khóa sẽ được đưa vào hàng đợi ngoại lệ (`legacy_exception_queue_items`) để xử lý thủ công.
  - Không tạo liên kết cứng (Foreign Key) vật lý trực tiếp giữa các bảng lịch sử của `RECORD_A` và `RECORD_B` khi chưa chạy đối soát thành công.

---

## 2. Quy Trình Backfill Chi Tiết

### 2.1. Backfill dữ liệu RECORD_A (Điều phối & In tem)
Dữ liệu nguồn gồm bảng `tbl_SentLog` (27.024 dòng thật, lịch sử gửi), `tbl_ToSend` (4 dòng, hàng chờ hiện tại)/`tbl_ToSend2` (696 dòng, dừng 2025-11-20) và `WAITING`/`tbl_Waiting` (57/71 dòng) — xem `database-inventory.md`.

- **Bước A1: Kiểm tra tệp Access nguồn** *(cập nhật 2026-07-17 — KHÔNG còn cần Compact & Repair)*
  - `RECORD.accdb` hiện có đọc được đầy đủ 27.024 dòng `tbl_SentLog` qua DAO/COM, không phát hiện lỗi hỏng trang dữ liệu (R-01 trong `risks-and-assumptions.md` — lo ngại từ đợt audit trước khi tìm thấy file này — nay đã lỗi thời, cần đánh dấu RESOLVED, xem Mục "Đối chiếu chéo" cuối tài liệu).
  - Xuất bảng sang CSV (UTF-8) hoặc đọc trực tiếp qua adapter (`Legacy Integration` domain, `domain-architecture.md` Mục 1.8) để import vào PostgreSQL staging.
- **Bước A2: Ánh xạ và chuyển đổi dữ liệu (Transform to Target)**
  - Di trú `tbl_SentLog` sang `app.dispatch_events` (loại event `SENT`). Lưu đầy đủ các trường `raw_qr_dye` và `raw_qr_chemical` từ Access.
  - `tbl_ToSend2`/`WAITING`/`tbl_Waiting`: **KHÔNG map vào `app.machine_dispatches` cho tới khi CH-TECH-004 (mapping cột nghi lệch, `open-questions.md`) được xác nhận** — 3 bảng này thiếu cột `rawqrdye`/`rawqrchem`/`scale_check` so với `tbl_SentLog`/`tbl_ToSend`, không có VBA nguồn xác nhận nào ghi/đọc chúng (xem `qr-label-printing-domain.md` Mục 1) — nạp vào staging thô, KHÔNG transform tới bảng đích trong Wave 3.
  - Ánh xạ trường `scale_check`→`scale_checked` 1:1 (cùng kiểu Boolean).

### 2.2. Backfill dữ liệu RECORD_B (Lịch sử Cân)
Dữ liệu nguồn gồm bảng `tblRECORD` (140.655 dòng, mẻ cân thuốc nhuộm) và `tblRECORD_chem` (5.061 dòng, mẻ cân hóa chất) — số liệu xác nhận qua `database-inventory.md`.

- **Bước B1: Import thô vào Staging**
  - Nạp toàn bộ dữ liệu từ `RECORD1.accdb` vào PostgreSQL staging.
- **Bước B2: Chuẩn hóa và Ghi nhận (Transform to Target)**
  - Chuyển đổi dữ liệu sang bảng `app.weighing_results`.
  - **Suy luận Thiết bị và Máy trạm (Device/Workstation Inference) — SỬA LẠI theo nguyên tắc "không tự gán sai":**
    - Access `tblRECORD`/`tblRECORD_chem` **không có cột lưu `device_id`/`workstation_id`** — VBA gốc (workbook 4/5) không ghi nhận máy trạm cân nào tạo ra dòng dữ liệu (cả 2 workbook cùng ghi vào 1 file `RECORD1.accdb` dùng chung).
    - **KHÔNG suy luận SMALL_SCALE/LARGE_SCALE bằng ngưỡng khối lượng (vd. ">5kg") hay bất kỳ heuristic nào không có bằng chứng** — đây là quy tắc tự bịa, vi phạm trực tiếp nguyên tắc "không được gán một weighing record cho dispatch/workstation chỉ dựa vào suy đoán" đã thống nhất trước đó.
    - **Quyết định backfill chính thức:** mọi dòng `tblRECORD`/`tblRECORD_chem` lịch sử được nạp với `workstation_id = NULL` (không gán), đánh dấu `legacy_workstation_inference = 'UNKNOWN'` — đưa vào **báo cáo riêng** (không phải exception queue full, vì đây không phải lỗi mà là giới hạn dữ liệu nguồn đã biết trước) để nghiệp vụ xác nhận có cách nào khác xác định (vd. đối chiếu theo khung giờ ca trực, sổ tay giấy) hay chấp nhận để trống vĩnh viễn cho dữ liệu lịch sử.
  - Ghi nhận khóa nghiệp vụ legacy (`legacy_source = 'tblRECORD'`, `legacy_id = ID`).

### 2.3. Backfill CHEM_ORDER, WAREHOUSE, DF_STORAGE (mục 5.1 — bổ sung, trước đây thiếu)

- **`CHEM_ORDER.tbl_status`** (40 dòng): backfill vào `app.machine_chemical_channels` (đã thực hiện 1 lần trước đó theo `session-log.md` — cần xác nhận lại còn khớp 40/40 hay đã có thay đổi trong file nguồn hiện tại trước khi backfill lại).
- **`CHEM_ORDER.tblRECORD`/`tblRECORD_chem`** (47.381/1.500 dòng, dừng 2026-03-31): phân loại `LEGACY_ARCHIVE`/`UNKNOWN_BLOCKED` theo `legacy-database-mapping.md` — **KHÔNG backfill vào bảng nghiệp vụ nào**, chỉ giữ nguyên trong staging thô (nếu cần) làm tài liệu tham chiếu, chờ CH-BUS-014.
- **`WAREHOUSE.tblWH_LOG`** (35 dòng): backfill vào bảng log tiêu thụ đề xuất (chưa có trong `target-data-model.md` chính thức — xem CH-BUS-007, `open-questions.md`) — **BLOCKED** cho tới khi có thiết kế bảng đích.
- **`DF_STORAGE.DF_STORAGE`** (89 dòng): giữ vai trò `legacy/reference` theo baseline mới nhất — chỉ backfill nếu xác nhận còn writer/reader thật (chưa xác nhận, xem Mục "Đối chiếu chéo" cuối tài liệu — đề xuất mở CH-BUS-015 mới).

---

## 3. Báo Cáo Chất Lượng Dữ Liệu Backfill (Quality Report Schema)

Mỗi lần thực thi chạy thử (dry-run) hoặc nạp chính thức, script migration phải xuất ra một báo cáo chất lượng dữ liệu (Quality & Reconciliation Report) bao gồm các chỉ số sau:

### 3.1. Chỉ số Đối soát Số lượng (Reconciliation Metrics)
- **Tổng số bản ghi nguồn (Access, số liệu xác nhận 2026-07-17 qua `database-inventory.md`):**
  - `tblRECORD` (RECORD_B): **140.655** dòng
  - `tblRECORD_chem` (RECORD_B): **5.061** dòng
  - `tbl_SentLog` (RECORD_A): **27.024** dòng
  - `tbl_ToSend` (RECORD_A): 4 dòng | `tbl_ToSend2`: 696 | `WAITING`: 57 | `tbl_Waiting`: 71 (3 bảng cuối: chỉ nạp staging, không transform — xem 2.1)
- **Tổng số bản ghi đích (PostgreSQL app schema):**
  - `app.weighing_results` (loại DYE): N dòng (kỳ vọng 140.655)
  - `app.weighing_results` (loại CHEMICAL): N dòng (kỳ vọng 5.061)
  - `app.dispatch_events`: N dòng (kỳ vọng 27.024)
- **Chênh lệch (Unmatched / Delta):** Yêu cầu phải bằng `0` cho 3 bảng có transform target rõ ràng ở trên.

### 3.2. Chỉ số Phân loại Lỗi Dữ Liệu — kèm Reason Code chuẩn hóa (bổ sung theo yêu cầu mục 5.3)

| Reason Code | Ý nghĩa | Xử lý |
|---|---|---|
| `MISSING_ORDER_KEY` | Dòng thiếu `color`/`code` (khóa nghiệp vụ tối thiểu) | Vào exception queue, không loại bỏ |
| `AMBIGUOUS_CORRELATION` | Nhiều bản ghi RECORD_B khớp cùng lúc với 1 dispatch RECORD_A (xem `record-a-record-b-correlation.md`) | Vào exception queue |
| `INVALID_DATE` | `TIME`/`TIME1..3` không parse được (đặc biệt `tbl_ToSend2`/`WAITING` lưu dạng Text US-format) | Ghi log, giữ giá trị gốc dạng text ở cột phụ, `posted_at`/`measured_at` = NULL |
| `UNKNOWN_WORKSTATION` | Không suy luận được `workstation_id` (xem 2.2 — mọi dòng RECORD_B lịch sử rơi vào đây theo thiết kế) | KHÔNG vào exception queue (đã biết trước, xem báo cáo riêng Mục 2.2), map với `workstation_id=NULL` |
| `UNKNOWN_DEVICE` | Tương tự, không suy luận được `device_id` | Cùng xử lý như trên |
| `DUPLICATE_LEGACY_ID` | `legacy_source`+`legacy_id` đã tồn tại (backfill chạy lại) | **Không lỗi** — đây là cơ chế idempotent hoạt động đúng (Mục 3.4), bỏ qua dòng trùng, không tạo bản ghi mới |
| `UNSUPPORTED_STATUS` | Giá trị trạng thái Access không map được vào 1 trong các state đã định nghĩa ở `state-machines.md` | Vào exception queue, giữ giá trị gốc trong `matched_on`/ghi chú |

- **Map thành công:** Số dòng di trú trơn tru vào bảng chính (không thuộc bất kỳ reason code nào ở trên).
- **Trùng lặp khóa:** Xem `DUPLICATE_LEGACY_ID` — xử lý bằng `UNIQUE(legacy_source, legacy_id)` (Mục 3.4), không phải lỗi cần sửa tay.

### 3.3. Chỉ số Giới hạn Thời gian & Checksum
- **Thời gian ghi nhận cũ nhất/mới nhất:** Theo dữ liệu mẫu đã lấy — `tbl_SentLog` mới nhất **2026-07-15 09:25**, `tblRECORD` (RECORD_B) mới nhất **2026-07-15 09:09** (xem `database-inventory.md`).
- **Tổng trọng lượng bột màu tích lũy (Sum of Weighing Quantity):**
  - Công thức đối soát: `SUM(Access.tblRECORD.Weight) = SUM(PostgreSQL.weighing_results.final_value)`
  - Sai số cho phép: `< 0.000001` (theo CLAUDE.md, bù trừ sai lệch làm tròn số float).
  - **Lưu ý:** cột `WEIGHT` trong `tblRECORD` là kiểu **Text** (không phải Numeric) theo `database-inventory.md` — bước transform phải parse an toàn, dòng parse lỗi → `reason_code=INVALID_DATE`-tương-tự (đề xuất thêm `INVALID_NUMBER` vào bảng reason code nếu gặp thực tế).

### 3.4. Idempotent design (mục 5.4 — bổ sung, trước đây thiếu)

| Yêu cầu | Thiết kế |
|---|---|
| Checkpoint | Bảng `app.backfill_runs` (đề xuất mới: `id`, `source_table`, `last_processed_legacy_id`, `started_at`, `finished_at`, `status`) — lưu vị trí đã xử lý tới đâu |
| Batch size | Đề xuất 1.000–5.000 dòng/batch (tùy tải DB), commit theo batch, không 1 transaction cho toàn bộ 140k dòng |
| Resumable | Backfill đọc `last_processed_legacy_id` từ `app.backfill_runs`, tiếp tục từ đó nếu bị ngắt giữa chừng |
| Dry-run | Chạy toàn bộ logic transform + validation nhưng KHÔNG `INSERT` vào bảng đích, chỉ xuất báo cáo Mục 3.1-3.3 — bắt buộc chạy dry-run trước lần chạy thật đầu tiên |
| Rerun | An toàn nhờ `UNIQUE(legacy_source, legacy_id)` ở mọi bảng đích (đã có nguyên tắc từ `target-data-model.md` Mục 1) — chạy lại không tạo trùng, tự động qua nhánh `DUPLICATE_LEGACY_ID` |
| Source checksum | Tính `MD5`/`SHA256` theo `(legacy_source, legacy_id, các cột nghiệp vụ chính)` lưu ở `app.backfill_runs` hoặc cột riêng — phát hiện nếu dữ liệu nguồn Access bị sửa giữa 2 lần backfill (không nên xảy ra với dữ liệu lịch sử, nhưng cần phát hiện được nếu có) |
| `legacy_source`+`legacy_id` unique constraint | Áp dụng cho MỌI bảng đích có backfill — đã là nguyên tắc thiết kế xuyên suốt từ `target-data-model.md` |
