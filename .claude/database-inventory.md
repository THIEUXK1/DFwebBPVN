# Kiểm kê 5 Database Access (database-inventory.md)

Lập 2026-07-17 theo yêu cầu đợt duyệt lần 4. Kiểm kê **read-only** (bản sao trong scratchpad, không đụng file gốc) toàn bộ bảng/cột/khóa/index/số dòng của 5 file `.accdb` hiện có tại `F:\DF`. Phương pháp: DAO qua `Access.Application` COM automation (`win32com`), đối chiếu chéo với dữ liệu mẫu (`OpenRecordset TOP N`).

> [!IMPORTANT]
> Đây là 5 file vật lý khác nhau, KHÔNG được coi 2 file `RECORD.accdb`/`RECORD1.accdb` là bản sao của nhau chỉ vì tên gần giống — xem bằng chứng chi tiết ở [legacy-database-mapping.md](file:///F:/DF/.claude/legacy-database-mapping.md).

---

## 1. `chem_order.accdb` (định danh tạm: **CHEM_ORDER**)

| Bảng | Số cột | PK | Index | Số dòng | Vai trò |
|---|---|---|---|---|---|
| `tbl_status` | 5 (`ID`,`machine`,`chem`,`chem_name`,`status`) | `ID` (Long) | PK only | **40** | Cấu hình kênh/van hóa chất — đọc/ghi bởi `chem_order.frm` (workbook 1, CHEMICAL_CALL). `status` là Text ("0"/"1" quan sát được trong mẫu), KHÔNG phải Boolean — lưu ý khi migrate. |
| `tblRECORD` | 12 (`ID`,`batchID`,`COLOR`,`CODE`,`MACHINE`,`LEVEL`,`RACK`,`DYECODE`,`WEIGHT`,`PROCESS`,`TIME`,`processCOLOR`) | `ID` (Long) | `batchID`,`CODE`,`DYECODE`,PK | 47.381 | **Cùng schema hệt** `RECORD1.accdb.tblRECORD` (xem Mục 2) nhưng dữ liệu mới nhất chỉ tới **2026-03-31**, trong khi `RECORD1.accdb.tblRECORD` mới nhất tới 2026-07-15. Không có Sub/Function nào trong `chem_order.frm` (đã audit đầy đủ ở NHÓM 0) đọc/ghi bảng này → nhiều khả năng là **bản sao/backup tĩnh cũ**, không phải bảng đang được `chem_order.frm` sử dụng trực tiếp. Chưa xác nhận cơ chế đồng bộ. |
| `tblRECORD_chem` | 12 (giống `tblRECORD` trừ không có `WH_DONE`/`WH_TIME`) | `ID` (Long) | như trên | 1.500 | Cùng nhận định như trên — dữ liệu rỗng ở các trường nghiệp vụ (RACK/DYECODE/WEIGHT/PROCESS), khớp phát hiện A-02 cũ (không cân tay hóa chất). |

## 2. `RECORD.accdb` (định danh tạm: **RECORD_A**)

| Bảng | Số cột | PK | Index | Số dòng | Vai trò |
|---|---|---|---|---|---|
| `tbl_SentLog` | 17 | *(không PK)* | `ID` (non-unique) | **27.024** | **Sổ cái gửi hàng thật** — dữ liệu mới nhất tới 2026-07-15 09:25 (hệ thống VBA vẫn đang chạy sản xuất thật tính đến 2 ngày trước "hôm nay"). Có `rawqrdye`/`rawqrchem` (Memo) + `scale_check` (Boolean) — khớp gần như tuyệt đối với `app.machine_dispatches.raw_qr_dye/raw_qr_chemical/scale_checked` đã thiết kế sẵn trong `target-data-model.md`. |
| `tbl_ToSend` | 17 (cùng field set với `tbl_SentLog`, thêm kiểu khác ở `SENDING`) | *(không PK)* | `ID` | 4 | Hàng đợi "sẵn sàng gửi" — gần rỗng tại thời điểm snapshot (đúng bản chất hàng đợi động). |
| `tbl_ToSend2` | 14 (**thiếu** `rawqrdye`/`rawqrchem`/`scale_check` so với `tbl_ToSend`) | *(không PK)* | `ID` | 696 | Dữ liệu dừng hẳn từ 2025-11-20 (`TIME1..3` toàn kiểu Text, định dạng US `MM/DD/YYYY`) — bảng cũ hơn, KHÔNG cùng thế hệ schema với `tbl_ToSend`. |
| `WAITING` | 15 | **`ID1`** (Long, unique) | `CODE`, `ID`, `ID1`(PK) | 57 | `TIME1` là DateTime nhưng `TIME2`/`TIME3`/`ISSENT` là Text — kiểu dữ liệu không đồng nhất trong cùng bảng, dấu hiệu bảng bị sửa cấu trúc nhiều lần qua các đời workbook khác nhau. |
| `tbl_Waiting` | 14 (không có `ID1`) | *(không PK)* | `ID` | 71 | `TIME1..3` toàn Text định dạng US, dữ liệu dừng cùng đợt với `tbl_ToSend2` (11/2025). |
| `TBL_INPUT_ALL` | 17 (cùng field set + `rawqrdye`/`rawqrchem`/`scale_check` như `tbl_SentLog`) | *(không PK)* | *(không index)* | **1** | Hàng đợi "vừa nhập" — gần rỗng tại thời điểm snapshot, đúng bản chất. |
| `tbl_ARCHIVE` | 14 (cùng schema `tbl_ToSend2`) | *(không PK)* | `ID` | 0 | Rỗng — chưa có bằng chứng đang dùng. |
| `tbl_OUTPUT_PROCESSING` | 14 (cùng schema `tbl_ToSend2`) | *(không PK)* | `ID` | 0 | Rỗng — chưa có bằng chứng đang dùng. |
| `tblSync` | 6 (`NextFE`,`FE1..5_Alive`) | *(không PK)* | *(không index)* | **0** | Xác nhận lại (lần 3): rỗng hoàn toàn. |

## 3. `RECORD1.accdb` (định danh tạm: **RECORD_B**)

| Bảng | Số cột | PK | Index | Số dòng | Vai trò |
|---|---|---|---|---|---|
| `tblRECORD` | 14 (`ID`,`batchID`,`COLOR`,`CODE`,`MACHINE`,`LEVEL`,`RACK`,`DYECODE`,`WEIGHT`,`PROCESS`,`TIME`,`processCOLOR`,`WH_DONE`,`WH_TIME`) | **`ID`** (Long, PK) | `batchID`,`CODE`,`DYECODE`,PK | **140.655** | Dữ liệu cân thật — mới nhất tới **2026-07-15 09:09** (cùng ngày với `tbl_SentLog` mới nhất — 2 bảng "sống" song song thật). Nguồn ghi: workbook 4/5 (SMALL_SCALE/LARGE_SCALE). |
| `tblRECORD_chem` | 12 (không có `WH_DONE`/`WH_TIME`) | **`ID`** (Long, PK) | như trên | 5.061 | — |

## 4. `WH.accdb` (định danh tạm: **WAREHOUSE**)

| Bảng | Số cột | PK | Index | Số dòng | Vai trò |
|---|---|---|---|---|---|
| `tblWH_LOG` | 5 (`ID`,`DYECODE`,`WEIGHT`,`PROCESS`,`RECORDTIME`) | `ID` (Long) | `DYECODE`, PK | 35 | Log tiêu thụ kho — dữ liệu mới nhất **2026-07-15 08:35**, đang hoạt động. **KHÔNG có bảng mapping vùng kho/zone/B24** trong toàn bộ file này (chỉ 1 bảng duy nhất) — logic B24 KHÔNG data-driven từ Access, hoàn toàn hard-code trong VBA `Mod_printslip.PrintSlip_70x100` (xem `b24-warehouse-routing.md`). |

## 5. `DF_STORAGE.accdb` (đã audit trước đây, không đổi)

| Bảng | Số cột | PK | Index | Số dòng |
|---|---|---|---|---|
| `DF_STORAGE` | 4 (`ID`,`DYECODE`,`WEIGHT`,`PROCESS`... `TIME`) | `ID` | `DYECODE`, PK | 89 |

---

## Ghi chú phương pháp

- Toàn bộ số liệu trên đọc trực tiếp từ **bản sao read-only** trong scratchpad (`accdb_copies/`), không đụng file gốc tại `F:\DF`.
- Không dùng regex/suy đoán tên cột — dùng `DAO.TableDef.Fields`/`.Indexes` qua COM, đối chiếu bằng `OpenRecordset TOP N` để lấy dữ liệu mẫu thật (không phải dữ liệu giả định).
- File JSON schema đầy đủ (mọi cột/kiểu/size/required) lưu tại scratchpad: `accdb_copies/_schema_all.json`, mẫu dữ liệu tại `accdb_copies/_samples.json` — có thể tái sử dụng nếu cần đối chiếu sâu hơn về sau.
