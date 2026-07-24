# P0-B — Truy vết luồng TẠO hàng chờ điều phối mới + Chênh lệch quy tắc 250L

Ngày viết: 2026-07-17
Phạm vi: Kế hoạch phân tích — KHÔNG sửa code. Nguồn: `group2_dispatch_findings.md` (đọc lại toàn bộ), VBA gốc đọc trực tiếp (`C3_grid_load_row_lock_id_FB___xlsm_.txt`, `MACHINE_ID_LOCKED_xlsm_.txt`), code web đọc trực tiếp (`MachineDispatchController.php`, `MachineDispatch.php`, `ProductionBatchController.php`, `Machine.php`, `Tank.php`, `routes/api.php`, `MachineDispatchConcurrencyTest.php`, `02_target_normalized_schema_postgresql.sql`).

---

## PHẦN A — Truy vết đầy đủ luồng tạo hàng chờ (P0-B)

### 1. Nguồn dữ liệu — 2 luồng nhập

**Luồng a — Nhập tay qua form `mainform` (Box1-7):** người vận hành gõ trực tiếp vào các TextBox Box1(color)…Box7(confirm2), bấm `CommandButton3/4/5` để chọn Tank/confirm2=OK/Machine qua popup `formselect1`/`formselect2`, rồi bấm nút Save → `btnSAVE_Click()`.

**Luồng b — Quét QR vào Box1 → `Box1_AfterUpdate()` (chỉ có ở `mainform`, KHÔNG có ở `checkform`/`subform` — 2 form đó dùng `TextBox1_AfterUpdate()` tương tự nhưng đơn giản hơn):**
- Bản C3 (`Box1_AfterUpdate`, dòng ~ trong `mainform.frm`): làm sạch chuỗi quét (`CleanLeadingGarbage`), thay `"/"→"-"`, gộp `"--"`, `Split` theo `"-"` → gán `mảng[0]→Box1(color)`, `mảng[1]→Box2(code)`, `mảng[2]→Box4(machine)`, `mảng[3]→Box6(level)`; đồng thời parse thêm 2 đoạn `"-dye-...-chem-..."` để tách 2 mã QR phụ gán vào `raw_qr_dye`/`raw_qr_chem`.
- Bản MID (`Box1_AfterUpdate`): làm y hệt bước clean/split cơ bản (Box1/2/4/6) nhưng **KHÔNG có đoạn parse `-dye-/-chem-`** (thiếu 2 trường phụ).
- Trong cả 2 bản: `UserForm_Change()` gán mặc định `Box3.Text = "OK"` (confirm1 luôn mặc định "OK", không phải người dùng gõ tay).

Ghi chú: đây là điểm khởi tạo dữ liệu form trước khi bấm Save — bản thân việc quét QR KHÔNG tự động lưu DB, chỉ điền form; `btnSAVE_Click` mới thực sự ghi.

### 2. Điều kiện tạo — Color/Code bắt buộc không rỗng

Nguyên văn `btnSAVE_Click()` (giống hệt ở cả 2 workbook, C3 dòng 258-261, MID dòng 252-255):
```vb
If Trim(Me.Box1.Text) = "" Or Trim(Me.Box2.Text) = "" Then
    MsgBox "Khong du thong tin", vbExclamation
    Exit Sub
End If
```
Chỉ 2 trường Box1 (color) và Box2 (code) bị bắt buộc không rỗng. Machine (Box4), Tank (Box5), Level (Box6), confirm2 (Box7) **không bị chặn rỗng ở bước này** — có thể lưu `tbl_input_all` với machine/tank/level trống (đúng với ý nghĩa "hàng chờ INPUT/WAITING chưa gán máy").

### 3. Kiểm tra trùng — `Exists_ColorCode`

Định nghĩa giống hệt nhau ở cả 2 workbook (C3 dòng 484-498, MID dòng 449-463):
```vb
Public Function Exists_ColorCode(color As String, code As String) As Boolean
    Dim cn As Object, rs As Object, sql As String
    Set cn = GetConn()

    sql = "SELECT id FROM tbl_input_all WHERE " & _
          "[color]='" & Replace(color, "'", "''") & "' AND " & _
          "[code]='" & Replace(code, "'", "''") & "';"

    Set rs = cn.Execute(sql)
    Exists_ColorCode = Not rs.EOF
    rs.Close
    cn.Close
End Function
```
- Khóa trùng theo **cặp field (color, code)**, chỉ kiểm tra trong bảng `tbl_input_all` (KHÔNG kiểm tra chéo sang `tbl_tosend`/`tbl_sentlog` — nghĩa là nếu 1 dòng đã được `MoveToSend` khỏi `tbl_input_all`, cùng color+code đó có thể được nhập lại mà không bị chặn trùng).
- Nối chuỗi trực tiếp (escape `'`→`''` thủ công), không dùng parameterized query — rủi ro SQL injection về nguyên tắc, nhưng đặc thù chung của toàn bộ code Access cũ.
- Gọi từ `btnSAVE_Click` TRƯỚC khi Insert (dòng 267 C3 / dòng 260 MID).

### 4. Điều kiện máy/thùng — danh sách đầy đủ

Trích từ `mainform.CommandButton5_Click()` (chọn máy, giống hệt cả 2 bản, C3 dòng 347+, MID dòng 312-319):
```vb
arrVD = Array( _
    "VD01", "VD02", "VD03", "VD04", "VD05", "VD06", _
    "VD07", "VD08", "VD09", "VD10", "VD11", "VD12", _
    "VD13", "VD14", "VD15", "VD16", "VD17", "VD18")
```
→ **18 máy: VD01-VD18**, giống hệt ở cả 2 workbook.

Trích từ `mainform.CommandButton3_Click()` (chọn thùng/tank):
- **C3** (dòng 334): `arrVD = Array("1A", "2B", "3C", "4D", "FB")` — **5 slot, có "FB"**.
- **MID** (dòng 299): `arrVD = Array("1A", "2B", "3C", "4D")` — **4 slot, KHÔNG có "FB"**.

Đây là khác biệt xác nhận đúng theo tên file C3 ("...FB").

### 5. Trạng thái ban đầu khi mới tạo

Trích nguyên văn `Insert_tbl_input_all` (giống nhau về phần trạng thái ở cả 2 bản, C3 dòng 518-531, MID dòng 481-491):
```vb
sql = "INSERT INTO tbl_input_all " & _
      "([color],[code],[confirm1],[machine],[tank],[level],[confirm2]," & _
      "[rawqrdye],[rawqrchem]," & _   ' (chỉ có ở C3 — MID không có 2 cột này)
      "[sending],[sent],[time1],[time2],[time3],[issent]) VALUES (" & _
      ...
      "0,0,Now(),Null,Null,0)"
```
Khởi tạo: `sending=0`, `sent=0`, `time1=Now()` (thời điểm tạo), `time2=Null`, `time3=Null`, `issent=0`. `confirm1` = giá trị Box3 (mặc định "OK" do `UserForm_Change`), `confirm2` = giá trị Box7 (người dùng gõ hoặc bấm `CommandButton4_Click` → "OK", mặc định rỗng nếu không thao tác).

### 6. Bảng Access được ghi — điều kiện rẽ nhánh

Trích nguyên văn (C3 dòng 297-310):
```vb
idNew = Insert_tbl_input_all( _
        Trim(Me.Box1.Text), Trim(Me.Box2.Text), Trim(Me.Box3.Text), _
        Trim(Me.Box4.Text), Trim(Me.Box5.Text), Trim(Me.Box6.Text), _
        c2, Trim(Me.raw_qr_dye.Text), Trim(Me.raw_qr_chem.Text))

If UCase$(c2) = "OK" And Trim(Me.Box5.Text) <> "" Then
    MoveToSend idNew
End If
```
MID (dòng 265-276) — **khác biệt quan trọng chưa nêu ở findings trước**: điều kiện rẽ nhánh MID **KHÔNG kiểm tra `Box5 <> ""` (tank)**, chỉ kiểm tra confirm2:
```vb
idNew = Insert_tbl_input_all( _
            Trim(Me.Box1.Text), Trim(Me.Box2.Text), Trim(Me.Box3.Text), _
            Trim(Me.Box4.Text), Trim(Me.Box5.Text), Trim(Me.Box6.Text), c2)

If UCase$(c2) = "OK" Then
    MoveToSend idNew
End If
```
→ Cả 2 bản: **MỌI dòng đều được INSERT vào `tbl_input_all` trước** (không có nhánh ghi thẳng `tbl_tosend` mà bỏ qua `tbl_input_all` — hàm `Insert_Color_Code_ToSend` tồn tại nhưng KHÔNG được gọi ở đâu, xác nhận DEAD_CODE). Sau khi Insert thành công, nếu `confirm2="OK"` (và ở C3 thêm điều kiện `tank<>""`) thì gọi `MoveToSend(idNew)` để chuyển tiếp — đây là bước UPDATE-ngầm-định (thực chất INSERT SELECT sang `tbl_tosend` rồi DELETE khỏi `tbl_input_all`, xem mục 43/70 trong findings). Vậy: "ghi `tbl_input_all` nếu confirm2≠OK" là ĐÚNG về mặt kết quả cuối (dòng nằm lại `tbl_input_all` nếu không đủ điều kiện move), nhưng về mặt kỹ thuật, bảng đích ban đầu LUÔN LUÔN là `tbl_input_all`, `tbl_tosend` chỉ nhận dữ liệu gián tiếp qua `MoveToSend`.

### 7. Khóa tranh chấp đa người dùng

Xác nhận lại (đã biết từ trước, không phân tích sâu thêm): VBA gốc **không có** cơ chế khóa tranh chấp thật giữa nhiều người dùng/máy trạm khi tạo mới — `Exists_ColorCode` chỉ chặn trùng dữ liệu logic (business key), không phải mutex; `LockAllTextboxes` (khóa UI readonly) đang bị comment/tắt. File chạy trên 1 máy đơn (`Z:\DF\DATA\record.accdb` là share network nhưng không có locking logic tường minh ở tầng ứng dụng cho thao tác ghi mới).

### 8. Lịch sử — ghi vào đâu khi tạo mới

**Không ghi log lịch sử/audit nào tại thời điểm tạo (`btnSAVE_Click`/`Insert_tbl_input_all`)** — chỉ có `time1=Now()` lưu ngay trong chính dòng dữ liệu (không phải bảng log riêng). Bảng `tbl_sentlog`/`tbl_SentLog` chỉ nhận dữ liệu khi dòng đã được **gửi máy thực sự** (qua cơ chế khác nằm ngoài phạm vi 2 file này — không thấy code nào INSERT vào `tbl_sentlog` trong cả `Insert_tbl_input_all` lẫn `MoveToSend`; `MoveToSend` chỉ chuyển `tbl_input_all→tbl_tosend`, KHÔNG chạm `tbl_sentlog`). Vậy: **tạo mới hoàn toàn không sinh audit trail**, chỉ có timestamp `time1` trong chính dòng.

### 9. Retry khi lưu Access lỗi

**Không có.** `Insert_tbl_input_all` và `MoveToSend` không có `On Error` handler nào bọc quanh câu `cn.Execute sql` — nếu Access báo lỗi (file khóa, mất kết nối mạng, vi phạm ràng buộc), lỗi sẽ nổi lên runtime error của VBA (crash form hoặc dừng thực thi, tùy chế độ Error Handling của VBE), không có cơ chế thử lại tự động.

### 10. Danh sách đầy đủ MsgBox lỗi trong luồng tạo (`btnSAVE_Click` → `Insert_tbl_input_all`/`MoveToSend`)

| MsgBox | Điều kiện | Vị trí |
|---|---|---|
| `"Khong du thong tin"` (vbExclamation) | Box1 hoặc Box2 rỗng | `btnSAVE_Click`, đầu hàm |
| `"Da ton tai mau nay"` (vbExclamation) | `Exists_ColorCode(Box1,Box2)=True` | `btnSAVE_Click`, sau bước 2 |
| `"[Machine] TANK [Tank] MINIMUM LEVEL 250L"` (vbExclamation) | **CHỈ CÓ Ở C3**: machine ∈{VD06..VD13} AND tank∈{1A,2B} AND level<250 | `btnSAVE_Click`, C3 dòng 285-289 (xem Phần B) |
| (không có MsgBox riêng cho lỗi `Insert_tbl_input_all`/`MoveToSend`) | Lỗi Access không try/catch — không có message thân thiện, sẽ là runtime error mặc định của VBA | — |

---

### Đối chiếu với `MachineDispatchController` hiện tại

**Đã đọc toàn bộ file `F:\DF\backend\app\Http\Controllers\MachineDispatchController.php` (186 dòng).** Xác nhận CHÍNH XÁC 4 action, không hơn không kém:
1. `index(Request $request)` — GET danh sách, lọc `queue_state IN ('INPUT','WAITING','TO_SEND','PROCESSING','ERROR')`, filter tùy chọn theo `machine_id` (qua quan hệ `batch`).
2. `claim(Request $request, $id)` — khóa 5 phút (300 giây hard-code dòng 45), log `AuditLog` action=`LOCK_OVERRIDE` khi cướp khóa hết hạn.
3. `release(Request $request, $id)` — mở khóa, log `AuditLog` action=`FORCE_UNLOCK` khi người khác mở khóa hộ.
4. `send(Request $request, $id)` — chuyển `queue_state='SENT'`, `is_sent=true`, `sent_at=now`, cập nhật `batch.status='SENT'`, log `AuditLog` action=`DISPATCH_TO_MACHINE`.

**KHÔNG có** `store`, `update`, `destroy`, `approve`. Route xác nhận qua `F:\DF\backend\routes\api.php` dòng 38-42: chỉ 4 route trên (`GET /machine-dispatches`, `POST .../claim`, `.../release`, `.../send`) — không có `POST /machine-dispatches` (store).

`F:\DF\backend\app\Http\Controllers\ProductionBatchController.php` (89 dòng, đã đọc toàn bộ) có action `store()` nhưng chỉ tạo **`ProductionBatch`** (bảng `app.production_batches`), KHÔNG tạo `MachineDispatch` (bảng `app.machine_dispatches`) — validate: `legacy_batch_id`, `color`, `product_code` bắt buộc, `machine_id` bắt buộc (`exists:pgsql.app.machines,id`), `tank_id`/`level_code` nullable. **Không kiểm tra trùng color+product_code** (dù DB có `UNIQUE(legacy_batch_id, product_code, machine_id)` ở tầng schema — khác khóa trùng VBA là color+code thuần túy, không gồm machine). **Không có bất kỳ validate level/250 nào** (đã grep "250"/"level"/"min" trên cả 2 controller — 0 kết quả ngoài field name `level_code`).

**Đối chiếu từng bước 1-10:**

| Bước VBA | Có method/API tương ứng chưa | Method dự kiến còn thiếu |
|---|---|---|
| 1. Nguồn nhập (form/QR) | KHÔNG. Frontend `MachineQueue.vue` không có form nhập Box1-7 lẫn ô scan QR tạo mới | Cần thêm form/route `MachineQueueCreate.vue` (hoặc modal) + parser QR JS tương đương `Box1_AfterUpdate` |
| 2. Bắt buộc color/code | KHÔNG có endpoint nào validate cặp này khi tạo dispatch. `ProductionBatchController::store` validate `color`+`product_code` NHƯNG là 2 field riêng của `ProductionBatch`, không map 1-1 với "code" của VBA (VBA code = mã hóa chất/thuốc nhuộm, không hẳn = `product_code`) — cần xác nhận mapping | `MachineDispatchController::store` (mới) + `StoreMachineDispatchRequest` (FormRequest) |
| 3. Chặn trùng `Exists_ColorCode` | KHÔNG. Không có logic nào query `machine_dispatches`/`production_batches` theo (color, code) để chặn trùng trước khi tạo | Cần thêm rule validate custom (Closure hoặc Rule class) trong FormRequest mới, hoặc method `MachineDispatchService::existsColorCode(color, code): bool` |
| 4. Danh sách máy/thùng | CÓ — chuẩn hóa tốt hơn qua bảng `app.machines`/`app.tanks` (Model `Machine.php`, `Tank.php`) thay vì mảng cứng | Không thiếu, nhưng cần xác nhận đã seed đủ VD01-VD18 và tank 1A/2B/3C/4D(+FB nếu áp dụng) — ngoài phạm vi code review này |
| 5. Trạng thái ban đầu | KHÔNG có nơi nào set `queue_state='INPUT'`/`'WAITING'` khi tạo mới qua API (chỉ tồn tại do di trú 1 lần) | Method mới cần set `queue_state` ban đầu (giá trị đề xuất tương đương `sending=0,sent=0,issent=0` cũ: `queue_state='INPUT'`, `is_sent=false`) |
| 6. Bảng ghi + rẽ nhánh move | KHÔNG có API nào thực hiện move `INPUT/WAITING → TO_SEND` khi đủ điều kiện (tương đương `MoveToSend`) | Cần `MachineDispatchController::store` gọi tiếp `moveToSend()` nội bộ nếu điều kiện tương đương đủ (xem VBA-DISPATCH-043/070 — đã MISSING từ trước) |
| 7. Khóa tranh chấp | Không áp dụng cho bước tạo (khóa chỉ áp dụng claim/release/send hiện có) — đúng, vì VBA gốc cũng không khóa lúc tạo | — |
| 8. Lịch sử/audit lúc tạo | KHÔNG có — `AuditLog` model đã tồn tại và đang dùng cho claim/release/send, nhưng chưa có entry `action='DISPATCH_CREATED'` nào | Cần thêm `AuditLog::create(['action' => 'DISPATCH_CREATED', ...])` trong `store` mới (CẢI TIẾN so với VBA — VBA gốc không audit lúc tạo, nhưng CLAUDE.md mục "Nhật ký Thay đổi" gợi ý nên audit các hành động quan trọng — cần xác nhận có bắt buộc audit bước tạo hay không, vì đây không nằm trong 4 hành động bắt buộc audit liệt kê ở CLAUDE.md mục 5) |
| 9. Retry khi lỗi lưu | Không áp dụng — PostgreSQL qua Laravel `DB::transaction` không cần retry thủ công kiểu VBA (đã REPLACED_EQUIVALENTLY ở tầng hạ tầng) | Method mới nên bọc trong `DB::transaction()` giống `send()` hiện có, không cần retry riêng |
| 10. Lỗi/validate | KHÔNG có FormRequest nào định nghĩa các rule tương đương 3 MsgBox lỗi trên | Cần `StoreMachineDispatchRequest` với rule `required` cho color/code, custom rule chặn trùng, và (nếu được xác nhận) custom rule min-level 250L |

---

## PHẦN B — Quy tắc dung tích tối thiểu 250L

### 1. Nguyên văn điều kiện `If` — 2 workbook

**C3** — `mainform.btnSAVE_Click()`, dòng **277-293** (`C3_grid_load_row_lock_id_FB___xlsm_.txt`):
```vb
If UCase$(Trim(Me.Box7.Text)) = "OK" Then
    Select Case UCase$(Trim(Me.Box4.Text))
        Case "VD06", "VD07", "VD08", "VD09", _
             "VD10", "VD11", "VD12", "VD13"

            If UCase$(Trim(Me.Box5.Text)) = "1A" _
            Or UCase$(Trim(Me.Box5.Text)) = "2B" Then

                If level < 250 Then
                    MsgBox Me.Box4.Text & " TANK " & Me.Box5.Text & _
                           " MINIMUM LEVEL 250L", vbExclamation
                    Exit Sub
                End If

            End If
    End Select
End If
```
(biến `level` được gán ngay trên, dòng 275: `level = Val(Me.Box6.Text)`)

**MID** (`MACHINE_ID_LOCKED_xlsm_.txt`): **Đã xác nhận bằng grep trực tiếp chuỗi `"250"` trên toàn bộ file (5337 dòng) — 0 kết quả.** `btnSAVE_Click()` của MID (dòng 247-291) đi thẳng từ bước `Exists_ColorCode` sang `Insert_tbl_input_all` mà không có bất kỳ đoạn `Select Case`/kiểm tra `level` nào chen giữa. Xác nhận: **MID hoàn toàn không có quy tắc 250L dưới bất kỳ hình thức nào** (không phải chỉ khác ngưỡng — là hoàn toàn vắng mặt logic này).

### 2. Máy/thùng áp dụng — chính xác theo code

- **Máy:** chỉ 8 trong 18 máy — `VD06, VD07, VD08, VD09, VD10, VD11, VD12, VD13`.
- **Thùng:** chỉ 2 trong 4-5 slot — `1A`, `2B` (KHÔNG áp dụng cho `3C`, `4D`, `FB`).
- Điều kiện được lồng: chỉ kiểm tra khi **confirm2 (Box7) = "OK"** (tức chỉ khi dòng sắp được `MoveToSend` ngay lập tức trong cùng lượt lưu này). Nếu confirm2 ≠ "OK" (dòng nằm lại `tbl_input_all` chờ duyệt sau), quy tắc 250L **KHÔNG được kiểm tra tại thời điểm tạo** — theo code, việc duyệt sau (`subform.btnApprove_Click` → `Approve_Update_Move`) không gọi lại bất kỳ đoạn kiểm tra level nào (đã xác nhận trong `group2_dispatch_findings.md` mục VBA-DISPATCH-043 — `Approve_Update_Move` chỉ UPDATE machine/tank/confirm2 rồi move, không có Select Case level).

### 3. Trường hợp ngoại lệ

- **Không có ngoại lệ theo vai trò người dùng** — 2 workbook này không có khái niệm role/permission trong code (không có bảng user/role nào được tham chiếu ở 2 file được giao).
- **Có ngoại lệ theo luồng nghiệp vụ (đã nêu ở mục 2):** quy tắc chỉ áp dụng khi **tạo mới VÀ confirm2="OK" ngay lúc lưu** (nhánh move-ngay). Nếu tạo dòng với confirm2 rỗng/khác "OK" rồi duyệt sau qua `subform.btnApprove_Click`/`Approve_Update_Move`, quy tắc 250L **không được áp dụng lại** ở bước duyệt — đây là một lỗ hổng nghiệp vụ tiềm ẩn trong chính VBA gốc (không phải do web tạo ra): người dùng có thể né quy tắc bằng cách để confirm2 trống lúc lưu rồi duyệt sau.
- Không tìm thấy đoạn code nào khác (ngoài `btnSAVE_Click` của C3) tham chiếu tới hằng số `250` trong toàn bộ 2 file.

### 4. Hệ thống web hiện dùng quy tắc nào

Đã grep trực tiếp `"250"`, `"level"`, `"min"` (case-insensitive) trên `F:\DF\backend\app\Http\Controllers\MachineDispatchController.php` và `ProductionBatchController.php` — **0 kết quả cho "250" và "min"**. Từ khóa "level" chỉ xuất hiện dưới dạng tên field `level_code` (string, nullable) trong validate của `ProductionBatchController::store` — **không có bất kỳ so sánh số học nào** (`< 250`, `>=`, v.v.) ở bất kỳ đâu trong 2 controller. Xác nhận: **hệ thống web hiện tại (2026-07-17) hoàn toàn không áp dụng quy tắc dung tích tối thiểu 250L dưới bất kỳ hình thức nào** — không phải theo C3, không phải theo MID, mà là chưa triển khai gì cả (vì `store` cho `MachineDispatch` chưa tồn tại).

Đáng chú ý thêm: cột `level_code` trong `app.production_batches` là kiểu **text**, không phải kiểu numeric — nếu sau này cần enforce `level < 250`, cần xác nhận thêm liệu `level_code` có được dùng để lưu giá trị dung tích số (như Box6 VBA — `Val(Me.Box6.Text)`) hay nó mang ý nghĩa khác (ví dụ mã cấp độ). Đây là điểm cần xác nhận nghiệp vụ bổ sung, không suy đoán.

### 5. Câu hỏi cần người dùng xác nhận (trình bày sự thật, không đề xuất chọn phiên bản)

Đã có sẵn trong `F:\DF\.claude\open-questions.md` mục **CH-BUS-005** (dòng 13-15), nội dung khớp với phát hiện ở đây. Bổ sung thêm các câu hỏi con cụ thể hơn cho nhóm thiết kế API `store`:

1. Quy tắc 250L (VD06-VD13, tank 1A/2B, level<250 → chặn) có còn hiệu lực trong sản xuất hiện tại không? Nếu có, đây có phải TOÀN BỘ quy tắc, hay còn phiên bản mới hơn (chưa được cung cấp workbook) áp dụng ngưỡng khác/máy khác?
2. Nếu quy tắc còn hiệu lực: có nên áp dụng cho **mọi lần lưu** (kể cả khi duyệt sau qua bước approve, khắc phục lỗ hổng "né kiểm tra" đã nêu ở mục 3), hay giữ nguyên hành vi cũ (chỉ chặn lúc tạo mới với confirm2=OK ngay)?
3. Trường `level_code` hiện tại trong `app.production_batches` là kiểu text — cần xác nhận đây có phải cột lưu giá trị số dung tích (lít) tương đương Box6 VBA hay không, hay cần một cột số riêng (ví dụ `level_liters numeric`) để so sánh `< 250`.
4. Danh sách 8 máy (VD06-VD13) và 2 tank (1A, 2B) có còn đúng với cấu hình máy/thùng thực tế hiện tại không (máy móc có thể đã thay đổi từ lúc code VBA được viết tới nay)?
5. Có nên đưa quy tắc này thành **cấu hình** (bảng `machine_min_level_rules` hoặc cột trên `app.machines`/`app.tanks`) thay vì hard-code trong controller, để vận hành có thể tự điều chỉnh mà không cần sửa code mỗi khi quy tắc thay đổi?

**Không tự chọn C3 hay MID làm chuẩn** — đây là quyết định nghiệp vụ, cần người phụ trách sản xuất xác nhận trước khi code `store`.

---

## PHẦN C — Kế hoạch FIX-003 (CHỈ LẬP KẾ HOẠCH, KHÔNG THỰC HIỆN)

### FIX-003: Bổ sung API tạo hàng chờ điều phối mới (`store`)

**Phạm vi:** Bổ sung khả năng tạo mới bản ghi `app.machine_dispatches` (kèm `app.production_batches` liên kết nếu chưa có) từ ứng dụng Web, thay thế hoàn toàn phần chức năng đã MISSING của `btnSAVE_Click`/`Insert_tbl_input_all`/`Exists_ColorCode`/`MoveToSend` trong VBA gốc. **Không bao gồm** UI duyệt thủ công (`btnApprove_Click`/`Approve_Update_Move` — đây là FIX riêng biệt, xem danh sách MISSING #2/#3 trong `group2_dispatch_findings.md`) — tách riêng để giữ FIX-003 gọn, tập trung đúng vào "tạo mới".

### File dự kiến sửa

- `F:\DF\backend\app\Http\Controllers\MachineDispatchController.php` — thêm method `store(StoreMachineDispatchRequest $request)`.
- `F:\DF\backend\routes\api.php` — thêm route `Route::post('/machine-dispatches', [MachineDispatchController::class, 'store']);` (dòng chèn cạnh dòng 38-42 hiện có).
- **File FormRequest mới:** `F:\DF\backend\app\Http\Requests\StoreMachineDispatchRequest.php` — validate color/code bắt buộc, machine_id/tank_id tồn tại, level numeric.
- **Service mới (khuyến nghị tách logic khỏi controller):** `F:\DF\backend\app\Services\MachineDispatchService.php` — chứa `existsColorCode()`, `createDispatch()`, `moveToSend()` (nếu bước 2 của FIX-003 được làm — xem Dependency).
- `F:\DF\backend\app\Models\MachineDispatch.php` — có thể cần bổ sung field `level` vào `$fillable` nếu quyết định lưu numeric level trực tiếp trên `machine_dispatches` thay vì `production_batches.level_code`.
- `F:\DF\backend\app\Models\ProductionBatch.php` — kiểm tra lại field `level_code`, có thể cần đổi kiểu hoặc thêm cột numeric (xem Database change).

### Database change

- **Bước 1 (không cần rule 250L):** Không cần thay đổi schema nếu tái sử dụng `production_batches.level_code` (text) để lưu tạm giá trị level thô như VBA (`Val(Box6.Text)` dạng string) — có thể ghi numeric-as-text mà chưa enforce.
- **Bước 2 (sau khi CH-BUS-005 được xác nhận, NẾU quy tắc 250L còn hiệu lực):**
  - Thêm cột numeric thực sự để so sánh: ví dụ `app.production_batches.level_liters numeric(10,2) NULL` (hoặc đổi kiểu `level_code`→numeric nếu xác nhận nó luôn là số).
  - Nếu nghiệp vụ xác nhận quy tắc cần **cấu hình được** (câu hỏi #5 Phần B): thêm bảng cấu hình dạng `app.machine_min_level_rules (id, machine_id FK, tank_id FK, min_level_liters numeric, is_active boolean, created_at)` thay vì hard-code danh sách VD06-VD13/1A,2B trong PHP — tránh lặp lại kiểu hard-code cứng như VBA gốc.

### Migration (tên file dự kiến, đúng convention `YYYY_MM_DD_HHMMSS_mo_ta.php` như các file hiện có trong `database/migrations/`)

- Bước 1: không cần migration mới (dùng schema hiện có).
- Bước 2 (có điều kiện): `2026_07_18_000001_add_level_liters_to_production_batches_table.php` (nếu chỉ thêm cột numeric); hoặc `2026_07_18_000002_create_machine_min_level_rules_table.php` (nếu làm bảng cấu hình).

### Acceptance criteria (Given/When/Then)

**Bước 1 — `store` cơ bản:**
- Given người dùng đã đăng nhập với quyền OPERATOR trở lên, When gửi `POST /api/machine-dispatches` với `color`, `code` hợp lệ (không rỗng) và (tùy chọn) `machine_id`, `tank_id`, `level`, Then hệ thống tạo `production_batches` (nếu chưa tồn tại theo `legacy_batch_id`/color/code tương ứng) + `machine_dispatches` với `queue_state='INPUT'`, trả về 201.
- Given `color` hoặc `code` rỗng, When gửi `POST /api/machine-dispatches`, Then trả về 422 với message tương đương `"Khong du thong tin"`.
- Given đã tồn tại 1 dispatch active (`queue_state` chưa `SENT`) với cùng cặp (color, code), When gửi `POST /api/machine-dispatches` với cùng cặp đó, Then trả về 409/422 với message tương đương `"Da ton tai mau nay"` (KHÔNG hard-delete/ghi đè — giữ nguyên bản ghi cũ).
- Given `confirm_2="OK"` VÀ có `tank_id` hợp lệ được gửi kèm, When tạo dispatch, Then hệ thống tự động chuyển `queue_state` sang `TO_SEND` ngay (tương đương `MoveToSend`) trong cùng 1 transaction — không cần bước duyệt riêng.

**Bước 2 — rule 250L (CHỈ implement sau khi CH-BUS-005 được xác nhận CÓ hiệu lực):**
- Given `machine_id` thuộc nhóm được cấu hình áp dụng rule (mặc định đề xuất VD06-VD13 nếu xác nhận giữ nguyên) VÀ `tank_id` thuộc nhóm áp dụng (1A, 2B) VÀ `confirm_2="OK"`, When `level` gửi lên < ngưỡng cấu hình (mặc định 250), Then trả về 422 với message tương đương `"{machine} TANK {tank} MINIMUM LEVEL {ngưỡng}L"`.
- Given cùng điều kiện trên nhưng `confirm_2` KHÔNG phải "OK" (dòng nằm ở `INPUT`/`WAITING`, chưa move), Then **cần xác nhận nghiệp vụ (câu hỏi #2 Phần B)** rule có áp dụng luôn tại bước tạo hay chỉ áp dụng khi thực sự move sang `TO_SEND` (kể cả nếu move xảy ra sau, ở 1 API duyệt khác, thuộc phạm vi FIX khác) — ghi rõ trong test case khi có câu trả lời, không tự giả định.

### Regression test

- Mở rộng `F:\DF\backend\tests\Feature\MachineDispatchConcurrencyTest.php` **hoặc** tạo file mới `F:\DF\backend\tests\Feature\MachineDispatchStoreTest.php` (khuyến nghị file mới — tách rõ test "tạo mới" khỏi test "khóa tranh chấp" đã có, giữ file cũ gọn theo đúng tên `...ConcurrencyTest`).
- Case tối thiểu: tạo thành công (201) + đúng `queue_state` ban đầu; chặn color/code rỗng (422); chặn trùng color+code (409/422); auto-move khi confirm_2=OK+tank có giá trị; (bước 2, có điều kiện) chặn min-level 250L đúng máy/tank, KHÔNG chặn ngoài danh sách máy/tank áp dụng, KHÔNG chặn khi confirm_2≠OK (trừ khi nghiệp vụ xác nhận ngược lại).

### Rollback

- Bước 1: xóa route `store`, xóa method controller, xóa FormRequest — không có thay đổi schema nên rollback code thuần túy, an toàn.
- Bước 2 (nếu đã thêm cột/bảng cấu hình): migration có `down()` chuẩn (`Schema::table(...)->dropColumn('level_liters')` hoặc `Schema::dropIfExists('app.machine_min_level_rules')`) — KHÔNG xóa dữ liệu `machine_dispatches`/`production_batches` đã tạo qua API (chỉ rollback cấu trúc, giữ nguyên dữ liệu giao dịch theo đúng CLAUDE.md mục 3).

### Dependency

- **PHẢI** xác nhận `F:\DF\.claude\open-questions.md` mục **CH-BUS-005** trước khi code phần validate min-level (Bước 2). Bước 1 (`store` cơ bản, không có rule 250L) **KHÔNG phụ thuộc** CH-BUS-005 và có thể triển khai độc lập, sớm hơn — đề xuất tách FIX-003 thành:
  - **FIX-003a:** `store` cơ bản (bắt buộc color/code, chặn trùng, trạng thái ban đầu, auto-move nếu đủ điều kiện confirm2+tank) — có thể làm ngay.
  - **FIX-003b:** bổ sung rule min-level (250L hoặc ngưỡng khác theo xác nhận nghiệp vụ, cấu hình được hoặc hard-code theo quyết định) — CHỈ làm sau khi có câu trả lời CH-BUS-005.
- Phụ thuộc phụ: cần xác nhận mapping "code" VBA (mã hóa chất/thuốc nhuộm) với field nào trong `production_batches`/`machine_dispatches` hiện tại — hiện `ProductionBatch` có `color` + `product_code`, chưa rõ "code" trong `Exists_ColorCode` map với field nào (có thể là `product_code`, cần xác nhận cùng nhóm nghiệp vụ nhập liệu, không thuộc phạm vi sâu của nhóm Dispatch).

### Rủi ro

- Nếu triển khai FIX-003a mà không có FIX-003b, hệ thống web sẽ ở trạng thái "kém an toàn hơn cả bản MID" (MID ít nhất còn chạy được, dù thiếu rule) — nhưng đây là rủi ro CHẤP NHẬN ĐƯỢC trong giai đoạn pilot nếu được thông báo rõ cho vận hành viên (dùng Excel VBA song song trong Phase 12 sẽ bù đắp tạm thời).
- Việc thêm bảng cấu hình `machine_min_level_rules` (nếu chọn) làm tăng độ phức tạp thiết kế — cần cân nhắc so với hard-code đơn giản nếu quy tắc thực tế hiếm khi thay đổi (quyết định nghiệp vụ, không phải kỹ thuật thuần túy).
- Chặn trùng theo (color, code) tại tầng `machine_dispatches` có thể xung đột với `UNIQUE(legacy_batch_id, product_code, machine_id)` đã có sẵn trên `production_batches` — cần rà soát kỹ để tránh 2 tầng validate mâu thuẫn nhau (ví dụ: VBA chặn trùng KHÔNG theo machine, còn constraint DB hiện tại lại CÓ machine trong khóa unique — 2 ngữ nghĩa khác nhau, cần thống nhất trước khi code).

### Estimate

- **FIX-003a (store cơ bản):** **M** — cần controller method mới, FormRequest, logic chặn trùng, logic auto-move (tái sử dụng ý tưởng `MoveToSend`), test mới; độ phức tạp vừa vì phải xử lý đúng transaction (tạo batch + dispatch + auto-move trong 1 transaction) và làm rõ mapping field color/code còn mơ hồ.
- **FIX-003b (rule 250L):** **S** nếu hard-code theo đúng ngưỡng/danh sách máy VBA gốc (đã xác nhận); **M** nếu làm bảng cấu hình `machine_min_level_rules` — phụ thuộc hoàn toàn vào câu trả lời CH-BUS-005, chưa thể ước lượng chính xác trước khi có xác nhận nghiệp vụ.
