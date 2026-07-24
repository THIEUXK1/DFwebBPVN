# Kiến trúc Domain Mục tiêu (domain-architecture.md)

Lập 2026-07-17 — Phase C (Target Design). Nguồn: `chemical-call-domain.md`, `qr-label-printing-domain.md`, `b24-warehouse-routing.md`, `local-agent-architecture.md`, `legacy-database-mapping.md` (Phase A/B đã hoàn tất). Đây là tài liệu THIẾT KẾ — không code sản xuất, không migration, không đổi schema thật.

**Baseline bắt buộc:** 6 máy nghiệp vụ / 5 workstation type (`CHEMICAL_CALL`×1, `PRODUCTION_ORDER`×1, `QR_LABEL_PRINTING`×1, `SMALL_SCALE`×2, `LARGE_SCALE`×1). 4 database legacy định danh: `RECORD_A` (dispatch/queue), `RECORD_B` (cân), `WAREHOUSE` (`WH.accdb`), `CHEM_ORDER` (`chem_order.accdb`), cộng `DF_STORAGE` (legacy/reference — xem Mục 9). Không gộp RECORD_A/RECORD_B.

---

## 1. 8 Domain — ranh giới rõ ràng

Tổ chức code theo domain nghiệp vụ (không theo workbook). Mỗi domain dưới đây liệt kê đủ 11 thành phần theo yêu cầu mục 3: Entity / Value Object / State Transition / Application Service / Domain Policy / Repository / API / Permission / Audit Event / Integration Event / Test.

### 1.1. Workstation & Device Management (nền tảng — dựng trước các domain khác)

> Kiến trúc menu (3 tầng Workstation Type → Instance → Device, quản lý máy in/cân bằng cấu hình thay vì hard-code, giao diện Admin, luồng load cấu hình tự động) đã được đặc tả đầy đủ tại [`menu-workstation-device-architecture.md`](file:///F:/DF/.claude/menu-workstation-device-architecture.md) — bảng dưới đây giữ nguyên làm tóm tắt ranh giới domain, không lặp lại chi tiết schema (đã chuyển sang `erd-target.md` Mục 2.1).

| Thành phần | Nội dung |
|---|---|
| Entity | `WorkstationType`, `Workstation`, `Device`, `WorkstationDevice`, `DeviceHeartbeat`, `DeviceEvent`, `WorkstationSession` |
| Value Object | `DeviceFingerprint`, `RegistrationToken`, `HeartbeatPayload` |
| State Transition | Device: `PENDING_REGISTRATION → ACTIVE → OFFLINE ⇄ ACTIVE → DISABLED` (xem `state-machines.md` Mục 5) |
| Application Service | `WorkstationRegistrationService` (đã có), `DeviceAdminService` (mở rộng) |
| Domain Policy | `WorkstationTypePolicy` (khóa loại trạm 1 lần theo Admin, xem `workstation-matrix.md` Mục 7) |
| Repository | `WorkstationRepository`, `DeviceRepository` |
| API | Xem `api-contracts.md` nhóm Agent + `WorkstationAdminController` (đã có) |
| Permission | `device.administration` (xem `permission-matrix.md`) |
| Audit Event | `DEVICE_REGISTERED`, `DEVICE_HEARTBEAT_TIMEOUT`, `DEVICE_DISABLED` |
| Integration Event | `device.heartbeat.missed` (cảnh báo cho Realtime Dashboard) |
| Test | `WorkstationRegistrationTest` (đã có), bổ sung `DeviceHeartbeatTimeoutTest` |

### 1.2. Chemical Call (Isolated - BLOCKED_BY_BUSINESS_CONFIRMATION)

> [!WARNING]
> Phân hệ `Chemical Call` tạm thời được cô lập hoàn toàn khỏi luồng tích hợp chung do blocker **`CH-BUS-015`**. Trạng thái thiết kế: **`BLOCKED_BY_BUSINESS_CONFIRMATION`**.

| Thành phần | Nội dung |
|---|---|
| Entity | `ChemicalChannel` (đã có, cấu hình tĩnh), `ChemicalCallRequest`, `ChemicalCallRequestEvent` |
| Value Object | `ChannelSlot` (machine+channel_number), `RequestIdempotencyKey` |
| State Transition | `CREATED→ORDERED→ACKNOWLEDGED→DONE` + `CANCELLED`/`FAILED`/`RESET` — xem `state-machines.md` Mục 1 |
| Application Service | `ChemicalCallService` (order/acknowledge/complete/cancel/reset) |
| Domain Policy | `ChemicalCallConcurrencyPolicy` (chống 2 request ORDER trùng kênh — partial unique index) |
| Repository | `ChemicalCallRequestRepository` |
| API | Xem `api-contracts.md` nhóm Chemical Call |
| Permission | `chemical_call.view/create/complete/reset` |
| Audit Event | `CHEMICAL_CALL_ORDERED`, `CHEMICAL_CALL_ACKNOWLEDGED`, `CHEMICAL_CALL_DONE`, `CHEMICAL_CALL_CANCELLED` |
| Integration Event | `chemical_call.status_changed` (realtime cho màn hình đèn tín hiệu) |
| Test | `ChemicalCallServiceTest`, `ChemicalCallConcurrencyTest` |

### 1.3. Production Order

| Thành phần | Nội dung |
|---|---|
| Entity | `ProductionOrder` (mở rộng `production_batches` đã có), `ProductionOrderItem`, `ProductionOrderStatusEvent`, `ProductionOrderLock` |
| Value Object | `MachineTankLevel`, `ColorCodeKey` (chống trùng color+code) |
| State Transition | Xem `state-machines.md` Mục 2 |
| Application Service | `ProductionOrderService` (create/approve/dispatch), `ProductionOrderLockService` |
| Domain Policy | `DuplicateColorCodePolicy`, `MinimumCapacityPolicy` (250L — chờ CH-BUS-005) |
| Repository | `ProductionOrderRepository` |
| API | Xem `api-contracts.md` nhóm Production Order |
| Permission | `production_order.create/approve`, `lock.override` |
| Audit Event | `ORDER_CREATED`, `ORDER_APPROVED`, `ORDER_LOCKED`, `ORDER_LOCK_OVERRIDDEN`, `ORDER_DISPATCHED` |
| Integration Event | `production_order.dispatched` (kích hoạt Dispatch domain) |
| Test | `MachineDispatchConcurrencyTest` (đã có, mở rộng theo lock strategy mới — Mục 4 bên dưới) |

### 1.4. Dispatch & QR Label Printing

| Thành phần | Nội dung |
|---|---|
| Entity | `DispatchJob` (mở rộng `machine_dispatches`), `DispatchJobItem`, `QrPayload`, `PrintJob`, `PrintAttempt`, `DispatchEvent` |
| Value Object | `QrPayloadContent` (qrDye/qrChem/qrProcess/qrExtra/qrFB), `RoutingDecision` (từ Warehouse Routing) |
| State Transition | Xem `state-machines.md` Mục 3 (Dispatch) và Mục 4 (Print Job) |
| Application Service | `ConfirmDispatchService` (thay `ConfirmRow`, 13 bước — Mục 7.3 yêu cầu gốc), `PrintJobService` |
| Domain Policy | `ScaleCheckPolicy`, `DispatchIdempotencyPolicy` |
| Repository | `DispatchJobRepository`, `QrPayloadRepository`, `PrintJobRepository` |
| API | Xem `api-contracts.md` nhóm QR/Print |
| Permission | `dispatch.confirm`, `print.execute/reprint/cancel` |
| Audit Event | `DISPATCH_CONFIRMED`, `PRINT_JOB_CREATED`, `PRINT_JOB_PRINTED`, `PRINT_JOB_FAILED`, `SCALE_CHECK_UPDATED` |
| Integration Event | `dispatch.confirmed` (kích hoạt Warehouse Routing + Print), `print_job.status_changed` (Agent↔Backend) |
| Test | `PrintJobPipelineTest` (đã có, mở rộng), `ConfirmDispatchServiceTest` (mới), `ConfirmRowTwiceTest` (mục 24) |

### 1.5. Warehouse Routing (B24)

| Thành phần | Nội dung |
|---|---|
| Entity | `RoutingRuleVersion` (versioned, KHÔNG hard-code rải rác) |
| Value Object | `RoutingDecision` (route, matchedRule, ruleVersion, inputSnapshot, warnings, needsManualReview) |
| State Transition | Không phải state machine (tính toán thuần túy) — nhưng có 3 mode: `LEGACY_EXACT`/`FIXED_D1`/`MANUAL_REVIEW` (xem Mục 8) |
| Application Service | `WarehouseRoutingService` |
| Domain Policy | `B24RoutingPolicy` (implement từ `b24-warehouse-routing.md`, versioned) |
| Repository | *(không cần — rule hiện tại là code/config, không phải bảng DB, xem `database-inventory.md`: WH.accdb không có bảng mapping)* |
| API | Nội bộ (gọi từ `ConfirmDispatchService`), không cần expose riêng trừ khi cần preview |
| Permission | `warehouse_routing.manual_override` |
| Audit Event | `ROUTING_DECIDED`, `ROUTING_MANUAL_REVIEW_REQUIRED` |
| Integration Event | Không |
| Test | `B24RoutingPolicyTest` (8 test case từ `b24-warehouse-routing.md` Mục 10) |

### 1.6. Weighing

| Thành phần | Nội dung |
|---|---|
| Entity | `WeighingJob` (đã có), `WeighingJobItem` (đã có), `WeighingSample` (mới), `WeighingResult` (mới), `WeighingEvent`, `ScaleDevice` |
| Value Object | `StableReading`, `ToleranceRange`, `DeltaTareResult` |
| State Transition | Xem `state-machines.md` Mục 5 (Weighing Job) |
| Application Service | `WeighingCoreService` (dùng chung), theo `SmallScalePolicy`/`LargeScalePolicy` |
| Domain Policy | `StableFilterPolicy`, `ToleranceCheckPolicy` (versioned — `local-agent-architecture.md` Mục 2) |
| Repository | `WeighingJobRepository`, `WeighingSampleRepository` |
| API | Xem `api-contracts.md` nhóm Weighing |
| Permission | `weighing.small_scale/large_scale`, `weighing.override_tolerance` |
| Audit Event | `WEIGH_SAMPLE_RECEIVED`, `WEIGH_STABLE_REACHED`, `WEIGH_ACCEPTED`, `WEIGH_REJECTED`, `WEIGH_OVERRIDDEN` |
| Integration Event | `weighing.completed` (kích hoạt Warehouse consumption log) |
| Test | `ScaleLiveWeightTest` (đã có), `WeighingCoreServiceTest` (mới, dùng chung 2 policy) |

### 1.7. Traceability & Audit

| Thành phần | Nội dung |
|---|---|
| Entity | `AuditLog` (đã có), `CorrelationLink` (mới — xem `record-a-record-b-correlation.md`), `LegacyExceptionQueueItem` (mới) |
| Value Object | `CorrelationConfidence` (EXACT/DETERMINISTIC/PROBABILISTIC/NONE) |
| State Transition | Không |
| Application Service | `TraceabilityQueryService` (truy vết xuyên suốt 7 bước — mục 2.7 yêu cầu gốc phiên trước) |
| Domain Policy | `CorrelationMatchingPolicy` |
| Repository | `AuditLogRepository`, `CorrelationLinkRepository` |
| API | `GET /api/traceability/{correlation_id}` |
| Permission | `audit.view`, `audit.export` |
| Audit Event | *(chính nó là audit)* |
| Integration Event | Không |
| Test | `TraceabilityQueryServiceTest` |

### 1.8. Legacy Integration

| Thành phần | Nội dung |
|---|---|
| Entity | Không entity nghiệp vụ — chỉ adapter/bridge |
| Value Object | `LegacySourceRef` (legacy_source + legacy_id, dùng ở mọi bảng đích) |
| State Transition | Không |
| Application Service | `LegacyReadAdapter` (đọc RECORD_A/RECORD_B/WAREHOUSE/CHEM_ORDER qua bridge có transaction log — KHÔNG dual-write trực tiếp vào Access, xem `cutover-rollback-plan.md`) |
| Domain Policy | Không |
| Repository | Không (đọc qua adapter, không ORM trực tiếp lên Access) |
| API | Không public |
| Permission | Chỉ Local Agent/Service account |
| Audit Event | `LEGACY_READ_SYNCED` |
| Integration Event | `legacy.sync.completed` |
| Test | `LegacyReadAdapterTest` (dry-run, không ghi thật) |

---

## 2. Nguyên tắc ranh giới (áp dụng toàn bộ 8 domain)

1. **Controller mỏng** — chỉ validate input + gọi Application Service + trả response. Toàn bộ logic B24, tính QR, kiểm tra dung sai đều nằm trong Domain Policy, KHÔNG trong Controller (đúng yêu cầu mục 7.3/8.1).
2. **Application Service = 1 transaction nghiệp vụ** — ví dụ `ConfirmDispatchService::confirm()` bọc toàn bộ 13 bước (mục 7.3) trong 1 DB transaction, không ghi 1 phần nếu bước sau lỗi.
3. **Domain Policy thuần túy, có version** — `B24RoutingPolicy`, `StableFilterPolicy`, `ToleranceCheckPolicy` đều nhận input trả output không side-effect, dễ unit test độc lập, có `policy_version` lưu kèm kết quả.
4. **Integration Event thay cho gọi trực tiếp giữa domain** — ví dụ Dispatch domain không gọi thẳng Warehouse Routing DB, mà qua `WarehouseRoutingService` (application service riêng domain 1.5) — giữ ranh giới rõ dù cùng 1 codebase (chưa cần message queue thật, có thể là service call trong-process ở giai đoạn đầu).
