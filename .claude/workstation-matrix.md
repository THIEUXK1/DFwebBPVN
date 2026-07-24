# Ma trận Trạm làm việc (workstation-matrix.md)

> [!IMPORTANT]
> **Cập nhật 2026-07-17 — HIỆU CHỈNH LỚN theo cơ cấu vận hành thực tế đã xác nhận với người dùng.**
> Phiên bản trước của tài liệu này giả định **7 workstation** (3× ORDER_SCAN, 3× WEIGH `TO_CONFIRM`, 1× PRINT) suy ra thuần túy từ lịch sử kết nối mạng (7 địa chỉ IP từng thấy), không có xác nhận nghiệp vụ cho vai trò từng IP. Người dùng đã xác nhận trực tiếp cơ cấu vận hành thật gồm **6 máy nghiệp vụ chính**, ánh xạ 1-1 với 5 workbook VBA nguồn. Tài liệu này viết lại theo cơ cấu đã xác nhận; phần IP lịch sử được giữ lại ở Mục 4 chỉ như dữ liệu tham chiếu **chưa đối chiếu xong** với 6 máy thật — không suy diễn thêm.
>
> **Cập nhật 2026-07-17 (đợt duyệt lần 4):** Đã hoàn tất audit database Access thật đứng sau 5 workstation (`database-inventory.md`, `legacy-database-mapping.md`) — xác nhận `RECORD.accdb`/`RECORD1.accdb` là 2 database độc lập (không phải bản sao), đã trích xuất đầy đủ logic B24 (`b24-warehouse-routing.md`), thiết kế đề xuất domain CHEMICAL_CALL (`chemical-call-domain.md`) và QR_LABEL_PRINTING (`qr-label-printing-domain.md`), kiến trúc Local Agent (`local-agent-architecture.md`), kịch bản pilot E2E (`pilot-end-to-end-scenarios.md`). Pilot 7 ngày nay PHẢI bao gồm CHEMICAL_CALL + QR_LABEL_PRINTING theo yêu cầu mới — không loại trừ.
>
> **Nguyên tắc bắt buộc từ đây trở đi:** không tự gán một khái niệm nghiệp vụ (hóa chất, A11, DLG, vận chuyển, tới thùng, cấp máy…) thành một workstation vật lý riêng nếu chưa có bằng chứng vận hành thật (workbook nguồn xác nhận + hoặc admin xác nhận IP/máy tương ứng). Xem thêm ghi chú Mục 5.

---

## 1. Bảng 6 Máy Nghiệp vụ Đã Xác nhận (Nguồn: người dùng, 2026-07-17)

| # | Workstation Type | Số máy client thật | Workbook VBA nguồn | UserForm chính | Đã audit procedure? |
| :-- | :--- | :--- | :--- | :--- | :--- |
| 1 | **CHEMICAL_CALL** | 1 | `1.báo phát AC XƯỞNG -193.xlsm` | `chem_order.frm` | **XONG (2026-07-17)** — 16 dòng/44 procedure, xem `.claude/vba-migration-matrix.md` NHÓM 0. Trước đây: CHƯA (workbook chưa từng nằm trong 355 dòng traceability gốc). |
| 2 | **PRODUCTION_ORDER** | 1 | `2.C3 grid load row lock id FB -192(QR).xlsm` | `mainform.frm` (+ `checkform.frm`, `formselect1.frm`, `formselect2.frm`, `subform.frm`) | CÓ — đã audit đầy đủ ở NHÓM 2 (DISPATCH) trong `vba-migration-matrix.md`, ID `VBA-DISPATCH-*`, workbook ký hiệu "C3". |
| 3 | **QR_LABEL_PRINTING** | 1 | `3.DF028  formulas - PRINTER LANDSCAPE - jit qr sending - 15l special.xlsm` | `TO_SEND.frm` (+ `printform.frm`, `wait_printform.frm`) | **XONG (2026-07-17)** — 51 dòng/308 procedure, xem `.claude/vba-migration-matrix.md` NHÓM 4-DF028. Trước đây: CHƯA — 83 dòng `VBA-PRINT-*` cũ audit nhầm 2 workbook khác không phải máy sản xuất thật. |
| 4 | **SMALL_SCALE** (profile) | 2 (dùng chung 1 profile cấu hình) | `4.semiauto-small scale - delta-stable-final_DF026-027.xlsm` | `scaleform.frm` (+ `checkform.frm`) | CÓ — audit ở NHÓM 3 (SCALE) trong `vba-migration-matrix.md`, workbook ký hiệu "C". |
| 5 | **LARGE_SCALE** (profile) | 1 | `5.Semiauto- lockmove SEND OVER6 - delta-stable-final-221.xlsm` | `scaleform.frm` (+ `checkform.frm`) | CÓ — audit ở NHÓM 3 (SCALE) trong `vba-migration-matrix.md`, workbook ký hiệu "B". |

**Tổng: 6 máy vật lý, 5 workstation type/profile** (SMALL_SCALE là 1 profile cấu hình dùng chung cho 2 máy client, không phải 2 profile riêng).

---

## 2. CẢNH BÁO QUAN TRỌNG — khoảng trống audit vừa phát hiện

Đối chiếu module VBA của 5 workbook xác nhận với nội dung đã có trong `vba-migration-matrix.md` cho thấy:

- **`chem_order.frm` (CHEMICAL_CALL) chưa từng được audit** — không xuất hiện trong bất kỳ nhóm nào của đợt rà soát 355 dòng trước đó.
- **`DF028 ... PRINTER LANDSCAPE ... jit qr sending - 15l special.xlsm` (QR_LABEL_PRINTING) — workbook in tem QR THẬT đang chạy sản xuất — cũng chưa từng được audit.** NHÓM 4 ("IN TEM VÀ QR") trong đợt trước audit 2 file khác: `in tem Copower.xlsm` và `QR PRINTER-send to access- NEW 9ROWS BIG QR.xlsm`. So sánh danh sách module xác nhận **2 file đó và DF028 là hai kiến trúc hoàn toàn khác nhau** (DF028 có `TO_SEND.frm`, `ModBackEndDB.bas`, `Mod_load_sentlog_sheet.bas`, `printform.frm`, `wait_printform.frm`, `Mod_printslip.bas`, `Mod_scalecheck.bas` — không có `scaleform.frm`/`checkform.frm` kiểu cân như 2 file cũ). Toàn bộ 83 dòng `VBA-PRINT-*` trong matrix hiện tại **không đại diện cho máy in tem QR thật** — cần coi là audit một workbook liên quan nhưng KHÔNG PHẢI workbook sản xuất chính.
- **`SEMI CHECKER.xlsm`** (được audit là file "A" trong NHÓM 3 — SCALE) **không nằm trong danh sách 5 workbook xác nhận** — chưa rõ đây có phải máy thứ 7 đang hoạt động song song hay là công cụ tra cứu phụ không gắn máy cân thật nào. Cần xác nhận nghiệp vụ (xem `open-questions.md`).

→ **Đã hoàn tất (2026-07-17)** 2 audit bổ sung cho `chem_order.frm` (NHÓM 0, 16 dòng/44 procedure) và `DF028` (NHÓM 4-DF028, 51 dòng/308 procedure). Kết quả đã gộp vào `vba-migration-matrix.md`; tổng số dòng traceability toàn tài liệu nay là **422** (kiểm chứng PASS bằng `verify-matrix-counts.sh`). `pilot-blockers.md` đã cập nhật thêm 2 pilot blocker mới (CHEMICAL_CALL hoàn toàn chưa xây, QR_LABEL_PRINTING có 4 khoảng trống nghiệp vụ lớn).

---

## 3. Bảng Truy vết Workstation ↔ Workbook ↔ Giao diện (theo yêu cầu duyệt lần 3)

| Workstation | Workbook nguồn | UserForm chính | Công năng bắt buộc | Giao diện web tương ứng | API/Service | DB | Test parity |
|---|---|---|---|---|---|---|---|
| **CHEMICAL_CALL** | `1.báo phát AC XƯỞNG -193.xlsm` | `chem_order.frm` | Bảng đèn tín hiệu đỏ/xanh (ORDER/DONE) theo 8 máy×2 slot hóa chất, auto-refresh 15s; 32 nút gọi/xác nhận cấp hóa chất — xem `VBA-CHEM-003…016` (NHÓM 0, hoàn tất 2026-07-17) | **CHƯA CÓ** — 0 view/route tìm thấy | **CHƯA CÓ** — 0 Controller/route (chỉ có Model tĩnh `MachineChemicalChannel.php` không route nào dùng) | `app.machine_chemical_channels` (chỉ có `is_active` tĩnh — **thiếu cột lưu tín hiệu ORDER/DONE động**) | **Chưa có** — 13/16 dòng MISSING, pilot blocker mới |
| **PRODUCTION_ORDER** | `2.C3 grid load row lock id FB -192(QR).xlsm` | `mainform.frm` | Nạp/khóa dòng lưới hàng chờ, gửi lệnh nạp máy nhuộm — xem `VBA-DISPATCH-011…070` | `MachineQueue.vue`, `ProductionBatches.vue` | `GET /api/machine-dispatches`, `POST /api/machine-dispatches/{id}/claim`, `.../send` | `app.production_batches`, `app.machine_dispatches` | `MachineDispatchConcurrencyTest.php` |
| **QR_LABEL_PRINTING** | `3.DF028 ... jit qr sending - 15l special.xlsm` | `TO_SEND.frm` (+ `printform.frm`, `wait_printform.frm`) | Lưới 27 dòng gửi + lưới chờ 18×9 tô màu theo tuổi (24h/48h); tick `scale_check`; ConfirmRow (ghi `tbl_sentlog`); in tem kèm phân vùng kho B24 + chọn chế độ QR — xem `VBA-QRPRINT-*` (NHÓM 4-DF028, hoàn tất 2026-07-17) | `MachineQueue.vue` (một phần — thiếu lưới chờ 18×9), `PrintStation.vue` — **không khớp đầy đủ luồng `TO_SEND`/`wait_printform` thật** | `MachineDispatchController`, `WeighingJobController::printLabel` — schema đã có `scale_checked`/`raw_qr_dye`/`raw_qr_chemical` nhưng **0 controller đọc/ghi 3 cột này** | `app.machine_dispatches` (3 cột chưa nối dây), `app.material_labels`, `app.print_jobs` — thiếu bảng "sentlog" độc lập tương đương `tbl_sentlog` | `PrintJobPipelineTest.php` — chưa phủ scale_check/B24/QR-mode |
| **SMALL_SCALE** (×2 máy) | `4.semiauto-small scale ... DF026-027.xlsm` | `scaleform.frm` | Cân bán tự động, StableFilter, tare/delta, in tem trực tiếp — xem `VBA-SCALE-*` (workbook "C") | `WeighingStation.vue` | `POST /api/devices/{id}/readings`, `POST /api/weighing-items/{id}/confirm` | `app.weighing_jobs`, `app.weighing_job_items`, `app.scale_measurements` | `ScaleLiveWeightTest.php` (StableFilter/ExtractLastNumber **chưa có test — PB-1/PB-2**) |
| **LARGE_SCALE** (×1 máy) | `5.Semiauto- lockmove SEND OVER6 ... -221.xlsm` | `scaleform.frm` | Như SMALL_SCALE + `Mod_lockmoveform` (theo dõi/khóa vị trí form) + quy tắc dung tích lớn (250L — xem `open-questions.md` CH-BUS-005) — xem `VBA-SCALE-*` (workbook "B", **có bug màu ACCEPTED/REJECTED luôn REJECTED — R-10**) | `WeighingStation.vue` | như trên | như trên | như trên; **thêm**: cần golden test riêng cho bug REJECTED của workbook B trước khi tin dữ liệu lịch sử |

---

## 4. Lịch sử kết nối mạng (7 IP) — CHƯA đối chiếu xong với 6 máy đã xác nhận

Dữ liệu này được ghi nhận trước đó (nguồn: bảng lịch sử mạng workstation, không phải xác nhận nghiệp vụ trực tiếp). Giữ nguyên để tham chiếu nhưng **không dùng để suy luận vai trò máy** cho tới khi Admin đối chiếu thủ công.

| IP lịch sử | Vai trò gán trước đây (giả định, CHƯA xác nhận) | Đối chiếu với 6 máy đã xác nhận |
| :--- | :--- | :--- |
| 192.168.250.192 | ORDER_SCAN | Có thể là PRODUCTION_ORDER, hoặc CHEMICAL_CALL — **TO_CONFIRM** |
| 10.0.3.95 | ORDER_SCAN | Có thể là PRODUCTION_ORDER, CHEMICAL_CALL, hoặc máy dự phòng/không còn dùng — **TO_CONFIRM** |
| 192.168.250.196 | ORDER_SCAN | Có thể là PRODUCTION_ORDER, CHEMICAL_CALL, hoặc máy dự phòng/không còn dùng — **TO_CONFIRM** |
| 10.0.19.74 | WEIGH (TO_CONFIRM) | Có thể là SMALL_SCALE #1, SMALL_SCALE #2, hoặc LARGE_SCALE — **TO_CONFIRM** |
| 10.0.19.171 | WEIGH (TO_CONFIRM) | như trên — **TO_CONFIRM** |
| 192.168.100.221 | WEIGH (TO_CONFIRM) | như trên — **TO_CONFIRM** |
| 10.0.19.79 | LABEL_PRINTING | Có thể là QR_LABEL_PRINTING — số lượng khớp (1=1) nhưng workbook liên kết trước đó (in tem Copower / QR PRINTER) **không khớp** workbook DF028 đã xác nhận — **TO_CONFIRM** |

> **Số học không khớp:** 7 IP lịch sử vs 6 máy đã xác nhận — nhóm WEIGH khớp số lượng (3 IP = 2 SMALL_SCALE + 1 LARGE_SCALE), nhóm PRINT khớp số lượng (1 IP = 1 QR_LABEL_PRINTING) nhưng **không có IP lịch sử nào được gán sẵn cho CHEMICAL_CALL**, trong khi nhóm ORDER_SCAN có 3 IP nhưng chỉ có 1 máy PRODUCTION_ORDER được xác nhận. Giả thuyết hợp lý nhất (CHƯA xác nhận): 1 trong 3 IP "ORDER_SCAN" thực chất là máy CHEMICAL_CALL, IP thứ 3 có thể là máy dự phòng/thử nghiệm/không còn hoạt động. Đây là câu hỏi cần Admin xác nhận trực tiếp — xem `open-questions.md` mục CH-BUS-009 (mới).

---

## 5. Các "workstation" viết mới KHÔNG có bằng chứng vận hành — không tính vào 6 máy xác nhận

`legacy-to-target-architecture.md` (Bước 6, 7, 8) và tài liệu tái cấu trúc trước đó có đề cập `WS-TRANS-01` (Vận chuyển), `WS-TANK-01` (Xác nhận tới thùng), `WS-FEED-01` (Cấp vào máy). Ba mục này:
- **Không có workbook VBA nguồn nào** (đã xác nhận qua toàn bộ đợt audit 355+ dòng — không VBA nào đề cập vận chuyển/tới thùng/cấp máy).
- **Không nằm trong 6 máy nghiệp vụ người dùng vừa xác nhận.**
- → Phân loại lại là **C. OPTIONAL EXTENSION** (tính năng mới, không phải di trú), xem `vba-migration-matrix.md` mục "BẢNG ƯU TIÊN HÓA" và phần A/B/C bổ sung. Không dùng các mục này để tuyên bố "đã hoàn thành workstation" khi chưa có bằng chứng máy vật lý tương ứng đang chạy.

Tương tự, `RECIPE` (Công thức — `CÔNG THỨC SẢN XUẤT CHUNG.xlsm`, `TraHeSo`) và `TROUBLESHOOTING` (`troubleshooting_support engine_DF.xlsm`) là 2 workbook nghiệp vụ hợp lệ (có VBA nguồn, có audit procedure đầy đủ ở NHÓM 1 và NHÓM 5) nhưng **không có bằng chứng gắn với 1 máy nhà xưởng vật lý cố định nào** trong 6 máy vừa xác nhận. Giả định hợp lý (CHƯA xác nhận): đây là công cụ dùng ở máy tính văn phòng/kỹ thuật, không phải kiosk nhà xưởng khóa công đoạn — cần Admin xác nhận trước khi thiết kế các công cụ này theo mô hình "workstation khóa cứng" giống 6 máy trên. Xem `open-questions.md` CH-BUS-010 (mới).

---

## 6. Kiểm kê Chi tiết Thiết bị — SMALL_SCALE / LARGE_SCALE

Cập nhật theo cơ cấu đã xác nhận (thay cho "chờ xác định" trước đây):

1. **SMALL_SCALE (2 máy)** — dùng chung 1 workstation type/profile cấu hình (không phải 2 profile riêng):
   - Nguồn: `4.semiauto-small scale - delta-stable-final_DF026-027.xlsm`.
   - Vật tư: lượng màu nhỏ, cân thủ công (theo mô tả người dùng — "02 máy cân thủ công cho lượng màu nhỏ").
   - Cân điện tử: đọc qua Putty log (`ModRead_putty_log.bas`), cổng Serial.
   - Đặc thù: `Mod_delta_raw.bas`, `Mod_UI_processcolor.bas` (ngưỡng dung sai ±1%, 3 màu vàng/xanh/đỏ).
2. **LARGE_SCALE (1 máy)**:
   - Nguồn: `5.Semiauto- lockmove SEND OVER6 - delta-stable-final-221.xlsm`.
   - Vật tư: khối lượng lớn ("01 máy hỗ trợ cân khối lượng lớn" — theo mô tả người dùng).
   - Đặc thù riêng so với SMALL_SCALE: `Mod_lockmoveform.bas` (theo dõi/khóa vị trí form — có bug thiếu safeguard chống chồng timer, xem `vba-migration-matrix.md` NHÓM 3 mục 0), bug màu ACCEPTED/REJECTED (R-10 trong `risks-and-assumptions.md`), khả năng áp dụng quy tắc dung tích tối thiểu 250L (CH-BUS-005).
   - **Chưa xác nhận**: quy tắc 250L áp dụng cho LARGE_SCALE hay không — xem `open-questions.md`.

Cổng kết nối / giao thức / thiết bị phụ trợ: giữ nguyên như phiên bản trước (Serial RS232/USB-to-Serial, Putty log, máy in TSC, scanner keyboard-wedge) — không có thông tin mới thay đổi phần này.

---

## 7. Kiến trúc Xác thực Trạm (Device Authentication)

Không thay đổi so với phiên bản trước — mô hình Workstation UUID + Registration Token + Device Fingerprint + IP chỉ là metadata vẫn đúng và áp dụng cho cả 6 máy đã xác nhận:

- **Workstation UUID:** Định danh duy nhất bất biến của trạm sinh ra tại database.
- **Registration Token:** Khi trạm đăng ký lần đầu, Admin cấp một token bí mật dùng một lần. Agent sẽ trao đổi token này lấy **Workstation Certificate / API Token** dài hạn.
- **Device Fingerprint:** Vân tay phần cứng (gồm Hostname, MAC Address, CPU ID, OS serial) được Agent trích xuất và gửi kèm trong mỗi request để phát hiện giả mạo trạm.
- **IP Address metadata:** Địa chỉ IP hiện tại chỉ đóng vai trò là thuộc tính mạng phục vụ kiểm tra kết nối và audit trail, không dùng làm định danh xác thực.
