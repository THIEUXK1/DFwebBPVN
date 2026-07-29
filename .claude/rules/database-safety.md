# Database Safety - Quy tắc An toàn Dữ liệu

> Bổ sung chi tiết vận hành cho mục 3 "Quy tắc An toàn Dữ liệu & Môi trường" và mục 6 "Lệnh Bị cấm" trong `.claude/CLAUDE.md`. Vai trò chịu trách nhiệm chính thi hành các quy tắc này là [[database-auditor]] (`.claude/agents/database-auditor.md`).

---

## 1. Phân vùng Schema và mức độ được phép thao tác

| Schema | Ghi được? | Sửa cấu trúc được? | Ghi chú |
|---|---|---|---|
| `legacy_df_data` | ❌ KHÔNG (chỉ import 1 lần ở Phase 2) | ❌ KHÔNG | Nguồn sự thật lịch sử phục vụ đối soát — bất biến |
| `legacy_df_scale` | ❌ KHÔNG | ❌ KHÔNG | Tương tự trên |
| `app` | ✅ Qua Laravel migration/Eloquent có kiểm soát | ✅ Qua migration có review | Dữ liệu chuẩn hóa phục vụ ứng dụng |

**Không có ngoại lệ** cho việc sửa dữ liệu thô trong 2 schema staging — kể cả khi phát hiện lỗi rõ ràng trong dữ liệu nguồn. Nếu cần "sửa", xử lý ở tầng transform (`03_transform_legacy_to_target.sql` hoặc tương đương) khi đổ dữ liệu sang `app`, không sửa ngược lại bảng staging.

## 2. Lệnh bị cấm tuyệt đối (nhắc lại từ CLAUDE.md, không có ngoại lệ trừ xác nhận rõ ràng bằng văn bản của chủ dự án)
- `DROP DATABASE ...`
- `DROP SCHEMA app CASCADE;` (hoặc bất kỳ CASCADE nào xóa toàn bộ schema `app`)
- Xóa file `.accdb`, `.docx` gốc, hoặc file `.sql` di trú gốc trong `sql_migration/`
- `git push --force` trên nhánh chính (`main`)
- Bất kỳ lệnh DDL/DML chạy trực tiếp trên database **Production** mà chưa có văn bản phê duyệt + kế hoạch backup cụ thể

## 3. Nguyên tắc Soft Delete
- **Không bao giờ** dùng `DELETE` vật lý cho dữ liệu giao dịch (Batch, Weighing Session, Material Transfer, Feed Operation...) hoặc cấu trúc lịch sử (Recipe Version, Master Data đã tham chiếu).
- Dùng cột `deleted_at` (Eloquent Soft Delete) hoặc trạng thái nghiệp vụ tường minh (`obsolete`, `archived`, `cancelled`, `inactive`).
- Master Data (máy, vật tư, mã hàng) không được xóa nếu đã được tham chiếu bởi công thức hoặc giao dịch — chỉ chuyển trạng thái `Inactive`.

## 4. Idempotency & Truy vết nguồn gốc (bắt buộc cho dữ liệu di trú)
- Mọi bảng trong `app` có nguồn gốc từ Access phải có: `legacy_source`, `legacy_id`, `legacy_row_no` (ADR-004) — phục vụ chạy lại migration nhiều lần mà không xung đột khóa và đối soát chéo số lượng bản ghi.
- Mọi bảng nhận dữ liệu realtime từ Local Agent (cân, in tem) phải có `idempotency_key` với ràng buộc UNIQUE để chống ghi trùng khi Agent đồng bộ lại dữ liệu offline.
- Khóa chính kỹ thuật trong schema `app` luôn là `UUID` (`gen_random_uuid()`), không dùng `integer` tự tăng — do chất lượng ID nguồn từ Access không đáng tin cậy.

## 5. Audit Log bất biến (Immutable Audit Trail)
- Bảng `app.audit_logs` là **append-only tuyệt đối** — Backend API không được có bất kỳ endpoint UPDATE/DELETE nào cho bảng này.
- Bắt buộc ghi log cho 100% các hành động:
  - Phê duyệt/phát hành phiên bản công thức sản xuất.
  - Override dung sai cân (kèm lý do + tài khoản QA/QC phê duyệt).
  - In lại tem (Reprint) và force unlock khóa điều phối thủ công.
  - Thay đổi kho tri thức sự cố (Troubleshooting KB).
- Định dạng lưu trữ: JSONB với `before_data` và `after_data` đầy đủ, không rút gọn.

## 6. Đối soát dữ liệu (Reconciliation) — tiêu chí chấp nhận
- Sai số trọng lượng cân cho phép: **±0.000001** (bù trừ sai lệch làm tròn float Access → numeric Postgres).
- Sai số định mức bột màu (Golden Master công thức): **0.0** — không có sai số.
- Đối soát bắt buộc: số dòng nguồn (Access) = số dòng đích (`app`) cho từng loại dữ liệu (DYE/CHEMICAL); quét bản ghi mồ côi (orphan) trong `machine_dispatches` và báo cáo tỉ lệ khớp mã lô.

## 7. Môi trường & Kết nối Production
- Môi trường phát triển/kiểm thử phải cô lập hoàn toàn với mạng sản xuất thực tế.
- Mọi thao tác SSH/DB trên server production, kể cả các lệnh chỉ đọc nhìn "an toàn", cần được xác nhận lại với người dùng trong phiên hiện tại trước khi thực thi — một lần đồng ý trước đó không tự động áp dụng cho phiên/lệnh khác.
- Trước khi chạy bất kỳ lệnh nào có khả năng ghi vào Git hoặc filesystem (kể cả trên môi trường dev), chạy `git status` để không vô tình ghi đè công việc đang dang dở của người khác.
