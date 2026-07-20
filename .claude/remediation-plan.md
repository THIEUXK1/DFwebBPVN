# Kế hoạch Khắc phục theo Kết quả Rà soát VBA (remediation-plan.md)

Lập ngày 2026-07-17, dựa trên [vba-migration-matrix.md](file:///F:/DF/.claude/vba-migration-matrix.md), 5 tài liệu phân tích P0 chi tiết trong [.claude/p0-analysis/](file:///F:/DF/.claude/p0-analysis/), và [source-files-missing.md](file:///F:/DF/.claude/source-files-missing.md).

> [!IMPORTANT]
> **TRẠNG THÁI: CHƯA ĐƯỢC PHÉP THỰC HIỆN.** Toàn bộ 10 FIX dưới đây là KẾ HOẠCH chờ người dùng duyệt. Chưa sửa code sản xuất, chưa chạy migration, chưa thay đổi schema. Thứ tự FIX-001→010 là thứ tự đánh số theo yêu cầu, KHÔNG phải thứ tự thực hiện bắt buộc — thứ tự thực hiện đề xuất xem mục "Trình tự đề xuất" cuối tài liệu.

Quy ước Estimate: **S** ≤ 0,5 ngày; **M** 1-2 ngày; **L** 3-5 ngày; **XL** > 1 tuần hoặc không ước lượng được do phụ thuộc ngoài.

---

## FIX-001: Khôi phục/thiết kế lại `TraHeSo` (tra hệ số 3 chiều mã × khổ vải × tiêu)

*Phân tích kỹ thuật đầy đủ (thuật toán, điều kiện biên, golden test GT-1→GT-6): [p0-a-traheso.md](file:///F:/DF/.claude/p0-analysis/p0-a-traheso.md).*

- **Phạm vi:** Bảng dữ liệu chuẩn hóa PostgreSQL + method service PHP tương đương hàm `TraHeSo` gốc; tùy chọn endpoint API + tích hợp `Recipes.vue`.
- **File dự kiến sửa:** migration mới `2026_07_17_000001_create_tension_lookup_tables.php`; model mới `TensionLookupCategory.php`, `TensionLookupMatrix.php`; `FormulaCalculationService.php` (thêm `lookupTensionCoefficient()`); `CalculationController.php`; `routes/api.php`; seeder mới; `Recipes.vue` (nếu tích hợp UI); `FormulaCalculationServiceTest.php`.
- **Database change:** Có — 2 bảng mới `app.tension_lookup_categories` (product_code → loại A/B/C) và `app.tension_lookup_matrix` (category, row_in_block, tiao_value, width_value → coefficient). KHÔNG sửa `water_configs`/`process_parameters`.
- **Migration:** `2026_07_17_000001_create_tension_lookup_tables.php`, `down()` drop 2 bảng mới (reference data, rollback không mất dữ liệu vận hành).
- **Acceptance criteria:** tra đúng ±0.000001 so với Excel gốc (Golden Master); Code/Width/Tiao không khớp → trả `null` (KHÔNG trả `0` — 0 là hệ số hợp lệ); hành vi "ô rỗng" phải xác nhận bằng Excel thật trước khi chốt; 6 golden test GT-1→GT-6 có expected thật (không placeholder); UI phân biệt rõ giá trị "tra tự động" vs "nhập tay".
- **Regression test:** 6 test mới trong `FormulaCalculationServiceTest.php`; chạy lại toàn suite xác nhận `test_calculate_water`/`test_get_precision_rounded_weight` không bị side-effect.
- **Rollback:** `php artisan migrate:rollback --path=...` — an toàn vì là bảng tra cứu tĩnh.
- **Dependency:** **CH-BUS-004 (chặn cứng)** — nếu nghiệp vụ trả lời "không còn dùng", FIX-001 hủy. Cần người dùng export dữ liệu bảng tra thật (`AI2:AK1500`, `D3:AE3`, 3 khối 8 dòng A/B/C) từ Excel gốc. Cần xác nhận ý nghĩa/đơn vị của `Code`/`Width`/`Tiao`.
- **Rủi ro:** dữ liệu export sai/thiếu không tự phát hiện được; đoán sai ý nghĩa nghiệp vụ 3 tham số → xây sai mô hình; nhầm lẫn `0` vs `null`; **có khả năng hàm đã là dead code trong vận hành cũ** (không tìm thấy nơi gọi trong VBA — chỉ suy đoán được gọi từ công thức ô) → nếu vậy toàn bộ FIX lãng phí; khác biệt case-sensitivity giữa `Find` Excel và so khớp SQL.
- **Estimate:** **L** — code đơn giản (S/M) nhưng effort chính nằm ở thu thập/xác thực dữ liệu bảng tra thật + chờ xác nhận nghiệp vụ + rủi ro làm lại.

## FIX-002: Sửa thuật toán đọc cân (`ScaleReader.CleanWeight` lấy số cuối) + bổ sung StableFilter / ngưỡng 3 mức / tare-delta

*Phân tích + 7 test vector chứng minh khác biệt: [p0-c-scale-algorithm.md](file:///F:/DF/.claude/p0-analysis/p0-c-scale-algorithm.md).*

- **Phạm vi:** Tách 2 đợt. **Đợt 1 (không phụ thuộc nghiệp vụ):** sửa `CleanWeight` lấy token số CUỐI CÙNG (như VBA `ExtractLastNumber`) thay vì match Regex đầu tiên; thêm `StableFilter` (ổn định = 2 lần đọc liên tiếp giống hệt chuỗi) vào Agent, bỏ hard-code `stable:true` ở `WeighingStation.vue:402`; khôi phục ngưỡng cảnh báo 3 mức (vàng-chưa đủ / xanh-đạt / đỏ-vượt) thay vì 2 mức hiện tại. **Đợt 2 (chờ nghiệp vụ):** tare/delta (`Delta_Begin`/`AutoFlow_OnWeight` tương đương).
- **File dự kiến sửa:** `F:\DF\agent\ScaleReader.cs` (CleanWeight, thêm StableFilter); `F:\DF\agent\Worker.cs` (truyền stable flag lên API); `WeighingJobController.php` (nhận/ghi stable flag, tare nếu Đợt 2); `WeighingStation.vue` (bỏ hard-code, hiển thị 3 mức); `ScaleLiveWeightTest.php`.
- **Database change:** Cân nhắc thêm cột `is_stable` (boolean) vào `app.scale_measurements` để lưu cờ ổn định thật (hiện field gửi lên bị bỏ qua); Đợt 2 có thể cần cột `tare_weight`.
- **Migration:** `2026_07_17_000002_add_stability_columns_to_scale_measurements.php` (nếu chốt thêm cột).
- **Acceptance criteria:** toàn bộ 7 test vector (TV1-TV7 trong p0-c) PASS — đặc biệt TV1 (input `"12,ST,GS,+000010.5g"` phải trả `+000010.5` chứ không phải `12`) và TV4 (gross/net khi có tare); dữ liệu rác cổng COM không được âm thầm quy về `0.0`.
- **Regression test:** test case accept/reject tại biên dung sai (Phần C.7 p0-c) vào `ScaleLiveWeightTest.php`; **cần tạo test project .NET cho Agent** (hiện hoàn toàn không có — khoảng trống hạ tầng phải giải quyết trước/cùng FIX-002, nếu không không thể chứng minh CleanWeight sửa đúng bằng CI).
- **Rollback:** Agent là ứng dụng standalone — rollback bằng deploy lại binary cũ; migration cột mới rollback chuẩn.
- **Dependency:** Đợt 1 độc lập, làm ngay được sau duyệt. Đợt 2 **chờ CH-BUS-006** (tare bằng nút vật lý trên cân hay phần mềm tự trừ).
- **Rủi ro:** sửa `CleanWeight` thay đổi hành vi đọc cân đang chạy — nếu pilot đã bắt đầu phải kiểm tra dữ liệu trước/sau deploy; tare/delta là thay đổi hành vi nghiệp vụ lớn nhất (ảnh hưởng trực tiếp `actual_weight` ghi vào audit/report) — làm sau cùng, UAT riêng.
- **Estimate:** Đợt 1 **S–M**; Đợt 2 **L** (gồm cả UAT riêng). Hạ tầng test .NET: **S** thêm.

## FIX-003: Bổ sung API tạo hàng chờ điều phối (`store`) — thay thế `btnSAVE_Click`/`Insert_tbl_input_all`/`MoveToSend`

*Truy vết 10 bước luồng gốc + nguyên văn quy tắc 250L: [p0-b-dispatch-flow.md](file:///F:/DF/.claude/p0-analysis/p0-b-dispatch-flow.md).*

- **Phạm vi:** Tách 2 bước. **FIX-003a:** `store` cơ bản — bắt buộc color/code, chặn trùng (lưu ý: kiểm tra trùng phải quyết định rõ phạm vi — VBA gốc chỉ check trong `tbl_input_all`, tức cùng color+code nhập lại được sau khi move — lỗ hổng có sẵn, KHÔNG copy nguyên trạng, cần nghiệp vụ chốt phạm vi check trùng); trạng thái ban đầu đúng chuẩn (`queue_state='INPUT'`, sending/sent/issent tương đương 0); auto-move sang `TO_SEND` khi `confirm2=OK AND tank≠rỗng` (theo nhánh C3 — nhánh MID không check tank rỗng là kém an toàn hơn); toàn bộ trong 1 transaction. **FIX-003b:** quy tắc min-level 250L (chờ CH-BUS-005). KHÔNG bao gồm UI duyệt thủ công (`Approve_Update_Move`) — tách FIX riêng nếu nghiệp vụ cần.
- **File dự kiến sửa:** `MachineDispatchController.php` (thêm `store`); service mới `MachineDispatchService.php` (`existsColorCode()`, `createDispatch()`, `moveToSend()`); FormRequest validation mới; `routes/api.php`; frontend `MachineQueue.vue` (form tạo mới) — hoặc màn hình nhập liệu riêng theo mô hình workstation.
- **Database change:** FIX-003a không cần. FIX-003b: nếu nghiệp vụ chọn "cấu hình được" → bảng `machine_min_level_rules`; nếu hard-code → không cần. **Lưu ý kiểu dữ liệu:** `production_batches.level_code` hiện là text — so sánh `< 250` cần cast/validate cẩn thận hoặc chuẩn hóa cột.
- **Migration:** chỉ khi FIX-003b chọn phương án bảng cấu hình: `2026_07_17_000003_create_machine_min_level_rules.php`.
- **Acceptance criteria:** tạo mới thành công trả 201 + bản ghi đúng trạng thái; trùng color+code trả 422 kèm thông báo rõ; auto-move đúng điều kiện (kèm test case tank rỗng KHÔNG được move — theo C3); (003b) máy VD06-VD13 + tank 1A/2B + level<250 bị chặn 422; toàn bộ trong transaction — lỗi giữa chừng không để lại bản ghi mồ côi.
- **Regression test:** file test mới `MachineDispatchStoreTest.php`; không phá vỡ `MachineDispatchConcurrencyTest.php` hiện có.
- **Rollback:** revert code (không có migration ở 003a); 003b rollback migration chuẩn.
- **Dependency:** 003a độc lập, làm ngay được. 003b **chờ CH-BUS-005** (quy tắc 250L đúng không, áp dụng máy/thùng nào — C3 và MID mâu thuẫn, KHÔNG tự chọn). Cần nghiệp vụ chốt thêm: danh sách tank 4 hay 5 slot (1A/2B/3C/4D có thêm FB không — C3 có, MID không).
- **Rủi ro:** triển khai 003a không có 003b khiến web "kém an toàn hơn C3" tạm thời — chấp nhận được trong Phase 12 vì Excel VBA vẫn chạy song song, nhưng phải thông báo rõ vận hành viên.
- **Estimate:** 003a **M**; 003b **S** (hard-code) hoặc **M** (bảng cấu hình).

## FIX-004: Xác minh/hoàn thiện mapping `tbl_ToSend2` / `WAITING` / `tbl_Waiting` / `tblSync`

*Inventory dữ liệu thật + phát hiện bảng thứ 4 lệch cột: [p0-d-legacy-tables-inventory.md](file:///F:/DF/.claude/p0-analysis/p0-d-legacy-tables-inventory.md).*

- **Phạm vi:** **CHỜ workbook nguồn** (P0 #1-3 trong `source-files-missing.md`). Không tự sửa mapping từ suy luận. Phạm vi MỞ RỘNG so với ban đầu: thêm bảng thứ 4 `tbl_Waiting` (71 dòng) — script transform hiện coi là "unshifted" nhưng dữ liệu thật cho thấy CŨNG lệch cột (CONFIRM1 luôn ="OK", MACHINE chứa mã VDxx, LEVEL chứa số — cùng kiểu lệch với `WAITING`).
- **File dự kiến sửa (sau khi có workbook):** `sql_migration/03_transform_legacy_to_target.sql` (khối 6/7/8, dòng 140-204); `migration-strategy.md`; `source-traceability.md`.
- **Database change:** Không (transform từ staging, không đổi schema).
- **Migration:** Không áp dụng (SQL transform 1 lần).
- **Acceptance criteria:** JOIN match ≥95% sang `production_batches`/`machines` — NHƯNG chỉ đo SAU khi chạy khối 1 transform populate `app.machines` từ `tbl_status` thật (DB dev hiện chỉ có 5 máy test nên JOIN luôn ~0% bất kể mapping đúng sai); `tblSync` rỗng → acceptance = xác nhận bằng văn bản rằng multi-Front-End sync không triển khai tại pilot, hoặc tìm được export có dữ liệu.
- **Regression test:** bổ sung 3 query vào `04_validation_queries.sql` (đối soát batch_id NULL theo source_table; cảnh báo format nguồn đổi; đếm TIME non-null trong WAITING).
- **Rollback:** an toàn tuyệt đối — staging không bị đụng; xóa dữ liệu `app.machine_dispatches` theo `source_table` rồi transform lại.
- **Dependency:** **Workbook nguồn (chặn cứng)** — không có thì không sửa.
- **Rủi ro:** dùng dữ liệu di trú từ 4 bảng này cho báo cáo/đối soát trước khi xác minh → số liệu sai không phát hiện được; `tblSync` rỗng có thể do tính năng chưa từng chạy HOẶC do export thiếu — chưa phân định được.
- **Estimate:** Kịch bản A (có workbook): **S**. Kịch bản B (không có, xác minh thủ công qua phỏng vấn vận hành): **M-L**, độ tin cậy thấp hơn.

## FIX-005: UI quản trị Knowledge Base chẩn đoán sự cố (CRUD `problem_cause_rules`) + Audit Log

*Phân tích đầy đủ + kết luận "VBA không có học tự động": [p0-e-troubleshooting-feedback.md](file:///F:/DF/.claude/p0-analysis/p0-e-troubleshooting-feedback.md).*

- **Phạm vi:** CHỈ khôi phục tương đương VBA Editor (thêm/sửa/xóa rule Problem-Cause, gán điểm, tương đương `btn_insert`/`btn_load`/`btn_renew`) + audit log bắt buộc (CLAUDE.md mục 5 — VBA gốc KHÔNG có audit, đây là điểm làm tốt hơn). **KHÔNG bao gồm "học tự động"** — đã xác minh VBA gốc cũng không có (cột feedback chỉ ghi, không bao giờ đọc lại); đó là tính năng mới hoàn toàn, cần PM/QA quyết định riêng, để sau Phase 12.
- **File dự kiến sửa:** `TroubleshootingController.php` (thêm CRUD routes cho rules) hoặc controller mới `KnowledgeBaseAdminController.php`; `routes/api.php` (middleware `role:` giới hạn TECHNOLOGIST/ADMIN); `Troubleshooting.vue` hoặc view admin mới; `ProblemCauseRule.php` (đã có, kiểm tra fillable).
- **Database change:** Không cần bảng mới — dùng `app.audit_logs` sẵn có (model đủ tổng quát, action mới ví dụ `KB_RULE_CREATE/UPDATE/DELETE`, before/after JSONB).
- **Migration:** Không.
- **Acceptance criteria:** CRUD rule thành công + mỗi thao tác có bản ghi AuditLog; `diagnose()` phản ánh ngay rule mới (không cần deploy); role không đủ quyền bị 403; xóa rule là thao tác trên dữ liệu cấu hình (không phải giao dịch lịch sử) nên hard-delete chấp nhận được NHƯNG phải có audit log trước/sau.
- **Regression test:** test mới `test_problem_cause_rule_crud_and_audit_log`; không phá vỡ `TroubleshootingInferenceTest.php`.
- **Rollback:** revert code, không có migration.
- **Dependency:** xác nhận CH-TECH-006 (phần "có cần UI Editor" — phần "ý nghĩa checkbox" đã được P0-E trả lời: không có học tự động); xác nhận migration `audit_logs` tồn tại (kiểm tra nhanh khi bắt đầu).
- **Rủi ro:** cho sửa rule qua UI có thể làm sai kết quả chẩn đoán nếu RBAC lỏng — giới hạn role chặt; ghi chú liên đới: `Troubleshooting.vue` hardcode LSL/USL (không gọi API parameters) sẽ thành lỗi đồng bộ thật nếu sau này mở khả năng sửa Parameter — không thuộc FIX-005 nhưng nên sửa cùng đợt nếu tiện (1 dòng gọi API).
- **Estimate:** **M**.

## FIX-006: Template tem còn thiếu (27 dòng / 15L special / landscape / JIT)

- **Phạm vi:** Audit + đối chiếu 2 workbook template tem bị thiếu; sau đó quyết định có cần bổ sung template TSPL tương ứng vào hệ mới không.
- **File dự kiến sửa:** chưa xác định được (phụ thuộc nội dung file thiếu) — dự kiến `WeighingJobController::printLabel`/`PrintJobController` (thêm biến thể template) + có thể cấu hình template theo workstation.
- **Database change / Migration:** chưa xác định — có thể cần bảng `label_templates` nếu nghiệp vụ xác nhận nhiều template cùng hoạt động.
- **Acceptance criteria:** (đặt sau khi có file) — layout tem in thử khớp mẫu tem thật đang dùng tại xưởng, xác nhận bằng đối chiếu bản in vật lý (theo `migration-strategy.md` bước 7).
- **Regression test:** mở rộng `PrintJobPipelineTest.php`.
- **Rollback:** revert code/template.
- **Dependency:** **2 file P0 nhóm In tem trong `source-files-missing.md`** (`DF002 - PRINTER...27rows`, `DF002 no formulas...landscape jit`) — chặn cứng.
- **Rủi ro:** nếu xưởng vẫn dùng tem 27 dòng/15L trong sản xuất thật mà hệ mới không có, trạm in không thay thế được Excel khi cutover — không chặn pilot (Excel chạy song song) nhưng chặn Phase 13.
- **Estimate:** **XL** (không ước lượng được trước khi có file — audit file mới: S-M; xây template: S-M/1 template; tổng phụ thuộc số template thật).

## FIX-007: Tồn kho phòng liệu — ngưỡng cảnh báo + log giao dịch + quy đổi kiện

- **Phạm vi:** Khôi phục 3 nghiệp vụ MISSING của `DF料房-染料存.xlsm`: (1) cảnh báo tồn kho thấp theo ngưỡng (VBA: đỏ <1000g, cam <5000g); (2) log giao dịch xuất/nhập kho có audit trail (thay cơ chế `tblWH_LOG` bị xóa sau xử lý — thiết kế lại có giữ lịch sử, đúng nguyên tắc CLAUDE.md); (3) quy đổi kiện hàng theo mã (bảng PCS 6 mức); (4) cơ chế tự trừ kho khi hoàn tất cân (nối `weighing_job_items` COMPLETED → trừ `materials.stock_qty`, qua job/event có idempotency).
- **File dự kiến sửa:** `MaterialController.php` (+ transactions endpoint); model mới `MaterialTransaction.php`; `Materials.vue` (badge cảnh báo, form nhập theo kiện); có thể listener/job trừ kho tự động.
- **Database change:** cột `min_stock_threshold`/`warning_threshold` (hoặc 1 bảng cấu hình ngưỡng) vào `app.materials`; bảng mới `app.material_transactions` (append-only); cột/bảng quy đổi kiện `pack_weight`.
- **Migration:** `2026_07_17_000004_create_material_transactions_and_thresholds.php`.
- **Acceptance criteria:** nhập kho theo kiện tính đúng `số_kiện × pack_weight + số_lẻ`; mọi thay đổi stock_qty đều có bản ghi transaction (không sửa tay trực tiếp nữa hoặc sửa tay cũng ghi log); cân xong tự trừ kho đúng 1 lần (idempotent); badge màu đúng ngưỡng.
- **Regression test:** test mới cho transaction log + auto-deduct; không phá vỡ test Material hiện có.
- **Rollback:** migration rollback; tính năng auto-deduct có feature-flag tắt được.
- **Dependency:** **CH-BUS-007** — xác nhận nghiệp vụ còn cần các cơ chế này không, và ai/hệ thống nào đang ghi `tblWH_LOG` (chưa rõ nguồn ghi — nếu là hệ thống ngoài phạm vi thì thiết kế integration khác).
- **Rủi ro:** tự trừ kho sai (double-deduct khi retry) nếu idempotency không chặt; ngưỡng 1000/5000g có thể là giá trị cũ cần nghiệp vụ cập nhật.
- **Estimate:** **L**.

## FIX-008: Bổ sung Golden/Regression test theo phát hiện audit

- **Phạm vi:** (1) golden test StableFilter/ExtractLastNumber/tare (3 bộ + 7 test vector — trùng với acceptance FIX-002, tách ra đây để theo dõi riêng phần hạ tầng); (2) tạo test project .NET cho Agent (hiện = 0 test); (3) golden test `TraHeSo` GT-1→6 (sau FIX-001); (4) test cumulative ProcessScore với PR≥2 cho `InferenceService` (test hiện tại chỉ phủ PR03/1-problem — khoảng trống đã phát hiện ở VBA-TROUBLE-035); (5) bổ sung 3 query đối soát vào `04_validation_queries.sql` (theo FIX-004); (6) test biên dung sai accept/reject không phụ thuộc màu (phòng tái xuất bug REJECTED kiểu workbook B); (7) đưa `verify-matrix-counts.sh` vào quy trình (chạy khi sửa ma trận).
- **File dự kiến sửa:** `backend/tests/**`, project test .NET mới trong `F:\DF\agent\`, `sql_migration/04_validation_queries.sql`.
- **Database change / Migration:** Không.
- **Acceptance criteria:** CI chạy được toàn bộ test mới; số test .NET > 0; mỗi FIX 001-007 khi hoàn tất đều có test tương ứng đã liệt kê trong mục Regression của FIX đó.
- **Regression test:** chính nó.
- **Rollback:** không áp dụng (chỉ thêm test).
- **Dependency:** từng phần phụ thuộc FIX tương ứng hoàn tất; phần (2), (4), (5), (6), (7) độc lập — làm ngay được.
- **Rủi ro:** thấp; rủi ro duy nhất là test vector dựa trên hiểu sai hành vi VBA — đã giảm thiểu bằng trích code nguyên văn trong p0-c.
- **Estimate:** **M** (phần độc lập); phần gắn FIX khác tính trong FIX đó.

## FIX-009: Xử lý các dòng PARTIALLY_MIGRATED còn lại (30 dòng, ngoài phạm vi FIX-001→007)

- **Phạm vi:** Nhóm theo cụm: (a) Dispatch — auto-refresh/polling thiếu (`MachineQueue.vue` không tự làm mới dù backend có RealtimeService — nối SSE/polling), màn hình lịch sử SENT (index() loại trừ SENT), cảnh báo bản ghi chờ >24/48h (alert rule mới); (b) Reports — dedup theo batch/khung 7 ngày của báo cáo tiêu hao (RECIPE-015/016/017); (c) Print — tiêu chí tra cứu COLOR+CODE+ngày (khác legacy_batch_id hiện có), thứ tự lưu/in + idempotency `PrintJobController::store` (chưa chặn double-submit), chuẩn hóa QR payload `LOT:...|` → `DF:<TYPE>:<uuid>`; (d) Troubleshooting — lưu breakdown điểm vào `case_recommendations` (mất khi xem lại case), prefill setpoint, gọi API parameters thay hardcode.
- **File dự kiến sửa:** theo cụm — `MachineQueue.vue`, `MachineDispatchController.php`, `ReportController.php`, `PrintJobController.php`, `TroubleshootingController.php`, `Troubleshooting.vue`, migration nhỏ thêm cột breakdown vào `case_recommendations`.
- **Database change:** cột `problem_score`/`process_score`/`parameter_score` vào `app.case_recommendations` (cụm d); có thể alert rule mới (cụm a — dữ liệu, không phải schema).
- **Migration:** `2026_07_17_000005_add_score_breakdown_to_case_recommendations.php` (cụm d).
- **Acceptance criteria:** theo từng cụm — liệt kê chi tiết khi tách ticket; nguyên tắc chung: hành vi mới phải được đối chiếu ngược về dòng ma trận tương ứng và cập nhật trạng thái dòng đó sang FULLY_MIGRATED/REPLACED_EQUIVALENTLY kèm bằng chứng test.
- **Regression test:** theo cụm.
- **Rollback:** theo cụm, đều là thay đổi nhỏ độc lập.
- **Dependency:** không có dependency chặn cứng — làm được ngay sau duyệt, ưu tiên sau FIX-002/003.
- **Rủi ro:** thấp/trung bình theo cụm; cụm (c) QR payload cần cẩn thận tương thích máy quét hiện có (ràng buộc C-04 trong `risks-and-assumptions.md`).
- **Estimate:** tổng **L** nếu làm cả 4 cụm; mỗi cụm riêng lẻ **S-M**.

## FIX-010: Rà soát và chốt số phận 67 dòng DEAD_CODE_CANDIDATE

- **Phạm vi:** KHÔNG xóa gì. Trình danh sách 67 dòng DEAD_CODE_CANDIDATE (xem `pilot-blockers.md` danh sách 3) cho người dùng/nghiệp vụ xác nhận từng cụm: (a) xác nhận "đúng là code chết, không cần migrate" → chuyển trạng thái sang DEPRECATED_CONFIRMED trong ma trận; (b) phát hiện "thực ra có được dùng" (ví dụ Shape/Button gán macro mà olevba không trích xuất được — hạn chế đã ghi nhận) → chuyển sang MISSING và lập FIX mới. Đặc biệt cần hỏi: `Open_Form`/`ShowUserForm1` (điểm vào duy nhất của form báo cáo tiêu hao và form tồn kho — nếu chúng "chết" thì cả cụm form đó chết theo, mâu thuẫn với việc nghiệp vụ có vẻ vẫn dùng các form này).
- **File dự kiến sửa:** chỉ `vba-migration-matrix.md` (cập nhật trạng thái theo xác nhận).
- **Database change / Migration:** Không.
- **Acceptance criteria:** 100% dòng DEAD_CODE_CANDIDATE được chuyển sang DEPRECATED_CONFIRMED hoặc MISSING (kèm nguồn xác nhận); chạy lại `verify-matrix-counts.sh` PASS.
- **Regression test:** `verify-matrix-counts.sh`.
- **Rollback:** không áp dụng (chỉ tài liệu).
- **Dependency:** cần buổi xác nhận với người dùng/vận hành viên (checklist câu hỏi đã có sẵn trong TÓM TẮT từng nhóm của ma trận).
- **Rủi ro:** thấp — rủi ro duy nhất là xác nhận sai (người được hỏi không phải người dùng thật của công cụ đó).
- **Estimate:** **S** (phía kỹ thuật) + thời gian chờ xác nhận nghiệp vụ.

---

## Trình tự thực hiện đề xuất (theo dependency và giá trị cho Phase 12)

| Đợt | FIX | Điều kiện bắt đầu |
|---|---|---|
| **Ngay sau duyệt** | FIX-002 Đợt 1 (CleanWeight + StableFilter + 3 mức) · FIX-008 phần độc lập (test .NET, test PR≥2, validation queries) · FIX-003a (store cơ bản) | Không chờ gì — chỉ cần duyệt kế hoạch này |
| **Sau khi có câu trả lời nghiệp vụ** | FIX-002 Đợt 2 (tare — CH-BUS-006) · FIX-003b (250L — CH-BUS-005) · FIX-001 (TraHeSo — CH-BUS-004 + dữ liệu bảng tra) · FIX-007 (tồn kho — CH-BUS-007) · FIX-005 (KB Editor — CH-TECH-006) | Câu hỏi tương ứng trong `open-questions.md` được trả lời |
| **Sau khi có file bổ sung** | FIX-004 (mapping 4 bảng — cần workbook P0 #1-3) · FIX-006 (template tem — cần 2 file DF002) | File trong `source-files-missing.md` mức P0 được cung cấp |
| **Nền, làm dần** | FIX-009 (4 cụm PARTIALLY) · FIX-010 (chốt dead code) | Sau các FIX ưu tiên, hoặc xen kẽ khi chờ xác nhận |
