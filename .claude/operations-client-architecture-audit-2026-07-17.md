# Audit độc lập: Kiến trúc Operations Client – Capability – Device (2026-07-17)

> Audit thực hiện trực tiếp trên source code, DB dev, route, middleware, test thật — không dựa vào tài liệu tự khai. Mọi phát hiện đều có bằng chứng file:line hoặc kết quả test/lệnh chạy thật kèm theo.

## 0. Bối cảnh quan trọng cần biết trước khi đọc báo cáo

Trong lúc audit đang diễn ra, phát hiện **một tiến trình khác đang chỉnh sửa đồng thời cùng repository** — không phải do phiên làm việc này tạo ra. Bằng chứng: `routes/api.php` bị sửa ngoài ý muốn (hệ thống tự báo), và migration `2026_07_17_131458_create_operation_client_architecture_tables.php` **đã chạy thật** trên DB dev trong lúc audit đang chạy, đổi tên `app.workstations` → `app.operation_clients`, xóa `app.workstation_allowed_actions`/`workstation_role_assignments`/`device_assignments`, viết lại `Workstation` model, `WorkstationGuard` middleware. Toàn bộ kiến trúc Operations Client/Capability/Kiosk mô tả trong yêu cầu audit **đã được xây dựng phần lớn** bởi tiến trình đó, không phải giả định/kế hoạch tương lai.

**Kết luận:** báo cáo này audit đúng trạng thái sống hiện tại của DB dev + code tại thời điểm chạy (2026-07-17), xác nhận bằng `php artisan tinker`, `php artisan test`, `php artisan migrate:status` thật.

---

## 1. Kiến trúc thực tế đang tồn tại vs kiến trúc yêu cầu

| Thành phần | Yêu cầu | Thực tế tìm thấy | Khớp? |
|---|---|---|---|
| Kiosk Link | `/operate/...{token}` | `/operate/c/:clientCode/:kioskToken` (`frontend/src/router/index.ts:38`) | Có, nhưng token nằm trong URL path (xem P1 §5) |
| Kiosk Token | Token riêng, hash | `app.operation_clients.kiosk_token_hash` (sha256), sinh bởi `OperationClientAdminController::generateKioskToken` | Có |
| Operations Client | 1 client = N capability | `app.operation_clients` (đổi tên từ `workstations`) | Có |
| Capabilities | Bảng capability tách biệt | `app.capabilities` (10 mã: 5 BUSINESS + 5 DEVICE, seed sẵn) | Có |
| operation_client_capabilities | N-N, có `enabled`, `configuration_json` | `app.operation_client_capabilities` | Có |
| Bound Devices | Client → N devices theo role | `app.operation_client_devices` (`device_role`, `is_default`, `priority`, `enabled`) | Có |
| Business Module resolve theo capability | Backend enforce capability mỗi request | **CHỈ áp dụng một phần** — xem P0 §4 | **KHÔNG đầy đủ** |

Mô hình "1 máy = 1 workstation type cố định" **đã được thay thế** ở tầng schema/model. Nhưng tầng **enforcement** (bắt buộc kiểm tra capability ở backend cho mọi API) **chưa hoàn chỉnh** — đây là khoảng cách lớn nhất giữa thiết kế và thực thi.

---

## 2. Kiosk link / token / session

**Đã xác nhận (bằng test thật, không suy diễn):**
- Token sinh bằng `Str::random(12)×3` nối `KT-`, đủ entropy, không đoán được.
- Token lưu dạng hash sha256 (`kiosk_token_hash`), không lưu plaintext trong `app.operation_clients`.
- `test_kiosk_session_initialization_fails_with_invalid_token` (đã có sẵn, PASS) xác nhận token sai bị từ chối 401.
- Revoke (`revokeKioskToken`) tắt `kiosk_token_active=false` **và** đóng toàn bộ `kioskSessions` đang ACTIVE của client đó (`$client->kioskSessions()->update(['status'=>'REVOKED'])`) — session cũ ngay lập tức không dùng được nữa vì `KioskAuthenticationMiddleware` lọc `where('status','ACTIVE')`.
- `test_kiosk_session_cannot_access_admin_api` (**mới viết, PASS**) — kiosk session KHÔNG gọi được `/api/admin/workstations` (401), vì `KioskAuthenticationMiddleware` không gọi `Auth::login()`, nên `CheckRole` (role:ADMIN) thấy `$request->user()===null` → chặn đúng.

**Phát hiện (P1):**
- **Token nằm trực tiếp trong URL path** (`/operate/c/{code}/{rawToken}`, sinh tại `OperationClientAdminController.php:287`, tiêu thụ tại `KioskLanding.vue:34-35` qua `route.params`). Token dạng path param có xu hướng lọt vào access log server, lịch sử trình duyệt, `Referer` header khi điều hướng ra ngoài — rủi ro rò rỉ cao hơn so với gửi qua POST body/header. Yêu cầu audit "Token không xuất hiện trong log" **chưa được đảm bảo bằng thiết kế**, phụ thuộc cấu hình log của web server.
- **Rotate token KHÔNG tự động thu hồi session đang mở** — `generateKioskToken` (gọi lại lần 2 để "rotate") chỉ đổi `kiosk_token_hash`, KHÔNG gọi `kioskSessions()->update(['status'=>'REVOKED'])` như `revokeKioskToken` làm. Nghĩa là: sau khi rotate, **link cũ không tạo được session mới** (đúng), nhưng **session đã mở từ link cũ vẫn sống tới hết cửa sổ trượt 2 giờ** (đúng 1 phần yêu cầu "link cũ hết hiệu lực sau rotate", sai phần còn lại).
- **Session token lưu plaintext trong `app.kiosk_sessions.token`** (`KioskSessionController.php:77`, không hash) — khác với `kiosk_token_hash` (đã hash đúng). Rủi ro thấp hơn (token phiên, hết hạn 2h, không phải credential dài hạn) nhưng lệch với nguyên tắc "không lưu secret rõ" nêu trong yêu cầu audit mục 4.

---

## 3. Capability resolution — PHÁT HIỆN P0 QUAN TRỌNG NHẤT

**Câu hỏi audit: "Capability có được backend kiểm tra hay chỉ dùng để ẩn menu?"**

**Trả lời bằng thực nghiệm: CHỈ MỘT PHẦN NHỎ API được backend kiểm tra capability. Phần lớn chỉ ẩn ở menu frontend (router guard), backend không chặn.**

Bằng chứng — 4 test mới viết tại `backend/tests/Feature/CapabilityEnforcementAuditTest.php`, chạy thật, **PASS cả 4** (`php artisan test --filter=CapabilityEnforcementAuditTest`, 4 passed, 10 assertions):

| Test | Kết quả thực tế | Kỳ vọng theo audit |
|---|---|---|
| Client CHỈ có `SMALL_SCALE` gọi `POST /api/print-jobs` | **Không bị chặn** (không phải 403) | Phải bị từ chối |
| Client CHỈ có `SMALL_SCALE` gọi `POST /api/machine-dispatches/{id}/confirm` | **Không bị chặn** (không phải 403) | Phải bị từ chối |
| Kiosk session gọi `GET /api/admin/workstations` | **Bị chặn đúng** (401) | Phải bị từ chối ✓ |

**Nguyên nhân gốc** (`backend/routes/api.php`): chỉ 9 route được gắn `->middleware('workstation.guard:<ACTION>')` (`WEIGH_ITEM`, `PRINT_LABEL`, `REPRINT_LABEL`, `SCAN_ORDER`×2, `SCAN_DUAL_VERIFY`, `CONFIRM_TRANSIT`, `CONFIRM_ARRIVAL`, `CONFIRM_FEED`×2). Toàn bộ route còn lại trong nhóm `KioskAuthenticationMiddleware` — bao gồm `POST /print-jobs`, `POST /machine-dispatches/{id}/confirm`, `POST /machine-dispatches/{id}/claim|release|send`, `POST /chemical-call-requests`, `POST /weighing-job-items/{id}/override`, `POST /production-batches`, `POST /recipes` — **chỉ cần "có phiên hợp lệ bất kỳ" (kiosk hoặc user), không kiểm tra phiên đó có đúng capability hay không**.

`WorkstationGuard::mapActionToCapability`/`mapActionToBusinessCapability` (logic map action→capability) **chỉ được gọi từ trong chính middleware `workstation.guard`** — nghĩa là route nào không gắn middleware này thì logic capability không bao giờ chạy tới.

**Phân loại: P0.** Đây chính là kịch bản audit yêu cầu bắt buộc test (mục 7, "gọi API print phải bị từ chối") và nó **fail** trên nhánh code hiện tại.

---

## 4. Device resolution (printer/scale binding)

**Phát hiện (P1):** Printer **không** được resolve server-side theo `operation_client → PRINT capability → operation_client_devices → device`.

- `PrintJobController::store()` (`backend/app/Http/Controllers/PrintJobController.php:24-40`) nhận `printer_address`/`printer_connection_type` **trực tiếp từ request body**, mặc định hard-code `'TSC TE200'` / `'USB'` nếu không gửi — không có bước tra `operation_client_devices` để tìm printer đã bind cho client đó.
- Agent .NET (`agent/appsettings.json`, `agent/ScaleReader.cs:38`, `agent/Worker.cs:138`) đọc printer/scale từ **file cấu hình cục bộ** (`Printer:Address`, `Scale:PortName`, mặc định `TSC TE200`/`COM1`) — hoàn toàn không gọi API nào để hỏi backend "thiết bị nào đang bind với client này". Đây là **thiết kế cấu hình tĩnh theo máy vật lý**, đúng mô hình cũ ("1 máy = 1 cấu hình cố định"), chưa chuyển sang mô hình "resolve theo capability" như kiến trúc mục tiêu yêu cầu.
- **Không tìm thấy** `PRINT_RESULT_UNKNOWN` hoặc trạng thái tương đương trong `PrintJob`/`PrintAttempt` (chỉ có `PENDING/PRINTED/FAILED/REJECTED` qua kiểm tra trước đó trong phiên) — yêu cầu "có trạng thái không chắc chắn" **chưa đáp ứng**.
- Client không có capability `PRINT` **vẫn gọi được** `/print-jobs` thành công (xem §3) — yêu cầu "Client không có PRINT phải bị backend chặn" **fail**.

Không tìm thấy IP/COM hard-code không thể override (mọi giá trị đều là default trong `_config.GetValue<T>(key, default)` — override được qua `appsettings.json`), nhưng **cách override là sửa file cục bộ trên từng máy**, không phải qua Admin UI + API như kiến trúc mục tiêu mô tả. Phân loại: **Configuration hợp lệ nhưng sai tầng** (nên là DB-driven qua Admin, không phải file cục bộ).

---

## 5. Giao diện Kiosk (đã đọc `KioskLanding.vue`, `KioskMenu.vue` không đọc sâu, `router/index.ts`)

**Xác nhận ĐÚNG:**
- `meta: { requiresAuth: false }` cho `KioskLanding` — không cần login trước khi vào trang thiết lập.
- `authStore.isAuthenticated` cho phiên kiosk chỉ dựa vào `!!state.token` được set từ `setKioskSession()` — **không** yêu cầu username/password. Xác nhận "không cần đăng nhập trên máy vận hành" là ĐÚNG về mặt luồng.
- Router guard (`router/index.ts:202-218`) giới hạn route kiosk được phép truy cập theo đúng danh sách capability của client (`allowedRoutes` build động từ `client.capabilities`).
- 1 capability → chuyển thẳng (`KioskLanding.vue:63-65`); nhiều capability → `/operate/menu` (chưa đọc sâu `KioskMenu.vue` để xác nhận UI chọn đơn giản).

**Phát hiện (P2):**
- `authStore.kioskClient` (danh sách capability/device dùng để dựng menu và giới hạn route) được cache trong `localStorage` (`df_operation_client`) và **không refetch từ backend mỗi lần điều hướng** — chỉ nạp lại lúc `initialize()` (page load). Nếu Admin thu hồi 1 capability giữa phiên làm việc, giao diện kiosk vẫn hiển thị/route theo capability cũ cho tới khi tải lại trang. Đây là vấn đề **hiển thị/UX bị trễ**, KHÔNG phải lỗ hổng bảo mật thật — vì tầng backend (`KioskAuthenticationMiddleware` + `workstation.guard` ở nơi có gắn) vẫn tra DB thật mỗi request, không tin `localStorage`. Nhưng đúng như audit lưu ý, **không nên chỉ dựa localStorage** — nên thêm cơ chế đồng bộ lại capability định kỳ hoặc qua sự kiện đẩy.
- Chưa xác minh trực tiếp bằng mắt trên trình duyệt thật (không có Playwright/E2E trong phiên này) — chỉ xác nhận qua đọc code + router guard logic. Cần xác minh hình ảnh thật trước khi coi khoản mục 8 (giao diện kiosk) là hoàn tất.

---

## 6. Giao diện Admin (`WorkstationAdmin.vue`, `OperationClientAdminController.php`)

**Xác nhận ĐÚNG:**
- Admin xem được danh sách đầy đủ client + `users`, `capabilities`, `devices` qua `index()` (eager load 3 quan hệ).
- Có `generateKioskToken`/`revokeKioskToken`/`suspend`/`resume`/`testConnection`/`register`/`updateConfig`.
- Route `/admin/workstations/*` nằm sau `role:ADMIN` — đã xác nhận bằng test kiosk-session-không-vào-được (§2).

**Phát hiện (P1 — rò rỉ secret):**
- `OperationClient` model (`backend/app/Models/OperationClient.php`) **không có thuộc tính `$hidden`**. `OperationClientAdminController::index()` dòng 54 gọi thẳng `$client->toArray()` rồi trả JSON — **`kiosk_token_hash` và `registration_token_hash` xuất hiện trong response `GET /api/admin/workstations`**, được `WorkstationAdmin.vue` poll mỗi 5 giây và hiển thị cho bất kỳ ai có quyền Admin xem DevTools/Network tab.
- Xác nhận bằng test mới `test_admin_workstations_list_leaks_token_hashes_to_frontend` — **PASS** (tức là hash CÓ mặt trong response thật).
- Vi phạm trực tiếp yêu cầu audit mục 4 "Không lưu secret trong JSON trả về frontend" — dù đây là **hash** chứ không phải token gốc (không thể đảo ngược trực tiếp), vẫn là rò rỉ không cần thiết, nên thêm `protected $hidden = ['kiosk_token_hash','registration_token_hash'];`.

**Chưa kiểm tra trong đợt này:** view-only mode cho Admin xem không sửa; last_heartbeat/agent status hiển thị đúng dữ liệu thật hay không (đã thấy field tồn tại, chưa xác minh Agent thật gửi heartbeat cập nhật nó — `AgentController::heartbeat` có tồn tại nhưng Agent .NET hiện KHÔNG gọi route này, xem §7).

---

## 7. Local Agent

**Phát hiện (P1):**
- Agent .NET (`agent/Worker.cs`, đã tự tay sửa trong phiên trước) dùng **`X-Workstation-Token`** xác thực qua middleware `AgentAuth` (tự viết phiên trước), tra `registration_token_hash` trên `app.operation_clients` (bảng đã đổi tên, cột này sống sót qua migration). Route Agent thật sự gọi (`POST /devices/readings`, `GET /agents/{workstation_id}/jobs`, `POST /jobs/{job_id}/ack`) hoạt động độc lập, KHÔNG dùng `kiosk_token`/`kiosk_session`, đúng yêu cầu "Agent không dùng kiosk token hoặc user token".
- **NHƯNG**: cơ chế này **không đi qua Capability** — `AgentAuth` chỉ xác nhận "token đúng workstation", không kiểm tra `operation_client` đó có capability `LOCAL_AGENT`/`WEIGH`/`PRINT` hay không, và không resolve theo `operation_client_devices` (không có `device_id` cụ thể trong payload `POST /devices/readings`, chỉ có `workstation_id` dạng chuỗi tự do).
- `AgentController` (register/heartbeat/event theo `device_id`, đúng mô hình thiết kế đầy đủ hơn) **vẫn mồ côi** — Agent .NET thật không gọi route này (đã xác nhận lại lần 2, `Worker.cs` chỉ gọi 3 route đã liệt kê).
- **Kết luận mục 18:** Agent có identity/credential riêng (không dùng user/kiosk token) — ĐÚNG một phần. Nhưng "Device binding", "Version reporting qua heartbeat thật", "Duplicate protection qua sequence/event ID" — **chưa xác nhận có** (payload `POST /devices/readings` không có `sequence_no`, không có chống trùng ngoài cache TTL 15s theo `workstation_id`).

---

## 8. Hard-code scan

Đã quét toàn repo (loại trừ `vendor/`, `node_modules/`) cho: IP, `TSC224`/`TSC TE200`, `COM[0-9]`, `putty_log.txt`, `WS-ORDER-01`, `WS-PRINT-01`, `SMALL_SCALE_0[12]`, đường dẫn `Z:\`.

| Vị trí | Giá trị | Phân loại |
|---|---|---|
| `agent/appsettings.json`, `ScaleReader.cs:38`, `Worker.cs:138` | `COM1`, `TSC TE200`, `D:\SCALE\putty_log.txt` (default trong `_config.GetValue(key, default)`) | **Configuration** — override được qua file cục bộ, nhưng sai tầng (nên qua Admin/API, xem §4) |
| `backend/.../PrintJobController.php:40` | `'TSC TE200'` default khi request không gửi | **Lỗi cần sửa (P1)** — nên resolve qua device binding, không nhận trực tiếp từ client |
| `backend/database/migrations/2026_07_15_221804_...:19` | cột `printer_address` default `'TSC TE200'` | Configuration (cùng gốc vấn đề trên) |
| `backend/database/seeders/WorkstationsSeeder.php` | `WS-ORDER-01`, `WS-PRINT-01` | **Seeder** — hợp lệ làm dữ liệu mẫu, nhưng chưa xác minh còn tương thích schema mới (chưa test) |

Không tìm thấy IP thật hard-code (172.x/192.x cụ thể của nhà máy) trong code nghiệp vụ — chỉ có trong `vendor/`/`node_modules/` (thư viện bên thứ 3, không tính).

---

## 9. CHEMICAL_CALL isolation + RECORD_A/RECORD_B correlation

**Xác nhận ĐÚNG (P0 không có):**
- `ChemicalCallController`/`ChemicalCallRequest` **không** tham chiếu RECORD_A, RECORD_B, `WS-PRINT-01`, hay `chem_order` bằng string literal nào (grep 0 kết quả) — không bị nối cứng vào QR_LABEL_PRINTING/RECORD_A như audit cấm.
- Correlation RECORD_A/B duy nhất tìm thấy đang dùng cho báo cáo truy vết (`TraceabilityQueryService.php:50-52`) là **read-only**, join theo `machine_id` + cửa sổ 24h — không phải state mutation, không gán receiver, không mở rộng state machine của ChemicalCallRequest.

**Phát hiện nhỏ (P2):**
- Correlation trong `TraceabilityQueryService` dùng `machine_id + time window`, **có yếu tố timestamp trong điều kiện match** — hơi lệch với yêu cầu mục 20 "Không match chỉ bằng timestamp". Vì đây chỉ là báo cáo tham khảo (không phải nguồn sự thật giao dịch), rủi ro thấp, nhưng nên bổ sung khóa cứng hơn (QR payload hash/order key) nếu báo cáo này được dùng để ra quyết định vận hành thay vì chỉ xem log.

---

## 10. Hai máy SMALL_SCALE độc lập — ĐÃ XÁC NHẬN AN TOÀN (cập nhật 2026-07-17, đợt sau)

**Cập nhật:** đã viết và chạy test thật (`tests/Feature/SmallScaleTwoStationIsolationTest.php`, 2 test, PASS, 17 assertions) mô phỏng 2 trạm cân độc lập (`SMALL-SCALE-A`/`SMALL-SCALE-B`) cùng xử lý 2 đơn khác nhau qua `POST /scanner/scan-dye-qr`:
- Job/item của 2 trạm là 2 bản ghi hoàn toàn khác nhau, không giao nhau (`assertEmpty(intersect(...))`).
- Cân xong ở trạm A không làm thay đổi trạng thái item/job của trạm B.
- Cache số cân trực tiếp (`DeviceController`) cô lập đúng theo `workstation_id` (kế thừa từ bản vá PB-2 đầu phiên).

**Lý do an toàn (xác nhận qua code + test, không phải suy đoán):** mỗi `WeighingJob` được khóa theo `production_batch_id` (không phải theo trạm) — 2 đơn khác nhau luôn tạo 2 job khác nhau, nên "cô lập" đến tự nhiên từ khóa nghiệp vụ, không cần thêm `operation_client_id` filter tường minh trong `WeighingJobController`. Rủi ro thật duy nhất còn lại (chưa test): **2 trạm quét TRÙNG 1 QR gần như đồng thời** — `assigned_workstation_id` bị ghi đè bởi request xử lý sau (không có `lockForUpdate`), có thể làm "quyền sở hữu" job nhảy giữa 2 trạm dù dữ liệu cân không bị trộn. Rủi ro vận hành thấp (2 trạm hiếm khi quét đúng 1 tem cùng lúc) nhưng nêu rõ để không bị coi là đã kiểm tra hết. Đã đóng finding A-05 ở mức "an toàn cho trường hợp chính (2 đơn khác nhau)", còn mở 1 rủi ro nhỏ (trùng QR đồng thời) — xếp **P2**.

---

## 11. Test đã chạy được (bằng chứng thật, không suy diễn)

```
php artisan test
→ 88 passed (472 assertions), 64.3s — TOÀN BỘ SUITE HIỆN CÓ PASS

php artisan test --filter=CapabilityEnforcementAuditTest
→ 4 passed (10 assertions) — 4 test audit mới viết trong phiên này
```

4 test mới (`backend/tests/Feature/CapabilityEnforcementAuditTest.php`) là bằng chứng thực nghiệm cho 2 P0 + 1 P1 + 1 xác nhận PASS nêu trên. **Giữ nguyên trong repo làm regression test** — khi nào các route được thêm `workstation.guard` đúng, các assertion `assertNotEquals(403,...)` sẽ cần đổi thành `assertEquals(403,...)` (đã ghi chú ngay trong test).

**Chưa chạy**: 7 kịch bản E2E đầy đủ theo mục 23 của yêu cầu audit (đặc biệt kịch bản 4 — 2 client song song, kịch bản 5 — client đa capability, kịch bản 7 — luồng Admin đầy đủ qua UI thật). Đây là giới hạn thời gian của đợt audit này, không phải đã kiểm tra và PASS.

---

## 12. Danh sách phát hiện theo mức độ

| ID | Severity | Domain | File/Line | Hiện trạng | Logic đúng | Ảnh hưởng | Đề xuất |
|---|---|---|---|---|---|---|---|
| A-01 | **P0** | Capability enforcement | `routes/api.php` (nhóm `KioskAuthenticationMiddleware`, các route không có `workstation.guard`) | Client chỉ có `SMALL_SCALE` gọi được `/print-jobs`, `/machine-dispatches/{id}/confirm` mà không bị chặn | Phải trả 403 khi thiếu capability | Client vận hành sai capability có thể tạo print job/confirm dispatch ngoài phạm vi được cấp — đúng kịch bản audit cảnh báo | Gắn `workstation.guard:<CAPABILITY>` (hoặc middleware capability mới) cho toàn bộ route ghi dữ liệu nghiệp vụ, không chỉ 9 route hiện có |
| A-02 | **P1** | Bảo mật dữ liệu | `app/Models/OperationClient.php` (thiếu `$hidden`), `OperationClientAdminController.php:54` | `kiosk_token_hash`/`registration_token_hash` lộ trong JSON `/api/admin/workstations` | Không trả secret/hash ra frontend | Rò rỉ hash token cho mọi Admin xem DevTools; giảm biên độ an toàn nếu thuật toán hash bị yếu đi sau này | Thêm `protected $hidden = ['kiosk_token_hash','registration_token_hash'];` |
| A-03 | **P1** | Kiosk token lifecycle | `OperationClientAdminController::generateKioskToken` | Rotate token không thu hồi session đang mở (chỉ `revokeKioskToken` mới thu hồi) | Rotate phải làm link cũ + session cũ hết hiệu lực | Token bị rotate vì nghi lộ nhưng kẻ tấn công còn session cũ vẫn thao tác được tới 2h | Gọi thêm `kioskSessions()->update(['status'=>'REVOKED'])` trong `generateKioskToken`, hoặc tách hẳn 2 hành động |
| A-04 | **P1** | Device resolution | `PrintJobController.php:26-40`, `agent/appsettings.json` | Printer/scale resolve qua request body / config file cục bộ, không qua `operation_client_devices` | Phải resolve server-side theo capability→device binding | Đổi thiết bị phải sửa tay từng máy/từng request thay vì qua Admin UI; không có single source of truth | Thêm bước resolve `PrintJobController::store` tra `operation_client_devices` theo `workstation_id`/client thay vì nhận trực tiếp printer_address từ client |
| A-05 | **P1** | Two-client isolation | `WeighingJobController.php` | Không thấy điều kiện lọc theo `operation_client_id` trong weigh/sample/accept | Phải cô lập job/sample theo từng client | Chưa xác nhận được 2 trạm SMALL_SCALE làm việc song song không trộn dữ liệu | Viết test đồng thời 2 client thật (kịch bản 4 audit) trước khi kết luận |
| A-06 | P2 | UX / staleness | `frontend/src/stores/auth.ts` | Capability/device cache trong `localStorage`, không refetch khi điều hướng | Không dựa hoàn toàn vào localStorage | Admin thu hồi quyền giữa phiên không phản ánh ngay trên UI kiosk (nhưng backend vẫn chặn đúng nếu route có guard) | Thêm polling/refresh định kỳ session capability |
| A-07 | P2 | Session token storage | `KioskSessionController.php:77` | `kiosk_sessions.token` lưu plaintext | Token phiên nên hash | Rủi ro thấp (2h TTL) nhưng lệch nguyên tắc chung | Hash session token, so khớp bằng hash khi xác thực |
| A-08 | P2 | URL token exposure | `OperationClientAdminController.php:287`, `KioskLanding.vue:34` | Token nằm trong URL path | Không xuất hiện trong log | Có thể lọt vào access log/Referer | Cân nhắc đổi sang cơ chế trao đổi 1 lần (link chỉ dùng để mở form nhập token, không nhúng token vào path) hoặc đảm bảo log server loại trừ path này |
| A-09 | P2 | Correlation basis | `TraceabilityQueryService.php:50-52` | RECORD_A/B correlation dùng machine_id+time window cho báo cáo | Không match chỉ bằng timestamp | Rủi ro thấp (chỉ báo cáo, không phải giao dịch) | Bổ sung khóa cứng hơn nếu dùng cho quyết định vận hành |
| A-10 | P3 | Đặt tên | `OperationClientAdminController.php` route path vẫn là `/admin/workstations/*` dù đã đổi model sang OperationClient | — | Naming nhất quán | Không ảnh hưởng chức năng | Cân nhắc đổi route path khi có đợt refactor lớn hơn (không gấp) |

---

## 13. File cần sửa (ưu tiên theo P0/P1 ở trên)

1. `backend/routes/api.php` — bổ sung `workstation.guard`/capability middleware cho các route ghi dữ liệu hiện chưa có (A-01).
2. `backend/app/Models/OperationClient.php` — thêm `$hidden` (A-02).
3. `backend/app/Http/Controllers/OperationClientAdminController.php::generateKioskToken` — thu hồi session cũ khi rotate (A-03).
4. `backend/app/Http/Controllers/PrintJobController.php` — resolve printer theo `operation_client_devices` (A-04).
5. `backend/app/Http/Controllers/WeighingJobController.php` + test mới — xác minh/khóa theo `operation_client_id` (A-05).

## 14. Đề xuất thứ tự khắc phục

1. **A-01 trước tiên** — đây là lỗ hổng phân quyền thật, ảnh hưởng trực tiếp tới đúng-sai nghiệp vụ giữa các loại trạm.
2. A-05 (viết test đồng thời để xác nhận có/không có vấn đề thật, trước khi sửa mù).
3. A-02, A-03 (bảo mật, nhanh, rủi ro thấp khi sửa).
4. A-04 (đổi tầng resolve thiết bị — ảnh hưởng cả Agent lẫn Backend, cần phối hợp).
5. A-06 đến A-09 (không chặn pilot, dọn dần).

## 15. Kết luận

**`SYSTEM_LOGIC_NOT_VALIDATED`**

Lý do — theo đúng tiêu chí mục 25 người yêu cầu đặt ra:
- ❌ Có P0 chưa đóng (A-01 — capability không được backend enforce nhất quán).
- ❌ Hai SMALL_SCALE isolation **chưa PASS** (chưa có test, chưa xác nhận được).
- ❌ Printer/scale vẫn resolve một phần qua config cục bộ/request body, chưa hoàn toàn qua device binding.
- ⚠️ Test bắt buộc theo mục 23 mới chạy được kịch bản 6 (Security — 1 phần) và phần capability của kịch bản 5/7; kịch bản 1, 2, 3, 4 đầy đủ (Production Order → Print → Weighing E2E thật, 2-client song song) **chưa chạy** trong đợt này.
- ✅ Kiosk link/token/session (mục 5, 6) hoạt động đúng phần lớn, có bằng chứng test thật.
- ✅ Admin route được bảo vệ đúng khỏi kiosk session (mục có test PASS).
- ✅ CHEMICAL_CALL vẫn cô lập đúng, không bị nối cứng.

**Blocker còn mở:** A-01 (P0), A-05 (P1, cần xác minh trước khi kết luận), A-02/A-03/A-04 (P1, chưa sửa).

---

*Ghi chú phương pháp: mọi khẳng định "PASS"/"có bằng chứng" trong báo cáo này đều đi kèm lệnh/test đã chạy thật trong phiên (`php artisan test`, `php artisan tinker`, `grep` trực tiếp trên source). Các mục ghi "chưa xác minh" là thật sự chưa chạy, không phải PASS ngầm định.*
