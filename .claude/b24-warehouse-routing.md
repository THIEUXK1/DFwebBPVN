# Logic Phân vùng Kho B24 — Truy vết & Bảng quyết định (b24-warehouse-routing.md)

Lập 2026-07-17. Nguồn duy nhất: `Mod_printslip.PrintSlip_70x100` trong workbook `3.DF028 ... jit qr sending - 15l special.xlsm` (dòng 1363-1753 của file VBA trích xuất). Đã đọc **toàn bộ** hàm (395 dòng), chép lại chính xác từng nhánh điều kiện — không suy diễn. Đã kiểm tra `WH.accdb` (5 cột, 1 bảng `tblWH_LOG`) — **không có bảng mapping vùng kho nào** trong Access; toàn bộ logic B24 là hard-code trong VBA.

---

## 1. Đầu vào

| Biến | Nguồn | Ý nghĩa |
|---|---|---|
| `f3Val` | `ws.Range("F3").Value` (đã UCase/Trim) | Mã máy nhuộm (vd. `VD06`) |
| `g3Val` | `ws.Range("G3").Value` (đã UCase/Trim) | Mã tank (`1A`/`2B`/`3C`/`4D`) |
| `H3` (level) | `ws.Range("H3").Value` | Level/dung tích (dùng khi `f3Val`∈{VD17,VD18} và `g3Val`∈{3C,4D}) |
| `rawDye`, `rawChem` | Tham số truyền vào hàm (chuỗi QR thô đã lưu ở `tbl_tosend`) | Dữ liệu bột màu/hóa chất cho QR |

## 2. Bảng quyết định B24 (điều kiện ưu tiên theo thứ tự If/ElseIf trong code — thứ tự có ý nghĩa, nhánh trên chặn nhánh dưới)

| # | Điều kiện Machine (`f3Val`) | Điều kiện Tank (`g3Val`) | Điều kiện phụ | Kết quả `B24` |
|---|---|---|---|---|
| 1 | VD06 ≤ machine ≤ VD13 | 1A hoặc 2B | — | `THUNG SAT CAO, MAY E13, MAY A11` |
| 2 | VD17 hoặc VD18 | 1A hoặc 2B | — | `THUNG SAT CAO, MAY E12, MAY A11` |
| 3 | VD17 hoặc VD18 | 3C hoặc 4D | `H3 = 50` | `PHA TAY, HOA CHAT DLG` |
| 3b | VD17 hoặc VD18 | 3C hoặc 4D | `H3 ≠ 50` | `THUNG SAT CAO, MAY E12, MAY DLG` |
| 4 | (VD01–VD05) hoặc (VD14–VD16) | 1A hoặc 2B | — | `THUNG SAT THAP, MAY JIT, MAY A11` |
| 5 | VD01 ≤ machine ≤ VD16 | 3C hoặc 4D | — | `THUNG SAT THAP, MAY JIT, MAY DLG` |
| 6 | *(không khớp nhánh nào ở trên)* | — | — | `B24` giữ nguyên **rỗng** (không có nhánh Else) |

**So sánh chuỗi (`f3Val >= "VD06" And f3Val <= "VD13"`) là so sánh TEXT theo alphabet, không phải số** — hoạt động đúng vì định dạng `VD` + 2 chữ số cố định (VD01..VD18), nhưng dễ vỡ nếu sau này có `VD1` (1 chữ số) hoặc `VD100`.

## 3. Chọn chế độ mã hóa QR (dựa trên chuỗi `B24` vừa tính ở Mục 2)

| Điều kiện (kiểm tra theo thứ tự) | `mode` | Ghi đè `B24` |
|---|---|---|
| `B24` chứa `"MAY JIT"` | `PROCESS` | Giữ nguyên |
| `B24` chứa `"THUNG SAT CAO"` | `EXTRA` | Giữ nguyên |
| Không khớp cả 2 (bao gồm cả trường hợp `B24` rỗng ở nhánh 6 Mục 2) | `FB` (mặc định) | **Ghi đè `B24` = `"PHU BAN-LAY LIEU COPOWER"`** |

## 4. Payload QR theo từng mode (chép nguyên văn công thức nối chuỗi)

- **`qrDye`** (luôn tạo, mọi mode): `"#" & color & "-" & code & "-" & machine & "-" & level & "-" & rawDye`
- **`qrChem`** (luôn tạo, mọi mode): `VD### (chuẩn hóa 3 chữ số) & CRLF & tank_ký_tự_đầu & CRLF & "#"&color&"-"&code & CRLF & random(1-9) & CRLF & level & (lặp CRLF+mã hóa chất+CRLF+khối lượng cho mỗi dòng có dữ liệu) & CRLF & "#"`
- **`qrProcess`** (mode=PROCESS): `color&"-"&code&" "&timestamp(yyyymmddhhmm) & CRLF & machine&"-"&tank&"-"&newLevel & CRLF & dyesProcess`
  - `newLevel` = `450` nếu tank=`1A`; `250` nếu tank=`2B`; ngược lại lấy nguyên giá trị `H3`.
- **`qrExtra`** (mode=EXTRA): `VD### & CRLF & tank_ký_tự_đầu & CRLF & color&" "&code & CRLF & random(1-9) & CRLF & level & CRLF & "1" & CRLF & tổng_khối_lượng_9_dòng(định dạng "0.###")`
- **`qrFB`** (mode=FB, mặc định): `color&"-"&code&" "&timestamp(hhmm) & (lặp CRLF+mã màu+CRLF+khối lượng cho mỗi dòng dye có dữ liệu) & (lặp CRLF+mã hóa chất+CRLF+khối lượng cho mỗi dòng chem có dữ liệu)`

## 5. `dyesProcess` (loại thuốc nhuộm — ảnh hưởng payload `qrProcess`)

1. Mặc định `"Nylon Dyes"`.
2. Quét 9 dòng dye (cột C, hàng 5-13): nếu **bất kỳ** mã kết thúc bằng `"C"` → `"Cation Dyes"`.
3. Trong cùng vòng quét: nếu mã kết thúc bằng `"D"` HOẶC bắt đầu bằng `"Y13"`/`"R23"`/`"B33"` → cờ `isDisperse = True`.
4. Quét 9 dòng chem (cột F, hàng 5-13): nếu chứa chuỗi con `"0574"` hoặc `"0507"` → cờ `hasChemKey = True`.
5. Nếu `dyesProcess` chưa phải `"Cation Dyes"` VÀ `isDisperse` VÀ `hasChemKey` → đổi thành `"Disperse Dyes"`.

## 6. `D1` (nhãn khu vực máy JIT) — **cây quyết định RIÊNG, không tái dùng B24**

| # | Điều kiện Machine | Điều kiện Tank | `D1` |
|---|---|---|---|
| 1 | VD06–VD13 | 1A hoặc 2B | `E13` |
| 2 | VD17 hoặc VD18 | 1A/2B/3C/4D (cả 4) | `E12` |
| 3 | VD01–VD05 | 1A hoặc 2B | `JIT3` |
| 4 | VD01–VD09 | 3C hoặc 4D | `JIT2` |
| 5 | VD14–VD16 | 1A hoặc 2B | `JIT4` |
| 6 | VD10–VD16 | 3C hoặc 4D | `JIT1` |
| — | *(mọi trường hợp khác)* | — | **`""` (rỗng — không có Else)** |

> [!IMPORTANT]
> **ĐÍNH CHÍNH 2026-07-17 (phát hiện khi review code Phase E):** Bản đầu tiên của mục này (đợt duyệt lần 4) từng ghi CẢNH BÁO sai rằng có "lỗ hổng" cho tổ hợp VD14-16+3C/4D — đây là lỗi transcription: đoạn cảnh báo tự mâu thuẫn với chính bảng ngay phía trên (bảng ghi đúng nhánh 6 = "VD10–VD16", nhưng đoạn cảnh báo lại tính nhầm như thể nhánh 6 dừng ở VD13). Đã đọc lại trực tiếp VBA gốc lần 2 để xác nhận: nhánh 6 **THẬT SỰ bao phủ VD10-VD16** (`f3Val >= "VD10" And f3Val <= "VD16"`). **KHÔNG CÓ lỗ hổng nào** — VD14-16+3C/4D nhận đúng `D1="JIT1"` như mọi máy khác trong dải. **ADR CH-BUS-012 đã đóng (RESOLVED)** — xem `decision-records.md`. Code `WarehouseRoutingService.php`/test đã sửa khớp ngày 2026-07-17 (trước đó code từng tái tạo nhầm "lỗ hổng" không có thật bằng cách hard-code dải VD10-VD13).

## 7. Trường hợp `15L special` (tên file) — KHÔNG TÌM THẤY NHÁNH RIÊNG

- Đã đọc toàn bộ `Mod_printslip.bas` (395 dòng) — không có bất kỳ so sánh `= 15`, `"15"`, hay `15L` nào.
- Đã trích xuất **toàn bộ 100 công thức Excel** trên `Sheet1` của workbook bằng `openpyxl` (không chỉ VBA) — chỉ là công thức quy đổi tank code→số thứ tự (`1A`→1, `2B`→2...), không liên quan "15L".
- Dữ liệu mẫu `RECORD1.accdb.tblRECORD` có giá trị `LEVEL="15"` xuất hiện thật (nhiều dòng) — xác nhận "15L" là 1 giá trị `level` hợp lệ trong vận hành thật, nhưng **không có nhánh code riêng nào xử lý khác biệt cho level=15** trong `PrintSlip_70x100` — nó chỉ đi qua nhánh chung (`newLevel = H3` khi tank không phải 1A/2B ở mode PROCESS).
- **Kết luận: `BLOCKED_BY_BUSINESS_CONFIRMATION`.** Không thể xác nhận "15L special" có phải 1 quy tắc nghiệp vụ ẩn (có thể nằm trong sheet `TOSEND` giá trị tĩnh, layout khổ giấy khi in `ws.PrintOut`, hoặc đơn giản là mô tả năng lực chung của workbook) hay chỉ là tên gọi lịch sử không còn phản ánh code hiện tại. **Không tự suy diễn thêm — cần hỏi trực tiếp người vận hành DF028.**

## 8. Ví dụ thực tế (dựng từ dữ liệu mẫu thật — không phải dữ liệu giả)

Từ `RECORD.accdb.tbl_SentLog`: dòng `MACHINE=VD10, TANK=1A, LEVEL=220`.
- Áp Mục 2: VD10 nằm trong [VD06,VD13] và tank=1A → nhánh 1 → `B24 = "THUNG SAT CAO, MAY E13, MAY A11"`.
- Áp Mục 3: chứa `"THUNG SAT CAO"` → `mode = EXTRA`.
- Áp Mục 6: VD10 nằm trong [VD06,VD13], tank=1A → nhánh 1 → `D1 = "E13"`.

Dòng `MACHINE=VD07, TANK=3C, LEVEL=50`.
- Áp Mục 2: VD07 nằm trong [VD01,VD16] và tank=3C → nhánh 5 → `B24 = "THUNG SAT THAP, MAY JIT, MAY DLG"`.
- Áp Mục 3: chứa `"MAY JIT"` → `mode = PROCESS`; `newLevel` = H3 gốc vì tank không phải 1A/2B → `newLevel = "50"`.
- Áp Mục 6: VD07 nằm trong [VD01,VD09] và tank=3C → nhánh 4 → `D1 = "JIT2"`.

## 9. Ngoại lệ / trường hợp không xác định

- Tổ hợp không khớp Mục 2 (vd. VD14-16 + tank khác thường, hoặc mã máy ngoài VD01-VD18) → `B24` rỗng → rơi vào mode `FB` mặc định (Mục 3) → ghi đè `B24 = "PHU BAN-LAY LIEU COPOWER"`. Đây là hành vi CÓ CHỦ ĐÍCH của code (fallback), không phải lỗi.
- Tổ hợp VD14-16 + 3C/4D → `D1` rỗng (Mục 6) — lỗ hổng thật, xem cảnh báo Mục 6.
- QR generation fail (lỗi mạng khi gọi `api.qrserver.com`) → `MsgBox` rồi `GoTo SAFE_EXIT`, chỉ Protect lại sheet, **không rollback** dữ liệu B2:I24 đã ghi — có thể để lại sheet ở trạng thái dở dang nếu in lần sau trước khi dọn.

## 10. Bảng test case đề xuất (chưa chạy — golden test tương lai)

| # | Input (machine, tank, level) | B24 kỳ vọng | mode kỳ vọng | D1 kỳ vọng |
|---|---|---|---|---|
| TC1 | VD10, 1A, 220 | THUNG SAT CAO, MAY E13, MAY A11 | EXTRA | E13 |
| TC2 | VD18, 2B, — | THUNG SAT CAO, MAY E12, MAY A11 | EXTRA | E12 |
| TC3 | VD17, 3C, 50 | PHA TAY, HOA CHAT DLG | FB | E12 |
| TC4 | VD17, 4D, 100 | THUNG SAT CAO, MAY E12, MAY DLG | EXTRA | E12 |
| TC5 | VD03, 1A, — | THUNG SAT THAP, MAY JIT, MAY A11 | PROCESS | JIT3 |
| TC6 | VD07, 3C, 50 | THUNG SAT THAP, MAY JIT, MAY DLG | PROCESS | JIT2 |
| TC7 | VD15, 3C, — (**lỗ hổng đã biết**) | THUNG SAT THAP, MAY JIT, MAY DLG | PROCESS | **rỗng (bug gốc)** |
| TC8 | VD99 (mã không hợp lệ), 1A, — | *(rỗng)* → ghi đè | FB → PHU BAN-LAY LIEU COPOWER | *(rỗng)* |

---

## Trạng thái: **PARTIALLY_RESOLVED** — code logic đã truy vết 100% chính xác từ VBA; câu hỏi "15L special" và lỗ hổng VD14-16+3C/4D vẫn `BLOCKED_BY_BUSINESS_CONFIRMATION`. Không code phần B24 cho tới khi có xác nhận nghiệp vụ 2 điểm này.
