# Kiến trúc Stack — Laravel 12 + Vue 3 + .NET 8 Agent

> Đọc khi: thêm dependency, chạy/khởi động hệ thống, gặp lỗi môi trường, cần tra route. Nơi đặt file mới: `architecture-workflow.md` mục 2.

## 1. Cây thư mục (chỉ phần quan trọng)
```
DFwed/
├── backend/              Laravel 12 — API :8500
│   ├── app/{Http/Controllers, Http/Middleware, Services, Models, Events, Exports, Console, Support}
│   ├── database/migrations/
│   ├── routes/api.php    toàn bộ API (không dùng web routes)
│   └── tests/            ⚠ TestCase DROP SCHEMA — xem mục 4
├── frontend/             Vue 3 SPA — :3001
│   └── src/{views, components, composables, services, stores, utils, router, locales, i18n}
├── agent/                .NET 8 Worker (DFAgent) — chạy trên từng máy trạm
│   ├── DFAgent.Tests/    xUnit
│   └── installer/        WiX MSI (CanNho / CanTo / CanTo-InOut) + appsettings mẫu từng loại trạm
├── sql_migration/        SQL di trú Access → Postgres (không xóa)
├── tools/                script vận hành CS-SERVER (backup, đồng bộ MES/BPDB)
├── docs/                 tài liệu bàn giao DF Connector (.md + .docx)
└── *.xlsm, *.accdb       workbook/DB gốc — nguồn đối chiếu, không xóa, không commit thay đổi vô tình
```

## 2. Phụ thuộc (mỗi gói một lý do)
**Backend (`composer.json`)**
| Gói | Vì sao |
|---|---|
| `laravel/framework ^12` | Nền tảng đã chốt |
| `laravel/sanctum` | Token cho tài khoản thường + phiên kiosk |
| `laravel/reverb` | WebSocket tự host thay SSE (ADR-008 cập nhật 2026-07-30) |
| `laravel/octane` | Có trong composer; server chạy `artisan serve` qua scheduled task — xác nhận trước khi dựa vào Octane |
| `maatwebsite/excel` | Xuất báo cáo Excel (M11) |
| `barryvdh/laravel-dompdf` | Xuất PDF báo cáo |
| dev: `pint`, `phpunit`, `mockery`, `pail` | Style, test, log tail |

**Frontend (`package.json`)**
| Gói | Vì sao |
|---|---|
| `vue`, `vue-router`, `pinia` | SPA đã chốt |
| `axios` | HTTP; `baseURL` = `http://<host đang mở trang>:8500` trong `main.ts` |
| `laravel-echo` + `pusher-js` | Client Reverb |
| `vue-i18n` | Chuỗi giao diện theo module |
| `qrcode` | Sinh QR nội bộ (ADR-003) |
| `vis-timeline` | Gantt máy nhuộm |
| `@fontsource/inter`, `outfit` | Font offline — xưởng không ra Internet |
| dev: `@vitejs/plugin-legacy` + `terser` | Bundle cho Chrome ≥ 49 trên kiosk cũ (crash `STATUS_ILLEGAL_INSTRUCTION` 2026-07-25) |
| dev: `typescript`, `vue-tsc` | Kiểm kiểu |

**Agent (`DFAgent.csproj`, net8.0)**: `Microsoft.Extensions.Hosting(.WindowsServices)` chạy nền/Windows Service · `System.IO.Ports` đọc cân · `Microsoft.Data.Sqlite` offline queue.

Thêm gói mới → hỏi người dùng trước; server không có Internet ổn định, `npm install` trên CS-SERVER dùng Node portable.

## 3. Quy ước bắt buộc riêng của stack
- Cổng cố định: backend 8500 (`main.ts`), frontend 3001 (`vite.config.ts`, `strictPort` trên server), Reverb 8080, Postgres 5433. Đổi cổng = sửa đúng file đó.
- CS-SERVER phục vụ frontend bằng bản **build** (`npm run preview --port 3001`), không phải Vite dev. Sửa `.vue` mà không build lại = xưởng vẫn chạy code cũ.
- Agent có 3 cấu hình trạm (`small`, `large`, `large-inout`); đổi code Agent phải build lại đúng MSI và cài lại lên máy trạm.
- Chế độ nguồn cân Agent: `PUTTY_LOG` / `SERIAL` / `AUTO_COM`, lưu ở `scale_mode.json` cạnh exe (ghi đè `Scale:Source`).

## 4. Cạm bẫy đã thực sự cắn
| Bẫy | Hậu quả đã xảy ra / sẽ xảy ra | Cách tránh |
|---|---|---|
| `backend/.env` máy dev trỏ `10.0.60.209`; `phpunit.xml` không ghi đè DB; `tests/TestCase.php` chạy `DROP SCHEMA app CASCADE` | Một lần `php artisan test` = xóa DB production (phát hiện 2026-08-04, **chưa vá**) | Không chạy test backend ở máy này — `core.md` mục 3 |
| Reverb không chạy | `Pusher error: cURL error 7 ... port 8080`, API vẫn sống nên khó nhận ra (2026-07-30) | Khởi động `php artisan reverb:start` cùng backend |
| Vite dev cũ giữ cổng 3001 từ 28/07 | Mọi lần deploy trông như "không ăn" (sửa 2026-08-09) | Kiểm tiến trình giữ cổng trước khi kết luận |
| Bảng `personal_access_tokens` bị xóa tay | Đăng nhập 500 (Phase 11) | Chỉ đổi schema qua migration |
| Dashboard N+1 | 16.822 query / 143s (`03ac7f5`) | `with()` + chọn cột |
| Giờ MES lệch 7 tiếng | Mẻ xong vẫn báo đang chạy (`984e1e0`) | Quy đổi múi giờ tường minh |
| Trạm cân kẹt `AUTO_REGISTERED` | 43 mẻ không gửi được từ 29/08 (`52ebc35`) | Mọi trạng thái phải có đường thoát; test chuyển trạng thái |
| Script `.ps1` lưu UTF-8 không BOM | Vỡ cú pháp tiếng Việt trên PowerShell 5.1 server | Lưu có BOM; lệnh trong scheduled task không được chờ nhập mật khẩu |
| `AUTO_COM` trong khi PuTTY đang mở cổng | Agent không đọc được cân | Tắt PuTTY trước khi gạt nguồn |
| Sai tên biến màu theme | Theme tối không ăn màu (`19e59da`) | Dùng đúng token CSS đã định nghĩa |

## 5. Route
**API (`backend/routes/api.php`)** — nhóm theo tiền tố, số route: `admin` 35 · `public` 25 · `machine-dispatches` 10 · `weighing-jobs` 9 · `troubleshooting` 7 · `feed-operations` 7 · `agents` 7 · `production-batches` 6 · `chemical-call-requests` 6 · `workstations`, `scanner`, `material-transports`, `dashboard` 5 · `reports`, `recipes` 4 · còn lại ≤ 3 (`auth`, `kiosk`, `rack-dispatch`, `print-jobs`, `audit-logs`, …). Kiosk nằm trong `KioskAuthenticationMiddleware`, admin trong `role:ADMIN`.

**SPA (`frontend/src/router/`)** — trạm vận hành: `/operate/c/:clientCode/:kioskToken`, `/operate/menu`, `/order`, `/print`, `/scalesmin`, `/scalesmax`, `/weighing-station(-v2|-large)`, `/chemical-call/*`, `/print-station`, `/qr-printer`. Giám sát/quản trị: `/`, `/production-batches/*`, `/bpdb-machines/{gantt,status}`, `/feeding-monitor`, `/machine-queue`, `/reports`, `/audit-logs`, `/workstation-admin`, `/bpdb-admin`.

## 6. Lệnh hay dùng
| Việc | Lệnh |
|---|---|
| Chạy toàn bộ local | skill `/run-local` (backend 8500 + Vite 3001 + Reverb khi cần, có smoke test) |
| Backend dev | `php artisan serve --host=0.0.0.0 --port=8500` (nền) · `php artisan reverb:start` · `php artisan queue:work` |
| Frontend dev (hot reload) | `npx vite --clearScreen false --logLevel warn` (nền) |
| Kiểm frontend | `npx vue-tsc --noEmit` · `npx vite build --logLevel warn` |
| Kiểm PHP | `php -l <file>` · `./vendor/bin/pint <file>` |
| Agent | `dotnet build -v q` · `dotnet test agent/DFAgent.Tests --nologo -v q` · MSI: `agent/installer/build.ps1` |
| Deploy | `/deploy` |
| Health production | `POST http://10.0.60.209:8500/api/auth/login` user rác → 401 là sống, 500 là hỏng |
