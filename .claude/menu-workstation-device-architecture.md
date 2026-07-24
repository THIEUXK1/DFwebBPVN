# Kiến trúc Menu Vận hành theo Workstation Type & Quản lý Thiết bị theo Workstation Instance (menu-workstation-device-architecture.md)

Lập 2026-07-17 — Phase C (Target Design), tiếp nối `domain-architecture.md` Mục 1.1 và `erd-target.md` Mục 2.1 (schema vật lý đầy đủ nằm ở đó, tài liệu này KHÔNG định nghĩa lại bảng, chỉ tham chiếu theo tên). Đây là tài liệu THIẾT KẾ — chưa code sản xuất, chưa migration, chưa đổi schema thật.

---

## 1. Mục tiêu — Menu tổ chức theo 5 Workstation Type, không theo máy vật lý

```
VẬN HÀNH
├── Gọi hóa chất [Đang xác minh] → CHEMICAL_CALL (Isolated - BLOCKED_BY_BUSINESS_CONFIRMATION)
├── Tạo đơn sản xuất       → PRODUCTION_ORDER
├── Nhận đơn & In tem QR   → QR_LABEL_PRINTING
├── Trạm cân nhỏ           → SMALL_SCALE   (1 module, quản lý 2 máy bằng workstation instance)
└── Trạm cân lớn           → LARGE_SCALE
```

> [!WARNING]
> **CHEMICAL_CALL** tạm thời được gỡ bỏ khỏi luồng tích hợp chung và hiển thị nhãn "Đang xác minh luồng nhận" trên Menu. Việc này đảm bảo không làm gián đoạn thử nghiệm (pilot) của 4 phân hệ còn lại trong khi chờ chốt blocker **`CH-BUS-015`**.

**Không tạo menu riêng cho từng máy vật lý.** `SMALL_SCALE_01` và `SMALL_SCALE_02` dùng chung 1 route/1 bộ màn hình Vue (`/operations/small-scale`) — khác biệt hoàn toàn nằm ở **dữ liệu runtime** (workstation instance đang đăng nhập), không phải ở code hay route.

---

## 2. Ba tầng bắt buộc phân biệt

```
Workstation Type  (5 giá trị cố định — app.workstation_types)
        │
        ▼
Workstation Instance  (6 dòng thật — app.workstations, vd. SMALL_SCALE_01, SMALL_SCALE_02)
        │
        ▼
Device  (nhiều thiết bị/instance — app.devices, qua app.workstation_devices)
        │
        ▼
Agent + Hardware  (Local Agent chạy tại instance, giao tiếp Printer/Scale thật)
```

| Tầng | Bảng | Ví dụ | Đặc điểm |
|---|---|---|---|
| Workstation Type | `app.workstation_types` | `SMALL_SCALE` | Quy định **code chạy gì** (route/màn hình/API nào), 5 giá trị cố định |
| Workstation Instance | `app.workstations` | `SMALL_SCALE_01`, `SMALL_SCALE_02` | Quy định **dữ liệu/thiết bị/job nào** — 2 máy cùng type, **hoàn toàn độc lập** về dữ liệu, trạng thái, thiết bị, lịch sử |
| Device | `app.devices` qua `app.workstation_devices` | `Scale_01` gắn `SMALL_SCALE_01` | Phần cứng thật, gắn 1-N vào 1 workstation instance qua bảng mapping có `role` |

**Hệ quả thiết kế quan trọng nhất:** mọi entity giao dịch (chemical call request, production order, dispatch job, weighing job) đều có cột `workstation_id` trỏ tới **Workstation Instance**, KHÔNG trỏ tới `workstation_type_id`. Truy vấn "lấy dữ liệu của SMALL_SCALE" luôn phải qua instance cụ thể — không có khái niệm "dữ liệu chung của cả loại SMALL_SCALE".

---

## 3. Chống anti-pattern: không hard-code theo IP/tên máy

**Không làm** (đúng như VBA cũ và đúng cảnh báo của yêu cầu):
```
if (ip == "10.0.19.74") { mở màn hình cân }
if (computerName == "PC-CAN-01") { load config trạm A }
```

**Phải làm** — request tới `/operations/{type-slug}` (vd. `/operations/small-scale`) luôn resolve theo chuỗi:

```
Session/Token của user đăng nhập trạm
        │
        ▼
workstation_id (đã bind cứng lúc Admin thiết lập trạm — app.workstation_sessions, ĐÃ CÓ)
        │
        ▼
workstation_type (JOIN app.workstation_types) → chọn đúng bộ UI/API
        │
        ▼
device_binding (JOIN app.workstation_devices → app.devices/printers/scale_devices)
        │
        ▼
user_permission (JOIN app.user_roles, xem permission-matrix.md)
        │
        ▼
feature_flag (theo scope Global→Type→Instance→Device, xem Mục 8)
```

IP/hostname (`app.devices.ip_address`/`hostname`) **chỉ là thuộc tính hiển thị/chẩn đoán** trong màn hình Admin, không bao giờ xuất hiện trong logic định tuyến hay logic nghiệp vụ.

---

## 4-5. Schema Workstation/Device — tham chiếu

Đã định nghĩa đầy đủ tại `erd-target.md` Mục 2.1: `app.workstation_types`, `app.workstations`, `app.devices`, `app.workstation_devices`, `app.device_heartbeats`, `app.device_events`. Dữ liệu mẫu 6 workstation instance đúng theo baseline:

| id | code | workstation_type_id |
|---|---|---|
| 1 | `CHEMICAL_CALL_01` | CHEMICAL_CALL |
| 2 | `PRODUCTION_ORDER_01` | PRODUCTION_ORDER |
| 3 | `QR_LABEL_PRINTING_01` | QR_LABEL_PRINTING |
| 4 | `SMALL_SCALE_01` | SMALL_SCALE |
| 5 | `SMALL_SCALE_02` | SMALL_SCALE |
| 6 | `LARGE_SCALE_01` | LARGE_SCALE |

---

## 6. Quản lý máy in — chuyển hóa VBA thành cấu hình (bắt buộc)

VBA gốc hard-code: tên máy tính, Windows Printer, TSC printer, template, khổ giấy, landscape, "15L special" (xem `b24-warehouse-routing.md` Mục 7 — xác nhận KHÔNG tìm thấy nhánh code riêng, nên KHÔNG tạo `printer_profile` riêng cho "15L" chỉ vì tên file, đúng nguyên tắc CH-BUS-011).

Schema đầy đủ: `erd-target.md` Mục 2.1 — `app.printers`, `app.printer_profiles`, `app.workstation_printers`. Ví dụ dữ liệu mục tiêu:

```
QR_LABEL_PRINTING_01
   └─ printer: TSC224_01 (device_type=PRINTER)
       └─ default_printer_profile: QR_LABEL_STANDARD (template_key trỏ TSPL template thật, orientation=LANDSCAPE theo tên workbook gốc)
```

**Không để `PrinterName = "TSC224"` trong code** — `PrintJobService` (đã đề xuất ở `domain-architecture.md` Mục 1.4) đọc `workstation_printers` theo `workstation_id` hiện tại để lấy `printer_id`+`default_printer_profile_id`, không nhận tên máy in từ tham số cứng.

---

## 7. Khi mở menu QR_LABEL_PRINTING — luồng load cấu hình tự động

```
Admin/User mở QR_LABEL_PRINTING
        ↓
Chọn/đã bind workstation instance (QR_LABEL_PRINTING_01)
        ↓
Backend load 1 lần: Printer (workstation_printers) + Template (printer_profiles)
                   + Agent (workstation_devices role=AGENT) + Feature flag (Mục 8) + Queue (dispatch_jobs theo workstation_id)
        ↓
Mở màn hình vận hành — KHÔNG hỏi lại người dùng chọn máy in mỗi lần
```

Đây chính là lý do `app.workstation_printers` có `priority` — nếu printer chính OFFLINE (`app.printers.status`), hệ thống tự chuyển printer dự phòng theo priority tiếp theo mà KHÔNG cần thao tác tay, chỉ cảnh báo (đúng tinh thần "Admin xem được workstation → device → status" ở Mục 10).

---

## 8. Quản lý cân — chuyển hóa VBA thành cấu hình

VBA gốc hard-code COM port/baud rate trong `ModRead_putty_log.InitPutty` (`D:\SCALE\putty_log.txt`, không phải COM trực tiếp thực ra — ghi chú: VBA đọc qua file log trung gian do Putty ghi, không mở COM port trực tiếp trong VBA; Local Agent theo thiết kế mới thay thế bằng đọc Serial/TCP trực tiếp, xem `local-agent-architecture.md` Mục 2). Schema `app.scale_devices` (xem `erd-target.md`) thay cho hard-code này — mapping workstation↔scale dùng lại `app.workstation_devices` (không tạo bảng mapping thứ 2 song song, tránh 2 nguồn sự thật).

```
SMALL_SCALE_01 → workstation_devices(role=PRIMARY, device_type=SCALE) → Scale_01 → scale_devices(port=COM3, baud_rate=9600)
SMALL_SCALE_02 → workstation_devices(role=PRIMARY, device_type=SCALE) → Scale_02 → scale_devices(port=COM4, baud_rate=9600)
```

---

## 9. Local Agent — 1 agent/workstation instance

```
QR_LABEL_PRINTING_01 → Agent: PRINT_AGENT_01 (device_type=LOCAL_AGENT, workstation_devices.role=AGENT)
SMALL_SCALE_01        → Agent: SCALE_AGENT_01
SMALL_SCALE_02        → Agent: SCALE_AGENT_02   -- Agent RIÊNG cho từng instance, KHÔNG dùng chung 1 agent cho 2 máy
LARGE_SCALE_01         → Agent: SCALE_AGENT_03
```

Agent chịu trách nhiệm giao tiếp máy in/cân, heartbeat, gửi trạng thái, nhận command — contract đầy đủ đã có ở `local-agent-architecture.md` Mục 4. **Trình duyệt tuyệt đối không giao tiếp trực tiếp phần cứng** (đúng CLAUDE.md mục 5 và nguyên tắc đã áp dụng xuyên suốt).

---

## 10. Giao diện Admin — `/admin/workstations`

| Workstation | Type | Device | Status |
|---|---|---|---|
| Chemical 01 | CHEMICAL_CALL | PC | Online |
| Order 01 | PRODUCTION_ORDER | PC | Online |
| Print 01 | QR_LABEL_PRINTING | TSC224 | Online |
| Scale 01 | SMALL_SCALE | Scale01 | Online |
| Scale 02 | SMALL_SCALE | Scale02 | Offline |
| Large 01 | LARGE_SCALE | Scale03 | Online |

Mỗi dòng có action: **Mở vận hành** (deep-link `/operations/{type-slug}?workstation={id}`, chỉ Admin dùng để kiểm tra — user vận hành thật không cần chọn, xem Mục 11), **Xem thiết bị** (drill-down `workstation_devices`), **Xem log** (`device_events`+`device_heartbeats`), **Cấu hình** (sửa `workstation_printers`/`scale_devices`), **Test device** (gửi lệnh test qua Agent, không phải lệnh nghiệp vụ thật — vd. in tem test, đọc cân thử).

---

## 11. Giao diện vận hành — không hỏi lại thiết bị

`GET /operations/small-scale` (route theo **type-slug**, không theo instance) → Backend xác định `current_workstation` (từ session đã bind, KHÔNG từ query param nhập tay) → `device_binding` → `load configuration` → render. **Không** hiển thị dropdown chọn máy cân/máy in/COM port/printer cho Operator — những lựa chọn đó đã cố định 1 lần bởi Admin ở `/admin/workstations`.

---

## 12. Feature Flag theo 4 cấp scope

Mở rộng `local-agent-architecture.md` Mục 5 (danh sách flag) với **scope hierarchy**: `Global → Workstation Type → Workstation Instance → Device`, override theo thứ tự cụ thể nhất thắng.

```
qr_print_enabled
  Global: true
  └─ QR_LABEL_PRINTING (type): true
      └─ QR_LABEL_PRINTING_01 (instance): true
          └─ Printer_TSC224_01 (device): true   -- có thể tắt riêng 1 printer khi bảo trì mà không tắt cả trạm
```

Đề xuất bảng `app.feature_flags` (đã nêu sơ bộ ở `local-agent-architecture.md` Mục 5) bổ sung cột `scope_type varchar(20)` (`GLOBAL`/`WORKSTATION_TYPE`/`WORKSTATION_INSTANCE`/`DEVICE`) + `scope_ref_id` (nullable, trỏ tới `workstation_type_id`/`workstation_id`/`device_id` tùy `scope_type`) thay vì 4 bảng flag riêng biệt — tránh trùng lặp cấu trúc.

---

## 13. Mapping VBA → Web (bổ sung cột "Menu/Device" cho bảng đã có ở `vba-migration-matrix.md`)

| VBA | Web |
|---|---|
| Computer name (`Environ$("COMPUTERNAME")`, ngầm định trong path cứng) | `app.workstations.code` (Workstation Instance) |
| IP (lịch sử mạng, `workstation-matrix.md` Mục 4) | `app.devices.ip_address` — **chỉ là thuộc tính**, không định tuyến |
| Windows Printer (`Application.ActivePrinter`) | `app.printers` + `app.workstation_printers` |
| COM port (`Serial`/Putty log path) | `app.scale_devices.port`/`baud_rate` |
| UserForm (`chem_order.frm`, `TO_SEND.frm`, `scaleform.frm`...) | Web page (`ChemicalCallStation.vue`, `MachineQueue.vue`, `WeighingStation.vue`...) |
| Button event (`btn_..._Click`) | API action (`POST /api/.../...`, xem `api-contracts.md`) |
| Module VBA (`Mod_printslip`, `ModRead_putty_log`...) | Service/Domain (`WarehouseRoutingService`, `ScaleAgent.ScaleCore`...) |
| Excel config (hằng số path, tên máy in cứng trong code) | Database config (`workstation_printers`, `scale_devices`, `printer_profiles`) |

---

## 14. Không migrate lỗi legacy (nhắc lại có chủ đích, đối chiếu `local-agent-architecture.md` Mục 1)

Không giữ: hard-code tên máy in trong code; hard-code IP; hard-code COM port trong code (phải nằm trong `scale_devices`, không phải constant); điều khiển chuột (`ModAPI_mouse.ClickAt`/`SendTextToApp` — đã xác nhận DEPRECATED_CONFIRMED toàn bộ ở các đợt audit trước); clipboard automation; timer loop không kiểm soát (`Mod_lockmoveform` bug của LARGE_SCALE — đã ghi `NOT_MIGRATED_LEGACY_BUG` ở `local-agent-architecture.md` Mục 1). Thay bằng: API, Agent, Queue, Event, Config database — đúng danh sách đối chiếu.

---

## 15. Test bắt buộc (bổ sung cho `test-architecture.md`)

**Test menu:** chọn từng type trong 5 loại → đúng route/màn hình tương ứng, không lẫn.

**Test thiết bị:** Printer online/offline/retry/duplicate-print/reprint (đã có trong `state-machines.md` Mục 4 + `api-contracts.md`); Scale online/mất kết nối/sample duplicate/agent reconnect (đã có trong `state-machines.md` Mục 5).

**Test 2 máy SMALL_SCALE (mới, quan trọng nhất của yêu cầu này):**
```
SMALL_SCALE_01 và SMALL_SCALE_02 phải:
  - Cùng chạy chung 1 bộ code/route (/operations/small-scale)
  - Khác workstation_id (instance riêng)
  - Khác device (Scale_01 ≠ Scale_02, Agent riêng)
  - Khác job/lịch sử (weighing_jobs.workstation_id lọc đúng)
  - Không nhận nhầm dữ liệu: sample gửi từ Scale_02 KHÔNG được xuất hiện trong job đang mở tại SMALL_SCALE_01
    (kiểm chứng bằng UNIQUE(device_id, sequence_no) + device_id luôn gắn đúng 1 workstation_id tại 1 thời điểm)
```
Đề xuất test case cụ thể: `WorkstationDeviceIsolationTest` — mở đồng thời 2 phiên tại 2 instance, gửi sample cùng lúc, assert mỗi `weighing_job` chỉ nhận sample từ đúng `device_id` đã bind.

---

## 16. Tiêu chí nghiệm thu (thiết kế — chưa implement, đối chiếu Phase E)

- [ ] Menu Vận hành có đúng 5 workstation type, không menu rời rạc theo máy.
- [ ] SMALL_SCALE không bị nhân đôi menu (1 route, 2 instance).
- [ ] Máy in quản lý bằng `workstation_printers` (device mapping), không hard-code.
- [ ] Máy cân quản lý bằng `scale_devices`+`workstation_devices`, không hard-code COM.
- [ ] Không còn hard-code IP/printer/COM trong bất kỳ thiết kế nào ở trên (đã rà soát toàn bộ tài liệu này — PASS).
- [ ] Admin xem được Workstation → Device → Status (`/admin/workstations`, Mục 10).
- [ ] Người vận hành mở đúng chương trình mà không chọn thiết bị thủ công (Mục 11).
- [ ] VBA function tương đương đầy đủ — đối chiếu `vba-migration-matrix.md`/domain gap report liên quan (`chemical-call-domain.md`, `qr-label-printing-domain.md`, `local-agent-architecture.md`).
- [ ] Traceability cập nhật (xem cập nhật cuối tài liệu này trong `session-log.md`).
- [ ] Test Mục 15 PASS (khi tới Phase E, hiện chỉ là thiết kế test).

**Đây vẫn là tài liệu THIẾT KẾ — chưa có tiêu chí nào ở trên được thực thi/kiểm chứng thật, vì chưa sang Phase E.**
