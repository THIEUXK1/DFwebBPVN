# Target Test Architecture — Kiến Trúc Kiểm Thử (test-architecture.md)

Lập 2026-07-17 — Phase C/D. Thiết kế chi tiết cho kiến trúc kiểm thử toàn diện của hệ thống Web mới và Local Agent, bao gồm cả các kiểm thử biên, lỗi kết nối và kiểm thử đồng thời nghiệp vụ. Tài liệu thiết kế — không sửa code sản xuất.

---

## 1. Bản Đồ Các Cấp Độ Kiểm Thử (Testing Hierarchy)

Hệ thống được đảm bảo chất lượng thông qua 6 cấp độ kiểm thử chính:

```
[Unit Tests] ──> [State & Policy Tests] ──> [Contract & Integration] ──> [Agent Sim] ──> [E2E & Pilot]
```

1. **Unit Tests (Kiểm thử đơn vị):** Kiểm tra các hàm logic độc lập, các helper class và các model validation.
2. **State Transition Tests (Kiểm thử máy trạng thái):** Đảm bảo các chuyển đổi trạng thái (Chemical Call, Production Order, Print Job...) tuân thủ đúng bảng quy tắc, chặn đứng chuyển đổi trạng thái không hợp lệ.
3. **Policy Tests (Kiểm thử chính sách nghiệp vụ):** Kiểm tra độc lập các rule engine cô lập như `B24RoutingPolicy`, `StableFilterPolicy`, `ToleranceCheckPolicy` với các test vector đầu vào đa dạng.
4. **Contract & API Integration Tests:** Kiểm tra tính đúng đắn của các API Endpoint về mặt định dạng request/response, mã lỗi HTTP, phân quyền truy cập và tính idempotent.
5. **Agent Simulator & Hardware Emulation:** Dùng virtual environment/com port và log file tĩnh để chạy thử Agent mà không có thiết bị thật.
6. **Concurrency & Failure Injection Tests:** Giả lập các tình huống tranh chấp tài nguyên, nghẽn mạng LAN, rớt kết nối máy in để kiểm tra độ bền của hệ thống.

---

## 2. Thiết Kế Các Kịch Bản Kiểm Thử Đặc Biệt (Edge Cases & Concurrency)

Dưới đây là thiết kế chi tiết cho 10 kịch bản kiểm thử bắt buộc theo yêu cầu:

### 2.1. Confirm Row Gọi Hai Lần (Double Submit Confirm)
- **Mô tả:** Người dùng bấm nút xác nhận dòng gửi hàng (ConfirmRow) 2 lần liên tiếp nhanh chóng.
- **Thiết kế kiểm thử:**
  - Frontend gửi request POST `/api/dispatch-jobs/{id}/confirm` đầu tiên kèm `idempotency_key = "key-abc"`.
  - Trước khi request 1 nhận response, frontend (hoặc công cụ test) gửi tiếp request POST 2 với cùng `idempotency_key = "key-abc"`.
  - **Kết quả kỳ vọng:** Database áp dụng transaction lock hoặc check unique index. Request 1 xử lý bình thường. Request 2 nhận được kết quả xử lý của request 1 (hoặc mã 200 no-op kèm kết quả cũ) thay vì chạy lại logic in tem và sinh QR lần 2.

### 2.2. Hai Người Xác Nhận Cùng Một Dòng Đồng Thời (Confirm Concurrency)
- **Mô tả:** Hai người vận hành tại 2 máy trạm khác nhau cùng mở màn hình và cùng bấm "Confirm" trên một dòng hàng chờ.
- **Thiết kế kiểm thử:**
  - Giả lập 2 HTTP thread đồng thời gửi request confirm cho cùng 1 `dispatch_id` nhưng dùng `idempotency_key` khác nhau (do sinh từ 2 phiên trạm khác nhau).
  - Sử dụng `SELECT FOR UPDATE` để khóa dòng hoặc kiểm tra `row_version`.
  - **Kết quả kỳ vọng:** Chỉ 1 thread ghi nhận thành công và chuyển trạng thái sang `CONFIRMED`. Thread còn lại nhận lỗi `409 Conflict` hoặc `422 ALREADY_CONFIRMED`.

### 2.3. In Thành Công Nhưng Mất Response Về Server (Silent Print Success)
- **Mô tả:** Local Print Agent in nhãn ra giấy thành công, máy in chạy bình thường, nhưng ngay lúc đó mạng LAN bị rớt khiến Agent không thể gửi response `agent_success` về server.
- **Thiết kế kiểm thử:**
  - Print Agent nhận job in, thực hiện in thô thành công.
  - Chặn kết nối mạng của Agent. Agent thử gửi response lên server nhưng lỗi kết nối.
  - Agent lưu trạng thái `PRINTED` vào SQLite cục bộ.
  - Khôi phục mạng. Agent chạy scheduled job đồng bộ hàng chờ cục bộ lên Backend.
  - **Kết quả kỳ vọng:** Server cập nhật trạng thái `print_jobs` thành `PRINTED`. Không tự động in lại tem nhãn đó khi Agent kết nối lại.

### 2.4. Agent Gửi Lại Weighing Sample Cũ (Replayed Weight Samples)
- **Mô tả:** Agent gửi lại sample đã gửi rồi do trước đó không nhận được ACK từ server.
- **Thiết kế kiểm thử:**
  - Agent gửi request POST `/api/weighing-jobs/{id}/samples` với `sequence_no = 456`. Server nhận và lưu thành công.
  - Agent gửi lại y hệt request trên với `sequence_no = 456`.
  - **Kết quả kỳ vọng:** Ràng buộc `uq_device_sequence` chặn trùng ở database. Server trả về HTTP 200 (idempotent no-op), không sinh thêm bản ghi cân mới.

### 2.5. Quét QR Hai Lần Tại Trạm Cân (Double QR Scan)
- **Mô tả:** Operator quét mã QR của mẻ nhuộm đã cân xong tại trạm cân.
- **Thiết kế kiểm thử:**
  - Gửi mã QR của một mẻ có trạng thái weighing job là `COMPLETED`.
  - **Kết quả kỳ vọng:** Hệ thống từ chối cho phép sửa dữ liệu cân, hiển thị job ở chế độ Read-Only hoặc trả lỗi `409 QR_ALREADY_PROCESSED`.

### 2.6. RECORD_A và RECORD_B Không Match (Correlation Exception)
- **Mô tả:** Hai database cũ lưu dữ liệu điều phối và dữ liệu cân lệch pha, không tìm thấy cặp trùng khớp.
- **Thiết kế kiểm thử:**
  - Chạy script đối soát với mẻ cân có mã hàng/màu không tồn tại bên dispatch.
  - **Kết quả kỳ vọng:** Bản ghi cân không bị gán bừa bãi sang dispatch khác, mà được đẩy vào bảng `app.legacy_exception_queue_items` với lý do `UNMATCHED_BUSINESS_KEY` để giám sát kiểm tra thủ công.

### 2.7. B24 Rơi Vào Lỗ Hổng D1 (B24 D1 Gap Case)
- **Mô tả:** Mẻ nhuộm thuộc máy VD14-VD16 + 3C/4D nhưng không có rule D1 đi kèm.
- **Thiết kế kiểm thử:**
  - Chạy `WarehouseRoutingService` với mẻ nhuộm thỏa mãn điều kiện trên khi feature flag `b24_d1_fix_enabled` đang OFF và `manual_routing_review_enabled` đang ON.
  - **Kết quả kỳ vọng:** Hệ thống không tự động route sai kho, mà chuyển trạng thái mẻ sang chờ duyệt thủ công (`needs_manual_review = true`) và ghi warning log.

### 2.8. Mẻ 15L Nhưng Không Có Rule Riêng (15L Non-Override Case)
- **Mô tả:** Xử lý mẻ nhuộm có nhãn "15L" khi chưa có xác nhận nghiệp vụ về quy tắc in/cân riêng.
- **Thiết kế kiểm thử:**
  - Chạy luồng dispatch cho mẻ 15L. Đánh dấu blocker `CH-BUS-011` hoạt động.
  - **Kết quả kỳ vọng:** Áp dụng template in và quy trình cân tiêu chuẩn (không tạo nhánh logic code hard-code "15L" riêng biệt). Hệ thống ghi log warning cho mẻ này để kiểm toán sau.

### 2.9. LARGE_SCALE Không Tái Xuất Hiện Lỗi Rò Rỉ Timer (Large Scale Timer Leak Prevention)
- **Mô tả:** Đảm bảo lỗi rò rỉ bộ nhớ/timer do gọi chồng chéo hàm `StartWatchFormPos` của Excel cũ không xuất hiện trên giao diện cân mới.
- **Thiết kế kiểm thử:**
  - Trên giao diện cân Web mới (Kiosk), chuyển qua chuyển lại liên tục giữa các tab hoặc kích hoạt/hủy kích hoạt màn hình cân 100 lần.
  - **Kết quả kỳ vọng:** Không rò rỉ CPU thread hoặc memory leak. Giao diện giải phóng sạch tài nguyên đo đạc cũ trước khi khởi động phiên đo mới.

### 2.10. ACCEPTED/REJECTED Của LARGE_SCALE Hiển Thị Đúng (Large Scale Color Bug Prevention)
- **Mô tả:** Đảm bảo giá trị cân đạt dung sai tại trạm LARGE_SCALE được hiển thị màu xanh lá (`ACCEPTED`) và ghi nhận đúng trạng thái chấp nhận (không bị lỗi so sai mã màu như VBA cũ).
- **Thiết kế kiểm thử:**
  - Thực hiện cân mẻ cân với giá trị nằm trong dải dung sai cho phép (ví dụ: 1.00kg, dải cho phép 0.99 - 1.01).
  - **Kết quả kỳ vọng:** Giao diện đổi màu xanh lá, kết quả lưu vào database ghi nhận `tolerance_status = 'ACCEPTED'`.

### 2.11. Chemical Call — Gọi trùng (Duplicate Order)
- Gửi 2 request `order` liên tiếp cho cùng `channel_id` (khác `idempotency_key`, giả lập 2 lần bấm thật). **Kỳ vọng:** request 2 nhận `409 CHANNEL_ALREADY_ORDERED` (vi phạm `uq_channel_active_order`), KHÔNG tạo 2 request `ORDERED` song song — đối chiếu `state-machines.md` Mục 1.

### 2.12. Chemical Call — Chuyển trạng thái ORDER→DONE hợp lệ/không hợp lệ
- Test toàn bộ bảng transition ở `state-machines.md` Mục 1: mọi cạnh hợp lệ PASS, mọi cạnh KHÔNG có trong bảng (vd. `CREATED→DONE` bỏ qua `ORDERED`) phải bị chặn `409 INVALID_STATE_TRANSITION`.

### 2.13. Reload trình duyệt không mất trạng thái
- Mở màn hình vận hành, thực hiện 1 nửa luồng (vd. đã `scan_qr` nhưng chưa `post_result`), reload trang. **Kỳ vọng:** trạng thái đọc lại đúng từ server (`weighing_jobs`/`chemical_call_requests` hiện tại), KHÔNG có state nào chỉ tồn tại ở frontend bị mất — đúng nguyên tắc "không mô phỏng trạng thái ở frontend" (`chemical-call-domain.md` Mục 4 #10).

### 2.14. Production Order — Optimistic conflict & Double approve
- 2 phiên cùng sửa 1 đơn với `row_version` cũ → phiên thứ 2 nhận `409 VERSION_CONFLICT`.
- 2 Trưởng ca cùng bấm `approve` gần như đồng thời → chỉ 1 thành công, người còn lại nhận `409`/idempotent-safe theo state machine (`state-machines.md` Mục 2).

### 2.15. Printer Offline / Retry / Reprint
- Print Agent báo `agent_error` khi máy in mất kết nối → job chuyển `FAILED`, tự động retry theo `retry_policy` (giới hạn số lần, `state-machines.md` Mục 4) — KHÔNG retry vô hạn.
- `print.reprint` yêu cầu `reason` bắt buộc (thiếu → `422 REASON_REQUIRED`), tạo `print_attempts` mới nhưng KHÔNG tạo `qr_payloads`/`dispatch_events` mới (đúng `api-contracts.md` nhóm QR/Print).

### 2.16. QR Golden Master
- So sánh `qrDye`/`qrChem`/`qrProcess`/`qrExtra`/`qrFB` sinh bởi `QrPayloadService` với payload thật trích từ VBA gốc (`b24-warehouse-routing.md` Mục 4) trên cùng bộ input — phải khớp **byte-for-byte** cho phần cấu trúc chuỗi (không chỉ giá trị số).

### 2.17. Stable Weight Algorithm
- Test `StableFilterPolicy` với các chuỗi sample: hội tụ nhanh (PASS ngay), dao động liên tục (không bao giờ đạt stable — timeout), rác lẫn giá trị hợp lệ (không bị nhiễu bởi 1 sample rác đơn lẻ) — đối chiếu 3 golden test đã đề xuất ở `vba-migration-matrix.md` nhóm SCALE.

### 2.18. Permission Denied
- Operator SMALL_SCALE_01 gọi API `weighing.accept` cho job thuộc SMALL_SCALE_02 → `403 WORKSTATION_SCOPE_MISMATCH` (không phải lỗi permission chung chung — đúng `permission-matrix.md` Mục 3.0).
- User không có `chemical_call.reset` gọi endpoint reset → `403`, có ghi log intent (không phải silent fail).

### 2.19. Feature Flag OFF
- Gọi API domain khi flag tương ứng OFF ở cấp Instance → `403 FEATURE_DISABLED {flag_key, scope}` (đúng `feature-flags.md` Mục 2.2), UI hiển thị disable+tooltip chứ không ẩn nút.

### 2.20. Migration Rerun / Backfill Rerun
- Chạy lại 1 migration Wave đã áp dụng → không lỗi, không tạo bảng trùng (idempotent theo `IF NOT EXISTS`/kiểm tra version).
- Chạy lại backfill đã chạy 1 lần → 100% dòng rơi vào `DUPLICATE_LEGACY_ID` (Mục 3.4 `backfill-plan.md`), 0 dòng tạo mới.

### 2.21. Rollback Pilot
- Kích hoạt kịch bản rollback 1 workstation (theo `cutover-rollback-plan.md` Mục 2.5) trong môi trường test — xác nhận: flag tắt đúng scope, Agent nhận lệnh dừng đúng cách (graceful, không cắt ngang job đang chạy), dữ liệu audit/print/weighing KHÔNG bị xóa.

---

## 3. Test Data (mục 7.2 yêu cầu — phân biệt rõ, không dùng toàn dữ liệu giả đơn giản)

| Loại | Nguồn | Dùng cho |
|---|---|---|
| **Synthetic fixture** | Sinh tay/factory (Faker theo pattern VD01-18, màu/mã hợp lệ) | Unit test, test biên nhanh (không cần dữ liệu thật) |
| **Sanitized legacy fixture** | Trích từ `database-inventory.md`/`_samples.json` thật, đã ẩn danh nếu cần (dữ liệu màu/mã ở đây không phải thông tin cá nhân, có thể dùng gần nguyên trạng) | Integration test, backfill dry-run test — đảm bảo transform đúng với hình dạng dữ liệu thật (kể cả dữ liệu bẩn: `TIME2=null`, `TANK=""`, `ISSENT` kiểu Text không nhất quán) |
| **Golden master fixture** | Kết quả tính tay/trích xuất trực tiếp từ VBA (B24 Mục 10 `b24-warehouse-routing.md`, 3 golden test SCALE) | Golden master test — so sánh byte-for-byte/số-học-chính-xác |
| **Device simulator data** | `putty_log.txt` giả lập (đã có tiền lệ `ScaleReader._simulationFilePath`), print job giả lập | Agent simulator test |
| **Failure scenarios** | Chuỗi input cố ý lỗi: mất mạng giữa chừng, sample trùng sequence, QR payload rỗng, Access field NULL bất thường | Failure injection test |

**Không dùng "toàn dữ liệu giả đơn giản" để kết luận tương đương VBA** — mọi golden test/B24 test BẮT BUỘC dùng Sanitized legacy fixture hoặc Golden master fixture, không phải Synthetic fixture.

---

## 4. Test Isolation cho 2 SMALL_SCALE (mục 7.3 — mở rộng `WorkstationDeviceIsolationTest` đã đề xuất ở `menu-workstation-device-architecture.md` Mục 15)

| # | Kịch bản | Kỳ vọng |
|---|---|---|
| 1 | SMALL_SCALE_01 và SMALL_SCALE_02 cùng nhận dữ liệu đồng thời | Cả 2 job xử lý độc lập, không block lẫn nhau |
| 2 | Không đọc nhầm job | Job list tại mỗi instance chỉ hiển thị job có `workstation_id` đúng |
| 3 | Không nhận sample của nhau | Sample gửi từ `Scale_02` KHÔNG xuất hiện trong `weighing_job_items` đang mở tại SMALL_SCALE_01 |
| 4 | Không dùng nhầm scale device | `scale_devices` mapping đúng 1-1 qua `workstation_devices`, API `POST /api/weighing-jobs/{id}/samples` validate `device_id` thuộc đúng `workstation_id` của job |
| 5 | Không dùng nhầm printer (nếu SMALL_SCALE có in tem phụ) | Tương tự Mục 4, qua `workstation_printers` |
| 6 | Không trộn lịch sử | `GET /api/weighing-jobs/{job_id}/history` chỉ trả dữ liệu của đúng job/instance |
| 7 | Không trùng sequence giữa 2 thiết bị | `UNIQUE(device_id, sequence_no)` — 2 device khác nhau có thể cùng `sequence_no=1` mà không xung đột (unique theo cặp, không theo `sequence_no` đơn) |
| 8 | 1 máy offline không ảnh hưởng máy còn lại | Tắt Agent SMALL_SCALE_02 (`device.status=OFFLINE`) — SMALL_SCALE_01 tiếp tục hoạt động bình thường, không có dependency chung ngoài database (đã tách domain/service dùng chung nhưng data cô lập theo `workstation_id`) |

---

## 5. Coverage & VBA→Test Mapping (mục 7.5)

**Nguyên tắc:** coverage % là chỉ báo phụ, KHÔNG thay thế việc kiểm tra đúng hành vi nghiệp vụ — 1 dòng code có coverage 100% vẫn có thể sai logic nếu test không assert đúng giá trị kỳ vọng (bài học từ chính VBA gốc: nhiều bug như `GetProcessStatus` màu sai vẫn "chạy được", không crash).

**Ngưỡng coverage đề xuất theo domain:** Domain Policy (B24, StableFilter, Tolerance) ≥ 90% (thuần logic, dễ test đầy đủ nhánh); Application Service ≥ 80%; Controller ≥ 60% (mỏng, ít logic — đã đẩy xuống Service theo `domain-architecture.md` Mục 2).

**Mapping bắt buộc (mẫu, áp dụng cho mọi procedure có trạng thái `MISSING`/`PARTIALLY_MIGRATED` trong `vba-migration-matrix.md` đang được Phase C/D thiết kế):**

| VBA procedure | Target behavior | Test case | Evidence |
|---|---|---|---|
| `VBA-CHEM-008 HandleButton` + `VBA-CHEM-009 UpdateStatus` | `ChemicalCallService::order()`/`complete()` | 2.11, 2.12 | `ChemicalCallServiceTest`, `ChemicalCallConcurrencyTest` |
| `DF028.TO_SEND.ConfirmRow` | `ConfirmDispatchService::confirm()` (13 bước) | 2.1, 2.2 | `ConfirmDispatchServiceTest` |
| `Mod_printslip.PrintSlip_70x100` (B24) | `B24RoutingPolicy` | 2.7, 2.16, 8 test case `b24-warehouse-routing.md` Mục 10 | `B24RoutingPolicyTest` |
| `Modcleanweight`/`Mod_delta_raw` (StableFilter/ExtractLastNumber) | `StableFilterPolicy`/`ScaleCore` | 2.17 | `WeighingCoreServiceTest`, golden test SCALE |
| `Mod_lockmoveform` (bug LARGE_SCALE) | KHÔNG migrate — `NOT_MIGRATED_LEGACY_BUG` | 2.9 | Regression test riêng |
| `Mod_print_tsc224.GetProcessStatus` (bug màu) | KHÔNG migrate — `NOT_MIGRATED_LEGACY_BUG` | 2.10 | Regression test riêng |

Bảng đầy đủ (mọi procedure, không chỉ mẫu trên) đề xuất duy trì như 1 cột bổ sung trong `vba-migration-matrix.md` khi bước sang Phase E (thêm cột `Test Case` cho từng dòng khi bắt đầu code thật) — trong Phase C/D chỉ mapping ở mức cluster (xem cập nhật "BẢNG ƯU TIÊN HÓA" cuối `vba-migration-matrix.md`).

---

## 6. Kịch bản E2E Integration Tests bắt buộc (Scenarios A - G)

### Scenario A – Production Order đến QR
1. **Tạo đơn:** Tạo bản ghi đơn hàng mới thông qua `ProductionBatchController::store` (ghi nhận mã màu, mã hàng, máy, tank, level).
2. **Duyệt đơn:** Gọi API `POST /api/production-orders/{id}/approve` duyệt đơn trong Transaction, kiểm tra quy tắc Capacity 250L.
3. **Tạo dispatch queue:** Tự động chèn bản ghi điều phối `app.machine_dispatches` ở trạng thái `QUEUED` (mô phỏng `tbl_tosend`).
4. **DF028/web nhận queue:** Màn hình `PrintStation.vue` tải hàng đợi hiển thị đúng mẻ nhuộm vừa duyệt.
5. **Confirm:** Admin bấm xác nhận in trên trạm in.
6. **Tạo QR:** Hệ thống sinh chuỗi QR thô thông qua `QrPayloadService` theo format VBA chuẩn.
7. **Tạo print job:** Lưu bản ghi `app.print_jobs` để Agent in.
8. **Ghi SentLog equivalent:** Lưu Audit Log hành động in, đánh dấu `scale_checked = true` trong database.
9. **Trạng thái:** Chuyển trạng thái mẻ sang `READY_FOR_WEIGHING`.

### Scenario B – SMALL_SCALE 01
1. **Scan QR:** Nhập mã QR của mẻ nhuộm tại trạm `SMALL_SCALE_01`.
2. **Claim job:** Gọi API `POST /api/weighing-jobs/claim` thành công để chiếm quyền cân cho trạm `WS-SMALL-01`.
3. **Nhận sample:** Live Stream dữ liệu cân từ Agent qua API endpoint `POST /api/devices/readings`.
4. **Stable:** Bộ lọc ổn định phát hiện 2 số cân giống nhau liên tiếp, kích hoạt nút xác nhận.
5. **Accept:** Số cân nằm đúng dải dung sai mục tiêu ($\pm 1\%$).
6. **Complete:** Bấm lưu kết quả cân.
7. **Ghi RECORD_B:** Kết quả cân được lưu thành công vào bảng kết quả (`app.weighing_results` đại diện cho `RECORD_B.tblRECORD`) và log tồn kho được lưu vào `app.warehouse_logs` (đại diện cho `WAREHOUSE.tblWH_LOG`).
8. **Correlate về dispatch:** Tự động tạo bản ghi đối chiếu `app.correlation_links` giữa mẻ cân và mẻ điều phối với độ tin cậy `confidence = 1.00`.

### Scenario C – SMALL_SCALE isolation
1. **Hoạt động song song:** Trạm `SMALL_SCALE_01` xử lý QR A; trạm `SMALL_SCALE_02` xử lý QR B.
2. **Cô lập luồng:**
   - Dữ liệu cân từ Agent 1 chỉ đi vào phiên cân trạm 1.
   - Trạm 2 không thể can thiệp hay hoàn tất job của trạm 1 (nỗ lực claim chéo bị từ chối bằng lỗi `409 Conflict`).
   - Sequence số 100 của Agent 1 hoàn toàn độc lập với sequence số 100 của Agent 2 trong DB.

### Scenario D – LARGE_SCALE
1. **Scan QR phù hợp:** Quét tem mẻ cân lớn tại trạm `LARGE_SCALE_01`.
2. **Áp dụng LargeScalePolicy:** Trạm cân cấu hình hiển thị số nguyên (làm tròn gram), bộ lọc ổn định yêu cầu 3 số đọc giống nhau.
3. **Không timer leak:** Kiểm tra khi Operator thoát màn hình cân lớn, các bộ đếm thời gian và luồng lắng nghe cân của WebSocket được giải phóng triệt để.
4. **ACCEPTED/REJECTED đúng:** Kiểm tra nhãn hiển thị màu sắc và kết quả dung sai (đúng màu xanh cho Accepted và đỏ cho Rejected).
5. **Complete:** Xác nhận lưu, hệ thống ghi nhận kết quả và log kho tương ứng.

### Scenario E – Duplicate (Chống ghi trùng & Idempotency)
1. **Duyệt đơn hai lần:** Gửi 2 request duyệt cùng mẻ cùng lúc $\rightarrow$ Request 2 bị chặn bằng cơ chế khóa dòng (optimistic/row-level lock) và trả về lỗi `409 Conflict` hoặc no-op.
2. **ConfirmRow hai lần:** Gửi 2 lệnh in cùng lúc $\rightarrow$ Trạm in kiểm tra `idempotency_key`, từ chối tạo Job in trùng.
3. **Print callback hai lần:** Giả lập Agent gửi kết quả in thành công 2 lần $\rightarrow$ Hệ thống chỉ ghi nhận 1 log in.
4. **Scan QR hai trạm cùng lúc:** 2 trạm cân cùng quét và gửi claim cùng 1 QR $\rightarrow$ Trạm gửi sau bị từ chối với lỗi `409 Conflict`.
5. **Scale sample gửi lại:** Local Agent gặp lỗi mạng gửi trùng lặp sample cân $\rightarrow$ Core check sequence number, loại bỏ duplicate sample thô.
6. **Complete job hai lần:** Bấm hoàn tất cân mẻ 2 lần $\rightarrow$ Bị từ chối do trạng thái mẻ cân đã chuyển thành `COMPLETED`.

### Scenario F – Lỗi máy in
1. **Printer offline:** Local Agent máy in rớt kết nối hoặc máy in kẹt giấy.
2. **Retry:** Người dùng bấm "Retry" trên giao diện Kiosk để gửi lại lệnh.
3. **Response bị mất:** Lệnh in ra nhãn thành công nhưng response gửi về server bị mất $\rightarrow$ Job được lưu ở trạng thái `PRINT_RESULT_UNKNOWN`.
4. **Không tự in trùng:** Hệ thống khóa không tự động in lại trên máy in dự phòng cho đến khi Operator xác nhận thủ công.
5. **Reprint có lý do:** Nếu phải in lại nhãn, Operator bắt buộc nhập lý do in lại ($\ge 5$ ký tự) và được ghi Audit Log rõ ràng.

### Scenario G – Mất mạng cân
1. **Agent buffer:** Khi Kiosk trạm cân mất kết nối LAN tới Server trung tâm $\rightarrow$ Agent tiếp tục thu thập dữ liệu cân và lưu vào SQLite ngoại tuyến cục bộ (Offline Queue).
2. **Reconnect:** Khôi phục kết nối mạng.
3. **Gửi lại theo sequence:** Agent tự động đẩy các gói tin cân trong offline queue lên backend theo đúng thứ tự thời gian.
4. **Không trùng/mất sample:** Backend xử lý gói tin đi kèm Idempotency Key, đảm bảo dữ liệu cân được phục hồi nguyên vẹn và không bị ghi nhận trùng lặp.

