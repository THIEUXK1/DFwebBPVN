# Local Agent Architecture — Scale & Print (local-agent-architecture.md)

Lập 2026-07-17. Tài liệu THIẾT KẾ ĐỀ XUẤT — chưa code sản xuất. Trả lời trực tiếp yêu cầu mục 8: không đọc `putty_log.txt` từ web server; dùng chung core cân, tách policy theo workstation.

---

## 1. Bảng so sánh SMALL_SCALE vs LARGE_SCALE (theo đúng yêu cầu mục 8)

Nguồn: `vba-migration-matrix.md` NHÓM 3 mục "SO SÁNH PHIÊN BẢN MODULE DÙNG CHUNG" (workbook B = `5.Semiauto- lockmove SEND OVER6...` = LARGE_SCALE, workbook C = `4.semiauto-small scale...` = SMALL_SCALE). Cả 2 workbook có **cùng 21 module** — khác biệt nằm ở NỘI DUNG 2 module, không phải cấu trúc.

| Chức năng | SMALL_SCALE (workbook C) | LARGE_SCALE (workbook B) | Dùng chung được? | Cần cấu hình? |
|---|---|---|---|---|
| Đọc cân (`ModRead_putty_log.StartFastLoop/ReadLastLineFast`) | Giống hệt | Giống hệt (chỉ khác path `D:\SCALE\` hoa/thường — không ảnh hưởng) | **CÓ** | Không |
| Làm sạch dữ liệu log (`Modcleanweight`: `CleanScaleRaw`/`ExtractLastNumber`/`StableFilter`) | Giống hệt (4 hàm) | Giống hệt (4 hàm) | **CÓ** | Không |
| Xác định số cân cuối (`ExtractLastNumber`) | Giống hệt | Giống hệt | **CÓ** | Không |
| Kiểm tra ổn định (`StableFilter`) | Giống hệt | Giống hệt | **CÓ** | Không |
| Delta/tare (`Mod_delta_raw`: `Delta_Begin`, `GetTargetWeight`, `PushRawToForm`) | Giống hệt 100% (đối chiếu từng dòng) | Giống hệt 100% | **CÓ** | Không |
| Tolerance/dung sai (`Mod_UI_processcolor.CheckRange`: <0.99 vàng, 0.99–1.01 xanh, >1.01 đỏ) | Giống hệt 100% | Giống hệt 100% | **CÓ** | Không |
| Cân vượt ngưỡng (đổi màu control) | Dùng đúng `CheckRange` → RGB(120,250,20)=ACCEPTED | **BUG:** `btnSave_Click`/`Mod_print_tsc224.GetProcessStatus` so màu với `RGB(60,200,100)` — màu này **không bao giờ được `CheckRange` gán thật** → `GetProcessStatus` **luôn trả REJECTED** kể cả khi đạt dung sai | **KHÔNG** — nếu port nguyên trạng LARGE_SCALE sẽ port luôn bug | **CÓ** — LARGE_SCALE cần vá đúng logic của SMALL_SCALE, không giữ bug |
| Chuyển rack (`Mod_sendRackauto.BuildRackBatch/FireRackBatch`) | Giống hệt 100% | Giống hệt 100% | **CÓ** (phần logic tính toán; phần `FireRackBatch` dùng kỹ thuật RPA giả lập chuột — DEPRECATED cả 2 bên, không migrate) | Không |
| Lock/move form (`Mod_lockmoveform.StartWatchFormPos`) | **Đã vá:** gọi `StopWatchFormPos` trước khi Start (tránh chồng timer), dùng ngưỡng `Abs(...)>1` pixel, có `WatchEnabled` guard | **BUG:** KHÔNG gọi `StopWatchFormPos` trước, `UserForm_Activate` gọi `StartWatchFormPos` **vô điều kiện** mỗi lần Activate → rò rỉ nhiều `Application.OnTime` chồng nhau | **KHÔNG** — cần dùng bản đã vá (SMALL_SCALE) làm chuẩn cho cả 2 | **CÓ** |
| Xác nhận hoàn tất (`btnSave_Click`) | Dùng đúng màu ACCEPTED | Dùng sai màu (xem dòng "Cân vượt ngưỡng") | **KHÔNG** (thừa kế bug) | **CÓ** |
| Gửi dữ liệu (ghi `tblRECORD` qua `ModAcessDB`) | `OpenDatabase("Z:DF_SCALE\RECORD.accdb")` — thiếu `\` (bug path, nhưng vô hại trên Windows nếu thư mục hiện hành đúng) | Cùng bug path | **CÓ** (cùng bug, cần sửa đồng thời ở cả 2 khi migrate) | Không (nhưng cần chuẩn hóa path khi build Agent) |
| Ghi lịch sử (`tblRECORD` INSERT) | Giống hệt cấu trúc | Giống hệt cấu trúc | **CÓ** | Không |
| Xử lý lỗi cân/mất kết nối | VBA gốc **cả 2 bên đều không có** cơ chế rõ ràng (không try/catch ở `ReadLastLineFast`, chỉ `Exit Function` trả rỗng nếu file không tồn tại) | Giống hệt | **CÓ** (cả 2 đều cần cải tiến như nhau — không phải khác biệt giữa 2 workstation) | Không |
| Hành vi khi quét lại QR (`txt_color_AfterUpdate`) | **Bền hơn:** vòng lặp `Do While InStr(sLower,"-dye-")>0` xử lý NHIỀU lần xuất hiện "-dye-", LCase triệt để | **Kém bền hơn:** chỉ `Replace` 1 lần, không lặp, LCase không nhất quán | **KHÔNG** — LARGE_SCALE nên dùng logic bền hơn của SMALL_SCALE | **CÓ** |
| Số dòng UI (`scaleform.frm`) | 9 dòng (rack/dye/weight/process 1-9) | 9 dòng (giống hệt cấu trúc) | **CÓ** (cấu trúc UI giống nhau) | Không |
| Quy tắc dung tích tối thiểu 250L | Chưa xác nhận áp dụng cho SMALL_SCALE (VBA không có logic 250L trong cả 3 workbook nhóm SCALE — quy tắc 250L chỉ thấy ở workbook DISPATCH C3, không phải ở SCALE) | Tương tự — **BLOCKED_BY_BUSINESS_CONFIRMATION**, xem CH-BUS-005 | — | Cần xác nhận trước khi code |

### Kết luận Mục 1

- **90% logic lõi (đọc cân, làm sạch, delta, tolerance, chuyển rack) giống hệt 100% giữa 2 workbook** — xác nhận đúng yêu cầu "dùng chung core, tách policy" là hướng thiết kế phù hợp, KHÔNG viết 2 bộ code copy-paste độc lập.
- **2 khác biệt thật, có ý nghĩa nghiệp vụ, đều là BUG của LARGE_SCALE (workbook B) chứ không phải khác biệt policy có chủ đích:** (1) màu ACCEPTED sai khiến luôn ghi REJECTED (R-10 trong `risks-and-assumptions.md`), (2) rò rỉ timer form-watch. **Không copy 2 bug này sang policy LARGE_SCALE của hệ mới** — dùng logic đã vá của SMALL_SCALE làm chuẩn chung, ghi rõ trong code rằng đây là bản đã sửa lỗi so với VBA gốc LARGE_SCALE.
- **Chưa tìm thấy khác biệt ngưỡng cân (kg) nào trong code** giữa 2 workbook — "khối lượng nhỏ" vs "khối lượng lớn" (theo mô tả người dùng) nhiều khả năng là đặc tính THIẾT BỊ CÂN VẬT LÝ (loại cân khác nhau), không phải logic phần mềm khác nhau. **BLOCKED_BY_BUSINESS_CONFIRMATION** — cần xác nhận model cân thật của từng loại trạm (đã có câu hỏi tương tự CH-TECH-002 trong `open-questions.md`).

---

## 2. Kiến trúc Scale Agent (đề xuất, đối chiếu mục 8.1)

**Không đọc `putty_log.txt` từ web server** — thiết kế Local Scale Agent chạy tại từng máy SMALL_SCALE (×2)/LARGE_SCALE (×1):

```
ScaleAgent (Windows Service, đã có khung sườn tại F:\DF\agent\ScaleReader.cs — cần audit lại, sửa CleanWeight lấy số CUỐI thay vì ĐẦU theo PB-1)
  - Nhận dữ liệu từ Serial/TCP hoặc putty_log.txt cục bộ (giữ tương thích quá độ)
  - ScaleCore (dùng chung SMALL_SCALE + LARGE_SCALE):
      CleanScaleRaw(raw) -> string
      ExtractLastNumber(cleaned) -> decimal     -- PB-1: hiện đang lấy SỐ ĐẦU, phải sửa thành SỐ CUỐI
      StableFilter(value, previousReads) -> bool -- PB-2: hiện chưa implement, hard-code true
      Delta/Tare theo slot                       -- theo AutoFlow_OnWeight VBA gốc
  - ScalePolicy (interface, 1 implementation/workstation type):
      SmallScalePolicy   -- dùng CheckRange đúng (RGB 120,250,20 tương đương "ACCEPTED")
      LargeScalePolicy   -- PHẢI dùng cùng ngưỡng CheckRange như Small (không copy bug B)
  - Device identity: workstation_id + device_fingerprint (đã có hạ tầng workstation-matrix.md)
  - Heartbeat: gửi định kỳ tới backend, đánh dấu online/offline
  - Offline buffer: SQLite cục bộ (đã yêu cầu ở CLAUDE.md mục 5) khi mất mạng, tự đồng bộ khi có mạng lại kèm Idempotency Key
  - Timestamp kép: device_timestamp (lúc đo) + server_timestamp (lúc backend nhận) — chống lệch giờ
```

**Vị trí đặt logic StableFilter/ExtractLastNumber:** đề xuất đặt ở Agent (gần hardware, latency thấp) NHƯNG phải có **version thuật toán** (`scale_algorithm_version`) lưu kèm mỗi sample, để khi thay đổi thuật toán không làm lẫn dữ liệu cũ/mới khi phân tích lịch sử — đối chiếu `p0-c-scale-algorithm.md` đã có 3 golden test đề xuất, cần bổ sung version field.

---

## 3. Kiến trúc Print Agent (đề xuất, đối chiếu mục 5.4)

```
PrintAgent (Windows Service tại máy QR_LABEL_PRINTING)
  - Nhận Print Job từ backend (qua polling hoặc WebSocket — KHÔNG dùng điều khiển chuột/clipboard như VBA ClickAt/SendTextToApp)
  - Trạng thái job: queued -> printing -> printed | failed | cancelled
  - Retry có giới hạn (không in trùng không kiểm soát — VBA gốc không có khái niệm "retry", chỉ có nút bấm lại thủ công của người dùng)
  - Ghi log mỗi lần in: workstation, printer, template, payload, số bản, người thao tác, thời gian, kết quả, lỗi
  - Giao tiếp máy in TSC qua USB/LAN port 9100 (đã có LabelPrinter.cs — cần audit lại, ngoài phạm vi VBA)
```

**Backend tạo Print Job → PrintAgent nhận → thực hiện in → báo trạng thái ngược lại** — khớp mô hình đã mô tả ở `system-context.md` mục 4.2, chỉ cần bổ sung 5 trạng thái tường minh (hiện tài liệu cũ chưa liệt kê đủ `cancelled`).

---

## 4. Contract đầy đủ (bổ sung 2026-07-17, Phase C — đối chiếu mục 11)

### 4.1. Chức năng bắt buộc (mục 11.1)

Kết nối cân; kết nối máy in; heartbeat; device discovery hoặc configured binding (đề xuất **configured binding** — Admin gán cứng device↔workstation 1 lần qua `WorkstationAdminController`, không auto-discover để tránh gán nhầm); nhận print job; gửi trạng thái print; gửi weighing sample; buffer offline (SQLite cục bộ); retry (có giới hạn); idempotency (`sequence_no` cho sample, `idempotency_key` cho print job); local structured log (JSON lines, xoay vòng theo dung lượng); agent version reporting (mỗi heartbeat).

### 4.2. Bảo mật (mục 11.2)

| Yêu cầu | Thiết kế đề xuất |
|---|---|
| Device credential riêng | `POST /api/agent/register` đổi `registration_token` (cấp 1 lần bởi Admin) → `device_credential` dài hạn — đã có khung sườn ở `workstation-matrix.md` Mục 7, mở rộng cho từng `device_id` thay vì chỉ `workstation` |
| Không dùng tài khoản người dùng cho agent | Toàn bộ `/api/agent/*` xác thực bằng device credential, tách hẳn Sanctum/JWT của user |
| Rotate credential | Đề xuất TTL cho `device_credential` (ví dụ 90 ngày), endpoint `POST /api/agent/rotate-credential` (thiết kế, chưa code) |
| TLS | Bắt buộc HTTPS nội bộ (LAN) giữa Agent↔Backend |
| Request signing/token | Bearer `device_credential` + HMAC chữ ký payload cho endpoint ghi dữ liệu (print report, scale sample) — chống giả mạo nếu credential lộ |
| Whitelist workstation/device | Kiểm tra `device_id` gửi lên phải khớp `workstation_id` đã đăng ký (Mục `state-machines.md` #6) |
| Agent không gọi API nghiệp vụ ngoài phạm vi | Middleware riêng cho nhóm route `/api/agent/*`, KHÔNG cho phép truy cập `/api/chemical-call-requests`, `/api/production-orders`... |
| Audit mọi command server→agent | Mỗi print job đẩy xuống ghi `PRINT_JOB_CREATED` kèm `device_id`; mọi lệnh điều khiển khác (nếu có trong tương lai) đều qua `device_events` |

> [!IMPORTANT]
> **Cập nhật triển khai 2026-07-17 (Phase E, phạm vi rút gọn):** Trước khi sửa, phát hiện 2 vấn đề thật trong code đã sinh sẵn: (1) 3 route Agent .NET **thực sự đang gọi** (`POST /devices/readings`, `GET /agents/{workstation_id}/jobs`, `POST /jobs/{job_id}/ack`) **hoàn toàn không xác thực** — bất kỳ ai cũng ghi được dữ liệu cân/job giả mạo qua `workstation_id` đoán được; (2) `AgentController` (register/heartbeat/event, đúng mô hình `device_id` như thiết kế ở trên) bị đặt **sau `auth:sanctum`** — sai nguyên tắc "không dùng tài khoản người dùng cho agent" — và **hoàn toàn mồ côi, Agent .NET chưa từng gọi tới**.
>
> Đã vá bằng middleware `AgentAuth` (`backend/app/Http/Middleware/AgentAuth.php`, alias `agent.auth`) — **tái dùng cơ chế `registration_token_hash` của `app.workstations` đã có sẵn** (cùng hạ tầng phục vụ handshake trình duyệt kiosk qua `WorkstationGuard`) làm credential rút gọn cho Agent, thay vì dựng bảng `device_credentials` + HMAC + rotation riêng như đề xuất đầy đủ ở trên. Đã áp `agent.auth` cho cả 3 route Agent thật sự dùng và toàn bộ nhóm `AgentController` (di chuyển ra khỏi `auth:sanctum`); `agent/Worker.cs` gửi header `X-Workstation-Token` (cấu hình `Workstation:Token` trong `appsettings.json`, để trống mặc định — phải điền khi triển khai từng trạm). Có test enforcement thật (không chỉ dựa vào bypass môi trường test) trong `ScaleLiveWeightTest`/`PrintJobPipelineTest`.
>
> **CHƯA làm** (vẫn đúng như đề xuất đầy đủ ở trên, chưa đổi trạng thái): credential theo từng **thiết bị vật lý** riêng biệt (hiện là 1 token/workstation, không phân biệt cân vs máy in cùng trạm); **rotation/TTL**; **HMAC ký payload**; whitelist device_id (mới có whitelist **workstation_id**, chưa có device_id vì schema hiện tại của 3 route thật không mang device_id). Middleware mặc định **bị bỏ qua trong môi trường `testing`** trừ khi có header `X-Enforce-Workstation-Guard` (theo đúng quy ước đã có của `WorkstationGuard`) — cần xác nhận trước UAT rằng môi trường thật (không phải `testing`) sẽ luôn enforce.

### 4.3. Print job protocol (mục 11.3)

Mỗi print job: `job_id (uuid)`, `idempotency_key`, `printer_id (device_id)`, `template`, `payload`, `copies`, `created_at`, `expiry`, `retry_policy {max_attempts, backoff}`, `correlation_id` (liên kết `dispatch_id`). Agent trả 1 trong 6 trạng thái: `RECEIVED`, `PRINTING`, `PRINTED`, `FAILED`, `REJECTED`, `EXPIRED` — khớp `state-machines.md` Mục 4.

### 4.4. Scale protocol (mục 11.4)

Mỗi sample: `agent_id`, `device_id`, `workstation_id`, `sequence_no (tăng dần, không lặp lại)`, `device_timestamp`, `agent_timestamp`, `value`, `unit`, `raw_data` (tùy chọn, để truy vết ngược nếu thuật toán làm sạch sai), `stability_flag` (nếu Agent tự tính `StableFilter`), `quality_code` (`OK`/`GARBAGE`/`DEVICE_ERROR`). Backend chống trùng bằng `UNIQUE(device_id, sequence_no)` (xem `erd-target.md` Mục 3) — KHÔNG dùng timestamp để chống trùng (đồng hồ có thể lệch/trùng giây).

## 5. Feature flags (đối chiếu mục 11 — KHÔNG hard-code phạm vi pilot)

Đề xuất bảng cấu hình `app.feature_flags` (key-value, per-environment hoặc per-workstation-type):

| Flag | Mặc định đề xuất | Ghi chú |
|---|---|---|
| `chemical_call_enabled` | `false` | Bật khi domain `chemical-call-domain.md` build xong tối thiểu |
| `production_order_enabled` | `true` | Đã có nền tảng |
| `qr_label_printing_enabled` | `true` (nhưng B24/scale_check chưa đủ — xem `qr-label-printing-domain.md`) | Cân nhắc tách flag con `qr_label_printing_b24_routing_enabled` |
| `small_scale_enabled` | `true` | — |
| `large_scale_enabled` | `true` | — |
| `b24_routing_enabled` | `false` | Chờ xác nhận 15L + lỗ hổng VD14-16 |
| `local_print_agent_enabled` | `false` cho tới khi Print Agent xác nhận không dùng kỹ thuật RPA | — |
| `local_scale_agent_enabled` | `false` cho tới khi PB-1/PB-2 (ExtractLastNumber/StableFilter) sửa xong | — |

**Không cài cứng danh sách workstation nào tham gia pilot trong source code** — đọc từ bảng flag/cấu hình, để có thể bật/tắt từng workstation độc lập theo tiến độ thật, đúng yêu cầu mục 11.
