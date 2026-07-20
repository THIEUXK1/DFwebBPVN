# Pilot Blockers — Phân loại theo mức chặn Phase 12 (pilot-blockers.md)

> [!IMPORTANT]
> **Cập nhật 2026-07-17 (đợt duyệt lần 4):** Theo yêu cầu mới, pilot phải bao phủ TOÀN BỘ 6 máy nghiệp vụ (không loại trừ CHEMICAL_CALL/QR_LABEL_PRINTING) — xem `pilot-end-to-end-scenarios.md`. Phạm vi bật/tắt từng workstation qua feature flag (`local-agent-architecture.md` Mục 4), không hard-code trong source.
>
> **Cập nhật Phase C/D (Target Design & Schema Proposal):** Đã hoàn tất toàn bộ thiết kế chi tiết cấp domain, thực thể, API contract, Local Agent contract, và Feature Flags matrix. Các giải pháp cho PB-1 đến PB-9 đã được đặc tả vật lý và sẵn sàng đưa vào triển khai ở Phase E.

Lập 2026-07-17. Phạm vi pilot Phase 12 theo CLAUDE.md: **triển khai Local Agent tại 2 trạm làm việc mẫu, vận hành thực tế CÂN + IN TEM trong 7 ngày, chạy song song với Excel VBA cũ**. Một hạng mục chỉ được coi là "Pilot Blocker" nếu ảnh hưởng trực tiếp tới cân/in tem tại 2 trạm pilot hoặc tính đúng đắn của việc đối soát song song. Các chức năng vẫn chạy trên Excel cũ trong thời gian song song (điều phối, công thức, tồn kho, chẩn đoán sự cố) KHÔNG chặn pilot nhưng CHẶN cutover Phase 13.

**Cập nhật phạm vi (đợt duyệt lần 4):** người dùng đã yêu cầu pilot 7 ngày PHẢI bao gồm CHEMICAL_CALL + QR_LABEL_PRINTING (không loại trừ) — nghĩa là PB-8 và PB-9 bên dưới nay là pilot blocker THẬT SỰ (không còn điều kiện "chỉ chặn nếu phạm vi gồm 2 máy này" — phạm vi CHẮC CHẮN gồm 2 máy này).

---

## Danh sách 1 — PILOT BLOCKERS (phải xử lý/trả lời TRƯỚC khi bắt đầu 7 ngày pilot)

| # | Hạng mục | Loại | Vì sao chặn pilot | Hành động | Tham chiếu |
|---|---|---|---|---|---|
| PB-1 | ~~`ScaleReader.CleanWeight` lấy số ĐẦU TIÊN thay vì số CUỐI~~ **ĐÃ SỬA + BUILD/TEST PASS 2026-07-17 (Phase E)** | Lỗi code | `ScaleReader.CleanWeight` nay implement đúng `ExtractLastNumber` (lọc whitelist `[0-9+\-.,]` rồi Split(",") + duyệt ngược, không còn Regex.Match-đầu-tiên). Đã cài .NET 8 SDK, `dotnet build` PASS (0 lỗi), tạo project `agent/DFAgent.Tests` (xUnit) với TV1/TV2/TV3 từ `p0-c-scale-algorithm.md` — lần chạy test ĐẦU TIÊN phát hiện thêm 1 bug thật (thiếu bước lọc whitelist trước khi tách token, khiến TV1 vẫn trả về 12 thay vì 10.5), đã sửa và **6/6 test PASS**. Còn thiếu: kiểm thử với phần cứng cân thật (chỉ mới unit-test thuật toán thuần túy) | FIX-002 Đợt 1 — hoàn tất mức unit-test | [p0-c-scale-algorithm.md](file:///F:/DF/.claude/p0-analysis/p0-c-scale-algorithm.md) Phần A.2, TV1; `agent/ScaleReader.cs`; `agent/DFAgent.Tests/ScaleReaderTests.cs` |
| PB-2 | ~~Không có bộ lọc ổn định (StableFilter) — `stable:true` hard-code~~ **ĐÃ SỬA + BUILD/TEST PASS 2026-07-17 (Phase E)** | Thiếu chức năng | Đã implement `ScaleReader.StableFilter` (đúng thuật toán VBA: 2 lần đọc liên tiếp cùng chuỗi thô = ổn định), truyền `is_stable` xuyên suốt Agent→`POST /api/devices/readings`→`DeviceController` cache→`GET .../readings/{id}`→`WeighingStation.vue` (không còn hard-code); nút Xác nhận cân bị khóa khi chưa ổn định. Phát hiện thêm & sửa kèm: bug đọc live weight qua cân thật bị che khuất bởi `res.data.data?.weight` sai tầng. TV3 (2 lần đọc giống hệt chuỗi mới coi là ổn định) đã có unit test PASS trong `agent/DFAgent.Tests`. Còn thiếu: kiểm thử phần cứng thật | FIX-002 Đợt 1 — hoàn tất mức unit-test | p0-c Phần A.4; `agent/ScaleReader.cs`, `agent/Worker.cs`, `backend/app/Http/Controllers/DeviceController.php`, `frontend/src/views/WeighingStation.vue`, `agent/DFAgent.Tests/ScaleReaderTests.cs` |
| PB-3 | Quy trình trừ bì (tare) chưa được định nghĩa cho hệ mới — VBA tự trừ, web không trừ | Câu hỏi nghiệp vụ chặn | Nếu thao tác viên không bấm TARE vật lý mà hệ thống cũng không tự trừ, `weight` gửi lên là GROSS (cả khay/cốc) → so dung sai sai toàn bộ. Phải có câu trả lời CH-BUS-006 và quy trình vận hành bằng văn bản TRƯỚC ngày pilot đầu tiên | Trả lời CH-BUS-006 → FIX-002 Đợt 2 nếu cần phần mềm tự trừ | p0-c Phần A.6, TV4 |
| PB-4 | Dữ liệu rác cổng COM bị `.NET` âm thầm quy về `0.0` (VBA giữ giá trị hợp lệ cuối) | Lỗi code | Trong 7 ngày chạy thật với cân vật lý + Putty log thật, nhiễu là chắc chắn xảy ra — quy về 0.0 có thể kích hoạt ghi nhận sai hoặc cảnh báo giả | FIX-002 Đợt 1 (gộp) | p0-c Phần A.10 |
| PB-5 | `OfflineQueue.cs` của Agent chưa được xác minh hoạt động đúng (mã hóa + idempotency khi mất mạng) | Chưa xác minh | CLAUDE.md mục 5 yêu cầu bắt buộc; xưởng pilot có rủi ro mất mạng LAN thật (R-03); audit vừa qua KHÔNG deep-dive file này — nếu offline queue không hoạt động, mất dữ liệu cân khi rớt mạng giữa phiên | Chạy kiểm thử mục 2.6 `testing-strategy.md` (Offline & Reconnect Test) trước pilot | `group4_print_findings` mục NEEDS_CONFIRMATION; `testing-strategy.md` 2.6 |
| PB-6 | Đối soát Golden Master với dữ liệu lịch sử: 31.361 dòng REJECTED (~22,3% của `tblRECORD`) KHÔNG phân tách được phần "REJECTED giả" do bug workbook B | Cảnh báo quy trình (không phải code) | Nếu đội đối soát so sánh tỉ lệ accept/reject của web pilot với tỉ lệ lịch sử, số lịch sử đã nhiễm bug — kết luận "web lệch với lịch sử" sẽ SAI. Biên bản đối soát phải ghi rõ caveat này | Ghi vào biên bản/kịch bản đối soát Phase 12; xác định trạm nào từng dùng workbook B nếu có thể | p0-c Phần C; `vba-version-comparison.md` mục 4 |
| PB-7 | Ràng buộc C-04 (QR mới phải đọc được bởi máy quét hiện có) chưa được kiểm thử vật lý với token `DF:<TYPE>:<uuid>`; tem in từ Excel cũ (payload tự chứa) KHÔNG quét được vào web trong thời gian song song | Chưa xác minh | Pilot chạy song song: cần quy ước rõ tem nào quét vào hệ nào; và cần 1 buổi test máy quét thật với tem web trước ngày đầu | Test vật lý máy quét + quy ước vận hành song song | `group4_print_findings` mục 3 (QR payload); `risks-and-assumptions.md` C-04 |
| PB-8 (mới 2026-07-17) | Nghiệp vụ **CHEMICAL_CALL** (1 trong 6 máy nghiệp vụ đã xác nhận) — Tách riêng khỏi luồng tích hợp chung | Cô lập nghiệp vụ | **TẠM HOÃN / KHÔNG CHẶN PILOT CÒN LẠI.** Đã tách riêng thành domain độc lập với trạng thái `BLOCKED_BY_BUSINESS_CONFIRMATION` do blocker `CH-BUS-015`. Trạm này sẽ tiếp tục chạy Excel/VBA cũ độc lập trong thời gian chạy pilot song song của 4 trạm còn lại. | Đã cô lập thành công khỏi luồng tích hợp | `vba-migration-matrix.md` NHÓM 0; `workstation-matrix.md` Mục 3 |
| PB-9 (mới 2026-07-17) | Workbook QR_LABEL_PRINTING thật (DF028) có 4 khoảng trống lớn: (1) lưới chờ 18×9 tô màu theo tuổi dữ liệu (24h/48h, 162 procedure) hoàn toàn MISSING; (2) trường `scale_checked`/`raw_qr_dye`/`raw_qr_chemical` có sẵn trong schema nhưng 0 controller đọc/ghi; (3) hành vi "in tem = tự động xác nhận scale-check" MISSING; (4) logic phân vùng kho B24 + chọn chế độ QR theo Machine×Tank không có tương đương | Thiếu chức năng + rủi ro nghiệp vụ | Nếu pilot dùng máy QR_LABEL_PRINTING thật, tem in ra có thể thiếu thông tin phân vùng kho hoặc không xác nhận scale-check tự động — rủi ro vật tư đi sai khu vực | Xác nhận nghiệp vụ khẩn cấp về logic B24 trước UAT; ưu tiên nối dây 3 field đã có sẵn trong schema (effort thấp) trước khi mở rộng | `vba-migration-matrix.md` NHÓM 4-DF028 |

**Điều kiện đủ để bắt đầu pilot:** PB-1/2/4 sửa xong + test PASS; PB-3 có câu trả lời và quy trình văn bản; PB-5 chạy Offline Test PASS; PB-6 ghi vào biên bản; PB-7 test máy quét PASS; PB-8/PB-9 chỉ chặn nếu phạm vi pilot 7 ngày bao gồm máy CHEMICAL_CALL/QR_LABEL_PRINTING tương ứng — cần xác nhận phạm vi pilot chính xác gồm những máy nào trong 6 máy đã xác nhận (xem `open-questions.md` CH-BUS-009).

---

## Danh sách 2 — MISSING NHƯNG KHÔNG CHẶN PILOT (chặn Cutover Phase 13 hoặc cần cho hoàn thiện)

| Cụm | Nội dung | ID ma trận chính | FIX | Chặn Phase 13? |
|---|---|---|---|---|
| Điều phối — luồng tạo/duyệt | Không có API `store`; không có UI duyệt máy/thùng; quy tắc 250L chưa chốt; kiểm tra trùng color+code | VBA-DISPATCH-019/033/036/037/038/043/044/053/055 (+UI 004/007/008/009/010/015/016/020/020B/021/022/025/052/056/057) | FIX-003 | **CÓ** — trạm điều phối không thể bỏ Excel |
| Điều phối — vận hành phụ | Auto-refresh/polling; lịch sử SENT; dashboard theo máy + cảnh báo chờ lâu | VBA-DISPATCH-029(P)/064/065/066/072/074/076(P) | FIX-009a | Có (mức độ thấp hơn) |
| Công thức — TraHeSo | Tra hệ số 3 chiều chưa migrate | VBA-RECIPE-012/013 | FIX-001 | **CÓ** nếu CH-BUS-004 xác nhận còn dùng |
| Công thức — QR phiếu công thức | In phiếu KHAIDON kèm QR nội bộ | VBA-RECIPE-001/003/007/008/009/010/011 | (chưa gán FIX — chờ xác nhận nghiệp vụ có cần in phiếu từ web không) | Có nếu nghiệp vụ cần |
| Tồn kho phòng liệu | Trừ kho tự động, log giao dịch, quy đổi kiện, ngưỡng cảnh báo | VBA-RECIPE-022/024/026 (+019/020/021 P) | FIX-007 | **CÓ** nếu CH-BUS-007 xác nhận còn dùng |
| Báo cáo tiêu hao | Dedup theo batch, khung 7 ngày, xếp hạng theo số lần cân | VBA-RECIPE-015/016/017 (P) | FIX-009b | Không (nice-to-have) |
| In tem — tra cứu & idempotency | Tra cứu COLOR+CODE+ngày; chống double-submit `PrintJobController::store`; chuẩn hóa QR payload `LOT:` | VBA-PRINT-055/064/070 (P) + phát hiện nội bộ | FIX-009c | Có (mức trung bình) |
| In tem — slip 9 dòng đầy đủ | `btnPrint2_Click` in bảng chi tiết cả mẻ | VBA-PRINT-047 | FIX-006 (gộp khi có file) | Cần xác nhận nghiệp vụ |
| In tem — template thiếu | 27 dòng / 15L / landscape / JIT | (ngoài ma trận — file thiếu) | FIX-006 | **CÓ** nếu template còn dùng |
| Chẩn đoán sự cố — KB Editor | CRUD rule + audit | VBA-TROUBLE-015/018/020/021/022/029 | FIX-005 | Có (kỹ sư mất khả năng tự cập nhật KB) |
| Chẩn đoán sự cố — chi tiết | Lọc 6M; cột W; breakdown lưu DB; prefill setpoint; giới hạn 9; top-10 | VBA-TROUBLE-001/002/006(P)/008(P)/013(P)/034(P) + 012/019 | FIX-009d | Không |
| Cân — tra cứu checker | checkform COLOR+CODE+ngày | VBA-SCALE-034…041/108 | FIX-009 (gộp cụm c) | Không |
| Cân — mô hình 6 ô song song, bàn phím ảo | shadow-lock, nhập tay trọng lượng | VBA-SCALE-015/016/070…088/105/106/107 | Chờ xác nhận nghiệp vụ (một phần trùng PB-3 về quy trình nhập tay fallback) | Cần xác nhận |
| Dữ liệu di trú | Mapping `tbl_ToSend2`/`WAITING`/`tbl_Waiting`/`tblSync` chưa xác minh | (bảng dữ liệu nhóm DISPATCH) | FIX-004 | **CÓ** cho độ tin cậy dữ liệu lịch sử |
| In tem QR — auto-refresh dữ liệu | Polling 15s (lưới gửi) / 3 phút (lưới chờ) — UI mới chỉ refresh khi bấm tay | VBA-QRPRINT-004/032/034/050/053 | (chưa gán FIX) | Có (mức trung bình — dữ liệu cũ hiển thị sai) |
| In tem QR — nút in trực tiếp từ hàng chờ | Có thể in lại bất kỳ lúc nào trước khi Confirm, không cần đã cân xong | VBA-QRPRINT-020/021/025/026 | (chưa gán FIX) | Cần xác nhận nghiệp vụ có còn cần luồng này không |
| In tem QR — sổ cái gửi hàng (`tbl_sentlog` tương đương) | `ConfirmRow` (INSERT sentlog + DELETE tosend) chưa có bảng độc lập tương đương ở web, chỉ có AuditLog sự kiện | VBA-QRPRINT-017 | (chưa gán FIX) | Cần xác nhận Audit Log Explorer có đủ thay thế vai trò "sổ cái" không |

*(P) = PARTIALLY_MIGRATED; còn lại MISSING/NEEDS_BUSINESS_CONFIRMATION. Danh sách ID đầy đủ từng trạng thái: chạy `bash .claude/verify-matrix-counts.sh` và xem TÓM TẮT từng nhóm trong ma trận.*

---

## Danh sách 3 — DEAD / DEPRECATED CANDIDATES (không cần migrate — chờ xác nhận cuối theo FIX-010)

**DEPRECATED_CONFIRMED (35 dòng — đã đủ căn cứ, không cần hỏi thêm):** toàn bộ nhóm kỹ thuật RPA/Excel-đặc-thù: giả lập chuột/bàn phím (`ClickAt`, `SendTextToApp`, `SetClipboardText`, `SmartDelay`, `btn_Out/In_Click`, `FireRackBatch` chuỗi OVER6 UI), topmost/ghim form (`SetTopMost`, `Mod_lockmoveform`, `SaveFormLayout`), vòng đời form Excel, `Workbook_SheetChange`/`CalculateFull`, công cụ sửa dữ liệu 1 lần (`Delete_SentLog_Year_2025`, `Fix_AllTime_SwapDM_Future` — tuyệt đối không migrate, vi phạm CLAUDE.md mục 3), spinner nhập điểm tay (TROUBLE-031). Chi tiết ID: xem cột Trạng thái trong ma trận (lọc `DEPRECATED_CONFIRMED`).

**DEAD_CODE_CANDIDATE (75 dòng — cần người dùng xác nhận theo FIX-010, KHÔNG tự xóa/bỏ):**
- CHEM (3, mới): 001, 002 (ThisWorkbook.cls/Sheet1.cls rỗng), 010 (`GetStatus` — mồ côi, có thể là API dự phòng chưa hoàn thiện chỗ gọi).
- RECIPE (6): 002, 004, 005, 014, 018, 023 — đáng chú ý 018 (`Open_Form`) và 023 (`ShowUserForm1`) là **điểm vào duy nhất** của form báo cáo tiêu hao và form tồn kho: nếu thật sự "chết" thì cả cụm form chết theo — mâu thuẫn với việc các form này có vẻ vẫn dùng. Nghi vấn chính: Shape/Button trên sheet gán macro mà olevba không trích xuất được (hạn chế công cụ đã ghi nhận). **Ưu tiên hỏi đầu tiên.**
- DISPATCH (17): 001, 002, 005, 006, 017, 024, 026, 027, 032, 039, 040, 041 (`Smart_Insert_FromForm` — có bug gọi hàm không tồn tại, chắc chắn chưa từng chạy), 054, 060 (`FillRow_V5`), 061 (`LockAllTextboxes` — bị comment), 077 (`ParseTime1`), 078.
- SCALE (13): 009, 010, 014, 018, 019, 026, 027, 029, 042, 043, 045, 090, 110, 117 (một số ID xem chú thích kép trong ma trận).
- PRINT (24): 008, 009, 012, 013, 018, 023, 026, 027, 028, 029, 030-033 (toàn bộ `Mod_print_tsc224` — dead ở VBA nhưng kỹ thuật đã dùng thật ở `LabelPrinter.cs`), 034, 035, 037, 076, 078-083 (toàn bộ `Mod_accesscore` mồ côi — lớp phê duyệt 2 bước hoàn chỉnh không nút nào gọi, nghi thuộc file thiếu).
- QRPRINT (5, mới): 011 (`ResetCheck` — lời gọi bị comment-out), 027 (`vd10_wait_lv13_Change` — stub rỗng), 043 (`printform.LoadByID` — mồ côi, trùng tên khác bảng với `wait_printform.LoadByID`), 066 (`Mod_nztext.NzText` Public — bị Private cục bộ che, không bao giờ được gọi), 068/069 (`ChucMungNamMoi`/`StopText` — easter egg chúc Tết, không liên quan nghiệp vụ).
- TROUBLE (7): 004, 007, 027, 030, 040 (`CompareStatus`), 052 (`Sheet10.cls` rỗng), 053.

**Lưu ý ràng buộc:** theo quy trình audit, DEAD_CODE_CANDIDATE **chưa được phép loại bỏ** cho tới khi FIX-010 hoàn tất (xác nhận từng cụm với người dùng). Đặc biệt cụm `Mod_accesscore` (PRINT-078…083) và các "điểm vào duy nhất" (RECIPE-018/023) có xác suất đáng kể KHÔNG phải code chết thật.
