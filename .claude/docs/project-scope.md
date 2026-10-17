# Project Scope - Phạm vi Dự án DF

> Đọc khi nhận một yêu cầu mới và cần xét nó có thuộc phạm vi không. Trích từ `.claude/project-overview.md` mục 4-5.

**Một câu:** DF là web app thay 12 workbook Excel VBA + các DB Access của xưởng nhuộm — cân thuốc/hóa chất, in tem QR, điều phối cấp máy — đứng giữa MES và hệ pha màu tự động, chạy song song với VBA cũ cho tới khi nghiệm thu.

---

## 1. Trong phạm vi (In Scope) — 12 Phân hệ MVP

| Mã | Phân hệ | Tóm tắt phạm vi |
|---|---|---|
| M01 | Master Data (Danh mục) | Máy nhuộm, bồn/rack, mã hàng, mã màu, thuốc nhuộm, hóa chất, phụ gia, cân, máy in |
| M02 | Formula & Technology (Công thức) | Số hóa TraHeSo, mực nước, lực căng, định lượng theo phiên bản có phê duyệt |
| M03 | Production Orders (Lệnh & Điều phối) | Hàng chờ sản xuất, khóa logic chống trùng, gửi lệnh máy nhuộm |
| M04 | Dye Weighing (Cân thuốc nhuộm) | Cân thời gian thực qua Agent, validate dung sai, override, lưu vết session |
| M05 | Automatic Chemical Dosing (Cấp hóa chất tự động) | Kích hoạt lệnh cấp hóa chất phụ trợ qua hệ pha màu tự động (Copower/Lawer) |
| M06 | Semi Checker (Kiểm tra bán thành phẩm) | Xác nhận chất lượng bán thành phẩm trước chuyển công đoạn |
| M07 | Dispatch/Feeding | Điều phối và cấp vào máy nhuộm, kiểm soát điều kiện đủ nước/nguyên liệu |
| M08 | Material Transfer (Vận chuyển) | Theo dõi nguyên liệu từ cân tới thùng 1A/2B, SLA và cảnh báo trễ |
| M09 | QR & Label Printing (Tem/QR) | Sinh QR nội bộ, in tem TSPL qua máy in TSC, reprint có audit |
| M10 | Troubleshooting | Bộ tri thức chẩn đoán sự cố, engine suy luận điểm số nguyên nhân |
| M11 | Reports (Báo cáo) | Tiêu hao, dung sai/override, sản lượng, Pareto sự cố, xuất Excel/PDF |
| M12 | Administration (Quản trị) | RBAC, Audit Log, quản lý tài khoản và thiết bị |

Ngoài ra, hệ thống đóng vai trò **cầu nối trung gian (Bridge)** giữa MES (nguồn dữ liệu màu sắc/mã hàng chuẩn) và Hệ thống Nhuộm/Pha màu tự động — xem ADR-006.

Di trú dữ liệu trong phạm vi: `RECORD.accdb`, `RECORD(1).accdb`, và `chem_order.accdb` (bảng cấu hình kênh hóa chất `tbl_status`).

## 2. Ngoài phạm vi Giai đoạn 1 (Out of Scope)
- Điều khiển trực tiếp PLC/SCADA của máy nhuộm, nếu Macro hiện tại chỉ xuất tệp hoặc gửi dữ liệu qua ứng dụng trung gian — **không mở rộng** thành điều khiển phần cứng trực tiếp.
- Tự động thay đổi quy trình sản xuất vật lý của nhà máy mà chưa có văn bản đồng ý của ban giám đốc.
- Xóa bỏ hoặc tắt các Macro Excel cũ trước khi giai đoạn chạy song song (Phase 12) đạt tiêu chuẩn nghiệm thu chính thức (biên bản UAT).
- Bất kỳ tính năng nào gọi dịch vụ sinh QR hoặc xử lý dữ liệu công thức qua bên thứ ba trên Internet (vi phạm ADR-003).

## 3. Do's & Don'ts riêng của phạm vi
- **Do:** port đúng hành vi VBA gốc trước, cải tiến sau — mọi khác biệt so với workbook phải ghi rõ lý do (xem `source-traceability.md`).
- **Do:** giữ song song đường cũ (PuTTY log, workbook) khi thêm đường mới cho xưởng, để UAT so được hai bên.
- **Don't:** "dọn" hay tắt một chức năng VBA vì trông lỗi thời — ADR-012 từng phải đảo ngược một quyết định DEPRECATED vì hệ pha màu không có API.
- **Don't:** tự đóng blocker nghiệp vụ (`CH-BUS-*`) bằng suy đoán — chỉ đóng bằng bằng chứng mã nguồn hoặc xác nhận văn bản (xem `decision-records.md`).

## 4. Vùng xám — phải hỏi trước khi làm
| Tình huống | Hỏi ai / ghi ở đâu |
|---|---|
| Yêu cầu không khớp phân hệ nào ở mục 1 | Người dùng: scope change hay hiểu nhầm phân hệ? Ghi `open-questions.md` hoặc ADR mới (qua `system-architect`) |
| Thêm tính năng mới (không phải sửa lỗi UAT) | Người dùng — có thể dời lịch Cutover (Phase 13) |
| Agent làm thêm việc với phần cứng/phần mềm khác trên máy trạm (RPA, đọc cửa sổ, cổng COM) | ADR mới; tham chiếu ADR-012/013 |
| Quy tắc nghiệp vụ còn mở: chia ca 3x8h (CH-BUS-003), 15L special (ADR-BUS-011), RECORD_A↔B (ADR-BUS-013), `chem_order.tblRECORD` (ADR-BUS-014) | Giữ default an toàn đã ghi trong `decision-records.md`, không tự chốt |

## 5. Người dùng & môi trường
- Operator/trưởng ca tại xưởng dùng máy trạm kiosk (Windows, có máy đời cũ — lý do có `@vitejs/plugin-legacy`), mô hình "1 máy tính = 1 công đoạn" (`workstation-redesign-audit.md`).
- Production: CS-SERVER `10.0.60.209` (xem `database-safety.md` mục 7). Giai đoạn hiện tại: `.claude/CLAUDE.md` mục 7 — Phase 12, công việc chính là **sửa lỗi UAT**.
