# Session Log - Phi锚n l脿m vi峄嘽 ng脿y 15/07/2026

## Nh岷璽 k媒 ho岷 膽峄檔g

### 1. File 膽茫 膽峄峜 v脿 ph芒n t铆ch
- `Bao_cao_phan_tich_thiet_ke_he_thong_tu_VBA.docx` (chuy峄僴 sang d岷g txt 膽峄?膽峄峜)
- `Bao_cao_PTTK_va_chuyen_doi_SQL_V1.0.docx` (chuy峄僴 sang d岷g txt 膽峄?膽峄峜)
- `F:\DF\chem_order.accdb` (C啤 s峄?d峄?li峄噓 Access m峄沬 b峄?sung, ki峄僲 tra qua pyodbc)
- `sql_migration/01_legacy_access_import_postgresql.sql`
- `sql_migration/02_target_normalized_schema_postgresql.sql`
- `sql_migration/03_transform_legacy_to_target.sql`
- `sql_migration/04_validation_queries.sql`
- `sql_migration/access_inventory.json`
- Th瓢 m峄 `.claude` c农 (c谩c t峄噋 `CLAUDE.md`, `instructions.md` v脿 c谩c t峄噋 trong th瓢 m峄 `commands/`).

### 2. Ph芒n lo岷 v脿 x峄?l媒 th瓢 m峄 `.claude` c农
To脿n b峄?th瓢 m峄 `.claude` c农 膽瓢峄 x谩c nh岷璶 sao ch茅p t峄?d峄?谩n "Stock Signal App" (峄﹏g d峄g ph芒n t铆ch ch峄﹏g kho谩n Vi峄噒 Nam) v脿 kh么ng c贸 b岷 k峄?li锚n quan n脿o 膽岷縩 d峄?谩n DF hi峄噉 t岷.

**B岷g ph芒n lo岷 x峄?l媒:**
- `CLAUDE.md` (c农): X贸a & Ghi 膽猫 m峄沬 ho脿n to脿n ph峄 v峄?d峄?谩n DF.
- `instructions.md` (c农): X贸a b峄?ho脿n to脿n (Kh么ng li锚n quan).
- Th瓢 m峄 `commands/` (c农) bao g峄搈 `audit-candles.md`, `audit-indicators.md`, `audit-momentum-bvps.md`, `audit-signals.md`, `audit-ui.md`, `backtest.md`, `run-app.md`: X贸a b峄?to脿n b峄?th瓢 m峄 (Kh么ng li锚n quan, tr谩nh g芒y hi峄僽 nh岷 cho l岷璸 tr矛nh vi锚n t瓢啤ng lai).

### 3. C谩c t峄噋 膽茫 t岷 m峄沬 / c岷璸 nh岷璽 trong `.claude`
- `CLAUDE.md` (C岷璸 nh岷璽)
- `project-overview.md` (T岷 m峄沬)
- `system-context.md` (T岷 m峄沬)
- `business-modules.md` (T岷 m峄沬)
- `current-data-model.md` (T岷 m峄沬)
- `target-data-model.md` (T岷 m峄沬)
- `migration-strategy.md` (T岷 m峄沬)
- `architecture-decisions.md` (T岷 m峄沬)
- `coding-standards.md` (T岷 m峄沬)
- `security-rules.md` (T岷 m峄沬)
- `testing-strategy.md` (T岷 m峄沬)
- `development-roadmap.md` (T岷 m峄沬)
- `open-questions.md` (T岷 m峄沬)
- `risks-and-assumptions.md` (T岷 m峄沬)
- `source-traceability.md` (T岷 m峄沬)
- `session-log.md` (T岷 m峄沬)

---

## C谩c Ph谩t hi峄噉 Ch铆nh (Key Findings)

### 1. L峄梚 l峄嘽h c峄檛 d峄?li峄噓 (Column Shift) nghi锚m tr峄峮g trong Access Legacy
Khi ph芒n t铆ch t峄噋 `01_legacy_access_import_postgresql.sql` v脿 so s谩nh c岷 tr煤c 膽峄媙h ngh末a v峄沬 d峄?li峄噓 `COPY` th峄眂 t岷? ch煤ng t么i ph谩t hi峄噉 hai b岷g h脿ng ch峄?膽i峄乽 ph峄慽 b峄?l峄嘽h c峄檛 nghi锚m tr峄峮g:
- **B岷g `tbl_ToSend2`:** C峄檛 `CODE` ch峄゛ d峄?li峄噓 Color, c峄檛 `CONFIRM1` ch峄゛ Product Code, c峄檛 `MACHINE` ch峄゛ confirmation `OK`, c峄檛 `TANK` ch峄゛ Machine (`VD15`), c峄檛 `CONFIRM2` ch峄゛ Level (`450`), c峄檛 `SENDING` ch峄゛ confirm 2 (`OK`), c峄檛 `SENT` ch峄゛ `0`, c峄檛 `TIME1` ch峄゛ `0`, c峄檛 `TIME2` ch峄゛ Time 1, v脿 c峄檛 `TIME3` ch峄゛ Time 2.
- **B岷g `WAITING`:** C峄檛 `COLOR` ch峄゛ Product Code (`L23892`), c峄檛 `CODE` ch峄゛ confirmation `OK`, c峄檛 `CONFIRM1` ch峄゛ Machine (`VD02` ho岷穋 `VD09`), c峄檛 `MACHINE` ch峄゛ Tank (`X`), c峄檛 `TANK` ch峄゛ Level (`50` ho岷穋 `100`), v脿 to脿n b峄?d峄?li峄噓 m脿u s岷痗 (`COLOR`) th峄眂 t岷?b峄?m岷 kh峄廼 b岷g n脿y.

> [!WARNING]
> **H岷璾 qu岷?** C芒u l峄噉h transform 膽峄檔g trong `03_transform_legacy_to_target.sql` s峄?d峄g ph茅p join t末nh:
> `LEFT JOIN app.machines m ON m.code=trim(d."MACHINE"::text)`
> `LEFT JOIN app.production_batches b ON b.product_code=d."CODE"::text AND b.machine_id=m.id`
> Vi峄嘽 n脿y s岷?d岷玭 膽岷縩 k岷縯 qu岷?join l脿 **NULL** cho t岷 c岷?c谩c b岷 ghi di tr煤 t峄?`tbl_ToSend2` v脿 `WAITING` v矛 `d."MACHINE"` ch峄゛ `'OK'` ho岷穋 `'X'` v脿 `d."CODE"` ch峄゛ `'EP68132'` (color) ho岷穋 `'OK'`. Script transform c岷 ph岷 膽瓢峄 vi岷縯 l岷 膽峄?谩nh x岷?c谩c c峄檛 ri锚ng bi峄噒 cho t峄玭g b岷g.

### 2. D峄?li峄噓 c芒n h贸a ch岷 trong `tblRECORD_chem` v脿 vai tr貌 c峄 `tbl_status` (T峄?`chem_order.accdb`)
- C岷?trong database primary (5.061 d貌ng) v脿 database b峄?sung `chem_order.accdb` (1.500 d貌ng), to脿n b峄?d峄?li峄噓 c峄檛 `WEIGHT` v脿 `PROCESS` trong `tblRECORD_chem` 膽峄乽 b峄?tr峄憂g.
- Tuy nhi锚n, t峄噋 `chem_order.accdb` m峄沬 ch峄゛ b岷g **`tbl_status`** (40 d貌ng) 膽峄媙h ngh末a c岷 h矛nh: `machine` (v铆 d峄?`'VD016'`) -> `chem` (v铆 d峄?`4`) -> `chem_name` (v铆 d峄?`'AC77'`) -> `status` (v铆 d峄?`'0'`).
- 膼芒y l脿 ph谩t hi峄噉 quan tr峄峮g: ch峄﹏g minh nh脿 m谩y nhu峄檓 s峄?d峄g **h峄?th峄憂g c岷 h贸a ch岷 t峄?膽峄檔g** (nh瓢 h峄?Copower). B岷g `tbl_status` l脿 b岷 膽峄?c岷 h矛nh van/k锚nh n岷 h贸a ch岷 cho t峄玭g m谩y nhu峄檓. Khi m岷?nhu峄檓 b岷痶 膽岷, VBA vi岷縯 l峄噉h n岷 h贸a ch岷 th么 v脿o b岷g n脿y v峄沬 `status = '0'` 膽峄?h峄?th峄憂g c岷 t峄?膽峄檔g th峄眂 thi.
- Do 膽贸, kh么ng c贸 d峄?li峄噓 c芒n h贸a ch岷 th峄?c么ng trong `tblRECORD_chem`. Ph芒n h峄?h贸a ch岷 c岷 膽瓢峄 chuy峄僴 sang t铆ch h峄 膽i峄乽 khi峄僴 t峄?膽峄檔g thay v矛 tr岷 c芒n th峄?c么ng.


### 3. L峄梚 Overflow Page c峄 `tbl_SentLog`
- B岷g nh岷璽 k媒 g峄璱 m谩y `tbl_SentLog` (ch峄゛ d峄?li峄噓 l峄媍h s峄?quan tr峄峮g nh岷 c峄 ph芒n h峄?膽i峄乽 ph峄慽 m谩y) kh么ng th峄?tr铆ch xu岷 t峄?膽峄檔g do l峄梚 trang d峄?li峄噓 Microsoft Access.
- C岷 ch岷 quy tr矛nh Compact & Repair tr锚n MS Access 膽峄?ph峄 h峄搃 d峄?li峄噓 tr瓢峄沜 khi di tr煤 ch铆nh th峄ヽ.

---

## Ph岷 h峄搃 v脿 X谩c nh岷璶 c峄 Ng瓢峄漣 d霉ng
Ng瓢峄漣 d霉ng 膽茫 x谩c nh岷璶 c谩c th么ng tin nghi峄噋 v峄?v脿 k峄?thu岷璽 c峄憈 l玫i:
1. **H峄?th峄憂g pha m脿u t峄?膽峄檔g & 膼峄媙h v峄?Web App:** Nh脿 m谩y nhu峄檓 膽ai s峄?d峄g h峄?th峄憂g pha m脿u t峄?膽峄檔g. D峄?li峄噓 m脿u s岷痗/s岷 ph岷﹎ n岷眒 tr锚n h峄?th峄憂g **MES**. 峄╪g d峄g Web m峄沬 膽贸ng vai tr貌 **c岷 n峄慽 trung gian (Connector)** li锚n k岷縯 MES v脿 h峄?nhu峄檓 t峄?膽峄檔g.
2. **K铆ch in tem & K岷縯 n峄慽:** S峄?d峄g d貌ng m谩y in TSC TE200 (ho岷穋 t瓢啤ng th铆ch), h峄?tr峄?k岷縯 n峄慽 USB m谩y tr岷 ho岷穋 qua m岷g LAN. **Cho ph茅p ng瓢峄漣 d霉ng t峄?膽i峄乽 ch峄塶h k铆ch th瓢峄沜 nh茫n tem in (Label Size) tr锚n giao di峄噉 Web.**
3. **Ch岷 l瓢峄g d峄?li峄噓 & Logic:** 膼峄搉g 媒 s峄璦 l峄嘽h c峄檛 cho 膽煤ng logic nghi峄噋 v峄? Ch岷 nh岷璶 cho l岷璸 tr矛nh vi锚n t峄?ki峄僲 tra m茫 ngu峄搉 VBA 膽峄?t峄?b峄?sung c岷 tr煤c c谩c b岷g b峄?thi岷縰 cho h峄 logic.
4. **Stack c么ng ngh峄?** Ph锚 duy峄噒 stack **Laravel + PostgreSQL + Vue.js + Local Agent .NET**.

---

## C谩c C芒u h峄廼 C貌n m峄?(Open Questions)
*Chi ti岷縯 xem t岷 [open-questions.md](file:///F:/DF/.claude/open-questions.md)*
1. Quy 膽峄媙h dung sai c芒n b峄檛 m脿u v脿 h贸a ch岷 ph峄?tr峄?(CH-BUS-002).
2. Giao th峄ヽ Serial k岷縯 n峄慽 c芒n 膽i峄噉 t峄?t岷 c谩c m谩y tr岷 (CH-TECH-002).
3. Giao th峄ヽ t铆ch h峄 c峄 h峄?pha m脿u t峄?膽峄檔g h岷?ngu峄搉 v峄沬 database th么ng qua b岷g `tbl_status` (CH-TECH-001).

---

## Nh岷璽 k媒 C岷璸 nh岷璽 (15/07/2026) - Ho脿n th脿nh Phase 1-3 & K铆ch ho岷 Phase 4
1. **Ho脿n th脿nh N峄乶 t岷g (Phase 1):** Kh峄焛 t岷 th脿nh c么ng c谩c repo trong `backend`, `frontend`, v脿 `agent`. Thi岷縯 l岷璸 Docker Compose Postgres ch岷 tr锚n c峄昻g 5433 (膽峄?tr谩nh xung 膽峄檛 d峄媍h v峄?Postgres c峄 b峄?tr锚n Windows tr岷).
2. **Ho脿n th脿nh Database (Phase 2):** Import th脿nh c么ng v脿 validation 膽峄慽 so谩t kh峄沺 100% d峄?li峄噓 l峄媍h s峄?c芒n thu峄慶 nhu峄檓 (140,660 d貌ng) v脿 h贸a ch岷 (5,061 d貌ng).
3. **Ho脿n th脿nh Auth/RBAC/Audit (Phase 3):** T铆ch h峄 Laravel Sanctum, m茫 h贸a m岷璽 kh岷﹗ admin/admin123 b岷眓g BCrypt. Vi岷縯 CheckRole middleware, thi岷縯 l岷璸 Audit Log Service t峄?膽峄檔g l瓢u v岷縯 JSONB th么. C峄昻g ch岷 Frontend 膽瓢峄 thi岷縯 l岷璸 t岷 c峄昻g `3001` (d霉ng Vite 5 tr谩nh l峄梚 Rolldown tr锚n Node v24).
4. **T谩i c岷 tr煤c 14 Phase:** D峄盿 tr锚n t脿i li峄噓 `phase.docx`, l峄?tr矛nh tri峄僴 khai 膽茫 膽瓢峄 膽i峄乽 ch峄塶h t峄?12 phase th脿nh 14 phase, b峄?sung chi ti岷縯 **Phase 8: V岷璶 chuy峄僴** v脿 **Phase 9: C岷 m谩y**.

---

## Nh岷璽 k媒 Phi锚n (Giai 膽o岷 ti岷縫 theo - 15/07/2026)
### 1. Ph芒n t铆ch & B峄?sung CSDL `chem_order.accdb`
- **膼峄媙h v峄?t峄噋 tin:** 膼茫 膽峄媙h v峄?th脿nh c么ng CSDL Access `chem_order.accdb` t岷 膽瓢峄漬g d岷玭 th峄眂 t岷?tr锚n h峄?th峄憂g: `C:\Users\V170192\OneDrive\Desktop\DF\database\chem_order\chem_order.accdb`.
- **Th峄憂g k锚 d峄?li峄噓:**
  - B岷g `tbl_status`: 40 d貌ng (ch峄゛ c岷 h矛nh n岷 van/k锚nh h贸a ch岷 t峄?膽峄檔g).
  - B岷g `tblRECORD`: 47,381 d貌ng (ch峄゛ c岷?d峄?li峄噓 c芒n thu峄慶 nhu峄檓 v脿 h贸a ch岷 theo d岷g header + detail).
  - B岷g `tblRECORD_chem`: 1,500 d貌ng (ch峄?ch峄゛ c谩c d貌ng ti锚u 膽峄?header cho c谩c l么 h贸a ch岷, kh么ng c贸 chi ti岷縯 kh峄慽 l瓢峄g hay dyecode).
- **膼峄慽 so谩t di tr煤:**
  - Wrote and ran comparisons showing that **all 47,381 rows of `tblRECORD`, all 1,500 rows of `tblRECORD_chem`, and all 40 rows of `tbl_status`** from `chem_order.accdb` are **100% matched and already successfully imported/migrated** into the PostgreSQL database.
  - No new/missing records were found in this database compared to the legacy Postgres staging schemas (`legacy_df_scale`), indicating migration for this source is complete.

### 2. Tri峄僴 khai & Ho脿n th脿nh Phase 4 (Master Data & Formula)
- **M峄 ti锚u:** S峄?h贸a danh m峄 thi岷縯 b峄?v岷璽 t瓢, c岷 h矛nh h峄?s峄?n瓢峄沜, v脿 logic t铆nh to谩n 膽峄媙h l瓢峄g c么ng th峄ヽ nhu峄檓 (Water & Weight Calculation).
- **K岷縯 qu岷?**
  - Thi岷縯 k岷?v脿 ch岷 di tr煤 th脿nh c么ng c谩c b岷g: `materials`, `water_configs`, `recipes`, `recipe_versions`, `recipe_materials` v脿 `process_parameters` v脿o PostgreSQL.
  - S峄?h贸a v脿 kh峄焛 t岷 (seed) **439 danh m峄 v岷璽 t瓢** v脿 **40 ma tr岷璶 h峄?s峄?n瓢峄沜** tr峄眂 ti岷縫 t峄?Excel.
  - L岷璸 tr矛nh `FormulaCalculationService` x峄?l媒 膽煤ng logic t铆nh n瓢峄沜, t峄?膽峄檔g tr铆ch xu岷 c么ng 膽o岷, v脿 l脿m tr貌n tr峄峮g l瓢峄g b峄檛 m脿u (Precision Rounding $\le 1\%$).
  - Vi岷縯 giao di峄噉 Vue.js ho脿n ch峄塶h g峄搈: Danh m峄 v岷璽 t瓢, C岷 h矛nh ma tr岷璶 n瓢峄沜, v脿 So岷 th岷 c么ng th峄ヽ 膽铆nh k猫m **Tr矛nh gi岷?l岷璸 t铆nh to谩n th峄漣 gian th峄眂 (Simulator)**.
  - V瓢峄 qua ki峄僲 th峄?**Unit Test** v脿 **Golden Master Test 50/50 m岷?m岷玼** kh峄沺 ho脿n to脿n 100% v峄沬 Excel VBA (sai s峄?= 0).
  - Kh么ng tri峄僴 khai quy tr矛nh duy峄噒 c么ng th峄ヽ ph峄ヽ t岷 theo y锚u c岷 tr峄眂 ti岷縫 t峄?ng瓢峄漣 d霉ng (c么ng th峄ヽ t岷 m峄沬 峄?tr岷g th谩i `ACTIVE` d霉ng 膽瓢峄 ngay).

---

### 3. Tri峄僴 khai & Ho脿n th脿nh Phase 5 (L峄噉h s岷 xu岷 & 膼i峄乽 ph峄慽 m谩y)
- **M峄 ti锚u:** Qu岷 l媒 l峄噉h s岷 xu岷 v脿 h脿ng ch峄?膽i峄乽 ph峄慽 g峄璱 l峄噉h n岷 van h贸a ch岷 t峄?膽峄檔g c贸 c啤 ch岷?kh贸a logic ch峄憂g tranh ch岷 (Claim Lock).
- **K岷縯 qu岷?**
  - Vi岷縯 `ProductionBatchController` v脿 `MachineDispatchController` ho脿n t岷 c谩c endpoints h峄?tr峄?l峄峜, di chuy峄僴 tr岷g th谩i l么, claim/release lock, v脿 gi岷?l岷璸 ph谩t l峄噉h g峄璱 m谩y nhu峄檓.
  - T铆ch h峄 c啤 ch岷?t峄?sinh UUID 峄?t岷g Eloquent (`creating` boot event) cho c岷?`ProductionBatch` v脿 `MachineDispatch`.
  - Tri峄僴 khai thu岷璽 to谩n t铆nh lock age s峄?d峄g tr峄?tuy峄噒 膽峄慽 `abs()` 膽峄?tr谩nh l峄梚 l峄嘽h d岷 th峄漣 gian khi Carbon so s谩nh.
  - Vi岷縯 giao di峄噉 Vue.js ho脿n t岷 g峄搈 m脿n h矛nh **L么 s岷 xu岷 (bao g峄搈 MES Mock Tool)** v脿 **膼i峄乽 ph峄慽 m谩y nhu峄檓 (hi峄僴 th峄?timer 膽岷縨 ng瓢峄 5 ph煤t, c瓢峄沺 kh贸a khi h岷縯 h岷 v脿 n煤t ph谩t l峄噉h)**.
  - V瓢峄 qua ki峄僲 th峄?**Integration Test (14 assertions)** x谩c minh 膽岷 膽峄?t铆nh 膽煤ng 膽岷痭 c峄 lu峄搉g kh贸a tranh ch岷, t峄?gi岷 ph贸ng khi g峄璱 m谩y, c瓢峄沺 kh贸a khi h岷縯 h岷, v脿 ch岷穘 g峄璱 m谩y khi m岷 kh贸a.

---

## Nh岷璽 k媒 Phi锚n (Giai 膽o岷 ti岷縫 theo - 16/07/2026)
### 4. Tri峄僴 khai & Ho脿n th脿nh Phase 6 (Module C芒n s岷 xu岷 & Local Scale Agent)
- **M峄 ti锚u:** Nh岷璶 gi谩 tr峄?c芒n 膽i峄噉 t峄?qua cache-based live weight streaming v脿 l瓢u l峄媍h s峄?c芒n ho脿n th脿nh.
- **K岷縯 qu岷?**
  - Vi岷縯 c谩c API l瓢u/l岷 s峄?c芒n th么 th峄漣 gian th峄眂 qua Cache v脿 l瓢u tr峄?k岷縯 qu岷?c芒n ho脿n t岷 (`app.scale_measurements`).
  - Drop th脿nh c么ng constraint `NOT NULL` cho `legacy_id` v脿 `legacy_source` tr锚n c峄檛 `app.scale_measurements` 膽峄?t瓢啤ng th铆ch lu峄搉g ghi nh岷璶 tr峄眂 ti岷縫 t峄?web.
  - N芒ng c岷 giao di峄噉 Vue.js **Tr岷 c芒n (WeighingStation.vue)** v峄沬 b岷g hi峄僴 th峄?LED ph谩t s谩ng, t峄?膽峄檔g 膽峄慽 so谩t dung sai sai s峄?$\le 1\%$ v脿 t铆ch h峄 Manual Slider 膽峄?ki峄僲 th峄?
  - V瓢峄 qua ki峄僲 th峄?**Integration Test (12 assertions)** c峄 `ScaleLiveWeightTest`.

### 5. Tri峄僴 khai & Ho脿n th脿nh Phase 7 (Quy tr矛nh 膽贸ng tem & In 岷)
- **M峄 ti锚u:** Qu岷 l媒 h脿ng ch峄?in tem nh茫n m岷?c芒n ho脿n th脿nh, sinh l峄噉h in TSPL 膽峄檔g h峄?tr峄?t霉y bi岷縩 k铆ch th瓢峄沜 nh茫n.
- **K岷縯 qu岷?**
  - Thi岷縯 k岷?v脿 ch岷 di tr煤 th脿nh c么ng b岷g `app.print_jobs` l瓢u tr峄?c谩c l峄噉h in.
  - L岷璸 tr矛nh `PrintJobController` sinh l峄噉h in chu岷﹏ **TSPL** (t瓢啤ng th铆ch m谩y in TSC TE200) ch峄゛ th么ng s峄?m岷?nhu峄檓, m茫 QR Code v脿 ch岷 nh岷璶 tham s峄?t霉y bi岷縩 k铆ch th瓢峄沜 nh茫n (`width` x `height`).
  - C岷璸 nh岷璽 `AgentJobsController` 膽峄?tr岷?v峄?c谩c l峄噉h in `PENDING` th峄眂 t岷?cho Local Agent v脿 ghi nh岷璶 x谩c nh岷璶 `ack` chuy峄僴 status sang `SUCCESS`.
  - T铆ch h峄 khung **馃彿锔?C岷 h矛nh In tem nh茫n** v脿 n煤t **In Tem Nh茫n M岷?* ngay tr锚n m脿n h矛nh Tr岷 c芒n Vue.js.
  - V瓢峄 qua ki峄僲 th峄?**Integration Test (15 assertions)** c峄 `PrintJobPipelineTest` kh峄沺 100% l峄噉h in TSPL.

---

## Nh岷璽 k媒 Phi锚n (Giai 膽o岷 ti岷縫 theo - 16/07/2026)
### 6. Tri峄僴 khai & Ho脿n th脿nh Phase 8 (V岷璶 chuy峄僴 v脿 X谩c nh岷璶 t峄沬 th霉ng)
- **M峄 ti锚u:** L瓢u v岷縯 h脿nh tr矛nh di chuy峄僴 nguy锚n li峄噓 t峄?c芒n t峄沬 m谩y nhu峄檓, t峄?膽峄檔g c岷h b谩o khi qu谩 SLA 膽峄媙h m峄ヽ v脿 qu茅t QR x谩c th峄眂 t岷 th霉ng.
- **K岷縯 qu岷?**
  - Thi岷縯 k岷?v脿 ch岷 di tr煤 th脿nh c么ng c谩c b岷g `app.material_transports` v脿 `app.material_transport_events` l瓢u l峄媍h s峄?di chuy峄僴 v脿 tr岷g th谩i.
  - L岷璸 tr矛nh `MaterialTransportController` t铆nh to谩n SLA 膽峄檔g theo nh贸m m谩y (m谩y th瓢峄漬g 15 ph煤t, c峄 th霉ng 25 ph煤t), x谩c nh岷璶 膽岷縩 th霉ng b岷眓g qu茅t QR d谩n th霉ng, t峄?膽峄檔g b岷痶 bu峄檆 nh岷璸 l媒 do tr峄?h岷 n岷縰 v瓢峄 SLA v脿 c岷璸 nh岷璽 tr岷g th谩i m岷?c芒n sang `WEIGHED`.
  - T岷 m峄沬 m脿n h矛nh **V岷璶 chuy峄僴 (MaterialTransfer.vue)** hi峄僴 th峄?danh s谩ch c谩c m岷?膽ang 膽i k猫m b峄?膽岷縨 ph煤t th峄眂 t岷?v脿 giao di峄噉 qu茅t m茫 QR/nh岷璸 l媒 do tr峄?h岷.
  - V瓢峄 qua ki峄僲 th峄?**Integration Test (15 assertions)** c峄 `MaterialTransferTest` th脿nh c么ng 100%.

---

## Nh岷璽 k媒 Phi锚n (Giai 膽o岷 ti岷縫 theo - 16/07/2026)
### 7. Tri峄僴 khai & Ho脿n th脿nh Phase 9 (S岷祅 s脿ng c岷 v脿 Gi谩m s谩t c岷 v脿o m谩y)
- **M峄 ti锚u:** Ki峄僲 so谩t 膽i峄乽 ki峄噉 膽峄?n瓢峄沜, 膽峄?nguy锚n li峄噓 v脿 ghi nh岷璶 n岷 h贸a ch岷 an to脿n c贸 c啤 ch岷?gi谩m s谩t override.
- **K岷縯 qu岷?**
  - Thi岷縯 k岷?v脿 ch岷 di tr煤 th脿nh c么ng b岷g `app.feed_operations` l瓢u v岷縯 ti岷縩 tr矛nh n岷 van c岷.
  - L岷璸 tr矛nh `FeedOperationController` ki峄僲 tra 膽i峄乽 ki峄噉 膽峄?n瓢峄沜, qu茅t nh茫n tem QR x谩c th峄眂 膽煤ng m岷?nhu峄檓, cho ph茅p Supervisor k媒 duy峄噒 Override c贸 l瓢u Audit Log an to脿n v脿 ho脿n t岷 c岷 m谩y 膽峄昳 tr岷g th谩i m岷?sang `DONE`.
  - Thi岷縯 k岷?m脿n h矛nh **C岷 m谩y (FeedingMonitor.vue)** hi峄僴 th峄?checklist 3 b瓢峄沜 c岷 m谩y tr峄眂 quan, t铆ch h峄 b峄?gi岷?l岷璸 qu茅t QR 膽峄慽 so谩t v脿 form override c峄 Supervisor.
  - V瓢峄 qua ki峄僲 th峄?**Integration Test (23 assertions)** c峄 `FeedReadinessTest` th脿nh c么ng 100% (bao g峄搈 c岷?lu峄搉g n岷 th么ng th瓢峄漬g v脿 lu峄搉g bypass override ghi Audit Log).

---

## Nh岷璽 k媒 Phi锚n (Giai 膽o岷 ti岷縫 theo - 16/07/2026)
### 8. Tri峄僴 khai & Ho脿n th脿nh Phase 10 (Troubleshooting - H峄?tr峄?S峄?c峄?
- **M峄 ti锚u:** Chuy峄僴 膽峄昳 b峄?tri th峄ヽ s峄?c峄?Excel VBA c农 sang c么ng c峄?ch岷﹏ 膽o谩n l峄梚 tr锚n 峄﹏g d峄g web s峄?d峄g thu岷璽 to谩n suy lu岷璶 ch岷 膽i峄僲 nguy锚n nh芒n l峄梚.
- **K岷縯 qu岷?**
  - Thi岷縯 k岷?v脿 ch岷 di tr煤 c谩c b岷g tri th峄ヽ: `app.problems`, `app.causes`, `app.problem_cause_rules`, `app.processes`, `app.parameters`, `app.troubleshooting_cases`, `app.case_evidences`, `app.case_recommendations`.
  - L岷璸 tr矛nh `TroubleshootingController` v脿 `InferenceService` sao ch茅p ch铆nh x谩c 100% thu岷璽 to谩n suy lu岷璶 `modInferenceEngine` c峄 VBA, x岷縫 h岷g nguy锚n nh芒n l峄梚 v脿 ghi nh岷璶 case s峄?c峄?
  - T铆ch h峄 giao di峄噉 ch岷﹏ 膽o谩n t瓢啤ng t谩c `Troubleshooting.vue` v脿 v瓢峄 qua ki峄僲 th峄?t铆ch h峄 `TroubleshootingInferenceTest.php`.

---

## Nh岷璽 k媒 Phi锚n (Giai 膽o岷 ti岷縫 theo - 16/07/2026)
### 9. Tri峄僴 khai & Ho脿n th脿nh Dashboard Realtime & Rule Engine C岷h b谩o (Nhi峄噈 v峄?b峄?sung)
- **M峄 ti锚u:** X芒y d峄眓g Dashboard gi谩m s谩t tr峄眂 quan th峄漣 gian th峄眂 (SSE) v脿 b峄?m谩y ch岷﹏ 膽o谩n C岷h b谩o tr峄?h岷 s岷 xu岷/m岷 k岷縯 n峄慽 Agent.
- **K岷縯 qu岷?**
  - Thi岷縯 k岷?v脿 ch岷 di tr煤 th脿nh c么ng b岷g Outbox `app.realtime_events`, b岷g c岷 h矛nh rule `app.alert_rules` v脿 nh岷璽 k媒 c岷h b谩o `app.alerts`.
  - L岷璸 tr矛nh `RealtimeController` thi岷縯 l岷璸 c峄昻g truy峄乶 Server-Sent Events (SSE) an to脿n, t铆ch h峄 c啤 ch岷?manual token validation v脿 telemetry live scale cache streaming.
  - X芒y d峄眓g `RealtimeService` 膽峄?publish s峄?ki峄噉 giao d峄媍h tin c岷瓂 v脿 ch岷 Rule Engine qu茅t tr峄?h岷 (`WEIGH_START_DELAY`, `TRANS_SLA_BREACH`, `SCALE_AGENT_OFFLINE`...).
  - Thi岷縯 l岷璸 th瓢 vi峄噉 Realtime Client `realtime.ts` ph铆a Frontend t峄?膽峄檔g x峄?l媒 reconnect backoff v脿 fallback polling 10s khi m岷 k岷縯 n峄慽 m岷g.
  - Thi岷縯 k岷?l岷 100% giao di峄噉 `Dashboard.vue` th脿nh Trung t芒m 膼i峄乽 ph峄慽 h峄 nh岷 5 Tab (Overview, Weighing, Dyeing, Alerts, Management KPIs) v脿 Timeline Milestones Dialog.
  - V瓢峄 qua b峄?Integration Test `RealtimeDashboardTest.php` v峄沬 28 assertions th脿nh c么ng 100%, ch岷 `npm run build` bi锚n d峄媍h s岷h s岷?

---

## Nh岷璽 k媒 Phi锚n (Giai 膽o岷 ti岷縫 theo - 16/07/2026)

### 10. Tri峄僴 khai & Ho脿n th脿nh Phase 11 (B谩o c谩o & Ph芒n t铆ch)
- **M峄 ti锚u:** X芒y d峄眓g b谩o c谩o ti锚u hao thu峄慶 nhu峄檓/h贸a ch岷 th峄眂 t岷?vs 膽峄媙h m峄ヽ, sai s峄?dung sai & t峄?l峄?override, s岷 l瓢峄g m谩y nhu峄檓 theo ng脿y/th谩ng/ca, Pareto nguy锚n nh芒n s峄?c峄? xu岷 Excel/PDF v脿 Audit Log Explorer theo 膽煤ng m峄 ti锚u Phase 11 trong `CLAUDE.md`.
- **R脿 so谩t tr瓢峄沜 khi code ph谩t hi峄噉 l峄?h峄昻g d峄?li峄噓:** `WeighingJobController::weighItem` ch岷 nh岷璶 `override_approved`/`override_reason` t峄?Frontend (`WeighingStation.vue` 膽茫 c贸 UI override s岷祅) nh瓢ng **kh么ng l瓢u v岷縯** v脿o DB v脿 **kh么ng ghi Audit Log**, kh谩c v峄沬 `FeedOperationController` 膽茫 l脿m 膽煤ng. N岷縰 kh么ng s峄璦, b谩o c谩o t峄?l峄?override s岷?kh么ng c贸 s峄?li峄噓 th岷璽. 膼茫 xin x谩c nh岷璶 ng瓢峄漣 d霉ng v脿 膽瓢峄 膽峄搉g 媒 s峄璦 trong 膽峄 n脿y.
- **K岷縯 qu岷?**
  1. **V谩 l峄?h峄昻g Override dung sai c芒n:** Th锚m migration `2026_07_16_000006_add_override_columns_to_weighing_job_items` (c峄檛 `override_approved`, `override_reason`, `override_by`). C岷璸 nh岷璽 `WeighingJobController::weighItem` b岷痶 bu峄檆 vai tr貌 SUPERVISOR/ADMIN khi override, y锚u c岷 l媒 do t峄慽 thi峄僽 5 k媒 t峄? l瓢u v岷縯 v脿o `weighing_job_items` v脿 ghi Audit Log b岷 bi岷縩 `WEIGH_TOLERANCE_OVERRIDE` (膽峄搉g nh岷 pattern v峄沬 `FeedOperationController::override`).
  2. **C脿i 膽岷穞 th瓢 vi峄噉 xu岷 b谩o c谩o:** `composer require maatwebsite/excel barryvdh/laravel-dompdf` (膽茫 ghim version `^3.1`).
  3. **`ReportController`** v峄沬 4 b谩o c谩o (`GET /api/reports/dye-consumption`, `/tolerance-stats`, `/machine-output`, `/troubleshooting-pareto`), m峄梚 b谩o c谩o h峄?tr峄?l峄峜 theo kho岷g ng脿y (`from`/`to`, m岷穋 膽峄媙h 30 ng脿y g岷 nh岷), v脿 tham s峄?`format=xlsx|pdf` 膽峄?xu岷 file qua `app/Exports/ArrayExport.php` v脿 view `resources/views/reports/pdf.blade.php`.
     - B谩o c谩o s岷 l瓢峄g h峄?tr峄?nh贸m theo **ca k铆p** b岷眓g c谩ch suy lu岷璶 t峄?gi峄?trong ng脿y theo m岷玼 3 ca 8h ph峄?bi岷縩 c峄 nh脿 m谩y (06h-14h / 14h-22h / 22h-06h) 鈥?膽芒y l脿 **gi岷?膽峄媙h t脿i li峄噓 h贸a r玫 trong code**, kh么ng ph岷 quy t岷痗 nghi峄噋 v峄?膽茫 x谩c nh岷璶, v矛 kh么ng c贸 c峄檛 "ca" trong d峄?li峄噓 ngu峄搉 (xem `open-questions.md` CH-BUS-002/CH-TECH-001).
  4. **Audit Log Explorer:** `GET /api/audit-logs` (ph芒n trang, l峄峜 theo user/action/entity_type/kho岷g th峄漣 gian) v脿 `GET /api/audit-logs/filters` (danh s谩ch action/entity_type 膽峄?膽峄?v脿o dropdown).
  5. **Frontend:** `Reports.vue` (4 tab: Ti锚u hao, Dung sai & Override, S岷 l瓢峄g, Pareto S峄?c峄?鈥?d霉ng chung 2 component bi峄僽 膽峄?SVG t峄?vi岷縯 `SimpleBarChart.vue`/`ParetoChart.vue`, tu芒n th峄?nguy锚n t岷痗 "one axis" cho Pareto b岷眓g c谩ch v岷?c峄檛 theo % thay v矛 tr峄 k茅p) v脿 `AuditLogExplorer.vue` (b岷g c贸 th峄?m峄?r峄檔g xem `before_data`/`after_data` JSON). Th锚m route `/reports`, `/audit-logs` v脿 m峄 menu m峄沬 trong nh贸m "B脕O C脕O & S峄?C峄?.
  6. **Ki峄僲 th峄?** `ReportsTest.php` m峄沬 (9 test, 45 assertions) 鈥?t峄昻g h峄 ti锚u hao 膽煤ng, t峄?l峄?override 膽煤ng, ch岷穘 override khi kh么ng ph岷 Supervisor (403), l瓢u 膽煤ng l媒 do/ng瓢峄漣 duy峄噒 + Audit Log, 膽岷縨 m岷?膽ang ch峄?x峄?l媒 ngo脿i dung sai, s岷 l瓢峄g theo ng脿y, Pareto t铆ch l农y 膽煤ng %, l峄峜 Audit Log theo action, ch岷穘 truy c岷璸 ch瓢a 膽膬ng nh岷璸 (401), xu岷 Excel t岷 file th脿nh c么ng. To脿n b峄?**28 test backend (216 assertions)** pass, `npx vue-tsc --noEmit` s岷h, `npm run build` bi锚n d峄媍h th脿nh c么ng.
- **Ph谩t hi峄噉 m么i tr瓢峄漬g (b谩o c谩o, 膽茫 x峄?l媒 峄?m峄 11 d瓢峄沬 膽芒y):** Database dev c峄 b峄?(`df-postgres`, DB `production_web`) hi峄噉 thi岷縰 b岷g `public.personal_access_tokens` d霉 d貌ng migration `2026_07_15_150959_create_personal_access_tokens_table` 膽茫 膽瓢峄 膽谩nh d岷 l脿 膽茫 ch岷 trong b岷g `migrations` 鈥?khi岷縩 `POST /api/auth/login` tr岷?l峄梚 500 khi th峄?膽膬ng nh岷璸 tr峄眂 ti岷縫 tr锚n DB dev n脿y (ph谩t hi峄噉 khi c峄?g岷痭g ki峄僲 th峄?th峄?c么ng qua tr矛nh duy峄噒/curl; kh么ng 岷h h瓢峄焠g b峄?test t峄?膽峄檔g v矛 test d霉ng `Sanctum::actingAs()` gi岷?l岷璸 x谩c th峄眂, kh么ng 膽i qua `createToken()` th岷璽).

---

## Nh岷璽 k媒 Phi锚n (Giai 膽o岷 ti岷縫 theo - 16/07/2026)

### 11. Kh岷痗 ph峄 d峄﹖ 膽i峄僲 l峄梚 膽膬ng nh岷璸 500 tr锚n DB dev (thi岷縰 b岷g `personal_access_tokens`)
- **M峄 ti锚u:** X谩c 膽峄媙h nguy锚n nh芒n g峄慶 v脿 kh么i ph峄 `/api/auth/login` tr锚n DB dev `production_web`, kh么ng m岷 d峄?li峄噓, kh么ng ch峄?t岷 b岷g ch峄痑 ch谩y th峄?c么ng.

#### Ch岷﹏ 膽o谩n (tr瓢峄沜 khi thay 膽峄昳 b岷 k峄?th峄?g矛)
- `.env`: `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_PORT=5433`, `DB_DATABASE=production_web` 鈥?x谩c nh岷璶 qua `php artisan about` (driver `pgsql`) v脿 `docker exec df-postgres psql -c "SELECT current_database();"` 鈫?c霉ng m峄檛 database (`production_web`), lo岷 tr峄?kh岷?n膬ng "hai database kh谩c nhau" (nguy锚n nh芒n #3 trong danh s谩ch nghi v岷).
- `SHOW search_path` 鈫?`"$user", public`; Laravel t峄?茅p `search_path=public` qua `config/database.php`. Qu茅t to脿n b峄?schema (`information_schema.tables WHERE table_name='personal_access_tokens'`) 鈫?**0 d貌ng 峄?b岷 k峄?schema n脿o** 鈫?b岷g th峄眂 s峄?kh么ng t峄搉 t岷, kh么ng ph岷 "n岷眒 nh岷 schema `app`" (lo岷 tr峄?nguy锚n nh芒n #4, #8).
- `php artisan migrate:status` + query tr峄眂 ti岷縫 b岷g `migrations` 鈫?d貌ng `2026_07_15_150959_create_personal_access_tokens_table` **batch 1, 膽茫 "Ran"**, nh瓢ng b岷g kh么ng t峄搉 t岷 鈫?x谩c nh岷璶 膽煤ng hi峄噉 t瓢峄g ng瓢峄漣 d霉ng m么 t岷?
- `docker volume inspect df_pgdata` (t岷 `2026-07-15T14:28:03Z`) v脿 `docker inspect df-postgres --format='{{.Created}}'` (`2026-07-15T15:04:02Z`) 鈫?**volume/container li锚n t峄 t峄?l煤c kh峄焛 t岷 d峄?谩n, kh么ng c贸 d岷 hi峄噓 b峄?wipe/t谩i t岷** 鈫?lo岷 tr峄?nguy锚n nh芒n #5 (docker volume c农/kh么ng 膽峄搉g b峄?.
- Kh么ng c贸 `bootstrap/cache/config.php` (ch峄?c贸 `packages.php`, `services.php` t峄?`package:discover`) v脿 `php artisan config:show database.connections.pgsql.database` tr岷?膽煤ng `.env` 鈫?lo岷 tr峄?nguy锚n nh芒n #7 (config cache c农).
- Kh么ng c贸 `.env.testing`; `phpunit.xml` kh么ng override `DB_CONNECTION`/`DB_DATABASE` (ch峄?comment s岷祅 d貌ng SQLite, ch瓢a b岷璽) 鈫?**b峄?test c农ng ch岷 tr锚n c霉ng DB `production_web`** qua `DatabaseTransactions` (rollback sau m峄梚 test). 膼芒y l脿 l媒 do 28 test tr瓢峄沜 膽贸 pass 100% m脿 kh么ng ph谩t hi峄噉 l峄梚: `Sanctum::actingAs()`/`actingAs()` g谩n th岷硁g user 膽茫 x谩c th峄眂 v脿o request, **kh么ng g峄峣 `createToken()` th岷璽**, n锚n kh么ng bao gi峄?膽峄g t峄沬 b岷g `personal_access_tokens`.

#### Nguy锚n nh芒n g峄慶 (c贸 b岷眓g ch峄﹏g)
- File migration `2026_07_15_150959_create_personal_access_tokens_table.php` **l脿 b岷 sao y nguy锚n stub m岷穋 膽峄媙h c峄 Sanctum** (`vendor/laravel/sanctum/database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php`), d霉ng `$table->morphs('tokenable')` 鈫?c峄檛 `tokenable_id` ki峄僽 **bigint**.
- `App\Models\User` (b岷g `app.users`) d霉ng **UUID** l脿m kh贸a ch铆nh (`$keyType='string'`, `$incrementing=false`) 鈥?kh么ng t瓢啤ng th铆ch v峄沬 `tokenable_id` ki峄僽 bigint.
- `storage/logs/laravel.log` c貌n l瓢u v岷縯 l峄梚 c农 膽煤ng nh瓢 d峄?膽o谩n: `SQLSTATE[22P02]: invalid input syntax for type bigint: "a1111111-1111-1111-1111-111111111111"` 鈥?kh峄沺 v峄沬 ghi ch煤 Phase 3 trong log n脿y ("S峄璦 膽峄昳 b岷g Sanctum tokenable_id t瓢啤ng th铆ch UUID").
- K岷縯 lu岷璶: ai 膽贸 膽茫 **s峄璦/x贸a b岷g th峄?c么ng ngo脿i h峄?th峄憂g migration** (kh么ng qua `php artisan migrate:rollback`) 膽峄?kh岷痗 ph峄 l峄梚 ki峄僽 d峄?li峄噓, nh瓢ng b瓢峄沜 t岷 l岷 b岷g v峄沬 `tokenable_id` ki峄僽 UUID ch瓢a t峄玭g 膽瓢峄 l瓢u th脿nh migration 鈥?b岷g b峄?m岷 h岷硁 trong khi b岷g `migrations` v岷玭 ghi nh岷璶 migration g峄慶 (ki峄僽 bigint sai) l脿 膽茫 ch岷. 膼芒y l脿 **l峄梚 tr么i d岷 gi峄痑 migration tracking v脿 schema th峄眂 t岷?(migration drift)**, kh么ng ph岷 do database kh谩c, cache, hay volume Docker.

#### C谩ch s峄璦 (an to脿n, kh么ng 膽峄g migration c农, kh么ng m岷 d峄?li峄噓)
- Th锚m migration m峄沬 `2026_07_16_000007_restore_missing_personal_access_tokens_table.php`:
  - Ki峄僲 tra `Schema::hasTable('personal_access_tokens')` tr瓢峄沜 khi t岷 (idempotent).
  - D霉ng `$table->uuidMorphs('tokenable')` thay v矛 `morphs()` 鈫?`tokenable_id` ki峄僽 UUID, kh峄沺 `app.users.id`.
  - `down()` l脿 **no-op c贸 ch峄?膽铆ch** (kh么ng drop b岷g) k猫m docblock gi岷 th铆ch: tr谩nh tr瓢峄漬g h峄 migration n脿y rollback tr锚n m峄檛 m么i tr瓢峄漬g m脿 b岷g 膽茫 c贸 token h峄 l峄? l脿m m岷 phi锚n 膽膬ng nh岷璸 c峄 ng瓢峄漣 d霉ng th岷璽.
  - Kh么ng s峄璦 file migration g峄慶 `2026_07_15_150959_...`, kh么ng s峄璦 b岷g `migrations`, kh么ng ch岷 `migrate:fresh`/`db:wipe`/`docker compose down -v`.
- Ch岷 `php artisan optimize:clear && php artisan migrate` 鈫?migration m峄沬 ch岷 th脿nh c么ng (batch 6). Verify qua `information_schema.columns`: `tokenable_id` nay l脿 ki峄僽 `uuid`.
- 膼峄慽 chi岷縰 s峄?d貌ng `app.users` (7), `app.audit_logs` (8), `app.production_batches`, `app.weighing_job_items` tr瓢峄沜/sau 鈫?**kh么ng 膽峄昳, kh么ng m岷 d峄?li峄噓 nghi峄噋 v峄?*.

#### Smoke-test b岷眓g lu峄搉g 膽膬ng nh岷璸 th岷璽 (kh么ng d霉ng `Sanctum::actingAs()`)
- Kh峄焛 膽峄檔g `php artisan serve` tr锚n c峄昻g c么 l岷璸 (8010, kh么ng 膽峄g c谩c ti岷縩 tr矛nh `artisan serve` kh谩c 膽茫 ch岷 s岷祅 tr锚n c峄昻g 8000 c峄 m谩y dev), t岷 t脿i kho岷 test r玫 t锚n `qa_smoke_test` (role ADMIN) v脿 `qa_smoke_operator` (role OPERATOR) qua tinker.
- `curl POST /api/auth/login` v峄沬 m岷璽 kh岷﹗ 膽煤ng 鈫?**HTTP 200**, tr岷?`access_token`/`token_type`/`user` 膽煤ng c岷 tr煤c; verify tr峄眂 ti岷縫 trong Postgres c贸 d貌ng m峄沬 trong `personal_access_tokens` v峄沬 `tokenable_id` = UUID user th岷璽.
- D霉ng token v峄玜 nh岷璶 g峄峣 `GET /api/reports/dye-consumption`, `/tolerance-stats`, `/machine-output?group_by=shift`, `/troubleshooting-pareto`, `/api/audit-logs`, `/api/audit-logs/filters`, `/api/auth/me` 鈫?**to脿n b峄?HTTP 200**.
- Xu岷 th峄?Excel (`?format=xlsx`, 膽煤ng `Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`) v脿 PDF (`?format=pdf`, verify b岷眓g `file` l脿 PDF 1.7 h峄 l峄?3 trang).
- Sai m岷璽 kh岷﹗ 鈫?401 (kh么ng c貌n 500); t脿i kho岷 kh么ng t峄搉 t岷 鈫?401; thi岷縰 tr瓢峄漬g b岷痶 bu峄檆 鈫?422.
- `POST /api/auth/logout` 鈫?200, thu h峄搃 token; g峄峣 l岷 `/api/auth/me` b岷眓g token c农 tr锚n **m峄檛 ti岷縩 tr矛nh curl m峄沬 膽峄檆 l岷璸** 鈫?401 "Unauthenticated" (x谩c nh岷璶 thu h峄搃 ho岷 膽峄檔g 膽煤ng trong m么i tr瓢峄漬g th岷璽).
- **Test lu峄搉g Override dung sai v峄沬 ng瓢峄漣 d霉ng th岷璽 (kh么ng gi岷?l岷璸):** t岷 batch/machine/material/weighing-job-item t岷 qua tinker 鈫?膽膬ng nh岷璸 b岷眓g `qa_smoke_operator` (OPERATOR) g峄峣 `POST /weighing-jobs/items/{id}/weigh` v峄沬 `override_approved=true`, c芒n v瓢峄 dung sai 鈫?**403 FORBIDDEN** 膽煤ng nh瓢 thi岷縯 k岷?鈫?膽膬ng nh岷璸 l岷 b岷眓g `qa_smoke_test` (ADMIN) g峄峣 l岷 c霉ng request 鈫?**200 SUCCESS**, response tr岷?v峄?`override_approved:true`, `override_reason`, `override_by` 膽煤ng d峄?li峄噓 膽茫 nh岷璸; verify `GET /api/audit-logs?action=WEIGH_TOLERANCE_OVERRIDE` c贸 b岷 ghi m峄沬, v脿 `GET /api/reports/tolerance-stats` ph岷 谩nh 膽煤ng s峄?li峄噓 override th岷璽 (`override_rate_pct: 100` cho v岷璽 t瓢 test). Sau 膽贸 d峄峮 s岷h batch/job/item/machine/material gi岷?l岷璸 (kh么ng x贸a b岷 ghi Audit Log v矛 nguy锚n t岷痗 b岷 bi岷縩); d峄玭g server verification c么 l岷璸.
- T脿i kho岷 `qa_smoke_test`/`qa_smoke_operator` 膽瓢峄 **gi峄?l岷** trong DB dev (kh么ng x贸a) 膽峄?ng瓢峄漣 d霉ng c贸 th峄?t峄?膽膬ng nh岷璸 ki峄僲 tra giao di峄噉 Phase 11 tr锚n tr矛nh duy峄噒 th岷璽 (c么ng c峄?hi峄噉 c贸 kh么ng c贸 kh岷?n膬ng 膽i峄乽 khi峄僴 tr矛nh duy峄噒 膽峄?t峄?ch峄 m脿n h矛nh x谩c minh UI).

#### Ki峄僲 th峄?ch峄憂g t谩i di峄卬
- Th锚m `tests/Feature/AuthenticationFlowTest.php` (6 test, 20 assertions) 鈥?c峄?t矛nh 膽i qua **API 膽膬ng nh岷璸 th岷璽** (`postJson('/api/auth/login')`) v脿 token Sanctum th岷璽, kh么ng d霉ng `Sanctum::actingAs()`/`actingAs()`:
  1. `personal_access_tokens` t峄搉 t岷 v脿 `tokenable_id` 膽煤ng ki峄僽 `uuid` (migration-schema test theo 膽煤ng y锚u c岷 d峄?ph貌ng).
  2. 膼膬ng nh岷璸 th岷璽 t岷 token v脿 l瓢u 膽煤ng v脿o DB.
  3. Token t峄?膽膬ng nh岷璸 th岷璽 g峄峣 膽瓢峄 endpoint c贸 `auth:sanctum`.
  4. Sai m岷璽 kh岷﹗ 鈫?401, kh么ng t岷 token m峄沬.
  5. T脿i kho岷 kh么ng t峄搉 t岷 鈫?401 (kh么ng ph岷 500).
  6. 膼膬ng xu岷 x贸a 膽煤ng b岷 ghi token kh峄廼 `personal_access_tokens`.
  - *Gi峄沬 h岷 ghi nh岷璶:* m峄檛 bi岷縩 th峄?ban 膽岷 c峄 test #6 th峄?g峄峣 l岷 endpoint b岷眓g token 膽茫 thu h峄搃 trong **c霉ng m峄檛 ti岷縩 tr矛nh PHPUnit** b峄?false-positive (v岷玭 tr岷?200) do `config('sanctum.guard')=['web']` khi岷縩 Sanctum 瓢u ti锚n ki峄僲 tra session guard tr瓢峄沜, v脿 Laravel test client d霉ng chung container/session `array` gi峄痑 c谩c l峄噉h g峄峣 li锚n ti岷縫 trong m峄檛 test 鈥?膽芒y l脿 膽岷穋 th霉 m么i tr瓢峄漬g test, **kh么ng ph岷 l峄梚 th岷璽** (膽茫 x谩c nh岷璶 h脿nh vi th岷璽 膽煤ng qua curl th岷璽 峄?b瓢峄沜 tr锚n). Test #6 v矛 v岷瓂 assert tr峄眂 ti岷縫 vi峄嘽 x贸a b岷 ghi trong DB thay v矛 replay request trong c霉ng ti岷縩 tr矛nh.
- To脿n b峄?**34 test backend (236 assertions)** pass sau khi th锚m.

#### K岷縯 qu岷?
- Nguy锚n nh芒n g峄慶: migration Sanctum g峄慶 d霉ng ki峄僽 `bigint` cho `tokenable_id`, kh么ng t瓢啤ng th铆ch User UUID; b岷g b峄?x贸a th峄?c么ng ngo脿i migration 膽峄?s峄璦 l峄梚 n脿y trong qu谩 kh峄?nh瓢ng ch瓢a t峄玭g 膽瓢峄 ghi l岷 th脿nh migration, g芒y tr么i d岷 gi峄痑 `migrations` bookkeeping v脿 schema th岷璽.
- 膼茫 t岷 migration ph峄 h峄搃 an to脿n, idempotent, kh么ng s峄璦 l峄媍h s峄?migration c农, `down()` kh么ng ph谩 d峄?li峄噓.
- 膼膬ng nh岷璸 th岷璽 ho岷 膽峄檔g (HTTP 200), token l瓢u 膽煤ng, thu h峄搃 膽煤ng, c谩c tr瓢峄漬g h峄 l峄梚 tr岷?膽煤ng m茫 (401/422, kh么ng c貌n 500).
- To脿n b峄?Phase 11 (4 b谩o c谩o, xu岷 Excel/PDF, Audit Log Explorer, override dung sai) 膽茫 smoke-test l岷 qua API th岷璽 v峄沬 ng瓢峄漣 d霉ng th岷璽, c贸 ph芒n quy峄乶 膽煤ng.
- Kh么ng m岷 d峄?li峄噓 nghi峄噋 v峄?(`app.users`, `app.audit_logs` v脿 c谩c b岷g kh谩c kh么ng 膽峄昳 s峄?d貌ng ngo脿i c谩c thay 膽峄昳 do ch铆nh phi锚n test n脿y t岷 ra r峄搃 t峄?d峄峮).
- 膼茫 ghi 2 gi岷?膽峄媙h nghi峄噋 v峄?c貌n t峄搉 膽峄峮g v脿o `open-questions.md`: `CH-BUS-003` (quy t岷痗 chia ca 3x8h l脿 gi岷?膽峄媙h k峄?thu岷璽, ch瓢a x谩c nh岷璶 nghi峄噋 v峄? khuy岷縩 ngh峄?膽瓢a v脿o b岷g c岷 h矛nh h峄?th峄憂g thay v矛 hard-code) v脿 `CH-RES-005` (x谩c nh岷璶 bi峄僽 膽峄?Pareto 1 tr峄 v岷玭 hi峄僴 th峄?膽峄?s峄?ca + % qua direct label/tooltip, kh么ng c岷 tr峄 k茅p).

---

## Nh岷璽 k媒 Phi锚n (Giai 膽o岷 ti岷縫 theo - 16/07/2026)

### 12. B岷痶 膽岷 t谩i c岷 tr煤c giao di峄噉 theo m么 h矛nh Workstation (DF Connector & Scale)
- **M峄 ti锚u:** Chuy峄僴 h峄?th峄憂g t峄?"m峄檛 ph岷 m峄乵 nhi峄乽 ch峄ヽ n膬ng" sang "1 m谩y t铆nh = 1 c么ng 膽o岷 = 1 nhi峄噈 v峄?= 1 giao di峄噉" theo y锚u c岷 ng瓢峄漣 d霉ng. Th峄?t峄?tri峄僴 khai WS-001 鈫?WS-012 do ng瓢峄漣 d霉ng quy 膽峄媙h.
- **R脿 so谩t tr瓢峄沜 khi s峄璦 (b岷痶 bu峄檆 theo y锚u c岷):** l岷璸 b谩o c谩o 膽岷 膽峄?t岷 [`workstation-redesign-audit.md`](file:///F:/DF/.claude/workstation-redesign-audit.md) tr瓢峄沜 khi 膽峄昳 b岷 k峄?giao di峄噉 n脿o. Ph谩t hi峄噉 ch铆nh: backend (`ScannerController`, `Workstation` model, 10 lo岷 tr岷 膽茫 seed) v脿 3 view (`WeighingStation.vue`, `MaterialTransfer.vue`, `FeedingMonitor.vue`) 膽茫 g岷 kh峄沺 m么 h矛nh qu茅t-l脿-ch铆nh; kho岷g c谩ch th岷璽 n岷眒 峄?t岷g 膽i峄乽 h瓢峄沶g (menu 膽岷 膽峄?hi峄僴 th峄?cho m峄峣 tr岷, kh么ng t峄?redirect theo lo岷 tr岷).
- **2 quy岷縯 膽峄媙h c峄 ng瓢峄漣 d霉ng l脿m thay 膽峄昳 thi岷縯 k岷?** (1) QR l脿 ch铆nh, cho ph茅p nh岷璸 tay khi m谩y qu茅t l峄梚 鈥?谩p d峄g m峄峣 tr岷 c贸 qu茅t; (2) t脿i kho岷 g岷痭 c峄﹏g theo c么ng 膽o岷 (kh么ng ph岷 ng瓢峄漣 d霉ng t峄?ch峄峮 tr岷 m峄梚 l岷), Admin ch峄媢 tr谩ch nhi峄噈 ph芒n quy峄乶 t脿i kho岷 cho t峄玭g c么ng 膽o岷.

#### WS-001 鈥?Workstation Model (膼茫 ho脿n th脿nh)
- Migration `2026_07_16_000008_add_workstation_model_fields_and_user_binding`: th锚m `allowed_actions` (jsonb), `default_screen` (string) v脿o `app.workstations`; th锚m `workstation_id` (FK, nullable, `onDelete('set null')`) v脿o `app.users`.
- C岷璸 nh岷璽 `Workstation`/`User` model (quan h峄?`users()`/`workstation()`), `WorkstationsSeeder` (g谩n `default_screen` + `allowed_actions` cho c岷?10 tr岷), `AuthController::login`/`me` tr岷?k猫m object `workstation` 膽岷 膽峄?
- Test m峄沬 `WorkstationBindingTest.php` (4 test): login tr岷?膽煤ng workstation cho t脿i kho岷 tr岷, tr岷?`null` cho t脿i kho岷 back-office, `/me` ph岷 谩nh 膽煤ng, x贸a workstation kh么ng cascade-x贸a user (ch峄?g峄?li锚n k岷縯).
- **Ti峄噉 th峄?v谩 l峄梚 c煤 ph谩p PHP** trong `app/Http/Middleware/CheckRole.php` (膽茫 膽膬ng k媒 alias `role` trong `bootstrap/app.php` nh瓢ng import sai `Symfony\Component\HttpFoundation.Response` 鈥?d岷 ch岷 thay v矛 `\` 鈥?s岷?crash app n岷縰 b峄?g峄峣; ch瓢a t峄玭g 膽瓢峄 d霉ng 峄?route n脿o n锚n ch瓢a g芒y s峄?c峄?th岷璽, ph谩t hi峄噉 khi chu岷﹏ b峄?d霉ng cho WS-003).

### 13. Ho脿n th脿nh WS-005 鈫?WS-012 (Redesign to脿n b峄?c谩c tr岷 v岷璶 h脿nh theo m么 h矛nh Workstation)
- **Ng瓢峄漣 d霉ng ch峄憈 "l脿m theo th峄?t峄?**, ti岷縫 t峄 tu岷 t峄?kh么ng d峄玭g l岷 xin x谩c nh岷璶 tr峄?khi c贸 quy岷縯 膽峄媙h thi岷縯 k岷?th岷璽 s峄?m啤 h峄?

#### WS-005 鈥?Tr岷 Qu茅t 膽啤n QR (膼茫 ho脿n th脿nh)
- Ph谩t hi峄噉 kho岷g tr峄憂g: `ScannerController::handleOrderScan` tr瓢峄沜 膽芒y ch峄?cho qu茅t ORDER t岷 c谩c tr岷 c芒n (t岷 nhi峄噈 v峄?c芒n ngay), `ORDER_DESK` s岷?b峄?t峄?ch峄慽 403. Thi岷縯 k岷?`ORDER_DESK` th脿nh b瓢峄沜 **xem tr瓢峄沜 (read-only) + x谩c nh岷璶 ri锚ng** 鈥?kh么ng 膽峄g v脿o lu峄搉g c芒n 膽茫 test.
- Backend: `ScannerController::handleOrderDeskPreview` (xem, kh么ng 膽峄昳 tr岷g th谩i) + `acknowledgeOrder` (chuy峄僴 `NEW 鈫?READY_TO_WEIGH`, **idempotent** 鈥?x谩c nh岷璶 l岷 l岷 2 kh么ng l峄梚 kh么ng t岷 audit log tr霉ng 鈥?ghi Audit Log `ORDER_RECEIVED_ACK`).
- Frontend `OrderScan.vue` m峄沬: m脿n h矛nh ch峄?qu茅t + 么 nh岷璸 tay t矛m theo m茫 L么 (`GET /api/production-batches?search=`) l脿m fallback th岷璽 (kh么ng ph岷 "ch峄?d脿nh cho ki峄僲 th峄?), card xem tr瓢峄沜 + n煤t "膼茫 nh岷璶 膽啤n", t峄?reset sau 3 gi芒y.
- Test `OrderDeskScanTest.php` (5 test, 17 assertions).

#### WS-006 鈥?Kh贸a menu theo Workstation (膼茫 ho脿n th脿nh, c峄憈 l玫i to脿n b峄?膽峄 t谩i c岷 tr煤c)
- N峄慽 `user.workstation` (t峄?WS-001) v脿o c啤 ch岷?`currentWorkstation` s岷祅 c贸 (`services/workstation.ts`) qua `stores/auth.ts`: `login()`/`initialize()` t峄?膽峄搉g b峄? `logout()` x贸a s岷h 鈥?t脿i kho岷 tr岷 kh么ng c岷 t峄?ch峄峮 tr岷 n峄痑, t脿i kho岷 back-office (kh么ng g岷痭 tr岷) v岷玭 gi峄?nguy锚n c啤 ch岷?ch峄峮 th峄?c么ng c农.
- `router/index.ts`: th锚m guard b岷痶 bu峄檆 膽i峄乽 h瓢峄沶g v峄?膽煤ng `default_screen` c峄 t脿i kho岷 tr岷, ch岷穘 truy c岷璸 route kh谩c k峄?c岷?g玫 URL tr峄眂 ti岷縫.
- `AppLayout.vue`: 岷﹏ ho脿n to脿n sidebar/menu khi t脿i kho岷 膽茫 g岷痭 tr岷 (`isLockedStation`), kh贸a lu么n n煤t "膽峄昳 tr岷" (kh么ng c贸 c啤 ch岷?t峄?膽峄昳 鈥?膽煤ng quy岷縯 膽峄媙h "Admin ch峄媢 tr谩ch nhi峄噈 ph芒n quy峄乶").
- **Gi峄沬 h岷 t峄?x谩c minh:** 膽芒y l脿 thay 膽峄昳 h脿nh vi 膽i峄乽 h瓢峄沶g UI 鈥?ch峄?verify 膽瓢峄 qua `vue-tsc`/`npm run build` s岷h v脿 r脿 so谩t logic, **kh么ng c贸 c么ng c峄?tr矛nh duy峄噒 膽峄?xem tr峄眂 ti岷縫**. 膼茫 b霉 膽岷痯 b岷眓g UAT d峄?li峄噓 th岷璽 峄?m峄 WS-012 b锚n d瓢峄沬 (x谩c nh岷璶 `default_screen` 膽煤ng cho m峄峣 lo岷 tr岷 qua API th岷璽).

#### WS-007 鈥?Tr岷 In tem 膽峄檆 l岷璸 (膼茫 ho脿n th脿nh)
- Th锚m lo岷 workstation m峄沬 `PRINT_STATION` (`PRINT-01`) 鈥?b峄?sung v脿o `WorkstationsSeeder`, kh么ng 膽峄g 10 tr岷 c农.
- Backend: `WeighingJobController::showLabel` (xem 1 tem theo id) + `searchLabels` (t矛m theo m茫 L么, ph峄 v峄?nh岷璸 tay fallback) 鈥?t谩i d霉ng nguy锚n `reprintLabel` 膽茫 c贸 s岷祅 Audit Log t峄?Phase 7.
- Frontend `PrintStation.vue` m峄沬: qu茅t tem 鈫?xem chi ti岷縯 (L么, v岷璽 t瓢, kh峄慽 l瓢峄g) 鈫?b岷痶 bu峄檆 nh岷璸 l媒 do 鈫?in l岷. Tr岷 n脿y 膽峄檆 l岷璸 v峄沬 tr岷 c芒n, d霉ng khi c岷 in l岷 tem gi峄?ng脿y kh谩c m脿 kh么ng c貌n 峄?phi锚n c芒n g峄慶.
- Test `PrintStationTest.php` (5 test, 13 assertions).

#### WS-008 鈥?Redesign V岷璶 chuy峄僴 (膼茫 ho脿n th脿nh)
- **Ph谩t hi峄噉 l峄梚 th岷璽 trong code c农:** `MaterialTransfer.vue`'s "widget gi岷?l岷璸 qu茅t" d霉ng **ID c峄 Batch gi岷?l脿m ID c峄 Tem v岷璽 t瓢** (`id: b.id` g谩n nh岷 cho `MaterialLabel`) 鈥?ho脿n to脿n kh么ng ho岷 膽峄檔g n岷縰 b岷 th峄? v矛 backend t矛m `MaterialLabel::findOrFail($batchId)` s岷?lu么n th岷 b岷 (tr峄?tr霉ng UUID ng岷玼 nhi锚n). 膼茫 x贸a b峄? thay b岷眓g nh岷璸 tay th岷璽 qua `GET /api/material-labels/search` (endpoint m峄沬 th锚m 峄?WS-007).
- Th锚m banner x谩c nh岷璶 sau khi qu茅t th脿nh c么ng hi峄僴 th峄?膽铆ch 膽岷縩 (M谩y/Th霉ng) 鈥?kh峄沺 m么 t岷?m峄 6D g峄慶. Backend `handleMaterialLabelScan` tr岷?k猫m `batch.machine`/`batch.tank` 膽峄?banner c贸 膽峄?d峄?li峄噓 hi峄僴 th峄?

#### WS-009 鈥?Redesign T峄沬 th霉ng (膼茫 ho脿n th脿nh)
- C霉ng l峄梚 widget gi岷?l岷璸 b峄?l岷玭 Batch-id-l脿m-Label-id nh瓢 WS-008, n岷眒 trong `FeedingMonitor.vue` (nh谩nh `TANK_RECEIVING`) 鈥?膽茫 s峄璦 t瓢啤ng t峄? ch峄峮 m谩y th岷璽 t峄?danh s谩ch + t矛m tem th岷璽 theo m茫 L么.
- Th锚m hi峄僴 th峄?l峄梚/th脿nh c么ng d岷g inline (thay `alert()` 峄?ph岷 n脿y) cho lu峄搉g qu茅t k茅p.

#### WS-010 鈥?Redesign C岷 m谩y (膼茫 ho脿n th脿nh)
- Ph谩t hi峄噉: dropdown ch峄峮 l么 c岷 m谩y (`readyBatches`) tr瓢峄沜 膽芒y t岷 **T岷 C岷?* l么 s岷 xu岷 kh么ng l峄峜 tr岷g th谩i 鈥?nh芒n vi锚n c贸 th峄?ch峄峮 nh岷 l么 c貌n 膽ang c芒n/v岷璶 chuy峄僴 v脿o c岷 m谩y. 膼茫 l峄峜 theo `status=ARRIVED_AT_TANK` (膽煤ng 膽i峄乽 ki峄噉 `FeedOperationController::checkReadiness` y锚u c岷), kh峄沺 nguy锚n t岷痗 "ch峄?hi峄僴 th峄?ch峄ヽ n膬ng 膽瓢峄 ph茅p".

#### WS-011 鈥?Dashboard Gi谩m s谩t (膼茫 ho脿n th脿nh)
- Ph谩t hi峄噉: kh峄慽 "C么ng c峄?ki峄僲 th峄?& Gi岷?l岷璸 (Admin / Developer)" trong `Dashboard.vue` (g峄搈 c岷?n煤t g峄璱 l峄噉h in TSPL th岷璽) 膽茫 ghi r玫 trong nh茫n l脿 d脿nh cho Admin/Developer nh瓢ng **ch瓢a t峄玭g 膽瓢峄 kh贸a quy峄乶 th岷璽** 鈥?b岷 k峄?ai v脿o Dashboard c农ng b岷 膽瓢峄. 膼茫 th锚m `v-if="authStore.isAdmin"`, 膽煤ng tinh th岷 m峄 11 "Dashboard kh么ng d霉ng 膽峄?nh岷璸 li峄噓, ch峄?gi谩m s谩t" cho t脿i kho岷 tr岷 MONITORING.

#### WS-012 鈥?UAT t峄玭g Workstation (膼茫 ho脿n th脿nh, qua HTTP th岷璽)
- Kh么ng c贸 c么ng c峄?tr矛nh duy峄噒 n锚n UAT 膽瓢峄 th峄眂 hi峄噉 b岷眓g c谩ch: t岷 8 t脿i kho岷 tr岷 th岷璽 qua ch铆nh API WS-003 v峄玜 x芒y (`uat_order_desk`, `uat_ws_dye`, `uat_ws_chem`, `uat_print`, `uat_trans`, `uat_tank`, `uat_feed`, `uat_monitor`), 膽膬ng nh岷璸 th岷璽 t峄玭g t脿i kho岷, v脿 ch岷 **to脿n b峄?v貌ng 膽峄漣 1 l么 nhu峄檓 th岷璽** qua 6 tr岷 li锚n ti岷縫 b岷眓g HTTP th岷璽 (kh么ng mock, kh么ng `Sanctum::actingAs()`):
  1. Order Desk: qu茅t xem tr瓢峄沜 鈫?x谩c nh岷璶 nh岷璶 膽啤n (`NEW 鈫?READY_TO_WEIGH`).
  2. Tr岷 c芒n DYE: qu茅t 膽啤n 鈫?sinh nhi峄噈 v峄?c芒n 鈫?c芒n 膽煤ng kh峄慽 l瓢峄g 鈫?ho脿n t岷.
  3. In tem t峄?膽峄檔g sau khi c芒n xong.
  4. Print Station: t矛m tem theo m茫 L么 鈫?in l岷 c贸 l媒 do (verify `reprint_count` t膬ng 膽煤ng).
  5. V岷璶 chuy峄僴: qu茅t tem 鈫?chuy峄僴 `IN_TRANSIT`.
  6. Tank Receiving: qu茅t k茅p m谩y+tem 鈫?膽峄慽 so谩t kh峄沺 鈫?`ARRIVED_AT_TANK`.
  7. Machine Feeding: ki峄僲 tra s岷祅 s脿ng (膽煤ng `true`) 鈫?x谩c nh岷璶 l么 xu岷 hi峄噉 trong danh s谩ch 膽茫 l峄峜 鈫?kh峄焛 t岷 鈫?x谩c nh岷璶 n瓢峄沜 鈫?m峄?van 鈫?ho脿n t岷.
  8. Monitoring: x谩c nh岷璶 t脿i kho岷 Gi谩m s谩t g峄峣 膽瓢峄 `/api/dashboard/overview`.
  9. Cross-check ph芒n quy峄乶: t脿i kho岷 tr岷 c芒n **KH脭NG** g峄峣 膽瓢峄 API Admin (403 膽煤ng, x谩c nh岷璶 middleware `role:ADMIN` 膽茫 v谩 ho岷 膽峄檔g th岷璽 trong lu峄搉g th岷璽, kh么ng ch峄?trong test).
- To脿n b峄?13 b瓢峄沜 膽峄乽 tr岷?膽煤ng HTTP status/d峄?li峄噓 mong 膽峄, kh么ng c贸 l峄梚 500 n脿o.
- D峄峮 d岷筽: x贸a s岷h batch/machine/material/recipe/transport/feed-operation gi岷?l岷璸 d霉ng 膽峄?UAT. **8 t脿i kho岷 UAT kh么ng x贸a 膽瓢峄** do b峄?kh贸a ngo岷 b岷 v峄?b峄焛 Audit Log b岷 bi岷縩 (膽煤ng thi岷縯 k岷? 鈥?膽茫 **v么 hi峄噓 h贸a** (`is_active=false`) thay v矛 x贸a, gi峄?nguy锚n v岷縯 audit.

#### K岷縯 qu岷?cu峄慽 c霉ng c峄 膽峄 t谩i c岷 tr煤c Workstation (WS-001 鈫?WS-012)
- To脿n b峄?**54 test backend (291 assertions)** pass. Frontend type-check s岷h, `npm run build` th脿nh c么ng li锚n t峄 qua t峄玭g b瓢峄沜.
- **Gi峄沬 h岷 膽茫 n锚u r玫:** kh么ng c贸 c么ng c峄?膽i峄乽 khi峄僴 tr矛nh duy峄噒 th岷璽, n锚n c谩c thay 膽峄昳 thu岷 UI (岷﹏/hi峄噉 sidebar, redirect, hi峄僴 th峄?banner) 膽瓢峄 x谩c minh qua type-check + build + r脿 so谩t logic + UAT d峄?li峄噓 th岷璽 qua API, **kh么ng ph岷 quan s谩t tr峄眂 ti岷縫 b岷眓g m岷痶 tr锚n tr矛nh duy峄噒**. Khuy岷縩 ngh峄?ng瓢峄漣 d霉ng t峄?膽膬ng nh岷璸 th峄?b岷眓g 1 trong c谩c t脿i kho岷 m岷玼 tr瓢峄沜 khi coi l脿 ho脿n th脿nh 100% (t脿i kho岷 UAT 膽茫 b峄?v么 hi峄噓 h贸a, c岷 t岷 t脿i kho岷 m峄沬 qua m脿n h矛nh "Qu岷 l媒 Workstation" n岷縰 mu峄憂 t峄?ki峄僲 tra).
- File audit `workstation-redesign-audit.md` 膽茫 膽瓢峄 c岷璸 nh岷璽 tr岷g th谩i 膽岷 膽峄?WS-001 鈫?WS-012.

#### WS-003 鈥?C岷 h矛nh Workstation (膽茫 g峄檖 WS-002, 膼茫 ho脿n th脿nh theo ph岷 vi 膽茫 ch峄憈)
- Ng瓢峄漣 d霉ng ch峄憈 ph岷 vi h岷筽: **ch峄?t岷 t脿i kho岷 tr岷 m峄沬**, ch瓢a l脿m g谩n l岷 t脿i kho岷 c农 / s峄璦 thi岷縯 b峄?/ th锚m-x贸a workstation (膽峄?d脿nh cho l岷 sau n岷縰 c岷).
- `WorkstationAdminController` (route `role:ADMIN`): `GET /api/admin/workstations` (danh s谩ch k猫m t脿i kho岷 膽茫 g谩n), `POST /api/admin/workstations/{id}/users` (t岷 t脿i kho岷 m峄沬, gi峄沬 h岷 vai tr貌 `OPERATOR/SUPERVISOR/TECHNOLOGIST` 鈥?kh么ng cho t岷 t脿i kho岷 `ADMIN` g岷痭 1 tr岷 v矛 m岷 媒 ngh末a back-office to脿n quy峄乶), ghi Audit Log `CREATE_STATION_ACCOUNT`.
- Frontend `WorkstationAdmin.vue`: l瓢峄沬 th岷?10 workstation (膽煤ng tinh th岷 mock-up m峄 5 g峄慶, nh瓢ng chuy峄僴 th脿nh m脿n h矛nh c岷 h矛nh c峄 Admin thay v矛 m脿n h矛nh ch峄峮 tr岷 h脿ng ng脿y c峄 operator), b岷 th岷?m峄?modal t岷 t脿i kho岷. Route `/workstation-admin` (`requiresAdmin`), m峄 menu m峄沬 nh贸m "QU岷 TR峄? ch峄?hi峄噉 v峄沬 `authStore.isAdmin`.
- Test m峄沬 `WorkstationAdminTest.php` (6 test): ch岷穘 non-admin xem danh s谩ch/t岷 t脿i kho岷 (403), t岷 th脿nh c么ng + verify Audit Log + verify t脿i kho岷 m峄沬 膽膬ng nh岷璸 膽瓢峄 v脿 nh岷璶 膽煤ng `workstation`, ch岷穘 t岷 role `ADMIN` g岷痭 tr岷 (422), ch岷穘 tr霉ng username (422).

#### WS-004 鈥?Scanner Service (膼茫 ho脿n th脿nh)
- Vi岷縯 l岷 `frontend/src/services/scanner.ts` quanh 1 pipeline d霉ng chung `processToken(token, source)` cho c岷?3 ngu峄搉: qu茅t v岷璽 l媒 (b脿n ph铆m wedge), nh岷璸 tay (`submitManualEntry`, fallback khi m谩y qu茅t l峄梚 theo quy岷縯 膽峄媙h #1), v脿 gi岷?l岷璸 ki峄僲 th峄?(`simulateScan`, gi峄?t瓢啤ng th铆ch ng瓢峄 3 m脿n h矛nh c农).
- B峄?sung: ch峄憂g scan tr霉ng (b峄?qua c霉ng 1 token trong 2 gi芒y, ph谩t ti岷縩g kh谩c bi峄噒 thay v矛 x峄?l媒 l岷), timeout buffer b脿n ph铆m (x贸a buffer n岷縰 g玫 d峄?dang qu谩 3 gi芒y, tr谩nh l脿m h峄弉g l瓢峄 qu茅t k岷?ti岷縫), `lastScanSource`/`lastResult` 膽峄?UI sau n脿y ph芒n bi峄噒 "qu茅t" vs "nh岷璸 tay".
- Ch峄?k媒 callback `onScan` 膽峄昳 t峄?`(token)` th脿nh `(token, source)` 鈥?t瓢啤ng th铆ch ng瓢峄 100% v峄沬 3 handler c农 (`WeighingStation.vue`, `MaterialTransfer.vue`, `FeedingMonitor.vue`) do TypeScript cho ph茅p h脿m nh岷璶 铆t tham s峄?h啤n kh峄沺 v脿o v峄?tr铆 c岷 nhi峄乽 tham s峄?h啤n; 膽茫 x谩c nh岷璶 qua `vue-tsc --noEmit` s岷h v脿 `npm run build` th脿nh c么ng.
- **Ch瓢a 膽峄昳 giao di峄噉 c谩c tr岷 qu茅t** 鈥?膽贸 l脿 ph岷 vi WS-005/006/008/009 (redesign t峄玭g m脿n h矛nh d霉ng `submitManualEntry` l脿m 么 nh岷璸 tay th岷璽, thay cho "widget gi岷?l岷璸 ch峄?d脿nh cho ki峄僲 th峄? hi峄噉 t岷).

#### K岷縯 qu岷?t峄沬 th峄漣 膽i峄僲 n脿y
- To脿n b峄?**44 test backend (261 assertions)** pass. Frontend type-check s岷h, `npm run build` th脿nh c么ng (140 module).

## Nh岷璽 k媒 Phi锚n (Giai 膽o岷 ti岷縫 theo - 16/07/2026)

### 14. R脿 so谩t to脿n di峄噉 VBA legacy 鈫?Web (膽峄慽 chi岷縰 c岷 procedure, KH脭NG s峄璦 code)
- **Nhi峄噈 v峄?** Theo y锚u c岷 t瓢峄漬g minh c峄 ng瓢峄漣 d霉ng, th峄眂 hi峄噉 膽峄 r脿 so谩t thu岷 膽峄峜/ph芒n t铆ch (kh么ng thi岷縯 k岷?l岷, kh么ng 膽峄昳 ki岷縩 tr煤c, kh么ng s峄璦 code) 膽峄慽 chi岷縰 to脿n b峄?VBA legacy c贸 m岷穞 t岷 `F:\DF\` v峄沬 source code h峄?th峄憂g web hi峄噉 t岷, ph芒n lo岷 t峄玭g procedure theo 9 tr岷g th谩i chu岷﹏, v脿 ch峄?d峄玭g l岷 b谩o c谩o 鈥?ch瓢a s峄璦 g矛.
- **C么ng c峄?** c脿i `oletools`/`olevba` (Python) tr铆ch xu岷 VBA source t峄?13 file `.xlsm`; c脿i `pywin32` 膽峄?m峄?3 file `.accdb` qua Access COM (ch峄?tr锚n **b岷 sao read-only** trong scratchpad, kh么ng 膽峄g file g峄慶) 鈥?x谩c nh岷璶 c岷?3 (`DF_STORAGE.accdb`, `RECORD.accdb`, `WH.accdb`) ch峄?l脿 kho d峄?li峄噓 th么, kh么ng ch峄゛ VBA/query/form.
- **Ph谩t hi峄噉 h岷?t岷g quan tr峄峮g:** Postgres dev (`production_web`) 膽茫 c贸 s岷祅 schema `legacy_df_data` (9 b岷g, g峄搈 `tbl_ToSend2`/`WAITING`/`tblSync` 鈥?3 b岷g KH脭NG c贸 VBA ngu峄搉 n脿o trong `F:\DF` tham chi岷縰 t峄沬) v脿 `legacy_df_scale` (3 b岷g) 鈥?b岷眓g ch峄﹏g m峄檛 膽峄 di tr煤 d峄?li峄噓 tr瓢峄沜 膽芒y t峄玭g c贸 quy峄乶 truy c岷璸 v脿o nhi峄乽 workbook/Access DB h啤n nh峄痭g g矛 hi峄噉 c贸 t岷 `F:\DF`.
- **Ph芒n c么ng:** dispatch 5 agent ch岷 song song, m峄梚 agent ph峄?tr谩ch 1 nh贸m nghi峄噋 v峄?(C么ng th峄ヽ, 膼i峄乽 ph峄慽/Kh贸a, C芒n b谩n t峄?膽峄檔g, In tem/QR, X峄?l媒 s峄?c峄?, m峄梚 agent t峄?膽峄峜 VBA + grep/膽峄峜 tr峄眂 ti岷縫 `F:\DF\backend`/`F:\DF\frontend` 膽峄?膽峄慽 chi岷縰, t峄?vi岷縯 b谩o c谩o chi ti岷縯 ra file ri锚ng.
- **K岷縯 qu岷?** ki峄僲 k锚 **~378 d貌ng procedure** (26 Recipe + 83 Dispatch + 133 Scale + 83 Print + 53 Troubleshooting). T峄昻g h峄 v脿o 2 t脿i li峄噓 m峄沬:
  - [`vba-migration-matrix.md`](file:///F:/DF/.claude/vba-migration-matrix.md) 鈥?ma tr岷璶 膽岷 膽峄?c岷 procedure, c贸 ID 峄昻 膽峄媙h (`VBA-RECIPE-*`, `VBA-DISPATCH-*`, `VBA-SCALE-*`, `VBA-PRINT-*`, `VBA-TROUBLE-*`).
  - [`vba-version-comparison.md`](file:///F:/DF/.claude/vba-version-comparison.md) 鈥?so s谩nh phi锚n b岷 trong t峄玭g nh贸m (k峄?c岷?c谩c c岷穚 kh么ng th峄?so s谩nh 膽瓢峄 v矛 thi岷縰 file `(1)`/`Copy of`).
- **5 ph谩t hi峄噉 nghi锚m tr峄峮g nh岷** (chi ti岷縯 峄?膽岷 `vba-migration-matrix.md`):
  1. H脿m `TraHeSo` (tra h峄?s峄?3 chi峄乽 m茫脳kh峄?v岷脳ti锚u) 鈥?nguy锚n t岷痗 c峄憈 l玫i CLAUDE.md y锚u c岷 b岷 to脿n 鈥?**ch瓢a migrate**; t脿i li峄噓 c农 ghi sai l脿 "膽茫 x谩c minh".
  2. To脿n b峄?lu峄搉g ghi m峄沬 v脿o h脿ng ch峄?膽i峄乽 ph峄慽 (`machine_dispatches`) **ch瓢a t峄搉 t岷** 鈥?`MachineDispatchController` ch峄?c贸 claim/release/send, kh么ng c贸 `store`.
  3. L玫i thu岷璽 to谩n c芒n b谩n t峄?膽峄檔g (StableFilter, delta/tare) **MISSING**; `ScaleReader.cs` l岷 s峄?膽岷 ti锚n thay v矛 s峄?cu峄慽 c霉ng nh瓢 VBA (`ExtractLastNumber`) 鈥?kh谩c bi峄噒 h脿nh vi th岷璽.
  4. `tbl_ToSend2`/`WAITING`/`tblSync` c贸 d峄?li峄噓 th岷璽 trong Postgres nh瓢ng kh么ng c贸 VBA ngu峄搉 膽峄?x谩c minh 鈥?mapping c峄檛 trong `03_transform_legacy_to_target.sql` l脿 suy lu岷璶 ch瓢a ki峄僲 ch峄﹏g.
  5. Feedback loop / Editor Knowledge Base c峄 h峄?ch岷﹏ 膽o谩n s峄?c峄?b峄?m岷 (ch峄?s峄璦 膽瓢峄 qua deploy l岷 seeder) 鈥?d霉 c么ng th峄ヽ scoring ch铆nh (`InferenceService`) 膽瓢峄 migrate 膽煤ng v脿 膽岷 膽峄? th岷璵 ch铆 s峄璦 膽瓢峄 1 bug c贸 s岷祅 trong VBA g峄慶.
- **Ph谩t hi峄噉 ph峄?膽谩ng ch煤 媒:** 2 route sai trong `source-traceability.md` (`/api/queue-items/...` thay v矛 `/api/machine-dispatches/...`) v脿 quy ch峄 sai module ngu峄搉 cho c啤 ch岷?kh贸a (`ModAcessDB`/`tblSync` 鈥?th峄眂 ra c啤 ch岷?`locked_by/locked_at` l脿 thi岷縯 k岷?m峄沬, kh么ng k岷?th峄玜 VBA) 鈥?膽茫 s峄璦. Bug c贸 s岷祅 trong VBA (kh么ng ph岷 do migrate): workbook `semiauto-...SEND OVER6...` lu么n ghi sai "REJECTED" do l峄嘽h m脿u so s谩nh; `SaveEngine` c峄 h峄?ch岷﹏ 膽o谩n s峄?c峄?lu么n ghi c峄﹏g breakdown 膽i峄僲 = 0 (膽茫 膽瓢峄 web s峄璦 膽煤ng).
- **T脿i li峄噓 膽茫 c岷璸 nh岷璽 theo k岷縯 qu岷?r脿 so谩t:** `source-traceability.md` (s峄璦 2 d貌ng sai), `open-questions.md` (+8 c芒u h峄廼 m峄沬 CH-BUS-004..008, CH-TECH-003..006), `risks-and-assumptions.md` (+6 r峄 ro m峄沬 R-06..R-11), `testing-strategy.md` (+3 b峄?golden test 膽峄?xu岷 cho thu岷璽 to谩n c芒n, x谩c nh岷璶 `scratch/` simulator ch瓢a tri峄僴 khai).
- **Ch瓢a s峄璦 code n脿o** 鈥?膽煤ng y锚u c岷 "ch峄?r脿 so谩t, d峄玭g l岷 sau khi g峄璱 b谩o c谩o, kh么ng t峄?媒 s峄璦 h脿ng lo岷". Ch峄?ng瓢峄漣 d霉ng duy峄噒 tr瓢峄沜 khi l锚n k岷?ho岷h b峄?sung theo m峄ヽ 瓢u ti锚n.
- **Gi峄沬 h岷 ph岷 vi:** 12 workbook 膽瓢峄 li峄噒 k锚 trong y锚u c岷 g峄慶 kh么ng c贸 m岷穞 t岷 `F:\DF` (ch峄?y岷縰 c谩c b岷 `(1)`/`Copy of` v脿 2 file template tem 27-d貌ng/15L-special/landscape/JIT) 鈥?xem danh s谩ch 膽岷 膽峄?+ m峄ヽ 瓢u ti锚n b峄?sung 峄?膽岷 `vba-version-comparison.md`.
- C貌n l岷 WS-005 鈫?WS-012 (redesign t峄玭g m脿n h矛nh theo t脿i kho岷-tr岷 + kh贸a menu, t谩ch tr岷 in tem, dashboard, UAT) 鈥?quy m么 l峄沶, 膽ang tri峄僴 khai tu岷 t峄?theo 膽煤ng th峄?t峄?ng瓢峄漣 d霉ng y锚u c岷, d峄玭g l岷 xin x谩c nh岷璶 峄?c谩c 膽i峄僲 r岷?nh谩nh thi岷縯 k岷?quan tr峄峮g.

### 15. Thi岷縯 k岷?v脿 chu岷﹏ h贸a c岷 h矛nh tr岷 l脿m vi峄嘽 (Workstation Matrix & Architecture)
- **Nhi峄噈 v峄?** Nh岷璶 y锚u c岷 b峄?sung c岷 h矛nh m谩y tr岷 th峄眂 t岷?v脿o h峄?th峄憂g v脿 ma tr岷璶. Ti岷縩 h脿nh c岷璸 nh岷璽 v脿 t岷 m峄沬 t脿i li峄噓 `.claude/` 膽峄?kh峄沺 v峄沬 m么 h矛nh tr岷 "1 m谩y t铆nh = 1 c么ng 膽o岷 ch铆nh = 1 m脿n h矛nh m岷穋 膽峄媙h", 膽峄檆 l岷璸 v峄沬 膽峄媋 ch峄?IP m岷g.
- **T脿i li峄噓 t岷 m峄沬 / c岷璸 nh岷璽:**
  - T岷 m峄沬 [`workstation-matrix.md`](file:///F:/DF/.claude/workstation-matrix.md): Chi ti岷縯 h贸a c岷 h矛nh 7 m谩y client, c谩c tr瓢峄漬g c啤 s峄?d峄?li峄噓 r脿 so谩t/b峄?sung, v脿 quy tr矛nh ki峄僲 k锚 thi岷縯 b峄?c芒n/in.
  - T岷 m峄沬 [`legacy-to-target-architecture.md`](file:///F:/DF/.claude/legacy-to-target-architecture.md): 脕nh x岷?9 b瓢峄沜 nghi峄噋 v峄?c峄憈 l玫i t峄?VBA/Access sang Web/API 膽铆ch, ch峄?r玫 tr岷g th谩i ho脿n thi峄噉 (Migrated, Missing, Replaced, New, Deprecated).
  - C岷璸 nh岷璽 [`system-context.md`](file:///F:/DF/.claude/system-context.md): T铆ch h峄 7 client v脿 lu峄搉g 膽膬ng k媒/x谩c th峄眂 tr岷 an to脿n qua certificate/token k岷縯 h峄 Device Fingerprint.
  - C岷璸 nh岷璽 [`source-traceability.md`](file:///F:/DF/.claude/source-traceability.md): B峄?sung truy v岷縯 cho c谩c th峄眂 th峄?v脿 API tr岷 l脿m vi峄嘽 m峄沬.
  - C岷璸 nh岷璽 [`open-questions.md`](file:///F:/DF/.claude/open-questions.md): Th锚m CH-TECH-007 (X谩c nh岷璶 lo岷 tr岷 cho 3 m谩y c芒n) v脿 CH-TECH-008 (V芒n tay thi岷縯 b峄?.
  - C岷璸 nh岷璽 [`security-rules.md`](file:///F:/DF/.claude/security-rules.md): Th锚m quy t岷痗 b岷 m岷璽 m谩y tr岷, c岷 ho脿n to脿n d霉ng IP l脿m kh贸a ch铆nh, ki峄僲 so谩t ch茅o API.
  - C岷璸 nh岷璽 [`testing-strategy.md`](file:///F:/DF/.claude/testing-strategy.md): Th锚m danh m峄 17 ca ki峄僲 th峄?tr岷 l脿m vi峄嘽 b岷痶 bu峄檆.
  - C岷璸 nh岷璽 [`vba-migration-matrix.md`](file:///F:/DF/.claude/vba-migration-matrix.md): T峄?膽峄檔g b峄?sung 8 c峄檛 Target Architecture cho t岷 c岷?c谩c b岷g procedure ki峄僲 k锚 (~378 d貌ng) s峄?d峄g script Python.
- **B谩o c谩o v脿 D峄玭g l岷:** Chu岷﹏ b峄?b谩o c谩o 10 h岷g m峄 v脿 K岷?ho岷h tri峄僴 khai (Implementation Plan) 膽峄?xin 媒 ki岷縩 duy峄噒 c峄 ng瓢峄漣 d霉ng tr瓢峄沜 khi s峄璦 b岷 k峄?d貌ng code n脿o.


## Nh岷璽 k媒 Phi锚n (Giai 膽o岷 ti岷縫 theo - 17/07/2026)

### 15. 膼峄 r脿 so谩t VBA l岷 2 鈥?chu岷﹏ h贸a s峄?li峄噓, ph芒n t铆ch s芒u 5 ph谩t hi峄噉 P0, l岷璸 k岷?ho岷h kh岷痗 ph峄 (CH漂A s峄璦 code)

- **B峄慽 c岷h:** Ng瓢峄漣 d霉ng duy峄噒 b谩o c谩o r脿 so谩t b瓢峄沜 膽岷 nh瓢ng CH漂A duy峄噒 k岷縯 lu岷璶 "膽茫 r脿 so谩t 膽岷 膽峄?to脿n b峄?VBA", y锚u c岷 8 h岷g m峄 b峄?sung. To脿n b峄?膽峄 n脿y thu岷 t脿i li峄噓 鈥?kh么ng s峄璦 code s岷 xu岷, kh么ng ch岷 migration, kh么ng 膽峄昳 schema.
- **Chu岷﹏ h贸a s峄?li峄噓 ki峄僲 k锚 (m峄 1):** x谩c 膽峄媙h ngu峄搉 ch锚nh l峄嘽h "~378": nh贸m DISPATCH t峄?b谩o 83 nh瓢ng b岷g th岷璽 c贸 84 d貌ng (s贸t `020B`); nh贸m SCALE t峄?b谩o 133 nh瓢ng 膽贸 l脿 s峄?ID c岷 ph谩t (4 d貌ng g峄檖 kho岷g ch峄゛ 28 ID), s峄?d貌ng b岷g th岷璽 l脿 109. S峄?ch铆nh x谩c: **355 d貌ng traceability**, **664 procedure v岷璽 l媒** (quy 瓢峄沜 膽岷縨 b岷 sao gi峄痑 workbook ri锚ng; 561 n岷縰 dedup). Chu岷﹏ h贸a 10 d貌ng c贸 c峄檛 Tr岷g th谩i sai 膽峄媙h d岷g (6 d貌ng `**MISSING**` b么i 膽岷璵; 2 d貌ng g谩n tr岷g th谩i k茅p; 1 d貌ng tham chi岷縰 ch茅o l峄嘽h c峄檛; 1 d貌ng b峄?tr峄憂g 鈥?m峄檛 ph岷 do m峄檛 phi锚n kh谩c 膽茫 ch岷 script Python th锚m 8 c峄檛 "Target *" v脿o b岷g g芒y l峄嘽h s峄?c峄檛 kh么ng 膽峄搉g nh岷). Vi岷縯 script ki峄僲 ch峄﹏g t峄?膽峄檔g [`verify-matrix-counts.sh`](file:///F:/DF/.claude/verify-matrix-counts.sh) 鈥?k岷縯 qu岷?PASS: SUM=355=ROWS, 0 unmatched. Ph芒n b峄?cu峄慽: FULLY 26, NO_TEST 1, PARTIALLY 30, MISSING 72, REPLACED 93, MERGED 5, DEPRECATED 35, DEAD 67, NEEDS_CONFIRM 26.
- **Danh s谩ch 12 workbook thi岷縰 (m峄 2):** t岷 [`source-files-missing.md`](file:///F:/DF/.claude/source-files-missing.md) 鈥?5 file P0 (3 file nh贸m 膽i峄乽 ph峄慽 nghi ch峄゛ logic tbl_ToSend2/WAITING/tblSync; 2 file template tem DF002 27rows/15L/landscape/JIT), 3 file P1 (nh贸m c芒n 鈥?"low stand1"/"8 rows"), 4 file P2. K岷縯 lu岷璶 ch铆nh th峄ヽ: nh贸m 膼i峄乽 ph峄慽 v脿 In tem/QR **CH漂A r脿 so谩t ho脿n ch峄塶h** khi thi岷縰 file P0.
- **Ph芒n t铆ch s芒u 5 ph谩t hi峄噉 P0 (m峄 3-4):** 5 agent song song, k岷縯 qu岷?l瓢u b峄乶 v峄痭g t岷 [`.claude/p0-analysis/`](file:///F:/DF/.claude/p0-analysis/) (5 file, m峄梚 file k猫m k岷?ho岷h FIX):
  - **P0-A TraHeSo:** pseudocode 膽岷 膽峄? 6 golden test (placeholder ch峄?d峄?li峄噓 b岷g tra th岷璽), ph谩t hi峄噉 m峄沬: kh么ng nh岷 qu谩n case-sensitivity ngay trong code g峄慶 (Find kh么ng ph芒n bi峄噒 hoa/th瓢峄漬g, Select Case ph芒n lo岷 A/B/C th矛 c贸).
  - **P0-B Dispatch:** truy v岷縯 10 b瓢峄沜 lu峄搉g t岷 h脿ng ch峄? nguy锚n v膬n quy t岷痗 250L (ch峄?c贸 峄?C3, MID grep "250" = 0 k岷縯 qu岷?; 3 l峄?h峄昻g c贸 s岷祅 trong VBA g峄慶: MID move 膽瓢峄 v峄沬 tank tr峄憂g, check tr霉ng ch峄?trong tbl_input_all, 250L kh么ng 膽瓢峄 ki峄僲 l岷 峄?b瓢峄沜 duy峄噒; l瓢u 媒 `level_code` l脿 text.
  - **P0-C Scale:** 膽峄慽 chi岷縰 10 膽i峄僲 + 7 test vector; x谩c nh岷璶 `.NET` l岷 s峄?膼岷 (VBA l岷 s峄?CU峄怚), r谩c COM b峄?quy v峄?0.0; 膽o d峄?li峄噓 th岷璽: 31.361/140.660 d貌ng REJECTED (~22,3%) nh瓢ng KH脭NG t谩ch 膽瓢峄 ph岷 "REJECTED gi岷? do bug workbook B (kh么ng c贸 c峄檛 li锚n k岷縯 tr岷鈫抴orkbook); `tblRECORD_chem.processCOLOR` r峄梟g 100%.
  - **P0-D Legacy tables:** ki峄僲 k锚 SELECT th岷璽 鈥?**膼脥NH CH脥NH:** `tblSync` R峄朜G (0 d貌ng), kh么ng ph岷 "c贸 d峄?li峄噓 th岷璽" nh瓢 c么ng b峄?16/07; `tbl_ToSend2` 696 d貌ng (d峄玭g 28/11/2025), `WAITING` 57 d貌ng (ID/TIME r峄梟g 100%); ph谩t hi峄噉 b岷g th峄?4 `tbl_Waiting` (71 d貌ng) b峄?script coi "unshifted" nh瓢ng th岷璽 ra c农ng l峄嘽h c峄檛; JOIN-match 0% ch瓢a k岷縯 lu岷璶 膽瓢峄 mapping sai (do app.machines dev ch峄?c贸 5 m谩y test).
  - **P0-E Feedback loop:** x谩c nh岷璶 VBA g峄慶 KH脭NG c贸 h峄峜 t峄?膽峄檔g (c峄檛 feedback ch峄?ghi, kh么ng bao gi峄?膽峄峜 l岷 鈥?grep to脿n b峄?; Editor KB l脿 CRUD th峄?c么ng thu岷 t煤y 鈫?FIX-005 l脿 migrate 膽啤n gi岷 (size M), "h峄峜 t峄?膽峄檔g" l脿 t铆nh n膬ng m峄沬 ho脿n to脿n 膽峄?phase sau.
- **T脿i li峄噓 m峄沬 (m峄 5-7):** [`pilot-blockers.md`](file:///F:/DF/.claude/pilot-blockers.md) (7 pilot blockers PB-1鈫扨B-7 + danh s谩ch missing-kh么ng-ch岷穘 + danh s谩ch dead/deprecated), [`remediation-plan.md`](file:///F:/DF/.claude/remediation-plan.md) (FIX-001鈫扚IX-010 膽岷 膽峄?ph岷 vi/file/DB/migration/AC/regression/rollback/dependency/r峄 ro/estimate + tr矛nh t峄?th峄眂 hi峄噉 膽峄?xu岷 4 膽峄), b岷g 瓢u ti锚n h贸a 18 c峄 (Criticality/Pilot-Blocker/Source/Evidence/Action/Scope) b峄?sung cu峄慽 `vba-migration-matrix.md`.
- **T脿i li峄噓 c岷璸 nh岷璽:** `vba-migration-matrix.md` (膽铆nh ch铆nh s峄?li峄噓 + chu岷﹏ h贸a tr岷g th谩i + b岷g 瓢u ti锚n), `vba-version-comparison.md` (膽铆nh ch铆nh tblSync/tbl_Waiting), `risks-and-assumptions.md` (R-11 c岷璸 nh岷璽 theo d峄?li峄噓 th岷璽), `open-questions.md` (CH-TECH-003/004 c岷璸 nh岷璽 d峄?li峄噓 th岷璽; CH-TECH-006 膽茫 tr岷?l峄漣 1 ph岷), `source-traceability.md` (th锚m m峄 Ghi ch煤 truy v岷縯 b峄?sung 鈥?gi峄?nguy锚n c谩c d貌ng do phi锚n kh谩c th锚m v峄?Workstation).
- **D峄狽G L岷營 theo y锚u c岷** 鈥?ch峄?ng瓢峄漣 d霉ng duy峄噒 danh s谩ch pilot blockers + k岷?ho岷h FIX + tr岷?l峄漣 c谩c c芒u h峄廼 CH tr瓢峄沜 khi s峄璦 b岷 k峄?code n脿o.

### 16. 膼峄 duy峄噒 l岷 3 鈥?hi峄噓 ch峄塶h m么 h矛nh workstation theo c啤 c岷 v岷璶 h脿nh th岷璽 (6 m谩y nghi峄噋 v峄?, audit b峄?sung 2 workbook ch瓢a t峄玭g r脿 so谩t (CH漂A s峄璦 code)

- **B峄慽 c岷h:** Ng瓢峄漣 d霉ng x谩c nh岷璶 tr峄眂 ti岷縫 c啤 c岷 v岷璶 h脿nh th岷璽: **6 m谩y nghi峄噋 v峄?/ 5 workstation type** 鈥?1脳 CHEMICAL_CALL (`1.b谩o ph谩t AC X漂峄濶G -193.xlsm`), 1脳 PRODUCTION_ORDER (`2.C3 grid load row lock id FB -192(QR).xlsm`), 1脳 QR_LABEL_PRINTING (`3.DF028 ... jit qr sending - 15l special.xlsm`), 2脳 SMALL_SCALE (`4.semiauto-small scale ... DF026-027.xlsm`), 1脳 LARGE_SCALE (`5.Semiauto- lockmove SEND OVER6 ... -221.xlsm`) 鈥?thay cho gi岷?膽峄媙h 7-workstation d峄盿 thu岷 t煤y v脿o l峄媍h s峄?k岷縯 n峄慽 m岷g tr瓢峄沜 膽贸 (kh么ng c贸 x谩c nh岷璶 nghi峄噋 v峄?. Y锚u c岷 r玫: kh么ng t峄?g谩n workstation ri锚ng cho kh谩i ni峄噈 nghi峄噋 v峄?(h贸a ch岷/A11/DLG/v岷璶 chuy峄僴/t峄沬 th霉ng/c岷 m谩y) khi ch瓢a c贸 b岷眓g ch峄﹏g v岷璶 h脿nh th岷璽; ph芒n lo岷 UI theo A. MIGRATION PARITY / B. UX IMPROVEMENT / C. OPTIONAL EXTENSION; gi峄?nguy锚n to脿n b峄?lu峄搉g/n煤t/tr岷g th谩i/ngo岷 l峄?VBA khi thi岷縯 k岷?UI m峄沬; ch峄?ho脿n th脿nh khi m峄峣 procedure c峄 5 workbook 膽茫 ph芒n lo岷. V岷玭 ch瓢a s峄璦 code s岷 xu岷/migration/schema.
- **膼峄慽 chi岷縰 5 workbook x谩c nh岷璶 v峄沬 audit c农:** workbook 2 (PRODUCTION_ORDER) v脿 4/5 (SMALL_SCALE/LARGE_SCALE) 膽茫 膽瓢峄 audit 膽岷 膽峄?tr瓢峄沜 膽贸 (NH脫M 2 "C3", NH脫M 3 workbook B/C). Ph谩t hi峄噉 quan tr峄峮g: workbook 1 (CHEMICAL_CALL) v脿 workbook 3 (QR_LABEL_PRINTING/DF028) **ch瓢a t峄玭g 膽瓢峄 audit 峄?c岷 procedure** 鈥?audit PRINT tr瓢峄沜 膽贸 (83 d貌ng `VBA-PRINT-*`) th峄眂 ch岷 audit 2 workbook kh谩c (`in tem Copower.xlsm`, `QR PRINTER...`) kh么ng ph岷 m谩y in tem s岷 xu岷 th岷璽; `SEMI CHECKER.xlsm` (audit l脿 file A trong NH脫M 3) c农ng kh么ng n岷眒 trong 5 workbook x谩c nh岷璶.
- **Audit b峄?sung b岷眓g 2 agent song song (膽峄峜 code, kh么ng s峄璦):**
  - **NH脫M 0 (CHEMICAL_CALL, 16 d貌ng/44 procedure):** x谩c nh岷璶 **to脿n b峄?nghi峄噋 v峄?g峄峣/x谩c nh岷璶 c岷 h贸a ch岷 ch瓢a h峄?膽瓢峄 x芒y tr锚n web** 鈥?0 Controller/route/view; ch峄?c贸 Model t末nh `MachineChemicalChannel.php` kh么ng route n脿o d霉ng; b岷g 膽铆ch `app.machine_chemical_channels` 膽茫 di tr煤 xong 40/40 d貌ng c岷 h矛nh t末nh nh瓢ng KH脭NG c贸 c峄檛 l瓢u t铆n hi峄噓 ORDER/DONE 膽峄檔g (gi谩 tr峄?v岷璶 h脿nh th岷璽 h脿ng ng脿y) 鈥?di tr煤 "xong" ch峄?l脿 l峄沺 c岷 h矛nh. Workbook ch峄?ph峄?8/~18 m谩y 脳 2/~9 slot 鈥?kh岷?n膬ng c貌n workbook ch峄?em ch瓢a t矛m th岷.
  - **NH脫M 4-DF028 (QR_LABEL_PRINTING th岷璽, 51 d貌ng/308 procedure):** x谩c nh岷璶 DF028 l脿 **ngu峄搉 ghi (INSERT) duy nh岷 t矛m 膽瓢峄 cho `tbl_sentlog`** trong to脿n b峄?膽峄 audit (`TO_SEND.ConfirmRow`) 鈥?tr岷?l峄漣 c芒u h峄廼 m峄?CH-TECH-004 t峄搉 t岷 nhi峄乽 膽峄; ph谩t hi峄噉 `app.machine_dispatches` 膽茫 c贸 s岷祅 3 c峄檛 `scale_checked`/`raw_qr_dye`/`raw_qr_chemical` kh峄沺 g岷 1:1 v峄沬 DF028 nh瓢ng **0 controller n脿o 膽峄峜/ghi** (schema s岷祅 s脿ng, t岷g Controller b峄?b峄?s贸t); logic ph芒n v霉ng kho B24 + ch峄峮 1-trong-3 ch岷?膽峄?m茫 h贸a QR theo t峄?h峄 Machine脳Tank (`Mod_printslip.PrintSlip_70x100`) 鈥?kh峄慽 nghi峄噋 v峄?ph峄ヽ t岷 nh岷, ch瓢a t峄玭g 膽瓢峄 nh岷痗 峄?audit n脿o tr瓢峄沜, kh么ng c贸 t瓢啤ng 膽瓢啤ng backend; l瓢峄沬 gi谩m s谩t t峄搉 膽峄峮g 18脳9 t么 m脿u theo tu峄昳 d峄?li峄噓 (162 procedure) ho脿n to脿n MISSING; h脿nh vi "in tem = t峄?膽峄檔g x谩c nh岷璶 scale-check" MISSING. `api.qrserver.com` (vi ph岷 CLAUDE.md) x谩c nh岷璶 t峄搉 t岷 膽峄搉g th峄漣 峄?鈮? workbook s岷 xu岷 song song. T锚n file tr霉ng g岷 nh瓢 ho脿n to脿n v峄沬 2 file P0 t峄玭g li峄噒 k锚 thi岷縰 (`DF002...15l special-27rows.xlsm`, `DF002 no formulas...jit qr sending...xlsm`) 鈥?kh岷?n膬ng cao 膽茫 膽贸ng 膽瓢峄 c谩c m峄 thi岷縰 膽贸, c岷 ng瓢峄漣 d霉ng x谩c nh岷璶.
  - 5 d貌ng dual-status ph谩t sinh khi bi锚n t岷璸 b岷g NH脫M 4-DF028 膽茫 膽瓢峄 t谩ch th脿nh 10 d貌ng 膽啤n-status 膽峄?gi峄?膽煤ng quy 瓢峄沜 "1 d貌ng = 1 tr岷g th谩i".
- **S峄?li峄噓 t峄昻g c岷璸 nh岷璽 (ki峄僲 ch峄﹏g PASS b岷眓g `verify-matrix-counts.sh`):** t峄昻g d貌ng traceability t峄?355 鈫?**422**; t峄昻g procedure v岷璽 l媒 t峄?664 鈫?**1016** (quy 瓢峄沜 膽岷縨 l岷穚) ho岷穋 t峄?561 鈫?**913** (quy 瓢峄沜 dedup).
- **T脿i li峄噓 c岷璸 nh岷璽:**
  - [`workstation-matrix.md`](file:///F:/DF/.claude/workstation-matrix.md): vi岷縯 l岷 ho脿n to脿n theo m么 h矛nh 6 m谩y 膽茫 x谩c nh岷璶; b岷g 膽峄慽 chi岷縰 7 IP l峄媍h s峄?m岷g (ch瓢a kh峄沺 h岷縯 v峄沬 6 m谩y 鈥?thi岷縰 IP n脿o g谩n CHEMICAL_CALL); b岷g Workstation鈫擶orkbook鈫擴serForm鈫擜PI/DB/Test theo 膽煤ng y锚u c岷; m峄 ri锚ng li峄噒 k锚 c谩c "workstation" vi岷縯 m峄沬 kh么ng c贸 b岷眓g ch峄﹏g v岷璶 h脿nh (V岷璶 chuy峄僴/T峄沬 th霉ng/C岷 m谩y) v脿 RECIPE/TROUBLESHOOTING (kh么ng r玫 c贸 g岷痭 m谩y v岷璽 l媒 c峄?膽峄媙h).
  - [`legacy-to-target-architecture.md`](file:///F:/DF/.claude/legacy-to-target-architecture.md): s峄璦 tr瓢峄漬g Workstation cho c岷?9 b瓢峄沜 theo c啤 c岷 6 m谩y; g岷痭 nh茫n A/B/C cho t峄玭g m峄 Tr岷g th谩i ho脿n thi峄噉; B瓢峄沜 6/7/8 (V岷璶 chuy峄僴/T峄沬 th霉ng/C岷 m谩y) 膽峄昳 nh茫n t峄?"[NEW] Ho脿n th脿nh 100%" sang r玫 r脿ng "C. OPTIONAL EXTENSION 鈥?kh么ng c贸 b岷眓g ch峄﹏g v岷璶 h脿nh".
  - [`system-context.md`](file:///F:/DF/.claude/system-context.md): thay "7 M谩y tr岷 th峄眂 t岷? b岷眓g "6 M谩y Nghi峄噋 v峄?Th峄眂 t岷?, gi峄?7 IP l峄媍h s峄?l脿m ph峄?l峄 tham chi岷縰.
  - [`vba-migration-matrix.md`](file:///F:/DF/.claude/vba-migration-matrix.md): th锚m NH脫M 0 v脿 NH脫M 4-DF028 膽岷 膽峄?(ki峄僲 k锚 module/procedure/d峄?li峄噓 Access/ph芒n lo岷 A-B-C); vi岷縯 l岷 "T峄昻g h峄 s峄?li峄噓" v峄沬 7 c峄檛 theo nh贸m; th锚m 2 ph谩t hi峄噉 nghi锚m tr峄峮g m峄沬 v脿o 膽岷 danh s谩ch.
  - [`pilot-blockers.md`](file:///F:/DF/.claude/pilot-blockers.md): th锚m PB-8 (CHEMICAL_CALL ch瓢a x芒y g矛) v脿 PB-9 (4 kho岷g tr峄憂g DF028); th锚m 3 d貌ng Danh s谩ch 2; c岷璸 nh岷璽 Danh s谩ch 3 (75 DEAD_CODE_CANDIDATE, th锚m CHEM脳3 v脿 QRPRINT脳5).
  - [`source-files-missing.md`](file:///F:/DF/.claude/source-files-missing.md): c岷h b谩o DF028 c贸 th峄?膽茫 膽贸ng 膽瓢峄 2/5 file P0 nh贸m PRINT, ch峄?x谩c nh岷璶 ng瓢峄漣 d霉ng.
  - [`risks-and-assumptions.md`](file:///F:/DF/.claude/risks-and-assumptions.md): th锚m R-12 (CHEMICAL_CALL), R-13 (4 kho岷g tr峄憂g DF028); c岷璸 nh岷璽 R-11 (膽茫 t矛m ngu峄搉 th岷璽 `tbl_sentlog`).
  - [`open-questions.md`](file:///F:/DF/.claude/open-questions.md): th锚m CH-BUS-009 (膽峄慽 chi岷縰 7 IP v峄沬 6 m谩y), CH-BUS-010 (RECIPE/TROUBLE c贸 ph岷 workstation v岷璽 l媒 kh么ng).
  - [`source-traceability.md`](file:///F:/DF/.claude/source-traceability.md): th锚m m峄 ghi ch煤 b峄?sung m峄沬 tr峄?t峄沬 c谩c thay 膽峄昳 tr锚n.
- **D峄狽G L岷營 theo y锚u c岷** 鈥?ch瓢a s峄璦 code s岷 xu岷, ch瓢a ch岷 migration, ch瓢a 膽峄昳 schema. Ch峄?ng瓢峄漣 d霉ng x谩c nh岷璶 CH-BUS-009/010 v脿 c谩c c芒u h峄廼 nghi峄噋 v峄?m峄沬 (膽岷穋 bi峄噒 logic ph芒n v霉ng kho B24, ph岷 vi pilot c贸 g峄搈 CHEMICAL_CALL/QR_LABEL_PRINTING hay kh么ng) tr瓢峄沜 khi thi岷縯 k岷?UI/backend chi ti岷縯.

### 17. 膼峄 duy峄噒 l岷 4 鈥?database discovery 膽岷 膽峄?(5 Access DB), gap analysis domain CHEMICAL_CALL/QR_LABEL_PRINTING, truy v岷縯 tbl_SentLog, logic B24, so s谩nh SMALL/LARGE_SCALE, ki岷縩 tr煤c Local Agent (CH漂A s峄璦 code)

- **B峄慽 c岷h:** Ng瓢峄漣 d霉ng y锚u c岷 t峄?ch峄ヽ l岷 d峄?谩n theo chu峄梚 v岷璶 h脿nh th峄眂 t岷?7 b瓢峄沜 (g峄峣 h贸a ch岷 鈫?t岷 膽啤n 鈫?nh岷璶 膽啤n/in tem 鈫?c芒n nh峄?l峄沶 鈫?ghi nh岷璶 ho脿n th脿nh 鈫?truy v岷縯 xuy锚n su峄憈), kh么ng ch峄?b峄?sung v脿i m脿n h矛nh. Y锚u c岷 1 膽峄 gap analysis m峄沬 d峄盿 tr锚n to脿n b峄?VBA + 4 database Access (`chem_order.accdb`, `RECORD.accdb`, `RECORD1.accdb`, `WH.accdb`) + source code web hi峄噉 t岷, 膽岷穋 bi峄噒 **kh么ng 膽瓢峄 coi `RECORD.accdb`/`RECORD1.accdb` l脿 c霉ng 1 database ch峄?v矛 tr霉ng t锚n**. V岷玭 thu岷 t脿i li峄噓/thi岷縯 k岷?鈥?kh么ng s峄璦 code s岷 xu岷, kh么ng migration, kh么ng 膽峄昳 schema, kh么ng b岷璽 t铆nh n膬ng m峄沬 cho ng瓢峄漣 d霉ng.
- **Database discovery (Phase A ho脿n t岷):** copy read-only 5 file `.accdb`, tr铆ch xu岷 schema 膽岷 膽峄?(b岷g/c峄檛/ki峄僽/PK/index/s峄?d貌ng) qua DAO/COM, l岷 m岷玼 d峄?li峄噓 th岷璽 qua `OpenRecordset`. K岷縯 qu岷?鈥?[`database-inventory.md`](file:///F:/DF/.claude/database-inventory.md):
  - `RECORD.accdb` (**RECORD_A**) ch峄゛ `tbl_SentLog` (27.024 d貌ng, m峄沬 nh岷 2026-07-15), `tbl_ToSend`/`tbl_ToSend2`/`WAITING`/`tbl_Waiting`/`TBL_INPUT_ALL`/`tblSync`/`tbl_ARCHIVE`/`tbl_OUTPUT_PROCESSING` 鈥?**膽芒y ch铆nh l脿 database dispatch/queue/s峄?g峄璱 h脿ng 膽茫 t矛m ki岷縨 nhi峄乽 膽峄 tr瓢峄沜 m脿 kh么ng th岷** (膽峄 audit 16-17/07 tr瓢峄沜 k岷縯 lu岷璶 nh岷 "kh么ng c贸 m岷穞 t岷 F:\DF").
  - `RECORD1.accdb` (**RECORD_B**) ch峄゛ `tblRECORD` (140.655 d貌ng, m峄沬 nh岷 2026-07-15) + `tblRECORD_chem` (5.061 d貌ng) 鈥?膽芒y m峄沬 l脿 file tr瓢峄沜 膽芒y 膽瓢峄 g峄峣 nh岷 l脿 "RECORD.accdb" trong 膽峄 audit g峄慶.
  - `chem_order.accdb` (**CHEM_ORDER**) ngo脿i `tbl_status` (40 d貌ng, 膽茫 bi岷縯) c貌n c贸 `tblRECORD`/`tblRECORD_chem` ri锚ng (47.381/1.500 d貌ng, d峄?li峄噓 d峄玭g 峄?2026-03-31) 鈥?c霉ng schema RECORD_B nh瓢ng KH脭NG c贸 Sub/Function n脿o trong `chem_order.frm` ch岷 t峄沬 鈥?nghi v岷 backup t末nh b峄?b峄?qu锚n (CH-BUS-014).
  - `WH.accdb` (**WAREHOUSE**) ch峄?c贸 1 b岷g `tblWH_LOG` (35 d貌ng, log ti锚u th峄? 鈥?**kh么ng c贸 b岷g mapping v霉ng kho/B24** n脿o.
  - **B岷眓g ch峄﹏g 膽瓢峄漬g d岷玭 VBA (grep tr峄眂 ti岷縫 source, kh么ng suy 膽o谩n):** workbook 2 (C3) v脿 3 (DF028) hard-code `Z:\DF\DATA\record.accdb` 鈫?RECORD_A; workbook 4/5 (SCALE) hard-code `Z:DF_SCALE\RECORD.accdb` (thi岷縰 `\`) + `Z:\DF_SCALE\WH.accdb` 鈫?RECORD_B + WAREHOUSE; workbook 1 (chem_order) hard-code `Z:\chem_order\chem_order.accdb` 鈫?CHEM_ORDER. K岷縯 lu岷璶: 2 database "RECORD" **ho脿n to脿n 膽峄檆 l岷璸, kh么ng 膽峄搉g b峄?tr峄眂 ti岷縫, kh么ng b岷g n脿o tr霉ng t锚n** 鈥?xem [`legacy-database-mapping.md`](file:///F:/DF/.claude/legacy-database-mapping.md).
- **Truy v岷縯 `tbl_SentLog` (m峄 6 y锚u c岷):** b岷g mapping 膽岷 膽峄?VBA鈫擜ccess鈫擶eb t岷 `qr-label-printing-domain.md` M峄 1 鈥?x谩c nh岷璶 l岷 (b岷眓g ch峄﹏g schema c峄檛-theo-c峄檛) `DF028.TO_SEND.ConfirmRow` l脿 ngu峄搉 ghi (INSERT) duy nh岷; 17 c峄檛 `tbl_SentLog` kh峄沺 g岷 nh瓢 tuy峄噒 膽峄慽 v峄沬 `app.machine_dispatches` 膽茫 thi岷縯 k岷?s岷祅 (`scale_checked`/`raw_qr_dye`/`raw_qr_chemical`).
- **Logic B24 (m峄 9):** 膽峄峜 to脿n b峄?`Mod_printslip.PrintSlip_70x100` (395 d貌ng) + tr铆ch xu岷 100 c么ng th峄ヽ Excel c峄 DF028 b岷眓g `openpyxl` 鈥?d峄眓g 膽岷 膽峄?b岷g quy岷縯 膽峄媙h B24/mode-QR/D1 t岷 [`b24-warehouse-routing.md`](file:///F:/DF/.claude/b24-warehouse-routing.md). Ph谩t hi峄噉: (1) l峄?h峄昻g c贸 s岷祅 trong VBA 鈥?t峄?h峄 VD14-16+3C/4D kh么ng c贸 nh茫n D1 (khe h峄?gi峄痑 2 nh谩nh If); (2) **kh么ng t矛m th岷 nh谩nh code ri锚ng cho "15L special"** 峄?c岷?VBA l岷玭 c么ng th峄ヽ Excel 鈥?2 膽i峄僲 n脿y 膽谩nh d岷 `BLOCKED_BY_BUSINESS_CONFIRMATION`, kh么ng t峄?suy di峄卬.
- **Gap report CHEMICAL_CALL v脿 QR_LABEL_PRINTING (m峄 4-5):** [`chemical-call-domain.md`](file:///F:/DF/.claude/chemical-call-domain.md) 鈥?t谩ch d峄?li峄噓 c岷 h矛nh t末nh kh峄廼 d峄?li峄噓 v岷璶 h脿nh ORDER/DONE, 膽峄?xu岷 entity `chemical_call_requests`/`chemical_call_request_events`, b岷g ch峄ヽ n膬ng theo taxonomy 6 tr岷g th谩i m峄沬 (IMPLEMENTED/PARTIALLY_IMPLEMENTED/REPLACED_BY_PLATFORM/NOT_REQUIRED_CONFIRMED/BLOCKED/MISSING). [`qr-label-printing-domain.md`](file:///F:/DF/.claude/qr-label-printing-domain.md) 鈥?lu峄搉g 11 b瓢峄沜, 膽峄?xu岷 service t谩ch kh峄廼 Controller (`QrPayloadService`/`PrintJobService`/`SentLogService`...), 膽峄慽 chi岷縰 2 file P0 t峄玭g "thi岷縰" 鈫?chuy峄僴 `PARTIALLY_RESOLVED` (kh么ng t峄?膽贸ng `RESOLVED` v矛 nh谩nh 15L ch瓢a x谩c minh 膽瓢峄).
- **So s谩nh SMALL_SCALE vs LARGE_SCALE (m峄 8):** [`local-agent-architecture.md`](file:///F:/DF/.claude/local-agent-architecture.md) M峄 1 鈥?90% logic l玫i gi峄憂g h峄噒 100% (膽峄峜 c芒n, l脿m s岷h, delta, tolerance, chuy峄僴 rack) 鈫?d霉ng chung core h峄 l媒; 2 kh谩c bi峄噒 th岷璽 膽峄乽 l脿 BUG c峄 LARGE_SCALE (m脿u ACCEPTED/REJECTED sai khi岷縩 lu么n REJECTED 鈥?R-10 c农; r貌 r峄?timer `Mod_lockmoveform`) 鈥?kh么ng copy bug khi migrate, d霉ng b岷 膽茫 v谩 c峄 SMALL_SCALE l脿m chu岷﹏ chung. Ch瓢a t矛m th岷 kh谩c bi峄噒 ng瓢峄g kg trong code 鈥?kh岷?n膬ng cao l脿 膽岷穋 t铆nh thi岷縯 b峄?v岷璽 l媒, kh么ng ph岷 software policy.
- **Ki岷縩 tr煤c Local Agent + feature flag (m峄 8.1, 11):** 膽峄?xu岷 ScaleAgent (ScaleCore d霉ng chung + Policy ri锚ng theo workstation type), PrintAgent (5 tr岷g th谩i job, kh么ng d霉ng RPA chu峄檛/clipboard nh瓢 VBA), 8 feature flag 膽峄?xu岷 (`chemical_call_enabled`...`local_scale_agent_enabled`) 鈥?kh么ng hard-code ph岷 vi pilot.
- **K峄媍h b岷 pilot E2E (m峄 11):** [`pilot-end-to-end-scenarios.md`](file:///F:/DF/.claude/pilot-end-to-end-scenarios.md) 鈥?7 k峄媍h b岷 (happy path, lock tranh ch岷, agent m岷 m岷g, printer fail/retry, scan QR 2 l岷, chemical call 2 thao t谩c g岷 nhau, shadow mode 膽峄慽 so谩t). C岷璸 nh岷璽 `pilot-blockers.md`: PB-8/PB-9 nay l脿 pilot blocker TH岷琓 S峄?(kh么ng c貌n 膽i峄乽 ki峄噉) v矛 ph岷 vi pilot ch岷痗 ch岷痭 g峄搈 CHEMICAL_CALL + QR_LABEL_PRINTING.
- **T脿i li峄噓 m峄沬 t岷 (8 file theo y锚u c岷):** `database-inventory.md`, `legacy-database-mapping.md`, `chemical-call-domain.md`, `qr-label-printing-domain.md`, `b24-warehouse-routing.md`, `local-agent-architecture.md`, `pilot-end-to-end-scenarios.md`. *(8 file y锚u c岷 鈥?th峄眂 t岷?7 file m峄沬, v矛 n峄檌 dung `target-data-model.md` l脿 c岷璸 nh岷璽 file 膽茫 c贸 s岷祅 thay v矛 t岷 m峄沬, theo 膽煤ng t锚n file 膽茫 t峄搉 t岷 t峄?tr瓢峄沜).*
- **T脿i li峄噓 c岷璸 nh岷璽:** `target-data-model.md` (m峄 2.X 鈥?3 b岷g m峄沬 膽峄?xu岷, CH漂A migration), `workstation-matrix.md`/`legacy-to-target-architecture.md`/`system-context.md`/`vba-migration-matrix.md` (ghi ch煤 tham chi岷縰 + taxonomy m峄沬), `pilot-blockers.md`, `source-files-missing.md` (2 file P0 鈫?`PARTIALLY_RESOLVED`), `risks-and-assumptions.md` (R-14, R-15), `open-questions.md` (CH-BUS-011 鈫?CH-BUS-014), `source-traceability.md`.
- **S峄?li峄噓 traceability kh么ng 膽峄昳** (422 d貌ng, ki峄僲 ch峄﹏g PASS `verify-matrix-counts.sh`) 鈥?2 domain gap report m峄沬 d霉ng taxonomy 6 gi谩 tr峄?ri锚ng, kh么ng ph谩 v峄?s峄?li峄噓 9-tr岷g-th谩i 膽茫 ki峄僲 ch峄﹏g c峄 `vba-migration-matrix.md`.
- **D峄狽G L岷營 theo y锚u c岷** 鈥?ch瓢a migration production, ch瓢a x贸a b岷g/c峄檛, ch瓢a 膽峄昳 d峄?li峄噓 th岷璽, ch瓢a b岷璽 t铆nh n膬ng m峄沬 cho ng瓢峄漣 d霉ng, ch瓢a 膽贸ng c芒u h峄廼 n脿o thi岷縰 b岷眓g ch峄﹏g, kh么ng d霉ng l岷 gi岷?膽峄媙h 7 workstation. Ch峄?ng瓢峄漣 d霉ng x谩c nh岷璶 vai tr貌 2 database RECORD (膽茫 c贸 b岷眓g ch峄﹏g m岷h, ch峄?x谩c nh岷璶 ch铆nh th峄ヽ 膽峄昳 t锚n), logic B24 (15L + l峄?h峄昻g VD14-16), v脿 v貌ng 膽峄漣 `tbl_SentLog` tr瓢峄沜 khi b岷痶 膽岷 s峄璦 code.
### 18. Phase C 鈥?Target Design v脿 Phase D 鈥?Schema Proposal (2026-07-17)

- **Nhi峄噈 v峄?** Ho脿n th脿nh thi岷縯 k岷?chi ti岷縯 c岷 domain, c啤 s峄?d峄?li峄噓 v岷璽 l媒/logic, state machine, API, v脿 c谩c ch铆nh s谩ch nghi峄噋 v峄?an to脿n. 膼芒y l脿 t脿i li峄噓 thi岷縯 k岷?鈥?kh么ng s峄璦 code s岷 xu岷, kh么ng ch岷 migration.
- **T脿i li峄噓 t岷 m峄沬 (8 file):**
  - [`permission-matrix.md`](file:///F:/DF/.claude/permission-matrix.md): Ph芒n quy峄乶 chi ti岷縯, c么 l岷璸 t脿i kho岷 c峄 Local Agent.
  - [`feature-flags.md`](file:///F:/DF/.claude/feature-flags.md): Qu岷 l媒 t铆nh n膬ng 膽峄檔g, c岷 h矛nh 3 ch岷?膽峄?ch岷 B24 (`LEGACY_EXACT`, `FIXED_D1`, `MANUAL_REVIEW`).
  - [`migration-plan.md`](file:///F:/DF/.claude/migration-plan.md): L峄?tr矛nh 5 wave di tr煤 (Foundation, Chemical Call, Dispatch/QR, Weighing, Correlation).
  - [`backfill-plan.md`](file:///F:/DF/.claude/backfill-plan.md): Quy tr矛nh dry-run, 膽峄慽 so谩t tr峄峮g l瓢峄g b峄檛 m脿u (`SUM`), b谩o c谩o l峄梚 kh么ng b峄?s贸t b岷 ghi.
  - [`cutover-rollback-plan.md`](file:///F:/DF/.claude/cutover-rollback-plan.md): Chuy峄僴 膽峄昳 10 giai 膽o岷, rollback cho t峄玭g lo岷 m谩y tr岷 v脿 膽峄慽 so谩t sau rollback.
  - [`test-architecture.md`](file:///F:/DF/.claude/test-architecture.md): Ki峄僲 th峄?edge cases (double scan, double confirm, m岷 response, correlation exception, large scale timer leak).
  - [`decision-records.md`](file:///F:/DF/.claude/decision-records.md): Nh岷璽 k媒 quy岷縯 膽峄媙h nghi峄噋 v峄?(ADR) cho 4 blocker `CH-BUS-011` 膽岷縩 `CH-BUS-014`.
  - [`record-a-record-b-correlation.md`](file:///F:/DF/.claude/record-a-record-b-correlation.md): Ph瓢啤ng th峄ヽ kh峄沺 exact, composite, probabilistic v脿 exception queue.
- **T脿i li峄噓 c岷璸 nh岷璽:**
  - `legacy-database-mapping.md`: Ph芒n lo岷 `chem_order.accdb.tblRECORD` th脿nh `LEGACY_ARCHIVE` (blocker `CH-BUS-014` / `UNKNOWN_BLOCKED`).
  - `verify-matrix-counts.sh`: B峄?sung 5 tr岷g th谩i m峄沬 (`TARGET_DESIGNED`, `SCHEMA_PROPOSED`, `BLOCKED`, `NOT_REQUIRED_CONFIRMED`, `LEGACY_BUG_NOT_MIGRATED`), s峄璦 l峄梚 `set -e` crash khi count = 0.
  - `vba-migration-matrix.md`: Thay 膽峄昳 tr岷g th谩i 13 d貌ng Chemical Call (`VBA-CHEM-003` 膽岷縩 `VBA-CHEM-016` tr峄?m峄?c么i) t峄?`MISSING` sang `SCHEMA_PROPOSED`.
  - `target-data-model.md` (M峄 2.X): Li峄噒 k锚 膽岷 膽峄?c谩c b岷g m峄沬 膽茫 thi岷縯 k岷?v岷璽 l媒 trong ERD.
  - `pilot-blockers.md` & `risks-and-assumptions.md` & `open-questions.md`: C岷璸 nh岷璽 ghi ch煤 Phase C/D ho脿n t岷 thi岷縯 k岷?chi ti岷縯 膽峄?kh岷痗 ph峄 r峄 ro v脿 c谩c blocker.
- **Ki峄僲 ch峄﹏g t峄?膽峄檔g:** Ch岷 `verify-matrix-counts.sh` PASS 100% (ROWS=422, UNMATCHED=0).
- **D峄狽G L岷營:** Kh么ng s峄璦 code s岷 xu岷, kh么ng ch岷 migration, s岷祅 s脿ng chuy峄僴 giao t脿i li峄噓 thi岷縯 k岷?Phase C/D cho Dev tri峄僴 khai 峄?Phase E.

### 18. Ki岷縩 tr煤c Menu V岷璶 h脿nh theo Workstation Type + Qu岷 l媒 thi岷縯 b峄?theo Workstation Instance (Phase C ti岷縫 n峄慽, CH漂A s峄璦 code)

- **B峄慽 c岷h:** Y锚u c岷 Dev c峄?th峄?h贸a m么 h矛nh 3 t岷g Workstation Type 鈫?Workstation Instance 鈫?Device cho menu v岷璶 h脿nh v脿 qu岷 l媒 m谩y in/c芒n, thay cho vi峄嘽 t峄?ch峄ヽ menu theo t峄玭g ch峄ヽ n膬ng/m谩y v岷璽 l媒 r峄漣 r岷 ho岷穋 hard-code theo IP/t锚n m谩y. 膼芒y l脿 ph岷 m峄?r峄檔g chi ti岷縯 c峄 domain "Workstation & Device Management" 膽茫 ph谩c th岷 s啤 b峄?峄?`domain-architecture.md` M峄 1.1 trong 膽峄 Phase C tr瓢峄沜.
- **T脿i li峄噓 m峄沬:** [`menu-workstation-device-architecture.md`](file:///F:/DF/.claude/menu-workstation-device-architecture.md) 鈥?menu 5 workstation type c峄?膽峄媙h; gi岷 th铆ch 3 t岷g Type鈫扞nstance鈫扗evice v峄沬 v铆 d峄?SMALL_SCALE (1 module qu岷 l媒 2 instance 膽峄檆 l岷璸); ch峄憂g anti-pattern hard-code IP/t锚n m谩y (lu峄搉g resolve 膽煤ng: session鈫抴orkstation_id鈫抰ype鈫抎evice_binding鈫抪ermission鈫抐eature_flag); lu峄搉g t峄?膽峄檔g load Printer/Template/Agent khi m峄?m脿n h矛nh v岷璶 h脿nh (kh么ng h峄廼 l岷 ng瓢峄漣 d霉ng); giao di峄噉 Admin `/admin/workstations`; b岷g mapping VBA鈫扺eb (Computer name/IP/Windows Printer/COM port/UserForm/Button event/Module VBA/Excel config 鈫?workstation/device attribute/printers/scale_devices/Vue page/API action/Service/DB config); nh岷痗 l岷 danh s谩ch l峄梚 legacy kh么ng migrate; b峄?sung test case `WorkstationDeviceIsolationTest` (2 m谩y SMALL_SCALE kh么ng nh岷璶 nh岷 d峄?li峄噓 c峄 nhau); ti锚u ch铆 nghi峄噈 thu (ch瓢a th峄眂 thi, ch峄?thi岷縯 k岷?.
- **C岷璸 nh岷璽 `erd-target.md` M峄 2.1:** b峄?sung schema v岷璽 l媒 膽岷 膽峄?cho qu岷 l媒 thi岷縯 b峄?鈥?`app.workstation_devices` (mapping N-N c贸 `role`), `app.printers`, `app.printer_profiles`, `app.workstation_printers` (mapping workstation鈫攑rinter鈫攖emplate m岷穋 膽峄媙h, c贸 `priority` cho printer d峄?ph貌ng), `app.scale_devices` (thay hard-code COM/baud rate) 鈥?d霉ng l岷 `app.workstation_devices` cho mapping scale thay v矛 t岷 b岷g mapping song song th峄?2.
- **C岷璸 nh岷璽 `domain-architecture.md`:** th锚m tham chi岷縰 ch茅o t峄沬 t脿i li峄噓 m峄沬 峄?M峄 1.1, tr谩nh tr霉ng l岷穚 n峄檌 dung schema.
- **Tr岷g th谩i c谩c h岷g m峄 Phase C/D kh谩c** (膽茫 l岷璸 峄?膽峄 tr瓢峄沜 trong phi锚n n脿y: `domain-architecture.md`, `erd-target.md`, `state-machines.md`, `api-contracts.md`, `local-agent-architecture.md` m峄?r峄檔g contract M峄 4) 鈥?**c貌n 膽峄?ng峄? ch瓢a th峄眂 hi峄噉 trong 膽峄 n脿y:** `permission-matrix.md`, `feature-flags.md` (file ri锚ng 鈥?hi峄噉 flag list n岷眒 r岷 r谩c trong `local-agent-architecture.md`/`menu-workstation-device-architecture.md` M峄 12), `migration-plan.md`, `backfill-plan.md`, `cutover-rollback-plan.md`, `test-architecture.md` (h峄 nh岷 鈥?hi峄噉 test case n岷眒 r岷 r谩c trong `state-machines.md`/`menu-workstation-device-architecture.md` M峄 15/`api-contracts.md`), `decision-records.md` (ADR cho CH-BUS-011鈫?14), `record-a-record-b-correlation.md`.
- **Ki峄僲 ch峄﹏g:** `verify-matrix-counts.sh` 鈫?PASS (422/422, kh么ng 膽峄昳 v矛 kh么ng ch岷 `vba-migration-matrix.md`).
- **D峄狽G L岷營 theo y锚u c岷** 鈥?ch瓢a s峄璦 code s岷 xu岷, ch瓢a migration, ch瓢a 膽峄昳 schema th岷璽, ch瓢a b岷璽 agent/g峄璱 l峄噉h in/k岷縯 n峄慽 c芒n th岷璽.

### 19. Ho脿n t岷 Phase C/D 鈥?8 t脿i li峄噓 c貌n thi岷縰, 膽峄慽 chi岷縰 ch茅o, c岷璸 nh岷璽 tr岷g th谩i thi岷縯 k岷?theo c峄 (CH漂A s峄璦 code)

- **B峄慽 c岷h:** Ho脿n th脿nh to脿n b峄?8 h岷g m峄 c貌n thi岷縰 c峄 Phase C/D (permission-matrix, feature-flags, migration-plan, backfill-plan, cutover-rollback-plan, test-architecture, decision-records, record-a-record-b-correlation) 鈥?c岷?8 file 膽茫 t峄搉 t岷 d岷g nh谩p (do phi锚n/c么ng c峄?kh谩c t岷 s岷祅), nhi峄噈 v峄?ch铆nh l脿 r脿 so谩t, b峄?sung ph岷 thi岷縰 theo 膽煤ng y锚u c岷 chi ti岷縯, v脿 s峄璦 c谩c 膽i峄僲 m芒u thu岷玭/sai l峄嘽h ph谩t hi峄噉 膽瓢峄.
- **L峄梚/m芒u thu岷玭 quan tr峄峮g 膽茫 ph谩t hi峄噉 v脿 s峄璦 (膽峄慽 chi岷縰 ch茅o m峄 10):**
  - `decision-records.md` ADR CH-BUS-014 ghi "膼贸ng blocker" 鈥?**vi ph岷 nguy锚n t岷痗 kh么ng t峄?膽贸ng blocker khi ch瓢a 膽峄?b岷眓g ch峄﹏g** 鈥?膽茫 s峄璦 l岷: gi峄?`UNKNOWN_BLOCKED`, ch峄?ghi nh岷璶 `LEGACY_ARCHIVE` l脿 ph芒n lo岷 k峄?thu岷璽 t岷, kh峄沺 膽煤ng v峄沬 `legacy-database-mapping.md`.
  - `migration-plan.md`/`backfill-plan.md` c貌n gi峄?s峄?li峄噓 c农 "140.660 d貌ng tblRECORD" v脿 y锚u c岷 "Compact & Repair" cho `tbl_SentLog` 鈥?c岷?2 膽峄乽 l峄梚 th峄漣 so v峄沬 s峄?li峄噓 th岷璽 膽茫 x谩c nh岷璶 (140.655 d貌ng; `tbl_SentLog` 膽峄峜 膽瓢峄 膽岷 膽峄?27.024 d貌ng, kh么ng c岷 s峄璦 file) 鈥?膽茫 s峄璦 c岷?2 file.
  - `migration-plan.md` Wave 1 thi岷縰 c谩c b岷g `workstation_devices`/`printer_profiles`/`workstation_printers`/`scale_devices`/`device_credentials` (b峄?sung sau khi c贸 `menu-workstation-device-architecture.md`) 鈥?膽茫 c岷璸 nh岷璽 膽岷 膽峄?
  - `backfill-plan.md` c贸 heuristic t峄?b峄媋 "kh峄慽 l瓢峄g >5kg 鈫?LARGE_SCALE" 膽峄?suy lu岷璶 workstation cho d峄?li峄噓 c芒n l峄媍h s峄?鈥?vi ph岷 nguy锚n t岷痗 "kh么ng t峄?g谩n sai" 鈥?膽茫 s峄璦: m峄峣 d貌ng l峄媍h s峄?`tblRECORD` map v峄沬 `workstation_id=NULL`, 膽谩nh d岷 r玫 gi峄沬 h岷 d峄?li峄噓 ngu峄搉, kh么ng suy 膽o谩n.
  - `erd-target.md` b峄?sung ghi ch煤 l脿m r玫 t锚n kh谩i ni峄噈 (Logical ERD: `DISPATCH_JOB`) kh谩c t锚n b岷g v岷璽 l媒 (`app.machine_dispatches`) 鈥?tr谩nh hi峄僽 nh岷 2 ngu峄搉 s峄?th岷璽.
- **B峄?sung n峄檌 dung c貌n thi岷縰 theo y锚u c岷 chi ti岷縯:** `permission-matrix.md` (5 Operation Mode, danh s谩ch permission 膽岷 膽峄? backend enforcement theo t岷g route/middleware/service); `feature-flags.md` (4 flag c貌n thi岷縰, quy t岷痗 瓢u ti锚n resolve 4 c岷, h脿nh vi khi OFF kh么ng ch峄?岷﹏ n煤t); `cutover-rollback-plan.md` (th峄?t峄?cutover 6 b瓢峄沜 c贸 gi岷 th铆ch ph峄?thu峄檆, b岷g rollback 膽岷 膽峄?9 tr瓢峄漬g/workstation, 膽谩nh gi谩 r峄 ro dual-write 6 ti锚u ch铆); `test-architecture.md` (11 test case b峄?sung cho 膽峄?23 k峄媍h b岷 y锚u c岷, ph芒n lo岷 test data 5 lo岷, test isolation 2 SMALL_SCALE 8 k峄媍h b岷, coverage + VBA鈫抰est mapping); `record-a-record-b-correlation.md` (t谩ch `AMBIGUOUS` kh峄廼 `PROBABILISTIC`, 膽峄?6 gi谩 tr峄?ph芒n lo岷, 膽岷 膽峄?tr瓢峄漬g evidence, Exception Queue API).
- **C岷璸 nh岷璽 `vba-migration-matrix.md`:** th锚m "B岷G TR岷燦G TH脕I THI岷綯 K岷?PHASE C/D THEO C峄" (b岷g m峄沬, kh么ng 膽峄昳 422 d貌ng chi ti岷縯) 鈥?谩p d峄g taxonomy 6 gi谩 tr峄?m峄沬 (`TARGET_DESIGNED`/`SCHEMA_PROPOSED`/`TEST_DESIGNED`/`BLOCKED`/`NOT_REQUIRED_CONFIRMED`/`LEGACY_BUG_NOT_MIGRATED`) cho 12 c峄/domain, li锚n k岷縯 Domain/Entity/API/Permission/Feature Flag/Migration Wave/Test Case/ADR 鈥?**kh么ng 膽谩nh d岷 procedure n脿o l脿 IMPLEMENTED**.
- **膼峄慽 chi岷縰 ch茅o 膽茫 th峄眂 hi峄噉:** table naming (machine_dispatches vs "dispatch_jobs" kh谩i ni峄噈 鈥?x谩c nh岷璶 nh岷 qu谩n, ch峄?1 ngu峄搉 s峄?th岷璽 v岷璽 l媒), workstation type (r脿 so谩t to脿n b峄?14 file Phase C/D 鈥?0 tham chi岷縰 t峄沬 enum 10-lo岷 c农 hay gi岷?膽峄媙h 7-workstation), feature flag (14/14 flag b岷痶 bu峄檆 xu岷 hi峄噉 nh岷 qu谩n 峄?c谩c file li锚n quan), file reference (0 link h峄弉g), permission/ADR (膽茫 s峄璦 m芒u thu岷玭 CH-BUS-014 n锚u tr锚n).
- **Ki峄僲 ch峄﹏g:** `verify-matrix-counts.sh` 鈫?PASS (422/422, 0 ch锚nh l峄嘽h) 鈥?ch岷 l岷 sau m峄峣 thay 膽峄昳.
- **D峄狽G L岷營 theo y锚u c岷** 鈥?ch瓢a s峄璦 code s岷 xu岷, ch瓢a t岷/ch岷 migration, ch瓢a 膽峄昳 schema th岷璽, ch瓢a ghi Access legacy, ch瓢a b岷璽 Agent th岷璽, ch瓢a g峄璱 l峄噉h in, ch瓢a k岷縯 n峄慽 c芒n th岷璽, kh么ng 膽谩nh d岷 procedure IMPLEMENTED, kh么ng t峄?膽贸ng CH-BUS-011/012/013/014. Ch峄?ph锚 duy峄噒 ri锚ng tr瓢峄沜 khi chuy峄僴 sang Phase E.

### 20. Phase E 鈥?review code 膽茫 sinh s岷祅, s峄璦 bug th岷璽 (race condition, QR format, D1 gap 岷, PB-1/PB-2)

- **B峄慽 c岷h:** Ng瓢峄漣 d霉ng y锚u c岷 "th峄眂 hi峄噉 E lu么n". Ph谩t hi峄噉 ph岷 l峄沶 Wave 1-5 膽茫 膽瓢峄 tri峄僴 khai s岷祅 (migrations 膽茫 ch岷 tr锚n DB dev `df-postgres`, Models/Services/Controllers/Vue views 膽茫 c贸) 鈥?chuy峄僴 vai tr貌 t峄?"vi岷縯 m峄沬" sang "review + s峄璦 l峄梚 th岷璽", 膽煤ng tinh th岷 th岷璶 tr峄峮g v峄沬 code 膽茫 ch岷 migration.
- **Bug 膽茫 t矛m v脿 s峄璦, c贸 test PASS (72/72 backend test):**
  1. **CH-BUS-012 t峄?膽贸ng nh岷** 鈥?t脿i li峄噓 B24 tr瓢峄沜 膽芒y ghi sai nh谩nh D1 cu峄慽 "VD10-VD13" (膽煤ng l脿 VD10-VD16). 膼峄峜 l岷 VBA g峄慶 l岷 2 x谩c nh岷璶 kh么ng c贸 l峄?h峄昻g. S峄璦 `WarehouseRoutingService.php`, test, v脿 to脿n b峄?t脿i li峄噓 (ADR RESOLVED, open-questions, ma tr岷璶).
  2. **`area_label` (D1) t铆nh ra nh瓢ng kh么ng l瓢u** 鈥?th锚m migration additive `2026_07_17_000007_add_area_label_to_routing_decisions_table` (膽茫 ch岷, ng瓢峄漣 d霉ng duy峄噒), c岷璸 nh岷璽 model + service + test.
  3. **Race condition th岷璽 trong `ConfirmDispatchService::confirm()`** 鈥?ki峄僲 tra "膽茫 confirm" ch岷 TR漂峄欳 khi kh贸a d貌ng; s峄璦 th峄?t峄?kh贸a鈫択i峄僲 tra, th锚m test `test_second_confirm_with_different_idempotency_key_does_not_duplicate`.
  4. **QR payload vi ph岷 CLAUDE.md C-04** 鈥?code c农 sinh `DF:DYE:uuid:color` t峄?ch岷?thay v矛 膽峄媙h d岷g VBA g峄慶. Vi岷縯 m峄沬 `QrPayloadService.php` b谩m s谩t c么ng th峄ヽ 膽茫 tr铆ch xu岷 (`b24-warehouse-routing.md` M峄 4): `buildDyePayload`, `buildChemPayload` (parse `raw_qr_chemical` theo 膽煤ng quy t岷痗 `ParseQR`), `buildProcessPayload` (PROCESS/EXTRA/FB) 鈥?c贸 ghi ch煤 r玫 gi峄沬 h岷 `dyesProcess`/`totalD` (m岷穋 膽峄媙h "Nylon Dyes"/0 v矛 thi岷縰 b岷g d貌ng dye/chem chi ti岷縯).
  5. **Race condition t瓢啤ng t峄?峄?`ChemicalCallController::createRequest`** 鈥?c贸 unique index b岷 v峄?DB (`uq_channel_active_order`) nh瓢ng thi岷縰 b岷痶 l峄梚 23505 鈫?nay tr岷?`409 CHANNEL_ALREADY_ORDERED` s岷h thay v矛 500.
  6. **PB-1 + PB-2 (pilot blocker CRITICAL 膽茫 t峄搉 膽峄峮g nhi峄乽 膽峄)** 鈥?s峄璦 `agent/ScaleReader.cs`: `CleanWeight` nay 膽煤ng `ExtractLastNumber` (Split(",") + duy峄噒 ng瓢峄 l岷 s峄?cu峄慽, kh么ng c貌n Regex-match-膽岷); th锚m `StableFilter` (膽煤ng thu岷璽 to谩n VBA: 2 l岷 膽峄峜 li锚n ti岷縫 c霉ng chu峄梚 = 峄昻 膽峄媙h). Truy峄乶 `is_stable` xuy锚n su峄憈: `Worker.cs` 鈫?`POST /api/devices/readings` 鈫?`DeviceController` cache 鈫?`GET .../readings/{id}` 鈫?`WeighingStation.vue` (b峄?hard-code `stable:true`, kh贸a n煤t X谩c nh岷璶 khi ch瓢a 峄昻 膽峄媙h, th锚m ch峄?b谩o tr峄眂 quan). Ph谩t hi峄噉 ph峄? bug `res.data.data?.weight` sai t岷g l峄搉g JSON (lu么n nh岷璶 `undefined`鈫? khi d霉ng c芒n th岷璽, b峄?che khu岷 v矛 simulator m岷穋 膽峄媙h b岷璽) 鈥?膽茫 s峄璦 c霉ng l煤c.
  7. **Bug nh峄?`ChemicalCall.vue`**: badge/label d霉ng status `'COMPLETED'` kh么ng kh峄沺 gi谩 tr峄?API th岷璽 `'DONE'` (dead code v矛 `current_request` ch峄?tr岷?khi status active 鈥?s峄璦 cho nh岷 qu谩n).
- **Gi峄沬 h岷 膽茫 bi岷縯:** Kh么ng c贸 .NET SDK trong m么i tr瓢峄漬g 膽峄?`dotnet build`/test Agent 鈥?膽茫 review th峄?c么ng k峄? `npm run build` (frontend) v脿 `php artisan test` (backend) 膽峄乽 PASS, nh瓢ng **Agent .NET ch瓢a 膽瓢峄 compile/test th岷璽** 鈥?c岷 verify tr锚n m谩y c贸 SDK tr瓢峄沜 khi tin t瓢峄焠g ho脿n to脿n cho pilot. `device_credential`/print-protocol m峄沬 theo `api-contracts.md`/`local-agent-architecture.md` ch瓢a 膽瓢峄 wire v脿o Agent (Agent v岷玭 d霉ng workstation_id 膽啤n gi岷, ch瓢a c贸 credential ri锚ng). `dyesProcess`/`totalD` trong QR payload c貌n 膽啤n gi岷 h贸a do thi岷縰 b岷g d貌ng dye/chem chi ti岷縯 trong schema hi峄噉 t岷. `WorkstationAdmin.vue`/`AppLayout.vue` ch瓢a review s芒u.
- **T脿i li峄噓 c岷璸 nh岷璽:** `b24-warehouse-routing.md`, `decision-records.md` (ADR-012 RESOLVED), `open-questions.md` (CH-BUS-012 chuy峄僴 sang m峄 膽茫 tr岷?l峄漣), `vba-migration-matrix.md` (b岷g c峄), `feature-flags.md` (`b24_d1_fix_enabled` kh么ng c貌n c岷), `pilot-blockers.md` (PB-1/PB-2 膽谩nh d岷 膽茫 s峄璦 code, ch峄?verify ph岷 c峄﹏g th岷璽).
- **Ki峄僲 ch峄﹏g:** `php artisan test` 鈫?72/72 PASS; `npm run build` (frontend) 鈫?th脿nh c么ng, kh么ng l峄梚 TypeScript; `verify-matrix-counts.sh` kh么ng b峄?岷h h瓢峄焠g (422/422, kh么ng 膽峄昳).
- Migration m峄沬 (`area_label`) 膽茫 ch岷 tr锚n DB dev sau khi 膽瓢峄 ng瓢峄漣 d霉ng x谩c nh岷璶 r玫 r脿ng (additive-only, c贸 rollback).

### 21. C么 l岷璸 CHEMICAL_CALL & Ho脿n thi峄噉 lu峄搉g li锚n k岷縯 Non-Chemical (Phase E - Thi岷縯 k岷?& B谩o c谩o)

- **B峄慽 c岷h:** Theo y锚u c岷 m峄沬, th峄眂 hi峄噉 t岷 th峄漣 t谩ch r峄漣 ph芒n h峄?`CHEMICAL_CALL` (膽岷穞 d瓢峄沬 tr岷g th谩i `BLOCKED_BY_BUSINESS_CONFIRMATION` do blocker `CH-BUS-015`) v脿 t岷璸 trung to脿n b峄?thi岷縯 k岷? giao 瓢峄沜 k峄?thu岷璽 cho chu峄梚 li锚n k岷縯 c谩c m谩y tr岷 v岷璶 h脿nh c貌n l岷 (`PRODUCTION_ORDER` 鈫?`QR_LABEL_PRINTING` 鈫?`SMALL_SCALE` / `LARGE_SCALE`).
- **T岷 m峄沬 6 t脿i li峄噓 ki岷縩 tr煤c:**
  - `non-chemical-runtime-topology.md`: 膼岷穋 t岷?s啤 膽峄?topology m岷g v岷璽 l媒, an to脿n c么 l岷璸 gi峄痑 c谩c Local Agent v脿 tr矛nh duy峄噒 Kiosk.
  - `production-order-to-dispatch-flow.md`: Quy tr矛nh duy峄噒 膽啤n h脿ng, ki峄僲 tra Capacity 250L cho VD06-13, c啤 ch岷?transaction v脿 lo岷 b峄?ho脿n to脿n vi峄嘽 di chuy峄僴/x贸a d貌ng v岷璽 l媒 c农.
  - `qr-weighing-contract.md`: Giao 瓢峄沜 c岷 tr煤c d峄?li峄噓 m茫 QR th么 (DYE, CHEM, PROCESS, EXTRA, FB) 膽岷 b岷 t瓢啤ng th铆ch ng瓢峄 100% v峄沬 m谩y qu茅t nh脿 x瓢峄焠g hi峄噉 t岷.
  - `dispatch-to-weighing-flow.md`: Quy tr矛nh x谩c nh岷璶 in nh茫n trong transaction (`ConfirmDispatchRowService`), in tem v岷璽 l媒, v脿 c啤 ch岷?tr岷 c芒n chi岷縨 quy峄乶 膽峄檆 quy峄乶 m岷?c芒n (Claim Job) ch峄憂g tranh ch岷.
  - `weighing-workstation-routing.md`: Quy t岷痗 膽峄媙h tuy岷縩 m岷?c芒n sang c芒n nh峄?c芒n l峄沶, v脿 thi岷縯 k岷?h瓢峄沶g 膽峄慽 t瓢峄g t谩ch bi峄噒 `WeighingCoreService` d霉ng chung v脿 c谩c `Policies` ri锚ng.
  - `printer-scale-device-binding.md`: C啤 ch岷?ph芒n gi岷 thi岷縯 b峄?膽峄檔g t峄?database th么ng qua `workstation_id` thay v矛 g谩n c峄﹏g 膽峄媋 ch峄?IP/COM/Port, t铆ch h峄 c啤 ch岷?in d峄?ph貌ng an to脿n (`PRINT_RESULT_UNKNOWN`).
- **C岷璸 nh岷璽 6 t脿i li峄噓 li锚n quan:**
  - `legacy-database-mapping.md`, `domain-architecture.md`, `menu-workstation-device-architecture.md`: Ghi nh岷璶 `CHEMICAL_CALL` 峄?tr岷g th谩i c么 l岷璸, th锚m nh茫n "膼ang x谩c minh" tr锚n menu.
  - `record-a-record-b-correlation.md`: X谩c nh岷璶 lo岷 tr峄?`CHEMICAL_CALL` kh峄廼 vi峄嘽 膽峄慽 chi岷縰 d峄?li峄噓.
  - `migration-plan.md`: 膼谩nh d岷 `WAVE 2: CHEMICAL CALL` 峄?tr岷g th谩i t岷 ho茫n (ON HOLD).
  - `test-architecture.md`: T铆ch h峄 膽岷 膽峄?m么 t岷?chi ti岷縯 c峄 7 K峄媍h b岷 Ki峄僲 th峄?End-to-End t铆ch h峄 b岷痶 bu峄檆 (Scenario A 膽岷縩 G).
  - `source-traceability.md`, `vba-migration-matrix.md`, `pilot-blockers.md` (PB-8), `open-questions.md` (CH-BUS-015/016): 膼峄搉g b峄?h贸a tr岷g th谩i c么 l岷璸 v脿 c谩c open questions m峄沬.
- **X谩c minh h峄?th峄憂g:**
  - Ch岷 backend test suite: **81 tests (445 assertions) PASS 100%**.
  - Bi锚n d峄媍h frontend production build th脿nh c么ng trong `5.62s`.
- **D峄狽G L岷營 REVIEW:** Ho脿n t岷 to脿n b峄?b谩o c谩o v脿 thi岷縯 k岷?li锚n k岷縯 h峄?th峄憂g, s岷祅 s脿ng cho ng瓢峄漣 d霉ng ki峄僲 duy峄噒. K岷縯 lu岷璶: **`NON_CHEMICAL_FLOW_DESIGNED`**.

### 22. Phase E 鈥?Fix bug th岷璽 + Audit 膽峄檆 l岷璸 ki岷縩 tr煤c Operations Client/Capability/Kiosk

- **Ph岷 1 鈥?ti岷縫 t峄 s峄璦 l峄梚 theo th峄?t峄?ng瓢峄漣 d霉ng y锚u c岷:**
  1. C脿i .NET 8 SDK (winget, c贸 x谩c nh岷璶 ng瓢峄漣 d霉ng), `dotnet build` Agent PASS. T岷 `agent/DFAgent.Tests` (xUnit) v峄沬 test vector TV1/TV2/TV3 t峄?`p0-c-scale-algorithm.md` 鈥?l岷 ch岷 膽岷 ph谩t hi峄噉 th锚m 1 bug th岷璽 trong `CleanWeight` (thi岷縰 b瓢峄沜 l峄峜 whitelist `[0-9+\-.,]` tr瓢峄沜 khi t谩ch token, khi岷縩 TV1 v岷玭 tr岷?`12.0` thay v矛 `10.5`) 鈥?膽茫 s峄璦, 6/6 test PASS.
  2. Ph谩t hi峄噉 3 route Agent .NET th岷璽 s峄?d霉ng (`POST /devices/readings`, `GET /agents/{workstation_id}/jobs`, `POST /jobs/{job_id}/ack`) ho脿n to脿n kh么ng x谩c th峄眂; 膽峄搉g th峄漣 `AgentController` (device_id-based, 膽煤ng thi岷縯 k岷? b峄?膽岷穞 sai sau `auth:sanctum` v脿 m峄?c么i (Agent .NET ch瓢a t峄玭g g峄峣). Vi岷縯 middleware `AgentAuth` (t谩i d霉ng `registration_token_hash` c贸 s岷祅 c峄 workstation, kh么ng d峄眓g b岷g `device_credentials` song song), 谩p cho c岷?3 route th岷璽 + to脿n b峄?`AgentController`; `Worker.cs` g峄璱 header `X-Workstation-Token`. C贸 test enforcement th岷璽 (kh么ng ch峄?d峄盿 bypass m么i tr瓢峄漬g test).
  3. Ho脿n thi峄噉 `dyesProcess`/`totalD` trong `QrPayloadService` (tr瓢峄沜 l脿 placeholder "Nylon Dyes"/0) 鈥?implement 膽煤ng thu岷璽 to谩n qu茅t 9 d貌ng dye/chem theo `b24-warehouse-routing.md` M峄 5, s峄璦 lu么n 膽峄媙h d岷g s峄?`totalD` cho kh峄沺 VBA `Format(...,"0.###")` (trim s峄?0 th峄玜) thay v矛 `number_format` c峄?膽峄媙h 3 ch峄?s峄? 7 test m峄沬 PASS.
  4. Review `WorkstationAdmin.vue`: ph谩t hi峄噉 3 taxonomy lo岷 tr岷 kh么ng kh峄沺 nhau (modal 膼膬ng k媒 d霉ng 5 lo岷 膽茫 x谩c nh岷璶 CHEMICAL_CALL/PRODUCTION_ORDER/QR_LABEL_PRINTING/SMALL_SCALE/LARGE_SCALE; `getDefaultActionsForType` 峄?c岷?`WorkstationRegistrationController` l岷玭 `WorkstationGuard` ch峄?bi岷縯 taxonomy c农 ORDER_SCAN/DYE_WEIGHING/...) 鈥?b谩o c谩o cho ng瓢峄漣 d霉ng, 膽ang ch峄?quy岷縯 膽峄媙h h瓢峄沶g s峄璦 (ch峄峮 "th锚m mapping cho 5 lo岷 m峄沬") th矛 ph谩t hi峄噉 DB 膽茫 膽峄昳 c岷 tr煤c d瓢峄沬 n峄乶 (xem Ph岷 2), n锚n NH脕NH BUG N脌Y CH漂A S峄珹 鈥?膽茫 l峄梚 th峄漣 v矛 `Workstation`/`WorkstationGuard` b峄?vi岷縯 l岷 ho脿n to脿n sang model Capability.
- **Ph岷 2 鈥?Audit 膽峄檆 l岷璸 ki岷縩 tr煤c "Operations Client 鈥?Capability 鈥?Device" (theo y锚u c岷 chi ti岷縯 26 m峄 c峄 ng瓢峄漣 d霉ng):**
  - Ph谩t hi峄噉 ngay 膽岷 audit: **m峄檛 ti岷縩 tr矛nh kh谩c 膽ang s峄璦 膽峄搉g th峄漣 c霉ng repo** 鈥?migration `2026_07_17_131458_create_operation_client_architecture_tables` 膽茫 CH岷燳 TH岷琓 gi峄痑 l煤c audit, 膽峄昳 `app.workstations`鈫抈app.operation_clients`, x贸a `workstation_allowed_actions`/`workstation_role_assignments`/`device_assignments`, vi岷縯 l岷 `Workstation` model (nay extends `OperationClient`) v脿 `WorkstationGuard`. To脿n b峄?ki岷縩 tr煤c Kiosk/Capability 膽茫 膽瓢峄 x芒y ph岷 l峄沶 b峄焛 ti岷縩 tr矛nh 膽贸 (`KioskSessionController`, `KioskAuthenticationMiddleware`, `OperationClientAdminController`, `OperationClient`/`Capability`/`KioskSession` models) 鈥?kh么ng ph岷 k岷?ho岷h t瓢啤ng lai.
  - Audit tr峄眂 ti岷縫 tr锚n DB dev + code s峄憂g (kh么ng d峄盿 t脿i li峄噓 t峄?khai): `php artisan tinker` x谩c nh岷璶 b岷g 膽茫 膽峄昳 t锚n th岷璽; `php artisan test` x谩c nh岷璶 88/88 PASS (kh么ng h峄弉g g矛); vi岷縯 **4 test th峄眂 nghi峄噈 m峄沬** (`tests/Feature/CapabilityEnforcementAuditTest.php`, gi峄?l岷 l脿m regression test) 膽峄?CH峄∟G MINH (kh么ng suy di峄卬) ph谩t hi峄噉 quan tr峄峮g nh岷: **P0 鈥?client ch峄?c贸 capability `SMALL_SCALE` v岷玭 g峄峣 th脿nh c么ng `POST /print-jobs` v脿 `POST /machine-dispatches/{id}/confirm`** (kh么ng b峄?403) v矛 ph岷 l峄沶 route trong nh贸m `KioskAuthenticationMiddleware` ch峄?c贸 `workstation.guard:<ACTION>` cho 9/t峄昻g s峄?route, c貌n l岷 ch峄?c岷 "c贸 phi锚n h峄 l峄?, kh么ng ki峄僲 tra 膽煤ng capability. 膼峄搉g th峄漣 x谩c nh岷璶 膽i峄乽 膼脷NG: kiosk session kh么ng v脿o 膽瓢峄 `/admin/*` (401, CheckRole ch岷穘 膽煤ng do `KioskAuthenticationMiddleware` kh么ng g峄峣 `Auth::login()`).
  - Ph谩t hi峄噉 th锚m qua 膽峄峜 code tr峄眂 ti岷縫 (P1): `OperationClient` model thi岷縰 `$hidden` 鈫?`kiosk_token_hash`/`registration_token_hash` l峄?ra JSON `/api/admin/workstations` (x谩c nh岷璶 b岷眓g test, PASS = c贸 l峄?th岷璽); rotate kiosk token kh么ng thu h峄搃 session 膽ang m峄?(ch峄?revoke m峄沬 l脿m); printer/scale v岷玭 resolve qua request body/config file c峄 b峄?(`PrintJobController`, `agent/appsettings.json`) ch峄?ch瓢a qua `operation_client_devices`; Agent .NET d霉ng `registration_token_hash` (kh么ng ph岷 kiosk/user token 鈥?膽煤ng y锚u c岷) nh瓢ng kh么ng ki峄僲 tra capability/device binding; kh么ng t矛m th岷 膽i峄乽 ki峄噉 l峄峜 theo `operation_client_id` trong `WeighingJobController` (ch瓢a x谩c nh岷璶 膽瓢峄 2 tr岷 SMALL_SCALE song song c贸 b峄?tr峄檔 d峄?li峄噓 hay kh么ng 鈥?c岷 test ri锚ng).
  - Ghi to脿n b峄?v脿o `.claude/operations-client-architecture-audit-2026-07-17.md` theo 膽煤ng template ng瓢峄漣 d霉ng y锚u c岷 (10 m峄 ph谩t hi峄噉 x岷縫 P0-P3, file c岷 s峄璦, th峄?t峄?kh岷痗 ph峄 膽峄?xu岷).
  - **K岷縯 lu岷璶: `SYSTEM_LOGIC_NOT_VALIDATED`** 鈥?c貌n P0 (A-01, capability enforcement kh么ng nh岷 qu谩n), P1 ch瓢a x谩c nh岷璶 (A-05, 2-client isolation), 4/7 k峄媍h b岷 E2E b岷痶 bu峄檆 ch瓢a ch岷 do gi峄沬 h岷 th峄漣 gian. Kh么ng t峄?媒 s峄璦 c谩c P0/P1 t矛m 膽瓢峄 trong 膽峄 audit n脿y 鈥?audit v脿 fix t谩ch bi峄噒, ch峄?ng瓢峄漣 d霉ng x谩c nh岷璶 th峄?t峄?瓢u ti锚n.

### 23. "T谩ch ri锚ng CHEMICAL_CALL v脿 ho脿n thi峄噉 li锚n k岷縯 PRODUCTION_ORDER鈫扱R_LABEL_PRINTING鈫扴MALL/LARGE_SCALE" 鈥?s峄璦 code th岷璽 + tr铆ch l岷 VBA g峄慶

- **B峄慽 c岷h:** Ng瓢峄漣 d霉ng y锚u c岷 膽啤n gi岷 h贸a (b峄?qua c岷 h矛nh m谩y tr岷/kiosk ph峄ヽ t岷 v峄玜 audit), t岷璸 trung ch峄﹏g minh b岷眓g code+test chu峄梚 PRODUCTION_ORDER 鈫?QR_LABEL_PRINTING 鈫?SMALL_SCALE/LARGE_SCALE, 膽煤ng tinh th岷 "ph岷 ch峄﹏g minh t峄玭g m农i t锚n b岷眓g VBA/DB/service/API/test, kh么ng n峄慽 module ch峄?b岷眓g gi岷?膽峄媙h". Khi t么i 膽峄媙h t峄?suy di峄卬 c谩ch tr岷 c芒n 膽峄峜 QR, ng瓢峄漣 d霉ng ph岷 b谩c 膽煤ng: *"T岷 sao l岷 kh么ng s峄?d峄g DB v脿 code c峄 VBA?"* 鈥?nh岷痗 膽煤ng nguy锚n t岷痗 ph岷 b谩m VBA g峄慶, kh么ng t峄?b峄媋.
- **Ph谩t hi峄噉 #1 鈥?PRODUCTION_ORDER 鈫?Dispatch queue THI岷綰 HO脌N TO脌N trong code:** `MachineDispatchController` kh么ng c贸 `store()`; `ProductionBatchController::updateStatus()` ch峄?膽峄昳 c峄檛 status t峄?do, kh么ng quy t岷痗 250L, kh么ng t岷 dispatch, kh么ng audit. 膼茫 vi岷縯 `ApproveProductionOrderService` (transaction + row lock `lockForUpdate` + idempotency theo `batch_id` + quy t岷痗 250L 膽煤ng VBA `btnSAVE_Click`: m谩y `VD006-VD013` + tank `1A`/`2B` + level<250 鈫?ch岷穘 "MINIMUM LEVEL 250L") + `BusinessRuleException` + route `POST /api/production-batches/{id}/approve`. Th锚m sequence Postgres `app.web_dispatch_seq` (migration additive) 膽峄?c岷 `legacy_row_no` duy nh岷 cho dispatch t岷 t峄?web (kh么ng ph岷 import Access, `source_table='WEB_APPROVAL'`). 5 test m峄沬 PASS (t岷 dispatch, duy峄噒 2 l岷 kh么ng tr霉ng, ch岷穘/qua 膽煤ng quy t岷痗 250L, kh么ng 谩p quy t岷痗 ngo脿i d岷).
- **Ph谩t hi峄噉 #2 鈥?QR_LABEL_PRINTING 鈫?SMALL_SCALE/LARGE_SCALE KH脭NG k岷縯 n峄慽 th岷璽:** QR do `QrPayloadService` sinh (膽煤ng VBA, s峄璦 t峄?膽岷 phi锚n theo C-04, vd `"#RED-P123-VD10-220-..."`) **kh么ng 膽瓢峄 b岷 k峄?endpoint qu茅t n脿o hi峄僽** 鈥?`ScannerController::scan()` ch峄?parse `DF:ORDER:<uuid>`/`DF:MATERIAL_LABEL:<uuid>`, m峄檛 膽峄媙h d岷g t峄?ch岷?kh么ng li锚n quan `dispatch_id`. Vi峄嘽 t岷 `WeighingJob` 膽i qua lu峄搉g ho脿n to脿n t谩ch bi峄噒 (qu茅t `DF:ORDER:` 鈫?tra Recipe theo `production_batch_id` 鈫?t岷 job theo `workstation->type` DYE_WEIGHING/CHEMICAL_WEIGHING/A11_WEIGHING/DLG_WEIGHING 鈥?kh么ng ph岷 SMALL_SCALE/LARGE_SCALE). B岷g `app.correlation_links` (膽煤ng schema RECORD_A鈫擱ECORD_B) t峄搉 t岷 nh瓢ng **ch瓢a t峄玭g 膽瓢峄 ghi b峄焛 b岷 k峄?code n脿o** (ch峄?c贸 code 膽峄峜 trong `TraceabilityQueryService`).
- **S峄璦 theo 膽煤ng VBA g峄慶 (kh么ng suy di峄卬):** d霉ng `olevba` tr铆ch l岷 **nguy锚n v膬n** `txt_color_AfterUpdate` t峄?`4.semiauto-small scale - delta-stable-final_DF026-027.xlsm` (d貌ng 973-1045) 鈥?x谩c nh岷璶 VBA g峄慶 **kh么ng tra UUID n脿o**: m谩y qu茅t g玫 th岷硁g QR v脿o textbox, code Trim 鈫?thay "," th脿nh "." 鈫?l岷穚 x贸a m峄峣 c峄 "-dye-" 鈫?c岷痶 t岷 "chem" n岷縰 c贸 鈫?Split theo "-" 鈫?4 ph岷 t峄?膽岷 l脿 color/code/machine/level 鈫?t峄?ph岷 t峄?5 膽峄峜 b峄?ba rack/dye/weight (t峄慽 膽a 9 b峄?. Port verbatim th脿nh `QrPayloadService::parseDyeScan()`, th锚m endpoint `POST /api/scanner/scan-dye-qr` (`ScannerController::scanRawDyeQr`) resolve `ProductionBatch` theo 膽煤ng kh贸a nghi峄噋 v峄?VBA d霉ng (**color+code**, kh么ng ph岷 UUID), t谩i d霉ng logic t岷 `WeighingJob` s岷祅 c贸, v脿 ghi `app.correlation_links` (`match_method='DETERMINISTIC_COMPOSITE'`, kh峄沺 theo color+code+machine, kh么ng d霉ng timestamp).
- **Test:** `QrPayloadServiceTest` +3 (round-trip build鈫抪arse, l岷穚 x贸a nhi峄乽 "-dye-", c岷痶 t岷 "chem" 鈥?膽煤ng test vector VBA). `QrScanToWeighingE2ETest` +2 鈥?test E2E th岷璽: t岷 膽啤n 鈫?dispatch confirm 鈫?sinh QR th岷璽 鈫?qu茅t t岷 `WS-DYE` 鈫?`WeighingJob` 膽瓢峄 t岷 鈫?`correlation_links` 膽瓢峄 ghi 膽煤ng 1 d貌ng (qu茅t l岷 kh么ng tr霉ng). S峄璦 1 l峄梚 t峄?g芒y trong l煤c vi岷縯 test: d霉ng gi谩 tr峄?fixture c贸 d岷 `-` trong color/machine (`E2E-COLOR`) l脿m g茫y ph茅p t谩ch chu峄梚 鈥?膽煤ng 膽岷穋 t铆nh th岷璽 c峄 VBA (color/machine kh么ng 膽瓢峄 ch峄゛ `-`), kh么ng ph岷 bug c峄 code.
- **Ki峄僲 ch峄﹏g:** `php artisan test` 鈫?**98/98 PASS (516 assertions)**, kh么ng h峄弉g g矛 so v峄沬 93 tr瓢峄沜 膽贸 (+5 approve, +3 parseDyeScan, +2 E2E, -5 ch锚nh do 膽岷縨 g峄檖... t峄昻g kh峄沺 98).
- **Ph岷 vi CH漂A l脿m trong 膽峄 n脿y (n锚u r玫, kh么ng t峄?nh岷璶 膽茫 xong):** ch峄?payload **DYE** 膽瓢峄 n峄慽 d芒y scan-side; **CHEM/PROCESS/EXTRA/FB ch瓢a c贸 endpoint scan t瓢啤ng 峄﹏g**. Quy t岷痗 ch峄峮 SMALL_SCALE hay LARGE_SCALE **v岷玭 膽煤ng l脿 blocker CH-BUS-016**, kh么ng t峄?suy di峄卬 ng瓢峄g 鈥?tr岷 n脿o qu茅t th矛 tr岷 膽贸 x峄?l媒 (kh么ng c贸 b瓢峄沜 "routing" ri锚ng). Ch瓢a 膽峄檔g v脿o ph岷 Kiosk/OperationClient/Capability (theo 膽煤ng y锚u c岷 "b峄?qua c岷 h矛nh m谩y tr岷" c峄 ng瓢峄漣 d霉ng) 鈥?c谩c P0/P1 膽茫 ghi trong entry #22 (audit ki岷縩 tr煤c) v岷玭 c貌n nguy锚n, ch瓢a s峄璦. CHEMICAL_CALL kh么ng b峄?膽峄g t峄沬, v岷玭 c么 l岷璸 膽煤ng nh瓢 x谩c nh岷璶 峄?entry #22.
- **膼茫 c岷璸 nh岷璽:** `qr-weighing-contract.md` (th锚m kh峄慽 `[!IMPORTANT]` ghi r玫 tr岷g th谩i tri峄僴 khai th岷璽, tr铆ch d岷玭 d貌ng VBA c峄?th峄?.

### 24. Ki峄僲 ch峄﹏g th锚m theo y锚u c岷 "ti岷縫 膽i" 鈥?lo岷 tr峄?CHEM scan (c贸 c膬n c峄?VBA) + 膽贸ng A-05

- **CHEM QR kh么ng c岷 n峄慽 scan-side:** tr铆ch l岷 VBA c峄 C岷?HAI workbook c芒n (`4.semiauto-small scale...xlsm` V脌 `5.Semiauto- lockmove SEND OVER6...xlsm`, olevba) 鈥?x谩c nh岷璶 kh么ng c贸 b岷 k峄?handler n脿o 膽峄峜 l岷 `qrChem`/`qrProcess`/`qrExtra`/`qrFB`; c岷?2 workbook ch峄?c贸 膽煤ng 1 d貌ng li锚n quan "chem": c岷痶 b峄?v脿 b峄?qua n岷縰 chu峄梚 qu茅t ch峄゛ "chem" (`InStr(sLower,"chem")>0 Then s=Left(s,...)`), kh么ng x峄?l媒 ti岷縫. K岷縯 lu岷璶: c谩c payload n脿y CH峄?膽峄?in tem gi岷 cho ng瓢峄漣 膽峄峜, KH脭NG 膽瓢峄 ph岷 m峄乵 c芒n qu茅t l岷. **Ch峄?膽峄檔g d峄玭g, kh么ng vi岷縯 endpoint "scan-chem-qr"** v矛 kh么ng c贸 c膬n c峄?VBA 鈥?tr谩nh 膽煤ng l峄梚 b峄媋 膽岷穞 h脿nh vi m脿 ng瓢峄漣 d霉ng 膽茫 c岷h b谩o. Ghi v脿o `qr-weighing-contract.md`.
- **A-05 (2 tr岷 SMALL_SCALE 膽峄檆 l岷璸) 鈥?膽贸ng, c贸 b岷眓g ch峄﹏g:** vi岷縯 `tests/Feature/SmallScaleTwoStationIsolationTest.php` (2 test, PASS, 17 assertions) 鈥?2 tr岷 x峄?l媒 2 膽啤n kh谩c nhau qua `scan-dye-qr`: job/item kh么ng giao nhau, c芒n xong 峄?tr岷 A kh么ng 岷h h瓢峄焠g job B, cache s峄?c芒n tr峄眂 ti岷縫 c么 l岷璸 膽煤ng theo `workstation_id`. L媒 do an to脿n: `WeighingJob` kh贸a theo `production_batch_id` (kh贸a nghi峄噋 v峄?, kh么ng ph岷 theo tr岷 鈥?c么 l岷璸 膽岷縩 t峄?nhi锚n. R峄 ro nh峄?c貌n m峄?(kh么ng ph岷 P1 n峄痑, h岷?xu峄憂g P2): 2 tr岷 qu茅t TR脵NG 1 QR g岷 nh瓢 膽峄搉g th峄漣 c贸 th峄?l脿m `assigned_workstation_id` b峄?ghi 膽猫 (kh么ng c贸 `lockForUpdate`) 鈥?ch瓢a test, r峄 ro v岷璶 h脿nh th岷. 膼茫 c岷璸 nh岷璽 `operations-client-architecture-audit-2026-07-17.md` M峄 10 ph岷 谩nh 膽煤ng tr岷g th谩i m峄沬.
- **Ki峄僲 ch峄﹏g:** `php artisan test` 鈫?**100/100 PASS (533 assertions)**.

### 25. N峄慽 UI cho t铆nh n膬ng m峄沬 + s峄璦 route PRODUCTION_ORDER b峄?g谩n nh岷

- **N峄慽 UI:** `ProductionBatches.vue` 鈥?th锚m n煤t "Duy峄噒 膽啤n" (g峄峣 `POST .../approve` m峄沬 thay v矛 dropdown 膽峄昳 status t峄?do), th锚m status `APPROVED` v脿o to脿n b峄?mapping badge/progress/KPI/filter; 膽峄昳 mock-tool t岷 膽啤n v峄?status `NEW` (tr瓢峄沜 l脿 nh岷 th岷硁g `READY_TO_WEIGH`, b峄?qua b瓢峄沜 duy峄噒). `WeighingStation.vue` 鈥?`handleBarcodeScan` (膽ang l岷痭g nghe scanner v岷璽 l媒 th岷璽 qua keyboard-wedge) nay t峄?膽峄媙h tuy岷縩: chu峄梚 b岷痶 膽岷 `#` 鈫?`/scanner/scan-dye-qr` (QR th岷璽), c貌n l岷 鈫?`/scanner/scan` (gi峄?nguy锚n h脿nh vi c农 v峄沬 `DF:ORDER:`); th锚m 么 nh岷璸 tay QR fallback khi m谩y qu茅t l峄梚.
- **Ph谩t hi峄噉 khi ng瓢峄漣 d霉ng y锚u c岷 link tr岷 PRODUCTION_ORDER 膽峄?膽峄慽 chi岷縰 VBA:** route `/order-scan` b峄?3 n啤i g谩n nh岷 l脿 default route c峄 capability `PRODUCTION_ORDER` (`OperationClientAdminController::getDefaultRouteForCap`, `WorkstationsSeeder` seed `WS-ORDER-01`, `KioskLanding.vue`/`router/index.ts` ph铆a frontend) 鈥?nh瓢ng `/order-scan` th峄眂 ch岷 l脿 tr岷 "ORDER DESK" kh谩c (ch峄?qu茅t QR xem/x谩c nh岷璶 膽茫 nh岷璶 膽啤n, `ScannerController::handleOrderDeskPreview`, KH脭NG t岷/duy峄噒 膽啤n). Route 膽煤ng kh峄沺 VBA Workbook C3 (`btnSAVE_Click`+`MoveToSend`, n啤i c贸 `ApproveProductionOrderService`) l脿 `/production-batches`. 膼茫 s峄璦 c岷?4 ch峄?(2 backend, 2 frontend) theo y锚u c岷 ng瓢峄漣 d霉ng.
- **Kh么ng s峄璦 (dead code, kh么ng 岷h h瓢峄焠g):** `WorkstationAdminController::index()` v脿 `WorkstationRegistrationController::getDefaultRouteForType/getDefaultActionsForType` 鈥?x谩c nh岷璶 kh么ng route n脿o g峄峣 t峄沬 c谩c h脿m n脿y n峄痑 (膽茫 b峄?`OperationClientAdminController` thay th岷?ho脿n to脿n cho lu峄搉g 膽膬ng k媒/danh s谩ch), `WorkstationAdminController` c貌n d霉ng c峄檛 `workstation_id` 膽茫 膽峄昳 t锚n n锚n n岷縰 g峄峣 s岷?l峄梚 鈥?n锚u 膽峄?bi岷縯, kh么ng s峄璦 v矛 kh么ng ph岷 code s峄憂g.

### 26. 膼啤n gi岷 h贸a: b峄?茅p "膽膬ng k媒 tr岷 qua token" 鈥?v脿o th岷硁g giao di峄噉, t峄?c岷 h矛nh c芒n/m谩y in t岷 ch峄?

- **Y锚u c岷:** "B峄?qua c谩c b瓢峄沜 膽膬ng k媒 n脿o m脿 v脿o lu么n giao di峄噉 c峄 m谩y. N岷縰 m谩y n脿o c岷 in th矛 thi岷縯 l岷璸 m谩y in, m谩y n脿o c岷 c芒n th矛 thi岷縯 l岷璸 k岷縯 n峄慽 c芒n" 鈥?s谩t v峄沬 VBA h啤n (m峄梚 workbook t峄?c贸 d貌ng c岷 h矛nh COM port/m谩y in c峄 b峄? ai ng峄搃 m谩y 膽贸 ch峄塶h 膽瓢峄, kh么ng qua ph锚 duy峄噒).
- **Ph谩t hi峄噉:** h峄?th峄憂g c贸 S岷碞 2 c啤 ch岷?ch峄峮 tr岷 song song 鈥?(A) dropdown 膽啤n gi岷 c贸 s岷祅 trong `AppLayout.vue` + `services/workstation.ts` (ch峄峮 t峄?danh s谩ch, l瓢u localStorage, KH脭NG c岷 token), v脿 (B) `WorkstationKioskSetup.vue` + kiosk token ph峄ヽ t岷 (y锚u c岷 Admin c岷 token tr瓢峄沜). Router `beforeEach` guard 膽ang 脡P m峄峣 ng瓢峄漣 qua (B) tr瓢峄沜 khi v脿o b岷 k峄?trang n脿o (`if (!hasToken) next('/workstation-setup')`), d霉 (A) 膽茫 t峄搉 t岷 s岷祅 v脿 膽啤n gi岷 h啤n nhi峄乽.
- **S峄璦:** x贸a 膽o岷 茅p bu峄檆 trong `router/index.ts` 鈥?gi峄?ch峄?c岷 膽膬ng nh岷璸 (`requiresAuth`), vi峄嘽 ch峄峮 tr岷 膽峄?l岷 ho脿n to脿n cho blocker c贸 s岷祅 trong `AppLayout.vue` (dropdown 膽啤n gi岷, kh么ng token). T脿i kho岷 b峄?Admin kh贸a c峄﹏g v脿o 1 tr岷 (t铆nh n膬ng WS-001 c农) v岷玭 ho岷 膽峄檔g y h峄噒 nh瓢 tr瓢峄沜, kh么ng 膽峄昳.
- **C岷 h矛nh c芒n/m谩y in t岷 ch峄?** th锚m `WorkstationLocalConfigController::updateDeviceConfig` (route m峄沬 `PUT /workstations/{id}/local-device-config`) 鈥?**kh么ng g岷痭 role:ADMIN**, ch峄?c岷 膽膬ng nh岷璸, ph岷 vi h岷筽 (ch峄?t岷/g谩n `Device` l脿m PRIMARY_SCALE/PRIMARY_PRINTER cho 膼脷NG tr岷 truy峄乶 v脿o, kh么ng 膽峄g capability/quy峄乶/route) n锚n an to脿n khi m峄?cho m峄峣 vai tr貌. 3 test PASS (g谩n c芒n m峄沬, g谩n l岷 thay c芒n c农 kh么ng t岷 tr霉ng, g谩n m谩y in k猫m connection_type/address).
- **N峄慽 UI:** `WeighingStation.vue` v脿 `PrintStation.vue` 鈥?banner c岷h b谩o "ch瓢a g谩n thi岷縯 b峄? nay c贸 n煤t "鈿欙笍 C岷 h矛nh ngay" m峄?form nh岷璸 t岷 ch峄?(m茫 thi岷縯 b峄? COM port/膽峄媋 ch峄?IP), g峄峣 th岷硁g endpoint m峄沬, kh么ng c岷 r峄漣 trang hay v脿o Admin.
- **S峄璦 k猫m (ph谩t hi峄噉 khi l脿m ph岷 n脿y):** `App\Models\Device` v岷玭 d霉ng c峄檛 `workstation_id` 膽茫 膽峄昳 t锚n th岷璽 th脿nh `operation_client_id` t峄?migration Operations Client (Session #22) 鈥?`$fillable` sai t锚n c峄檛 (b峄?Eloquent 芒m th岷 b峄?qua) v脿 quan h峄?`workstation()` s岷?l峄梚 th岷璽 n岷縰 b峄?g峄峣. 膼茫 s峄璦 c岷?2 theo 膽煤ng t锚n c峄檛 th岷璽.
- **Ki峄僲 ch峄﹏g:** `php artisan test` 鈫?**103/103 PASS (545 assertions)**. `npm run build` (frontend) 鈫?s岷h, kh么ng l峄梚 TypeScript.
- **Ch瓢a l脿m/kh么ng 膽峄g:** `WorkstationKioskSetup.vue` v脿 route `/operate/c/:code/:token` v岷玭 c貌n trong code (kh么ng x贸a, ch峄?kh么ng c貌n b峄?茅p d霉ng) 鈥?n岷縰 sau n脿y c岷 tri峄僴 khai kiosk th岷璽 (m谩y c么ng c峄檔g, kh么ng 膽膬ng nh岷璸) th矛 h岷?t岷g 膽贸 v岷玭 s岷祅 s脿ng d霉ng l岷.

### 27. Bug th岷璽: danh s谩ch tr岷 tr峄憂g tr锚n giao di峄噉 鈥?`GET /api/workstations` crash 500

- **Ng瓢峄漣 d霉ng b谩o:** v脿o link, m脿n h矛nh ch峄峮 m谩y l脿m vi峄嘽 kh么ng c贸 g矛 膽峄?ch峄峮 ("膽茫 c贸 trong danh s谩ch 膽芒u?").
- **Truy v岷縯 b岷眓g request th岷璽** (kh么ng 膽o谩n): t岷 Sanctum token th岷璽 qua tinker, g峄峣 th岷硁g `curl -H "Authorization: Bearer ..." /api/workstations` 鈥?tr岷?l峄梚 500: `Call to undefined method App\Models\Workstation::getWorkstationTypeAttribute()`. D峄?li峄噓 6 tr岷 m岷玼 (WS-CHEMICAL-01, WS-ORDER-01, WS-PRINT-01, WS-SMALL-01/02, WS-LARGE-01) **v岷玭 c贸 s岷祅 trong DB** 鈥?kh么ng ph岷 thi岷縰 d峄?li峄噓, m脿 API ch岷縯 n锚n frontend nh岷璶 l峄梚, `fetchWorkstations()` ch峄?`console.error` im l岷穘g, ng瓢峄漣 d霉ng th岷 dropdown r峄梟g kh么ng r玫 l媒 do.
- **Nguy锚n nh芒n:** `app/Models/Workstation.php::$appends` li峄噒 k锚 `'workstation_type'` v脿 `'type'` 鈥?膽芒y l脿 **C峄楾 TH岷琓** tr锚n b岷g `app.operation_clients` (膽茫 t峄?serialize s岷祅), kh么ng ph岷 virtual attribute, nh瓢ng b峄?nh茅t v脿o `$appends` khi岷縩 Eloquent c峄?g峄峣 `getWorkstationTypeAttribute()`/`getTypeAttribute()` (kh么ng t峄搉 t岷) m峄梚 l岷 serialize ra JSON 鈫?crash to脿n b峄?endpoint tr岷?v峄?workstation (`/api/workstations`, v脿 c贸 th峄?c岷?n啤i kh谩c d霉ng model n脿y).
- **S峄璦:** b峄?`'workstation_type'`/`'type'` kh峄廼 `$appends` (gi峄?nguy锚n c谩c virtual attribute th岷璽: `assigned_scale_device_id`, `assigned_printer_device_id`, `allowed_actions`, `active`, `default_screen`).
- **Ti峄噉 s峄璦 lu么n A-02 (r貌 r峄?token, 膽茫 ghi nh岷璶 峄?膽峄 audit tr瓢峄沜 nh瓢ng ch瓢a v谩):** th锚m `protected $hidden = ['kiosk_token_hash', 'registration_token_hash']` v脿o `OperationClient` model 鈥?膽煤ng response `/api/workstations` v峄玜 debug th峄眂 t岷?c貌n th岷 r玫 2 field n脿y l峄?ra.
- **Test m峄沬:** `WorkstationListEndpointTest` (regression cho 膽煤ng bug 500 n脿y 鈥?t岷 tr岷, g峄峣 endpoint, assert 200 + field 膽煤ng); c岷璸 nh岷璽 `CapabilityEnforcementAuditTest::test_admin_workstations_list_leaks_token_hashes_to_frontend` 鈫?膽峄昳 t锚n + 膽岷 ng瓢峄 assertion th脿nh `does_not_leak` (膽煤ng theo ghi ch煤 t峄?膽峄?l岷 trong test c农).
- **Ki峄僲 ch峄﹏g:** `php artisan test` 鈫?**104/104 PASS (551 assertions)**. X谩c nh岷璶 l岷 b岷眓g `curl` th岷璽 v峄沬 token Sanctum th岷璽 (kh么ng ph岷 gi岷?l岷璸 test) 鈥?endpoint tr岷?`status:SUCCESS` k猫m 膽峄?6 tr岷 m岷玼, kh么ng c貌n `kiosk_token_hash` trong response.

### 28. Bug th岷璽: link `?ws=CODE` b峄?router redirect ng瓢峄, m岷 lu么n 膽峄媙h danh m谩y 鈥?chuy峄僴 h岷硁 sang c啤 ch岷?Kiosk Token (kh么ng 膽膬ng nh岷璸 cho m谩y tr岷, ch峄?Admin 膽膬ng nh岷璸)

- **Ng瓢峄漣 d霉ng b谩o (k猫m 岷h):** m峄?link `/production-batches?ws=WS-ORDER-01` v岷玭 hi峄噉 m脿n "Ch峄峮 tr岷 l脿m vi峄嘽" nh瓢 ch瓢a c贸 link ri锚ng g矛 c岷? 膼峄搉g th峄漣 ch峄?ra 膽煤ng b岷 ch岷 l峄梚: h峄?th峄憂g c贸 3 kh谩i ni峄噈 "Workstation" kh么ng 膽峄搉g b峄?(t脿i kho岷 ng瓢峄漣 d霉ng g谩n c峄﹏g tr岷, dropdown `services/workstation.ts`, v脿 session Kiosk) 鈥?膽谩 nhau.
- **Nguy锚n nh芒n g峄慶 (x谩c nh岷璶 b岷眓g 膽峄峜 code, kh么ng 膽o谩n):** `router/index.ts` d貌ng `if (requiresAuth && lockedScreen && to.path !== lockedScreen) next(lockedScreen)` ch岷 TR漂峄欳 khi trang k峄媝 膽峄峜 query `?ws=`, 茅p m峄峣 膽i峄乽 h瓢峄沶g v峄?`lockedScreen` c峄 t脿i kho岷 膽膬ng nh岷璸 (ho岷穋 `/` n岷縰 t脿i kho岷 kh么ng c贸 tr岷 g谩n) 鈥?x贸a m岷 query string, `AppLayout.vue` nh岷璶 `currentWorkstation = null` 鈫?hi峄噉 l岷 m脿n ch峄峮 tr岷.
- **S峄璦 v貌ng 1 (膽茫 l脿m, 膽峄?cho tr瓢峄漬g h峄 c貌n y锚u c岷 膽膬ng nh岷璸):** th锚m nh谩nh b峄?qua kh峄慽 `lockedScreen` khi `to.query.ws` c贸 m岷穞 (v岷玭 gi峄?nguy锚n ch岷穘 `requiresAuth`/`requiresAdmin`). `AppLayout.vue` t谩ch blocker c农 th脿nh 3 tr岷g th谩i r玫 r脿ng: 膽ang resolve t峄?link (kh么ng c岷 thao t谩c), m茫 tr岷 trong link kh么ng t峄搉 t岷 (b谩o l峄梚 r玫), v脿 fallback dropdown ch峄?khi m峄?trang g峄慶 kh么ng qua link. Test l岷 `WorkstationAdmin/Binding/Impersonation/TroubleshootingInference/SmallScaleIsolation` 鈫?17/17 PASS (l岷 fail 14 test tr瓢峄沜 膽贸 l脿 artifact c峄 1 l岷 ch岷 n峄乶 l峄嘽h th峄漣 膽i峄僲 migrate, 膽茫 x谩c minh l岷 b岷眓g `migrate:status` + query DB tr峄眂 ti岷縫, kh么ng ph岷 h峄搃 quy th岷璽).
- **Y锚u c岷 ti岷縫 theo c峄 ng瓢峄漣 d霉ng:** m谩y tr岷 KH脭NG c岷 膽膬ng nh岷璸 g矛 c岷?鈥?b岷 link l脿 v脿o th岷硁g giao di峄噉 v岷璶 h脿nh; ch峄?Admin m峄沬 c岷 膽膬ng nh岷璸.
- **Ph谩t hi峄噉:** c啤 ch岷?n脿y **膽茫 t峄搉 t岷 s岷祅 t峄?tr瓢峄沜**, ch峄?b峄?b峄?qu锚n/ng岷痶 k岷縯 n峄慽 鈥?`KioskSessionController::establishSession` (`POST /api/kiosk/session`, x谩c th峄眂 b岷眓g `client_code` + `kiosk_token` b铆 m岷璽, kh么ng c岷 t脿i kho岷 ng瓢峄漣 d霉ng), `KioskAuthenticationMiddleware` (膽茫 b峄峜 **to脿n b峄?* route nghi峄噋 v峄? ch岷 nh岷璶 song song Sanctum HO岷禖 kiosk session token 鈥?x谩c nh岷璶 膽峄峜 tr峄眂 ti岷縫 middleware, kh么ng ph岷 suy 膽o谩n), `KioskLanding.vue` (route `/operate/c/:clientCode/:kioskToken`, t峄?thi岷縯 l岷璸 session r峄搃 膽i峄乽 h瓢峄沶g th岷硁g v脿o m脿n h矛nh 膽煤ng capability), v脿 `authStore.setKioskSession()` (膽茫 t峄?膽峄檔g g峄峣 `setWorkstation()` 膽峄?膽峄搉g b峄?v峄沬 `services/workstation.ts` 鈥?2 trong 3 c啤 ch岷?"workstation" th峄眂 ra 膽茫 膽瓢峄 n峄慽 s岷祅, ch峄?c贸 lu峄搉g `?ws=` m峄沬 m脿 t么i th锚m 峄?膽峄 tr瓢峄沜 l脿 膽峄﹏g ri锚ng).
- **Bug 膽i k猫m ph谩t hi峄噉 khi n峄慽 l岷:** `KioskLanding.vue::getRouteForCapability` v脿 `router/index.ts` (nh谩nh `authStore.isKiosk` gi峄沬 h岷 `allowedRoutes`) map c峄﹏g `CHEMICAL_CALL 鈫?/feeding-monitor` 鈥?SAI, `/feeding-monitor` l脿 m脿n h矛nh kh谩c (`FeedOperationController`, kh么ng li锚n quan `ChemicalCallController`). Route 膽煤ng l脿 `/chemical-call` (膽茫 x谩c nh岷璶 qua DB `default_route` c峄 `WS-CHEMICAL-01` v脿 `ChemicalCall.vue` g峄峣 膽煤ng API `chemical-call-requests`). 膼茫 s峄璦 c岷?2 ch峄?
- **S峄璦 th锚m:** `AppLayout.vue::isLockedStation` b峄?sung `authStore.isKiosk` 鈫?kh贸a c峄﹏g, 岷﹏ n煤t 膽峄昳 tr岷 cho phi锚n kiosk (tr瓢峄沜 膽贸 ch峄?nh岷璶 di峄噉 kh贸a qua `user.workstation`/`wsConfig`, b峄?s贸t kiosk).
- **膼茫 sinh kiosk token th岷璽 cho 6 tr岷 m岷玼** (qua ch铆nh logic c峄 `OperationClientAdminController::generateKioskToken`, kh么ng b峄媋) v脿 x谩c nh岷璶 **to脿n b峄?chu峄梚 th岷璽 qua `curl`**: `POST /api/kiosk/session` v峄沬 token WS-ORDER-01 鈫?tr岷?膽煤ng `default_capability: PRODUCTION_ORDER`, `default_route: /production-batches`, danh s谩ch capabilities 膽岷 膽峄?
- **Ki峄僲 ch峄﹏g:** `npm run build` s岷h. `php artisan test --filter="Kiosk|CapabilityEnforcement|WorkstationSecurity"` 鈫?13/13 PASS.
- **R峄 ro c貌n t峄搉 膽峄峮g, CH漂A s峄璦 (n岷眒 ngo脿i y锚u c岷 l岷 n脿y, c岷 n锚u r玫 v矛 kiosk gi峄?l脿 c峄昻g v脿o ch铆nh):** `CapabilityEnforcementAuditTest` v岷玭 x谩c nh岷璶 1 client ch峄?c贸 capability `SMALL_SCALE` v岷玭 g峄峣 th脿nh c么ng `POST /print-jobs` v脿 `confirm dispatch` (route thi岷縰 `workstation.guard` 膽煤ng capability) 鈥?finding P0 t峄?膽峄 audit ki岷縩 tr煤c tr瓢峄沜, tr瓢峄沜 膽芒y l脿 r峄 ro ph峄? nay quan tr峄峮g h啤n v矛 kiosk token 膽茫 tr峄?th脿nh 膽瓢峄漬g v脿o ch铆nh th峄ヽ thay v矛 t脿i kho岷 ng瓢峄漣 d霉ng.

### 30. X芒y m脿n h矛nh "H脿ng ch峄?in tem" th岷璽 cho Print Station + ph谩t hi峄噉 & v谩 bug nghi锚m tr峄峮g: B24 routing sai ho脿n to脿n do so s谩nh chu峄梚 m茫 m谩y sai 膽峄媙h d岷g s峄?ch峄?s峄?

- **Y锚u c岷:** r脿 so谩t VBA cho tr岷 in tem (`3.DF028... jit qr sending`), x谩c 膽峄媙h n峄檌 dung tem in + tr岷g th谩i sau khi in, sau 膽贸 x芒y m脿n h矛nh t瓢啤ng 峄﹏g. Ng瓢峄漣 d霉ng g峄璱 k猫m 岷h ch峄 `TO_SEND.frm` 膽ang ch岷 th岷璽 (d貌ng 膽峄?v峄玜 g峄璱 t峄沬, d貌ng xanh+checkbox=膽茫 in tem) 膽峄?膽峄慽 chi岷縰.
- **R脿 so谩t VBA (`TO_SEND.frm`, `Mod_FE_REFRESH.bas`, `Mod_printslip.bas`, `printform.frm`):**
  - M谩y in tem **KH脭NG nh岷璶 th么ng b谩o/push n脿o** 鈥?t峄?polling `SELECT ... FROM tbl_tosend` m峄梚 15 gi芒y qua `Application.OnTime` (`StartAutoRefresh`/`Backend_AutoRefresh`).
  - N煤t **"print"** (`btn_print_scaleslip_Click` 鈫?`PrintSlip_70x100`) ch峄?render sheet + xu岷 岷h QR 鈥?**kh么ng ghi DB**. N煤t **"OK"** (`ConfirmRow`, HO脌N TO脌N T脕CH BI峄員 v峄沬 n煤t print) m峄沬 l脿 h脿nh 膽峄檔g chuy峄僴 d貌ng t峄?`tbl_tosend` sang `tbl_sentlog` (l瓢u tr峄?, ghi `TIME3=Now()`. C峄檛 `ISSENT` kh么ng h峄?膽瓢峄 set `true` 峄?b岷 k峄?膽芒u trong workbook n脿y 鈥?ch峄?copy nguy锚n tr岷g khi chuy峄僴 b岷g.
  - Checkbox (c峄檛 `scale_check`) 鈥?theo x谩c nh岷璶 tr峄眂 ti岷縫 t峄?ng瓢峄漣 d霉ng qua 岷h ch峄 鈥?c贸 媒 ngh末a v岷璶 h脿nh th岷璽 l脿 **"膽茫 in tem"**, do ng瓢峄漣 v岷璶 h脿nh t峄?tick tay sau khi in, 膽峄檆 l岷璸 ho脿n to脿n v峄沬 n煤t print/OK (膽煤ng kh峄沺 ph谩t hi峄噉 code: kh么ng c贸 li锚n k岷縯 t峄?膽峄檔g).
  - Tem in ra g峄搈: header (m脿u/m茫 h脿ng/m谩y/th霉ng/m峄ヽ n瓢峄沜), b岷g t峄慽 膽a 9 d貌ng dye + 9 d貌ng chem, v脿 **lu么n 2 QR** (`qr_dye` d霉ng 峄?**tr岷 c芒n li峄噓**, `qr_chem` d霉ng 峄?**Color Service**) **+ 1 QR th峄?3 t霉y mode B24** (`qr_process`/`qr_extra`/`qr_fb` 鈥?c岷?3 膽峄乽 d脿nh cho Color Service, kh谩c nhau theo c峄 m谩y/tank). 膼峄慽 chi岷縰 b岷眓g ch铆nh 4 d貌ng th岷璽 trong 岷h ng瓢峄漣 d霉ng g峄璱 (VD09/VD12/VD16/VD07 + tank 3C/4D 鈫?膽峄乽 r啤i 膽煤ng nh谩nh 5 B24 = mode PROCESS).
  - Ng瓢峄漣 d霉ng l瓢u 媒: **m岷玼 tem v岷璽 l媒 (layout) do m谩y in c岷 h矛nh s岷祅 quy岷縯 膽峄媙h** 鈥?web ch峄?c岷 g峄璱 膽煤ng d峄?li峄噓 QR, kh么ng t峄?v岷?layout tem (kh谩c VBA v峄憂 t峄?v岷?l锚n sheet Excel).
- **X芒y `PrintStation.vue`:** th锚m panel "H脿ng ch峄?in tem m峄沬" (port 膽煤ng `TO_SEND.frm`), t峄?l脿m m峄沬 m峄梚 8 gi芒y qua `GET /api/machine-dispatches` (膽茫 c贸 s岷祅, 膽煤ng vai tr貌 `tbl_tosend`), n煤t "In tem" g峄峣 `POST /machine-dispatches/{id}/confirm` (膽茫 c贸 s岷祅 t峄?tr瓢峄沜 鈥?`ConfirmDispatchService`, sinh 膽峄?3 QR payload qua `QrPayloadService`, t岷 `PrintJob`) 鈥?**route n脿y tr瓢峄沜 膽贸 KH脭NG h峄?c贸 UI n脿o g峄峣 t峄沬**, 膽芒y ch铆nh l脿 m岷痶 x铆ch c貌n thi岷縰 膽茫 b谩o 峄?l瓢峄 tr瓢峄沜. Kh么ng l脿m l岷 honor-system checkbox+OK th峄?c么ng c峄 VBA v矛 h峄?th峄憂g web 膽茫 c贸 `PrintJob.status` (PENDING鈫扨RINTED/FAILED qua Local Agent ack) t峄憈 h啤n 鈥?c岷 ti岷縩 膽茫 c贸 s岷祅 t峄?Phase 7, kh么ng ph岷 th锚m m峄沬 h么m nay.
- **Ph谩t hi峄噉 bug nghi锚m tr峄峮g khi test end-to-end th岷璽** (kh么ng ph岷 gi岷?l岷璸): t岷 膽啤n m谩y `VD007` + tank `3C` (膽煤ng nh谩nh 5 B24 = PROCESS theo t脿i li峄噓), nh瓢ng API tr岷?v峄?`mode=FB` (fallback r峄梟g, SAI) d霉 feature flag `b24_routing_enabled` 膽ang `true`. Truy v岷縯: `WarehouseRoutingService::isBetween()` so s谩nh CHU峄朓 (`'VD007' >= 'VD06'` 鈫?**FALSE** d霉 7鈮? 膽煤ng v峄?s峄? v矛 k媒 t峄?`'0'` t岷 v峄?tr铆 th峄?4 nh峄?h啤n `'6'`) 鈥?code n脿y vi岷縯 t峄?tr瓢峄沜, gi岷?膽峄媙h m茫 m谩y lu么n 2 ch峄?s峄?nh瓢 VBA g峄慶, nh瓢ng `app.machines` th岷璽 d霉ng 3 ch峄?s峄?(VD006-018, 膽茫 x谩c nh岷璶 qua QR th岷璽 tr瓢峄沜 膽贸). **Bug n脿y khi岷縩 M峄孖 m谩y th岷璽 膽峄乽 r啤i v脿o fallback r峄梟g sai ho脿n to脿n 鈥?QR g峄璱 sai h峄?Color Service** (ch峄峮 nh岷 lu峄搉g h貌a tan/b啤m h贸a ch岷) 鈥?m峄ヽ 膽峄?nghi锚m tr峄峮g cao v矛 岷h h瓢峄焠g tr峄眂 ti岷縫 v岷璶 h脿nh v岷璽 l媒.
  - **Nguy锚n nh芒n bug kh么ng b峄?ph谩t hi峄噉 tr瓢峄沜 膽芒y:** `tests/Unit/WarehouseRoutingServiceTest.php` t峄?t岷 m谩y test b岷眓g m茫 2 ch峄?s峄?(`'VD10'`, `'VD17'`...) qua `Machine::firstOrCreate` 鈥?kh么ng kh峄沺 膽峄媙h d岷g th岷璽 3 ch峄?s峄? n锚n test lu么n pass d霉 code sai v峄沬 d峄?li峄噓 th岷璽.
  - **S峄璦:** 膽峄昳 to脿n b峄?so s谩nh trong `WarehouseRoutingService.php` t峄?so s谩nh chu峄梚 (`isBetween` c农) sang so s谩nh S峄?(tr铆ch s峄?th峄?t峄?m谩y b岷眓g regex `^VD(\d+)$`, h脿m `numBetween` m峄沬). S峄璦 `WarehouseRoutingServiceTest.php` d霉ng 膽煤ng m茫 3 ch峄?s峄?th岷璽 (`VD010`, `VD017`...) + s峄璦 `Machine::firstOrCreate`/`Tank::firstOrCreate` tra c峄﹗ 膽煤ng theo kh贸a unique th岷璽 (tr瓢峄沜 膽贸 truy峄乶 c岷?`name` v脿o 膽i峄乽 ki峄噉 t矛m khi岷縩 kh么ng bao gi峄?kh峄沺 m谩y 膽茫 t峄搉 t岷, g芒y l峄梚 tr霉ng kh贸a `machines_code_key` 鈥?1 bug ph峄?kh谩c l峄?ra khi s峄璦).
- **Ki峄僲 ch峄﹏g:** test th岷璽 qua `curl` (kh么ng ph岷 mock) 鈥?VD007+3C nay tr岷?膽煤ng `mode:PROCESS, route:"THUNG SAT THAP, MAY JIT, MAY DLG", matched_rule:RULE_5`, kh峄沺 ch铆nh x谩c `b24-warehouse-routing.md`. `php artisan test` 鈫?**115/115 PASS (584 assertions)**. `npm run build` s岷h.

### 31. B峄?sung L峄媍h s峄?in tem + n煤t Ch峄峮/thi岷縯 l岷璸 m谩y in lu么n m峄?膽瓢峄 cho Print Station

- **Y锚u c岷:** gi峄?l岷 l峄媍h s峄?in tem b锚n d瓢峄沬 h脿ng ch峄?(膽峄?bi岷縯 m茫 h脿ng n脿o 膽茫 in), h脿ng ch瓢a in ph岷 t么 m脿u 膽峄?(膽煤ng 岷h VBA th岷璽 g峄璱 tr瓢峄沜 膽贸), v脿 c岷 khu v峄眂 ch峄峮/thi岷縯 l岷璸 m谩y in d峄?th岷 h啤n (tr瓢峄沜 膽贸 ch峄?hi峄噉 khi CH漂A g谩n m谩y in).
- **Backend:** th锚m `GET /api/machine-dispatches/history` (`MachineDispatchController::history`) 鈥?li峄噒 k锚 t峄慽 膽a 50 dispatch 膽茫 `queue_state=CONFIRMED` g岷 nh岷, k猫m `print_job` (tr岷g th谩i PENDING/PRINTED/FAILED th岷璽 t峄?Local Agent ack). Th锚m quan h峄?`MachineDispatch::printJobs()` (hasMany, s岷痯 `created_at` desc) v脿 `PrintJob::dispatch()`.
  - **Bug ph峄?ph谩t hi峄噉 khi vi岷縯 quan h峄?** d霉ng `hasOne(...)->latestOfMany('created_at')` (c谩ch chu岷﹏ Laravel) b峄?l峄梚 500 th岷璽 `SQLSTATE[42883]: function max(uuid) does not exist` 鈥?`latestOfMany()` lu么n d霉ng `MAX(id)` cho join aggregate b岷 k峄?c峄檛 s岷痯 x岷縫 ch峄?膽峄媙h, m脿 kh贸a ch铆nh 峄?膽芒y l脿 UUID (Postgres kh么ng c贸 `MAX(uuid)`). 膼峄昳 sang `hasMany` s岷痯 s岷祅 + controller t峄?l岷 ph岷 t峄?膽岷, tr谩nh h岷硁 `ofMany`.
- **Frontend (`PrintStation.vue`):**
  - H脿ng ch峄?in: m峄峣 d貌ng 膽峄乽 t么 n峄乶 膽峄?(`row-not-printed`, badge "Ch瓢a in") 鈥?膽煤ng 媒 ngh末a th岷璽 (m峄峣 d貌ng trong h脿ng ch峄?theo 膽峄媙h ngh末a 膽峄乽 CH漂A in, v矛 action "In tem" = `confirm()` v峄玜 t岷 l峄噉h in v峄玜 膽瓢a d貌ng ra kh峄廼 h脿ng ch峄?lu么n).
  - Th锚m b岷g "馃摐 L峄媍h s峄?in tem g岷 膽芒y" ngay b锚n d瓢峄沬, t么 n峄乶 xanh (`row-printed`), 膽峄峜 t峄?endpoint m峄沬, c贸 n煤t "L脿m m峄沬" v脿 t峄?膽峄檔g refetch c霉ng nh峄媝 poll 8s + ngay sau khi b岷 In tem th脿nh c么ng.
  - Chuy峄僴 khu v峄眂 c岷 h矛nh m谩y in t峄?banner c岷h b谩o 岷﹏/hi峄噉 c贸 膽i峄乽 ki峄噉 (ch峄?khi ch瓢a g谩n) sang 1 n煤t "鈿欙笍 Ch峄峮 / thi岷縯 l岷璸 m谩y in" lu么n c贸 trong banner 膽岷 trang, m峄?panel c岷 h矛nh b岷 c峄?l煤c n脿o k峄?c岷?khi 膽茫 c贸 m谩y in (膽峄昳 m谩y in d峄?d脿ng), t谩i d霉ng 膽煤ng API `PUT /workstations/{id}/local-device-config` 膽茫 c贸.
- **Ki峄僲 ch峄﹏g:** test m峄沬 `ConfirmDispatchTest::test_history_endpoint_lists_confirmed_dispatch_with_print_job_status` (x谩c nh岷璶 膽啤n CH漂A confirm kh么ng c贸 trong l峄媍h s峄?+ c貌n trong h脿ng ch峄? SAU confirm th矛 ng瓢峄 l岷 鈥?膽煤ng r峄漣 h脿ng ch峄? 膽煤ng xu岷 hi峄噉 trong l峄媍h s峄?k猫m `print_job.status=PENDING`). `php artisan test` 鈫?**116/116 PASS (592 assertions)**. `npm run build` s岷h.

### 29. R脿 so谩t VBA m脿n h矛nh Nh岷璸 膽啤n s岷 xu岷 (qu茅t QR MES th岷璽) + x芒y tr岷 qu茅t thay th岷?MES-mock form + v谩 l峄梚 n峄乶 t岷g g芒y "database t峄?ho脿n t谩c" l岷穚 l岷 nhi峄乽 l岷 trong phi锚n

- **Y锚u c岷:** "d霉ng m谩y qu茅t 膽峄?nh岷璸 th么ng tin. B岷 r脿 so谩t l岷 VBA. 膽峄?check l岷 logic cho t么i? sau 膽岷 thi岷縯 k岷?giao di峄噉 cho ph霉 h峄" 鈥?ng瓢峄漣 d霉ng sau 膽贸 g峄璱 k猫m 1 岷h phi岷縰 MES th岷璽 (BEST PACIFIC, m茫 QR "ALL DATA") v脿 1 岷h ch峄 tr峄眂 ti岷縫 MainForm VBA 膽ang ch岷 v峄沬 1 l岷 qu茅t th岷璽, cu峄慽 c霉ng g峄璱 file 岷h QR th岷璽 `F:\DF\mau_phieu_mes.PNG`.
- **Tr铆ch xu岷 VBA th岷璽** (`2.C3 grid load row lock id FB -192(QR).xlsm`, olevba, to脿n b峄?18 module): m脿n h矛nh Nh岷璸 膽啤n CH峄?c贸 1 么 qu茅t (`Box1`), `Box1_AfterUpdate` t峄?t谩ch theo `-` ra color/code/machine/level (4 ph岷 t峄?膽岷) + tr铆ch ri锚ng 膽o岷 `-dye-...-chem-...` b岷眓g `InStr`/`Mid$` (膽峄檆 l岷璸 v峄沬 Split). Th霉ng (Box5) KH脭NG qu茅t 膽瓢峄 鈥?ch峄峮 nhanh t峄?list c峄?膽峄媙h "1A/2B/3C/4D/FB" (`formselect1.frm`). N煤t th岷璽 tr锚n form: SAVE (ghi DB th岷璽 鈥?`btnSAVE_Click`: check tr霉ng `Exists_ColorCode`, 谩p quy t岷痗 250L, insert `tbl_input_all`, n岷縰 confirm2="OK" + c贸 tank th矛 g峄峣 `MoveToSend` ngay), CLEAR, **PH脢 DUY峄員** (x谩c nh岷璶 qua 岷h ch峄 MainForm th岷璽 鈥?ch铆nh l脿 `CommandButton4_Click`, ch峄?set `Box7.Text="OK"`, KH脭NG ghi DB), CHECK (m峄?`checkform` ki峄僲 tra tr霉ng).
- **X谩c minh "QR ALL DATA" b岷眓g b岷眓g ch峄﹏g, kh么ng suy 膽o谩n:** ki峄僲 tra to脿n b峄?9/9 b岷g th岷璽 trong `RECORD.accdb` (`TBL_INPUT_ALL, tbl_ToSend, tbl_ToSend2, tbl_ARCHIVE, tbl_OUTPUT_PROCESSING, tbl_SentLog, tbl_Waiting, WAITING, tblSync`) qua pyodbc 鈥?KH脭NG b岷g n脿o c贸 c峄檛 kh谩ch h脿ng/ng脿y SX/th么ng s峄?c么ng ngh峄?ph峄?gia-n峄搉g 膽峄? **Gi岷 m茫 tr峄眂 ti岷縫 岷h QR th岷璽** (`F:\DF\mau_phieu_mes.PNG`, OpenCV `QRCodeDetector` sau khi crop v霉ng QR + ph贸ng to 3x 鈥?full 岷h g峄慶 kh么ng t峄?nh岷璶 ra) ra chu峄梚 th岷璽: `#EP43110-SE5718-VD04-450-dye-51-Y1104-111.15-44-R2128-33.75-0-B3113-36.45-chem-42-AC02-3600-19-AC06-3600` 鈥?kh峄沺 CH脥NH X脕C 膽峄媙h d岷g `parseDyeScan`/`Box1_AfterUpdate` 膽茫 port t峄?tr瓢峄沜 (color/code/machine/level + dye/chem rack-weight triples), v脿 KH脭NG ch峄゛ kh谩ch h脿ng/ng脿y/nhi峄噒 膽峄?n峄搉g 膽峄?nh瓢 b岷g "Technology mode" in tr锚n phi岷縰 (m茫 h贸a ch岷 trong QR l脿 AC02/AC06, kh谩c h岷硁 "AC68" ghi trong b岷g ph峄?gia tr锚n phi岷縰 鈥?x谩c nh岷璶 膽芒y l脿 2 lu峄搉g d峄?li峄噓 kh谩c nhau, b岷g ph峄?gia/n峄搉g 膽峄?nhi峄乽 kh岷?n膬ng c岷 ri锚ng cho Color Service qua `tbl_status`, kh么ng qua QR n脿y).
- **X芒y tr岷 qu茅t th岷璽 thay th岷?"T岷 l么 t峄?MES (Gi岷?l岷璸)"** trong `ProductionBatches.vue`: panel "馃敨 Qu茅t 膽啤n s岷 xu岷" v峄沬 1 么 qu茅t l峄沶 t峄?focus, g峄峣 `POST /production-batches/scan-parse` (m峄沬 鈥?port `Box1_AfterUpdate`/`CleanLeadingGarbage` nguy锚n v膬n v脿o `QrPayloadService::parseOrderEntryScan()`), t峄?resolve m茫 m谩y qu茅t 膽瓢峄 (vd "VD04") sang `machine_id` th岷璽 (chu岷﹏ ho谩 2-3 ch峄?s峄?qua `normalizeVdCode`), dropdown ch峄峮 Th霉ng l峄峜 theo m谩y 膽茫 resolve, hi峄噉 raw_qr_dye/raw_qr_chem d岷g preview, n煤t SAVE/CLEAR/PH脢 DUY峄員/CHECK kh峄沺 膽煤ng h脿nh vi th岷璽 (PH脢 DUY峄員+ch峄峮 Th霉ng tr瓢峄沜 khi SAVE = l瓢u v脿 duy峄噒 ngay trong 1 l岷 g峄峣, gi峄憂g VBA).
- **Ph谩t hi峄噉 + v谩 2 l峄?h峄昻g d峄?li峄噓 n峄乶 khi build t铆nh n膬ng n脿y:**
  1. `app.tanks` kh么ng h峄?c贸 tank n脿o cho d岷 m谩y VD (ch峄?c贸 cho L1-4/T5-8, ph峄 v峄?module C岷 h矛nh n瓢峄沜) 鈫?quy t岷痗 250L trong `ApproveProductionOrderService` ch瓢a t峄玭g k铆ch ho岷 膽瓢峄. `app.machines` c农ng ch峄?c贸 VD006-013, THI岷綰 VD001-005/014-018 鈥?x谩c nh岷璶 thi岷縰 b岷眓g ch铆nh 2 m岷玼 QR th岷璽 (VD04, VD02) kh么ng resolve 膽瓢峄.
  2. Th锚m c峄檛 `raw_qr_dye`/`raw_qr_chemical` v脿o `production_batches` (tr瓢峄沜 膽芒y kh么ng c贸 ch峄?l瓢u, m岷 d峄?li峄噓 th么 qu茅t 膽瓢峄 鈥?VBA gi峄?xuy锚n su峄憈 `tbl_input_all`鈫抈tbl_tosend`). Th锚m ch岷穘 tr霉ng color+code 峄?`store()` (膽煤ng `Exists_ColorCode`, ch峄?t铆nh 膽啤n 膽ang `NEW`). Th锚m `GET /machines`, `GET /tanks` (danh m峄 th岷璽 thay m岷g hardcode c农 trong frontend).
- **Ph谩t hi峄噉 nguy锚n nh芒n g峄慶 "database t峄?ho脿n t谩c" (膽茫 b谩o nghi v岷 "ti岷縩 tr矛nh kh谩c" 峄?l瓢峄 tr瓢峄沜 鈥?KH脭NG 膽煤ng, 膽茫 t矛m ra nguy锚n nh芒n th岷璽):** `tests/TestCase.php::setUp()` (ch岷 1 l岷 m峄梚 ti岷縩 tr矛nh `php artisan test`) **DROP CASCADE + t岷 l岷 to脿n b峄?schema `app`+`public` r峄搃 ch岷 `migrate` + `db:seed`** 鈥?v脿 `MachinesAndTanksSeeder` (g峄峣 b峄焛 `DatabaseSeeder`) xo谩 s岷h `app.tanks`/`app.machines` r峄搃 ch峄?t岷 l岷 L1-4/T5-8 + VD006-013 g峄慶. Ngh末a l脿: **m峄峣 l岷 ch岷 `php artisan test`** trong phi锚n n脿y 膽峄乽 芒m th岷 xo谩 s岷h d峄?li峄噓 t么i v峄玜 th锚m b岷眓g tinker/migration (gi岷 th铆ch c岷?vi峄嘽 tank VD-range bi岷縩 m岷 2 l岷 V脌 kiosk token b峄?v么 hi峄噓 ho谩 l岷穚 l岷 nhi峄乽 l岷 tr瓢峄沜 膽贸 鈥?kh么ng ph岷 ti岷縩 tr矛nh l岷?n脿o can thi峄噋, m脿 l脿 ch铆nh v貌ng l岷穚 test c峄 d峄?谩n). **膼茫 s峄璦 d峄﹖ 膽i峄僲:** 膽瓢a to脿n b峄?logic seed d岷 VD001-018 + tank "1A/2B/3C/4D/FB" m峄梚 m谩y V脌O TH岷睳G `MachinesAndTanksSeeder`, 膽峄?n贸 s峄憂g s贸t qua m峄峣 l岷 `db:seed` t峄?膽峄檔g c峄 `TestCase.php` thay v矛 b峄?ch铆nh seeder 膽贸 xo谩 m岷.
- **Ki峄僲 ch峄﹏g:** 2 m岷玼 QR th岷璽 膽峄慽 chi岷縰 qua `curl` tr峄眂 ti岷縫 `POST /production-batches/scan-parse` 鈫?t谩ch 膽煤ng 100% c岷?2 m岷玼 (EP43110/SE5718/VD04/450 v脿 AP88646/T6276/VD02/50). `npm run build` s岷h. `php artisan test` 鈫?**115/115 PASS (584 assertions)** 鈥?ch岷 L岷禤 L岷營 2 l岷 li锚n ti岷縫 膽峄?x谩c nh岷璶 kh么ng c貌n flaky do seeder n峄痑 (tr瓢峄沜 khi s峄璦 seeder: 113/115, lu么n fail 膽煤ng 2 test li锚n quan tank VD-range).
- **Ch瓢a l脿m (ngo脿i ph岷 vi h么m nay, c岷 x谩c nh岷璶 th锚m):** d峄?li峄噓 "Technology mode" (nhi峄噒 膽峄?ph峄?gia/n峄搉g 膽峄?theo Box) in tr锚n phi岷縰 MES 鈥?CH漂A x谩c 膽峄媙h 膽瓢峄 ngu峄搉/膽铆ch th岷璽 trong h峄?th峄憂g hi峄噉 t岷 (gi岷?thuy岷縯: c岷 ri锚ng cho Color Service qua `tbl_status`, c岷 x谩c nh岷璶 t峄?ng瓢峄漣 d霉ng tr瓢峄沜 khi thi岷縯 k岷?t铆ch h峄 Color Service).

### 32. Unlock VBA workbook "semiauto-small-scale" + x芒y 2 ph岷 c貌n thi岷縰 cho Weighing Station: Tra c峄﹗ b谩n th脿nh ph岷﹎ (checker) v脿 Phi岷縰 c芒n t峄昻g h峄 (print slip)

- **Y锚u c岷:** ng瓢峄漣 d霉ng g峄璱 `semiautosmall scale  deltastablefinal1_UNLOCKED.xlsm` (膽岷穞 t岷 `c:\laragon\www\DF`, VBA project 膽茫 膽瓢峄 unlock 鈥?`Protection=0` x谩c nh岷璶 qua Excel COM), y锚u c岷 d峄盿 v脿o 膽贸 l脿m ph岷 c貌n thi岷縰 cho `/weighing-station` (ch岷 tr锚n `DFwed`, c峄昻g 3001 鈥?x谩c nh岷璶 qua `vite.config.ts`, ph芒n bi峄噒 v峄沬 `DFwed2` c峄昻g 3002 l脿 b岷 sao/nh谩nh kh谩c).
- **Tr铆ch xu岷 l岷 to脿n b峄?VBA** qua Excel COM Automation (`VBComponents`/`CodeModule.Lines`, kh么ng d霉ng olevba v矛 m谩y n脿y kh么ng c贸 Python) 鈥?x谩c nh岷璶 22 module kh峄沺 100% v峄沬 "workbook C" (`semiauto-small-scale...`) 膽茫 audit tr瓢峄沜 膽贸 trong `p0-c-scale-algorithm.md`/`pilot-blockers.md` (PB-1 CleanWeight, PB-2 StableFilter 膽茫 s峄璦 xong 2026-07-17; tare/delta 膽茫 port v脿o `WeighingStation.vue` 2026-07-18) 鈥?kh么ng ph谩t hi峄噉 sai l峄嘽h logic m峄沬 so v峄沬 audit c农.
- **2 ph岷 x谩c nh岷璶 C脪N THI岷綰 th岷璽** (膽峄慽 chi岷縰 code hi峄噉 c贸, kh么ng suy 膽o谩n t峄?t脿i li峄噓 c农): `checkform` (tra c峄﹗ b谩n th脿nh ph岷﹎ theo COLOR+CODE+s峄?ng脿y, m峄?t峄?`scaleform.btnCheck_Click`) v脿 `scaleform.btnPrint_Click` (phi岷縰 c芒n t峄昻g h峄 d岷g b岷g, in tr峄眂 ti岷縫 qua TSC). X谩c nh岷璶 qua Explore agent: 0 route/controller/view n脿o t峄搉 t岷 cho 2 t铆nh n膬ng n脿y tr瓢峄沜 膽芒y 鈥?膽煤ng kh峄沺 `pilot-blockers.md` m峄 "C芒n 鈥?tra c峄﹗ checker" (FIX-009, KH脭NG ch岷穘 pilot).
- **Backend:**
  - `ScaleMeasurementController::checker()` (m峄沬) 鈥?`GET /api/scale-measurements/checker?color=&code=&days_back=`: l峄峜 `scale_measurements` theo `color`+`product_code` (+ `measured_at >= now-N ng脿y` t霉y ch峄峮), gom nh贸m theo `legacy_batch_id` (kh谩c Access c农 v矛 schema web 膽茫 ph岷硁g h贸a 膽峄? kh么ng c岷 t谩ch d貌ng header/detail). V矛 `process_color` x谩c nh岷璶 v岷玭 ch岷縯/lu么n NULL (膽茫 ki峄僲 l岷, kh峄沺 `p0-c-scale-algorithm.md`) v脿 h峄?m峄沬 ch峄?t岷 `ScaleMeasurement` khi l瓢u th脿nh c么ng (trong dung sai ho岷穋 c贸 Override 鈥?kh么ng c贸 "REJECTED 膽茫 l瓢u" nh瓢 Access), suy ra c峄?hi峄僴 th峄?th岷璽 t峄?`weighing_job_items.override_approved` c峄 item li锚n k岷縯 thay v矛 b峄媋 l岷 c峄檛 ch岷縯.
  - `WeighingJobController::printSlip()` (m峄沬) 鈥?`POST /api/weighing-jobs/{id}/print-slip`, 膽i qua 膽煤ng pipeline `PrintJob`/Local Agent hi峄噉 c贸 (kh么ng t峄?v岷?in tr峄眂 ti岷縫 t峄?tr矛nh duy峄噒 鈥?膽煤ng CLAUDE.md m峄 5). Kh么ng b岷痶 bu峄檆 job COMPLETED (gi峄?膽煤ng h脿nh vi VBA g峄慶 鈥?`btnPrint_Click` in 膽瓢峄 b岷 c峄?l煤c n脿o, d貌ng ch瓢a c芒n hi峄噉 "PENDING").
  - Route m峄沬 + `workstation.guard:PRINT_SLIP` (th锚m mapping capability `PRINT`/`SMALL_SCALE` v脿o `WorkstationGuard::mapActionToCapability`/`mapActionToBusinessCapability` 鈥?action code ho脿n to脿n free-form, kh么ng c岷 migration/seeder).
- **Frontend (`WeighingStation.vue`):** n煤t "馃攳 Tra c峄﹗" tr锚n banner m峄?modal tra c峄﹗ (COLOR/CODE/s峄?ng脿y 鈫?danh s谩ch m岷?gom nh贸m, m峄梚 m岷?hi峄噉 b岷g rack/v岷璽 t瓢/kh峄慽 l瓢峄g/tr岷g th谩i); n煤t "馃枿锔?In phi岷縰 c芒n" trong khu v峄眂 job 膽ang c芒n, g峄峣 `print-slip` b岷 k峄?job 膽茫 ho脿n t岷 hay ch瓢a.
- **Ki峄僲 ch峄﹏g:** vi岷縯 `tests/Feature/ScaleCheckerAndPrintSlipTest.php` (4 test) theo 膽煤ng convention nh脿 (DatabaseTransactions, `WorkstationsSeeder`, Sanctum). **KH脭NG ch岷 膽瓢峄 `php artisan test` trong m么i tr瓢峄漬g n脿y** 鈥?DB test Postgres (c峄昻g 5433, container `df-postgres`) y锚u c岷 Docker, nh瓢ng m谩y n脿y kh么ng c贸 Docker CLI/daemon l岷玭 PostgreSQL c脿i native (膽茫 x谩c minh `docker`, `psql`, `pg_ctl` 膽峄乽 kh么ng t峄搉 t岷). Thay v脿o 膽贸 膽茫 **smoke-test logic th岷璽 tr峄眂 ti岷縫** b岷眓g c谩ch g峄峣 th岷硁g 2 controller method qua PHP script t岷 (`DB::beginTransaction()`...`rollBack()`, kh么ng 膽峄峮g d峄?li峄噓) nh岷痬 v脿o DB dev SQLite th岷璽 膽ang ch岷 (backend `php artisan serve` c峄昻g 8002, `.env` dev d霉ng `DB_CONNECTION=sqlite`) 鈥?c岷?2 endpoint tr岷?膽煤ng k岷縯 qu岷?mong 膽峄 (gom nh贸m 膽煤ng theo batch, l峄峜 膽煤ng theo color/code, `days_back` lo岷 膽煤ng b岷 ghi c农, c峄?override 膽煤ng, TSPL phi岷縰 c芒n sinh 膽煤ng n峄檌 dung + 膽煤ng tr岷g th谩i ACCEPTED/PENDING theo t峄玭g item). `npx vue-tsc --noEmit` s岷h, `npm run build` s岷h.
- **C貌n n峄?(kh么ng ch岷穘, c岷 l脿m khi c贸 m么i tr瓢峄漬g 膽峄?Docker/Postgres):** ch岷 th岷璽 `php artisan test --filter=ScaleCheckerAndPrintSlipTest` 膽峄?x谩c nh岷璶 PASS tr锚n schema Postgres 膽岷 膽峄?(test 膽茫 vi岷縯 膽煤ng convention, 膽茫 smoke-test logic t瓢啤ng 膽瓢啤ng qua SQLite, nh瓢ng ch瓢a ch岷 qua ch铆nh PHPUnit/Postgres nh瓢 quy tr矛nh chu岷﹏ c峄 d峄?谩n).

### 33. B峄?quy t岷痗 nghi峄噋 v峄?"MINIMUM LEVEL 250L" theo y锚u c岷 ng瓢峄漣 d霉ng 鈥?ng瓢峄漣 d霉ng b谩o kh么ng duy峄噒 膽瓢峄 膽啤n 峄?`/production-batches`

- **Ng瓢峄漣 d霉ng b谩o:** v脿o `http://localhost:3001/production-batches`, b岷 "Duy峄噒" th矛 hi峄噉 alert `MINIMUM LEVEL 250L`, kh么ng duy峄噒 膽瓢峄.
- **Truy v岷縯:** kh么ng ph岷 bug 鈥?膽煤ng h脿nh vi c贸 ch峄?膽铆ch c峄 `ApproveProductionOrderService::assertMinLevelRule()` (port nguy锚n v膬n t峄?VBA `btnSAVE_Click`): ch岷穘 duy峄噒 khi m谩y thu峄檆 d岷 VD006鈥揤D013 V脌 Th霉ng tr峄檔 l脿 1A/2B V脌 M峄ヽ n瓢峄沜 (`level_code`, ch峄峮 t峄?dropdown c峄?膽峄媙h 50/100/250/450) < 250. 膼啤n ng瓢峄漣 d霉ng 膽ang duy峄噒 r啤i 膽煤ng 3 膽i峄乽 ki峄噉 n脿y (m峄ヽ n瓢峄沜 ch峄峮 50 ho岷穋 100).
- **膼茫 h峄廼 r玫 h瓢峄沶g x峄?l媒** (gi峄?quy t岷痗 + th锚m Override c贸 audit log, hay x贸a h岷硁) 鈥?ng瓢峄漣 d霉ng ch峄峮 x贸a h岷硁, x谩c nh岷璶 qua 2 c芒u tr岷?l峄漣 li锚n ti岷縫 ("th铆ch ch峄峮 nh瓢 n脿o th矛 ch峄峮", "膽煤ng m峄眂 n瓢峄沜 峄?ph岷 select c贸 l脿 膽瓢峄") = ch岷 nh岷璶 m峄峣 gi谩 tr峄?h峄 l峄?trong dropdown m峄ヽ n瓢峄沜, kh么ng ph芒n bi峄噒 m谩y/th霉ng.
- **S峄璦:** x贸a `assertMinLevelRule()` + c谩c h岷眓g s峄?`MIN_LEVEL_*` (膽茫 kh么ng c貌n d霉ng) kh峄廼 `ApproveProductionOrderService.php`. C岷璸 nh岷璽 3 test 膽ang assert h脿nh vi ch岷穘 c农 (`ApproveProductionOrderTest::test_approve_rejects_when_min_level_250_violated` 鈫?膽峄昳 th脿nh `test_approve_allows_low_level_on_previously_restricted_machine_tank` expect 201; x贸a `test_approve_min_level_rule_does_not_apply_outside_range` v矛 kh么ng c貌n 媒 ngh末a; `ProductionOrderScanEntryTest::test_250l_rule_fires_using_real_seeded_tank_data` 鈫?膽峄昳 th脿nh `test_approve_allows_low_level_using_real_seeded_tank_data` expect 201). D峄峮 c谩c comment tham chi岷縰 quy t岷痗 250L 膽茫 l峄梚 th峄漣 峄?`ProductionBatches.vue` v脿 `ProductionBatchesList.vue` (frontend kh么ng c岷 s峄璦 logic 鈥?dropdown m峄ヽ n瓢峄沜 v峄憂 膽茫 lu么n hi峄噉 膽峄?4 gi谩 tr峄? kh么ng l峄峜 theo m谩y/th霉ng).
- **Ki峄僲 ch峄﹏g:** `php -l` s岷h cho c岷?3 file PHP 膽茫 s峄璦. **KH脭NG ch岷 膽瓢峄 `php artisan test`** trong m么i tr瓢峄漬g n脿y 鈥?DB test Postgres loopback (`127.0.0.1:5433`) kh么ng c贸 ti岷縩 tr矛nh l岷痭g nghe, kh么ng c贸 Docker CLI (膽煤ng h岷 ch岷?m么i tr瓢峄漬g 膽茫 ghi nh岷璶 峄?m峄 32); `.env` dev tr峄?t峄沬 `DB_HOST=10.0.60.209` (host m岷g th岷璽, kh么ng ph岷 localhost) n锚n KH脭NG th峄?k岷縯 n峄慽/ghi th峄?v脿o 膽贸 膽峄?tr谩nh r峄 ro 膽峄g d峄?li峄噓 th岷璽 ngo脿i ph岷 vi y锚u c岷. `npx vue-tsc --noEmit` (frontend) s岷h.
- **C貌n n峄?** ch岷 `php artisan test --filter="ApproveProductionOrderTest|ProductionOrderScanEntryTest"` tr锚n m么i tr瓢峄漬g c贸 Postgres test DB th岷璽 膽峄?x谩c nh岷璶 2 test 膽茫 s峄璦 PASS 膽煤ng nh瓢 k峄?v峄峮g.

### 34. B峄?sung n煤t "In tem" t瓢啤ng th铆ch k铆ch c峄?m脿n h矛nh 峄?`/print-station` + s峄璦 bug th岷璽: 岷h QR kh么ng hi峄僴 th峄?峄?`/chemical-call/monitor` v脿 `/chemical-call/pending`

- **Y锚u c岷 1 (responsive):** ng瓢峄漣 d霉ng b谩o trang `/print-station` kh么ng "th铆ch nghi theo k铆ch c峄? v脿 b峄?m岷 n煤t khi thu nh峄?m脿n h矛nh. Nguy锚n nh芒n: `.station-banner`, `.remote-banner` v脿 v脿i h脿ng n煤t kh谩c trong `PrintStation.vue` d霉ng `display:flex` kh么ng c贸 `flex-wrap`, c谩c n煤t `.btn` l岷 c贸 `white-space:nowrap` (style.css) n锚n kh么ng co 膽瓢峄 鈥?khi h岷縯 ch峄? h脿ng flex tr脿n ra ngo脿i v脿 b峄?`.layout-main { overflow:hidden }` (AppLayout.vue) c岷痶 m岷, 膽煤ng hi峄噉 t瓢峄g "m岷 n煤t". **S峄璦:** th锚m `flex-wrap:wrap` + `gap` cho `.station-banner`/`.banner-right`/`.dev-badge`/`.remote-banner`/`.banner-content`, th锚m `min-width` h峄 l媒 cho `.manual-input`, 膽瓢a `.printer-config-form`/`.details-grid` v脿o breakpoint 768px s岷祅 c贸 (collapse v峄?1 c峄檛), th锚m `flex-wrap` cho `.preview-modal-actions`. Ch峄?s峄璦 CSS, kh么ng 膽峄昳 logic.
- **Y锚u c岷 2 (bug 岷h QR):** ng瓢峄漣 d霉ng b谩o "岷h kh么ng nh矛n th岷 g矛" 鈥?x谩c nh岷璶 c峄?th峄?l脿 QR 峄?`/chemical-call/monitor`. Truy v岷縯: `MachineChemicalChannel::qrImageUrl()` (backend/app/Models/MachineChemicalChannel.php:56) tr岷?v峄?膽瓢峄漬g d岷玭 **t瓢啤ng 膽峄慽** (`/chemical-qr/QR_{machine}_{combo}.jpg`, ph峄 v峄?t末nh t峄?`public/chemical-qr/`, 膽茫 x谩c nh岷璶 c贸 38 岷h th岷璽 trong th瓢 m峄 n脿y). `ChemicalCallQrImage.vue` (d霉ng chung b峄焛 `ChemicalCallMonitor.vue` V脌 `ChemicalCallPending.vue`) g岷痭 th岷硁g gi谩 tr峄?n脿y v脿o `<img :src>` 鈥?v矛 backend ch岷 c峄昻g 8500 c貌n frontend ch岷 c峄昻g 3001 (xem `main.ts::axios.defaults.baseURL`), tr矛nh duy峄噒 t峄?resolve 膽瓢峄漬g d岷玭 t瓢啤ng 膽峄慽 theo origin c峄 frontend (3001), ra 404, 岷h tr岷痭g kh么ng hi峄噉 g矛. 膼芒y l脿 bug th岷璽, kh么ng ph岷 do thao t谩c sai.
- **S峄璦:** `ChemicalCallQrImage.vue` t峄?gh茅p domain backend th岷璽 (`http://${window.location.hostname}:8500${src}`) tr瓢峄沜 khi g谩n v脿o `<img src>` 鈥?膽煤ng pattern 膽茫 c贸 s岷祅 trong `AppLayout.vue:278` (`agentInstallerUrl`) cho c霉ng v岷 膽峄?(link t岷 file t末nh t峄?backend). S峄璦 1 ch峄?(component d霉ng chung) fix c岷?2 trang `/chemical-call/monitor` v脿 `/chemical-call/pending`.
- **Ki峄僲 ch峄﹏g:** `npx vue-tsc --noEmit` s岷h, `npm run build` th脿nh c么ng kh么ng l峄梚 (c岷?2 l岷 s峄璦, ch岷 sau m峄梚 膽峄 s峄璦).

### 35. Fix b峄?sung m峄 34: gh茅p domain backend th么i ch瓢a 膽峄?鈥?d岷 "+" trong t锚n file 岷h QR b峄?hi峄僽 sai

- **Ng瓢峄漣 d霉ng b谩o l岷:** sau fix m峄 34, `http://localhost:3001/chemical-call/monitor` v岷玭 "kh么ng th岷 岷h 膽瓢峄 膽岷﹜ ra".
- **Truy v岷縯 b岷眓g curl tr峄眂 ti岷縫 (kh么ng 膽o谩n):** `curl http://localhost:8500/chemical-qr/QR_VD006_AC77+AC78.jpg` tr岷?v峄?**HTTP 200 nh瓢ng `content-type: text/html`** 鈥?th芒n tr岷?l峄漣 l脿 trang welcome m岷穋 膽峄媙h c峄 Laravel, kh么ng ph岷 file 岷h. So s谩nh v峄沬 file kh么ng c贸 d岷 `+` (`QR_VD001_AC68.jpg`, tr岷?膽煤ng `image/jpeg`) v脿 v峄沬 c霉ng URL nh瓢ng encode `+` th脿nh `%2B` (c农ng tr岷?膽煤ng `image/jpeg`) x谩c nh岷璶 ch铆nh x谩c nguy锚n nh芒n: d岷 `+` th么 trong URL path b峄?t岷g ph峄 v峄?static file hi峄僽 sai (kh么ng kh峄沺 t锚n file th岷璽 tr锚n 膽末a), request r啤i qua route Laravel m岷穋 膽峄媙h thay v矛 tr岷?file 鈥?v矛 `qrImageUrl()` (m峄 34) tr岷?path ch峄゛ `+` th么 n锚n d霉 膽茫 gh茅p 膽煤ng domain:port, 岷h v岷玭 kh么ng t岷 膽瓢峄.
- **S峄璦:** `MachineChemicalChannel::qrImageUrl()` 鈥?v岷玭 `file_exists()` ki峄僲 tra b岷眓g t锚n file th岷璽 (c贸 `+` th么, 膽煤ng t锚n file tr锚n 膽末a), nh瓢ng khi tr岷?URL th矛 `rawurlencode($filename)` (ch峄?encode ph岷 filename, kh么ng encode c岷?path) 鈫?tr岷?v峄?`/chemical-qr/QR_VD006_AC77%2BAC78.jpg`.
- **Ki峄僲 ch峄﹏g:** `curl` tr峄眂 ti岷縫 URL m峄沬 tr岷?膽煤ng `HTTP 200 image/jpeg` 膽煤ng k铆ch th瓢峄沜 file th岷璽; `php artisan tinker` g峄峣 `qrImageUrl()` x谩c nh岷璶 output 膽煤ng d岷g `%2B`; `npx vue-tsc --noEmit` s岷h.

### 36. S峄璦 404 t岷 c么ng c峄?DF Agent 鈥?chuy峄僴 Inno Setup .exe sang g贸i MSI theo vai tr貌, b峄?Token, kh峄焛 膽峄檔g l岷 Reverb

- **B峄慽 c岷h:** Link "T岷 C脭NG C峄? tr锚n sidebar b谩o 404 v矛 `backend/public/downloads/` (gitignored, ch峄?l脿 n啤i deploy) ch瓢a t峄玭g c贸 file `.exe` build s岷祅. Qua nhi峄乽 v貌ng trao 膽峄昳, ng瓢峄漣 d霉ng 膽峄昳 媒 nhi峄乽 l岷 v峄?h矛nh th峄ヽ ph芒n ph峄慽 鈥?ghi l岷 quy岷縯 膽峄媙h CU峄怚 C脵NG 膽茫 tri峄僴 khai, kh么ng ph岷 c谩c ph瓢啤ng 谩n trung gian 膽茫 b峄?
- **Ph谩t hi峄噉 #1 鈥?Windows Defender t峄?x贸a file `.exe` build t峄?Inno Setup:** build xong `DFAgentSetup.exe` b峄?Defender c谩ch ly ngay l岷璸 t峄ヽ (`Program:Win32/Wacapew.A!ml`, heuristic false-positive 膽i峄僴 h矛nh v峄沬 installer t峄?ch岷 `sc.exe` 岷﹏ c峄璦 s峄?膽峄?膽膬ng k媒 service) 鈥?x谩c nh岷璶 qua `Get-MpThreatDetection`. Th锚m exclusion Defender t岷 th峄漣 kh么ng gi岷 quy岷縯 膽瓢峄 v矛 l峄梚 l岷穚 l岷 峄?M峄孖 m谩y t岷 file (k峄?c岷?m谩y tr岷 th岷璽), kh么ng ph岷 v岷 膽峄?ri锚ng m谩y dev.
- **Quy岷縯 膽峄媙h #1 鈥?chuy峄僴 sang WiX Toolset (MSI) thay Inno Setup:** d霉ng `ServiceInstall`/`ServiceControl` native c峄 MSI thay v矛 shell `sc.exe`, gi岷 h岷硁 nguy c啤 b峄?g岷痭 nh茫n heuristic 鈥?x谩c nh岷璶 b岷眓g th峄眂 nghi峄噈 (build nhi峄乽 l岷, kh么ng l岷 n脿o b峄?Defender 膽峄檔g t峄沬). C脿i `WixToolset.UI.wixext` 5.0.2 (ph岷 ghim 膽煤ng version kh峄沺 `wix` CLI 5.0.2, b岷 m峄沬 nh岷 `7.0.0` kh么ng t瓢啤ng th铆ch). `<UIRef Id="WixUI_Minimal"/>` b峄?l峄梚 WIX0094 "inaccessible due to its protection level" 鈥?bug 膽茫 bi岷縯 c峄 WiX v5 CLI (`wix build`, kh么ng ph岷 MSBuild) 鈥?kh岷痗 ph峄 b岷眓g c谩ch l岷痯 th峄?c么ng `UIRef Id="WixUI_Common"` + khai b谩o tr峄眂 ti岷縫 `TextStyle`/`DialogRef`/`Publish` thay cho aggregate `WixUI_Minimal`.
- **Quy岷縯 膽峄媙h #2 鈥?nhi峄乽 g贸i MSI ri锚ng theo vai tr貌 v岷璽 l媒, kh么ng ph岷 1 g贸i c岷 h矛nh chung:** ng瓢峄漣 d霉ng ban 膽岷 x谩c nh岷璶 c岷 3 m谩y t铆nh 膽峄檆 l岷璸 鈥?m谩y in ri锚ng cho `/print-station`, m谩y in ri锚ng cho `/weighing-station`, m谩y c芒n ri锚ng cho `/weighing-station` 鈥?build c霉ng 1 file `agent/installer/DFAgentSetup.wxs` qua bi岷縩 ti峄乶 x峄?l媒 `StationId`+`AppSettingsFile`+`PackageVersion` ra 3 file: `DFAgentSetup-PrintStation.msi` (`WS-PRINT-STATION`), `DFAgentSetup-WeighingPrinter.msi` (`WS-WEIGH-PRINTER`), `DFAgentSetup-WeighingScale.msi` (`WS-WEIGH-SCALE`). S峄璦 `agent/Worker.cs` th锚m `Workstation:Role` (`PRINT_ONLY`/`SCALE_ONLY`/`BOTH`, m岷穋 膽峄媙h `BOTH` 膽峄?t瓢啤ng th铆ch ng瓢峄) 膽峄?t岷痶 v貌ng l岷穚 c芒n ho岷穋 v貌ng l岷穚 in kh么ng li锚n quan t峄沬 vai tr貌 膽茫 ch峄峮. **Quy岷縯 膽峄媙h cu峄慽 (sau khi test th岷璽):** ng瓢峄漣 d霉ng 膽峄昳 媒 g峄檖 l岷 鈥?g贸i `WeighingScale` d霉ng `Role: BOTH` (v峄玜 膽峄峜 c芒n v峄玜 nh岷璶 l峄噉h in, d霉ng chung 1 m茫 tr岷 `WS-WEIGH-SCALE` cho c岷?2 vi峄嘽 tr锚n `/weighing-station`), g贸i `WeighingPrinter` (in ri锚ng, kh么ng c芒n) v岷玭 gi峄?l岷 l脿m l峄盿 ch峄峮 ph峄?trong menu t岷 cho tr瓢峄漬g h峄 th岷璽 s峄?c岷 t谩ch 2 m谩y. 膼峄昳 nh茫n hi峄僴 th峄?trong `AppLayout.vue` cho r玫: "M谩y in ri锚ng 鈥?Weighing Station (kh么ng k猫m c芒n)" / "M谩y c芒n + m谩y in 鈥?Weighing Station (g峄檖 1 m谩y)".
- **Quy岷縯 膽峄媙h #3 (膽峄昳 b岷 m岷璽, 膽茫 x谩c nh岷璶 r玫 v峄沬 ng瓢峄漣 d霉ng) 鈥?b峄?token, backend t峄?膽膬ng k媒 workstation:** ng瓢峄漣 d霉ng y锚u c岷 "c脿i l脿 d霉ng th么i, kh么ng c岷 h矛nh g矛 c岷?, 膽瓢峄 h峄廼 l岷 r玫 r脿ng v峄?膽谩nh 膽峄昳 b岷 m岷璽 (b岷 k峄?m谩y n脿o trong LAN ch岷 膽瓢峄 backend 膽峄乽 t峄?x瓢ng danh 膽瓢峄 1 trong c谩c `workstation_id` n脿y, kh么ng c貌n token b岷 v峄? 鈥?ng瓢峄漣 d霉ng x谩c nh岷璶 膽峄搉g 媒 2 l岷. S峄璦 `backend/app/Http/Middleware/AgentAuth.php`: khi request KH脭NG c贸 header `X-Workstation-Token`, t峄?`Workstation::firstOrCreate(['code' => $claimedId], ['name' => ..., 'type' => 'AUTO_REGISTERED', 'status' => 'ACTIVE'])` thay v矛 tr岷?401; 膽瓢峄漬g token c农 (workstation 膽茫 c岷 token th岷璽) gi峄?nguy锚n h脿nh vi c农, kh么ng ph谩 v峄?g矛. **Ch瓢a ch岷 膽瓢峄 test t峄?膽峄檔g cho middleware n脿y** 鈥?m谩y dev kh么ng c贸 Postgres test DB (c峄昻g 5433 connection refused) 鈥?ch峄?so谩t logic th峄?c么ng + test tay b岷眓g `Invoke-WebRequest` gi岷?l岷璸 Agent.
- **Bug th岷璽 ph谩t hi峄噉 khi test tay #1 鈥?403 oan 峄?l岷 g峄峣 膽岷 ti锚n c峄 tr岷 t峄?膽膬ng k媒 m峄沬:** `firstOrCreate()` kh么ng t峄?n岷 l岷 attribute `status` t峄?default c峄檛 DB v脿o instance v峄玜 t岷 trong C脵NG request, khi岷縩 `$workstation->active` (accessor d峄盿 v脿o `status === 'ACTIVE'`) tr岷?`false` ngay 峄?request 膽岷 ti锚n d霉 tr岷 ho脿n to脿n h峄 l峄?鈥?c谩c request sau m峄沬 膽煤ng v矛 膽茫 fetch l岷 t峄?DB. S峄璦 b岷眓g c谩ch g谩n r玫 `'status' => 'ACTIVE'` ngay trong m岷g create-attributes c峄 `firstOrCreate`, kh么ng d峄盿 v脿o default c峄檛. X谩c nh岷璶 b岷眓g test tay: tr岷 ho脿n to脿n m峄沬 (`WS-TEST-FIRSTCALL`) g峄峣 l岷 膽岷 tr岷?200 sau khi s峄璦 (tr瓢峄沜 膽贸 403).
- **Bug th岷璽 ph谩t hi峄噉 khi test tay #2 鈥?ph岷 t峄?tay "C岷 h矛nh c芒n ngay"/"C岷 h矛nh m谩y in ngay" m峄沬 h岷縯 c岷h b谩o:** `QrScanPanel.vue` ch岷穘 m脿n h矛nh c芒n b岷眓g c岷h b谩o "鈿狅笍 Tr岷 ch瓢a g谩n thi岷縯 b峄?C芒n"/"鈿狅笍 Tr岷 ch瓢a g谩n m谩y in ch铆nh" d峄盿 v脿o `assigned_scale_device_id`/`assigned_printer_device_id` 鈥?2 tr瓢峄漬g n脿y KH脭NG t峄?c贸 d霉 Agent 膽茫 b谩o s峄?c芒n th岷璽 l锚n cache (`/devices/readings` ch峄?ghi Cache, kh么ng 膽峄g t峄沬 b岷g `operation_client_devices`). S峄璦 `DeviceController::storeReading()`: khi nh岷璶 s峄?c芒n th岷璽 l岷 膽岷 cho 1 tr岷, t峄?`Device::firstOrCreate(['code' => "SCALE_{workstation_id}"], ...)` r峄搃 g谩n `OperationClientDevice` role `PRIMARY_SCALE` 鈥?t谩i d霉ng 膽煤ng c啤 ch岷?膽茫 c贸 s岷祅 峄?`WorkstationLocalConfigController::updateDeviceConfig` (n煤t c岷 h矛nh th峄?c么ng), ch峄?t峄?膽峄檔g h贸a b瓢峄沜 g谩n. L瓢u 媒 an to脿n: match theo `code` (string) ch峄?kh么ng `orWhere('id', ...)` v矛 c峄檛 `id` l脿 bigint 鈥?Postgres l峄梚 ngay "invalid input syntax for type bigint" n岷縰 so s谩nh v峄沬 chu峄梚 kh么ng ph岷 s峄? **Ch瓢a l脿m t瓢啤ng t峄?cho m谩y in** tr锚n `/weighing-station` (do m谩y in n岷眒 峄?tr岷 v岷璽 l媒 kh谩c `WS-WEIGH-PRINTER` tr瓢峄沜 khi ng瓢峄漣 d霉ng quy岷縯 膽峄媙h g峄檖 鈥?sau khi g峄檖 `Role: BOTH`, c霉ng c啤 ch岷?POST `/agents/{id}/printers` 鈫?`ReportInstalledPrintersAsync` 膽茫 c贸 s岷祅 t峄?tr瓢峄沜 s岷?t峄?b谩o c谩o m谩y in d瓢峄沬 膽煤ng `WS-WEIGH-SCALE`, ch瓢a x谩c nh岷璶 l岷 b岷眓g test tay sau khi g峄檖).
- **Ph谩t hi峄噉 #2 鈥?MSI kh么ng t峄?hi峄噉 UAC khi user kh么ng ph岷 admin:** kh谩c v峄沬 `.exe` (`PrivilegesRequired=admin`), double-click `.msi` b岷眓g t脿i kho岷 kh么ng ph岷 admin kh么ng hi峄噉 h峄檖 tho岷 UAC, ch峄?b谩o l峄梚 "insufficient privileges" r峄搃 d峄玭g 鈥?x谩c nh岷璶 膽煤ng theo b谩o c谩o ng瓢峄漣 d霉ng, v脿 m谩y ng瓢峄漣 d霉ng c农ng kh么ng c贸 "Run as administrator" trong menu chu峄檛 ph岷 cho `.msi`. Kh岷痗 ph峄 b岷眓g route m峄沬 `GET /downloads/agent-launcher/{role}` (`backend/routes/web.php`) sinh 膼峄楴G (kh么ng ph岷 file t末nh, 膽峄?t峄?l岷 膽煤ng host 膽ang truy c岷璸 鈥?localhost l煤c dev, IP LAN l煤c th岷璽) 1 file `.cmd` nh峄?g峄峣 `Start-Process msiexec.exe -Verb RunAs` 鈥?b岷璽 膽煤ng UAC credential prompt. N煤t "T岷 C脭NG C峄? (`AppLayout.vue`) 膽峄昳 t峄?1 link t岷 th岷硁g `.msi` sang dropdown menu nhi峄乽 l峄盿 ch峄峮, m峄梚 l峄盿 ch峄峮 t岷 file `.cmd` t瓢啤ng 峄﹏g.
- **Ph谩t hi峄噉 #3 鈥?MSI l峄梚 1721 "A program run as part of the setup did not finish as expected":** x岷 ra tr锚n m谩y th岷璽 khi c脿i (岷h ch峄 m脿n h矛nh ng瓢峄漣 d霉ng g峄璱) 鈥?nguy锚n nh芒n l脿 Custom Action deferred g峄峣 PowerShell qua c啤 ch岷?truy峄乶 d峄?li峄噓 `[~]` (CustomActionData relay) 膽峄?sinh `appsettings.json` l煤c c脿i, kh么ng 膽谩ng tin c岷瓂 v脿 kh么ng debug 膽瓢峄 t峄?xa. V矛 StationId/Role/BackendUrl/PuttyLogPath gi峄?膽茫 c峄?膽峄媙h ho脿n to脿n l煤c build (kh么ng c貌n wizard nh岷璸 tay), **b峄?h岷硁 Custom Action + PowerShell l煤c c脿i**, thay b岷眓g file `appsettings.<role>.json` T抹NH d峄眓g s岷祅 n峄檌 dung 膽煤ng cho t峄玭g vai tr貌 (`agent/installer/appsettings.print-station.json`, `appsettings.weighing-printer.json`, `appsettings.weighing-scale.json`), 膽贸ng g贸i th岷硁g qua bi岷縩 `AppSettingsFile`. 膼茫 x谩c minh b岷眓g `msiexec /a ... /qn TARGETDIR=...` (administrative extraction) 膽峄峜 l岷 膽煤ng n峄檌 dung JSON mong 膽峄 tr瓢峄沜 khi giao cho ng瓢峄漣 d霉ng c脿i l岷.
- **Bug th岷璽 ph谩t hi峄噉 khi test tay #3 鈥?c脿i g贸i vai tr貌 B tr锚n m谩y 膽茫 c贸 vai tr貌 A kh么ng thay th岷?g矛 c岷?** c岷?3 file MSI ban 膽岷 d霉ng CHUNG `UpgradeCode` V脌 CHUNG `Version` (1.4.0.0) 鈥?Windows Installer coi l脿 "膽茫 c脿i 膽煤ng b岷 n脿y r峄搃" n锚n b峄?qua khi c脿i g贸i kh谩c vai tr貌 tr锚n c霉ng m谩y, 膽峄?nguy锚n c岷 h矛nh c农 (x谩c nh岷璶 b岷眓g c谩ch 膽峄峜 l岷 `appsettings.json` tr锚n m谩y th岷璽 sau khi ng瓢峄漣 d霉ng b谩o "c脿i r峄搃 m脿 ch瓢a nh岷璶 膽煤ng"). S峄璦: m峄梚 vai tr貌 c贸 `PackageVersion` ri锚ng (kh谩c nhau 峄?m峄峣 l岷 build l岷) + `<MajorUpgrade AllowDowngrades="yes" />` 膽峄?膽峄昳 qua l岷 gi峄痑 c谩c vai tr貌 tr锚n c霉ng 1 m谩y l煤c n脿o c农ng 膽瓢峄 (kh么ng b峄?ch岷穘 ki峄僽 "downgrade").
- **X谩c nh岷璶 th脿nh c么ng tr锚n m谩y dev (kh么ng ph岷 gi岷?膽峄媙h):** ng瓢峄漣 d霉ng c脿i `DFAgentSetup-PrintStation.msi` th脿nh c么ng (service `DFAgent` Running, `appsettings.json` 膽煤ng n峄檌 dung `WS-PRINT-STATION`/`PRINT_ONLY`). `/print-station` b谩o "ch瓢a nh岷璶 膽瓢峄 d峄?li峄噓 m谩y in" 鈥?nguy锚n nh芒n: `Backend:Url` m岷穋 膽峄媙h c峄﹏g `http://10.0.200.248:8500/api` (膽煤ng cho server LAN th岷璽 ngo脿i x瓢峄焠g) kh么ng k岷縯 n峄慽 膽瓢峄 t峄?m谩y dev (IP th岷璽 `10.0.17.20`, x谩c nh岷璶 qua Windows Event Log li锚n t峄 b谩o `HttpClient.Timeout of 5 seconds`). S峄璦 t岷 `appsettings.json` tr锚n m谩y dev n脿y sang `http://localhost:8500/api` (qua UAC elevate v矛 c岷 quy峄乶 ghi `Program Files`) + restart service 鈥?x谩c nh岷璶 **th脿nh c么ng th岷璽**: `operation_clients.configuration` c峄 `WS-PRINT-STATION` 膽茫 c贸 `available_printers` (g峄搈 `TSC TTP-244 Pro`) v脿 `printers_reported_at` m峄沬. **L瓢u 媒 quan tr峄峮g cho c谩c l岷 c脿i sau tr锚n m谩y dev n脿y:** m峄梚 l岷 c脿i l岷 (d霉 膽峄昳 vai tr貌) 膽峄乽 c岷 l岷穚 l岷 b瓢峄沜 s峄璦 `Backend:Url` n脿y b岷眓g tay 鈥?file MSI g峄慶/b岷 build cho m谩y tr岷 th岷璽 ngo脿i x瓢峄焠g v岷玭 gi峄?nguy锚n `10.0.200.248` nh瓢 thi岷縯 k岷? kh么ng t峄?膽峄昳.
- **Ph谩t hi峄噉 #4 (ngo脿i lu峄搉g) 鈥?l峄梚 "Pusher error: cURL... port 8080":** kh么ng li锚n quan Agent 鈥?do Laravel Reverb (`BROADCAST_CONNECTION=reverb`, xem `app/Events/RealtimeEventBroadcast.php`) ch瓢a 膽瓢峄 kh峄焛 膽峄檔g tr锚n m谩y dev (`php artisan reverb:start`). 膼茫 kh峄焛 膽峄檔g l岷 (ch岷 n峄乶, x谩c nh岷璶 膽ang `Listen` c峄昻g 8080). Ph谩t hi峄噉 t脿i li峄噓 `architecture-decisions.md` (ADR-008) ch瓢a c岷璸 nh岷璽 theo thay 膽峄昳 ki岷縩 tr煤c th岷璽 膽茫 di峄卬 ra 2026-07-25 (chuy峄僴 t峄?SSE v貌ng l岷穚 `while(true)` 鈥?g芒y treo to脿n b峄?server tr锚n Windows do `php artisan serve` kh么ng c贸 concurrency th岷璽 鈥?sang Reverb) 鈥?膽茫 c岷璸 nh岷璽 ADR-008 ph岷 谩nh 膽煤ng code hi峄噉 t岷 theo 膽煤ng quy t岷痗 "瓢u ti锚n code, b谩o l岷 ng瓢峄漣 d霉ng, kh么ng t峄?s峄璦 t脿i li峄噓 m脿 kh么ng x谩c nh岷璶" (`architecture-workflow.md` M峄 5) 鈥?ng瓢峄漣 d霉ng 膽茫 x谩c nh岷璶 膽峄搉g 媒 c岷璸 nh岷璽. **Ch瓢a x谩c minh l岷** ADR-009 (Transactional Outbox)/ADR-010 (Fallback Polling) c贸 c貌n 谩p d峄g nguy锚n v岷筺 v峄沬 Reverb hay c农ng c岷 c岷璸 nh岷璽 鈥?n锚u r玫 trong ADR-008 膽峄?膽峄 sau r脿 so谩t ri锚ng.
- **Vi峄嘽 CH漂A l脿m / c岷 theo d玫i ti岷縫:** Reverb l脿 ti岷縩 tr矛nh n峄乶 ph岷 t峄?kh峄焛 膽峄檔g l岷 m峄梚 l岷 restart m谩y dev/server (ch瓢a c贸 c啤 ch岷?t峄?ch岷 c霉ng l煤c v峄沬 `artisan serve`); `agent/installer/build-all.ps1` 膽瓢峄 nh岷痗 t峄沬 trong comment `.wxs` nh瓢ng **ch瓢a th峄眂 s峄?t岷 file n脿y** 鈥?hi峄噉 膽ang build tay t峄玭g l峄噉h `wix build` ri锚ng l岷? g贸i `WeighingScale` (Role BOTH, sau khi g峄檖) v脿 `WeighingPrinter` **ch瓢a 膽瓢峄 ng瓢峄漣 d霉ng c脿i th峄?th岷璽** tr锚n m谩y n脿o 鈥?ch峄?`PrintStation` 膽茫 x谩c nh岷璶 c脿i th脿nh c么ng th岷璽; t峄?膽峄檔g g谩n m谩y in cho `/weighing-station` sau khi g峄檖 Role BOTH ch瓢a ki峄僲 ch峄﹏g b岷眓g test tay (ch峄?suy lu岷璶 t峄?code `ReportInstalledPrintersAsync` c贸 s岷祅).
- **Ki峄僲 ch峄﹏g:** `npm run build`/`vue-tsc --noEmit` (frontend) 鈫?s岷h, kh么ng l峄梚 TypeScript, nhi峄乽 l岷 qua c谩c 膽峄 s峄璦. `php artisan test` 鈫?100/100 PASS kh么ng 膽峄昳 峄?c谩c 膽峄 s峄璦 route/backend kh么ng 膽峄g logic nghi峄噋 v峄?hi峄噉 c贸; ri锚ng `AgentAuth.php`/`DeviceController.php` (膽峄 s峄璦 l岷 n脿y) ch瓢a c贸 test t峄?膽峄檔g, ch峄?test tay qua HTTP.

### 37. Th锚m dropdown ch峄峮 m谩y in th岷璽 峄?`/weighing-station` + s峄璦 bug th岷璽: tr岷 t峄?膽膬ng k媒 (m峄 36) kh么ng qu茅t 膽瓢峄 QR 膽啤n v矛 `type='AUTO_REGISTERED'`

- **Y锚u c岷 1:** ng瓢峄漣 d霉ng mu峄憂 `/weighing-station` c农ng c贸 UI **ch峄峮** m谩y in (dropdown m谩y in Agent 膽茫 ph谩t hi峄噉) thay v矛 ch峄?t峄?g谩n 芒m th岷 鈥?mirror 膽煤ng UX "鈿欙笍 膼峄昳 m谩y in" 膽茫 c贸 峄?`/print-station`. S峄璦 `frontend/src/components/weighing/QrScanPanel.vue`: th锚m `installedPrinters`/`defaultInstalledPrinter`/`loadingInstalledPrinters` + h脿m `fetchInstalledPrinters()` (膽峄峜 `/api/workstations`, l岷 `configuration.available_printers`/`default_printer` 鈥?膽煤ng ngu峄搉 d峄?li峄噓 do `AgentJobsController::reportPrinters()` ghi, m峄 36), thay 么 nh岷璸 tay m谩y in b岷眓g `<select>` th岷璽 (v岷玭 gi峄?么 nh岷璸 tay l脿m d峄?ph貌ng khi Agent ch瓢a b谩o c谩o m谩y in n脿o). `vue-tsc --noEmit` s岷h.
- **Bug th岷璽 ph谩t hi峄噉 khi ng瓢峄漣 d霉ng test:** qu茅t QR 膽啤n c么ng th峄ヽ t岷 `/weighing-station` (tr岷 `WS-WEIGH-SCALE`, t峄?膽膬ng k媒 theo c啤 ch岷?m峄 36) b峄?403 "M茫 QR 膼啤n c么ng th峄ヽ ch峄?膽瓢峄 ph茅p qu茅t t岷 c谩c Tr岷 C芒n s岷 xu岷." 鈥?**G峄慶 r峄?** `AgentAuth.php` (m峄 36) t岷 `Workstation` m峄沬 v峄沬 `type='AUTO_REGISTERED'` chung chung cho M峄孖 m茫 tr岷; `ScannerController::handleOrderScan()` (d貌ng ~215) ch峄?ch岷 nh岷璶 4 type c峄?th峄?(`DYE_WEIGHING`/`CHEMICAL_WEIGHING`/`A11_WEIGHING`/`DLG_WEIGHING`) 膽峄?suy ra `job_type` c峄 `WeighingJob` 鈥?`AUTO_REGISTERED` kh么ng n岷眒 trong danh s谩ch n脿y n锚n lu么n b峄?ch岷穘. X谩c nh岷璶 `Workstation` model (`app/Models/Workstation.php`) th峄眂 ch岷 l脿 **alias c峄 `OperationClient`** (`protected $table = 'operation_clients'`) 鈥?ch峄?1 b岷g th岷璽 duy nh岷, kh么ng ph岷 2 b岷g ri锚ng nh瓢 nh岷 t瓢峄焠g ban 膽岷 khi truy v岷縯.
- **S峄璦 2 l峄沺:**
  1. **D峄?li峄噓 hi峄噉 c贸 (dev DB, kh么ng ph岷 Production):** c岷璸 nh岷璽 tr峄眂 ti岷縫 qua `php artisan tinker` 鈥?`WS-WEIGH-SCALE` 鈫?`type`/`workstation_type = DYE_WEIGHING` (膽煤ng nghi峄噋 v峄? tr岷 n脿y t瓢啤ng 峄﹏g file VBA g峄慶 `4.semiauto-small scale deltastablefinal1...xlsm`, t峄ヽ c芒n thu峄慶 nhu峄檓 DYE), `default_capability = SMALL_SCALE`, 膽峄搉g b峄?capability `SMALL_SCALE`/`WEIGH`/`PRINT`/`SCAN_QR`/`LOCAL_AGENT`. L脿m t瓢啤ng t峄?cho `WS-PRINT-STATION` 鈫?`type = QR_LABEL_PRINTING` (ch峄?膽峄檔g s峄璦 tr瓢峄沜, tr谩nh l岷穚 l岷 膽煤ng bug n脿y 峄?`/print-station`).
  2. **Ph貌ng t谩i di峄卬 (code):** `AgentAuth.php` th锚m b岷g 谩nh x岷?`$knownStationDefaults` cho 3 m茫 tr岷 c峄?膽峄媙h d霉ng trong MSI (`WS-WEIGH-SCALE`, `WS-WEIGH-PRINTER`, `WS-PRINT-STATION`) 鈫?g谩n 膽煤ng `type`/`workstation_type`/`default_capability`/`default_route` + 膽峄搉g b峄?capability ngay l煤c t岷 (`$workstation->wasRecentlyCreated`), thay v矛 lu么n g谩n `AUTO_REGISTERED`. M茫 tr岷 l岷?(ngo脿i 3 m茫 n脿y) v岷玭 r啤i v峄?`AUTO_REGISTERED` nh瓢 c农 (kh么ng 膽o谩n b峄玜 nghi峄噋 v峄?cho tr岷 ch瓢a bi岷縯).
- **Ki峄僲 ch峄﹏g:** test tay qua `tinker` 鈥?t岷 tr岷 m峄沬 ho脿n to脿n `WS-WEIGH-SCALE-TEST` qua 膽煤ng logic m峄沬, x谩c nh岷璶 `type=DYE_WEIGHING` + 膽峄?5 capability ngay t峄?l岷 t岷 膽岷 ti锚n, d峄峮 d岷筽 b岷 ghi test sau 膽贸. X谩c nh岷璶 l岷 `WS-WEIGH-SCALE` th岷璽 trong DB dev 膽茫 膽煤ng `type=DYE_WEIGHING` (n岷眒 trong danh s谩ch `handleOrderScan()` ch岷 nh岷璶). **Ch瓢a x谩c nh岷璶 l岷 b岷眓g qu茅t QR th岷璽 tr锚n tr矛nh duy峄噒** 鈥?c岷 ng瓢峄漣 d霉ng th峄?l岷 `/weighing-station`.
- **Vi峄嘽 CH漂A l脿m:** ch瓢a vi岷縯 Integration Test t峄?膽峄檔g cho `AgentAuth.php` (v岷玭 v瓢峄沶g Postgres test DB port 5433 kh么ng ch岷 膽瓢峄, nh瓢 m峄 36); ch瓢a r脿 so谩t c谩c m茫 tr岷 `AUTO_REGISTERED` kh谩c c贸 th峄?c貌n s贸t (ngo脿i 2 m茫 膽茫 s峄璦 tay l岷 n脿y).

### 38. File `.msi` t岷 tr锚n CS-SERVER b谩o "could not be opened" 鈥?3 l峄沺 nguy锚n nh芒n ch峄搉g nhau, ph谩t hi峄噉 th岷璽: `php artisan serve` 膽啤n lu峄搉g kh么ng t岷 n峄昳 file l峄沶 tr锚n Production

- **Tri峄噓 ch峄﹏g ban 膽岷:** ng瓢峄漣 d霉ng t岷 c么ng c峄?t岷 `http://10.0.60.209:3001/print-station` (KH脭NG ph岷 m谩y dev 鈥?CS-SERVER th岷璽), Windows b谩o "This installation package could not be opened...".
- **L峄沺 nguy锚n nh芒n #1 (膽茫 s峄璦 m峄 36 nh瓢ng ch瓢a deploy l锚n CS-SERVER):** 3 file `.msi` build s岷祅 m峄沬 ch峄?n岷眒 tr锚n m谩y dev, ch瓢a t峄玭g 膽瓢a l锚n CS-SERVER 鈥?`backend/public/downloads/` b峄?gitignore n锚n `git pull` deploy th瓢峄漬g kh么ng mang file nh峄?ph芒n n脿y theo. X谩c nh岷璶 qua 岷h ch峄 m脿n h矛nh ng瓢峄漣 d霉ng g峄璱: `Invoke-WebRequest` b谩o r玫 `(404) Not Found` t岷 `http://10.0.60.209:8500/downloads/...`. **S峄璦:** `scp` tr峄眂 ti岷縫 3 file `.msi` (膽茫 xin ph茅p ng瓢峄漣 d霉ng r玫 r脿ng qua `AskUserQuestion` tr瓢峄沜 khi ch岷 Production) l锚n 膽煤ng `C:\DFwebBPVN\backend\public\downloads\` tr锚n CS-SERVER, x谩c nh岷璶 b岷眓g `Get-FileHash`/`curl -I` kh峄沺 100% v峄沬 b岷 g峄慶 tr锚n m谩y dev.
- **L峄沺 nguy锚n nh芒n #2:** sau khi h岷縯 404, l峄梚 膽峄昳 sang `Invoke-WebRequest : IOException: Unable to read data from the transport connection: An existing connection was forcibly closed by the remote host` 鈥?l岷穚 l岷 y h峄噒 c岷?3 l岷 th峄?(岷h ch峄 m脿n h矛nh CMD ng瓢峄漣 d霉ng g峄璱). V矛 l峄梚 l岷穚 l岷 gi峄憂g h峄噒 (kh么ng ph岷 ng岷玼 nhi锚n ki峄僽 m岷 s贸ng), nghi ng峄?nguy锚n nh芒n 峄?ch铆nh server ch峄?kh么ng ph岷 m岷g ph铆a client.
- **Truy v岷縯 ra nguy锚n nh芒n g峄慶 th岷璽 (kh么ng 膽o谩n):** 膽峄峜 `C:\DFwebBPVN\tools\run-backend.bat` tr锚n CS-SERVER (qua SSH, ch峄?膽峄峜) 鈥?x谩c nh岷璶 backend Production ch岷 b岷眓g `php artisan serve --host=0.0.0.0 --port=8500`, t峄ヽ server ph谩t tri峄僴 (dev server) t铆ch h峄 s岷祅 c峄 PHP, **ch岷 膽啤n lu峄搉g (single-threaded, kh么ng h峄?tr峄?`pcntl_fork` tr锚n Windows n锚n kh么ng b岷璽 膽瓢峄 `PHP_CLI_SERVER_WORKERS`)**. Khi server 膽ang b岷璶 truy峄乶 file `.msi` 28MB cho 1 client, n贸 kh么ng x峄?l媒 膽瓢峄 b岷 k峄?request n脿o kh谩c c峄 to脿n h峄?th峄憂g trong l煤c 膽贸, v脿 ng瓢峄 l岷 c谩c request kh谩c chen v脿o (polling API c峄 c谩c trang 膽ang m峄? l脿m d峄﹖ k岷縯 n峄慽 膽ang truy峄乶 file l峄沶 gi峄痑 ch峄玭g 鈥?kh峄沺 ch铆nh x谩c v峄沬 l峄梚 th岷璽 g岷穚 ph岷. 膼芒y l脿 h岷 ch岷?ki岷縩 tr煤c 膽茫 bi岷縯 c峄 `php artisan serve`, ch瓢a t峄玭g l峄?ra tr瓢峄沜 膽芒y v矛 h峄?th峄憂g ch瓢a t峄玭g ph岷 ph峄 v峄?file t末nh l峄沶 (ch峄?JSON API nh峄?.
- **S峄璦 (膽茫 xin ph茅p ng瓢峄漣 d霉ng qua `AskUserQuestion` tr瓢峄沜 khi 膽峄昳 h岷?t岷g Production 鈥?server c贸 峄﹏g d峄g kh谩c ch岷 chung, x谩c nh岷璶 qua `Get-Website` th岷 c贸 site `DnDbWebAPI` kh么ng li锚n quan t峄沬 DF):** t谩ch h岷硁 vi峄嘽 ph峄 v峄?`backend/public/downloads/` ra kh峄廼 ti岷縩 tr矛nh backend API ch铆nh, d霉ng 1 ti岷縩 tr矛nh `php -S 0.0.0.0:8501` t末nh ri锚ng (kh么ng qua Laravel/router, ch峄?serve file th么) 鈥?file m峄沬 `C:\DFwebBPVN\tools\run-downloads.bat` (c霉ng pattern loop t峄?kh峄焛 膽峄檔g l岷 nh瓢 `run-backend.bat`), 膽膬ng k媒 Scheduled Task `DFWeb-Downloads` (`schtasks /create ... /sc onstart /ru SYSTEM /rl HIGHEST`, ch岷 ngay b岷眓g `schtasks /run` kh么ng c岷 膽峄 reboot server), m峄?th锚m rule firewall inbound TCP 8501 (`New-NetFirewallRule`). Route `backend/routes/web.php` (`agent-launcher`) s峄璦 膽峄?tr峄?`$msiUrl` sang c峄昻g `8501` khi KH脭NG ph岷 `localhost`/`127.0.0.1` (gi峄?nguy锚n c峄昻g `8500` c农 khi test tr锚n m谩y dev, v矛 m谩y dev ch瓢a c岷 d峄眓g th锚m server 8501 ri锚ng).
- **Ki峄僲 ch峄﹏g th岷璽 (kh么ng ph岷 gi岷?膽峄媙h):** t岷 tr峄峮 v岷筺 file qua `curl` t峄沬 `http://10.0.60.209:8501/DFAgentSetup-PrintStation.msi` (kh么ng ch峄?HEAD) 鈥?7 gi芒y, dung l瓢峄g 膽煤ng 29,405,184 bytes, MD5 kh峄沺 100% v峄沬 b岷 g峄慶 tr锚n m谩y dev (`08f35a8304da81365a0a33c1db5c0616`). Deploy code fix (2 l岷: fix retry-download trong `.cmd`, r峄搃 fix chuy峄僴 c峄昻g 8501) b岷眓g 膽煤ng quy tr矛nh `git push` 鈫?SSH `git pull` 鈫?restart Scheduled Task `DFWeb-Backend` tr锚n CS-SERVER, x谩c nh岷璶 l岷 `curl` route `agent-launcher` tr岷?膽煤ng URL c峄昻g `8501` sau deploy. **Ch瓢a x谩c nh岷璶 l岷 b岷眓g c脿i 膽岷穞 th岷璽 tr锚n m谩y tr岷 ng瓢峄漣 d霉ng** 鈥?膽ang ch峄?ng瓢峄漣 d霉ng th峄?l岷 n煤t "T岷 C脭NG C峄?.
- **R峄 ro c貌n l岷 c岷 l瓢u 媒:** `DFWeb-Downloads` l脿 process/port m峄沬 tr锚n server d霉ng chung nhi峄乽 峄﹏g d峄g 鈥?ch瓢a th么ng b谩o ch铆nh th峄ヽ cho b峄?ph岷璶 IT qu岷 l媒 server (ng瓢峄漣 d霉ng 膽茫 膽峄搉g 媒 ngay trong phi锚n l脿m vi峄嘽 n脿y, ch瓢a r玫 c贸 c岷 th么ng b谩o th锚m ai kh谩c qu岷 l媒 h岷?t岷g chung kh么ng). V峄?l芒u d脿i, h岷 ch岷?膽啤n lu峄搉g c峄 `php artisan serve` v岷玭 c貌n nguy锚n cho M峄孖 request kh谩c c峄 backend API (kh么ng ch峄?ri锚ng t岷 file) 鈥?膽谩ng c芒n nh岷痗 thay b岷眓g web server th岷璽 (IIS+PHP ho岷穋 php-fpm) cho to脿n b峄?backend tr瓢峄沜 khi Cutover ch铆nh th峄ヽ (Phase 13), kh么ng ch峄?v谩 ri锚ng ph岷 t岷 file nh瓢 膽峄 n脿y.

### 39. S峄璦 bug th岷璽: dropdown "M谩y in 膽茫 c脿i tr锚n m谩y n脿y" 峄?`/print-station` thi岷縰 m谩y in 鈥?`agent/PrinterDiscovery.cs` qu茅t th锚m k岷縯 n峄慽 m谩y in ri锚ng theo user

- **Ng瓢峄漣 d霉ng b谩o:** m谩y in 膽茫 c脿i th岷璽 tr锚n m谩y tr岷 kh么ng hi峄僴 th峄?膽岷 膽峄?trong dropdown "鈿欙笍 膼峄昳 m谩y in cho tr岷 n脿y". H峄廼 r玫 v脿 x谩c nh岷璶: 膽煤ng l脿 THI岷綰 m谩y in trong danh s谩ch (kh么ng ph岷 l峄梚 UI/CSS b峄?c岷痶, kh么ng ph岷 danh s谩ch tr峄憂g ho脿n to脿n).
- **Truy v岷縯:** `agent/Program.cs:18` x谩c nh岷璶 DF Agent ch岷 d瓢峄沬 **Windows Service** (t脿i kho岷 Local System). `PrinterDiscovery.cs` c农 ch峄?ch岷 `Get-Printer` 鈥?cmdlet n脿y ch峄?th岷 m谩y in c脿i "cho m峄峣 ng瓢峄漣 d霉ng" (l瓢u m谩y-wide trong Spooler). M谩y in m岷g/LAN c脿i theo c谩ch th么ng th瓢峄漬g (kh么ng tick "cho m峄峣 ng瓢峄漣 d霉ng", c谩ch ph峄?bi岷縩 nh岷 khi ng瓢峄漣 v岷璶 h脿nh t峄?c脿i) l瓢u d岷g k岷縯 n峄慽 ri锚ng theo profile Windows (`HKCU\Printers\Connections`) 鈥?ti岷縩 tr矛nh Service ch岷 d瓢峄沬 SYSTEM kh么ng 膽峄峜 膽瓢峄 `HKCU` c峄 user kh谩c n锚n c谩c m谩y in 膽贸 bi岷縩 m岷 kh峄廼 danh s谩ch Agent b谩o c谩o l锚n web.
- **S峄璦:** `PrinterDiscovery.cs::ListInstalledPrinters()` 鈥?qu茅t th锚m `HKEY_USERS\<SID>\Printers\Connections` cho m峄峣 user 膽ang 膽膬ng nh岷璸 (profile hive 膽ang load, l峄峜 theo pattern SID `S-1-5-21-...` 膽峄?b峄?qua `.DEFAULT`/hive h峄?th峄憂g), g峄檖 k岷縯 qu岷?v峄沬 `Get-Printer`, lo岷 tr霉ng b岷眓g `Sort-Object -Unique`.
- **Ki峄僲 ch峄﹏g:** vi岷縯 script PowerShell y h峄噒 logic m峄沬, ch岷 th峄?tr峄眂 ti岷縫 tr锚n m谩y dev 鈥?x谩c nh岷璶 b岷痶 膽瓢峄 c岷?m谩y in m谩y-wide (`TSC TTP-244 Pro`, `HP LaserJet...`) L岷狽 c谩c k岷縯 n峄慽 m岷g d岷g `\\10.0.193.254\ZP-...` m脿 kh么ng ph岷 l煤c n脿o `Get-Printer` m峄檛 m矛nh c农ng th岷 t霉y ng峄?c岷h user. `dotnet build` s岷h (0 l峄梚, 3 warning c农 kh么ng li锚n quan).
- **C貌n n峄?** c岷 rebuild MSI Agent m峄沬 r峄搃 c脿i 膽猫 (upgrade) l锚n 膽煤ng m谩y tr岷 ng瓢峄漣 d霉ng b谩o l峄梚 膽峄?Agent b谩o c谩o l岷 danh s谩ch m谩y in 膽岷 膽峄?h啤n 鈥?ch瓢a build/deploy MSI m峄沬 trong phi锚n n脿y (ng瓢峄漣 d霉ng ch瓢a y锚u c岷).

### 40. 膼啤n gi岷 h贸a `/print-station`: b峄?"鈿?In nhanh" v脿 "馃枿锔?In tem" (g峄璱 th岷硁g Local Agent), ch峄?c貌n 1 c谩ch in DUY NH岷 l脿 "馃枼锔?In qua tr矛nh duy峄噒"

- **Y锚u c岷:** ng瓢峄漣 d霉ng mu峄憂 b峄?n煤t "In nhanh" 峄?h脿ng ch峄?in tem, ch峄?gi峄?l岷 1 c谩ch in duy nh岷 l脿 in qua tr矛nh duy峄噒 (h峄檖 tho岷 in Windows/tr矛nh duy峄噒, ch峄峮 膽瓢峄 b岷 k峄?m谩y in n脿o 膽茫 c脿i, kh么ng c岷 qua Local Agent/TSPL).
- **膼茫 h峄廼 r玫 tr瓢峄沜 khi s峄璦:** v矛 "In qua tr矛nh duy峄噒" tr瓢峄沜 膽贸 CH峄?m峄?c峄璦 s峄?xem tr瓢峄沜/in th峄?(kh么ng g峄峣 API confirm, 膽啤n v岷玭 n岷眒 nguy锚n trong h脿ng ch峄?"Ch瓢a in"), c貌n n煤t "馃枿锔?In tem" (Local Agent) m峄沬 l脿 h脿nh 膽峄檔g th岷璽 s峄?膽谩nh d岷 膽啤n 膽茫 in (g峄峣 `POST /machine-dispatches/{id}/confirm`, ghi Audit Log). Ng瓢峄漣 d霉ng x谩c nh岷璶: khi b岷 "In qua tr矛nh duy峄噒" ph岷 T峄?膼峄楴G 膽谩nh d岷 膽啤n 膽茫 in lu么n (g峄檖 2 h脿nh 膽峄檔g l脿m 1), kh么ng c岷 thao t谩c th锚m.
- **R峄 ro k峄?thu岷璽 ph谩t hi峄噉 khi 膽峄峜 code (kh么ng ph岷 gi岷?膽峄媙h):** `ConfirmDispatchService::createPrintJob()` LU脭N t岷 `PrintJob` v峄沬 `status='PENDING'` b岷 k峄?c贸 g峄璱 `printer_address` hay kh么ng (c峄檛 DB t峄?d霉ng default `'USB'`/`'TSC TE200'` khi thi岷縰) 鈥?n岷縰 ch峄?膽啤n gi岷 g峄峣 confirm sau khi in qua tr矛nh duy峄噒 m脿 kh么ng x峄?l媒 g矛 th锚m, Local Agent (`AgentJobsController::getJobs()` ch峄?l岷 job `status=PENDING`) v岷玭 s岷?l岷 job n脿y v脿 g峄璱 l峄噉h TSPL th岷璽 xu峄憂g m谩y in v岷璽 l媒 鈥?**in tr霉ng l岷 2** v峄沬 b岷 v峄玜 in qua tr矛nh duy峄噒.
- **S峄璦 (3 l峄沺, 膽峄搉g b峄?frontend + backend):**
  1. `frontend/src/views/PrintStation.vue`: x贸a n煤t "鈿?In nhanh" (h脿ng ch峄? v脿 n煤t "馃枿锔?In tem" (modal xem tr瓢峄沜, x贸a lu么n h脿m `confirmPrintFromPreview` kh么ng c貌n d霉ng). `printPreviewViaBrowser()` sau khi m峄?c峄璦 s峄?`window.print()` gi峄?t峄?g峄峣 `confirmAndPrint(d, previewSelectedPrinter.value, true)` (tham s峄?th峄?3 `viaBrowser=true` m峄沬 th锚m) r峄搃 t峄?膽贸ng modal preview n岷縰 kh么ng l峄梚.
  2. `backend/app/Http/Controllers/MachineDispatchController.php::confirm()`: th锚m field `printed_via_browser` (boolean, optional) v脿o validate + truy峄乶 xu峄憂g service.
  3. `backend/app/Services/ConfirmDispatchService.php::createPrintJob()`: khi `printed_via_browser=true` 鈫?set th岷硁g `status='PRINTED'` (kh么ng ph岷 `PENDING`) n锚n Agent kh么ng bao gi峄?l岷 job n脿y; 膽峄搉g th峄漣 t岷 `PrintAttempt` (attempt_no=1, status=PRINTED) + ghi event `SENT_TO_PRINTER`/`PRINT_SUCCEEDED` ngay l岷璸 t峄ヽ 鈥?y h峄噒 c岷 tr煤c d峄?li峄噓 m脿 `AgentJobsController::acknowledgeJob()` t岷 khi Agent b谩o in th脿nh c么ng th岷璽, ch峄?kh谩c ngu峄搉 x谩c nh岷璶 l脿 tr矛nh duy峄噒 t峄?b谩o ngay l煤c confirm thay v矛 Agent b谩o sau.
- **Ki峄僲 ch峄﹏g:** `php -l` s岷h c岷?2 file PHP 膽茫 s峄璦. `npx vue-tsc --noEmit` s岷h (kh么ng c貌n tham chi岷縰 t峄沬 h脿m/n煤t 膽茫 x贸a). **Kh么ng ch岷 膽瓢峄 `php artisan test`** trong m么i tr瓢峄漬g n脿y 鈥?Postgres test DB (`127.0.0.1:5433`) kh么ng c贸 ti岷縩 tr矛nh l岷痭g nghe (h岷 ch岷?m么i tr瓢峄漬g 膽茫 ghi nh岷璶 nhi峄乽 l岷 峄?c谩c m峄 tr瓢峄沜).
- **C貌n n峄?** ch瓢a x谩c minh b岷眓g m岷痶 tr锚n tr矛nh duy峄噒 th岷璽 (b岷 "Xem tr瓢峄沜" 鈫?"In qua tr矛nh duy峄噒" 鈫?x谩c nh岷璶 膽啤n bi岷縩 m岷 kh峄廼 h脿ng ch峄?+ Audit Log/PrintAttempt 膽瓢峄 ghi 膽煤ng) 鈥?c岷 ng瓢峄漣 d霉ng t峄?th峄?t岷 `/print-station`. Lu峄搉g "In l岷 tem" (qu茅t QR 峄?m脿n d瓢峄沬, n煤t "馃枿锔?In l岷 tem") KH脭NG b峄?膽峄g t峄沬 trong 膽峄 s峄璦 n脿y 鈥?v岷玭 膽i qua Local Agent nh瓢 c农, ch峄?h脿ng ch峄?in tem m峄沬 (`TO_SEND`) 峄?tr锚n b峄?膽峄昳.

### 41. Th锚m l岷 "鈿?In nhanh" 峄?h脿ng ch峄?鈥?v岷玭 d霉ng c啤 ch岷?tr矛nh duy峄噒 (m峄 40), kh么ng quay l岷 Local Agent

- **Y锚u c岷:** ng瓢峄漣 d霉ng mu峄憂 c贸 l岷 n煤t in nhanh ngay 峄?h脿ng ch峄?(kh么ng c岷 m峄?modal Xem tr瓢峄沜 tr瓢峄沜), nh瓢ng v岷玭 qua tr矛nh duy峄噒 nh瓢 m峄 40, kh么ng quay l岷 g峄璱 th岷硁g Local Agent/TSPL nh瓢 b岷 g峄慶 tr瓢峄沜 膽贸.
- **S峄璦:** `PrintStation.vue` 鈥?t谩ch ph岷 d峄眓g HTML tem + `window.print()` + g峄峣 `confirmAndPrint(..., true)` (tr瓢峄沜 膽贸 n岷眒 nguy锚n trong `printPreviewViaBrowser`, ph峄?thu峄檆 `previewDispatch`/`previewDyeLines`/`previewChemLines` c峄 modal) ra h脿m d霉ng chung `printDispatchViaBrowser(d, printerOverride?)` nh岷璶 th岷硁g `dispatch` + parse rack lines c峄 b峄?b岷眓g `parseRackLines()` thay v矛 膽峄峜 computed ref c峄 modal. `printPreviewViaBrowser()` gi峄?ch峄?l脿 wrapper m峄弉g g峄峣 h脿m n脿y v峄沬 `previewDispatch.value`/`previewSelectedPrinter.value` r峄搃 膽贸ng modal. Th锚m n煤t "鈿?In nhanh" 峄?h脿ng ch峄?g峄峣 `quickPrintViaBrowser(d)` 鈥?wrapper g峄峣 h脿m chung v峄沬 `resolvedPrinter.value` (m谩y in 膽茫 suy lu岷璶 s岷祅 cho tr岷), b峄?qua b瓢峄沜 m峄?modal xem tr瓢峄沜.
- **Ki峄僲 ch峄﹏g:** `npx vue-tsc --noEmit` s岷h.

### 42. S峄璦 bug th岷璽: tem in qua tr矛nh duy峄噒 ra 膽煤ng n峄檌 dung nh瓢ng "ch瓢a to 膽煤ng k铆ch c峄? 鈥?thi岷縰 `@page` n锚n in theo kh峄?gi岷 m岷穋 膽峄媙h c峄 driver

- **Ng瓢峄漣 d霉ng g峄璱 岷h ch峄 tem in ra th岷璽** t峄?`/print-station`: 膽煤ng n峄檌 dung (DF_WEIGHING_SLIP, JIT3, b岷g RACK/m茫/kh峄慽 l瓢峄g, 2 QR) nh瓢ng ph岷 tem ch峄?chi岷縨 1 g贸c nh峄?ph铆a tr锚n t峄?gi岷 l峄沶 h啤n nhi峄乽, kh么ng "to 膽煤ng k铆ch c峄?.
- **Truy v岷縯:** c岷?3 n啤i d峄眓g HTML r峄搃 g峄峣 `window.print()` (c啤 ch岷?"in qua tr矛nh duy峄噒" th锚m t峄?m峄 34/40) 鈥?`printDispatchViaBrowser()` (`PrintStation.vue`, tem 70x100mm), `printMaterialLabelViaBrowser()` (`PrintStation.vue`, tem 80x50mm), v脿 `printTsplViaBrowser()` (`utils/tsplPrint.ts`, d霉ng chung cho c谩c tr岷 kh谩c) 鈥?膽峄乽 CH峄?c贸 `.slip { zoom: 1 }` trong `@media print` m脿 THI岷綰 khai b谩o `@page { size: ...; margin: 0 }`. Kh么ng c贸 `@page`, tr矛nh duy峄噒 in theo kh峄?gi岷 膽ang ch峄峮 s岷祅 trong driver m谩y in (m岷穋 膽峄媙h th瓢峄漬g A4/Letter) 鈥?`.slip` v岷玭 膽煤ng k铆ch th瓢峄沜 th岷璽 tuy峄噒 膽峄慽 (70mm/80mm..., kh么ng h峄?b峄?co gi茫n sai t峄?l峄? nh瓢ng ch峄?chi岷縨 1 ph岷 nh峄?gi峄痑 t峄?gi岷 to h啤n nhi峄乽, 膽煤ng y h峄噒 hi峄噉 t瓢峄g trong 岷h ng瓢峄漣 d霉ng g峄璱.
- **S峄璦:** th锚m `@page { size: <kh峄?tem th岷璽>; margin: 0; }` v脿o c岷?3 n啤i (70mm 100mm / 80mm 50mm / `${widthMm}mm ${heightMm}mm` 膽峄檔g theo TSPL `SIZE`) 鈥?Chrome/Edge h峄?tr峄?`@page size` s岷?t峄?y锚u c岷 膽峄昳 kh峄?gi岷 kh峄沺 膽煤ng kh峄?tem khi in, thay v矛 gi峄?nguy锚n kh峄?m岷穋 膽峄媙h c峄 driver.
- **S峄?c峄?nh峄?trong l煤c s峄璦 (膽茫 t峄?ph谩t hi峄噉 v脿 s峄璦 ngay):** 峄?`tsplPrint.ts`, b岷 s峄璦 膽岷 ti锚n qu锚n 膽贸ng comment CSS (`/* ... */`) tr瓢峄沜 khi th锚m `@page`, khi岷縩 c岷?`@page` l岷玭 `@media print` ph铆a sau b峄?nu峄憈 v脿o trong comment (m岷 t谩c d峄g ho脿n to脿n, kh么ng l峄梚 c煤 ph谩p JS/TS n锚n `vue-tsc` kh么ng b岷痶 膽瓢峄). Ph谩t hi峄噉 khi t峄?膽峄峜 l岷 file, s峄璦 l岷 b岷眓g c谩ch 膽贸ng `*/` 膽煤ng ch峄?tr瓢峄沜 khi khai b谩o `@page`.
- **Ki峄僲 ch峄﹏g:** `npx vue-tsc --noEmit` s岷h sau khi s峄璦 (c岷?b岷 l峄梚 comment l岷玭 b岷 膽茫 s峄璦 膽峄乽 "s岷h" v峄?TypeScript 鈥?nh岷痗 l岷: l峄梚 comment CSS kh么ng ph岷 l峄梚 m脿 `vue-tsc` ph谩t hi峄噉 膽瓢峄, ph岷 t峄?膽峄峜 l岷 code m峄沬 th岷). **Ch瓢a in th峄?l岷 tr锚n m谩y in th岷璽** 鈥?c岷 ng瓢峄漣 d霉ng t峄?in l岷 tem 峄?`/print-station` 膽峄?x谩c nh岷璶 kh峄?gi岷 膽茫 kh峄沺 膽煤ng 70x100mm.


### 42. 脕p y h峄噒 h脿nh vi VBA `semiauto-small-scale` v脿o `/weighing-station`: dung sai 卤1%, b峄?c峄昻g ch岷穘 override, `Abs()` cho delta, in danh s谩ch RACK thay `btn_Out`/`btn_In`

- **Tr铆ch xu岷 l岷 to脿n b峄?VBA** t峄?`semiautosmall scale  deltastablefinal1_UNLOCKED.xlsm` qua Excel COM (`VBComponents`/`CodeModule.Lines`, `Protection=0`) 鈥?22 module. 膼峄慽 chi岷縰 v峄沬 code hi峄噉 t岷 x谩c nh岷璶 **ph岷 l峄沶 c啤 ch岷?膽茫 port 膽煤ng t峄?tr瓢峄沜**: b岷g RACK/DYE/WEIGHT/PROCESS 9 d貌ng, `StableFilter` (`agent/ScaleReader.cs`, `cnt>=1`), b矛/delta (`Delta_Begin`/`AutoFlow_OnWeight`), 3 m峄ヽ m脿u `CheckRange`, `checkform` 鈫?`WeighingCheckerModal`, `btnPrint_Click` 鈫?`printSlip`, t谩ch tr瓢峄漬g QR `txt_color_AfterUpdate`.
- **Ph谩t hi峄噉 th锚m khi 膽峄峜 VBA (ghi nh岷璶, ch瓢a x峄?l媒 鈥?膽峄乽 n岷眒 trong file ngu峄搉, kh么ng ph岷 code web):** `ModAcessDB.GetDB()` thi岷縰 d岷 `\` (`"Z:DF_SCALE\RECORD.accdb"` l脿 膽瓢峄漬g d岷玭 t瓢啤ng 膽峄慽 theo th瓢 m峄 hi峄噉 h脿nh c峄 峄?Z:, kh么ng ph岷 tuy峄噒 膽峄慽); `btnSave_Click` n峄慽 chu峄梚 SQL tr峄眂 ti岷縫 v脿 kh么ng c贸 transaction (l峄梚 gi峄痑 ch峄玭g 膽峄?l岷 b岷 ghi d峄?; `checkform.btnCheck_Click` in l岷穚 d貌ng header v矛 thi岷縰 `rs.MoveNext` tr瓢峄沜 v貌ng detail; `Modcleanweight` c贸 `Option Explicit` l岷 th峄?hai 峄?d貌ng 54 n岷眒 ngo脿i declarations section (`CountOfDeclarationLines = 1`) 鈥?nghi l峄梚 bi锚n d峄媍h ti峄乵 岷﹏, **ch瓢a ch岷 `Debug > Compile VBAProject` 膽峄?x谩c nh岷璶**; `mdQRCodegen.GenerateQRCode` (g峄峣 `api.qrserver.com`) v脿 to脿n b峄?`Mod_print_tsc224` (`OpenPrinter`/`WritePrinter` RAW) l脿 **code ch岷縯** 鈥?`btnPrint_Click` th峄眂 t岷?in qua `ws.PrintOut ActivePrinter:="TSC TTP-224 Pro"` (driver GDI), kh么ng ph岷 TSPL th么.
- **4 膽i峄僲 l峄嘽h c貌n l岷 鈥?ng瓢峄漣 d霉ng ch峄憈 t峄玭g 膽i峄僲 qua c芒u h峄廼 tr峄眂 ti岷縫 tr瓢峄沜 khi code:**
  1. **Dung sai 卤1%** (`ScannerController`): x贸a h岷眓g `FIXED_TOLERANCE_GRAMS = 0.01` (thay 膽峄昳 ch瓢a commit h么m 30/07 theo y锚u c岷 "t么i mu峄憂 ch峄?膽瓢峄 ch锚nh 0.01"), quay l岷 `TOLERANCE_RATIO = 0.01` nh芒n v峄沬 m峄 ti锚u 峄?c岷?2 lu峄搉g t岷 `WeighingJobItem` (ad-hoc + c贸 Recipe) 鈥?膽煤ng `Mod_UI_processcolor.CheckRange` (`ratio 0.99鈥?.01`). Gi峄?m么 h矛nh l瓢u tuy峄噒 膽峄慽 v脿o `tolerance_minus`/`tolerance_plus` (t瓢啤ng 膽瓢啤ng to谩n h峄峜, snapshot l煤c qu茅t n锚n kh么ng tr么i). **膼啤n 膽茫 t岷 tr瓢峄沜 膽贸 v岷玭 gi峄?卤0.01g 膽茫 snapshot.**
  2. **B峄?c峄昻g ch岷穘 dung sai** (`WeighingJobController::weighItem`): x贸a kh峄慽 tr岷?422 `OUT_OF_TOLERANCE` v脿 **to脿n b峄?lu峄搉g override** (PIN Gi谩m s谩t, ki峄僲 tra role SUPERVISOR/ADMIN, b岷痶 l媒 do 鈮? k媒 t峄? `AuditLog` `WEIGH_TOLERANCE_OVERRIDE`). M峄峣 l岷 c芒n 膽峄乽 l瓢u 膽瓢峄, lu么n `status = COMPLETED`. Nh茫n 膼岷燭/KH脭NG 膼岷燭 **suy ra, kh么ng th锚m c峄檛/migration**: accessor `WeighingJobItem::process_status` (`$appends`) so `actual_weight` v峄沬 `planned_weight 卤 tolerance_*` 膽茫 snapshot tr锚n ch铆nh item 鈥?t瓢啤ng 膽瓢啤ng c峄檛 `processColor` VBA ghi xu峄憂g Access. `printSlip`, `ScaleMeasurementController::checker`, `WeighingRackTable.vue`, `WeighingCheckerModal.vue` 膽峄乽 膽峄峜 chung accessor n脿y.
  3. **`Abs()` cho delta** (`WeighingStation.vue::ingestRawWeight`): `liveWeight = Math.abs(raw - tareBaseline)` 膽煤ng `Mod_delta_raw.AutoFlow_OnWeight`. B峄?tr岷g th谩i `negative` (th锚m h么m 30/07 膽峄?b岷痶 hao h峄) kh峄廼 `toleranceStatus`/`statusMessage` + 2 rule CSS trong `LiveScaleDisplay.vue`. **膼谩nh 膽峄昳 膽茫 n锚u r玫 v脿 膽瓢峄 ch岷 thu岷璶:** l岷 b峄泃 v岷璽 t瓢 ra kh峄廼 膽末a gi峄?hi峄僴 th峄?gi峄憂g nh瓢 膽ang th锚m v脿o.
  4. **`btn_Out`/`btn_In` 鈫?in danh s谩ch RACK qua tr矛nh duy峄噒**: VBA b岷痭 rack sang app pha m脿u ngo脿i b岷眓g m么 ph峄弉g chu峄檛 + clipboard v脿o to岷?膽峄?m脿n h矛nh c峄?膽峄媙h (`ClickAt 345,200`...) 鈥?kh么ng port 膽瓢峄 sang web v脿 v峄憂 r岷 mong manh. Thay b岷眓g n煤t "馃彿锔?In danh s谩ch RACK" d峄眓g HTML + `window.print()`, **gi峄?膽煤ng c谩ch chia l么 6 rack/l岷** c峄 `Mod_sendRackauto.BuildRackBatch` (l峄峜 rack r峄梟g v脿 rack `"0"`). `window.open()` g峄峣 膽峄搉g b峄?ngay trong handler theo 膽煤ng ti峄乶 l峄?`printSlip`/`PrintStation.vue` (tr谩nh Chrome/Edge ch岷穘 popup sau `await`).
- **L峄嘽h c贸 ch峄?膽铆ch kh峄廼 `CLAUDE.md` m峄 5 (膽茫 h峄廼 r玫, ng瓢峄漣 d霉ng ph锚 duy峄噒):** kh么ng c貌n Audit Log `WEIGH_TOLERANCE_OVERRIDE` v矛 kh么ng c貌n h脿nh vi "ph锚 duy峄噒" n脿o 膽峄?ghi. D峄?li峄噓 v岷玭 膽峄?t谩i d峄眓g nh茫n 膼岷燭/KH脭NG 膼岷燭 v末nh vi峄卬 t峄?`actual_weight` + `planned_weight` + `tolerance_*` tr锚n `weighing_job_items`.
- **B谩o c谩o M11 b峄?v峄?theo, 膽茫 s峄璦 c霉ng l煤c** (`ReportController::toleranceStats`): `SUM(CASE WHEN wji.override_approved ...)` v脿 `where('status','OUT_OF_TOLERANCE')` sau thay 膽峄昳 tr锚n **v末nh vi峄卬 b岷眓g 0**. 膼峄昳 sang bi峄僽 th峄ヽ SQL so tr峄眂 ti岷縫 `actual_weight` v峄沬 bi锚n dung sai (c霉ng c么ng th峄ヽ v峄沬 accessor), 膽峄昳 kh贸a JSON `override_count`/`override_rate_pct`/`total_override` 鈫?`reject_count`/`reject_rate_pct`/`total_reject`, b峄?`pending_resolution_count`. C岷璸 nh岷璽 nh茫n t瓢啤ng 峄﹏g trong `Reports.vue` ("Override" 鈫?"Kh么ng 膽岷", tab "Dung sai & Kh么ng 膽岷").
- **Ki峄僲 ch峄﹏g:** `php -l` s岷h cho c岷?5 file PHP 膽茫 s峄璦. `npx vue-tsc --noEmit -p tsconfig.app.json` **kh么ng ph谩t sinh l峄梚 m峄沬** (c貌n 膽煤ng 3 l峄梚 c农 c贸 s岷祅 峄?`WeighingStation.vue` d貌ng 60/181, kh么ng li锚n quan). Accessor `process_status` test b岷眓g instance in-memory qua `tinker` (kh么ng 膽峄g DB) 膽煤ng c岷?6 m峄慶 bi锚n v峄沬 m峄 ti锚u 12.5g/卤0.125: 12.30 鈫?REJECTED, **12.375 鈫?ACCEPTED**, 12.45 鈫?ACCEPTED, **12.625 鈫?ACCEPTED**, 12.70 鈫?REJECTED, ch瓢a c芒n 鈫?PENDING. Bi峄僽 th峄ヽ SQL `reject_count` m峄沬 ch岷 th岷璽 tr锚n Postgres dev (SELECT read-only qua `tinker`) 鈫?`SQL OK 鈥?total=4 reject=0`, kh么ng l峄梚 c煤 ph谩p. Vite HMR s岷h, 3 ti岷縩 tr矛nh (backend 8500, Reverb 8080, vite 3001) ch岷 n峄乶 su峄憈 phi锚n.
- **CH漂A ch岷 膽瓢峄 `php artisan test`** 鈥?v岷玭 膽煤ng h岷 ch岷?m么i tr瓢峄漬g 膽茫 ghi 峄?m峄 32鈥?3 (kh么ng c贸 Postgres test DB c峄昻g 5433, `.env` dev tr峄?DB th岷璽 `10.0.60.209` n锚n kh么ng ghi th峄?v脿o 膽贸). **Ch瓢a x谩c minh b岷眓g m岷痶 tr锚n tr矛nh duy峄噒** 鈥?膽茫 m峄?s岷祅 `http://localhost:3001/weighing-station`, c岷 ng瓢峄漣 d霉ng t峄?ki峄僲 tra 5 膽i峄僲: (1) m峄 ti锚u 12.5g th矛 12.30 v脿ng / 12.45 xanh / 12.70 膽峄? (2) b岷 X谩c nh岷璶 khi 膽ang 膽峄?v岷玭 l瓢u 膽瓢峄 v脿 kh么ng hi峄噉 h峄檖 PIN, (3) k茅o slider xu峄憂g d瓢峄沬 b矛 th矛 s峄?hi峄僴 th峄?d瓢啤ng, (4) "馃彿锔?In danh s谩ch RACK" hi峄噉 h峄檖 tho岷 in ngay l岷 b岷 膽岷, (5) `/reports` tab dung sai hi峄噉 s峄?"Kh么ng 膽岷" kh峄沺 th峄眂 t岷?
- **膼茫 r脿 so谩t v脿 s峄璦 test c农 b峄?v峄?theo** (grep to脿n `tests/`, kh么ng ch峄?suy 膽o谩n): `KioskOperationTest::test_kiosk_mode_weighing_override_requires_and_verifies_supervisor_pin` 鈫?膽峄昳 th脿nh `test_kiosk_mode_saves_out_of_tolerance_weight_and_labels_it_rejected` (b峄?chu峄梚 assert 422/403/403/200, c貌n 1 l岷 POST duy nh岷 expect 200 + `process_status = REJECTED`); `ReportsTest::test_tolerance_stats_report_computes_override_rate` 鈫?`..._computes_reject_rate` (kh贸a JSON m峄沬), **x贸a** `test_tolerance_stats_counts_pending_out_of_tolerance_items` (tr岷g th谩i `OUT_OF_TOLERANCE` kh么ng c貌n 膽瓢峄 set), `test_weigh_item_persists_override_and_writes_audit_log` 鈫?`test_operator_can_save_out_of_tolerance_weight_without_supervisor`, b峄?lu么n tham s峄?`$override` kh峄廼 helper `makeCompletedWeighingItem` (c岷?5 call site 膽峄乽 truy峄乶 `false`); `ScaleCheckerAndPrintSlipTest` 鈫?膽峄昳 assert `override_approved` sang `process_status = REJECTED`, v脿 s峄璦 assert `PrintJob::status` t峄?`PENDING` sang `PRINTED` + `printer_connection_type = BROWSER` (v峄?do thay 膽峄昳 in-qua-tr矛nh-duy峄噒 峄?m峄 40-41, **test n脿y 膽茫 sai t峄?l煤c 膽贸 m脿 ch瓢a ai ph谩t hi峄噉 v矛 kh么ng ch岷 膽瓢峄 test suite**). `FeedReadinessTest` c贸 `override_approved` nh瓢ng l脿 t铆nh n膬ng KH脕C (`/api/feed-operations/{id}/override`, M07) 鈥?kh么ng 膽峄g t峄沬. `ConfirmDispatchTest`/`PrintJobEventsTest` assert `PENDING` cho lu峄搉g dispatch confirm kh么ng g峄璱 `printed_via_browser` 鈥?v岷玭 膽煤ng, kh么ng s峄璦.
- **Vi峄嘽 CH漂A l脿m:** ch瓢a c岷璸 nh岷璽 `CLAUDE.md` m峄 5 膽峄?ph岷 谩nh vi峄嘽 b峄?Audit Log override dung sai; c谩c test 膽茫 s峄璦 **ch瓢a ch岷 膽瓢峄 l岷 n脿o** 膽峄?x谩c nh岷璶 PASS th岷璽.

### 43. G峄檖 v峄?1 b峄?c脿i Local Agent duy nh岷 (ch峄?nh岷璶 c芒n) 鈥?v脿 s峄璦 l峄梚 ch岷穘: lu峄搉g c芒n th岷璽 qua RS232 ch瓢a t峄玭g 膽岷﹜ 膽瓢峄 s峄?n脿o l锚n backend

- **Y锚u c岷:** "t么i ch峄?c岷 1 b峄?c脿i duy nh岷 膽峄?nh岷璶 c芒n th么i, m谩y in t么i in qua tr矛nh duy峄噒 r峄搃." Sau m峄 40-42, c岷?Print Station l岷玭 Weighing Station 膽峄乽 in b岷眓g h峄檖 tho岷 in c峄 tr矛nh duy峄噒, n锚n ph岷 m谩y in c峄 Agent kh么ng c貌n 膽瓢峄 d霉ng 峄?膽芒u.
- **L峄梚 ch岷穘 ph谩t hi峄噉 khi r脿 l岷 c啤 ch岷?nh岷璶 c芒n (kh么ng ph岷 gi岷?膽峄媙h 鈥?膽峄峜 th岷硁g code):** `ScaleReader.ReadCurrentWeightWithStability()` khi ch岷 c芒n TH岷琓 qua c峄昻g COM tr岷?v峄?`(null, false)` v么 膽i峄乽 ki峄噉, k猫m ch煤 th铆ch "serial port uses event-driven reading". Nh瓢ng handler s峄?ki峄噉 `ProcessRawData()` ch峄?`LogDebug` r峄搃 **v峄﹖ b峄?k岷縯 qu岷?*, kh么ng l瓢u 膽i 膽芒u c岷? `Worker.cs` ch峄?push l锚n backend khi `currentWeight.HasValue` 鈫?**c岷痬 c芒n th岷璽 qua RS232 th矛 Agent kh么ng bao gi峄?g峄璱 膽瓢峄 s峄?c芒n n脿o l锚n h峄?th峄憂g.** L峄梚 b峄?che khu岷 su峄憈 v矛 c岷?3 file `appsettings.*.json` 膽贸ng g贸i trong MSI 膽峄乽 膽峄?`Scale:UseSimulation = true`, lu么n r啤i v脿o nh谩nh 膽峄峜 file log PuTTY.
  - **S峄璦:** t谩ch `IngestSerialData(chunk)` (public, test 膽瓢峄 m脿 kh么ng c岷 c峄昻g COM th岷璽) 鈥?膽峄噈 d峄?li峄噓 v脿 **ch峄?x峄?l媒 d貌ng 膽茫 k岷縯 th煤c b岷眓g CR/LF**, ch峄憈 s峄?膽峄峜 m峄沬 nh岷 v脿o field c贸 `lock`; `ReadCurrentWeightWithStability()` tr岷?v峄?s峄?膽贸 khi d霉ng c峄昻g COM. X贸a `ProcessRawData()`.
  - **L媒 do ph岷 膽峄噈 theo d貌ng:** `SerialPort.ReadExisting()` tr岷?膽煤ng n峄檌 dung buffer t岷 th峄漣 膽i峄僲 g峄峣, c岷痶 gi峄痑 d貌ng l脿 b矛nh th瓢峄漬g (`"12,ST,GS,+00001"` | `"0.5g\r\n"`). 膼瓢a th岷硁g m岷h c峄 v脿o `CleanWeight` th矛 token s峄?cu峄慽 l脿 s峄?C峄 (1 thay v矛 10.5) 鈥?**sai s峄?c芒n m脿 kh么ng c贸 d岷 hi峄噓 g矛**. 膼茫 kh贸a b岷眓g test.
  - Kh么ng 膽岷穞 th峄漣 h岷 h岷縯 hi峄噓 l峄眂 cho s峄?膽峄峜 cu峄慽: gi峄?t峄沬 khi c贸 s峄?m峄沬, 膽煤ng quy 瓢峄沜 TV6 v脿 kh峄沺 nh谩nh 膽峄峜 file (d貌ng cu峄慽 log PuTTY c农ng n岷眒 nguy锚n 膽贸). Cache backend TTL 15s v岷玭 l脿 l峄沺 ch岷穘 cu峄慽 n岷縰 Agent ch岷縯.
- **Test project c峄 Agent ho谩 ra 膽茫 KH脭NG BI脢N D峄奀H 膼漂峄 t峄?2026-07-17** 鈥?4 l峄梚 `CS0266/CS1503` do 膽峄 膽峄昳 `CleanWeight` sang `double?` (TV6) kh么ng c岷璸 nh岷璽 test theo. Ngh末a l脿 unit test Agent ch瓢a t峄玭g ch岷 k峄?t峄?膽贸. 膼茫 s峄璦 4 call site + 膽峄昳 `CleanWeight_ChuoiRong_TraVe0` 鈫?`..._TraVeNull` cho 膽煤ng h脿nh vi TV6 hi峄噉 t岷.
- **1 b峄?c脿i duy nh岷:**
  - `agent/installer/appsettings.scale.json` **m峄沬** (Role `SCALE_ONLY`, `UseSimulation: false` 鈥?膽峄峜 c芒n th岷璽 qua COM, kh么ng c貌n m峄 `Printer`), **x贸a** 3 file `appsettings.print-station/weighing-printer/weighing-scale.json`.
  - `DFAgentSetup.wxs`: m岷穋 膽峄媙h `StationId = WS-WEIGH-SCALE` + `AppSettingsFile = appsettings.scale.json`, `PackageVersion = 2.0.0.0` (cao h啤n h岷硁 1.4.x c峄 c岷?3 b岷 c农 n锚n m谩y tr岷 膽茫 c脿i b岷 k峄?vai tr貌 n脿o c农ng n芒ng c岷 th岷硁g l锚n 膽瓢峄 鈥?gi峄?nguy锚n `UpgradeCode` 膽峄?`MajorUpgrade` t峄?g峄?b岷 c农, tr谩nh 2 service tr霉ng t锚n `DFAgent`). 膼峄昳 m么 t岷?service sang 膽煤ng vi峄嘽 c貌n l岷 (膽峄峜 c芒n).
  - `agent/installer/build.ps1` **m峄沬** (thay `build-all.ps1` v峄憂 膽瓢峄 nh岷痗 trong ch煤 th铆ch nh瓢ng **kh么ng t峄搉 t岷 trong repo**): publish .NET 鈫?`wix build` 鈫?copy sang `backend/public/downloads/`.
  - `backend/routes/web.php`: b峄?h岷硁 tham s峄?`{role}`, route c貌n `/downloads/agent-launcher` ph峄 v峄?`DFAgentSetup-Scale.msi`.
  - `AppLayout.vue`: dropdown 2 m峄 鈫?1 link t岷 th岷硁g "DF Agent (Nh岷璶 c芒n)", x贸a `toolMenuOpen` + 3 rule CSS c峄 menu.
  - X贸a 6 artifact c农 (`DFAgentSetup-{PrintStation,WeighingPrinter,WeighingScale}.{msi,wixpdb}`) kh峄廼 git v脿 kh峄廼 `public/downloads/`.
- **`WeighingStation.vue`: `useSimValue` m岷穋 膽峄媙h `true` 鈫?`false`.** Khi b岷璽 simulator, `fetchLiveWeight()` tho谩t ngay 峄?d貌ng 膽岷 n锚n s峄?c芒n th岷璽 do Agent 膽岷﹜ l锚n **b峄?b峄?qua ho脿n to脿n** 鈥?膽峄?m岷穋 膽峄媙h b岷璽 th矛 tr岷 c岷痬 c芒n th岷璽 v岷玭 kh么ng th岷 s峄?m脿 kh么ng c贸 d岷 hi峄噓 g矛. V岷玭 gi峄?c么ng t岷痗 cho demo/UAT.
- **Ki峄僲 ch峄﹏g:** `dotnet test` (k猫m `DOTNET_ROLL_FORWARD=Major` v矛 m谩y ch峄?c贸 runtime 3.1/9/10, kh么ng c贸 8.0) 鈫?**10/10 PASS**, g峄搈 4 test m峄沬: chunk c岷痶 gi峄痑 d貌ng kh么ng sinh s峄?sai, nhi峄乽 d貌ng trong 1 chunk l岷 d貌ng cu峄慽, d貌ng r谩c gi峄?nguy锚n s峄?h峄 l峄?g岷 nh岷 (TV6), 2 d貌ng gi峄憂g nhau m峄沬 膽谩nh d岷 峄昻 膽峄媙h (StableFilter theo t峄玭g d貌ng c芒n g峄璱 ra, kh么ng theo v貌ng poll). `php -l routes/web.php` s岷h. `vue-tsc` **25 l峄梚 = 膽煤ng b岷眓g baseline** (`git stash` r峄搃 膽岷縨 l岷), kh么ng ph谩t sinh l峄梚 m峄沬. Build MSI th岷璽 th脿nh c么ng (28.1 MB); **gi岷 n茅n MSI ki峄僲 tra l岷** `appsettings.json` 膽贸ng g贸i b锚n trong 膽煤ng `Role: SCALE_ONLY` + `UseSimulation: false`. Smoke test HTTP th岷璽: `/downloads/agent-launcher` 鈫?200 (993 B, n峄檌 dung .cmd tr峄?膽煤ng `DFAgentSetup-Scale.msi`), `/downloads/DFAgentSetup-Scale.msi` 鈫?200 膽峄?29.413.376 byte, route c农 `/downloads/agent-launcher/print-station` 鈫?**404** nh瓢 mong 膽峄.
- **CH漂A ki峄僲 ch峄﹏g 膽瓢峄 (n锚u r玫, kh么ng b谩o l脿 膽茫 ch岷):** kh么ng ch岷 th峄?`DFAgent.exe` end-to-end t岷 ch峄?v矛 `storeReading()` t峄?t岷/g谩n `OperationClient`/`Device` 鈥?s岷?**ghi v脿o DB th岷璽 `production_web` (10.0.60.209)**, vi ph岷 quy t岷痗 an to脿n d峄?li峄噓. Nh谩nh serial m峄沬 ch峄?膽瓢峄 ph峄?b岷眓g unit test, **ch瓢a ch岷 v峄沬 c芒n v岷璽 l媒** 鈥?c岷 x谩c minh t岷 tr岷 khi c岷痬 c芒n th岷璽.
- **C岷 l脿m khi tri峄僴 khai:** `Backend:Url` trong `appsettings.scale.json` v岷玭 膽贸ng c峄﹏g `http://10.0.200.248:8500/api`, v脿 `Workstation:Id` 膽贸ng c峄﹏g `WS-WEIGH-SCALE` 鈥?**n岷縰 c贸 t峄?2 tr岷 c芒n tr峄?l锚n** th矛 m峄梚 tr岷 ph岷 s峄璦 `Workstation:Id` trong `C:\Program Files\DFAgent\appsettings.json` sau khi c脿i r峄搃 restart service, n岷縰 kh么ng c谩c tr岷 s岷?ghi 膽猫 s峄?c芒n c峄 nhau (cache backend 膽谩nh kh贸a theo `workstation_id`).

### 44. Ch峄塶h vi峄乶/kho岷g c谩ch tem `/print-station` (`printDispatchViaBrowser`) 鈥?ng瓢峄漣 d霉ng ph岷 谩nh "k岷?vi峄乶 to v脿 ch瓢a chu岷﹏ l岷痬, 膽ang b峄?膽猫 l锚n ch峄?

- **Ng瓢峄漣 d霉ng b谩o** (kh么ng k猫m 岷h m峄沬 l岷 n脿y, d峄盿 tr锚n 岷h tem in th岷璽 膽茫 g峄璱 tr瓢峄沜 膽贸 峄?m峄 42): vi峄乶 k岷?tr锚n tem in ra to qu谩 v脿 kh么ng chu岷﹏, c贸 ch峄?膽猫 l锚n ch峄?
- **R脿 l岷 CSS (`.slip`/`.box`/`.gridcell`) trong h脿m `printDispatchViaBrowser`:** vi峄乶 ngo脿i `.slip` 膽ang 膽峄?`1.2mm` 鈥?d脿y b岷 th瓢峄漬g so v峄沬 c谩c vi峄乶 kh谩c (`.box` 0.3mm, `.gridcell` 0.2mm). 膼谩ng ch煤 媒 h啤n: c谩c 么 峄?h脿ng ti锚u 膽峄?(DF_WEIGHING_SLIP/zone/QR ch岷?膽峄? v脿 h脿ng m脿u/m茫 h脿ng/m谩y/th霉ng/m峄眂 n瓢峄沜) m峄梚 么 t峄?v岷?vi峄乶 ri锚ng b岷眓g `position:absolute` 膽岷穞 s谩t c岷h nhau (to岷?膽峄?x2 么 n脿y = x1 么 sau) 鈥?2 vi峄乶 0.3mm c峄 2 么 li峄乶 k峄?c峄檔g l岷 t岷 膽煤ng 1 膽瓢峄漬g ranh gi峄沬 nh矛n d脿y g岷 g岷 膽么i (~0.6mm), trong khi padding ch峄?0.4-0.8mm n锚n ch峄?c贸 c岷 gi谩c b峄?vi峄乶 膽猫 s谩t v脿o.
- **S峄璦 (ch峄?ch峄塶h 膽峄?d脿y vi峄乶 + padding, KH脭NG 膽峄昳 to岷?膽峄?b峄?c峄):** `.slip` 1.2mm 鈫?0.4mm, `.box` 0.3mm 鈫?0.2mm, `.gridcell` 0.2mm 鈫?0.15mm, padding `.box` 0.4mm 0.8mm 鈫?0.5mm 0.9mm (gi茫n c谩ch ch峄?v峄沬 vi峄乶 nhi峄乽 h啤n). Ch峄?s峄璦 template tem h脿ng ch峄?dispatch (`DF_WEIGHING_SLIP`, 膽煤ng c谩i trong 岷h ng瓢峄漣 d霉ng g峄璱) 鈥?kh么ng 膽峄g t峄沬 template tem v岷璽 t瓢 (`printMaterialLabelViaBrowser`, vi峄乶 膽茫 m峄弉g s岷祅 0.3mm, kh么ng c贸 l瓢峄沬 b岷g n锚n kh么ng b峄?l峄梚 c峄檔g d峄搉 vi峄乶 t瓢啤ng t峄?.
- **Ki峄僲 ch峄﹏g:** `npx vue-tsc --noEmit` s岷h (thay 膽峄昳 thu岷 CSS trong template string, kh么ng 岷h h瓢峄焠g logic/type). **Ch瓢a in th峄?l岷 tr锚n tem th岷璽** 鈥?c岷 ng瓢峄漣 d霉ng in l岷 v脿 x谩c nh岷璶 vi峄乶 膽茫 m峄弉g/r玫 r脿ng h啤n, 膽岷穋 bi峄噒 t岷 c谩c 膽瓢峄漬g ph芒n c谩ch b峄?c峄檔g d峄搉 (gi峄痑 DF_WEIGHING_SLIP/zone/QR, v脿 gi峄痑 4 么 m脿u-m茫 h脿ng/m谩y/th霉ng/m峄眂 n瓢峄沜).

### 45. T膬ng nh岷?QR g贸c tr锚n b锚n ph岷 (ch岷?膽峄?PROCESS/EXTRA/FB) 峄?tem `/print-station` 鈥?b峄?gi峄沬 h岷 v岷璽 l媒 b峄焛 chi峄乽 cao h脿ng ti锚u 膽峄?14mm

- **Y锚u c岷:** ng瓢峄漣 d霉ng mu峄憂 QR 峄?g贸c tr锚n b锚n ph岷 to h啤n.
- **R脿 l岷 to岷?膽峄?** 么 QR n脿y (`boxDot(391,0,560,112,...)`) n岷眒 trong h脿ng ti锚u 膽峄?cao 膽煤ng 112dot=14mm 鈥?h脿ng th峄?2 (m脿u/m茫 h脿ng/m谩y/th霉ng/m峄眂 n瓢峄沜) b岷痶 膽岷 ngay sau 峄?y=114dot, g岷 nh瓢 kh么ng c贸 khe h峄? 膼芒y l脿 gi峄沬 h岷 c峄﹏g c峄 layout hi峄噉 t岷 (膽茫 port 膽煤ng to岷?膽峄?tem th岷璽, xem ch煤 th铆ch 膽岷 h脿m `printDispatchViaBrowser`).
- **膼茫 l脿m (an to脿n, kh么ng 膽峄昳 to岷?膽峄?么 n脿o kh谩c):** th锚m tham s峄?`extraClass` cho `box()`/`boxDot()` 膽峄?g岷痭 class ri锚ng `.mode-qr-cell` ch峄?cho 么 n脿y, gi岷 padding c峄 ri锚ng 么 n脿y t峄?chu岷﹏ `.box` (0.5mm/0.9mm, d脿nh cho ch峄? xu峄憂g `0.15mm` (么 n脿y ch峄?ch峄゛ 岷h, kh么ng c岷 膽峄噈 cho ch峄? 鈥?t膬ng 岷h QR t峄?`13mm` 鈫?`13.2mm`, 膽茫 t铆nh to谩n 膽峄?KH脭NG v瓢峄 qu谩 chi峄乽 cao th岷璽 c貌n l岷 c峄 么 (14mm tr峄?vi峄乶 0.2mm脳2 tr峄?padding 0.15mm脳2 = 13.3mm), tr谩nh t谩i di峄卬 l峄梚 tr脿n vi峄乶 v峄玜 s峄璦 峄?m峄 44.
- **C貌n n峄?(ch瓢a l脿m, c岷 x谩c nh岷璶 tr瓢峄沜):** m峄ヽ t膬ng tr锚n kh谩 khi锚m t峄憂 (~2%) do 膽煤ng l脿 h岷縯 ch峄?v岷璽 l媒. N岷縰 ng瓢峄漣 d霉ng mu峄憂 to r玫 r峄噒 h啤n (v铆 d峄?~17-18mm), c谩ch duy nh岷 l脿 n峄沬 cao c岷?h脿ng ti锚u 膽峄?鈥?k茅o theo ph岷 d峄媍h 2 么 "Th霉ng"/"M峄眂 n瓢峄沜" c峄 h脿ng d瓢峄沬 (c霉ng d岷 c峄檛 X v峄沬 QR, x:391-560) xu峄憂g theo, l岷 kho岷g tr峄憂g d瓢 ra t峄?vi峄嘽 thu g峄峮 chi峄乽 cao 9 h脿ng b岷g RACK/CHEM (hi峄噉 膽ang d瓢 kh谩 nhi峄乽 so v峄沬 c峄?ch峄?2.2mm d霉ng trong b岷g). 膼芒y l脿 thay 膽峄昳 b峄?c峄 l峄沶 h啤n (膽峄g v峄?tr铆 nhi峄乽 么), **ch瓢a l脿m**, c岷 ng瓢峄漣 d霉ng x谩c nh岷璶 tr瓢峄沜 khi tri峄僴 khai.
- **Ki峄僲 ch峄﹏g:** `npx vue-tsc --noEmit` s岷h. Ch瓢a in th峄?l岷 tr锚n tem th岷璽.

### 47. T峄?膽贸ng c峄璦 s峄?in sau khi in xong 鈥?谩p d峄g cho c岷?3 n啤i d霉ng c啤 ch岷?"in qua tr矛nh duy峄噒"

- **Y锚u c岷:** ng瓢峄漣 d霉ng mu峄憂 c峄璦 s峄?popup m峄?ra 膽峄?in (h峄檖 tho岷 in Windows/tr矛nh duy峄噒) t峄?膽贸ng l岷 sau khi in xong, kh么ng ph岷 t峄?tay 膽贸ng t峄玭g tab.
- **S峄璦:** th锚m `window.onafterprint = function () { window.close(); };` tr瓢峄沜 d貌ng `window.print()` 峄?C岷?3 n啤i d霉ng c啤 ch岷?n脿y: `PrintStation.vue::printDispatchViaBrowser` (tem h脿ng ch峄?dispatch) + `printMaterialLabelViaBrowser` (tem v岷璽 t瓢 reprint), `WeighingStation.vue` (in danh s谩ch RACK), v脿 `utils/tsplPrint.ts::printTsplViaBrowser` (d霉ng chung cho c谩c tr岷 kh谩c). S峄?ki峄噉 `afterprint` b岷痭 ra sau khi h峄檖 tho岷 in 膽贸ng l岷 (d霉 b岷 In hay H峄), Chrome/Edge/Firefox 膽峄乽 h峄?tr峄?
- **Ki峄僲 ch峄﹏g:** `npx vue-tsc --noEmit` s岷h. Ch瓢a test tay tr锚n tr矛nh duy峄噒 th岷璽 (c岷 x谩c nh岷璶 `afterprint` b岷痭 膽煤ng v脿 `window.close()` kh么ng b峄?tr矛nh duy峄噒 ch岷穘 鈥?c峄璦 s峄?膽瓢峄 m峄?b岷眓g `window.open()` t峄?ch铆nh script n锚n v峄?nguy锚n t岷痗 膽贸ng 膽瓢峄, nh瓢ng c岷 x谩c nh岷璶 th峄眂 t岷?tr锚n m谩y ng瓢峄漣 d霉ng).

### 48. Th锚m c峄?"膼茫 t峄玭g in" (kh谩c "鉁?OK"/CONFIRMED) cho h脿ng ch峄?`/print-station` 鈥?膽峄昳 n峄乶 v峄?b矛nh th瓢峄漬g sau l岷 in 膽岷, c贸 么 tick ri锚ng

- **Y锚u c岷:** ng瓢峄漣 d霉ng mu峄憂 khi 1 膽啤n 膽茫 t峄玭g 膽瓢峄 in (qua "鈿?In nhanh"/"馃枼锔?In qua tr矛nh duy峄噒"), n峄乶 h脿ng ch峄?膽峄昳 t峄?膽峄?v峄?b矛nh th瓢峄漬g 鈥?nh瓢ng KH脭NG t铆nh l脿 "膽茫 in xong" (膽贸 v岷玭 l脿 vi峄嘽 c峄 n煤t "鉁?OK", chuy峄僴 xu峄憂g l峄媍h s峄?. C岷 1 么 tick ri锚ng cho "膽茫 t峄玭g in", t峄?膽峄檔g t铆ch khi in l岷 膽岷.
- **V岷 膽峄?** t峄?m峄 40 tr峄?膽i, "鈿?In nhanh"/"馃枼锔?In qua tr矛nh duy峄噒" ch峄?m峄?h峄檖 tho岷 in (client-side thu岷, kh么ng g峄峣 API) 鈥?kh么ng c贸 n啤i n脿o 峄?backend l瓢u l岷 "膽啤n n脿y 膽茫 t峄玭g 膽瓢峄 in qua tr矛nh duy峄噒 ch瓢a", n锚n kh么ng c贸 d峄?li峄噓 膽峄?t么 m脿u theo y锚u c岷.
- **S峄璦 (3 l峄沺):**
  1. **Migration** `2026_07_31_000001_add_ever_printed_to_machine_dispatches.php`: th锚m c峄檛 `ever_printed` (boolean, default false) v脿o `machine_dispatches`. Kh么ng c岷 Audit Log 鈥?ch峄?l脿 c峄?bookkeeping hi峄僴 th峄? kh么ng 膽峄昳 routing/QR/PrintJob th岷璽.
  2. **Backend:** `MachineDispatch` model th锚m `ever_printed` v脿o `$fillable`/`$casts`; `MachineDispatchController::markEverPrinted()` (route m峄沬 `PATCH /api/machine-dispatches/{id}/ever-printed`) 鈥?ch峄?set c峄? kh么ng 膽峄g `queue_state`.
  3. **Frontend (`PrintStation.vue`):** th锚m c峄檛 "膼茫 t峄玭g in" (checkbox) v脿o b岷g h脿ng ch峄? h脿m `toggleEverPrinted(dispatch, value)` g峄峣 API + c岷璸 nh岷璽 l岷 quan t岷 ch峄?(rollback n岷縰 l峄梚). `printDispatchViaBrowser()` t峄?g峄峣 `toggleEverPrinted(d, true)` ngay sau khi m峄?膽瓢峄 c峄璦 s峄?in l岷 膽岷 (`if (!d.ever_printed)`). Class n峄乶 h脿ng 膽峄昳 t峄?ch峄?d峄盿 v脿o `confirmedIds` sang `confirmedIds.has(d.id) || d.ever_printed` 鈥?ng瓢峄漣 d霉ng v岷玭 tick/b峄?tick tay 膽瓢峄 么 n脿y n岷縰 c岷 s峄璦 l岷.
- **Ki峄僲 ch峄﹏g:** `npx vue-tsc --noEmit` s岷h, `php -l` s岷h c岷?3 file PHP. 膼茫 h峄廼 x谩c nh岷璶 ng瓢峄漣 d霉ng tr瓢峄沜 khi 膽峄g `production_web` (10.0.60.209) theo `database-safety.md` 鈥?ng瓢峄漣 d霉ng 膽峄搉g 媒, 膽茫 ch岷 `php artisan migrate --path=... --force`, x谩c nh岷璶 `Schema::hasColumn('machine_dispatches','ever_printed')` tr岷?`true`.

### 49. Fix m峄 47: `window.onafterprint` kh么ng th岷璽 s峄?膽贸ng 膽瓢峄 c峄璦 s峄?in 鈥?chuy峄僴 sang g峄峣 `window.close()` ngay sau `window.print()`

- **Ng瓢峄漣 d霉ng b谩o:** sau m峄 47, c峄璦 s峄?in (hi峄噉 "about:blank") v岷玭 kh么ng t峄?膽贸ng sau khi in xong.
- **Nguy锚n nh芒n (suy lu岷璶 t峄?h脿nh vi tr矛nh duy峄噒, kh么ng test tay 膽瓢峄 c么ng c峄?in th岷璽 trong m么i tr瓢峄漬g n脿y):** s峄?ki峄噉 `afterprint` c贸 nhi峄乽 b岷 膽峄媙h gi峄痑 c谩c tr矛nh duy峄噒/phi锚n b岷 khi c峄璦 s峄?膽瓢峄 t岷 b岷眓g `window.open()` + `document.write()` r峄搃 g峄峣 `window.print()` ngay trong `onload` 鈥?kh么ng 膽岷 b岷 lu么n b岷痭 ra 膽煤ng l煤c 膽峄?`window.close()` ch岷.
- **S峄璦 (谩p d峄g c岷?3 n啤i 鈥?`PrintStation.vue` x2, `WeighingStation.vue`, `utils/tsplPrint.ts`):** b峄?`window.onafterprint`, g峄峣 th岷硁g `window.print(); window.close();` li锚n ti岷縫 trong `onload` 鈥?d峄盿 v脿o `window.print()` ch岷穘 (blocking) th峄眂 thi script t峄沬 khi h峄檖 tho岷 in 膽贸ng l岷 tr锚n Chrome/Edge (2 tr矛nh duy峄噒 Windows 膽ang d霉ng th峄眂 t岷?, n锚n `window.close()` 峄?d貌ng k岷?ti岷縫 ch岷痗 ch岷痭 ch岷 SAU khi ng瓢峄漣 d霉ng b岷 In/H峄.
- **Ki峄僲 ch峄﹏g:** `npx vue-tsc --noEmit` s岷h. **Ch瓢a x谩c nh岷璶 l岷 tr锚n tr矛nh duy峄噒 th岷璽** 鈥?c岷 ng瓢峄漣 d霉ng in th峄?l岷 xem c峄璦 s峄?膽茫 t峄?膽贸ng 膽煤ng ch瓢a; n岷縰 Chrome/Edge tr锚n m谩y 膽贸 KH脭NG ch岷穘 峄?`window.print()` (m峄檛 s峄?c岷 h矛nh enterprise/extension c贸 th峄?膽峄昳 h脿nh vi n脿y) th矛 c谩ch n脿y c农ng kh么ng 膬n thua, l煤c 膽贸 c岷 quay l岷 h瓢峄沶g kh谩c (v铆 d峄?t峄?膽贸ng sau 1 kho岷g `setTimeout` c峄?膽峄媙h, ch岷 nh岷璶 膽贸ng h啤i s峄沵/tr峄?.

### 50. L峄梚 t么i t峄?g芒y ra 峄?m峄 46: d岷 backtick trong comment CSS l脿m v峄?template literal, trang tr岷痭g `[plugin:vite:vue] Missing semicolon`

- **Ng瓢峄漣 d霉ng b谩o:** `[plugin:vite:vue] [vue/compiler-sfc] Missing semicolon. (423:24)` t岷 `PrintStation.vue` 鈥?trang kh么ng ch岷 膽瓢峄.
- **Nguy锚n nh芒n (l峄梚 t么i g芒y ra, kh么ng ph岷 bug c贸 s岷祅):** 峄?m峄 46 t么i vi岷縯 comment CSS b锚n trong template literal HTML c峄 `printDispatchViaBrowser()` c贸 d霉ng d岷 backtick 膽峄?tr铆ch d岷玭 t锚n thu峄檆 t铆nh CSS: ``膽峄昳 `align-items` sang `flex-start` ``. Template literal JS d霉ng ch铆nh k媒 t峄?backtick l脿m d岷 k岷縯 chu峄梚 鈥?backtick 膽岷 ti锚n trong comment K岷綯 TH脷C chu峄梚 HTML gi峄痑 ch峄玭g, to脿n b峄?ph岷 c貌n l岷 b峄?parser hi峄僽 l脿 code JS, g芒y l峄梚 c煤 ph谩p 峄?v峄?tr铆 r岷 xa n啤i th岷璽 s峄?sai (b谩o d貌ng 423 trong khi l峄梚 n岷眒 峄?d貌ng 774).
- **S峄璦:** b峄?backtick trong comment 膽贸 (vi岷縯 tr岷 `align-items`/`flex-start`).
- **B脿i h峄峜 quy tr矛nh (quan tr峄峮g, 膽茫 l岷穚 l岷 2 l岷 trong phi锚n n脿y):** `vue-tsc --noEmit` **KH脭NG b岷痶 膽瓢峄** l峄梚 n脿y 鈥?n贸 ch峄?ki峄僲 tra ki峄僽 TypeScript, kh么ng bi锚n d峄媍h 膽岷 膽峄?SFC/template literal. C农ng nh瓢 l峄梚 comment CSS ch瓢a 膽贸ng `*/` 峄?m峄 42, c岷?2 l岷 `vue-tsc` 膽峄乽 b谩o s岷h trong khi code th岷璽 s峄?h峄弉g. **T峄?nay khi s峄璦 n峄檌 dung b锚n trong template literal HTML (c谩c h脿m in qua tr矛nh duy峄噒), ph岷 ch岷 `npm run build` ch峄?kh么ng ch峄?`vue-tsc --noEmit`.**
- **Ki峄僲 ch峄﹏g:** `npx vue-tsc --noEmit` s岷h V脌 `npm run build` th脿nh c么ng (36.17s, kh么ng l峄梚) 鈥?l岷 n脿y d霉ng build th岷璽 膽峄?ch岷痗 ch岷痭, 膽煤ng theo b脿i h峄峜 v峄玜 n锚u. R脿 l岷 to脿n b峄?backtick c貌n l岷 trong 3 file li锚n quan (`PrintStation.vue`, `tsplPrint.ts`, `WeighingStation.vue`) x谩c nh岷璶 kh么ng c貌n ch峄?n脿o d霉ng backtick sai ng峄?c岷h.

### 51. N煤t "馃枿锔?In l岷" 峄?b岷g L峄媍h s峄?膽茫 in (`/print-station`) 鈥?c贸 ghi Audit Log + l媒 do theo 膽煤ng CLAUDE.md

- **Y锚u c岷:** 膽啤n 膽茫 n岷眒 峄?b岷g "馃搵 L峄媍h s峄?膽茫 in" v岷玭 c岷 in l岷 膽瓢峄, c岷 1 n煤t in l岷 峄?b岷g d瓢峄沬.
- **Quy岷縯 膽峄媙h thi岷縯 k岷?(KH脕C c贸 ch峄?膽铆ch v峄沬 "鈿?In nhanh" 峄?h脿ng ch峄?:** in l岷 tem 膽茫 x谩c nh岷璶 xong l脿 **h脿nh 膽峄檔g nh岷 c岷** 鈥?CLAUDE.md m峄 5 v脿 `database-safety.md` m峄 5 膽峄乽 li峄噒 k锚 "In l岷 tem (Reprint)" v脿o nh贸m 100% ph岷 ghi Audit Log b岷 bi岷縩 k猫m l媒 do. V矛 v岷瓂 n煤t n脿y KH脭NG ch峄?m峄?h峄檖 tho岷 in nh瓢 峄?h脿ng ch峄? m脿 b岷痶 bu峄檆 nh岷璸 l媒 do (t峄慽 thi峄僽 3 k媒 t峄? r峄搃 g峄峣 endpoint `POST /machine-dispatches/{id}/reprint` s岷祅 c贸 (膽茫 ghi `AuditLog: PRINT_JOB_REPRINTED` + event `REPRINT_REQUESTED`, t谩i d霉ng 膽煤ng QrPayload l岷 膽岷, kh么ng t铆nh l岷 routing).
- **S峄璦:**
  - **Backend** `MachineDispatchController::reprint()`: th锚m `printed_via_browser` v脿o validate + `$request->only()` 鈥?c霉ng l媒 do nh瓢 `confirm()` (m峄 40): tem 膽茫 in xong qua tr矛nh duy峄噒 r峄搃, n岷縰 膽峄?PrintJob 峄?`PENDING` th矛 Local Agent s岷?l岷 v脿 in tr霉ng l岷 n峄痑 xu峄憂g m谩y in v岷璽 l媒. `ConfirmDispatchService::reprint()` truy峄乶 th岷硁g `$options` xu峄憂g `createPrintJob()` n锚n c峄?n脿y 膽茫 膽瓢峄 x峄?l媒 s岷祅, kh么ng ph岷 s峄璦 service.
  - **Frontend** `PrintStation.vue`: th锚m c峄檛 "S峄?l岷 in" (`d.print_jobs?.length`, 膽煤ng key 膽茫 d霉ng 峄?`PrintJobHistoryTable.vue`) + c峄檛 "Thao t谩c" v峄沬 n煤t "馃枿锔?In l岷" v脿o b岷g l峄媍h s峄? h脿m `reprintFromHistory(dispatch)` **m峄?c峄璦 s峄?in TR漂峄欳 r峄搃 m峄沬 `prompt()` h峄廼 l媒 do** 鈥?n岷縰 m峄?sau prompt th矛 "transient user activation" c峄 c煤 click c贸 th峄?膽茫 h岷縯 h岷 (ng瓢峄漣 d霉ng g玫 l媒 do m岷 v脿i gi芒y) v脿 tr矛nh duy峄噒 ch岷穘 popup; h峄 prompt th矛 膽贸ng c峄璦 s峄?v峄玜 m峄? Refactor `printDispatchViaBrowser(d, opts)` nh岷璶 th锚m `existingWin` (d霉ng l岷 c峄璦 s峄?caller 膽茫 m峄? v脿 `markEverPrinted` (膽岷穞 `false` cho 膽啤n 峄?l峄媍h s峄?鈥?c峄?"膽茫 t峄玭g in" c峄 h脿ng ch峄?kh么ng c貌n 媒 ngh末a v峄沬 膽啤n 膽茫 CONFIRMED).
- **Ki峄僲 ch峄﹏g:** `npx vue-tsc --noEmit` s岷h, `npm run build` th脿nh c么ng (36.67s 鈥?d霉ng build th岷璽 theo b脿i h峄峜 m峄 50), `php -l` s岷h. **Ch瓢a test tay tr锚n tr矛nh duy峄噒** 鈥?c岷 ng瓢峄漣 d霉ng b岷 th峄?"馃枿锔?In l岷" 峄?b岷g l峄媍h s峄?膽峄?x谩c nh岷璶: h峄檖 tho岷 in m峄?膽煤ng, l媒 do 膽瓢峄 l瓢u, c峄檛 "S峄?l岷 in" t膬ng l锚n sau khi in l岷.

### 52. `/production-batches`: b岷g "10 l么 g岷 nh岷" 鈫?"30 l么 g岷 nh岷" (ph岷 s峄璦 c岷?backend, kh么ng ch峄?n峄沬 `slice` 峄?client)

- **Y锚u c岷:** ng瓢峄漣 d霉ng b谩o l么 sau khi b岷 "鉁?OK" 峄?`/print-station` th矛 bi岷縩 m岷 kh峄廼 b岷g "馃晿 10 l么 g岷 nh岷" 峄?`/production-batches`, mu峄憂 膽峄昳 th脿nh 30 l么 g岷 nh岷.
- **X谩c minh nguy锚n nh芒n tr瓢峄沜 khi s峄璦 (kh么ng 膽o谩n):** 膽峄峜 l岷 `ConfirmDispatchService::confirm()` 鈥?ch峄?膽峄昳 `queue_state` c峄 `machine_dispatches`, **kh么ng** 膽峄g `production_batches.status`; `ProductionBatchController::index()` c农ng ch峄?岷﹏ `status = CANCELLED`. V岷瓂 l么 KH脭NG b峄?l峄峜 m岷 v矛 tr岷g th谩i 鈥?n贸 ch峄?b峄?c谩c l么 m峄沬 qu茅t sau 膽贸 膽岷﹜ ra kh峄廼 top 10. K岷縯 lu岷璶: n峄沬 gi峄沬 h岷 l锚n 30 膽煤ng l脿 c谩ch x峄?l媒 cho hi峄噉 t瓢峄g ng瓢峄漣 d霉ng g岷穚.
- **膼i峄僲 d峄?sai 膽茫 tr谩nh:** ch峄?膽峄昳 `slice(0, 10)` 鈫?`slice(0, 30)` 峄?frontend l脿 KH脭NG 膽峄?鈥?`ProductionBatchController::index()` `paginate(15)`, t峄ヽ API ch峄?tr岷?t峄慽 膽a 15 d貌ng/trang, b岷g s岷?d峄玭g 峄?15 ch峄?kh么ng bao gi峄?膽峄?30.
- **S峄璦:**
  - **Backend** `ProductionBatchController::index()`: th锚m tham s峄?t霉y ch峄峮 `per_page` (m岷穋 膽峄媙h v岷玭 15 膽峄?**kh么ng 膽峄昳 h脿nh vi ph芒n trang** c峄 `/production-batches/list`, `FeedingMonitor`, `OrderScan`, `Troubleshooting`, `QrScanPanel` 膽ang d霉ng chung endpoint), ch岷穘 tr岷 100.
  - **Frontend** `ProductionBatches.vue`: th锚m h岷眓g `RECENT_BATCH_LIMIT = 30` d霉ng chung cho c岷?ti锚u 膽峄?(`馃晿 {{ RECENT_BATCH_LIMIT }} l么 g岷 nh岷`), `slice()`, v脿 tham s峄?`per_page` khi g峄峣 API 鈥?tr谩nh 3 ch峄?l峄嘽h nhau v峄?sau. D峄峮 c谩c comment/CSS c貌n ghi c峄﹏g "10 l么 g岷 nh岷".
- **L峄 铆ch k猫m theo:** `batches` c农ng l脿 ngu峄搉 d峄?li峄噓 cho `checkDuplicateOrder` (CHECK tr霉ng m脿u/m茫 h脿ng tr瓢峄沜 khi SAVE) 鈥?c贸 30 d貌ng thay v矛 15 th矛 ph谩t hi峄噉 tr霉ng ch铆nh x谩c h啤n.
- **Ki峄僲 ch峄﹏g:** `npx vue-tsc --noEmit` s岷h, `npm run build` th脿nh c么ng (36.03s), `php -l` s岷h. **Ch瓢a test tay tr锚n tr矛nh duy峄噒** 鈥?c岷 ng瓢峄漣 d霉ng m峄?l岷 `/production-batches` x谩c nh岷璶 b岷g hi峄噉 膽峄?30 d貌ng.

### 53. Tem in m岷 vi峄乶 TR脢N + TR脕I (n岷眒 ngo脿i tem) 鈥?h峄?qu岷?c峄 ch铆nh `@page margin:0` th锚m 峄?m峄 42, s峄璦 b岷眓g scale 95% co v脿o t芒m

- **Ng瓢峄漣 d霉ng b谩o:** tem in ra 峄?`/print-station` kh么ng th岷 vi峄乶 ph铆a tr锚n v脿 b锚n tr谩i, ph岷 膽贸 "n岷眒 ngo脿i tem r峄搃".
- **Nguy锚n nh芒n (l脿 h峄?qu岷?tr峄眂 ti岷縫 c峄 m峄 42, kh么ng ph岷 l峄梚 m峄沬 膽峄檆 l岷璸):** m峄 42 th锚m `@page { size: 70mm 100mm; margin: 0; }` 膽峄?tem kh么ng c貌n in b茅 x铆u gi峄痑 t峄?A4. Nh瓢ng `.slip` c农ng 膽煤ng 70x100mm + `margin: 0` ngh末a l脿 **vi峄乶 ngo脿i c峄 tem n岷眒 膼脷NG m茅p gi岷 v岷璽 l媒** 鈥?r啤i tr峄峮 v脿o v霉ng kh么ng in 膽瓢峄 (unprintable margin) m脿 m峄峣 m谩y in 膽峄乽 c贸 (膽岷 in kh么ng v峄沬 t峄沬 s谩t m茅p), n锚n n茅t vi峄乶 tr锚n/tr谩i bi岷縩 m岷. 膼芒y l脿 gi峄沬 h岷 ph岷 c峄﹏g, kh么ng ch峄塶h 膽瓢峄 b岷眓g driver.
- **S峄璦:** trong `@media print`, th锚m `transform: scale(0.95); transform-origin: center center;` cho `.slip` 鈥?thu to脿n b峄?tem c貌n 95% v脿 co v脿o T脗M trang, ch峄玜 膽峄乽 ~1.75mm ngang / 2.5mm d峄峜 quanh 4 c岷h.
  - **V矛 sao d霉ng `transform` ch峄?kh么ng ph岷 `zoom`:** `zoom` t铆nh l岷 layout (膽岷﹜ v峄?tr铆 `.slip` trong trang, d峄?l峄嘽h t芒m), c貌n `transform` ch峄?v岷?l岷 鈥?t芒m tem gi峄?膽煤ng t芒m trang.
  - **V矛 sao kh么ng s峄璦 l岷 to岷?膽峄?b锚n trong:** to脿n b峄?b峄?c峄 d霉ng to岷?膽峄?tuy峄噒 膽峄慽 mm (port 膽煤ng t峄?dot TSPL c峄 backend). Scale c岷?`.slip` gi峄?nguy锚n t峄?l峄?g峄慶 c峄 m峄峣 么/l瓢峄沬/QR 鈥?s峄璦 1 d貌ng thay v矛 t铆nh l岷 ~30 m峄慶 to岷?膽峄? v脿 kh么ng r峄 ro l峄嘽h so v峄沬 tem TSPL th岷璽.
- **脕p cho c岷?3 n啤i in qua tr矛nh duy峄噒** (c霉ng m谩y in v岷璽 l媒, c霉ng c啤 ch岷?`@page margin:0` n锚n ch岷痗 ch岷痭 c霉ng b峄?: tem dispatch 70x100 + tem v岷璽 t瓢 80x50 (`PrintStation.vue`), v脿 `utils/tsplPrint.ts` (d霉ng chung cho `/weighing-station`).
- **Ki峄僲 ch峄﹏g:** `npx vue-tsc --noEmit` s岷h, `npm run build` th脿nh c么ng (36.95s 鈥?d霉ng build th岷璽 theo b脿i h峄峜 m峄 50). **Ch瓢a in th峄?tr锚n tem th岷璽** 鈥?c岷 ng瓢峄漣 d霉ng in l岷 x谩c nh岷璶 膽茫 th岷 膽峄?vi峄乶 4 c岷h; n岷縰 v岷玭 thi岷縰 峄?1 ph铆a c峄?th峄?th矛 膽贸 l脿 do m谩y in l峄嘽h gi岷 (feed offset), l煤c 膽贸 ph岷 ch峄塶h ri锚ng b岷眓g c谩ch d峄媍h tem (th锚m `translate`) ch峄?kh么ng ph岷 gi岷 scale ti岷縫.

### 54. Tem in ra 膼脷NG b峄?c峄 nh瓢ng M峄?鈥?n茅t kh么ng tr貌n dot 峄?m谩y in nhi峄噒 203dpi + QR thi岷縰 膽峄?ph芒n gi岷 ngu峄搉

- **Ng瓢峄漣 d霉ng b谩o:** sau m峄 53 tem in ra 膽煤ng r峄搃 nh瓢ng m峄?qu谩.
- **Ph芒n t铆ch (膽岷穋 th霉 m谩y in NHI峄員, kh谩c h岷硁 m谩y in phun/laser):** m谩y in tem ch峄?c贸 2 m峄ヽ 膽en/tr岷痭g, kh么ng c贸 s岷痗 x谩m. M峄峣 pixel x谩m m脿 tr矛nh duy峄噒 sinh ra 膽峄乽 b峄?dither th脿nh l瓢峄沬 ch岷 th瓢a 鈥?m岷痶 nh矛n th岷 l脿 "m峄?. C贸 3 ngu峄搉 sinh x谩m trong tem hi峄噉 t岷:
  1. **N茅t vi峄乶 kh么ng tr貌n dot:** 峄?203dpi, 1 dot = 0.125mm. B岷 m峄 44 d霉ng `0.15mm` (1.2 dot) v脿 `0.2mm` (1.6 dot) 鈥?kh么ng ph岷 b峄檌 s峄?dot, tr矛nh duy峄噒 kh峄?r膬ng c瓢a th脿nh n茅t x谩m. N芒ng v峄?**0.25mm (2 dot)** cho `.box`/`.gridcell` v脿 **0.5mm (4 dot)** cho `.slip`. V岷玭 m峄弉g h啤n h岷硁 b岷 g峄慶 (0.3/1.2mm) n锚n **kh么ng quay l岷 l峄梚 "vi峄乶 to 膽猫 ch峄?** c峄 m峄 44 (padding gi峄?nguy锚n 0.5/0.9mm).
  2. **Ch峄?qu谩 m岷h:** `.cellval` ch峄?2.2mm, n茅t Arial th瓢峄漬g 峄?c峄?n脿y m岷h h啤n 1 dot. T膬ng `font-weight: 600` (v脿 `.label-sm`/`.med` t瓢啤ng t峄? 鈥?**t膬ng 膽峄?膽岷璵 n茅t thay v矛 t膬ng c峄?ch峄?*, 膽峄?kh么ng ph岷 n峄沬 l岷 chi峄乽 cao h脿ng/b峄?c峄 v峄憂 膽茫 kh峄沺 tem th岷璽.
  3. **QR thi岷縰 膽峄?ph芒n gi岷 ngu峄搉:** `QRCode.toDataURL(..., { width: 240 })` 鈥?trang in 膽瓢峄 render 峄?DPI cao h啤n m脿n h矛nh nhi峄乽 n锚n 岷h 240px b峄?PH脫NG TO khi in, c岷h module QR nho猫 x谩m. N芒ng ngu峄搉 l锚n **960px** (mode QR 800px) 膽峄?l煤c in lu么n l脿 thu nh峄?(n峄檌 suy m瓢峄, n茅t 膽en gi峄?膽岷穋).
- **Th锚m `print-color-adjust: exact`** (k猫m ti峄乶 t峄?`-webkit-`) 膽峄?tr矛nh duy峄噒 kh么ng t峄?"t峄慽 瓢u" m脿u in l脿m 膽en b峄?nh岷 th脿nh x谩m.
- **膼i峄僲 膽茫 c芒n nh岷痗 v脿 CH峄?膼峄楴G KH脭NG l脿m:** ban 膽岷 膽峄媙h th锚m `image-rendering: pixelated` cho 岷h QR (c谩ch ph峄?bi岷縩 膽峄?gi峄?c岷h s岷痗), nh瓢ng **膽茫 b峄?* 鈥?QR 峄?膽芒y 膽ang b峄?*thu nh峄?, m脿 `pixelated` l煤c downscale v峄﹖ pixel kh么ng 膽峄乽 s岷?l脿m m茅o module QR khi岷縩 m谩y qu茅t d峄?膽峄峜 sai. V峄沬 downscale, c谩ch 膽煤ng l脿 t膬ng 膽峄?ph芒n gi岷 ngu峄搉 (膽茫 l脿m 峄?tr锚n) r峄搃 膽峄?tr矛nh duy峄噒 n峄檌 suy.
- **脕p cho c岷?3 n啤i in qua tr矛nh duy峄噒:** tem dispatch 70x100 + tem v岷璽 t瓢 80x50 (`PrintStation.vue`), v脿 `utils/tsplPrint.ts` (`/weighing-station`; nh芒n ti峄噉 h岷?`border` `.slip` t峄?1.2mm xu峄憂g 0.5mm cho kh峄沺 2 tem kia).
- **Ki峄僲 ch峄﹏g:** `npx vue-tsc --noEmit` s岷h, `npm run build` th脿nh c么ng (38.41s). **Ch瓢a in th峄?tr锚n tem th岷璽.**
- **L瓢u 媒 quan tr峄峮g cho ng瓢峄漣 d霉ng (y岷縰 t峄?NGO脌I code):** 膽峄?膽岷璵 b岷 in tr锚n m谩y in nhi峄噒 ph岷 l峄沶 do c脿i 膽岷穞 **Darkness/Density** v脿 **t峄慶 膽峄?in (Speed)** trong driver m谩y in quy岷縯 膽峄媙h, kh么ng ph岷 CSS. N岷縰 sau b岷 s峄璦 n脿y v岷玭 nh岷 th矛 c岷 t膬ng Darkness / gi岷 Speed trong Printing Preferences c峄 m谩y in, ho岷穋 ki峄僲 tra gi岷/ribbon (gi岷 nhi峄噒 c农, 膽岷 in b岷﹏ c农ng g芒y m峄?膽峄乽 to脿n tem).

### 55. G峄?`transform: scale(0.95)` c峄 m峄 53 鈥?ch铆nh n贸 l脿 th峄?ph岷 l脿m tem m峄?(m峄 54) v脿 膽瓢峄漬g th岷硁g in ra 膼峄═ QU脙NG

- **Ng瓢峄漣 d霉ng b谩o:** "tr瓢峄沜 in th矛 kh么ng sao, gi峄?in c谩i 膽瓢峄漬g th岷硁g n贸 c农ng b峄?膽峄﹖ 膽峄﹖" 鈥?x谩c nh岷璶 膽芒y l脿 **regression do t么i g芒y ra**, kh么ng ph岷 v岷 膽峄?c贸 s岷祅 c峄 m谩y in.
- **Nguy锚n nh芒n (truy ng瓢峄 膽煤ng thay 膽峄昳 g芒y l峄梚):** m峄 53 th锚m `transform: scale(0.95)` 膽峄?ch峄玜 l峄?tr谩nh m岷 vi峄乶 tr锚n/tr谩i. Nh瓢ng `scale` nh芒n v脿o **c岷?膼峄?D脌Y n茅t**: vi峄乶 `0.25mm` (膽煤ng 2 dot 峄?203dpi) 鈫?`0.2375mm` = **1.9 dot**, kh么ng c貌n tr貌n dot. Khi rasterize, m峄梚 膽o岷 d峄峜 theo c霉ng m峄檛 膽瓢峄漬g b峄?l脿m tr貌n l煤c 1 dot l煤c 2 dot 鈫?**n茅t 膽峄﹖ qu茫ng**, v脿 c谩c n茅t b峄?"g岷" 膽i th脿nh x谩m 鈫?膽煤ng c岷?2 tri峄噓 ch峄﹏g ng瓢峄漣 d霉ng b谩o l岷 l瓢峄 峄?m峄 54 ("m峄?) v脿 l岷 n脿y ("膽峄﹖ 膽峄﹖"). T峄ヽ m峄 54 t么i 膽茫 ch峄痑 TRI峄哢 CH峄∟G (t膬ng 膽峄?d脿y, t膬ng font-weight, t膬ng DPI c峄 QR) m脿 **kh么ng nh岷璶 ra nguy锚n nh芒n g峄慶 n岷眒 峄?ch铆nh scale m矛nh v峄玜 th锚m**.
- **S峄璦:** g峄?b峄?ho脿n to脿n `transform: scale(0.95)` kh峄廼 c岷?3 n啤i in qua tr矛nh duy峄噒, tr岷?v峄?t峄?l峄?**1:1** 膽峄?m峄峣 n茅t khai b谩o theo mm lu么n tr貌n dot v脿 in ra li峄乶 m岷h.
- **Gi峄?l岷 c谩c c岷 ti岷縩 c峄 m峄 54** (kh么ng ph岷 nguy锚n nh芒n, v脿 th峄眂 s峄?gi煤p n茅t 膽岷璵 h啤n 峄?m谩y in nhi峄噒): vi峄乶 tr貌n dot 0.25mm/0.5mm, `font-weight: 600` cho ch峄?nh峄? QR ngu峄搉 960px, `print-color-adjust: exact`.
- **H峄?qu岷?ph岷 ch岷 nh岷璶 / c岷 ng瓢峄漣 d霉ng quy岷縯:** b峄?scale th矛 l峄梚 m岷 vi峄乶 tr锚n/tr谩i c峄 m峄 53 **quay l岷** 鈥?v矛 `.slip` 膽煤ng b岷眓g kh峄?gi岷 n锚n vi峄乶 ngo脿i n岷眒 ngay v霉ng kh么ng in 膽瓢峄. 膼芒y l脿 膽谩nh 膽峄昳 v岷璽 l媒 th岷璽 s峄?(kh峄?gi岷 = kh峄?tem), kh么ng th峄?v峄玜 1:1 v峄玜 c贸 l峄? **膼茫 h峄廼 ng瓢峄漣 d霉ng ch峄峮 h瓢峄沶g x峄?l媒** thay v矛 t峄?quy岷縯, v矛 ch峄?ng瓢峄漣 c岷 tem th岷璽 m峄沬 bi岷縯 m峄ヽ n脿o ch岷 nh岷璶 膽瓢峄.
- **B脿i h峄峜:** khi ng瓢峄漣 d霉ng b谩o l峄梚 m峄沬 xu岷 hi峄噉 ngay sau m峄檛 thay 膽峄昳 c峄 m矛nh, ph岷 nghi ng峄?ch铆nh thay 膽峄昳 膽贸 TR漂峄欳 khi 膽i ch峄痑 tri峄噓 ch峄﹏g 鈥?m峄 54 膽茫 b峄?qua b瓢峄沜 n脿y v脿 l脿m m岷 th锚m 1 v貌ng ph岷 h峄搃.
- **Ki峄僲 ch峄﹏g:** `npx vue-tsc --noEmit` s岷h, `npm run build` th脿nh c么ng (38.56s).

### 56. Tem in ra: 膽瓢峄漬g k岷?r膬ng c瓢a + ch峄?s峄?nh峄?nho猫 鈥?n茅t m岷h kh么ng s峄憂g s贸t qua dither c峄 driver m谩y in nhi峄噒

- **Ng瓢峄漣 d霉ng g峄璱 岷h tem in th岷璽** (sau khi g峄?scale 峄?m峄 55): 膽瓢峄漬g k岷?么 hi峄噉 ra d岷g **r膬ng c瓢a/l瓢峄 s贸ng** thay v矛 th岷硁g li峄乶; ch峄?to (JIT3, LEP70158, SE5433, VD003) 膽en 膽岷穋 s岷痗 n茅t nh瓢ng **ch峄?s峄?nh峄?trong b岷g** (Y1019A, R2064G, 6.65, 0.85鈥? nho猫, kh贸 膽峄峜.
- **Ph芒n t铆ch (膽峄慽 chi岷縰 tr峄眂 ti岷縫 v峄沬 岷h 鈥?c谩i g矛 r玫, c谩i g矛 m峄?:** ch峄?R脮 膽峄乽 l脿 n茅t d脿y (ch峄?3.2-5.5mm, font-weight 700); ch峄?M峄?膽峄乽 l脿 n茅t m岷h (膽瓢峄漬g k岷?0.25mm = 2 dot, ch峄?2.2mm c贸 n茅t 膽峄﹏g < 1 dot). Driver m谩y in nhi峄噒 TSC nh岷璶 岷h raster 峄?DPI cao t峄?tr矛nh duy峄噒 r峄搃 h岷?v峄?203dpi b岷眓g **dither** 鈥?n茅t ch峄?c岷 l峄嘽h n峄璦 dot l脿 b峄?chuy峄僴 th脿nh chu峄梚 ch岷 so le, 膽煤ng h矛nh r膬ng c瓢a th岷 trong 岷h. N茅t d脿y th矛 ph岷 l玫i v岷玭 膽en tuy峄噒 膽峄慽 n锚n kh么ng b峄?岷h h瓢峄焠g. K岷縯 lu岷璶: **ng瓢峄g an to脿n l脿 n茅t 鈮?3 dot**, kh么ng ph岷 2 dot nh瓢 gi岷?膽峄媙h 峄?m峄 54.
- **S峄璦:**
  - 膼瓢峄漬g k岷?(`.box`, `.gridcell`): `0.25mm` (2 dot) 鈫?**`0.375mm` (膽煤ng 3 dot)**.
  - Ch峄?nh峄? `.cellval` 2.2mm 鈫?**2.6mm**, `.label-sm` 2.3mm 鈫?2.6mm, `.med` 2.6mm 鈫?2.9mm, `.title` 2.4mm 鈫?2.6mm; t岷 c岷?n芒ng `font-weight` l锚n **700**. 峄?font-weight 700, c峄?鈮?2.6mm cho n茅t 膽峄﹏g 鈮?2 dot 鈥?膽峄?峄昻 膽峄媙h qua dither.
  - **Ki峄僲 tra kh么ng tr脿n 么 tr瓢峄沜 khi 膽峄昳** (kh么ng 膽o谩n): h脿ng b岷g cao 41 dot = 5.125mm, tr峄?padding 0.5mm脳2 c貌n 4.1mm > 2.6mm; 么 m茫 thu峄慶 nhu峄檓 r峄檔g 96 dot = 12mm, tr峄?padding 0.9mm脳2 c貌n 10.2mm, chu峄梚 6 k媒 t峄?峄?2.6mm bold 鈮?9.4mm 鈥?v岷玭 v峄玜. **Kh么ng ph岷 s峄璦 to岷?膽峄?b峄?c峄** 膽茫 kh峄沺 tem th岷璽.
- **Kh么ng 膽峄g tem v岷璽 t瓢 80x50** (ch峄?膽茫 3-3.4mm, v峄憂 膽峄?l峄沶) v脿 `tsplPrint.ts` (c峄?ch峄?suy ra t峄?l峄噉h TSPL c峄 backend, 膽峄昳 峄?膽芒y s岷?l峄嘽h v峄沬 tem TSPL th岷璽).
- **Ki峄僲 ch峄﹏g:** `npx vue-tsc --noEmit` s岷h, `npm run build` th脿nh c么ng (37.17s). **Ch瓢a in th峄?tr锚n tem th岷璽.**
- **L瓢u 媒 膽茫 nh岷痗 ng瓢峄漣 d霉ng:** tem trong 岷h c贸 th峄?膽瓢峄 in TR漂峄欳 khi b岷 g峄?scale (m峄 55) k峄媝 谩p d峄g 鈥?c岷 t岷 l岷 trang (Ctrl+F5) r峄搃 m峄沬 in th峄?膽峄?膽谩nh gi谩 膽煤ng b岷 m峄沬 nh岷.

### 57. T脤M RA NGUY脢N NH脗N G峄怌 c峄 c岷?chu峄梚 l峄梚 m峄?r膬ng c瓢a: `@page size` = 膼脷NG kh峄?gi岷 (m峄 42) l脿m tr矛nh duy峄噒 co c岷?trang cho v峄玜 v霉ng in 膽瓢峄

- **Manh m峄慽 quy岷縯 膽峄媙h t峄?ng瓢峄漣 d霉ng:** "tr瓢峄沜 膽贸 c膬n ch瓢a chu岷﹏, nh瓢ng in ra tem n脿o c农ng N脡T; gi峄?in chu岷﹏ v峄?tr铆 r峄搃 th矛 in ra b峄?nh瓢 v岷瓂". T峄ヽ ch岷 l瓢峄g n茅t x岷 膽i **膽煤ng t峄?l煤c tem b岷痶 膽岷 in 膽煤ng kh峄?膽煤ng v峄?tr铆** 鈥?m峄慶 膽贸 ch铆nh l脿 m峄 42 (th锚m `@page { size: 70mm 100mm; margin: 0 }`).
- **Nguy锚n nh芒n g峄慶:** v霉ng in 膽瓢峄 (printable area) c峄 M峄孖 m谩y in lu么n NH峄?H茽N kh峄?gi岷 v岷璽 l媒. Khi `@page size` khai 膽煤ng 70x100mm v脿 n峄檌 dung tr岷 k铆n t峄沬 m茅p, Chrome ph岷 co c岷?trang cho v峄玜 v霉ng in 膽瓢峄 ("fit to printable area") 鈥?h峄?s峄?co l脿 s峄?l岷? nh芒n v脿o **m峄峣** n茅t v脿 c峄?ch峄?鈫?kh么ng c貌n tr貌n dot 峄?203dpi 鈫?driver m谩y in nhi峄噒 dither 鈫?**r膬ng c瓢a + m峄?*. 膼芒y l脿 c霉ng m峄檛 c啤 ch岷?v峄沬 `transform: scale(0.95)` m脿 t么i t峄?th锚m 峄?m峄 53 r峄搃 ph岷 g峄?峄?m峄 55 鈥?ch峄?kh谩c l脿 l岷 n脿y tr矛nh duy峄噒 t峄?l脿m, n锚n g峄?scale xong v岷玭 c貌n l峄梚.
- **Nh矛n l岷 chu峄梚 m峄 53鈫?6 膽峄?r煤t kinh nghi峄噈:** m峄 53 (th锚m scale) l脿m l峄梚 N岷禢G TH脢M; m峄 54 v脿 56 ch峄痑 tri峄噓 ch峄﹏g (t膬ng 膽峄?d脿y n茅t, t膬ng c峄?ch峄? m脿 kh么ng ch岷 t峄沬 nguy锚n nh芒n. N岷縰 h峄廼 ng瓢峄漣 d霉ng s峄沵 "tr瓢峄沜 膽芒y in c贸 n茅t kh么ng" th矛 膽茫 khoanh v霉ng 膽瓢峄 ngay t峄?m峄 54 鈥?**manh m峄慽 qu媒 nh岷 lu么n l脿 m峄慶 th峄漣 gian l峄梚 b岷痶 膽岷 xu岷 hi峄噉.**
- **S峄璦 (thu 峄?T岷G TO岷?膼峄? kh么ng ph岷 transform/zoom):** th锚m `FIT = 0.955` v脿 `MARGIN_MM = 1.6` v脿o `printDispatchViaBrowser`; `mmD()` gi峄?tr岷?`(dot / 8) * FIT`, `.slip` l岷 k铆ch th瓢峄沜 t峄?ch铆nh `mmD(560)` x `mmD(800)` + `margin: 1.6mm` (b峄?hard-code 70x100mm). B岷 v岷?th脿nh 66.85 x 95.5mm n岷眒 g峄峮 trong v霉ng in 膽瓢峄 鈫?**tr矛nh duy峄噒 kh么ng ph岷 co trang n峄痑**.
  - **Kh谩c bi峄噒 then ch峄憈 so v峄沬 `transform: scale()`:** 峄?膽芒y ch峄?**TO岷?膼峄?* b峄?nh芒n h峄?s峄? c貌n **膼峄?D脌Y n茅t v脿 C峄?CH峄?v岷玭 khai b谩o b岷眓g mm nguy锚n** (`0.375mm` = 膽煤ng 3 dot, ch峄?2.6mm) 鈫?n茅t v岷玭 tr貌n dot, in ra li峄乶 m岷h. 膼芒y ch铆nh l脿 膽i峄僲 m脿 `transform` kh么ng l脿m 膽瓢峄.
  - Gi岷 `padding` ngang `.box` `0.9mm 鈫?0.6mm` 鈥?b霉 l岷 vi峄嘽 么 h岷筽 膽i 4.5%, 膽峄?m茫 6 k媒 t峄?(Y1019A/R2064G) kh么ng ch岷 m茅p 么.
- **Ki峄僲 ch峄﹏g:** `npx vue-tsc --noEmit` s岷h, `npm run build` th脿nh c么ng (39.09s). **Ch瓢a in th峄?tr锚n tem th岷璽.**
- **N岷縰 v岷玭 ch瓢a n茅t (thao t谩c c峄 ng瓢峄漣 d霉ng, kh么ng ph岷 code):** trong h峄檖 tho岷 in c峄 Chrome m峄?ph岷 **More settings** v脿 膽岷穞 **Scale = 100%** (kh么ng 膽峄?"Fit to printable area") + **Margins = None**. N岷縰 Chrome v岷玭 t峄?co th矛 m峄峣 ch峄塶h s峄璦 CSS 膽峄乽 v么 hi峄噓, v矛 h峄?s峄?co n岷眒 ngo脿i t岷 ki峄僲 so谩t c峄 trang web. S峄璦 ti岷縫 m峄 45: QR g贸c tr锚n d谩n s谩t vi峄乶 tr锚n, kh么ng th岷 vi峄乶 鈥?co nh峄?c貌n ~95% + 膽岷﹜ xu峄憂g

- **Ng瓢峄漣 d霉ng b谩o:** sau m峄 45, QR 膽i s谩t qu谩, kh么ng c貌n th岷 vi峄乶 TR脢N c峄 tem; y锚u c岷 co nh峄?l岷 c貌n kho岷g 95% v脿 膽岷﹜ QR xu峄憂g 1 ch煤t 膽峄?ch峄玜 vi峄乶.
- **Nguy锚n nh芒n:** b岷 m峄 45 d霉ng padding 膽峄乽 `0.15mm` c岷?4 c岷h cho 么 n脿y + `align-items:center` 鈥?岷h 13.2mm g岷 nh瓢 l岷 膽岷 h岷縯 chi峄乽 cao kh岷?d峄g (~13.3mm), ph岷 膽峄噈 ph铆a tr锚n vi峄乶 ch峄?c貌n ~0.35mm (vi峄乶 0.2mm + 膽峄噈 0.15mm), th峄眂 t岷?in ra kh么ng ph芒n bi峄噒 膽瓢峄 ranh gi峄沬.
- **S峄璦:** `.qr-block-inline` 膽峄昳 `align-items: center` 鈫?`flex-start` (neo 岷h v脿o m茅p tr锚n c峄 V脵NG N峄業 DUNG thay v矛 canh gi峄痑 c岷?么); 岷h `13.2mm` 鈫?`12.5mm` (~95%); padding ri锚ng 么 n脿y 膽峄昳 t峄?膽峄乽 `0.15mm` sang l峄嘽h `0.9mm` (tr锚n) / `0.15mm` (ph岷/d瓢峄沬/tr谩i) 鈥?膽岷﹜ h岷硁 岷h xu峄憂g, ch峄玜 khe h峄?r玫 r脿ng v峄沬 vi峄乶 tr锚n, 3 c岷h c貌n l岷 v岷玭 gi峄?s谩t nh瓢 c农 (kh么ng b峄?ng瓢峄漣 d霉ng ph脿n n脿n).
- **Ki峄僲 ch峄﹏g:** `npx vue-tsc --noEmit` s岷h. Ch瓢a in th峄?l岷 tr锚n tem th岷璽.

### 58. 膼瓢峄漬g nh岷璶 s峄?c芒n c峄 `/weighing-station-v2` 鈥?tr峄?t峄沬 1.5s v脿 kh么ng ph芒n bi峄噒 膽瓢峄 "m岷 t铆n hi峄噓" v峄沬 "c芒n r峄梟g"

- **B峄慽 c岷h:** sau khi V2 chuy峄僴 sang ch峄憈 B脤 **t峄?膽峄檔g** t峄?l岷 膽峄峜 峄昻 膽峄媙h 膽岷 ti锚n sau khi b岷 NEXT (b谩m 膽煤ng `Mod_delta_raw.AutoFlow_OnWeight`), ch岷 l瓢峄g 膽瓢峄漬g truy峄乶 s峄?c芒n tr峄?th脿nh y岷縰 t峄?quy岷縯 膽峄媙h 膽峄?ch铆nh x谩c, ch峄?kh么ng c貌n ch峄?l脿 chuy峄噉 hi峄僴 th峄?m瓢峄 hay gi岷璽.
- **Chu峄梚 hi峄噉 tr岷g:** C芒n 鈫?Agent poll **500ms** 鈫?`POST /devices/readings` 鈫?`Cache` TTL 15s 鈫?tr矛nh duy峄噒 poll **1000ms**. T峄昻g tr峄?t峄沬 **1.5 gi芒y**; VBA g峄慶 膽峄峜 file log m峄梚 **10ms** (`p0-c-scale-algorithm.md` M峄 A.1).
- **4 h峄?qu岷?膽茫 x谩c 膽峄媙h:**
  1. **B矛 ch峄憈 mu峄檔:** th峄?b岷 NEXT r峄搃 膽峄?ngay trong 1 gi芒y 鈫?m岷玼 峄昻 膽峄媙h 膽岷 ti锚n tr矛nh duy峄噒 b岷痶 膽瓢峄 膽茫 c贸 b峄檛 tr锚n 膽末a 鈫?b矛 t铆nh lu么n ph岷 b峄檛 膽贸 鈫?delta thi岷縰 鈫?th峄?膽峄?d瓢 m脿 m脿n h矛nh v岷玭 b谩o ch瓢a 膽峄?
  2. **B矛 ch峄憈 v脿o s峄?C浓:** cache s峄憂g 15s m脿 `getReading` kh么ng tr岷?th峄漣 膽i峄僲 膽峄峜 鈫?tr矛nh duy峄噒 kh么ng ph芒n bi峄噒 膽瓢峄 s峄?v峄玜 膽峄峜 v峄沬 s峄?8 gi芒y tr瓢峄沜.
  3. **StableFilter sai nh峄媝 峄?ch岷?膽峄?膽峄峜 file PuTTY:** "峄昻 膽峄媙h" = 2 l岷 膽峄峜 li锚n ti岷縫 gi峄憂g nhau = 2 脳 PollIntervalMs 鈫?VBA 20ms, Agent 1 gi芒y. (Ch岷?膽峄?RS232 th岷璽 th矛 膽煤ng v矛 m峄梚 d貌ng serial = 1 l岷 膽峄峜.)
  4. **M岷 t铆n hi峄噓 c芒n hi峄僴 th峄?y h峄噒 c芒n r峄梟g:** `getReading` tr岷?m岷穋 膽峄媙h `weight = 0.0` khi cache tr峄憂g 鈥?膽煤ng l峄沺 l峄梚 TV6 膽茫 v谩 峄?Agent (`Worker.cs` kh么ng 膽岷﹜ 0.0 gi岷? nh瓢ng **backend t峄?t谩i t岷 l岷**. Ch岷 `scaleOnline` ch峄?b谩o g峄峣 膽瓢峄 API, kh么ng b谩o c芒n s峄憂g.
- **S峄璦:**
  - `DeviceController::getReading` tr岷?th锚m `has_reading` + `age_ms`; `storeReading` 膽峄昳 `time()` 鈫?`microtime(true)` 膽峄?c贸 膽峄?ph芒n gi岷 d瓢峄沬 gi芒y (`RealtimeService` 茅p `(int)` v峄?gi芒y n锚n kh么ng 岷h h瓢峄焠g). Key `scale_live_weight_timestamp_` v峄憂 膽茫 ghi s岷祅 t峄?tr瓢峄沜 nh瓢ng **ch瓢a t峄玭g 膽瓢峄 膽峄峜 ra**.
  - `useScaleFeed.ts`: th锚m ng瓢峄g `STALE_READING_MS = 1500`. S峄?c农 h啤n ng瓢峄g 鈫?gi峄?nguy锚n m脿n h矛nh v脿 tho谩t (膽煤ng quy 瓢峄沜 TV6), **kh么ng** 膽瓢峄 l脿m b矛, `isStable` 茅p v峄?false. Th锚m `signalLive` t谩ch kh峄廼 `scaleOnline`.
  - `WeighingStationV2.vue`: poll 1000ms 鈫?**200ms**; ch岷 xanh nay d峄盿 tr锚n `scaleOnline && signalLive`; th锚m banner 膽峄?"M岷 T脥N HI峄哢 C脗N".
  - Agent: `Scale:PollIntervalMs` m岷穋 膽峄媙h 500 鈫?**150ms** (`Worker.cs`) v脿 trong `installer/appsettings.scale.json`.
- **膼谩nh 膽峄昳 膽茫 c芒n nh岷痗:** m峄梚 tr岷 c芒n 膽i t峄?~2 l锚n ~6-7 request/gi芒y v脿o Laravel. V峄沬 2 tr岷 pilot kh么ng 膽谩ng k峄? n岷縰 nh芒n l锚n 10+ tr岷 c岷 b脿n l岷 (route nh岷?kh么ng boot full framework, ho岷穋 Agent m峄?c峄昻g HTTP c峄 b峄?cho tr矛nh duy峄噒 h峄廼 th岷硁g 鈥?h瓢峄沶g sau **ph谩 ranh gi峄沬 ph芒n l峄沺, ph岷 c贸 ADR m峄沬**).
- **KH脭NG d霉ng SSE cho lu峄搉g n脿y:** ADR-009 b岷痶 m峄峣 s峄?ki峄噉 realtime 膽i qua Transactional Outbox `app.realtime_events`; ghi 5-10 d貌ng/gi芒y/tr岷 s峄?c芒n nh岷 th峄漣 v脿o 膽贸 l脿 sai m峄 膽铆ch c峄 outbox (d脿nh cho s峄?ki峄噉 nghi峄噋 v峄? kh么ng ph岷 s峄?hi峄僴 th峄?tho谩ng qua).
- **L漂U 脻 TRI峄侼 KHAI:** 膽峄昳 m岷穋 膽峄媙h `PollIntervalMs` trong code **kh么ng** t峄?谩p d峄g cho 2 m谩y pilot 膽茫 c脿i MSI 鈥?`C:\Program Files\DFAgent\appsettings.json` ghi 膽猫 gi谩 tr峄?n脿y, ph岷 s峄璦 tay r峄搃 restart service `DFAgent`, ho岷穋 c脿i l岷 MSI.
- **Ki峄僲 ch峄﹏g:** `vue-tsc --noEmit` s岷h, `vite build` th脿nh c么ng (16.16s), `dotnet build` agent th脿nh c么ng (0 l峄梚). Th锚m 2 test `ScaleLiveWeightTest::test_get_reading_reports_age_of_last_push` v脿 `..._flags_missing_reading_instead_of_faking_zero` 鈥?**ch瓢a ch岷 膽瓢峄**: file test n脿y kh么ng d霉ng `RefreshDatabase` n锚n c岷 DB th岷璽, m脿 Postgres test (`127.0.0.1:5433`) hi峄噉 kh么ng ch岷. Test .NET c峄 Agent c农ng kh么ng ch岷 膽瓢峄 (m谩y ch峄?c贸 .NET runtime 3.1/9.0/10.0, project test nh岷痬 net8.0). C岷?hai 膽峄乽 l脿 h岷 ch岷?m么i tr瓢峄漬g c贸 s岷祅, kh么ng li锚n quan t峄沬 thay 膽峄昳 n脿y.
- **Ch瓢a x谩c minh b岷眓g c芒n th岷璽** 鈥?c岷 ch岷 th峄?t岷 tr岷 pilot 膽峄?x谩c nh岷璶 150ms/200ms c贸 膽峄?膽峄?b矛 ch峄憈 膽煤ng l煤c hay kh么ng.

### 59. Quay v峄?膼峄孋 C脗N THEO C脕CH C浓 (file log PuTTY) 峄?nh峄媝 10ms 鈥?t谩ch nh峄媝 膼峄孋 kh峄廼 nh峄媝 膼岷╕

- **Y锚u c岷 ng瓢峄漣 d霉ng:** d霉ng l岷 c谩ch 膽峄峜 c芒n c农 (file log PuTTY nh瓢 Excel VBA) v脿 膽峄峜 峄?nh峄媝 **10ms** 膽煤ng b岷眓g VBA.
- **Nh岷璶 膽峄媙h then ch峄憈 鈥?hai nh峄媝 c贸 chi ph铆 kh谩c h岷硁 nhau, tr瓢峄沜 膽芒y b峄?g峄檖 l脿m m峄檛:**
  - **膼峄孋** (膽u么i file c峄 b峄?/ bi岷縩 膽茫 ch峄憈 t峄?c峄昻g COM): g岷 nh瓢 mi峄卬 ph铆. 膼芒y l脿 nh峄媝 quy岷縯 膽峄媙h `StableFilter` 鈥?"峄昻 膽峄媙h" = 2 l岷 膽峄峜 li锚n ti岷縫 gi峄憂g nhau, n锚n 10ms 鈬?**20ms**, 膽煤ng b岷眓g VBA (tr瓢峄沜 峄?500ms l脿 **1 gi芒y** m峄沬 d谩m b谩o 峄昻 膽峄媙h).
  - **膼岷╕** l锚n backend: m峄梚 l岷 l脿 1 HTTP request + 1 v貌ng bootstrap Laravel. 膼芒y m峄沬 l脿 th峄?膽岷痶 v脿 l脿 th峄?duy nh岷 c岷 c芒n nh岷痗 khi nh芒n s峄?tr岷.
  - T谩ch ra th脿nh `Scale:ReadIntervalMs` (10) v脿 `Scale:PushIntervalMs` (200). M峄 58 h岷?`PollIntervalMs` 500鈫?50 l脿 **tho岷?hi峄噋 sai ch峄?* v矛 c貌n g峄檖 chung; nay b峄?
- **Ph谩t hi峄噉 khi 膽峄峜 l岷 VBA g峄慶:** v貌ng `ModRead_putty_log.StartFastLoop` c贸 膽i峄乽 ki峄噉 `If s <> "" And s <> rawline`, nh瓢ng `rawline` 膽瓢峄 g谩n **gi谩 tr峄?膽茫 l峄峜** (`rawline = CleanWeight(s)`) r峄搃 膽em so v峄沬 `s` **th么** 鈥?hai chu峄梚 n脿y g岷 nh瓢 kh么ng bao gi峄?b岷眓g nhau n锚n 膽i峄乽 ki峄噉 lu么n 膽煤ng, t峄ヽ VBA th峄眂 ch岷 **膽岷﹜ m峄梚 10ms b岷 k峄?s峄?c贸 膽峄昳 hay kh么ng**. Ch铆nh 膽i峄乽 膽贸 l脿m `StableFilter` ho岷 膽峄檔g 膽瓢峄. V矛 v岷瓂 b岷 port n岷 **m峄峣** l岷 膽峄峜 v脿o filter, kh么ng l峄峜 theo thay 膽峄昳.
- **Hai c谩i b岷珁 c峄 nh峄媝 10ms, 膽茫 x峄?l媒 tr瓢峄沜 khi h岷?nh峄媝:**
  1. `ReadSimulatedWeight` d霉ng `File.ReadAllLines` 鈥?**膽峄峜 TO脌N B峄?file m峄梚 l岷**. File log PuTTY ph矛nh d岷 su峄憈 ca; 膽峄峜 c岷?file 100 l岷/gi芒y s岷?ngh岷箃 I/O m谩y tr岷. Thay b岷眓g `ReadLastCompleteLine`: seek t峄沬 cu峄慽, ch峄?膽峄峜 4KB cu峄慽, chi ph铆 kh么ng ph峄?thu峄檆 k铆ch th瓢峄沜 file. M峄?v峄沬 `FileShare.ReadWrite|Delete` v矛 PuTTY 膽ang gi峄?file 膽峄?ghi.
  2. **D貌ng cu峄慽 膽ang ghi d峄?*: 峄?10ms, x谩c su岷 ch峄檖 膽煤ng l煤c PuTTY m峄沬 ghi n峄璦 d貌ng (`12,ST,GS,+0000`) cao g岷 ~50 l岷 so v峄沬 500ms, m脿 `CleanWeight` s岷?parse m岷h c峄 th脿nh `0` 鈥?m峄檛 s峄?c芒n H峄 L峄?nh瓢ng SAI. Nay b峄?qua ph岷 膽u么i ch瓢a c贸 CR/LF. 膼谩nh 膽峄昳: ch岷璵 h啤n 膽煤ng m峄檛 d貌ng (c芒n ph谩t ~5-10 d貌ng/gi芒y) 膽峄?kh么ng bao gi峄?膽峄峜 ph岷 s峄?c峄.
- **V谩 lu么n kh谩c bi峄噒 A.1** (`p0-c-scale-algorithm.md`): VBA `ReadLastLineFast` b峄?qua d貌ng r峄梟g (`If Len(s) > 0`), b岷 .NET c农 l岷 d貌ng v岷璽 l媒 cu峄慽 n锚n tr岷?`""` khi file k岷縯 th煤c b岷眓g d貌ng tr岷痭g, r峄搃 b峄?hi峄僽 th脿nh "c芒n 膽峄峜 0kg".
- **膼峄昳 t锚n c峄?c岷 h矛nh:** th锚m `Scale:Source` = `PUTTY_LOG` | `SERIAL`. Tr瓢峄沜 膽芒y mu峄憂 膽峄峜 file PuTTY ph岷 b岷璽 `UseSimulation: true` 鈥?膽岷穞 t锚n sai b岷 ch岷, v矛 膽峄峜 file PuTTY l脿 c谩ch v岷璶 h脿nh TH岷琓 c峄 x瓢峄焠g nhi峄乽 n膬m nay, kh么ng ph岷 demo; r岷 d峄?b峄?ai 膽贸 t岷痶 v矛 t瓢峄焠g l脿 膽峄?gi岷?l岷璸. C峄?c农 v岷玭 膽瓢峄 膽峄峜 l脿m d峄?ph貌ng.
- **T谩ch nh峄媝 l岷 l峄噉h in** (`Printer:PollIntervalMs`, m岷穋 膽峄媙h 1000ms): tr瓢峄沜 b峄?bu峄檆 chung v貌ng l岷穚 c芒n, 膽峄?v貌ng l岷穚 ch岷 10ms m脿 kh么ng t谩ch s岷?th脿nh 100 request l岷 l峄噉h in m峄梚 gi芒y.
- **Ki峄僲 ch峄﹏g:** `dotnet test` **15/15 pass** (th锚m 6 test m峄沬 cho 膽峄峜 膽u么i file: d貌ng cu峄慽 kh么ng r峄梟g, file k岷縯 th煤c b岷眓g d貌ng tr岷痭g, d貌ng ghi d峄? file 4MB 膽峄峜 100 l岷 < 500ms, file 膽ang b峄?ti岷縩 tr矛nh kh谩c gi峄?膽峄?ghi). Ch岷 膽瓢峄 nh峄?`DOTNET_ROLL_FORWARD=LatestMajor` 鈥?m谩y ch峄?c贸 .NET runtime 3.1/9.0/10.0 c貌n project nh岷痬 net8.0. `dotnet build` 0 l峄梚, `vue-tsc --noEmit` s岷h.
- **Ch瓢a x谩c minh tr锚n c芒n th岷璽.**
- **Tri峄僴 khai l锚n 2 m谩y pilot:** s峄璦 `C:\Program Files\DFAgent\appsettings.json` 膽岷穞 `"Source": "PUTTY_LOG"` + `"LogFilePath"` tr峄?膽煤ng 膽瓢峄漬g d岷玭 PuTTY 膽ang ghi (xem m峄 60), r峄搃 restart service `DFAgent`. N岷縰 KH脭NG s峄璦 g矛, m谩y v岷玭 ch岷 ch岷?膽峄?SERIAL nh瓢 c农 nh瓢ng 膽茫 t峄?h瓢峄焠g nh峄媝 膽峄峜 10ms (key `ReadIntervalMs` v岷痭g m岷穞 鈬?m岷穋 膽峄媙h 10) 鈥?t峄ヽ `StableFilter` 膽瓢峄 v谩 m脿 kh么ng c岷 膽峄g c岷 h矛nh.

### 60. Ch峄憈 膽瓢峄漬g d岷玭 file log PuTTY tr锚n m谩y tr岷 c芒n: `D:\scale\putty_log.txt`

- **Ng瓢峄漣 d霉ng ch峄憈:** Agent 膽峄峜 c芒n t峄?`D:\scale\putty_log.txt` tr锚n m谩y c脿i DFAgent.
- **膼峄昳 kho谩 c岷 h矛nh `Scale:SimulationFilePath` 鈫?`Scale:LogFilePath`** (kho谩 c农 v岷玭 膽峄峜 l脿m d峄?ph貌ng, c贸 test kho谩 l岷). C霉ng l媒 do v峄沬 `Source`/`UseSimulation` 峄?m峄 59: 膽芒y l脿 膽瓢峄漬g ch岷 TH岷琓 c峄 x瓢峄焠g, 膽峄?t锚n "Simulation" l脿 m峄漣 ng瓢峄漣 kh谩c t岷痶 nh岷.
- **膼瓢峄漬g d岷玭 m岷穋 膽峄媙h trong code** (`ScaleReader.DefaultLogFilePath`) c农ng 膽峄昳 th脿nh 膽瓢峄漬g d岷玭 n脿y, c贸 test kho谩 鈥?膽峄?vi峄嘽 膽峄昳 膽瓢峄漬g d岷玭 ph岷 l脿 h脿nh 膽峄檔g c贸 ch峄?媒.
- **膼茫 truy ng瓢峄 c谩ch MSI 膽贸ng g贸i c岷 h矛nh** thay v矛 膽o谩n: `DFAgentSetup.wxs:32,91` 膽贸ng g贸i th岷硁g `installer/appsettings.scale.json` th脿nh `appsettings.json` trong th瓢 m峄 c脿i. 膼贸 l脿 file duy nh岷 c岷 s峄璦. (`DFAgentSetup.iss` l脿 b岷 Inno Setup c农, tr峄?t峄沬 `appsettings.template.json` 鈥?file n脿y KH脭NG t峄搉 t岷 n峄痑, nh谩nh 膽贸 膽茫 ch岷縯, kh么ng d霉ng.)
- **Ki峄僲 ch峄﹏g:** `dotnet test` **18/18 pass** (th锚m 3 test: 膽瓢峄漬g d岷玭 m岷穋 膽峄媙h, kho谩 c农 `SimulationFilePath` c貌n hi峄噓 l峄眂, kho谩 m峄沬 `LogFilePath` th岷痭g kho谩 c农 khi c贸 c岷?hai).
- **膼i峄乽 ki峄噉 v岷璶 h脿nh c岷 nh岷痗 ng瓢峄漣 d霉ng:** ch岷?膽峄?PUTTY_LOG 膽貌i PuTTY ph岷 膽ang ch岷 v脿 膽茫 b岷璽 Session Logging ghi 膽煤ng v脿o 膽瓢峄漬g d岷玭 n脿y. Agent KH脭NG t峄?b岷璽 PuTTY. N岷縰 PuTTY t岷痶/ghi sai ch峄? Agent ng峄玭g 膽岷﹜ s峄?v脿 m脿n h矛nh V2 hi峄噉 "M岷 T脥N HI峄哢 C脗N" (c啤 ch岷?th锚m 峄?m峄 58) 鈥?kh么ng c貌n im l岷穘g hi峄僴 th峄?0.00 nh瓢 tr瓢峄沜.

### 61. Dung bo cai DFAgent 2.1.0.0 - phat hien Backend:Url trong config TRO SAI DIA CHI

- **Yeu cau:** nguoi dung can bo cai Agent chuan de cai va nhan can.
- **Da build:** `agent\installer\build.ps1` -> `DFAgentSetup-Scale.msi` (28.1 MB), copy san sang `backend\public\downloads\`. Tang `PackageVersion` 2.0.0.0 -> **2.1.0.0** vi may pilot dang cai 2.0.0.0; giu nguyen so thi MajorUpgrade khong nang cap sach duoc.
- **LOI QUAN TRONG PHAT HIEN KHI KIEM TRA (khong phai do doi lan nay gay ra, co san tu truoc):** `Backend:Url` trong `appsettings.scale.json` dong cung `http://10.0.200.248:8500/api`. Kiem chung bang `Test-NetConnection`: dia chi do **KHONG ping va KHONG mo cong 8500**; con CS-SERVER `10.0.60.209:8500` thi **nhan ket noi TCP** va tra **HTTP 401** tren `/api/devices/readings/...` (endpoint ton tai, doi xac thuc). Da doi sang `http://10.0.60.209:8500/api`. Neu khong sua, Agent cai xong se doc duoc can nhung **khong gui duoc so nao len he thong** 鈥?va trieu chung o man hinh chi la "MAT TIN HIEU CAN", rat de bi doan nham la loi cong COM/PuTTY. Muc 780 (2026-07-31) da tung ghi chu "can sua khi trien khai" nhung khong ai sua.
- **LOI THIET KE TU MINH GAY RA O MUC 59, PHAT HIEN KHI CHAY THAT:** vong doc 10ms dang `await` lenh day HTTP ngay ben trong no. `HttpClient` de timeout 5 giay, nen mot lan backend khong phan hoi se lam Agent **ngung doc can 5 giay** 鈥?nhip 10ms thanh vo nghia dung luc can nhat. Sua: viec mang chay roi khoi vong doc (`Task? pushInFlight` / `printPollInFlight`, giu toi da 1 viec moi loai dang bay, chua xong thi bo qua luot nay thay vi xep hang). Gop 2 viec mang cua may in vao `ProcessPrintWorkAsync()` de vong doc chi theo doi 1 handle.
- **Kiem chung END-TO-END tren ban da publish** (khong chi unit test):
  1. Chay `DFAgent.exe` doc file log gia -> log ra dung `doc can moi 10ms, day len backend moi 200ms`, doc duoc 10.5 kg (so CUOI, khong phai "12").
  2. **Chong ket vong doc:** tro `Backend:Url` vao IP khong dinh tuyen (`10.255.255.1`, ket noi treo toi timeout 5s) roi doi so can 6 lan cach nhau 400ms -> Agent ghi nhan **du ca 6 lan**. Truoc khi sua thi vong doc da dung im.
  3. **Endpoint gia bang HttpListener** de xem Agent that su day gi: `is_stable` chuyen **false -> true tu lan doc thu hai** (dung ngu nghia VBA), khoang cach giua cac lan day do duoc **192-220ms** (dung nhip 200ms), 15 lan day trong 3 giay.
  4. **Giai nen MSI bang `msiexec /a`** de xac minh cau hinh THAT nam trong bo cai, khong tin vao script build: Backend.Url=10.0.60.209:8500/api, Source=PUTTY_LOG, LogFilePath=D:\scale\putty_log.txt, ReadIntervalMs=10, PushIntervalMs=200, Role=SCALE_ONLY.
- `dotnet test` 18/18 pass, `dotnet build` 0 loi.
- **CON TON DONG:** 2 truong moi `has_reading`/`age_ms` cua `DeviceController::getReading` (muc 58) **chua deploy len CS-SERVER**. Frontend da co duong lui (thieu truong thi coi nhu con tuoi) nen khong vo, nhung banner "MAT TIN HIEU CAN" chi hoat dong sau khi deploy backend.

### 62. Them bao cao "May da o trang thai hien tai bao lau roi" duoi luoi trang thai may (Dashboard tab Dieu do tong the)

- **Yeu cau nguoi dung (2026-08-01):** o trang chu can them 1 bao cao phia duoi cho biet may nao dang o tinh trang do bao lau roi.
- **Van de nguon du lieu:** BPDB KHONG co cot "thoi diem doi trang thai". Trang thai may la ket qua suy ra tu task quyet dinh (`reduceMachineStatus`), nen moc dem phai lay tu chinh task do:
  - PROCESSING / ERROR -> `WorkStartTime` (luc may thuc su bat dau chay), fallback `CreateTime`
  - WAITING / TRANSITIONING -> `CreateTime`
  - COMPLETED_RECENTLY / CANCELLED -> `FinishTime`, fallback `CreateTime`
  - IDLE -> khong co task nao, phai query rieng (xem duoi)
  Tra ve kem `statusSinceSource` de nguoi xem biet dong ho dang dem tu moc nao, khong phai doan.
- **May IDLE:** query trang thai chinh chi lay task active + 24h gan nhat nen khong du de biet may trong bao lau. Them `getLastActivityByMachineId()` - 1 query aggregate `MAX(COALESCE(FinishTime, WorkStartTime, CreateTime)) GROUP BY Machine`, cua so 30 ngay, cache **60s** (do chinh xac tung giay vo nghia voi may dang trong). Chi chay khi thuc su co it nhat 1 may IDLE; loi query thi nuot va tra rong, khong lam hong ca bang trang thai. May khong co task nao trong 30 ngay -> `statusSince = null`, giao dien ghi "> 30 ngay" (nguong duoi that), KHONG bia so cu the.
- **Chong lech dong ho:** frontend KHONG lay `new Date() - statusSince` (may tram nha xuong hay lech gio so voi server/BPDB, se ra so am hoac vong len hang gio). Dung `statusDurationSeconds` do server tinh + so giay troi qua ke tu luc nhan snapshot (`bpdbFetchedAtLocal`). Dong ho nhich moi giay bang `nowTick`, clear interval khi unmount.
- **Nhanh non-admin** (`/api/dashboard/overview`, du lieu noi bo `app`): bang `production_batches` khong co `status_changed_at`, chi co `updated_at` -> tra `status_since` = `updated_at` va ghi ro tren giao dien la **UOC TINH**, may trong -> null (khong xac dinh, khong phai 0).
- **Giao dien:** bang **sap xep theo ma may** (thu tu tu nhien, `localeCompare` numeric de "VD9" khong nhay sau "VD10") - nguoi dung yeu cau doi tu sap xep theo thoi luong sang theo ten may de do doi chieu voi luoi trang thai ngay tren; to vang dong co `stuckWarning` (nguong doc tu `feature_flags`, khong hard-code), to do dong ERROR; co checkbox "Chi hien may co canh bao keo dai" de van loc nhanh may bat thuong.
- **Kiem chung:** `php -l` sach 2 file backend, `vue-tsc --noEmit` sach. **Chua xac minh bang mat tren trinh duyet that** va chua do thoi gian chay thuc te cua query aggregate 30 ngay tren BPDB (query nay chay 1 lan/60s, trong khi query trang thai hien co da chay 1 lan/4s va cung khong dung duoc index sach vi co menh de OR).
- **File cham:** `backend/app/Services/ColorService/BpdbMachineMonitoringService.php`, `backend/app/Http/Controllers/DashboardController.php`, `frontend/src/views/Dashboard.vue`.

### 62. "MAT TIN HIEU CAN" sau khi cai Agent 鈥?Agent CHAY TOT, loi la frontend hoi cache bang ID SO con Agent ghi bang MA TRAM

- **Trieu chung:** cai xong Agent, man hinh `/weighing-station-v2` bao "MAT TIN HIEU CAN".
- **Chan doan (chi doc DB, khong ghi):** truy van bang `cache` cua `production_web` -> khoa `scale_live_weight_WS-WEIGH-SCALE` **con han 15 giay**, tuc **Agent dang day so can len binh thuong**. Tram `WS-WEIGH-SCALE` co `id=6` trong `operation_clients`.
- **NGUYEN NHAN GOC:** `DeviceController::storeReading` ghi cache theo `workstation_id` Agent gui len = **MA tram** (`scale_live_weight_WS-WEIGH-SCALE`), con frontend goi `/api/devices/readings/{id}` voi `Workstation.id` la **KHOA CHINH DANG SO** -> tra khoa `scale_live_weight_6`, **mot khoa khong bao gio ton tai**. Hai ben chua bao gio gap nhau.
- **Vi sao den gio moi lo:** truoc day `getReading` tra mac dinh `weight = 0.0` khi cache trong -> man hinh hien "0.00" y het mot cai can rong dang cho dat vat tu. Co `has_reading` them o muc 58 bien loi im lang nay thanh canh bao nhin thay duoc. **Banner khong bao sai 鈥?no dang noi dung ve mot loi co san tu truoc.** Anh huong ca `/weighing-station` (V1) va `Dashboard.vue`, khong rieng V2: ca 3 deu truyen id so.
- **Sua:** them `DeviceController::resolveReadingKey()` 鈥?tham so khong phai so thi dung thang lam ma tram; la so thi tra `operation_clients.id -> code` roi doc cache theo code. Bat buoc kiem tra `ctype_digit` TRUOC khi so voi cot `id` (bigint), neu khong Postgres loi ngay "invalid input syntax for type bigint". Sua o backend thay vi sua tung cho goi ben frontend de ca 3 man hinh cung huong.
- **Kiem chung tren DU LIEU THAT** (goi thang controller, chi doc):
  - `getReading('6')` -> `weight=-0.02, stable=true, has_reading=true, age_ms=60` (so moi 60ms, dung nhip day 200ms cua Agent)
  - `getReading('WS-WEIGH-SCALE')` -> cung ket qua (khong pha duong cu)
  - `getReading('99999999')` -> `has_reading=false`, HTTP 200, khong loi 500
- Them 2 test vao `ScaleLiveWeightTest` (`..._accepts_numeric_workstation_id_not_only_code`, `..._with_unknown_numeric_id_reports_no_reading`). **CHUA CHAY DUOC**: file test nay khong dung `RefreshDatabase` nen can DB that, ma `.env` tro thang vao DB SAN XUAT (`10.0.60.209:5433/production_web`) 鈥?chay test se ghi vao production, khong lam. DB test rieng `127.0.0.1:5433` khong chay.
- **Luu y moi truong phat hien duoc:** `backend/.env` cua may dev dang tro `DB_HOST=10.0.60.209` (DB san xuat) va `CACHE_STORE=database` 鈥?nen backend local va CS-SERVER **dung chung cache**, do la ly do so can Agent day len CS-SERVER van toi duoc man hinh localhost. Cung co nghia moi lenh test/migrate chay o may dev deu cham thang vao production.

### 63. S峄璦 l峄梚 500 "column assigned_workstation_id does not exist" khi qu茅t m茫 v岷h 峄?Tr岷 c芒n (c岷?localhost l岷玭 CS-SERVER)

- **Tri峄噓 ch峄﹏g:** ng瓢峄漣 d霉ng b谩o l峄梚 SQL 500 y h峄噒 tr锚n c岷?`http://localhost:3001/weighing-station-v2` l岷玭 `http://10.0.60.209:3001/weighing-station-v2` ngay khi qu茅t 膽啤n 鈥?`SQLSTATE[42703]: column "assigned_workstation_id" does not exist`. C霉ng l峄梚 峄?c岷?2 m么i tr瓢峄漬g 鈬?bug trong code, kh么ng ph岷 drift d峄?li峄噓 ri锚ng c峄 1 server.
- **Nguy锚n nh芒n (膽峄峜 code, kh么ng 膽o谩n):** migration `2026_07_17_131458_create_operation_client_architecture_tables.php` (膽峄 t谩i c岷 tr煤c "ki岷縩 tr煤c OperationClient") 膽茫 **膽峄昳 t锚n c峄檛 th岷璽** trong b岷g `weighing_jobs` t峄?`assigned_workstation_id` 鈫?`assigned_operation_client_id`. 膼峄?kh么ng ph岷 s峄璦 l岷 to脿n b峄?ch峄?g峄峣, `WeighingJob.php` 膽瓢峄 g岷痭 accessor/mutator 谩nh x岷?2 chi峄乽 (`getAssignedWorkstationIdAttribute`/`setAssignedWorkstationIdAttribute`). 脕nh x岷?n脿y **ch峄?ch岷 khi 膽峄峜/ghi qua object model** (`$job->assigned_workstation_id`, `WeighingJob::create([...])`, `fill()`) 鈥?Eloquent 谩p d峄g mutator cho c谩c 膽瓢峄漬g n脿y. N贸 **kh么ng ch岷** khi t锚n c峄檛 膽瓢峄 truy峄乶 d瓢峄沬 d岷g chu峄梚 v脿o query builder (`where()`, `whereNotNull()`, `pluck()`) 鈥?nh峄痭g l峄噉h n脿y build SQL th岷硁g t峄?chu峄梚, b峄?qua to脿n b峄?l峄沺 accessor/mutator c峄 model.
- **`ScannerController::handleOrderScan`** (lu峄搉g qu茅t m茫 v岷h 峄?Tr岷 c芒n) c贸 膽煤ng 4 ch峄?d霉ng t锚n c峄檛 c农 trong query builder 鈥?膽芒y l脿 ngu峄搉 g芒y crash: d貌ng t矛m "m谩y kh谩c 膽ang c芒n 膽啤n n脿y" (`whereNotNull`/`where`/`pluck`) v脿 d貌ng t矛m job c贸 th峄?t谩i s峄?d峄g c峄 ch铆nh tr岷 (`where('assigned_workstation_id', $workstation->id)`). C岷?4 d貌ng n脿y ch瓢a t峄玭g ho岷 膽峄檔g k峄?t峄?khi migration 膽峄昳 t锚n c峄檛 ch岷 鈥?ngh末a l脿 **lu峄搉g qu茅t 膽啤n 峄?Tr岷 c芒n 膽茫 crash 100% m峄峣 l岷** tr锚n b岷 k峄?DB n脿o 膽茫 ch岷 migration 膽贸.
- **S峄璦:** 膽峄昳 4 ch峄?trong `ScannerController.php` sang 膽煤ng t锚n c峄檛 th岷璽 `assigned_operation_client_id`. C谩c ch峄?kh谩c trong c霉ng file (`WeighingJob::create([...])`, `$job->assigned_workstation_id = ...`) gi峄?nguy锚n t锚n c农 v矛 膽贸 l脿 膽瓢峄漬g 膽i qua model, mutator x峄?l媒 膽煤ng 鈥?膽峄昳 nh峄痭g ch峄?膽贸 s岷?kh么ng sai nh瓢ng kh么ng c岷 thi岷縯.
- **R脿 th锚m v脿 s峄璦 c霉ng l峄梚 trong test** (kh么ng ch峄?d峄玭g 峄?code ch岷 th岷璽): `WeighBatchTest.php` c贸 3 ch峄?`->where('assigned_workstation_id', ...)` c霉ng l峄沺 bug 鈥?n岷縰 ch岷 膽瓢峄 (m么i tr瓢峄漬g hi峄噉 kh么ng c贸 Postgres test DB) s岷?t峄?v峄?ngay ch峄?kh么ng ph岷 gi岷?l峄梚 th岷璽. S峄璦 c岷?3 sang `assigned_operation_client_id`. `SmallScaleTwoStationIsolationTest.php` (膽峄峜 `$jobA->assigned_workstation_id`) v脿 c谩c ch峄?g谩n/t岷 trong `WeighBatchTest.php` 膽峄乽 膽i qua model n锚n kh么ng 膽峄g.
- **Ki峄僲 ch峄﹏g:** `php -l` s岷h c岷?`ScannerController.php` l岷玭 `WeighBatchTest.php`. Backend (port 8500) 膽ang ch岷 n峄乶 t峄?tr瓢峄沜 kh么ng c岷 kh峄焛 膽峄檔g l岷 鈥?`php artisan serve` 膽峄峜 l岷 file m峄梚 request; g峄峣 th峄?1 endpoint kh谩c x谩c nh岷璶 ti岷縩 tr矛nh v岷玭 s峄憂g (401 膽煤ng ngh末a thi岷縰 auth, kh么ng ph岷 500). **Ch瓢a qu茅t th峄?m峄檛 膽啤n th岷璽 qua UI** 膽峄?x谩c nh岷璶 h岷縯 l峄梚 鈥?c岷 ng瓢峄漣 d霉ng t峄?th峄?l岷 t岷 `/weighing-station-v2`. 膼峄昳 tr锚n localhost xong; **CS-SERVER (10.0.60.209) c岷 deploy code n脿y ri锚ng** (git pull + restart backend) m峄沬 h岷縯 l峄梚 峄?膽贸 鈥?ch瓢a deploy trong phi锚n n脿y.

### 64. T膬ng t峄慶 SAVE (tem in ra l芒u) v脿 qu茅t 膽啤n 峄?/weighing-station-v2 鈥?c岷痶 s峄?v貌ng 膽i-v峄?DB

- **Tri峄噓 ch峄﹏g ng瓢峄漣 d霉ng:** "ph岷 save ch瓢a 峄昻, tem in load ra l芒u qu谩" v脿 "ph岷 qu茅t l煤c 膽岷 膽岷﹜ ra c农ng b峄?l芒u qu谩".
- **膼O TH岷琓 tr锚n DB production (ch峄?SELECT, kh么ng ghi)** thay v矛 膽o谩n 鈥?script 膽岷縨 query + th峄漣 gian qua `DB::listen`, ch岷 tr锚n 1 job c贸 s岷祅 4 d貌ng:
  - 膼峄峜 quan h峄?trong v貌ng l岷穚 nh瓢 code hi峄噉 t岷: **17 query, 607 ms**
  - C霉ng vi峄嘽 膽贸 nh瓢ng eager load: **8 query, 243 ms**
  - 鈬?**~36 ms/query**. 膼芒y m峄沬 l脿 ph谩t hi峄噉 quan tr峄峮g: DB n岷眒 峄?m谩y kh谩c (`10.0.60.209`), n锚n **T峄擭G S峄?query m峄沬 l脿 th峄?quy岷縯 膽峄媙h th峄漣 gian ph岷 h峄搃**, kh么ng ph岷 膽峄?n岷穘g t峄玭g query. M峄峣 t峄慽 瓢u d瓢峄沬 膽芒y 膽峄乽 nh岷痬 膽煤ng v脿o vi峄嘽 gi岷 s峄?v貌ng 膽i-v峄?
  - Ghi ch煤 m么i tr瓢峄漬g: `DbHostResolver` TCP-probe v峄沬 timeout 0.5s r峄搃 cache 20s ra file temp; l岷 probe tr瓢峄 s岷?r啤i v峄?`candidates[0]` = `127.0.0.1` (kh么ng c贸 Postgres c峄 b峄? v脿 m峄峣 script sau 膽贸 fail t峄沬 khi h岷縯 cache. Xo谩 `%TEMP%\df_pgsql_active_host.json` 膽峄?bu峄檆 probe l岷.
- **Lu峄搉g SAVE 鈥?tr瓢峄沜:** `weighBatch` g峄峣 `WeighingItemRecorder::record()` trong v貌ng l岷穚, m峄梚 d貌ng ~8 query (3 lazy-load quan h峄?`$item->job`/`$job->batch`/`$batch->machine` + insert measurement + save item + count d貌ng ch瓢a xong + `$job->save` + t矛m next_item). 9 d貌ng 鈮?**89 query**, r峄搃 frontend c貌n g峄峣 ti岷縫 `POST /print-slip` (~7 query + nguy锚n m峄檛 v貌ng HTTP) m峄沬 c贸 n峄檌 dung tem. T峄昻g 鈮?**96 query 鈮?3,4 gi芒y** (瓢峄沜 t铆nh theo 36ms/query 膽茫 膽o).
- **Lu峄搉g SAVE 鈥?sau (鈮?4 query, 1 request):**
  1. Th锚m `WeighingItemRecorder::recordMany()`: 膽峄峜 quan h峄?1 l岷 tr瓢峄沜 v貌ng l岷穚, g峄檖 9 b岷 ghi `scale_measurements` th脿nh **1 INSERT**, cascade tr岷g th谩i job/l么 ch岷 **膽煤ng 1 l岷** sau c霉ng. C貌n l岷 膽煤ng 1 UPDATE/d貌ng 鈥?膽贸 l脿 ghi th岷璽, kh么ng c岷痶 膽瓢峄. T谩ch ph岷 cascade ra `cascadeJobAndBatch()` 膽峄?lu峄搉g ghi t峄玭g d貌ng (`record`) v脿 ghi c岷?m岷?d霉ng chung m峄檛 quy t岷痗.
  2. `weighBatch` eager load `batch.machine` + `items.material`.
  3. **D峄眓g phi岷縰 c芒n ngay trong response `weigh-batch`** (`buildAndStoreSlip()` t谩ch ra t峄?`printSlip`, t谩i d霉ng batch/items 膽茫 n岷) 鈥?b峄?h岷硁 request `/print-slip` th峄?hai. Frontend 膽峄峜 `data.slip.label_payload`; n岷縰 kh么ng c贸 th矛 v岷玭 quay v峄?膽瓢峄漬g c农 g峄峣 `/print-slip` (kh么ng ph谩 lu峄搉g n脿o kh谩c).
  - `insert()` g峄檖 b峄?qua hook `creating` c峄 model n锚n **t峄?sinh UUID**; 膽茫 膽峄慽 chi岷縰 migration: 4 c峄檛 NOT NULL (`id`, `legacy_source`, `legacy_id`, `material_type`) 膽峄乽 膽瓢峄 膽i峄乶, `imported_at` c贸 `useCurrent()`.
- **BUG T峄?G脗Y RA R峄扞 T峄?B岷甌 膼漂峄 (膽谩ng ghi l岷):** b岷 膽岷 膽岷穞 t锚n kho谩 m峄沬 l脿 `workstation_code`. Nh瓢ng `assertScaleDeviceBound()` trong C脵NG h脿m c农ng 膽峄峜 膽煤ng kho谩 膽贸 膽峄?quy岷縯 膽峄媙h c贸 b岷痶 bu峄檆 ki峄僲 tra thi岷縯 b峄?c芒n hay kh么ng 鈥?m脿n h矛nh V2 g峄璱 `scale_device_id: 'MOCK_SCALE'` khi tr岷 ch瓢a g谩n c芒n, n锚n th锚m kho谩 膽贸 v脿o payload s岷?**b岷璽 h脿ng r脿o l锚n v脿 l脿m SAVE tr岷?400**. 膼峄昳 th脿nh `slip_workstation_code`. 膼芒y l脿 l媒 do ph岷 膽峄峜 h岷縯 h脿m tr瓢峄沜 khi th锚m tham s峄? kh么ng ch峄?膽峄峜 ch峄?m矛nh s峄璦.
- **Lu峄搉g qu茅t:** `handleOrderScan()` nh岷璶 th锚m 膽瓢峄 c岷?`ProductionBatch` 膽茫 n岷 s岷祅 (kh么ng ch峄?UUID) 鈥?`scanRawDyeQr` v峄玜 tra/t岷 ch铆nh b岷 ghi 膽贸 xong nh瓢ng v岷玭 `findOrFail` l岷 k猫m 2 quan h峄?eager. Truy峄乶 th岷硁g object b峄?膽瓢峄 ~3 query. Nh谩nh ad-hoc (ph峄?bi岷縩 nh岷, qu茅t QR tem) 膽茫 膽瓢峄 g峄檖 insert t峄?tr瓢峄沜 n锚n kh么ng 膽峄g th锚m.
- **Ph谩t hi峄噉 k猫m theo, CH漂A x峄?l媒:** `.env` 膽峄?`QUEUE_CONNECTION=database` + `CACHE_STORE=database` + `SESSION_DRIVER=database`. Ngh末a l脿 m峄梚 `RealtimeService::publish()` t峄憂 2 query (1 `realtime_events` + 1 `jobs`), v脿 lu峄搉g qu茅t g峄峣 publish 2 l岷 (`weighing_job.received` + `weighing_job.started` 鈥?c霉ng th峄漣 膽i峄僲, c霉ng payload, nghi l脿 tr霉ng l岷穚). Kh么ng g峄檖/b峄?v矛 c贸 th峄?c贸 consumer 膽ang nghe; c岷 x谩c nh岷璶 nghi峄噋 v峄?tr瓢峄沜. 膼谩ng l瓢u 媒 h啤n: cache DB ngh末a l脿 endpoint `/api/devices/readings/{id}` (frontend poll 200ms) c农ng 膽i 1 query xu峄憂g DB qua LAN m峄梚 l岷.
- **Ki峄僲 ch峄﹏g:** `php -l` s岷h 3 file backend; `vue-tsc` **26 l峄梚 = 膽煤ng b岷眓g baseline nh谩nh hi峄噉 t岷**, kh么ng l峄梚 n脿o thu峄檆 file v峄玜 s峄璦. **CH漂A ch岷 膽瓢峄 test v脿 CH漂A b岷 SAVE th峄?* 鈥?kh么ng c贸 Postgres test c峄 b峄? v脿 `.env` tr峄?th岷硁g DB s岷 xu岷 n锚n kh么ng ch岷 th峄?膽瓢峄漬g GHI (k峄?c岷?ki峄僽 ghi-r峄搃-rollback) m脿 ch瓢a h峄廼. Con s峄?"鈮?6 鈫?鈮?4 query" l脿 **瓢峄沜 t铆nh 膽岷縨 t末nh t峄?code** nh芒n v峄沬 36ms/query 膽o 膽瓢峄, KH脭NG ph岷 s峄?膽o end-to-end. C岷 ng瓢峄漣 d霉ng b岷 SAVE m峄檛 m岷?th岷璽 膽峄?x谩c nh岷璶.

### 65. "Kh么ng th峄?m峄?l峄噉h s岷 xu岷 n脿y" khi qu茅t 鈥?KH脭NG ph岷 l峄梚 code qu茅t, m脿 l脿 `DbHostResolver` t峄?kho谩 h峄?th峄憂g v脿o host DB sai su峄憈 20 gi芒y

- **Tri峄噓 ch峄﹏g:** qu茅t m茫 QR test 峄?`/weighing-station-v2` b谩o "Kh么ng th峄?m峄?l峄噉h s岷 xu岷 n脿y." 膼贸 ch峄?l脿 **th么ng b谩o m岷穋 膽峄媙h c峄 frontend** khi response l峄梚 kh么ng k猫m `message` (`WeighingStationV2.vue:437`) 鈥?kh么ng n贸i l锚n nguy锚n nh芒n, ph岷 膽峄峜 log server.
- **Log th岷璽:** `SQLSTATE[08006] connection to server at "127.0.0.1", port 5433 failed: Connection refused` 鈥?backend c峄?n峄慽 DB 峄?**127.0.0.1**, trong khi DB th岷璽 峄?`10.0.60.209`. L峄梚 n茅m ra ngay t岷 t岷g Sanctum tra `personal_access_tokens`, t峄ヽ **m峄峣 endpoint 膽峄乽 ch岷縯**, kh么ng ri锚ng g矛 qu茅t.
- **Lo岷 tr峄?nguy锚n nh芒n m岷g b岷眓g 膽o, kh么ng 膽o谩n:** `fsockopen` t峄沬 `10.0.60.209:5433` **5/5 l岷 膽峄乽 n峄慽 膽瓢峄, nhanh nh岷 10ms** 峄?c岷?2 m峄ヽ timeout 0.5s v脿 2s. M岷g ho脿n to脿n 峄昻 膽峄媙h. (L瓢u 媒: `Test-NetConnection` b谩o 4,7-6,7 gi芒y l脿 do n贸 ping + ph芒n gi岷 DNS tr瓢峄沜, KH脭NG ph岷 谩nh chi ph铆 TCP handshake m脿 resolver th峄眂 s峄?ch峄媢 鈥?膽峄玭g d霉ng s峄?膽贸 膽峄?k岷縯 lu岷璶.)
- **NGUY脢N NH脗N G峄怌 鈥?3 khi岷縨 khuy岷縯 trong `DbHostResolver::resolve()` c峄檔g l岷:**
  1. **K岷縯 qu岷?fallback C浓NG b峄?ghi cache.** `$resolved = $candidates[0]` 膽瓢峄 kh峄焛 t岷 tr瓢峄沜 v貌ng probe, v脿 `writeCache()` g峄峣 v么 膽i峄乽 ki峄噉 sau v貌ng l岷穚. Ch峄?c岷 **m峄檛** l岷 probe tr瓢峄 l脿 host sai b峄?kho谩 v脿o cache **20 gi芒y**, m峄峣 request trong 20 gi芒y 膽贸 tr岷?500 d霉 DB v岷玭 ch岷 b矛nh th瓢峄漬g. 膼芒y l脿 khi岷縨 khuy岷縯 nghi锚m tr峄峮g nh岷: bi岷縩 m峄檛 tr峄 tr岷穋 tho谩ng qua th脿nh s峄?c峄?k茅o d脿i.
  2. **Fallback l脿 `$candidates[0]`**, m脿 `.env` 膽峄?`DB_HOST_CANDIDATES=127.0.0.1,10.0.60.209,...` 鈥?ph岷 t峄?膽岷 l脿 `127.0.0.1`, n啤i **ch岷痗 ch岷痭 kh么ng c贸 DB** 峄?m谩y n脿y. Fallback 膽谩ng ra ph岷 l脿 `DB_HOST` (膽峄媋 ch峄?c岷 h矛nh ch峄?膽铆ch).
  3. **Timeout probe 0.5s** qu谩 s谩t so v峄沬 th峄眂 t岷?v岷璶 h脿nh.
- **S峄璦:** ch峄?`writeCache()` **b锚n trong** nh谩nh n峄慽 膽瓢峄 (k猫m `return` ngay); kh么ng probe 膽瓢峄 c谩i n脿o th矛 tr岷?`DB_HOST` v脿 **kh么ng ghi cache** 膽峄?l岷 sau probe l岷 ngay; n峄沬 timeout 0.5s 鈫?2s (kh么ng l脿m ch岷璵 膽瓢峄漬g b矛nh th瓢峄漬g v矛 probe th脿nh c么ng v岷玭 tr岷?v峄?sau ~10ms).
- **Ki峄僲 ch峄﹏g (script ch峄?m峄?膽贸ng socket + file cache t岷, kh么ng truy v岷 DB): 5/5 PASS**, g峄搈 膽煤ng k峄媍h b岷 膽茫 g芒y l峄梚:
  1. Danh s谩ch th岷璽 trong `.env` (127.0.0.1 膽峄﹏g 膽岷) 鈫?ch峄峮 膽煤ng `10.0.60.209` v脿 ghi cache 膽煤ng host s峄憂g.
  2. **T岷 c岷?candidate 膽峄乽 ch岷縯** 鈫?fallback v峄?`DB_HOST` ch峄?kh么ng ph岷 `candidates[0]`, v脿 **kh么ng ghi cache** (tr瓢峄沜 b岷 v谩: ghi 鈫?k岷箃 20s).
  3. Ch峄?1 candidate 鈫?tr岷?th岷硁g, kh么ng probe.
- **Ph谩t hi峄噉 k猫m:** c岷?3 ti岷縩 tr矛nh (backend 8500, vite 3001, reverb 8080) 膽茫 t岷痶 h岷硁 t峄?l煤c n脿o kh么ng r玫 鈥?膽贸 l脿 l媒 do l岷 smoke test 膽岷 tr岷?"Unable to connect". 膼茫 kh峄焛 膽峄檔g l岷 c岷?3; smoke test: `/` 鈫?**200**, `/api/production-batches` 鈫?**401** (膽煤ng, app boot s岷h + middleware auth ch岷), frontend 鈫?**200**, v脿 `DB OK: production_web @ 10.0.60.209:5433`.
- **M茫 QR test 膽茫 sinh** cho ng瓢峄漣 d霉ng th峄?(th瓢 vi峄噉 `qrcode` c贸 s岷祅 trong frontend, kh么ng g峄峣 d峄媍h v峄?ngo脿i 鈥?ADR-003): `#TESTQR-TC001-VD10-220-R1-DYE001-12.50-...` 9 d貌ng rack. M脿u/m茫 kh么ng kh峄沺 l么 n脿o c贸 s岷祅 n锚n 膽i nh谩nh ADHOC (t峄?t岷 l么 m峄沬, c芒n t峄?do) 鈥?kh么ng 膽峄g l么 s岷 xu岷 th岷璽. **L瓢u 媒: qu茅t/SAVE b岷眓g m茫 n脿y GHI TH岷琓 v脿o `production_web`**, d峄?li峄噓 test nh岷璶 di峄噉 qua ti峄乶 t峄?`ADHOC-` v脿 m茫 `TESTQR`.

### 66. N煤t c峄?chai th岷璽 c峄 t峄慶 膽峄?KH脭NG ph岷 s峄?query m脿 l脿 CHI PH脥 M峄?K岷綯 N峄怚 DB 鈥?b岷璽 k岷縯 n峄慽 b峄乶, ti岷縯 ki峄噈 ~155ms m峄梚 request

- **Y锚u c岷:** "khi qu茅t, 膽岷﹜ ra nhanh h啤n n峄痑, si锚u nhanh".
- **膼脥NH CH脥NH s峄?li峄噓 m峄 64:** con s峄?"~36ms/query" ghi 峄?m峄 tr瓢峄沜 l脿 **膽o g峄檖 c岷?chi ph铆 m峄?k岷縯 n峄慽**, kh么ng ph岷 chi ph铆 thu岷 c峄 m峄梚 query. T谩ch b岷h b岷眓g ph茅p 膽o ri锚ng (ch峄?`select 1`, kh么ng ghi):
  - **M峄?k岷縯 n峄慽 l岷 膽岷: ~212ms** 鈥?ch峄媢 M峄楾 L岷 cho m峄梚 request c贸 ch岷 DB
  - **Round-trip m峄梚 query sau 膽贸: ~33ms**
  - Ping t峄沬 `10.0.60.209`: trung b矛nh **12,8ms** (min 8, max 23) 鈬?33ms/query l脿 h峄 l媒 v峄沬 膽瓢峄漬g m岷g n脿y, kh么ng ph岷 DB ch岷璵
  - Bootstrap Laravel + middleware (endpoint 401, kh么ng ch岷 DB): **19ms** 鈥?ho脿n to脿n kh么ng ph岷 v岷 膽峄?
  - K岷縯 lu岷璶: v峄沬 lu峄搉g qu茅t ~22 query th矛 ri锚ng ph岷 **m峄?k岷縯 n峄慽 chi岷縨 212ms**, t峄ヽ g岷 1/4 t峄昻g th峄漣 gian, m脿 tr瓢峄沜 gi峄?kh么ng ai 膽峄?媒 v矛 n贸 kh么ng hi峄噉 ra d瓢峄沬 d岷g "query ch岷璵" trong b岷 k峄?log n脿o.
- **S峄璦 1 鈥?k岷縯 n峄慽 b峄乶 (`PDO::ATTR_PERSISTENT`)** trong `config/database.php`. 膼o 膽峄慽 ch峄﹏g tr锚n ch铆nh 膽瓢峄漬g m岷g n脿y: m峄?m峄沬 ~212ms vs t谩i d霉ng ~57ms 鈬?**ti岷縯 ki峄噈 ~155ms cho M峄孖 request c贸 ch岷 DB**, kh么ng ri锚ng g矛 tr岷 c芒n. X谩c minh Laravel th岷璽 s峄?d霉ng k岷縯 n峄慽 b峄乶 (kh么ng ch峄?PDO tr岷): trong c霉ng ti岷縩 tr矛nh, `DB::disconnect()` r峄搃 truy v岷 l岷 ch峄?t峄憂 **89ms thay v矛 324ms**, v脿 `transactionLevel()` = 0 (kh么ng k岷?th峄玜 transaction d峄?c峄 l岷 tr瓢峄沜).
  - **膼谩nh 膽峄昳 膽茫 c芒n nh岷痗 v脿 ghi r玫 trong code:** request ch岷縯 gi峄痑 transaction v矛 l峄梚 nghi锚m tr峄峮g (OOM/timeout) th矛 PDO KH脭NG t峄?rollback tr锚n k岷縯 n峄慽 b峄乶. Ch岷 nh岷璶 膽瓢峄 v矛 m峄峣 膽瓢峄漬g ghi 膽峄乽 b峄峜 `DB::transaction()`. C贸 c峄?`DB_PERSISTENT=false` trong `.env` 膽峄?t岷痶 ngay m脿 kh么ng ph岷 s峄璦 code.
- **S峄璦 2 鈥?b峄?query tr霉ng 峄?膽岷 lu峄搉g qu茅t:** `scanRawDyeQr` validate `exists:operation_clients,code` (1 query ch峄?膽峄?ki峄僲 tra t峄搉 t岷) r峄搃 NGAY d貌ng d瓢峄沬 truy v岷 ch铆nh b岷g 膽贸 l岷 n峄痑 膽峄?l岷 b岷 ghi. B峄?rule `exists`, gi峄?`firstOrFail()` v峄憂 膽茫 b岷痶 膽煤ng tr瓢峄漬g h峄 m茫 tr岷 sai. **鈭?3ms.**
- **S峄璦 3 鈥?g岷痭 s岷祅 quan h峄?cho l么 v峄玜 t岷:** nh谩nh ADHOC v峄玜 `firstOrCreate` xong c谩i m谩y (膽茫 c岷 object) v脿 l么 m峄沬 th矛 ch岷痗 ch岷痭 ch瓢a c贸 b峄搉, nh瓢ng `handleOrderScan` v岷玭 `loadMissing(['machine','tank'])` truy v岷 l岷 c岷?hai. D霉ng `setRelation()` g岷痭 th岷硁g. **鈭?6ms.**
- **T峄昻g c峄檔g cho m峄檛 l岷 qu茅t 膽啤n m峄沬: ~鈭?54ms** (155 + 33 + 66), c峄檔g th锚m ph岷 膽茫 c岷痶 峄?m峄 64.
- **Ki峄僲 ch峄﹏g:** `php -l` s岷h 2 file. Backend kh峄焛 膽峄檔g l岷 膽峄?n岷 config m峄沬 (`bootstrap/cache` ch峄?c贸 `packages.php`/`services.php`, KH脭NG c贸 `config.php` n锚n kh么ng c岷 `config:clear`); smoke test sau restart: `/api/production-batches` 鈫?**401** 膽煤ng nh瓢 mong 膽峄.
- **CH漂A 膽o 膽瓢峄 end-to-end** th峄漣 gian qu茅t th岷璽 v矛 膽瓢峄漬g qu茅t l脿 膽瓢峄漬g GHI v脿o DB s岷 xu岷 鈥?c岷 ng瓢峄漣 d霉ng qu茅t th峄?v脿 cho bi岷縯 c岷 nh岷璶.

### 67. Qu茅t hi峄噉 b岷g T峄– TH脤 鈥?parse chu峄梚 QR b岷眓g JS ngay t岷 tr矛nh duy峄噒, vi峄嘽 t岷 v貌ng c芒n d瓢峄沬 DB ch岷 n峄乶

- **膼峄?xu岷 c峄 ng瓢峄漣 d霉ng:** "sao kh么ng d霉ng JS 膽峄?膽岷﹜ chu峄梚 QR tr瓢峄沜, sau 膽贸 l瓢u sau c贸 ph岷 nhanh h啤n kh么ng?" 鈥?膽煤ng, v脿 膽芒y l脿 th峄?duy nh岷 th岷璽 s峄?膽瓢a th峄漣 gian ch峄?v峄?~0 thay v矛 ch峄?b峄泃 v脿i tr膬m ms nh瓢 m峄 66.
- **Nh岷璶 膽峄媙h:** chu峄梚 QR 膼脙 ch峄゛ 膽峄?`rack/dye/weight` c峄 c岷?m岷? B岷痶 thao t谩c vi锚n 膽峄﹏g ch峄?g岷 1 gi芒y 膽i-v峄?m岷g ch峄?膽峄?nh矛n 膽煤ng nh峄痭g con s峄?v峄憂 膽茫 n岷眒 s岷祅 trong tay l脿 v么 ngh末a.
- **C谩ch l脿m:**
  1. `frontend/src/utils/qrDyeParser.ts` (m峄沬) 鈥?port `QrPayloadService::parseDyeScan` sang TS. T谩ch th脿nh file ri锚ng thay v矛 vi岷縯 trong `.vue` **ch铆nh l脿 膽峄?ki峄僲 ch峄﹏g 膽瓢峄** (xem d瓢峄沬).
  2. `handleBarcodeScan`: v峄沬 QR th岷璽 (`#...`), parse t岷 ch峄?鈫?`applyOptimisticJob()` v岷?ngay 9 d貌ng v峄沬 dung sai 卤1% t铆nh s岷祅 峄?client (膽煤ng `ScannerController::TOLERANCE_RATIO`, l峄嘽h l脿 m脿u LED l煤c c芒n s岷?kh谩c k岷縯 qu岷?膼岷燭/KH脭NG 膼岷燭 server ch峄憈 l煤c l瓢u). Con tr峄?tr岷?v峄?么 qu茅t ngay, kh么ng 膽峄 h岷縯 request.
  3. Request t岷 job ch岷 n峄乶, gi峄?trong ref `jobReady`. Khi v峄? `adoptRealJob()` thay khung t岷 b岷眓g d峄?li峄噓 server **nh瓢ng GI峄?NGUY脢N** s峄?th峄?膽茫 c芒n trong l煤c ch峄?鈥?c峄?媒 KH脭NG d霉ng `applyActiveJob()` v矛 h脿m 膽贸 xo谩 s岷h `capturedWeights` 膽峄?b岷痶 膽岷 m岷?m峄沬.
  4. `onSave()` `await jobReady` tr瓢峄沜 khi g峄璱 鈥?ph貌ng tr瓢峄漬g h峄 th峄?c芒n xong s峄沵 h啤n server. Th峄眂 t岷?g岷 nh瓢 kh么ng ph岷 ch峄? 膽峄?v岷璽 t瓢 l锚n c芒n l芒u h啤n nhi峄乽 so v峄沬 m峄檛 v貌ng 膽i-v峄?m岷g.
  - Token gi岷?l岷璸 `DF:ORDER:<uuid>` KH脭NG 谩p d峄g (kh么ng mang d峄?li峄噓 d貌ng n脿o, bu峄檆 ph岷 h峄廼 server).
- **Ba c谩i b岷珁 膽茫 x峄?l媒, 膽峄乽 l脿 chuy峄噉 m岷 d峄?li峄噓 ch峄?kh么ng ph岷 th岷﹎ m峄?**
  1. **Request l峄梚 trong khi b岷g 膽茫 hi峄噉** 鈫?ph岷 xo谩 b岷g 膽i. 膼峄?nguy锚n th矛 th峄?c芒n v脿o m峄檛 b岷g KH脭NG BAO GI峄?l瓢u 膽瓢峄.
  2. **`applyActiveJob` g峄峣 `cancelAbandonedJob(activeJob.id)`** v峄沬 khung t岷 `id: null` 鈫?th锚m 膽i峄乽 ki峄噉 `activeJob.value?.id`, kh么ng 膽i h峄 c谩i ch瓢a t峄搉 t岷.
  3. **CLEAR trong l煤c job 膽ang t岷 d峄?* 鈫?kh么ng h峄 ngay 膽瓢峄 (ch瓢a c贸 id). Ph岷 ch峄?`jobReady` xong r峄搃 m峄沬 h峄, n岷縰 kh么ng v貌ng c芒n 膽贸 th脿nh m峄?c么i v脿 **l么 k岷箃 v末nh vi峄卬 kh么ng v峄?膽瓢峄 WEIGHED**. L峄梚 t峄?b岷痶 膽瓢峄 khi vi岷縯: b岷 膽岷 膽峄峜 `activeJob.value?.id` b锚n trong `.then()`, nh瓢ng l煤c 膽贸 CLEAR 膽茫 g谩n `activeJob = null` (膽峄搉g b峄? r峄搃 鈫?s峄璦 cho `jobReady` **tr岷?v峄?ch铆nh job** 膽峄?膽峄峜 id t峄?k岷縯 qu岷?promise.
- **R峄 ro ch铆nh l脿 hai b岷 parse l峄嘽h nhau** (th峄?nh矛n m峄檛 膽岷眓g, DB ghi m峄檛 n岷籵). 膼茫 kho谩 b岷眓g `frontend/scripts/check-qr-parser.mjs`: transpile b岷 TS b岷眓g esbuild, n岷 `QrPayloadService` th岷璽 qua `php -r`, ch岷 **13 ca 膽峄慽 chi岷縰** tr锚n c霉ng 膽岷 v脿o 鈫?**13/13 PASS**, g峄搈 c谩c ca hi峄僲: kh么ng c贸 `#` 膽岷, d岷 ph岷﹜ thay d岷 ch岷, c峄 `-dye-` xen gi峄痑, c岷痶 ph岷 `chem`, thi岷縰 b峄?ba cu峄慽, h啤n 9 b峄?ba (ph岷 c岷痶 c貌n 9), d岷 g岷h l岷穚, chu峄梚 r峄梟g, ch峄?c贸 `#`, `-DyE-` ch峄?th瓢峄漬g l岷玭 hoa. Kh么ng ch岷 DB, kh么ng g峄峣 API.
- **Ki峄僲 ch峄﹏g:** `vue-tsc` **26 l峄梚 = 膽煤ng baseline**, kh么ng l峄梚 n脿o 峄?`WeighingStationV2.vue` hay `qrDyeParser.ts`. **CH漂A qu茅t th峄?th岷璽** 鈥?c岷 ng瓢峄漣 d霉ng x谩c nh岷璶 b岷g hi峄噉 t峄ヽ th矛 v脿 SAVE v岷玭 ghi 膽峄?

### 68. Qu茅t KH脭NG ch岷 m岷g ch煤t n脿o 鈥?c岷?m岷?g贸i v脿o M峄楾 l峄噉h duy nh岷 l煤c SAVE

- **Ng瓢峄漣 d霉ng ch峄憈** (qua c芒u h峄廼 tr峄眂 ti岷縫, 3 ph瓢啤ng 谩n c贸 n锚u r玫 膽谩nh 膽峄昳): *"Kh么ng g峄峣 g矛 鈥?d峄搉 h岷縯 v脿o SAVE"*. M峄 67 v岷玭 c貌n 1 request n峄乶 t岷 v貌ng c芒n l煤c qu茅t; nay b峄?n峄憈.
- **膼芒y ch铆nh l脿 c谩ch VBA g峄慶 l脿m:** `scaleform` ch峄?gi峄?d峄?li峄噓 trong RAM (bi岷縩 `p1..p9`), `btnSave_Click` m峄沬 INSERT xu峄憂g Access. Kh么ng c贸 b瓢峄沜 "m峄?l峄噉h s岷 xu岷" ri锚ng n脿o c岷?
- **Backend 鈥?endpoint m峄沬 `POST /api/scanner/weigh-from-qr`** (`ScannerController::weighFromQr`), l脿m T岷 C岷?trong m峄檛 transaction: m峄?t矛m l么 s岷 xu岷 鈫?t岷 v貌ng c芒n + 9 d貌ng 鈫?ghi s峄?c芒n 鈫?d峄眓g phi岷縰 in, tr岷?`slip.label_payload` lu么n.
  - **T谩i d霉ng `handleOrderScan()` thay v矛 ch茅p l岷 logic**: th锚m tham s峄?`$returnJob` 膽峄?n贸 tr岷?`['job','batch','notice']` thay v矛 `JsonResponse`. Gi峄?nguy锚n to脿n b峄?nghi峄噋 v峄?膽茫 c贸 (nh谩nh c贸 c么ng th峄ヽ vs c芒n t峄?do, kh贸a ch峄憂g 2 m谩y c芒n chung 1 job, cascade tr岷g th谩i l么, RACK auto-fill theo v峄?tr铆). Ch茅p l岷 l脿 ch岷痗 ch岷痭 s岷?tr么i d岷 kh峄廼 nhau theo th峄漣 gian.
  - L峄梚 nghi峄噋 v峄?(kh么ng c贸 c么ng th峄ヽ ACTIVE, qu茅t sai lo岷 tr岷) v岷玭 tr岷?nguy锚n `JsonResponse` c农 鈥?endpoint m峄沬 ch峄?vi峄嘽 `return $scan` khi nh岷璶 v峄?kh么ng ph岷 array.
  - **`rows` kh峄沺 theo `sequence_no`**, kh么ng theo `item_id`: client ch瓢a t峄玭g g峄峣 server n锚n kh么ng bi岷縯 id n脿o. D貌ng client g峄璱 m脿 job kh么ng c贸 (QR nhi峄乽 d貌ng h啤n c么ng th峄ヽ) th矛 b峄?qua 鈥?kh么ng t峄?ch岷?th锚m v岷璽 t瓢 ngo脿i c么ng th峄ヽ.
  - `buildAndStoreSlip` (private) 膽瓢峄 m峄?ra qua `buildSlipForJob()` 膽峄?d霉ng chung.
  - Gi峄?nguy锚n h脿ng r脿o `NOT_STABLE` nh瓢 `weighItem`/`weighBatch` 鈥?client c贸 th峄?g峄峣 th岷硁g API, kh么ng ph峄?thu峄檆 UI.
- **Frontend:** qu茅t QR th岷璽 (`#...`) gi峄?**kh么ng g峄峣 API n脿o**: parse b岷眓g `qrDyeParser.ts` 鈫?`applyLocalJob()` d峄眓g b岷g + gi峄?lu么n chu峄梚 QR 膽峄?g峄璱 l煤c SAVE. `onSave()` g峄峣 `/scanner/weigh-from-qr` v峄沬 `raw_qr` + `rows`. Token `DF:ORDER:<uuid>` v岷玭 膽i 膽瓢峄漬g c农 (`/scan-dye-qr` l煤c qu茅t + `/weigh-batch` l煤c l瓢u) v矛 n贸 kh么ng mang d峄?li峄噓 d貌ng n脿o.
  - G峄?b峄?`jobReady`, `adoptRealJob` (m峄 67) 鈥?kh么ng c貌n job n峄乶 膽峄?ch峄?hay 膽峄?nh岷璶 nu么i.
  - **`onClear` 膽啤n gi岷 h岷硁:** m岷?膽峄峜 t峄?QR ch瓢a ghi g矛 xu峄憂g DB n锚n CLEAR ch峄?l脿 xo谩 m脿n h矛nh, **kh么ng c貌n v貌ng c芒n m峄?c么i 膽峄?d峄峮**. Tr瓢峄沜 膽芒y 膽芒y l脿 ngu峄搉 l峄梚 th岷璽 (l么 k岷箃 v末nh vi峄卬 kh么ng v峄?膽瓢峄 WEIGHED n岷縰 qu锚n h峄).
- **膼谩nh 膽峄昳 膽茫 n锚u r玫 v峄沬 ng瓢峄漣 d霉ng TR漂峄欳 khi l脿m v脿 膽瓢峄 ch岷 thu岷璶:** qu茅t xong m脿 ch瓢a SAVE th矛 d瓢峄沬 DB kh么ng c贸 g矛 c岷?鈬?(a) m岷 c岷h b谩o "膽啤n n脿y c农ng 膽ang 膽瓢峄 c芒n 峄?m谩y kh谩c", (b) tr岷 kh谩c kh么ng th岷 m岷?膽ang c芒n d峄? 膼峄昳 l岷: qu茅t kh么ng t峄憂 v貌ng m岷g n脿o, v脿 kh么ng bao gi峄?c貌n sinh v貌ng c芒n m峄?c么i.
- **Ki峄僲 ch峄﹏g:** `php -l` s岷h 3 file; `php artisan route:list` x谩c nh岷璶 `POST api/scanner/weigh-from-qr` 膽茫 膽膬ng k媒; `vue-tsc` **26 l峄梚 = 膽煤ng baseline**, kh么ng l峄梚 n脿o 峄?file v峄玜 s峄璦. **CH漂A ch岷 th峄?膽瓢峄漬g ghi** 鈥?c岷?`weighFromQr` b峄峜 trong `DB::transaction()` n锚n l峄梚 gi峄痑 ch峄玭g t峄?rollback s岷h, nh瓢ng v岷玭 c岷 ng瓢峄漣 d霉ng qu茅t + SAVE m峄檛 m岷?th岷璽 膽峄?x谩c nh岷璶.

### 69. F5 gi峄痑 ch峄玭g v岷玭 c芒n ti岷縫 膽瓢峄 鈥?kh么i ph峄 m岷?d峄?HO脌N TO脌N t岷 client

- **Y锚u c岷:** "n岷縰 膽茫 qu茅t m脿 ch瓢a CLEAR th矛 khi F5 膽ang c芒n d峄?s岷?c芒n 膽瓢峄 ti岷縫 lu么n, l瓢u v脿o cookie".
- **V矛 sao vi峄嘽 n脿y th脿nh c岷 thi岷縯:** sau m峄 68, qu茅t kh么ng ghi g矛 xu峄憂g DB n峄痑, n锚n 膽瓢峄漬g kh么i ph峄 c农 (`restoreSession` h峄廼 `/api/weighing-jobs/active` r峄搃 膽峄慽 chi岷縰 `jobId`) **kh么ng c貌n d峄?li峄噓 膽峄?h峄廼**. F5 l脿 m岷 tr岷痭g m岷?膽ang c芒n.
- **D霉ng `localStorage` ch峄?kh么ng ph岷 cookie** (膽茫 c贸 s岷祅 `SESSION_KEY = 'df_ws2_session_v1'` t峄?tr瓢峄沜): cookie b峄?膽铆nh k猫m v脿o M峄孖 request g峄璱 l锚n server 鈥?v峄玜 t峄憂 b膬ng th么ng v么 铆ch cho th峄?thu岷 tu媒 ph铆a m谩y tr岷, v峄玜 v瓢峄沶g tr岷 ~4KB m脿 m峄檛 m岷?9 d貌ng k猫m b矛/g峄檖 t峄玭g 么 c贸 th峄?ch岷 t峄沬. localStorage kh么ng g峄璱 膽i 膽芒u v脿 c贸 5-10MB.
- **C谩ch l脿m:** l瓢u th锚m `rawQr` v脿o phi锚n. **Chu峄梚 QR CH脥NH L脌 c岷?m岷?* 鈥?F5 xong ch峄?c岷 parse l岷 chu峄梚 膽贸 l脿 d峄眓g nguy锚n b岷g, kh么ng h峄廼 server c芒u n脿o. T谩ch `buildLocalJob()` ra kh峄廼 `applyLocalJob()` 膽峄?kh么i ph峄 d霉ng l岷 膽瓢峄 m脿 KH脭NG xo谩 m岷 s峄?膽茫 c芒n (`applyLocalJob` c峄?媒 xo谩 s岷h v矛 n贸 d脿nh cho l岷 qu茅t m茫 M峄欼).
- **Gi峄?nguy锚n c啤 ch岷?nh岷璶 l岷 b矛 膽茫 c贸 s岷祅** (`pendingResume`): kh么ng nh岷璶 b矛 c农 ngay m脿 ch峄?s峄?c芒n 峄昻 膽峄媙h 膽岷 ti锚n r峄搃 膼峄怚 CHI岷綰 v峄沬 s峄?g峄檖 膽茫 l瓢u (sai l峄嘽h 鈮?0.5g m峄沬 coi l脿 "膽末a ch瓢a b峄?膽峄g v脿o"). F5 kh么ng 膽峄g v脿o 膽末a, nh瓢ng "ai 膽贸 nh岷 膽末a ra r峄搃 m峄沬 F5" c农ng ch岷硁g 膽峄?l岷 d岷 v岷縯 g矛 kh谩c 鈥?nh岷璶 b峄玜 b矛 c农 trong tr瓢峄漬g h峄 膽贸 s岷?cho ra s峄?c芒n sai m脿 v岷玭 t么 xanh 膼岷燭.
- Nh谩nh kh么i ph峄 theo `jobId` (m岷?m峄?b岷眓g token `DF:ORDER:<uuid>`, c贸 v貌ng c芒n th岷璽 d瓢峄沬 DB) gi峄?nguy锚n, v岷玭 h峄廼 server.
- **D峄峮 t脿n d瓢:** xo谩 膽o岷 ch煤 th铆ch "KH脭NG kh么i ph峄 m岷?膽ang d峄?khi v脿o trang (y锚u c岷 2026-08-01)" 鈥?膽茫 l岷 h岷璾, `restoreSession()` v岷玭 膽瓢峄 `onMounted` g峄峣 su峄憈 v脿 gi峄?ch铆nh l脿 th峄?膽瓢峄 y锚u c岷.
- **Ki峄僲 ch峄﹏g:** th锚m ph茅p ki峄僲 tra **t铆nh thu岷 tu媒 c峄 `parseDyeQr`** v脿o `check-qr-parser.mjs` (parse l岷 c霉ng chu峄梚 lu么n cho c霉ng k岷縯 qu岷? 鈥?膽芒y ch铆nh l脿 t铆nh ch岷 m脿 vi峄嘽 kh么i ph峄 d峄盿 v脿o; n岷縰 h脿m kh么ng thu岷 tu媒 th矛 F5 xong th峄?c贸 th峄?th岷 b岷g kh谩c l煤c qu茅t. **14/14 PASS**. `vue-tsc` 26 l峄梚 = 膽煤ng baseline. **CH漂A th峄?F5 th岷璽** 鈥?c岷 ng瓢峄漣 d霉ng c芒n d峄?v脿i 么 r峄搃 F5 膽峄?x谩c nh岷璶.

### 64. "N岷 膽啤n l芒u qu谩" 峄?`/weighing-station-v2` 鈥?n煤t th岷痶 KH脭NG ph岷 JS m脿 l脿 h脿ng 膽峄 c峄 backend m峄檛 ti岷縩 tr矛nh

- **Ng瓢峄漣 d霉ng h峄廼:** n岷 膽啤n l芒u, c贸 d霉ng JS cho nhanh h啤n 膽瓢峄 kh么ng.
- **膼o tr瓢峄沜, kh么ng 膽o谩n.** Frontend g峄璱 **膽煤ng 1 request** khi qu茅t (`handleBarcodeScan` 鈫?`/api/scanner/scan-dye-qr`), n锚n kh么ng c贸 g矛 膽峄?t峄慽 瓢u 峄?ph铆a JS theo ngh末a "g峄峣 铆t 膽i".
  - 膼峄?tr峄?DB (膽o b岷眓g script ch峄?膽峄峜): **RTT 9.16 ms/query**, ri锚ng **m峄?k岷縯 n峄慽 l岷 膽岷 119.8 ms** (DB n岷眒 峄?m谩y kh谩c 鈥?`10.0.60.209:5433`).
  - Ph岷 膼峄孋 c峄 lu峄搉g qu茅t: **8 query, 62.8 ms** (55.8 ms l脿 DB, 6.9 ms l脿 PHP) 鈥?kh么ng c贸 N+1, kh么ng ph岷 th峄?ph岷.
  - **Ph谩t hi峄噉 quy岷縯 膽峄媙h:** g峄峣 l岷穚 **c霉ng m峄檛 endpoint tr岷?401** (tho谩t 峄?middleware auth, KH脭NG ch岷 DB nghi峄噋 v峄? m脿 k岷縯 qu岷?l脿 `min 19ms / trung v峄?239ms / max 2117ms`, **5/20 l岷 > 300ms**. M峄檛 endpoint kh么ng l脿m g矛 c岷?th矛 kh么ng th峄?t峄?ch岷璵 鈥?ch锚nh l峄嘽h 膽贸 ch峄?c贸 th峄?l脿 **x岷縫 h脿ng**.
- **NGUY脢N NH脗N G峄怌:** backend ch岷 `php artisan serve` 鈫?th峄眂 ch岷 l脿 `php -S` v峄沬 **PHP_CLI_SERVER_WORKERS r峄梟g = M峄楾 worker duy nh岷**, x峄?l媒 tu岷 t峄?t峄玭g request (ki峄僲 ch峄﹏g b岷眓g `Get-CimInstance Win32_Process`, 膽峄峜 膽煤ng d貌ng l峄噉h c峄 PID 膽ang nghe c峄昻g 8500). Trong khi 膽贸 `WeighingStationV2.vue` g峄峣 `startPolling(200)` ngay t峄?`onMounted` 鈥?**5 request/gi芒y li锚n t峄** 膽峄?l岷 s峄?c芒n, ch岷 c岷?khi m脿n h矛nh 膽ang tr峄憂g ch瓢a c贸 膽啤n n脿o. Request qu茅t 膽啤n c峄 th峄?ph岷 x岷縫 h脿ng sau ch铆nh nh峄痭g l瓢峄 poll 膽贸. (Tr锚n Windows kh么ng th峄?b岷璽 nhi峄乽 worker cho `php -S`: c啤 ch岷?膽贸 d峄盿 v脿o `fork()`.)
- **S峄璦 1 鈥?nh峄媝 poll th铆ch 峄﹏g theo tr岷g th谩i** (`WeighingStationV2.vue`): `POLL_MS_WEIGHING = 200` gi峄?nguy锚n khi 膼ANG c贸 膽啤n (200ms l脿 b岷痶 bu峄檆, xem m峄 59: b矛 ch峄憈 t峄?l岷 膽峄峜 峄昻 膽峄媙h 膽岷 ti锚n sau NEXT, nh峄媝 th瓢a th矛 b矛 膬n c岷?ph岷 v岷璽 t瓢 v峄玜 膽峄?, nh瓢ng **`POLL_MS_IDLE = 1000` khi ch瓢a c贸 膽啤n** 鈥?t峄ヽ 膽煤ng l煤c th峄?膽ang qu茅t. Gi岷 80% t岷 n峄乶 v脿o 膽煤ng th峄漣 膽i峄僲 c岷 膽瓢峄漬g th么ng nh岷. Th锚m `watch(() => !!activeJob.value, ...)` 膽峄?膽峄昳 nh峄媝 ngay khi n岷/xo谩 膽啤n; so `!!` 膽峄?kh么ng kh峄焛 膽峄檔g l岷 b峄?膽岷縨 m峄梚 l岷 object job 膽瓢峄 g谩n l岷.
- **S峄璦 2 鈥?ch猫n g峄檖 9 d貌ng c芒n** (`ScannerController.php`, c岷?2 nh谩nh ad-hoc v脿 c贸 Recipe): `WeighingJobItem::create()` trong v貌ng l岷穚 = 9 v貌ng 膽i-v峄?DB 鈮?**82 ms**, thay b岷眓g 1 l岷 `insert()` g峄檖. Ph岷 t峄?sinh UUID v矛 `insert()` 膽i th岷硁g query builder n锚n kh么ng ch岷 hook `creating` c峄 model; an to脿n v峄沬 b岷g n脿y v矛 `$timestamps = false` (kh么ng c贸 `created_at`/`updated_at` 膽峄?膽i峄乶) 鈥?膽茫 膽峄峜 `WeighingJobItem.php` x谩c nh岷璶 tr瓢峄沜 khi 膽峄昳, kh么ng suy 膽o谩n.
- **Ki峄僲 ch峄﹏g:** `php -l` s岷h. `vue-tsc` **26 l峄梚 = 膼脷NG B岷癗G baseline** (膽o b岷眓g `git stash push -u` r峄搃 ch岷 l岷, sau 膽贸 `git stash pop` kh么i ph峄 膽峄?4 file) 鈥?kh么ng ph谩t sinh l峄梚 m峄沬. 膼o l岷 c霉ng endpoint 401 sau khi s峄璦: **trung v峄?239ms 鈫?19ms**, s峄?l岷 > 300ms **5/20 鈫?1/20**.
- **GI峄欼 H岷燦 C峄 PH脡P 膼O SAU KHI S峄珹 鈥?kh么ng 膽瓢峄 膽峄峜 l脿 "膽茫 xong":** kh么ng ki峄僲 so谩t 膽瓢峄 bi岷縩 s峄?quan tr峄峮g nh岷 l脿 tr矛nh duy峄噒 ng瓢峄漣 d霉ng c贸 膽ang m峄?`/weighing-station-v2` hay kh么ng t岷 th峄漣 膽i峄僲 膽o, n锚n ph岷 c岷 thi峄噉 do s峄璦 v脿 ph岷 do trang t矛nh c峄?膽贸ng/膽ang reload **ch瓢a t谩ch b岷h 膽瓢峄**. `max` v岷玭 c貌n **2657 ms** 峄?m峄檛 l瓢峄 鈥?ngh岷絥 ch瓢a bi岷縩 m岷 h岷硁, ch峄?th瓢a 膽i. **Ch瓢a b岷 qu茅t m峄檛 膽啤n th岷璽 qua UI 膽峄?膽o th峄漣 gian th峄?th峄眂 s峄?ph岷 ch峄?**
- **Ch瓢a l脿m 鈥?c谩ch ch峄痑 t岷璶 g峄慶:** m峄檛 ti岷縩 tr矛nh PHP ph峄 v峄?tu岷 t峄?l脿 tr岷 c峄﹏g; m峄峣 t峄慽 瓢u query ch峄?l脿 b脿o m峄弉g ph岷 ng峄峮. Mu峄憂 h岷縯 h岷硁 ph岷 cho backend ch岷 膽a ti岷縩 tr矛nh (Apache/Nginx s岷祅 c贸 trong Laragon, ho岷穋 php-cgi nhi峄乽 ti岷縩 tr矛nh). 膼芒y l脿 thay 膽峄昳 h岷?t岷g, **岷h h瓢峄焠g c岷?quy tr矛nh ch岷 local l岷玭 scheduled task `DFWeb-Backend` tr锚n CS-SERVER**, n锚n ch瓢a t峄?媒 l脿m 鈥?c岷 ng瓢峄漣 d霉ng quy岷縯.

### 63. RAW khong nhay theo can that 鈥?nguyen nhan: PuTTY KHONG CHAY, khong lien quan code

- **Trieu chung nguoi dung bao:** "RAW -0.02 dang khong nhay theo dung so can o tren may".
- **Chan doan tai cho (chi doc, khong sua gi code):**
  - `D:\scale\putty_log.txt` ton tai, 458 bytes, **sua lan cuoi cach day 51 phut** (08:09:53, luc kiem tra la 09:01:00) 鈥?dung yen dung khoang thoi gian nguoi dung thay so khong doi.
  - `Get-Process putty` -> **KHONG CO tien trinh PuTTY nao dang chay** tren may.
  - Xem raw bytes cuoi file: dong cuoi bi CAT DO, ket thuc bang ky tu `W` khong co CRLF theo sau -> phien PuTTY bi dong/rot dung giua luc dang ghi, khong ai mo lai.
  - Service `DFAgent`: **Running** binh thuong 鈥?Agent khong loi, chi don gian khong co gi moi de doc.
  - COM6 (Prolific PL2303GT, adapter noi can that): **Status OK**, phan cung khong hong.
  - Registry PuTTY saved session ten `can`: cau hinh dung 鈥?SerialLine=COM6, SerialSpeed=9600, LogFileName=D:\scale\putty_log.txt, LogType=2 (ghi toan bo output) 鈥?khop chinh xac voi duong dan Agent dang doc (muc 60). Khong phai loi cau hinh.
  - Khong co Startup shortcut / Scheduled Task nao tu khoi dong lai PuTTY neu no dong/crash 鈥?**day la lo hong van hanh thuc su**, khong phai code.
- **Sua tai cho (khoi phuc trang thai, khong doi code):** `putty -load "can"` de mo lai dung phien da luu. Xac nhan file lai duoc ghi tiep (458 -> 3805 bytes trong ~9 giay, so cai dat +0.07/+0.06 dung nhu dat vat gi do len can). Goi thang `DeviceController::getReading` (chi doc) xac nhan toan chuoi song: `weight=0.06, has_reading=true, age_ms=597` 鈥?duoi 1 giay, dung nhip day 200ms cua Agent.
- **KET LUAN: day khong phai loi code.** Banner "MAT TIN HIEU CAN" (muc 58) da bao dung su that ca 2 lan lien tiep trong phien nay 鈥?lan truoc la loi khoa cache id-vs-code (muc 62), lan nay la PuTTY thuc su khong chay. Ca hai deu la vi du dung viec canh bao "khong con hien 0.00 im lang" phat huy tac dung dung nhu thiet ke.
- **RUI RO CON TON TAI, CHUA XU LY (can nguoi dung quyet dinh):** khong co co che nao tu dong khoi dong lai PuTTY neu bi dong/crash/may restart. Neu xay ra o tram pilot dang chay that ma khong ai de y, Agent se tiep tuc "chay tot" (service Running) trong khi khong co so can nao thuc su duoc gui, va thao tac vien chi biet duoc qua banner canh bao tren man hinh. De nghi: them mot trong 2 huong 鈥?(1) Startup shortcut/Scheduled Task tu mo lai `putty -load can` moi khi dang nhap Windows hoac dinh ky kiem tra tien trinh con song khong roi tu khoi dong lai; hoac (2) doi huong lau dai la Agent tu mo thang cong COM (Scale:Source=SERIAL, code da co san tu truoc, xem ScaleReader.cs) de khong con phu thuoc PuTTY nua. Chua tu lam vi day la thay doi hanh vi khoi dong may/kien truc doc can, can nguoi dung chon huong.

- **Quyet dinh nguoi dung (2026-08-01):** khong xu ly co che tu khoi dong lai PuTTY luc nay 鈥?chon "Chua xu ly, de sau". Giu nguyen hien trang: banner "MAT TIN HIEU CAN" la lop bao ve duy nhat, nguoi van hanh tu phat hien va mo lai PuTTY bang tay (`putty -load "can"`). Can hoi lai truoc khi trien khai pilot 7 ngay lien tuc (muc tieu Phase 12) neu van chua co giai phap.

### 64. PROCESS khong hien so AM khi bo vat tu ra 鈥?VA PHAT HIEN DA PORT TU FILE VBA SAI

- **Nguoi dung bao:** bam NEXT thi PROCESS ve 0 dung roi, nhung khi bo do ra khoi dia thi khong hien so am.
- **PHAT HIEN LON: hai file VBA KHAC NHAU o dung doan tinh delta.**
  - Ban da port truoc gio lay tu **ban sao DA MO KHOA** trong git (`semiautosmall scale deltastablefinal1_UNLOCKED.xlsm`), vi file that bi khoa VBA project.
  - File CHAY THAT (`4.semiauto-small scale - delta-stable-final_DF026-027.xlsm`) khoa VBA nen `VBComponents` doc khong duoc 鈥?nhung **giai nen .xlsm (la file ZIP) roi quet chuoi ASCII thang trong `xl/vbaProject.bin`** thi doc duoc nguyen van.
  - Doi chieu `AutoFlow_OnWeight`:

    | | File that (DF026-027) | Ban sao da mo khoa (da port nham) |
    |---|---|---|
    | BASE INIT | `If DeltaBaseWeight < 0 Then` | `If DeltaBaseWeight = -1 Then` |
    | CALC | `deltaVal = rawW - DeltaBaseWeight`<br>`If deltaVal < 0 Then deltaVal = 0` | `deltaVal = Abs(rawW - DeltaBaseWeight)` |

    Dung 2 dong CALC cua file that lai la 2 dong **bi comment** trong ban sao. Xac nhan them: chuoi `Abs(rawW` **KHONG TON TAI** trong file that (`Abs(` duy nhat nam trong doan canh vi tri cua so form, khong lien quan).
- **Sua:** bo `Math.abs()`, dung delta CO DAU (`raw - tareBaseline`). Day la **lech co chu y so voi ca hai ban VBA**, theo yeu cau nguoi dung, va tot hon ca hai:
  - `Abs()` la lua chon te nhat: nhac dia ra khoi can cho so ve 0 thi `|0 - bi|` = dung bang bi, mot so DUONG lon 鈥?co the roi trung dai +-1% va an nen XANH "dat" cho o chua he can.
  - Ban goc kep ve 0 thi khong noi doi, nhung giau mat chuyen da tut xuong duoi moc bi.
  - Co dau: tut duoi bi -> so am -> `ratio < 0.99` -> nen vang "chua du". Khong co duong nao de so am an nen xanh.
- **Kiem chung:** `vue-tsc --noEmit` sach, `vite build` thanh cong (20.29s). **Chua thu tren can that.**

**HAI VIEC PHAT HIEN THEM, CHUA SUA, CAN NGUOI DUNG QUYET:**

1. **Bug that trong file VBA goc:** `If DeltaBaseWeight < 0 Then DeltaBaseWeight = rawW` 鈥?dung `< 0` lam co hieu thay vi mot sentinel rieng. Neu can doc AM nhe (thuc te da do duoc `-0.02` o muc 63!) thi sau khi chot bi, `DeltaBaseWeight` van `< 0`, nen lan doc ke tiep **chot lai bi lan nua**, lap vo han -> delta luon ~0, khong bao gio len duoc so that. V2 dung `null` lam sentinel nen khong dinh loi nay. Chua bao nguoi dung day co phai loi da tung gap o xuong khong.

2. **Nhip chot bi lech VBA:** trong VBA, `StableFilter` **KHONG phai cong chan** 鈥?no tra ve `lastGood` (mot GIA TRI) o moi lan goi, nen `AutoFlow_OnWeight` chay 100 lan/giay va dong `If DeltaBaseWeight...Then DeltaBaseWeight = rawW` chot bi **ngay tuc khac** bang gia tri dang hien san (~10ms sau khi bam NEXT). V2 lai coi `is_stable` la cong chan (`if (!stable) return;` dat TRUOC doan chot bi), nen bi phai **cho mot lan doc on dinh MOI**. Neu tho bam NEXT roi do luon, bi bi chot muon va nuot luon phan bot da vao dia -> PROCESS hien thieu. Da bat dau sua roi revert lai de giu thay doi lan nay gon trong dung yeu cau; can nguoi dung xac nhan truoc khi doi vi no thay doi cam giac thao tac.

### 65. CLEAR luon hoi xac nhan + quet lai ma da SAVE = CAN LAI TU DAU

- **Yeu cau nguoi dung:** "khi an clear thi co xac nhan, va khi quet lai ma day thi coi nhu la can moi".
- **CLEAR:** truoc do CHI hoi khi da can duoc it nhat 1 o (`capturedWeights` khong rong). Nay hoi ca khi CHUA can o nao mien la dang co don tren man hinh 鈥?bam nham luc do tuy khong mat so can nhung van mat don vua quet, phai chay di lay phieu quet lai. Man hinh dang trang thi van xoa thang khong hoi (bam CLEAR tren form trong la vo hai). Hai cau thong bao khac nhau tuy truong hop.
- **Quet lai ma:** `ScannerController::handleOrderScan` truoc do tim job theo `production_batch_id + job_type` va tai dung BAT KE trang thai. Sau khi da SAVE, job la COMPLETED nen quet lai se hien nguyen 9 dong so cu, ma `weighBatch` lai bo qua het dong da COMPLETED -> man hinh dung im khong can duoc gi. Nay them `->where('status', '!=', 'COMPLETED')` + `orderByDesc('created_at')`, nen job da xong khong con duoc tai dung va nhanh `if (! $job)` tao VONG CAN MOI.
- **Job cu giu nguyen** 鈥?khong sua, khong xoa (CLAUDE.md muc 3, khong xoa vat ly du lieu giao dich). Hop voi tinh huong that: tho can sai, da luu, muon lam lai; ban ghi sai phai con de doi soat.
- **HE QUA CO CHU Y, CAN LUU Y KHI LAM BAO CAO:** 1 lo gi峄?co the co NHIEU vong can. Trang thai lo quay ve PARTIALLY_WEIGHED trong luc vong moi dang chay roi tro lai WEIGHED khi xong (WeighingItemRecorder tu cascade 鈥?da co san, khong sua). **Bao cao tieu hao phai cong don theo VONG, khong duoc gia dinh 1 lo = 1 lan can** 鈥?neu dang gia dinh vay thi so lieu se sai khi co lo can lai. Chua ra soat cac bao cao hien co, can kiem tra rieng.
- **Kiem chung:** `php artisan test --filter=WeighBatchTest` (SQLite in-memory) **7/7 pass, 44 assertions**, gom test moi `completed_job_is_not_reused_so_rescan_starts_a_new_round` (job COMPLETED khong con duoc coi la tai su dung duoc, VA job cu van con nguyen). `php -l` sach, `vue-tsc --noEmit` sach, `vite build` thanh cong (19.15s).

### 66. Popup bi chan lam SAVE "coi nhu that bai" du DA LUU XONG + them trang Lich su can

**A. Loi SAVE (nguoi dung bao: "Trinh duyet da chan cua so moi... thi lai toi khong luu duoc")**

- **Nguyen nhan:** thu tu thao tac trong `onSave`:
  ```
  await axios.post(...weigh-batch...)   // LUU DA XONG
  await printSlip()                     // window.open() o day BI CHAN
  onClear(true)                         // van chay, xoa sach man hinh
  ```
  Chrome/Edge chi cho `window.open` khi con "user activation" 鈥?tuc ngay trong handler cua cu click, chua qua `await` nao. `printSlip` mo cua so SAU `await axios.post` nen bi chan. Chinh comment trong `printSlip` da ghi "mo cua so NGAY truoc await" nhung `onSave` lai vi pham.
- **Hau qua:** me DA LUU THANH CONG nhung khong in duoc phieu, form van bi `onClear` xoa sach. Bam SAVE lai thi `activeJob` da null -> `rows` rong -> bao "Khong co dong nao de luu". Thao tac vien tuong chua luu duoc gi.
- **XAC NHAN BANG DU LIEU THAT:** endpoint lich su moi cho thay **2 vong can luc 02:10:49 va 02:33:31 cung lo LEP70158/SE5433/VD003** 鈥?dung 2 lan bam SAVE. Ca hai deu da luu thanh cong. (Ca hai `dat=0 khong-dat=3` vi luu khi con dong chua can.)
- **Sua:**
  1. `onSave` mo cua so in NGAY TAI DAU khoi try, truoc moi `await` 鈥?con trong user activation nen khong bi chan. `window.confirm` o tren khong pha chuoi nay vi no dong bo.
  2. `printSlip(preOpened?)` nhan cua so mo san; nut PRINT goi thang tu click nen van tu mo nhu cu.
  3. Neu popup VAN bi chan: me da luu roi thi **tuyet doi khong xoa form am tham** 鈥?hien thong bao "DA LUU XONG me can, nhung trinh duyet chan cua so in. Cho phep popup roi bam PRINT" va giu nguyen man hinh de bam PRINT duoc.
  4. Luu hong thi `printWin?.close()` de khong de lai cua so trang lo lung.

**B. Trang Lich su can (`/weighing-history`)**

- Backend `WeighingJobController::history` + route `GET /api/weighing-jobs/history` (dat TRUOC `/weighing-jobs/{id}`, neu khong "history" bi nuot thanh `{id}`).
- **Moi dong la MOT VONG CAN, khong phai mot lo** 鈥?tu muc 65 mot lo co the can lai nhieu vong; gom theo lo se giau mat cac lan can lai, dung thu can nhin thay nhat khi doi soat.
- Loc: khoang ngay, tim theo mau/ma hang/ma lo/may. Phan trang bat buoc (bang chi tang, khong bao gio tra het mot luot). Bam vao dong de xem chi tiet 9 dong: RACK / DYE CODE / muc tieu / thuc can / **lech co dau** / DAT-KHONG DAT.
- Dem dat/khong-dat tinh o SERVER (`process_status` la thuoc tinh suy dien cua model) de web va bao cao luon dung chung mot dinh nghia.
- O chua can hien `鈥擿 chu KHONG hien `0.00`: 0.00 la mot ket qua can hop le (dia rong), khong duoc lan voi "khong he can".
- **Dung `like` chu khong `ILIKE`** 鈥?ban dau viet ILIKE, ra soat thay ca du an dung `like`, doi lai cho dong nhat va khong khoa cung vao Postgres.
- Menu: "Lich su can" ngay duoi "Tram can (V2)", `adminOnly` giong V2.
- **Kiem chung tren du lieu that (chi doc, goi thang controller):** khong loc -> 3 vong; `from=2026-08-01` -> 2 vong; `q` khong khop -> 0; phan trang/dem dat-khong dat dung.
- `php -l` sach, `vue-tsc --noEmit` sach, `vite build` OK (24.08s), `WeighBatchTest` **7/7 pass (44 assertions)**.

### 67. /weighing-station-v2 luon mo o trang thai TRANG, khong nap lai me cu

- **Yeu cau nguoi dung:** "khi toi quay ve neu da luu roi thi khong duoc nhay lai ma cu... luc nao cung trong trang thai san sang de can dot moi".
- **Nguyen nhan:** `onMounted` goi `restoreActiveJob()` -> `GET /api/weighing-jobs/active` -> tu nap lai don dang do cua tram. Quay ve man hinh la thay nhay lai ma cu.
- **Sua:** bo han `restoreActiveJob` khoi V2. Vao trang la form trang, quet QR moi nap don.
- **Bo han la DUNG cho V2, khong chi vi tien:** gia tri 9 o song trong RAM cua trang toi luc bam SAVE nen reload la MAT HET. Nap lai don cu chi dung duoc cai khung voi toan bo o PROCESS trang 鈥?nhin nhu dang can do nhung so da bay sach. Nguy hiem that su: thao tac vien tuong da can xong roi bam SAVE, luc do moi dong chua can bi chot luon thanh KHONG DAT va **khong can lai duoc nua** (server chan ghi de dong da COMPLETED).
- **Cung dung ban goc:** form VBA mo ra luon trang, khong co khai niem khoi phuc 鈥?quet QR la nap lai toan bo don trong mot nhip. Mat mang/dong trang giua chung thi quet lai ma do.
- **KHONG dung toi `/weighing-station` (V1)** 鈥?man hinh cu van giu `restoreActiveJob` vi no luu TUNG DONG ngay khi can xong, nen khoi phuc o do that su co y nghia (so da nam trong DB). Day la khac biet ban chat giua 2 luong, khong phai bo sot.
- Don luon comment chet nhac toi `restoreActiveJob` trong `onSave`.
- **Kiem chung:** `vue-tsc --noEmit` sach, `vite build` OK (17.82s).

### 68. Quet ma o o COLOR "day ra cham qua" 鈥?DO DUOC nguyen nhan, phan lon la do MOI TRUONG DEV

- **Do that (khong doan)** bang script chi doc: **moi truy van DB mat ~20ms** tu may dev (`SELECT 1` = 26.7ms, cac truy van thuc te 19-21ms). Luong quet nap don chay ~25-30 truy van (tim lo, tim cong thuc, tao job, 9 x firstOrCreate vat tu, 9 insert dong, cap nhat lo, 2 publish realtime, load lai quan he) -> **~600ms chi rieng do tre mang**, cong bootstrap Laravel + HTTP thanh gan 1 giay.
- **NGUYEN NHAN GOC LA CAU HINH MOI TRUONG, KHONG PHAI CODE:** `backend/.env` cua may dev co `DB_HOST=10.0.60.209` 鈥?backend chay o may nguoi dung nhung DB o CS-SERVER. Tren CS-SERVER that, backend (cong 8500) va DB (cong 5433) **nam cung mot may**, moi truy van ~1ms, cung luong do chi ton ~30ms. **Tram pilot se KHONG gap do cham nay.** Da bao nguoi dung.
- **Van sua 2 thu co ich cho ca hai moi truong:**
  1. **Backend 鈥?tra vat tu GOP 1 truy van** thay cho `Material::firstOrCreate` trong vong lap 9 lan: `whereIn` lay ma da co, `array_diff` ra ma thieu, `insert()` gop 1 lan. Giu dung ngu nghia firstOrCreate (khong dung vao ma da ton tai). **Do that: 6 ma tra gop 13.0ms vs tra tung ma 95.5ms 鈥?nhanh hon 7.3 lan.**
     - **Bay da tranh:** `insert()` gop di thang query builder nen KHONG tu dong dau `created_at`/`updated_at` nhu `firstOrCreate`. Cot la nullable nen khong loi, chi am tham de rong -> mat dau vet ma vat tu tu tao luc nao. Da dien tuong minh.
  2. **Frontend 鈥?phan hoi NGAY khi quet:** bip ngay luc nhan ma (khong doi server), o COLOR doi sang "Dang nap don鈥? + nhap nhay xanh, khoa o va chan quet chong. Truoc do man hinh dung im gan 1 giay, thao tac vien tuong may quet khong an va ban lai ma lan nua.
- **KHONG lam:** tach chuoi QR o client de ve form ngay lap tuc nhu VBA (`txt_color_AfterUpdate` khong he goi server). Se nhanh nhat nhung phai chep lai logic tach chuoi sang TS -> 2 ban parser de troi dat khoi nhau, va 9 dong ve tam chua co `item.id` nen bam NEXT/SAVE se hong. Chi nen lam neu do cham con lam phien sau khi da chay tren CS-SERVER.
- **Kiem chung:** `php -l` sach, `vue-tsc --noEmit` sach, `vite build` OK (18.63s), `WeighBatchTest` 7/7 pass.
  - **CHUA CHAY DUOC 2 test phu dung duong code nay** (`QrScanToWeighingE2ETest`) 鈥?hong san vi SQLite thieu bang `operation_clients` (migration dung raw `ALTER TABLE ... RENAME`). Lan `ProductionOrderScanEntryTest > store rejects duplicate...` cung hong san (test cho 422, code tra 409 tu khi doi sang canh bao). Ca 3 deu co san tu dau phien, khong do thay doi lan nay. Bu lai bang script chi doc doi chieu ket qua tra gop vs tra tung ma tren du lieu that: cung ra dung 4 ma da co / 2 ma thieu, khong chen gi.

### 69. Giu me dang do RIENG TUNG MAY 鈥?quet o may nao thi o lai may do toi khi CLEAR/SAVE

- **Yeu cau nguoi dung:** "cai nay se duoc dung tren nhieu may, khi quet 1 don tren may va chua clear thi van se hien thi don do tren may do de can tiep".
- **Khong mau thuan voi muc 67** ("luc nao cung san sang can dot moi"). Quy tac day du: **da SAVE hoac da CLEAR -> mo trang; quet do ma chua CLEAR -> may DO giu de can tiep.** Muc 67 bo `restoreActiveJob` vi no khoi phuc VO DIEU KIEN (ke ca me da bo), va vi khoi phuc luc do KHONG mang theo so da can.
- **Van de phai xu ly cung luc:** 9 o chi song trong RAM toi luc bam SAVE. Khoi phuc moi cai khung ma mat so con NGUY HIEM HON khong khoi phuc 鈥?thao tac vien nhin thay don, tuong da can xong, bam SAVE -> moi dong chua can bi chot KHONG DAT va khong can lai duoc.
- **Cach lam 鈥?2 lop, deu tu nhien theo tung may:**
  1. **Server:** `/api/weighing-jobs/active` von da loc theo `assigned_operation_client_id` va da loai job COMPLETED -> chi tra don cua dung tram dang hoi.
  2. **localStorage `df_ws2_session_v1`** (rieng tung may): luu `{workstationId, jobId, capturedWeights, capturedTare}`. Day la thu DUY NHAT nho duoc so 9 o.
- **Diem chot khi khoi phuc:** `currentIndex = -1`, KHONG tu nhay vao o dang can do. Bi la trang thai VAT LY cua cai dia ngay luc do 鈥?khoi phuc tu localStorage sau reload la bia, vi dia co the da bi nhac ra/them bot hoac can da troi so. Bam NEXT de vao o chua can ke tiep va lay bi moi.
- **`onNext` lan dau gio bo qua ca o da luu o server LAN o vua can xong dang giu o may nay** 鈥?neu khong, bam NEXT sau khi khoi phuc se nhay ve o 1 va ghi de so da can.
- **Cac diem noi:** `applyActiveJob` + `captureCurrentSlot` -> `saveSession()` (ghi ngay tung o, mat dien giua me chi mat dung o dang can do); `onClear` + SAVE thanh cong -> `clearSession()`; `onMounted` -> `restoreSession()` (khong await de so can chay ngay).
- **Cac truong hop da xu ly:** dau vet cua tram khac (may duoc gan lai tram) -> bo, khong nap nham don tram kia; server khong con coi la don dang do (da SAVE noi khac) -> bo dau vet, mo trang; localStorage hong/day -> nuot loi, khong lam hong luong can dang chay.
- **CHUA LAM (khoang trong da biet):** sua tay o RACK truoc khi SAVE khong duoc luu vao phien 鈥?sau reload se quay ve gia tri tu QR. Rack von den tu QR nen sua tay la hiem; chua lam vi phai gop nguoc gia tri vao job moi tai ve, khong dang danh doi do phuc tap luc nay.
- **Kiem chung:** `vue-tsc --noEmit` sach, `vite build` OK (18.23s). **Chua thu tay tren trinh duyet** 鈥?can nguoi dung kiem: quet don -> F5 -> phai thay lai don va cac o da can; bam CLEAR -> F5 -> phai trang; SAVE -> F5 -> phai trang.

### 70. F5 phai CAN TIEP DUOC NGAY 鈥?nho ca vi tri o dang can va bi, co chot an toan doi chieu dia can

- **Nguoi dung chinh lai muc 69:** "tuc la phai ghi nho xem toi can den dau roi chu, khi F5 lai van tiep tuc can tiep binh thuong".
- **Muc 69 da qua than trong:** khoi phuc xong dat `currentIndex = -1`, bat bam NEXT lai. Ly do khi do: bi la trang thai VAT LY cua dia, khoi phuc tu localStorage la "bia". **Lap luan do sai o cho:** F5 khong he dung vao dia 鈥?truong hop binh thuong thi bi cu VAN DUNG.
- **Nhung rui ro that su van con:** "ai do nhac dia ra roi moi F5" khong de lai dau vet gi khac voi "F5 thuan tuy". Nhan bua bi cu trong truong hop do se cho ra so can SAI ma van to xanh DAT.
- **Giai phap 鈥?doi chieu so can GOP:**
  - Phien luu them `currentIndex`, `tareBaseline`, `grossAtSave` (so can gop luc ghi).
  - `grossAtSave` duoc cap nhat moi khi can DUNG o mot gia tri moi (watch tren `isStable`+`grossWeight`): dang do vat tu thi khong ghi (chua on dinh), gia tri khong doi cung khong ghi -> khong dung localStorage moi vong poll.
  - Khoi phuc: **khong nhan bi ngay**, dat `pendingResume` roi cho lan doc on dinh dau tien. Lech <= **0.5g** -> dia y nguyen, noi lai dung o dang can do voi bi cu, can tiep nhu chua he F5. Lech hon -> bao ro *"Dia can da thay doi trong luc tai lai trang (X 鈫?Y). Cac o da can van con nguyen 鈥?bam NEXT de can tiep o ke va lay bi moi."*
  - 0.5g: can doc theo gram, vat tu nhe nhat trong cong thuc cung vai gram, du rong de bo qua troi so/rung nen ma van bat duoc moi thao tac that.
- **Thao tac tay thang viec noi lai con treo:** bam NEXT / chon o khac -> `pendingResume = null`, khong de no nhay vao sau lung nguoi dung.
- **Ghi phien ngay khi chuyen o** (`onNext`, `onSelectRow`): `captureCurrentSlot` con ghi theo o CU, neu F5 roi dung khoang giua do thi phien se sai vi tri dung 1 o.
- **Kiem chung:** `vue-tsc --noEmit` sach, `vite build` OK (18.62s). **Chua thu tay** 鈥?can nguoi dung kiem 3 tinh huong: (1) can do o giua roi F5 -> phai can tiep duoc ngay dung o do, so cu con nguyen; (2) F5 roi NHAC DIA RA -> phai hien canh bao, khong nhan bi cu; (3) CLEAR/SAVE roi F5 -> phai trang.

### 71. Cai Agent len MAY NAO CUNG CHAY - ghep can theo IP may, khong theo ma tram cau hinh tay

- **Yeu cau:** "toi muon may nao cung dung duoc co Agent la duoc".
- **Loi that dang co:** bo cai MSI dong cung `Workstation:Id = WS-WEIGH-SCALE` cho MOI may, nen 2 tram can chay cung luc ghi de len dung mot khoa cache `scale_live_weight_WS-WEIGH-SCALE` -> moi man hinh doc phai so can cua tram kia. Truoc day phai sua tay appsettings.json tren tung may sau khi cai.
- **Cach sua - ghep cap theo IP nguon:** Agent va trinh duyet cua tho chay tren CUNG mot may tram, va ca hai goi thang `http://<server>:8500` KHONG qua proxy nao (da kiem: `vite.config.ts` khong co proxy, `main.ts:17` dat baseURL thang toi cong 8500) -> backend thay chung mot IP, du de ghep ma khong can cau hinh gi.
  - `storeReading` ghi THEM bo khoa `scale_live_weight_machine_<ip>` (khong thay the khoa theo ma tram).
  - `getReading` nhan co `?local=1`; chi cac man hinh can bat co nay. Dashboard KHONG bat vi no xem nhieu tram tu xa cung luc.
- **Da thu cach "lay ban tuoi hon" va NO SAI** - probe bat duoc: moi Agent deu ghi chung mot khoa theo ma tram nen khoa chung gan nhu luon vua duoc may khac cap nhat, tuoi hon khoa theo IP cua chinh may nay -> may A VAN doc phai so cua may B. Sua thanh **may dang ngoi thang tuyet doi**.
- **Moc nhan dien la `read_at` (TTL 1 gio), khong phai `weight` (TTL 15s):** may nao da tung bao so trong 1 gio qua thi coi la may CO CAN. Nho vay khi Agent/PuTTY chet, man hinh bao thang "MAT TIN HIEU CAN" thay vi am tham tut ve hien thi can cua tram khac - can sai ma van to xanh DAT nguy hiem hon han mat so.
- **Sua ca V1** (`WeighingStation.vue`) chu khong chi V2: cung mot loi, neu chi sua V2 thi hai tram chay V1 van de so cua nhau.
- **KHONG phai build lai MSI** - nhan dien chuyen sang tang backend nen bo cai 2.1.0.0 dang co giu nguyen, cai y het nhau len bao nhieu may cung dung.
- **Kiem chung:** probe chay thang controller (ep `cache.default=array`, dung ma tram khong ton tai nen khong co INSERT nao vao production) - **6/6 DAT**: 2 may tach bach dung so cua minh; may khong co Agent lui ve khoa ma tram nhu cu; tram trong bao `has_reading=false`; may co Agent da chet KHONG tut ve so cua may khac. Them 2 test vao `ScaleLiveWeightTest` (**chua chay duoc** - chua co DB test co lap). `vue-tsc` sach, `vite build` OK (19.43s). Pint fail o 2 file nay nhung **da fail san tu HEAD** truoc khi sua, khong chay `pint --fix` de khoi de ra diff dinh dang lon.
- **Con lai:** phai deploy backend + frontend len CS-SERVER moi co tac dung o xuong.

### 72. Nhieu may cung chay - moi may tu dang ky thanh mot tram rieng, khong may nao anh huong may nao

- **Yeu cau:** "toi muon dung o nhieu may, cha may nao anh huong may nao". Nguoi dung chon: **may tu dang ky** (chua dung tram tay) va **cho vao nhung canh bao** khi 2 may quet trung don.
- **Ba cho dung chung, sua ca ba:**
  1. **So can** (muc 71): da ghep theo IP may.
  2. **Ma tram**: bo cai dong cung mot Id cho moi may. Nay `Workstation:Id` de trong -> `Worker.ResolveWorkstationId` sinh `WS-SCALE-<TEN-MAY>`. Cau hinh tuong minh VAN duoc uu tien (may cai tu truoc khong bi doi ma sau khi cap nhat).
  3. **Vong can dung chung**: `handleOrderScan` tra ve CUNG mot WeighingJob cho moi may quet cung don -> 2 may can song song, ai SAVE sau bi bo qua nhung dong may kia da luu (weighBatch bo dong COMPLETED) 鈥?mat so ma khong ai biet.
- **Tu dang ky (khong phai khai tay):**
  - `AgentAuth` da co san nhanh tu tao tram nhung chi nhan **3 ma co dinh**; ma sinh tu ten may khong the co trong danh sach do nen roi ve type `AUTO_REGISTERED` va bi `handleOrderScan` chan 403. Da doi sang **suy loai tram tu truong `role`** Agent gui kem (SCALE_ONLY -> DYE_WEIGHING + caps SMALL_SCALE/WEIGH/PRINT). Ten tram lay `machine_name` that cho de nhan ra may nao la may nao.
  - Endpoint moi `GET /api/workstations/whoami`: "may toi dang ngoi la tram nao?" 鈥?tra theo IP nguon ra tram ma Agent tren chinh may do da dang ky. `storeReading` ghi mapping IP->ma tram (TTL 12h ~ tron mot ca).
  - Frontend `adoptLocalWorkstation()` goi trong `onMounted` cua V2. **KHONG dung** toi tai khoan/kiosk da gan cung tram (`df_workstation_config`) 鈥?do la thu WS-001 dung ra de chan chon nham.
- **Canh bao trung don:** khi tram quet khac `assigned_workstation_id` hien tai, tra them truong `warning` (ten may kia + gio mo + so dong da luu), chuyen quyen sang may vua quet de may thu ba duoc canh bao theo may dang can that. **Khong chan** 鈥?chan cung se ket dung luc can nhat (may kia treo/mat dien thi don khong ai can duoc nua). Hien bang dai canh bao vang tai cho, KHONG dung `alert()` vi alert nuot mat phat ban ma ke tiep cua may quet.
- **Kiem chung:** probe `whoami` **7/7 DAT** (may chua co Agent -> 200 + data=null chu khong 404; ghi dung mapping; tra du `capability_codes`/`id`/`type` frontend can). Agent test **26/26 DAT** (them 8 test cho ResolveWorkstationId: uu tien cau hinh tuong minh, on dinh giua cac lan goi, chi sinh `[A-Z0-9-]`). `vue-tsc` sach, `vite build` OK (17.54s). MSI **2.2.0.0** dung xong, da bung ra kiem dung cau hinh ben trong (`Id` rong, Backend 10.0.60.209).
- **Con lai:** phai deploy backend + frontend len CS-SERVER thi co che nay moi co tac dung o xuong. Cai Agent truoc khi deploy thi may van day so len duoc nhung man hinh can chua nhan dung.

### 73. Doi quyet dinh: moi may MOT VONG CAN RIENG, ca hai deu SAVE duoc day du

- **Nguoi dung doi lai muc 72:** "hai may quet cung don -> chung mot job, ai SAVE thi cung deu save duoc, k anh huong 2 ban cung duoc". Tuc la bo huong "canh bao roi van dung chung job", chuyen han sang **tach vong can theo may**.
- **Sua o `ScannerController::handleOrderScan`:** truy van tim job tai dung them dieu kien `assigned_workstation_id = <tram dang quet>`. Truoc do khong loc theo tram nen hai may nhan ve CUNG mot WeighingJob -> may bam SAVE sau bi bo qua toan bo dong may kia da ghi (weighBatch bo dong COMPLETED), mat so ma khong ai biet.
  - Loc **chat**, khong nhan job co `assigned_workstation_id` rong: nhan job rong thi hai may lai cung vo phai mot job va quay ve dung loi tren. Job cu giu nguyen, khong sua/khong xoa.
- **Bo hoan toan canh bao "dang mo o may khac"** (dung 1 vong doi), thay bang **ghi chu trung tinh** `notice`: "Don nay cung dang duoc can o <ten may>. Me cua ban ghi rieng, hai ben khong anh huong nhau." Mau XANH THONG TIN chu khong vang 鈥?to vang se khien tho tuong phai dung lai xu ly. Van giu de hai tho biet ma tranh can trung mot don.
  - `notice` tinh **truoc** khi tim/tao job vi nhanh can tu do (duong ma QR that di qua) thoat som bang `return` rieng.
- **Cascade trang thai lo van dung san**, da doc lai `WeighingItemRecorder`: no dem theo TAT CA job cua lo, nen job A xong truoc trong khi B con do -> lo la PARTIALLY_WEIGHED, chi WEIGHED khi ca hai xong. Khong phai sua gi.
- **HE QUA CAN LUU Y (chua xu ly):** may quet nham don roi bo di se de lai mot job treo, lo khong bao gio ve duoc WEIGHED -> tram Van chuyen khong chuyen duoc sang IN_TRANSIT (`handleMaterialLabelScan` doi dung `status === 'WEIGHED'`). Truoc day it gap hon vi may thu hai tai dung job cu; nay moi lan quet them la mot job moi. Can co duong huy/tha vong can bo do.
- **Kiem chung:** `WeighBatchTest` **9/9 DAT** tren SQLite in-memory (`DB_CONNECTION=sqlite DB_DATABASE=:memory:`; DB test Postgres 127.0.0.1:5433 van khong chay). Them 2 test: hai may cung lo deu luu du 3 dong voi so KHAC NHAU (100.0 vs 55.0, chung minh khong ai de ai) va lo chi WEIGHED khi ca hai vong xong. Da sua truy van sao chep trong test cu cho khop dieu kien moi. `vue-tsc` sach, `vite build` OK (17.80s).

### 74. Dep vong can bo do (CANCELLED) - khong de lo ket vinh vien khong bao gio ve duoc WEIGHED

- **Xu ly he qua bo ngo o muc 73:** quet nham don roi bo di (chua cau SAVE) de lai mot WeighingJob mo coi -> lo khong bao gio ve duoc WEIGHED vi cascade doi TAT CA job cua lo phai COMPLETED.
- **Endpoint moi `POST /api/weighing-jobs/{id}/cancel`** (`WeighingJobController::cancel`, middleware `workstation.guard:WEIGH_ITEM` giong weigh-batch):
  - Chi huy duoc khi **CHUA co dong nao COMPLETED** 鈥?con dong da can that thi tu choi 409 `JOB_HAS_COMPLETED_ITEMS` (dung `restart()` neu muon bo toan bo ket qua da can that, co audit log).
  - Job da COMPLETED -> 409 `JOB_ALREADY_COMPLETED`. Job da CANCELLED -> 200 idempotent, khong loi.
  - **Khong ghi AuditLog** (khac `restart()`): khong lam mat so c芒n th芒t nao, chi doi y nghia "khong tinh vao vong c芒n nao cua lo nua".
- **Loai CANCELLED khoi 3 truy van** (cung mau voi COMPLETED):
  - `ScannerController::handleOrderScan` 鈥?ca truy van tim job tai dung LAN truy van "may khac cung dang can" (`whereNotIn(['COMPLETED','CANCELLED'])`).
  - `WeighingItemRecorder::record` 鈥?cascade dem `$allJobs` cua lo phai LOAI CANCELLED, neu khong dem no vao thi lo khong bao gio WEIGHED duoc (dung cai loi dang xu ly).
  - `WeighingJobController::activeForWorkstation` 鈥?khong khoi phuc lai mot job da huy khi F5/mo lai trang.
- **Frontend V2 tu goi cancel o 2 cho** (best-effort, nuot loi, khong chan thao tac):
  - `onClear()`: huy vong c芒n dang mo TRUOC khi xoa state, **tru khi vua SAVE xong** (them tham so `alreadySaved` de khoi goi thua 1 request moi lan SAVE thanh cong 鈥?server van tu choi em neu lo goi nhung khong can ton round-trip do).
  - `applyActiveJob()`: quet mot don MOI trong khi don cu chua SAVE (khong qua CLEAR) se huy don cu truoc khi thay the 鈥?day la duong bo do khac ma truoc day chua xu ly.
- **Kiem chung:** `WeighBatchTest` **15/15 DAT** tren SQLite in-memory (them 6 test: huy job trong, tu choi khi co dong COMPLETED, tu choi job da COMPLETED, idempotent, **loai khoi cascade** (job that + job huy cung lo -> lo van WEIGHED), khong tai dung duoc khi quet lai). `vue-tsc` sach, `vite build` OK (18.16s). Test file khac (`ScannerWorkflowTest` etc.) fail do moi truong (thieu bang `operation_clients` tren SQLite, da biet tu truoc, khong lien quan thay doi nay).

### 75. NEXT khong con tat o dong cuoi cua don - chay het 9 dong lu?i dung nhu VBA

- **Yeu cau (02/08/2026):** "toi muon nut next luon mo du la den R3 thi khi an next van co the an next de can hang duoi tiep, mac du k co gi".
- **Nguyen nhan:** `canPressNext` chan theo `jobItems.length` (so vat tu trong don) nen QR 3 dong la NEXT tat ngay o R3. VBA goc chan theo `CurrentBoxIndex < 9` - moc la **9 DONG LUOI**, khong phai so vat tu. Day la cho da port lech tu dau.
- **Sua:**
  - Them hang chung `MAX_RACK_LINES = 9` trong `frontend/src/utils/qrDyeParser.ts` (truoc do so 9 nam rai rac 3 cho: vong lap parse, `NINE_ROWS` trong VbaRackGrid, va gian tiep o canPressNext).
  - `WeighingStationV2.vue`: `canPressNext = currentIndex < MAX_RACK_LINES - 1`; `onNext` tang chi so toi dong 9. Truong hop can xong het vat tu cua don roi moi bam NEXT: xuong **dong trong ke tiep** (`min(jobItems.length, 8)`) chu khong quay ve o 1 - quay ve la ghi de so da can.
  - `VbaRackGrid.vue`: dong trong bay gio **chon duoc** (bo dieu kien `items[idx] &&` o `@click`), va o PROCESS hien so can song/so da chot ke ca khi dong do khong co vat tu - khong the bam NEXT xuong mot o trang tron roi bao la "can duoc".
  - `processStyle` cho dong trong: nen TRANG (dung nhanh "target rong" cua `Mod_UI_processcolor.CheckRange`), khong bao gio an nen xanh.
- **Gioi han da noi ro tren man hinh:** dong khong co vat tu trong QR thi **SAVE khong luu** so can o do - `onSave` duyet theo `jobItems` nen dong trong tu nhien bi loai, va day cung dung VBA (`btnSave_Click` chi INSERT dong co WEIGHT muc tieu). Them canh bao vang ngay duoi luoi khi con tro dang o dong trong, thay vi de thao tac vien can xong roi tuong da ghi.
- **Kiem chung:** `node frontend/scripts/check-qr-parser.mjs` **14/14 PASS** (xac nhan viec rut so 9 ra thanh hang khong doi hanh vi parse, ke ca ca "nhieu hon 9 bo ba phai cat con 9"). `vue-tsc` **26 loi = dung baseline**, khong phat sinh loi moi. **CHUA thu tay tren trinh duyet** - can nguoi dung quet ma 3 dong roi bam NEXT qua R4..R9 de xac nhan.

### 76. NEXT dung duoc ca khi CHUA quet don - man hinh lam duoc viec cua mot cai can thuong

- **Yeu cau (02/08/2026):** "du chua co quet thi nut next van dung binh thuong, biet dau toi can dung tam de can cai gi do".
- **Sua:** bo dieu kien `!activeJob` khoi `:disabled` cua nut NEXT. Bam NEXT tren form trang -> `onNext` di dung nhanh cu (`jobItems` rong -> `findIndex` tra -1 -> ve o 0), chot bi o lan doc on dinh dau tien, o PROCESS hien so can song. Chay duoc het 9 dong.
- **Keo theo mot cho de bo sot: NHIP LAY SO CAN.** `pollIntervalForState()` truoc do bam theo `activeJob` nen can tay khong don se roi vao nhip nhan roi 1000ms - dung cai luong can nhip day nhat lai bi nhip thua nhat, va bi se bi chot vao luc tho da bat dau do vat tu (chinh loi da phan tich o muc 58/59). Doi moc "dang can" thanh `dangCan = activeJob !== null || currentIndex >= 0` cho ca `pollIntervalForState()` lan `watch` khoi dong lai bo dem.
- **Khong dung/khong khoa:** SAVE va PRINT van tat khi chua co don - khong co dong nao de ghi. `saveSession()` van thoat som khi `!activeJob` nen can tay khong de lai dau vet localStorage, F5 la mat, dung nhu ban chat "can tam".
- **Noi ro tren man hinh:** canh bao duoi luoi tach 2 truong hop - chua co don ("dang CAN TAY, khong co gi duoc luu") va co don nhung dung o dong ngoai QR ("SAVE se khong luu so o dong nay"). Dong goi y khi form trang cung noi them "can can tam cai gi do thi bam thang NEXT, khong can don".
- **Don kem:** `v-for="(row, idx)"` -> `(_row, idx)` trong VbaRackGrid, tat TS6133 co san tu truoc o dung dong vua sua.
- **Kiem chung:** `vue-tsc` **25 loi** (baseline 26, giam 1 do don TS6133 noi tren; khong loi nao thuoc 3 file vua sua). `vite build` **OK 22.45s**. **CHUA thu tay tren trinh duyet.**

### 77. /weighing-history: tim kiem + phan trang chuyen han sang JS, va co nut IN LAI phieu can

- **Yeu cau (02/08/2026):** "toi muon dung js 100% va muon load nhanh hon, co nut de in lai".

**a) DO TRUOC KHI SUA** (script chi doc, khong ghi gi xuong DB):
- `history()` cu: **5 cau truy van / 193ms**, payload chi **16KB** cho 7 vong can. Tung cau ~30-35ms - gan nhu toan bo la **di-ve mang toi DB o CS-SERVER**, khong phai cong viec that. Mo ket noi 174ms, bootstrap CLI 2048ms (nguoi khong dai dien, ban chay qua `artisan serve` co opcache).
- Ket luan: du lieu qua nho so voi chi phi vong mang. Moi lan bam Loc / doi trang deu tra dung cai gia do cho ~16KB.

**b) Bo phan trang phia server** (`WeighingJobController::history`)
- Tra TRON mot cua so du lieu (`limit`, mac dinh 200, tran `HISTORY_MAX_ROWS = 500`) thay vi `paginate()`.
- Bo hoan toan cau `count(*)` cua paginator: lay du **dung 1 dong** (`limit($limit + 1)`) de biet co bi cat hay khong. **5 cau -> 4 cau, 193ms -> 135ms.**
- Hinh dang response doi: `data` gio la `{ rounds, truncated, limit }` chu khong con la object paginator. Chi co `WeighingHistory.vue` dung endpoint nay (da grep), khong co test nao - khong pha vo cho khac.
- Giu lai tham so `q` phia server: khong dung cho o tim thuong ngay nua, nhung la duong thoat khi thu can tim nam NGOAI cua so da tai.

**c) Frontend chuyen sang JS hoan toan** (`WeighingHistory.vue`)
- O tim kiem loc ngay tren `allRounds` da tai, **khong goi server**, go toi dau thay toi do, ho tro nhieu tu (phai khop TAT CA).
- Phan trang cung thuan JS (20 dong/trang) - doi trang **0 request**, khong con trang thai cho.
- Chi con dung 1 vong HTTP khi: mo trang, doi khoang ngay, bam Lam moi, hoac bam "Tim tren toan bo lich su".
- **Khong im lang cat bot:** cua so bi cat thi hien bang canh bao vang; nut "Tim tren toan bo lich su" luon hien khi o tim co chu (khong doi toi luc "khong thay gi" - loc ra 2 dong khong co nghia la chi co 2). Ket qua tim toan cuc co bang bao rieng + duong quay lai.
- Chuoi de so khop tinh MOT LAN luc du lieu ve (`ganChuoiTim`), khong tinh trong computed: ghi thuoc tinh vao object dang nam trong ref ngay giua luot doc cua computed se lam Vue tinh lai vong vong.

**d) Nut IN LAI phieu can** (bieu tuong may in tren tung dong)
- Goi lai `POST /api/weighing-jobs/{id}/print-slip` roi render qua `printTsplViaBrowser`. `window.open` goi **dong bo ngay trong handler**, truoc moi `await` - sau await la mat "user activation" va bi chan popup (dung loi da dinh o luong SAVE).
- **KHONG dung phieu bang JS tai cho** du du lieu da co san tren man hinh: CLAUDE.md muc 5 bat buoc moi luot in lai phai de lai Audit Log bat bien. Dung o client thi khong co gi de ma ghi.
- **Vi vay bo sung AuditLog `WEIGH_SLIP_REPRINT`** trong `printSlip()` - truoc day chi co ban ghi PrintJob, khong truy duoc AI bam in lai. Moi luot goi endpoint nay deu la in lai (luong SAVE dung `buildSlipForJob` truc tiep, khong di qua day) nen khong bi ghi trung.
- **Phan giai ma tram:** them nhanh `$job->workstation?->code` truoc nhanh "may dang dung". Man Lich su chay tren may van phong khong gan tram nao, ma phieu in lai phai mang dung ma tram DA CAN ra no. Da kiem chung: **ca 7/7 vong can hien co deu phan giai ra ma tram ton tai trong `operation_clients`** (script chi doc), nen `firstOrFail()` trong `buildAndStoreSlip` khong the no.

- **Kiem chung:** `php -l` sach. Do lai truy van: **4 cau / 135ms**, hinh dang JSON dung cai frontend doc (`rounds/truncated/limit`, item co `process_status`). Endpoint HTTP tra **401** khi chua dang nhap (app boot sach, route dung cho). `vue-tsc` **25 loi = baseline**, khong loi nao o `WeighingHistory.vue`. `vite build` **OK 17.20s**. **CHUA bam thu nut IN LAI tren trinh duyet** - no GHI (PrintJob + AuditLog) nen de nguoi dung tu bam, khong tu chay tren DB that.

### 78. /weighing-station-v2: lam lai giao dien (bo khung Windows 95), so DELTA to tu doi mau theo dung sai

- **Yeu cau (02/08/2026):** "cai tien giao dien cho toi voi, de cho no dep hon".

**Nhung thu KHONG duoc dong** (deu la quyet dinh co ly do da ghi trong code, khong phai mac dinh):
- 3 ma mau o PROCESS `#FAE605/#78FA14/#FF1400` - dung ma RGB goc theo yeu cau nguoi dung.
- Bang luon nen sang chu den ke ca khi theme toi - tho quen bang trang, nen toi lam mat cam nhan 3 mau tin hieu.
- Kich thuoc nut lon (bam bang gang tay), luoi 9 dong co dinh khong nhay.
- Bang do dac "MAT TIN HIEU CAN" (doc tu 1-2m) va bang xanh thong bao trung don (co y KHONG dung vang/do).

**a) Bo bo khung Windows 95** 鈥?`border: 2px inset/outset` + nen `#ece9d8` doi thanh khoi `.panel` bo goc 12px, vien 1px, do bong nhe; nen tong `#eef1f6` (xam xanh trung tinh). **Cung do sang** nen 3 mau tin hieu doc y het, chi khong con cam giac phan mem hai muoi nam truoc. Phan hoi bam nut doi tu `border-style: inset` sang `translateY(2px)` - tho deo gang khong cam nhan duoc cu bam bang tay, phan hoi thi giac la thu duy nhat bao may da nhan.

**b) O so DELTA tu doi mau theo dung sai** (thay doi dang ke nhat)
- Dai mau tren dinh o + vien doi theo **DUNG 3 mau cua o PROCESS**, them dong "muc tieu 12.50" ngay canh. Tho dang do vat tu thi mat dan vao so to nay; bat ho liec xuong bang moi biet du hay chua la thua mot nhip.
- **Chua chot bi thi de trung tinh**: luc do `liveWeight` chua tru duoc gi, to mau chi la doan bua, ma mot o to dung mau vang "chua du" ngay khi vua bam NEXT thi gay hieu nham hon la giup.
- Phan SO giu nen trang (chi dai tren cung an mau) de luon doc duoc; rieng tone do thi so chuyen do dam.

**c) Tach `utils/processColor.ts`** 鈥?quy tac suy mau tu `Mod_UI_processcolor.CheckRange` gio nam MOT cho, dung chung boi luoi 9 dong va o DELTA. Hai cho nay nam canh nhau tren cung man hinh: lech nhau du chi o ranh gioi 0.99/1.01 la tho thay so to bao DAT ma o trong bang van vang - mat luon niem tin vao ca hai.
- **Guard `frontend/scripts/check-process-color.mjs`**: doi chieu ban moi voi doan ma CU chep nguyen van tu VbaRackGrid (git 5929b55), quet day 1200 diem quanh 2 ranh gioi + cac muc tieu bat thuong (null/0/am/NaN/khong co vat tu) + khang dinh **khong so am nao an nen xanh**. **1229/1229 PASS.** Dang co vi mau o PROCESS khong phai chuyen tham my: trong VBA chinh BackColor o nay la trang thai nghiep vu (`btnSave_Click` doc nguoc mau nen de ghi ACCEPTED/REJECTED).

**d) Vai tr貌 n煤t hi峄噉 b岷眓g m脿u** 鈥?SAVE xanh la dac (chot ca me), NEXT xanh duong dac (nut bam nhieu nhat), CLEAR **vien do chu khong nen do dac**: no nam canh SAVE va duoc bam thuong xuyen, to do ruc se thanh nhieu thi giac va lam nhat luon bang canh bao mat tin hieu can.

**e) Luoi 9 dong** 鈥?dong dang can doi tu mot vach xanh mong sang **nen xanh nhat + dai 5px + o so thu tu dao mau**; vien den doi sang xam nhat; so dung `tabular-nums` de cot khong nhay khi so doi. O PROCESS to hon (24px, 800).

- **Kiem chung:** `vue-tsc` **25 loi = baseline**, khong loi nao o 3 file vua sua. `vite build` **OK 16.54s**. `check-process-color` **1229/1229**, `check-qr-parser` **14/14**. **CHUA nhin bang mat tren trinh duyet** - may nay khong co trinh dieu khien trinh duyet (khong co playwright/puppeteer/chromium-cli) va khong tu cai them. Can nguoi dung mo xem.

### 79. Don sach 25 loi vue-tsc -> 0, va 3 thu duoc phat hien tren duong di

- **Yeu cau (02/08/2026):** "fix cac loi do cho toi" (25 loi vue-tsc ton dong).
- **Ket qua: 25 -> 0.** `vite build` OK 16.59s, `check-process-color` 1229/1229, `check-qr-parser` 14/14.
- Luu y: `npm run build` chi la `vite build`, ma Vite dung esbuild - **chi boc kieu chu khong kiem kieu**. 25 loi nay tich lai duoc chinh vi khong co gi chan chung. `vue-tsc` van la cong tu nguyen, khong nam trong build.

**a) Lech kieu THAT (2 loi)** - `stores/auth.ts` interface `Workstation` thieu 2 truong ma code va DB deu dung: `default_route` (cot co that trong `operation_clients`) va `capability_codes` (them 18/07 de va loi kiosk bi chan nham "tram khong co quyen"). **Code dung, kieu sai** - xoa truong cho het loi la dung lai dung con bug cu. Da bo sung ca hai kem chu thich canh bao.

**b) Trang Gantt: 8 loi, chi can 1 dong khai kieu.** `res.data` tu axios la `any` nen `items` cung thanh `any`, keo theo moi tham so .sort/.filter/.map mat kieu va `new Set(...)` ra `Set<unknown>`. Them `interface GanttItem` + chu kieu cho `items` la het 7/8.
- Loi con lai (TS2459 `DataSet`) la **thieu sot typings cua thu vien**: `vis-timeline/declarations/index.d.ts` co mot binding `DataSet` cuc bo (chi import, khong export) lam hong chuoi `export *`. Sua bang cach **tach doi**: GIA TRI van lay tu `vis-timeline/standalone` (bat buoc - lay tu goi `vis-data` rieng se ra mot lop DataSet KHAC voi lop Timeline ben trong dang dung, hai ben khong nhan nhau), KIEU lay tu `vis-data`. Da thu them `paths` vao tsconfig truoc do nhung khong can thiet - **da go lai, tsconfig y nguyen**.

**c) Troubleshooting.vue la file .vue DUY NHAT trong hon 40 file chua dung TypeScript.** Bat `lang="ts"` lam lo **33 loi moi**, nhung tat ca deu gom ve vai cho khai bao goc: `ref([])` -> `ref<any[]>([])` (goc cua ~16 loi "type never"), `form.parameters` -> `Record<string, any>` (truy cap bang ten dong), `resolveModal.cause`/`detailModal.case`, va 4 tham so ham. **Chi sua kieu, khong doi mot dong logic nao.**

**SAI SOT TRONG LUC LAM:** doc/ghi file UTF-8 bang `Get-Content -Raw`/`Set-Content` cua PowerShell 5.1 (khong chi -Encoding) da lam hong ky tu tieng Viet cua Troubleshooting.vue. Da khoi phuc tu ban sao va xac nhan `git diff` sach truoc khi lam lai bang cong cu sua file. **Bai hoc: khong dung PowerShell de doc/ghi file nguon co tieng Viet.**

**d) 3 thu phat hien duoc khi ra tung bien "thua" thay vi xoa hang loat:**
1. **`saveTareToStorage` trong `useScaleFeed.ts` chua bao gio duoc goi** - nghia la khoa `df_weigh_tare_state_v2` khong bao gio duoc ghi, nen `restoreTareFromStorage` (co XUAT ra ngoai) chi co the tra ve null. Mot ham khoi phuc luon tra null la cai bay cho nguoi dung no sau nay -> **da go ca bo 3 hang**. Viec nho bi qua F5 hien do `df_ws2_session_v1` trong WeighingStationV2 lo, va ban do luu bi KEM vi tri o dang can + so can gop de doi chieu dia can - bo trong composable khong mang theo hai thu do nen du co chay cung khong du an toan.
2. **`handleLogout` bi bo quen o 5 man hinh** (MachineQueue, Materials, Recipes, WaterConfigs, Troubleshooting) - nut Dang xuat da chuyen han vao `AppLayout.vue` (dong 132). Xoa xong lo tiep `router`/`authStore` cung chi con phuc vu no -> xoa not ca import.
3. **`elapsed` trong `MachineQueue.getLockAgeSeconds`** - bien tinh do lech dong ho may tram/server, tinh ra roi **khong dung vao ket qua tra ve**. Da go va **ghi ro canh bao tai cho**: moc het han khoa 5 phut hien tinh bang dong ho MAY TRAM doi chieu voi moc gio server, may tram lech gio thi moc nay lech theo. Chua sua (can quyet dinh lay gio server o dau).

- Ngoai ra: `echo` nhap thua o WeighingStation (realtime nam trong QrScanPanel), `SvgIcon`/`reactive` thua o Dashboard, `kioskUrlInput` thua o WorkstationAdmin (go luon `ref=` tren template vi nut copy dung `navigator.clipboard`), `state` thua trong getter `isKiosk`, tham so `el` trong LabelPreview (dung `unknown` chu khong `any` - da co `instanceof` loc san).

### 80. Mat mang o Tram can: mo ta dung hanh vi hien tai, va va mot lo hong IM LANG

- **Cau hoi nguoi dung (02/08/2026):** "trong truong hop mat mang k luu duoc thi lam sao".

**a) Hanh vi HIEN CO (doc tu code, khong suy doan) - phan da dung san:**
- **Quet KHONG can mang.** Tu muc 68, chuoi QR duoc parse ngay tai trinh duyet; ca me chi cham mang DUNG MOT LAN luc bam SAVE.
- **SAVE hong thi KHONG mat so.** `onSave` catch chi dat `errorMsg`, **khong dong vao `capturedWeights`** - bam SAVE lai duoc ngay khi co mang.
- **Dong trinh duyet/mat dien cung khong mat.** Phien nam trong `localStorage` (`df_ws2_session_v1`), `clearSession()` chi chay khi SAVE THANH CONG. Mo lai trang la dung lai nguyen me (muc 69).
- **Dieu KHONG lam duoc:** can tiep khi mat mang. So can di duong Agent -> backend -> trinh duyet (ADR-002: trinh duyet khong bao gio noi thang voi phan cung), nen mat mang toi CS-SERVER la mat luon so can du cai can va Agent deu nam ngay tren may do.

**b) LO HONG PHAT HIEN DUOC - mat mang gan nhu IM LANG (da va):**
- Bang canh bao `MAT TIN HIEU CAN` co dieu kien `scaleOnline && !signalLive`. Nhung khi mat mang thi `scaleOnline` = **false**, nen bang nay **khong hien**. Ca man hinh chi con mot cham do 9px bao hieu.
- Te hon: nhanh `catch` cua `fetchLiveWeight` **khong ha `isStable`**. `ingestRawWeight` khong he chay trong nhanh do nen `isStable` GIU NGUYEN gia tri cu. Mat mang dung luc can dang dung yen -> man hinh treo lai o "ON DINH" voi mot con so dong cung, con o DELTA thi van giu nguyen mau xanh "DAT". Va chinh co `stable` nay duoc gui len server lam dieu kien cho ghi (`weighFromQr` chan khi stable=false).
- **Da sua 4 cho:**
  1. `useScaleFeed` catch: ha `isStable = false`.
  2. Tach **hai** bang canh bao khac nhau vi cach xu ly khac nhau: `MAT TIN HIEU CAN` (goi duoc backend, so cu -> kiem tra Agent/PuTTY/day can) va `MAT KET NOI MAY CHU` (khong goi duoc backend -> mang/server), bang thu hai noi ro "So da can KHONG mat, can lai duoc ngay khi co mang".
  3. `deltaTone` tra `none` khi mat tin hieu - khong to xanh "DAT" cho mot con so khong con phan anh cai dia can.
  4. Vien "ON DINH/CHO ON DINH" them trang thai rieng `MAT TIN HIEU`, va so DELTA lam mo di.

**c) CHUA lam (can nguoi dung quyet dinh):** frontend khong co bat ky co che phat hien offline nao (`navigator.onLine`, su kien online/offline deu khong duoc dung o dau trong `frontend/src`), va SAVE **khong tu thu lai** - thao tac vien phai tu nhan ra va bam lai. Hang doi offline hien chi co o phia Local Agent (CLAUDE.md muc 5), khong co o phia trinh duyet.

- **Kiem chung:** `vue-tsc` **0 loi**, `vite build` OK 16.62s. **CHUA thu ngat mang that** - can nguoi dung tu rut mang/tat backend de xac nhan hai bang canh bao hien dung.

### 81. Hang doi SAVE trong localStorage - mat mang van can tiep, van in duoc, khong mat me nao

- **Yeu cau (02/08/2026):** "hang doi SAVE trong localStorage, co mang thi tu day, kem idempotency_key... van co the in, khi an save roi thi no se nam trong hang cho, khi co mang thi day len".

**a) Backend - chong ghi trung (`ScannerController::weighFromQr`)**
- Nhan them `idempotency_key` (nullable, de client cu va goi tay van chay).
- **Truoc** khi vao transaction: neu khoa da ton tai -> dung lai phieu tu du lieu da luu va tra **200 kem `reused: true`**, KHONG ghi them gi. Tra 200 chu khong phai loi la co y: voi hang doi thi day la ket qua DUNG (me da nam duoi DB), tra loi se khien no thu lai mai khong thoi.
- Dong dau khoa **trong cung transaction** voi viec ghi so can - hoac ca hai cung co, hoac ca hai cung khong. Ghi khoa ngoai transaction se ho ke: lan gui lai thay khoa da ton tai trong khi so can da bi rollback mat.
- **Ca hiem nhat can chan KHONG phai mat mang han**, ma la: request DA toi server va ghi xong, nhung phan hoi rot giua duong -> hang doi tuong that bai va gui lai.
- Migration `2026_08_02_000001_add_idempotency_key_to_weighing_jobs` (nullable + unique, idempotent, co down() an toan). **CHUA CHAY tren DB production** - migration tren prod phai xac nhan rieng (`.claude/rules/database-safety.md` muc 7, khong nam trong allowlist deploy).

**b) In duoc khi mat mang - `utils/weighSlip.ts` + tach ham thuan ben PHP**
- Tach `WeighingJobController::buildSlipTspl()` thanh **ham thuan** (khong cham DB) khoi `buildAndStoreSlip()`. Nho vay moi doi chieu duoc hai ban ma khong ghi PrintJob nao xuong DB.
- Port sang JS: dung phieu TSPL ngay tai trinh duyet tu du lieu dang co tren man hinh. Phai ban sao ca `number_format($x, 2)` (dau phay hang nghin) va `Carbon::format('d/m/Y H:i:s')` - **khong** dung `toLocaleString` vi ket qua doi theo ngon ngu may tram.
- **Guard `frontend/scripts/check-weigh-slip.mjs`**: doi chieu TSPL **tung ky tu** giua hai ban, 7 ca (rack rong, so lon co dau phay hang nghin, so am, dau nhay kep phai bi bo, khong dong nao, 9 dong). **7/7 PASS.**

**c) Hang doi - `services/saveQueue.ts`**
- Xep hang **TRUOC** khi gui, khong phai sau khi gui hong: xep sau thi co ke ho - dong trinh duyet/mat dien dung luc request dang bay la me bay theo, khong con dau vet nao de gui lai.
- **Loi MANG va loi NGHIEP VU xu ly khac nhau.** 4xx (tru 408/429) = payload sai, gui lai bao nhieu lan cung the -> danh dau ket, bo qua khi day hang doi, khong de mot me hong chan luon cac me sau. Mat mang/5xx -> giu lai thu tiep.
- Day **tuan tu** chu khong song song: nhieu me co the tro ve cung mot lo, ma `handleOrderScan` co khoa chong hai may can chung mot vong - ban song song la tu tranh chap voi chinh minh.
- Ba moc kich hoat chong len nhau: su kien `online`, nhip dinh ky 15s, va mot luot ngay khi mo man hinh. Su kien `online` KHONG bat duoc ca "co mang LAN ma may chu chet" nen van can nhip dinh ky.

**d) Man hinh can**
- SAVE -> xep hang -> gui thu ngay. Thanh cong: in phieu tu server roi xoa form (nhu cu). Mat mang: **in phieu do trinh duyet dung**, xoa form luon de tho quet me ke tiep - dung muc dich cua hang doi - va bao ro "me dang nam trong HANG DOI, dung xoa du lieu trinh duyet". Loi nghiep vu: **giu nguyen so tren man hinh** de tho sua.
- Chi bao "N me cho gui" canh den tin hieu can (do khi co me ket), bang chi tiet co GUI NGAY / THU LAI / BO (bo phai xac nhan - me chua len server, bo la mat luon).
- **Sua kem mot loi co san:** nut PRINT goi `/api/weighing-jobs/null/print-slip` voi me doc tu QR (id null) nen **luon 404** - tuc nut PRINT hong voi chinh luong chay chinh. Nay dung ban dung phieu cuc bo.
- Luong "DF:ORDER:<uuid>" **khong** qua hang doi: vong can da co san duoi DB tu luc quet, va endpoint weigh-batch chua co khoa chong ghi trung. Giu nguyen hanh vi cu.

- **Kiem chung:** `WeighFromQrIdempotencyTest` **4/4 PASS** (test quan trong nhat: gui 2 lan cung khoa -> moi dong chi co DUNG 1 ban ghi ScaleMeasurement). `WeighBatchTest` **15/15** khong vo. Tong **19 passed, 94 assertions** tren SQLite in-memory. `check-weigh-slip` 7/7, `check-process-color` 1229/1229, `check-qr-parser` 14/14. `vue-tsc` **0 loi**, `vite build` OK.
- **CHUA thu tay tren trinh duyet** va **CHUA chay migration** - hai viec nay can nguoi dung.

### 82. Da chay migration idempotency_key TREN DB PRODUCTION (nguoi dung xac nhan trong phien)

- **Nguoi dung yeu cau ro trong phien (02/08/2026): "lam di"** 鈥?day la xac nhan bat buoc theo `.claude/rules/database-safety.md` muc 7 (migration KHONG nam trong allowlist deploy thuong quy).

**Kiem tra TRUOC khi chay (deu chi doc):**
- Xac nhan dang noi dung DB production: `production_web @ 10.0.60.209:5433`.
- `migrate:status`: **chi DUNG MOT migration dang treo** la cai nay 鈥?khong co migration la nao di ke. Day la buoc quan trong nhat: `php artisan migrate` chay TAT CA migration treo chu khong rieng cai minh muon.
- Quy mo: `weighing_jobs` **20 dong / 24 kB**, **0 khoa** dang giu tren bang -> them cot (can ACCESS EXCLUSIVE) chi khoa vai mili giay.
- Ke hoach lui: thay doi la **chi THEM cot nullable**, khong dung vao dong du lieu nao; `down()` chi xoa dung cot vua them (dang rong) -> lui bang `migrate:rollback --step=1`, khong co kha nang mat du lieu. Khong dump toan bo DB vi khong tuong xung voi muc rui ro.
- Chay **co gioi han pham vi**: `--path=database/migrations/2026_08_02_000001_...php` chu khong chay tran.

**Ket qua:** `DONE 161.36ms`.

**Kiem chung SAU khi chay (chi doc):**
- Cot: `character varying(100)`, `nullable: YES` 鈥?dung thiet ke.
- Index: `weighing_jobs_idempotency_key_unique` (UNIQUE btree). **Day moi la thu thuc su chan ghi trung** 鈥?khong co index nay thi ca co che chi la trang tri.
- Du lieu cu nguyen ven: **20 dong truoc = 20 dong sau**, 0 dong co idempotency_key (dung, cot moi).
- Smoke test `POST /api/scanner/weigh-from-qr` -> **401** (app boot sach, route dung cho, middleware auth chay).

- **Con lai cho nguoi dung:** thu ngat mang + SAVE tren trinh duyet that. CHUA ai chay duong ghi that qua endpoint nay tren DB production.

### 83. SAVE in phieu NGAY tu du lieu tren man hinh, khong cho vong mang nao

- **Yeu cau (02/08/2026):** "khi an save tem ma in ra lay nhung cai dang dung hien thi o tren web de in off luon".
- **Truoc do:** mac du da dung san phieu cuc bo, luong SAVE van **cho `axios.post` tra ve** roi moi in, va uu tien phieu server tra ve. Tuc van cho tron mot vong mang chi de lay ve dung thu minh da co san 鈥?chinh cho tho tung keu "bam SAVE xong cho mai tem moi hien".
- **Nay:** in NGAY sau khi mo cua so, TRUOC khi cham mang. An toan vi ban dung cua trinh duyet da duoc doi chieu TUNG KY TU voi ban server (`check-weigh-slip.mjs`, 7/7). `window.print()` chan trong cua so CON nen request van bay di binh thuong trong luc hop thoai in dang mo.

**Hai he qua phai xu ly, khong bo lo:**

1. **Moc gio in.** Trinh duyet in bang dong ho may tram; neu de server tu lay gio cua no thi ban ghi `print_jobs` mang mot moc khac voi to phieu dang nam tren hang 鈥?mat kha nang doi chieu, dung thu ma ban ghi do sinh ra de lam. Nay chot moc MOT LAN o trinh duyet (`nowSlipTimestamp()`), gui kem `printed_at` len server, xuyen qua `buildSlipForJob -> buildAndStoreSlip -> buildSlipTspl`. Khong gui thi server tu lay gio minh nhu cu.

2. **Loi nghiep vu (4xx) sau khi DA in.** Truoc day nhanh nay `boQua()` (xoa khoi hang doi) va giu nguyen man hinh. Gio phieu da ra giay roi, xoa khoi hang doi se de lai **mot to phieu tren hang ma trong may khong con dau vet nao**. Doi thanh `danhDauKet()`: ngung tu gui lai nhung GIU trong hang doi, hien chi bao do, tho mo bang ra xem/xu ly. Man hinh van xoa trang de quet me ke tiep 鈥?khong mat gi vi moi thu deu nam trong hang doi.

- Ket qua: bam SAVE -> phieu hien ra ngay, form trang ngay, quet me ke tiep duoc ngay. Ket qua gui len server (thanh cong / cho mang / bi tu choi) deu hien qua chi bao hang doi chu khong chan tho lai.

- **Kiem chung:** them 2 test 鈥?`printed_at` tu trinh duyet phai xuat hien trong phieu server luu, va khong gui thi van co moc gio hop le. `WeighFromQrIdempotencyTest` **6/6**, tong voi `WeighBatchTest` la **21 passed, 98 assertions**. `check-weigh-slip` 7/7. `vue-tsc` **0 loi**, `vite build` OK 17.01s.
- **CHUA thu tay tren trinh duyet.**

### 84. Da bam SAVE thi BAT BUOC phai gui 鈥?bo duong vut me, va go cai bay ket vinh vien di kem

- **Yeu cau (02/08/2026):** "1 me o hang cho k bo duoc, bat buoc phai gui khi da an save".
- **Sua truc tiep:** bo nut **BO** khoi bang hang doi va bo ham `onBoMe`. `saveQueue.boQua()` doi ten thanh **`danhDauDaGui()`** 鈥?ten moi noi dung viec no lam va **la duong DUY NHAT** mot me roi hang doi: chi goi sau khi cam trong tay phan hoi 2xx that. Ghi thanh dieu kien 3 o dau file (truoc co 3, nay 4).

**Nhung neu chi lam bay nhieu thi tao ra mot cai bay CHET 鈥?2 thu bat buoc phai sua kem:**

1. **Co `stable` gui len la co SAI.** `onSave` gui `stable: isStable.value` 鈥?do la co SONG cua lan doc **ngay luc nay**, tra loi cau hoi "cai can LUC NAY co dung yen khong". Server hoi mot cau khac: "may con so trong goi nay co phai so da dung yen khong". O man V2 hai thu cach nhau HANG PHUT vi can ca me xong moi SAVE mot lan.
   - **Bay that, tren duong thuong:** bam NEXT xong bam SAVE luon thi `resetTareForNewSlot()` vua ha `isStable = false`; hoac mat mang thi `fetchLiveWeight()` catch cung ha no xuong 鈥?**ma mat mang chinh la luc hang doi duoc dung**. Me vao hang doi voi `stable=false` se an **422 NOT_STABLE mai mai**, gui lai bao nhieu lan cung the. Truoc day escape la nut BO; bo nut BO ma khong sua cho nay = ket vinh vien kem mot to phieu da in.
   - **Sua:** gui `stable: true` cho nhanh QR. **Dung theo cau tao, khong phai noi lieu:** `capturedWeights` chi nhan `liveWeight`, ma `liveWeight` chi nhuc nhich sau `if (!stable) return` trong `ingestRawWeight`; bi cung chi chot tu lan doc on dinh, khong co bi thi khong chot duoc so nao. O khong can gui `weight = null` va bi gan KHONG DAT, khong phai so rac. Tuc **weight khac null <=> da chot tu mot lan doc on dinh**.
   - Cong chan `NOT_STABLE` phia server **giu nguyen** (test `rejected when stable false` van pass) 鈥?chi doi thu ma client V2 gui len.

2. **Tu bo cuoc sau 20 lan hong mang.** `MAX_LAN_THU = 20` danh dau me thanh "ket" sau 20 lan gui hong, tuc **chi 5 phut mat mang** (nhip 15s) la ngung tu gui va bat nguoi bam tay tung me. Mang xuong chet nua tieng hay khoi dong lai server la dinh 鈥?mau thuan thang voi "bat buoc phai gui". **Bo han nguong nay:** loi mang chi dem `so_lan_thu`, khong bao gio dat `loi_nghiep_vu`. Khong ton gi: `dayHangDoi` dung ca luot ngay lan hong dau nen tong cong van chi **mot request moi 15s** du hang doi co bao nhieu me. Tu nay `loi_nghiep_vu` chi con nghia "server tra loi tu choi", khong con nghia "thu nhieu qua".

- **`tatTuDay()` nay co dieu kien:** roi man hinh can ma hang doi con me thi **khong tat nhip tu day**. Truoc day `onUnmounted` tat han -> me nam im cho toi lan sau co nguoi mo dung man hinh can. Hang doi rong thi moi 15s no chi so hai con so roi thoi.
- **DA CAN NHAC VA BO** chan `beforeunload` khi hang doi con me: `main.ts` bat 401 bang `authStore.logout()` + `window.location.reload()`, nen phien het han se bung hop thoai "roi trang?" ma tho khong he bam gi 鈥?bam Huy con ket lai o trang da dang xuat. Dong tab KHONG mat me (localStorage con nguyen, da ra soat: ca frontend khong co `localStorage.clear()` nao, chi `removeItem` dung khoa, khong dung toi `df_ws2_save_queue_v1`) va nhip tu day van song khi roi man hinh 鈥?doi lay mot hop thoai doa nguoi la lo.
- **Duong thoat cho me bi server tu choi that:** nut **THU LAI**. Cac nguyen nhan 4xx thuc te deu sua duoc roi thu lai (401 het phien -> dang nhap lai; 403 thieu quyen tram -> admin cap; khoa lo -> doi tram kia nha), khong con cai nao vinh vien sau khi da go bay `stable` o tren.
- **Kiem chung:** `vue-tsc` **0 loi**, `vite build` OK 17.79s, `WeighFromQrIdempotencyTest` + `WeighBatchTest` **21 passed (98 assertions)**, 3 script guard 7/7 + 1229/1229 + 14/14. Ra soat sach: khong con `boQua`/`MAX_LAN_THU`/`onBoMe` o dau.
- **CHUA thu tay tren trinh duyet.**

### 85. Ping dinh ky 鈥?thong thi moi day me len

- **Yeu cau (02/08/2026):** "toi muon co ping dinh ki, khi ma thong thi day".
- **Truoc do:** nhip 15s nem thang ca me (goi vai KB) ra duong roi ngoi cho het gio. Mat mang la lap lai mai.
- **Nay:** `GET /api/ping` (route moi, `routes/api.php`) tra `{"status":"OK"}`, nhip 15s goi `thuDay()`: ping truoc, **thong moi day**. Su kien `online` cua trinh duyet cung di qua `thuDay` chu khong day thang 鈥?trinh duyet chi biet card mang da len, no khong biet server da voi toi duoc chua.

**Ba quyet dinh dang ghi lai:**

1. **Route ping CO Y de NGOAI middleware auth.** Phien het han cung phai ping duoc, neu khong "mat mang" va "het phien" nhin giong het nhau. Quan trong hon: 401 se kich hoat interceptor o `main.ts` -> `logout()` + `window.location.reload()` 鈥?mot nhip chay ngam se **tu da tho ra khoi man hinh can giua luc dang can**. Da chot bang test `test_ping_answers_without_authentication`.
   - Ping cung **khong cham DB**: cau hoi la "web con song khong", khong phai "DB con song khong". Bat no truy van DB thi moi tram can mat mang se nen them mot truy van moi 15 giay.

2. **BAT KY ma HTTP nao cung la THONG, ke ca 404/500.** Co ma tra ve nghia la goi tin da di toi noi va co thu gi do tra loi 鈥?dung cai can biet. **Khong phai chuyen vun:** server chua deploy route `/api/ping` se tra 404; coi 404 la tac thi hang doi dung im vinh vien tren dung nhung tram can no nhat. Chi khi KHONG co phan hoi nao moi la tac. Da kiem chung that: goi `/api/ping-chua-deploy` -> co phan hoi HTTP 404.

3. **Timeout ping 8 giay la DO DUOC, khong phai doan.** Ban dau dat 4s. Do that tren backend dang chay: luc nong **~25ms**, nhung lan goi dau sau khi server nam im **2.1s**, va lan dau tien sau khoi dong **4.2s** (PHP dung lai tien trinh + bootstrap Laravel). De 4s la thinh thoang bao "mat ket noi" trong khi server song nguyen.

- **Them timeout 20s cho chinh lenh gui me** (truoc do khong co -> mang nua song nua chet co the treo hang phut, ma treo la `flushing` ket bat, chan luon moi nhip sau). **Cat ngang KHONG so mat me:** neu request that ra da toi server va ghi xong thi lan gui lai mang dung `idempotency_key` cu, server nhan ra va tra ban da ghi (`reused=true`) 鈥?dung cai co che muc 82 dung ra de lam.
- **Chi bao cho tho:** them `duongThong` (ref). Chip hien "mat ket noi", bang hang doi hien "mat ket noi 鈥?dang do lai moi 15 giay" thay vi "dang cho mang" chung chung. Phan biet duoc 2 tinh huong nhin giong het nhau ma cach xu ly khac han: mat mang thi dung doi la xong, con me bi may chu che thi phai goi nguoi. `guiMot` cung cap nhat co nay (cung quy tac "co ma HTTP la thong") de nut GUI NGAY bam tay 鈥?di thang khong qua ping 鈥?van hien dung.
- **Kiem chung:** `php -l` sach, `route:list --path=ping` co dung 1 route, goi that khong dang nhap -> **HTTP 200**. `vue-tsc` **0 loi**, `vite build` OK 23.08s, test **22 passed (100 assertions)**.
- **CHUA thu tay tren trinh duyet.**

### 86. "Co 1 me cho gui nhung khong thay day" 鈥?hang doi DUNG IM HOAN TOAN khi chi con me bi tu choi

- **Nguoi dung bao (02/08/2026):** "co 1 the cho gui nhung van chua thay day".
- **Nguyen nhan (doc thang code, khong doan):** me bi danh dau `loi_nghiep_vu` thi **hai cong cung dong lai**:
  - `thuDay()` co cong `queueCount <= stuckCount` -> con dung 1 me va me do bi tu choi thi `1 <= 1` -> **thoat ngay, khong ping, khong day**.
  - `dayHangDoi()` loc `.filter(i => !i.loi_nghiep_vu)` -> danh sach rong -> vong lap khong chay lan nao.
  - **Hau qua: bam ca nut GUI NGAY cung khong xay ra gi**, khong co dau hieu nao cho biet vi sao. Chip van hien "1 me cho gui" nhu binh thuong.
- **Day la loi thiet ke cua chinh muc 84**, khong phai loi moi: bo nut BO nhung van giu nguyen co che "4xx thi ngung tu gui" 鈥?thanh ra me khong bo duoc MA cung khong gui duoc.

**Ba sua:**

1. **KHONG BAO GIO ngung thu lai, du loi loai nao.** Bo `.filter(i => !i.loi_nghiep_vu)` trong `dayHangDoi`. Ly do ban dau ("4xx thi gui lai bao nhieu lan cung the") **SAI voi thuc te o day**: phan lon 4xx tu khoi khi nguoi ta sua nguyen nhan 鈥?401 het phien (dang nhap lai), 403 thieu quyen tram (admin cap), khoa lo (tram kia nha). Loc bo nghia la bat tho nho quay lai bam THU LAI dung luc; quen la me nam do vinh vien. `loi_nghiep_vu` tu nay chi de HIEN cho nguoi doc, khong con la co dung. Khong ton gi: da co ping chan nen chi chay khi duong thong, va me hong that thi `continue` chu khong `break` nen khong chan me phia sau.
2. **Cong `thuDay` doi thanh `queueCount === 0`.**
3. **`vaMeCu()` 鈥?va payload da dong bang trong localStorage.** Me xep hang boi ban code CU mang theo `stable: false` (cai bay da mo ta o muc 84). Sua code chi cuu duoc me MOI; me da nam trong localStorage van an 422 NOT_STABLE mai mai, **ma gio khong bo me duoc nua**. `batTuDay()` nay quet hang doi, thay `payload.stable === false` thi dat lai `true` va xoa `loi_nghiep_vu` cu. **Va la DUNG chu khong phai lach:** moi `weight` khac null trong payload deu da chot tu mot lan doc on dinh (`capturedWeights` chi nhan `liveWeight`, ma `liveWeight` chi nhuc nhich sau `if (!stable) return`) 鈥?cai sai nam o co, khong nam o so.

- **Don dep kem:** header file danh so sai (hai muc cung so 3), dieu kien 2 viet nguoc voi hanh vi moi 鈥?da viet lai ca bon. Chu tren man hinh doi theo: bang hang doi hien "*(van dang tu gui lai moi 15 giay)*" canh loi, tooltip chip bo chu "bi ket".
- **LUU Y VAN HANH:** `vaMeCu()` chay trong `onMounted` -> **phai tai lai trang** (F5) thi me dang ket moi duoc va. HMR khong chay lai `onMounted`.
- **Kiem chung:** `vue-tsc` **0 loi**, `vite build` OK 46.84s. **Nguyen nhan goc cua rieng me dang ket tren may nguoi dung thi CHUA xac minh** 鈥?co che thi da doc thang code va chac chan, nhung ly do me do bi tu choi la suy luan. Xem bang `JSON.parse(localStorage.getItem('df_ws2_save_queue_v1'))` trong Console.

### 87. Gia lap khong can duoc 鈥?so go vao bi an lam BI, o DELTA hien 0.00 mai

- **Nguoi dung bao (02/08/2026):** "toi dang dung gia lap, nhu can khong duoc, no cu bi nha ve 0.0 o cac cai can can".
- **Loi that, khong phai dung sai. NGUYEN NHAN GOC chi co MOT: gia lap chi nap so khi gia tri DOI.** `fetchLiveWeight` `return` thang khi dang gia lap, nen nguon duy nhat la `watch(simulatedWeight)` 鈥?ma watch chi chay khi gia tri thay doi. Hau qua day chuyen:
  - Bam NEXT -> `resetTareForNewSlot()` dat `tareBaseline = null`, cho "lan doc on dinh dau tien" lam bi (`Delta_Begin` cua VBA). Nhung **khong co lan doc nao ca** 鈥?khong ai ban so vao.
  - Go so thu nhat -> watch chay -> so do bi an lam BI, `net = raw - bi = 0` -> o DELTA hien **0.00**.
  - Go lai dung so cu -> watch khong chay -> man hinh dung im. Nhin y het "can khong duoc".
- **Sua:** nhanh gia lap trong `fetchLiveWeight` nay nap lai `simulatedWeight` moi nhip, dung nhu cai can that ban so lien tuc. Van khong hoi Agent (dang gia lap thi so that phai bi bo qua hoan toan 鈥?bai hoc V1 ghi o dau `useSimValue`). Giu `watch(simulatedWeight)` de go xong thay doi tuc thi thay vi doi het mot nhip 200ms.
- **MOT BUOC SAI DA TU SUA TRONG PHIEN:** ban dau con them nhanh rieng cho gia lap trong `resetTareForNewSlot()` 鈥?chot `tareBaseline = 0` de "so go vao chinh la so can duoc". **Nguoi dung bat ngay:** *"khi an next thi phai can lai tu 0 chu, mac du la can tiep"*. Dung 鈥?vat tu o truoc VAN NAM tren dia, nen bi phai chot bang so DANG CO tren can luc bam NEXT thi o moi moi dem dung phan do them. Dat cung bi = 0 la bien so go vao thanh so can luon, tuc bo mat chinh cai hanh vi dang can thu. **Da go bo nhanh do** 鈥?gia lap nay chay y het can that, khong con biet le nao. Bai hoc: sua cho A thi dung vien mot ngoai le o B, cai `return` thieu o `fetchLiveWeight` moi la loi that.
- **Luong dung sau khi sua:** bam NEXT -> trong 200ms bi tu chot = so dang go, o DELTA ve **0.00**; go so lon hon (vi du 12.5 -> 20.8) -> hien **8.3**, dung phan vua do them; bam NEXT -> chot 8.3 vao o va sang o ke, lai ve 0.00.
- **Kiem chung:** `vue-tsc` **0 loi**, `vite build` OK 25.35s. **CHUA thu tay tren trinh duyet.**

### 88. Gia lap: so nhay ve 0.0 khi go dau thap phan, va F5 luon bao "dia can da thay doi"

Hai bao loi tiep theo cua nguoi dung sau muc 87, deu la loi that.

**A. "khi toi dien gia lap so cu bi nhay nhay ve 0.0"**

- **Nguyen nhan:** `<input type="number">` tra ve **CHUOI RONG** o moi trang thai go do 鈥?go `12.` la trinh duyet coi chua hop le. `v-model.number` day chuoi rong do vao `simulatedWeight`, roi `ingestRawWeight('')` -> JS tinh `'' - bi` ra SO chu khong ra loi (`'' - 0 === 0`) -> o DELTA nhay ve 0.00. Cu go toi dau cham thap phan la nhay mot cai.
- **Sua:** chan ngay dau `ingestRawWeight`: `if (!Number.isFinite(raw)) return;` 鈥?bo qua so rac, GIU NGUYEN so dang hien, dung nhu cai can that giu mat so khi khong doc duoc gi moi.
- **Bat duoc them mot loi co san:** duong can THAT cung di qua cong nay 鈥?`parseFloat(res.data.weight)` ra NaN khi Agent day len chuoi hong, truoc day NaN chay thang vao `liveWeight` va mat so hien "NaN". Nay cung bi chan.

**B. "khi toi f5 lai thi tiep tuc can cai toi da an next"**

- **Nguyen nhan:** `useSimValue` la ref thuong, **khong song qua F5**. Tai lai trang xong gia lap TU TAT, man hinh quay ve doc can that (so 0 hoac mat tin hieu). Ma co che noi lai o dang can do (`pendingResume`) lai so `grossWeight` hien tai voi `grossAtSave` da luu 鈥?mot ben la so gia lap, mot ben la so cua can that -> **lech, lan nao F5 cung an "Dia can da thay doi trong luc tai lai trang"**.
- **Sua:** luu `useSim` + `simWeight` vao `df_ws2_session_v1`, va khoi phuc chung trong `khoiPhucGiaLap(saved)` 鈥?goi **TRUOC** khi dat `pendingResume` o ca hai nhanh (QR va token). Thu tu la bat buoc: khong dung lai nguon so gia lap truoc thi nhip poll dau tien nap so cua can that va phep so luon lech.
- Da kiem tra `onNext` khong co loi thu tu: `captureCurrentSlot()` -> tang `currentIndex` -> `saveSession()`, nen phien luu dung O MOI. Sau F5 quay lai dung o dang can do la **hanh vi thiet ke** (muc 69), khong phai bug.
- **Kiem chung:** `vue-tsc` **0 loi**, `vite build` OK 19.96s. **CHUA thu tay tren trinh duyet.**

### 89. Giam tai nhip poll cua /weighing-station-v2 鈥?9 truy van DB moi 200ms

- **Nguoi dung hoi:** "co gop y gi cho toi de chay muot hon nua khong".
- **Do bang cach doc dung duong code** cua `GET /api/devices/readings/{id}?local=1`:

| Cho | Truy van |
|---|---|
| Sanctum auth | 2 (`personal_access_tokens` + `users`) |
| `resolveReadingKey` tra id -> ma tram | 1 (`operation_clients`) |
| `readCacheSlot(ma tram)` | 3 |
| `readCacheSlot(theo IP may)` | 3 |

  **= 9 truy van x 5 lan/giay = ~45 truy van/giay cho MOI tram can.** 6 truy van cache la do `.env` de **`CACHE_STORE=database`** 鈥?moi `Cache::get` di thang xuong PostgreSQL. Agent day so len con nang hon: `storeReading` goi **7 `Cache::put`** moi lan doc can.
- **Tren may dev day chinh la thu lam no i:** DB o 10.0.60.209, moi truy van ~20ms (da do o muc 68) -> 9 x 20 = **~180ms** trong khi nhip poll la 200ms, gan nhu bao hoa. Tren CS-SERVER DB nam cung may (~1ms) nen nguoi dung khong thay, nhung van la 45 truy van/giay/tram nen vao mot bang `cache` duy nhat.

**DA LAM (nguoi dung chon 2 + 4):**

2. **`Cache::many()` thay 3 lan `Cache::get`** trong `readCacheSlot`. Da **doc thang vendor** de xac minh chu khong tin suong: `Illuminate\Cache\DatabaseStore::many()` (dong 125) gom thanh MOT `whereIn('key', ...)`. **6 truy van xuong 2.** Khong doi dinh dang luu -> KHONG can deploy dong bo voi Agent, Agent van ghi 3 khoa roi nhu cu.
   - **Bay da chan:** `many()` tra `null` cho khoa trong, KHONG nhan gia tri mac dinh nhu `Cache::get($key, false)`. Quen bu `?? false` thi `is_stable` ra `null` 鈥?trinh duyet `Boolean(null)` van ra false nen nhin thi "van chay", nhung kieu du lieu trong phan hoi da sai va bat ky cho nao so `=== false` se hong am tham. Da chot bang test rieng.
4. **Ngung poll khi tab bi an** (`visibilitychange` + `document.hidden`). Gop chung vao `capNhatNhipPoll()` de ca `watch(dangCan)` lan su kien hien/an di qua mot cho. Hien lai thi doc so NGAY roi moi vao nhip, khong doi toi nhip ke. An toan vi khong co thao tac can nao xay ra khi trang bi an (khong bam duoc NEXT, khong quet duoc ma). **KHONG dung toi nhip hang doi** (`batTuDay`) 鈥?me chua gui van phai duoc day len ke ca khi tho da chuyen cua so khac.

- **Test moi `ScaleReadingCacheTest` (4 test):** doc dung so trong cache; **cache rong -> `is_stable` phai la `false` dung kieu bool, khong duoc null** (dung ca `many()` khac `get()`); so het han nhung moc thoi gian con -> van bao duoc `age_ms` (thu ma man hinh dung de bao MAT TIN HIEU CAN); `?local=1` -> can o chinh may thang ma tram cau hinh san.
- **Kiem chung:** `php -l` sach, `vue-tsc` **0 loi**, `vite build` OK 17.54s, **26 passed (116 assertions)**.

**CHUA LAM 鈥?de nguoi dung quyet:**

1. **`CACHE_STORE=database` -> `file`** (config server). Bo duoc 6/9 truy van doc VA ca 7 lan ghi cua Agent. Khong sua dong code nao. CS-SERVER chay tat ca tren mot may nen file cache la du dung. **Day van la don bay lon nhat**, muc 2 chi go duoc mot phan. Doi xong nho `config:clear`; cache so can TTL 15s nen mat cache khong anh huong gi.
3. **Bo truy van tra id -> ma tram.** Frontend dang gui `id`; gui thang `code` thi `resolveReadingKey` tra ve ngay, khoi truy van. **CHUA xac minh** Agent ghi cache theo ma tram hay theo id 鈥?phai kiem truoc khi doi.
5. **Thay poll bang SSE** (ADR-008 da chot SSE cho realtime, du an da co Transactional Outbox). Do moi la cach bo han 45 truy van/giay. Dang Phase 12 UAT nen khong dong vao kien truc.
- **Khong phai van de:** canh bao chunk >500kB luc build 鈥?router da lazy-load du 30 route, canh bao do den tu thu vien khac chu khong phai trang nay.

### 90. Lam not muc 1 + 3: 9 truy van/lan poll xuong con 2. Va DINH CHINH muc 5 鈥?SSE la khuyen nghi SAI

**1. `CACHE_STORE=database` -> `file`** (`backend/.env`, da `config:clear`).

- Da ra soat truoc khi doi: **khong co `Cache::lock` hay `Cache::tags` nao** trong `app/` 鈥?file store khong ho tro tags nen day la dieu kien bat buoc phai kiem.
- File store la CUC BO TUNG MAY, nhung dung o day: ca ghi (Agent POST -> `storeReading`) lan doc (trinh duyet GET -> `getReading`) deu chay trong tien trinh backend tren CUNG mot may CS-SERVER.
- **Kiem chung that** bang tinker: put 3 khoa roi `Cache::many` doc lai -> `{"..._WSKT":12.34,"..._stable_WSKT":true,"..._timestamp_WSKT":1785663207.33,"..._KHONG_CO":null}`. Ket qua cuoi cung xac nhan dung cai bay da chan o muc 89: khoa trong tra `null`.
- **`.env` nam trong `backend/.gitignore`** -> sua o day CHI doi may dev. **CS-SERVER phai tu doi**, khong deploy kem duoc.
- Test khong bi anh huong: `phpunit.xml` ep `CACHE_STORE=array`.

**3. Frontend gui MA tram thay vi id.**

- **Da xac minh truoc khi doi** (muc 89 con ghi la chua): `storeReading` ghi cache bang dung chuoi `workstation_id` Agent gui len 鈥?mot MA (vi du "WS-WEIGH-SCALE"), khong phai so. `resolveReadingKey` chi tra DB khi tham so la so, de doi id -> `OperationClient.code`. Vay gui thang ma la trung dung khoa do, bot 1 truy van, ket qua khong doi.
- Khong co rui ro thu tu deploy: backend nhan ca hai dang, frontend moi chay duoc voi backend cu.
- Co `encodeURIComponent` + lui ve `id` khi tram chua co ma.

**Ket qua don:**

| | Truoc hom nay | Sau muc 2 | Sau muc 1+3 |
|---|---|---|---|
| Truy van DB / lan poll | 9 | 5 | **2** (chi con Sanctum auth) |
| Ghi DB moi lan Agent doc can | 7 | 7 | **0** |

**DINH CHINH 鈥?muc 5 (SSE) la khuyen nghi SAI, da rut lai:**

- O muc 89 toi de xuat "thay poll bang SSE" va vien dan ADR-008. **Toi da khong doc phan cap nhat 2026-07-30 cua chinh ADR do:** SSE g峄慶 (`/api/realtime/stream`, vong lap `while(true)`) **da duoc lam va da gay treo TOAN BO server** 鈥?`php artisan serve` tren Windows khong co concurrency that (khong `fork()`), chi mot tab mo Dashboard la chiem request-handling thread vinh vien.
- SSE da bi thay bang **Laravel Reverb** (WebSocket, tu host), dang chay san: task `DFWeb-Reverb`, `frontend/src/services/echo.ts`, `app/Events/RealtimeEventBroadcast.php`, `BROADCAST_CONNECTION=reverb`.
- Lam theo dung loi toi noi la dung lai su co thang truoc. **Ban dung cua muc 5 la phat so can qua Reverb** 鈥?ha tang co san nen re hon tuong, nhung khac han ve rui ro: `storeReading` phai goi Reverb moi lan Agent day so (vai lan/giay), can kenh rieng tung tram, va **Reverb chet la man hinh can mat so** trong khi poll hien tai tu lanh. Phai giu poll lam du phong (dung ADR-010).
- **CHUA LAM, dung lai hoi nguoi dung** 鈥?cai ho duyet (SSE) khong ton tai nhu mot lua chon.
- **Bai hoc:** `.claude/architecture-decisions.md` co ADR da bi thay the nhung TIEU DE van giu nguyen ten cu ("ADR-008: Lua chon SSE..."), phan bac bo nam o muc con ben duoi. Doc tieu de roi trich dan la sai. Lan sau doc het ca muc truoc khi vien dan bat ky ADR nao.
- **Kiem chung:** `config:clear` OK, `cache.default = file`, `vue-tsc` **0 loi**, `vite build` OK 17.09s, **26 passed (116 assertions)**.

### 91. CAN TAY 鈥?can khong quet don van luu duoc

- **Yeu cau (02/08/2026):** "khi k quet QR ma can khong van co the luu binh thuong". Nguoi dung chon phuong an: **"van in phieu binh thuong, cai gi trong thi trong, van luu DB binh thuong"**.
- **Truoc do:** khong quet don thi `activeJob` null -> `jobItems` rong -> `rows` rong -> SAVE bao "Khong co dong nao de luu". Man hinh con ghi thang "khong co gi duoc luu".

**Rang buoc luoc do buoc phai xu ly (da tra migration truoc khi thiet ke):**

- `weighing_jobs.production_batch_id` **NOT NULL** + khoa ngoai -> phai co mot lo.
- `production_batches.color` / `product_code` / `machine_id` / `level_code` **deu nullable** -> de TRONG duoc, dung y nguoi dung, khong phai bia.
- `weighing_job_items.material_code` **NOT NULL** + khoa ngoai toi `materials.code`, va `planned_weight` **NOT NULL** -> hai cho nay khong the trong.

**Cach lam:**

- Lo: `legacy_batch_id = 'CANTAY-<YmdHis>-<4 ky tu>'`, color/product_code/machine_id/level_code **NULL**. Bao cao tieu hao/dung sai/san luong phai LOAI cac lo nay 鈥?nhan dien qua tien to `CANTAY-`, cung cach dang dung cho `ADHOC-`.
- Dong: `material_code = 'CANTAY'` (ma moi, `Material::firstOrCreate` 1 lan), `planned_weight = 0`, dung sai 0.
- **`process_status` tra `'MANUAL'`** cho dong mang ma moi nay. Neu khong, `planned_weight = 0` se cho ra REJECTED 鈥?**gan "khong dat" cho mot con so khong co gi de doi chieu la noi SAI**, khac han voi de trong. Nhan qua `material_code` chu KHONG qua `planned_weight <= 0`: ma moi chi dong can tay moi co, nen **khong ban ghi cu nao bi doi nhan** (dong QR muc tieu 0 do tem hong van giu nguyen REJECTED nhu truoc).
- **Di CHUNG endpoint `weigh-from-qr`** (`manual=true`, `raw_qr` doi thanh `required_without:manual`) chu khong tach endpoint rieng: in ngay, hang doi, chong ghi trung, xoa form 鈥?tat ca dung y het. Tach ra la co hai bo ngu nghia hang doi phai giu dong bo voi nhau.
- **Kiem chong ghi trung chuyen len TRUOC khi re nhanh.** O can tay hau qua con nang hon me QR: moi lan gui lai tao MOT lo moi toanh, khong co gi trung de ma dung nhau 鈥?khong co khoa thi ghi trung bao nhieu lan cung lot.
- Dong chua can thi KHONG tao item (khac me theo don: don quy dinh san phai can nhung gi nen phai ghi ca o bo trong; can tay khong co danh sach nao de ma thieu). Khong co dong nao da can -> tra 422, khong de lo rong.
- Frontend: them `manualRacks` (o RACK cua dong trong truoc day go xong roi vao hu khong vi `onUpdateRack` ghi vao `jobItems[idx]` khong ton tai), nhanh `canTay` trong `onSave`, `dungPhieuCanTay()`, doi lai dong chu tren man hinh.

**Kiem chung:**

- **Them ca "can tay" vao bo doi chieu JS<->PHP** (`check-weigh-slip.mjs`): dau phieu trong tron, nhan MANUAL, va dong can tay CHUA co so cai phai ra MANUAL chu khong phai REJECTED 鈥?chot dung THU TU cac nhanh. **Bo doi chieu bat duoc ngay mot troi dat that:** `processStatus` ban JS chua biet nhan MANUAL. Da bo sung + hang so `MANUAL_MATERIAL_CODE` dung chung. **8/8 pass**, hai ban ra CUNG mot chuoi.
- 5 test backend moi: luu duoc khong can QR + lo de trong dung cho; khong dong nao bi gan KHONG DAT; van co phieu voi dau phieu trong; gui lai khong de them lo; chua can gi thi choi 422 va khong tao lo rong.
- `php -l` sach, `vue-tsc` **0 loi**, `vite build` OK 16.80s, **31 passed (138 assertions)**, guard 8/8 + 14/14 + 1229/1229.

- **CHUA thu tay tren trinh duyet.**

### 92. Can tay: song qua F5, va tach han khoi moi thong ke san xuat

Hai viec con thieu o muc 91, nguoi dung yeu cau lam not: *"van can tiep duoc tru khi an clean"* va *"them phan danh dau rang cai nay, k lien quan den cai quet don"*.

**A. Me can tay song qua F5**

- `saveSession()` bo dieu kien `!activeJob` (truoc day thoat ngay -> can tay do dang ma F5 la mat sach). Thay bang dieu kien "co gi do dang de giu" (`activeJob` HOAC da bam NEXT HOAC da chot o nao) 鈥?khong co thi khong ghi, tranh moi lan can dung yen lai ghi mot ban rong vao localStorage.
- Luu them `canTay: true` (danh dau tuong minh, khong de `restoreSession` suy ra tu cho thieu jobId/rawQr 鈥?suy ra thi khong phan biet duoc voi mot ban ghi hong) va `manualRacks`.
- `restoreSession` them nhanh `canTay`: dung lai so da can + rack + gia lap, roi noi lai o dang can do bang dung co che `pendingResume` nhu me theo don.
- `watch([isStable, grossWeight])` ghi moc `grossAtSave` bo dieu kien `activeJob` 鈥?khong bo thi can tay khong co moc nao de doi chieu, F5 xong khong noi lai duoc.
- CLEAR/SAVE van `clearSession()` nhu cu -> dung yeu cau "tru khi an clean".

- **BAT DUOC MOT LOI CODE CHET CUA CHINH MUC 91:** o RACK trong `VbaRackGrid` co `v-if="items[idx]"` 鈥?dong khong co vat tu **khong he render o nhap**. Nghia la `manualRacks` + `onUpdateRack` them o muc 91 khong bao gio chay duoc, can tay khong co cach nao go ma rack. Da them prop `manualRacks` + `allowManualRack` va render o nhap cho dong trong KHI dang can tay. Dong trong nam DUOI mot don da quet thi van khoa 鈥?SAVE cua me theo don khong gui chung di, cho go vao do chi tao cam giac da ghi duoc cai gi do.

**B. Tach can tay khoi moi thong ke san xuat**

- `ProductionBatch::MANUAL_BATCH_PREFIX` + scope **`khongPhaiCanTay()`** 鈥?mot cho dung chung, thay vi rai dieu kien khap noi.
- **Phan biet ro voi tien to `ADHOC-`, va CO Y khong loc ADHOC:** lo ADHOC **den tu mot tem QR that** (chi la chua khop lo nao trong Web) nen no van la viec san xuat va van phai nam trong bao cao. Lo CANTAY thi khong lien quan gi toi quet don 鈥?dung nhu nguoi dung dien dat.
- **Ap o 7 cho, khong chi bao cao** (ra soat moi noi liet ke `ProductionBatch`): `DashboardController` (4 cho: tong quan, hang cho can, may nhuom, 2 so dem), `ProductionBatchController::index`, `ReportController` (tieu hao + dung sai). De lot vao Dashboard la bang dieu khien day nhung dong trong tron.
  - `ReportController::machineOutput` **khong can sua**: no `join('machines', 'mac.id', '=', 'pb.machine_id')` ma lo can tay co `machine_id` NULL -> inner join tu loai; va no bat dau tu `feed_operations` ma can tay khong bao gio tao.
  - Bao cao dung query builder tran nen scope cua model khong ap duoc -> co ban viet tay `ReportController::loaiCanTay()`.
- **BAY DA CHAN, viet vao ca hai ban:** phai viet dang "`legacy_batch_id` NULL **HOAC** not like" chu khong `not like` tran 鈥?trong SQL `NULL NOT LIKE '...'` cho ra **NULL** chu khong ra TRUE, nen `not like` tran se **nem sach moi lo khong co ma cu ra khoi bao cao**. Hong am tham: so chi nho di chu khong bao loi. Da chot bang test rieng `test_batches_without_a_legacy_id_are_still_counted`.

**Kiem chung:**

- 2 test moi: lo can tay bi loai khoi bao cao tieu hao **trong khi lo quet don van duoc tinh** (test tim thay `DYE001` nen no doc dung cau truc phan hoi, khong phai so tren mang rong); va lo `legacy_batch_id` NULL van duoc giu.
- `php -l` 4 file sach, `vue-tsc` **0 loi**, `vite build` OK 17.07s, **33 passed (144 assertions)**, guard 8/8.
- **`ReportsTest` (12 test) hong SAN, khong phai do thay doi nay:** class do dung `DatabaseTransactions` chu khong `RefreshDatabase` nen doi DB da migrate san (Postgres) 鈥?tren SQLite in-memory no chet ngay o `User::factory()` voi `no such table: users`. Da xac minh TUNG test deu chet dung loi thieu bang do. Phan loc bao cao van duoc phu that, bang test nam trong class co `RefreshDatabase`.
- **CHUA thu tay tren trinh duyet.**

### 93. Can tay: SAVE duoc NGAY khi co so, khong bat bam NEXT truoc

- **Yeu cau (02/08/2026):** *"chi can khong nhap don, khi can lieu co chi so la save duoc"*.
- **Truoc do** muc 91-92 chi lam duong bam NEXT: `capturedWeights` rong -> `rows` rong -> SAVE choi. Nhung o **RAW** (`grossWeight`) hien so **ngay ca khi chua bam NEXT** 鈥?tho nhin thay so ma bam SAVE khong duoc.
- **Nay `dongCanTayDeLuu()` nhan CA HAI cach dung:**
  1. Bam NEXT chay nhieu o nhu can theo don -> lay cac o DA CHOT SO (nhu cu).
  2. Khong bam NEXT gi ca -> lay thang so RAW dang hien, mot dong.
- **Vi sao lay `grossWeight` chu khong `liveWeight`:** cach 2 chua he chot bi, ma `ingestRawWeight` thoat som khi chua `armed` nen `liveWeight` VAN DUNG O 0. Thu duy nhat co that la so can gop. Ghi kem `tare_weight = null` cho khop su that: khong tru bi lan nao ca.
- **Ba dieu kien phai qua, va bao dung ly do tung cai** (gop thanh mot cau chung la tho dung bam lai mai ma khong biet phai lam gi):
  - `signalLive` 鈥?so chet la con so dong cung tu lan doc cuoi truoc khi mat tin hieu.
  - `isStable` 鈥?so chua dung yen la dang do do.
  - `grossWeight !== 0` 鈥?can rong khong phai "co chi so". **Chan o day vi ban ghi tao ra KHONG XOA DUOC** (CLAUDE.md muc 3, khong xoa vat ly) 鈥?bam nham mot cai la de lai rac vinh vien.
- Doi lai dong huong dan tren man hinh: dat vat tu len can, thay so o RAW la bam SAVE duoc ngay; muon can nhieu thu thanh mot phieu thi bam NEXT cho tung thu roi moi SAVE.
- **Kiem chung:** them test `test_manual_weighing_accepts_a_single_untared_row` 鈥?payload hinh dang khac han nhanh bam NEXT (dung MOT dong, `tare_weight` = null), server phai nhan duoc chu khong duoc doi co bi. `vue-tsc` **0 loi**, `vite build` OK 16.99s, **34 passed (148 assertions)**.
- **CHUA thu tay tren trinh duyet.**

### 94. Nut SAVE van khoa `!activeJob` 鈥?toan bo duong can tay KHONG DUNG DUOC

- **Nguoi dung bao (02/08/2026):** *"nut save dang hien thi cam, toi bam duoc"* 鈥?nut xam, con tro hinh cam.
- **Loi cua chinh toi, va la loi nang nhat trong nhom nay:** muc 91-93 xay tron duong luu can tay (backend + hang doi + phieu + test) nhung **quen go dieu kien khoa o chinh cai nut goi no**: `:disabled="saving || !activeJob"`. Khong co don thi nut xam vinh vien -> **khong mot dong nao trong 3 muc do chay duoc tu giao dien**. Test backend xanh het nhung nguoi dung khong cham toi duoc.
- **Bai hoc:** test API xanh khong chung minh tinh nang **DUNG DUOC**. Duong di tu ngon tay nguoi dung toi ham do 鈥?nut, dieu kien disabled, o nhap 鈥?phai duoc ra soat rieng. Day la lan thu HAI trong phien: muc 92 cung phat hien o RACK cua dong trong khong he render (`v-if="items[idx]"`) nen `manualRacks` la code chet.
- **Sua:** `SAVE` chi con khoa khi `saving`. Ly do khong luu duoc thi `onSave` noi ro tung truong hop (mat tin hieu / chua dung yen / can rong) 鈥?**noi ra duoc van hon mot cai nut xam khong giai thich gi**.
- **Sua kem cung cho hut do:** nut `PRINT` cung khoa `!activeJob`. Nay bo khoa va `printSlip` them nhanh can tay 鈥?dung chung `dongCanTayDeLuu()` voi SAVE nen to in thu va to in luc luu khong the khac nhau. Cua so in van mo DONG BO truoc moi `await` (khong bi chan popup).
- **Ra soat lai toan bo `:disabled=`** trong file: chi con `saving` (CLEAR/SAVE), `saving || !canPressNext` (NEXT), `flushing` (GUI NGAY) 鈥?deu dung.
- **Kiem chung:** `vue-tsc` **0 loi**, `vite build` OK 16.72s. **CHUA thu tay tren trinh duyet.**

### 95. Man hinh "Can to" (/weighing-station-large) -- port workbook VBA #5, man RIENG khong dung chung V2

- **Yeu cau (03/08/2026):** *"o layout toi muon them 1 phan ten la can to co giao dien va chuc nang giong form vba nay"* (`5.Semiauto- lockmove SEND OVER6 - delta-stable-final-221.xlsm`). Nguoi dung chot ro: **"no la 1 phan khac, k lien quan den v2"**, va nut OUT/IN **"dung agent loai khac"**.

**A. Doi chieu nguon truoc khi code (source-traceability)**

- Da trich toan bo VBA cua workbook #5 va #4 bang bo doc CFB + giai nen MS-OVBA viet rieng (Excel dang mo file trong VBE nen khong dung duoc COM). Ca hai workbook: **22 module y het nhau** (2 UserForm `scaleform`/`checkform`, 16 module chuan, ThisWorkbook + Sheet1-3 rong).
- **Diff code giua #4 (can nho) va #5 (can to) chi co 3 cho, deu khong phai nghiep vu:**
  - `txt_color_AfterUpdate`: ban #4 xu ly `-dye-`/`chem` khong phan biet hoa thuong (tot hon), ban #5 phan biet hoa thuong.
  - `Mod_lockmoveform`: ban #4 co them chong rung (`Abs(...) > 1`) + kiem tra `WatchForm.Visible`.
  - GUID form.
- **Dung sai giong het:** `Mod_UI_processcolor.CheckRange` 0 dong khac -- ratio <0.99 vang / <=1.01 xanh / >1.01 do. `Mod_sendRackauto` cung **y het** o ca hai file (tuc khoi SEND OVER 6 ton tai o ca can nho, chi la V2 chua port).
- Da bao lai phat hien nay cho nguoi dung; nguoi dung van chot lam man RIENG -- lam theo quyet dinh do.

**B. Da lam**

- **`frontend/src/views/WeighingStationLarge.vue` (moi)** -- man hinh doc lap: quet QR vao o COLOR, luoi 9 dong, NEXT/SAVE/PRINT/CLEAR/CHECK/CLOSE, o DELTA co lon, thanh RAW, hang doi gui me, khoi phuc phien sau F5.
  - **Khoa localStorage RIENG `df_wslarge_session_v1`** (khong dung chung `df_ws2_session_v1` cua V2): hai man co the mo cung mot may luc kiem thu, dung chung khoa thi mo man nay se nuot mat me dang can do cua man kia.
  - **Dung chung HA TANG** voi cac man can khac (`useScaleFeed`, `VbaRackGrid`, `saveQueue`, `weighSlip`, `qrDyeParser`, `processColor`, `tsplPrint`). Day la ban port DUY NHAT cua thuat toan VBA goc (delta/bi, dung sai +-1%, bo cuc phieu, doc QR) -- chep tay lan nua la mo duong cho hai man cung can mot me ra hai ket qua khac nhau.
- **Khoi "SEND OVER 6" (rieng cua can to, V2 khong co)** -- port `Mod_sendRackauto.BuildRackBatch`: gom o RACK khac rong va khac `"0"`, don lien tuc, 6 ma dau vao LO 1, phan du vao LO 2. Nut IN don LO 2 len thanh LO 1 (dung `rackBatch1(i) = rackBatch2(i)` cua VBA), don **vo dieu kien** ke ca khi gui hong -- dung ban goc, gui va don la hai chuyen tach roi.
  - Lo rack duoc luu vao phien -- F5 giua chung khong mat thu tu rack con lai.
  - `onUpdateRack` gom lai lo khi da tung gom, neu khong khoi SEND OVER hien so cu cua lan gom truoc va tho bam OUT la **gui nham ma**.
- **`frontend/src/services/rackDispatch.ts` (moi)** -- diem tich hop DUY NHAT voi agent. VBA goc dieu khien chuot (`ClickAt 345,200` + `SendKeys "^v"` vao toa do man hinh cua app pha mau); cach do **khong port sang web duoc va vi pham ADR-002**. Web chi PHAT LENH: `POST /api/rack-dispatch` kem `idempotency_key` (rules/database-safety muc 4).
- Route `/weighing-station-large`, `ROUTE_CAPABILITY_MAP` **chi `LARGE_SCALE`** (khac 2 dong `/weighing-station*` von nhan ca SMALL lan LARGE -- co chu y, thao tac gui rack chi ton tai o khu can lon), muc menu **"Can to"** (adminOnly trong luc chay thu, vi tai khoan van hanh bi khoa cung vao 1 man theo workstation binding), tieu de man hinh trong `AppLayout.vue`.

**C. CON TON DONG -- chua lam**

- **Backend `POST /api/rack-dispatch` CHUA CO**, va **agent xu ly OUT/IN chua duoc xac dinh** ("agent loai khac", chua ro la agent nao). Hien bam OUT/IN se bao loi ro rang bang tieng Viet va chi tho dung nut **COPY** (chep LO 1 ra clipboard, dan tay sang he pha mau) -- duong thoat dung duoc ngay, khong phai nut chet.
- **CHUA thu tay tren trinh duyet.**
- **Kiem chung da chay:** `vue-tsc --noEmit` 0 loi o file moi, `vite build` OK 13.48s (chunk `WeighingStationLarge` 23.86 kB).

### 96. Can to: dung lai giao dien 1:1 theo dung toa do form VBA

- **Yeu cau (03/08/2026):** *"can to toi muon giao dien giong het trong form VBA"*.

**A. Lay toa do that 鈥?VBA project BI KHOA MAT KHAU**

- Moi duong qua Excel COM deu TREO (khong bao loi): `wb.VBProject` bat hop thoai hoi mat khau ma cua so lai vo hinh. Nguyen nhan chi lo ra khi doc stream `PROJECT`: khoa **`DPx=`** (bien the cua `DPB=`, nen lan grep dau tim `DPB=` bao "khong khoa" 鈥?sai). Workbook #4 dung `DPB=`, #5 dung `DPx=`.
- => Doc thang binary **MS-OFORMS** trong `xl/vbaProject.bin`: storage `scaleform` -> stream **`f`** (ten + Left/Top + ObjectStreamSize + ClsidCacheIndex + TabIndex) va stream **`o`** (Size + Caption + Font). Don vi goc himetric (1/100 mm), quy ve POINT.
- **Khong dung duoc spec tu tri nho** cho vung `SiteDepthsAndTypes` (ma hoa run-length) -> thay bang cach do: thu parse chuoi ban ghi tu moi offset, lay chuoi DAI NHAT. Tu kiem chung: ra dung **74 control**, moi ten deu la dinh danh hop le, va an vua khit stream.
- **Hai quy luat rut ra bang doi chieu tay 8 control du 3 loai** (khong co trong tri nho, phai suy tu chinh file):
  - Than control = `[0, 4+cb)`; moi thu sau do la ban ghi con (TextProps = font).
  - **Size (Cx,Cy) LUON la 8 byte cuoi cua than** -> offset `4+cb-8`. Caption nam ngay truoc Size.
- **Bang chung parse dung:** `txt_RACK1` @(12.02, 11.99)pt, `txt_RACK2` @(12.02, 59.98) -> buoc dong dung **48pt**, trung voi so da biet tu ban #4; `btnSAVE` left 552 + width 180 = 732 鈮?be ngang form 734.26.
- **Mot bay da mac va da go:** chuoi ASCII trong stream `o` cho ra `RACK#`, nhung CaptionLength = 4 -> caption that la **`RACK`**, ky tu `#` chinh la byte thap cua Cx (0x0423 = 1059 himetric = 30.02pt). Doc caption bang "quet chuoi in duoc" la sai.

**B. Bo cuc that (form 734.26 x 546.01 pt = 979 x 728 px)**

- Luoi 9 dong: RACK @12.02 (48pt), DYE @65.99 (186pt), WEIGHT @257.98 (132pt), PROCESS @396 (150pt); cao 44.39pt, buoc dong 48.04pt; nhan so thu tu 1..9 @6.01 rong 6pt.
- Cot phai: DEL/PRINT/CHECK @top 6; ban phim so 1-9 (48x42) @66/114/162; `0` + CLOSE @210; **OUT (90x84) + IN (84x84) @258**; CLEAR (180x57.6) @348; SAVE (180x54) @408; NEXT (180x72) @468.
- Duoi trai: COLOR(90x25.2) / MACHINE(48x25.2) @450, CODE / LV @480, **delta_rawline 384x93pt font 80.2pt**, rawline 144x30pt.
- Font: luoi va delta dung **Arial Narrow 36pt**, nut/nhan dung **Tahoma**.

**C. Da lam**

- `WeighingStationLarge.vue` viet lai template + style: khung CO DINH 979x728px, moi control dat tuyet doi theo dung so do tren, ca khung thu/phong bang **MOT phep `transform: scale()`** (ResizeObserver) -> ti le giua cac control, co chu, do day vien khong bao gio lech so voi ban VBA.
- **Hang so `C` / `HEADERS` / `NUMPAD` la ban sao cua form that 鈥?da ghi chu ro KHONG duoc chinh tay**, muon doi thi sua .xlsm roi trich lai.
- Ban phim so nay chay dung ban goc: chi go vao o **RACK** (`LastInputBox` cua VBA chi duoc dat trong `txt_rackN_Enter`).
- **Moi thu bang web KHONG co trong VBA** (den mat tin hieu, hang doi gui me, gia lap, thong bao loi, lo rack + nut COPY) day het ra **DAI NGOAI khung form** -> phan form giong het ban goc, ma tho van thay duoc may dang hong gi.
- Da bo `VbaRackGrid` khoi man nay: component do dung ti le cua workbook **#4** (RACK 48 | DYE 330 | WEIGHT 312 | PROCESS 360 pt), khong phai #5.
- Hai cho CO Y khac ban goc, deu chi them chu khong bot: vien xanh o dong dang can (9 dong giong het nhau, khong co dau thi phai do bang mat), va o delta to mau theo dung 3 mau dung sai cua o PROCESS.

**D. Kiem chung**

- `vue-tsc --noEmit` **0 loi**, `vite build` OK 13.58s.
- Doi chieu so hoc: hang cuoi luoi ket thuc @440.7pt < COLOR @450 (khong chong nhau); NEXT ket thuc @540 < cao form 546; CHECK ket thuc @731.99 < rong form 734.26.
- **CHUA thu tay tren trinh duyet** 鈥?can nguoi dung mo /weighing-station-large xem bang mat.

### 97. Can to: lam lai phan NHIN (giu nguyen bo cuc + thao tac)

- **Yeu cau (03/08/2026):** *"giao dien nay dep hon, de nhin hon"* 鈥?sau khi da xem that tren trinh duyet.
- **Bo gia kieu Windows 95** (vien outset/inset, xam #f0f0f0, Tahoma): no chi lam man hinh trong cu chu khong giup doc nhanh hon. Thay bang: vo ngoai toi, mat form la mot the sang bo goc co do do; o du lieu bo goc, vien manh; **soc chan/le** cho 9 dong (9 o trang giong het nhau thi mat rat de nhay nham dong khi liec tu can len); nut phan mau theo VAI TRO (SAVE xanh duong / NEXT xanh la / CLEAR do / OUT tim / IN xanh mong); o delta thanh tam nen DEN, chu so doi mau theo dung sai.
- **3 mau tin hieu GIU NGUYEN ma RGB goc** (`utils/processColor`) 鈥?do la thu duy nhat tren man hinh mang nghia nghiep vu.

**Da tu bat va sua 3 loi bang cach CHUP MAN HINH bo cuc that**

Trang can dang nhap nen khong chup truc tiep duoc -> dung `scratchpad/preview.mjs`: doc THANG khoi `<style>` cua component, dung lai DOM tinh voi du lieu mau **co tinh chon dai nhat co the gap** (`1024`, `BLACK-ECO-N`, `1234.75`), roi chup bang Edge headless. Ba loi chi lo ra khi nhin anh:

1. **Chu tran o, cat mat so.** Ban dau toi doi ca luoi sang Inter 鈥?sai: o RACK chi rong 48pt (64px) ma chu 36pt (48px). Ban goc dung **Arial Narrow** chinh vi ly do do. Da tra font hep ve cho o du lieu, Inter chi dung cho nhan/nut.
2. **Van con cat ngay ca voi Arial Narrow** 鈥?vi ban goc de ca 4 cot 36pt, tuc **form that cung dang cat mat ma rack 3 ky tu**. Da chinh co chu theo be rong that tung cot: RACK 26 / DYE 30 / WEIGHT 30 / **PROCESS 34 (giu to nhat, day la so mang tin hieu)**.
3. **4 o cung mot dong bi so le.** Ban goc dat DYE/WEIGHT/PROCESS thap hon o RACK 3.65pt (gan nhu chac chan do keo tha chuot), nhin ra la rang cua. Dat `ROW_OFFSET = 0` 鈥?**sai lech toa do DUY NHAT so voi ban goc, va chi 4.9px**. Nhan cot cung cho ve cung mot moc.

**Sua khac**

- **Nhan cho hang o duoi**: ban VBA de COLOR/MACHINE/CODE/LV tran trui. Them nhan vao khe 9.3pt giua day luoi va hang o (COLOR/MACHINE/DELTA); CODE/LV/RAW khong con khe nen nhan nam luon trong o.
- **Them lop `.stage-fit`**: `transform: scale()` khong doi o layout cua phan tu, nen khi phong to > 1 mat form bi tran ra ngoai vung cuon va cut mep. Lop dem mang dung kich thuoc sau khi phong.
- Chan tren cua phong to 1.6 -> 2.0, va tru padding cua vung cuon khi tinh ti le.

**Kiem chung:** `vue-tsc --noEmit` **0 loi**, `vite build` OK 13.25s. Anh chup bo cuc: `scratchpad/preview2.png`. **Chua thu tay tren trinh duyet that.**

### 98. Can to: het cuon chuot 鈥?man hinh vua DUNG MOT khung hinh

- **Yeu cau (03/08/2026):** *"toi muon cac o lam sao nhin trong 1 view ma k can cuon chuot vi qua dai"*.
- **Nguyen nhan (loi cua muc 96-97):** route nay co `requiresAuth` nen `App.vue` boc no trong **AppLayout** 鈥?noi dung nam trong `.content-container` (`flex:1; padding:24px; overflow-y:auto`), tuc DA la mot vung cuon co san va da bi thanh tren an mat mot phan chieu cao. Toi lai dat `.wsl-root { min-height: 100vh }` -> ep man hinh cao BANG CA CUA SO roi cong them thanh tren => luon dai hon mot khung hinh, phai cuon moi thay het mat form.
- **Sua:** bo `100vh`. Chieu cao nay **do bang JS** (`fitRoot`): `window.innerHeight - getBoundingClientRect().top - paddingBottom cua phan tu cha`. Dung trong moi truong hop, ke ca khi bat/tat toan man hinh hay doi chieu cao thanh tren. `.wsl-root` them `overflow: hidden` 鈥?moi thu BAT BUOC phai vua mot khung hinh.
  - `fitAll()` = `fitRoot()` roi `nextTick(fitStage)`: khong cho qua mot nhip thi `fitStage` van doc chieu cao CU va mat form bi thu nho hon muc can thiet.
  - Do lai mot nhip nua trong `requestAnimationFrame`: luc `onMounted` chay, AppLayout co the chua dung xong thanh tren nen `top` con la so tam.
  - `ResizeObserver` van gan vao `.stage-wrap` 鈥?dai thong tin / dong loi duoi cung hien roi an lam khung cao thap khac nhau.
- **Nhuong them cho cho mat form:** padding vung cuon 14 -> 8px, dai thong tin 8 -> 5px, dong loi 7 -> 5px va **chan toi da 2 dong** (thong bao dai khong duoc phep doi mat form len).
- **RACK 26 -> 24pt + le 2px:** anh chup cho thay ma rack 4 ky tu (`1024`) van sat mep o 26pt. 24pt cho ra 58.3px trong o rong 60px -> vua du 4 ky tu.

**Kiem chung bang anh chup, khong doan:** `scratchpad/preview.mjs` nay **mo phong luon khung AppLayout** (sidebar 240 + topbar 64 + `.content-container` padding 24) va chay ban sao cua chinh `fitRoot`/`fitStage`. Chup o **1920x1080** (scale 1.50) va **1600x900** (scale 1.24): toan bo mat form + dai thong tin + dong loi nam gon trong mot khung hinh, khong co thanh cuon. Anh: `preview_fhd.png`, `preview_final.png`.

`vite build` OK 13.27s. **Chua thu tay tren trinh duyet that.**

### 99. Tach lam 2 Agent / 2 bo cai doc lap: Can nho vs Can to

- **Yeu cau (03/08/2026):** *"/weighing-station-v2 va /weighing-station-large toi muon tach ra lam 2 agent, 2 bo cai k lien quan den nhau"*.
- **Hien trang truoc do:** DUNG 1 bo cai `DFAgentSetup-Scale.msi` (service `DFAgent`, thu muc `ProgramFiles\DFAgent`, ma tram `WS-SCALE-<TEN-MAY>`). Backend ghep cap trinh duyet voi Agent **theo IP nguon** (`machine_<ip>`), nen mot may chay hai Agent la ca hai ghi de len dung mot khoa cache 鈥?man Can to se hien so cua can nho.

**Cach tach: cung ma nguon, khac dung 1 khoa cau hinh `Workstation:ScaleKind` (SMALL/LARGE)**

| | Can nho | Can to |
|---|---|---|
| MSI | `DFAgentSetup-CanNho.msi` | `DFAgentSetup-CanTo.msi` |
| Service | `DFAgentSmall` | `DFAgentLarge` |
| Thu muc cai | `ProgramFiles\DFAgent-Small` | `ProgramFiles\DFAgent-Large` |
| UpgradeCode | `CD108F1A-...` (giu Guid lich su) | `2FDBACF6-...` (Guid moi) |
| Ma tram tu sinh | `WS-SCALE-<TEN-MAY>` | `WS-LARGE-<TEN-MAY>` |
| Man hinh | `/weighing-station-v2` | `/weighing-station-large` |

- **UpgradeCode khac nhau la thu quyet dinh "khong lien quan den nhau"**: dung chung Guid thi `MajorUpgrade` cua bo thu hai se TU GO bo thu nhat luc cai.
- **Bo Guid dong cung tren `<Component>`** trong .wxs, de WiX v5 tu sinh theo key path. Quy tac component cua Windows Installer cam mot Guid tro toi hai duong dan khac nhau, ma hai bo nay cai vao hai thu muc khac nhau. **Da do lai bang cach mo 2 file MSI**: 224 component moi ben, **0 Guid trung nhau**.
- `agent_cache.db` (hang doi offline) nam canh file .exe nen tu dong tach rieng theo thu muc cai, khong phai lam gi them.
- **Can nho GIU NGUYEN tien to `WS-SCALE-`** 鈥?co y. Doi tien to la moi may pilot dang chay tu sinh mot tram moi va bo lai tram cu thanh rac trong DB.
- Version nhay **2.2.0.0 -> 3.0.0.0** vi ban can nho doi ca ten service lan thu muc cai; may dang cai 2.2.0.0 phai qua MajorUpgrade de service `DFAgent` cu duoc go sach, khong de lai service mo coi tro toi thu muc da xoa.

**Sua backend 鈥?cho de khong lan so can**

- `DeviceController`: `machineKey($ip, $kind)` them hau to `_LARGE`. **SMALL khong co hau to** => khoa cu giu nguyen, tram can nho dang chay khong dut so luc deploy va khong phai xoa cache.
- `storeReading` nhan them `scale_kind` (nullable, in:SMALL,LARGE); `getReading` va `whoami` nhan `?kind=`. Thieu tham so deu ve SMALL 鈥?V1 (`/weighing-station`) va Dashboard khong truyen gi, phai chay y nhu cu.
- `AgentAuth`: tram tu dang ky voi `scale_kind=LARGE` duoc cap capability **LARGE_SCALE** + `default_route=/weighing-station-large`. Truoc do moi tram SCALE_ONLY deu ra SMALL_SCALE, tuc tram can to se **khong vao noi chinh man hinh cua no** (`ROUTE_CAPABILITY_MAP['/weighing-station-large']` doi dung LARGE_SCALE).
- `routes/web.php`: `/downloads/agent-launcher/{kind?}`. URL cu khong tham so van chay, tra bo can nho.

**Sua frontend**

- `useScaleFeed(kind)` gui `&kind=`; `adoptLocalWorkstation(kind)` gui `?kind=`. V2 khai bao tuong minh `'SMALL'`, Large khai bao `'LARGE'` (du SMALL la mac dinh 鈥?doc man nao biet ngay man do cam vao cai can nao).
- Sidebar "TAI CONG CU" gio co **2 link**: `DF Agent 鈥?Can nho` / `DF Agent 鈥?Can to`.

**Kiem chung**

- `dotnet test` agent: **35/35 pass** (them 3 test moi: chuan hoa ScaleKind, hai loai can tren cung may ra hai ma khac nhau, cau hinh cu khong co ScaleKind van giu tien to `WS-SCALE-`). Luu y: may dev khong co .NET 8 runtime (chi 3.1/9/10) 鈥?phai chay voi `DOTNET_ROLL_FORWARD=Major`.
- `build.ps1`: build **ca 2 MSI thanh cong** (28 MB moi file), da doi chieu UpgradeCode/ProductCode/ServiceName/thu muc trong chinh file MSI.
- `vite build` OK 14.13s.
- **Backend phpunit KHONG chay duoc tren may nay** 鈥?khong co PostgreSQL cong 5433 va khong co Docker, ca 12 test cu cua `ScaleLiveWeightTest` cung fail vi ly do do chu khong phai vi thay doi nay. **2 test moi (`test_hai_agent_can_nho_va_can_to_tren_cung_mot_may_khong_ghi_de_nhau`, `test_whoami_tra_dung_tram_theo_loai_can`) chua tung duoc chay** 鈥?phai chay lai o moi truong co DB test truoc khi tin.
- Da xoa artifact cu `DFAgentSetup-Scale.msi`/`.wixpdb` (da bi thay the, sinh lai duoc bang `build.ps1`).

**Con lai:** hai may tram ngoai xuong phai cai dung bo cua minh, va neu cai ca hai len cung mot may thi PuTTY phai co **2 session rieng ghi ra 2 file log khac nhau** (`Scale:LogFilePath` 鈥?ban can to mac dinh `D:\scale\putty_log_large.txt`, cong `COM2`). Trung file log la hai Agent cung doc mot cai can.

### 100. Agent day so can len NHIEU backend 鈥?mo bang localhost hay bang IP server deu nhan can

- **Trieu chung nguoi dung bao:** *"http://localhost:3001/weighing-station-v2 cai nay dang k nhan can, toi cai roi"*, sau do: *"toi muon ca 2 dia chi deu chay duoc"*.
- **NGUYEN NHAN GOC (do bang cach doc cau hinh that tren may, khong doan):**
  - Frontend suy ra host API tu **chinh URL trinh duyet dang mo**: `axios.defaults.baseURL = http://<hostname>:8500` (`main.ts:25`).
  - Mo bang `localhost:3001` => hoi backend **cuc bo** (`127.0.0.1:8500`, `CACHE_STORE=file`, cache rieng cua may).
  - Agent lai dong cung `Backend:Url = http://10.0.60.209:8500/api` => day so len **CS-SERVER**.
  - Hai kho cache tach roi => man hinh khong bao gio thay so. **Khong phai loi man hinh, khong phai loi cai dat.**
- **Da loai tru cac nghi van khac bang bang chung:** service `DFAgentSmall` dang Running, `appsettings.json` dung ban moi (`ScaleKind=SMALL`, `Id` de trong), PuTTY van ghi `D:\scale\putty_log.txt` lien tuc. Event Log cho thay loi *unreachable host 10.0.60.209:8500* luc 07:52 (mat mang tam thoi) da tu het sau khi service khoi dong lai luc 08:37.

**Sua: `Backend:Urls` (mang) 鈥?Agent day len TAT CA backend, song song**

- `Worker.ResolveBackendUrls()`: doc `Backend:Urls`; loc muc rong, cat dau `/` thua, bo trung (khong phan biet hoa thuong). Rong thi lui ve `Backend:Url` (chuoi don) 鈥?cau hinh tren may da cai **khong bi bo qua im lang** sau khi cap nhat Agent.
- `PushWeightToBackendAsync` = `Task.WhenAll` qua tat ca URL. **Song song chu khong tuan tu**: mot backend chet se giu ca luot day dung bang thoi gian cho timeout (5s), lam so can tren backend con song tre theo.
- **Chi xep vao hang doi offline khi KHONG backend nao nhan duoc.** Con mot noi nhan la so can da co cho luu; xep hang them chi tao ban ghi trung luc dong bo lai.
- **Chan spam log** (`GhiNhanTrangThaiBackend`): nhip day la 200ms, mot backend chet ma moi lan hong lai ghi mot dong canh bao la **5 dong/giay** do vao Event Log, troi mat moi thu khac dung luc can doc nhat. Chi ghi khi trang thai DOI: luc hong lan dau, va luc song lai. Nho co chan spam nay moi dam de san `127.0.0.1` trong danh sach mac dinh cua CA HAI bo cai 鈥?may tram khong chay backend cuc bo thi dia chi do chi that bai im lang (mot lan TCP refused tren loopback moi nhip, khong ton mang).
- `PackageVersion` 3.0.0.0 -> **3.1.0.0**, da build lai ca 2 MSI (28.1 MB moi file, da doi chieu ProductVersion trong chinh file MSI).

**Kiem chung:** `dotnet test` **39/39 pass** (them 4 test: mac dinh khi khong khai bao gi, `Backend:Url` don le van chay, `Backend:Urls` duoc uu tien hon, loc muc rong/dau '/' thua/dia chi trung).

**Con lai 鈥?CHUA CHAY THU THAT:** may nay dang cai ban 3.0.0.0 (chi day len CS-SERVER). Phai cai de ban 3.1.0.0 bang quyen admin moi kiem chung duoc tren trinh duyet that; da soan san script `scratchpad/tro-agent-ve-backend-cuc-bo.ps1`. **CS-SERVER van chay backend cu** 鈥?chua deploy thay doi cua muc 99-100.

### 101. Gantt: bam vao me hien them TONG SO ME ma do da chay tu dau toi nay

- **Yeu cau (2026-08-03):** bang chi tiet khi bam vao thanh Gantt phai co ca tong so me ma **m茫 m脿u - m茫 h脿ng** do da tung chay tu luc dau den gio 鈥?khac hai con so da co (so me gop lien tiep tren 1 Tank, va tong so thanh dang ve theo khoang ngay loc).
- **Backend:** `BpdbMachineMonitoringService::getLotRunTotal()` + endpoint public `GET /api/public/bpdb-machines-gantt/lot-total?color=&productCode=` (`BpdbMachineController::lotTotal`).
  - **KHONG nhet vao `/gantt`**: query quet toan bo lich su `SUP_Tasks`, chay san cho hang tram thanh dang ve la nen BPDB vo ich 鈥?chi goi khi nguoi dung that su bam mo 1 me. Cache 5 phut moi ma (`LOT_TOTAL_CACHE_TTL`), frontend cache them trong phien.
  - **Tieu chi dem** = dung tieu chi thanh Gantt: `WorkStartTime IS NOT NULL`, `IsDeleted=0`, `TaskStatus <> 99`; pham vi toan bo may VD trong registry, khong gioi han khoang ngay loc.
  - **Khop TaskTitle theo tien to CO dau cach** (`'{lot} %'` hoac bang chinh xac), khong dung `LIKE 'lot%'` tran 鈥?neu khong "RED-L1803" se dem nham ca me cua "RED-L18032". Da escape `[`, `%`, `_` trong m茫 m脿u/m茫 h脿ng truoc khi ghep vao LIKE.
- **Frontend (`BpdbMachinesGantt.vue`):** dong "Tong da chay" trong popup chi tiet (`lotTotal` + `loadLotTotal`), co trang thai *Dang dem鈥? / *Khong dem duoc (BPDB mat ket noi)*; khong cache ket qua khi BPDB rot de lan bam sau con thu lai. Me khong tach duoc m茫 m脿u/m茫 h脿ng thi an han dong nay.
- **Kiem chung that:** `vue-tsc --noEmit` exit 0; goi that endpoint tren BPDB 鈥?`EP69725-L18032` tra `total=5`, chay lan dau 12/07/2026, lan cuoi 24/07/2026; thieu tham so tra 422 `LOT_REQUIRED`. **Chua xem bang mat tren trinh duyet** (backend cuc bo chi bat tam de test roi tat lai).
- **Bo sung cung phien:** them hang **"Theo may"** trong chinh bang chi tiet cua me vua an 鈥?ma nay da chay o nhung may VD nao, moi may bao nhieu me (may nhieu nhat dung truoc), kem so may da tung chay ma nay. Query doi sang `GROUP BY Machine` roi cong don trong PHP (mot lan quet lich su ra ca tong lan phan chia, khong chay 2 query). **1 may VD co nhieu `machine_id`** (moi to hop Machine+Tank+MucNuoc la 1 dong `DyeMachines`) nen phai quy nguoc ve ma may vat ly truoc khi cong, neu khong moi machine_id se thanh mot "may" rieng. Kiem chung that: `EP69725-L18032` -> `VD003: 4`, `VD002: 1`, tong 5 (khop dung con so tong da do truoc do).

### 102. Nut "Toan man hinh" thay phim F11 + luoi C3 tu vua man hinh (2026-08-04)

- **Yeu cau nguoi dung:** `/production-batches/grid` phai tu co gian cho thay du 81 o; sau do them nut phong to thay F11 cho ca 8 man hinh van hanh.
- **`ProductionBatchesGrid.vue`:** port dung co che da dung o `PrintOrderEntry.vue` 鈥?mat form giu nguyen toa do goc 768x540pt, thu/phong bang MOT phep `transform: scale()` (khoang 30%-200%) nen ti le o/co chu/do day vien khong lech so voi ban VBA. Chieu cao vung lam viec **do that** tu mep tren phan tu (khong dat cung `100vh`) nen dung ca khi an sidebar/topbar hay F11. Dai trang thai luon hien (neu `v-if` thi luc an/hien chieu cao doi va ca luoi nhay co) va co them chi so "Vua man hinh: N%".
- **Component dung chung `components/FullscreenButton.vue`** (8 man hinh can nen tach, khong chep 8 lan): mot nut noi goc phai duoi an ca hai lop che man hinh 鈥?Fullscreen API bo thanh trinh duyet (dung phan F11 lam) + co `isFullscreen` cua AppLayout bo sidebar/topbar.
  - Nghe `fullscreenchange` de thoat bang **ESC/F11 that** thi nhan nut va menu app tro ve dung trang thai, khong bi ket.
  - `watch(isFullscreen)` dong bo nguoc voi nut thoat cua AppLayout, tranh canh menu hien lai ma thanh trinh duyet van mat. Unmount thi tat het, khong de man hinh ke tiep mat menu.
  - Prop `variant` ('vba' giu he mau Windows co dien / 'app' dung design token) va prop **`zIndex`** 鈥?moi trang mot thang z-index rieng nen khong co so mac dinh nao dung cho tat ca: man can dung 40 (duoi `.queue-overlay` 50-60), `/chemical-call/monitor` va `/pending` dung 10 (duoi anh QR phong to khi re chuot = 20), con lai 900 (duoi hop thoai VBA = 1000).
- **Da gan cho:** `/production-batches/grid`, `/print-order-entry`, `/print-sent-log`, `/chemical-call`, `/chemical-call/monitor`, `/chemical-call/pending`, `/weighing-station-large`, `/weighing-station-v2`.
- **Kiem chung:** `vue-tsc --noEmit` exit 0, `vite build` thanh cong. **Chua xem bang mat tren trinh duyet** 鈥?can nguoi dung tu mo kiem tra.

### 103. Man hinh "Bang may VD (MACHINE_ID)" 鈥?dung lai UserForm mainform cua MACHINE_ID_LOCKED.xlsm (2026-08-04)

- **Nguon:** trich VBA that tu `MACHINE_ID_LOCKED.xlsm` (27 component, VBProject KHONG khoa) qua Excel COM; toa do/font/mau doc thang tu `Designer.Controls` chu khong doan.
  - `mainform` = **man hinh chi doc**, 734 control, InsideWidth 1413pt x InsideHeight 755.25pt, font Tahoma 8.25pt.
  - Bo cuc: nua tren VD10..VD18 (trai sang phai), nua duoi VD09..VD01; moi may 1 khoi rong 138pt (code 60 + color 54 + lv 24), cach nhau 156pt, bat dau Left 18pt. Moi may co khoi **da gui** 4 thung 1A/2B/3C/4D (moi thung 1 dong code/color/lv + 1 dong thoi gian 138pt) va khoi **dang cho** 6 dong. `TextBox541` (1386x3pt, nen COLOR_HIGHLIGHT) la vach ngan 2 nua.
  - **Ma chet trong workbook:** `mainform` con nguyen handler `Box1..Box7`, `btnSAVE`, `btnClear`, `CommandButton3/4/5`, `sub1_Click..sub83_Click` nhung **cac control do khong con ton tai tren form** 鈥?ban con lai chi la bang theo doi. `col17..col19`/`sub17..sub19` nam o Top 786pt, tuc **ngoai chieu cao form 755pt** (khong bao gio hien) nen ban web khong ve lai.
- **Quy tac to mau (giu nguyen ban goc):**
  - Da gui (`Mod_load_sentlog.LoadAllVD`, `tbl_SentLog`, chi lay ban ghi moi nhat con trong 24h cho moi may+thung): <6h `#00B050`, <12h `#FFC000`, con lai `#FF0000`. O thoi gian **khong** bi to mau (ban goc chi set BackColor cho code/color/lv).
  - Dang cho (`Mod_load_input.LoadAllVD_Input`, `tbl_input_all`, TOP 6 theo TIME1 tang dan): <24h trang, <48h `#B4CDE6`, con lai `#B4A0C8`.
  - Nap lai moi **3 phut** (`Mod_time3min.RunAutoVD`).
- **Ban web `views/MachineIdBoard.vue`** (route `/machine-id-board`, nhom menu VAN HANH): dung nguyen toa do pt, thu/phong bang MOT `transform: scale()` nhu `ProductionBatchesGrid`/`PrintOrderEntry`, co `FullscreenButton variant="vba"`.
  - Nguon du lieu: `GET /api/machine-dispatches/history` (queue_state CONFIRMED) thay `tbl_SentLog`, `GET /api/machine-dispatches` (INPUT/WAITING/TO_SEND/PROCESSING/ERROR) thay `tbl_input_all`. Moc "da gui" lay print job DAU TIEN cua don 鈥?cung quy uoc voi `/print-sent-log` vi web khong co cot TIME3 rieng.
  - **Ma may phai khop theo phan so**: web seed `VD001..VD018`, ban VBA dung `VD01..VD18`.
  - Nap lai 3 phut dung nhip ban goc, co them listener Echo `production-batches` de khong phai cho het 3 phut khi co don moi.
- **Kiem chung:** `vue-tsc --noEmit` exit 0. **Chua xem bang mat tren trinh duyet** 鈥?can nguoi dung tu mo `/machine-id-board` doi chieu voi form VBA that.

### 104. 2 tai khoan rieng cho 2 tram can + thu gon nut thoat Toan man hinh (2026-08-04)

- **Yeu cau nguoi dung:** nut thoat Toan man hinh cua layout dai qua, can gon lai; va can **2 tai khoan** 鈥?mot cho "Can nho", mot cho "Can to" 鈥?dang nhap vao **chi thay dung man hinh cua minh**.
- **Nut thoat Toan man hinh (`AppLayout.vue`):** bo nhan chu, chi con dau `鉁昤 trong nut tron 32x32 (nhan day du chuyen vao `title`). Nut nay nam de o goc phai tren suot thoi gian toan man hinh nen phai chiem it cho nhat co the. **Khong dung** toi `FullscreenButton.vue` (nut noi goc phai duoi cua 8 man van hanh) 鈥?component khac.
- **2 tai khoan:** `ScaleOperatorUsersSeeder` (chay rieng: `php artisan db:seed --class=ScaleOperatorUsersSeeder`).
  - `cannho` / `cannho@123` -> tram `WS-SMALL-01` -> `/weighing-station-v2` (ban dung lai `4.semiauto-small scale.xlsm`).
  - `canto` / `canto@123` -> tram `WS-LARGE-01` -> `/weighing-station-large` (ban dung lai `5.Semiauto- lockmove SEND OVER6.xlsm`).
  - **Khong viet co che khoa moi** 鈥?noi vao co che WS-001 da co: `users.operation_client_id` -> AuthController tra kem `workstation` -> `router/index.ts` da moi route khac ve `default_route` (tai khoan khong phai ADMIN) -> `AppLayout.vue` an han sidebar va khoa nut doi tram (`isLockedStation`).
  - Seeder **tu tao luon 2 tram** neu chua co: DB dev/production duoc dung dan bang dang ky Agent theo IP, khong phai luc nao cung da chay `WorkstationsSeeder` (DB dev thuc te khong he co `WS-SMALL-01`/`WS-LARGE-01`). Phai gan du capability (`SMALL_SCALE`/`LARGE_SCALE` + WEIGH/PRINT/SCAN_QR/LOCAL_AGENT), thieu la `AppLayout` chan nham "tram khong co quyen cho man hinh nay".
  - **KHONG goi trong `DatabaseSeeder`**: `WorkstationsSeeder` xoa sach `operation_clients`/`devices` truoc khi seed lai, chay ca bo tren may dang co du lieu la mat tram. Seeder moi chi `updateOrCreate`, chay lai bao nhieu lan cung duoc (chi dat lai mat khau 2 tai khoan).
- **Sua kem:**
  - `WorkstationsSeeder`: `default_route` cua `WS-SMALL-01/02` va `WS-LARGE-01` truoc day deu tro `/weighing-station` (man quan ly tram can ban web, khong port tu workbook nao) 鈥?nay tro dung 2 man dung lai. Link kiosk `/scalesmin`, `/scalesmax` vi vay cung vao dung man.
  - `AuthController::workstationPayload()`: them `default_route` (router doc truong nay TRUOC roi moi roi ve `default_screen`).
  - `AppLayout.vue`: bo `adminOnly` o 2 muc menu "Can nho"/"Can to" 鈥?dung nhu ghi chu cu da dan (bo co nay khi `default_route` tro dung 2 route do).
- **Kiem chung that:** seeder chay tren DB dev thanh cong; goi that `AuthController::login` cho `cannho` -> tra `workstation.default_route = /weighing-station-v2`, `capability_codes` co `SMALL_SCALE`; `canto` -> `WS-LARGE-01` + `LARGE_SCALE`. `vue-tsc --noEmit` exit 0. **Chua xem bang mat tren trinh duyet**; `php artisan test` khong chay duoc (DB test port 5433 chua bat) 鈥?loi moi truong, khong lien quan thay doi nay.

### 105. Nut "Dang nhap" tren cac trang cong khai (2026-08-04)

- **Yeu cau nguoi dung:** cac trang mo khong can dang nhap (muc 0f716b3) khong co nut nao de quay ve man hinh dang nhap tai khoan 鈥?chi con cach tu go URL `/login`.
- **`NavToggleButton.vue`:** truoc day chi render nut 3 gach cho nguoi DA dang nhap, nguoi xem cong khai khong thay gi (khong co AppLayout nen cung khong co nut Dang xuat o topbar). Nay them nhanh `v-else`: nut **Dang nhap** (icon `user` + chu, cung 2 he mau `vba`/`app`, cung vi tri goc phai duoi) day sang `/login?redirect=<duong dan hien tai>`.
- **`Login.vue`:** dang nhap xong doc `?redirect=` de quay lai dung trang dang xem. Chi chap nhan duong dan noi bo (bat dau `/` va khong phai `//`) 鈥?khong de chuoi tren URL tro thanh dich dieu huong ra ngoai; khong hop le thi ve `/` nhu cu.
- Ap dung ngay cho ca 5 trang dang dung `NavToggleButton`: `/production-batches/grid`, `/print-order-entry`, `/machine-id-board`, `/chemical-call/classic`, `/chemical-call/pending-classic`. Trang Gantt (`/bpdb-machines/gantt`) co bo nut rieng, khong dung component nay 鈥?chua dong toi.
- **Kiem chung:** `vue-tsc --noEmit` exit 0. **Chua xem bang mat tren trinh duyet.**

### 106. Tach "khoa tram" khoi "an menu": tai khoan van hanh tu doi tram duoc, menu chi con cua ADMIN (2026-08-04)

- **Yeu cau nguoi dung (3 buoc lam ro trong cung 1 phien):**
  1. "Dang nhap tai khoan nao cung van chon duoc tram nhu binh thuong" -> bo khoa tram theo tai khoan.
  2. "Admin thay duoc menu, cac tai khoan khach chi thay phan ben tren" -> sidebar chi cua ADMIN.
  3. "Dang nhap can nho bay thang toi /weighing-station-v2, can to tuong tu /weighing-station-large" -> **khoa han o do**, go tay URL man khac cung bi da ve.
- **Van de goc:** `isLockedStation` (AppLayout) truoc day gom 3 viec vao 1 co 鈥?an sidebar, khoa nut doi tram, va di kem voi router guard khoa man hinh. Go 1 thu la 2 thu kia di theo. Nay tach doi:
  - `isLockedStation` = **chi phien kiosk** (mo bang link may, KHONG dang nhap). Moi tai khoan da dang nhap deu bam duoc nut tram tren topbar de doi tram. Tram gan san (`users.operation_client_id`) nay chi con la **gia tri mac dinh luc dang nhap**, khong phai rang buoc.
  - `canSeeMenu` = **chi ADMIN** (co moi) 鈥?dung cho `<aside class="sidebar">` va nut 3 gach mobile. Tai khoan khac chi con thanh tren cung (ten tram, doi tram, dang xuat).
  - Co `wsConfig.locked_to_type` (localStorage `df_workstation_config`) khong con duoc dung de khoa nua 鈥?no la cau hinh may, khong phai tai khoan.
- **Router guard (`router/index.ts`)**: **giu nguyen quy tac WS-001** 鈥?`requiresAuth && !isAdmin && lockedScreen && to.path !== lockedScreen` -> `next(lockedScreen)`. Da thu go han o giua phien (theo doc y buoc 1) roi **bat lai theo xac nhan cua nguoi dung o buoc 3**. Doi trang thai co y: doi tram la de can dung thiet bi cua minh, khong phai de di sang cong doan khac.
  - Khong cham 3 man cong khai (`requiresAuth:false`): dieu kien `requiresAuth` bo qua chung, nguoi da dang nhap van mo bang link nhu may xuong.
  - Nhanh `?ws=` va nhanh kiosk giu nguyen nhu truoc.
- **Anh huong tai lieu:** mo hinh WS-001 "1 may tinh = 1 cong doan" nay **chi con khoa MAN HINH, khong con khoa TRAM**. `workstation-redesign-audit.md` mo ta "khoa nut doi tram" da lac hau tu ban nay.
- **Kiem chung:** `vue-tsc --noEmit` exit 0 sau moi lan sua; Vite HMR nap sach, khong loi runtime trong log dev server. **Chua xem bang mat tren trinh duyet** 鈥?can nguoi dung dang nhap `cannho`/`canto` doi chieu.
- **Bo sung cung phien:** "1 tai khoan chon tram nao cung duoc" -> `capabilityMismatch` khong con chan cung noi dung voi tai khoan da dang nhap. Tach ra `blockOnMismatch = isKiosk && capabilityMismatch`: chi phien kiosk moi bi man chan (tram do LINK quyet dinh, nguoi dung may khong tu sua duoc). Doi lai, ws-pill tren topbar chuyen mau cam + icon 鈿狅笍 + tooltip khi tram dang chon sai loai 鈥?giu lai tin hieu "khong am tham ghi du lieu duoi ten tram sai loai" von la ly do khoi sinh cua man chan nay. Dropdown chon tram truoc gio VON KHONG loc theo capability, khong phai sua.
- **Giu tram da chon qua F5 (cung phien):** chon tram xong nhan F5 la mat, quay ve tram gan voi tai khoan. Nguyen nhan: `df_current_workstation` VAN luu dung, nhung 2 nguon tu dong ghi de len o moi lan nap trang 鈥?`authStore.initialize()` (chay trong `router.beforeEach`, tuc moi lan dieu huong/F5) dat lai `user.workstation`, va `adoptLocalWorkstation()` hoi backend "may nay la tram nao" theo IP. Ban than localStorage khong he mat du lieu.
  - Them co `df_workstation_manual` (`services/workstation.ts`): `setWorkstation(ws, { manual: true })` ghim tram do NGUOI DUNG tu chon; moi nguon tu dong goi khong kem `manual` thi XOA ghim.
  - Thu tu uu tien sau ban nay: **link `?ws=CODE`** (chi dinh tuong minh, thang tuyet doi) > **tram chon tay** (ghim, song qua F5) > **tram cua tai khoan luc DANG NHAP** > **whoami theo IP cua Agent**. Dang xuat xoa sach ghim.
  - `initialize()` khong con dat lai tram khi da co ghim; `adoptLocalWorkstation()` return false som khi da co ghim.
- **Bo sung cung ngay:** bang thong tin me o `/bpdb-machines/gantt` doi nhan `Tong da chay` -> **`So lan danh mau`** (cach goi cua xuong). Chi doi nhan hien thi, con so va cach dem (`loadLotTotal`, API BPDB) giu nguyen; don vi ben canh van la "me".

### 107. Bo cai CAN TO khong tu hien ra tram tai may tram 鈥?tach "bao danh" khoi "day so can" (2026-08-04)

- **Bao loi cua nguoi dung:** "Agent bo cai can lon dang khong tu [nhan tram] tai tram duoc giong nhu can be."
- **Chan doan tren may dev (bang chung truc tiep):** ca 2 service `DFAgentSmall`/`DFAgentLarge` deu Running, nhung `D:\scale\` **chi co `putty_log.txt`**, khong co `putty_log_large.txt` 鈥?dung file ma `appsettings.large.json` bao Agent can to doc. Nguoi dung xac nhan may tram ngoai xuong cung vay: chi co MOT PuTTY, ghi vao duong dan chuan cu.
- **Chuoi hong (goc re that su):** `ScaleReader` khong thay file -> tra `null` -> `Worker` **khong POST gi len backend** -> `AgentAuth` khong bao gio tao ban ghi tram `WS-LARGE-<TEN MAY>` -> `DeviceController::storeReading` khong ghi khoa `scale_machine_station_<ip>_LARGE` -> `whoami?kind=LARGE` tra `null` -> `/weighing-station-large` khong tu nhan duoc tram. Can nho chay tot chi vi no doc dung file PuTTY dang co san. **Loi kien truc:** su ton tai cua mot TRAM bi buoc vao tinh trang cua cai CAN.
- **Sua 1 鈥?Agent BAO DANH doc lap voi so can (`Worker.cs`, `DeviceController::hello`, `routes/api.php`):** them `POST /api/devices/hello` (middleware `agent.auth`), Agent goi luc khoi dong va moi **60 giay**, gui `workstation_id` + `role` + `scale_kind` + `machine_name` len TAT CA backend trong danh sach. Endpoint chi ghi cap MAY->TRAM (`scale_machine_station_*`, TTL 12h) va goi `ensureScaleDeviceAttached()` (tach ra tu `storeReading`, dung chung) 鈥?**tuyet doi khong dung vao 3 khoa so can**, vi bao danh khong phai bang chung can dang song. Ket qua: cai xong la tram hien ra, con "chua co tin hieu can" quay ve dung ban chat cua no 鈥?mot canh bao rieng tren man hinh (`has_reading`/`age_ms`), khong con lam tram bien mat.
- **Sua 2 鈥?buoc lui file log cho ban CAN TO (`ScaleReader.cs`, `appsettings.large.json`):** them khoa `Scale:LogFilePathFallback` = `D:\scale\putty_log.txt`. File rieng `putty_log_large.txt` van uu tien **tuyet doi**; chi khi no khong ton tai moi lui ve file chuan, va moi lan lui deu ghi mot dong canh bao (throttle 30s): neu may do chay ca 2 Agent thi hai ben dang doc CHUNG mot cai can. Ung vien duoc tra lai moi lan doc chu khong chot luc khoi dong 鈥?mo PuTTY rieng sau do la Agent tu chuyen ve file rieng, khong can restart service.
- **Ban cai:** `PackageVersion` 3.2.0.0 -> **3.3.0.0**, da build lai ca 2 file MSI va copy sang `backend/public/downloads/`.
- **Kiem chung:** `dotnet test` Agent **44/44 pass** (them 5 test cho `ScaleReader.ResolveLogFilePaths`, chay bang `DOTNET_ROLL_FORWARD=LatestMajor` vi may nay khong co runtime .NET 8). Da them 2 test backend (`test_hello_dang_ky_tram_can_to_khi_chua_doc_duoc_so_can_nao`, `test_hello_tach_rieng_theo_loai_can_tren_cung_mot_may`) nhung **CHUA CHAY DUOC**: `.env.testing` tro toi Postgres `127.0.0.1:5433` khong chay tren may nay, con `.env` dev tro thang vao DB **production** 鈥?khong chay test ghi du lieu vao do. Moi chi `php -l`. **Chua kiem chung bang mat tren may tram that.**
- **Thu tu trien khai bat buoc:** deploy **backend truoc**, roi moi cai MSI moi. Nguoc lai thi `/devices/hello` tra 404 鈥?khong gay hong gi (Agent chi ghi mot dong canh bao va chay y nhu cu), nhung tram van chua hien ra.

### 108. `/production-batches/grid`: nut TANK khong chon duoc thung 鈥?bo loc theo may sai voi VBA goc (2026-08-04)

- **Bao loi:** tai `http://10.0.60.209:3001/production-batches/grid` khong chon duoc thung "nhu trong Form VBA".
- **Doi chieu nguon goc** (giai nen `xl/vbaProject.bin` cua `2.C3 grid load row lock id FB -192(QR).xlsm` bang cong cu tu viet 鈥?OLE `StgOpenStorage` + giai nen MS-OVBA, xem ghi chu duoi):
  - `mainform.CommandButton3_Click` (nut TANK): do vao ListBox mot **mang CO DINH** `Array("1A","2B","3C","4D","FB")`, roi `Set f.TargetTextBox = Me.Box5`. **Khong lien quan gi toi may dang chon.**
  - `mainform.CommandButton5_Click` (nut MACHINE): tuong tu, mang co dinh `VD01..VD18` -> Box4.
  - `btnSAVE_Click`: chi bat buoc **Box1 (mau) + Box2 (ma hang)**. Thu tu chon may/thung tuy y.
- **Sai o ban port:** `tankPickerOptions` loc `tanks.value.filter(t => t.machine_id === machineId)` voi `machineId` lay tu Box4. Box4 mac dinh RONG -> danh sach rong hoan toan, khong mot dong nao, khong thong bao gi 鈥?nhin y het man hinh hong. Phai chon MAY truoc moi hien duoc thung, mot rang buoc **khong he co trong ban goc**.
- **Sua (`ProductionBatchesGrid.vue`):** `tankPickerOptions` tra ve **danh sach ma thung phan biet (distinct) cua toan danh muc**, khong loc theo may 鈥?dung tinh than ban goc. Van uu tien danh muc that (them loai thung trong Master Data la co ngay), chi lui ve mang cung `TANK_CODES_VBA` khi danh muc chua nap duoc (mat API) 鈥?dung bang viec ban goc luon co san 5 thung ke ca khi mat ket noi. Template doi sang lap theo ma (`string`) thay vi doi tuong tank.
  - `confirmTankPick` nhanh **SubForm** nay phai tu quy ma thung -> `tank_id` theo may CUA DON DO (`t.machine_id === subFormBatch.machine_id && t.code === ...`), vi danh sach khong con loc san theo may. Khong quy duoc thi bao ro thay vi de `tank_id = null` im lang (nut PHE DUYET se bi khoa ma khong ai biet vi sao).
  - Header (`currentTank`) giu nguyen: van quy theo may + ma, vi SAVE cua ban web bat buoc co may (rang buoc rieng cua ban web do `tank_id` la khoa ngoai theo tung may 鈥?khac VBA von luu thung la chuoi tu do; **chua doi**, neu muon giong het VBA thi phai cho `machine_id` null trong DB, la mot quyet dinh khac).
- **Kiem chung:** `vue-tsc --noEmit` exit 0. API production `GET /api/public/tanks` tra dung 18 may x 5 ma (`1A/2B/3C/4D/FB`) va `/machines` tra 18 may 鈥?**du lieu khong thieu, loi thuan tuy o tang lo giao dien**. **Chua xem bang mat tren trinh duyet** va **chua deploy**.
- **Cong cu moi (scratchpad, khong commit):** trinh giai nen VBA tu viet (`vbadump`) doc thang `vbaProject.bin` -> tung module `.bas`, khong can Excel/python/oletools va khong bi chan boi VBA project bi khoa mat khau. Hai cho de sai da vap phai: `BitCount = Max(4, CeilingLog2(so byte da giai nen TRONG CHUNK))` (sai la van "giai nen" duoc nhung ra rac lap lai), va cham diem ung vien offset phai theo **so dong PHAN BIET** chu khong phai tong so dong (rac lap lai se thang).

### 109. "Trang nao cung cham" tren may dev 鈥?DbHostResolver probe 127.0.0.1 het gio 2 GIAY moi 20 giay (2026-08-05)

- **Bao loi:** "du lieu day tu DB ra Web load cham qua". Hoi ro va nguoi dung xac nhan: cham o **MOI trang** (Gantt/BPDB, hang cho san xuat, goi hoa chat), va cham tren **may dev** (localhost:3001/8500).
- **Do that truoc khi doan** (`Invoke-WebRequest`, moi endpoint nhieu lan):

| Duong | min | tb | max |
|---|---|---|---|
| `/api/khong-ton-tai` (404, **khong cham DB**) | 318 | 1019 | 2988 |
| `/api/public/chemical-channels` (3 truy van) | 553 | 686 | 861 |
| `/api/production-batches` (401, chan truoc DB) | 273 | 332 | 450 |

  So dat gia nhat la **404 khong cham DB ma van 318-2988ms** -> chi phi KHONG nam o truy van, nam o duong boot cua moi request.
- **Boc tach boot** (script rieng trong scratchpad): `vendor/autoload` 10ms, `bootstrap/app.php` 3ms, `make(HttpKernel)` 47ms, **`DbHostResolver::resolve()` khi cache het han = 2,044ms**.
- **Nguyen nhan goc:** `config/database.php:90` goi `DbHostResolver::resolve()`. `config/` duoc nap lai tren MOI request (khong `config:cache`), nen ke ca route 404 cung tra phi nay. Cache file cua resolver TTL 20 giay -> cu moi 20 giay lai co 1 request phai probe lai. Ung vien dau danh sach la `127.0.0.1` (`DB_HOST_CANDIDATES=127.0.0.1,10.0.60.209,192.168.250.151`).
- **Gia dinh cu SAI, da do lai de bac bo:** ghi chu 2026-08-02 trong chinh file do viet "probe truot van tra ve ngay". Do lai bang script probe rieng: `fsockopen('127.0.0.1', 5433, ..., 2.0)` tra **errno 10060 = HET GIO**, khong phai 10061 = bi tu choi. Goi tin bi firewall **nuot** chu khong bi da ve, nen moi lan probe truot **dot tron 2.0 giay**. Do 3/3 lan giong nhau; `localhost` va `192.168.250.151` cung vay.
- **Sua (`app/Services/DbHostResolver.php`):** `array_unshift` host trong `DB_HOST` len dau danh sach ung vien (+ `array_unique`). DB_HOST luon la host chu dich cua chinh moi truong dang chay, nen phat probe dau tien gan nhu luon trung 鈥?khong con phai di qua ung vien chet. Danh sach ung vien giu nguyen vai tro du phong. Khong doi TTL, khong doi timeout, khong dong vao `.env`.
- **Ket qua do lai sau khi sua:**

| Duong | min truoc -> sau | tb truoc -> sau |
|---|---|---|
| `DbHostResolver` (cache het han) | 2044 -> **51 ms** | 鈥?|
| `/api/khong-ton-tai` (404) | 318 -> **21 ms** | 1019 -> **164 ms** |
| `/api/public/chemical-channels` | 553 -> **251 ms** | 686 -> **414 ms** |
| `/api/production-batches` | 273 -> **45 ms** | 332 -> **181 ms** |

  Chay 40 request lien tiep sau khi sua: khong con dot gai 2 giay dinh ky nao (chi request dau tien 2178ms do opcache nap lai file vua sua).
- **Phan CON LAI khong phai loi, la ban chat may dev** (do bang script PDO rieng): mo ket noi PDO toi Postgres **~100-112ms MOI request** 鈥?da loai tru SSL (`sslmode=prefer` 96.8ms ~ `disable` 104.4ms, khong khac); moi truy van **~25-30ms** di-ve (ICMP ping chi 9ms nhung vong di-ve giao thuc PG la 25-30ms). Vay san cua 1 endpoint don gian tren may dev = boot ~70ms + ket noi ~100ms + N x 27ms. **Tren CS-SERVER DB nam cung may nen phan nay gan nhu bang 0** 鈥?day la ly do may dev luon cham hon server that.
- **Do them ve dong thoi:** 1 request `/api/public/chemical-channels` = 550ms; 6 request cung luc = 1482ms (khong phai 3300ms neu don luong hoan toan, cung khong phai 550ms neu song song hoan toan) -> `php artisan serve` co xu ly chong lan nhung **van xep hang mot phan**.
- **Kiem chung:** `php -l` sach. **Chua chay `php artisan test`** (test suite se xoa schema tren DB that 鈥?cam chay o may nay). **Chua deploy len CS-SERVER.**
- **Can luu y khi deploy:** neu `.env` cua CS-SERVER dat `DB_HOST=10.0.60.209` thi sau thay doi nay server se probe LAN IP truoc thay vi loopback (van chay dung, chi khac duong di). Muon giu uu tien loopback tren server thi dat `DB_HOST=127.0.0.1` trong `.env` cua CS-SERVER 鈥?`.env` bi gitignore nen phai sua tay tren server.
- **Chua lam, de nguoi dung quyet:** (1) ket noi PDO persistent 鈥?bo duoc ~100ms/request nhung co rui ro ro ri trang thai giua cac request; (2) thay `php artisan serve` bang web server that (IIS/nginx + php-fpm) 鈥?da ghi nhan tu muc 38 la viec nen lam truoc Cutover; (3) dung ban sao Postgres cuc bo tren may dev de bo han 25-30ms/truy van.

### 110. Gantt BPDB: me chua ket thuc luon nam SAU vach gio hien tai + dong nhat chieu cao dong (2026-08-05)

- **Yeu cau:** (1) me moi/chua co gio ket thuc **luon nam ben phai vach thoi gian**, duoc phep day cac me cung hang lui ve trai; don da hoan thanh thi don ve phia truoc; (2) dinh dang lai khoang cach cac dong cho dong bo kich thuoc.
- **Sua 1 鈥?thuat toan xep me (`fetchGantt`, `BpdbMachinesGantt.vue`):** goc toa do doi tu "me som nhat cua Tank" sang **kim do** (`syncSnapshot`, dung mot moc voi `calculateNeedle`). Tach me theo **trang thai** (khong theo thu tu trong mang 鈥?du lieu BPDB co truong hop me dang chay bat dau som hon mot me da xong cung Tank):
  - Luot 1: me DA XONG xep **nguoc** tu kim do lui ve qua khu, giu do dai va khoang cach toi thieu `MIN_VISUAL_DURATION_MS`; me khong du cho bi day sang **trai** (truoc day day sang phai).
  - Luot 2: me DANG CHAY xep **xuoi** tu kim do ve tuong lai; be rong = thoi gian da chay duoc (me vua mo = 2.5h toi thieu).
  - Luot 3: lap khoang trong trong tung nhom; me da xong cuoi cung chi keo **toi dung kim do** va **chi khi Tank do that su co me dang chay** (Tank da nghi thi giu do dai that, khong ve keo dai toi hien tai gay hieu nham la con ban).
  - Bang chi tiet (bam vao thanh) van lay gio THAT tu `itemDetails` 鈥?chi vi tri VE bi dich.
- **Sua 2 鈥?chieu cao dong dong deu:** truoc day vis-timeline tinh chieu cao hang theo **me dang lot khung nhin** nen Tank khong co me nao trong khung gio hien tai co lai bang chieu cao nhan, con **tu doi moi khi cuon ngang**. Chot cung ca 3 yeu to: `ITEM_HEIGHT=24` ep bang CSS (`box-sizing: border-box` de vien 1px cua me thuong va vien 2px cua me dang chay ra cung chieu cao), `margin: {axis:5, item:{horizontal:0, vertical:10}}` khai bao tuong minh, va **`groupHeightMode: 'fixed'`** (tinh theo TOAN BO me cua nhom). `ROW_HEIGHT = 5+24+5 = 34px` khop dung cong thuc `Group._calculateHeight` cua thu vien.
  - Hang **May VD** va hang **Tank rong** khong chua me nao nen chieu cao cua chung = `clientHeight` cua chinh `.vis-inner` -> phai ep `height: 34px` len `.vis-inner`. Vi `.vis-inner` cua hang Tank **dang la** vien thuoc (pill), phai tach doi: `.vis-inner` = o chua trong suot cao co dinh, ten tank boc trong the con `.gantt-tank-pill` (them vao `applyMachineFilter`, the `span` da co san trong `whiteList` xss). Bo `position:absolute + translateY(-50%)` cu.
- **Kiem chung:** `vue-tsc --noEmit` exit 0, `npm run build` OK. **Chay lai dung thuat toan tren du lieu API that** (`/api/public/bpdb-machines-gantt`, 7 ngay, 1078 ban ghi -> 610 thanh sau gop, 4 me dang chay): 0 me dang chay nam truoc kim do, 0 me da xong vuot kim do, 0 cap de len nhau, 0 thanh be rong am. **Chua xem bang mat tren trinh duyet** va **chua deploy**.

### 111. Gantt BPDB: bo han hang rieng cua May VD, an may VD001 (2026-08-05)

- **Yeu cau:** bo "dong dang ngan cach voi cac may" (da hoi lai va nguoi dung chon: **hang ten May VD** 鈥?hang khong chua thanh me nao, chi tao mot dai trong ngan giua cac cum) va **an may VD001**.
- **Sua 1 鈥?bo group cha (`applyMachineFilter`, `BpdbMachinesGantt.vue`):** chi nap **hang Tank** vao vis; group cha (`nestedGroups`) van duoc backend tra ve va van giu trong `allGroups` (can de biet TEN may va de o tim kiem khop theo ten may) nhung khong con duoc ve. O ten may gop nay nam de len chinh **hang Tank dau tien** cua may, keo dai xuong het cum.
  - `className: 'gantt-machine-head'` gan cho hang do -> CSS `z-index: 6` chuyen tu `.vis-nesting-group` sang `.vis-label.gantt-machine-head`. **Bat buoc giu** 鈥?day la fix bug ve sai cua Chromium (ten may bien mat khi cuon toi may chua tung vao khung nhin, 2026-07-29), khong phai hieu ung UI.
  - `capNhatOGopMay()`: moc phan cum doi tu class `.vis-nested-group` sang **su co mat cua o gop** (`.gantt-machine-merged`) o hang ke tiep.
  - Ghim may: tru 100000 vao order cua **toan bo hang Tank** cua may (truoc kia chi tru vao 1 hang cha) -> ca cum cung nhay len dau.
  - CSS: `.vis-nested-group .vis-inner` -> `.vis-label .vis-inner` (khong con class nao mang `.vis-nested-group`); `font-weight: 700` chuyen sang `.gantt-machine-name`; the may lay lai 11px be ngang (`left: 15px` -> `4px`) vi khong con mui ten dong/mo cua vis o dau hang.
  - **DANH DOI da chap nhan:** mat mui ten dong/mo gon tung may (tinh nang do gan lien voi `nestedGroups`). Ghim may + tim may van nguyen ven.
- **Sua 2 鈥?an VD001 (`BpdbMachineMonitoringService::buildGanttTimeline`):** hang so `GANTT_HIDDEN_MACHINES = ['VD001']`, bo qua ngay trong vong lap dung group. Loc o **buildGanttTimeline** chu khong o `getMachineRegistry()`: danh muc may con dung chung cho man hinh trang thai may va cho phan dem "So lan danh mau theo may" 鈥?an o danh muc se mat luon ca nhung cho do, vuot pham vi yeu cau. Vi machine_id cua VD001 khong vao `$machineIdToTankGroup` nen task cua no cung khong duoc query, khong ton bang thong.
  - **CON TON DONG, can nguoi dung quyet:** phan "So lan danh mau / Theo may" trong bang chi tiet (`getLotRunTotal`) **VAN dem ca VD001** vi dung nguyen registry. Neu muon an luon o do thi noi de sua tiep.
- **Kiem chung:** `php -l` sach, `vue-tsc --noEmit` exit 0, `npm run build` OK. Goi API that: groups tu **132 -> 126**, **khong con VD001**, may dau danh sach la VD002. Mo phong lai buoc dung `renderedGroups` tren du lieu that: **101 hang ve** (het hang cha), **25 o ten may** dung bang so may, **0 hang dau cum sai vi tri**, **0 may thieu ten**. **Chua xem bang mat tren trinh duyet** va **chua deploy**.

### 112. Tram CAN TO: tach bo cai IN/OUT rieng 鈥?Agent chay trong phien nguoi dung (2026-08-05)

**A. Van de phat hien khi ra soat "IN/OUT da dung duoc chua"**

- Chuoi phan mem cua SEND OVER 6 da noi du tu truoc: man hinh gom lo rack -> `POST /api/rack-dispatch` -> bang `rack_dispatch_commands` -> Agent poll `/agents/{ws}/rack-commands` -> `RackSender` mo phong chuot -> ack. Nhung **khong chay duoc tren may that**, vi 3 diem:
  1. **CHAN THAT SU 鈥?session 0 isolation.** MSI cai Agent lam Windows Service `Account="LocalSystem"`. Tien trinh service nam o **session 0**, tach biet voi phien dang nhap: `SetCursorPos`/`mouse_event`/`keybd_event` ban vao desktop cua session 0, va clipboard cung la clipboard rieng cua window station do 鈥?ung dung pha mau ben phien nguoi dung **khong bao gio nhan duoc gi**. Te hon: `SendOut()` van tra `true` (dat clipboard thanh cong) nen Agent ack DONE trong khi thuc te khong dan duoc ma nao = **bao thanh cong gia**. Excel VBA lam duoc chinh vi Excel chay trong phien nguoi dung.
  2. `Rack.Enabled = false` mac dinh -> Agent khong he poll lenh rack sau khi cai.
  3. Web bao "Da gui ... sang he pha mau" ngay khi `POST` tra 201 鈥?tuc moi chi **xep hang**, chua doc trang thai DONE/FAILED ma Agent ack ve.

**B. Da lam 鈥?nguoi dung chot "tach thanh 2 bo cai rieng biet cho can to"**

- **Bo cai thu ba `DFAgentSetup-CanTo-InOut.msi`** (`appsettings.large-inout.json`, thu muc `DFAgent-Large-InOut`, UpgradeCode `AC5DC759-...`): **khong cai service**, thay bang shortcut o **Startup (All Users) + Start Menu**, chay bang chinh tai khoan dang dang nhap. Cai CHONG LEN bo `DFAgentSetup-CanTo.msi` tren cung may: bo cu lo **nhan can** (van la service), bo moi lo **IN/OUT**.
  - Bien tien xu ly moi **`RunMode`** (`service` | `session`) trong `DFAgentSetup.wxs` bao quanh `ServiceInstall`/`ServiceControl` va 2 component shortcut. Da build thu ca hai che do va **kiem tra bang trong MSI**: ban `session` KHONG co bang `ServiceInstall`, CO bang `Shortcut` (2 dong); ban `service` nguoc lai 鈥?dung y do.
  - **`Role = RACK_ONLY`** -> `_scaleEnabled`/`_printEnabled` deu false, chi con bao danh 60 giay + vong lay lenh rack. `Scale:Source = PUTTY_LOG` (khong mo cong COM, tranh gianh cong voi bo nhan can), `ReadIntervalMs` 10 -> 250ms.
  - **Dung CHUNG ma tram voi bo nhan can**: `Workstation:Id` de trong + `ScaleKind = LARGE` -> ca hai deu ra `WS-LARGE-<TEN MAY>`. Bat buoc phai trung, vi man hinh gui lenh theo ma tram no dang dung (`whoami?kind=LARGE`); lech ma la lenh xep vao hang doi khong ai lay.
- **`AgentAuth`: them `RACK_ONLY` vao `$roleDefaults`, tro cung mot bo mac dinh voi `SCALE_ONLY`.** Neu khong: may nao bo IN/OUT bao danh TRUOC thi tram sinh ra voi `type = AUTO_REGISTERED`, khong co capability `LARGE_SCALE`, va trinh duyet khong vao noi `/weighing-station-large` (`ROUTE_CAPABILITY_MAP` doi dung capability do) 鈥?`firstOrCreate` chi gan capability luc TAO nen sua sau phai vao Quan ly Workstation bam tay.
- **`OfflineQueue`: `agent_cache.db` chuyen tu canh file .exe sang `%LOCALAPPDATA%\DF Local Agent\<thu muc cai>\`.** Bat buoc: bo IN/OUT chay bang tai khoan cua tho, ma tai khoan thuong **khong ghi duoc vao Program Files**. Lan chay dau tu chep kho cu sang cho moi neu co.
- **Bao ket qua THAT thay vi bao thanh cong luc xep hang:** them `GET /api/rack-dispatch/{id}`; `services/rackDispatch.ts` hoi lai moi 700ms trong toi da 12 giay (Agent poll 2s + mot luot OUT ~2.5s thao tac chuot) roi bao 4 truong hop khac nhau: **DONE** = "He pha mau da nhan...", **FAILED** = "Agent KHONG thuc hien duoc...", **PENDING het gio** = "Agent tren may tram CHUA lay lenh 鈥?kiem tra Agent con chay khong", **SENT het gio** = "da nhan lenh nhung chua bao xong". Man hinh hien "Dang gui... cho Agent xac nhan" trong luc cho (nut xam 12 giay ma khong co chu thi tho tuong may treo va bam lai).
- Them muc tai ve thu ba o sidebar + `routes/web.php` (`/downloads/agent-launcher/large-inout`).

**C. CON TON DONG**

- **Toa do RPA chua hieu chinh tren may that** 鈥?6 o + 4 diem click trong `appsettings.large-inout.json` la toa do MAN HINH TUYET DOI cua may hieu chinh goc. Phai do lai tren chinh may can to truoc khi cho tho dung that.
- **Chua kiem tra migration `rack_dispatch_commands` da chay tren CS-SERVER chua** (probe endpoint bi chan quyen trong phien nay). Neu chua chay thi `POST /api/rack-dispatch` loi 500 ngay tu buoc dau.
- **Chua build lai 3 file MSI** va **chua deploy** (MSI phai copy sang `backend/public/downloads/` + server tai ve cong 8501).
- **Chua chay unit test cua Agent**: may dev chi co .NET 10 runtime, project test nham net8.0 -> `dotnet test` abort ("missing Microsoft.NETCore.App 8.0.0"). Day la thieu runtime san co tu truoc, khong lien quan thay doi nay.
- **Kiem chung da chay:** `dotnet build DFAgent.csproj -c Release` 0 loi; `wix build` thanh cong ca 2 che do + doi chieu bang trong MSI nhu tren; `vue-tsc --noEmit` exit 0; JSON ca 2 file appsettings parse sach; `build.ps1` parse sach. **Chua thu tay tren may that.**

### 113. Tem in qua trinh duyet: dung lai 1:1 theo sheet DF_WEIGHING_SLIP + Mod_printslip cua 3.DF028 (2026-08-05)

**A. Cau hoi goc:** "tem in o /print-order-entry da giong het trong 3.DF028 formulas - PRINTER LANDSCAPE - jit qr sending - 15l special.xlsm chua?" -> **Chua**. Doi chieu bang cach bung workbook va giai nen truc tiep `xl/vbaProject.bin` (script CFB + RLE tu viet, lay MODULEOFFSET tu stream `dir`) de doc **VBA that trong chinh file DF028**.

- `Mod_printslip.PrintSlip_70x100` cua DF028 giong hoan toan ban `Mod_printslip_full.txt` dang dung lam nguon port, **tru mot diem: khong goi `SetupSlipPage`** - in thang bang page setup luu san trong sheet.
- Page setup that (doc tu `xl/printerSettings/printerSettings2.bin`, DEVMODE): may in **TSC TE200**, kho giay tuy bien **72.6 x 97.5 mm** (paperSize=256), vung in `$B$1:$H$24`, `fitToPage=1`, chi `horizontalCentered` (KHONG can giua doc). => Excel in ra noi dung **56.7 x 96.2 mm can giua ngang**, chua trang ~7.9mm moi ben. Ban web cu ve tran 66.85 x 95.5mm nen chu be ngang hon ~16%.
- Sheet `DF_WEIGHING_SLIP` cua DF028 va cua "Copy of Copy of DF002 no formulas..." **giong het nhau** ve o/kieu/vien (chi khac du lieu mau con sot), nen nguon do bo cuc truoc day van dung - cai lech la o cach dung lai.

**B. Cac lech da phat hien va da sua (`frontend/src/utils/dispatchSlipPrint.ts` viet lai toan bo)**

- **Hinh hoc:** khong con toa do dot TSPL tu tinh. Lay thang do rong cot B..H (78/68/51/11/69/76/44 px), chieu cao dong 1..24 tu `sheet2.xml`, roi ep vua 1 trang **54%** dung cong thuc fit-to-page cua Excel, dat trong `@page 72.6mm 97.5mm`, can giua ngang, dinh mep tren.
- **Vien:** ve tren dung gridline bang phan tu rieng (`LineSet`, khu trung), khong de moi o tu ve border - neu khong 2 net canh nhau cong lai thanh duong day gap doi (dung loi "vien to de chu" 2026-07-30). Do day = **0.125mm = 1 dot** o 203dpi (dung "thin" cua Excel), doi o mot hang so `BORDER_MM`.
- **Bo cuc lay lai dung ban goc:** o Mau va Ma hang **tach lam 2 khung, co duong ke giua, chu can TRAI**; **khong** con vien bao quanh ca tem; **co** khung chu nhat quanh 2 vung QR; dong B24 khong vien va cho chu tran sang phai; chu can duoi (Excel mac dinh bottom), rieng dong 1 can giua doc.
- **Font:** Calibri dung co that (12/20/14/36/16pt sau khi nhan 54%), **chi in dam dung o Excel in dam** - go het `font-weight:700` toan cuc va cac lan tang co chu cho may in nhiet hoi 07-31.
- **QR:** kich thuoc theo dung cong thuc VBA (`Min(rong,cao vung B16:D22) * 0.8` = ~15.2mm; QR che do = `cao G1:H1 * 0.95` = ~12.8mm), va dat **errorCorrectionLevel 'L'** cho trung so module voi QR cua api.qrserver.com ma VBA goi (van sinh noi bo, khong goi API ngoai - CLAUDE.md muc 5).
- **Noi dung QR - 3 loi that:**
  1. **qrFB thieu du lieu:** ban cu chi co `mau-ma hhmm`, trong khi VBA con noi tiep toan bo cap (ma dye + khoi luong) roi (ma chem + khoi luong). Backend `QrPayloadService` lam dung tu truoc -> tem in qua Local Agent va tem in qua trinh duyet **khac nhau** o mode FB.
  2. **qrChem lay sai cot:** VBA ghi `Cells(r,"F")` = **rack/vi tri**, ban cu ghi ma hoa chat (cot G).
  3. **dyesProcess quet sai cot:** VBA quet cot F tim "0574"/"0507", ban cu quet ma hoa chat.
- **Go cac lech co chu y truoc day:** dong trong xen giua cac truong cua `qrChem`/`qrProcess` (2026-07-22) tra ve **1 CRLF** dung VBA.
- **Routing D1/B24:** port dung phep **so sanh CHUOI** cua VBA (`f3Val >= "VD06" And f3Val <= "VD13"`), khong con so sanh so. An toan vi danh muc may da ve 2 chu so VD01..VD18 (commit fcbbf9b) - neu sau nay doi lai 3 chu so thi cho nay se lech, phai sua cung luc.
- Tach `splitVbaTriples` rieng (giu phan tu rong, chi nhan bo ba day du, toi da 9) thay vi dung `utils/rackParser.ts` (co loc rong -> lech cot khi chuoi tho co dau "-" thua).

**C. Kiem chung**

- `vue-tsc --noEmit` exit 0.
- Dung du lieu mau con luu trong chinh sheet DF028 (HS51457 / T6206 / VD06 / 4D / 50) chay ham dung tem roi render bang Edge headless: khu D1 ra **JIT2** va B24 ra **THUNG SAT THAP, MAY JIT, MAY DLG** - **trung y het gia tri con luu trong sheet**, tuc port routing dung. Print-to-PDF cho MediaBox 205.92 x 276 pt = dung 72.6 x 97.5mm; anh chup o ty le in that cho noi dung can giua ngang, cao 96.2mm, khong bi cat.
- **Chua thu in tren may TSC that.** Rui ro can theo doi: chu trong bang gio la 12pt thuong (~2.29mm, khong dam) va duong ke chi 1 dot - dung nhu Excel, nhung neu driver dither lam mo/rang cua thi nang `BORDER_MM` len 0.25mm. Neu Chrome co trang (tem nho hon ~5%) thi ha `BROWSER_FIT` xuong 0.955.
- **Chua dong bo backend:** `QrPayloadService::buildTsplLabel70x100` va `buildChemPayload` (tem TSPL do Local Agent in) **van giu bo cuc/payload cu** - hai duong in dang co y khac nhau, cho nguoi dung quyet co keo backend ve dung VBA khong.


---

### 114. Hang doi cua /weighing-station-v2: them nut BO ME, va nut THU LAI phai bao ket qua that (2026-08-05)

**A. Yeu cau nguoi dung:** *"o phan neu k day duoc o hang cho toi muon co nut de bo ban day, va phai dam bao rang khi an thu lai phai day duoc, k thi phai bao loi ra"*.

**B. Hai loi that trong ban cu**

1. **Khong co duong bo me.** `saveQueue.ts` co y cam han (dieu kien 3, 2026-08-02): da in phieu thi bat buoc phai len server. Thuc te UAT cho thay me hong vinh vien (quet nham, lo da dong) nam lai chan man hinh va bat tho nhin chi bao do mai mai.
2. **Nut THU LAI khong noi duoc no lam gi.** `onThuLai` chi `thuLai(key)` (xoa co loi) roi goi `dayHangDoi()` **khong await**. Co do tat ngay lap tuc nen nhin nhu da gui xong, trong khi: (a) neu dung luc nhip tu dong dang chay thi `flushing=true` -> `dayHangDoi` **return ngay, khong gui gi ca**; (b) neu gui that ma van hong thi cung khong hien gi.

**C. Da sua**

- `services/saveQueue.ts`:
  - `guiMot` doi tu tra chuoi sang tra `{ kq, message }` - co nguyen van ly do hong (4xx lay `data.message`; 5xx/timeout/mat mang co cau chu rieng cho tung ca).
  - Them `guiMotNgay(key)`: gui **dung mot me**, giu cung co `flushing` voi nhip tu dong, tra `{ ok, message }`.
  - Them `boMe(key)` + nhat ky `df_ws2_da_bo_v1` (localStorage, giu 100 me gan nhat, kem payload + loi cuoi). **Khong xoa trang**: luc me vao hang doi la phieu da in ra giay, bo ma khong de lai gi thi to phieu do thanh vo chu. Khong co duong tu dong bo - chi ham nay, do nguoi bam.
  - Cap nhat dieu kien 3 o dau file cho khop quyet dinh moi.
- `views/WeighingStationV2.vue`:
  - Nut **THU LAI** hien cho MOI me (khong chi me bi server che) va nay `await guiMotNgay`, hien ket qua that; nut **BO ME** (kieu `.danger`) co hop xac nhan neu ro hau qua.
  - Bang bao ket qua `.queue-msg` (xanh/do) cho ca THU LAI / BO ME / GUI NGAY, tu tat sau 8s, xoa khi dong bang.

**D. Kiem chung**

- `vue-tsc --noEmit` exit 0; `vite build` thanh cong (chi con canh bao chunk size co san).
- **Chua thu bang tay tren trinh duyet** voi mot me thuc su dang ket - can nguoi dung mo /weighing-station-v2 kiem tra 2 tinh huong: bam THU LAI luc mat mang (phai hien do "Khong ket noi duoc may chu") va luc co mang (phai hien xanh va me bien khoi bang).
- `/weighing-station-large` dung chung `saveQueue.ts` nen huong loi thay doi o service, nhung **giao dien bang hang doi cua man do chua sua** (van chi co THU LAI kieu cu) - de nguyen theo dung pham vi yeu cau.


---

### 115. Hop chon MACHINE/TANK o /production-batches/grid bi cat khi phong to (2026-08-05)

**A. Yeu cau nguoi dung:** *"phan chon machine va tank dam bao hien thi phai tu thich ung khi phong to thu nho de k bi khuat khi phong to"*.

**B. Loi that**

Mat form chinh da co co che thu/phong (`fitStage`, scale 0.3-2.0) nhung **4 hop thoai thi khong**: chung van dat cung kich thuoc pt goc. Hop chon MAY (`formselect2`) cao 470pt ~ 627px, nen khi phong to trinh duyet (Ctrl +) hay man hinh thap thi nut OK nam ngoai vung nhin thay va **khong bam duoc** - nen cung khong chon duoc may.

**C. Da sua** (`views/ProductionBatchesGrid.vue`)

- Them `modalFit(wPt, hPt)`: do `window.innerWidth/innerHeight` (hop thoai nam tren nen `position: fixed` nen khong gian dung duoc la ca cua so, khong phai vung cuon cua mat form), chua le 16px, tra ve style cho 2 lop - lop ngoai `.vba-modal-fit` mang kich thuoc SAU khi scale de canh giua khong lech, lop trong giu nguyen toa do pt goc roi `transform: scale()`. Cung cach lam va cung bien do 0.3-2.0 voi mat form.
- `fitModals()` goi trong `fitAll()` nen an theo dung cac nhip do san co: resize, ResizeObserver khung ngoai, bat/tat toan man hinh.
- Ap cho ca 4 hop thoai (MACHINE, TANK, SubForm, CHECK) de hanh vi dong nhat.
- `.vba-modal-backdrop` them `overflow: auto` + padding lam chot chan cuoi neu cua so con be hon ca muc thu nho toi thieu 30%.

**D. Kiem chung**

- `vue-tsc --noEmit` exit 0.
- **Chua xac minh bang mat tren trinh duyet** - can nguoi dung mo /production-batches/grid, bam MACHINE roi Ctrl+ vai nac de xac nhan nut OK van nam trong man hinh.

### 115. Tem can 55x35mm in ra "chu nho qua, vo chu, khong nhin thay gi" - HAI loi cong lai (2026-08-05)

**A. Trieu chung:** nguoi dung in tem tu `/weighing-station-v2`, chu qua nho va vo net, khong doc duoc. Xay ra ngay sau commit e03c0c8 sang cung ngay (doi tem tu 76x130mm sang 55x35mm va "giam co chu").

**B. Do that thay vi doan.** Dung `buildSlipTspl` that sinh TSPL mau 9 dong, dung lai DUNG chuoi HTML ma `tsplPrint.ts` sinh ra, roi chup bang Edge headless o `--force-device-scale-factor=2.1167` + `--window-size=209,133` -> anh 440x280 px = **dung so dot that cua may in 203dpi**. Anh cho thay chu cao chua toi 6 dot.

**C. Hai nguyen nhan, deu la loi that**

1. **`FONT_DOT_HEIGHT` trong `utils/tsplPrint.ts` ghi nham chieu RONG thanh chieu CAO.** Bang cu `{1:8, 2:12, 3:16, 4:24, 5:32}` chinh la CHIEU RONG cua cac font TSC (font 1 = 8x12, 2 = 12x20, 3 = 16x24, 4 = 24x32, 5 = 32x48). Moi chu in qua trinh duyet vi the nho hon y dinh cua lenh TSPL ~1.5 lan, tu 2026-07-30 den nay. Da sua ca `tsplPrint.ts` lan `components/LabelPreview.vue` (hai noi phai trung nhau, neu khong ban xem truoc khac ban in).
2. **Bo cuc tem dung font "1" (o chu 8x12 dot = 1mm) cho CA BANG.** Sua duoc loi 1 thi chu cao 1.5mm - van be. Da dung lai bo cuc cho font "2" (12x20 dot ~ 2.5mm, cao gap doi ban cu):
   - 4 dong dau MAU/HANG/MAY/MUC gop con 2: mau + ma hang thanh MOT dong font "3" (3mm), duoi la "MAY: .. MUC: ..".
   - Cot STATUS: ACCEPTED/REJECTED/PENDING/MANUAL -> **DAT/LECH/CHO/TAY** (8-9 ky tu chiem gan 1/3 be ngang tem 55mm).
   - So can: bo dau phay hang nghin va chu "g" lap o tung dong; don vi ghi mot lan o tieu de cot "MT(g)"/"TT(g)".
   - Dong "In luc:" o chan phieu chuyen len goc tren ben phai (dong nay truoc chiem tron mot hang).
   - Can tay (khong quet don) nay ghi thang "CAN TAY" o dong tieu de thay vi de trong.
   - Ngan sach: 2 dong dau + tieu de bang = 80 dot, 9 dong x 21 = 189 -> 272/280 dot. Cot DYE CODE 138 dot ~ 9-10 ky tu (da noi them sau khi anh render cho thay "YELLOW4GL" cham cot MT).

**D. Kiem chung**

- `node frontend/scripts/check-weigh-slip.mjs`: **8/8 PASS** - ban PHP (`WeighingJobController::buildSlipTspl`) va ban trinh duyet (`utils/weighSlip.ts`) ra chuoi y het nhau sau khi sua ca hai.
- Anh render lai o dung 203dpi: 9 dong + phan dau nam gon trong 55x35mm, khong cot nao de len cot nao.
- `vue-tsc --noEmit` exit 0; `vite build` thanh cong; `php -l` sach ca 3 file PHP da sua.
- **CHUA in tren may TSC that** - can nguoi dung in thu 1 tem de xac nhan.
- Da sua cac assert bam vao bo cuc cu (`ACCEPTED`/`PENDING`/`MAU: `/`In luc:`) trong `WeighFromQrIdempotencyTest` va `ScaleCheckerAndPrintSlipTest`. **Khong chay duoc test suite o may dev** (.env tro DB production, `RefreshDatabase` se xoa schema) - da doi chieu bang tay tung assert voi chuoi TSPL that do script o tren sinh ra.
- `/weighing-station-large` dung CHUNG `buildSlipTspl` nen tem cua man do cung to len y het - khong phai sua rieng.

### 116. "Lien tuc bao mat tin hieu Agent, nhap nhay du dang nhan" tren may tram khac (2026-08-05)

**A. Nguyen nhan GOC (agent/Worker.cs) - mot backend cham giu luon nhip day cua backend con lai**

- Cau hinh mac dinh cua CA 3 bo cai (`appsettings.small/large/large-inout.json`) deu co **2 backend**: `http://10.0.60.209:8500/api` va `http://127.0.0.1:8500/api`. May tram khong chay backend cuc bo -> URL thu hai luon hong.
- `PushWeightToBackendAsync` goi `Task.WhenAll(...)` cho ca 2 URL, va vong lap chinh giu **MOT** handle `pushInFlight` chung. Nghia la luot day ke tiep phai cho backend **CHAM NHAT**: neu mot URL bi firewall DROP (khong tra RST) thi moi luot treo den 5 giay (timeout HttpClient) -> so can chi len backend con song **1 lan moi 5 giay** thay vi moi 200ms. Man hinh doc `age_ms` de bao mat tin hieu (nguong 1500ms) nen no **nhap nhay dung nhu nguoi dung mo ta, du so van ve**.
- Tr峄?tr锚u: ghi chu ngay tren ham do noi ro "song song chu khong tuan tu vi mot backend chet se giu ca luot day" - nhung cai **cong nhip** o vong lap lai buoc chung dinh vao nhau lan nua.
- **Da sua:** moi backend mot handle rieng (`Dictionary<string, Task> pushInFlight` + `DaySoCanToiCacBackend`), backend nao ranh thi day backend do, khong cho nhau. `nextPushAt` chi doi khi thuc su co it nhat 1 backend nhan luot. Hang doi offline chuyen sang `XepHangOfflineNeuMatHet` (chi ghi khi MOI backend deu dang hong). `_backendOk` doi sang `ConcurrentDictionary` vi cac luot day nay hoan tat tren nhieu luong.

**B. Nguyen nhan PHU (frontend) - dung MOT nguong cho hai viec khac han nhau**

- `STALE_READING_MS = 1500` vua la **cong an toan** (khong cho chot bi/tinh delta/luu bang so cu) vua la **den bao** cho nguoi nhin. Nhung mot khoang tre thoang qua > 1.5s la chuyen thuong (backend `php artisan serve` MOT tien trinh, 2 may tram x 5 poll/giay + 5 push/giay la du xep hang) -> canh bao do nhay lien tuc.
- **Da sua:** them `LOST_SIGNAL_MS = 3000` va co rieng `signalLost` (bat khi qua 3s khong co so moi, tat NGAY khi co so moi). Man hinh dung `signalLost` cho: den do, chu "MAT TIN HIEU", bo mau o DELTA. Con `signalLive` (1.5s) **giu nguyen y het** cho cong an toan: chot bi, cho phep SAVE, ghi phien.
- Them `readingAgeMs` va hien "(so can cu X.Xs)" ngay trong dong canh bao - de phan biet "Agent chet han" (so tang vo han) voi "Agent song nhung so ve cham" (dung quanh vai giay), hai thu nay sua o hai cho khac nhau.
- Ap cho ca `/weighing-station-v2` lan `/weighing-station-large` vi hai man dung chung `useScaleFeed`.

**C. Kiem chung**

- `dotnet build` agent: 0 warning, 0 error. `dotnet test`: **44/44 pass** (phai dat `DOTNET_ROLL_FORWARD=LatestMajor` vi may dev chi co .NET 9/10, khong co runtime 8).
- `vue-tsc --noEmit` exit 0; `vite build` thanh cong.
- **Chua thu tren may tram that.** Phan A chi co tac dung sau khi **cai lai MSI** (hoac sua tay `C:\Program Files\DFAgent*\appsettings.json` + restart service) - `appsettings.json` tren may da cai ghi de mac dinh trong code.
- Neu sau khi cap nhat van nhap nhay: doc con so "(so can cu X.Xs)" tren man hinh. Tang deu -> Agent/PuTTY/day can chet that. Dung quanh 2-4s -> backend dang xep hang (nut that `php artisan serve` mot tien trinh, xem muc 64), hoac may do van con mot backend URL hong.

### 117. Build lai bo cai Agent 4.1.0.0 + tach dia chi backend theo tung may tram (2026-08-05, chieu)

**A. Dinh chinh muc 116.** O muc 116 toi viet nhu the URL chet `127.0.0.1:8500` la thu phong gay treo 5 giay moi luot day. **Noi qua**: ket noi toi cong DONG tren loopback bi tu choi NGAY LAP TUC (RST), khong het gio. Cai treo 5 giay chi xay ra khi goi tin bi DROP im lang (firewall), khong phai truong hop nay. Loi ghep chung `Task.WhenAll` van la loi that va van dang sua - nhung tren may tram that, **nguyen nhan chinh nhieu kha nang la do tre cua chinh backend con song**, do duoc o muc 100: 1 request 550ms, 6 request cung luc 1482ms (`php artisan serve` co xu ly chong lan nhung van xep hang mot phan).

**B. Phat hien moi tu nguoi dung: HAI may tram vao giao dien bang HAI dia chi khac nhau.**
- May CAN NHO -> `http://10.0.60.209:3001`
- May CAN TO  -> `http://192.168.250.151:3001`
- Xac nhan voi nguoi dung: hai dia chi la **CUNG mot may chu** (CS-SERVER, 2 card mang) -> cung mot Laravel, cung mot kho cache. Khong phai hai backend.
- Do tu may dev (10.0.17.38): `10.0.60.209:8500` mo TCP duoc; `192.168.250.151:8500` **khong co duong toi** tu day (dai mang rieng duoi xuong).
- Vi sao van quan trong du cung mot server: (1) may can to nam CHUNG dai 192.168.250.x voi server, day so qua 10.0.60.209 la di vong qua router - duong dai hon, de sinh tre dot bien, ma tre dot bien chinh la thu lam nhay canh bao; (2) ban IN/OUT lay lenh rack tu **Urls[0]**, dat sai la man hinh bao "Agent CHUA lay lenh" du Agent van chay.

**C. Da lam**
- `appsettings.small.json`: Urls = chi `10.0.60.209:8500` (bo 127.0.0.1).
- `appsettings.large.json` + `appsettings.large-inout.json`: Urls = chi `192.168.250.151:8500` (bo 127.0.0.1, doi sang dia chi cung dai voi may tram).
- Ghi ro trong comment cua tung file: vi sao bo 127.0.0.1, khi nao phai them lai (cai Agent len may CO chay backend), va y nghia dac biet cua Urls[0].
- `DFAgentSetup.wxs`: PackageVersion 4.0.0.0 -> **4.1.0.0** kem ghi chu ly do (khong doi ten service/thu muc/UpgradeCode nen cai de len la xong).
- Build lai ca 3 MSI (`build.ps1`, WiX 5.0.2), da copy sang `backend/public/downloads/`.

**D. Kiem chung**
- 3 file MSI moi: LastWriteTime 16:09 (sau ban sua Worker.cs luc 15:34), doc nguoc ProductVersion tu bang Property cua tung file: **4.1.0.0** ca 3.
- 3 file appsettings: ConvertFrom-Json chay sach (khong hong cu phap sau khi sua tay), Urls dung nhu tren.
- `dotnet build` + 44/44 unit test da chay o muc 116.
- **VAN CHUA thu tren may tram that** - can cai 4.1.0.0 len may can nho va may can to roi cho nguoi dung xac nhan. Neu con nhay: doc con so "(so can cu X.Xs)" tren man hinh (them o muc 116).

### 118. Man hinh moi /qr-printer (alias /copower-print) — dung lai UserForm `scaleform` cua "QR PRINTER-send to access- NEW 9ROWS BIG QR.xlsm" 1:1 (2026-08-06)

- **Duong di cua yeu cau:** ban dau nguoi dung chi link toi `/production-batches/grid` va noi "in tem Copower.xlsm". Toi da dung xong ban port workbook **"in tem Copower.xlsm"** — roi nguoi dung **dinh chinh: nham file**, thu can la **"QR PRINTER-send to access- NEW 9ROWS BIG QR.xlsm"**, va yeu cau **xoa het lam lai tu dau**. Da xoa 3 file cua ban Copower (`CopowerPrintForm.vue`, `copowerSlipPrint.ts`, `copowerRackMap.ts`) va dung lai.
- **Bay da mac va da tranh:** co HAI ban cua workbook nay. Ban trong `Danny\Danny\` (22/06/2026) **CU HON** ban o goc repo (06/08/2026, nguoi dung vua dat vao). Toi trich nham ban cu truoc, phat hien khi so LastWriteTime + SHA256. **Luon dung ban o goc repo.** Khac biet that giua 2 ban: (1) o mang `Z:` -> `W:`, (2) them nut **CLEAR WEIGHT**, (3) `btnSend_Click` da BO COMMENT dong `btnClear_Click` -> gui xong la form tu xoa trang.

**A. Trich nguon — VBA project khoa mat khau that (`DPB=`)**

- Khac workbook Copower (da bi go san bang thu thuat `DPx=`), workbook nay khoa bang `DPB=` that -> `wb.VBProject` se hoi mat khau va treo.
- **Cach go (chi lam tren BAN SAO trong scratchpad, khong dung file goc):** doi 1 byte `DPB=` -> `DPx=` trong stream PROJECT cua `xl/vbaProject.bin`, nen lai thanh .xlsm moi. Khi mo, VBE bat 2 hop thoai *"contains invalid key 'DPx'. Continue Loading Project?"* (Yes) va *"Unexpected error (40230)"* (OK) roi nap project **khong con khoa**.
- 2 hop thoai do vo hinh voi automation -> chay `scratchpad/dismiss.ps1` song song: EnumWindows tim cua so lop `#32770` thuoc tien trinh EXCEL, tim nut `&Yes`/`OK` roi `PostMessage BM_CLICK` **dung vao handle nut do** (khong SendKeys toan cuc). Con phai dat `Application.FileValidation = 0` truoc `Workbooks.Open`, khong thi Office File Validation treo ngay o buoc mo file.
- Da lay: **26 module**, **toan bo Left/Top/Width/Height + Font + BackColor/BackStyle cua 82 control tren 4 UserForm**, va sheet `semi` (doi chieu: **giong het** sheet `semi` cua workbook Copower).

**B. Form that (scaleform): 588.75 x 549 pt**

- Header: `txt_COLOR`(6,6,96,25.2 — nen COLOR_INFOBACKGROUND) / `txt_MACHINE`(108,6,48,25.2) / `btnMachine`(162,6,54,24) / `btnCLEAR`(222,6,132,60) / **`btnSend`(360,6,156,60)** / `btnClose`(522,6,60,30); hang 2: `txt_CODE`(6,42,96,25.8) / `txt_tank`(108,42,48,25.2) / `btnTank`(162,42,54,24) / `btnPrint`(522,36,60,30); hang 3: `txt_LV`(6,72,48,25.2) / `btnCheck`(522,66,60,30); **`btn_clearWeight`(246,90,96,24)**.
- Hai bang 9 dong bat dau tu T=114 (Copower bat dau tu T=84 vi khong co hang `txt_LV`/CLEAR WEIGHT).
- **Top cua 3 cot trong CUNG mot dong lech nhau vai phan tram point** (dong 3 khoi hoa chat: 210 / 210 / 209.95) — do keo tha chuot. Da chep nguyen 6 mang hang so, KHONG dung cong thuc `114 + i*48`.
- Font: o RACK **Arial Narrow 14.25 dam**, o DYE/WEIGHT/chem **Arial Narrow 36 dam**, COLOR/CODE/LV Tahoma 16.2, MACHINE/tank/cac nut nho Tahoma 12, SEND/CLEAR Tahoma 36, nhan Tahoma 7.8.
- Mau (doc bang `GetSysColor`, moi control `BackStyle = Opaque` nen hien that): **SEND + vach ngan + 9 nhan so thu tu = COLOR_HIGHLIGHT #0078D7**, CLEAR/machine/tank/print/check/CLOSE = COLOR_SCROLLBAR #C8C8C8, **CLEAR WEIGHT = COLOR_ACTIVECAPTION #99B4D1**.
- 3 form phu: `formselect1` (chon may, 120.75x573.75, ListBox Calibri 22, VD01..VD18), `Formselect2` (chon thung, 158.25x246, Calibri 25, 1A/2B/3C/4D/FB), `checkform` (441x659.25, them nut **"check time sent"** ma ban Copower khong co).

**C. Nghiep vu da port**

- `txt_color_AfterUpdate`: **BAT BUOC co tu khoa `-DYE-` / `-CHEM-`** moi do duoc 2 khoi — **KHONG co nhanh du phong "lay tu vi tri thu 5"** nhu workbook Copower. Dung vay vi tem do chinh no in ra luon co 2 tu khoa nay (vong quet khep kin).
- `FillBlock`: to xanh `RGB(200,255,200)` khi `Val(rack) <> 0`, vang `RGB(255,255,180)` khi rack = 0; `Exit For` giua chung thi dong do KHONG duoc to mau. Khoa 4 o header, focus `txt_RACK9`.
- `FillDyeRack`/`FillRack` -> tra rack tu sheet `semi`, **khong tim thay thi ghi "0"** (ban Copower xoa trang) — va "0" chinh la gia tri lam o bi to VANG.
- `btn_clearWeight_Click`, `btnClear_Click`, `btnClose_Click`.
- `btnPrint_Click`: phieu A1:C19 Calibri 19 ke khung toan bo + QR o cot E/dong 4 canh 288pt; QR = `#COLOR-CODE-MACHINE-LV-DYE[-rack-dye-weight]...-CHEM[-rack-code-weight]...` (du 9 dong, **loc theo o RACK khac rong**). May in goc `vn-ld047\TSC TE200`.
- `btnSend_Click`: kiem tra may VD01~VD18 va thung 1A/2B/3C/4D/FB (giu nguyen ca noi dung thong bao loi), dung `rawqrdye`/`rawqrchem` = cac bo ba `rack-code-weight` noi bang "-" (**loc theo o MA khac rong** — khac dieu kien cua QR), cat "-" thua o cuoi.

**D. Anh xa SEND sang he web**

- Ban goc `INSERT INTO tbl_tosend` thang vao Access. Trong he web `tbl_tosend` = hang cho **DA DUYET** (`machine_dispatches`), nen tuong duong la TAO don roi DUYET ngay: `POST /api/public/production-batches` + `POST .../{id}/approve` — dung 2 buoc ma /production-batches/grid lam khi o confirm2 = "OK".
- **Vi vay don gui tu day hien o luoi TO_SEND cua `/print-order-entry`, KHONG nam trong 81 o "cho duyet" cua `/production-batches/grid`.**
- Ban goc ghi thang nen khong can khoa ngoai; ban web phai doi chieu ma may/thung voi danh muc that de lay id — ma hop le theo VBA ma chua co trong danh muc thi bao loi ro thay vi tao don mo coi.
- Duong `btnSend` cua VBA **khong kiem tra trung** (chi `Exists_ColorCode` o duong tbl_input_all) -> goi API voi `confirm_duplicate: true` de giu dung hanh vi.
- `checkform`: nut CHECK -> `GET /api/public/scale-measurements/checker` (port san tu truoc); nut "check time sent" -> `GET /api/public/machine-dispatches/history`.

**E. Da lam**

- `frontend/src/views/QrPrinterForm.vue`, `frontend/src/utils/qrPrinterSlip.ts`, `frontend/src/utils/qrPrinterRackMap.ts` (moi).
- Route **`/qr-printer`** kem **alias `/copower-print`** (giu lai vi do la duong dan nguoi van hanh dang mo san tren may xuong), muc menu "QR PRINTER (9 dong)", tieu de man hinh trong `AppLayout.vue`.
- **`backend/routes/api.php`: them `GET /public/scale-measurements/checker`** — CHI DOC, de man hinh khong dang nhap dung duoc nut CHECK. Ban co `auth:sanctum` o nhom duoi van giu nguyen.

**F. Doi soat PHAN IN voi Excel that (them 06/08, sau khi nguoi dung hoi "phan in da giong het chua")**

Ban dau phan in dung **uoc luong** hinh hoc (chieu cao dong tinh bang cong thuc, be rong cot de trinh duyet tu co). Da thay bang **so DO THAT**:

1. **`scratchpad/measure_slip.ps1`** — cho Excel chay lai DUNG chuoi thao tac cua `btnPrint_Click` tren workbook trang roi doc nguoc hinh hoc:
   - chieu cao MOI dong 1..19 = **24.75pt**; qrTop = `Cells(4,5).Top` = **74.25pt**; qrWidth = `Range("E1:J1").Width` = **288pt**; cot D..J = 48pt.
   - AutoFit voi bo du lieu mau: **A=101.25 B=85.5 C=77.25pt** (tong 264pt), qrLeft = **312pt**.
   - **Moi o deu HorizontalAlignment = xlGeneral** -> Excel canh PHAI cho so, canh TRAI cho chu.
   - Chuoi tu TextBox bi Excel **DOI THANH SO**: B6 "450", A9 "1", C9 "1234.75" deu tra ve kieu number (mat so 0 dau, bo so 0 thua sau dau phay). `Format(Now,"HH:nn")` bi nhan ra la GIO -> luu serial 0.4576, canh phai.
2. **`measure_text.ps1` + `measure_num.ps1`** — rut ra cong thuc AutoFit bang thuc nghiem (khong lay tu tai lieu):
   `be rong cot (px) = be rong chu do bang canvas (px) + 10.4 [+ 7 neu la SO co phan thap phan]`
   - Chuoi 'W','WW','WWWW','W'x8 -> 33/55/100/191px => moi W ~22.6px, dem ~10.4px (KHONG phai 5px nhu toi doan luc dau).
   - Cap text/so cung noi dung: 1234.75 -> 96px(text)/103px(so); 98.5 -> 56/63; 7.125 -> 69/76 (deu +7). So NGUYEN (0,1,12,450,10000000) +0.
3. **Da sua code theo so do:** `ROW_H_PT = 24.75` (hang so do duoc thay cho cong thuc), ham `autoFitColumnsPt` do chu bang canvas o dung font Calibri 19pt roi ap cong thuc tren, `<colgroup>` + `table-layout: fixed` de chot be rong, va **mo hinh dinh dang General** (`generalCell`/`textCell`/`timeCell`) quyet dinh canh trai/phai + chuyen doi so.
4. **Doi soat cuoi bang chinh code that** (`esbuild` bundle `qrPrinterSlip.ts` roi chay trong Edge headless, `scratchpad/slip_preview.html`):

   | Hang muc | Excel | Web | Lech |
   |---|---|---|---|
   | Bang A1:C19 | 264.00 x 470.25 pt | 263.85 x 471.43 pt | 0.15 / 1.18 pt |
   | Canh anh QR | 288.00 pt | 287.99 pt | 0.01 pt |
   | QR left | 312.00 pt | 311.84 pt | 0.16 pt |
   | QR top | 74.25 pt | 74.24 pt | 0.01 pt |
   | Cot A/B/C | 101.25 / 85.50 / 77.25 | 101.55 / 85.46 / 75.62 | +0.30 / -0.04 / -1.63 |

   Lech con lai la do Excel do be rong chu bang GDI (lam tron buoc nhay glyph ve so nguyen pixel) con canvas dung so le — cong don tren chuoi nhieu chu so. **Tong lech 1.37pt tren 264pt = 0.5%**, sau khi ep vua tem 63.5mm thi ~0.35mm, tuc ~3 dot cua may in nhiet 203dpi.
5. **Loi that da bat duoc nho buoc nay:** trong template literal cua trang in toi viet chu thich co dau backtick (`` `table-layout: fixed` ``) -> dong chuoi som, file khong build duoc. `npx vue-tsc ... | Select-Object -First 15` van bao exit 0 vi **pipe lam hong `$LASTEXITCODE`** — chi `esbuild` moi lo ra. Tu nay kiem tra build phai doc dung ma loi, khong doc exit code qua pipe.

**F5. Doi soat rieng CHUC NANG SINH QR voi VBA (06/08, nguoi dung hoi "in QR da giong VBA chua")**

1. **Chuoi payload: KHOP 1:1** — doi chieu tung dong `scaleform.frm` (btnPrint_Click, dong 332-363) voi `buildSlipQrData`: dau `"#"` + 4 truong header KHONG trim (dung `.Value` tho), 9+9 dong chi lay khi o RACK khac rong, moi dong trim, khoi luong rong -> `"0"`, hai moc `-DYE` / `-CHEM` co dinh.
2. **Tham so QR: KHOP** — `mdQRCodegen.GenerateQRCode` goi URL **khong co tham so nao ngoai `size`**: `?size=300x300&data=<urlencode UTF-8>`. Vay qrserver dung mac dinh cua no: **ecc = L**, **qzone = 0** -> dung bang `errorCorrectionLevel:'L'` + `margin:0` trong code web.
3. **Anh QR: KHONG dam bao giong** (da do bang `scratchpad/qr_mode_check.js`, chay chinh package `qrcode` cua frontend):

   | payload | `qrcode` tu chon che do | ep BYTE thuan |
   |---|---|---|
   | 74 ky tu | version 3 = 29x29 o | version 4 = 33x33 o |
   | 164 ky tu | version 6 = 41x41 o | version 8 = 49x49 o |

   Thu vien `qrcode` tach chuoi thanh doan alphanumeric + byte nen ra ma NHO HON. **Khong kiem chung duoc qrserver chon che do nao** vi CLAUDE.md muc 5 cam goi API do. Noi dung giai ma ra van y het, moi may quet deu doc duoc ca 2 che do (ISO 18004), va it o hon = moi o to hon tren tem 63.5mm = DE quet hon o 203dpi. **Co y KHONG ep byte** de "giong anh" vi se lam ma to ra va kho quet hon.
4. **Sua 2 chu thich SAI trong `qrPrinterSlip.ts`:**
   - Chu thich cu hua "dat ecc L + margin 0 de so module trung anh qrserver tra ve" -> **sai**, so module co the khac; da thay bang so do that o tren.
   - Chu thich cu dan `mdQRCodegen.CleanString` -> ham do **chi co o workbook A** (`vba/mdQRCodegen.bas` dong 12), workbook B dua thang qrData vao `URLEncode_UTF8`. Da ghi ro va giai thich vi sao `trimSpaces` con lai vo hai (chuoi luon bat dau `#`, ket thuc bang `-CHEM` hoac khoi luong da Trim).
   - Kiem chung: `vue-tsc --noEmit` **exit 0**, `npm run build` **exit 0** 18.00s.

**F9. LOI CHAN CA ME: "The rows.0.tare_weight field must be at least 0" (06/08)**

Nguoi dung: can xong o tram khong day duoc, server tra loi tren.

**Nguyen nhan:** `tare_weight`/`gross_weight` bi rang `numeric|min:0` o **6 cho / 2 controller**:
- `WeighingJobController::weighItem` (2), `::weighBatch` (2) — `/weighing-station`, `/weighing-station-v2`
- `ScannerController` nhanh rows (2) — `/api/scanner/weigh-from-qr`

Nhung hai truong nay la **SO DOC THO cua mat can**, ma mat can AM la chuyen binh thuong: nhac vat ra khoi dia la tut duoi moc 0, va bi duoc chot **TU DONG** o lan doc on dinh dau tien sau NEXT (`useScaleFeed.ingestRawWeight`) nen roi dung vao luc do. Chan lai lam **hong CA ME** — mat het 9 o da can — chi de bao ve mot truong thuan audit.

Chinh file do da ghi ly do bo `min:0` cho `weight` ("can cong don tren cung 1 dia, net co the lech am") nhung 2 truong bi/gop thi quen — cung mot lop loi, sua thieu cho.

**Bi am con la thu lam NET DUNG, khong phai so rac:** dia rong troi ve -0.5, do 10.0 vao thi mat can hien 9.5, net = 9.5 - (-0.5) = 10.0. Chan bi am la chan dung co che bu troi. => sua validation, **frontend khong doi gi**.

- Da bo `min:0` o ca 6 cho, ghi chu thich dai o `weighItem` va tro toi tu 2 cho con lai.
- Cot DB `decimal(18,6)` co dau, luu so am duoc — khong can migration.
- Them test `test_weighing_accepts_negative_tare_from_a_drifted_scale` (bi -0.5 / gop 9.5 / net 10.0, kiem ca `scale_measurements` luu dung so am chu khong kep ve 0).
- `php -l` sach ca 3 file. **CHUA CHAY TEST** — `.env` may dev tro thang vao DB production 10.0.60.209, chay test la `RefreshDatabase` xoa schema `app` that (xem memory `never_run_tests_on_dev_machine`). Test nay phai chay tren server.

**F8. Tem can: chu qua nho + bi VO + chua sat mep (06/08, vong 2 sau F7)**

Nguoi dung: "da quay ngang nhung van chua sat mep tem => noi dung qua nho, bi vo". Ba nguyen nhan doc lap, sua ca ba trong `SCRIPT_TU_IN` (`utils/slipPrint.ts`), VAN khong dung payload -> khong sua PHP:

1. **BI VO: dung `transform: scale()` de thu nho.** Chrome rasterize lop co transform o do phan giai MAN HINH roi moi phong len do phan giai may in -> chu nho ra ro/vo. Doi sang **`zoom: k`** (bo tri lai, chu ve thang o co da co -> net sach); `transform` chi con lo phan XOAY.
2. **QUA NHO: chieu cao dong 5.3mm ep he so co.** Payload dat 5.3mm/dong (chieu cao dong mac dinh Excel), 19 dong = 106mm — ma be rong tem chi 49.3mm, nen chinh CHIEU CAO bang moi la thu chan he so, khong phai be rong. Cho dong co ve hop chu (`height:auto; line-height:1.08`) -> bang thap lai -> he so co tang -> **chu in ra to hon** du co chu khai bao khong doi.
3. **LOI THAT bat duoc khi do:** `zoom` BO TRI LAI chu khong co anh, be rong chu o co nho khong ti le tuyet doi voi co goc (lam tron pixel/hinting) -> kich thuoc that sau zoom lech vai % so voi "kich thuoc goc nhan k". Do MOT LAN roi tin la tran ra ngoai tem: do duoc **51.33mm tren tem rong 49.30mm** (xen mat 1 cot). Sua thanh vong lap **zoom -> DO LAI -> chinh**, toi 6 lan, va **chi duoc dung khi da nam gon** (`heSo >= 1`), khong dung khi con thua du chi 0.1%.

**Ket qua do bang chinh code da build** (`scratchpad/rot_check.html`):

| | Truoc F8 | Sau F8 |
|---|---|---|
| He so co k | 0.465 | **0.601** |
| Co chu tren giay | 5.58 pt | **7.21 pt** (+29%) |
| Dung be rong tem | 100% | 99.96% (khong tran) |
| Dung chieu dai tem | 60.7% | **79.15%** |
| Net vien | 0.62 dot | **2.12 dot** |
| Chieu cao 1 dong tren giay | — | 4.07 mm |

- **Backtick trong chu thich BEN TRONG template literal — lan thu 3** (`kich thuoc goc x k`, `r.height / k`). esbuild bat duoc; `vue-tsc` khong. Da bo backtick. Quy tac: trong `SCRIPT_TU_IN`/`buildSlipHtml`/mo ta trang in, chu thich TUYET DOI khong dung backtick.
- `node scripts/check-weigh-slip.mjs` **8 pass / 0 fail** (payload khong doi), `vue-tsc` exit 0, `npm run build` exit 0 28.55s.
- **Con lai 20.85% chieu dai tem bo trong** — het cach neu khong keo dan chu (scale khong deu) hoac bo 3 dong TRONG cua VBA (dong 2/7/18). Bo 3 dong trong -> 16 dong -> k ~0.714, chu ~8.6pt, dung ~94% chieu dai; nhung dong trong nam trong PAYLOAD nen phai sua CA `weighSlip.ts` LAN `WeighingJobController.php`. Da bao nguoi dung, cho quyet dinh.

**F7. Tem /weighing-station-v2 (+ -large, WeighingStation, WeighingHistory): XOAY 90 do sang phai (06/08)**

Nguoi dung bao "tem in ra khong quay dung chieu", chon: **xoay noi dung 90 do sang PHAI**, co chu "thich nghi".

**Chan doan bang so do** (`scratchpad/slip_fit_check.html`, dung `buildSlipHtml` that):
- Bang phieu 5 cot tu nhien **127.53 x 106.10 mm** — NAM NGANG. Con tem 53.3 x 101.6mm — DOC.
- Ep thang: k = **0.39** -> chu 12pt con **4.64pt**, vien 0.2mm con **0.62 dot** (203dpi, duoi 1 dot -> may in nhiet ra net dut quang).
- Xoay roi moi co: k = **0.46** -> chu **5.58pt**, dung het 101.6mm cho be rong bang.
- DEVMODE trong workbook (`scratchpad/devmode.ps1`): printerSettings1 = TSC TTP-244 Pro, paperSize 257, 53.3x101.6mm, **dmOrientation = 1 (PORTRAIT)**; sheet1.xml cung `orientation="portrait"`. Tuc ban VBA cung in doc — noi dung bi co nho y het. Xoay la CHU DINH cua nguoi dung, khong phai sua cho khop VBA.

**Cach sua — xoay o DUONG IN, KHONG dung payload** (`utils/slipPrint.ts`, `SCRIPT_TU_IN`):
- `print_jobs.label_payload` phai khop TUNG KY TU giua `weighSlip.ts` va `WeighingJobController::buildSlipHtml`. Dung vao payload la phai sua ca PHP roi giu dong bo mai mai; xoay o duong in thi chi 1 cho. **Khong sua PHP dong nao.**
- `transform: translateX(h0*k) rotate(90deg) scale(k)` — doc PHAI sang TRAI: co -> xoay thuan kim dong ho -> keo lai vao khung (xoay quanh goc tren-trai day bang sang ben trai truc, x' = -y).
- **Dao hai ve khi tinh k**: da xoay nen BE RONG tem chan CHIEU CAO bang: `k = min(1, rongPx/h0, caoPx/w0)`. Quen dao la phieu van tran ra ngoai.
- `page.style.width/height` cung phai dao, vi hop bo cuc khong xoay theo transform.
- **Bu net vien:** sau khi co k=0.46, vien 0.2mm chi con 0.62 dot. Excel khong bi vay vi GDI khong bao gio ve net mong hon 1 pixel — nen ep vien truoc khi co len `0.125/k` mm de sau khi co van >= 1 dot. Day la BAT CHUOC Excel chu khong phai lech khoi VBA.

**Kiem chung bang chinh code da build** (`scratchpad/rot_check.html`, esbuild bundle `slipPrint.ts` + `weighSlip.ts`, chan `print()` roi do trong iframe):

```
Vung in cua tem            : 49.30 x 97.60 mm
Khung phieu sau khi xu ly  : 49.30 x 59.25 mm
DA XOAY chua?              : ROI
transform                  : translateX(186.331px) rotate(90deg) scale(0.464665)
Co tran ra ngoai tem?      : khong        Dung 100% be rong / 60.71% chieu cao
vien sau khi co            : 0.98 dot     (truoc khi sua: 0.62 dot)
```
- **Bay khi do:** ban xem tren man hinh co `zoom: 2.4` (trong `@media screen`), zoom lam Chrome lam tron be rong vien theo pixel thiet bi -> do ra 0.34 dot, SAI. Phai `classList.remove('ready')` truoc khi do. Luc in khong co zoom nay.
- `node scripts/check-weigh-slip.mjs` **8 pass / 0 fail** (payload khong doi, dung nhu thiet ke), `vue-tsc` exit 0, `npm run build` exit 0 29.29s.
- **CHUA in tem that** — can nguoi dung in 1 tem doi chieu.

**F6. CHAY THAT VBA de doi soat NOI DUNG QR — 13/13 khop tung ky tu (06/08)**

Nguoi dung chi can NOI DUNG QR giong VBA (chap nhan anh khac). F5 moi doc code doi chieu bang mat — nay lam that:

- `scratchpad/run_vba_qr.ps1`: tao workbook trang qua COM, **chen nguyen khoi VBA** `btnPrint_Click` dong 332-363 (chep y nguyen, thay doi DUY NHAT la nguon doc: `Me.Controls("txt_xxx" & i).Value` -> o tren sheet), roi `Application.Run("BuildQR")` tren 13 bo du lieu.
  - **Bay quan trong:** phai `NumberFormat = '@'` cho vung nhap, khong Excel doi `" 12 "` thanh SO 12 va an het dau cach — bai test se mat y nghia ma van "pass".
- `scratchpad/compare_qr.js`: esbuild bundle `qrPrinterSlip.ts` sang CJS roi chay **dung ham dang duoc build vao web** (`buildSlipQrData`) tren cung 13 bo, so tung ky tu (tab/NBSP doi thanh `<TAB>`/`<NBSP>` de nhin duoc).
- **Ket qua: 13 KHOP / 0 KHAC.** Cac ca hiem da bat duoc dung hanh vi:

  | Ca | Ket qua ca VBA lan Web |
  |---|---|
  | Rong hoan toan | `#----DYE-CHEM` |
  | Rack rong nhung co ma+KL | BO CA DONG (`-DYE-5-CCC-33-CHEM`) |
  | KL rong / chi co dau cach | ra `-0` |
  | Rack = "0" | VAN ghi vao QR (`-0-KHONGCO-12`) |
  | Ma rong, rack co | ghi ma rong: `-7--19` |
  | Dau cach trong HEADER | **KHONG bi trim**: `#  HDR  - CD - VD03 - 250 -DYE...` |
  | TAB / NBSP quanh gia tri | **KHONG bi cat** o ca hai ben |
  | So 0 dau, thap phan | giu nguyen chuoi: `01-0AB-01.500` |

  2 ca cuoi la ly do co y dung `trimSpaces` (`/^ +\| +$/`) chu KHONG dung `String.trim()` cua JS: `.trim()` cat ca tab va NBSP, VBA `Trim` thi khong — dung `.trim()` la lech ngay.

**F2/F3 — DA HOAN TAC, xem F4. Giu lai o day de khong ai lam lai 2 lan.**

**F4. CHOT: in ra CUA SO RIENG noi ben ngoai (06/08)**

Nguoi dung chot: *"de lai nhu cu cho toi, thu toi can la 1 web noi ben ngoai de toi an in"*. Da hoan tac ca 2 buoc thu nghiem F2 (iframe an trong trang) va F3 (dung chung modal `printform` voi /print-order-entry):

- `qrPrinterSlip.ts`: bo `printSlipInPage`, tra lai **`writeSlipToWindow(win, data)`**; trang in tu goi `window.print()` roi `window.close()` nhu ban dau.
- `QrPrinterForm.vue`: bo import + the `<VbaPrintForm>` + `previewData`; `handlePrint` tro lai `window.open('', '_blank', 'width=900,height=700')` DONG BO trong handler click (truoc moi `await` dung QR — neu khong se mat "user gesture" va bi chan pop-up).
- `components/VbaPrintForm.vue`: **`git checkout --`** ve dung ban da commit -> prop `printAction` bien mat, /print-order-entry khong con vet sua nao.
- Bai hoc: yeu cau "hop thoai in hien ngay o trang nay" o day co nghia la **cua so phieu noi ra ngoai de nguoi dung TU BAM IN**, khong phai hop thoai in cua trinh duyet bung tren trang. Hoi ro truoc khi doi luong in lan sau.
- Kiem chung sau khi hoan tac: `vue-tsc --noEmit` **exit 0**, `npm run build` **exit 0** 20.22s (`QrPrinterForm-Br5vY6m1.js` 24.69 kB). Grep toan bo `frontend/src`: khong con tham chieu `printSlipInPage` / `data-slip-ready` / `previewData` trong man QR PRINTER.

**F2 (da hoan tac). Hop thoai in hien NGAY TREN TRANG, bo hean popup (06/08, theo yeu cau nguoi dung)**

Truoc: bam `print` -> `window.open('','_blank')` roi ghi HTML vao cua so moi, cua so tu goi `window.print()` + `window.close()`. Nguoi dung muon hop thoai in noi len ngay tren `/qr-printer`.

- Thay `writeSlipToWindow(win, data)` bang **`printSlipInPage(data)`**: dung `<iframe>` an trong chinh trang, gan noi dung qua **`srcdoc`**, doi su kien `load` roi goi `iframe.contentWindow.print()`.
- 3 rang buoc BAT BUOC (khong phai tuy chon), da ghi chu thich trong code:
  1. Dung `srcdoc` chu khong `document.write` — `document.write` khong ban su kien `load` dang tin, ma phai doi load xong (anh QR + font san sang) thi script ep-vua-trang moi do dung.
  2. Iframe **khong duoc `display:none`** — trinh duyet bo qua khung an kieu do khi in. Dung `position:fixed; left:-10000px; visibility:hidden` va giu kich thuoc that.
  3. Van phai hen gio don iframe (1s) thay vi xoa ngay sau `print()`: Chrome/Edge CHAN o `print()` toi khi dong hop thoai, nhung Firefox/Safari tra ve ngay.
- **Loi ich phu:** bo luon rang buoc "user gesture" — sinh anh QR la tac vu bat dong bo nen `window.open` sau do bi Chrome/Edge chan; in iframe thi khong. Da xoa thong bao "Trinh duyet da chan cua so in".
- Trang in khong con tu goi `print()/close()`; script ben trong chi ep vua trang roi dat `document.body[data-slip-ready]="1"`.
- **Doi soat lai bang chinh code that** (`esbuild` bundle roi chay Edge headless, `scratchpad/iframe_check.html`, stub `print()` de do hinh hoc dung luc in):

  ```
  so cua so/tab moi mo ra   = 0        so lan goi print() = 1
  iframe co bi display:none? khong     script ep-vua-trang da chay? roi
  bang (pt)  = 263.85 x 471.43   [Excel: 264.00 x 470.25]
  anh QR     = 287.99pt          [Excel: 288.00]
  QR left    = 311.84pt          [Excel: 312.00]    QR top = 74.24pt [Excel: 74.25]
  kho giay @page = 63.5mm 38.5mm       noi dung co tran vung in? khong
  sau 1.6s con sot iframe?  da don sach
  ```
  => hinh hoc **y het truoc khi doi**, chi doi duong dan hien thi.
- **Meo chay Edge headless:** `--dump-dom` chup DOM ngay sau `load`, KHONG doi `setTimeout` chay xong -> phai them `--virtual-time-budget=6000` moi thay duoc buoc don iframe. (Va `npx vue-tsc` lay ban trong npm-cache bi loi `ERR_PACKAGE_PATH_NOT_EXPORTED` — phai goi `frontend\node_modules\.bin\vue-tsc.cmd`.)

**F3 (da hoan tac). Hop thoai in dung CHUNG voi /print-order-entry (06/08, yeu cau tiep theo)**

Nguoi dung: "/qr-printer toi muon hien thi hoi thoai in giong /print-order-entry". Tren /print-order-entry, "hoi thoai in" la modal `printform` cua DF002 (`components/VbaPrintForm.vue`, 306x390pt, 2 o chuoi tho + 9 dong DYE + 9 dong CHEM + PRINT/CLEAR/CLOSE).

- **Dung lai dung component do**, khong ve lai cai moi: them prop tuy chon **`printAction`** vao `VbaPrintForm` — khong truyen thi in tem dieu phoi DF002 nhu cu (/print-order-entry khong doi mot dong nao), co truyen thi goi ham do.
  - Dat ten `printAction` chu KHONG `onPrint`: Vue coi prop mo dau bang `on` la trinh xu ly su kien, de lan voi `@close`.
- `/qr-printer` truyen `printAction = printQrSlip` -> nut PRINT in tem cua workbook QR PRINTER (`printSlipInPage`, iframe trong trang), khong phai tem DF002.
- Bam `print` mot lan = **vua hien hop thoai xem truoc vua bung hop thoai in**, dung nhip thao tac cua /print-order-entry (yeu cau 06/08 truoc do). Nut PRINT trong hop thoai giu de in lai.
- Modal doc 9+9 dong tu CHUOI THO qua `parseRackLines`, nen /qr-printer dung lai `buildRawQr(dye)` / `buildRawQr(chem)` cua nut SEND -> hai man hinh hien cung mot noi dung.
- Kiem chung: `vue-tsc --noEmit` **exit 0**, `npm run build` **exit 0** 15.73s (`QrPrinterForm-DmWZBbzk.js` 25.38 kB, `VbaPrintForm-USE6IPFZ.js` 3.97 kB tach chunk dung chung). **Chua nhin bang mat tren trinh duyet** — can nguoi dung mo /qr-printer bam `print` xac nhan.

**G. Kiem chung**

- `vue-tsc --noEmit` **0 loi**, `vite build` OK 34.7s (chunk `QrPrinterForm` 23.28 kB). `php -l routes/api.php` sach.
- Sau khi doi sang in trong trang: `vue-tsc --noEmit` **exit 0**, `npm run build` **exit 0** 16.19s (`QrPrinterForm-D2nu7kwc.js` 24.94 kB).
- Doi chieu so hoc: o phai cung ket thuc @582pt < rong form 588.75; dong cuoi ket thuc @542.75pt < cao form 549.
- **CHUA thu tay tren trinh duyet** — can nguoi dung mo /qr-printer xem bang mat, in thu mot tem doi chieu voi tem VBA, va bam SEND mot don thu roi kiem tra no hien o /print-order-entry.


### 119. Phieu can cua /weighing-station-v2 dung lai 1:1 theo form VBA (2026-08-06)

**A. Cau hoi cua nguoi dung:** *"khi save tem in ra da giong 100% o 4.semiauto-small scale - delta-stable-final_DF026-027.xlsm chua"* -> **CHUA**, va sau do: *"1 giong o form vba 100%"*.

**B. Doc thang tu workbook thay vi doan.** Giai nen `xl/vbaProject.bin` cua chinh workbook dang chay ngoai xuong (quet moi offset co byte 0x01 roi giai nen MS-OVBA), lay duoc nguyen van module `scaleform`. Ket qua:

- `btnSave_Click` ket thuc bang `btnPrint.Value = True` roi `btnCLEAR.Value = True` -> SAVE la co in.
- `btnPrint_Click` **KHONG in TSPL**: `Sheets.Add` mot sheet moi, do du lieu vao o, `.Borders.LineStyle = xlContinuous` cho ca vung A1:E19, Calibri 12, `.Columns.AutoFit`, PageSetup dung (FitToPagesWide/Tall = 1, 4 le 0.2cm), roi `ws.PrintOut ActivePrinter:="TSC TTP-224 Pro"` va `ws.Delete`.
- Kho giay lay tu `xl/printerSettings/printerSettings1.bin` (DEVMODE): TSC TTP-244 Pro, dmPaperWidth 533 / dmPaperLength 1016 -> **53.3 x 101.6mm doc**, 203dpi.
- Sheet1 con luu nguyen mot ban in cu (DF_WEIGHING_SLIP / COLOR: / CODE: / MACHINE: / LEVEL: / bang RACK-DYE CODE-WEIGHT-PROCESS / "Print time:") -> khop voi code.

**C. Khoang cach so voi ban web cu** (bo cuc TSPL 55x35mm tu thiet ke, xem muc 115): khac ca 5 cot (`MT(g)/TT(g)/KQ` vs `WEIGHT/PROCESS/STATUS`), khac phan dau (gop 1 dong vs 4 dong co nhan), khac nhan ket qua (DAT/LECH vs ACCEPTED/REJECTED), khac cho dat moc gio (goc tren phai vs dong cuoi), khong co vien o, chi in dong co du lieu thay vi du 9 dong.

**D. Da lam - phieu can chuyen tu TSPL sang HTML**

- `utils/weighSlip.ts` + `WeighingJobController::buildSlipHtml` (doi ten tu `buildSlipTspl`) dung lai dung sheet do: **19 hang x 5 cot**, vien moi o, Calibri 12pt, dong 5.3mm (= 15pt mac dinh Excel), luon in du 9 dong du lieu, "Print time:" o hang cuoi.
- **Ly do phai bo TSPL**: TSPL khong ve duoc vien o, khong co Calibri, khong in dam theo dong - ba thu deu la mot phan cua "giong 100%". `tsplPrint.ts` VAN GIU cho tem vat tu (MaterialLabel) va cac tem khac; hai duong in nay co y khac nhau.
- `utils/slipPrint.ts` (moi): boc manh HTML thanh trang in va **co lai cho vua 1 trang** - ban dung lai `FitToPagesWide/Tall = 1`. Phai do tai trinh duyet (`table.offsetWidth`) chu khong tinh san duoc, vi be rong bang phu thuoc be rong that cua tung chu Calibri sau `.Columns.AutoFit`. `Math.min(1, ...)` vi Excel chi THU NHO, khong phong qua 100%. Sau khi scale phai ep lai kich thuoc khung trang, neu khong phan thua ben phai/duoi van tinh la noi dung va bi day sang trang 2.
- `label_payload` luu **manh HTML tu mang CSS**, khong kem doan script tu in -> `LabelPreview.vue` nhung dung manh do vao iframe (mode `html` moi) ma khong bi bung hop thoai in, va CSS cua phieu khong ro ra toan ung dung.
- Doi call site: `/weighing-station-v2`, `/weighing-station-large`, `/weighing-history`, `/weighing-station` (nut in PHIEU). Nut in TEM VAT TU cua `/weighing-station` giu nguyen `printTsplViaBrowser`.

**E. BA chi tiet trong nhu loi nhung chinh la ban goc - dung "sua"**

1. Dong in dam la dong 1 va dong **7** (dong 7 trong tron). VBA viet `ws.Range("A7:E7").Font.Bold = True` nhung tieu de bang nam o dong **8** -> tren phieu that ngoai xuong **dong tieu de bang KHONG dam**. Da chep y nguyen.
2. Cot `WEIGHT` la so can **MUC TIEU** (`txt_weight` do QR nap vao), cot `PROCESS` moi la so can **THUC TE** (`txt_process` - o ma `CheckRange` to mau theo dung sai). Doc ten cot theo nghia thong thuong la hieu nguoc.
3. `For i = 1 To 9` -> luon in du 9 dong ke ca don it vat tu hon.

**F. HAI cho co y KHONG chep y nguyen (da bao nguoi dung)**

- Dong trong de trong ca cot STATUS. VBA goi `GetProcessStatus` cho ca o rong, ma ham do so sai hang so mau (`RGB(60,200,100)` - mau `CheckRange` khong bao gio gan) nen tra "REJECTED" cho moi o trang -> in ra 6 chu REJECTED gia moi to. Day la loi legacy da chot **NOT_MIGRATED_LEGACY_BUG** (`.claude/test-architecture.md`).
- Cot STATUS cua dong co vat tu suy tu dung sai da snapshot (`WeighingJobItem::process_status`) chu khong doc nguoc mau nen o - cung ly do tren.

**G. Kiem chung**

- `node frontend/scripts/check-weigh-slip.mjs`: **8/8 PASS** - ban PHP va ban trinh duyet ra chuoi y het nhau. Da them **kiem tra CAU TRUC** vao script (dung 19 hang, dung 95 o, dong in dam dung la `[1,7]`, du 5 cot, co dong "Print time:") - phep so PHP/JS khong bat duoc truong hop hai ban cung sai giong nhau.
- Render that o dung so dot may in: Edge headless `--window-size=202,384 --force-device-scale-factor=2.1146` -> anh **427x812 px = dung 53.3 x 101.6mm @203dpi**. Anh cho thay bang 19 hang co vien day du, nam gon trong 1 trang, khong tran sang trang 2.
- `vue-tsc --noEmit` **exit 0**; `vite build` **OK 17.03s**; `php -l` sach ca controller lan 2 file test.
- Da sua assert bam bo cuc cu o `WeighFromQrIdempotencyTest` va `ScaleCheckerAndPrintSlipTest` va **doi chieu tung chuoi assert voi phieu that do script sinh ra**. **Khong chay duoc test suite o may dev** (.env tro DB production, `RefreshDatabase` se xoa schema).
- **CHUA in tren may TSC that** - can nguoi dung bam SAVE mot me o /weighing-station-v2 va in thu 1 to.

### 120. Doi chieu tiep phieu can cua /weighing-station-large voi workbook can TO (2026-08-06)

**A. Cau hoi:** *"tem in ben nay da giong 5.Semiauto- lockmove SEND OVER6 - delta-stable-final-221.xlsm 100% chua, toi muon giong 100%"*.

**B. Giai nen workbook 5 va doi chieu nguyen van.** Ket qua quan trong: `scaleform.btnPrint_Click` cua workbook can TO **giong het** workbook can NHO (muc 119) - tung dong, ke ca `printerName = "TSC TTP-224 Pro"`, vung `A1:E19`, `Range("A7:E7").Font.Bold`, `For i = 1 To 9`, PageSetup FitToPages 1x1 le 0.2cm. `btnSave_Click` cung ket thuc bang `btnPrint.Value = True` + `btnCLEAR.Value = True`. `printerSettings1.bin` cung la **53.3 x 101.6mm** (paperSize 257, 203dpi), Sheet1 cung con nguyen mot ban in cu giong het.

=> **KHONG phai tach bo cuc rieng cho hai man hinh.** `buildSlipHtml` dung chung la dung.

**C. Khac biet DUY NHAT con lai - cot STATUS o dong trong.** Lan nay giai nen duoc ca `Mod_print_tsc224` (workbook 4 khong bung ra duoc module nay):

```vba
Public Function GetProcessStatus(tb As MSForms.TextBox) As String
    Select Case tb.BackColor
        Case RGB(120, 250, 20): GetProcessStatus = "ACCEPTED"
        Case Else:              GetProcessStatus = "REJECTED"
    End Select
End Function
```

- Day la ban **DA SUA** (so dung mau xanh ma `CheckRange` that su gan), khac ban trong workbook can nho von so voi `RGB(60,200,100)` - mau khong bao gio duoc gan - nen o do moi dong deu ra REJECTED ke ca dong can dat. Ban dung o web lay theo ban DA SUA nay.
- He qua: vi `For i = 1 To 9` chay het 9 dong, **dong trong cung co chu REJECTED** (o txt_process nen trang -> nhanh Else). Truoc do web de trong cot do. Da sua cho khop: `statusInRaPhieu()` (TS) / `slipStatus()` (PHP), va dong trong nay in `['', '', '', '', 'REJECTED']`.
- Dong chua can / lech dung sai / vuot deu ra REJECTED - chi o nen XANH moi ACCEPTED. Da bo nhan `PENDING` rieng tren phieu (VBA khong co khai niem do).

**D. Cho DUY NHAT co y khong chep y nguyen:** dong CAN TAY van in "MANUAL" thay vi "REJECTED". Can tay la luong **chi co o web** (VBA khong co), nen khong co to phieu VBA nao de ma lech; in "REJECTED" len ca me can tay dat la day mot tin hieu sai vao tay tho. Muon literal 100% thi bo nhanh `if (status === 'MANUAL')` o ca hai ban.

**E. Kiem chung**

- `node frontend/scripts/check-weigh-slip.mjs`: **8/8 PASS**. Da them mot kiem tra cau truc nua: **ca 9 dong du lieu deu phai co cot STATUS** (`ACCEPTED|REJECTED|MANUAL`), khong dong nao duoc bo trong.
- Render lai o 203dpi (427x812 px = 53.3 x 101.6mm): 5 dong trong deu mang chu REJECTED, dung nhu sheet VBA.
- Sua assert `ScaleCheckerAndPrintSlipTest` sang so **CA HANG** thay vi tung o: tu nay dong trong nao cung co `<td>REJECTED</td>` nen assert mot minh chuoi do se **luon dung bat ke du lieu**, tuc khong chung minh duoc gi. Da doi chieu 3 chuoi assert voi phieu that do `buildSlipHtml` sinh ra (chay Laravel bootstrap, khong cham DB).
- `vue-tsc --noEmit` **exit 0**; `vite build` **OK 15.75s**; `php -l` sach controller + test.
- **CHUA in tren may TSC that** o ca hai tram - can nguoi dung in thu 1 to moi ben.

### 121. Nut PRINT cua /weighing-station-v2 "khong co phan ung gi" (2026-08-06)

**A. Nguyen nhan GOC — mot cap ngoac thieu trong template.**

`WeighingStationV2.vue` viet `@click="printSlip"` **khong ngoac**. Vue truyen `PointerEvent` vao tham so dau tien cua handler, ma tham so do la `preOpened?: Window | null`:

```ts
const win = preOpened ?? window.open('', '_blank', ...);   // preOpened = PointerEvent -> truthy
win.document.write(...);                                   // PointerEvent.document = undefined -> TypeError
```

Su kien truthy nen `??` **khong bao gio mo cua so**, roi `win.document.write` nem TypeError ngay trong handler. Khong co cua so, khong co alert, khong co gi -> **dung nhu nguoi dung mo ta**. `/weighing-station-large` viet `@click="printSlip()"` co ngoac nen khong dinh — day la ly do loi chi xuat hien o mot man.

Truoc do con mot cua chan im lang thu hai: `if (!currentWorkstation.value) { preOpened?.close(); return; }` — chua gan tram thi thoat khong noi mot loi (va `.close()` tren PointerEvent cung nem TypeError).

**B. Da sua**

1. Template: `@click="printSlip()"`.
2. `cuaSoThat()` trong `utils/slipPrint.ts`: chi nhan dung mot `Window` that, moi thu khac tra `null`. Sua template la het loi lan nay, nhung nut nao goi kieu do cung dinh lai y het; kiem o day thi **ca lop loi** bien mat va te nhat cung chi la mo thua mot cua so. Ap cho ca hai man.
3. Bo cua chan `!currentWorkstation` — no roi vao nhanh dung phieu CUC BO. Server can `workstation_code` de ghi PrintJob, con to giay thi khong: du lieu de in da nam san tren man hinh.

**C. Doi chieu tiep nut PRINT voi VBA — con MOT khac biet nua, da sua luon**

`btnPrint_Click` do thang `txt_rack/dye/weight/process` cua **ca 9 o** ra sheet, bat ke o do co gi. Ban web goi `dungPhieuCucBo()` von **loc bo** dong khong co `planned_weight` — ma V2/Large cho bam NEXT xuong dong trong de dung man hinh nhu mot cai can thuong, nen co the co so can o dong QR khong mang vat tu toi. Nhung dong do hien tren luoi nhung **bien mat khoi phieu**.

- Them `dongPhieuTheoLuoi()` + `dungPhieuTuLuoi()` (ca hai man): dung dung 9 dong **nhu dang hien tren luoi** — rack lay tu item hoac `manualRacks`, PROCESS lay tu `capturedWeights`, dong trong tra ve moi truong rong (ra o trong + STATUS REJECTED, trung khit cai ma `buildSlipHtml` tu sinh cho vi tri khong co du lieu).
- Bo luon chan `rows.length === 0` -> **form trang van in ra mot to 9 dong trong**, dung VBA. Ban truoc chan bang alert "Chua co so can nao de in": tien cho web nhung lech ban goc.

**D. KHAC BIET CON LAI (co y giu, da bao nguoi dung)**

- **Luong SAVE** van in dung nhung dong **duoc luu** (`dungPhieuCucBo`/`dungPhieuCanTay`), khong phai ca 9 dong nhu VBA. Ly do: to giay phai khop ban ghi `print_jobs` do server dung — day la thuoc tinh truy vet da chon co chu dich. VBA khong co rang buoc nay (`btnSave_Click` chi INSERT dong co WEIGHT nhung `btnPrint` van in ca form). Chi lech o dung mot canh: co so can o dong khong co muc tieu.
- Web in qua **hop thoai in cua trinh duyet**, VBA `PrintOut` thang toi may in ten cung. Da chot tu 2026-07-30, khong doi duoc trong kien truc web.

**E. Kiem chung**

- `vue-tsc --noEmit` **exit 0**; `vite build` **OK 15.57s**; `check-weigh-slip.mjs` **8/8 PASS**.
- Quet lai ca hai man tim handler truyen su kien nham: moi `@click`/`@change` con lai (`onSave`, `onNext`, `onClose`, `numDel`, `retare`, `refit`, `onIn`, `onOut`, `onCopyRacks`, `onGuiNgay`) deu la ham **khong tham so** -> khong con cho nao dinh cung loi.
- **CHUA bam thu tren trinh duyet** — can nguoi dung mo /weighing-station-v2 bam PRINT xac nhan.

### 122. /qr-printer: 2 nut goc tren ben phai mo tab lich su DA GUI / DA IN (2026-08-07)

**Yeu cau:** bam SEND xong co thong bao, nhung khong tra lai duoc "vua roi da gui/in nhung gi". Them 2 nut o goc tren ben phai — khong lien quan toi form dang nhap lieu — mo sang tab khac hien lich su da gui va lich su da in.

**A. Noi luu — dung `app.audit_logs`, KHONG tao bang moi**

Nut `print` cua form von chi mo cua so dung phieu (khong sinh `print_jobs` nhu tram in), con SEND thi tao lo o `queue_state = WAITING` nen **khong lot vao** `/machine-dispatches/history` (endpoint do loc `CONFIRMED`). Tuc la truoc ban nay khong co nguon du lieu nao tra loi duoc cau hoi cua nguoi dung.

Chon ghi vao `audit_logs` vi: (1) bang nay von la noi luu vet append-only cua he thong, CLAUDE.md muc 5 da yeu cau in tem phai ghi Audit Log; (2) `user_id` nullable nen trang cong khai ghi duoc; (3) **khong can migration tren Production giua Phase 12**. Danh doi: khong co dinh danh nguoi thuc hien (giong moi endpoint `/public` khac), chi co `client_ip`.

- `action`: `QR_PRINTER_SEND` / `QR_PRINTER_PRINT`; `entity_type`: `qr_printer_form`.
- `entity_id`: id lo that neu da gui, khong thi `COLOR-CODE`.
- `after_data` (JSONB): color/code/machine/tank/lv/batch_id/note + 9 dong dye + 9 dong chem (**da loc bo dong trong** — don that chi dung 2-4 dong).

**B. Da them**

- `backend/app/Http/Controllers/QrPrinterLogController.php` — `store` (ghi) + `index` (doc, mac dinh 7 ngay, tran 200 dong, moi nhat len dau).
- `routes/api.php`: `GET|POST /api/public/qr-printer/logs` (cung nhom public voi cac endpoint man /qr-printer dang dung).
- `frontend/src/views/QrPrinterHistory.vue` + route `/qr-printer/history` (`requiresAuth: false`), 2 tab `?tab=sent|print`, chon khoang 1/7/30/90 ngay, o loc chay phia trinh duyet, bam mot dong de bung chi tiet 9 dong dye/chem. Tu nap lai moi 20 giay — 2 tab la 2 tien trinh rieng, khong co su kien nao ban qua lai.
- `QrPrinterForm.vue`: dai `.vba-topbar` NGOAI mat form (khong chen vao toa do pt cua UserForm), 2 nut mo `target="_blank"` de phien nhap dang do khong bi mat; kieu nut co y khac `.vba-btn` de khong nham la nut cua phieu. Ham `logAction()` ban rồi quen — nhat ky hong khong lam hong viec da thanh cong. SEND ghi **truoc** `handleClear()`, neu khong thi form da xoa trang, khong con gi de ghi.

**C. Loc du lieu**

`index` co y KHONG loc theo tu khoa phia DB: noi dung nam trong cot JSON, loc phia DB se phai viet SQL rieng cho tung loai DB. Trang lich su loc phia trinh duyet tren tap da tra ve, giong cach `/print-sent-log` dang lam.

**D. Kiem chung**

- `php -l` controller + `routes/api.php` **OK**; `php artisan route:list --path=qr-printer` hien du 2 route.
- `vue-tsc --noEmit` **exit 0**; `vite build` **OK 15.75s**.
- **CHUA bam thu tren trinh duyet** — can nguoi dung mo /qr-printer, bam SEND va print roi mo 2 nut moi de xac nhan. Luu y: .env may dev tro thang DB Production nen moi lan bam thu se sinh ban ghi that trong `app.audit_logs` (chi them dong moi, khong sua/xoa gi).

**E. Bo sung ngay sau do — tab DA GUI doi nguon sang bang lo that**

Nguoi dung mo /qr-printer/history?tab=sent thay TRANG. Dung nhu thiet ke ban dau: nhat ky chi ghi tu luc co code moi, con toan bo lo da gui truoc do khong co dong nao.

Sua: tab **DA GUI** doc thang `GET /api/public/production-batches?per_page=100` (bang `app.production_batches` — nguon su that cua viec gui don) thay vi doc audit log. Nho vay co san lich su cu, va gom ca lo gui tu man khac (/production-batches/grid, /print-order-entry) — thao tac vien tra "may VD07 da co lo chua" thi can thay het. 9 dong chi tiet doc nguoc tu `raw_qr_dye`/`raw_qr_chemical` da luu luc gui (`parseRawQr`, cung quy uoc voi `buildRawQr`; cung diem yeu: ma thuoc chua dau "-" se tach sai, giu nguyen nhu ban VBA). Cot cuoi doi thanh TRANG THAI (`status` lo + `queue_state` hang cho).

Tab **DA IN** van doc audit log — bam `print` chi mo cua so dung phieu, khong sinh `print_jobs`, khong co nguon nao khac. Chi co du lieu tu 07/08/2026 tro di.

Van giu `logAction('SEND')`: la ban ghi audit that (co IP may tram + anh chup form tai thoi diem bam), khong phai nguon hien thi cua tab DA GUI nua.

`vue-tsc --noEmit` exit 0; `vite build` OK 15.21s.

---

### 123. Backup dinh ky DB PostgreSQL — dua script vao repo (2026-08-07)

**Cau hoi cua nguoi dung:** "DB hien tai co backup dinh ki chua". Tim ca repo: KHONG co script backup nao. Ghi chu server co nhac mot Scheduled Task `DFWeb-Backup` (2h sang, giu 14 ngay, `C:\web\tools\backups\`) nhung script do — neu con ton tai — nam ngoai repo, khong ai review duoc, khong biet con chay khong. **Chua kiem chung duoc tren server**: auto-mode classifier chan moi lenh SSH toi 10.0.60.209, nguoi dung phai tu chay lenh kiem tra.

**Da them (4 file, deu chua chay tren server):**

- `tools/db-backup.ps1` — doc `backend/.env` lay DB_DATABASE/USERNAME/PASSWORD/PORT, `pg_dump -Fc -Z 6` qua **loopback 127.0.0.1** (chay ngay tren server, khong di vong qua LAN) + `pg_dumpall --globals-only`. Vi sao can file globals: role/quyen nam o cap cluster, KHONG co trong dump cua 1 database — thieu no thi restore sang may khac se mat het user.
- `tools/db-backup.bat` — vo boc cho Scheduled Task, redirect stdout vao `tools\logs\db-backup.log` (dung quy uoc cua `bpdb-sync.bat`; script .ps1 co y KHONG tu ghi file log de tranh 2 nguon cung ghi).
- `tools/db-backup-verify.ps1` — kiem chung ban moi nhat: tuoi < 26h, kich thuoc, doc muc luc bang `pg_restore -l`, dem so bang co du lieu, co file globals khong. **CHI DOC**. Co y KHONG restore that vao server: viec do phai tao roi xoa database tam, ma `DROP DATABASE` nam trong danh sach lenh bi cam (`rules/database-safety.md` muc 2). Restore test day du (yeu cau Phase 13) phai lam tren may dev.
- `tools/register-db-backup-task.ps1` — dang ky task chay 02:00 hang ngay bang tai khoan SYSTEM. Neu task cung ten da ton tai thi IN RA cau hinh cu truoc roi moi ghi de (co `-WhatIfOnly` de chi xem).

**Quy tac giu ban cu:** giu 14 ngay gan nhat; rieng ban chay ngay mung 1 giu them 6 thang — de con diem khoi phuc dai han ma khong phinh dia. Dump < 512 KB coi la hong, bao loi va GIU LAI file de dieu tra chu khong ghi de.

**Diem yeu con lai:** backup nam cung o dia voi `C:\pgdata` — hong o dia la mat ca hai. Tham so `-MirrorDir` da co san nhung de trong, vi task chay bang SYSTEM khong truy cap duoc share mang; muon mirror phai doi sang tai khoan mien co quyen ghi len share.

**Kiem chung:** 3 file .ps1 parse sach bang `[Parser]::ParseFile` (0 loi cu phap); logic giu/xoa theo ten file test rieng bang bo ten gia — dung ngay 01 giu, ngay thuong qua 14 ngay xoa, file khong dung dinh dang bi bo qua. **Chua chay that lan nao** — chua biet `pg_dump` tren server that mat bao lau, file to bao nhieu.

### 124. Tem cua /weighing-station-v2 "in ra bi mo" — to phieu thanh anh 1-bit thay vi de trinh duyet ve chu (2026-08-07)

**A. Trieu chung:** ngay sau khi doi sang cuon tem 60x40mm (muc truoc, commit 344c306), nguoi dung
bao *"tem in ra bi mo toi can sieu net"*.

**B. Nguyen nhan.** KHONG phai co chu, ma la cach chu duoc rasterize.
- Duong in cu de nguyen bang HTML roi `zoom` cho vua tem -> chu do **trinh duyet** ve, ma trinh
  duyet luon khu rang cua: moi net chu co vien XAM hai ben.
- May in tem la may in nhiet **1 bit** — khong in duoc xam, driver phai dither sac xam thanh luoi
  cham thua. O co chu ~12-15 dot thi phan vien xam chiem gan nua net -> ra giay la cham lam tam
  quanh moi chu = dung cam giac "mo".
- Khong co thuoc tinh CSS nao tat duoc khu rang cua tren Chrome/Windows. `-webkit-font-smoothing:
  none` nam trong `wrapSlipDocument` tu 30/07 that ra **khong lam gi ca** — no chi co tac dung tren
  macOS. Da giu lai (vo hai) nhung dung tin no.

**C. Da sua — `frontend/src/utils/slipPrint.ts` (SCRIPT_TU_IN viet lai hoan toan)**

Tu quyet dinh tung dot thay vi nho trinh duyet:
1. Doc bang HTML cua payload ra mang 2 chieu.
2. Chon co chu LON NHAT ma bang con nam gon trong vung in, tinh bang **dot that** (8 dot/mm, quy
   uoc TSPL nhu `tsplPrint.ts`). Van xoay 90 do neu xoay cho co chu lon hon (tem doc cuon cu thi
   xoay co loi, tem ngang 60x40 thi khong).
3. Ve luoi o + chu vao canvas o **dung do phan giai do**, moi toa do la so nguyen dot. Vien o ve
   bang `fillRect` day dung 1 dot (khong `strokeRect` — stroke ve giua duong nen roi vao nua dot
   hai ben va ra xam).
4. **Nhi phan hoa**: quet tung pixel, toi hon nguong 176 -> den dac, con lai -> trang dac. Sau buoc
   nay trong anh khong con mot sac xam nao de ma dither.
5. In canvas o dung kich thuoc vat ly cua no (so dot / 8 mm) voi `image-rendering: pixelated`, tuc
   1 pixel anh = 1 dot may in, khong noi suy.

Hai diem co y lech ban VBA, da ghi ro trong code:
- **In dam toan bo** (VBA chi dam dong 1 va 7): o co duoi 16 dot, net chu thuong manh hon 1.5 dot
  nen sau khi nhi phan hoa bi dut quang. `tsplPrint.ts` cung de font-weight 700 cho moi tem.
- Chieu cao dong lay tron phan cao con lai chia deu cho so dong, khong buoc theo ti le co dinh voi
  co chu. Buoc theo ti le thi chieu cao dong nhay nac va bo phi phan du — ban dau tui viet 1.3 va
  no chot o 14 dot/dong, bo khong 21 dot (gan mot dong ruoi, du de chu nho hon mot co).

**Payload KHONG doi** — van la bang HTML, van khop tung ky tu voi ban PHP. Canvas chi la cach VE
no ra giay, nen khong phai sua `WeighingJobController::buildSlipHtml` va ban xem truoc o Lich su in
van doc duoc.

**D. Kiem chung — do that, khong doan**

Dung `esbuild` bundle `weighSlip.ts` + `slipPrint.ts`, dung trang in that, chan `window.print`, roi
mo bang **Edge headless** va lay thang `canvas.toDataURL()` — tuc **dung anh bitmap ma may in se
nhan**, khong phai anh chup man hinh.

| | Truoc | Sau |
|---|---|---|
| Cach ve chu | trinh duyet ve, co khu rang cua | canvas 1-bit, nguong 176 |
| Sac xam trong anh | co (moi canh net) | **khong con** |
| Co chu | 12pt x zoom 0.41 ~ 4.9pt (~13.8 dot em), net manh | **12 dot em, in dam** |
| Chieu cao dong | ~15 dot | 15 dot |
| Kich thuoc bang | (khong do duoc trong so dot) | 390 x 286 dot / vung in 448 x 288 |

- `node frontend/scripts/check-weigh-slip.mjs`: **8/8 PASS** (payload khong doi nen phai the).
- `vue-tsc --noEmit` exit 0.
- **CHUA in tren may TSC that** — can nguoi dung in thu 1 to.

**E. Con lai: chu KHONG to len, chi net len.** Ngan sach dot la co dinh: vung in 448 x 288 dot,
**chieu cao la thu chan**, 19 dong chia 288 dot chi duoc 15 dot/dong. Muon chu to hon thi bat buoc
phai bot dong, ma bot dong la lech ban VBA (yeu cau "giong form VBA 100%" ngay 06/08):

| Bo cuc | So dong | dot/dong | Co chu |
|---|---|---|---|
| Nguyen ban VBA (hien tai) | 19 | 15 | 12 dot |
| Bo 3 dong TRONG (dong 2, 7, 18) | 16 | 17 | ~13-14 dot |
| Bo dong trong + gop COLOR/CODE va MACHINE/LEVEL | 14 | 20 | ~13 dot (**be RONG chan lai**) |

Luu y bay thu ba: qua 16 dong thi **be rong** thanh thu chan, vi cot 1 phai rong bang chuoi
`DF_WEIGHING_SLIP` (16 ky tu) do Excel AutoFit cot theo o rong nhat. Muon an tiep phai cho dong
tieu de trai het 5 cot (colspan) thay vi nam gon trong cot 1 — luc do moi len duoc ~16-17 dot.

Script dung de do nam o scratchpad cua phien (`render-slip.mjs`), **chua dua vao repo** vi nguoi
dung chua yeu cau; dang lam mot cong cu thuong truc neu con phai chinh tem tiep.

### 125. /weighing-station-v2: bam CLEAR bi van khoi F11, va phong to man hinh theo nac (2026-08-07)

**A. Trieu chung:** *"khi an clean k bi vang khoi che do f11, va toi muon to hon de de nhin hon"*.

**B. Nguyen nhan cua phan F11 — KHONG phai loi cua nut CLEAR.**
Chrome/Edge **thoat toan man hinh** ngay khi mot hop thoai GOC (`alert`/`confirm`/`prompt`) bat len
— quy tac chong gia mao giao dien cua trinh duyet, khong co cach nao tat. `onClear()` goi
`window.confirm` de hoi truoc khi xoa, nen moi lan bam CLEAR (khi tren man hinh dang co don hoac co
so chua luu) la tho bi day ve che do cua so binh thuong, phai bam F11 lai.

Man nay con **7 cho khac** dinh y het: `alert` luc quet QR hong, luc server tu choi don, luc popup
in bi chan, luc in phieu loi; `confirm` luc SAVE con dong chua can va luc BO ME trong bang hang doi.
Da doi **tat ca** sang hop thoai ve trong trang, khong con `alert`/`confirm`/`prompt` nao o man nay.

**C. Hai cai bay khi thay confirm bang hop thoai bat dong bo**

1. **Chan popup in o luong SAVE.** `onSave` mo cua so in bang `window.open` va chi lam duoc trong
   luc con "user activation" — tuc truoc moi `await`. Ban cu dat `window.open` SAU `window.confirm`
   van chay duoc vi confirm la ham DONG BO. Hop thoai moi bat buoc `await`, nen da **chuyen
   `window.open` len TRUOC** hop xac nhan; tho bam "KHONG" thi `printWin?.close()`. Khong doi thu
   tu nay thi bam SAVE khi con dong chua can se luu duoc nhung **khong in ra phieu**.
2. **Tranh chap tieu diem voi o quet.** O COLOR tu gianh lai tieu diem de don may quet. Neu hop
   thoai khong keo tieu diem ve thi phim Enter (va ca mot cu quet nham) roi thang vao o quet trong
   luc hop thoai dang chan man hinh. Da them `watch` dua tieu diem vao nut AN TOAN cua hop thoai
   (nut "KHONG" dung truoc nut "DONG Y"), va giu dung **ngu nghia CHAN** cua `alert` cu: moi cho
   truoc day goi `alert()` gio `await baoTin()`, nen phan viec phia sau (tra con tro ve o quet,
   khoi `finally`) van chi chay SAU khi tho bam OK.

`onClear` va `onBoMe` doi sang `async`; 2 cho goi `onClear(true, true)` trong `onSave` da them
`await` (deu la `skipConfirm` nen than ham van chay dong bo, `await` chi de giu thu tu ro rang).

**D. Phong to man hinh (`zoom` theo nac, nho lua chon)**

- 4 nac 100/125/150/175%, nut `A−` / `A+` nam o dong RAW canh den tin hieu can, luu
  `localStorage['ws2.co-hien-thi']`. **Mac dinh 125%** — nguoi dung da noi thang ban 100% kho nhin.
- De tho tu chinh chu khong chot cung mot con so: moi tram mot co man hinh, dung gan xa khac nhau,
  doan sai thi hoac chu van be hoac bang 9 dong tran khoi man.
- Dung `zoom` chu khong phai `transform: scale()` — zoom tinh lai bo cuc that nen chu van net.
  **Bay:** zoom nhan ca don vi `vw`/`vh` ben trong. Da chia nguoc lai cho bien `--ws2-zoom` o 3 cho
  dung vw/vh (`.ws2-root` min-height, `.queue-modal`, `.thoai-box`); quen chia la nen cao hon man
  hinh dung bang he so phong va trang luon co thanh cuon doc thua.

**E. Kiem chung**

- `vue-tsc --noEmit` exit 0; `vite build` thanh cong.
- **CHUA xem bang mat tren trinh duyet.** Can nguoi dung: bam F11, quet mot don, bam CLEAR — phai
  hien hop thoai trong trang va **van o toan man hinh**; roi thu SAVE khi con dong chua can de xac
  nhan phieu VAN in ra (day la cho de vo nhat, xem muc C1).

### 126. Duong can CUC BO: Agent phuc vu so can ngay tai may tram (ADR-013) — 2026-08-07

**A. Yeu cau:** *"RAW / Agent co cach nao de no co the nhanh nhu o 4.semiauto-small scale ... .xlsm khong"* -> sau khi trinh bay 3 phuong an, nguoi dung chon **cach A**.

**B. Do TRUOC khi sua, khong doan**

| Chang | Do duoc |
|---|---|
| Agent doc `putty_log.txt` | moi **10ms** (`Scale:ReadIntervalMs`) — da ngang `StartFastLoop` cua VBA |
| Agent -> backend | doi toi **200ms** (`Scale:PushIntervalMs`) roi moi POST |
| Backend | `php artisan serve` MOT tien trinh: 1 request 18-23ms nhung **6 request dong thoi mat 110ms** = dung 6 lan, tuc xep hang noi duoi (do tren may dev, endpoint `/api/devices/readings`) |
| Trinh duyet -> backend | hoi moi **200ms** (`POLL_MS_WEIGHING`) |

Tong ~**400-900ms**. VBA doc cung file do, cung may, ghi thang vao form — khong co chang nao.
**Ket luan: Agent von da nhanh bang VBA; cham nam o hai chang di-ve may chu.**

**C. Da lam**

- **ADR-013** trong `architecture-decisions.md` — ghi ca 2 phuong an bi loai (giam nhip / Reverb) va ly do.
- `agent/ScaleSnapshot.cs` (moi): ban chup so can dung chung. **KHONG** de duong cuc bo tu goi
  `ScaleReader.ReadCurrentWeightWithStability()` — ham do khong thuan, no nap tung lan doc vao
  StableFilter ma "on dinh" = hai lan doc LIEN TIEP giong nhau; goi tu luong thu hai la chen lan
  doc la vao giua chuoi do va lam hong chinh co on dinh (co ma luong chot bi va dieu kien cho
  phep luu deu dua vao). Dung MOT noi doc can, moi noi khac doc lai ban chup.
- `agent/LocalWeightServer.cs` (moi): `HttpListener` **chi nghe 127.0.0.1**, `GET /weight`, tra
  **dung hinh dang JSON cua `DeviceController::getReading`** de frontend dung mot doan xu ly cho
  ca hai nguon.
- `Worker.cs`: ghi ban chup ngay tai nhip doc 10ms, **truoc va doc lap voi moi viec mang** (dat
  sau cong nhip day 200ms la vut bo dung cai duong cuc bo sinh ra de co).
- `useScaleFeed.ts`: thu duong cuc bo TRUOC, hong thi roi thang xuong backend **ngay trong nhip
  do** (khong bo trang mot nhip), va nghi 30 giay moi thu lai. Xuat them `nguonCucBo`.
- 2 man cân: nhip poll **60ms** khi duong cuc bo song (`POLL_MS_CUC_BO`) thay vi 200ms — khong
  ton gi cua ai vi cuoc goi khong roi khoi may. Them chi bao "⚡ Agent tai cho" / "☁ qua may chu".
- 3 file `appsettings*.json`: them khoi `Local`. **Ban `large-inout` = RACK_ONLY -> tat**, va code
  cung tu chan (`Worker.ScaleEnabled`): no khong doc can, ma no cai chung may voi `DFAgentLarge`
  (khac service name nen chay song song duoc) thi co the **GIANH MAT cong 8771** cua ban that.
- MSI **4.2.0.0**, build lai ca 3, da copy sang `backend/public/downloads/`.

**D. Ba diem de lam sai, da ghi ro trong code**

1. **Private Network Access.** Mo man bang `http://10.0.60.209:3001` (IP rieng) roi goi xuong
   loopback la request "xuyen vung mang" theo luat PNA cua Chrome — phai tra
   `Access-Control-Allow-Private-Network: true` cho preflight, thieu la Chrome chan, duong cuc bo
   **chet cam** va man hinh lang le tut ve duong cham ma khong ai hieu vi sao. Co test rieng.
2. **`AbortSignal.timeout()` khong dung duoc** — build con ha muc tieu xuong chrome >= 49 va
   `@vitejs/plugin-legacy` khong polyfill no. Dung `AbortController` + `setTimeout`.
3. **`fetch` chu khong phai `axios`** — axios da bi dat `baseURL` tro backend va co interceptor
   gan token dang nhap; Agent la tien trinh khac, gui token sang la ro mot thu no khong can biet.

**E. Kiem chung — do that**

- `dotnet test`: **51/51 pass** (them 7 test moi cho duong cuc bo: hinh dang JSON, preflight PNA,
  chua doc duoc lan nao -> `has_reading=false`, 404, tat bang cau hinh, chan RACK_ONLY).
  Test chay khong can quyen admin -> `HttpListener` tren 127.0.0.1 khong doi urlacl.
- **Chay Agent that** (`Backend:Urls` tro `127.0.0.1:1` de KHONG cham backend production):
  1 lan goi cuc bo **min 0.95ms / trung vi 2.11ms / max 2.84ms**; `age_ms` lon nhat **15ms** —
  so can khong bao gio cu qua 15ms, tuc ban chup that su duoc ghi lai moi nhip 10ms.
- **CORS bang trinh duyet that**: trang o `http://127.0.0.1:3999` goi `http://127.0.0.1:8770`
  (khac origin), Edge headless -> `OK {...,"source":"AGENT_LOCAL","age_ms":5}`.
- `vue-tsc --noEmit` exit 0; `vite build` thanh cong. 3 MSI doc nguoc ProductVersion = **4.2.0.0**.
- **CHUA thu tren may tram that** — can cai `DFAgentSetup-CanNho.msi` 4.2.0.0 len may can nho roi
  mo `/weighing-station-v2`: chi bao phai hien **"⚡ Agent tai cho"**. Neu van "☁ qua may chu" thi
  doc Event Log cua service `DFAgentSmall` tim dong "Duong can cuc bo:" — thieu dong do la cong
  bi chiem hoac `Local:Enabled=false`.

### 127. /weighing-station-large: cung bo hop thoai trong trang (khong con van khoi F11) — 2026-08-07

**A. Yeu cau:** *"http://localhost:3001/weighing-station-large ben nay toi cung muon tuong tu clean k bi mat f11,...."*

**B. Da lam — TACH RA DUNG CHUNG thay vi chep doi**

Muc 125 viet bo hop thoai nam GON trong `WeighingStationV2.vue`. Nay man cân to can y het, tuc da
du 2 noi dung (`coding-standards.md` muc 4: khong them truu tuong khi chua co tu 2 nghiep vu tro
len). Da tach:

- `composables/useHopThoai.ts` — trang thai + `hoiXacNhan()` / `baoTin()` / `dongThoai()`.
- `components/HopThoaiVba.vue` — phan nhin thay + keo tieu diem vao nut AN TOAN khi mo.

`WeighingStationV2.vue` chuyen sang dung 2 file nay (bo ~60 dong logic + CSS trung lap).
Bien CSS doi ten `--ws2-zoom` -> `--df-zoom` cho trung tinh vi component dung chung phai doc no.

Man cân to: doi **5 `alert`** -> `await baoTin`, **2 `confirm`** -> `await hoiXacNhan`, `onClear`
thanh `async`, 2 cho goi `onClear(true, true)` them `await`. Da grep xac nhan **khong con
`alert`/`confirm` goc nao** o ca hai man (chi con trong dong ghi chu).

**C. Bay quan trong — lap lai y het muc 125, va o day cung phai sua**

`onSave` cua man cân to cung mo cua so in bang `window.open` NGAY SAU hop xac nhan. `window.confirm`
cu dong bo nen con "user activation"; hop thoai moi bat buoc `await`, ma mo cua so sau mot `await`
la dung truong hop Chrome/Edge chan. Da **chuyen `window.open` len TRUOC** hop xac nhan, bam
"KHONG" thi `printWin?.close()`. Khong sua thi bam SAVE khi con dong chua can se luu duoc nhung
**khong in ra phieu**.

**D. Phan "to hon" thi KHONG lam gi — va day la ket luan tu do dac, khong phai bo sot**

Man cân to **da tu phong to san**: no dung mot "stage" kich thuoc co dinh (979 x 728px, quy tu
734.26 x 546.01pt cua form VBA goc) roi `fitStage()` co gian cho lap day cua so, chan tren 2.0.
Tinh thu he so that:

| Man hinh | He so tu dong |
|---|---|
| 1920x1080 F11 | 1.46 |
| 1920x1080 con sidebar/topbar | 1.27 |
| 1366x768 F11 | 1.03 |

Deu **duoi tran 2.0**, tuc dang bi chinh kich thuoc cua so chan lai — khong con cho trong nao de
mot nut A−/A+ them vao. Them nut phong to o day la dat mot nut vo tac dung, hoac te hon la de tho
phong qua co roi form tran ra ngoai khung. Man V2 khong tu co gian (bo cuc thuong, kich thuoc px
co dinh) nen o do nut A−/A+ moi co viec de lam.

**E. Kiem chung**

- `vue-tsc --noEmit` exit 0; `vite build` thanh cong.
- `grep` xac nhan sach `alert`/`confirm` o ca hai man.
- **CHUA xem bang mat tren trinh duyet** o ca hai man. Can nguoi dung: bam F11, quet mot don, bam
  CLEAR -> phai hien hop thoai trong trang va VAN o toan man hinh; roi bam SAVE khi con dong chua
  can -> phai hien hop thoai VA sau khi bam DONG Y phai IN RA PHIEU (day la cho de vo nhat, muc C).

### 128. Tem van "mo" sau muc 124 — loi la NGUONG nhi phan hoa, khong phai khu rang cua (2026-08-07)

**A. Nguoi dung bao:** *"tem in ra bi mo, toi muon no binh thuong thoi, tat khu rang cua di"*.

**B. Dinh chinh muc 124.** Khu rang cua ĐA tat roi (canvas 1-bit, trong anh khong con mot sac xam
nao — da do). Cai lam mo la **nguong 128 tui dat thanh 176** voi ly le "net day ma lien de doc hon
manh ma dut". Sai: o co 12 dot, nguong do nuot ca vung xam nhat **GIUA hai net**, cac chu dinh vao
nhau thanh mang den — mat doc ra dung la "mo". Toi tu tao ra dung cai trieu chung dang phai chua.

**C. Da so BA to hop bang anh 1:1 o dung 203dpi, khong doan**

| To hop | Ket qua |
|---|---|
| thuong + 128 | **CHU VO** — net manh hon 1.5 dot bien mat han. "WEIGHT" ra "WE GHT", "TESTQR" ra "^ESTQR", **"2026" ra "2025"**. So doc sai la hong nguy hiem, khong phai xau. |
| dam + 176 (ban muc 124) | ky tu dung nhung day, dinh nhau -> "mo" |
| **dam + 128** | ky tu dung, net dac, khong dinh  <-- **da chot** |

Bai hoc: **do dam giu cho ky tu khoi vo, con NGUONG moi la thu quyet dinh net hay nhoe.** Truoc do
tui gan hai thu nay vao nhau ("dam thi phai nguong cao") va do chinh la loi.

**D. Da lam**
- `NGUONG` 176 -> **128**; giu in dam moi dong (thu bo dam theo dung VBA roi, chu vo, xem bang tren).
- Ghi thang bang so sanh vao comment cua `NGUONG` kem canh bao "dung doi neu chua render lai anh
  1:1 va NHIN" — de lan sau khong ai (ke ca toi) chinh mo mot con so roi tu tin no tot hon.

**E. Kiem chung**
- Anh 1:1 render lai: doc duoc tung ky tu, khong con mang den dinh nhau.
- `check-weigh-slip.mjs` 8/8 (payload khong doi), `vue-tsc` exit 0, `vite build` sach.
- **VAN CHUA in tren may TSC that.** Anh 1:1 la dung so dot may in nhan, nhung con mot khau nua
  chua kiem duoc bang script: Chrome rasterize trang luc in o do phan giai cua NO roi driver moi
  ha xuong 203dpi. Neu khau do lam nhoe lai thi phai in thu moi biet — can nguoi dung in 1 to.

### 129. Nut CHECK cua /weighing-station-v2 -> mo Lich su can sang TAB MOI (2026-08-07)

**A. Yeu cau:** *"khi toi an check toi muon sang 1 tap khac, tap nay la lich su can da save va co nut in lai"*.

**B. Da lam**
- `WeighingStationV2.vue`: nut CHECK goi `moLichSuCan()` -> `window.open('/weighing-history', '_blank')`.
  **Tab moi chu khong dieu huong**: man can dang giu ca me do lan so da can CHUA LUU
  (`capturedWeights`), roi trang la mat sach. Mo tab rieng thi tra cuu xong dong tab la ve dung cho.
- `/weighing-history` **da co san** dung thu can: moi dong la mot vong can da COMPLETED, co loc
  ngay/tim nhanh, va co nut **IN LAI (🖨)** ghi Audit Log `WEIGH_SLIP_REPRINT`. Khong phai lam moi.

**C. Cai bay suyt lam tinh nang chet cam**

Tai khoan tram bi khoa cong doan (WS-001): `router/index.ts` da tay MOI duong khac ve
`lockedScreen`. Neu chi doi nut ma khong dung toi router thi **tab moi bi da nguoc ve man can ngay
luc mo**, va nguoi dung chi thay "bam CHECK khong ra gi" — khong co loi nao de ma lan ra.

Da them `MAN_PHU_TRO` trong `router/index.ts`: noi DUNG mot man cho DUNG hai tram can. Ly do chap
nhan duoc: (1) Lich su can chi la xem lai nhung me ma chinh cong doan nay vua luu, khong phai di
sang cong doan khac; (2) no chi doc, hanh dong duy nhat la IN LAI ma viec do da co Audit Log bat
buoc. **Day la NOI QUYEN** — da ghi canh bao ngay tren bien do: them man vao day phai can nhac nhu
mot thay doi bao mat.

**D. Mat gi**

Nut CHECK truoc day mo `WeighingCheckerModal` — "Tra cuu ban thanh pham da can", tra theo
COLOR+CODE, **khong co nut in lai**. Nay khong con cho goi o man V2 nua. **Component van con
nguyen trong repo**, dung lai thanh mot nut rieng chi la mot dong — chua lam vi nguoi dung khong
yeu cau. Da go `import`/`ref`/the goi khong con dung de khong de lai code chet.

**E. Kiem chung**
- `vue-tsc --noEmit` exit 0; `vite build` thanh cong.
- **CHUA thu tren trinh duyet.** Can nguoi dung dang nhap **bang tai khoan `cannho`** (khong phai
  admin — admin khong bi khoa nen khong the hien duoc cai bay o muc C) roi bam CHECK: phai mo tab
  moi vao Lich su can va **o lai do**, khong bi da ve man can.

---

### 130. Bam SAVE/PRINT khong con van khoi F11 — bo cua so in, in bang IFRAME AN (2026-08-07)

**A. Trieu chung**

Sau muc 125/127 (bo `confirm`/`alert` sang `HopThoaiVba`) thi bam CLEAR da giu duoc F11, nhung bam
**SAVE** van van ra khoi toan man hinh. Nut PRINT cung vay.

**B. Nguyen nhan**

Khong phai hop thoai nua. La `window.open('', '_blank', 'width=780,height=980')` — luong SAVE mo
san mot **CUA SO trinh duyet moi** de in phieu vao do. Chrome/Edge **thoat che do toan man hinh
(F11)** cua cua so hien tai ngay khi trang mo mot cua so moi: cua so moi phai hien ra duoc thi cua
so dang phu kin man hinh buoc phai thu ve dang thuong. Khong co CSS/JS nao chan duoc.

(Luu y phan biet: `window.open(url, '_blank')` KHONG kem `width/height` chi mo mot **tab**, khong
lam mat F11 — nen nut CHECK o muc 129 van an toan.)

**C. Da sua**

Them `inPhieuTrongTrang()` trong `utils/slipPrint.ts`: in bang mot **iframe an** dat ngay trong
trang, khong tao cua so nao.
- Iframe day ra ngoai man hinh (`position:fixed; left:-10000px`) chu **khong** `display:none` —
  phan tu display:none khong duoc bo cuc, ma doan script tu in phai do `offsetWidth` cua bang moi
  chon duoc co chu.
- Dung lai MOT khung duy nhat cho moi lan in, khong tao/xoa moi lan: xoa dung luc thi phai rinh
  xem hop thoai in dong chua, ma khong co su kien nao bao tin cay duoc.
- `window.print()` trong iframe chi in NOI DUNG IFRAME, khong in trang cha.
- Trong `SCRIPT_TU_IN`, `window.close()` nay boc `if (!window.frameElement)` — trong iframe thi
  close() vo tac dung va Chrome ghi canh bao ra console.

**D. Duoc kem theo: het rang buoc "user activation"**

Iframe tao duoc sau `await`, nen bo duoc toan bo phan cong kenh `printWin` da dung tu 06/08:
khong con mo san cua so truoc hop xac nhan, khong con truyen `preOpened` vao `printSlip()`, khong
con nhanh bao loi "trinh duyet da chan cua so in" (khong con cua so de ma bi chan), khong con
`printWin?.close()` khi luu hong. `printSlip()` gio khong nhan tham so.

Ap dung cho **ca hai** man `/weighing-station-v2` va `/weighing-station-large` (nguoi dung da yeu
cau doi ngang hai man tu muc 127).

`printSlipHtml()` va `cuaSoThat()` VAN GIU trong `slipPrint.ts` — `WeighingHistory.vue` va
`WeighingStation.vue` con dung, hai man do khong chay F11 nen chua doi.

**E. Kiem chung**
- `vue-tsc --noEmit` exit 0; `vite build` OK; `scripts/check-weigh-slip.mjs` 8 pass / 0 fail.
- **CHUA thu tren trinh duyet va CHUA in thu tren may in that.** Can nguoi dung bam F11 roi bam
  SAVE: phai VAN o toan man hinh, va hop thoai in van hien ra binh thuong.

**F. Bo sung: van mat F11 — thu pham CUOI CUNG la HOP THOAI IN cua trinh duyet (2026-08-07)**

Bo cua so in (muc C/D) roi ma bam SAVE VAN van khoi F11. Nguyen nhan con lai: **hop thoai in cua
Chrome/Edge**. No la giao dien cap TRINH DUYET, muon hien ra thi cua so phai roi che do toan man
hinh. Trang web khong co cach nao can thiep — khong phai loi cua code.

Da **do thuc te** bang Edge tren may dev (script trong scratchpad: trang goi `fetch('truoc')` ->
`window.print()` -> `fetch('sau')`; hop thoai chan JS thi 'sau' khong bao gio toi):
- Chay thuong        -> chi nhan duoc "truoc"          -> print() BI CHAN, co hop thoai.
- Them `--kiosk-printing` -> nhan du "truoc" + "sau"   -> print() KHONG chan, in thang.

=> Cach duy nhat het mat F11 la **bo hop thoai in**, tuc mo trinh duyet bang `--kiosk-printing`.
Them file `tools/mo-man-can.bat` de mo man can dung co do.

Hai dieu kien bat buoc, ghi ro trong file bat:
1. May in TEM phai la **may in mac dinh** cua Windows tren may tram (`--kiosk-printing` luon in ra
   may in mac dinh, dung cai dat mac dinh cua driver, khong hoi ai).
2. Phai co `--user-data-dir` rieng. Neu Chrome dang mo san bang profile thuong thi lenh moi chi mo
   them mot TAB trong tien trinh cu va **moi co bi bo qua**.

Loi kem theo: in tro thanh TUC THI, dung nhu ban Excel VBA (`Sheet.PrintOut` cung in thang khong
hoi) — do bo duoc mot buoc bam tay moi lan luu me.

Con lai (chua lam): neu sau nay muon bo han trinh duyet khoi duong in thi phai in qua Local Agent
(ADR-002), viec do lon hon nhieu va chua can thiet.

---

### 131. Duong can cuc bo them SSE — Agent TU DAY so, bo hoi vong 25ms (Agent 4.4.0.0) — 2026-08-07

**A. Yeu cau**

"co cach nhan can nao nhanh hon khong, kieu lay truc tiep tu web vao can luon".

**B. Lay thang tu trinh duyet vao can: KHONG lam**

Trinh duyet co Web Serial API, nhung:
1. ADR-002 cam trang web noi chuyen thang voi phan cung.
2. **Chan cung**: cong COM la DOC QUYEN, mot luc chi mot chuong trinh mo duoc. PuTTY dang giu no,
   va chinh file log cua PuTTY la thu **ca Agent lan Excel VBA cu** cung doc. Trinh duyet gianh
   cong do la he Excel chet ngay — ma Phase 12 dang chay song song hai he.
3. Duoc khoang 15ms. Khong dang.

**C. Da lam: SSE tren duong cuc bo**

Truoc: Agent ghi ban chup moi 10ms, trinh duyet **hoi vong** moi 25ms. Nghia la so nao cung nam
khong trung binh 12ms trong bo nho Agent truoc khi co ai toi lay, va 24/25 lan hoi la hoi thua.

Nay them `GET /weight/stream` (SSE) canh `GET /weight` cu:
- `ScaleSnapshot` co them **chuong bao** (`ChoSoMoi()`) — TaskCompletionSource dung-mot-lan roi
  thay moi. Khong dung `event` C# vi ben cho la code bat dong bo; `event` goi lai DONG BO ngay
  tren luong doc can, tuc day mot goi qua socket ngay giua vong lap 10ms khong duoc phep chan.
  `RunContinuationsAsynchronously` bat buoc, cung ly do.
- `LocalWeightServer.PhucVuLuong()` day goi khi **so can hoac co on dinh DOI**, hoac khi da im qua
  500ms. KHONG so ca goi JSON de quyet dinh: trong goi co `age_ms` nhich moi mili giay nen so ca
  goi la lan nao cung "khac" -> 100 goi/giay, dung cai lang phi vua bo di.
- 500ms nhip tim la BAT BUOC, hai viec: (1) `age_ms` phai tiep tuc nhich len, khong thi can CHET
  CUNG trong y het can dang dung yen — ma man hinh dua dung vao `age_ms` de bat "MAT TIN HIEU" va
  de chan luu; (2) ket noi da dut chi lo ra khi ghi vao no.

**D. Frontend: SSE CHONG LEN poll, khong thay the**

`useScaleFeed.ts` mo `EventSource` toi `/weight/stream`. Nhip poll cu VAN CHAY va tro thanh
**nguoi canh chung**: `fetchLiveWeight()` thoat ngay neu SSE con co so ve trong 1500ms; im lau hon
la no tu ganh lai (duong cuc bo `/weight`, roi backend). Nho vay hong SSE o bat ky dang nao —
Agent cu chua co endpoint, Chrome chan, ket noi dut — cung chi la mat toc do, khong mat so can.

Hai bay da chan san:
- `onmessage` phai tu kiem `useSimValue` — no KHONG di qua `fetchLiveWeight` nen thieu dong do la
  so that de len so gia lap dang go tay ma khong de lai dau vet (dung loi cua V1).
- `onerror` phai goi `close()`. Bo tham chieu khong thi EventSource **tu noi lai vo han moi ~3
  giay** — may van phong khong co Agent se do ruc Console mai mai.

**E. Kiem chung**
- `dotnet test --filter LocalWeightServerTests`: **10 pass / 0 fail** (7 cu + 3 moi). Ba test moi:
  goi dau tien ve ngay khi ket noi; so moi ra khoi Agent **duoi 200ms** (day chu khong phai hoi);
  ghi lai so Y HET trong 1 giay chi ra 1-8 goi chu khong phai ~100.
  LUU Y: may nay khong co .NET 8 runtime, phai chay bang `DOTNET_ROLL_FORWARD=Major`.
- `vue-tsc --noEmit` exit 0; `vite build` OK.
- Da build lai 3 MSI len **4.4.0.0**, da copy sang `backend/public/downloads/` va
  `C:\Users\v240298\Downloads\BoCaiCan\`.
- **CHUA thu tren trinh duyet va CHUA cai ban 4.4.0.0 len may nao.** May dev dang cai 4.2.0.0
  (chua co `/weight/stream`) nen no se chay nhanh du phong cho toi khi cai ban moi.

**F. Ghi chu do luong con thieu**

May dev **khong co so can song**: `D:\scale\putty_log.txt` dung tu 04/08, PuTTY khong chay. Agent
van tra 0.01/stable vi doc lai dong cuoi file cu. Nen do tre THAT chua do duoc o day — phai do tai
may tram ngoai xuong.

---

### 132. IN/OUT cua /weighing-station-large "chua giong ban Excel 100%" — thieu han `SetTopMost Me, False` (Agent 4.5.0.0) — 2026-08-07

**Yeu cau goc:** *"xem co phuong an nao de toi co the dung in out giong 5.Semiauto- lockmove SEND
OVER6 - delta-stable-final-221.xlsm 100%"*.

**A. Doi chieu lai nguon that**

Giai nen `xl/vbaProject.bin` (MS-OVBA) cua workbook can to, doc `scaleform.btn_Out_Click` /
`btn_In_Click`, `Mod_sendRackauto`, `ModAPI_mouse`, `ModDelay_paste`. Tim ra 3 cho lech giua ban
port va ban goc:

1. **Thieu `SetTopMost Me, False` / `True`** — chi mang. Chi tiet o muc B.
2. Tre 2 giay do `Rack:PollIntervalMs` (Excel bam la chay ngay).
3. Nhanh OUT khi `txt_WEIGHT1` trong: ban goc ban thang `txt_RACK1..6` theo dung vi tri dong, KE CA
   o rong, KHONG loc "0"; ban web luon gom-nen-loc. Khac ket qua khi co dong trong o giua.

Muc nay chi sua (1) theo dung yeu cau; (2) va (3) van con.

**B. Loi that: khong ai go trinh duyet ra khoi mat truoc**

Ban VBA mo dau moi luot ban bang `SetTopMost Me, False` -> `DoEvents` -> `SmartDelay 150` ->
`ClickAt 10, 100`. Cau dau khong chi "go form Excel": no lam **lo cua so ung dung pha mau ra mat
truoc**, nho vay cu click (10,100) ngay sau moi roi dung vao no.

Ban port bo doan nay (ADR-012: *"Agent khong co form nen khong co gi de go"*) — dung cau chu, sai he
qua. Cai che ung dung pha mau bay gio **la trinh duyet chay toan man hinh F11/kiosk** cua chinh man
can. Ket qua: click (10,100) trung trinh duyet, 6 lan Ctrl+V do vao trang web, ma Agent **van ack
DONE** vi `SetCursorPos`/clipboard deu tra ve thanh cong. Bao thanh cong gia — tho tuong da cap rack.

**C. Da sua (Agent 4.5.0.0)**

- `agent/WindowFocus.cs` (moi): liet ke cua so (bo qua cua so cloaked va cua so cua chinh Agent),
  tim theo MOT PHAN tieu de va/hoac ten tien trinh, dua len truoc bang `AttachThreadInput` +
  `SetForegroundWindow` roi **cho co xac minh** toi khi no thuc su o truoc (tinh ca cua so con cung
  tien trinh — ung dung dich bat hop thoai thi tieu diem nam o hop thoai).
- `agent/RackSender.cs`: goi buoc tren truoc moi luot OUT/IN; **khong xac dinh duoc cua so dich thi
  FAILED ngay, khong ban mu** (`Rack:RequireTargetWindow`, mac dinh true). Ban xong tra tieu diem ve
  cua so dung truoc do = `SetTopMost Me, True`. Them canh bao (khong chan) khi toa do cau hinh nam
  NGOAI khung cua so dich — dau hieu chua do lai toa do tren may nay.
- Ly do hong that di nguoc len man hinh: `RackSender.LoiCuoi` -> ack `error_message` ->
  `GET /api/rack-dispatch/{id}` -> `rackDispatch.ts` hien nguyen van. Truoc day moi loi deu ra mot
  cau chung "xem log Agent tren may tram", ma tho dung o xuong thi khong mo duoc log may tram.
  Khi chua xac dinh duoc cua so, log Agent in ra **danh sach toan bo cua so dang mo** de lay tieu de.
- Cau hinh moi (`Rack`): `TargetWindowTitle`, `TargetProcessName`, `RequireTargetWindow`,
  `ForegroundTimeoutMs` (1500), `RestoreForeground`. Da them vao ca 3 file appsettings.
- ADR-012: them muc cap nhat 07/08/2026, gach dong "Khong port SetTopMost".

**KHONG doi:** toa do van la toa do MAN HINH TUYET DOI, thu tu buoc va moi moc tre giu nguyen. Phuong
an click TUONG DOI theo cua so van chua lam (chu du an da chot 03/08 la giu dung hanh vi ban goc).

**D. Kiem chung**
- `dotnet test`: **54 pass / 0 fail** (chay voi `DOTNET_ROLL_FORWARD=Major` — may nay khong co .NET 8
  runtime). Luu y: chua co test rieng cho `WindowFocus` — no thao tac cua so that cua desktop nen
  test tu dong se doi hoi mot ung dung that dang mo.
- `vue-tsc --noEmit` exit 0.
- Da build 3 MSI len **4.5.0.0**, da copy sang `backend/public/downloads/`.
- **CHUA cai va CHUA chay thu tren may tram co ung dung pha mau that.**

**E. Anh huong khi nang cap**

May dang chay 4.4.0.0 **bat buoc phai dien `Rack:TargetWindowTitle`** sau khi cai de len, neu khong
IN/OUT se bao hong (kem huong dan day du tren man hinh) thay vi ban mu nhu truoc. Do la co y: ban mu
im lang nguy hiem hon bao hong. Muon giu hanh vi cu: `Rack:RequireTargetWindow=false`.

**F. Bo sung 4.6.0.0 cung ngay — bo yeu cau khai bao tieu de cua so**

Phan hoi cua nguoi dung ngay sau khi ban 4.5.0.0: *"sao lai cai dat nhieu the, khong co cach nao bam
va no hoat dong nhu binh thuong a"*. Dung — va nghi lai thi ban 4.5 da hieu sai ban goc mot lan nua:
**chinh VBA cung KHONG BIET ung dung pha mau ten gi**. `SetTopMost Me, False` chi day cai dang che
xuong duoi roi click thang vao toa do, cai gi nam do thi nhan. Bat tho di tra tieu de cua so la them
mot buoc ban goc khong he co.

Lam lai dung nhu vay (`WindowFocus.CuaSoTaiCacDiem` + `DayXuongDay`):
1. `WindowFromPoint` tai TOAN BO toa do sap click (9 diem), leo len cua so goc, bo phieu theo da so —
   mot diem le roi trung mep cua so khac khong doi duoc ket qua. Bo nen desktop / thanh tac vu.
2. Neu ke thang phieu la TRINH DUYET (chrome/msedge/firefox/...) thi `SetWindowPos(HWND_BOTTOM)` day
   no xuong day = `SetTopMost Me, False`, cho `PreDelayMs` roi nhin lai.
3. Cai lo ra chinh la ung dung pha mau -> dua len truoc -> ban. Khong co gi lo ra (chi thay nen
   desktop) hoac van la trinh duyet -> FAILED kem ly do cu the, khong ban mu.

CO Y khong dung `SW_MINIMIZE`: ban goc cung chi bo always-on-top chu khong thu nho form, va thu nho
trinh duyet thi co nguy co no bung ra khoi toan man hinh luc khoi phuc — dung cai phien ma muc 125,
127, 130 da mat cong dep.

`TargetWindowTitle` / `TargetProcessName` VAN CON nhung thanh TUY CHON, chi dien khi vung toa do co
nhieu cua so chong nhau va buoc tu do chon nham (log ghi ro moi luot no chon cua so nao).

=> Cach cai bay gio: chay MSI, xong. Khong sua file cau hinh, khong chay PowerShell tra tieu de.

Kiem chung lai: `dotnet test` 54 pass / 0 fail, da build 3 MSI len **4.6.0.0**. Van CHUA chay thu
tren may tram co ung dung pha mau that.

---

### 133. Tai khoan tram can: thanh tren (topbar) MAC DINH THU GON, co nut bat lai (2026-08-08)

**Yeu cau:** *"http://localhost:3001/weighing-station-v2 toi muon o tai khoan can nho hoac can to thi
layout phia ben tren se mac dinh la an va co nut de an hien thi de tiet kiem k gian"*.

**Da lam** (frontend, khong dong backend):
- `services/layout.ts`: them `topbarPref` (`show` / `hide` / null) + `setTopbarPref()`, nho trong
  `localStorage[df_topbar_pref]`. Tach han khoi `isFullscreen` co san: `isFullscreen` la trang thai
  TUC THOI cua phien xem (cac trang tu dat lai luc mount/unmount, FullscreenButton con keo theo ca
  Fullscreen API cua trinh duyet), con cai nay la nep LAU DAI cua may tram, song qua F5.
- `AppLayout.vue`:
  - `isScaleAccount` — nhan dien theo TRAM GAN VOI TAI KHOAN (`authStore.user.workstation`:
    `default_route` la 1 trong 2 man can, hoac capability `SMALL_SCALE`/`LARGE_SCALE`), khong theo
    route dang mo. Yeu cau la "o tai khoan can", nen di sang man khac van giu nep thu gon.
    KHONG dung `currentWorkstation` lam can cu: no doi duoc bang tay va bang suy doan theo IP, lay no
    thi tai khoan back-office ghe tram can cung bi thu gon oan.
  - `topbarCollapsed = isScaleAccount && topbarPref !== show` -> mac dinh AN voi cannho/canto, tai
    khoan khac khong bao gio bi anh huong ke ca khi may con luu `hide` cua ca truoc.
  - Thay cho topbar 70px la dai mong 24px (`.topbar-collapsed`) gom nut "Thanh tren" + MA TRAM.
    CO Y khong dung nut noi `position: fixed`: goc phai tren man can la cum CLEAR/SAVE/NEXT, goc trai
    tren la o quet COLOR — nut noi o dau cung che mat mot thu bam hang ngay. Giu lai ma tram tren dai
    mong vi can sai tram = ghi du lieu sai cho, khong duoc phep bien mat cung thanh tren.
  - Trong topbar day du them nut thu gon lai (chi tai khoan tram can thay).

**Kiem chung:** `npx vue-tsc --noEmit` exit 0. **CHUA xem bang mat tren trinh duyet** — can dang nhap
`cannho` / `canto` mo `/weighing-station-v2` xac nhan: vao la thanh tren da thu gon, bam "Thanh tren"
thi hien lai day du (co nut Dang xuat), bam nut thu gon lai, F5 van nho lua chon.

---

### 134. Ban IN/OUT (Can to) CHAY NGAM O KHAY HE THONG nhu WeChat — Agent 4.7.0.0 (2026-08-08)

**Yeu cau:** *"DF Agent — Can to (IN/OUT) toi muon la loai ra du tat x thi van la chay ngam o nhu wechat ay"*.

**Trieu chung goc:** ban IN/OUT bat buoc chay TRONG PHIEN NGUOI DUNG (session 0 isolation, xem ghi
chu RunMode dau DFAgentSetup.wxs) nen no hien ra la mot cua so console tren thanh tac vu. Tho thay
cua so la de bam X -> nut IN/OUT cua /weighing-station-large chet cam cho toi khi co nguoi biet
duong bat lai bang shortcut Start Menu.

**KHONG lam duoc kieu "bat su kien bam X roi an di":** cua so console thuoc ve conhost.exe chu khong
phai tien trinh nay (khong subclass duoc tu ngoai tien trinh), con SetConsoleCtrlHandler /
CTRL_CLOSE_EVENT chi cho DON DEP chu KHONG huy duoc lenh dong — ham xu ly chay xong la Windows giet
tien trinh. Nen lam nguoc lai, va cung dung kieu WeChat hon:

**Da lam** — `agent/TrayIcon.cs` (moi) + `agent/Program.cs`:
1. Vao la AN HAN cua so console, Agent nam o khay he thong: bam dup xem nhat ky, chuot phai de
   an/hien hoac "Thoat han" (co hoi lai vi day la menu rat de bam nham).
2. Luc cua so nhat ky dang hien thi nut X cua no bi GO khoi menu he thong (GetSystemMenu +
   DeleteMenu SC_CLOSE) — lo tay bam cung khong tat mat Agent.
3. Chi dung/an console khi console do la CUA RIENG Agent (`GetConsoleProcessList` tra ve 1). Chay
   `dotnet run` tu cmd/Terminal thi console la cua cmd — an di la giau mat cua so cua chinh nguoi
   dang go lenh, va go luon nut X cua ho.
4. Nghe thong diep `TaskbarCreated`: Explorer khoi dong lai la moi bieu tuong khay bien mat; khong
   them lai thi Agent van chay nhung khong con duong nao vao, trong y het da tat.
5. CHAN CHAY TRUNG BAN (Mutex `Local\DFAgent-<Service:Name>`, chi khi Tray:Enabled): tu khi khong
   con cua so tren thanh tac vu, rat de bam shortcut lan hai vi tuong chua chay — ma hai ban la hai
   vong lay lenh rack tranh nhau mot hang doi. Bam lan hai chi hien hop thoai nhac.

**Tu viet P/Invoke, KHONG dung WinForms NotifyIcon:** NotifyIcon keo theo `net8.0-windows` +
UseWindowsForms, ma ban publish self-contained dung CHUNG cho ca ba bo cai — hai bo doc can (chay
service, khong bao gio co giao dien) cung phai ganh them Windows Desktop runtime. Shell_NotifyIcon
tran khong them mot byte phu thuoc nao (MSI van 28.1 MB nhu truoc).

**Pham vi:** `Tray:Enabled` mac dinh FALSE, chi bat trong `appsettings.large-inout.json`. Code con
tu chan them bang `WindowsServiceHelpers.IsWindowsService()` — session 0 khong co thanh tac vu de
hien bieu tuong, va MessageBox mo o do la hop thoai khong ai thay de bam.

**Kiem chung:**
- `dotnet build -c Release` sach (0 warning), `dotnet test` 54 pass / 0 fail (may dev khong cai
  runtime .NET 8 nen phai dat `DOTNET_ROLL_FORWARD=LatestMajor`).
- Chay THAT ban Release trong thu muc rieng o scratchpad (cau hinh cach ly: backend tro
  127.0.0.1:59999, Rack tat, Local tat — khong cham gi toi may chu that): Agent song sau 8 giay va
  in dung dong `Khay he thong: Agent chay ngam o goc phai duoi...`. Dong log nay CHI in sau khi
  Shell_NotifyIcon(NIM_ADD) tra ve true, tuc bieu tuong da vao khay that.
- Da build lai 3 MSI len **4.7.0.0** va copy sang `backend/public/downloads/`.
- **CHUA thu bang tay tren may tram that:** can cai `DFAgentSetup-CanTo-InOut.msi` moi roi kiem tra
  4 viec — (a) cai xong bam shortcut Start Menu: khong hien cua so nao, co bieu tuong o goc phai
  duoi kem bong thong bao; (b) chuot phai > Hien cua so nhat ky: cua so hien ra va nut X cua no xam;
  (c) bam shortcut lan hai: hien hop thoai "da chay san"; (d) chuot phai > Thoat han: hoi lai, dong
  y thi bieu tuong bien mat va nut IN/OUT tren man can bao "Agent CHUA lay lenh".


---

### 135. /qr-printer: them nut CLEAR WEIGHT cho khoi HOA CHAT (2026-08-09)

Nguoi dung chi vao o TRONG duoi nut SEND (khoanh do tren anh chup man hinh xuong) va yeu cau mot
nut xoa nhanh toan bo cot WEIGHT de dien lai tu dau.

**Nguyen nhan co khoang trong do:** UserForm `scaleform` goc chi co MOT nut `btn_clearWeight`
(toa do 246/90, phuc vu 9 o WEIGHT cua khoi THUOC NHUOM). Khoi HOA CHAT khong co nut tuong ung —
thao tac vien phai xoa tay tung o.

**Da lam** (`frontend/src/views/QrPrinterForm.vue`, BO SUNG ngoai ban VBA goc):
- Nut `CLEAR WEIGHT` thu hai tai `box(390, 66, 126, 24)` — canh phai trung mep phai nut SEND
  (516pt), nam gon trong khoang 66..90pt nen KHONG de len 3 nhan tieu de cot o 102pt (khoi hoa
  chat co nhan `chem CODE` chiem 402..450 nen khong the dat cung cao do 90pt nhu nut ben DYE).
- `handleClearChemWeight()`: chi xoa cot `weight` cua 9 dong `chem`, GIU nguyen RACK va ma —
  quet lai tem khong can thiet. Xoa xong focus o WEIGHT dong 1 khoi hoa chat (`focusCell(0, 5)`),
  doi xung voi `handleClearWeight()` cua khoi thuoc nhuom.
- Mau nut dung lai `c-activecaption` giong nut cu de thao tac vien nhan ra cung mot loai.

**Kiem chung:** `npx vue-tsc --noEmit` exit 0. **CHUA nhin bang mat tren trinh duyet** — can nguoi
dung mo /qr-printer bam thu.

**Chinh tiep trong cung phien (yeu cau: "nut ben phai sat nhu nut ben trai, va bo nut check di"):**
- Nut moi doi ve **96×24pt** dung bang nut ben trai, dat tai `box(486, 66, 96, 24)` — canh phai
  sat mep 582pt (mep phai cot WEIGHT hoa chat), doi xung voi nut trai sat mep 342pt cua khoi
  thuoc nhuom. Van phai de o 66pt chu khong phai 90pt: khoi hoa chat co nhan `WEIGHT` nam o
  504..540pt, dat 90pt la de len nhan. Cham day nut `print` (ket thuc dung 66pt), khong chong.
- **Da go nut `check`** (`box(522, 66, 60, 30)`) va keo theo toan bo hop thoai `checkform`
  (DATABASE CHECKER) + 5 ham `checkCleanString` / `splitCheckScan` / `clearCheckForm` / `runCheck`
  / `runCheckTimeSent` + `fmtDateTime` + `checkFit` + 4 lop CSS chi no dung (`.vba-f8`,
  `.vba-f1425`, `.vba-f1575`, `.vba-result`). Bo nut la mat duong duy nhat mo hop thoai, giu lai
  chi la code chet. **Muon khoi phuc thi lay lai tu git** (commit truoc 09/08/2026) — 2 endpoint
  backend `scale-measurements/checker` va `machine-dispatches/history` KHONG dong toi, van con.
- Sau chinh: `npx vue-tsc --noEmit` exit 0. Van CHUA nhin bang mat tren trinh duyet.

---

### 136. /weighing-station-large: to ra khi KHONG dung F11 + nac A-/A+ nhu /weighing-station-v2 (2026-08-09)

**Yeu cau:** *"toi se k dung f11, nen la toi muon giao dien to ra va tu thich nghi va co the chinh
kich co nhu /weighing-station-v2"*.

**Vi sao man nay nho khi khong F11:** mat form la kho CO DINH 979x728px, `fitStage` thu cho vua
khung nen no luon bi chan theo CHIEU CAO. Khung do lai nam trong `.content-container` cua AppLayout
(`padding: 24px`), cong them thanh tren -> 48px doc bi mat doi thang thanh ~6% co chu tren TOAN BO
mat form.

**KHONG dung `zoom` nhu V2.** Ben V2 bo cuc chay theo dong nen `zoom` tinh lai layout that. Ben nay
`zoom` se lam `fitStage` do duoc khung "to ra" dung bay nhieu lan roi thu nho lai y chung do -> bam
A+ khong thay gi doi. Nen he so cua tho NHAN VAO chinh ti le vua khung.

**Da lam** (`frontend/src/views/WeighingStationLarge.vue`):
1. **Go padding khung cha** — lop `df-man-can-tran-vien` dat tren `<body>` luc mount, go luc
   unmount; quy tac nam trong mot khoi `<style>` **KHONG scoped** o cuoi file (style scoped khong
   voi toi phan tu CHA). Kem `overflow: hidden` de khong hien thanh cuon doc mong khi do hut 1-2px.
2. **Nac A-/A+** trong dai `.webbar`: `MUC_PHONG = [1, 1.15, 1.3, 1.5, 1.75]`, nho o localStorage
   `wslarge.co-hien-thi`, **mac dinh 1** (khac V2 mac dinh 1.25 — o day 100% da la vua khit khung,
   khong phai kho goc cua form). Con so % vua la nhan vua la nut bam ve 100%.
3. `fitStage` tach lam 2: `tiLeVuaKhung()` (= moc 100%) va `fitStage()` = `vua * heSoPhong`. Tran
   tren nang **2.0 -> 3.0**, neu khong thi nac 1.5/1.75 chong len mot khung von da 1.3x se dung
   tran va thanh vo nghia.
4. `.stage-wrap.zoomed` (chi khi >100%): `display: block; overflow: auto` — CO Y cho tran va cuon.
   Khong giu flex vi flex item bi can giua ma lon hon khung thi phan tran bi cat o MEP TREN/TRAI va
   khong cuon toi duoc (`safe center` chua dung duoc, build con ha muc tieu xuong Chrome doi cu).
5. **ResizeObserver thu hai dat tren khung CHA** -> bat/tat thanh tren (nut "▾ Thanh tren"), an/hien
   sidebar, vao/ra Toan man hinh deu lam mat form do lai. Truoc do chi nghe `resize` cua cua so, ma
   nhung thao tac do KHONG doi kich thuoc cua so -> mat form giu nguyen co cu toi lan F5 ke. Day dung
   la canh hay gap khi khong dung F11.
6. `fitRoot` them chot "chi ghi khi lech > 1px" — chong vong do lap vo tan giua observer va viec gan
   lai chieu cao.

**Kiem chung:** `npm run build` (vite) exit 0. **CHUA nhin bang mat tren trinh duyet.**

**Chua dong toi:** `/weighing-station-v2` giu nguyen co che `zoom` cua no — hai man hai kieu bo cuc
khac han nhau, gop lam mot la sai o ca hai.

**Chinh tiep trong cung phien:** *"cai nay toi muon de no o me ben phai"* (dai thong tin: ma tram,
MAT TIN HIEU, LO 1 + COPY, A-/A+, gia lap, thong bao loi).

Dua `.webbar` tu DAY sang **cot doc sat mep PHAI**, rong co dinh 208px (`.wsl-root` doi
`flex-direction: column` -> `row`). Khong chi la doi cho: mat form ti le 979x728 (1.35) tren man
16:9 (1.78) LUON bi chan theo chieu cao, nen 34px doc lay lai duoc doi thang thanh co chu, con cho
thua theo chieu ngang von khong dung vao viec gi. Do 1920x1080: fit 1.349 -> 1.395.

Keo theo trong cung do:
- `.wb-msg` neo o **day cot** (`margin-top: auto`) va duoc **xuong dong** thay vi cat ellipsis —
  doc tron ly do ngay tai cho, khong phai re chuot xem tooltip. Neo day de moi lan co/het thong bao
  khong lam ca cum ben tren nhay vi tri.
- `.wb-rack` cho **wrap**: 6 ma rack hien du. Ban nam ngang truoc day phai cat bot, ma cat dung cai
  danh sach sap ban sang he pha mau la thu te nhat de giau.
- `.webbar` co `padding-bottom: 52px` chua cho nut "⛶ Toan man hinh" (position: fixed, right/bottom
  16px) — khong co thi nut de dung len o thong bao loi.
- **`@media (max-width: 1000px)` tra dai ve DAY**: duoi nguong do mat form da bi chan theo chieu
  NGANG, cat them 208px be ngang la mat form nho di that, dung nguoc muc dich.

`npm run build` exit 0. **Van CHUA nhin bang mat tren trinh duyet.**

---

### 137. May quet o MAY TRAM: "moi lan quet lai ra 1 cai khac, va con k dung o" (2026-08-09)

**Trieu chung nguoi dung bao:** tren may DEV thi binh thuong; tren MAY TRAM, cung con tem cung may
quet do, **Excel quet binh thuong** con web thi moi lan quet ra mot ket qua khac va roi vao khong
dung o. Viec Excel chay tot tren CHINH may do loai bo kha nang may quet cau hinh sai — loi nam o web.

**3 loi that trong `frontend/src/views/WeighingStationLarge.vue`:**

1. **O quet khong bao gio duoc xoa/boi den -> cu quet sau NOI THEM vao cu.** O nay vua hien thi ma
   mau (`:value` do `activeBatch.color` vao, dung nhu txt_COLOR ban goc) vua la o bat phim. Quet me
   1 xong o hien "DF9001", tieu diem tra ve o do voi con tro o CUOI. Quet me 2 -> DOM value thanh
   `DF9001#DF9002-LG2509-...` -> `parseDyeQr` tra color = "DF9001#DF9002". Sai mot kieu KHAC NHAU
   moi lan vi con tuy me truoc la me nao => **dung triêu chung "moi lan ra 1 cai khac"**.
   *Sua:* `@focus="boiDenOQuet"` + helper `veOQuet()` (focus + select) thay cho moi cho goi
   `scanInputRef.value?.focus()`.

2. **Chi nghe `@keyup.enter`, khong nghe `change` -> may quet hau to TAB thi hong.** VBA
   `txt_color_AfterUpdate` chay khi o MAT TIEU DIEM, tuc an ca Tab lan Enter. Web chi bat Enter, nen
   may quet cau hinh hau to Tab (chay hoan hao ben Excel): chuoi nam im trong o quet, Tab day tieu
   diem sang phan tu ke = **o RACK dong 1**, cu quet SAU do nguyen chuoi vao o rack do roi Tab tiep
   sang rack dong 2... => **dung trieu chung "khong dung o"**.
   *Sua:* them `@change="docOQuet"`. Kem chot chong chay hai lan (`tokenCuoi` + cua so 2 giay, bang
   `DUPLICATE_WINDOW_MS` cua services/scanner) vi Enter trong o text lam Chrome ban CA HAI su kien.

3. **Bam bat ky nut nao la tieu diem roi khoi o quet, cu quet ke tiep mat hang.** Trinh duyet chuyen
   tieu diem sang chinh cai nut vua bam (NEXT, ban phim so, OUT/IN, CLEAR) — form VBA khong co canh
   nay vi UserForm om tron ban phim cua cua so.
   *Sua:* `batPhimLacRaNgoai()` nghe `keydown` o `document` voi `capture: true` (chay TRUOC bo dem
   may quet toan cuc trong `services/scanner.ts`, von cung nghe keydown tang window): co ky tu go ra
   ma tieu diem KHONG nam trong o nhap nao thi keo ve o quet ngay tu ky tu dau. Tu chen ky tu dau
   roi `preventDefault` chu khong trong cho trinh duyet chen ho sau khi doi tieu diem — hanh vi do
   khac nhau giua cac trinh duyet, ma mat dung ky tu dau cua ma mau thi chuoi van "doc duoc", chi la
   sai, kieu sai im lang te nhat. Khong dung toi o RACK / o gia lap / luc dang mo hop thoai-bang.

**`npm run build` exit 0. CHUA thu bang may quet that tren may tram** — can nguoi dung xac nhan.

**`/weighing-station-v2` DINH Y HET CA 3 LOI** (cung mot khuon: `:value` binding + chi
`@keyup.enter` + khong co bat phim cap man hinh). CO Y chua sua vi nguoi dung chi hoi ve man can to
— can xac nhan truoc khi dong vao man dang chay san xuat.

---

### 138. CAN TO: so can nhap nhay "dung roi ve 0", NEXT chot bi luc duoc luc khong (Agent 4.8.0.0) — 2026-08-09

**Nguyen nhan goc — log THAT cua may tram** (nguoi dung gui, quyet dinh moi thu):

```
US,+000466.6  g
0000000
US,+000486.7  g
0000000
```

Cai can to phat **XEN KE** mot dong so that roi mot dong `0000000`. `ReadLastCompleteLine` lay
"dong cuoi cung khong rong" nen cu mot nhip trung so that, mot nhip trung `0000000` — ma chuoi do
`CleanWeight` parse ra **0.0 hoan toan hop le**. Day la kha nang (a) trong 3 gia thuyet muc truoc.

**Hai loi, sua ca hai** (`agent/ScaleReader.cs`):

1. **`LaDongSoCan()` — bo qua dong TOAN CHU SO khi chon dong cuoi** (dung o ca nhanh doc file
   PuTTY lan nhanh doc cong COM). Dieu kien dat HEP NHAT co the: mot khung can that luon co it nhat
   mot ky tu khong phai chu so — dau `,`, dau +/-, dau thap phan, hoac chu (ST/US, "g"/"kg"). Ca 2
   dinh dang dang chay o xuong deu thoa (`US,+000466.6  g` va `12,ST,GS,+000010.5g`). CO Y khong
   doi phai co dau "," hay dau +/-: doi chat hon thi gap mot con can xuat dinh dang khac la man
   hinh chet cam hoan toan, te hon han so voi nhieu.

2. **`StableFilter` phai nhan TOKEN SO, khong phai ca dong tho** — day la loi PORT SAI, doc lap voi
   chuyen nhieu o tren. VBA `PushRawToForm` goi `StableFilter(rawNum)` voi `rawNum` da qua
   `CleanScaleRaw` + `ExtractLastNumber`, tuc chi con CON SO. Ban .NET dua nguyen `rawInput` vao.
   Dong cua can to mang co trang thai ST/US o dau va co nay **nhay lien tuc ngay ca khi con so dung
   yen**:

   ```
   US,-008359.3  g
   ST,-008359.3  g      <- cung mot so, chuoi khac -> bo dem reset
   ```

   Hau qua: can dung yen roi ma Agent van bao "chua on dinh", nen bam NEXT khong chot duoc bi. So
   tren token thi hai dong tren la mot, dung nhu VBA.
   **KHONG** doc co ST/US de suy ra on dinh du cai can noi san: VBA khong dung no, doi dinh nghia
   "on dinh" la doi luon thoi diem chot bi — phai la quyet dinh rieng co nguoi xac nhan.

**Kiem chung:** `dotnet test` — **63/63 PASS** (chay voi `DOTNET_ROLL_FORWARD=Major` vi may dev chi
co .NET 3.1/9/10, khong co runtime 8.0). Them 3 test moi: `LaDongSoCan_LoaiDungDongNhieuToanChuSo`
(Theory 7 ca), `ReadWeightWithStability_DoiCoST_US_NhungSoKhongDoi_VanOnDinh`,
`LogXenKeDongNhieu_DocDungSoCuoiVaChotDuocOnDinh` (dung log that lam du lieu).

**Bump `PackageVersion` 4.7.0.0 -> 4.8.0.0** trong `DFAgentSetup.wxs`. **PHAI build lai MSI va cai
de len tren may tram can to** — sua nay nam trong Agent, deploy web KHONG dong toi.

---

### 135. Man can tu choi tem ma VBA van nhan: bo dieu kien "phai du 4 o dau" (2026-08-09)

**Cau hoi cua nguoi dung:** *"cho quet QR hien tai da giong voi 5.Semiauto- lockmove SEND OVER6 -
delta-stable-final-221.xlsm chua, khi quet xong QR lieu co day dung vi tri nhu trong form VBA?"* —
sau khi doi chieu thi con dung MOT cho lech, va nguoi dung yeu cau: *"de giong VBA duoc k"*.

**Doi chieu tan goc (khong doan):** giai nen `vbaProject.bin` cua chinh workbook do de doc nguyen van
`txt_color_AfterUpdate`, `CleanLeadingGarbage` (Mod_delta_raw 243-261), `BuildRackBatch`/
`FireRackBatch`, `btn_Out_Click`. May dev khong co Python/oletools nen tu viet bo giai nen (CFB +
MS-OVBA RLE) — script luu o scratchpad, dung lai duoc cho workbook khac. Ket qua: 6 buoc lam sach
chuoi, thu tu 4 o dau (COLOR / **CODE** / **MACHINE** / LV) va vong lap 9 bo ba deu KHOP.

**Cho lech duy nhat:** `docQrMeNhuom` doi ca 4 o dau khac rong moi nhan; VBA thi chi co dung mot cua
thoat `If s = "" Then GoTo SafeExit` — tem 3 o (lo chua gan may, LV trong) VBA van nap va dien duoc
o nao hay o do, con web day nham sang nhanh token "DF:ORDER:<uuid>" roi bao "khong doc duoc ma QR".
Dung lop loi da gap voi dau "#" o dau chuoi (commit f2fddd7).

**Da sua:**
- `frontend/src/utils/qrDyeParser.ts` — nguong nhan ha xuong `color` + `code`, bo yeu cau
  machine/level. KHONG ha xuong "chuoi khac rong" nhu VBA: `ScannerController::weighFromQr` can ca
  color lan code moi tim/tao duoc lo san xuat luc SAVE (422 neu thieu). Nhan vao roi chan o SAVE —
  sau khi tho da can xong ca me — te hon han noi ngay luc quet.
- Hai man `/weighing-station-v2` va `/weighing-station-large`: thong bao noi dung nguyen nhan that
  ("thieu COLOR hoac CODE"), kem cau "thieu MACHINE/LV thi van nap duoc".
- `backend/.../ScannerController.php` (CA HAI cho: `scanRawDyeQr` va `weighFromQr`) — tem thieu o
  MACHINE thi KHONG `firstOrCreate` may voi ma rong nua; `machine_id` nullable nen de trong. Neu
  khong, moi tem thieu may deu dinh chung vao mot ban ghi may "" trong danh muc. Cac cho doc deu da
  null-safe san ('N/A' / 'VD-COMMON').

**Kiem chung:** `node frontend/scripts/check-qr-parser.mjs` -> **21 pass / 0 fail** (14 ca cu doi
chieu JS vs PHP + 7 ca MOI cho cua nhan: tem 3 o va tem chi color+code phai NHAN, chi co color /
chuoi rong / token DF: phai TU CHOI). `npx vue-tsc --noEmit` exit 0, `php -l` sach.
**CHUA quet bang may quet that** tren man hinh — va **KHONG chay `php artisan test`** o may nay
(bo test se DROP SCHEMA app tren DB production vi .env tro 10.0.60.209).

---

### 136. /weighing-station-large: cot thong tin ben phai MAC DINH THU GON (2026-08-09)

**Yeu cau:** *"toi muon mac dinh la an di va co nut de hien thi ra neu can dung"* — noi ve cot ben
phai (ma tram, o ON DINH, "LO 1: ... COPY", A-/100%/A+, gia lap, dong thong bao).

**Da lam** (`WeighingStationLarge.vue`):
- `hienWebbar` + `datHienWebbar()`, nho trong `localStorage['wslarge.hien-cot-thong-tin']`, MAC DINH
  an. Cung kieu voi `KHOA_MUC_PHONG` (co hien thi) — may nao dat sao thi may do nho.
- Thu gon roi thi thay bang dai mong **26px** (thay cho 208px) co nut "THONG TIN" chu doc. Van la
  khoi TRONG luong bo cuc chu khong phai nut noi: `fitStage` do be rong con lai de phong mat form,
  nen thu gon xong form TU TO RA chiem cho — nut noi thi vua che mot goc form vua khong tra lai
  duoc cho do. Doi trang thai xong goi `nextTick(fitStage)` de do lai ngay.
- **Khong nuot mat tin hieu hong:** `coSuCo` = `statusMsg.bad` (loi thao tac / mat tin hieu can /
  mat ket noi may chu) hoac con me ket trong hang doi -> dai mong TU DOI DO va nhap nhay, nut ghi
  "SU CO", tooltip la nguyen van thong bao. CO Y khong tu bung ca cot ra: bung ra la mat form co
  lai ngay giua luc tho dang can, ma mat ket noi may chu thi lap lai lien tuc o xuong.
- Trong cot day them nut "THU GON >" de an lai.

**Kiem chung:** `npx vue-tsc --noEmit` exit 0. **CHUA xem bang mat tren trinh duyet** — can mo
`/weighing-station-large` kiem tra: vao la chi con dai mong ben phai va mat form to hon truoc; bam
"THONG TIN" thi cot hien lai day du; rut mang cho mat ket noi thi dai mong phai do + nhap nhay; F5
van nho lua chon.

**Chua ap cho `/weighing-station-v2`** — man cân nhỏ khong co cot nay (thong tin nam trong dai
`ws2-rawline` ngang duoi form), la bo cuc khac han nen khong sua lay.
