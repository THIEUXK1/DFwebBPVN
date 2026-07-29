# Phase "Core Features" - Phát triển các Tính năng Cốt lõi

> Tương ứng Phase 2 → Phase 11 trong `.claude/development-roadmap.md`. Toàn bộ đã **hoàn thành** tính đến 16/07/2026. Tài liệu này gộp các Phase kỹ thuật liền kề thành một bản kế hoạch "core features" duy nhất để agent mới có thể nắm nhanh toàn bộ phạm vi tính năng nghiệp vụ đã xây dựng, thay vì phải đọc rời rạc 10 mục trong roadmap.

---

## 1. Mục tiêu Phase
Số hóa toàn bộ 12 phân hệ nghiệp vụ (`.claude/business-modules.md`, M01-M12) từ Excel VBA/Access sang Laravel + Vue + PostgreSQL, đảm bảo mọi kết quả tính toán khớp Golden Master với sai số cân ±0.000001.

## 2. Bảng tổng hợp tính năng theo phân hệ

| # | Phân hệ (module) | Phase gốc | Nội dung chính đã xây dựng | Exit criteria |
|---|---|---|---|---|
| 1 | Database & Migration | Phase 2 | Sửa lệch cột `WAITING`/`tbl_ToSend2` bằng ánh xạ thủ công, import 145.721 dòng lịch sử cân dyes/chems vào schema `app` qua staging | Đối soát khớp 100% |
| 2 | Auth / RBAC / Audit | Phase 3 | Laravel Sanctum, BCrypt, 7 vai trò RBAC (Operator, Shift Leader, Technologist, Approver/QA, Dispatcher, Auditor, System Admin), `app.audit_logs` JSONB append-only | Đăng nhập + audit log hoạt động |
| 3 | Danh mục & Công thức (M01, M02) | Phase 4 | Máy nhuộm VD01-VD18, thùng 1A/2B, thuốc nhuộm/hóa chất, `recipe`/`recipe_version` có version, workflow duyệt `draft→submitted→approved→obsolete` | Golden Master 50 mẻ mẫu khớp 100% |
| 4 | Lệnh & Điều phối (M03) | Phase 5 | `production_order`, `production_batch`, `dispatch_order`, `machine_queue`, claim/lock có thời hạn (owner, version) | Auto-unlock hết hạn + audit log force unlock |
| 5 | Cân sản xuất & Scale Agent (M04) | Phase 6 | `weighing_task`/`weighing_measurement`, Local Agent .NET đọc Serial Port, Stable Filter, tolerance check, override bắt buộc lý do + phê duyệt giám sát, offline buffer SQLite | Chặn vượt dung sai không override, đồng bộ offline thành công |
| 6 | Tem & QR Code (M09) | Phase 7 | QR sinh nội bộ (không gọi bên thứ ba — ADR-003), template TSPL, Print Agent C#, reprint có audit log | QR quét đúng ID lô, chặn in trùng qua idempotency key |
| 7 | Vận chuyển (Material Transfer) | Phase 8 | `material_transfer`/`material_transfer_event`, SLA theo loại nguyên liệu/line, cảnh báo trễ hạn | Cảnh báo đúng SLA, xác nhận arrived đổi trạng thái |
| 8 | Cấp máy (Feeding) | Phase 9 | `feed_readiness_check`/`feed_operation`, chặn cấp khi thiếu nguyên liệu/nước sai cấu hình, override có phê duyệt | Chặn cấp máy thiếu điều kiện khi không override |
| 9 | Troubleshooting (M-troubleshoot) | Phase 10 | `problem`/`cause`/`problem_cause_rule`/`troubleshooting_case`, `InferenceService` suy luận điểm số nguyên nhân | Điểm số khớp 100% logic bảng ENGINE VBA cũ |
| 10 | Báo cáo & Audit Log Explorer | Phase 11 | 4 báo cáo (tiêu hao, dung sai/override, sản lượng ngày/tháng/ca, Pareto sự cố), xuất Excel/PDF, `ReportController`, `Audit Log Explorer` | Query < 2s dưới tải lớn, số liệu khớp giao dịch thực tế |

## 3. Quy tắc xuyên suốt áp dụng cho mọi phân hệ trên
- **Idempotency bắt buộc** cho mọi API nhận dữ liệu từ Local Agent (cân, in tem).
- **Audit Log JSONB bất biến** cho: duyệt công thức, override dung sai, reprint, force unlock, thay đổi Troubleshooting KB (xem `.claude/rules/database-safety.md` mục Audit Log).
- **Không sửa công thức đang dùng trực tiếp** — luôn tạo phiên bản mới (`recipe_version`).
- **Test bắt buộc theo từng phân hệ:** Golden Master Test (công thức), Concurrency Claim Lock (điều phối), Scale stability + tolerance + offline sync (cân), TSPL/QR readability (in tem), SLA timer (vận chuyển), Feed readiness rule (cấp máy), Troubleshooting regression (suy luận sự cố), Query explain plan + Excel export layout (báo cáo).

## 4. Nợ kỹ thuật / vấn đề đã phát hiện và xử lý trong giai đoạn này
- **Lỗ hổng override dung sai không lưu vết** (`WeighingJobController::weighItem`) — đã vá: thêm cột `override_approved/override_reason/override_by`, bắt buộc vai trò SUPERVISOR/ADMIN, ghi Audit Log `WEIGH_TOLERANCE_OVERRIDE`.
- **Sự cố đăng nhập 500** do thiếu bảng `personal_access_tokens` (lệch kiểu UUID/bigint, từng bị xóa tay ngoài migration) — đã khắc phục bằng migration phục hồi idempotent, verify bằng luồng đăng nhập HTTP thật, bổ sung `AuthenticationFlowTest.php`.
- **Giả định kỹ thuật chưa xác nhận nghiệp vụ:** quy tắc chia ca 3x8h dùng cho báo cáo sản lượng — xem `open-questions.md` mục CH-BUS-003, cần xác nhận trước UAT chính thức.

## 5. Việc KHÔNG nằm trong phạm vi Phase này
- UAT thực tế tại xưởng pilot và chạy song song với Excel VBA — thuộc Phase 12 (xem `.claude/development-roadmap.md`).
- Cutover / Go-live chính thức — thuộc Phase 13.

## 6. Tài liệu liên quan
- Chi tiết đầy đủ từng Phase: `.claude/development-roadmap.md` (mục "Chi tiết các Phase").
- Nhật ký thực thi theo từng phiên: `.claude/session-log.md`.
- Kiến trúc & ADR liên quan: `.claude/architecture-decisions.md`.
