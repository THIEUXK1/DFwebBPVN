# 00 - Master Plan: Chuyển đổi Hệ thống Sản xuất DF

> Tài liệu này là bản tóm lược điều hướng (index) của kế hoạch dự án. Nguồn chi tiết đầy đủ và có trạng thái cập nhật realtime nằm tại `.claude/development-roadmap.md` (14 Phase) — file này không lặp lại toàn bộ nội dung đó mà đóng vai trò bản đồ giúp agent/dev định vị nhanh mình đang ở đâu.

---

## 1. Mục tiêu tối thượng
Thay thế hoàn toàn 12 workbook Excel VBA + 2 database Microsoft Access (`RECORD.accdb`, `RECORD(1).accdb`) bằng hệ thống Web tập trung (Laravel 12 + Vue 3 + PostgreSQL 15), đóng vai trò cầu nối trung gian (Bridge) giữa hệ thống MES và hệ thống Nhuộm/Pha màu tự động — trong khi bảo toàn 100% dữ liệu lịch sử và quy tắc nghiệp vụ đang chạy tốt. Chi tiết bối cảnh và phạm vi: `.claude/project-overview.md`, `.claude/rules/project-scope.md`.

## 2. Stack kỹ thuật đã CHỐT (không phải đề xuất)
- **Backend:** Laravel 12 (PHP 8.2+), Eloquent, Laravel Sanctum (JWT/token), Laravel Queue.
- **Frontend:** Vue 3 (Composition API + Pinia), Vite 5, chạy dev cổng `3001`.
- **Database:** PostgreSQL 15 qua Docker Compose, cổng `5433` (tránh xung đột Postgres bản địa `5432`). Schema: `legacy_df_data`, `legacy_df_scale` (staging read-only), `app` (chuẩn hóa).
- **Local Device Agent:** .NET 8 Worker Service (C#) — đọc cân qua Serial Port, in tem TSPL qua TSC TE200, offline buffer bằng SQLite cục bộ.
- **Realtime:** Server-Sent Events (SSE) + Transactional Outbox Pattern (ADR-008, ADR-009) — không dùng WebSocket server riêng.

## 3. Cấu trúc kế hoạch theo giai đoạn (mapping với 14-Phase Roadmap)
| Plan file trong `.claude/plans/` | Tương ứng Phase trong `development-roadmap.md` | Trạng thái |
|---|---|---|
| [phase0-setup.md](phase0-setup.md) | Phase 0 (Khảo sát & `.claude`) + Phase 1 (Nền tảng dự án) | Đã hoàn thành |
| [phase1-core-features.md](phase1-core-features.md) | Phase 2 → Phase 11 (Database, Auth/RBAC, Danh mục & Công thức, Lệnh & Điều phối, Cân, QR/In tem, Vận chuyển, Cấp máy, Troubleshooting, Báo cáo) | Đã hoàn thành |
| *(chưa tạo — dùng trực tiếp roadmap)* | Phase 12 (UAT & Chạy song song) | **Đang chạy — giai đoạn hiện tại** |
| *(chưa tạo — dùng trực tiếp roadmap)* | Phase 13 (Cutover & Go-Live) | Chưa bắt đầu |

> Quy ước đặt tên: `phaseN-*.md` trong thư mục này nhóm các Phase kỹ thuật liền kề theo **chủ đề** (setup hạ tầng vs. phát triển tính năng nghiệp vụ), không nhất thiết ánh xạ 1-1 theo số thứ tự Phase của roadmap gốc. Khi tạo kế hoạch cho Phase 12/13, đặt tên `phase2-uat-pilot.md` và `phase3-cutover.md` để giữ mạch đánh số.

## 4. Nguyên tắc áp dụng plan
- Trước khi thực hiện bất kỳ công việc nào thuộc một Phase, đọc plan file tương ứng trong thư mục này **và** mục Phase gốc trong `development-roadmap.md` để lấy trạng thái/exit-criteria mới nhất — plan file có thể "đông cứng" ở thời điểm viết trong khi roadmap được cập nhật liên tục.
- Mọi thay đổi kiến trúc trong lúc lập kế hoạch phải tuân theo vai trò [[system-architect]] (`.claude/agents/system-architect.md`).
- Mọi thao tác động tới dữ liệu trong lúc lập kế hoạch di trú phải tuân theo vai trò [[database-auditor]] (`.claude/agents/database-auditor.md`) và [[database-safety]] (`.claude/rules/database-safety.md`).
- Không tạo plan cho Phase 13 (Cutover) chi tiết tới mức thực thi trước khi Phase 12 (UAT) có biên bản nghiệm thu ký chính thức.

## 5. Liên kết tài liệu tham chiếu bắt buộc
- Miền nghiệp vụ: `.claude/business-modules.md`
- Ma trận nguồn VBA/Access: `.claude/source-traceability.md`
- Quyết định kiến trúc: `.claude/architecture-decisions.md`
- Nhật ký phiên làm việc: `.claude/session-log.md`
- Câu hỏi mở / rủi ro nghiệp vụ chưa chốt: `.claude/open-questions.md`
