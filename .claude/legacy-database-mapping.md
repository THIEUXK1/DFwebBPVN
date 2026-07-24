# Ánh xạ Database Legacy — RECORD_A / RECORD_B / CHEM_ORDER / WAREHOUSE (legacy-database-mapping.md)

Lập 2026-07-17. Trả lời trực tiếp yêu cầu "không được coi 2 file RECORD là cùng một database chỉ vì trùng tên". Bằng chứng dưới đây kết hợp: (1) schema/PK/index (xem `database-inventory.md`), (2) dữ liệu mẫu thật, (3) **đường dẫn hard-code trong chính VBA của 5 workbook đã xác nhận** (grep trực tiếp source, không suy đoán).

---

## Kết luận: 2 file `RECORD*.accdb` là 2 DATABASE HOÀN TOÀN ĐỘC LẬP, không đồng bộ, không chia sẻ bảng nào

| Định danh tạm | File vật lý hiện có | Network path hard-code trong VBA (grep xác nhận) | Bảng bên trong | Vai trò |
|---|---|---|---|---|
| **RECORD_A** | `RECORD.accdb` (29 MB) | `Z:\DF\DATA\record.accdb` (workbook 2 — C3, dòng 496/1050) và `z:\df\data\RECORD.accdb` / `Z:\DF\DATA\RECORD.accdb` (workbook 3 — DF028, dòng 912/930/988) | `TBL_INPUT_ALL`, `tbl_ToSend`, `tbl_ToSend2`, `tbl_Waiting`, `WAITING`, `tbl_SentLog`, `tblSync`, `tbl_ARCHIVE`, `tbl_OUTPUT_PROCESSING` | **Database Điều phối/Hàng chờ/Sổ gửi hàng** — dùng chung bởi PRODUCTION_ORDER (ghi `tbl_input_all`) và QR_LABEL_PRINTING (đọc `tbl_input_all`/`tbl_tosend`, ghi `tbl_sentlog`) |
| **RECORD_B** | `RECORD1.accdb` (24.5 MB) | `Z:DF_SCALE\RECORD.accdb` (thiếu `\` sau `Z:` — workbook 4 dòng 445, workbook 5 dòng 446; cùng lỗi đã ghi nhận ở workbook A/SEMI CHECKER trong đợt audit trước) | `tblRECORD` (140.655 dòng, có `WH_DONE`/`WH_TIME`), `tblRECORD_chem` (5.061 dòng) | **Database Cân** — dùng bởi SMALL_SCALE và LARGE_SCALE, mỗi lần cân xong INSERT 1 dòng |

**Bằng chứng loại trừ khả năng "2 file chỉ là bản sao của nhau":**
1. Schema khác hẳn — RECORD_A không có bảng `tblRECORD` nào cả; RECORD_B không có bảng `tbl_SentLog`/`tbl_ToSend*`/`WAITING`/`tblSync` nào cả. 0 bảng trùng tên giữa 2 file.
2. Đường dẫn network khác thư mục — `Z:\DF\DATA\` (RECORD_A) vs `Z:\DF_SCALE\` (RECORD_B).
3. Workbook nào đọc/ghi file nào tách biệt hoàn toàn — C3/DF028 chỉ chạm RECORD_A; workbook 4/5 chỉ chạm RECORD_B — không có Sub/Function nào trong 5 workbook xác nhận mở cả 2 file cùng lúc.
4. Dữ liệu thật của cả 2 vẫn đang cập nhật gần nhau về thời gian (RECORD_A `tbl_SentLog` mới nhất 2026-07-15 09:25; RECORD_B `tblRECORD` mới nhất 2026-07-15 09:09) — **2 database đang chạy song song độc lập trong cùng hệ sản xuất thật**, không phải 1 cái là bản backup của cái kia.

→ **Không có cơ chế đồng bộ trực tiếp nào giữa RECORD_A và RECORD_B được tìm thấy trong VBA của 5 workbook đã xác nhận.** Liên kết logic duy nhất là gián tiếp qua nghiệp vụ: `MACHINE`+`COLOR`+`CODE` xuất hiện ở cả 2 phía (điều phối gửi lệnh ở RECORD_A, kết quả cân ở RECORD_B) nhưng không có khóa ngoại/ID chung nào liên kết trực tiếp 2 bảng — đây là **BLOCKED_BY_BUSINESS_CONFIRMATION**: cần xác nhận nghiệp vụ có quy trình đối chiếu thủ công nào giữa 2 hệ này không, trước khi thiết kế khóa ngoại giả định trong schema đích.

---

## `CHEM_ORDER` (`chem_order.accdb`)

- Path VBA: `Z:\chem_order\chem_order.accdb` (workbook 1, dòng 166) — thư mục riêng, không liên quan RECORD_A/B.
- Bảng `tbl_status` (40 dòng, cấu hình kênh van) — **CÓ** được `chem_order.frm` đọc/ghi (đã xác nhận toàn bộ ở NHÓM 0).
- Bảng `tblRECORD`/`tblRECORD_chem` (47.381/1.500 dòng, cùng schema RECORD_B nhưng dữ liệu dừng ở 2026-03-31) — **KHÔNG** có Sub/Function nào trong `chem_order.frm` chạm tới 2 bảng này (đã audit đầy đủ 44 procedure).
- **Phân loại cuối cùng:** **`BLOCKED_BY_BUSINESS_CONFIRMATION`** (Trạng thái cô lập hoàn toàn khỏi luồng tích hợp chung, blocker **`CH-BUS-015`** - cần xác định máy/chương trình thực tế đọc và xử lý `chem_order.accdb.tbl_status`). Giả thuyết hợp lý nhất: đây là bản sao/backup tĩnh của `tblRECORD` (RECORD_B) được nhúng vào `chem_order.accdb` tại một thời điểm bảo trì (khoảng cuối tháng 3/2026) rồi không cập nhật tiếp, không được coi 2 bảng này là "đang dùng" hoặc đưa vào target model cho tới khi có xác nhận chính thức từ IT/Nghiệp vụ.

## `WAREHOUSE` (`WH.accdb`)

- Path VBA: `Z:\DF_SCALE\WH.accdb` — **cùng thư mục mạng với RECORD_B** (`Z:\DF_SCALE\`), được ghi bởi workbook 4/5 (SMALL_SCALE/LARGE_SCALE), dòng 920/929 — KHÔNG liên quan tới DF028/QR_LABEL_PRINTING hay logic B24 (đã kiểm tra: DF028 không có bất kỳ tham chiếu nào tới `WH.accdb`).
- Bảng `tblWH_LOG` (35 dòng, `DYECODE`/`WEIGHT`/`PROCESS`/`RECORDTIME`) — ghi log tiêu thụ sau khi cân xong, khớp với cột `WH_DONE`/`WH_TIME` trong `tblRECORD` (RECORD_B) — đây là cầu nối SCALE→WAREHOUSE, KHÔNG phải bảng mapping vùng kho B24 như giả định ban đầu. **Sửa nhận định:** B24 hoàn toàn không data-driven từ Access — xem `b24-warehouse-routing.md`.

---

## Bảng mapping Workstation → Workbook → Database → Bảng (theo yêu cầu duyệt lần 4)

| Workstation | Workbook nguồn | Database (định danh tạm) | File vật lý | Bảng đọc | Bảng ghi |
|---|---|---|---|---|---|
| CHEMICAL_CALL | `1.báo phát AC XƯỞNG -193.xlsm` | CHEM_ORDER | `chem_order.accdb` | `tbl_status` | `tbl_status` (UPDATE Status) |
| PRODUCTION_ORDER | `2.C3 grid load row lock id FB -192(QR).xlsm` | RECORD_A | `RECORD.accdb` | `tbl_input_all` (đọc lưới) | `tbl_input_all` (ghi dòng mới qua `MoveToSend`/nhập liệu — xem NHÓM 2 DISPATCH) |
| QR_LABEL_PRINTING | `3.DF028 ... jit qr sending - 15l special.xlsm` | RECORD_A | `RECORD.accdb` | `tbl_tosend`, `tbl_input_all`, `tbl_sentlog` (48h) | `tbl_sentlog` (INSERT — `ConfirmRow`), `tbl_tosend` (UPDATE `scale_check`, DELETE sau Confirm), `tbl_input_all` (UPDATE `scale_check` — `MarkScaleCheckYes_ByID`) |
| SMALL_SCALE (×2) | `4.semiauto-small scale ... DF026-027.xlsm` | RECORD_B + WAREHOUSE | `RECORD1.accdb` + `WH.accdb` | `tblRECORD` | `tblRECORD` (INSERT kết quả cân), `tblWH_LOG` (ghi log tiêu thụ) |
| LARGE_SCALE (×1) | `5.Semiauto- lockmove SEND OVER6 ... -221.xlsm` | RECORD_B + WAREHOUSE | `RECORD1.accdb` + `WH.accdb` | `tblRECORD` | `tblRECORD` (INSERT kết quả cân), `tblWH_LOG` |

**Ghi chú quan trọng:** RECORD_A được **dùng chung** bởi 2 workstation (PRODUCTION_ORDER ghi `tbl_input_all`, QR_LABEL_PRINTING đọc lại và đẩy tiếp qua `tbl_tosend`→`tbl_sentlog`) — đây chính là 3 bước đầu của chuỗi nghiệp vụ 7 bước (tạo đơn → nhận đơn/in tem → …). RECORD_B+WAREHOUSE dùng chung bởi 2 workstation cân (SMALL_SCALE/LARGE_SCALE), độc lập hoàn toàn với RECORD_A về mặt kỹ thuật.

---

## Đề xuất định danh nghiệp vụ chính thức (chờ xác nhận, CHƯA đổi tên trong code/schema)

| Định danh tạm | Đề xuất tên nghiệp vụ | Lý do |
|---|---|---|
| RECORD_A | `DISPATCH_QUEUE_DB` hoặc `PRODUCTION_DISPATCH_DB` | Chứa toàn bộ vòng đời hàng chờ điều phối + sổ gửi hàng |
| RECORD_B | `SCALE_WEIGHING_DB` | Chứa toàn bộ lịch sử cân thật |
| CHEM_ORDER | Giữ nguyên `CHEM_ORDER_DB` | Đã là tên rõ ràng, không gây nhầm lẫn |
| WAREHOUSE | `SCALE_WAREHOUSE_LOG_DB` (không phải `B24_ROUTING_DB` — đã xác nhận không liên quan B24) | Tránh nhầm với logic phân vùng kho B24 (thuần VBA, không nằm trong DB này) |

**Chỉ đổi tên chính thức trong tài liệu/schema sau khi người dùng xác nhận** — theo đúng yêu cầu.
