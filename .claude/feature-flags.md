# Feature Flags Matrix — Ma Trận Feature Flags (feature-flags.md)

Lập 2026-07-17 — Phase C/D. Thiết kế chi tiết cho việc quản lý các tính năng mới/legacy bằng Feature Flags. Tài liệu thiết kế — không sửa code sản xuất, không chạy migration.

---

## 1. Cơ Chế Hoạt Động & Phạm Vi (Scoping Strategy)

Feature Flags cho phép bật/tắt động các luồng nghiệp vụ mà không cần redeploy code. Một flag có thể được đánh giá dựa trên các phạm vi (scope) từ rộng đến hẹp:

1. **Global (Toàn hệ thống):** Áp dụng cho mọi trạm, mọi người dùng.
2. **Workstation Type (Loại trạm):** Bật/tắt theo loại trạm nghiệp vụ (ví dụ: chỉ bật cho các trạm cân `SMALL_SCALE`).
3. **Workstation Instance (Mã trạm cụ thể):** Bật/tắt cho một máy trạm cụ thể bằng `workstation_code` (tiện cho việc pilot cuốn chiếu từng máy trạm).
4. **User / Role (Người dùng / Vai trò):** Bật cho nhóm Shift Leader hoặc một số nhân viên chạy thử.
5. **Pilot Group (Nhóm Pilot):** Bật cho một tập hợp chỉ định gồm cả User và Workstation.

**Nguyên tắc tối cao:** Mọi tính năng chưa được nghiệp vụ xác nhận chính thức hoặc có rủi ro cao đều phải được đặt giá trị mặc định là **OFF** (hoặc chạy ở chế độ an toàn tối đa như `MANUAL_REVIEW`).

---

## 2. Danh Sách Feature Flags Mục Tiêu (Feature Flags Matrix)

| Flag Key | Scope Hỗ Trợ | Mặc Định | Mô Tả & Điều Kiện Bật |
|---|---|:---:|---|
| `chemical_call_enabled` | Global, Workstation | **OFF** | Bật domain Chemical Call. Khi OFF, giao diện trạm `CHEMICAL_CALL` hiển thị thông báo bảo trì, API trả lỗi 503. |
| `qr_label_printing_enabled`| Global, Workstation | **ON** | Bật in tem QR. |
| `b24_routing_enabled` | Global, Instance | **OFF** | Bật tự động tính toán phân vùng kho B24. Khi OFF, bỏ qua bước 4 trong Confirm Row và không in thông tin kho B24 lên tem. |
| `b24_d1_fix_enabled` | Global | **OFF (không còn cần thiết)** | **CẬP NHẬT 2026-07-17 (CH-BUS-012 RESOLVED):** đã xác nhận VBA gốc KHÔNG có lỗ hổng D1 cho VD14-16+3C/4D (lỗi transcription của đợt audit trước) — nhánh D1 đúng đã implement thẳng trong `WarehouseRoutingService` không cần flag riêng. Giữ lại flag này ở trạng thái không dùng (no-op) để không phá vỡ tương thích, có thể dọn bỏ ở đợt refactor sau. |
| `manual_routing_review_enabled`| Global, Instance | **ON** | Khi bật, các trường hợp thiếu quy tắc route B24 rõ ràng (hoặc rơi vào lỗ hổng D1 khi flag D1 đang OFF) sẽ dừng lại ở trạng thái chờ QA duyệt thủ công, không tự route. |
| `local_print_agent_enabled`| Workstation, Instance| **OFF** | Bật in qua Local Print Agent thay vì in thủ công qua trình duyệt/mạng nội bộ. |
| `local_scale_agent_enabled`| Workstation, Instance| **OFF** | Bật đọc cân qua Local Scale Agent. Phải giữ OFF cho đến khi vá xong thuật toán PB-1 (lấy số cuối) và PB-2 (bộ lọc ổn định). |
| `record_correlation_enabled`| Global | **OFF** | Bật cơ chế tự động đối chiếu dữ liệu `RECORD_A` và `RECORD_B` (chờ đóng CH-BUS-013). |
| `legacy_dual_write_enabled`| Global | **OFF** | Cho phép ghi song song xuống database Access legacy qua adapter. **Mặc định OFF vĩnh viễn** trừ khi được phê duyệt và đã đánh giá rủi ro khóa file Access. |
| `production_order_enabled` | Global, Workstation | **ON** | Bật domain Production Order — đã có nền tảng (`MachineDispatchController`), rủi ro thấp hơn 2 domain mới. |
| `small_scale_enabled` | Global, Instance | **ON** | Bật riêng từng instance (SMALL_SCALE_01 độc lập SMALL_SCALE_02) — cho phép pilot 1 máy trước, giữ máy kia chạy VBA. |
| `large_scale_enabled` | Global, Instance | **ON** | — |
| `remote_operation_enabled` | Global, Role | **OFF** | Bật `REMOTE_OPERATE` cho các permission có hậu tố `.remote` (xem `permission-matrix.md` Mục 1.1) — mặc định TẮT, mọi thao tác nghiệp vụ bắt buộc `LOCAL_OPERATE` cho tới khi xác nhận nghiệp vụ cần duyệt từ xa. |
| `device_test_enabled` | Workstation Instance, Device | **ON** (chỉ cho Admin/Kỹ sư qua permission `device.test`, không phải bật cho Operator) | Cho phép nút "Test device" ở `/admin/workstations` (`menu-workstation-device-architecture.md` Mục 10) — test KHÔNG tạo dữ liệu nghiệp vụ thật (in tem test/đọc cân thử, không ghi `dispatch_events`/`weighing_samples`). |

**Bảng trên đã bổ sung đủ scope `Device`** (ví dụ `device_test_enabled` áp cấp Device để tắt riêng 1 thiết bị lỗi mà không tắt cả Workstation Instance) — khớp yêu cầu 4 cấp Global→Type→Instance→Device.

---

## 2.1. Quy tắc ưu tiên resolve (mục 3.2 yêu cầu)

**Thứ tự:** `Device override → Workstation Instance → Workstation Type → Global default`. Cấp cụ thể hơn LUÔN thắng nếu có giá trị được set tường minh (không phải "kế thừa ngầm").

| Câu hỏi | Trả lời thiết kế |
|---|---|
| `false` có luôn thắng không? | **KHÔNG mặc định.** Ưu tiên theo *cấp cụ thể nhất có set giá trị*, không phải theo giá trị `false`/`true`. Ví dụ: Global=`false`, Instance=`true` tường minh → kết quả `true` tại instance đó. Ngoại lệ: `legacy_dual_write_enabled` có "hard OFF" ở tầng code (không đọc override ở cấp nào) cho tới khi có phê duyệt riêng — đây là 1 cờ đặc biệt, không theo quy tắc chung. |
| Có force-disable khẩn cấp không? | **Có** — đề xuất `emergency_disable` (Boolean riêng, KHÔNG phải 1 flag thường) ở cấp Global, khi bật sẽ ghi đè TẤT CẢ flag về OFF bất kể cấu hình cấp thấp hơn, dùng cho sự cố (vd. phát hiện bug nghiêm trọng khi pilot) — chỉ Admin có quyền `feature_flag.manage` mới bật/tắt được. |
| Cache và thời gian áp dụng | Backend cache flag resolve trong 60 giây (Redis/in-memory), Agent pull qua heartbeat (Mục 4) — độ trễ áp dụng tối đa ~60s cho Web, tối đa 1 chu kỳ heartbeat cho Agent (đề xuất heartbeat 15-30s). `emergency_disable` bỏ qua cache, áp dụng ngay (invalidate cache khi set). |
| Audit khi thay đổi | Mọi thay đổi flag (bất kể cấp) ghi `AUDIT_LOG` action=`FEATURE_FLAG_CHANGED` với `before_data`/`after_data` (scope, giá trị cũ/mới, actor, lý do bắt buộc nếu là flag rủi ro cao như `legacy_dual_write_enabled`). |
| Ai có quyền thay đổi | `feature_flag.manage` (chỉ Admin theo `permission-matrix.md` Mục 2.2) — không có quyền tự thay đổi cấp User/Instance cho chính mình. |

## 2.2. Hành vi khi OFF (mục 3.3 yêu cầu — không chỉ ẩn nút frontend)

| Khía cạnh | Thiết kế |
|---|---|
| Menu ẩn hay chỉ disable? | **Disable + thông báo lý do**, KHÔNG ẩn hoàn toàn — Operator vẫn thấy module tồn tại (đúng cấu trúc 5 workstation type cố định ở `menu-workstation-device-architecture.md`) nhưng nút hành động bị khóa kèm tooltip lý do (vd. "CHEMICAL_CALL đang bảo trì"). Ẩn hoàn toàn dễ gây hiểu lầm "chức năng không tồn tại". |
| Backend phản hồi mã lỗi nào? | `403 FEATURE_DISABLED` kèm `{flag_key, scope}` trong response body — KHÔNG dùng `404`/`500` (dễ nhầm lỗi hệ thống) |
| Agent có tiếp tục nhận command không? | **KHÔNG** — Agent kiểm tra flag tương ứng (`local_print_agent_enabled`/`local_scale_agent_enabled`) mỗi heartbeat; nếu OFF, Agent chuyển chế độ chờ (không poll print job/không gửi sample mới), nhưng **vẫn duy trì heartbeat** để Admin thấy Agent còn sống |
| Job đang chạy xử lý thế nào? | Job đã ở giữa luồng (vd. `print_jobs.status=PRINTING`) được phép **hoàn tất** (không hủy giữa chừng) — flag OFF chỉ chặn job MỚI, không cắt ngang job đang xử lý dở, tránh mất dữ liệu/in dở dang |
| Dữ liệu đang thao tác có bị mất không? | **KHÔNG** — mọi bảng giao dịch (Mục `erd-target.md`) là append-only/có audit, tắt flag không xóa/rollback dữ liệu đã ghi, chỉ chặn thao tác mới |
| Có cần graceful shutdown không? | **Có, cho Agent** — khi nhận flag OFF, Agent hoàn tất job hiện tại, báo cáo trạng thái cuối cùng, rồi mới vào chế độ chờ (không ngắt kết nối đột ngột giữa lệnh in/đang đọc cân) |

---

## 3. Quản Lý Trạng Thái Cho Các Chế Độ Kho B24 (Routing Modes)

Logic Warehouse Routing cho kho B24 được cấu hình bằng tổ hợp 3 flag `b24_routing_enabled`, `b24_d1_fix_enabled` và `manual_routing_review_enabled`. Ba chế độ hoạt động tương ứng gồm:

1. **LEGACY_EXACT (Giữ nguyên lỗi của VBA cũ):**
   - Cấu hình: `b24_routing_enabled = true`, `b24_d1_fix_enabled = false`, `manual_routing_review_enabled = false`.
   - Kết quả: Route y hệt VBA cũ kể cả khi thiếu quy tắc D1 (để trống hoặc sinh warning).
2. **FIXED_D1 (Áp dụng quy tắc D1 đã vá):**
   - Cấu hình: `b24_routing_enabled = true`, `b24_d1_fix_enabled = true`, `manual_routing_review_enabled = false/true`.
   - Kết quả: Áp dụng quy tắc D1 khi khớp mã hàng/tank.
3. **MANUAL_REVIEW (Mặc định cho Pilot - Đứng chờ duyệt):**
   - Cấu hình: `b24_routing_enabled = true`, `b24_d1_fix_enabled = false`, `manual_routing_review_enabled = true`.
   - Kết quả: Rơi vào lỗ hổng VD14–VD16 + 3C/4D mà thiếu D1 → Chuyển trạng thái dòng sang `needs_manual_review = true`, gửi cảnh báo lên Dashboard và chặn in nhãn tự động cho đến khi Shift Leader duyệt kho đích.

---

## 4. API Tải Cấu Hình Flag Cho Local Agent

Local Agent sẽ định kỳ kéo (pull) trạng thái feature flag của mình qua endpoint heartbeat:
- **Request:** `POST /api/agent/heartbeat` `{ device_id }`
- **Response:**
  ```json
  {
    "device_id": "uuid-1234",
    "feature_flags": {
      "local_scale_agent_enabled": false,
      "stable_filter_count": 2,
      "scale_algorithm_version": "v1.0"
    }
  }
  ```
- Nếu `local_scale_agent_enabled` trả về `false`, Agent sẽ hoạt động ở chế độ giả lập (Simulation / Read file log tĩnh) hoặc tạm dừng gửi sample lên server để tránh làm nhiễu dữ liệu sản xuất thật trong thời gian chưa sẵn sàng.
