# Coding Standards - Tiêu chuẩn Viết Code

> Đọc khi viết/review code. Tóm tắt 6 dòng nằm ở `.claude/rules/core.md` mục 5; nơi đặt file mới ở `architecture-workflow.md` mục 2. `.claude/coding-standards.md` (root) là bản đề xuất stack Phase 0 — chỉ còn giá trị lịch sử.

---

## 1. Chung (mọi ngôn ngữ)
- **Đặt tên:** DB `snake_case`, bảng số nhiều (`app.production_batches`), khóa ngoại `<bảng số ít>_id`, khóa chính `id` (UUID). Class `PascalCase`, method/biến `camelCase`, endpoint REST `kebab-case` số nhiều (`POST /api/rack-dispatch`, `/api/formula-versions/{id}/approve`). Frontend được phép đặt tên tiếng Việt không dấu cho state gắn nghiệp vụ xưởng (`nguonCan`, `doiNguonCan`) — giữ nhất quán trong cùng file.
- **Comment:** tiếng Việt, chỉ viết khi có ràng buộc ẩn/lý do không hiển nhiên — ghi ngày và sự cố gốc (vd. comment `legacy()` trong `vite.config.ts` giải thích máy kiosk crash 2026-07-25). Không mô tả lại code làm gì.
- **Abstraction:** không thêm tầng mới khi chưa có ≥ 2 nghiệp vụ thật dùng chung. Không thêm dependency khi chưa hỏi.
- **Port từ VBA:** giữ tên/ý nghĩa cột và hằng số như workbook (vd. `f3Val >= "VD10" And f3Val <= "VD16"`), ghi nguồn (`Mod_printslip.bas`, hàm …) ở comment đầu hàm.

## 2. Backend — Laravel 12 (PHP 8.2+, server chạy PHP 8.4)
- **Style:** PSR-12, `./vendor/bin/pint` cho file đã sửa. Pint báo fixer ở dòng không đụng tới → đối chiếu HEAD, đừng sửa lan.
- **Controller mỏng:** nhận request → validate → gọi `app/Services/*` → trả JSON. Tính toán, chuyển trạng thái, gọi MES nằm ở Service.
- **Validate:** thực tế repo dùng `$request->validate([...])` trong controller (71 chỗ), chưa có thư mục `FormRequest`. Giữ cách đó; chỉ tạo `FormRequest` khi cùng bộ luật dùng ≥ 2 nơi. Cấm `$request->all()` đưa thẳng vào `create/update` — chỉ dùng mảng trả về từ `validate()`.
- **Lỗi:** validate sai → 422 + `errors` theo field (mặc định Laravel). Lỗi nghiệp vụ → 4xx + `message` tiếng Việt người vận hành đọc được. Không trả stacktrace/SQL ra client; log server kèm `correlation_id` khi đi qua job Agent.
- **Transaction:** ghi nhiều bảng / chuyển trạng thái → `DB::transaction()` + `lockForUpdate()` khi có tranh chấp. Broadcast (Reverb) chỉ sau commit — dùng outbox `RealtimeEvent` hoặc `DB::afterCommit`.
- **Dữ liệu rỗng:** API danh sách trả `[]` chứ không `null`/404; field số không có giá trị trả `null`, không `0` (0 kg là số cân thật).
- **Quyền:** kiểm phía server bằng middleware (`role:*`, `WorkstationGuard`, `AgentAuth`) — không tin vai trò/công đoạn gửi từ client.

## 3. Frontend — Vue 3 + TypeScript + Vite 5
- `<script setup lang="ts">` + Composition API; không viết Options API mới. `npx vue-tsc --noEmit` phải sạch trước khi báo xong.
- **Tầng:** view = bố cục + gọi composable/service; logic dùng lại → `composables/`; gọi API/hàng đợi/realtime → `services/`; hàm thuần → `utils/`; state chung → `stores/` (Pinia). Không gọi `axios` rải rác trong component con khi đã có service tương ứng.
- **SPA, không reload:** mọi thao tác qua axios + cập nhật state; `@submit.prevent`/`event.preventDefault()`. Cấm `location.reload()` trong luồng nghiệp vụ — ngoại lệ duy nhất hiện có: interceptor 401 hết phiên trong `main.ts` (dọn state hỏng rồi để router đưa về đúng màn). Lỗi mạng → giữ dữ liệu đã nhập (hàng đợi `saveQueue.ts`), báo lỗi, không mất số cân.
- **Realtime & timer:** mọi `Echo.channel(...)`, `setInterval`, `EventSource`, listener `window` phải gỡ trong `onBeforeUnmount`/`onUnmounted` — màn cân mở cả ca, rò là treo máy kiosk.
- **Chuỗi hiển thị:** qua vue-i18n `locales/modules/<View>.ts`, không hardcode chuỗi mới trong template.
- **Wide layout:** tối ưu màn xưởng/kiosk, chữ lớn, vừa một màn hình không cuộn cho màn giám sát. Kiểm trên Chrome đời cũ: build ra `es2015` + bundle legacy.
- **Biểu đồ:** SVG tự viết (tham chiếu `Reports.vue`, `BpdbMachineStatusChart.vue`); màu định nghĩa theo biến theme — từng vỡ theme tối vì sai tên biến màu (`19e59da`). Chỉ `vis-timeline` cho Gantt.
- **QR:** sinh bằng gói `qrcode` nội bộ, không gọi dịch vụ ngoài (ADR-003).

## 4. Local Agent — .NET 8 Worker Service (C#)
- Đọc Serial/PuTTY log trong background service, không chặn luồng khay (`TrayIcon`).
- Offline: `OfflineQueue` (SQLite) + `idempotency_key` mỗi bản ghi.
- Không hardcode COM/máy in/IP: đọc `appsettings.json` của trạm (mẫu trong `agent/installer/appsettings.{small,large,large-inout}.json`) và cấu hình đăng ký Workstation từ backend (ADR-007).
- Endpoint loopback (`LocalWeightServer`) kiểm `Origin` — chỉ loopback + host trong `Backend:Urls`/`Local:AllowedOriginHosts` được POST.
- Mỗi thay đổi đọc cân/lọc ổn định có unit test trong `agent/DFAgent.Tests/` với chuỗi nhiễu giả lập; `dotnet test` phải xanh. Đổi Agent → nhắc người dùng build lại MSI (`agent/installer/build.ps1`).

## 5. Golden Master
Hàm tính công thức, TraHeSo, mực nước, lực căng, dung sai cân phải qua Golden Master trước khi merge (`.claude/testing-strategy.md` mục 2.2): định mức bột màu sai số 0.0, trọng lượng ±0.000001.

## 6. Terminal output
Theo `core.md` mục 6 (output clamping). Build frontend: `npx vite build --logLevel warn`; chỉ chạy lại verbose khi lỗi.
