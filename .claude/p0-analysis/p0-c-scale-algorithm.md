# P0-C: Đối chiếu thuật toán Cân VBA vs `ScaleReader.cs` + Phân tích bug A "luôn ghi REJECTED"

Trạng thái: TÀI LIỆU PHÂN TÍCH + KẾ HOẠCH — KHÔNG sửa code. Không có file nào trong
`F:\DF\backend`, `F:\DF\frontend`, `F:\DF\agent` bị thay đổi trong quá trình lập tài liệu này.

Nguồn tham chiếu:
- `group3_scale_findings.md` (audit 133 procedure — đã đọc lại toàn bộ, dùng làm nền, KHÔNG viết lại từ đầu Golden test đã có).
- VBA gốc đọc trực tiếp: workbook B (`semiauto- lockmove SEND OVER6 - delta-stable-final.xlsm`) và workbook C (`semiauto-small scale - delta-stable-final.xlsm`), file trích xuất olevba tại `scratchpad\vba_extracted\`.
- Code hệ mới đọc toàn bộ: `F:\DF\agent\ScaleReader.cs`, `F:\DF\agent\Worker.cs`, `F:\DF\backend\app\Http\Controllers\WeighingJobController.php`, `F:\DF\backend\app\Models\WeighingJobItem.php`, `F:\DF\backend\app\Models\ScaleMeasurement.php`, `F:\DF\frontend\src\views\WeighingStation.vue`, `F:\DF\backend\tests\Feature\ScaleLiveWeightTest.php`, `F:\DF\backend\app\Http\Controllers\DeviceController.php`.
- Database: `docker exec df-postgres psql -U postgres -d production_web` — chỉ chạy SELECT, không sửa dữ liệu.

---

## Phần A — Đối chiếu từng bước VBA vs ScaleReader.cs / backend

### A.1 — Đọc dòng dữ liệu từ cân

**VBA** (`ModRead_putty_log.ReadLastLineFast`, Private, giống hệt cả A/B/C):
```vba
Private Function ReadLastLineFast(f As String) As String
    Dim FF As Integer, s As String, last As String
    If Dir(f) = "" Then Exit Function
    FF = FreeFile
    Open f For Input As #FF
    Do While Not EOF(FF)
        Line Input #FF, s
        If Len(s) > 0 Then last = s
    Loop
    Close #FF
    ReadLastLineFast = Trim$(last)
End Function
```
Gọi trong vòng lặp busy-wait `StartFastLoop` (`ModRead_putty_log.bas:69-87`):
```vba
Do While fastLoopRunning
    s = ReadLastLineFast(puttyPath)
    If s <> "" And s <> rawline Then
        rawline = CleanWeight(s)
        PushRawToForm rawline
    End If
    DoEvents
    SleepFast 10     ' 10ms = 100 lần/giây
Loop
```
→ Đọc **toàn bộ file** mỗi 10ms, lấy dòng cuối **không rỗng**. File không tồn tại → `Exit Function`, trả `""`, form giữ nguyên `rawline` cũ.

**.NET** (`ScaleReader.ReadSimulatedWeight`, `F:\DF\agent\ScaleReader.cs:81-109`):
```csharp
string[] lines = File.ReadAllLines(_simulationFilePath);
if (lines.Length > 0)
{
    string lastLine = lines[^1];
    return CleanWeight(lastLine);
}
```
Gọi từ `Worker.ExecuteAsync` (`F:\DF\agent\Worker.cs:51-77`) với `Task.Delay(_pollIntervalMs)`, mặc định `_pollIntervalMs = 500` (dòng 40).

**GIỐNG**: chiến lược "đọc cả file, lấy dòng cuối" giống nhau.
**KHÁC**: (1) `.NET` không lấy dòng cuối *không rỗng* — `lines[^1]` là dòng vật lý cuối cùng bất kể rỗng hay không (nếu file kết thúc bằng dòng trắng, `.NET` có thể trả `""` trong khi VBA sẽ tự động bỏ qua và lấy dòng có nội dung gần nhất trước đó). (2) Chu kỳ polling: VBA 10ms, `.NET` 500ms — chậm hơn 50 lần.
**Hậu quả**: Độ trễ hiển thị cân tăng tới 500ms (không phản ứng tức thời khi đặt vật tư lên cân); nếu file mô phỏng/kết nối cân thật kết thúc bằng dòng rỗng, `.NET` có thể trả về cân nặng "" → `CleanWeight("")` → `0.0`, khác hành vi "giữ giá trị cũ" của VBA.

### A.2 — Trích số từ chuỗi raw (đầu hay cuối)

**VBA** (luồng chính thật, dùng ở B/C — `Mod_delta_raw.PushRawToForm` gọi `Modcleanweight.ExtractLastNumber`, dòng ~1164):
```vba
Public Function ExtractLastNumber(ByVal s As String) As String
    Dim arr() As String, i As Long, t As String
    arr = Split(s, ",")
    For i = UBound(arr) To 0 Step -1        ' duyệt TỪ CUỐI mảng về đầu
        t = Trim$(arr(i))
        If IsNumeric(t) Then
            ExtractLastNumber = t
            Exit Function
        End If
    Next i
    ExtractLastNumber = ""
End Function
```
→ Split theo dấu phẩy, duyệt từ token **cuối cùng** về đầu, trả token số hợp lệ **đầu tiên gặp khi duyệt ngược** — tức là số **cuối cùng** trong chuỗi gốc.

**.NET** (`ScaleReader.CleanWeight`, `F:\DF\agent\ScaleReader.cs:111-126`):
```csharp
public double CleanWeight(string rawInput)
{
    if (string.IsNullOrEmpty(rawInput)) return 0.0;
    // Porting of VBA ExtractLastNumber and CleanScaleRaw logic
    Match match = Regex.Match(rawInput, @"[-+]?\d+(\.\d+)?");
    if (match.Success)
    {
        if (double.TryParse(match.Value, out double val))
        {
            return val;
        }
    }
    return 0.0;
}
```
`Regex.Match` (không có flag `RightToLeft`) trả về **match đầu tiên tìm thấy khi quét chuỗi từ trái sang phải** — **đây là điểm đối chiếu quan trọng, đã đọc lại nguyên văn dòng 117 để xác nhận chắc chắn**: đúng như group3 đã ghi nhận, `.NET` lấy số **đầu tiên**, ngược hoàn toàn với VBA lấy số **cuối cùng**.

**KHÁC — XÁC NHẬN LẠI CHÍNH XÁC**: VBA = số cuối cùng (duyệt ngược mảng sau `Split(",")`); .NET = số khớp regex đầu tiên (duyệt xuôi chuỗi thô, không tách theo dấu phẩy). Comment trong code .NET ("Porting of VBA ExtractLastNumber... logic") là **sai lệch với hành vi thật của chính hàm đó** — đây là bug port, không phải cố ý đổi thiết kế.
**Hậu quả**: nếu raw log có nhiều số (ví dụ mã trạm/timestamp đứng trước giá trị cân thật), `.NET` trả về sai số hoàn toàn — xem Test vector TV1.

### A.3 — Parsing / lọc ký tự (whitelist)

**VBA** — 2 lớp lọc riêng biệt trong luồng chính B/C:
- `ModRead_putty_log.CleanWeight` (Private, chạy trước khi vào `PushRawToForm`, dòng 57-64): `If c Like "[0-9.,-]" Then out = out & c` — giữ `0-9`, `.`, `,`, `-` (không giữ `+`).
- `Modcleanweight.CleanScaleRaw` (Public, chạy lại lần 2 bên trong `PushRawToForm`, dòng 1109-1123): `If ch Like "[0-9]" Or ch="+" Or ch="-" Or ch="." Or ch="," Then` — giữ `0-9`, `+`, `-`, `.`, `,`.
→ 2 lần lọc chồng nhau (module `ModRead_putty_log` lọc trước khi lưu `rawline`, rồi `Mod_delta_raw.PushRawToForm` gọi `CleanScaleRaw` lọc lại từ `s` gốc, không phải từ `rawline` đã lọc) — dư thừa nhưng vô hại vì tập ký tự gần như trùng.

**.NET** (`CleanWeight`, dòng 111-126): không có bước lọc whitelist tách biệt — dùng thẳng Regex bắt số trên chuỗi thô, gộp luôn 2 bước "lọc + trích số" thành 1.

**KHÁC**: VBA giữ **mọi** ký tự số/dấu hợp lệ trong toàn chuỗi rồi mới tách bằng dấu phẩy để chọn token; `.NET` chỉ cần regex khớp `[-+]?\d+(\.\d+)?` là dừng ngay tại match đầu tiên, không quan tâm đến việc chuỗi có bao nhiêu token. Khác biệt về **chiến lược** (token-based vs match-based), hậu quả trùng với A.2.

### A.4 — Stable / ổn định

**VBA** (`Mod_delta_raw.StableFilter`, bản **thật sự dùng** trong luồng B/C, dòng 120-138 — cũng có bản trùng lặp dead code ở `Modcleanweight.StableFilter` dòng 1182-1198, nội dung giống hệt):
```vba
Public Function StableFilter(ByVal newVal As String) As String
    Static lastVal As String
    Static lastGood As String
    Static cnt As Long

    If newVal = lastVal Then
        cnt = cnt + 1
    Else
        cnt = 0
    End If

    lastVal = newVal

    If cnt >= 1 Then
        lastGood = newVal
    End If

    StableFilter = lastGood
End Function
```
→ So sánh **chuỗi ký tự tuyệt đối** (`newVal = lastVal`, string so string, không có dung sai numeric) giữa lần đọc hiện tại và lần đọc **ngay trước đó**. "Ổn định" = 2 lần đọc liên tiếp cho **cùng một chuỗi**. Dùng `Static` nên trạng thái tồn tại xuyên suốt các lần gọi (không reset trừ khi module unload).

**.NET/backend**: **Không có hàm tương đương ở bất kỳ tầng nào** đã đọc. Cụ thể:
- `ScaleReader.cs`: không có hàm `StableFilter`/tương đương, không có biến trạng thái lưu lần đọc trước.
- `WeighingJobController::weighItem` (dòng 38-44): `'stable' => 'required|boolean'` — chỉ validate là boolean hợp lệ, **không có logic `if (!$stable) { ... }`** nào trong toàn bộ hàm (đã đọc hết dòng 36-201).
- `WeighingStation.vue:402`: `stable: true` — **hard-code cứng**, gửi luôn giá trị `true` mỗi lần gọi API, không phụ thuộc vào bất kỳ phép so sánh nào giữa các lần đọc.

**KHÁC HOÀN TOÀN**: đây là khoảng trống thuật toán lớn nhất — cơ chế chống đọc số đang nhảy/rung (bounce) của cân hoàn toàn vắng mặt ở hệ mới.
**Hậu quả**: xem TV3.

### A.5 — Delta (chênh lệch giữa các lần đọc)

**VBA** (`Mod_delta_raw.AutoFlow_OnWeight`, dòng 1695-1731):
```vba
Public Sub AutoFlow_OnWeight(ByVal rawW As Double)
    If BlockAutoFlow Then Exit Sub
    If Not AutoRunning Then Exit Sub
    If Not DeltaEnabled Then Exit Sub
    If scaleform Is Nothing Then Exit Sub
    If CurrentBoxIndex < 1 Or CurrentBoxIndex > 9 Then Exit Sub

    Dim deltaVal As Double
    ...
    If DeltaBaseWeight = -1 Then
        DeltaBaseWeight = rawW
        Exit Sub
    End If

    deltaVal = Abs(rawW - DeltaBaseWeight)

    scaleform.delta_rawline.text = Format(deltaVal, "0.00")
    CheckRange deltaVal, tp, tw
    ...
```
→ Có tính `deltaVal`, luôn lấy trị tuyệt đối.

**.NET/backend**: `WeighingJobController::weighItem` dùng thẳng `$measuredWeight = (float)$request->input('weight')` (dòng 50) để so với `$minAllowed/$maxAllowed` — **không có bất kỳ phép trừ delta nào**. `ScaleReader.ReadCurrentWeight()` cũng chỉ trả về giá trị đọc thô, không có baseline.

**KHÁC HOÀN TOÀN** — xem A.6 (đây là 2 mặt của cùng 1 cơ chế tare/delta).

### A.6 — Tare / trừ bì

**VBA** (`Mod_delta_raw.Delta_Begin`, dòng 1680-1694, gọi mỗi khi bắt đầu 1 slot mới):
```vba
Public Sub Delta_Begin()
    If Not AutoRunning Then Exit Sub
    If scaleform Is Nothing Then Exit Sub
    DeltaBaseWeight = -1          ' reset — lần đọc kế tiếp sẽ là baseline mới
    DeltaEnabled = True
    With scaleform.Controls("txt_process" & CurrentBoxIndex)
        .text = "0.00"
        .BackColor = vbWhite
        .Locked = False
    End With
End Sub
```
Kết hợp với `AutoFlow_OnWeight` (A.5): **lần đọc đầu tiên sau `Delta_Begin` không được coi là kết quả cân** — nó tự động trở thành `DeltaBaseWeight` (bì/tare), `Exit Sub` ngay, không gọi `CheckRange`, không hiển thị kết quả nào. Chỉ từ lần đọc **thứ hai** trở đi, `deltaVal = Abs(rawW - DeltaBaseWeight)` mới được tính và hiển thị/so ngưỡng.

**Backend** (`WeighingJobController::weighItem`, đã đọc toàn bộ dòng 36-201, xác nhận chắc chắn): **không có bước trừ bì nào**. Biến `$measuredWeight` lấy thẳng từ `$request->input('weight')` (dòng 50) và dùng trực tiếp để so `$inRange = ($measuredWeight >= $minAllowed && $measuredWeight <= $maxAllowed)` (dòng 64). Không có trường `base_weight`/`tare_weight` nào trong `$fillable` của `WeighingJobItem` (đã đọc toàn bộ model — chỉ có `planned_weight`, `tolerance_minus`, `tolerance_plus`, `actual_weight`).

**KHÁC HOÀN TOÀN, RỦI RO NGHIỆP VỤ CAO NHẤT**: nếu Agent/frontend gửi lên giá trị cân **gộp cả bì** (gross weight — ví dụ khay/cốc + vật tư) thay vì giá trị đã trừ bì (net), hệ mới sẽ so sai hoàn toàn với `planned_weight`. Đây chính là câu hỏi mở `CH-BUS-006` trong `open-questions.md` — **PHẢI xác nhận nghiệp vụ trước khi sửa** (xem Phần D — Dependency).

### A.7 — Threshold / ngưỡng dung sai

**VBA** (`Mod_UI_processcolor.CheckRange`, **giống hệt 100% giữa B và C**, đã trích nguyên văn ở Phần C — 3 mức):
```vba
ratio = deltaVal / target
If ratio < 0.99 Then
    newColor = RGB(250, 230, 5)     ' vàng — CHƯA ĐỦ
ElseIf ratio <= 1.01 Then
    newColor = RGB(120, 250, 20)    ' xanh — ĐẠT
Else
    newColor = RGB(255, 20, 0)      ' đỏ — VƯỢT
End If
```
→ Đối xứng theo tỉ lệ % (±1% quanh 100%), **3 mức phân biệt rõ "thiếu" khác "vượt"**.

Workbook A dùng ngưỡng khác, đơn giản hơn (`scaleform.CheckRange`, nhị phân): `Abs(w-s)/s<=0.01` → xanh `RGB(144,238,144)`/đỏ `RGB(255,128,128)`.

**Backend** (`weighItem`, dòng 56-64):
```php
$target = (float)$item->planned_weight;
$toleranceMinus = (float)$item->tolerance_minus;
$tolerancePlus = (float)$item->tolerance_plus;
$minAllowed = $target - $toleranceMinus;
$maxAllowed = $target + $tolerancePlus;
$inRange = ($measuredWeight >= $minAllowed && $measuredWeight <= $maxAllowed);
```
**Frontend** (`WeighingStation.vue:367-376`):
```js
const toleranceStatus = computed(() => {
  if (!activeIngredient.value) return 'out';
  const w = liveWeight.value;
  if (w === 0) return 'zero';
  if (w >= min && w <= max) return 'in-range';
  return 'out-of-range';
});
```
→ Chỉ **2 mức** thật sự có ý nghĩa nghiệp vụ (`in-range` / `out-of-range`), cộng thêm `'zero'` chỉ là trạng thái UI "cân rỗng chưa đặt vật tư" chứ không phải mức dung sai thứ 3. Dung sai bất đối xứng theo từng vật tư (`tolerance_minus`/`tolerance_plus` riêng) — tổng quát hơn %, nhưng **mất hẳn khái niệm "vàng — chưa đủ, tiếp tục thêm vật tư"** khác với "đỏ — đã vượt/lệch, cần xử lý khác (bớt ra hoặc override)".

**KHÁC**: 3 mức (VBA B/C) → gộp còn 2 mức (hệ mới). Ngưỡng đổi từ % cố định sang giá trị tuyệt đối theo từng item (cải tiến hợp lý), nhưng thiếu phân biệt "thiếu" và "vượt".

### A.8 — Accept / reject (quyết định lưu)

**VBA workbook B (BUG)**: `btnSave_Click` đọc màu nền control (`RGB(60,200,100)`) để quyết định `processColor`, nhưng màu này **không bao giờ được `CheckRange` gán** (`CheckRange` chỉ gán `RGB(250,230,5)`/`RGB(120,250,20)`/`RGB(255,20,0)`) → luôn rơi vào nhánh `Else` → **luôn ghi "REJECTED"** bất kể cân đạt hay không. Chi tiết đầy đủ ở Phần C.

**VBA workbook C (đã sửa)**: cùng cơ chế nhưng so đúng `RGB(120,250,20)` → hoạt động đúng.

**Backend** (hệ mới): tính **trực tiếp bằng số**, không qua màu UI ở bất kỳ khâu nào:
```php
$inRange = ($measuredWeight >= $minAllowed && $measuredWeight <= $maxAllowed);
if (!$inRange && !$overrideApproved) {
    $item->status = 'OUT_OF_TOLERANCE';
    ...
    return response()->json([...], 422);
}
```
**KHÁC — nhưng là cải tiến đúng hướng**: hệ mới **không có khái niệm "màu UI" làm nguồn sự thật** cho accept/reject nên **không kế thừa lớp bug này** (xem xác nhận đầy đủ ở Phần C mục 6).

### A.9 — Raw reading (lưu từng lần đọc thô)

**VBA**: không lưu bất kỳ lần đọc thô nào xuống Access. Biến module (`rawline`, `p1..p9`) chỉ tồn tại trong RAM khi form đang mở, bị mất khi đóng form. Chỉ 1 bản ghi/dòng chi tiết được ghi xuống `tblRECORD` khi bấm `btnSave_Click` — là giá trị **cuối cùng** hiển thị lúc bấm lưu.

**Backend**: `DeviceController::store` (`F:\DF\backend\app\Http\Controllers\DeviceController.php:24-25`) — chỉ `Cache::put("scale_live_weight_{workstationId}", $weight, 15)` (TTL 15 giây), **không ghi xuống bảng nào** — mỗi lần Agent post số cân mới, giá trị cũ trong cache bị ghi đè, không giữ lịch sử polling. `ScaleMeasurement::create()` trong `weighItem` chỉ tạo **1 bản ghi mỗi lần thao tác viên bấm xác nhận cân** (comment dòng 104: "create new for every weigh attempt, no overwrites") — tức 1 bản ghi/lần **xác nhận**, không phải 1 bản ghi/lần **đọc thô** từ cân.

**GIỐNG về bản chất** (không lưu raw polling stream ở cả 2 hệ) nhưng **khác về đơn vị lưu vết**: VBA chỉ lưu 1 dòng/batch (ghi đè hoàn toàn nếu cân lại), hệ mới lưu **mỗi lần bấm xác nhận** thành 1 `ScaleMeasurement` riêng (không ghi đè) — cải tiến audit trail tốt hơn, nhưng vẫn **không đáp ứng yêu cầu "lưu raw reading liên tục"** nếu có (không có bảng nào tên gợi ý "raw_readings"/"scale_log" trong migration đã audit).

### A.10 — Error handling (mất kết nối / dữ liệu rác)

**VBA**: `ReadLastLineFast` — `If Dir(f) = "" Then Exit Function` (trả `""`) khi file không tồn tại → form **giữ nguyên giá trị hiển thị cũ**, im lặng, không cảnh báo. `CleanScaleRaw`/`ExtractLastNumber` trả `""` khi không có số hợp lệ → `PushRawToForm` `Exit Sub` ngay, **không ghi đè** `rawline`/kết quả cũ bằng bất cứ gì.

**.NET** (`ScaleReader.ReadSimulatedWeight`, dòng 81-109):
```csharp
catch (Exception ex)
{
    _logger.LogWarning("Failed to read simulated weight: {Msg}", ex.Message);
}
return 0.0;
```
Và `CleanWeight` (dòng 111-126): nếu Regex không khớp (dữ liệu rác/lỗi cổng COM) → `return 0.0;` — **trả về một giá trị số hợp lệ là 0.0 thay vì giữ giá trị cũ hoặc báo lỗi**. `Worker.cs.PushWeightToBackendAsync` (dòng 80-106) bắt lỗi mạng bằng try/catch, khi backend không phản hồi thì gọi `_offlineQueue.SaveScaleReading(...)` — có offline queue cho lỗi **mạng**, nhưng **không có xử lý riêng cho lỗi đọc cân/cổng COM** (mọi lỗi đọc cân trong `ReadSimulatedWeight`/`CleanWeight` đều âm thầm quy về `0.0`, một giá trị hợp lệ về mặt kiểu dữ liệu nhưng sai về ý nghĩa nghiệp vụ).

**KHÁC — rủi ro thật**: VBA giữ nguyên giá trị cũ khi lỗi (an toàn hơn); `.NET` biến lỗi/rác thành "cân nặng = 0.0 kg" tường minh, có thể bị hiểu nhầm là "cân đang rỗng" (trạng thái `zero` trong `toleranceStatus` của Vue) thay vì "mất kết nối/lỗi đọc". `WeighingStation.vue` có `scaleOnline` (dòng 297-299) dựa trên catch lỗi gọi API `fetchLiveWeight` — cảnh báo mất kết nối **API**, không phải mất kết nối **cân vật lý** (Agent vẫn có thể chạy, backend vẫn phản hồi 200, nhưng giá trị cân bên trong là `0.0` giả do lỗi đọc — `scaleOnline` sẽ vẫn hiển thị xanh sai lệch với thực tế).

---

## Phần B — Test vector chứng minh hành vi khác nhau

Bảng dưới **mở rộng** 3 bộ Golden test đã có trong `group3_scale_findings.md` mục XI (Golden test 1/2/3 → TV3/TV1/TV4 bên dưới, giữ nguyên input/logic gốc, diễn giải lại theo khung bảng thống nhất) và bổ sung TV2, TV5, TV6, TV7 mới.

| # | Input (raw string / sự kiện) | VBA output (dự đoán theo code đã trích) | .NET/backend output hiện tại (dự đoán theo code đã trích) | Khác nhau? | Ảnh hưởng thực tế |
|---|---|---|---|---|---|
| **TV1** | `"12,ST,GS,+000010.5g"` (giả định mã trạm/prefix số "12" đứng trước dữ liệu cân) | `CleanScaleRaw` giữ `[0-9+\-.,]` → `"12,,,+000010.5"`; `Split(",")` → mảng có phần tử cuối `"+000010.5"` là numeric → `ExtractLastNumber` = **`"+000010.5"`** (đúng giá trị cân thật) | `Regex.Match(@"[-+]?\d+(\.\d+)?")` quét từ trái, khớp ngay `"12"` là match đầu tiên → `CleanWeight` = **`12.0`** (SAI — đây là mã trạm, không phải cân nặng) | **CÓ — sai lệch nghiêm trọng** | Nếu định dạng log cân thật có prefix số ở đầu dòng (mã máy, timestamp dạng số, số thứ tự), Agent sẽ gửi lên backend một con số hoàn toàn sai (12.0 thay vì 10.5), backend so dung sai với số rác này → có thể tạo ACCEPTED/REJECTED sai hoặc yêu cầu override oan |
| **TV2** | `"ST,GS,+000010.5g"` (không có prefix số nào trước giá trị cân — trường hợp đơn giản) | `ExtractLastNumber` = `"+000010.5"` | `CleanWeight` = `10.5` (match đầu tiên trùng với số duy nhất có trong chuỗi) | Không (trùng nhau ở case đơn giản) | Không ảnh hưởng — nhưng **chỉ đúng do trùng hợp cấu trúc dữ liệu**, không phải vì thuật toán tương đương; cần test TV1 mới lộ lỗi thật |
| **TV3** (StableFilter, mở rộng Golden test 1) | Chuỗi đọc liên tiếp: `["12.30", "12.3", "12.3"]` (cùng giá trị vật lý nhưng biểu diễn chuỗi khác nhau ở lần đọc đầu) | So sánh **string tuyệt đối**: lần 2 `"12.3"` ≠ lần 1 `"12.30"` → `cnt=0`, `lastGood` chưa cập nhật; lần 3 `"12.3"="12.3"` (so lần 2) → `cnt=1` → `lastGood="12.3"`. **Phải mất tối thiểu 2 lần đọc giống hệt chuỗi** (~20ms ở polling 10ms) mới coi là "ổn định" và mới được đẩy vào `AutoFlow_OnWeight`/`CheckRange` | Không có `StableFilter` — `stable: true` gửi cứng ngay tại lần xác nhận đầu tiên bất kể dữ liệu vừa mới đọc có đang dao động hay không | **CÓ** | Hệ mới có thể xác nhận (lưu `ScaleMeasurement`, đổi trạng thái item) ngay tại một khung hình cân đang **rung/nhảy số** (do rung động cơ khí, gió, thao tác viên chưa đặt ổn định vật tư) — VBA có cơ chế "khoá" tự nhiên chống việc này, hệ mới thì không |
| **TV4** (Tare/delta, mở rộng Golden test 3) | `Delta_Begin` reset baseline → đọc `2.3` (khay rỗng) → đọc `52.1` (khay+vật tư), target=50.0g, tolerance ±1% (quy đổi `tolerance_minus=tolerance_plus=0.5g` để so trực tiếp) | Lần đọc `2.3`: `DeltaBaseWeight=-1` → gán `DeltaBaseWeight=2.3`, **Exit Sub, không tính là kết quả**. Lần đọc `52.1`: `deltaVal = |52.1-2.3| = 49.8` → so `CheckRange(49.8, ...)` → `ratio=49.8/50=0.996` → **ACCEPTED** (trong khoảng ±1%) | Nếu client gửi thẳng giá trị đọc được (không tự trừ bì): `weighItem` nhận `weight=52.1` → `minAllowed=49.5, maxAllowed=50.5` → `52.1 > 50.5` → **`OUT_OF_TOLERANCE` (422)**, yêu cầu override dù thực tế đạt dung sai đúng (net 49.8g) | **CÓ — sai lệch nghiêm trọng nếu Agent/UI không tự trừ bì** | Đây là rủi ro nghiệp vụ lớn nhất (CH-BUS-006): nếu quy trình cân thật cần đặt khay/cốc rỗng lên cân trước, hệ mới sẽ liên tục báo sai lệch dung sai giả, buộc Shift Leader phải override hàng loạt cho các trường hợp thực chất đạt chuẩn |
| **TV5** (ngưỡng 3 mức bị gộp 2 mức) | target=100g, tolerance ±1g. `deltaVal=98.5` (thiếu 1.5g, chưa đạt nhưng gần đạt) | `ratio=98.5/100=0.985 < 0.99` → màu **vàng** `RGB(250,230,5)` = "chưa đủ, tiếp tục thêm vật tư" — KHÔNG phải reject, chỉ là trạng thái trung gian trong lúc đang cân, operator chưa bấm Save | `measuredWeight=98.5`, `minAllowed=99, maxAllowed=101` → `98.5 < 99` → `$inRange=false` → nếu operator bấm "Xác nhận cân" ngay lúc này: **`OUT_OF_TOLERANCE` (422)**, cùng 1 lỗi và cùng luồng override như trường hợp **vượt cân thật sự** | **CÓ** | Hệ mới không phân biệt được về mặt UI/luồng nghiệp vụ giữa "chưa cân đủ, cứ thêm vào" và "đã cân sai/vượt, cần supervisor quyết định" — cả hai đều rơi vào cùng 1 thông báo lỗi 422 + luồng override, có thể khiến thao tác viên xin override oan cho trường hợp chỉ cần thêm vật tư là xong |
| **TV6** (dữ liệu rác/lỗi COM) | Raw nhận được từ cổng COM: `"SCALE ERROR"` hoặc chuỗi rỗng `""` (nhiễu tín hiệu) | `CleanScaleRaw("SCALE ERROR")` → không ký tự nào khớp `[0-9+\-.,]` → trả `""` → `PushRawToForm`: `If rawNum = "" Then Exit Sub` → **giữ nguyên giá trị `rawline`/kết quả cũ hiển thị trên form**, không có gì thay đổi, không lỗi | `ScaleReader.CleanWeight("SCALE ERROR")` → Regex không khớp → `return 0.0;` — trả về **giá trị số hợp lệ 0.0**, được hiểu là "cân đang đọc 0kg" | **CÓ — rủi ro dữ liệu sai lặng lẽ** | Dữ liệu rác từ cổng COM (nhiễu, ngắt kết nối tạm thời) bị hệ mới âm thầm biến thành "0.0 kg" hợp lệ về kiểu dữ liệu, có thể được cache/hiển thị/thậm chí gửi lên backend như một số đọc thật, trong khi VBA sẽ giữ nguyên số cũ và không coi đây là 1 lần đọc hợp lệ |
| **TV7** (polling interval — độ trễ) | Vật tư đặt lên cân, cân vật lý ổn định sau ~100ms | Với polling 10ms + yêu cầu 2 lần đọc liên tiếp giống hệt để "stable", VBA phản ánh giá trị ổn định trên UI trong khoảng **~110-120ms** sau khi cân vật lý ổn định | Với polling 500ms và không có debounce, `.NET`/frontend có thể hiển thị giá trị **bất kỳ thời điểm nào trong khoảng 0-500ms** kể từ lúc cân bắt đầu dao động — bao gồm cả lúc cân **chưa** ổn định cơ học (đang nảy/rung) | **CÓ** | Thao tác viên trên hệ mới có thể nhìn thấy và xác nhận (bấm nút) một con số đang trong quá trình dao động vật lý của cân (do polling thưa + không debounce), dẫn đến kết quả cân ghi nhận sai số cao hơn hệ cũ dù bản thân cân vật lý hoạt động tốt |

---

## Phần C — Bug "luôn ghi REJECTED" (workbook B)

### C.1 — Nguyên văn `GetProcessStatus` (workbook B, `Mod_print_tsc224.bas`)

Trích trực tiếp từ `scratchpad\vba_extracted\semiauto__lockmove_SEND_OVER6___delta_stable_final_xlsm_.txt`, dòng 1251-1259:
```vba
Public Function GetProcessStatus(tb As MSForms.TextBox) As String
    Select Case tb.BackColor
        
        Case RGB(60, 200, 100)
            GetProcessStatus = "ACCEPTED"
        Case Else
            GetProcessStatus = "REJECTED"
    End Select
End Function
```
→ Giá trị RGB dùng để so sánh ACCEPTED: **`RGB(60, 200, 100)`**.

### C.2 — Nguyên văn `CheckRange` (workbook B, `Mod_UI_processcolor.bas`)

Trích trực tiếp từ cùng file, dòng 389-428:
```vba
Public Sub CheckRange(deltaVal As Double, _
                      txtProcess As Object, _
                      txtTarget As Object)
    ...
    ratio = deltaVal / target

    If ratio < 0.99 Then
        newColor = RGB(250, 230, 5)
    ElseIf ratio <= 1.01 Then
        newColor = RGB(120, 250, 20)
    Else
        newColor = RGB(255, 20, 0)
    End If

    If txtProcess.BackColor <> newColor Then
        txtProcess.BackColor = newColor
    End If
End Sub
```
→ 3 màu **thực sự** có thể được gán khi đạt/không đạt dung sai: `RGB(250,230,5)` (vàng), **`RGB(120,250,20)`** (xanh — đạt dung sai), `RGB(255,20,0)` (đỏ). Đã grep toàn bộ workbook B, xác nhận **không có bất kỳ đoạn code nào khác** gán màu `RGB(60,200,100)` cho control `txt_process*`.

### C.3 — Xác nhận 2 giá trị RGB khác nhau

- Màu `GetProcessStatus` (C.1) so sánh để trả "ACCEPTED": **RGB(60, 200, 100)**.
- Màu `CheckRange` (C.2) thực sự gán khi đạt dung sai (nhánh xanh, `0.99 ≤ ratio ≤ 1.01`): **RGB(120, 250, 20)**.
- **RGB(60,200,100) ≠ RGB(120,250,20)** — khác nhau ở cả 3 thành phần (R: 60 vs 120, G: 200 vs 250, B: 100 vs 20). Do đó `Select Case tb.BackColor` trong `GetProcessStatus` **không bao giờ khớp `Case RGB(60,200,100)`**, luôn rơi vào `Case Else` → trả `"REJECTED"`.

Ngoài ra, bug lặp lại **thêm 1 lần nữa** ngay trong `scaleform.btnSave_Click` của workbook B (dòng 892-946), không chỉ ở `GetProcessStatus`:
```vba
Set c = Me.Controls("txt_process" & i)
If c.BackColor = RGB(60, 200, 100) Then
    processColor = "ACCEPTED"
Else
    processColor = "REJECTED"
End If
```
→ Đây chính là đoạn **trực tiếp ghi giá trị `processColor` xuống Access**, không phải qua `GetProcessStatus` (hàm này thực chất chỉ dùng ở `Mod_print_tsc224` khi in, không được gọi trong `btnSave_Click`) — nhưng **cùng lỗi so màu sai `RGB(60,200,100)`** được lặp lại độc lập ở 2 nơi trong workbook B.

### C.4 — Giá trị đúng dự kiến (workbook C đã sửa)

Trích `scratchpad\vba_extracted\semiauto_small_scale___delta_stable_final_xlsm_.txt`, dòng 1264-1272:
```vba
Public Function GetProcessStatus(tb As MSForms.TextBox) As String
    Select Case tb.BackColor
        
        Case RGB(120, 250, 20)
            GetProcessStatus = "ACCEPTED"
        Case Else
            GetProcessStatus = "REJECTED"
    End Select
End Function
```
`Mod_UI_processcolor.CheckRange` của workbook C **giống hệt 100%** workbook B (đã đối chiếu byte-for-byte, cùng nội dung dòng 389-428 nêu ở C.2) — chỉ có `GetProcessStatus`/`btnSave_Click` (VBA-SCALE-129) được sửa đúng thành **RGB(120, 250, 20)**, khớp với màu thật `CheckRange` gán khi đạt dung sai.

### C.5 — Dữ liệu lịch sử có bị ảnh hưởng không

**Cột bị ảnh hưởng trực tiếp**: `processColor` trong Access `tblRECORD` → tương ứng cột **`processCOLOR`** (đúng chính tả cột thật, viết hoa) trong Postgres `legacy_df_scale."tblRECORD"`. Cột này chỉ được ghi ở dòng **chi tiết** (mỗi `RACK`/`DYECODE`/`WEIGHT`/`PROCESS`), không ghi ở dòng header (header chỉ có `COLOR`/`CODE`/`MACHINE`/`LEVEL`/`TIME`) — 2 loại dòng cùng `batchID` nhưng do 2 câu `INSERT` tách biệt trong `btnSave_Click`.

**Đã chạy SELECT COUNT thật qua `docker exec df-postgres psql -U postgres -d production_web`** (chỉ SELECT, không sửa dữ liệu):

```sql
SELECT "processCOLOR", COUNT(*) FROM legacy_df_scale."tblRECORD" GROUP BY "processCOLOR" ORDER BY 2 DESC;
```
Kết quả thật:
| processCOLOR | Số dòng |
|---|---|
| ACCEPTED | 77,290 |
| (rỗng/NULL — dòng header, không có processCOLOR) | 31,716 |
| **REJECTED** | **31,361** |
| "0" (giá trị rác/lỗi nhập liệu) | 292 |
| ký tự lạ "⸳" | 1 |
| **Tổng cộng `tblRECORD`** | **140,660** |

```sql
SELECT "processCOLOR", COUNT(*) FROM legacy_df_scale."tblRECORD_chem" GROUP BY "processCOLOR";
```
Kết quả: toàn bộ 5,061 dòng của `tblRECORD_chem` có `processCOLOR` **rỗng/NULL** — bảng này chưa từng ghi nhận giá trị ACCEPTED/REJECTED nào (có thể được nạp qua đường khác, ngoài phạm vi 3 workbook cân được giao).

**KHÔNG XÁC ĐỊNH ĐƯỢC trạm nào dùng workbook B để đối soát chính xác con số bị ảnh hưởng**, lý do cụ thể đã kiểm chứng bằng dữ liệu thật:
- Đã kiểm tra cột `MACHINE` (cột duy nhất có khả năng gợi ý nguồn trạm/máy) trên các dòng chi tiết có `processCOLOR='REJECTED'`: **100% các dòng này có `MACHINE` rỗng** — không phải vì trạm B không ghi `MACHINE`, mà vì `MACHINE` chỉ được ghi ở câu `INSERT` header riêng biệt (theo đúng code `btnSave_Click` ở C.3), dòng chi tiết luôn để trống cột này bất kể workbook A/B/C. Đã đối chiếu thêm: dòng có `processCOLOR='ACCEPTED'` **cũng 100% có `MACHINE` rỗng** — xác nhận đây là đặc điểm cấu trúc bảng phẳng, không phải tín hiệu phân biệt workbook.
- Không có cột nào trong `tblRECORD`/`tblRECORD_chem` (đã `\d` cả 2 bảng, liệt kê đủ 14/12 cột) ghi nhận tên workbook, đường dẫn file nguồn, hoặc mã trạm/máy tính vật lý đã tạo ra dòng dữ liệu đó.
- Do đó: **31,361 dòng REJECTED là con số thật đếm được từ toàn hệ thống `tblRECORD`, nhưng không thể tách riêng phần nào trong số đó là do workbook B (bug) và phần nào là REJECTED thật sự hợp lệ từ workbook A/C (logic đúng)**. Không đủ căn cứ để kết luận toàn bộ hay một phần cụ thể của 31,361 dòng này là "REJECTED giả" — cần xác nhận nghiệp vụ (ví dụ: hỏi trực tiếp người vận hành xưởng những trạm/máy tính nào từng cài workbook B, và khoảng thời gian sử dụng, để lọc theo cột `TIME`) trước khi coi bất kỳ phần nào của dữ liệu lịch sử REJECTED là "không đáng tin".

### C.6 — Hệ thống mới xử lý thế nào (xác nhận không kế thừa bug)

Đã đọc toàn bộ `WeighingJobController::weighItem` (dòng 36-201) — xác nhận **accept/reject được tính hoàn toàn bằng số**, không có bất kỳ khái niệm "màu UI" nào ở tầng server:
```php
$minAllowed = $target - $toleranceMinus;
$maxAllowed = $target + $tolerancePlus;
$inRange = ($measuredWeight >= $minAllowed && $measuredWeight <= $maxAllowed);
```
Trạng thái `$item->status` được set trực tiếp là `'OUT_OF_TOLERANCE'` hoặc `'COMPLETED'` dựa trên `$inRange`/`$overrideApproved` — không có bước trung gian "đọc màu nền control" nào có thể lệch nhau giữa 2 module như ở VBA. **Kết luận: hệ mới không kế thừa bug này**, vì đổi hẳn kiến trúc so sánh (số thay vì màu) đã loại bỏ toàn bộ lớp lỗi "2 module định nghĩa màu khác nhau".

Lưu ý phụ (đã ghi trong `group3_scale_findings.md` mục 4, xác nhận lại): trường `process_color` **vẫn tồn tại** trong `$fillable` của model `ScaleMeasurement` (`F:\DF\backend\app\Models\ScaleMeasurement.php:41`) nhưng `weighItem()` (dòng 105-118) **không gán giá trị này** khi tạo `ScaleMeasurement` mới → cột này sẽ luôn `NULL` cho mọi dữ liệu tạo qua hệ mới. Không phải bug (vì logic accept/reject nay dựa vào `status`/`override_approved`), nhưng là cột "chết" cần dọn dẹp hoặc xác nhận nghiệp vụ có cần dùng lại không.

### C.7 — Đề xuất test case phòng hồi quy

Không viết code test, chỉ mô tả input/expected để nhóm dev tự hiện thực khi được duyệt sửa code:

- **Test case "Accept/reject không phụ thuộc màu UI"**: Gọi `POST /api/weighing-jobs/items/{id}/weigh` với `weight` nằm đúng giữa khoảng `[planned_weight - tolerance_minus, planned_weight + tolerance_plus]` (ví dụ `planned_weight=100`, `tolerance_minus=1`, `tolerance_plus=1`, `weight=100.0`). Expected: HTTP 200, `item.status = 'COMPLETED'`, không có lỗi `OUT_OF_TOLERANCE`. Lặp lại với `weight` ở đúng biên dưới (`99.0`) và biên trên (`101.0`) — cả 2 đều phải `COMPLETED` (không "luôn reject" như bug workbook B). Đây là test hồi quy trực tiếp nhắm vào đúng lớp bug đã phát hiện: đảm bảo logic accept/reject của hệ mới **không bao giờ** phụ thuộc vào một hằng số/màu song song không đồng bộ với ngưỡng dung sai thật — nếu sau này có ai refactor logic accept/reject sang dùng enum/màu trạng thái, test này sẽ bắt được lỗi tương tự ngay lập tức.

---

## Phần D — Kế hoạch FIX-002 (CHỈ LẬP KẾ HOẠCH, KHÔNG THỰC HIỆN)

### FIX-002: Sửa `ScaleReader.CleanWeight` (lấy số cuối thay vì đầu) + bổ sung StableFilter/delta/tare còn thiếu

**Phạm vi**: Vá đúng thuật toán trích số cân (đảo chiều regex → lấy token cuối theo dấu phẩy, port đúng `ExtractLastNumber`), và bổ sung 2 cơ chế thuật toán cân hoàn toàn vắng mặt ở hệ mới: (1) `StableFilter` (chống đọc số khi cân đang dao động), (2) tare/delta (trừ bì theo từng item/slot). Không đổi kiến trúc tổng thể (giữ nguyên mô hình "1 item tại 1 thời điểm theo sequence job" của `WeighingStation.vue`, không quay lại mô hình "6-9 ô song song" của VBA).

**File dự kiến sửa**:
- `F:\DF\agent\ScaleReader.cs` — sửa `CleanWeight` (lấy token cuối), thêm hàm `StableFilter`-tương-đương (state theo từng workstation, không phải static toàn cục như VBA vì Agent có thể phục vụ nhiều cân) và state tare (`_baseWeight` reset khi có lệnh "bắt đầu slot mới" từ backend/UI).
- `F:\DF\agent\Worker.cs` — cân nhắc giảm `_pollIntervalMs` nếu nghiệp vụ yêu cầu độ trễ gần với 10ms cũ (đánh đổi CPU/network traffic — cần bàn với người phụ trách hạ tầng Agent, không tự ý đổi).
- `F:\DF\backend\app\Http\Controllers\WeighingJobController.php::weighItem` — cân nhắc dùng thật giá trị `stable` gửi lên (hiện chỉ validate, không dùng) để chặn lưu khi `stable=false`; cân nhắc thêm field tare nếu quyết định hệ thống (chứ không phải Agent) chịu trách nhiệm trừ bì (phụ thuộc câu trả lời CH-BUS-006).
- `F:\DF\frontend\src\views\WeighingStation.vue` — bỏ hard-code `stable: true` (dòng 402), thay bằng giá trị thật nhận từ Agent/API cân sống; cân nhắc thêm trạng thái UI thứ 3 ("chưa đủ — vàng") tách khỏi "vượt/lệch — đỏ" trong `toleranceStatus` (dòng 367-376) nếu nghiệp vụ xác nhận cần giữ phân biệt 3 mức như VBA B/C.

**Database change**: Cân nhắc cần thêm cột lưu **stable-flag thật** (không phải chỉ tin tưởng request body) và **base_weight/tare_weight** trên `app.scale_measurements` hoặc `app.weighing_job_items` nếu quyết định hệ thống lưu vết việc trừ bì (phục vụ audit "tại sao actual_weight lại là net chứ không phải gross"). Raw-reading log (lưu từng lần đọc liên tục, không chỉ lần xác nhận) là optional — VBA gốc cũng không có, không bắt buộc để đạt tương đương hành vi, chỉ cân nhắc nếu nghiệp vụ muốn audit sâu hơn legacy.

**Migration**: Nếu quyết định thêm cột, đặt tên file dự kiến theo convention đã thấy trong dự án (`YYYY_MM_DD_HHMMSS_add_stability_tare_fields_to_scale_measurements.php`), thêm cột dạng: `is_stable_confirmed boolean`, `base_weight_tare numeric nullable`. Không tự tạo file migration trong giai đoạn lập kế hoạch này.

**Acceptance criteria** (map trực tiếp với Phần B):
- TV1: input `"12,ST,GS,+000010.5g"` → `CleanWeight` phải trả `10.5`, không phải `12.0`.
- TV2: input `"ST,GS,+000010.5g"` → vẫn trả `10.5` (không phá vỡ case đơn giản đang đúng).
- TV3: chuỗi đọc `["12.30","12.3","12.3"]` → giá trị "ổn định" (được phép dùng để xác nhận) chỉ xuất hiện từ lần đọc thứ 3 trở đi, đúng ngữ nghĩa string-compare của VBA.
- TV4: nếu quyết định hệ thống tự trừ bì — chuỗi đọc `[2.3 (tare), 52.1 (gross)]` với target 50±0.5 phải cho kết quả **ACCEPTED** (net 49.8), không phải `OUT_OF_TOLERANCE` (gross 52.1).
- TV5: cần quyết định rõ (bàn nghiệp vụ) có khôi phục mức "vàng — chưa đủ" tách khỏi "đỏ — vượt" hay không; nếu có, test giá trị `98.5` (target 100±1) phải không rơi vào cùng luồng lỗi 422/override như giá trị vượt thật (ví dụ `102`).
- TV6: input rác `"SCALE ERROR"` → không được trả `0.0` một cách im lặng; phải giữ giá trị cũ hoặc trả tín hiệu lỗi rõ ràng phân biệt với "cân đọc đúng 0kg".
- TV7: xác nhận nghiệp vụ về polling interval chấp nhận được (500ms hay cần giảm) trước khi coi đây là đã "pass".

**Regression test**: Bổ sung test case Phần C.7 (accept/reject tại biên dung sai, không phụ thuộc màu/state trung gian) vào `F:\DF\backend\tests\Feature\ScaleLiveWeightTest.php` hoặc file test mới cùng thư mục; cần thêm test .NET riêng cho `ScaleReader.CleanWeight`/`StableFilter` (hiện **hoàn toàn không có test project cho Agent** — đây là khoảng trống hạ tầng test cần giải quyết trước hoặc song song với FIX-002, nếu không sẽ không có cách nào chứng minh bằng CI rằng `CleanWeight` đã sửa đúng).

**Rollback**: Vì đây là sửa logic thuần túy (không đổi schema bắt buộc trừ khi thêm cột optional ở trên), rollback = revert commit sửa `ScaleReader.cs`/`WeighingJobController.php`/`WeighingStation.vue` về bản trước. Nếu có migration thêm cột, dùng migration `down()` để drop cột — an toàn vì cột mới không được đọc bởi code cũ.

**Dependency**: **Bắt buộc chờ xác nhận nghiệp vụ `CH-BUS-006`** (`F:\DF\.claude\open-questions.md`) — quy trình cân mới có yêu cầu trừ bì bằng tay (nút TARE vật lý trên cân) hay hệ thống phần mềm phải tự trừ như VBA — trước khi triển khai phần tare/delta của FIX-002. Phần sửa `CleanWeight` (trích số) và phần thêm `StableFilter` **không phụ thuộc** câu trả lời này, có thể làm độc lập trước.

**Rủi ro**:
- Sửa `CleanWeight` thay đổi hành vi trích số cân — theo CLAUDE.md mục 7, dự án đang ở **Phase 12 (UAT & Chạy song song)**, đã có kế hoạch triển khai Local Agent tại 2 trạm làm việc mẫu vận hành thực tế cân/in tem 7 ngày liên tục. **Cần xác nhận cụ thể trạm pilot đã bắt đầu ghi dữ liệu thật hay chưa trước khi deploy bản vá** — nếu đã có dữ liệu cân đang chạy dùng thuật toán trích số cũ (lấy số đầu), việc đổi sang lấy số cuối giữa chừng có thể tạo ra sự không nhất quán giữa dữ liệu trước/sau vá trong cùng 1 đợt pilot, gây khó khăn khi đối soát Golden Master.
- Bổ sung `StableFilter` có thể làm chậm/khó chịu trải nghiệm thao tác viên nếu ngưỡng "ổn định" implement quá chặt (ví dụ yêu cầu nhiều lần đọc giống hệt hơn cần thiết do polling 500ms thay vì 10ms của VBA — 1 lần "không ổn định" ở hệ mới tương đương độ trễ dài hơn nhiều so với VBA).
- Thêm tare/delta là thay đổi lớn nhất về hành vi nghiệp vụ (ảnh hưởng trực tiếp đến số liệu `actual_weight` ghi vào audit/report) — rủi ro cao nhất trong toàn bộ FIX-002, phải làm sau cùng và có UAT riêng.

**Estimate**:
- **Sửa `CleanWeight` (lấy số cuối)**: **S** (nhỏ) — thay đổi 1 hàm thuần túy, logic đơn giản (đảo `Regex.Match` → `Split(',')` + duyệt ngược, hoặc dùng `Regex.Matches` rồi lấy phần tử cuối), không đụng schema, không đụng luồng nghiệp vụ khác. Rủi ro chính chỉ là thiếu test project cho Agent (`.NET`) nên cần viết test mới song song.
- **Bổ sung `StableFilter` đầy đủ**: **M** (trung bình) — cần thiết kế state theo từng workstation (khác VBA `Static` đơn giản vì 1 file Excel = 1 máy, còn Agent/backend có thể phục vụ nhiều trạm đồng thời), cần quyết định tầng nào giữ state (Agent hay backend hay cả 2), cần đổi cả frontend (bỏ hard-code `stable: true`) và có thể cả backend (dùng giá trị `stable` thật để chặn lưu).
- **Bổ sung tare/delta đầy đủ**: **L** (lớn) — phụ thuộc quyết định nghiệp vụ CH-BUS-006 chưa có; nếu hệ thống phải tự trừ bì, cần đổi luồng UI (thêm bước "đặt khay rỗng → bấm tare" hoặc tự động phát hiện "đọc đầu tiên sau khi chọn item = baseline" giống VBA `Delta_Begin`), đổi API `weighItem` (thêm field base_weight hoặc nhận `net_weight` đã tính sẵn), khả năng cần schema mới, và **bắt buộc UAT lại toàn bộ luồng cân tại xưởng pilot** vì đây là thay đổi hành vi nghiệp vụ cốt lõi, không phải chỉ sửa lỗi kỹ thuật.
- **Ngưỡng 3 mức (vàng/xanh/đỏ)**: **S–M** tuỳ quyết định — nếu chỉ đổi computed property ở Vue thì nhỏ; nếu backend cũng cần phân biệt 3 trạng thái (ví dụ để báo cáo Pareto sau này) thì cần đổi cả `$item->status` enum và migration — cần xác nhận phạm vi trước khi ước lượng chính xác.

Tổng thể FIX-002 nên tách làm ít nhất 2 đợt triển khai độc lập: **Đợt 1 (S–M)**: sửa `CleanWeight` + `StableFilter` + ngưỡng 3 mức (không phụ thuộc câu trả lời nghiệp vụ nào, có thể làm ngay sau khi được duyệt code). **Đợt 2 (L)**: tare/delta — chờ CH-BUS-006, làm riêng, UAT riêng.

---

## Tóm tắt phát hiện mới (so với `group3_scale_findings.md`)

1. Đã trích **nguyên văn xác nhận lại từ chính file VBA gốc** (không chỉ dựa vào audit trước) toàn bộ chuỗi hàm `ReadLastLineFast → CleanWeight(private) → PushRawToForm → CleanScaleRaw → ExtractLastNumber → StableFilter → AutoFlow_OnWeight(tare+delta) → CheckRange(3 mức)` — xác nhận đúng 100% những gì `group3_scale_findings.md` đã ghi nhận, không phát hiện sai lệch nào so với audit trước.
2. **Xác nhận bằng số liệu DB thật**: `legacy_df_scale."tblRECORD"` có 140,660 dòng, trong đó 31,361 dòng (~22.3%) có `processCOLOR='REJECTED'`, 77,290 dòng ACCEPTED. **Không có cột nào trong `tblRECORD`/`tblRECORD_chem` (đã kiểm tra đủ 14/12 cột) hoặc dữ liệu nào (đã kiểm tra cột `MACHINE`) cho phép liên kết một dòng dữ liệu cụ thể với workbook nguồn (A/B/C)** — kết luận không thể tách phần REJECTED thật khỏi REJECTED giả do bug workbook B chỉ bằng truy vấn SQL; cần xác nhận nghiệp vụ (trạm nào, khoảng thời gian nào dùng workbook B) trước khi đối soát Golden Master.
3. Bảng `legacy_df_scale."tblRECORD_chem"` (5,061 dòng) có `processCOLOR` **rỗng 100%** — chưa từng ghi nhận ACCEPTED/REJECTED, gợi ý luồng hóa chất được nạp qua đường khác ngoài phạm vi 3 workbook cân đã audit — cần lưu ý cho nhóm phụ trách nguồn dữ liệu `_chem`.
