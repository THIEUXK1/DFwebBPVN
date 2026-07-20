# Cutover & Rollback Plan — Kế Hoạch Chuyển Đổi & Quay Lui (cutover-rollback-plan.md)

Lập 2026-07-17 — Phase C/D. Bản đề xuất quy trình chuyển đổi hệ thống (Cutover) 10 giai đoạn và kịch bản quay lui an toàn (Rollback) cho từng máy trạm. Tài liệu thiết kế — không can thiệp hệ thống đang chạy.

---

## 1. Chiến Lược Chuyển Đổi (Cutover Strategy) - 10 Giai Đoạn

Quy trình chuyển dịch từ Excel VBA cũ sang hệ thống Web mới được chia thành 10 giai đoạn để giảm thiểu tối đa rủi ro gián đoạn sản xuất:

```
[GĐ 1-3: Shadowing] ──> [GĐ 4-5: Web-Write/Compare] ──> [GĐ 6-7: Dual-Write & Pilot] ──> [GĐ 8-10: Go-Live & Decom]
```

1. **Giai đoạn 1: Read-Only Legacy Discovery**
   - Đọc dữ liệu từ Access cũ sang PostgreSQL staging liên tục để phân tích cấu trúc, tần suất ghi nhận và phát hiện lỗi dữ liệu mà không can thiệp vào vận hành.
2. **Giai đoạn 2: Shadow Read**
   - Ứng dụng Web mới chạy ngầm, liên tục đọc dữ liệu mẻ cân/yêu cầu điều phối từ Access thông qua Legacy Adapter để hiển thị lên màn hình giám sát (Dashboard) của giám sát viên.
3. **Giai đoạn 3: Shadow Calculation**
   - Khi có dữ liệu mới trên Access, Backend Web tự động chạy song song bộ tính toán (như tính toán kho B24, tính toán dung sai) và so sánh kết quả tính với kết quả do VBA tính. Ghi log nếu có lệch.
4. **Giai đoạn 4: Web ghi Database mới, VBA vẫn vận hành**
   - Vận hành viên bắt đầu nhập liệu thử trên Web. Web ghi dữ liệu trực tiếp vào database PostgreSQL mới. Tuy nhiên, lệnh in tem và số cân thật vẫn lấy từ Excel VBA cũ.
5. **Giai đoạn 5: Đối chiếu Kết quả (Reconciliation)**
   - So sánh đối chiếu kết quả nhập liệu, in nhãn và số cân giữa Database Web mới và tệp Access cũ cuối mỗi ca làm việc. Đảm bảo khớp thông tin 100%.
6. **Giai đoạn 6: Dual-Write có kiểm soát**
   - **Cảnh báo quan trọng:** Không thực hiện dual-write trực tiếp từ Web vào file Access `.accdb` qua mạng LAN vì rủi ro lock file và hỏng (corruption) database Access là cực kỳ lớn.
   - Giải pháp: Thực hiện Dual-Write qua một **Transaction Bridge Adapter** độc lập. Adapter này ghi nhận các command vào hàng chờ PostgreSQL, sau đó thực hiện ghi tuần tự (serialized write) vào Access cũ để đảm bảo không bị xung đột khóa.
7. **Giai đoạn 7: Pilot theo Workstation**
   - Bật feature flag chạy thật cho từng máy trạm độc lập (ví dụ: chạy thử trạm cân `SMALL_SCALE_1` trước, các trạm khác vẫn chạy Excel).
8. **Giai đoạn 8: Cutover chính thức**
   - Tuyên bố ngừng sử dụng Excel VBA cũ cho toàn bộ 6 máy trạm nghiệp vụ.
   - Bật toàn bộ feature flag của Local Agent tại các trạm.
9. **Giai đoạn 9: Read-Only Legacy**
   - Khóa quyền ghi (Read-Only) đối với các file Access `RECORD.accdb`, `RECORD1.accdb`, `WH.accdb` và `chem_order.accdb`. Chỉ mở quyền đọc phục vụ tra cứu dữ liệu cũ.
10. **Giai đoạn 10: Decommission**
    - Sau 3 - 6 tháng hoạt động ổn định trên hệ thống Web mới, thực hiện đóng gói và archive các database Access cũ vào hệ thống lưu trữ lâu dài của nhà máy.

---

## 1.1. Thứ tự Cutover theo Workstation Type (bổ sung 2026-07-17 — mục 6.2 yêu cầu)

| Thứ tự | Workstation | Lý do (phụ thuộc dữ liệu + rủi ro) |
|---|---|---|
| 1 | **CHEMICAL_CALL** | Domain độc lập nhất về dữ liệu (không phụ thuộc RECORD_A/RECORD_B, chỉ CHEM_ORDER) — rủi ro cutover thấp nhất, lỗi không lan sang domain khác. Đồng thời đây là domain rủi ro nghiệp vụ cao nhất nếu để cuối cùng (0% đã xây — pilot sớm để có thời gian sửa lỗi phát sinh dài nhất). |
| 2 | **PRODUCTION_ORDER** | Nguồn tạo dữ liệu đầu chuỗi (`tbl_input_all`→RECORD_A) — phải ổn định trước khi QR_LABEL_PRINTING (bước 3) có dữ liệu đúng để xử lý. Đã có nền tảng `MachineDispatchController`, rủi ro kỹ thuật thấp hơn CHEMICAL_CALL. |
| 3 | **QR_LABEL_PRINTING** | Phụ thuộc trực tiếp output của PRODUCTION_ORDER (bước 2) — không thể cutover trước bước 2 vì sẽ không có dữ liệu để in. Rủi ro cao nhất về nghiệp vụ (B24, "15L", `tbl_SentLog`) — cutover sau khi 2 bước trước đã ổn định để cô lập biến số. |
| 4 | **SMALL_SCALE (máy 1/2)** | Domain RECORD_B độc lập kỹ thuật với RECORD_A (không phụ thuộc trực tiếp bước 1-3) nhưng phụ thuộc QR_LABEL_PRINTING **về mặt nghiệp vụ** (cần tem đã in để quét) — cutover 1 máy trước để so sánh trực tiếp với máy còn lại đang chạy VBA (đối chứng tự nhiên, giảm rủi ro). |
| 5 | **SMALL_SCALE (máy 2/2)** | Sau khi máy 1 pilot ổn định ≥7 ngày không phát sinh lỗi — áp dụng cùng cấu hình đã kiểm chứng. |
| 6 | **LARGE_SCALE** | Cutover cuối cùng — đây là workbook có 2 bug đã xác nhận (màu ACCEPTED/REJECTED sai, timer leak) cần thời gian dài nhất để kiểm chứng bản vá không tái phát (`NOT_MIGRATED_LEGACY_BUG`, xem `local-agent-architecture.md` Mục 1) trước khi tin tưởng cho sản xuất thật. |

**Không cutover CHEMICAL_CALL và QR_LABEL_PRINTING đồng thời với SMALL_SCALE/LARGE_SCALE** — giữ tối đa 1-2 workstation type đang trong giai đoạn Pilot (GĐ 7) tại 1 thời điểm để cô lập nguyên nhân khi có sự cố.

---

## 2. Kế Hoạch Quay Lui (Rollback Plan) Theo Workstation Type

Khi xảy ra lỗi nghiêm trọng (mất mạng LAN kéo dài, Agent hỏng driver cân, lỗi in tem hàng loạt làm nghẽn sản xuất > 2 tiếng), Shift Leader có quyền ra lệnh Rollback hệ thống về Excel VBA theo từng loại máy trạm:

### 2.1. Trạm CHEMICAL_CALL
- **Các bước thực hiện:**
  1. Tắt feature flag `chemical_call_enabled` trên Web Admin.
  2. Yêu cầu vận hành viên tắt trình duyệt Web tại máy trạm, mở lại file Excel `1.báo phát AC XƯỞNG -193.xlsm` cũ.
  3. Kỹ sư vận hành reset trạng thái van hóa chất trong tệp `chem_order.accdb` về trạng thái trước khi lỗi.
- **Dữ liệu:** Các lệnh gọi hóa chất đã thực hiện thành công trên Web được giữ nguyên trong `app.chemical_call_requests` làm dữ liệu audit, đánh dấu trạng thái `PILOT_ROLLBACK`.

### 2.2. Trạm PRODUCTION_ORDER
- **Các bước thực hiện:**
  1. Tắt feature flag `production_order_enabled`.
  2. Mở file Excel `2.C3 grid load row lock id FB -192(QR).xlsm` tại máy trạm.
- **Đối soát sau rollback (Reconciliation):** Export danh sách đơn hàng đã duyệt trên Web trong ca làm việc nạp thủ công vào bảng `tbl_input_all` của Access để Excel có dữ liệu chạy tiếp.

### 2.3. Trạm QR_LABEL_PRINTING
- **Các bước thực hiện:**
  1. Tắt feature flag `qr_label_printing_enabled` và `local_print_agent_enabled`.
  2. Mở file Excel `3.DF028 ... xlsm` tại trạm.
- **Dữ liệu:** Giữ nguyên lịch sử `print_jobs` trên Web. Vận hành viên thực hiện in lại các tem bị lỗi trực tiếp bằng Excel.

### 2.4. Trạm Cân (SMALL_SCALE và LARGE_SCALE)
- **Các bước thực hiện:**
  1. Tắt feature flag `local_scale_agent_enabled` (theo scope Instance — chỉ tắt đúng máy gặp sự cố, KHÔNG tắt cả 2 SMALL_SCALE nếu chỉ 1 máy lỗi, xem `feature-flags.md` Mục 2.1).
  2. Mở file Excel `4.semiauto-small scale ... xlsm` hoặc `5.Semiauto- lockmove ... xlsm`.
- **Đối soát sau rollback:** 
  - Đọc toàn bộ weighing events ghi nhận cục bộ trong SQLite của Agent (nếu có lúc mất mạng) nạp bổ sung vào bảng `tblRECORD` của Access.
  - Đánh dấu phiên pilot bị rollback trên Web để giám sát chất lượng.

### 2.5. Bảng tổng hợp Rollback (đầy đủ 9 trường theo yêu cầu mục 6.3)

| Workstation Type | Trigger rollback | Feature flag tắt | Agent command ngừng | Dữ liệu Web giữ lại | Dữ liệu cần reconciliation | Cách quay về VBA | Người phê duyệt | Thời gian tối đa phục hồi | Điều kiện thử pilot lại |
|---|---|---|---|---|---|---|---|---|---|
| CHEMICAL_CALL | Lỗi ghi `chemical_call_requests` > 3 lần liên tiếp, hoặc mất kết nối DB > 5 phút | `chemical_call_enabled` (Instance) | Không có Agent riêng cho domain này | `chemical_call_requests`/`_events` (toàn bộ, đánh dấu `PILOT_ROLLBACK`) | Đối chiếu trạng thái van hóa chất thật với `tbl_status` trước khi mở lại VBA | Mở lại `chem_order.frm`, kỹ sư xác nhận trạng thái van khớp thực tế trước khi vận hành tiếp | Trưởng ca | 15 phút (chỉ cần đóng trình duyệt + mở Excel) | Xác định nguyên nhân + fix + chạy lại `ChemicalCallServiceTest` PASS |
| PRODUCTION_ORDER | Lỗi tạo/duyệt đơn liên tục, hoặc lock bị treo không giải phóng | `production_order_enabled` (Instance) | Không có Agent riêng | `production_batches`/`production_order_status_events` | Export đơn đã duyệt trên Web, nạp tay vào `tbl_input_all` | Mở `2.C3 grid...xlsm`, nhập bù các đơn đã tạo trên Web nếu VBA chưa thấy | Trưởng ca | 30 phút (cần nhập bù dữ liệu) | Test optimistic-lock/lease-lock PASS + không còn đơn bị treo lock |
| QR_LABEL_PRINTING | In sai/lặp hàng loạt, hoặc B24 route sai được phát hiện | `qr_label_printing_enabled` + `local_print_agent_enabled` (Instance) | Print Agent: dừng nhận job mới, hoàn tất job đang PRINTING (không cắt ngang, xem `feature-flags.md` Mục 2.2) | `dispatch_events`/`qr_payloads`/`print_jobs`/`print_attempts` | Đối chiếu tem đã in trên Web vs tem in bù bằng Excel (tránh in trùng) | Mở `3.DF028...xlsm`, vận hành viên in lại tem cho các đơn CHƯA in trên Web (tra theo `dispatch_events`) | Trưởng ca + QA (vì liên quan B24/routing) | 20 phút | B24 routing đã xác nhận đúng (ADR CH-BUS-012 áp dụng) + `ConfirmDispatchServiceTest` PASS |
| SMALL_SCALE (từng máy) | Sai số cân bất thường, Agent offline > 10 phút, hoặc `ACCEPTED`/`REJECTED` hiển thị sai | `local_scale_agent_enabled` (Device, chỉ máy lỗi) | Scale Agent tương ứng: dừng gửi sample, giữ buffer local | `weighing_samples`/`weighing_results`/`weighing_events` (không xóa, kể cả sample lỗi) | Đối chiếu số cân đã ghi Web vs sổ tay giấy nếu có khoảng trống dữ liệu | Mở workbook 4 tại đúng máy, vận hành viên cân tiếp bằng VBA | Kỹ sư + Trưởng ca | 10 phút (máy kia — nếu còn — không bị ảnh hưởng, đúng thiết kế cô lập `WorkstationDeviceIsolationTest`) | `ScaleLiveWeightTest`/golden test PASS + xác nhận StableFilter/tare hoạt động đúng |
| LARGE_SCALE | Tương tự SMALL_SCALE, **đặc biệt**: phát hiện lại bug màu ACCEPTED/REJECTED hoặc timer leak (`NOT_MIGRATED_LEGACY_BUG` tái xuất hiện) | `local_scale_agent_enabled` (Device) | Scale Agent LARGE_SCALE: dừng ngay lập tức (không chờ hoàn tất nếu nghi ngờ bug legacy tái xuất) | Như SMALL_SCALE | Như SMALL_SCALE + **bắt buộc** kiểm tra lại toàn bộ dữ liệu trong phiên pilot có bị lỗi màu tương tự bug cũ không | Mở `5.Semiauto-lockmove...xlsm` | Kỹ sư + Trưởng ca + xác nhận riêng của người review code (vì liên quan bug đã biết) | 10 phút | Regression test riêng cho 2 bug đã biết PASS liên tục ≥3 lần chạy trước khi thử lại |

**Nguyên tắc bất biến (nhắc lại, mục 6.3 cuối):** Rollback **KHÔNG BAO GIỜ xóa** `audit_logs`, `print_attempts`/`dispatch_events`, hay `weighing_samples` — mọi dữ liệu đã ghi trong thời gian pilot được giữ nguyên vĩnh viễn để phục vụ reconciliation và điều tra sự cố, kể cả khi phiên pilot đó bị đánh giá là thất bại.

## 2.6. Rủi ro Dual-Write vào Access (bổ sung — mục 6.4 yêu cầu đánh giá rõ trước khi cân nhắc)

| Rủi ro | Đánh giá |
|---|---|
| Locking | Access `.mdb`/`.accdb` dùng file-level/page-level lock qua `.laccdb` — nhiều tiến trình ghi đồng thời (VBA tại nhiều máy + Bridge Adapter) tăng mạnh xác suất deadlock/timeout |
| Network share | Toàn bộ 5 file đang ở network path `Z:\...` (SMB/CIFS) — ghi qua mạng có độ trễ và rủi ro mất gói cao hơn ghi local, dễ để file ở trạng thái dở dang nếu mất kết nối giữa lúc ghi |
| Corruption risk | Access nổi tiếng dễ hỏng khi bị ngắt kết nối giữa lúc ghi (đây chính là rủi ro mà R-01 cũ từng lo ngại, dù thực tế chưa xảy ra với `RECORD.accdb` hiện tại) — dual-write làm tăng tần suất ghi → tăng rủi ro |
| Retry | DAO/ADODB không có cơ chế retry tích hợp tốt — phải tự xây ở tầng Bridge Adapter, phức tạp và dễ sai |
| Transaction limitation | Access hỗ trợ transaction rất hạn chế so với PostgreSQL — không thể đảm bảo atomicity xuyên suốt Web+Access trong 1 thao tác |
| Duplicate writes | Nếu Bridge Adapter retry sau lỗi mà không có idempotency key phía Access (VBA gốc không thiết kế cho việc này) → dễ ghi trùng dòng |

**Kết luận:** `legacy_dual_write_enabled = false` mặc định và duy trì trong toàn bộ Phase 12 (pilot) — chỉ xem xét bật nếu có thiết kế Bridge Adapter riêng đã qua đánh giá rủi ro độc lập (ngoài phạm vi Phase C/D này).
