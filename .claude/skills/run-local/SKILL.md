---
name: run-local
description: Chạy toàn bộ hệ thống DF ở máy local (backend Laravel + frontend Vite + Reverb) rồi mở sẵn trên trình duyệt. Dùng khi người dùng nói "chạy code", "chạy app", "mở trên web", "khởi động hệ thống".
---

Người dùng gõ `/run-local` (hoặc nói "chạy code và mở trên web") nghĩa là: khởi động hệ thống DF ở máy local rồi tự mở trình duyệt — làm đúng quy trình dưới đây, không hỏi lại từng bước.

Repo root: `c:\laragon\www\DFwed`.

## 0. Bản đồ cổng (đã kiểm chứng 01/08/2026)

| Dịch vụ | Cổng | Lệnh khởi động | Bắt buộc? |
|---|---|---|---|
| Backend Laravel | 8500 | `php artisan serve --host=0.0.0.0 --port=8500` (trong `backend/`) | ✅ Có |
| Frontend Vite | 3001 | `npm run dev` (trong `frontend/`) | ✅ Có |
| Reverb (WebSocket) | 8080 | `php artisan reverb:start` (trong `backend/`) | Chỉ khi cần realtime |
| Queue worker | — | `php artisan queue:work` (trong `backend/`) | Chỉ khi cần job nền |

Cổng 3001 do `frontend/vite.config.ts` quy định (`server.port`), cổng 8500 do `frontend/src/main.ts` quy định
(`axios.defaults.baseURL = http://${window.location.hostname}:8500`). **Đổi cổng nào cũng phải sửa đúng file đó**, đừng
truyền cờ dòng lệnh khác đi rồi thắc mắc frontend không gọi được API.

## 1. Kiểm tra cổng trước — KHÔNG khởi động trùng

```powershell
Get-NetTCPConnection -State Listen -LocalPort 8500,3001,8080 -ErrorAction SilentlyContinue | Select-Object LocalPort, OwningProcess
```

Cổng nào đã có tiến trình lắng nghe thì **bỏ qua**, không start lại (start trùng sẽ lỗi "address in use", hoặc tệ hơn là
Vite tự nhảy sang cổng khác khiến frontend gọi API sai). Chỉ start những cổng còn trống.

## 2. Khởi động (chạy nền, mỗi dịch vụ một tiến trình background riêng)

Backend và frontend chạy `run_in_background: true` — chúng là tiến trình chạy dài, không bao giờ tự thoát.

- Backend: `cd backend && php artisan serve --host=0.0.0.0 --port=8500`
- Frontend: `cd frontend && npm run dev`
- Reverb / queue: chỉ start khi thay đổi lần này có đụng tới realtime (Gantt máy nhuộm, trạm cân, broadcast Events) hoặc
  job nền. Không thì bỏ qua cho nhẹ máy — nhưng **phải nói rõ với người dùng là chưa bật**, kèm lệnh để họ tự bật.

## 3. Smoke test — bắt buộc, đừng chỉ start rồi báo xong

Đợi ~3 giây cho server lên, rồi kiểm tra thật:

```bash
curl -s -o /dev/null -w "backend -> %{http_code}\n" http://localhost:8500/
curl -s -w "\n-> %{http_code}\n" http://localhost:8500/api/production-batches
curl -s -o /dev/null -w "frontend -> %{http_code}\n" http://localhost:3001/
```

Kết quả **đúng** phải là:
- `http://localhost:8500/` → **200**
- `http://localhost:8500/api/production-batches` → **401** kèm JSON tiếng Việt `"Yêu cầu không hợp lệ. Vui lòng đăng nhập..."`
  — đây là dấu hiệu tốt: app boot sạch, route nạp được, middleware auth hoạt động. **401 là PASS, không phải lỗi.**
- `http://localhost:3001/` → **200**

Nếu ra **500** thay vì 401 → app boot lỗi, đọc log ở file output của tiến trình background rồi sửa, đừng mở trình duyệt.
Lưu ý `/api/health` **không tồn tại** (trả 404) — đừng dùng nó làm health check.

Nếu nghi ngờ kết nối DB, kiểm riêng:
```bash
cd backend && php artisan tinker --execute="try { \DB::connection()->getPdo(); echo 'DB OK: '.\DB::connection()->getDatabaseName(); } catch (\Throwable \$e) { echo 'DB FAIL: '.\$e->getMessage(); }"
```

## 4. Mở trình duyệt

```powershell
Start-Process "http://localhost:3001"
```

## 5. Báo cáo

Bảng trạng thái 4 dịch vụ (cổng + đã chạy sẵn hay vừa start), kết quả smoke test thực tế (mã HTTP thật, không phải suy
đoán), và **luôn nhắc lại cảnh báo ở mục 6 nếu nó còn đúng**.

## 6. ⚠️ Cảnh báo bắt buộc kiểm tra mỗi lần chạy: backend đang trỏ DB Production

Đọc `backend/.env`. Tính tới 01/08/2026 file này có:

```
DB_HOST=10.0.60.209      # = CS-SERVER, máy chủ PRODUCTION
DB_DATABASE=production_web
```

Nghĩa là app local ghi **thẳng vào dữ liệu thật của nhà máy**, trái với `.claude/CLAUDE.md` mục 3 ("Môi trường phát triển
và kiểm thử phải hoàn toàn cô lập với mạng sản xuất thực tế").

Cách xử lý:
- **Không tự ý sửa `.env`** — đây là cấu hình chủ ý của người dùng, sửa lén sẽ làm họ mất kết nối đang cần.
- **Phải nêu rõ cảnh báo này trong báo cáo** mỗi lần chạy, chừng nào `DB_HOST` còn trỏ ra ngoài `127.0.0.1`/`localhost`.
- Nếu người dùng sắp thao tác ghi (cân, in tem, gửi lệnh máy) chỉ để thử nghiệm → khuyên đổi `DB_HOST=127.0.0.1` trước.
- `.env` có sẵn `DB_HOST_CANDIDATES=127.0.0.1,10.0.60.209,192.168.250.151` để tham khảo khi đổi.

## Ghi chú

- Skill này chỉ chạy **local**. Muốn đưa code lên server production thì dùng `/deploy` (`.claude/commands/deploy.md`) —
  hai việc hoàn toàn khác nhau, đừng gộp.
- Không chạy `php artisan migrate` trong quy trình này. Migration trên DB production phải xác nhận riêng với người dùng
  (xem `.claude/rules/database-safety.md` mục 7).
