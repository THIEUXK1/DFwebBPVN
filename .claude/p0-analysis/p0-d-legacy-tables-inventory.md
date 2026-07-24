# P0-D — Inventory dữ liệu 3 bảng nghi vấn: `tbl_ToSend2`, `WAITING`, `tblSync`

Ngày kiểm kê: 2026-07-17 (dựa trên đồng hồ hệ thống phiên làm việc).
Nguồn: `docker exec df-postgres psql -U postgres -d production_web` — **chỉ chạy SELECT**, không có lệnh ghi/sửa nào được thực thi trong phiên này.
Database: `production_web`, schema `legacy_df_data` (staging) và `app` (target chuẩn hóa).

> **Kết luận nhanh (đọc trước):** Dữ liệu này thật, không phải giả lập, nhưng đã DỪNG cập nhật ít nhất từ cuối tháng 11/2025. `tblSync` rỗng hoàn toàn (0 dòng) trong bản sao hiện có — mâu thuẫn với giả định ban đầu trong đề bài rằng cả 3 bảng "có dữ liệu thật". Phát hiện quan trọng nhất của đợt kiểm kê này là: có một bảng thứ 4, `tbl_Waiting` (71 dòng, chữ thường, KHÁC với `WAITING` chữ hoa), mà script transform hiện tại coi là "unshifted" (không lệch cột) — nhưng dữ liệu mẫu thực tế cho thấy bảng này CŨNG bị lệch cột giống hệt kiểu lệch của `WAITING`. Đây là rủi ro cần xử lý trước FIX-004.

---

## 1. `legacy_df_data."tbl_ToSend2"`

### 1.1 Schema (14 cột, tất cả kiểu `text`)
```
1  ID        text
2  COLOR     text
3  CODE      text
4  CONFIRM1  text
5  MACHINE   text
6  TANK      text
7  LEVEL     text
8  CONFIRM2  text
9  SENDING   text
10 SENT      text
11 TIME1     text
12 TIME2     text
13 TIME3     text
14 ISSENT    text
```

### 1.2 Số dòng
`SELECT COUNT(*) ...` → **696 dòng**.

### 1.3 Mẫu dữ liệu (10 dòng đầu, nguyên văn)
```
ID=0            COLOR=(rỗng) CODE=EP68132  CONFIRM1=SE5775 MACHINE=OK TANK=VD15 LEVEL=(rỗng) CONFIRM2=450 SENDING=OK SENT=0 TIME1=0 TIME2=11/18/2025 14:04:00        TIME3=11/20/2025 10:12:00        ISSENT=true
ID=0            COLOR=(rỗng) CODE=AP81742  CONFIRM1=SF5415 MACHINE=OK TANK=VD03 LEVEL=(rỗng) CONFIRM2=100 SENDING=OK SENT=0 TIME1=0 TIME2=11/18/2025 09:02:00        TIME3=11/20/2025 10:12:00        ISSENT=true
ID=0            COLOR=(rỗng) CODE=AP72986  CONFIRM1=T5A321 MACHINE=OK TANK=VD03 LEVEL=(rỗng) CONFIRM2=100 SENDING=OK SENT=0 TIME1=0 TIME2=11/18/2025 08:13:00        TIME3=11/20/2025 10:12:00        ISSENT=true
ID=1            COLOR=(rỗng) CODE=MBP00219 CONFIRM1=T5A459 MACHINE=OK TANK=VD15 LEVEL=(rỗng) CONFIRM2=50  SENDING=OK SENT=0 TIME1=0 TIME2=11/18/2025 04:50:00        TIME3=11/20/2025 10:23:00        ISSENT=true
ID=2            COLOR=(rỗng) CODE=HS31926  CONFIRM1=L12083 MACHINE=OK TANK=VD09 LEVEL=(rỗng) CONFIRM2=50  SENDING=OK SENT=0 TIME1=0 TIME2=11/17/2025 22:32:00        TIME3=11/20/2025 10:23:00        ISSENT=true
ID=3            COLOR=(rỗng) CODE=YS47059  CONFIRM1=SF5839 MACHINE=OK TANK=VD05 LEVEL=(rỗng) CONFIRM2=50  SENDING=OK SENT=0 TIME1=0 TIME2=11/18/2025 06:24:00        TIME3=11/20/2025 10:23:00        ISSENT=true
ID=1763609526   COLOR=(rỗng) CODE=HS52065  CONFIRM1=L10049 MACHINE=OK TANK=VD09 LEVEL=(rỗng) CONFIRM2=50  SENDING=OK SENT=0 TIME1=0 TIME2=11/17/2025 22:28:00        TIME3=11/20/2025 10:32:00        ISSENT=true
ID=-882633985   COLOR=(rỗng) CODE=AP71485  CONFIRM1=T7020  MACHINE=OK TANK=VD01 LEVEL=(rỗng) CONFIRM2=100 SENDING=OK SENT=0 TIME1=0 TIME2=11/18/2025 06:24:00        TIME3=11/20/2025 10:32:00        ISSENT=true
ID=1763612900   COLOR=(rỗng) CODE=HS24445  CONFIRM1=Y7568  MACHINE=OK TANK=VD06 LEVEL=1A    CONFIRM2=450 SENDING=OK SENT=0 TIME1=0 TIME2=11/20/2025 11:28:00 AM     TIME3=11/20/2025 11:28:00 AM     ISSENT=true
ID=-1128417259  COLOR=(rỗng) CODE=DP20337  CONFIRM1=T5269  MACHINE=OK TANK=VD03 LEVEL=3C    CONFIRM2=50  SENDING=OK SENT=0 TIME1=0 TIME2=11/20/2025 10:58:00 AM     TIME3=11/20/2025 11:49:00 AM     ISSENT=true
```
Quan sát: `MACHINE` luôn = `"OK"` (100% trong 696 dòng — xem 1.6), `TANK` luôn dạng `VDxx`, `TIME1` luôn `0` hoặc rỗng, `TIME2`/`TIME3` chứa timestamp thật (2 định dạng khác nhau xen kẽ: `MM/DD/YYYY HH24:MI:SS` và `MM/DD/YYYY H:MI:SS AM/PM`).

### 1.4 Duplicate ID
`SELECT "ID", COUNT(*) ... GROUP BY "ID" HAVING COUNT(*)>1` → chỉ **`ID=0` trùng 3 lần**. Tổng 696 dòng nhưng chỉ **694 giá trị ID phân biệt** (`COUNT(DISTINCT ID)=694`).
Giá trị ID rất bất thường: có ID nhỏ tuần tự (`0,1,2,3…`) xen lẫn số lớn kiểu Unix-epoch/ngẫu nhiên có dấu (`1763609526`, `-882633985`, `-1128417259`, độ dài 10-11 ký tự). → ID **không đáng tin cậy làm khóa chính hoặc để sắp thứ tự thời gian** — không nên dùng ID DESC để suy ra "mới nhất".

### 1.5 Khoảng thời gian dữ liệu
- `TIME1`: luôn rỗng hoặc `'0'` → **không dùng được**.
- `TIME2` (parse cả 2 định dạng AM/PM và 24h): **MIN = 2025-11-17 22:28:00, MAX = 2025-11-28 07:47:19**.
- `TIME3`: **MIN = 2025-11-20 10:12:00, MAX = 2025-11-27 08:43:00**.
→ Toàn bộ 696 dòng nằm gọn trong cửa sổ **11 ngày, 17/11/2025 – 28/11/2025**.

### 1.6 Trường liên kết / JOIN match
- `MACHINE` distinct: chỉ 1 giá trị duy nhất `"OK"` (696/696 dòng) → **không phải mã máy**, càng củng cố giả thuyết lệch cột (cột tên "MACHINE" thực chất chứa một cờ trạng thái).
- `TANK` distinct: toàn `VDxx` (VD01–VD18), phân bố: VD09=73, VD06=60, VD15=60, VD03=59, VD04=49, VD08=49, VD02=47, VD16=46, VD14=44, VD12=44, VD05=43, VD07=41, VD11=36, VD17=18, VD18=8.
- JOIN test theo đúng câu lệnh yêu cầu (`TANK` vs `app.machines.code`):
  ```sql
  SELECT COUNT(*) FROM legacy_df_data."tbl_ToSend2" d
  LEFT JOIN app.machines m ON m.code = trim(d."TANK")
  WHERE m.id IS NULL;
  → 696 (100% KHÔNG join được)
  ```
- **Nguyên nhân cần làm rõ:** `app.machines` trong DB dev hiện chỉ có **5 dòng test/fixture** (`L1`, `M-FED01`, `M-PRT01`, `T5-01`, `TEST-M01`) — KHÔNG có mã `VDxx` nào cả, dù `legacy_df_scale.tbl_status` (nguồn thật của bước INSERT app.machines trong script transform) có đủ 18 mã máy `VD001..VD018`. `app.tanks` đang **rỗng hoàn toàn (0 dòng)**. `app.production_batches` cũng **rỗng (0 dòng)**.
  → Tỉ lệ JOIN-match 0% ở đây **một phần là do DB dev chưa chạy bước 1 của `03_transform_legacy_to_target.sql` (insert app.machines từ tbl_status) trên dữ liệu thật**, KHÔNG chỉ do mapping cột sai. Cần chạy transform bước 1 trước rồi test lại JOIN mới đánh giá được mapping đúng/sai một cách công bằng. Đây là điểm cần lưu ý khi lập acceptance criteria cho FIX-004 — không thể kết luận "mapping sai" chỉ từ số 0% này một mình.

### 1.7 Dữ liệu "mới nhất"
ID không tăng dần (xem 1.4) nên **không dùng ORDER BY "ID" DESC**. Sắp theo `TIME3` (mốc gần nghĩa "sent"/hoàn tất) DESC:
```
ID=-547731848  CODE=GS65229 CONFIRM1=L21470 TANK=VD16 LEVEL=4D CONFIRM2=100 TIME2=11/27/2025 8:09 AM  TIME3=11/27/2025 8:43 AM
ID=-717174349  CODE=HS46532 CONFIRM1=T7038  TANK=VD08 LEVEL=2B CONFIRM2=220 TIME2=11/27/2025 2:57 AM  TIME3=11/27/2025 8:32 AM
ID=1081926074  CODE=EP49366 CONFIRM1=CT2013 TANK=VD03 LEVEL=3C CONFIRM2=50  TIME2=11/26/2025 9:03 PM  TIME3=11/27/2025 8:30 AM
ID=-184234691  CODE=EP68418 CONFIRM1=L23892 TANK=VD02 LEVEL=2B CONFIRM2=220 TIME2=11/27/2025 8:08 AM  TIME3=11/27/2025 8:29 AM
ID=922362796   CODE=P61773  CONFIRM1=L11471 TANK=VD02 LEVEL=3C CONFIRM2=50  TIME2=11/27/2025 8:09 AM  TIME3=11/27/2025 8:12 AM
```

### 1.8 Bảng còn "sống" hay đã "chết"?
Dòng cuối cùng có timestamp `27/11/2025 08:43`. Tính đến ngày hiện tại (giữa tháng 7/2026), **dữ liệu đã ngừng ghi khoảng 8 tháng**. Vì đây là bản sao staging nhập 1 lần từ file Access/Excel gốc (theo README `sql_migration/README.md`: "tbl_ToSend2: 696 dòng" — con số khớp 100% với DB), không có cơ chế đồng bộ liên tục nào tới Postgres, nên **không thể kết luận hệ thống VBA gốc đã ngừng hoạt động** — chỉ có thể kết luận **bản snapshot Postgres này đã cũ (dừng tại thời điểm export, ~cuối 11/2025)**. Cần xác nhận với người vận hành xưởng liệu file Excel/Access nguồn (không có mặt tại `F:\DF`) có còn được ghi tiếp sau 28/11/2025 hay không.

---

## 2. `legacy_df_data."WAITING"`

### 2.1 Schema (15 cột, tất cả kiểu `text`, có thêm `ID1` so với `tbl_ToSend2`)
```
1  ID1       text
2  ID        text
3  COLOR     text
4  CODE      text
5  CONFIRM1  text
6  MACHINE   text
7  TANK      text
8  LEVEL     text
9  CONFIRM2  text
10 SENDING   text
11 SENT      text
12 TIME1     text
13 TIME2     text
14 TIME3     text
15 ISSENT    text
```

### 2.2 Số dòng
**57 dòng**.

### 2.3 Mẫu dữ liệu (toàn bộ 57 dòng — bảng nhỏ nên trích full thay vì chỉ 10 dòng để thấy rõ pattern)
```
(dòng 1 toàn rỗng)
ID1=  ID=  COLOR=L23892   CODE=OK  CONFIRM1=VD02   MACHINE=       TANK=     LEVEL=  ...
ID1=  ID=  COLOR=L33419   CODE=OK  CONFIRM1=VD09   MACHINE=X      TANK=50   LEVEL=  ...
ID1=  ID=  COLOR=T7319    CODE=OK  CONFIRM1=VD09   MACHINE=X      TANK=100  LEVEL=  ...
ID1=  ID=  COLOR=TA5130   CODE=OK  CONFIRM1=VD09   MACHINE=X      TANK=50   LEVEL=  ...
ID1=  ID=  COLOR=TA5183   CODE=OK  CONFIRM1=VD09   MACHINE=X      TANK=50   LEVEL=  ...
ID1=  ID=  COLOR=L11497   CODE=OK  CONFIRM1=VD06   MACHINE=X      TANK=220  LEVEL=  ...
ID1=  ID=  COLOR=T5102    CODE=OK  CONFIRM1=VD09   MACHINE=X      TANK=50   LEVEL=  ...
... (tổng 57 dòng, đầy đủ trong log phiên làm việc)
```
Tất cả 57 dòng đều: `ID1` = rỗng, `ID` = rỗng, `MACHINE`/`TANK`/`LEVEL`/`CONFIRM2`/`SENDING`/`SENT`/`TIME1`/`TIME2`/`TIME3`/`ISSENT` = rỗng hoàn toàn. Chỉ `COLOR`, `CODE`, `CONFIRM1` (và đôi khi `MACHINE`, `TANK`) có giá trị.

**Nhận xét lệch cột:** `COLOR` chứa giá trị dạng mã lô/CONFIRM1 thật (`L23892`, `T7319`, `SE5775`...), `CODE` chứa hằng số `"OK"` (giống hệt giá trị cột `MACHINE` trong `tbl_ToSend2`), `CONFIRM1` chứa mã `VDxx` (giống giá trị cột `TANK` trong `tbl_ToSend2`), `MACHINE` chứa `"X"` hoặc mã cấp (`3C`), `TANK` chứa số (`50`,`100`,`220`,`450` — giống dải giá trị `CONFIRM2` trong `tbl_ToSend2`). → Cùng kiểu lệch 2 cột sang trái như `tbl_ToSend2`, và **khớp đúng với comment trong script transform** (`03_transform_legacy_to_target.sql` dòng 190-203, xem mục 5).

### 2.4 Duplicate ID
`ID` **100% rỗng ở cả 57 dòng** (đã kiểm bằng `COUNT(*) FILTER (WHERE "ID" IS NOT NULL AND "ID" <> '')` = 0, và `"ID1"` cũng = 0) → không có khóa nào để kiểm tra trùng lặp; cột ID/ID1 trong bảng này **vô dụng hoàn toàn** trên dữ liệu thật.

### 2.5 Khoảng thời gian dữ liệu
`TIME1`, `TIME2`, `TIME3` **100% rỗng ở cả 57 dòng** → **không xác định được mốc thời gian nào từ chính bảng `WAITING`**. Đây là khác biệt quan trọng so với `tbl_ToSend2` (vẫn còn TIME2/TIME3). Bảng `WAITING` giống một snapshot trạng thái "đang chờ" tại 1 thời điểm (không có log thời gian đi kèm từng dòng).

### 2.6 Trường liên kết / JOIN match
Dùng đúng cách hiểu lệch cột của script transform (`CONFIRM1` = mã máy thật):
```sql
SELECT COUNT(*) total, COUNT(*) FILTER (WHERE m.id IS NULL) unmatched
FROM legacy_df_data."WAITING" d
LEFT JOIN app.machines m ON m.code = trim(d."CONFIRM1")
WHERE nullif(trim(d."CONFIRM1"),'') IS NOT NULL;
→ total=55, unmatched=55 (100% KHÔNG join được)
```
Cùng lý do như 1.6: `app.machines` dev chưa có mã `VDxx` nào.

### 2.7 Dữ liệu "mới nhất"
Không thể xác định — không có ID số hoá được và không có timestamp nào trong bảng. Thứ tự vật lý các dòng trong bảng (ctid) là manh mối duy nhất còn lại, nhưng không đáng tin cậy làm "thời gian".

### 2.8 Bảng còn "sống" hay đã "chết"?
Không đủ dữ kiện thời gian để trả lời trực tiếp từ bảng này. Vì `WAITING` có kích thước nhỏ (57 dòng) và không có timestamp, nhiều khả năng đây là **snapshot trạng thái hàng đợi tức thời** (giống "current queue" chứ không phải log lịch sử) được chụp lại đúng lúc export dữ liệu — tương tự vai trò với `tbl_Waiting` (xem mục 4).

---

## 3. `legacy_df_data."tblSync"`

### 3.1 Schema (6 cột, tất cả kiểu `text`)
```
1 NextFE      text
2 FE1_Alive   text
3 FE2_Alive   text
4 FE3_Alive   text
5 FE4_Alive   text
6 FE5_Alive   text
```

### 3.2 Số dòng
```sql
SELECT COUNT(*) FROM legacy_df_data."tblSync";
→ 0
```
Xác nhận lại bằng `pg_stat_user_tables` (`n_live_tup=0, n_dead_tup=0`) — **không phải lỗi query, bảng thực sự trống**.

### 3.3–3.7 Mẫu dữ liệu, duplicate, timestamp, JOIN, "mới nhất"
**Không áp dụng được — bảng không có dòng nào.** `SELECT * ... LIMIT 10` trả về `(0 rows)`.

### 3.8 Bảng còn "sống" hay đã "chết"?
**Quan trọng — đính chính giả định trong đề bài:** đề bài giả định "cả 3 bảng có dữ liệu thật", nhưng `tblSync` **rỗng hoàn toàn trong bản Postgres dev hiện có**. `sql_migration/README.md` (dòng 22) đã ghi nhận từ trước: *"Các bảng TBL_INPUT_ALL, tbl_ToSend, tbl_OUTPUT_PROCESSING, tbl_ARCHIVE, tblSync hiện rỗng theo bản sao nhận được."* → đây không phải phát hiện mới, mà là tình trạng đã biết từ lúc import Access ban đầu. Không có bằng chứng nào (dữ liệu thật) để đánh giá tblSync "sống hay chết" — chỉ có thể nói: **file Access nguồn tại thời điểm export có bảng `tblSync` trống** (có thể do: (a) tính năng đồng bộ đa Front-End — tên cột gợi ý `NextFE`/`FE1_Alive`..`FE5_Alive` = theo dõi "Front-End nào đang sống" trong kiến trúc Access multi-user split — chưa từng được dùng trong bản cài đặt tại xưởng pilot, hoặc (b) bảng bị dọn/reset trước khi export, hoặc (c) tính năng này chỉ tồn tại trong 1 trong nhiều workbook chưa thu thập được).

---

## 4. Phát hiện phụ quan trọng: bảng `tbl_Waiting` (khác `WAITING`)

Ngoài phạm vi 3 bảng ban đầu, khi đọc `\dt legacy_df_data.*` phát hiện **9 bảng** trong schema, trong đó có **`tbl_Waiting`** (chữ thường "aiting") — MỘT BẢNG KHÁC với `WAITING` (chữ hoa toàn bộ) mà đề bài yêu cầu kiểm kê. Vì bảng này được script transform hiện tại xử lý riêng (khối "unshifted") và ảnh hưởng trực tiếp đến việc đánh giá đúng/sai của mapping `WAITING`, ghi nhận lại đây:

- Schema: 14 cột giống hệt `tbl_ToSend2` (không có `ID1`).
- Số dòng: **71** (khớp README).
- Mẫu dữ liệu:
```
Dòng 1: toàn rỗng, trừ SENDING=0, SENT=0, ISSENT=false
Dòng 2: ID=(rỗng) COLOR=EP68418 CODE=L23892 CONFIRM1=OK MACHINE=VD02 TANK=(rỗng) LEVEL=220 CONFIRM2=(rỗng) SENDING=0 SENT=0 TIME1=11/20/2025 3:04:00 PM ISSENT=false
Dòng 3: COLOR=HS54003 CODE=L33419 CONFIRM1=OK MACHINE=VD09 TANK=X LEVEL=50 TIME1=11/21/2025 7:24:00 PM ISSENT=false
Dòng 4: COLOR=HS45521 CODE=T7319 CONFIRM1=OK MACHINE=VD09 TANK=X LEVEL=100 TIME1=11/21/2025 7:31:00 PM ISSENT=false
Dòng 5: COLOR=HS48857 CODE=TA5130 CONFIRM1=OK MACHINE=VD09 TANK=X LEVEL=50 TIME1=11/21/2025 7:34:00 PM ISSENT=false
```

**Vấn đề phát hiện:** trong `sql_migration/03_transform_legacy_to_target.sql`, khối số 6 (dòng 140-160) gắn nhãn `tbl_Waiting` là **"unshifted tbl_Waiting"** và map trực tiếp `CONFIRM1`→`confirm_1`, `MACHINE`→máy (qua `normalize_machine_code(d."MACHINE")`), tức coi bảng này **KHÔNG bị lệch cột**. Nhưng dữ liệu thật cho thấy: `CONFIRM1` luôn = `"OK"` (giống cột MACHINE của `tbl_ToSend2`/CODE của `WAITING`), `MACHINE` chứa mã `VDxx` (giống TANK/CONFIRM1-thật), `TANK` chứa `"X"`, `LEVEL` chứa số (50/100/220 — giống CONFIRM2 thật). **Đây là cùng kiểu lệch cột với `WAITING`, không phải "unshifted"** như comment trong code khẳng định. Giả định "unshifted" trong script hiện tại **có khả năng SAI** và cần được xác minh cùng lúc với `tbl_ToSend2`/`WAITING` khi có workbook nguồn — không nằm ngoài phạm vi rủi ro P0-D, cần bổ sung vào FIX-004.

Bối cảnh thêm — toàn bộ 9 bảng trong `legacy_df_data` và số dòng thật:
```
TBL_INPUT_ALL          0
WAITING                57
tblSync                0
tbl_ARCHIVE             0
tbl_OUTPUT_PROCESSING   0
tbl_SentLog             0
tbl_ToSend              0
tbl_ToSend2            696
tbl_Waiting             71
```
(Khớp với `sql_migration/README.md` — không có sai lệch giữa README và DB thật.)

---

## 5. Đối chiếu code Laravel/PHP — có đọc trực tiếp bảng staging không?

```
grep -rniE "tbl_ToSend2|WAITING|tblSync|legacy_df_data" F:\DF\backend --include="*.php"
```
Kết quả ban đầu khớp nhiều file (`DashboardController.php`, `MachineDispatchController.php`, `ReportController.php`, và một số file trong `vendor/`) — nhưng khi grep hẹp lại theo tên bảng/chuỗi chính xác (`"WAITING"`, `tbl_ToSend2`, `tblSync`, `legacy_df_data.`), chỉ còn **1 kết quả duy nhất**:

```
F:\DF\backend\app\Http\Controllers\MachineDispatchController.php:21
->whereIn('queue_state', ['INPUT', 'WAITING', 'TO_SEND', 'PROCESSING', 'ERROR']);
```

Đây là giá trị **enum trạng thái hàng đợi** (`queue_state`) của bảng `app.machine_dispatches` (bảng target đã chuẩn hoá — khớp với `queue_state='WAITING'` được gán trong transform khối 6, và `'TO_SEND'`/`'WAITING_LEGACY'` ở khối 7-8), **không phải** tham chiếu tới bảng staging `legacy_df_data."WAITING"`. 

**Kết luận:** không có code Laravel/PHP nào trong `F:\DF\backend` đọc trực tiếp `legacy_df_data.tbl_ToSend2`, `legacy_df_data.WAITING`, hay `legacy_df_data.tblSync`. Đúng như kỳ vọng thiết kế — staging chỉ được dùng 1 lần bởi `sql_migration/03_transform_legacy_to_target.sql`, ứng dụng web chỉ đọc `app.*`.

---

## 6. Trích nguyên văn mapping trong `03_transform_legacy_to_target.sql`

### Khối 6 — `tbl_Waiting` ("unshifted", dòng 140-160)
```sql
-- 6. Insert machine dispatches (unshifted tbl_Waiting)
INSERT INTO app.machine_dispatches(legacy_row_no, legacy_id, batch_id, confirm_1, confirm_2, sending_value, sent_value, confirmed_at_1, confirmed_at_2, sent_at, is_sent, queue_state, source_table)
SELECT 
  row_number() OVER () as legacy_row_no, 
  cast(d."ID" as bigint) as legacy_id, 
  b.id as batch_id,
  trim(d."CONFIRM1"::text) as confirm_1,
  trim(d."CONFIRM2"::text) as confirm_2,
  trim(d."SENDING"::text) as sending_value,
  trim(d."SENT"::text) as sent_value,
  CASE WHEN nullif(trim(d."TIME1"), '') IS NOT NULL AND d."TIME1" != '0' AND d."TIME1" != '\N' THEN to_timestamp(d."TIME1", 'MM/DD/YYYY HH24:MI:SS') ELSE NULL END as confirmed_at_1,
  CASE WHEN nullif(trim(d."TIME2"), '') IS NOT NULL AND d."TIME2" != '0' AND d."TIME2" != '\N' THEN to_timestamp(d."TIME2", 'MM/DD/YYYY HH24:MI:SS') ELSE NULL END as confirmed_at_2,
  CASE WHEN nullif(trim(d."TIME3"), '') IS NOT NULL AND d."TIME3" != '0' AND d."TIME3" != '\N' THEN to_timestamp(d."TIME3", 'MM/DD/YYYY HH24:MI:SS') ELSE NULL END as sent_at,
  CASE WHEN d."ISSENT" = 'true' THEN true ELSE false END as is_sent,
  'WAITING' as queue_state,
  'tbl_Waiting' as source_table
FROM legacy_df_data."tbl_Waiting" d
LEFT JOIN app.machines m ON m.code = app.normalize_machine_code(d."MACHINE"::text)
LEFT JOIN app.production_batches b ON b.product_code = trim(d."CODE"::text) AND b.machine_id = m.id
WHERE app.normalize_machine_code(d."MACHINE"::text) IS NOT NULL
ON CONFLICT(source_table, legacy_row_no) DO NOTHING;
```
→ Như mục 4 đã chỉ ra, cast trực tiếp `d."MACHINE"` qua `normalize_machine_code` **thực chất đang lấy đúng cột chứa mã VDxx thật** (may mắn trùng khớp vì cột MACHINE của tbl_Waiting chứa giá trị TANK-thật), nhưng comment "unshifted" trong code là **gây hiểu lầm** — bảng vẫn lệch, chỉ là do tình cờ cột `MACHINE` ở vị trí lệch lại đúng là cột chứa mã máy cần dùng. Các cột còn lại (`confirm_1` lấy từ `CONFIRM1` thật ra đang chứa `"OK"`, `confirm_2` lấy từ `CONFIRM2` thật ra rỗng) vẫn map sai theo dữ liệu quan sát được.

### Khối 7 — `tbl_ToSend2` ("shifted", dòng 162-182)
```sql
-- 7. Insert machine dispatches (shifted tbl_ToSend2)
INSERT INTO app.machine_dispatches(legacy_row_no, legacy_id, batch_id, confirm_1, confirm_2, sending_value, sent_value, confirmed_at_1, confirmed_at_2, sent_at, is_sent, queue_state, source_table)
SELECT 
  row_number() OVER () as legacy_row_no, 
  cast(d."ID" as bigint) as legacy_id, 
  b.id as batch_id,
  trim(d."MACHINE"::text) as confirm_1, -- MACHINE contains CONFIRM1
  trim(d."SENDING"::text) as confirm_2, -- SENDING contains CONFIRM2
  trim(d."SENT"::text) as sending_value, -- SENT contains SENDING
  trim(d."TIME1"::text) as sent_value, -- TIME1 contains SENT
  CASE WHEN nullif(trim(d."TIME2"), '') IS NOT NULL AND d."TIME2" != '0' AND d."TIME2" != '\N' THEN to_timestamp(d."TIME2", 'MM/DD/YYYY HH24:MI:SS') ELSE NULL END as confirmed_at_1, -- TIME2 contains TIME1
  CASE WHEN nullif(trim(d."TIME3"), '') IS NOT NULL AND d."TIME3" != '0' AND d."TIME3" != '\N' THEN to_timestamp(d."TIME3", 'MM/DD/YYYY HH24:MI:SS') ELSE NULL END as confirmed_at_2, -- TIME3 contains TIME2
  NULL::timestamp as sent_at,
  CASE WHEN d."ISSENT" = 'true' THEN true ELSE false END as is_sent,
  'TO_SEND' as queue_state,
  'tbl_ToSend2' as source_table
FROM legacy_df_data."tbl_ToSend2" d
LEFT JOIN app.machines m ON m.code = app.normalize_machine_code(d."TANK"::text) -- TANK contains Machine
LEFT JOIN app.production_batches b ON b.product_code = trim(d."CONFIRM1"::text) AND b.machine_id = m.id -- CONFIRM1 contains Product Code
WHERE app.normalize_machine_code(d."TANK"::text) IS NOT NULL
ON CONFLICT(source_table, legacy_row_no) DO NOTHING;
```
Ghi chú trong code (`-- MACHINE contains CONFIRM1`, `-- TANK contains Machine`...) khớp với quan sát dữ liệu thật ở mục 1.3/1.6 — **tự nhất quán với các mẫu quan sát được**, nhưng đây vẫn là **suy luận từ việc đọc dữ liệu (reverse-engineering), chưa có VBA nguồn xác nhận** logic ghi dữ liệu gốc thực sự lệch đúng theo trật tự này (ví dụ không loại trừ khả năng có 1 cột bị bỏ qua ở giữa thay vì lệch đều 2 cột, hoặc `CODE`/`COLOR` cũng bị ảnh hưởng mà mẫu 10-696 dòng không đủ để phát hiện nếu tỉ lệ lỗi thấp).

### Khối 8 — `WAITING` ("shifted", dòng 184-204)
```sql
-- 8. Insert machine dispatches (shifted WAITING)
INSERT INTO app.machine_dispatches(legacy_row_no, legacy_id, batch_id, confirm_1, confirm_2, sending_value, sent_value, confirmed_at_1, confirmed_at_2, sent_at, is_sent, queue_state, source_table)
SELECT 
  row_number() OVER () as legacy_row_no, 
  cast(nullif(trim(d."ID"), '') as bigint) as legacy_id, 
  b.id as batch_id,
  trim(d."CODE"::text) as confirm_1, -- CODE contains CONFIRM1
  trim(d."LEVEL"::text) as confirm_2, -- LEVEL contains CONFIRM2
  trim(d."CONFIRM2"::text) as sending_value, -- CONFIRM2 contains SENDING
  trim(d."SENDING"::text) as sent_value, -- SENDING contains SENT
  CASE WHEN nullif(trim(d."SENT"), '') IS NOT NULL AND d."SENT" != '0' AND d."SENT" != '\N' THEN to_timestamp(d."SENT", 'MM/DD/YYYY HH24:MI:SS') ELSE NULL END as confirmed_at_1, -- SENT contains TIME1
  CASE WHEN nullif(trim(d."TIME1"), '') IS NOT NULL AND d."TIME1" != '0' AND d."TIME1" != '\N' THEN to_timestamp(d."TIME1", 'MM/DD/YYYY HH24:MI:SS') ELSE NULL END as confirmed_at_2, -- TIME1 contains TIME2
  CASE WHEN nullif(trim(d."TIME2"), '') IS NOT NULL AND d."TIME2" != '0' AND d."TIME2" != '\N' THEN to_timestamp(d."TIME2", 'MM/DD/YYYY HH24:MI:SS') ELSE NULL END as sent_at, -- TIME2 contains TIME3
  CASE WHEN trim(d."TIME3") ILIKE 'true' OR trim(d."TIME3") = '1' THEN true ELSE false END as is_sent, -- TIME3 contains ISSENT
  'WAITING_LEGACY' as queue_state,
  'WAITING' as source_table
FROM legacy_df_data."WAITING" d
LEFT JOIN app.machines m ON m.code = app.normalize_machine_code(d."CONFIRM1"::text) -- CONFIRM1 contains Machine
LEFT JOIN app.production_batches b ON b.product_code = trim(d."COLOR"::text) AND b.machine_id = m.id -- COLOR contains Product Code
WHERE app.normalize_machine_code(d."CONFIRM1"::text) IS NOT NULL
ON CONFLICT(source_table, legacy_row_no) DO NOTHING;
```
Đối chiếu với mục 2.3: comment `CONFIRM1 contains Machine` và `COLOR contains Product Code` **khớp với dữ liệu thật quan sát được** (CONFIRM1=VDxx, COLOR=mã lô dạng CODE thật). Nhưng vì cả bảng `WAITING` không có `TIME1/TIME2/TIME3/ID` nào khác rỗng (mục 2.5), các dòng `CASE WHEN ... to_timestamp(d."SENT"...)`, `to_timestamp(d."TIME1"...)`, `to_timestamp(d."TIME2"...)` trong khối này **sẽ luôn trả về NULL** trên toàn bộ 57 dòng hiện có (vì nguồn đều rỗng) — về mặt kỹ thuật không sai, nhưng cần lưu ý khi viết acceptance test đừng kỳ vọng có timestamp non-null sau transform khối 8.

### `tblSync`
```
grep -rn "tblSync" F:\DF\sql_migration
→ chỉ khớp trong README.md (liệt kê "hiện rỗng") và trong chính kết quả grep này — KHÔNG xuất hiện trong bất kỳ file .sql nào (01_*, 02_*, 03_*, 04_*).
```
**Xác nhận: chưa có bất kỳ xử lý nào cho `tblSync` trong toàn bộ `sql_migration/`.** Không có INSERT, không có transform, không có validation query nào tham chiếu tới bảng này.

---

## 7. Code legacy nghi vấn là nguồn (liệt kê lại, không điều tra thêm)

Theo `source-files-missing.md` mục P0 #1-3 (đã xác định trước, không có mặt tại `F:\DF`):
1. `C3 grid load row lock id FB -(1).xlsm`
2. `Copy of MACHINE_ID_LOCKED.xlsm`
3. `MACHINE_ID_LOCKED(1).xlsm`

Ghi chú: `F:\DF` hiện có `C3 grid load row lock id FB -.xlsm` (không có hậu tố `(1)`) và `MACHINE_ID_LOCKED.xlsm` (không có "Copy of" và không có hậu tố `(1)`) — đây có thể là phiên bản khác/cũ hơn của cùng workbook, KHÔNG chắc chắn chứa cùng logic VBA ghi `tbl_ToSend2`/`WAITING`/`tbl_Waiting`/`tblSync`. Không mở/phân tích các file `.xlsm` này trong phiên làm việc này (ngoài phạm vi nhiệm vụ — chỉ liệt kê lại).

---

## 8. Kế hoạch FIX-004 (CHỈ LẬP KẾ HOẠCH — KHÔNG THỰC HIỆN)

**FIX-004: Xác minh/hoàn thiện mapping cột cho `tbl_ToSend2` / `WAITING` / `tbl_Waiting` (bổ sung phát hiện mới) / `tblSync`**

- **Phạm vi:** Đây là công việc **CẦN workbook nguồn bổ sung trước khi thực hiện**. Không được tự sửa mapping cột dựa trên suy luận từ dữ liệu quan sát (dù suy luận hiện tại trong code có vẻ tự nhất quán, đây vẫn là reverse-engineering, chưa có VBA xác nhận trật tự ghi cột thật). Phạm vi mở rộng thêm bảng `tbl_Waiting` — theo phát hiện ở mục 4, comment "unshifted" cho bảng này trong code hiện tại nhiều khả năng cũng sai, cần xác minh cùng đợt.

- **File dự kiến sửa (SAU KHI có workbook nguồn):**
  - `F:\DF\sql_migration\03_transform_legacy_to_target.sql` (khối 6, 7, 8 — dòng 140-204)
  - `F:\DF\.claude\migration-strategy.md` (cập nhật mô tả mapping đã xác minh)
  - Có thể `F:\DF\.claude\source-traceability.md` nếu tìm thấy VBA nguồn xác thực

- **Database change:** Không. Đây là transform từ staging đã import sẵn (`legacy_df_data.*`), không đổi schema `app.*`, không đổi cấu trúc staging.

- **Migration:** Không áp dụng dạng Laravel migration — đây là SQL transform chạy 1 lần (`BEGIN...COMMIT`), không phải schema migration có version.

- **Acceptance criteria (đề xuất, cần xác nhận nghiệp vụ):**
  1. Sau khi có VBA nguồn xác nhận trật tự cột thật, mapping được coi là đúng khi chạy JOIN `app.machine_dispatches` (sau transform) → `app.production_batches`/`app.machines` đạt tỉ lệ match ≥ 95% — **NHƯNG chỉ sau khi đã chạy khối 1 của `03_transform_legacy_to_target.sql` để populate `app.machines` từ `legacy_df_scale.tbl_status` bằng dữ liệu thật** (hiện DB dev chỉ có 5 machine test/fixture, chưa phản ánh đúng trạng thái sau transform thật — xem mục 1.6/2.6). Nếu đo JOIN match trên `app.machines` hiện tại (5 dòng test), số liệu sẽ luôn ~0% bất kể mapping đúng hay sai, gây kết luận sai lệch.
  2. Với `tblSync`: vì bảng rỗng, acceptance criteria không thể dựa trên dữ liệu — chỉ có thể yêu cầu xác nhận bằng văn bản/workbook rằng tính năng multi-Front-End sync KHÔNG được triển khai thực tế tại xưởng pilot, hoặc tìm được bản export khác có dữ liệu.

- **Regression test:** `sql_migration/04_validation_queries.sql` cần bổ sung:
  - Query đối soát riêng cho khối 6/7/8 của `machine_dispatches`: so sánh `COUNT(*) FILTER (WHERE batch_id IS NULL)` theo từng `source_table` trước/sau khi sửa mapping — phải giảm, không được tăng.
  - Query kiểm tra `MACHINE` distinct = 1 giá trị (`"OK"`) trong `tbl_ToSend2` vẫn đúng sau mỗi lần load dữ liệu mới (cảnh báo sớm nếu format nguồn đổi).
  - Query đếm số dòng `TIME1/TIME2/TIME3` non-null trong `WAITING` — hiện = 0, nếu batch import sau này có dòng non-null, cần re-test lại toàn bộ giả định lệch cột trên phần dữ liệu mới đó (không được giả định format cũ áp dụng cho mọi lần import).

- **Rollback:** Không rủi ro mất dữ liệu gốc — đây là transform 1 chiều đọc từ staging (`legacy_df_data.*`, không sửa) ghi ra `app.*`. Rollback = xoá dữ liệu vừa insert trong `app.machine_dispatches` theo `source_table IN ('tbl_Waiting','tbl_ToSend2','WAITING')` rồi chạy lại transform với mapping mới. Dữ liệu staging gốc không bị đụng tới ở bất kỳ bước nào.

- **Dependency:** PHẢI có workbook nguồn (xem `source-files-missing.md` mục P0 #1-3) xác nhận logic ghi cột thật trước khi sửa mapping. Không có workbook → không sửa.

- **Rủi ro:** Nếu triển khai Local Agent đa máy trạm (Phase 12) trước khi xác minh `tbl_Waiting`/`WAITING`/`tblSync`, có nguy cơ:
  - Dữ liệu điều phối (machine_dispatches) bị gán sai `confirm_1`/`confirm_2`/mã máy do mapping suy luận sai, dẫn tới báo cáo/điều phối sai mà không phát hiện được (vì transform chạy êm, không lỗi).
  - Nếu tính năng `tblSync` (theo dõi Front-End sống/chết) thực sự cần cho điều phối đa máy trạm nhưng bị bỏ qua do bảng rỗng trong staging, có thể mất cơ chế phát hiện xung đột đa người dùng khi vận hành song song thật.

- **Estimate:**
  - **Kịch bản A — CÓ workbook nguồn xác nhận:** ước tính **S** (0.5-1 ngày) — chỉ cần đối chiếu VBA, sửa 3 khối SQL, chạy lại transform trên DB test, kiểm tra JOIN match rate.
  - **Kịch bản B — KHÔNG có workbook nguồn (phải tiếp tục dựa vào suy luận từ dữ liệu):** ước tính **M-L** (2-4 ngày) — cần thêm bước: xin thêm mẫu dữ liệu (nếu có bản export mới hơn), phỏng vấn người vận hành xưởng để xác nhận thủ công từng cột, viết test case đối chiếu chéo giữa 3+1 bảng (tbl_ToSend2/WAITING/tbl_Waiting/tblSync) để tìm điểm bất nhất, và **chấp nhận rủi ro tồn đọng** (không đạt được mức tin cậy như kịch bản A). Ước tính không thể chính xác hơn nếu chưa biết liệu Kịch bản B có khả thi (có thể không bao giờ đạt độ tin cậy đủ để lên production).

---

## 9. Tóm tắt số liệu chính

| Bảng | Số dòng | Khoảng thời gian | JOIN-match (app.machines, DB dev hiện tại) | Duplicate ID | Ghi chú |
|---|---|---|---|---|---|
| `tbl_ToSend2` | 696 | TIME2: 17/11/2025–28/11/2025; TIME3: 20/11/2025–27/11/2025 | 0/696 (0%) — do app.machines dev chỉ có 5 mã test, không phải do mapping chắc chắn sai | ID=0 trùng 3 lần; 694/696 ID phân biệt | MACHINE luôn="OK" (100%) — dấu hiệu lệch cột rõ |
| `WAITING` | 57 | Không xác định (TIME1/2/3 100% rỗng) | 0/55 (0%) — cùng lý do | Không kiểm được (ID 100% rỗng) | Có lẽ là snapshot trạng thái tức thời, không phải log |
| `tblSync` | **0** | Không áp dụng | Không áp dụng | Không áp dụng | Rỗng từ lúc import Access, đã ghi nhận sẵn trong README |
| `tbl_Waiting` (phát hiện phụ, ngoài phạm vi ban đầu) | 71 | TIME1 rải rác 20-21/11/2025 (mẫu 5 dòng đầu) | Chưa test | Không kiểm (ID rỗng) | Code transform coi "unshifted" nhưng dữ liệu cho thấy CŨNG lệch cột |
