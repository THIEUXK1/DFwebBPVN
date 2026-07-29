# Project Scope - Phạm vi Dự án DF

> Trích lọc và chuẩn hóa từ `.claude/project-overview.md` mục 4-5, dùng làm tiêu chí nhanh để đánh giá một yêu cầu mới có nằm trong phạm vi MVP hay không trước khi bắt tay code.

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

## 3. Quy tắc khi nhận yêu cầu mới nằm ngoài danh sách 12 phân hệ trên
1. Không tự triển khai ngay — xác nhận với người dùng đây là mở rộng phạm vi (scope change) hay thuộc phân hệ hiện có bị hiểu nhầm.
2. Nếu là mở rộng phạm vi thật sự, đề xuất ghi nhận vào `.claude/open-questions.md` hoặc tạo ADR mới (phối hợp vai trò [[system-architect]]) trước khi code.
3. Không tự ý mở rộng phạm vi điều khiển phần cứng (PLC/SCADA) hoặc tích hợp bên thứ ba dưới bất kỳ lý do "tiện lợi" nào.

## 4. Giai đoạn hiện tại và ảnh hưởng tới phạm vi làm việc
Dự án đang ở **Phase 12 – UAT & Chạy song song** (xem `.claude/CLAUDE.md` mục 7). Điều này có nghĩa:
- 12 phân hệ MVP nêu trên đã code xong (Phase 0-11) — công việc hiện tại là **sửa lỗi phát sinh từ UAT thực tế**, không phải phát triển tính năng mới trừ khi có yêu cầu rõ ràng.
- Trước khi thêm tính năng mới ngoài việc sửa lỗi UAT, xác nhận với người dùng vì có thể ảnh hưởng tới lịch trình Cutover (Phase 13).
- Vấn đề nghiệp vụ chưa chốt cần xác nhận trước khi UAT hoàn tất: quy tắc chia ca 3x8h cho báo cáo sản lượng (`open-questions.md` mục CH-BUS-003).
