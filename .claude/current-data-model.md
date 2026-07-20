# Current Data Model - Mô hình Dữ liệu Legacy (Access)

Tài liệu này đặc tả chi tiết mô hình dữ liệu vật lý hiện tại của hệ thống legacy dựa trên kết quả trích xuất tệp `access_inventory.json` và `01_legacy_access_import_postgresql.sql`.

---

## 1. Cơ sở Dữ liệu 1: RECORD.accdb (Điều phối máy)
Nằm tại thư mục chia sẻ ổ mạng `Z:\DF\DATA\`. Có dung lượng khoảng 28MB.
Trong cơ sở dữ liệu PostgreSQL di trú, DB này được import vào schema **`legacy_df_data`**.

### 1.1. Bảng `tbl_SentLog` (Nhật ký gửi máy lịch sử)
- **Trạng thái trích xuất:** Lỗi trang dữ liệu (Overflow page). Tạo được cấu trúc bảng trong SQL staging nhưng không nhập được dữ liệu tự động. Cần Compact & Repair để cứu dữ liệu.
- **Số cột:** 17 cột.
- **Kiểu dữ liệu:** Hầu hết là TEXT và TIMESTAMP.

### 1.2. Bảng `tbl_Waiting` (Hàng chờ điều phối trạm 1)
- **Số lượng bản ghi:** 71 dòng.
- **Số cột:** 14 cột.
- **Cấu trúc cột:** ID (TEXT), COLOR (TEXT), CODE (TEXT), CONFIRM1 (TEXT), MACHINE (TEXT), TANK (TEXT), LEVEL (TEXT), CONFIRM2 (TEXT), SENDING (TEXT), SENT (TEXT), TIME1 (TEXT), TIME2 (TEXT), TIME3 (TEXT), ISSENT (BOOLEAN).
- **Chất lượng dữ liệu:** Bảng này có cấu trúc cột hoàn toàn chuẩn khớp với dữ liệu thực tế (Không bị lệch cột). Nhiều dòng có ID rỗng (`\N`).

### 1.3. Bảng `tbl_ToSend2` (Hàng chờ điều phối trạm 2)
- **Số lượng bản ghi:** 696 dòng.
- **Số cột:** 14 cột.
- **Hiện tượng đặc biệt - Lệch cột nghiêm trọng:** Dữ liệu trong tệp SQL import bị lệch lệch 1 cột so với định nghĩa bảng.
  - `ID`: Chứa ID (Bigint, ví dụ `0`, `1763609526`, hoặc số âm `-882633985`). Có 2 dòng trùng ID.
  - `COLOR`: Bị **BỎ TRỐNG** (rỗng `''`).
  - `CODE`: Thực tế chứa mã màu **Color** (ví dụ `'EP68132'`, `'AP81742'`).
  - `CONFIRM1`: Thực tế chứa mã sản phẩm **Product Code** (ví dụ `'SE5775'`, `'SF5415'`).
  - `MACHINE`: Thực tế chứa giá trị xác nhận confirmation `'OK'`.
  - `TANK`: Thực tế chứa mã máy nhuộm **Machine** (ví dụ `'VD15'`, `'VD03'`).
  - `LEVEL`: Bị rỗng `''`.
  - `CONFIRM2`: Thực tế chứa mức nước **Level** (ví dụ `'450'`, `'100'`).
  - `SENDING`: Thực tế chứa xác nhận confirmation 2 `'OK'`.
  - `SENT`: Thực tế chứa trạng thái gửi `'0'`.
  - `TIME1`: Thực tế chứa trạng thái gửi `'0'`.
  - `TIME2`: Thực tế chứa thời điểm nạp **Time 1** (ví dụ `'11/18/2025 14:04:00'`).
  - `TIME3`: Thực tế chứa thời điểm gửi **Time 2** (ví dụ `'11/20/2025 10:12:00'`).
  - `ISSENT`: Chứa giá trị BOOLEAN `'true'`.

### 1.4. Bảng `WAITING` (Hàng chờ điều phối dự phòng/cũ)
- **Số lượng bản ghi:** 57 dòng.
- **Số cột:** 15 cột.
- **Hiện tượng đặc biệt - Lệch cột nghiêm trọng:**
  - `ID1`: Bị rỗng `''`.
  - `ID`: Rỗng `\N`.
  - `COLOR`: Thực tế chứa mã sản phẩm **Product Code** (ví dụ `'L23892'`, `'L33419'`).
  - `CODE`: Thực tế chứa xác nhận confirmation `'OK'`.
  - `CONFIRM1`: Thực tế chứa mã máy nhuộm **Machine** (ví dụ `'VD02'`, `'VD09'`).
  - `MACHINE`: Thực tế chứa mã **Tank** (ví dụ `'X'`).
  - `TANK`: Thực tế chứa mức nước **Level** (ví dụ `'50'`, `'100'`).
  - Toàn bộ cột còn lại đều rỗng. Dữ liệu Color thực tế hoàn toàn bị mất.

### 1.5. Các bảng khác trong `legacy_df_data`
- `tbl_OUTPUT_PROCESSING` (0 dòng), `tbl_ARCHIVE` (0 dòng), `TBL_INPUT_ALL` (0 dòng), `tbl_ToSend` (0 dòng), `tblSync` (0 dòng).
- Toàn bộ các bảng này đều rỗng theo bản sao nhận được.

---

## 2. Cơ sở Dữ liệu 2: RECORD(1).accdb (Dữ liệu cân)
Nằm tại trạm cân `Z:\DF_SCALE\`. Có dung lượng khoảng 24MB.
Trong cơ sở dữ liệu PostgreSQL di trú, DB này được import vào schema **`legacy_df_scale`**.

### 2.1. Bảng `tblRECORD` (Nhật ký cân thuốc nhuộm)
- **Số lượng bản ghi:** 140,660 dòng.
- **Số cột:** 14 cột.
- **Cấu trúc cột:** ID (BIGINT, khóa chính), batchID (TEXT), COLOR (TEXT), CODE (TEXT), MACHINE (TEXT), LEVEL (TEXT), RACK (TEXT), DYECODE (TEXT), WEIGHT (TEXT), PROCESS (TEXT), TIME (TIMESTAMP), processCOLOR (TEXT), WH_DONE (BOOLEAN), WH_TIME (TIMESTAMP).
- **Phát hiện cấu trúc Trộn lẫn Header - Detail (Single Table Mix):**
  - Bảng này trộn chung thông tin của Lô (Batch Header) và Từng thành phần bột màu cân (Measurement Detail).
  - **Dòng Header của Batch:** Trường `DYECODE`, `WEIGHT`, `PROCESS`, `RACK` đều rỗng (`\N`). Dòng này chỉ ghi nhận `batchID`, `COLOR`, `CODE`, `MACHINE`, `LEVEL` và `TIME` phát hành.
  - **Dòng Detail cân:** Trường `batchID`, `COLOR`, `CODE`, `MACHINE` có thể bị rỗng hoặc lặp lại, nhưng trường `DYECODE` (mã bột màu), `WEIGHT` (trọng lượng cân thực tế), `PROCESS` (công đoạn) và `RACK` (vị trí bồn/rack bột màu) sẽ có giá trị.
  - Cần phải chuẩn hóa tách thành 2 bảng `production_batches` (Header) và `scale_measurements` (Detail) trong database đích.

### 2.2. Bảng `tblRECORD_chem` (Nhật ký cân hóa chất)
- **Số lượng bản ghi:** 5,061 dòng (Trong database primary) / 1,500 dòng (Trong database bổ sung `chem_order.accdb`).
- **Số cột:** 12 cột.
- **Chất lượng dữ liệu - Rỗng hoàn toàn:**
  - Cả trong database primary và `chem_order.accdb`, toàn bộ các dòng của bảng `tblRECORD_chem` đều có các trường `RACK`, `DYECODE`, `WEIGHT`, `PROCESS` trống hoàn toàn (`None` / `\N`).
  - Dữ liệu chỉ ghi nhận Header của mẻ cân (ID, batchID, COLOR, CODE, MACHINE, LEVEL, TIME).
  - Đây là bằng chứng cho thấy hệ thống cũ **không thực hiện cân thủ công hóa chất** tại trạm cân, mà sử dụng hệ thống cấp hóa chất tự động (Automatic Dispensing System).

### 2.3. Bảng `tbl_status` (Cấu hình kênh hóa chất tự động)
- **Nguồn:** Chỉ có trong cơ sở dữ liệu bổ sung [chem_order.accdb](file:///F:/DF/chem_order.accdb).
- **Số lượng bản ghi:** 40 dòng.
- **Số cột:** 5 cột.
- **Cấu trúc cột:** ID (COUNTER/BIGINT, khóa chính), machine (VARCHAR), chem (SMALLINT), chem_name (VARCHAR), status (VARCHAR).
- **Ý nghĩa nghiệp vụ:**
  - Bảng này đóng vai trò là **Bản đồ cấu hình kênh/van hóa chất tự động** cho từng máy nhuộm.
  - Mỗi dòng ánh xạ một máy nhuộm (`machine` - ví dụ `'VD016'`), một số hiệu kênh hóa chất (`chem` - ví dụ `4`), tên hóa chất nạp trong kênh đó (`chem_name` - ví dụ `'AC77'`), và trạng thái hoạt động (`status` - ví dụ `'0'`).
  - Ví dụ: Dòng `['VD016', 4, 'AC77', '0']` có nghĩa là tại Máy nhuộm VD016, Kênh hóa chất số 4 đang được nạp hóa chất AC77. Khi mẻ nhuộm yêu cầu hóa chất AC77 cho máy VD016, hệ thống web/agent mới sẽ biết cần kích hoạt kênh số 4.

---

## 3. Tổng hợp Chất lượng Dữ liệu Legacy
1. **Lệch cột dữ liệu:** Bảng `tbl_ToSend2` và `WAITING` bị lệch cột trong file SQL trích xuất.
2. **Khóa chính ID bất nhất:** ID trong Access là số nguyên ngẫu nhiên (chứa cả số âm và số dương rất lớn, ví dụ `-1128417259` và `1763609526`), nhiều ID bị rỗng. Không thể dùng ID Access làm khóa chính nối quan hệ vật lý trong PostgreSQL app schema.
3. **Mô hình Header-Detail lồng nhau:** `tblRECORD` cần được phân tách cấu trúc khi chuyển sang DB mới.
4. **Lỗi hỏng dữ liệu:** `tbl_SentLog` bị hỏng cấu trúc tệp (overflow page) cần cứu hộ thủ công.
5. **Dữ liệu thời gian dạng Text:** Cột thời gian trong một số bảng hàng chờ lưu dạng TEXT (`MM/DD/YYYY HH:MM:SS`) dễ gây lỗi parse khi import vào cột TIMESTAMP của PostgreSQL.
6. **Bảng cấu hình bổ sung:** Sự xuất hiện của bảng `tbl_status` trong `chem_order.accdb` giúp làm sáng tỏ phân hệ cấp hóa chất tự động (Automatic Dispensing), thay thế cho sự thiếu hụt dữ liệu cân thủ công trong `tblRECORD_chem`.

