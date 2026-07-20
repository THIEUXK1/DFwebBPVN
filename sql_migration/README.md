# Bộ chuyển đổi Access → PostgreSQL

## Nguồn
- RECORD.accdb: điều phối máy, checksum `456d481142f97315fc7d92b5befb87b2973af7e3df6e74f822bbd53e55bb4dff`
- RECORD(1).accdb: dữ liệu cân, checksum `1afca8455e8552f0db059670d92065eda2de5675ea8800e9913d9e88914e3aba`

## Thứ tự chạy
```bash
createdb production_web
psql -v ON_ERROR_STOP=1 -d production_web -f 01_legacy_access_import_postgresql.sql
psql -v ON_ERROR_STOP=1 -d production_web -f 02_target_normalized_schema_postgresql.sql
psql -v ON_ERROR_STOP=1 -d production_web -f 03_transform_legacy_to_target.sql
psql -d production_web -f 04_validation_queries.sql
```

## Kết quả trích xuất
- tblRECORD: 140,660 dòng
- tblRECORD_chem: 5,061 dòng
- tbl_Waiting: 71 dòng
- tbl_ToSend2: 696 dòng
- WAITING: 57 dòng
- Các bảng TBL_INPUT_ALL, tbl_ToSend, tbl_OUTPUT_PROCESSING, tbl_ARCHIVE, tblSync hiện rỗng theo bản sao nhận được.

## Cảnh báo quan trọng
`tbl_SentLog` có lỗi overflow page khi đọc bằng thư viện độc lập. File import tạo cấu trúc bảng nhưng không nhập dữ liệu bảng này. Hãy Compact & Repair trên một bản sao bằng Microsoft Access, sau đó export bảng thành CSV/ACCDB mới để nhập bổ sung.

## Nguyên tắc
- Không chạy trên database sản xuất trước khi thử ở môi trường test.
- Giữ nguyên hai file Access và checksum để đối soát.
- Kiểm tra encoding tiếng Việt/Trung và timezone sau import.
