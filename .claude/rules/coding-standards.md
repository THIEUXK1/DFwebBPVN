# Coding Standards (Rules) - Tiêu chuẩn Viết Code Vận hành

> Tài liệu gốc `.claude/coding-standards.md` (root) mô tả đề xuất stack và quy ước đặt tên/thư mục ở mức phê duyệt kiến trúc. File này là **phần bổ sung vận hành**: quy tắc viết code cụ thể theo từng ngôn ngữ đã CHỐT (Laravel 12 PHP / Vue 3 TypeScript / .NET 8 C#), dùng để review code hàng ngày.

---

## 1. Backend - Laravel 12 (PHP 8.2+)
- **Chuẩn code style:** PSR-12, chạy `./vendor/bin/pint` trước khi commit.
- **Kiến trúc thư mục dịch vụ:** Logic nghiệp vụ đặt trong `app/Services/` (ví dụ `FormulaService`, `TraHeSoService`, `DispatchLockService`), Controller chỉ điều phối request/response, không chứa logic tính toán.
- **DTO / FormRequest bắt buộc:** Không dùng `Request::all()` trực tiếp trong Controller. Mọi input phải qua `FormRequest` (validate) hoặc DTO tường minh (xem `.claude/coding-standards.md` mục 4.2).
- **Database Transaction bắt buộc** cho mọi thao tác ghi ảnh hưởng nhiều bảng hoặc chuyển trạng thái nghiệp vụ, dùng `DB::transaction()` kết hợp `lockForUpdate()` khi có tranh chấp đồng thời (claim/lock điều phối, cân, cấp máy).
- **Test:** Pest hoặc PHPUnit trong `df-backend/tests/`. Mỗi Controller nghiệp vụ nhạy cảm (Weighing, Dispatch, Report, Auth) phải có Integration Test riêng — xem mẫu `ReportsTest.php`, `AuthenticationFlowTest.php` (`.claude/development-roadmap.md` Phase 11).
- **Migration:** Không bao giờ sửa tay dữ liệu ngoài migration để "vá lỗi nhanh" — bài học từ sự cố `personal_access_tokens` (mục Phase 11 trong roadmap) cho thấy việc này gây trôi dạt migration tracking. Luôn tạo migration mới, idempotent, có `down()` an toàn (no-op nếu việc rollback có thể mất dữ liệu).
- **Exception Handling:** Global Exception Handler tập trung; không trả stacktrace thô ra client — trả Correlation ID + thông báo thân thiện (xem `.claude/coding-standards.md` mục 4.3).

## 2. Frontend - Vue 3 + Vite 5 (TypeScript khuyến khích)
- **Composition API + `<script setup>`** là mặc định, không viết Options API mới.
- **State tập trung:** Pinia store trong `src/store/`, không dùng props-drilling nhiều tầng cho state dùng chung (trạng thái phiên cân, trạng thái Workstation).
- **Wide Layout mặc định:** Giao diện tối ưu hiển thị nhà xưởng/tablet — tránh layout hẹp kiểu form desktop thông thường.
- **SSE Client:** Quản lý vòng đời kết nối `EventSource` chặt chẽ — luôn `close()` khi component unmount để tránh rò rỉ bộ nhớ (theo ADR-010). Token xác thực truyền qua query param `?token=...` (xem `.claude/security-rules.md` mục 2).
- **Component tái sử dụng** (bảng cân, thẻ trạng thái, QR viewer) đặt trong `src/components/`; các trang nghiệp vụ theo phân hệ đặt trong `src/views/`.
- **Biểu đồ/Dataviz:** Tuân thủ chuẩn thiết kế dataviz nội bộ (nguyên tắc "one axis" cho Pareto, xem `Reports.vue` làm mẫu tham chiếu) — không dùng thư viện chart bên thứ ba nếu SVG tự viết đã đáp ứng.

## 3. Local Device Agent - .NET 8 (C#) Worker Service
- **Không block UI thread** khi đọc Serial Port — dùng background service pattern chuẩn của .NET Worker Service.
- **Offline Queue:** Dùng SQLite cục bộ để buffer dữ liệu cân/lệnh in khi mất mạng; đồng bộ lại tự động khi có mạng, kèm `idempotency_key` cho mỗi bản ghi để chống ghi trùng phía server.
- **Không hardcode** cấu hình cổng COM, tên máy in, IP — đọc từ cấu hình đăng ký thiết bị (Workstation Registration) trên Backend (xem `.claude/security-rules.md` mục 2, ADR-007).
- **Stable Filter:** Thuật toán lọc số cân ổn định phải có unit test riêng với các chuỗi đầu vào giả lập nhiễu/nhảy số (xem `.claude/testing-strategy.md` mục 2.1).

## 4. Quy ước chung (áp dụng mọi ngôn ngữ)
- Đặt tên theo `.claude/coding-standards.md` mục 2 (snake_case DB, PascalCase class, camelCase method/biến, kebab-case endpoint).
- Không viết comment mô tả lại CÁI GÌ code làm — chỉ comment khi có ràng buộc ẩn hoặc lý do không hiển nhiên (ví dụ: lý do chọn sai số ±0.000001, lý do dùng `uuidMorphs` thay vì `morphs` mặc định).
- Không thêm abstraction/tầng trừu tượng mới khi chưa có từ 2 nghiệp vụ trở lên thực sự cần dùng chung.
- Commit message ngắn gọn, mô tả **tại sao** thay đổi (bug gì, tính năng gì) hơn là liệt kê file đã sửa.

## 5. Golden Master - tiêu chí bắt buộc riêng cho logic tính toán
Bất kỳ hàm nào tính công thức, hệ số TraHeSo, mực nước, lực căng, hoặc dung sai cân phải được kiểm chứng bằng Golden Master Test trước khi merge — xem `.claude/testing-strategy.md` mục 2.2. Sai số cho phép định mức bột màu là `0.0`, sai số trọng lượng cân là `±0.000001`.
