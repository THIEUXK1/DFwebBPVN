# P0-A — Phân tích kỹ thuật & Kế hoạch sửa: Hàm `TraHeSo` (tra hệ số 3 chiều mã × khổ vải × tiêu)

> Trạng thái tài liệu: **PHÂN TÍCH + KẾ HOẠCH — KHÔNG SỬA CODE.** Không có file nào trong `F:\DF\backend`, `F:\DF\frontend`, `F:\DF\agent`, `F:\DF\sql_migration` bị chỉnh sửa khi lập tài liệu này.

---

# PHẦN 1 — TÀI LIỆU PHÂN TÍCH KỸ THUẬT CHI TIẾT

## 1. Module/procedure nguồn

| # | File workbook | Module VBA | Chữ ký hàm |
|---|---|---|---|
| A | `CÔNG THỨC SẢN XUẤT CHUNG - new.xlsm` | `Module2.bas` | `Function TraHeSo(Code As String, Width As Variant, Tiao As Variant) As Variant` |
| B | `张力表-NEW VERSION.xlsm` | `Module1.bas` | `Function TraHeSo(Code As String, Width As Variant, Tiao As Variant) As Variant` |

Vị trí đọc thân hàm thật:
- File A: `C__NG_TH___C_S___N_XU___T_CHUNG___new_xlsm_.txt`, dòng 683–730 (`Option Explicit` ở dòng 681, `End Function` dòng 730).
- File B: `__________NEW_VERSION_xlsm_.txt`, dòng 27–74 (`Option Explicit` dòng 25, `End Function` dòng 74).

**Đối chiếu byte:** Đã Read toàn bộ 2 đoạn code và so sánh thủ công từng dòng — **hai bản GIỐNG HỆT NHAU 100%**, kể cả tên biến (`ws`, `c`, `loai`, `rStart`, `r`, `cCol`), thứ tự lệnh, chuỗi hằng ("A"/"B"/"C"), tham chiếu vùng (`AI2:AK1500`, `D3:AE3`, cột `C`), và cách trả lỗi (`CVErr(xlErrNA)`). Không có bất kỳ sai khác nào (kể cả khoảng trắng/thụt lề trong bản trích xuất olevba). Xác nhận lại kết luận của báo cáo audit gốc (VBA-RECIPE-012/013): đây là copy-paste y hệt giữa 2 workbook, không phải 2 phiên bản độc lập trôi dạt (diverged) theo thời gian.

## 2. Input

Chữ ký: `TraHeSo(Code As String, Width As Variant, Tiao As Variant) As Variant`

| Tham số | Kiểu VBA | Ý nghĩa nghiệp vụ | Ghi chú |
|---|---|---|---|
| `Code` | `String` (bắt buộc, không phải Variant — không nhận `Null`/rỗng an toàn theo kiểu Variant) | Mã hàng / mã sản phẩm dùng làm khóa tra trong vùng `AI2:AK1500` của `Sheet1`. Dựa trên context toàn cục của workbook (trường `product_code`/`color_code` xuất hiện xuyên suốt các sheet `KHAIDON` khác trong cùng workbook, và tên bảng "CÔNG THỨC SẢN XUẤT CHUNG"), **giả thuyết (suy luận, chưa xác nhận nghiệp vụ):** `Code` nhiều khả năng là **mã hàng/mã sản phẩm (product code)**, không phải mã màu (color code) — vì mã màu thường gắn với công thức thuốc nhuộm cụ thể còn hệ số tra ở đây gắn với đặc tính vật lý vải (khổ vải, tiêu chuẩn căng) áp dụng chung cho nhiều màu của cùng 1 mã hàng. Không có bằng chứng trực tiếp trong code VBA để khẳng định 100%. |
| `Width` | `Variant` | Khổ vải. Đơn vị **không xác định được từ code** — vùng tiêu đề `D3:AE3` là dữ liệu ô Excel (olevba không trích xuất được nội dung ô, xem mục 4). **Giả thuyết dựa trên tên workbook thứ 2 (`张力表` = "bảng lực căng", tiếng Trung, liên quan ngành dệt/nhuộm):** khổ vải trong ngành dệt Việt Nam/Trung Quốc thường tính bằng **cm hoặc inch** tùy loại máy; không đủ căn cứ để chọn 1 trong 2 — cần xác nhận nghiệp vụ. |
| `Tiao` | `Variant` | "Tiao" — không phải từ tiếng Việt chuẩn, khả năng là phiên âm không dấu của **"Tiêu"** (viết tắt trong code gốc, biến `Tiao` xuất hiện cùng ngữ cảnh chuỗi `张力表` = "bảng lực căng/lực kéo căng vải"). **Giả thuyết nghiệp vụ (suy luận):** `Tiao` có thể là **"tiêu chuẩn căng" hoặc "độ tiêu"/mức lực căng đai** áp dụng cho quy trình nhuộm — phù hợp với field `tension_value` đã tồn tại trong `app.process_parameters` (xem mục 11) và nhãn UI tiếng Việt "Lực căng đai (Tension)" trong `Recipes.vue` dòng 138. **Đây chỉ là suy luận dựa trên tên workbook và trường tương tự trong hệ thống mới — KHÔNG có bằng chứng trực tiếp trong VBA xác nhận nghĩa chính xác của "Tiao".** Cần hỏi nghiệp vụ trực tiếp. |

Lưu ý kỹ thuật: `Code` được khai báo cứng kiểu `String` trong khi `Width`/`Tiao` là `Variant` — nghĩa là khi gọi từ công thức ô Excel, Excel sẽ tự ép kiểu `Code` về String (nếu ô nguồn là số, Excel áp dụng `CStr` ngầm có thể gây lệch định dạng số/text — rủi ro kinh điển trong VBA UDF, không có xử lý phòng vệ nào trong code).

## 3. Output

Kiểu trả về: `Variant` (bắt buộc vì hàm phải trả được cả giá trị số tra được LẪN mã lỗi `CVErr(xlErrNA)` — 2 kiểu dữ liệu khác nhau không thể gộp trong 1 kiểu tĩnh).

Ý nghĩa: giá trị tại **giao điểm (dòng, cột)** của bảng tra 3 chiều — dòng ứng với `Tiao` khớp trong khối 8 dòng, cột ứng với `Width` khớp trong `D3:AE3`. Đây là **hệ số** (tên hàm `TraHeSo` = "Tra Hệ Số") nhưng **không xác định được về mặt nghiệp vụ đây là hệ số gì cụ thể** (hệ số pha loãng? hệ số nhân định lượng? hệ số điều chỉnh tốc độ máy theo khổ vải?) vì:
- Không tìm được nơi gọi hàm này trong toàn bộ 4 workbook đã audit (kể cả comment) — xem mục 9.
- Không có tên cột/named range/comment nào trong code VBA gợi ý ý nghĩa giá trị trả về.
- Dữ liệu ô thật trong `AI2:AK1500`/`D3:AE3` không trích xuất được qua olevba (olevba chỉ đọc VBA project, không đọc nội dung sheet).

**Kết luận trung thực:** Không thể khẳng định "hệ số gì" — đây là hạn chế thật của việc audit chỉ qua olevba, cần xác nhận nghiệp vụ hoặc mở file Excel gốc bằng Excel/openpyxl để đọc giá trị ô thật.

## 4. Dữ liệu bảng tra (Sheet1 — dữ liệu Ô TÍNH, KHÔNG PHẢI VBA)

**Hạn chế công cụ (ghi rõ):** `olevba` chỉ trích xuất mã VBA (macro), **không trích xuất được nội dung/công thức của ô tính Excel**. Vì vậy toàn bộ mô tả dưới đây chỉ là **cấu trúc truy vấn suy ra từ code VBA**, KHÔNG PHẢI dữ liệu thật trong bảng tra. Không biết được: giá trị mã hàng thật trong `AI2:AK1500`, giá trị khổ vải thật trong `D3:AE3`, giá trị Tiêu thật trong cột C, hay giá trị hệ số thật tại các ô giao điểm.

Cấu trúc suy ra từ code (dòng 692–728 file A / 36–72 file B):

- **`ws = Worksheets("Sheet1")`** — toàn bộ bảng tra nằm trên sheet tên `Sheet1` (không phải `KHAIDON`).
- **Vùng `AI2:AK1500`** (3 cột: AI, AJ, AK — 1499 dòng): vùng chứa mã hàng (`Code`) để `Find`. `Find` trả về ô `c` đầu tiên khớp, sau đó code lấy `loai = ws.Cells(1, c.Column).Value` — nghĩa là **dòng 1** (header) của **CỘT MÀ MÃ ĐƯỢC TÌM THẤY** (AI, AJ, hoặc AK) chứa giá trị phân loại `"A"`, `"B"`, hoặc `"C"`. Suy ra: bảng có 3 cột danh sách mã (AI/AJ/AK), mỗi cột ứng với 1 loại A/B/C, và mã hàng được liệt kê trong đúng 1 trong 3 cột tùy loại nó thuộc về.
- **`rStart` theo loại:** A→4, B→12, C→20 — mỗi loại chiếm 1 khối **8 dòng liên tiếp** (4-11 cho A, 12-19 cho B, 20-27 cho C). Đây là bảng hệ số dùng chung cho MỌI mã cùng loại (không phải 1 khối riêng cho từng mã) — nghĩa là bảng tra thực chất chỉ có **3 khối 8 dòng cố định** (tổng 24 dòng dữ liệu số), còn `AI2:AK1500` chỉ là **bảng ánh xạ mã hàng → loại (A/B/C)**, không chứa hệ số trực tiếp.
- **Vùng `D3:AE3`** (cột D đến AE, dòng 3): header khổ vải — `Find(Width, LookAt:=xlWhole)` trả về cột `cCol`. Vùng D-AE là 28 cột → tối đa 28 giá trị khổ vải khác nhau được hỗ trợ.
- **Cột `C`** trong mỗi khối 8 dòng (`ws.Cells(r, "C").Value`): giá trị Tiêu (Tiao) — so khớp bằng `=` (không phải `Find`, so sánh trực tiếp giá trị Variant).
- **Ô giao điểm `ws.Cells(r, cCol)`**: giá trị hệ số trả về.

## 5. Thuật toán (pseudocode chính xác theo code thật)

```
FUNCTION TraHeSo(Code: String, Width: Variant, Tiao: Variant) -> Variant
    ws = Worksheets("Sheet1")

    // Bước 1: tra mã hàng để xác định "loại" A/B/C
    c = ws.Range("AI2:AK1500").Find(Code, LookAt:=xlWhole)
    IF c IS NOTHING THEN
        RETURN CVErr(xlErrNA)   // #N/A — không tìm thấy Code
    END IF

    loai = ws.Cells(1, c.Column).Value    // đọc header dòng 1 của cột chứa Code

    // Bước 2: ánh xạ loại -> dòng bắt đầu khối 8 dòng
    SELECT CASE loai
        CASE "A": rStart = 4
        CASE "B": rStart = 12
        CASE "C": rStart = 20
        CASE ELSE:
            RETURN CVErr(xlErrNA)   // #N/A — loai không phải A/B/C
    END SELECT

    // Bước 3: tra khổ vải để xác định cột
    c2 = ws.Range("D3:AE3").Find(Width, LookAt:=xlWhole)
    IF c2 IS NOTHING THEN
        RETURN CVErr(xlErrNA)   // #N/A — không tìm thấy Width
    END IF
    cCol = c2.Column

    // Bước 4: duyệt 8 dòng của khối, so khớp Tiao ở cột C
    FOR r = rStart TO rStart + 7
        IF ws.Cells(r, "C").Value = Tiao THEN
            RETURN ws.Cells(r, cCol).Value   // giá trị hệ số tại giao điểm
        END IF
    NEXT r

    RETURN CVErr(xlErrNA)   // #N/A — duyệt hết 8 dòng không khớp Tiao
END FUNCTION
```

**Chi tiết kiểu so khớp (đọc trực tiếp từ code, không suy đoán):**
- Tra `Code`: `.Find(Code, LookAt:=xlWhole)` — so khớp **toàn bộ nội dung ô** (không phải khớp một phần `xlPart`). Không truyền tham số `MatchCase` → mặc định **KHÔNG phân biệt hoa/thường** (Excel `Find` mặc định `MatchCase:=False` trừ khi set khác trong lần dùng `Find`/`Replace` gần nhất qua UI — đây là hành vi runtime phụ thuộc trạng thái Excel Find/Replace dialog trước đó, một rủi ro tiềm ẩn của VBA `Find` không truyền `MatchCase` tường minh). Cũng không truyền `MatchByte` hay `SearchOrder`/`SearchDirection` → dùng giá trị mặc định/trạng thái Find gần nhất của session Excel (rủi ro tái lập kết quả không nhất quán giữa các lần chạy nếu người dùng từng dùng Find/Replace thủ công trước đó với cấu hình khác).
- Tra `Width`: `.Find(Width, LookAt:=xlWhole)` — tương tự, khớp toàn bộ, không truyền `MatchCase`.
- So khớp `Tiao`: **KHÔNG dùng `Find`** — dùng phép so sánh trực tiếp `ws.Cells(r, "C").Value = Tiao` (toán tử `=` của VBA). Đây là so sánh giá trị Variant chuẩn: nếu cả 2 phía đều numeric thì so numeric; nếu 1 phía text thì VBA sẽ cố ép kiểu — **không có `Trim`/chuẩn hóa nào trước khi so sánh**, nên nếu ô có khoảng trắng thừa hoặc kiểu dữ liệu lệch (số lưu dạng text) sẽ không khớp, dẫn tới rơi vào nhánh `#N/A` một cách âm thầm mà không có cảnh báo.

## 6. Điều kiện biên

| Trường hợp | Trả về | Vị trí trong code |
|---|---|---|
| `Code` không khớp bất kỳ ô nào trong `AI2:AK1500` | `CVErr(xlErrNA)` (tức `#N/A`) | dòng 695-698 (file A) |
| `Code` khớp nhưng `loai` (giá trị dòng 1 cùng cột) không phải đúng 1 trong 3 chuỗi `"A"`/`"B"`/`"C"` (kể cả rỗng, khác hoa/thường vì `Select Case` VBA mặc định **CÓ** phân biệt hoa/thường trừ khi dùng `Option Compare Text` — file không khai báo `Option Compare Text` nên `"a"` ≠ `"A"`) | `CVErr(xlErrNA)` | dòng 709-711 |
| `Width` không khớp bất kỳ cột nào trong `D3:AE3` | `CVErr(xlErrNA)` | dòng 715-718 |
| `Tiao` không khớp giá trị cột C của cả 8 dòng trong khối | `CVErr(xlErrNA)` | dòng 728 (cuối hàm, sau vòng `For`) |
| Ô giao điểm `(r, cCol)` rỗng | Trả về `0` hoặc chuỗi rỗng tùy nội dung ô thật (VBA `.Value` của ô rỗng trả `Empty`, khi gán vào `Variant` kết quả UDF Excel hiển thị là `0`) — **không có kiểm tra rỗng riêng**, hàm coi ô rỗng là "khớp thành công" và trả nguyên giá trị Empty/0 | Không có logic riêng — hệ quả của việc thiếu kiểm tra `IsEmpty` |

**Tổng cộng đúng 3 điểm `CVErr(xlErrNA)` như báo cáo audit gốc (VBA-RECIPE-012) đã mô tả** — đã xác nhận bằng cách đọc trực tiếp code, khớp chính xác.

## 7. Fallback

**Không có cơ chế dự phòng (fallback) nào.** Không có giá trị mặc định (default coefficient), không có `On Error Resume Next`, không có logic "nếu không tìm thấy thì dùng giá trị gần đúng/nội suy". Cả 3 nhánh lỗi đều trả thẳng `CVErr(xlErrNA)` (tương đương `#N/A` hiển thị trên ô Excel gọi hàm). Nếu ô Excel dùng công thức bọc `TraHeSo` trong `IFERROR(...)` ở lớp công thức ô (không thuộc phạm vi VBA, không trích xuất được) thì mới có fallback — nhưng không có bằng chứng nào về việc này trong code VBA đã đọc.

## 8. Rounding

**Xác nhận: hàm `TraHeSo` KHÔNG có bất kỳ phép làm tròn, tính toán, hay biến đổi số nào.** Toàn bộ hàm là thuần túy **tra bảng (lookup)** — đọc code dòng 683-730 (file A), không có bất kỳ lệnh `Round`, `Int`, `Fix`, phép nhân/chia, hay format số nào. Giá trị trả về là **nguyên trạng `.Value` của ô Excel** tại giao điểm tìm được. Điều này khác biệt rõ với `FormulaCalculationService::calculateWater()` (có `ceil($water / 10.0) * 10.0`) và `getPrecisionRoundedWeight()` (vòng lặp `round($target, $decimals)` theo quy tắc sai số 1%) — 2 hàm phía web hiện có logic làm tròn riêng, không liên quan gì đến `TraHeSo`.

## 9. Nơi gọi

Đã xác nhận lại: `TraHeSo` là **UDF (User Defined Function)** — dấu hiệu kỹ thuật rõ ràng: khai báo `Function ... As Variant` ở cấp Module (không phải `Sub`), không có tham số `ByRef` đặc biệt, kiểu trả về `Variant` cho phép trả `CVErr` (đặc trưng bắt buộc của hàm dùng trong công thức ô Excel để hiển thị `#N/A` thay vì crash runtime VBA). Đây là mẫu hình chuẩn của một hàm được gọi bằng công thức ô kiểu `=TraHeSo(A1, B1, C1)`.

**Bằng chứng gián tiếp tìm được:** Đã tìm kiếm chuỗi `"TraHeSo"` trong toàn bộ nội dung VBA đã trích xuất của cả 2 workbook (dùng Grep trên file text trích xuất) — **không có bất kỳ lời gọi `Call TraHeSo` hay `= TraHeSo(...)` nào xuất hiện trong mã VBA** (kể cả trong comment). Điều này nhất quán với giả thuyết UDF vì công thức ô không nằm trong phạm vi trích xuất của olevba.

**Gợi ý gián tiếp về nơi dùng (KHÔNG PHẢI bằng chứng chắc chắn):** Sheet `KHAIDON` là sheet nghiệp vụ trung tâm của workbook A (nơi các Sub QR khác đọc dữ liệu: `D3`, `D4`, `D5`, `O24`, `O25`, `O26`, các dòng 27-39 — xem VBA-RECIPE-001 đến 011 trong báo cáo audit gốc). Tên trường `tension_value` đã tồn tại sẵn trong hệ thống mới (`app.process_parameters`, `Recipes.vue` nhãn "Lực căng đai (Tension)") gợi ý khả năng ô nào đó trên `KHAIDON` từng dùng công thức `=TraHeSo(...)` để tự động tra `tension_value`/hệ số căng theo mã hàng + khổ vải thay vì người dùng nhập tay — nhưng **đây chỉ là suy luận dựa trên tên trường tương tự, KHÔNG có bằng chứng trực tiếp nào (không tìm thấy comment, không tìm thấy named range, không tìm thấy tham chiếu chéo) xác nhận ô cụ thể nào trên `KHAIDON` gọi `TraHeSo`.** Cần mở file Excel gốc bằng Excel thật hoặc hỏi người vận hành để xác nhận.

## 10. Ảnh hưởng nghiệp vụ nếu không migrate đúng

Vì không xác định được chính xác ý nghĩa của hệ số trả về (mục 3, 9), hậu quả cụ thể phải nêu ở dạng **kịch bản có điều kiện** (nêu rõ đây là suy luận rủi ro, không phải khẳng định):

- **Nếu hệ số là hệ số điều chỉnh định lượng thuốc nhuộm/hóa chất theo khổ vải:** không tra đúng → định lượng bột màu/hóa chất bị tính sai theo khổ vải thực tế của lô vải → lệch màu (color shift), có thể phải nhuộm lại (tốn hóa chất, thời gian máy, nhân công) hoặc nếu không phát hiện thì hàng lỗi màu đến tay khách hàng.
- **Nếu hệ số là hệ số căng đai (tension) theo mã hàng × khổ vải × tiêu chuẩn:** không tra đúng → đai vải bị căng sai mức quy định → nguy cơ giãn vải, co rút không đều, lỗi biên vải (mép vải bị xoắn/gợn sóng), ảnh hưởng chất lượng vật lý vải chứ không chỉ màu sắc.
- Trong cả 2 kịch bản: hiện trạng hệ thống mới đang để `tension_value` là **trường nhập tay tự do, không có ràng buộc/tra cứu tự động nào** (xác nhận qua `ProcessParameter.php`, migration cột `tension_value` decimal(10,4) nullable, không có foreign key hay lookup logic nào ràng buộc) — nghĩa là hiện tại **rủi ro con người nhập sai/nhập nhầm hoàn toàn không được chặn bởi hệ thống**, khác hẳn cơ chế tra bảng cứng (không thể nhập sai vì máy tự tra) của VBA gốc.
- **Mức độ nghiêm trọng thực tế cần nghiệp vụ xác nhận** — vì không rõ hàm còn được dùng trong vận hành thực tế hay đã bị thay bằng quy trình nhập tay từ lâu trước khi dự án migrate bắt đầu (không tìm được lời gọi nào trong VBA — có thể đã là legacy code chết ngay trong hệ thống cũ). Đây chính là nội dung câu hỏi mở **CH-BUS-004** đã có sẵn trong `open-questions.md`.

## 11. Component mới dự kiến (CHỈ ĐỀ XUẤT — KHÔNG VIẾT CODE)

**Đề xuất cấu trúc bảng DB mới** (không tái dùng `water_configs`/`process_parameters` — lý do nêu dưới):

```
app.tension_lookup_categories        -- ánh xạ Code (mã hàng) -> loai (A/B/C)
    id
    product_code        varchar(100)   -- tương ứng "Code" trong VBA, khớp AI2:AK1500
    category             char(1)        -- 'A' / 'B' / 'C', tương ứng "loai"
    unique(product_code)

app.tension_lookup_matrix             -- bảng hệ số 3 chiều: category x width x tiao
    id
    category              char(1)        -- 'A' / 'B' / 'C'
    row_in_block           smallint       -- 1..8, vị trí trong khối 8 dòng (tương ứng r - rStart)
    tiao_value             decimal(10,4)  -- giá trị cột C, khóa so khớp Tiao
    width_value             varchar(20) hoặc decimal  -- header D3:AE3, khóa so khớp Width (kiểu dữ liệu cần xác nhận nghiệp vụ - số hay text)
    coefficient             decimal(12,6)  -- giá trị hệ số tại giao điểm
    unique(category, row_in_block, width_value)
```

**Lý do KHÔNG tái dùng được `app.water_configs`:**
Đọc migration `2026_07_15_000001_create_phase4_tables.php` (dòng 27-35): `water_configs` chỉ có unique key `(machine_line, process_code)` — 2 chiều — và cột giá trị `ratio_coefficient`/`liquor_ratio`. Không có cột nào biểu diễn "khổ vải" hay "tiêu chuẩn" dạng ma trận nhiều dòng x nhiều cột. Bản chất `TraHeSo` cần lưu **ma trận N×M** (8 dòng Tiao × 28 cột Width, nhân 3 cho mỗi loại A/B/C = tối đa 8×28×3 = 672 giá trị hệ số), trong khi `water_configs` là bảng key-value phẳng 1 hệ số/1 tổ hợp 2 khóa — sai cấu trúc dữ liệu hoàn toàn, không thể ép dùng chung mà không làm sai lệch ngữ nghĩa.

**Lý do KHÔNG tái dùng được `app.process_parameters`:**
Đọc `ProcessParameter.php` và migration dòng 78-86: `process_parameters` là bảng 1-1 gắn với `recipe_version_id`, cột `tension_value` là **giá trị đã tra/nhập cuối cùng cho 1 recipe version cụ thể** (kết quả), không phải bảng tra cứu (nguồn). Về nguyên tắc chuẩn hóa dữ liệu, `tension_value` trong `process_parameters` **có thể** là nơi LƯU kết quả sau khi tra `TraHeSo` mới (nếu logic tra được khôi phục), nhưng bản thân bảng này không thể đóng vai trò bảng tra ma trận 3 chiều.

**Method/class dự kiến trong `FormulaCalculationService`:**
- `lookupTensionCoefficient(string $productCode, string $width, $tiao): float|null` — hàm mới, KHÔNG trộn vào `calculateWater()` (khác hoàn toàn miền dữ liệu: `calculateWater` dùng `machine_line`+`process_code`, hàm mới dùng `product_code`+`width`+`tiao`).
- Nội bộ: bước 1 tra `app.tension_lookup_categories` theo `product_code` → lấy `category`; bước 2 query `app.tension_lookup_matrix` theo `category` + `width_value` + `tiao_value` → trả `coefficient`; nếu bất kỳ bước nào không tìm thấy → trả `null` (tương đương ngữ nghĩa `CVErr(xlErrNA)` — cần quyết định API trả lỗi HTTP 404/422 kèm message rõ ràng, KHÔNG trả `0` ngầm vì `0` hợp lệ là giá trị hệ số thật có thể có ở ô rỗng, xem mục 6).
- Cần bổ sung endpoint mới (ví dụ `GET /api/calculations/tension-lookup?product_code=...&width=...&tiao=...`) hoặc tích hợp vào `CalculationController::preview` — quyết định thuộc phạm vi FIX-001 Phần 2, không quyết định trong tài liệu phân tích này.

## 12. Golden test cases (đề xuất cấu trúc — PLACEHOLDER, cần dữ liệu bảng tra thật)

> **Ghi chú bắt buộc:** Vì không có quyền truy cập nội dung ô thật của `Sheet1` (olevba không trích xuất được, xem mục 4), toàn bộ giá trị `expected` dưới đây là **PLACEHOLDER** — cần người dùng cung cấp dữ liệu bảng tra thật (mở file Excel gốc, export `AI2:AK1500` + `D3:AE3` + 24 dòng dữ liệu của 3 khối A/B/C) trước khi có thể điền giá trị `expected` thật và chạy test đối soát Golden Master theo đúng quy định CLAUDE.md mục 4 (sai số cho phép ±0.000001).

| # | Case | Code (input) | Width (input) | Tiao (input) | Expected (PLACEHOLDER) | Mục đích |
|---|---|---|---|---|---|---|
| GT-1 | Tra thành công bình thường | `<mã hàng có thật, loại A, ví dụ "T7400">` | `<1 giá trị khổ vải có thật nằm giữa vùng D3:AE3>` | `<1 giá trị Tiao có thật nằm giữa dòng 4-11>` | `<CẦN NGƯỜI DÙNG ĐIỀN — giá trị hệ số thật tại ô giao điểm>` | Xác nhận luồng tra thành công chuẩn (đường đi "happy path" qua cả 3 bước Find) |
| GT-2 | Code không tồn tại | `"MA_KHONG_TON_TAI_XYZ"` | (bất kỳ giá trị Width hợp lệ) | (bất kỳ giá trị Tiao hợp lệ) | `#N/A` (`CVErr(xlErrNA)`) | Xác nhận nhánh lỗi bước 1 (dòng 695-698) |
| GT-3 | Width không khớp cột nào | `<mã hàng có thật>` | `"999999_KHONG_TON_TAI"` | (bất kỳ giá trị Tiao hợp lệ) | `#N/A` (`CVErr(xlErrNA)`) | Xác nhận nhánh lỗi bước 3 (dòng 715-718) — lưu ý: bước này chỉ chạy SAU KHI Code đã tra được loại hợp lệ |
| GT-4 | Tiao không khớp dòng nào trong khối 8 dòng | `<mã hàng có thật, loại bất kỳ>` | `<khổ vải có thật>` | `"999999_KHONG_TON_TAI"` | `#N/A` (`CVErr(xlErrNA)`) | Xác nhận nhánh lỗi bước 4, sau khi duyệt hết cả 8 dòng (dòng 721-728) |
| GT-5 | Biên: dòng đầu và dòng cuối của khối 8 dòng | `<mã hàng loại "C", để rStart=20>` | `<khổ vải có thật>` | Test 5a: `Tiao` = giá trị tại dòng **20** (`rStart`, dòng đầu khối); Test 5b: `Tiao` = giá trị tại dòng **27** (`rStart+7`, dòng cuối khối) | `<CẦN NGƯỜI DÙNG ĐIỀN>` cho cả 5a và 5b | Xác nhận vòng lặp `For r = rStart To rStart + 7` không lệch chỉ số 1 (off-by-one) ở cả 2 biên — rủi ro kinh điển khi port từ VBA (1-indexed, `To` bao gồm cả 2 đầu) sang PHP/SQL (cần xác nhận `BETWEEN`/`WHERE row_in_block BETWEEN 1 AND 8` bao gồm đúng cả biên) |
| GT-6 (bổ sung) | Ô giao điểm rỗng | `<mã hàng thật>` | `<khổ vải thật>` | `<Tiao ứng với 1 ô giao điểm được xác nhận là RỖNG trong bảng gốc>` | `0` hoặc `Empty` (theo hành vi VBA thật — cần xác nhận bằng cách mở Excel gốc) | Xác nhận hệ thống mới xử lý "khớp tọa độ nhưng hệ số rỗng" giống VBA gốc (không nhầm với case #N/A) — đây là hành vi dễ bị bỏ sót khi thiết kế lại vì trực giác sẽ muốn coi rỗng = lỗi, nhưng VBA gốc KHÔNG coi đây là lỗi (xem mục 6) |

---

# PHẦN 2 — KẾ HOẠCH FIX-001 (CHỈ LẬP KẾ HOẠCH, KHÔNG THỰC HIỆN)

## FIX-001: Khôi phục/thiết kế lại `TraHeSo`

### Phạm vi
Khôi phục logic tra hệ số 3 chiều (mã hàng × khổ vải × tiêu chuẩn) tương đương hàm `TraHeSo` gốc, dưới dạng bảng dữ liệu chuẩn hóa PostgreSQL + method service PHP + (tùy chọn) endpoint/API sử dụng trong màn hình `Recipes.vue`. **Phạm vi này CHƯA ĐƯỢC PHÉP triển khai** — phụ thuộc xác nhận nghiệp vụ ở mục Dependency bên dưới.

### File dự kiến sửa
- **Backend:**
  - `F:\DF\backend\database\migrations\2026_07_17_000001_create_tension_lookup_tables.php` (mới)
  - `F:\DF\backend\app\Models\TensionLookupCategory.php` (mới)
  - `F:\DF\backend\app\Models\TensionLookupMatrix.php` (mới)
  - `F:\DF\backend\app\Services\FormulaCalculationService.php` (sửa — thêm method `lookupTensionCoefficient()`)
  - `F:\DF\backend\app\Http\Controllers\CalculationController.php` (sửa — thêm nhánh gọi lookup mới, hoặc endpoint riêng)
  - `F:\DF\backend\routes\api.php` (sửa — thêm route nếu tách endpoint riêng, cần đọc file này trước khi code để khớp convention route hiện có — chưa đọc trong phạm vi audit này)
  - `F:\DF\backend\database\seeders\` (mới — seeder nạp dữ liệu ma trận thật sau khi có dữ liệu từ người dùng)
- **Frontend:**
  - `F:\DF\frontend\src\views\Recipes.vue` (sửa — nếu quyết định tự động tra `tension_value` thay vì nhập tay tự do như hiện tại, cần đổi input `#new-tension` (dòng 138-146) thành cơ chế gọi API tra cứu + fallback nhập tay)
- **Test:**
  - `F:\DF\backend\tests\Unit\FormulaCalculationServiceTest.php` (sửa — thêm test case mới, không đổi test hiện có)
  - `F:\DF\backend\tests\Feature\` (mới, nếu cần test API endpoint — cần xác nhận có thư mục Feature test hiện có theo convention nào không, chưa audit trong phạm vi này)

### Database change
**Có.** Bảng mới:
- `app.tension_lookup_categories` (product_code → category A/B/C)
- `app.tension_lookup_matrix` (category, row_in_block, tiao_value, width_value → coefficient)

Chi tiết cột đề xuất xem mục 11 Phần 1. **Không sửa/không thêm cột vào `app.water_configs` hay `app.process_parameters`** (giữ nguyên, vì lý do nêu ở mục 11).

### Migration
Tên file dự kiến (theo đúng convention Laravel + convention thực tế đang dùng trong repo, đối chiếu ví dụ gần nhất `2026_07_16_000009_create_workstation_security_and_network_tables.php`):

```
2026_07_17_000001_create_tension_lookup_tables.php
```

### Acceptance criteria
- Given dữ liệu ma trận tra cứu thật đã được nạp vào `app.tension_lookup_matrix` và `app.tension_lookup_categories`, When gọi `lookupTensionCoefficient(productCode, width, tiao)` với bộ 3 khóa khớp đúng 1 dòng dữ liệu, Then trả về đúng giá trị `coefficient` với sai số ≤ ±0.000001 so với giá trị gốc trong file Excel (theo quy định Golden Master của CLAUDE.md mục 4).
- Given `productCode` không tồn tại trong `tension_lookup_categories`, When gọi lookup, Then trả về `null` (hoặc response lỗi rõ ràng ở tầng API), KHÔNG được trả `0` (tránh nhầm với hệ số hợp lệ = 0, xem GT-6 Phần 1 mục 12).
- Given `width` không khớp bất kỳ giá trị nào trong ma trận của đúng `category` đã xác định, When gọi lookup, Then trả về `null`.
- Given `tiao` không khớp bất kỳ dòng nào trong 8 dòng (`row_in_block` 1-8) của đúng `category`+`width`, When gọi lookup, Then trả về `null`.
- Given ô hệ số gốc trong Excel là rỗng nhưng tọa độ (category, width, tiao) khớp đúng, When gọi lookup, Then hành vi trả về phải nhất quán với hành vi VBA gốc đã xác nhận bằng cách mở file Excel thật (không suy đoán) — ghi rõ quyết định (trả `0.0` hay `null`) trong code comment kèm tham chiếu dòng VBA gốc.
- Toàn bộ 6 golden test case (GT-1 đến GT-6, Phần 1 mục 12) phải có test tương ứng trong `FormulaCalculationServiceTest.php` với giá trị `expected` thật (không còn placeholder) trước khi coi FIX-001 là hoàn tất.
- `Recipes.vue` (nếu được quyết định tích hợp UI) phải hiển thị rõ ràng cho người dùng biết giá trị `tension_value` là **tự động tra được** hay **người dùng tự nhập** (tránh nhầm lẫn giữa 2 nguồn dữ liệu).

### Regression test
- **Test mới cần viết:** 6 test case tương ứng GT-1 đến GT-6 (mục 12 Phần 1) cho method `lookupTensionCoefficient()` trong `FormulaCalculationServiceTest.php`.
- **Test hiện có có thể bị ảnh hưởng:** `test_calculate_water()` và `test_get_precision_rounded_weight()` (dòng 36-78 file test hiện tại) — **về lý thuyết KHÔNG bị ảnh hưởng** vì `TraHeSo` là logic hoàn toàn tách biệt (khóa tra khác, không dùng chung `water_configs`). Cần chạy lại toàn bộ suite sau khi thêm method mới để xác nhận không có side-effect ngoài ý muốn (ví dụ nếu lỡ tay sửa nhầm `calculateWater()` khi thêm code mới vào cùng file service).
- `test_get_process_code()` (dòng 21-31) không liên quan `TraHeSo`, không cần sửa.

### Rollback
Nếu migration đã chạy trên môi trường dev/UAT cần lùi lại:
```
php artisan migrate:rollback --path=database/migrations/2026_07_17_000001_create_tension_lookup_tables.php
```
File migration `down()` cần viết `Schema::dropIfExists('app.tension_lookup_matrix')` và `Schema::dropIfExists('app.tension_lookup_categories')` (thứ tự xóa: bảng con trước nếu có foreign key, hoặc bảng không phụ thuộc trước nếu độc lập — cần xác định khi thiết kế thật). **Vì đây là bảng tra cứu tĩnh (reference data), không phải bảng giao dịch, rollback không có rủi ro mất dữ liệu vận hành thật** — khác hẳn các bảng như `weighing_job_items`. Tuyệt đối KHÔNG chạy migration này trên Production nếu chưa qua UAT, theo đúng CLAUDE.md mục 3 và mục 6 (cấm `DROP SCHEMA app CASCADE`).

### Dependency
- **PHẢI có xác nhận nghiệp vụ CH-BUS-004** (đã có sẵn trong `F:\DF\.claude\open-questions.md`, mục "Các Câu hỏi Còn mở") trước khi bắt đầu code: *"Mô hình tra cứu 3 chiều (mã hàng × khổ vải × tiêu chuẩn) có còn được dùng trong vận hành thực tế không, hay đã được thay thế có chủ đích bằng mô hình khác?"* — nếu câu trả lời là "không còn dùng", FIX-001 bị hủy/hạ mức ưu tiên, giữ nguyên trạng nhập tay hiện tại.
- **Cần dữ liệu bảng tra thật** từ người dùng (export `AI2:AK1500`, `D3:AE3`, và 24 dòng dữ liệu 3 khối A/B/C từ file Excel gốc mở bằng Excel thật hoặc `openpyxl`) — không thể viết seeder hay golden test thật nếu thiếu dữ liệu này (xem Phần 1 mục 4 và mục 12).
- **Cần xác nhận ý nghĩa nghiệp vụ của `Code`, `Width`, `Tiao`** (đơn vị Width là cm hay inch; Tiao có đúng là "tiêu chuẩn căng"/tension không) — nếu không xác nhận, rủi ro thiết kế sai cấu trúc cột (ví dụ kiểu dữ liệu `width_value` nên là số hay chuỗi).
- Phụ thuộc gián tiếp: cần đọc `F:\DF\backend\routes\api.php` (chưa nằm trong phạm vi audit của phát hiện P0-A này) trước khi quyết định endpoint mới, để khớp đúng convention route/middleware hiện có.

### Rủi ro
1. **Rủi ro sai lệch dữ liệu tra cứu:** Nếu người dùng cung cấp dữ liệu export không đầy đủ/sai sót (ví dụ thiếu vài mã hàng, hoặc copy sai từ Excel sang seeder), hệ số tra sai mà không có cách nào tự phát hiện — cần đối soát Golden Master nghiêm ngặt (±0.000001) theo đúng CLAUDE.md mục 4 trước khi go-live.
2. **Rủi ro không rõ ý nghĩa nghiệp vụ thật (mục 2, 3, 9 Phần 1):** Có thể xây sai mô hình nếu đoán nhầm `Code` là mã màu thay vì mã hàng, hoặc nhầm `Tiao` không phải là tension. Thiết kế bảng nên giữ tên cột trung lập (không đặt tên cột kiểu `tension_value` ngay từ đầu nếu chưa chắc) hoặc chờ xác nhận trước khi đặt tên cột cuối cùng.
3. **Rủi ro hành vi "ô rỗng = 0" bị hiểu nhầm là lỗi hoặc ngược lại** (mục 6 Phần 1) — nếu code mới coi hệ số 0/null lẫn lộn, có thể làm sai định lượng thực tế (0 là giá trị hợp lệ, không phải "không tìm thấy").
4. **Rủi ro hàm đã là dead code trong thực tế vận hành cũ** (không tìm thấy nơi gọi nào trong toàn bộ VBA, mục 9) — nếu build lại toàn bộ hạ tầng cho 1 hàm đã ngừng dùng từ trước migration, lãng phí effort. Đây là lý do chính khiến Dependency #1 (CH-BUS-004) mang tính chặn cứng (blocking), không thể bỏ qua.
5. **Rủi ro `Find` không tường minh `MatchCase`** (mục 5 Phần 1) — hành vi gốc phụ thuộc trạng thái Find/Replace runtime của Excel, có thể không tái lập chính xác 100% khi port sang so khớp SQL tường minh (`=` có phân biệt hoa/thường theo collation DB) — cần quyết định rõ collation/case-sensitivity khi thiết kế cột `product_code`/`width_value`.

### Estimate
**L (Large).** Lý do: mặc dù bản thân thuật toán tra cứu rất đơn giản (4 bước, không có tính toán phức tạp — nếu chỉ tính effort code thuần túy sẽ là S/M), nhưng phần lớn effort thực tế nằm ở việc **thu thập và xác thực dữ liệu bảng tra thật** (không có sẵn, phải trích xuất từ Excel gốc, đối chiếu Golden Master ±0.000001 theo từng ô trong ma trận tối đa 672 giá trị), **chờ xác nhận nghiệp vụ bắt buộc (CH-BUS-004)** trước khi bắt đầu (có thể mất nhiều ngày/tuần tùy tốc độ phản hồi), và **rủi ro cao phải làm lại nếu giả thuyết về ý nghĩa `Code`/`Width`/`Tiao` sai** (mục 2, 3 Phần 1) — đây là dạng công việc "nhỏ về code, lớn về xác minh dữ liệu và nghiệp vụ", nên xếp mức L thay vì M.

---

# PHỤ LỤC — Phát hiện bổ sung khi đọc lại kỹ (so với báo cáo audit gốc `group1_recipe_findings.md`)

1. **`Code` được khai báo kiểu `String` cứng** (không phải `Variant` như `Width`/`Tiao`) — chi tiết nhỏ nhưng có ý nghĩa: khi gọi từ công thức ô, nếu ô nguồn chứa số, Excel sẽ ép kiểu ngầm về String trước khi truyền vào, khác hành vi so khớp với `Width`/`Tiao` (giữ nguyên kiểu Variant, so sánh có thể theo kiểu số). Báo cáo gốc không phân biệt điểm này.
2. **`Select Case loai` không có `Option Compare Text`** trong cả 2 file (chỉ có `Option Explicit`) — nghĩa là so khớp `"A"`/`"B"`/`"C"` PHÂN BIỆT hoa/thường tuyệt đối ở bước này (khác với `Find` ở 2 bước kia vốn mặc định không phân biệt hoa/thường). Đây là điểm không nhất quán trong chính code gốc mà báo cáo audit ban đầu chưa nêu rõ.
3. **Xác nhận bằng đối chiếu byte-by-byte thủ công (không chỉ tin mô tả cũ):** 2 bản `TraHeSo` ở 2 workbook giống hệt nhau kể cả cấu trúc dòng trắng và thứ tự khai báo biến — củng cố thêm độ tin cậy của kết luận "copy-paste dùng chung" trong báo cáo gốc.
4. **`source-traceability.md` đã được cập nhật đúng** (dòng 14) — không còn ghi nhận sai "đã xác minh" như báo cáo audit gốc từng cảnh báo; tài liệu `.claude` hiện tại (thời điểm đọc) đã phản ánh đúng hiện trạng MISSING và trỏ đúng tới VBA-RECIPE-012/013 — điểm này khác nhẹ so với mô tả "tài liệu .claude đang ghi nhận sai hiện trạng" trong báo cáo gốc (có thể đã được sửa giữa lúc audit gốc chạy và lúc tài liệu này được viết).
