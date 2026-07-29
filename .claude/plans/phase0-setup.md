# Phase 0 + 1 - Khởi tạo Khung Dự án và Cơ sở Dữ liệu

> Tương ứng Phase 0 (Khảo sát và `.claude`) và Phase 1 (Nền tảng dự án) trong `.claude/development-roadmap.md`. Cả hai Phase đã **hoàn thành**; tài liệu này ghi lại nội dung đã thực hiện để làm chuẩn tham chiếu khi cần dựng lại môi trường (máy dev mới, disaster recovery) hoặc onboard thành viên/agent mới.

---

## 1. Mục tiêu Phase
1. Đồng thuận thuật ngữ nghiệp vụ, xác định cấu trúc dữ liệu bị lỗi (lệch cột), xác minh quy trình VBA/Access hiện tại.
2. Phê duyệt chính thức stack công nghệ.
3. Dựng khung mã nguồn (scaffold) độc lập cho 3 phân hệ: Backend, Frontend, Local Agent.
4. Khởi tạo hạ tầng Database PostgreSQL cô lập hoàn toàn với mạng sản xuất thực tế.

## 2. Kết quả đã đạt (Definition of Done)

### 2.1. Bối cảnh & tri thức (`.claude/`)
- Khởi tạo thư mục `.claude/` làm nguồn sự thật kỹ thuật, ban đầu 16 file markdown bao gồm: `project-overview.md`, `business-modules.md`, `architecture-decisions.md`, `source-traceability.md`, `coding-standards.md`, `security-rules.md`, `development-roadmap.md`...
- Khóa phạm vi MVP và ngoài phạm vi (xem `.claude/project-overview.md` mục 4-5, `.claude/rules/project-scope.md`).

### 2.2. Stack công nghệ (đã phê duyệt — Phương án 1 trong `coding-standards.md`)
| Thành phần | Công nghệ | Ghi chú |
|---|---|---|
| Backend | Laravel 12 (PHP 8.2+) | Eloquent ORM, Sanctum cho token |
| Frontend | Vue 3 + Vite 5 | Composition API + Pinia, cổng dev `3001` |
| Local Agent | .NET 8 Worker Service (C#) | Đọc Serial Port, in TSPL |
| Database | PostgreSQL 15 (Docker) | Cổng `5433`, tránh đụng Postgres bản địa `5432` |

### 2.3. Scaffold mã nguồn
```
DF/ (hoặc DFwed/)
├── .claude/                # Tri thức dự án (tài liệu này)
├── sql_migration/          # SQL di trú Access -> Postgres (KHÔNG XÓA)
├── df-backend/             # Laravel 12
│   ├── app/                # Services, Models, Controllers, DTO/FormRequest
│   ├── database/           # Migrations, Seeders
│   └── tests/               # Pest/PHPUnit
├── df-frontend/            # Vue 3 + Vite
│   └── src/
│       ├── components/, views/, store/ (Pinia)
└── df-local-agent/         # .NET 8 Worker Service
    └── src/                 # SerialPort reader, TSPL print spooler
```

### 2.4. Hạ tầng Database
- Docker Compose chạy PostgreSQL 15 tại cổng `5433`.
- Tạo 3 schema: `app` (chuẩn hóa, ghi bởi Laravel migrations), `legacy_df_data`, `legacy_df_scale` (staging, chờ import ở Phase 2).
- Health check endpoint xác nhận Backend kết nối DB thành công.
- Môi trường dev/test cô lập hoàn toàn với mạng sản xuất thực (theo `CLAUDE.md` mục 3).

## 3. Rủi ro đã ghi nhận từ Phase này
- Chất lượng khóa chính (ID) trong Access rất kém → dẫn tới ADR-004 (UUID + trường truy vết `legacy_id`/`legacy_row_no`).
- Lỗi lệch cột đã phát hiện sớm ở bảng `tbl_ToSend2` và `WAITING` — xử lý chi tiết dời sang Phase 2, xem [phase1-core-features.md](phase1-core-features.md) mục Database & Migration.

## 4. Checklist tái lập môi trường (dùng khi cần dựng lại máy dev)
- [ ] Clone repo, cài PHP 8.2+, Composer, Node 18+, .NET 8 SDK.
- [ ] `docker compose up -d` để khởi động PostgreSQL 15 cổng `5433`.
- [ ] `cp .env.example .env` cho `df-backend`, cấu hình `DB_PORT=5433`, chạy `php artisan migrate`.
- [ ] `npm install && npm run dev` trong `df-frontend` (cổng `3001`).
- [ ] Xác nhận 3 schema `app`/`legacy_df_data`/`legacy_df_scale` tồn tại qua `\dn` trong `psql`.
- [ ] Không kết nối bất kỳ bước nào ở trên tới database Production hoặc mạng xưởng thực tế.

## 5. Tài liệu liên quan
- Quyết định kiến trúc gốc: `.claude/architecture-decisions.md` (ADR-001, ADR-004, ADR-005).
- Quy ước đặt tên & cấu trúc thư mục đầy đủ: `.claude/coding-standards.md`, `.claude/rules/coding-standards.md`.
- An toàn dữ liệu: `.claude/rules/database-safety.md`.
