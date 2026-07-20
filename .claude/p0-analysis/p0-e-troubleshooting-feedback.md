# P0-E: Cơ chế Feedback Loop / Tự cập nhật Knowledge Base của hệ Troubleshooting — Phân tích + Kế hoạch FIX-005

Ngày phân tích: 2026-07-17. Phạm vi: chỉ phân tích + lập kế hoạch, KHÔNG sửa code.

Nguồn đối chiếu:
- `group5_troubleshooting_findings.md` (audit 53 procedure, đã đọc lại toàn bộ 174 dòng)
- VBA gốc: `vba_extracted\troubleshooting_support_engine_DF_xlsm_.txt` (đọc trực tiếp thân hàm `btn_insert_Click` dòng 676-772, `btn_load_Click` dòng 773-851, `btn_renew_Click` dòng 853-927, `btn_Report_Click` dòng 379-455, `btn_Submit_Click` dòng 458-560, `btn_clear_editor_Click` dòng 624-640, `btn_Select_CAUSE_Click` dòng 656-660)
- Web: `TroubleshootingController.php`, `InferenceService.php`, các Model, migration, routes/api.php, `Troubleshooting.vue`, `TroubleshootingInferenceTest.php`, `AuditLog.php`, `open-questions.md`

---

## 1. Đối chiếu chi tiết

### 1.1. VBA Editor (btn_insert / btn_load / btn_renew / btn_clear_editor / btn_Select_CAUSE)

**`btn_insert_Click`** (dòng 676-772) — thêm/sửa 1 rule Problem-Cause:
```vba
For i = 1 To 25
    If Me.Controls("txt_CauseID" & i).Value <> "" Then
        If Val(Me.Controls("txt_CauseScore" & i).Value) <= 0 Then
            MsgBox "Please assign a score greater than 0 for:" & ...
            Exit Sub
        End If
        ' Check duplicate Problem+Cause -> hỏi Yes/No để UPDATE điểm
        ' Nếu không trùng -> ghi dòng mới:
        LastRow = ws.Cells(ws.Rows.Count, 1).End(xlUp).Row + 1
        NextID = LastRow - 1
        ws.Cells(LastRow, 1).Value = "PC" & Format(NextID, "00000")   ' PC_ID sinh tuần tự từ SỐ DÒNG, không phải counter riêng
        ws.Cells(LastRow, 2).Value = txt_Problemid.Value
        ws.Cells(LastRow, 3).Value = Me.Controls("txt_CauseID" & i).Value
        ws.Cells(LastRow, 4).Value = Val(Me.Controls("txt_CauseScore" & i).Value)
    End If
Next i
```
Xác nhận: validate Score>0 đúng như findings mô tả. PC_ID sinh từ `LastRow - 1` (vị trí dòng), KHÔNG phải sequence riêng biệt — nếu dòng nào đó bị xoá không đúng cách, ID có thể trùng lặp về lý thuyết (rủi ro kỹ thuật của VBA gốc, không phải vấn đề cần migrate lại nguyên trạng).

**`btn_load_Click`** (dòng 773-851) — nạp lại toàn bộ Cause đã gán cho 1 Problem (tối đa 25 dòng) từ `KB_ProblemCause` + tra tên qua Dictionary từ `KB_Cause`, đổ vào 25 textbox để sửa. Đúng như mô tả trong findings.

**`btn_renew_Click`** (dòng 853-927) — validate Score>0 cho tất cả 25 dòng trước, sau đó **xoá cứng toàn bộ mapping cũ của Problem này** (`ws.Rows(r).Delete` lặp từ LastRow xuống 2), rồi **ghi lại từ đầu** 25 dòng (PC_ID lại sinh theo vị trí dòng mới). Xác nhận đúng "hard delete + insert toàn bộ" như findings mô tả.

**`btn_clear_editor_Click`** (dòng 624-640): chỉ xoá control trên form (`txt_Problemid`, `txt_Problem`, 25 cặp `txt_CauseID{n}`/`txt_Cause{n}`/`txt_CauseScore{n}`/`spn_CauseScore{n}`) — KHÔNG đụng tới sheet dữ liệu. Đây chỉ là reset UI, không phải chức năng nghiệp vụ.

**`btn_Select_CAUSE_Click`** (dòng 656-660): chỉ 1 dòng `frmcauses.Show` — mở form phụ để kỹ sư chọn thủ công 1 Cause từ danh sách lọc theo 6M (Man/Machine/Material/Method/Measurement/Environment) và copy vào textbox editor. Đây KHÔNG phải là 1 phần của thuật toán suy luận — nó là công cụ hỗ trợ NHẬP LIỆU cho Editor (giúp kỹ sư không phải gõ tay CauseID).

**Không có audit trail nào trong VBA cho việc sửa rule qua Editor** — `btn_insert`/`btn_renew` ghi thẳng vào sheet `KB_ProblemCause`, không ghi ai sửa, khi nào, giá trị cũ là gì. Duy nhất có hộp thoại `MsgBox` xác nhận "Update Score?" khi trùng — đây là UI confirmation tức thời, không phải audit log lưu trữ.

**Web hiện có:** Không có gì. `TroubleshootingController.php` chỉ có đúng 7 phương thức public — đã liệt kê đầy đủ ở mục 7 bên dưới — không có bất kỳ route CRUD nào cho `ProblemCauseRule`. Model `ProblemCauseRule.php` tồn tại (`fillable: problem_id, cause_id, cause_score`) nhưng chỉ được `InferenceService` đọc (`whereIn('problem_id', $problemIds)->get()`), không có nơi nào ghi/sửa/xoá qua HTTP. Cách duy nhất để đổi rule hiện tại là sửa `database/data/kb_*.json` rồi chạy lại `TroubleshootingKnowledgeBaseSeeder` (yêu cầu deploy lại code).

**Kết luận mục 1:** MẤT HOÀN TOÀN. Toàn bộ luồng Editor (5 nút: insert/load/renew/clear_editor/Select_CAUSE) không có tương đương ở web.

### 1.2. Submit (`btn_Submit_Click`, dòng 458-560)

Xác nhận đúng: sinh `CaseID = "CASE" & Format(Now, "yyyymmddhhmmss")`, ghi **N dòng vào sheet `Submit`** (N = số dòng trong `ENGINE`, tức TOÀN BỘ cause được chấm điểm, không giới hạn top-N) — mỗi dòng chứa CaseID, ProblemID nối `|`, CauseID, 8 giá trị actual/setpoint tham số, 4 combobox trạng thái, Stage, và 4 cột điểm lấy trực tiếp từ ENGINE cột 3-6 (ProblemScore/ProcessScore/ParameterScore/TotalScore — 3 cột đầu luôn = 0 do bug `SaveEngine`, xem mục 5).

Web tương đương: `TroubleshootingController::diagnose()` — sinh UUID thay vì timestamp, chỉ lưu **top 10** (`array_slice($recommendations, 0, 10)`, dòng 128 controller) vào `case_recommendations`.

### 1.3. Feedback (`btn_Report_Click`, dòng 379-455 + checkbox check1..24)

Trích nguyên văn đoạn quyết định (dòng 411-438):
```vba
For r = 2 To LastRow
    If wsS.Cells(r, 1).Value = CaseID Then
        NewRow = wsD.Cells(wsD.Rows.Count, 1).End(xlUp).Row + 1
        'Copy 16 cột A:P (thực ra là A:X = 24 cột theo range code)
        wsD.Range(wsD.Cells(NewRow, 1), wsD.Cells(NewRow, 24)).Value = _
            wsS.Range(wsS.Cells(r, 1), wsS.Cells(r, 24)).Value

        'Verify (Cột 17 = Q)  <- comment ghi nhầm, thực tế ghi cột 25
        If idx <= 24 Then
            If Me.Controls("check" & idx).Value Then
                wsD.Cells(NewRow, 25).Value = 5
            Else
                wsD.Cells(NewRow, 25).Value = 0
            End If
        End If
        idx = idx + 1
    End If
Next r
' ... sau đó XOÁ toàn bộ dòng CaseID khỏi Submit (hard delete)
```

**Điểm mấu chốt đã xác nhận bằng grep toàn bộ 2 workbook (không chỉ 1 file):**
```
grep "Submited" trên cả 2 file .txt trích xuất (troubleshooting_support_engine_DF_xlsm_.txt
và troubleshooting_support_engine_________________xlsm_.txt) => CHỈ 2 kết quả/file:
  dòng 393: Set wsD = Sheets("Submited")
  dòng 409: 'Copy Submit -> Submited (comment)
```
Không có bất kỳ dòng code nào khác trong toàn bộ 2 workbook đọc lại sheet `Submited` hay cột 25/Y của nó. Grep pattern `check\d+` trên toàn bộ `vba_extracted` cũng chỉ khớp trong `btn_Report_Click` (gán giá trị, dòng 426) — không có nơi nào đọc lại giá trị `check1..24` để tính toán hay "học" gì thêm.

**=> KẾT LUẬN QUAN TRỌNG NHẤT: VBA gốc KHÔNG có "feedback loop tự động" thật sự.** `check1..24` chỉ là 24 checkbox tick tay khi kỹ sư bấm "Report", ghi tĩnh giá trị 5/0 vào cột Y của sheet `Submited` để LƯU TRỮ/ĐÁNH DẤU — không có bất kỳ procedure nào (trong `modInferenceEngine.bas` hay bất kỳ module nào khác) đọc lại cột này để điều chỉnh trọng số `KB_ProblemCause`, tính lại score, hay huấn luyện gì. Đây là **dữ liệu chết** (dead data) về mặt thuật toán — có tiềm năng dùng cho phân tích thủ công ngoài Excel (ví dụ export ra rồi tính tay), nhưng bản thân workbook VBA không hề tự động hoá bước "học" này.

Web hiện có: `resolveCase()` — 1 `actual_cause_id` + `effectiveness_rating` (1-5) ở CẤP CASE (không phải cấp từng recommendation như 24 checkbox của VBA). Cũng KHÔNG đọc lại các giá trị này để điều chỉnh `problem_cause_rules.cause_score` — tức web CŨNG không có "học tự động", đúng bằng đúng đặc điểm của VBA gốc (dù cấu trúc dữ liệu ghi nhận khác nhau — case-level so với per-cause-level).

### 1.4. Cập nhật Knowledge Base qua UI — Audit trail

Xác nhận: VBA hoàn toàn KHÔNG có audit trail khi sửa `KB_ProblemCause` qua `btn_insert`/`btn_renew` — không log user, không log timestamp, không log giá trị trước/sau. Việc "ai sửa, khi nào" chỉ có thể tra cứu gián tiếp qua Excel's built-in change history nếu bật (không thấy code bật tính năng này).

### 1.5. Scoring — xác nhận nhanh (không phân tích lại sâu)

`InferenceService::calculate()` dòng 27-39 (Problem Score cộng dồn), dòng 63-73 (Process Score +2/công đoạn cumulative), dòng 185-204 (`getParameterScore` — khớp `Select Case` gốc từng nhánh HIGH/LOW/HIGH-LOW/rỗng). Đã đọc trực tiếp — công thức khớp chính xác với `modInferenceEngine.bas` gốc (`LoadProblemScore`, `ApplyProcessScore`, `GetParameterScore`). Xác nhận lại: FULLY_MIGRATED, không có sai lệch công thức.

### 1.6. History — xem lại case

VBA: `cbo_CaseID_Change` → `LoadCase` (Mod_load_submit.bas) nạp lại dữ liệu case cũ (denormalized, đọc thẳng dòng phẳng trong `Submit`).

Web: `TroubleshootingController::showCase()`:
```php
public function showCase($id)
{
    $case = TroubleshootingCase::with(['batch', 'stage', 'reporter', 'actualCause', 'problems', 'evidences.parameter', 'recommendations.cause'])
        ->findOrFail($id);
    return response()->json(['status' => 'SUCCESS', 'data' => $case]);
}
```
`recommendations` load quan hệ tới `CaseRecommendation` — model này (`app/Models/CaseRecommendation.php`) chỉ có `fillable: id, case_id, cause_id, score, rank, recommendation_text`. Migration `2026_07_16_000002_create_troubleshooting_tables.php` (dòng 130-141) xác nhận bảng `app.case_recommendations` chỉ có cột `score` (tổng), `rank`, `recommendation_text` — **KHÔNG có cột `problem_score`/`process_score`/`parameter_score`/`details`**. Breakdown 4 phần này (`$breakdown[$cid]` trong `InferenceService::calculate()`, dòng 32-37) chỉ tồn tại trong response JSON tức thời lúc gọi `diagnose()` — không hề được lưu xuống DB. Đối chiếu với `Troubleshooting.vue`: breakdown chỉ hiển thị ở panel "Kết Quả Chẩn Đoán" ngay sau khi bấm suy luận (dòng 137-148, dùng `res.breakdown.problem_score`...); ở modal "Chi Tiết Ca Sự Cố" (History tab, dòng 218-264) — hoàn toàn KHÔNG có phần hiển thị breakdown, chỉ có `evidences`, `problems`, `resolution_notes`, `effectiveness_rating`. => Xác nhận bằng code thật: breakdown MẤT khi xem lại case qua History.

---

## 2. Danh sách route thực tế của `TroubleshootingController` + `routes/api.php` (đọc code, không suy đoán)

`routes/api.php` dòng 68-75:
```php
Route::get('/troubleshooting/problems',            [TroubleshootingController::class, 'indexProblems']);
Route::get('/troubleshooting/processes',            [TroubleshootingController::class, 'indexProcesses']);
Route::get('/troubleshooting/parameters',           [TroubleshootingController::class, 'indexParameters']);
Route::get('/troubleshooting/cases',                [TroubleshootingController::class, 'indexCases']);
Route::get('/troubleshooting/cases/{id}',            [TroubleshootingController::class, 'showCase']);
Route::post('/troubleshooting/diagnose',            [TroubleshootingController::class, 'diagnose']);
Route::post('/troubleshooting/cases/{id}/resolve',  [TroubleshootingController::class, 'resolveCase']);
```
(có thêm `GET /reports/troubleshooting-pareto` ở `ReportController`, khác controller, xem VBA-TROUBLE-019.)

`TroubleshootingController.php` có đúng **7 public method**, không hơn không kém — đã liệt kê ở trên khớp 1-1 với 7 route. Map ngược:

| Route | Chức năng VBA tương ứng | Ghi chú |
|---|---|---|
| `GET /troubleshooting/problems` | VBA-TROUBLE-005/028 (`frmProblem_x`/`frmProblem` UserForm_Initialize) | Đã migrate đúng, lọc `is_active` |
| `GET /troubleshooting/processes` | VBA-TROUBLE-008 (nạp `cboStage` từ KB_Process) | Đã migrate |
| `GET /troubleshooting/parameters` | VBA-TROUBLE-008 (GetParameterSpec, prefill setpoint) | Có API nhưng FE không gọi (hardcode) — lỗi khác, ngoài phạm vi P0-E |
| `GET /troubleshooting/cases` | VBA-TROUBLE-050 (`LoadCaseID`) | Đã migrate, trả full object thay vì chỉ ID |
| `GET /troubleshooting/cases/{id}` | VBA-TROUBLE-009/051 (`cbo_CaseID_Change`/`LoadCase`) | Đã migrate NHƯNG thiếu breakdown (mục 1.6) |
| `POST /troubleshooting/diagnose` | VBA-TROUBLE-019/032/013 (`btn_Analysis_Click` + `btn_Submit_Click` gộp làm 1) | Đã migrate, có cải tiến (sửa bug SaveEngine) nhưng chỉ lưu top 10 |
| `POST /troubleshooting/cases/{id}/resolve` | VBA-TROUBLE-011/012 (`btn_Report_Click`) | Đã migrate Ở MỨC CASE, không có mức per-cause như 24 checkbox |

**Không có route nào tương ứng với mục 1 (Editor: btn_insert/btn_load/btn_renew/btn_clear_editor/btn_Select_CAUSE)** — xác nhận đây là chức năng viết mới cần làm nếu quyết định khôi phục, không phải route bị lỗi/thiếu sót nhỏ.

---

## 3. Kết luận rõ ràng

### Đã migrate
- Toàn bộ công thức scoring (Problem/Process/Parameter Score + rank) — đúng 100%, kể cả sửa đúng 1 bug gốc (`SaveEngine` luôn ghi 0 cho breakdown — VBA-TROUBLE-044).
- Luồng chẩn đoán 1 lần (Analysis + Submit gộp thành `diagnose()`).
- Luồng đóng case ở cấp CASE (`resolveCase`, tương đương tinh thần `btn_Report_Click` nhưng khác cấu trúc dữ liệu — soft-status thay vì hard-delete, đây là cải tiến đúng theo CLAUDE.md mục 3).
- Danh sách Problem/Process/Parameter (đọc).
- Xem lại lịch sử case (nhưng thiếu breakdown, xem dưới).

### Đã mất
1. **Toàn bộ UI Editor quản trị Knowledge Base** (`btn_insert`, `btn_load`, `btn_renew`, `btn_clear_editor`, `btn_Select_CAUSE`) — không có route/controller nào. Đây là CRUD thủ công của kỹ sư vận hành, KHÔNG phải "học tự động" (đã xác nhận bằng grep — VBA không tự học).
2. **Breakdown điểm (problem_score/process_score/parameter_score) không được lưu xuống DB** — bị mất khi xem lại case qua `showCase`/History tab dù `InferenceService` tính đúng lúc `diagnose()`.
3. **Không có ghi nhận feedback per-cause** (24 checkbox `check1..24` của VBA đánh dấu từng cause riêng biệt) — web chỉ có 1 `actual_cause_id` + `effectiveness_rating` per case. Lưu ý: đây KHÔNG phải "học tự động" bị mất, vì VBA gốc cũng chưa từng dùng dữ liệu này để tự học — chỉ là granularity ghi nhận (per-cause vs per-case) bị giảm.
4. Audit trail khi sửa rule KB — nhưng vì VBA gốc CŨNG không có, đây không phải "quay lại như cũ" mà là CƠ HỘI làm tốt hơn VBA (xem khuyến nghị FIX-005 bên dưới, đồng thời bắt buộc theo CLAUDE.md mục 5).

### Dữ liệu cần lưu nếu quyết định khôi phục
- **CRUD `problem_cause_rules`**: không cần bảng mới cho dữ liệu chính — `app.problem_cause_rules` đã đủ field (`problem_id, cause_id, cause_score`).
- **Audit trail khi sửa rule**: CLAUDE.md mục 5 đã liệt kê rõ "Thay đổi kho tri thức sự cố (Troubleshooting Knowledge Base)" là 1 trong 4 loại hành động BẮT BUỘC ghi Audit Log bất biến (JSONB before/after). Đã kiểm tra `AuditLog.php`:
  ```php
  protected $fillable = ['user_id','action','entity_type','entity_id','before_data','after_data','client_ip'];
  protected $casts = ['before_data' => 'array','after_data' => 'array','created_at' => 'datetime'];
  ```
  Model này ĐỦ TỔNG QUÁT để dùng lại — `entity_type='problem_cause_rule'`, `entity_id=<id bảng>`, `before_data`/`after_data` là JSONB snapshot của `ProblemCauseRule` trước/sau khi sửa. **Khuyến nghị: dùng chung `app.audit_logs`, KHÔNG cần tạo bảng `problem_cause_rule_history` riêng** — tránh trùng lặp cơ chế đã có.
- **Breakdown per-case** (nếu muốn khôi phục hiển thị lại ở History): cần thêm 3 cột `problem_score`, `process_score`, `parameter_score` (double, nullable) + `details` (jsonb, nullable) vào `app.case_recommendations` — đây là vấn đề khác của P0-E (mục 1.6), có thể gộp vào cùng đợt sửa hoặc tách riêng FIX khác, tùy PM quyết định phạm vi.

### Có cần giữ trong MVP hay để phase sau

**Phân biệt rõ 2 loại việc — quan trọng để tránh nhầm lẫn phạm vi:**

1. **CRUD Knowledge Base (Editor UI, tương đương VBA `btn_insert`/`btn_load`/`btn_renew`)** — đây là công việc **MIGRATE** (khôi phục lại cái đã có trong VBA), không phải tính năng mới. Vì bản chất chỉ là CRUD đơn giản (validate Score>0, chống trùng, insert/update/delete `problem_cause_rules`), độ phức tạp kỹ thuật thấp. **Khuyến nghị: đưa vào MVP/làm sớm** — đặc biệt vì hiện tại đổi 1 rule chẩn đoán phải deploy lại code (rủi ro vận hành cao hơn hẳn VBA gốc, đi ngược mục tiêu "hiện đại hóa" của CLAUDE.md mục 1).

2. **"Học tự động" từ feedback (dùng `check1..24`/`effectiveness_rating` để tự động điều chỉnh `cause_score`)** — đây là tính năng **MỚI HOÀN TOÀN**, VBA gốc CHƯA TỪNG có (đã xác nhận bằng grep — không nơi nào đọc lại cột Y của Submited để tính toán). Việc thiết kế thuật toán "học" (vd: tăng/giảm `cause_score` theo tỷ lệ case đúng/sai) đòi hỏi quyết định nghiệp vụ (công thức điều chỉnh thế nào, ai duyệt, có cần review trước khi áp dụng không) — **KHÔNG nên coi đây là phần của FIX-005/P0-E migrate, phải tách thành đề xuất tính năng riêng, cần PM/nghiệp vụ QA quyết định và làm rõ trước, để phase sau (sau Phase 12 UAT)**.

---

## 4. Kế hoạch FIX-005 (CHỈ LẬP KẾ HOẠCH — KHÔNG THỰC HIỆN)

**FIX-005: Bổ sung UI quản trị Knowledge Base (CRUD `problem_cause_rules`) + Audit Log**

### Phạm vi
- CHỈ làm CRUD tương đương VBA Editor (`btn_insert`/`btn_load`/`btn_renew` — thêm/sửa/xoá rule Problem-Cause qua UI web, có validate Score>0, chống trùng Problem+Cause).
- KHÔNG bao gồm "học tự động" từ feedback — xác nhận VBA gốc không có sẵn, nếu nghiệp vụ muốn thì lập FIX/feature riêng sau khi có quyết định PM.
- KHÔNG bao gồm khôi phục breakdown per-case ở History (vấn đề riêng của mục 1.6 — có thể note là FIX phụ, tùy phạm vi PM chọn gộp hay tách).
- KHÔNG bao gồm UI chọn Cause theo nhóm 6M (`frmcauses`/cột W) — đây là mục CH khác (VBA-TROUBLE-001/002), nếu PM muốn làm cùng đợt thì mở rộng phạm vi rõ ràng trong ticket, không ngầm định.

### File dự kiến sửa (liệt kê, KHÔNG sửa trong lần này)
- Backend: mở rộng `F:\DF\backend\app\Http\Controllers\TroubleshootingController.php` (thêm `indexProblemCauseRules`, `storeProblemCauseRule`, `updateProblemCauseRule`, `destroyProblemCauseRule`) HOẶC tạo controller mới `ProblemCauseRuleController.php` (khuyến nghị controller riêng để không phình `TroubleshootingController` vốn đã 7 method — theo nguyên tắc single-responsibility).
- `F:\DF\backend\app\Models\ProblemCauseRule.php` — đã có sẵn đủ field (`problem_id, cause_id, cause_score`), cần thêm `id` (hiện là `bigIncrements`, không cần đổi) — kiểm tra đủ, không cần sửa model.
- `F:\DF\backend\app\Models\AuditLog.php` — dùng lại nguyên trạng, không cần sửa.
- `F:\DF\backend\routes\api.php` — thêm route nhóm mới, ví dụ:
  ```
  Route::get('/troubleshooting/problem-cause-rules', ...);          // list, filter theo problem_id (tương đương btn_load)
  Route::post('/troubleshooting/problem-cause-rules', ...);          // create/update theo Problem+Cause (tương đương btn_insert)
  Route::put('/troubleshooting/problem-cause-rules/{id}', ...);      // sửa 1 rule
  Route::delete('/troubleshooting/problem-cause-rules/{id}', ...);   // xoá 1 rule
  Route::put('/troubleshooting/problems/{id}/rules', ...);           // replace toàn bộ mapping của 1 Problem (tương đương btn_renew) — cân nhắc có thực sự cần "replace toàn bộ" hay chỉ cần sửa từng dòng là đủ (đơn giản hơn, an toàn hơn hard-delete)
  ```
- Frontend: view Admin mới, ví dụ `F:\DF\frontend\src\views\admin\KnowledgeBaseEditor.vue` (KHÔNG nhét vào `Troubleshooting.vue` hiện tại — vốn đã dài 1052 dòng, nên tách trang Admin riêng có kiểm soát quyền).

### Database change
- Không cần bảng mới cho dữ liệu chính (`app.problem_cause_rules` đã đủ).
- Ghi audit log: dùng `app.audit_logs` sẵn có (đã xác nhận model đủ tổng quát — xem mục 3). Mỗi lần insert/update/delete rule → 1 bản ghi `AuditLog` với `entity_type='problem_cause_rule'`, `before_data`/`after_data` là snapshot JSON, `action` = `CREATE`/`UPDATE`/`DELETE`.
- Không cần migration mới nếu chỉ dùng `audit_logs` sẵn có — CẦN kiểm tra lại (ngoài phạm vi task này) rằng bảng `app.audit_logs` đã tồn tại qua migration nào đó trước khi code — việc kiểm tra migration `audit_logs` nằm ngoài phạm vi P0-E, nên ghi là **Dependency** cần xác nhận khi thực hiện FIX-005.

### Migration
- Dự kiến KHÔNG cần file migration mới (dùng bảng có sẵn). Nếu khi thực hiện phát hiện `app.audit_logs` chưa tồn tại hoặc thiếu cột, thì cần 1 migration bổ sung, đặt tên theo convention hiện tại, ví dụ `2026_MM_DD_HHMMSS_add_missing_columns_to_audit_logs.php` (tên chính xác xác định lúc thực hiện).

### Acceptance criteria
1. Kỹ sư có role phù hợp (xem RBAC dưới) có thể: xem danh sách rule theo Problem, thêm rule mới (validate Score>0, chặn trùng Problem+Cause hoặc hỏi xác nhận ghi đè), sửa Score của rule đã có, xoá 1 rule.
2. Mọi thao tác CREATE/UPDATE/DELETE rule đều sinh 1 bản ghi `AuditLog` với `before_data`/`after_data` đầy đủ và `user_id` của người thực hiện.
3. `InferenceService::calculate()` phản ánh NGAY rule mới mà không cần deploy lại code (kiểm chứng bằng cách sửa rule qua UI rồi chạy `diagnose()` lại và thấy điểm thay đổi tương ứng).
4. Validate Score>0 hoạt động đúng ở cả backend (không chỉ frontend) — chặn request trực tiếp gửi Score<=0.
5. Route CRUD trả lỗi 403 nếu user không có role được phép sửa KB (xem Rủi ro).

### Regression test
- **Không được phá vỡ `TroubleshootingInferenceTest.php` hiện có** — test này seed qua `TroubleshootingKnowledgeBaseSeeder`, không đụng route mới, nên về nguyên tắc không ảnh hưởng trực tiếp — nhưng CẦN chạy lại toàn bộ suite sau khi thêm route để đảm bảo không có side-effect (ví dụ route mới đăng ký sai middleware group làm lệch route khác).
- Thêm test mới (không nằm trong FIX-005 nhưng nên làm cùng lúc theo quy trình CLAUDE.md mục 4): `test_problem_cause_rule_crud_and_audit_log` — assert tạo/sửa/xoá rule thành công + có bản ghi AuditLog tương ứng + `diagnose()` phản ánh đúng rule mới.

### Rollback
- Vì không có migration schema mới (giả định `audit_logs` đã tồn tại), rollback chỉ cần revert code (controller/routes/frontend) — không có rủi ro dữ liệu vì `problem_cause_rules` không đổi cấu trúc.
- Nếu có migration audit_logs bổ sung, rollback theo `down()` chuẩn của migration đó.

### Dependency
- Cần xác nhận `open-questions.md` mục **CH-TECH-006** (đã đọc trực tiếp, dòng 37-39): "Có cần xây UI quản trị Knowledge Base... đồng thời làm rõ ý nghĩa checkbox `check1..check24`". Phân tích P0-E này đã trả lời phần "ý nghĩa checkbox": **VBA gốc không tự học từ checkbox này, chỉ ghi tĩnh** — nên phần "feedback xác nhận đúng/sai theo từng cause" ở cấp `case_recommendations` là TÙY CHỌN (nice-to-have), không phải điều kiện tiên quyết để làm FIX-005 (Editor CRUD có thể làm độc lập, không phụ thuộc quyết định về per-cause feedback).
- Cần PM xác nhận: có làm CRUD "replace toàn bộ" (tương đương `btn_renew`, hard-delete+insert) hay chỉ CRUD từng dòng (an toàn hơn, tránh mất dữ liệu ngoài ý muốn nếu request bị lỗi giữa chừng).

### Rủi ro
- Cho phép sửa rule qua UI có thể làm SAI LỆCH kết quả chẩn đoán toàn hệ thống nếu không kiểm soát chặt quyền hạn — vì `problem_cause_rules` là bảng dùng chung cho MỌI case chẩn đoán sau này (khác với sửa dữ liệu giao dịch của riêng 1 case). **Đề xuất: chỉ role QA_ENGINEER hoặc ADMIN (cần xác nhận tên role chính xác trong hệ thống RBAC hiện tại — kiểm tra bảng `roles`) được phép CREATE/UPDATE/DELETE `problem_cause_rules`; role OPERATOR chỉ được xem (read-only) hoặc không thấy màn hình này.**
- Nếu triển khai "replace toàn bộ" (`btn_renew` style hard-delete+insert) mà không có transaction DB bao ngoài, có nguy cơ mất dữ liệu giữa chừng nếu request lỗi (VBA gốc cũng có rủi ro này, không có transaction — Excel single-threaded nên ít gặp, nhưng web multi-request cần transaction DB tường minh).
- Vì đây là bảng cấu hình dùng chung (không phải dữ liệu giao dịch lịch sử), hard-delete khi "renew" có thể chấp nhận được theo tinh thần CLAUDE.md mục 3 (chỉ cấm hard-delete "dữ liệu giao dịch hoặc cấu trúc lịch sử") — nhưng khuyến nghị vẫn ưu tiên soft-approach (update in-place + audit log) để tận dụng cơ chế audit đã có, tránh phải khôi phục thủ công nếu sai sót.

### Estimate: **M (Medium)**
Lý do: bản thân CRUD 1 bảng đơn giản (3-4 trường) là **S**, nhưng cộng thêm: (a) thiết kế route "replace toàn bộ theo Problem" cần transaction cẩn thận, (b) tích hợp AuditLog cho cả 3 hành động CRUD, (c) RBAC theo role mới cần xác nhận + có thể cần thêm middleware/policy, (d) frontend cần 1 trang Admin mới (không chỉ mở rộng trang hiện có) với UI danh sách + form thêm/sửa + xác nhận xoá, (e) viết test CRUD + audit log mới. Tổng thể vượt mức "vài giờ" của size S nhưng không đến mức phức tạp kiến trúc của L/XL (không cần bảng mới, không cần thiết kế thuật toán mới).

---

## 5. Ghi chú bổ sung — không thuộc phạm vi FIX-005 nhưng phát hiện trong quá trình đối chiếu
- `Troubleshooting.vue` hardcode LSL/USL của tham số số (`numericParams`, dòng 322-327) thay vì gọi `GET /api/troubleshooting/parameters` — nếu FIX-005 mở khả năng sửa `KB_Parameter`/`app.parameters` trong tương lai, lỗi đồng bộ này sẽ phát sinh ngay. Không sửa trong FIX-005 (ngoài phạm vi P0-E) nhưng nên ghi nhận là rủi ro liên đới.
- Breakdown per-case bị mất ở History (mục 1.6/3) là vấn đề tách biệt với Editor CRUD — nếu PM muốn gộp chung 1 đợt sửa "Troubleshooting KB & History", cần mở ticket riêng hoặc mở rộng rõ ràng phạm vi FIX-005, không ngầm định gộp.
