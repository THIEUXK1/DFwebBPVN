# Architecture Workflow - Quy trình Làm việc & Kiến trúc

> Đọc khi: tạo file mới, thay đổi chạm nhiều tầng, chuẩn bị commit/deploy. Quy tắc cấm tuyệt đối nằm ở `.claude/rules/core.md` mục 3 — không nhắc lại ở đây.

---

## 1. Phân lớp đầy đủ

```
Trình duyệt kiosk (Vue SPA) --HTTP axios :8500 + Sanctum token--> Backend Laravel 12 --Eloquent/DB::--> PostgreSQL 15 (:5433)
Backend --broadcast (Reverb, WebSocket :8080)--> Trình duyệt (laravel-echo + pusher-js)
Local Agent .NET 8 --HTTPS + AgentAuth--> Backend (đẩy số cân, nhận lệnh in/rack)
Trình duyệt --HTTP 127.0.0.1 (LocalWeightServer)--> Local Agent (chỉ đọc số cân + gạt nguồn cân; ADR-013)
Local Agent --Serial/PuTTY log--> Cân · --TSPL--> Máy in TSC · --RPA chuột+clipboard--> Hệ pha màu (ADR-012)
```

- Trình duyệt **không** nói chuyện trực tiếp với phần cứng — luôn qua Agent (ADR-002). Kênh loopback 127.0.0.1 của ADR-013 vẫn là Agent, không phải phần cứng.
- Realtime: **Laravel Reverb** thay SSE từ 2026-07-30 (ADR-008 bản cập nhật). Reverb là tiến trình riêng — không chạy thì broadcast lỗi `cURL error 7 ... port 8080` dù API HTTP vẫn sống.
- Sự kiện realtime ghi qua outbox `app.realtime_events` (`App\Models\RealtimeEvent`, ADR-009) trong cùng transaction với thay đổi nghiệp vụ. ADR-009/010 viết cho SSE — chưa rà lại với Reverb; màn hình quan trọng vẫn có polling (`setInterval`) làm đường dự phòng.

## 2. Tạo file mới ở đâu

| Loại nội dung | Vị trí | Ghi chú |
|---|---|---|
| Endpoint HTTP | `backend/app/Http/Controllers/*Controller.php` + route trong `backend/routes/api.php` | Controller chỉ điều phối; nhóm kiosk nằm trong `KioskAuthenticationMiddleware`, admin trong `role:ADMIN` |
| Nghiệp vụ, tính toán, chuyển trạng thái | `backend/app/Services/` (thư mục con theo miền: `Mes/`, `ColorService/`) | Một service một nghiệp vụ |
| Truy cập bảng | `backend/app/Models/` (Eloquent) | Repo **không** có tầng Repository — không tự thêm |
| Middleware quyền/thiết bị | `backend/app/Http/Middleware/` | `AgentAuth`, `CheckRole`, `WorkstationGuard`, `KioskAuthenticationMiddleware` |
| Tiện ích thuần không phụ thuộc DB | `backend/app/Support/` | vd. `MachineCodeNormalizer` |
| Broadcast event | `backend/app/Events/` | |
| Xuất Excel/PDF | `backend/app/Exports/` (maatwebsite/excel), PDF qua dompdf | |
| Lệnh artisan / đồng bộ định kỳ | `backend/app/Console/` | |
| Migration | `backend/database/migrations/` | idempotent + `down()` an toàn — xem `database-safety.md` mục 4 |
| Test backend | `backend/tests/` | **Không chạy ở máy dev** — `core.md` mục 3 |
| Trang (route SPA) | `frontend/src/views/*.vue` + đăng ký trong `frontend/src/router/` | |
| Component tái sử dụng | `frontend/src/components/` (thư mục con theo miền, vd. `weighing/`) | |
| Logic dùng lại có state/vòng đời | `frontend/src/composables/use*.ts` | vd. `useScaleFeed` |
| Gọi API / hàng đợi / kênh realtime | `frontend/src/services/` | `saveQueue.ts`, `echo.ts`, `workstation.ts` |
| State dùng chung | `frontend/src/stores/` (Pinia) | |
| Hàm thuần (parse QR, dựng TSPL, in phiếu) | `frontend/src/utils/` | |
| Chuỗi giao diện | `frontend/src/locales/modules/<View>.ts` (vue-i18n) | |
| Agent | `agent/*.cs` (một lớp một file), test ở `agent/DFAgent.Tests/` | Cấu hình theo trạm: `agent/installer/appsettings.{small,large,large-inout}.json` |
| Script vận hành server | `tools/*.ps1` / `*.bat` | `.ps1` phải lưu UTF-8 **có BOM** |
| SQL di trú Access → Postgres | `sql_migration/` | Không xóa file gốc |
| Tài liệu tra cứu | `.claude/docs/` hoặc `.claude/*.md` | Không thêm file vào `.claude/rules/` |

Không khớp ô nào → hỏi `system-architect` trước khi tạo.

## 3. Git flow (thực tế đang dùng)
- Một nhánh `main`, commit thẳng — repo một người phát triển. Muốn thử lớn/rủi ro thì tách nhánh `fix/<mô-tả>` hoặc `feat/<mô-tả>` rồi merge.
- Chỉ commit/push khi người dùng yêu cầu (hoặc qua `/deploy`). Trước khi ghi: `git status --short` — cây làm việc thường có thay đổi dở của người dùng (kể cả `.xlsm`), không `git add -A` bừa.
- Mẫu commit: tiếng Việt **không dấu**, một dòng nói bug/tính năng và **vì sao**, kèm hệ quả đo được nếu có. Ví dụ thật: `Sua tram can bi ket vinh vien o AUTO_REGISTERED: 43 me can xong khong gui duoc tu 29/08`.
- Cấm `git push --force` lên `main`. Push lên `origin/main` **không** tự deploy.
- Deploy: `/deploy` (`.claude/commands/deploy.md`). Allowlist lệnh deploy ở `database-safety.md` mục 7.

## 4. Quy trình cho mỗi thay đổi
1. Xác định phân hệ M01-M12 (`.claude/business-modules.md`).
2. Tra nguồn VBA/Access gốc (`.claude/source-traceability.md`) — không viết lại logic nghiệp vụ mà không đối chiếu workbook.
3. Tra ADR chi phối (`.claude/architecture-decisions.md`): realtime → ADR-008/009/010; thiết bị → ADR-002/012/013; nhãn in → ADR-007.
4. Đụng bảng/migration → `database-safety.md` + `database-auditor`. Đụng kiến trúc (service mới, giao thức, luồng dữ liệu) → `system-architect`.
5. Sửa code theo `coding-standards.md`.
6. Kiểm chứng trong giới hạn an toàn: `php -l`, `npx vue-tsc --noEmit`, `npx vite build`, `dotnet test` (agent). Logic công thức/dung sai → Golden Master (sai số cân ±0.000001, định mức bột màu 0.0 — `.claude/testing-strategy.md`). Migration dữ liệu → `sql_migration/04_validation_queries.sql`.
7. Hành động nhạy cảm (duyệt công thức, override dung sai, reprint, force unlock, sửa KB) → xác nhận audit log ghi đủ `before_data`/`after_data` (`database-safety.md` mục 5).
8. Ghi `.claude/session-log.md` (yêu cầu · đã sửa · đã kiểm chứng · **chưa** kiểm chứng gì trên máy thật) và cập nhật `.claude/plans/00-context-memory.md`.

## 5. State machine
Thực thể có vòng đời dùng trạng thái tường minh, không dùng cờ boolean rời:
- Recipe: `draft → submitted → approved → obsolete`
- Batch: `NEW → WAITING_CONFIRM → READY_TO_WEIGH → WEIGHING → WEIGHED → READY_TO_SEND → SENT → DONE`
- Weighing Session: `PENDING → WEIGHING → OVERRIDE_REQUIRED → CONFIRMED → PRINTED`
- Material Transfer: `ready_for_transfer → in_transit → arrived_at_tank → accepted/rejected`

Thêm state/transition → cập nhật `.claude/state-machines.md` và ghi session-log.

## 6. Khi tài liệu lệch code
Tin code/git log hiện tại, báo người dùng, đề xuất sửa tài liệu — không tự sửa tài liệu cho "khớp" khi chưa xác nhận.

## 7. Khi nào gọi subagent nào
| Tình huống | Agent |
|---|---|
| Service/luồng dữ liệu/giao thức mới, đụng ADR, phân vân giữa 2 phương án | `system-architect` |
| Migration mới, nghi dữ liệu sai, đối soát staging ↔ `app`, audit log | `database-auditor` |
| Review diff trước commit/deploy (bảo mật, N+1, rò rỉ tài nguyên) | `code-reviewer` |
