# BIÊN BẢN BÀN GIAO HỆ THỐNG
## Hệ thống DF Connector — Cầu nối MES ↔ Nhuộm & Pha màu tự động

**Công ty TNHH Best Pacific Việt Nam / 超盈纺织（越南）有限公司**

---

Hôm nay, ngày **12** tháng **08** năm **2026**, tại **Phân xưởng nhuộm DF — Công ty TNHH Best Pacific Việt Nam**, chúng tôi gồm:

### BÊN BÀN GIAO (Bên A)

| Mục | Nội dung |
|---|---|
| Họ và tên | **Bùi Văn Thiều** |
| Chức vụ | Nhân viên |
| Bộ phận | IT |

### BÊN NHẬN BÀN GIAO (Bên B)

| Mục | Nội dung |
|---|---|
| Họ và tên | …………………………………………… |
| Chức vụ | …………………………………………… |
| Bộ phận | …………………………………………… |

### BÊN CHỨNG KIẾN (nếu có)

| Mục | Nội dung |
|---|---|
| Họ và tên | …………………………………………… |
| Chức vụ / Bộ phận | …………………………………………… |

Cùng tiến hành bàn giao hệ thống với các nội dung sau:

---

## I. THÔNG TIN CHUNG VỀ HỆ THỐNG

| Mục | Nội dung |
|---|---|
| **Tên hệ thống** | DF Connector |
| **Mục đích** | Chuyển đổi bộ công cụ sản xuất phân tán trên Excel VBA + Microsoft Access sang ứng dụng Web tập trung; đóng vai trò cầu nối giữa hệ thống MES và hệ thống Nhuộm/Pha màu tự động |
| **Phạm vi áp dụng** | Phân xưởng nhuộm DF |
| **Kiến trúc** | Web (trình duyệt) → Backend API → PostgreSQL; thiết bị (cân, máy in tem) đi qua **Local Device Agent** cài trên từng máy trạm |
| **Công nghệ** | Backend: Laravel (PHP) · Frontend: Vue 3 + Vite + TypeScript · Agent: .NET 8 (Windows Service) · CSDL: PostgreSQL 15+ |
| **Ngôn ngữ giao diện** | Tiếng Việt · English · 中文 |
| **Giai đoạn hiện tại** | Phase 12 — UAT & Chạy song song (Parallel Run Pilot) |

---

## II. DANH MỤC HẠNG MỤC BÀN GIAO

### II.1. Mã nguồn và tài liệu

| # | Hạng mục | Mô tả / Quy mô | Vị trí | Tình trạng | Bên B xác nhận |
|---|---|---|---|---|---|
| 1 | **Kho mã nguồn Git** | Toàn bộ mã nguồn hệ thống, nhánh `main` | `https://github.com/THIEUXK1/DFwebBPVN.git` | ☐ Đã chuyển quyền sở hữu <br> ☐ Chưa chuyển | ☐ |
| 2 | **Mã nguồn Backend** | Laravel — 34 Controller, 64 Model, 52 Migration, 5 lệnh đồng bộ (Console Command) | `backend/` | ☐ Đầy đủ | ☐ |
| 3 | **Mã nguồn Frontend** | Vue 3 — 41 màn hình, đa ngôn ngữ VI/EN/ZH | `frontend/` | ☐ Đầy đủ | ☐ |
| 4 | **Mã nguồn Local Agent** | DFAgent (.NET) — đọc cân serial/putty log, in tem TSC, hàng đợi offline, khay hệ thống | `agent/` | ☐ Đầy đủ | ☐ |
| 5 | **Bộ cài Agent (MSI)** | 3 bộ: `DFAgentSetup-CanNho.msi`, `DFAgentSetup-CanTo.msi`, `DFAgentSetup-CanTo-InOut.msi` | `agent/installer/` | ☐ Đầy đủ | ☐ |
| 6 | **Script di trú CSDL** | Import staging từ Access + schema chuẩn hóa + transform + truy vấn kiểm tra | `sql_migration/` | ☐ Đầy đủ | ☐ |
| 7 | **Bộ test tự động** | 37 file test (PHPUnit) | `backend/tests/` | ☐ Đã chạy đạt <br> ☐ Chưa xác nhận | ☐ |
| 8 | **Tài liệu kiến trúc & thiết kế** | ~50 tài liệu nội bộ: kiến trúc, mô hình dữ liệu, ma trận nguồn VBA, lộ trình, nhật ký phiên | `.claude/` | ☐ Đầy đủ | ☐ |
| 9 | **Hướng dẫn sử dụng cho vận hành** | Tài liệu này kèm theo | `docs/huong-dan-van-hanh.md` | ☐ Đã nhận | ☐ |
| 10 | **Nội dung đào tạo** | Giáo trình đào tạo vận hành (biểu mẫu BPVN-HR-PR-030) | `docs/noi-dung-dao-tao.md` | ☐ Đã nhận | ☐ |
| 10b | **Tài liệu kỹ thuật triển khai & vận hành** | Dành cho IT tiếp quản: kiến trúc, cổng dịch vụ, deploy, sao lưu — khôi phục, Agent, xử lý sự cố, dựng lại môi trường | `docs/tai-lieu-ky-thuat.md` | ☐ Đã nhận | ☐ |

### II.2. Dữ liệu gốc và cơ sở dữ liệu

| # | Hạng mục | Mô tả | Tình trạng | Bên B xác nhận |
|---|---|---|---|---|
| 11 | **Database PostgreSQL vận hành** | `production_web` trên máy chủ CS-SERVER | ☐ Đã bàn giao truy cập | ☐ |
| 12 | **Database Access gốc** | `RECORD.accdb`, `RECORD1.accdb`, `WH.accdb`, `DF_STORAGE.accdb`, `chem_order.accdb` | ☐ Đã bàn giao (giữ nguyên trạng, không được xóa) | ☐ |
| 13 | **Workbook Excel VBA gốc** | 12+ file `.xlsm` nguồn của hệ thống cũ | ☐ Đã bàn giao (giữ nguyên trạng) | ☐ |
| 14 | **Bản sao lưu CSDL** | Bản backup tự động gần nhất trong `C:\web\tools\backups` (task `DFWeb-Backup` chạy 02:00 hằng ngày, giữ 14 ngày + mỗi mùng 1 giữ 6 tháng) | Bên B kiểm tra bằng `tools\db-backup-verify.ps1` | ☐ |

### II.3. Công cụ vận hành

| # | Hạng mục | Tệp | Chức năng | Bên B xác nhận |
|---|---|---|---|---|
| 15 | Sao lưu CSDL tự động | `tools/db-backup.ps1`, `db-backup.bat`, `register-db-backup-task.ps1` | Backup định kỳ qua Scheduled Task | ☐ |
| 16 | Kiểm tra bản sao lưu | `tools/db-backup-verify.ps1` | Xác minh bản backup dùng được | ☐ |
| 17 | Đồng bộ MES | `tools/mes-sync-batch.bat`, `register-mes-sync-task.ps1` | Đồng bộ giờ kết thúc mẻ & bảng tra màu từ MES | ☐ |
| 18 | Đồng bộ BPDB | `tools/bpdb-sync.bat` | Đồng bộ dữ liệu Color Service (BPDB) | ☐ |

### II.4. Thông tin truy cập (bàn giao bằng phụ lục riêng, niêm phong)

> ⚠️ **Không ghi mật khẩu vào biên bản này.** Toàn bộ thông tin đăng nhập được lập thành **Phụ lục A — Bảng thông tin truy cập**, bàn giao trực tiếp trong phong bì niêm phong và **phải đổi mật khẩu ngay sau khi nhận**.

| # | Loại tài khoản | Hệ thống | Bên B xác nhận đã nhận | Đã đổi mật khẩu |
|---|---|---|---|---|
| 19 | Quản trị ứng dụng (`admin`) | DF Connector Web | ☐ | ☐ |
| 20 | Vận hành cân nhỏ (`cannho`) | DF Connector Web | ☐ | ☐ |
| 21 | Vận hành cân to (`canto`) | DF Connector Web | ☐ | ☐ |
| 22 | Tài khoản PostgreSQL | CSDL `production_web` | ☐ | ☐ |
| 23 | Tài khoản đọc BPDB (`bpdb_monitor_readonly`) | SQL Server BPDB | ☐ | ☐ |
| 24 | Tài khoản đọc MES | VN-MES (Sedo Planboard) | ☐ | ☐ |
| 25 | Truy cập máy chủ (SSH) | CS-SERVER | ☐ | ☐ |
| 26 | Quyền quản trị kho mã nguồn | GitHub | ☐ | ☐ |

### II.5. Máy trạm và thiết bị đã triển khai

| # | Mã trạm | Vị trí | Công đoạn | Agent đã cài | Thiết bị kèm theo | Bên B xác nhận |
|---|---|---|---|---|---|---|
| 27 | `WS-SMALL-01` | Khu cân nhỏ | Cân < 6 kg | DFAgentSmall | Cân điện tử, máy in tem | ☐ |
| 28 | `WS-LARGE-01` | Khu cân lớn | Cân ≥ 6 kg | DFAgentLarge + bộ IN/OUT | Cân điện tử, máy in tem | ☐ |
| 29 | `WS-PRINT-01` | Khu in tem | In tem | DFAgent | Máy in TSC | ☐ |
| 30 | `WS-ORDER-01` | Khu nhập đơn | Nhập đơn | — | Đầu đọc QR | ☐ |

> Danh sách trên là các trạm chính đã triển khai. Trạm phát sinh thêm sau ngày bàn giao do Bên B tự quản lý qua màn hình **Quản lý Workstation & Tài khoản** trên hệ thống.

---

## III. NỘI DUNG ĐÃ ĐÀO TẠO CHO BÊN NHẬN

**Người giảng:** Bùi Văn Thiều — Nhân viên IT · **Ngày đào tạo:** 10/08/2026 · **Thời gian:** 14:00 – 16:00 · **Địa điểm:** Phòng đào tạo

Toàn bộ 9 nội dung dưới đây được đào tạo trong cùng một buổi ngày 10/08/2026.

| # | Nội dung đào tạo | Số buổi | Ngày | Bên B xác nhận đã nắm |
|---|---|---|---|---|
| 1 | Tổng quan hệ thống, vai trò từng công đoạn | 01 | 10/08/2026 | ☐ |
| 2 | Nhập đơn sản xuất (MainForm) | 01 | 10/08/2026 | ☐ |
| 3 | In tem nhập đơn (TO_SEND) và QR PRINTER | 01 | 10/08/2026 | ☐ |
| 4 | Vận hành trạm Cân nhỏ (< 6 kg) | 01 | 10/08/2026 | ☐ |
| 5 | Vận hành trạm Cân to (≥ 6 kg) và khối SEND OVER 6 | 01 | 10/08/2026 | ☐ |
| 6 | Tra cứu Lịch sử cân, in lại phiếu, phát hiện cân trùng | 01 | 10/08/2026 | ☐ |
| 7 | Gọi hóa chất | 01 | 10/08/2026 | ☐ |
| 8 | Xử lý sự cố: mất tín hiệu cân, mất mạng, mẻ chờ gửi | 01 | 10/08/2026 | ☐ |
| 9 | Các điều cấm và quy tắc an toàn dữ liệu | 01 | 10/08/2026 | ☐ |

*Chi tiết ghi trong biểu mẫu **BPVN-HR-PR-030 — Biên bản đào tạo** kèm theo.*

---

## IV. TÌNH TRẠNG HỆ THỐNG TẠI THỜI ĐIỂM BÀN GIAO

### IV.1. Các phân hệ đã hoàn thành và đang vận hành

| Phân hệ | Trạng thái |
|---|---|
| Danh mục (máy nhuộm, thùng, vật tư, mã màu, mã hàng) | ✅ Đang vận hành |
| Nhập đơn sản xuất & phê duyệt | ✅ Đang vận hành |
| In tem & sinh QR nội bộ (không phụ thuộc dịch vụ Internet bên thứ ba) | ✅ Đang vận hành |
| Trạm cân nhỏ (< 6 kg) | ✅ Đang vận hành |
| Trạm cân to (≥ 6 kg) kèm khối SEND OVER 6 | ✅ Đang vận hành |
| Hàng đợi offline & tự đồng bộ khi có mạng | ✅ Đang vận hành |
| Lịch sử cân, in lại phiếu, phát hiện cân trùng | ✅ Đang vận hành |
| Gọi hóa chất (bản mới + bản giao diện cổ điển) | ✅ Đang vận hành |
| Bảng máy VD, biểu đồ Gantt tiến độ máy | ✅ Đang vận hành |
| Chẩn đoán sự cố (Troubleshooting) | ✅ Đang vận hành |
| Báo cáo & Phân tích (tiêu hao, dung sai/override, sản lượng, Pareto sự cố) | ✅ Đang vận hành |
| Audit Log Explorer | ✅ Đang vận hành |
| Phân quyền theo vai trò (RBAC) và khóa "1 máy = 1 công đoạn" | ✅ Đang vận hành |
| Tích hợp MES (đồng bộ bảng tra màu, giờ kết thúc mẻ) | ✅ Đang vận hành |
| Tích hợp BPDB / Color Service (chỉ đọc) | ✅ Đang vận hành |
| Đa ngôn ngữ VI / EN / ZH toàn bộ màn hình | ✅ Đang vận hành |

### IV.2. Các điểm còn tồn đọng — Bên B cần tiếp tục xử lý

| # | Nội dung tồn đọng | Mức độ | Đề xuất xử lý | Thời hạn |
|---|---|---|---|---|
| 1 | **Các tệp `run-backend.bat`, `run-frontend.bat`, `run-downloads.bat` chỉ tồn tại trên CS-SERVER, không có trong kho mã nguồn.** Đây là script khởi động của 3 trong 5 dịch vụ ứng dụng — máy chủ hỏng là phải viết lại từ đầu | **Cao** | Copy 3 tệp từ server vào `tools/` của repo và commit | Bên B tự thống nhất sau khi tiếp nhận |
| 2 | **Tệp `sql_migration/colorservice_handoff/create_bpdb_monitor_readonly.sql` được tham chiếu trong cấu hình nhưng không tồn tại trong kho mã nguồn** | Trung bình | Bổ sung tệp hoặc cập nhật lại chú thích cấu hình | Bên B tự thống nhất sau khi tiếp nhận |
| 3 | **Chưa có script đăng ký 5 Scheduled Task dịch vụ ứng dụng** (`DFWeb-Backend`, `DFWeb-Frontend`, `DFWeb-Reverb`, `DFWeb-Queue`, `DFWeb-Downloads`). Backup và MES-sync đã có script đăng ký, 5 task này thì chưa | Trung bình | Viết script `register-app-tasks.ps1` theo mẫu 2 script đã có | Bên B tự thống nhất sau khi tiếp nhận |
| 4 | **Tệp nhật ký kích thước lớn tồn đọng** trong `backend/` (log lớn nhất ~16 MB) | Thấp | Thiết lập xoay vòng nhật ký (log rotation) | Bên B tự thống nhất sau khi tiếp nhận |

> Bốn điểm trên **không chặn việc bàn giao**. Cách xử lý và thời hạn do Bên B chủ động quyết định sau khi tiếp nhận hệ thống. Chi tiết kỹ thuật của từng điểm xem Phần IV — Tài liệu kỹ thuật triển khai & vận hành.

### IV.2.b. Điểm đã xử lý ngay trước khi bàn giao

| # | Nội dung | Ngày xử lý | Ghi chú cho Bên B |
|---|---|---|---|
| 1 | **Gỡ bỏ tùy chọn "Sản lượng theo ca kíp"** trên màn hình Báo cáo & Phân tích | 12/08/2026 | Bản trước suy ra ca từ giờ trong ngày theo mẫu 3 ca × 8 giờ. Do xưởng chạy **24/24** và mỗi trạm cân dùng **một tài khoản chung**, con số gom theo ca không quy được cho ca nào hay người nào — giữ lại chỉ gây hiểu nhầm. Báo cáo Sản lượng nay chỉ còn **Theo ngày / Theo tháng**. **Các báo cáo "theo ca" đã xuất trước ngày 12/08/2026 không dùng để đối chiếu được.** |
| 2 | **Lập tài liệu kỹ thuật triển khai & vận hành** cho đội IT tiếp quản — `docs/tai-lieu-ky-thuat.md` | 12/08/2026 | 13 chương: kiến trúc, bảng cổng dịch vụ, 7 Scheduled Task, cấu hình `.env`, quy trình deploy, sao lưu — khôi phục, Local Agent, lệnh artisan, quy tắc an toàn dữ liệu, xử lý sự cố, dựng lại môi trường từ đầu, và các điểm cần biết trước khi sửa code. Thay cho `backend/README.md` (vẫn là bản mặc định của Laravel, không xóa để giữ nguyên gốc framework) |

### IV.3. Rủi ro cần lưu ý

| # | Rủi ro | Ảnh hưởng | Biện pháp phòng ngừa |
|---|---|---|---|
| 1 | Xóa dữ liệu trình duyệt trên máy trạm khi còn **mẻ chờ gửi** | Mất vĩnh viễn dữ liệu cân đã in phiếu | Phổ biến điều cấm cho toàn bộ người vận hành; khóa quyền xóa dữ liệu trình duyệt trên máy trạm |
| 2 | DF Agent bị tắt hoặc gỡ trên máy trạm | Toàn trạm ngừng cân/in | Cài dạng Windows Service tự khởi động; giới hạn quyền quản trị máy trạm |
| 3 | Mất kết nối tới máy chủ CSDL | Không lưu được mẻ mới lên máy chủ (dữ liệu vẫn giữ trong hàng đợi) | Giám sát mạng LAN; duy trì quy trình Excel dự phòng cho tới khi Cutover |
| 4 | Chưa Cutover — hai hệ thống chạy song song | Nguy cơ lệch số liệu giữa Web và Excel | Duy trì đối soát hằng ca; ghi biên bản mọi trường hợp lệch |
| 5 | Chỉ có một người nắm toàn bộ hệ thống | Rủi ro phụ thuộc cá nhân | Bên B bố trí tối thiểu 2 người tiếp quản; hoàn thiện tài liệu kỹ thuật (tồn đọng số 2) |

---

## V. CAM KẾT CỦA CÁC BÊN

### 5.1. Bên A (Bên bàn giao) cam kết

1. Toàn bộ hạng mục liệt kê tại Mục II là đầy đủ và đúng tình trạng mô tả tại thời điểm bàn giao.
2. Đã bàn giao đầy đủ mã nguồn, tài liệu và thông tin truy cập cần thiết để Bên B tiếp tục vận hành và phát triển.
3. Đã phổ biến, đào tạo các nội dung tại Mục III cho nhân sự do Bên B chỉ định.
4. Hỗ trợ kỹ thuật trong thời gian **01 ngày — đến hết ngày 13/08/2026** kể từ ngày ký biên bản này, với phạm vi: giải đáp vướng mắc vận hành và xử lý lỗi thuộc phạm vi hệ thống đã bàn giao. Sau thời hạn này, Bên B tự chịu trách nhiệm vận hành và bảo trì hệ thống.
5. Không giữ lại bất kỳ quyền truy cập nào vào hệ thống sau khi kết thúc thời gian hỗ trợ nêu trên.

### 5.2. Bên B (Bên nhận bàn giao) cam kết

1. Đã kiểm tra, nhận đủ các hạng mục có đánh dấu xác nhận tại Mục II.
2. **Đổi toàn bộ mật khẩu** được bàn giao ngay sau khi ký biên bản.
3. Tuân thủ các quy tắc an toàn dữ liệu của hệ thống, đặc biệt:
   - Không xóa vật lý dữ liệu giao dịch hoặc lịch sử (chỉ dùng xóa mềm và trạng thái nghiệp vụ).
   - Không chỉnh sửa trực tiếp dữ liệu thô trong schema staging (`legacy_df_data`, `legacy_df_scale`) — đây là nguồn sự thật lịch sử phục vụ đối soát.
   - Không thay đổi cấu trúc hoặc dữ liệu CSDL vận hành nếu chưa có phê duyệt và kế hoạch sao lưu cụ thể.
   - Không xóa các tệp dữ liệu gốc `.accdb` và các tệp `.sql` di trú.
4. **Không tắt hoặc xóa các Macro Excel cũ** cho tới khi giai đoạn chạy song song đạt tiêu chuẩn nghiệm thu và có biên bản chính thức.
5. Duy trì cơ chế **sao lưu định kỳ** và kiểm tra bản sao lưu theo quy trình đã bàn giao.
6. Tiếp tục xử lý các điểm tồn đọng tại Mục IV.2 theo thời hạn đã thống nhất.

---

## VI. KẾT LUẬN

Biên bản được lập thành **02 bản** có giá trị pháp lý như nhau, mỗi bên giữ **01 bản**.

Hai bên đã cùng đọc lại biên bản, thống nhất toàn bộ nội dung và ký tên xác nhận dưới đây.

Việc bàn giao được coi là **hoàn tất** khi:
- ☐ Toàn bộ hạng mục tại Mục II được Bên B đánh dấu xác nhận đã nhận;
- ☐ Bên B đã đổi toàn bộ mật khẩu tại Mục II.4;
- ☐ Các nội dung đào tạo tại Mục III đã hoàn thành và có biên bản đào tạo kèm theo;
- ☐ Các điểm tồn đọng mức độ **Cao** tại Mục IV.2 đã được thống nhất phương án xử lý.

---

*Phân xưởng DF, ngày 12 tháng 08 năm 2026*

### Người trực tiếp bàn giao — tiếp nhận

| **BÊN BÀN GIAO (Bên A)** | **BÊN NHẬN BÀN GIAO (Bên B)** |
|---|---|
| *(Ký, ghi rõ họ tên)* | *(Ký, ghi rõ họ tên)* |
| <br><br><br><br> | <br><br><br><br> |
| **Bùi Văn Thiều** | ………………………………… |

### Xác nhận của hai bộ phận

| **BỘ PHẬN BÀN GIAO** | **BỘ PHẬN TIẾP NHẬN** |
|---|---|
| Tên bộ phận: **IT** | Tên bộ phận: ………………………………… |
| *(Trưởng bộ phận ký, ghi rõ họ tên)* | *(Trưởng bộ phận ký, ghi rõ họ tên)* |
| <br><br><br><br> | <br><br><br><br> |
| ………………………………… | ………………………………… |
| Ngày ……/……/…… | Ngày ……/……/…… |

### Xác nhận cấp trên

| **NGƯỜI CHỨNG KIẾN** | **XÁC NHẬN CỦA LÃNH ĐẠO** |
|---|---|
| *(Ký, ghi rõ họ tên)* | *(Ký, đóng dấu)* |
| <br><br><br><br> | <br><br><br><br> |
| ………………………………… | ………………………………… |

---

## PHỤ LỤC KÈM THEO

| Phụ lục | Nội dung | Số trang | Đính kèm |
|---|---|---|---|
| **A** | Bảng thông tin truy cập (niêm phong riêng) | ……… | ☐ |
| **B** | Kết quả chạy bộ test tự động tại thời điểm bàn giao | ……… | ☐ |
| **C** | Hướng dẫn sử dụng cho vận hành (`docs/huong-dan-van-hanh.md`) | ……… | ☐ |
| **D** | Nội dung đào tạo (`docs/noi-dung-dao-tao.md`) | ……… | ☐ |
| **E** | Biên bản đào tạo BPVN-HR-PR-030 (bản đã ký) | ……… | ☐ |
| **F** | Danh sách máy trạm và thiết bị chi tiết | ……… | ☐ |
