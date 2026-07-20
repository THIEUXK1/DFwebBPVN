# So sánh Phiên bản Workbook VBA (vba-version-comparison.md)

Tài liệu này đối chiếu các workbook VBA cùng nhóm chức năng theo **nội dung code thật** (không theo tên file), theo yêu cầu Section VI của quy trình rà soát VBA→Web. Kết quả tổng hợp từ 5 nhóm audit song song ngày 2026-07-16. Xem chi tiết dòng-theo-dòng trong [vba-migration-matrix.md](file:///F:/DF/.claude/vba-migration-matrix.md).

---

## 0. Giới hạn quan trọng: nhiều cặp phiên bản không thể so sánh vì thiếu file

Yêu cầu audit gốc liệt kê nhiều workbook có hậu tố `(1)` hoặc tiền tố `Copy of` — đúng dạng "nhiều phiên bản của cùng 1 công cụ" mà mục này cần so sánh. Tuy nhiên **không có file nào trong nhóm này thực sự có mặt tại `F:\DF`** — chỉ có 1 bản duy nhất của mỗi công cụ:

| Công cụ | Bản có mặt | Bản `(1)`/`Copy of` được liệt kê nhưng KHÔNG có mặt |
|---|---|---|
| Bảng lực căng | 张力表-NEW VERSION.xlsm | 张力表-NEW VERSION(1).xlsm |
| Lượng dùng thuốc nhuộm | df lượng dùng thuốc nhuộm.xlsm | df lượng dùng thuốc nhuộm(1).xlsm |
| Khóa dòng điều phối (MID) | MACHINE_ID_LOCKED.xlsm | MACHINE_ID_LOCKED(1).xlsm, Copy of MACHINE_ID_LOCKED.xlsm |
| Khóa dòng điều phối (C3) | C3 grid load row lock id FB -.xlsm | C3 grid load row lock id FB -(1).xlsm |
| Kiểm tra bán thành phẩm | SEMI CHECKER.xlsm | SEMI CHECKER(1).xlsm |
| Cân bán tự động | semiauto- lockmove SEND OVER6 - delta-stable-final.xlsm, semiauto-small scale - delta-stable-final.xlsm | semiauto-Checker plus-accept_reject semi 9rows lockmove SEND OVER6 - low stand1.xlsm, semiauto-chem-deltaRaw 8rows.xlsm |
| In tem | in tem Copower.xlsm | in tem Copower(1).xlsm |
| In tem/QR | QR PRINTER-send to access- NEW 9ROWS BIG QR.xlsm | QR PRINTER...(1).xlsm, DF002 - PRINTER - qr sending - 15l special-27rows.xlsm, DF002 no formulas - PRINTER LANDSCAPE - jit qr sending - 15l special.xlsm |

**Hệ quả quan trọng nhất:** bảng `legacy_df_data.tbl_ToSend2` / `WAITING` / `tbl_Waiting` / `tblSync` **không hề được ghi/đọc bởi bất kỳ VBA nào hiện có** — logic tạo ra các bảng này chắc chắn nằm trong 1 trong các file `(1)`/`Copy of` bị thiếu ở nhóm "Khóa dòng điều phối". Xem chi tiết mục 3 bên dưới. **[Đính chính 2026-07-17 sau kiểm kê dữ liệu thật — `p0-analysis/p0-d-legacy-tables-inventory.md`]:** `tbl_ToSend2` 696 dòng (dừng ghi 28/11/2025), `WAITING` 57 dòng (ID/TIME 100% rỗng), `tblSync` **rỗng hoàn toàn (0 dòng)** — không phải "có dữ liệu thật" như bản đầu ghi; phát hiện thêm bảng thứ 4 `tbl_Waiting` (71 dòng) bị script transform gắn nhãn "unshifted" nhưng dữ liệu thật cho thấy CŨNG lệch cột cùng kiểu với `WAITING`.

**Khuyến nghị:** người dùng bổ sung các file trên vào `F:\DF\` (đặc biệt ưu tiên nhóm điều phối và nhóm in tem — 2 nhóm có phát hiện MISSING lớn nhất) để hoàn thành đợt so sánh phiên bản và xác nhận không bỏ sót nhánh nghiệp vụ nào.

---

## 1. Nhóm Công thức: `张力表-NEW VERSION.xlsm` vs `CÔNG THỨC SẢN XUẤT CHUNG - new.xlsm`

**Kết luận: bản sao byte-for-byte, không phải nhánh nghiệp vụ khác.** Toàn bộ workbook `张力表-NEW VERSION.xlsm` (ThisWorkbook/Sheet1/Sheet2 đều rỗng) chỉ chứa **một hàm duy nhất có ý nghĩa**: `TraHeSo(Code, Width, Tiao)` — giống hệt 100% hàm cùng tên trong `Module2.bas` của `CÔNG THỨC SẢN XUẤT CHUNG - new.xlsm`. Việc trùng lặp xác nhận `TraHeSo` là hàm dùng chung quan trọng giữa 2 workbook — không phải code thử nghiệm cô lập, càng làm phát hiện "MISSING" ở nhóm Recipe (xem mục 5 dưới) nghiêm trọng hơn.

---

## 2. Nhóm Điều phối: `C3 grid load row lock id FB -.xlsm` (C3) vs `MACHINE_ID_LOCKED.xlsm` (MID)

**Kết luận: 2 form cùng tên "mainform" nhưng phục vụ 2 mục đích khác nhau, dùng chung một lớp module thư viện.** Không phải "bản cũ/bản mới" của cùng 1 màn hình — C3 là màn hình **nhập liệu + duyệt điều phối** (lưới 81 ô chờ duyệt), còn MID là màn hình **dashboard tổng quan theo máy VD01-18** (lịch sử 24h + top 6 hàng chờ mỗi máy). Các khác biệt thật (không phải đổi tên biến vô hại):

| Khác biệt | C3 | MID | Ý nghĩa |
|---|---|---|---|
| Quy tắc dung tích tối thiểu | Chặn lưu nếu machine∈{VD06-VD13} + tank∈{1A,2B} + level<250L | **Không kiểm tra** | Rủi ro nghiệp vụ thật — 2 bản có business rule KHÔNG đồng nhất; cần hỏi bản nào đúng trước khi thiết kế API `store` |
| Slot Tank | 5 lựa chọn (1A,2B,3C,4D,**FB**) | 4 lựa chọn (1A,2B,3C,4D — không có FB) | Khác biệt số lượng tank hỗ trợ |
| Cơ chế polling | `Mod_TIMER_AUTOGRID`, chu kỳ 15 giây, làm mới lưới 81 ô chờ duyệt | `Mod_time3min`, chu kỳ 3 phút, làm mới dashboard theo máy | Đúng như phân tích ở trên — 2 mục đích khác nhau |
| Kết nối Access (`GetDB_Read`) | Không có retry | Có retry 5 lần/1 giây nếu file bị khóa | MID được viết/sửa sau, workaround hạn chế file-based DB khi nhiều máy trạm truy cập đồng thời |
| `Insert_tbl_input_all` | 9 tham số (có `rawqrdye`/`rawqrchem`) | 7 tham số (thiếu 2 trường) | C3 khớp đúng schema đích (`MachineDispatch.php` có field `raw_qr_dye`/`raw_qr_chemical`) — C3 là bản đầy đủ hơn |
| `Approve_Update_Move` | Có `On Error GoTo EH`, có xử lý `scale_check` | Không bắt lỗi, không có `scale_check` | C3 an toàn hơn |
| Dấu vết code chết | — | `'StopAutoGrid` (dòng 237, bị comment) | Bằng chứng MID được fork từ C3 rồi chuyển sang cơ chế AutoVD nhưng chưa dọn hết code cũ |

**Không có nhánh nào nên bị loại bỏ** — cả 2 phục vụ mục đích khác nhau và cả 2 đều có phần MISSING ở hệ mới (xem `vba-migration-matrix.md` nhóm DISPATCH).

---

## 3. Bảng dữ liệu không có VBA nguồn: `tbl_ToSend2`, `WAITING`, `tblSync`

Không phải so sánh phiên bản theo nghĩa thông thường, nhưng thuộc phạm vi Section VI vì đây chính là bằng chứng cho sự tồn tại của phiên bản workbook thứ 3 (chưa cung cấp) trong nhóm điều phối:

- Đã đọc **toàn bộ** code (không chỉ grep từ khóa) của cả `C3 grid load row lock id FB -.xlsm` và `MACHINE_ID_LOCKED.xlsm`: **0 tham chiếu** tới `tbl_ToSend2`, `WAITING`, `tblSync`, `NextFE`, `FE1_Alive`...`FE5_Alive`.
- 2 workbook hiện có chỉ dùng `tbl_tosend` (17 cột), `tbl_input_all` (17 cột), `tbl_sentlog` (17 cột).
- Trong Postgres `legacy_df_data`: `tbl_ToSend2` (14 cột, thiếu 3 cột so với `tbl_ToSend`), `tbl_Waiting` (14 cột), `WAITING` (15 cột — có thêm `ID1` ở đầu, đây chính là "lỗi lệch cột" CLAUDE.md đã ghi nhận), `tblSync` (6 cột hoàn toàn khác: `NextFE`, `FE1_Alive`..`FE5_Alive` — cơ chế heartbeat/round-robin đa máy trạm).
- `sql_migration/03_transform_legacy_to_target.sql` và `.claude/migration-strategy.md` đã có sẵn ánh xạ cột thủ công cho `tbl_ToSend2` và `WAITING` — nhưng đây là **suy luận dựa trên so sánh cấu trúc cột** với `tbl_ToSend` chuẩn, **chưa từng được xác minh bằng code VBA thật**. `tblSync` thì **chưa có bất kỳ mapping/giả định nào** trong toàn bộ `sql_migration/`.

**Nghi vấn nguồn gốc:** rất có thể nằm trong `C3 grid load row lock id FB -(1).xlsm` hoặc `Copy of MACHINE_ID_LOCKED.xlsm` — 2 file bị thiếu (xem mục 0).

---

## 4. Nhóm Cân bán tự động: A=`SEMI CHECKER.xlsm`, B=`semiauto-...SEND OVER6...xlsm`, C=`semiauto-small scale...xlsm`

**Kết luận: B và C là 2 bản phát triển song song từ cùng gốc, C có sửa lỗi mà B chưa có** (dù cả 2 cùng mang hậu tố tên file "delta-stable-final"). 3 lệch có ý nghĩa nghiệp vụ, xác nhận bằng đọc trực tiếp code (không chỉ theo tên module):

1. **Đường dẫn Access** (`ModAcessDB.GetDB`): A dùng `"Z:\DF_SCALE\RECORD.accdb"` (đúng cú pháp); B và C dùng `"Z:DF_SCALE\RECORD.accdb"` (**thiếu dấu `\`** sau `Z:` — cú pháp Windows này trỏ tới "thư mục hiện hành trên ổ Z:", không phải thư mục gốc). Lỗi copy-paste từ B sang C, chưa sửa.
2. **Bug màu ACCEPTED/REJECTED (nghiêm trọng nhất):** `Mod_print_tsc224.GetProcessStatus` của workbook **B** dùng `RGB(60,200,100)` để nhận diện "ACCEPTED", nhưng module tô màu thật (`Mod_UI_processcolor.CheckRange`) không bao giờ gán màu này (chỉ gán `RGB(120,250,20)` cho ACCEPTED) → **mọi bản ghi cân từ trạm dùng workbook B trong lịch sử đều bị lưu là "REJECTED"**, kể cả khi thực tế đạt dung sai. Workbook **C** đã sửa đúng thành `RGB(120,250,20)`. **Cảnh báo đối soát:** nếu Golden Master dùng dữ liệu lịch sử `tblRECORD`/`processColor` từ trạm B làm chuẩn, kết quả "REJECTED" không đáng tin.
3. **`Mod_lockmoveform` (ghim form):** B không gọi `StopWatchFormPos` trước khi `StartWatchFormPos` (rủi ro chồng nhiều `Application.OnTime`, rò rỉ timer); C đã vá bằng cách gọi `StopWatchFormPos` trước + kiểm tra `WatchForm.Visible`. Không ảnh hưởng hệ mới (chức năng bị loại bỏ hoàn toàn — DEPRECATED_CONFIRMED) nhưng là bằng chứng thứ 3 khẳng định C mới hơn B.

Ngoài ra: `txt_color_AfterUpdate` ở C xử lý chuỗi quét "-dye-" lặp nhiều lần + không phân biệt hoa/thường triệt để hơn B (bền hơn khi parse mã quét).

**Kết luận tổng:** nếu phải chọn 1 bản làm "nguồn sự thật" để đối chiếu Golden Master thuật toán cân, nên ưu tiên **workbook C** (đã sửa 2/3 lỗi trên) — nhưng cả 3 bản đều cần trong phạm vi audit vì A là công cụ khác mục đích (checker, không phải trạm cân).

---

## 5. Nhóm In tem/QR: A=`in tem Copower.xlsm`, B=`QR PRINTER-send to access- NEW 9ROWS BIG QR.xlsm`

**Kết luận: B là bản mở rộng/nâng cấp của A** (A là tập con module của B). B bổ sung nguyên một lớp "gửi dữ liệu sang Access" (`tbl_input_all`/`tbl_tosend`/`tbl_sentlog`, qua `Mod_accesscore`, `Mod_move_tosend`, `Mod_getDB`) mà A hoàn toàn không có. Khác biệt cụ thể:

- **`mdQRCodegen.bas`** (module dùng chung nhưng CÓ khác biệt): bản B bổ sung xử lý loại bỏ BOM UTF-8 (`CleanString`, `URLEncode_UTF8` có thêm bước phát hiện 3 byte `EF BB BF`) trước khi gọi `api.qrserver.com` — bản A không có. Cả 2 bản đều **giống nhau ở điểm bị flag chính sách**: gọi thẳng API bên thứ 3.
- **`scaleform.frm`:** A có `btnPrint_Click` cũ chỉ xử lý **2/9 rack** (bằng chứng form từng được nâng cấp từ ít dòng lên 9 dòng ngay trong lịch sử nội bộ, không cần đến file "27 rows" mới thấy được sự tiến hoá) và `btnPrint2_Click` (chỉ có ở A) in slip đầy đủ 9 rack+9 chem trên khổ lớn — cả 2 đều **không có tương đương ở B** (B chỉ có 1 bản `btnPrint_Click` xử lý đủ 9 rack, layout nhỏ). B bổ sung `btnTank_Click`/`btnMachine_Click`/`btnSend_Click` mà A không có.
- **Tên máy in hard-code khác nhau ngay giữa 2 workbook "chị em":** A = `"TSC TE200"`, B = `"vn-ld047\TSC TE200"`.
- **`checkform.frm`:** B bổ sung `btnCheck2_Click` (tra cứu `tbl_sentlog` — lịch sử gửi) mà A không có.

**Kết luận tổng:** B (bản "9 rows") là phiên bản đang dùng thật (đầy đủ hơn, tên file khớp với thực tế cấu trúc form 9 dòng); A có vẻ là bản trước đó hoặc công cụ phụ trợ đơn giản hơn cho cùng mục đích. Không phát hiện nhánh nghiệp vụ nào cần giữ riêng từ A mà B không có, ngoại trừ `btnPrint2_Click` (in slip đầy đủ, xem MISSING trong ma trận).

---

## 6. Nhóm Xử lý sự cố: `troubleshooting_support engine_DF.xlsm` vs `troubleshooting_support engine - 染纱-缸染.xlsm`

**Kết luận: CÙNG MỘT PHIÊN BẢN CODE, không phải 2 nhánh nghiệp vụ khác nhau.** Xác nhận bằng `diff` toàn văn bản trích xuất của cả 2 file: toàn bộ 21 module VBA thực thi (kể cả `modInferenceEngine.bas` — công thức chấm điểm lõi) **giống hệt nhau 100% byte-for-byte**, kể cả comment và thụt lề. 2 khác biệt duy nhất, đều KHÔNG phải khác biệt nghiệp vụ:

1. Bản `染纱-缸染` có thêm 1 class module rỗng thừa (`Sheet10.cls`, không code).
2. Phần `VBA FORM STRING` (chuỗi nhị phân olevba trích từ `vbaProject.bin`) có sai khác thứ tự/số lần lặp — artifact của cách đóng gói OLE binary, không phải mã nguồn hay dữ liệu nghiệp vụ.

Tên file `染纱-缸染` ("nhuộm sợi - nhuộm lồng/bồn") gợi ý đây là bản triển khai/đổi tên cho 1 xưởng/quy trình cụ thể, dùng chung 100% engine với bản `_DF` tổng quát. **Không có bằng chứng ngày sửa đổi** để xác định bản nào đang chạy sản xuất — cần hỏi phòng QA/nhuộm (xem `open-questions.md`). Vì code giống hệt, **không được coi 1 trong 2 bản là "lỗi thời rồi bỏ qua"** — cả 2 có thể đang dùng song song cho 2 xưởng khác nhau chia sẻ chung 1 knowledge base engine.

*Lưu ý phạm vi:* audit này chỉ so sánh **code**, không so sánh **dữ liệu** trong các sheet `KB_Problem`/`KB_Cause`/`KB_Process`/`KB_Parameter`/`KB_ProblemCause` của 2 file (nằm ngoài khả năng trích xuất của olevba, vốn chỉ đọc được VBA project chứ không đọc ô tính) — nếu 2 file có bộ dữ liệu tri thức (knowledge base) khác nhau dù code giống nhau, đó vẫn là khác biệt nghiệp vụ thật cần rà soát riêng.

---

## 7. Module "thư viện dùng chung" xuất hiện xuyên suốt nhiều nhóm — giới hạn xác minh

Các module sau xuất hiện *giống hệt tên* ở nhiều workbook thuộc **nhiều nhóm khác nhau** (không chỉ trong 1 nhóm): `ModAcessDB.bas`, `ModRead_putty_log.bas`, `CALLFORM.bas`, `Mod_print_tsc224.bas`, `Modcleanweight.bas`, `mdQRCodegen.bas`, `ModAPI_mouse.bas`, `ModTOP_most.bas`, `Mod_clickAT.bas`, `Mod_off_topmost.bas`, `ModDelay_paste.bas`, `Mod_callform_safe.bas`, `Mod_put_rawtoform.bas`.

Mỗi nhóm audit (Cân, In tem) đã so sánh các module này **trong phạm vi nội bộ nhóm mình** (ví dụ so sánh `ModAcessDB.bas` giữa SEMI CHECKER/semiauto-*, và riêng giữa in tem Copower/QR PRINTER) và đã phát hiện lệch thật (mục 4, 5 ở trên). **Tuy nhiên chưa có ai so sánh các module này XUYÊN NHÓM** (ví dụ: `ModAcessDB.bas` trong SEMI CHECKER.xlsm có giống hệt `ModAcessDB.bas` trong in tem Copower.xlsm không?) — đây là khoảng trống xác minh còn lại, mức độ rủi ro thấp (các module này đều đã bị phân loại DEPRECATED_CONFIRMED/REPLACED_EQUIVALENTLY ở hệ mới, không mang logic nghiệp vụ cân/in quan trọng), nên không chặn tiến độ nhưng ghi nhận để đầy đủ hồ sơ.

---

## Phạm vi chưa audit — cần người dùng bổ sung file

Xem bảng đầy đủ ở mục 0. Ưu tiên theo mức độ ảnh hưởng:
1. **Cao:** `C3 grid load row lock id FB -(1).xlsm` hoặc `Copy of MACHINE_ID_LOCKED.xlsm` — nghi vấn chứa logic `tbl_ToSend2`/`WAITING`/`tblSync` (ảnh hưởng trực tiếp tới độ tin cậy dữ liệu đã di trú, cần trước Phase 12 nếu pilot chạy >1 máy trạm).
2. **Trung bình:** `DF002 - PRINTER - qr sending - 15l special-27rows.xlsm`, `DF002 no formulas - PRINTER LANDSCAPE - jit qr sending - 15l special.xlsm` — template tem 27 dòng/15L đặc biệt/landscape/JIT chưa audit được.
3. **Thấp:** các file `(1)` còn lại (张力表, df lượng dùng thuốc nhuộm, SEMI CHECKER, in tem Copower, QR PRINTER, semiauto-Checker plus/chem-deltaRaw) — dựa trên bằng chứng gián tiếp (byte-for-byte giống nhau giữa các cặp workbook đã audit được), khả năng cao đây cũng là bản sao/gần giống, rủi ro bỏ sót nghiệp vụ thấp hơn nhóm 1-2.
