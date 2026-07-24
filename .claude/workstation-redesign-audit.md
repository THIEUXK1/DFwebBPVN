# Báo cáo Rà soát — Tái cấu trúc Giao diện theo Mô hình Workstation

Tài liệu này là **Bước 2 (Rà soát hệ thống hiện tại)** theo yêu cầu tái cấu trúc "1 máy tính = 1 công đoạn = 1 nhiệm vụ = 1 giao diện" (mô hình DF Connector & Scale).

> **Trạng thái triển khai (cập nhật 16/07/2026):** Toàn bộ **WS-001 → WS-012 đã hoàn thành**. Chi tiết ở `session-log.md` mục 12-13. 54 test backend (291 assertions) pass; UAT toàn bộ vòng đời 1 lô nhuộm qua 6 trạm bằng HTTP thật (không mock) đều đúng. **Giới hạn:** không có công cụ trình duyệt để tự quan sát UI trực tiếp — các thay đổi thuần giao diện (ẩn/hiện menu, redirect) chỉ verify được qua type-check/build/UAT dữ liệu thật qua API, khuyến nghị người dùng tự đăng nhập kiểm tra bằng mắt trước khi coi là hoàn tất 100%.

---

## 1. Kết luận nổi bật (đọc trước)

Hệ thống **đã có sẵn phần lớn nền tảng backend** cho mô hình workstation mà bạn mô tả — đây không phải là xây từ đầu:

- Bảng `app.workstations` đã tồn tại với **10 loại trạm** gần như khớp 1:1 với bảng WS-01→WS-10 bạn đưa ra (`ORDER_DESK`, `PRODUCTION_DISPATCH`, `DYE_WEIGHING`, `CHEMICAL_WEIGHING`, `A11_WEIGHING`, `DLG_WEIGHING`, `MATERIAL_TRANSFER`, `TANK_RECEIVING`, `MACHINE_FEEDING`, `MONITORING`).
- `ScannerController` đã hiện thực đúng luồng "quét QR → tự nạp dữ liệu, không cho nhập tay" cho cả 3 kịch bản: quét đơn (sinh nhiệm vụ cân từ công thức), quét tem vật tư (tạo vận chuyển), quét kép đối chiếu tại thùng (verify machine/tank khớp batch).
- `scannerService.ts` đã là một "Scanner Manager" hoạt động đúng nguyên lý bạn mô tả ở mục 7: bắt bàn phím wedge-scanner, tự nhận Enter, lọc theo tốc độ gõ, phát âm thanh, chống nhầm với gõ tay.
- 3 màn hình `WeighingStation.vue`, `MaterialTransfer.vue`, `FeedingMonitor.vue` **đã là màn hình chờ-quét chuyên biệt** (icon lớn, chữ lớn, "vui lòng quét QR để bắt đầu"), có nhánh hiển thị theo `currentWorkstation.type`.

**Khoảng cách thực sự nằm ở tầng điều hướng/khung giao diện (routing & layout), không phải ở nghiệp vụ lõi.** Chi tiết ở mục 4.

---

## 2. Rà soát Frontend (2.1)

| Hạng mục | Hiện trạng |
|---|---|
| Framework | Vue 3 + Vite 5 + TypeScript, chạy cổng `3001` |
| State | Pinia (chỉ dùng cho `auth` store) + các module reactive nhẹ (`workstation.ts`, `scanner.ts`, `realtime.ts`) — không dùng Vuex, không có store lớn |
| Router | `vue-router` 4, danh sách route **phẳng, không phân cấp theo workstation**; `beforeEach` chỉ kiểm tra `requiresAuth`, không có logic điều hướng theo loại trạm |
| Layout | `AppLayout.vue` — sidebar cố định + topbar, dùng chung cho **toàn bộ** 12 trang, không đổi theo workstation |
| Menu | 3 nhóm menu (**VẬN HÀNH**, **CÔNG NGHỆ**, **BÁO CÁO & SỰ CỐ**) hiển thị **toàn bộ 12 mục cho mọi người dùng**, không lọc theo `currentWorkstation.type` |
| Các trang hiện có | `Login`, `Dashboard` (`/`), `ProductionBatches`, `WeighingStation`, `MaterialTransfer`, `FeedingMonitor`, `MachineQueue`, `Materials`, `WaterConfigs`, `Recipes`, `Troubleshooting`, `Reports`, `AuditLogExplorer` |
| Component dùng chung | `AppLayout.vue`, `SvgIcon.vue`, `charts/SimpleBarChart.vue`, `charts/ParetoChart.vue` |
| Nhận diện user | Bearer token (Sanctum) lưu `localStorage`, gắn vào `axios.defaults.headers.common['Authorization']`, Pinia `auth` store giữ `user` + `roles[]` |
| Nhận diện thiết bị | `workstation.ts`: người dùng **tự chọn** workstation từ dropdown, lưu vào `localStorage` key `df_current_workstation` (theo trình duyệt, không theo phần cứng/IP). `AppLayout.vue` có `ws-blocker-overlay` chặn thao tác cho tới khi chọn — **nhưng chỉ chặn "chưa chọn", không giới hạn menu sau khi đã chọn** |

### Đã gần đạt mô hình workstation (giữ lại, chỉ cần siết lại điều hướng)
- `WeighingStation.vue`, `MaterialTransfer.vue`: màn hình chờ quét full-width, có widget giả lập quét (dùng cho kiểm thử/demo, không ảnh hưởng luồng quét thật bằng máy quét vật lý qua bàn phím wedge).
- `FeedingMonitor.vue`: đã rẽ nhánh giao diện theo `currentWorkstation.type === 'TANK_RECEIVING'` — đúng tinh thần "component biết mình đang phục vụ công đoạn nào".
- `Dashboard.vue`: đã là màn hình giám sát 5-tab, khớp vai trò WS-10 (Giám sát), không dùng để nhập liệu.

### Sai với mô hình "1 máy tính - 1 nhiệm vụ" (cần sửa)
1. **Không có màn hình "Chọn công đoạn" dạng thẻ lớn** như mục 5 mô tả — hiện tại chỉ là 1 dropdown + nút xác nhận nằm trong overlay chặn của `AppLayout.vue`.
2. **Sau khi chọn workstation, menu đầy đủ vẫn hiển thị** — một trạm `DYE_WEIGHING` vẫn thấy và bấm được vào Vật tư, Công thức, Báo cáo, Audit Log, Điều phối máy... Đây là vi phạm trực tiếp nguyên tắc "chỉ hiển thị chức năng được phép".
3. **App không tự mở đúng màn hình khi khởi động** — sau khi chọn workstation, người dùng vẫn phải tự bấm vào sidebar để vào đúng trang; router không tự redirect theo `workstation.type`.
4. **Không có trạm "Quét đơn QR" độc lập (WS-01/WS-006 theo đặc tả)** — loại `ORDER_DESK` đã có trong DB nhưng chưa có Vue view riêng; màn hình gần nhất là `ProductionBatches.vue`, vốn là màn hình CRUD tổng hợp (tạo/sửa lô, kèm "MES Mock Tool"), không phải màn hình chỉ-quét-QR.
5. **Không có trạm "In tem" độc lập (WS-006/mục 6C)** — in tem hiện đang gắn liền bên trong `WeighingStation.vue`, tự động in ngay sau khi cân xong chứ chưa tách thành 1 kiosk quét-để-in riêng.
6. **Không có `allowed_actions` khai báo theo workstation** — phân quyền hiện chỉ dựa vào Role (OPERATOR/SUPERVISOR/TECHNOLOGIST/ADMIN) kiểm tra rải rác trong từng controller, chưa gắn với loại trạm.

---

## 3. Rà soát Backend (2.2)

| Miền nghiệp vụ | Model / Bảng | Trạng thái |
|---|---|---|
| Production Order / Batch | `ProductionBatch` (`app.production_batches`), `MachineDispatch` (`app.machine_dispatches`) | Đầy đủ, có state machine, có test |
| Recipe | `Recipe`, `RecipeVersion`, `RecipeMaterial`, `ProcessParameter`, `Material`, `WaterConfig` | Đầy đủ, có `FormulaCalculationService`, Golden Master đã pass |
| Weighing | `WeighingJob`, `WeighingJobItem` (+ `override_approved/reason/by` mới thêm ở Phase 11), `ScaleMeasurement`, `MaterialLabel` | Đầy đủ, có dung sai, override, audit |
| Scale Agent | `DeviceController` (cache live weight qua Redis-less `Cache`), Agent C# `ScaleReader.cs` (đang chạy **SIMULATION mode**, giao thức Serial thật chưa xác nhận — xem `open-questions.md` CH-TECH-002) | Hoạt động ở mức giả lập, kiến trúc 1 workstation ↔ 1 `assigned_scale_device_id` đã đúng mô hình |
| Print Agent | `PrintJobController` (sinh TSPL động theo kích thước), `AgentJobsController` (agent poll PENDING job + ack) | Đầy đủ, đã test (`PrintJobPipelineTest`) |
| QR | Sinh nội bộ dạng `DF:<TYPE>:<uuid>` (không gọi bên thứ 3, đúng ràng buộc C-03 trong `risks-and-assumptions.md`) | Đầy đủ |
| Workstation | `Workstation` model: `code, name, type, location, active, assigned_scale_device_id, assigned_printer_device_id, configuration(jsonb), last_seen_at` | **Thiếu** `allowed_actions`, `default_screen` so với đặc tả mục 3 |
| Audit log | `AuditLog` (`app.audit_logs`), JSONB `before_data`/`after_data` bất biến | Đã dùng cho LOGIN/LOGOUT, REPRINT, WEIGH_TOLERANCE_OVERRIDE, FEED_OVERRIDE_APPROVED, LOCK_OVERRIDE, DISPATCH_TO_MACHINE — bao phủ tốt |
| Permission | `Role` (`OPERATOR/SUPERVISOR/TECHNOLOGIST/ADMIN`) + `User::hasRole()` | Chỉ theo **role**, chưa có ma trận (role × loại trạm × hành động); không dùng Laravel Policy/Gate tập trung, kiểm tra rải rác trong từng Controller |

---

## 4. Phần nào giữ, phần nào sửa (2.3)

### ✅ GIỮ NGUYÊN (đã đúng, đã test, dùng lại)
- Toàn bộ model/schema nghiệp vụ lõi (Production Batch, Recipe, Weighing, Transport, Feed, Print, Audit).
- `ScannerController::scan/verifyTank` — logic quét đúng tinh thần "QR quyết định tất cả, không cho chọn tay".
- `scannerService.ts` — dùng làm nền cho "ScannerManager" (mục 7), có thể cần bổ sung debounce chống-scan-trùng rõ ràng hơn (hiện chỉ lọc theo tốc độ gõ, chưa chặn quét lại đúng 1 mã trong X giây).
- `WeighingStation.vue`, `MaterialTransfer.vue`, `FeedingMonitor.vue`, `Dashboard.vue` — dùng làm khung cho WS-006/008/009/010, chỉ cần **tách khỏi menu chung** và **ẩn/gỡ các control không phải quét** khỏi luồng vận hành thật (giữ lại dưới cờ môi trường dev/test).
- `PrintJobController` + `AgentJobsController` — dùng lại nguyên vẹn cho WS-007, chỉ cần thêm 1 view frontend mới.
- Audit log, Realtime/Alert engine — giữ nguyên.

### 🔧 CẦN SỬA (đúng như bạn chỉ ra — sai với quy trình thực tế nhà máy)
1. `AppLayout.vue` — phải trở thành **có điều kiện theo `currentWorkstation.type`**: với các loại trạm vận hành 1-nhiệm-vụ (`DYE_WEIGHING`, `CHEMICAL_WEIGHING`, `A11_WEIGHING`, `DLG_WEIGHING`, `MATERIAL_TRANSFER`, `TANK_RECEIVING`, `MACHINE_FEEDING`), **ẩn hoàn toàn sidebar/menu**, chỉ để lại 1 nút "Đổi trạm" có khóa quyền (Supervisor/Admin). Loại `MONITORING` và các trạm back-office (`ORDER_DESK`, `PRODUCTION_DISPATCH`) mới giữ layout nhiều chức năng.
2. `router/index.ts` — thêm guard: sau khi có `currentWorkstation`, tự động redirect về `default_screen` của loại trạm đó; chặn điều hướng thủ công tới route không thuộc trạm (trừ role Admin/Supervisor).
3. Màn hình "Chọn công đoạn" — tách khỏi `AppLayout.vue` thành 1 view riêng dạng thẻ lớn (`WorkstationSelect.vue`) đúng mock-up mục 5, thay cho dropdown hiện tại.
4. Tạo view độc lập cho `ORDER_DESK` (quét đơn QR thuần) — **cần bạn xác nhận thêm**: hiện `ProductionBatches.vue` vừa là nơi tạo lô (có "MES Mock Tool") vừa được xem là màn hình vận hành. Theo mục 6A, trạm quét đơn KHÔNG được tạo/sửa gì, chỉ quét và nhận. Vậy `ProductionBatches.vue` nên tách thành: (a) 1 kiosk quét-nhận-đơn thuần cho WS sản xuất, và (b) giữ màn hình tạo/sửa lô như một công cụ back-office riêng, không gắn vào workstation nào (chỉ Admin/Technologist dùng qua trình duyệt văn phòng). **Xem câu hỏi mở bên dưới.**
5. Tạo view độc lập "Trạm in tem" (WS-007) — quét kết quả cân đã hoàn tất → hiển thị thông tin → in, tách khỏi luồng tự-động-in trong `WeighingStation.vue`.
6. Bổ sung `allowed_actions` (jsonb) + `default_screen` (string) vào `Workstation` — migration mới, không sửa cấu trúc bảng cũ.
7. Chuẩn hóa kiểm tra quyền theo (role × loại trạm) — cân nhắc dùng Laravel Gate/Policy tập trung thay vì rải rác `if ($user->hasRole(...))` trong từng Controller.

### Migration cần bổ sung (dự kiến, chưa chạy)
- `xxxx_add_allowed_actions_and_default_screen_to_workstations_table.php`.

### API cần thay đổi (dự kiến, chưa code)
- Không cần sửa API nghiệp vụ lõi (Scanner/Print/Weighing) — giữ nguyên.
- Có thể cần `GET /api/workstations/{code}` (chi tiết 1 trạm gồm `allowed_actions`/`default_screen`) để router guard dùng, tránh phải tải cả danh sách.

---

## 5. Câu hỏi cần xác nhận trước khi triển khai WS-001

1. **`ORDER_DESK` / `PRODUCTION_DISPATCH` thực tế vận hành thế nào tại nhà máy?** Có phải nhân viên khai đơn cũng chỉ thao tác bằng quét QR (đơn đã có sẵn từ MES), hay đây vẫn là 1 vị trí back-office cần nhập liệu/tạo lô thủ công như hiện tại? Câu trả lời quyết định `ProductionBatches.vue` có bị tách đôi hay không.
2. **Ràng buộc thiết bị:** hiện "1 máy tính = 1 workstation" được hiện thực bằng `localStorage` theo trình duyệt (đổi trình duyệt/máy khác là mất lựa chọn). Việc này có đủ (nếu mỗi PC nhà xưởng có trình duyệt kiosk riêng, không ai đăng nhập chung) hay cần ràng buộc chặt hơn theo IP/hostname thiết bị?
3. **Khóa/đổi workstation:** ai được phép đổi trạm của 1 máy tính — chỉ Admin, hay cả Supervisor? Có cần PIN riêng hay dùng lại đăng nhập tài khoản Supervisor/Admin hiện có?

---

## 6. Đề xuất thứ tự triển khai tiếp theo

Giữ nguyên thứ tự bạn đưa ra (WS-001 → WS-012). Vì phần lớn WS-004 (Scanner Service), WS-005/006/008/009 (các luồng quét) **đã có nền tảng gần hoàn chỉnh**, khối lượng việc thực tế tập trung vào: WS-001 (bổ sung 2 cột), WS-002/WS-003 (màn hình chọn công đoạn + khóa điều hướng — đây là phần việc lớn nhất), và tách WS-006 (in tem) + làm rõ WS-005 (quét đơn) theo câu hỏi mục 5.1.

**Chưa viết code.** Đang chờ xác nhận 3 câu hỏi ở mục 5 và xác nhận bắt đầu WS-001.
