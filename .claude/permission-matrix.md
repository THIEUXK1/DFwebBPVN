# Permission Matrix — Bảng Phân Quyền Chi Tiết (permission-matrix.md)

Lập 2026-07-17 — Phase C. Thiết kế ma trận phân quyền chi tiết, không chỉ dùng vai trò (role) chung mà phân rã theo quyền thao tác cụ thể (permission/action-level). Đây là tài liệu thiết kế hệ thống đích, không sửa code sản xuất.

---

## 1. Định nghĩa Vai trò (Roles) trong Hệ thống Đích

Hệ thống phân quyền dựa trên vai trò (RBAC - Role-Based Access Control) kết hợp kiểm tra phân trạm (Workstation Enforcement). Các vai trò chính gồm:

1. **System Administrator (Admin):** Quản trị hệ thống, có toàn quyền cấu hình trạm, thiết bị, quản lý người dùng và cấu hình feature flag.
2. **Shift Leader / QA (Trưởng ca / QA):** Phê duyệt đơn hàng, ghi nhận lý do override dung sai cân, giải phóng khóa (force release lock) và duyệt phân vùng kho thủ công khi cần.
3. **Operator (Vận hành viên):** Người trực tiếp thao tác tại các máy trạm. Tùy theo công việc, vận hành viên được phân vào nhóm nghiệp vụ tương ứng (CHEMICAL_CALL, PRODUCTION_ORDER, QR_LABEL_PRINTING, SMALL_SCALE, LARGE_SCALE).
4. **Local Agent (Tài khoản Thiết bị / Service Account):** Tài khoản dành riêng cho phần mềm Local Agent (Scale Agent, Print Agent) cài tại máy trạm. **Tuyệt đối không cấp quyền người dùng cho Agent** (như tạo đơn, phê duyệt, reset kênh hóa chất...).

---

## 1.1. Chế độ truy cập (Operation Mode) — bổ sung 2026-07-17 theo yêu cầu mục 2.1

Không mặc định Admin được thực hiện mọi thao tác nghiệp vụ — Admin có quyền `ADMIN_CONFIGURE`/`DEVICE_SERVICE` (cấu hình hệ thống), KHÔNG tự động có quyền nghiệp vụ hàng ngày (`chemical_call.create`, `production_order.approve`...) trừ khi được gán riêng.

| Mode | Ý nghĩa | Ví dụ |
|---|---|---|
| `VIEW_ONLY` | Chỉ xem, không thao tác nghiệp vụ | Admin/Auditor xem job đang chạy tại workstation khác |
| `LOCAL_OPERATE` | Thao tác nghiệp vụ tại chính workstation đã bind (đúng session, đúng thiết bị) | Operator bấm ORDER tại CHEMICAL_CALL_01 — **mode mặc định cho mọi permission nghiệp vụ** |
| `REMOTE_OPERATE` | Thao tác nghiệp vụ từ xa (không phải workstation đã bind) — mặc định TẮT, cần permission `*.remote` riêng + feature flag `remote_operation_enabled` | Trưởng ca duyệt đơn (`production_order.approve`) từ máy văn phòng |
| `ADMIN_CONFIGURE` | Cấu hình hệ thống (workstation/device/printer/scale/feature flag) | Gán printer cho QR_LABEL_PRINTING_01 |
| `DEVICE_SERVICE` | Thao tác kỹ thuật thiết bị (test print, test scale) — KHÔNG tạo dữ liệu nghiệp vụ thật | Bấm "Test device" ở `/admin/workstations` (`menu-workstation-device-architecture.md` Mục 10) |

## 2. Ma Trận Phân Quyền (Permission Matrix)

Dưới đây là ma trận ánh xạ giữa các quyền cụ thể và vai trò tương ứng:

| Nhóm nghiệp vụ | Quyền (Permission Code) | Admin | Shift Leader / QA | Operator (CC/PO/QR) | Operator (Scale) | Local Agent |
|---|---|:---:|:---:|:---:|:---:|:---:|
| **Chemical Call** | `chemical_call.view` | ✓ | ✓ | ✓ | | |
| | `chemical_call.create` | ✓ | ✓ | CC only | | |
| | `chemical_call.complete` | ✓ | ✓ | CC only | | |
| | `chemical_call.cancel` | ✓ | ✓ | CC only | | |
| | `chemical_call.reset` | ✓ | | | | |
| **Production Order**| `production_order.create` | ✓ | ✓ | PO only | | |
| | `production_order.approve`| ✓ | ✓ | | | |
| | `production_order.lock_override` | ✓ | ✓ | | | |
| **Dispatch / QR** | `dispatch.view` | ✓ | ✓ | QR only | | |
| | `dispatch.confirm` | ✓ | | QR only | | |
| | `warehouse_routing.manual`| ✓ | ✓ | | | |
| **Printing** | `print.execute` | ✓ | | QR only | | |
| | `print.reprint` | ✓ | ✓ | | | |
| | `print.cancel` | ✓ | ✓ | QR only | | |
| **Weighing** | `weighing.small_scale` | ✓ | | | Small only | |
| | `weighing.large_scale` | ✓ | | | Large only | |
| | `weighing.override_tolerance` | ✓ | ✓ | | | |
| **Device Admin** | `device.register` | ✓ | | | | |
| | `device.heartbeat` | | | | | ✓ |
| | `device.push_samples` | | | | | Scale Agent |
| | `device.report_status` | | | | | Print/Scale |
| | `device.admin_control` | ✓ | | | | |
| **Audit & History** | `audit.view` | ✓ | ✓ | | | |
| | `audit.export` | ✓ | | | | |

### 2.1. Danh sách permission đầy đủ theo domain (đối chiếu chính xác yêu cầu)

**CHEMICAL_CALL:** `chemical_call.view`, `chemical_call.create`, `chemical_call.acknowledge` *(mới — tùy chọn, mặc định OFF, khớp trạng thái ACKNOWLEDGED tùy chọn ở `state-machines.md` Mục 1)*, `chemical_call.complete`, `chemical_call.cancel`, `chemical_call.reset`, `chemical_call.view_history`.

**PRODUCTION_ORDER:** `production_order.view`, `production_order.create`, `production_order.update`, `production_order.approve`, `production_order.cancel`, `production_order.dispatch`, `production_order.override_lock`.

**QR_LABEL_PRINTING:** `dispatch.view_queue`, `dispatch.confirm`, `dispatch.manual_route`, `qr.preview` *(ứng với `GET /api/dispatch-jobs/{id}/preview-payload` — B. UX IMPROVEMENT, `api-contracts.md`)*, `print.create`, `print.retry`, `print.reprint`, `print.cancel`, `print.view_history`.

**SMALL_SCALE / LARGE_SCALE:** `weighing.view`, `weighing.scan_qr`, `weighing.accept`, `weighing.reject`, `weighing.complete`, `weighing.override_tolerance`, `weighing.manual_input` *(nhập tay fallback khi máy quét lỗi — đã có tiền lệ WS-001→012 theo `workstation-redesign-audit.md`)*, `weighing.view_samples`.

**Device/Admin:** `workstation.view`, `workstation.configure`, `device.view`, `device.configure`, `device.test`, `agent.manage`, `feature_flag.manage`, `audit.view`, `audit.export`.

**Correlation (bổ sung — dùng bởi `record-a-record-b-correlation.md` Mục 4):** `correlation.view` (đi kèm `audit.view`), `correlation.manual_link`, `correlation.reject` — mặc định chỉ QA/Admin (Mục 2.2).

### 2.2. Ma trận Role × Permission (rút gọn — đủ để phân biệt Operator theo domain, khớp Mục 2 bảng trên với tên permission đầy đủ ở 2.1)

| Permission | Operator | Trưởng ca | QA/QC | Kỹ sư | Admin | Auditor |
|---|---|---|---|---|---|---|
| `chemical_call.view/create/acknowledge` | LOCAL_OPERATE (CC) | LOCAL_OPERATE | — | — | VIEW_ONLY | VIEW_ONLY |
| `chemical_call.complete` | LOCAL_OPERATE (CC) | LOCAL_OPERATE | — | — | — | — |
| `chemical_call.cancel` | — | LOCAL_OPERATE | — | — | ADMIN_CONFIGURE | — |
| `chemical_call.reset` | — | — | — | — | ADMIN_CONFIGURE (flag OFF mặc định) | — |
| `chemical_call.view_history` | LOCAL_OPERATE | LOCAL_OPERATE | VIEW_ONLY | — | VIEW_ONLY | VIEW_ONLY |
| `production_order.view/create/update` | LOCAL_OPERATE (PO) | LOCAL_OPERATE | — | — | VIEW_ONLY | VIEW_ONLY |
| `production_order.approve` | — | LOCAL_OPERATE/REMOTE_OPERATE* | LOCAL_OPERATE | — | — | — |
| `production_order.cancel` | LOCAL_OPERATE (PO) | LOCAL_OPERATE | — | — | — | — |
| `production_order.dispatch` | LOCAL_OPERATE (PO) | LOCAL_OPERATE | — | — | — | — |
| `production_order.override_lock` | — | LOCAL_OPERATE | — | — | ADMIN_CONFIGURE | — |
| `dispatch.view_queue/confirm` | LOCAL_OPERATE (QR) | LOCAL_OPERATE | — | — | VIEW_ONLY | VIEW_ONLY |
| `dispatch.manual_route` | — | LOCAL_OPERATE | LOCAL_OPERATE | LOCAL_OPERATE | — | — |
| `qr.preview` | LOCAL_OPERATE (QR) | LOCAL_OPERATE | — | — | VIEW_ONLY | — |
| `print.create/retry` | LOCAL_OPERATE (QR) | LOCAL_OPERATE | — | — | — | — |
| `print.reprint` | — | LOCAL_OPERATE | LOCAL_OPERATE | — | ADMIN_CONFIGURE | — |
| `print.cancel` | — | LOCAL_OPERATE | — | — | ADMIN_CONFIGURE | — |
| `print.view_history` | LOCAL_OPERATE | LOCAL_OPERATE | VIEW_ONLY | — | VIEW_ONLY | VIEW_ONLY |
| `weighing.view/scan_qr/accept/reject/complete/view_samples` | LOCAL_OPERATE (Scale) | LOCAL_OPERATE | — | — | VIEW_ONLY | VIEW_ONLY |
| `weighing.override_tolerance` | — | LOCAL_OPERATE | LOCAL_OPERATE | — | — | — |
| `weighing.manual_input` | LOCAL_OPERATE (Scale) | LOCAL_OPERATE | — | — | — | — |
| `workstation.view/device.view` | — | LOCAL_OPERATE | — | LOCAL_OPERATE | VIEW_ONLY/ADMIN_CONFIGURE | VIEW_ONLY |
| `workstation.configure/device.configure` | — | — | — | — | ADMIN_CONFIGURE | — |
| `device.test` | — | — | — | DEVICE_SERVICE | DEVICE_SERVICE | — |
| `agent.manage/feature_flag.manage` | — | — | — | ADMIN_CONFIGURE | ADMIN_CONFIGURE | — |
| `audit.view` | — | LOCAL_OPERATE | LOCAL_OPERATE | — | VIEW_ONLY | VIEW_ONLY |
| `audit.export` | — | — | LOCAL_OPERATE | — | ADMIN_CONFIGURE | VIEW_ONLY |

*`production_order.approve` cho Trưởng ca mặc định `LOCAL_OPERATE`; chuyển sang cho phép `REMOTE_OPERATE` là quyết định nghiệp vụ cần xác nhận riêng (chưa bật mặc định).

### 2.3. User vs Role (phân biệt rõ theo yêu cầu)

Permission gán qua **Role** (RBAC chuẩn, Mục 1) là cơ chế chính. Trường hợp cần override cá nhân (vd. 1 Operator cụ thể tạm thời được cấp `weighing.override_tolerance` khi Trưởng ca vắng mặt) dùng **User-level permission override** — bảng `app.user_permission_overrides` (đề xuất mới: `user_id`, `permission_code`, `granted_by`, `expires_at`, `reason`) — có hạn dùng bắt buộc (`expires_at`), không cấp vĩnh viễn ở cấp User để tránh permission "rò rỉ" ra ngoài Role chuẩn.

*Ghi chú:*
- **CC only:** Chỉ vận hành viên trực tại trạm `CHEMICAL_CALL` mới có quyền thao tác.
- **PO only:** Chỉ vận hành viên trực tại trạm `PRODUCTION_ORDER` mới có quyền thao tác.
- **QR only:** Chỉ vận hành viên trực tại trạm `QR_LABEL_PRINTING` mới có quyền thao tác.
- **Small/Large only:** Chỉ vận hành viên tại trạm `SMALL_SCALE`/`LARGE_SCALE` tương ứng.
- **Scale Agent / Print Agent:** Quyền tối thiểu của Agent, chỉ được gọi endpoint quy định riêng cho thiết bị.

---

## 3. Quy Tắc Bảo Mật & Ràng Buộc Phân Quyền (Security Rules)

### 3.0. Tầng kiểm tra (route/middleware vs service) — bổ sung theo yêu cầu

| Tầng | Kiểm tra gì | Ví dụ |
|---|---|---|
| Route/Middleware | Permission code tĩnh (user có permission X không) — chặn sớm, không tốn transaction | `chemical_call.complete` chưa gán → 403 ngay tại middleware, không vào Controller |
| Service (nghiệp vụ) | Permission PHỤ THUỘC STATE hiện tại của entity — middleware không biết state | `dispatch.confirm` hợp lệ về permission nhưng job đang `SENT` (đã confirm rồi) → Service trả `409 ALREADY_CONFIRMED` (idempotent, không phải lỗi permission) |
| Service (workstation scope) | So `session.workstation_id` với `entity.workstation_id` | Operator SMALL_SCALE_01 có `weighing.accept` nhưng gọi lên job thuộc SMALL_SCALE_02 → 403 `WORKSTATION_SCOPE_MISMATCH` (xem 3.1) |
| Service (operation mode) | LOCAL_OPERATE mặc định; REMOTE_OPERATE cần permission `.remote` + flag `remote_operation_enabled` | — |

### 3.1. Ràng buộc chéo Workstation (Workstation-Enforced Security)
- Hệ thống backend bắt buộc kiểm tra IP/Token của workstation thực hiện cuộc gọi. Kể cả khi tài khoản người dùng có quyền `weighing.small_scale`, nếu họ thực hiện request từ một IP không thuộc workstation kiểu `SMALL_SCALE` (hoặc session token của workstation đó không khớp), backend sẽ trả về lỗi `403 Forbidden`.
- Điều này ngăn chặn việc Operator mang thiết bị cá nhân hoặc sang máy trạm khác để "thực hiện hộ" công đoạn mà không có mặt vật lý tại trạm quy định.

### 3.2. Cô lập Quyền của Local Agent (Local Agent Sandbox)
- Local Agent xác thực bằng phương thức **Device Key** (sinh ra sau khi register thành công bằng token 1 lần). Credentials của Agent được lưu ở bảng `app.devices`, không lưu ở bảng `app.users`.
- Quyền của Agent chỉ giới hạn ở: gửi heartbeat, đẩy dữ liệu thô (sample) lên hàng chờ, kéo print job được gán cho chính nó và báo cáo trạng thái in.
- **Tuyệt đối cấm:**
  - Agent gọi API nghiệp vụ: `/api/production-orders`, `/api/chemical-call-requests`, v.v.
  - Agent đọc dữ liệu của thiết bị khác (ví dụ: Scale Agent tại trạm 1 không được lấy danh sách print job của trạm 2).
  - Agent tự động duyệt dung sai hoặc override dữ liệu cân.

### 3.3. Quy trình Phê duyệt và Kiểm toán Override (Approval & Audit Controls)
- Khi cân lệch dung sai (`REJECTED`), hệ thống khóa màn hình và yêu cầu Shift Leader quét thẻ/đăng nhập để thực hiện `weighing.override_tolerance`.
- API `/api/weighing-job-items/{id}/override` bắt buộc truyền kèm `reason` (lý do override) và UUID của Shift Leader. Hệ thống ghi một bản ghi audit loại `WEIGH_OVERRIDDEN` chứa thông tin:
  - Giá trị cân thô (raw sample).
  - Người thực hiện (Shift Leader).
  - Trạm cân (Workstation).
  - Lý do override.
  - Timestamp.
