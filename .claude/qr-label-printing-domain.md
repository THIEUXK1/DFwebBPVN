# Domain: QR & Label Printing (qr-label-printing-domain.md)

Lập 2026-07-17. Nguồn nghiệp vụ gốc: `3.DF028 formulas - PRINTER LANDSCAPE - jit qr sending - 15l special.xlsm` (`TO_SEND.frm`/`printform.frm`/`wait_printform.frm`, 308 procedure — audit đầy đủ tại `vba-migration-matrix.md` NHÓM 4-DF028). Database: `RECORD.accdb` = **RECORD_A** (xem `legacy-database-mapping.md`). Tài liệu THIẾT KẾ ĐỀ XUẤT — chưa migration, chưa code sản xuất.

---

## 1. Truy vết vòng đời `tbl_SentLog` (bảng bắt buộc theo yêu cầu mục 6)

| VBA source | Access table/field | Web table/field | Writer | Reader | Trigger condition | Status |
|---|---|---|---|---|---|---|
| `Mod_load_input.LoadAllVD_Input` (workbook C3/MID — DISPATCH, chưa audit sâu phần INSERT) | `TBL_INPUT_ALL` (17 cột, RECORD_A) | *(chưa có bảng riêng ở web — hợp nhất vào `app.machine_dispatches` với `queue_state='INPUT'`)* | **Nghi vấn** workbook C3/MID (`MoveToSend`/nhập liệu) | `DF028.Mod_load_input.LoadAllVD_Input` (đọc theo từng máy VD01-18) | Operator nhập liệu tại PRODUCTION_ORDER | **BLOCKED** — chưa audit sâu ai/khi nào INSERT vào `TBL_INPUT_ALL`, chỉ xác nhận DF028 KHÔNG phải nơi ghi (chỉ đọc + UPDATE `scale_check`) |
| `DF028.TO_SEND.HandleSendPrint` → `printform`/`wait_printform` | `tbl_tosend` (RECORD_A, đọc theo id) | `app.machine_dispatches` (`queue_state='TO_SEND'`) | *(chuyển từ INPUT sang TOSEND — cơ chế cụ thể chưa audit, nghi vấn `Mod_move_tosend.MoveToSend` mồ côi trong workbook B cũ)* | `DF028.TO_SEND.LoadGrid` (27 dòng) | Operator/hệ thống chuyển trạng thái sau khi nhập đủ thông tin | **PARTIALLY_IMPLEMENTED** — bảng đích đã có `queue_state`, nhưng cơ chế chuyển INPUT→TO_SEND chưa xác nhận |
| `DF028.printform/wait_printform.btn_print_scaleslip_Click` → `Mod_printslip.PrintSlip_70x100` | *(không ghi DB — chỉ đọc `tbl_tosend`/`tbl_input_all` để lấy `rawqrdye`/`rawqrchem`)* | — | — | — | Operator bấm nút "In" | Xem `b24-warehouse-routing.md` cho logic tính QR |
| `DF028.wait_printform.btn_print_scaleslip_Click` → `Mod_scalecheck.MarkScaleCheckYes_ByID` | `tbl_input_all.scale_check` (UPDATE) | `app.machine_dispatches.scale_checked` **(cột đã có, 0 controller ghi)** | `DF028` | *(chưa có reader ở web)* | Ngay sau khi in tem thành công (chỉ nhánh `wait_printform`, KHÔNG áp dụng cho `printform`) | **MISSING** — field có sẵn trong schema, cần nối dây Controller |
| `DF028.TO_SEND.check1..27_Click` → `SavePrintCheck` | `tbl_tosend.scale_check` (UPDATE) | `app.machine_dispatches.scale_checked` | `DF028` | — | Operator tick checkbox thủ công trên lưới 27 dòng (**độc lập với** cơ chế tự động ở dòng trên) | **MISSING** |
| **`DF028.TO_SEND.ConfirmRow`** (gọi từ `OK1_Click..OK27_Click`) | **INSERT `tbl_SentLog`** (copy toàn bộ 17 cột từ `tbl_tosend` + set `TIME3=Now`) rồi **DELETE `tbl_tosend`** | `app.machine_dispatches` (UPDATE `queue_state='SENT'` tại chỗ — KHÔNG move-and-delete) | **`DF028` — nguồn ghi (INSERT) DUY NHẤT tìm được cho `tbl_sentlog` trong toàn bộ đợt audit** | `DF028.Mod_load_sentlog_sheet.LoadSent_Last24h` (đọc 48h gần nhất, không rõ call site) | Operator bấm nút OK (xác nhận hoàn tất/lưu trữ vĩnh viễn) | **PARTIALLY_IMPLEMENTED** — mô hình dữ liệu đích khác hẳn (state machine 1 bảng thay vì move-between-tables); **KHÔNG có bảng "sentlog" độc lập tương đương** ở web, chỉ có `AuditLog` sự kiện (không phải bản sao đầy đủ dữ liệu dòng) |
| — | `tbl_SentLog.rawqrdye`/`rawqrchem` (Memo, copy nguyên văn từ `tbl_tosend`) | `app.machine_dispatches.raw_qr_dye`/`raw_qr_chemical` **(cột đã có, 0 controller ghi)** | `ConfirmRow` (copy) | `DF028.printform.LoadRawQR` (hiển thị lại khi in lại) | — | **MISSING** |
| *(không có VBA nào)* | `tbl_ToSend2` (RECORD_A, 696 dòng, dừng 2025-11-20) | *(chưa xác định bảng đích)* | **Không có nguồn ghi nào tìm được** trong 5 workbook đã audit | *(không có VBA nào đọc)* | — | **BLOCKED** — schema khác `tbl_ToSend` (thiếu `rawqrdye`/`rawqrchem`/`scale_check`), khả năng cao là bảng của 1 đời workbook cũ hơn (trước khi 3 cột QR/scale_check được thêm vào) — cần workbook nguồn P0 còn thiếu (`source-files-missing.md`) để xác nhận |
| *(không có VBA nào trong 5 workbook)* | `WAITING` (PK=`ID1`, 57 dòng), `tbl_Waiting` (71 dòng) | *(chưa xác định)* | **Không có nguồn ghi nào tìm được** | *(không có nguồn đọc nào tìm được)* | — | **BLOCKED** — cùng nhận định `tbl_ToSend2`, cần file thiếu |
| *(không có VBA nào)* | `tblSync` (0 dòng) | — | — | — | — | **NOT_REQUIRED_CONFIRMED** (đề xuất, chờ xác nhận) — rỗng hoàn toàn, không VBA nào tham chiếu; đề xuất KHÔNG thiết kế cơ chế round-robin đa Front-End tương đương trừ khi người dùng xác nhận tính năng từng chạy thật |

**Kết luận (trả lời trực tiếp yêu cầu mục 6):** `tbl_SentLog` **CÓ** nguồn ghi thật, đã xác định chính xác (`DF028.TO_SEND.ConfirmRow`) — không đóng hạng mục này chỉ vì thấy tên bảng giống trong migration, mà vì đã truy vết được procedure cụ thể + đối chiếu schema cột-theo-cột (17 cột `tbl_SentLog` khớp gần như tuyệt đối với `app.machine_dispatches`, xem `database-inventory.md` Mục 2). Điều kiện ghi: operator bấm OK — không có điều kiện chống trùng nào trong VBA gốc (không kiểm tra đã Confirm chưa trước khi cho phép bấm lại, ngoại trừ guard `idVal=0 hoặc rsSrc.EOF → Exit Sub` khi dòng đã bị xoá).

---

## 2. Luồng xác nhận thực tế (11 bước, đối chiếu code — theo yêu cầu mục 5.1)

| # | Bước | Procedure VBA tương ứng | Trạng thái web hiện tại |
|---|---|---|---|
| 1 | Tải danh sách đơn chờ gửi/in | `TO_SEND.LoadGrid` (27 dòng) + `Mod_load_input.LoadAllVD_Input` (lưới chờ 18×9) | **PARTIALLY_IMPLEMENTED** — chỉ có 1 danh sách (`MachineQueue.vue`), thiếu lưới chờ 18×9 |
| 2 | Operator chọn/xác nhận từng dòng | `check1..27_Click` (tick scale_check) | **MISSING** |
| 3 | Xác định loại QR cần tạo | `Mod_printslip` Mục 3 (PROCESS/EXTRA/FB theo B24) | **MISSING** — không có logic mode ở backend |
| 4 | Xác định dữ liệu thuốc nhuộm/hóa chất | `LoadRawQR`/`ParseQR` (đọc `rawqrdye`/`rawqrchem` đã lưu, KHÔNG tính lại) | **MISSING** — field có sẵn, chưa nối dây đọc |
| 5 | Xác định vùng kho / B24 | `Mod_printslip` Mục 2 | **MISSING** — xem `b24-warehouse-routing.md` |
| 6 | Tạo dữ liệu QR thô | `GenerateQRCode` (gọi `api.qrserver.com` — VI PHẠM CLAUDE.md) | **REPLACED_BY_PLATFORM** — `PrintJobController` đã sinh TSPL QRCODE nội bộ, đúng hướng, nhưng payload/mode khác B24 |
| 7 | Gửi lệnh in | `ws.PrintOut` (Excel print trực tiếp, không qua hàng đợi) | **REPLACED_BY_PLATFORM** — `LabelPrinter.cs` qua Agent, kiến trúc tốt hơn |
| 8 | Ghi nhận kết quả in | Không có (VBA không track kết quả in, chỉ biết "đã gọi PrintOut") | **PARTIALLY_IMPLEMENTED** — web có print job status, VBA không có gì để đối chiếu |
| 9 | Cập nhật `scale_checked` đúng thời điểm | `MarkScaleCheckYes_ByID` (chỉ nhánh `wait_printform`, KHÔNG áp dụng `printform`) | **MISSING** |
| 10 | Ghi vào bảng lịch sử tương đương `tbl_SentLog` | `ConfirmRow` | **PARTIALLY_IMPLEMENTED** — xem Mục 1 |
| 11 | Không gửi/ghi lịch sử 2 lần | **VBA gốc KHÔNG CÓ cơ chế chống trùng nào** (chỉ guard `idVal=0`) | **BLOCKED** — cần quyết định: giữ nguyên hành vi gốc (không chặn) hay thắt chặt (cần khai báo B/C rõ ràng) |

## 3. Không gọi QR bên ngoài (mục 5.2) — hiện trạng & đề xuất

- **VBA gốc vi phạm:** `mdQRCodegen.GenerateQRCode` gọi `https://api.qrserver.com/v1/create-qr-code/` — xác nhận tồn tại ở **3 workbook sản xuất song song** (2 file PRINT cũ + DF028), cùng gốc code.
- **Web hiện tại đã đúng hướng:** `WeighingJobController`/`PrintJobController` sinh TSPL `QRCODE` nội bộ qua Agent — không gọi ra ngoài.
- **Đề xuất bổ sung (đối chiếu mục 5.2, hiện CHƯA có ở web):**
  - Service `QrPayloadService` lưu **payload QR nguyên bản** (không chỉ ảnh) — hiện `raw_qr_dye`/`raw_qr_chemical` đã có cột nhưng chưa ghi.
  - Version cho định dạng QR (`qr_payload_version` — vì VBA có ít nhất 4 định dạng khác nhau: qrDye/qrChem/qrProcess/qrExtra/qrFB, mỗi định dạng có thể đổi theo thời gian).
  - Khả năng tái tạo lại tem từ dữ liệu lịch sử — cần `raw_qr_*` lưu đủ, không chỉ lưu ảnh PNG.

## 4. Tách nghiệp vụ in khỏi Controller (mục 5.3) — đề xuất service

```
QrPayloadService     -- build qrDye/qrChem/qrProcess/qrExtra/qrFB theo mode (dựa trên b24-warehouse-routing.md)
LabelTemplateService -- chọn template theo loại tem (70x100 slip, tem QR chuẩn...)
PrintJobService       -- tạo print job, quản lý trạng thái queued/printing/printed/failed/cancelled
PrinterGateway        -- interface giao tiếp Local Agent (đã có LabelPrinter.cs phía Agent, cần gateway phía backend)
SentLogService        -- ConfirmRow tương đương: chuyển machine_dispatches sang SENT + ghi sent_log_entries (đề xuất bảng riêng, xem Mục 5)
```

Hiện tại `WeighingJobController`/`PrintJobController` có khả năng đang gộp toàn bộ logic — **cần audit code hiện tại (ngoài phạm vi VBA) để xác nhận mức độ gộp trước khi refactor** — đây là việc của Phase C (thiết kế), chưa thực hiện trong lượt này.

## 5. Đề xuất bảng SentLog độc lập (đóng khoảng trống Mục 1 dòng ConfirmRow)

```
app.machine_dispatch_sent_log_entries   -- MỚI, tương đương tbl_SentLog thật
  id, dispatch_id (FK app.machine_dispatches, nullable sau khi archive),
  color, code, machine_id, tank, level, confirm_1, confirm_2,
  sending_value, sent_value, is_sent, scale_checked,
  raw_qr_dye, raw_qr_chemical, qr_payload_version,
  confirmed_at, legacy_id, legacy_source, created_at
```

Lý do cần bảng riêng thay vì chỉ dựa vào `AuditLog`: `tbl_SentLog` VBA là **bản sao đầy đủ dữ liệu dòng tại thời điểm confirm** (snapshot), không phải sự kiện đơn thuần — `AuditLog` (`action=DISPATCH_TO_MACHINE`) hiện tại ghi sự kiện nhưng chưa xác nhận có lưu đủ snapshot dữ liệu hay không (**BLOCKED** — cần đọc code `AuditLog` hiện tại để xác nhận, ngoài phạm vi audit VBA).

## 6. Quản lý hàng đợi in (mục 5.4)

Đối chiếu với kiến trúc Agent đã có (`LabelPrinter.cs`, `agent/` — đã tồn tại từ trước, KHÔNG phải VBA): cần xác nhận (ngoài phạm vi audit VBA lần này) code hiện tại đã có đủ 5 trạng thái `queued/printing/printed/failed/cancelled` và ghi đủ 9 trường (workstation/printer/template/payload/số bản/người/thời gian/kết quả/lỗi) hay chưa — đây là việc audit code web hiện có, KHÔNG phải audit VBA, đề xuất làm ở Phase B tiếp theo nếu cần.

## 7. Đối chiếu 2 file P0 từng ghi "thiếu" (mục 10)

Xem `source-files-missing.md` mục cập nhật — DF028 tên trùng gần như hoàn toàn 2 file `DF002...15l special-27rows.xlsm`/`DF002 no formulas...15l special.xlsm`. Bằng chứng đối chiếu:
- Tên workbook: khớp cụm `PRINTER LANDSCAPE - jit qr sending - 15l special` (chỉ khác "DF002"↔"DF028", "no formulas"↔"formulas").
- UserForm: DF028 có `TO_SEND.frm` với lưới **27 dòng** — khớp "27rows" trong tên file #4.
- Chức năng gửi QR/ghi `tbl_SentLog`: DF028 có đầy đủ (`ConfirmRow`, `GenerateQRCode`).
- Nhánh 15L: **KHÔNG tìm thấy code riêng** (xem `b24-warehouse-routing.md` Mục 7) — không thể xác nhận DF028 đã "bao phủ" đúng nhánh 15L của 2 file kia hay chưa vì cả DF028 lẫn 2 file kia (chưa có trong tay) đều không rõ ràng về điểm này.

**Trạng thái đề xuất: `PARTIALLY_RESOLVED`** (không tự đóng thành `RESOLVED`) — tên/kiến trúc/chức năng chính khớp mạnh, nhưng nhánh "15L special" cụ thể chưa xác minh được ở cả 2 phía. Ghi ngày xác nhận: 2026-07-17, nguồn: audit NHÓM 4-DF028 + đối chiếu tên file.
