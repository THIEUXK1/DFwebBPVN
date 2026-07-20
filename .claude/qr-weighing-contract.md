# Giao ước dữ liệu mã QR vật tư (qr-weighing-contract.md)

Tài liệu này đóng vai trò là Giao ước Hợp đồng (Data Contract) đặc tả cấu trúc mã QR được tạo ra từ Trạm in nhãn (`QR_LABEL_PRINTING`) và được đọc tại các Trạm cân (`SMALL_SCALE` / `LARGE_SCALE`).

---

## 1. Nguyên tắc thiết kế (Design Principles)
1.  **Tính tương thích ngược (C-04):** Định dạng chuỗi thô (raw string) trong mã QR được thiết kế giống hệt với mã QR sinh ra từ Excel/VBA cũ. Điều này giúp các máy quét cầm tay Honeywell/Zebra hiện tại tại xưởng không cần phải lập trình lại phần cứng (firmware) để đọc mã.
2.  **Tính bất biến (Immutability):** Chuỗi QR sau khi sinh ra và in ấn là bất biến, mang tính đại diện duy nhất cho một mẻ/lô nguyên liệu cần cân.
3.  **Tự chứa thông tin (Self-Containing):** Payload chứa đầy đủ định danh máy, mẻ, mã màu, mã hàng, số kênh, và danh sách các nguyên liệu kèm theo định mức để trạm cân có thể tự phân giải mà không bị phụ thuộc hoàn toàn vào mạng LAN thời gian thực.

---

## 2. Đặc tả các kiểu Payload QR

### 2.1. Kiểu QR Thuốc nhuộm (DYE Payload)
- **Tên Mode trong VBA:** `qrDye`
- **Giao thức định dạng:**
  `#{color}-{code}-{machine}-{level}-{rawDye}`
- **Ký tự ngăn cách (Delimiter):** Dấu gạch ngang `-`.
- **Ví dụ chuỗi thô:**
  `#RED01-N20-VD006-450-1-A01-12.5-2-A02-4.25-3-A03-0.55`
- **Cách phân tích (Parse) trong VBA & Web:**
  1. Loại bỏ ký tự `#` ở đầu.
  2. Tách chuỗi (Split) bằng dấu `-`.
  3. 4 phần tử đầu tiên tương ứng với: `color`, `product_code`, `machine_code`, `level_code`.
  4. Từ phần tử thứ 5 trở đi là danh sách nguyên liệu nhuộm lặp lại theo nhóm 3 phần tử: `sequence_no`, `material_code`, `planned_weight` (ví dụ: `1`, `A01`, `12.5`).

---

### 2.2. Kiểu QR Hóa chất phụ trợ (CHEM Payload)
- **Tên Mode trong VBA:** `qrChem`
- **Giao thức định dạng:**
  Các trường phân tách bởi ký tự xuống dòng `\r\n` (CRLF). Dòng cuối cùng kết thúc bằng ký tự `#`.
- **Cấu trúc dòng:**
  - Dòng 1: Mã máy nhuộm (Chuẩn hóa VD###, ví dụ `VD006`)
  - Dòng 2: Ký tự đầu của Thùng (ví dụ `1` từ thùng `1A`)
  - Dòng 3: `#` + `color` + `-` + `code` (ví dụ `#RED01-N20`)
  - Dòng 4: Số ngẫu nhiên từ `1` đến `9`
  - Dòng 5: Mức nước (`level_code`)
  - Các dòng tiếp theo: Cặp dòng `mã hóa chất` và `khối lượng` lặp lại (tối đa 9 cặp).
  - Dòng cuối cùng: `#`
- **Ví dụ chuỗi thô:**
  ```text
  VD006
  1
  #RED01-N20
  4
  450
  C_SALT_01
  150.5
  C_SODA_02
  45
  #
  ```

---

### 2.3. Kiểu QR Công đoạn sản xuất (PROCESS Payload)
- **Tên Mode trong VBA:** `qrProcess`
- **Giao thức định dạng:**
  Các trường ngăn cách bởi ký tự xuống dòng `\r\n` (CRLF).
- **Cấu trúc dòng:**
  - Dòng 1: `color` + `-` + `code` + ` ` + `timestamp` (YmdHi)
  - Dòng 2: `machine` + `-` + `tank` + `-` + `newLevel` (Mực nước đã hiệu chỉnh)
  - Dòng 3: Loại thuốc nhuộm (ví dụ `Nylon Dyes` / `Cation Dyes` / `Disperse Dyes`)
- **Ví dụ chuỗi thô:**
  ```text
  RED01-N20 202607171930
  VD006-1A-450
  Nylon Dyes
  ```

---

## 3. Quản lý phiên bản QR (QR Versioning Contract)
Để hỗ trợ nâng cấp phần mềm trong tương lai mà không làm vỡ tính tương thích ngược, cấu trúc QR được định nghĩa thêm các trường siêu dữ liệu (Metadata) lưu dưới database tại bảng `app.qr_payloads`:

| Tên trường DB | Kiểu dữ liệu | Vai trò |
|---|---|---|
| `payload_type` | `VARCHAR(32)` | Xác định loại QR: `DYE`, `CHEM`, `PROCESS`, `EXTRA`, `FB` |
| `payload_version` | `INTEGER` | Phiên bản cấu trúc (mặc định khởi tạo `1`) |
| `payload_hash` | `VARCHAR(64)` | Mã hash SHA-256 của chuỗi thô để đối chiếu nhanh |
| `raw_payload` | `TEXT` | Chuỗi ký tự thô đầy đủ in ra tem |

### Quy tắc phân giải phiên bản (Version Parsing Rule)
- Khi máy quét đọc được mã QR tại trạm cân, Agent sẽ đẩy chuỗi quét lên Backend API.
- Bộ phận phân giải (`QrPayloadService`) sẽ kiểm tra ký tự bắt đầu:
  - Nếu chuỗi bắt đầu bằng `#` và có dấu `-` ngăn cách: Tự động phân giải theo cấu trúc `DYE (v1)`.
  - Nếu chuỗi chứa ký tự xuống dòng `\r\n` và kết thúc bằng `#`: Tự động phân giải theo cấu trúc `CHEM (v1)`.
  - Nếu xuất hiện định dạng mới trong tương lai, hệ thống sẽ sử dụng tiền tố `DF:V{version}:` ở đầu chuỗi để định tuyến bộ phân giải tương ứng.

> [!IMPORTANT]
> **Cập nhật triển khai 2026-07-17 (đợt "Tách riêng CHEMICAL_CALL và hoàn thiện liên kết"):** Trước bản vá này, mục "Cách phân tích (Parse) trong VBA & Web" ở Mục 2.1 chỉ là **mô tả**, chưa có code — `ScannerController::scan()` (endpoint quét thật duy nhất tồn tại) chỉ hiểu định dạng giả `DF:ORDER:<uuid>`/`DF:MATERIAL_LABEL:<uuid>`, hoàn toàn không đọc được payload `#color-code-machine-level-...` mà `QrPayloadService` thực sự in ra. Đã trích lại nguyên văn `txt_color_AfterUpdate` bằng olevba trực tiếp từ `4.semiauto-small scale - delta-stable-final_DF026-027.xlsm` (dòng 973-1045) để xác nhận đúng thuật toán VBA (không tra UUID — chỉ tách chuỗi và khớp theo **color+code**), rồi implement `QrPayloadService::parseDyeScan()` (port verbatim) + endpoint mới `POST /api/scanner/scan-dye-qr` (`ScannerController::scanRawDyeQr`). Có test round-trip (`QrPayloadServiceTest`) và test E2E thật (`QrScanToWeighingE2ETest`: QR_LABEL_PRINTING sinh QR → quét → tạo WeighingJob → ghi `app.correlation_links`).
>
> **Phạm vi đã xong:** chỉ payload **DYE**. `app.correlation_links` trước bản vá tồn tại trong schema nhưng chưa từng được ghi bởi bất kỳ code nào — nay được ghi tại `ScannerController::scanRawDyeQr` với `match_method='DETERMINISTIC_COMPOSITE'`, khớp theo color+code+machine (không dùng timestamp).
>
> **Đã kiểm chứng KHÔNG cần làm (không phải thiếu sót):** trích lại toàn bộ VBA của CẢ HAI workbook cân (`4.semiauto-small scale...xlsm` và `5.Semiauto- lockmove SEND OVER6...xlsm`, olevba, 2026-07-17) — không tìm thấy bất kỳ handler nào đọc lại payload `qrChem`/`qrProcess`/`qrExtra`/`qrFB` (không có control nào split theo CRLF ngoài `TrimPuttyLog` — hàm cắt log cân, không liên quan QR). Cả 2 workbook chỉ có đúng 1 chỗ nhắc tới "chem": `If InStr(sLower,"chem")>0 Then s = Left(s, InStr(...)-1)` — tức là **CẮT BỎ và bỏ qua** nếu chuỗi quét được có chứa "chem", không xử lý tiếp. Kết luận: `qrChem`/`qrProcess`/`qrExtra`/`qrFB` là payload **chỉ để in ra tem giấy cho người đọc bằng mắt** (hoặc dùng ở khâu khác chưa xác định), KHÔNG được phần mềm cân quét lại. Vì vậy KHÔNG xây dựng thêm endpoint "scan-chem-qr" — làm vậy sẽ là bịa hành vi không có căn cứ VBA.
