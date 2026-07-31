---
description: Commit, push và deploy code mới nhất lên CS-SERVER (pull + build + restart service)
---

Người dùng gõ `/deploy` (hoặc dán nguyên văn lệnh này) nghĩa là: đẩy toàn bộ thay đổi code hiện tại lên GitHub rồi deploy lên server production CS-SERVER, làm đúng quy trình dưới đây — không cần hỏi lại người dùng từng bước, trừ các điểm được đánh dấu "phải xác nhận".

## 1. Kiểm tra thay đổi
- Chạy `git status` ở repo root (`c:\laragon\www\DFwed`).
- Nếu không có gì thay đổi: báo cho người dùng biết, dừng lại, không làm gì thêm.
- Đọc qua `git diff` các file đã đổi để hiểu bản chất thay đổi (không cần hỏi người dùng nếu thay đổi rõ ràng, tự chứa, khớp với các quy tắc trong `.claude/CLAUDE.md` và `.claude/rules/`). Nếu phát hiện thay đổi có tính kiến trúc lớn, đụng tới quy tắc cứng (Audit Log, Local Agent, soft delete...), hoặc trông như dở dang/không nhất quán — dừng lại hỏi người dùng trước khi tiếp tục.

## 2. Kiểm tra trước khi commit (theo khả năng thay đổi)
- Có sửa `frontend/**`: chạy `npm run build` trong `frontend/`.
- Có sửa `backend/**/*.php`: chạy `php -l` cho từng file đã đổi.
- Có sửa `agent/**`: chạy `dotnet build` trong `agent/`.
- Nếu build/lint lỗi: dừng lại, sửa hoặc báo lỗi cho người dùng, không commit code lỗi.

## 3. Commit & push
- `git add` đúng các file liên quan tới thay đổi lần này (không dùng `git add -A` mù quáng — kiểm tra file lạ như file Excel tạm/backup trước khi thêm).
- Viết commit message ngắn gọn, tiếng Việt không dấu, nêu **tại sao** thay đổi.
- `git push origin main`.

## 4. Deploy lên CS-SERVER
Server: `ssh -i ~/.ssh/df_server_key color@10.0.60.209`, thư mục code `C:\DFwebBPVN`.

1. Pull code: `git -c safe.directory=C:/DFwebBPVN pull origin main` trong `C:\DFwebBPVN`.
2. Nếu có migration mới trong diff vừa pull: **phải xác nhận với người dùng trước khi chạy** `php artisan migrate --force` (không nằm trong allowlist tự động — xem `.claude/rules/database-safety.md` mục 7). Nêu rõ migration đó làm gì (thêm/xóa cột, có mất dữ liệu không) khi hỏi.
3. Nếu có sửa `frontend/**`: chạy `npm run build` trong `C:\DFwebBPVN\frontend`.
4. Restart đúng service bị ảnh hưởng bằng `schtasks /end /tn <task>` rồi `schtasks /run /tn <task>` (không cần restart service không liên quan):
   - Sửa `backend/**`: restart `DFWeb-Backend`.
   - Sửa `frontend/**`: restart `DFWeb-Frontend`.
   - Sửa Events/broadcast: restart `DFWeb-Reverb` và `DFWeb-Queue`.
5. Kiểm tra lại bằng cách đọc vài dòng cuối `C:\DFwebBPVN\tools\logs\backend.log` (hoặc log tương ứng) để chắc chắn service khởi động sạch, không lỗi.

## 5. Báo cáo kết quả
Tóm tắt ngắn gọn: đã commit gì (hash), đã deploy gì lên server, có chạy migration không, service nào đã restart, có gì cần người dùng lưu ý (ví dụ xung đột với tài liệu CLAUDE.md cần xác nhận thêm).

## Ghi chú
- Các lệnh git/ssh/build trong quy trình này đã nằm trong allowlist `.claude/settings.json` (xác nhận 31/07/2026) nên không cần hỏi xin phép mỗi lần — CHỈ trừ bước migration ở mục 4.2.
- Không tự ý mở rộng phạm vi deploy ra ngoài quy trình này (ví dụ sửa trực tiếp DB, đổi cấu hình server, sửa file ngoài `C:\DFwebBPVN`) mà không hỏi trước.
