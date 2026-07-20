# Coding Standards - Quy chuẩn Lập trình

Tài liệu này định nghĩa quy chuẩn viết code, tổ chức cấu trúc thư mục, đề xuất công nghệ và quy ước đặt tên cho dự án DF.

---

## 1. Đề xuất Công nghệ (Stack)

Chúng tôi đề xuất 2 phương án Stack công nghệ chính để phát triển hệ thống và so sánh:

### Phương án 1 (Ưu tiên - Đề xuất phê duyệt): Laravel + PostgreSQL + Vue.js
- **Backend:** Laravel 10+ (PHP 8.2+). Sử dụng Eloquent ORM, Laravel Queue (Database/Redis) cho việc gửi lệnh máy nhuộm và in tem bất đồng bộ.
- **Frontend:** Vue.js 3 (Sử dụng Composition API và Pinia quản lý trạng thái) hoặc React. Giao diện tối ưu Wide Layout, sử dụng Vanilla CSS hoặc TailwindCSS.
- **Local Agent:** Windows Service viết bằng .NET 8 (C#) để dễ tích hợp WinAPI điều khiển cổng serial và driver in TSC thô.
- **Ưu điểm:** Tốc độ phát triển cực nhanh, có sẵn hệ thống phân quyền (Spatie Permission), validation và migration cơ sở dữ liệu mạnh mẽ. Phù hợp với năng lực bảo trì của các đội IT nội bộ doanh nghiệp.

### Phương án 2 (Thay thế): NestJS + PostgreSQL + React
- **Backend:** NestJS (TypeScript, Node.js). Sử dụng TypeORM hoặc Prisma.
- **Frontend:** React + Vite.
- **Local Agent:** Windows Service viết bằng Go hoặc .NET 8.
- **Ưu điểm:** TypeScript đồng bộ từ frontend sang backend, NestJS tích hợp WebSocket (Socket.io) cực mạnh mẽ giúp cập nhật số cân thời gian thực mượt mà từ Agent lên giao diện Web.

> [!IMPORTANT]
> **Đánh dấu:** Cả hai phương án stack công nghệ này đều cần được **Người dùng phê duyệt chính thức** trong Phase 0 trước khi khởi tạo kho mã nguồn sản xuất.

---

## 2. Quy ước Đặt tên (Naming Conventions)

### 2.1. Cơ sở Dữ liệu (PostgreSQL)
- **Tên bảng:** Viết thường dạng `snake_case`, sử dụng danh từ số nhiều (ví dụ: `app.users`, `app.production_batches`, `app.scale_measurements`).
- **Tên cột:** Viết thường dạng `snake_case` (ví dụ: `legacy_batch_id`, `product_code`, `measured_at`).
- **Khóa ngoại:** Tên bảng đích số ít + `_id` (ví dụ: `machine_id`, `batch_id`).
- **Khóa chính:** Luôn đặt tên cột là `id`.

### 2.2. Mã nguồn Backend & Frontend
- **Tên lớp (Classes/Controllers/Services):** Viết hoa chữ cái đầu dạng `PascalCase` (ví dụ: `FormulaService`, `DispatchController`, `WeighingReadingDto`).
- **Tên hàm (Methods/Functions):** Viết thường chữ cái đầu dạng `camelCase` (ví dụ: `calculateCoefficients()`, `confirmWeighingSession()`).
- **Tên biến:** Dạng `camelCase` (ví dụ: `targetWeight`, `toleranceLimit`).
- **Endpoint API:** RESTful, sử dụng danh từ số nhiều viết thường dạng `kebab-case` (ví dụ: `GET /api/production-orders`, `POST /api/formula-versions/{id}/approve`).

---

## 3. Cấu trúc Thư mục Đề xuất (Folder Structure)

Cấu trúc dự án phân tách rõ ràng 3 phân hệ chính:
```
F:\DF\
├── .claude\                   # Tài liệu tri thức dự án
├── sql_migration\             # Các tệp SQL di trú dữ liệu Access -> Postgres
├── df-backend\                # Mã nguồn Backend API (Laravel/NestJS)
│   ├── app\                   # Services, Models, Controllers, DTOs
│   ├── database\              # Migrations, Seeds
│   └── tests\                 # Unit & Integration Tests
├── df-frontend\               # Giao diện Web (Vue/React)
│   ├── src\
│   │   ├── components\        # Các component tái sử dụng (Bảng cân, Trạm in...)
│   │   ├── views\             # Các trang nghiệp vụ chính
│   │   └── store\             # Quản lý state tập trung
└── df-local-agent\            # Ứng dụng Agent cục bộ tại máy trạm
    └── src\                   # Logic đọc Serial Port và spool lệnh in TSC
```

---

## 4. Quy tắc Lập trình An toàn và Hiệu năng

### 4.1. Sử dụng Database Transaction
Mọi hoạt động cập nhật dữ liệu liên quan đến nhiều bảng hoặc thay đổi trạng thái nghiệp vụ (State transitions) bắt buộc phải được bọc trong một Database Transaction:
```php
// Ví dụ trong Laravel
DB::transaction(function () use ($dispatchId) {
    $dispatch = MachineDispatch::lockForUpdate()->find($dispatchId); // Pessimistic Lock
    // 1. Kiểm tra trạng thái hợp lệ
    // 2. Tạo Send Job gửi máy bất đồng bộ
    // 3. Cập nhật trạng thái
    // 4. Giải phóng khóa logic
});
```

### 4.2. DTO và Input Validation
- Tuyệt đối không nhận dữ liệu trực tiếp từ client (`Request::all()`) mà không qua bộ lọc.
- Sử dụng các lớp DTO (Data Transfer Object) hoặc FormRequest để định nghĩa chính xác kiểu dữ liệu, bắt buộc kiểm tra trường rỗng, độ dài chuỗi và giới hạn số thập phân trước khi chuyển tiếp vào Business Service.

### 4.3. Quản lý Lỗi (Error Handling)
- Viết bộ xử lý lỗi tập trung (Global Exception Handler) để bắt mọi lỗi phát sinh ở tầng Controller/API.
- Không bao giờ trả về lỗi thô của hệ thống (như stacktrace của Database, NullPointerException) cho giao diện Web. Thay vào đó, ghi log chi tiết lỗi kèm theo **Correlation ID** trên server, và trả về cho client thông báo lỗi thân thiện kèm mã lỗi đó để phục vụ tra cứu.
