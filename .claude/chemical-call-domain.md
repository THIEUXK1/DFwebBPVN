# Domain: Chemical Call (chemical-call-domain.md)

Lập 2026-07-17. Nguồn nghiệp vụ gốc: `1.báo phát AC XƯỞNG -193.xlsm` (`chem_order.frm`, 44 procedure — audit đầy đủ tại `vba-migration-matrix.md` NHÓM 0). Database: `chem_order.accdb` (định danh tạm **CHEM_ORDER**, xem `legacy-database-mapping.md`). Đây là tài liệu THIẾT KẾ ĐỀ XUẤT — chưa migration, chưa code sản xuất.

---

## 1. Tình trạng hiện tại (xác nhận lại, không suy diễn)

- 0 Controller, 0 route, 0 view, 0 service trên web hiện có (grep xác nhận trên `backend/app/Http/Controllers`, `backend/routes/api.php`, `frontend/src`).
- 1 Model tĩnh `MachineChemicalChannel.php` tồn tại nhưng **không route/controller nào dùng**.
- Bảng đích `app.machine_chemical_channels` đã di trú xong **cấu hình tĩnh** (40/40 dòng `tbl_status`) — nhưng đây chỉ là 1 nửa dữ liệu; nửa còn lại (tín hiệu vận hành ORDER/DONE) hoàn toàn chưa có chỗ lưu.
- Workbook chỉ phủ 8/18 máy (VD006-VD013) × 2/9 slot hóa chất (5,6) trong 40 dòng `tbl_status` — khả năng còn workbook chị em phụ trách phần còn lại (VD001-005, VD014-018, slot 1-4/7-9), **chưa tìm thấy**.

## 2. Phân tách 2 loại dữ liệu (theo yêu cầu mục 4.1)

### 2.1. Dữ liệu cấu hình tĩnh (ĐÃ CÓ, giữ nguyên bảng)
Tương ứng `chem_order.accdb.tbl_status` (cột `machine`, `chem`, `chem_name`) → `app.machine_chemical_channels` hiện có (`machine_id`, `channel_number`, `chemical_code`, `is_active`). **Không sửa bảng này** — chỉ dùng để tra cứu "máy X kênh Y chứa hóa chất gì".

### 2.2. Dữ liệu vận hành động (CHƯA CÓ — cần thiết kế mới, đề xuất)
Tương ứng cột `Status` (Boolean/Text "0"/"1") trong `tbl_status` bị VBA `UpdateStatus`/`HandleButton` ghi liên tục — đây KHÔNG phải cấu hình, là **giao dịch nghiệp vụ có vòng đời**. Đề xuất tách hẳn khỏi bảng cấu hình:

- Yêu cầu gọi hóa chất (khi operator bấm nút `chemN`).
- Trạng thái ORDER (đang chờ cấp).
- Trạng thái DONE (đã cấp xong, operator xác nhận bằng nút `okN`).
- Thời điểm gọi / thời điểm xác nhận.
- Người/máy phát lệnh, workstation phát lệnh.
- Máy sản xuất + kênh hóa chất liên quan (FK tới `machine_chemical_channels`).
- Lịch sử thay đổi trạng thái (VBA gốc KHÔNG có audit trail — đây là điểm cải tiến bắt buộc theo CLAUDE.md).
- **Lưu ý:** VBA gốc chỉ có đúng 2 trạng thái (`ORDER`↔`DONE`), KHÔNG có "lỗi"/"hủy"/"reset" — không tự thêm các trạng thái này vào state machine chỉ vì "có vẻ hợp lý"; nếu web cần chúng (ví dụ chống thao tác nhầm) đó là phần **B. UX IMPROVEMENT** cần khai báo rõ, không phải A. MIGRATION PARITY.

## 3. Đề xuất Entity (tên có thể điều chỉnh theo convention dự án — CHƯA migration)

```
app.chemical_channels                  -- ĐÃ CÓ (đổi tên đề xuất từ machine_chemical_channels, hoặc giữ nguyên)
  id, machine_id (FK app.machines), channel_number, chemical_code, is_active, legacy_id

app.chemical_call_requests              -- MỚI — 1 dòng = 1 chu kỳ ORDER→DONE
  id (uuid)
  channel_id (FK chemical_channels)
  machine_id (FK app.machines, denormalized để query nhanh)
  status varchar(20)                    -- 'ORDER' | 'DONE'  (KHÔNG thêm state ngoài VBA gốc trừ khi xác nhận)
  requested_at timestamptz
  requested_by_user_id (FK app.users, nullable — VBA gốc không phân quyền theo người)
  requested_by_workstation_id (FK app.workstations)
  confirmed_at timestamptz (nullable — null khi còn ORDER)
  confirmed_by_user_id (FK app.users, nullable)
  confirmed_by_workstation_id (FK app.workstations, nullable)
  idempotency_key text UNIQUE           -- chống double-submit (yêu cầu mục 4.3)
  row_version integer DEFAULT 1         -- optimistic lock chống 2 thao tác gần nhau
  legacy_source varchar(30) DEFAULT 'tbl_status.Status'
  created_at, updated_at

app.chemical_call_request_events        -- MỚI — audit trail chi tiết (VBA gốc không có, bắt buộc theo CLAUDE.md mục 5)
  id, request_id (FK), event_type ('ORDERED'|'CONFIRMED'|...), occurred_at,
  actor_user_id, actor_workstation_id, before_status, after_status, note

app.workstations, app.workstation_devices, app.operation_audit_logs   -- DÙNG LẠI hạ tầng đã có (workstation-matrix.md), không tạo mới
```

**Ràng buộc UNIQUE quan trọng:** `chemical_call_requests` nên có unique constraint kiểu `(channel_id) WHERE status = 'ORDER'` (partial unique index) để tại 1 thời điểm, 1 kênh chỉ có tối đa 1 request đang ở trạng thái ORDER — đây là bản chất thật của VBA gốc (1 ô đèn tín hiệu = 1 trạng thái duy nhất tại 1 thời điểm), không phải phát minh mới.

## 4. Yêu cầu chức năng (đối chiếu mục 4.3, trạng thái theo taxonomy 6 giá trị)

| # | Chức năng | VBA gốc | Trạng thái đề xuất | Ghi chú |
|---|---|---|---|---|
| 1 | Hiển thị trạng thái từng máy/kênh (đèn đỏ/xanh) | `loadstatus` (VBA-CHEM-013) | **MISSING** | Cần UI mới hoàn toàn — GET endpoint trả danh sách channel+status |
| 2 | Gửi tín hiệu gọi hóa chất | `HandleButton`+`UpdateStatus` (VBA-CHEM-005/008/009) | **MISSING** | POST tạo `chemical_call_requests` status=ORDER |
| 3 | Ghi nhận DONE | tương tự, action="ok" (VBA-CHEM-006) | **MISSING** | PATCH request → status=DONE + `confirmed_at` |
| 4 | Tự động làm mới trạng thái | `StartAutoRefresh`/`AutoRefresh` polling 15s (VBA-CHEM-014/015) | **MISSING** | Đề xuất WebSocket/SSE qua `RealtimeService` đã có (dùng cho DISPATCH), không cần polling 15s kiểu VBA — đây là **B. UX IMPROVEMENT** hợp lệ (không đổi hành vi nghiệp vụ, chỉ đổi cơ chế truyền tải) |
| 5 | Chống gửi trùng | **VBA gốc KHÔNG CÓ** (click 2 lần chỉ ghi đè cùng giá trị, không cảnh báo) | **BLOCKED** (mới, C-loại) | Idempotency key + partial unique index (Mục 3) là cải tiến B/C — cần xác nhận có được phép thắt chặt hơn VBA hay phải giữ hành vi "click nhiều lần vô hại" y hệt |
| 6 | Xử lý 2 thao tác gần nhau | **VBA gốc KHÔNG CÓ** | **BLOCKED** | Đề xuất optimistic lock (`row_version`) — cần xác nhận cơ chế phù hợp (xem mục 7.1 câu hỏi PRODUCTION_ORDER áp dụng tương tự ở đây) |
| 7 | Ghi audit log | **VBA gốc KHÔNG CÓ** (không try/catch, không log) | **MISSING** (bắt buộc theo CLAUDE.md mục 5, dù VBA không có) | `chemical_call_request_events` + `operation_audit_logs` |
| 8 | Hiển thị lỗi kết nối/ghi DB | VBA: không try/catch, crash form âm thầm nếu mất ổ mạng Z: | **MISSING** | Web phải trả lỗi rõ ràng — đây là **B. UX IMPROVEMENT** so với VBA (VBA thực chất "im lặng crash", không phải hình mẫu tốt để copy) |
| 9 | Phân quyền gọi/xác nhận/reset | **VBA gốc KHÔNG CÓ** (ai mở file cũng bấm được cả 8 máy) | **BLOCKED** | Cần xác nhận nghiệp vụ: giữ nguyên "không phân quyền" (đúng VBA) hay thêm phân quyền theo workstation/role (thay đổi hành vi — phải khai báo B/C rõ ràng, không được âm thầm thắt chặt) |
| 10 | Không mất trạng thái khi reload | VBA: trạng thái nằm hoàn toàn ở Access, form chỉ là view — reload tự nhiên đọc lại đúng | **MISSING** (cùng lý do 1) | Miễn là UI mới cũng đọc trạng thái từ server (không lưu ở frontend state cục bộ), yêu cầu này tự động thỏa mãn — không mô phỏng trạng thái ở frontend |

## 5. Câu hỏi cần xác nhận trước khi code (BLOCKED_BY_BUSINESS_CONFIRMATION)

1. Có workbook chị em nào phụ trách 10/18 máy và 7/9 slot hóa chất còn lại không? (đã ghi ở NHÓM 0, nhắc lại vì ảnh hưởng trực tiếp tới việc entity `chemical_channels` có đủ dữ liệu để build UI đầy đủ hay chỉ 8 máy).
2. Có cần phân quyền gọi/xác nhận/reset theo vai trò không, hay giữ nguyên "ai cũng bấm được" như VBA?
3. Có cần chặn gửi trùng/2 thao tác gần nhau (thắt chặt hơn VBA) hay phải giữ hành vi gốc?
4. `chem_order.accdb.tblRECORD`/`tblRECORD_chem` (47.381/1.500 dòng, dừng ở 2026-03-31) có còn ý nghĩa gì với CHEMICAL_CALL không, hay chỉ là backup tĩnh không liên quan? (xem `legacy-database-mapping.md`)

## 6. File code dự kiến sẽ tạo (đề xuất — CHƯA tạo)

- `backend/app/Http/Controllers/ChemicalCallController.php`
- `backend/app/Models/ChemicalCallRequest.php`, `ChemicalCallRequestEvent.php`
- `backend/app/Services/ChemicalCallService.php`
- `backend/database/migrations/..._create_chemical_call_requests_table.php`, `..._create_chemical_call_request_events_table.php`
- `frontend/src/views/ChemicalCallStation.vue`
- Test: `ChemicalCallServiceTest.php` (state transition, idempotency), `ChemicalCallControllerTest.php` (API), `ChemicalCallConcurrencyTest.php` (2 thao tác gần nhau, tương tự `MachineDispatchConcurrencyTest.php`)
