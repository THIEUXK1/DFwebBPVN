# Database Safety - An toàn Dữ liệu

> Đọc khi: viết migration, viết truy vấn thô, chạm dữ liệu di trú/audit log, thao tác trên CS-SERVER. Danh sách lệnh cấm tuyệt đối nằm ở `.claude/rules/core.md` mục 3. Người thi hành chính: subagent `database-auditor`.

---

## 1. Sự thật nền tảng — môi trường nào, ai sở hữu dữ liệu
- **Chỉ có MỘT database thật: `production_web` trên CS-SERVER `10.0.60.209:5433`** (Windows Service `postgresql-df`). `backend/.env` ở máy dev trỏ thẳng vào đó (`DB_HOST=10.0.60.209`). Mọi lệnh DB chạy từ máy dev — `artisan migrate`, `tinker`, seed, test — **là chạy trên production**.
- Vì vậy "môi trường dev cô lập" hiện **chưa tồn tại**. Không chắc lệnh sẽ chạm DB nào → coi như production.
- Dữ liệu thuộc về xưởng đang chạy song song UAT: số cân, lệnh cấp máy, audit log là bằng chứng nghiệm thu. Backup tự động 2h sáng (`DFWeb-Backup` → `tools/db-backup.ps1`, giữ 14 ngày), kiểm tra được bằng `tools/db-backup-verify.ps1`.
- Nguồn cũ (Access `.accdb` trên `\\10.0.55.3\Share`) chỉ đọc; luôn copy ra thư mục tạm trước khi mở.

## 2. Phân vùng Schema

| Schema | Ghi được? | Sửa cấu trúc? | Ghi chú |
|---|---|---|---|
| `legacy_df_data` | ❌ (import 1 lần ở Phase 2) | ❌ | Nguồn sự thật lịch sử để đối soát — bất biến |
| `legacy_df_scale` | ❌ | ❌ | Như trên |
| `app` | ✅ qua Eloquent / migration có kiểm soát | ✅ qua migration có review | Dữ liệu chuẩn hóa |

Dữ liệu staging sai → sửa ở tầng transform (`sql_migration/03_transform_legacy_to_target.sql`) khi đổ sang `app`, không sửa ngược staging. Không có ngoại lệ.

## 3. Soft delete
- Không `DELETE` vật lý dữ liệu giao dịch (batch, weighing, material transfer, feed operation…) hay cấu trúc lịch sử (recipe version, master data đã được tham chiếu).
- Dùng `deleted_at` hoặc trạng thái nghiệp vụ tường minh (`obsolete`, `archived`, `cancelled`, `inactive`). Master data đã tham chiếu chỉ chuyển `Inactive`.

## 4. Migration — up/down, idempotent
- Luôn tạo migration mới trong `backend/database/migrations/`; không sửa tay dữ liệu/cấu trúc ngoài migration. Bài học `personal_access_tokens` (Phase 11): bảng bị xóa tay → trôi migration tracking → đăng nhập 500.
- `up()` idempotent: kiểm `Schema::hasTable`/`hasColumn` hoặc `IF NOT EXISTS` trước khi tạo.
- `down()` an toàn: nếu rollback làm mất dữ liệu thì để no-op và ghi chú lý do.
- Khóa chính `UUID` (`gen_random_uuid()`); bảng có nguồn Access mang `legacy_source`, `legacy_id`, `legacy_row_no` (ADR-004); bảng nhận dữ liệu từ Agent có `idempotency_key` UNIQUE.
- Lệch cột `tbl_ToSend2` / `WAITING`: ánh xạ cột thủ công, không SQL động chung.
- `php artisan migrate` trên CS-SERVER **không** thuộc allowlist deploy — hỏi lại người dùng mỗi lần.

## 5. Viết truy vấn an toàn
- Luôn tham số hóa: Eloquent/Query Builder, hoặc `DB::select($sql, [$a, $b])` với placeholder. Cấm nối chuỗi input vào SQL; tên cột/bảng động phải qua whitelist.
- `UPDATE`/`DELETE` luôn có `WHERE` hẹp theo khóa; thao tác nhiều bảng hoặc chuyển trạng thái bọc `DB::transaction()` + `lockForUpdate()` khi có tranh chấp (claim lock điều phối, cân, cấp máy).
- Danh sách lớn: eager load (`with()`), chọn cột cần thiết — dashboard từng chạy 16.822 query/143s vì N+1 (commit `03ac7f5`).
- Giờ từ MES lệch 7 tiếng so với giờ xưởng (commit `984e1e0`) — so thời gian giữa nguồn MES và `app` phải quy đổi múi giờ tường minh.

## 6. Audit log bất biến
- `app.audit_logs` append-only: không có endpoint UPDATE/DELETE.
- Bắt buộc ghi cho: duyệt/phát hành công thức; override dung sai cân (lý do + tài khoản QA/QC duyệt); reprint tem; force unlock điều phối; sửa Troubleshooting KB.
- JSONB `before_data` / `after_data` đầy đủ, không rút gọn.

## 7. Đối soát — tiêu chí chấp nhận
- Trọng lượng cân ±0.000001 (làm tròn float Access → numeric Postgres). Định mức bột màu (Golden Master) sai số 0.0.
- Số dòng nguồn Access = số dòng đích `app` theo loại (DYE/CHEMICAL); quét orphan trong `machine_dispatches`, báo tỉ lệ khớp mã lô. Script: `sql_migration/04_validation_queries.sql`.
- Dung sai cân nghiệp vụ thật trong `RECORD.accdb` là ±1% (xem `record-a-record-b-correlation.md`).

## 8. Production & allowlist deploy
- Mọi thao tác SSH/DB trên CS-SERVER, kể cả chỉ đọc, phải được xác nhận trong phiên hiện tại — đồng ý lần trước không tự áp dụng.
- **Ngoại lệ (xác nhận 31/07/2026):** đúng các lệnh deploy thường quy — git push, SSH `git pull` trên CS-SERVER, `npm run build` cục bộ và trên server, dừng/khởi động scheduled task `DFWeb-Backend`/`DFWeb-Reverb`/`DFWeb-Frontend`/`DFWeb-Queue`, đọc log/trạng thái task — đã allowlist exact-match trong `.claude/settings.json`, không hỏi lại. Migration, DDL/DML, SSH ngoài quy trình, đổi cấu hình server vẫn phải hỏi.
- Đổi quy trình deploy → cập nhật cả `.claude/settings.json` và mục này.

## 9. Khi nghi dữ liệu sai
1. Dừng ghi. Không "vá nhanh" bằng UPDATE tay.
2. Đọc, đếm, so với staging/Access/MES bằng truy vấn chỉ đọc (người dùng xác nhận trước nếu chạy trên CS-SERVER).
3. Gọi `database-auditor` để lập phát hiện + script sửa đề xuất (kèm câu lệnh chính xác, bảng & số hàng bị chạm, cách rollback, backup đêm gần nhất).
4. Người dùng tự chạy script sửa; ghi session-log và audit log nếu là hành động nhạy cảm.
