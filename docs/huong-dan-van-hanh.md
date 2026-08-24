# HƯỚNG DẪN SỬ DỤNG HỆ THỐNG DF CONNECTOR
## Dành cho nhân viên vận hành và tổ trưởng phân xưởng nhuộm

| | |
|---|---|
| **Tên hệ thống** | DF Connector — Hệ thống cầu nối MES ↔ Nhuộm/Pha màu tự động |
| **Phiên bản tài liệu** | 1.0 |
| **Ngày ban hành** | 12/08/2026 |
| **Người biên soạn** | Bùi Văn Thiều — Nhân viên IT |
| **Người phê duyệt** | …………………………… |
| **Đối tượng áp dụng** | Công nhân vận hành, tổ trưởng ca, nhân viên cân, nhân viên in tem |

> **Đọc trước khi dùng:** Hệ thống này thay thế các file Excel VBA cũ. Trong giai đoạn chạy song song, **không được tự ý tắt hoặc xóa các file Excel cũ**. Mọi khác biệt giữa kết quả Web và Excel phải báo tổ trưởng ngay trong ca.

---

## MỤC LỤC

1. [Khái niệm cơ bản cần nhớ](#1-khái-niệm-cơ-bản-cần-nhớ)
2. [Chuẩn bị đầu ca](#2-chuẩn-bị-đầu-ca)
3. [Đăng nhập và mở màn hình của mình](#3-đăng-nhập-và-mở-màn-hình-của-mình)
4. [Công đoạn 1 — Nhập đơn sản xuất (MainForm)](#4-công-đoạn-1--nhập-đơn-sản-xuất-mainform)
5. [Công đoạn 2 — In tem nhập đơn (TO_SEND)](#5-công-đoạn-2--in-tem-nhập-đơn-to_send)
6. [Công đoạn 3 — QR PRINTER 9 dòng](#6-công-đoạn-3--qr-printer-9-dòng)
7. [Công đoạn 4 — Trạm CÂN NHỎ (dưới 6 kg)](#7-công-đoạn-4--trạm-cân-nhỏ-dưới-6-kg)
8. [Công đoạn 5 — Trạm CÂN TO (từ 6 kg)](#8-công-đoạn-5--trạm-cân-to-từ-6-kg)
9. [Tra cứu Lịch sử cân và In lại phiếu](#9-tra-cứu-lịch-sử-cân-và-in-lại-phiếu)
10. [Gọi hóa chất](#10-gọi-hóa-chất)
11. [Màn hình treo xưởng (chỉ xem)](#11-màn-hình-treo-xưởng-chỉ-xem)
12. [Xử lý sự cố thường gặp](#12-xử-lý-sự-cố-thường-gặp)
13. [Những việc TUYỆT ĐỐI KHÔNG được làm](#13-những-việc-tuyệt-đối-không-được-làm)
14. [Liên hệ hỗ trợ](#14-liên-hệ-hỗ-trợ)

---

## 1. Khái niệm cơ bản cần nhớ

| Thuật ngữ | Nghĩa trong hệ thống |
|---|---|
| **Trạm làm việc (Workstation)** | Máy tính của một công đoạn. Mỗi máy được gán **một mã trạm** (ví dụ `WS-SMALL-01`, `WS-LARGE-01`, `WS-PRINT-01`). Mã trạm hiện ở góc trên bên phải màn hình. |
| **DF Agent** | Phần mềm chạy ngầm trên máy trạm, có nhiệm vụ **đọc số cân** và **in tem**. Trình duyệt không nói chuyện trực tiếp với cân/máy in — mọi thứ đều đi qua Agent. Agent tắt = không cân được, không in được. |
| **Mẻ (Batch)** | Một vòng cân hoàn chỉnh của một đơn, gồm nhiều dòng vật tư. |
| **BÌ (Tare)** | Trọng lượng vỏ/khay. Hệ thống tự chốt bì rồi trừ ra, số hiện ở ô **DELTA** đã là số thực của vật tư. |
| **DELTA** | Số cân **đã trừ bì** — đây là số được lưu và in lên tem. |
| **Hàng đợi gửi (Offline Queue)** | Khi mất mạng, mẻ đã cân được giữ lại trong máy và **tự gửi lên máy chủ khi có mạng trở lại**. Phiếu vẫn in bình thường. |
| **1 máy tính = 1 công đoạn** | Tài khoản vận hành chỉ mở được đúng màn hình của công đoạn mình. Gõ tay địa chỉ màn khác sẽ bị đưa về. Đây là quy định, không phải lỗi. |

**Đổi ngôn ngữ:** Góc trên bên phải có nút chọn ngôn ngữ — **Tiếng Việt / English / 中文**. Toàn bộ màn hình đều có đủ 3 thứ tiếng.

---

## 2. Chuẩn bị đầu ca

Làm đủ 4 bước sau **trước khi nhận đơn đầu tiên**:

| # | Việc kiểm tra | Đạt khi | Nếu không đạt |
|---|---|---|---|
| 1 | **Cân đã bật, dây tín hiệu cắm chắc** | Màn hình cân hiện số | Kiểm tra nguồn/dây, báo bảo trì |
| 2 | **DF Agent đang chạy** | Có biểu tượng Agent ở khay hệ thống (góc phải thanh taskbar) | Xem [mục 12.1](#121-mất-tín-hiệu-cân) |
| 3 | **Máy in tem đã bật, có giấy và mực** | In thử 1 tem ra được | Thay giấy/mực, báo bảo trì |
| 4 | **Mở đúng màn hình công đoạn** | Góc trên bên phải hiện **đúng mã trạm** của máy mình | Xem [mục 12.6](#126-chưa-gán-trạm) |

> **Dấu hiệu tốt cần thấy ở màn cân:** nhãn **⚡ Agent tại chỗ** (đọc nhanh ~70ms). Nếu thấy **☁ qua máy chủ** thì vẫn cân được nhưng chậm hơn — báo IT kiểm tra Agent trên máy đó.

---

## 3. Đăng nhập và mở màn hình của mình

Mỗi máy trạm đã được đặt sẵn **link riêng** trên màn hình nền hoặc trang chủ trình duyệt. **Chỉ cần mở link đó**, không cần gõ địa chỉ.

| Công đoạn | Đường dẫn | Có phải đăng nhập? |
|---|---|---|
| Nhập đơn sản xuất (MainForm) | `/production-batches/grid` | Không |
| In tem nhập đơn (TO_SEND) | `/print-order-entry` | Không |
| QR PRINTER 9 dòng | `/qr-printer` | Không |
| Sent log (đơn đã xác nhận) | `/print-sent-log` | Có |
| **Trạm cân nhỏ (< 6 kg)** | `/weighing-station-v2` | Tự đăng nhập, không phải gõ |
| **Trạm cân to (≥ 6 kg)** | `/weighing-station-large` | Tự đăng nhập, không phải gõ |
| Lịch sử cân | `/weighing-history` | Có |
| Gọi hóa chất (bảng cổ điển) | `/chemical-call/classic` | Không |
| Hàng đợi hóa chất (cổ điển) | `/chemical-call/pending-classic` | Không |
| Bảng máy VD (màn treo) | `/machine-id-board` | Không |
| Biểu đồ Gantt máy VD | `/bpdb-machines/gantt` | Không |

**Nếu màn hình yêu cầu đăng nhập:** dùng tài khoản do tổ trưởng/IT cấp. Hai trạm cân dùng tài khoản riêng đã cài sẵn (`cannho`, `canto`) — **không dùng tài khoản của người khác để cân**, vì hệ thống ghi lại ai là người cân và ai duyệt sai lệch dung sai.

**Nút hữu ích trên thanh trên:**

| Nút | Tác dụng |
|---|---|
| **▾ Thanh trên** | Hiện lại thanh trên khi đã thu gọn |
| **Toàn màn hình** | Ẩn menu, phóng to vùng làm việc — nên bật ở máy xưởng |
| Nút phóng to/thu nhỏ (±) | Chỉnh cỡ chữ toàn màn hình cân. **Máy tự nhớ lựa chọn của bạn** |
| Nút chọn ngôn ngữ | VI / EN / ZH |

---

## 4. Công đoạn 1 — Nhập đơn sản xuất (MainForm)

**Màn hình:** `Nhập đơn sản xuất — MainForm (C3)` · `/production-batches/grid`

### Các bước

1. Đặt con trỏ vào ô **Box1 (SCAN QR)**.
2. **Quét mã QR trên phiếu MES** rồi nhấn **Enter**. Hệ thống tự tách ra:
   - **Box1** → giữ lại **Mã màu**
   - **Box2** → **Mã hàng (CODE)**
   - **Box4** → **Máy nhuộm**
   - **Box6** → **Mực nước**
3. Chọn **MACHINE** (máy nhuộm) và **TANK** (thùng) bằng hai nút cùng tên nếu chưa tự điền.
4. Bấm **CHECK** để kiểm tra đơn đã có trong hàng chờ chưa:
   - **YES** — Đơn đã tồn tại trong hàng chờ → **không lưu lại lần nữa**, báo tổ trưởng.
   - **NO** — Chưa tồn tại, lưu mới được.
5. Bấm **SAVE** để lưu đơn.
6. Nếu ô **Box7** đang là `OK` thì SAVE xong hệ thống **tự PHÊ DUYỆT** đơn (bắt buộc đã chọn Thùng). Nếu không, bấm **PHE DUYET** riêng.

### Cảnh báo phải dừng lại

| Thông báo | Ý nghĩa | Xử lý |
|---|---|---|
| *"Mã quét bị rớt ký tự giữa chừng — SAVE đã bị khóa"* | Đầu đọc quét thiếu ký tự | Bấm **CLEAR**, đưa lại phiếu MES và **quét lại**. Không gõ tay bù. |
| *"Khong du thong tin (thiếu Màu / Mã hàng / Máy)"* | Còn ô trống bắt buộc | Điền đủ rồi SAVE lại |
| *"Máy của đơn này không có thùng … trong danh mục"* | Thùng chưa khai báo cho máy | Báo tổ trưởng/IT bổ sung danh mục |

**Hủy đơn:** dùng nút hủy trên form phụ — đơn chuyển sang trạng thái **CANCELLED**, không bị xóa khỏi hệ thống. Chỉ hủy khi đơn thực sự bỏ.

> **Lưu ý:** Sau khi đơn đã lưu thì **không đổi được Máy** nữa. Chọn kỹ trước khi SAVE.

---

## 5. Công đoạn 2 — In tem nhập đơn (TO_SEND)

**Màn hình:** `In tem nhập đơn — TO_SEND (DF002)` · `/print-order-entry`

### Các bước

1. Màn hình liệt kê các đơn **đang chờ in / chờ gửi**.
2. Bấm **print** ở dòng đơn cần in → mở phiếu xem trước và hộp thoại in.
3. Kiểm tra phiếu xem trước: đúng màu, đúng mã hàng, đúng máy → bấm in.
4. Dán tem lên thùng liệu.
5. Tick ô **scale_check** nếu đơn này cần đối chiếu cân (tick là ghi xuống hệ thống ngay).
6. Bấm **OK** để xác nhận **đã in & đã gửi** → đơn rời hàng chờ, chuyển sang **SENT LOG**.

> Nếu trình duyệt báo *"đã chặn cửa sổ in"*: bấm **Cho phép pop-up** cho trang này, hoặc bấm nút **PRINT** ngay trong phiếu xem trước.

**Xem lại đơn đã xử lý:** bấm link **📄 SENT LOG** ở góc màn hình, hoặc mở `/print-sent-log`.

### Màn hình SENT LOG

- Lọc theo **Cửa sổ thời gian** (24 giờ / 48 giờ / 7 ngày / 30 ngày), **Máy**, **Tank**, **CHECK**, **Trạng thái in**, **Trạm gửi**.
- Cột **IN** hiển thị trạng thái lệnh in **mới nhất**: `Đã in` / `Chờ in` / `Lỗi` / `Đã hủy` / `Chưa in`, kèm **số lần đã in (tính cả in lại)**.
- Bảng sắp xếp **mới nhất lên đầu**.
- Nút **LÀM MỚI** để nạp lại, **XÓA LỌC** để bỏ hết điều kiện lọc.

---

## 6. Công đoạn 3 — QR PRINTER 9 dòng

**Màn hình:** `QR PRINTER — scaleform (NEW 9ROWS BIG QR)` · `/qr-printer` (hoặc `/copower-print`)

### Các bước

1. Đặt con trỏ vào ô **txt_COLOR**, **quét tem** rồi nhấn **Enter**. Hệ thống tự tách ra mã lô và mã hàng.
2. Kiểm tra **9 dòng DYE** và **9 dòng CHEM** đã hiện đúng.
3. Di chuyển giữa các ô bằng **phím mũi tên ← ↑ → ↓**; **Enter** để xuống ô dưới.
4. Chỉnh **Khổ giấy (mm)** nếu dùng loại tem khác — hệ thống ghi lại khổ giấy vào nhật ký in.
5. Bấm **in phiếu** hoặc **SEND** để đẩy xuống hàng chờ gửi máy.

### Hai nút tra cứu ở góc trên bên phải

| Nút | Mở gì |
|---|---|
| **📤 LỊCH SỬ ĐÃ GỬI** | Danh sách các đơn đã SEND từ chính màn này (mở sang tab mới) |
| **🖨️ LỊCH SỬ ĐÃ IN** | Danh sách các phiếu đã in từ chính màn này (mở sang tab mới) |

### Cảnh báo phải dừng lại

| Thông báo | Xử lý |
|---|---|
| *"Máy … chưa có trong danh mục máy của hệ thống web — không gửi được"* | Báo tổ trưởng/IT khai báo máy trước |
| *"Máy … chưa có thùng … trong danh mục — không gửi được"* | Báo tổ trưởng/IT khai báo thùng |
| *"Trình duyệt đã chặn cửa sổ in"* | Cho phép pop-up cho trang này rồi bấm lại |

---

## 7. Công đoạn 4 — Trạm CÂN NHỎ (dưới 6 kg)

**Màn hình:** `Cân nhỏ (Trạm cân dưới 6kg)` · `/weighing-station-v2` · trạm `WS-SMALL-01`

### 7.1. Sáu nút chính

| Nút | Tác dụng |
|---|---|
| **XOÁ** (CLEAR) | Xóa sạch màn hình. **Có hỏi lại** vì sẽ mất cả số đã cân chưa LƯU |
| **LƯU** (SAVE) | Chốt cả mẻ, **lưu và in phiếu** |
| **TIẾP** (NEXT) | Chốt số ô đang cân → sang ô kế → **tự lấy bì mới** |
| **IN** (PRINT) | In phiếu |
| **TRA CỨU** (CHECK) | Mở **Lịch sử cân** sang tab mới |
| **ĐÓNG** (CLOSE) | Đóng phiên làm việc |

### 7.2. Cân theo đơn (cách chuẩn)

1. Đặt con trỏ vào ô **COLOR** (có chữ mờ *"quét mã vào đây"*).
2. **Quét mã QR trên phiếu** → hệ thống nạp đơn, hiện đủ danh sách dòng cần cân kèm **mục tiêu** từng dòng.
3. Bấm **TIẾP** để bắt đầu ô 1.
4. Đặt vật tư lên cân. Chờ đến khi thấy nhãn **● ỔN ĐỊNH** (không phải **○ CHỜ ỔN ĐỊNH**).
5. Đọc số ở ô **DELTA — đã trừ bì**, đối chiếu với **mục tiêu** hiển thị ngay cạnh.
6. Bấm **TIẾP** để chốt ô này và sang ô kế (hệ thống tự lấy bì mới).
7. Lặp lại cho tới hết các dòng.
8. Bấm **LƯU** → hệ thống lưu mẻ và **in phiếu cân**.

### 7.3. Cân tay (không có đơn)

Khi cần cân tạm, **không bắt buộc phải quét đơn**:

1. Đặt vật tư lên cân, chờ **● ỔN ĐỊNH**.
2. Bấm **LƯU** → vẫn in phiếu và lưu bình thường.
3. Muốn gộp nhiều thứ vào **một phiếu**: bấm **TIẾP** cho từng thứ rồi mới bấm **LƯU**.

> Màn hình sẽ hiện dòng nhắc *"Đang cân tay (chưa quét đơn nào)"*. Ở chế độ này **không có màu / mã hàng / mục tiêu để đối chiếu** — chỉ dùng khi tổ trưởng cho phép.

### 7.4. Chốt bì sai — nút BÌ LẠI

Nếu bì bị chốt nhầm (ví dụ đặt vật tư lên trước khi hệ thống chốt bì):

→ Bấm **BÌ LẠI**. Bì hiện tại bị bỏ, **lần đọc ổn định kế tiếp sẽ tự lấy bì mới**.

### 7.5. Hệ thống KHÔNG cho LƯU trong 3 trường hợp

| Thông báo | Nguyên nhân | Xử lý |
|---|---|---|
| *"Mất tín hiệu cân — số đang hiện là số cũ"* | Agent/dây cân có vấn đề | Xem [mục 12.1](#121-mất-tín-hiệu-cân). **Tuyệt đối không lưu số cũ** |
| *"Số cân chưa đứng yên"* | Cân còn dao động | Chờ hiện **● ỔN ĐỊNH** rồi LƯU lại |
| *"Cân đang rỗng (0.00)"* | Chưa đặt vật tư | Đặt vật tư lên cân rồi LƯU |

### 7.6. Cảnh báo cần đọc kỹ trước khi bấm

| Thông báo | Ý nghĩa | Cân nhắc |
|---|---|---|
| *"Còn N dòng CHƯA CÂN. Lưu bây giờ sẽ chốt các dòng đó là KHÔNG ĐẠT và **không cân lại được nữa**"* | Bỏ sót dòng | **Chỉ chọn "Vẫn lưu" khi thực sự không cân các dòng đó.** Nếu lỡ tay, phải báo tổ trưởng ngay |
| *"Dòng N không có vật tư trong mã QR — LƯU sẽ không lưu số ở dòng này"* | Mã QR thiếu vật tư dòng đó | Cân được để tham khảo nhưng **số sẽ không được lưu**. Báo người nhập đơn |
| *"Mã quét thiếu COLOR hoặc CODE"* | Tem hỏng hoặc đầu đọc lỗi | Quét lại; vẫn lỗi thì đổi tem/báo IT. (Thiếu MACHINE/LV thì **vẫn nạp được**, ô đó để trống) |

### 7.7. Tải lại trang giữa chừng

Nếu trang bị tải lại khi đang cân dở, hệ thống có thể báo:

> *"Đĩa cân đã thay đổi trong lúc tải lại trang (… → …). Các ô đã cân vẫn còn nguyên — bấm TIẾP để cân tiếp ô kế và lấy bì mới."*

→ **Số đã cân KHÔNG mất.** Cứ bấm **TIẾP** và cân tiếp.

---

## 8. Công đoạn 5 — Trạm CÂN TO (từ 6 kg)

**Màn hình:** `Cân to (Trạm cân lớn ≥ 6kg)` · `/weighing-station-large` · trạm `WS-LARGE-01`

Thao tác cân **giống hệt trạm cân nhỏ** (mục 7). Phần dưới đây là những điểm **chỉ có ở cân to**.

### 8.1. Bảng dữ liệu

Bảng có các cột: **RACK · DYE CODE · WEIGHT · PROCESS**, cùng các ô **COLOR · MACHINE · CODE · LV · RAW** và ô **DELTA — ĐÃ TRỪ BÌ**.

### 8.2. Sửa mục tiêu trước khi bắt đầu cân

- **Sửa được mục tiêu** cho tới khi bấm **TIẾP lần đầu** (dùng bàn phím số bên phải màn hình).
- Sau khi đã bắt đầu cân: *"Đã bắt đầu cân — không sửa mục tiêu giữa mẻ nữa. Bấm XOÁ rồi quét lại nếu cần đổi."*
- Mục tiêu đã sửa tay sẽ hiện chú thích **"Mục tiêu đã sửa tay. Số in trên tem: …"** — kiểm tra kỹ trước khi in.

### 8.3. Khối SEND OVER 6 — gửi mã rack sang hệ pha màu

| Nút | Tác dụng |
|---|---|
| **OUT** | Gửi lô mã rack đang chuẩn bị sang **hệ pha màu**. Màn hình báo *"Đang gửi mã rack sang hệ pha màu — chờ Agent xác nhận…"* |
| **IN** | Gửi lệnh **NHẬN** sang hệ pha màu |
| **COPY** | Chép lô 1 ra clipboard để **dán tay bằng Ctrl+V** sang hệ pha màu |
| **DEL** | Xóa dòng trong bảng rack |

**Quy trình:**
1. Nhập mã ở cột **RACK** hoặc quét đơn trước.
2. Kiểm tra dải **LÔ 1:** — đây chính là lô sẽ gửi khi bấm **OUT**.
3. Bấm **OUT** và **chờ Agent xác nhận** — không bấm liên tục nhiều lần.

| Thông báo | Xử lý |
|---|---|
| *"Không có mã rack nào để gửi"* | Nhập mã ở cột RACK hoặc quét đơn trước |
| *"Lô 1 đang trống, không có gì để chép"* | Chưa có mã rack nào |
| *"Trình duyệt không cho chép clipboard"* | Chép tay từ dải thông tin bên dưới |

> **Quan trọng:** Chức năng OUT/IN cần bộ **DF Agent — Cân to (IN/OUT)** cài riêng và **chạy trong phiên đăng nhập của thợ** (không phải service). Nếu đăng xuất Windows thì chức năng này ngừng. Biểu tượng nằm ở khay hệ thống — chuột phải để xem nhật ký hoặc thoát.

### 8.4. Cột thông tin bên phải

| Nút | Tác dụng |
|---|---|
| **SỰ CỐ** | Hiện cảnh báo mẻ chưa gửi được lên máy chủ |
| **THÔNG TIN** | Hiện cột trạm, hàng đợi, cỡ chữ, giả lập |
| **THU GỌN ›** | Thu gọn cột này cho rộng mặt form |

---

## 9. Tra cứu Lịch sử cân và In lại phiếu

**Màn hình:** `Lịch sử cân` · `/weighing-history` — mở nhanh bằng nút **TRA CỨU / CHECK** trên màn cân (mở sang **tab mới**, màn cân vẫn giữ nguyên).

Có thêm bản **giao diện cổ điển** tại `/weighing-history/classic` cho ai quen kiểu Windows/Excel cũ.

### 9.1. Bộ lọc

| Ô lọc | Dùng để |
|---|---|
| **Từ ngày / Đến ngày** | Khoanh khoảng thời gian |
| **Tìm nhanh** | Gõ Màu / mã hàng / mã lô / máy / LV — lọc ngay trong danh sách đang xem |
| **🔎 Tìm trên toàn bộ lịch sử** | Tìm vượt ra ngoài danh sách đang tải (dùng khi tìm nhanh không ra) |
| **Máy · LV · Cân · Kết quả** | Lọc theo máy, LV, cân to/cân nhỏ, `Có dòng KHÔNG ĐẠT` / `Toàn bộ ĐẠT` |
| **Mỗi trang** | Số dòng hiển thị mỗi trang |
| **Xoá lọc / ⟳ Làm mới** | Bỏ điều kiện lọc / nạp dữ liệu mới nhất |

> Nếu thấy cảnh báo *"⚠ Chỉ đang xem N vòng cân gần nhất — còn nữa ở phía trước"*: **thu hẹp khoảng ngày** hoặc bấm **🔎 Tìm trên toàn bộ lịch sử**.

### 9.2. Phát hiện cân trùng

Hệ thống tự đếm và cảnh báo các đơn **bị cân nhiều lần**.

> **Trùng = giống cả 4 yếu tố: Màu + Mã hàng + Máy + LV.**

Bấm vào ô thống kê để lọc riêng các vòng cân bị trùng — các vòng của cùng một đơn được **xếp liền nhau, mỗi cụm một nền riêng** cho dễ đối chiếu. Phát hiện trùng phải **báo tổ trưởng**, không tự xử lý.

### 9.3. Cột dữ liệu

`Thời điểm cân · Cân (TO/NHỎ) · Màu · Mã hàng · Máy · LV · Số dòng · Đạt · Không đạt`

### 9.4. In lại phiếu

Mỗi dòng có nút **In lại phiếu cân của vòng này**.

> ⚠️ **Mọi lần in lại đều được hệ thống ghi nhật ký (Audit Log)** kèm tên người thực hiện. Chỉ in lại khi phiếu gốc hỏng/mất, và phải báo tổ trưởng lý do.

---

## 10. Gọi hóa chất

**Màn hình:** `Bảng Gọi Hóa chất — Giao diện cổ điển` · `/chemical-call/classic`
**Hàng đợi:** `Hàng đợi Đang chờ — Giao diện cổ điển` · `/chemical-call/pending-classic`

Giao diện dựng lại **y hệt UserForm CHEM_ORDER** của Excel cũ (2 cột, ô đỏ/xanh ORDER/DONE, nút OK riêng).

### Các bước

1. Trên bảng, **bấm nút mang tên mã hóa chất** ở dòng máy cần cấp → gửi lệnh gọi hóa chất cho máy đó.
2. Ô chuyển trạng thái **ORDER** (đang chờ cấp).
3. Khi đã cấp xong, bấm **OK** để **xác nhận đã cấp xong** → chuyển **DONE**.
4. Màn hàng đợi hiển thị các thùng đang chờ. Nếu có dòng *"+N khác đang chờ"* → xem đầy đủ ở màn **"Đang chờ xử lý"**.

| Thông báo lỗi | Xử lý |
|---|---|
| *"Không thể kết nối đến máy chủ API để lấy thông tin van đường ống"* | Mất mạng/máy chủ — báo IT ngay, **không cấp hóa chất bằng phán đoán** |
| *"Không thể gọi hóa chất cho thùng này"* | Thử lại; vẫn lỗi thì báo IT |
| *"Không thể xác nhận xong cho thùng này"* | Thử lại; vẫn lỗi thì ghi giấy và báo tổ trưởng |

---

## 11. Màn hình treo xưởng (chỉ xem)

Các màn này **chỉ để xem, không thao tác**:

| Màn hình | Đường dẫn | Nội dung |
|---|---|---|
| **Bảng máy VD (MACHINE_ID)** | `/machine-id-board` | Thông tin đơn theo máy. **Tự nạp lại mỗi 3 phút.** Chú thích màu: `Đã gửi:` / `Đang chờ:`. Góc màn hiện `Cập nhật lúc …` |
| **Gantt máy VD (BPDB)** | `/bpdb-machines/gantt` | Tiến độ chạy máy theo dạng biểu đồ thời gian |

> Nếu **"Cập nhật lúc"** đứng yên quá lâu (trên 10 phút): báo IT — có thể mất kết nối máy chủ.

---

## 12. Xử lý sự cố thường gặp

### 12.1. Mất tín hiệu cân

**Dấu hiệu:** dải đỏ **`⚠ MẤT TÍN HIỆU CÂN — kiểm tra Agent / PuTTY / dây cân`**, nhãn **`✕ MẤT TÍN HIỆU`**, kèm ghi chú *"(số cân cũ … giây)"*.

**Số đang hiện là SỐ CŨ — không được lưu.**

| Bước | Việc làm |
|---|---|
| 1 | Kiểm tra **cân đã bật** và **dây tín hiệu** cắm chắc hai đầu |
| 2 | Kiểm tra biểu tượng **DF Agent** ở khay hệ thống còn chạy không |
| 3 | Kiểm tra **PuTTY** (nếu trạm dùng đường putty_log) còn mở không |
| 4 | Tải lại trang (F5) — **số đã cân không mất** |
| 5 | Vẫn lỗi → **báo IT**, chuyển sang cân bằng Excel cũ và ghi lại số bằng tay |

### 12.2. Mất kết nối máy chủ

**Dấu hiệu:** `⚠ MẤT KẾT NỐI MÁY CHỦ — số cân không cập nhật. Số đã cân KHÔNG mất, cân lại được ngay khi có mạng.`

→ **Bình tĩnh, dữ liệu không mất.** Chờ mạng có lại. Nếu quá 15 phút, báo IT.

### 12.3. Mẻ chờ gửi (hàng đợi offline)

**Dấu hiệu:** chỉ báo **`N mẻ chờ gửi`** ở cột thông tin. Bấm vào để mở danh sách.

**Hiểu đúng bản chất:**
> Các mẻ này **đã cân xong và đã in phiếu**, chỉ chưa lên được máy chủ. **Máy tự gửi lại mỗi 15 giây** khi có mạng.

| Nút trong danh sách | Khi nào dùng |
|---|---|
| **GỬI NGAY** | Muốn thử gửi lại ngay lập tức |
| **THỬ LẠI** | Mẻ bị máy chủ từ chối — thử lại sau khi IT xử lý |
| **BỎ MẺ** | ⚠️ **Chỉ khi phiếu đã in cũng bị hủy** (quét nhầm, cân lại mẻ khác) |

> ⚠️ **BỎ MẺ nghĩa là mẻ đó sẽ KHÔNG BAO GIỜ được gửi lên máy chủ nữa**, trong khi phiếu đã in ra giấy. Mẻ bị bỏ vẫn được giữ vết trong máy để đối chiếu. **Phải hỏi tổ trưởng trước khi bỏ mẻ.**

Cột trạng thái cho biết: `đang chờ tới lượt gửi` / `mất kết nối — đang dò lại mỗi 15 giây` / lý do máy chủ từ chối.

### 12.4. Máy chủ từ chối mẻ

**Dấu hiệu:** *"Máy chủ TỪ CHỐI mẻ này: … Phiếu đã in, mẻ vẫn nằm trong hàng đợi."*

→ Bấm chỉ báo **"mẻ chờ gửi"** để xem lý do → **báo IT kèm ảnh chụp màn hình**. Đừng bỏ mẻ.

### 12.5. Không in được phiếu / tem

| Dấu hiệu | Xử lý |
|---|---|
| *"Trình duyệt đã chặn cửa sổ in"* | Cho phép **pop-up** cho trang này rồi bấm lại |
| *"Không thể in phiếu cân"* | Kiểm tra máy in bật/có giấy; kiểm tra DF Agent còn chạy; thử in lại từ **Lịch sử cân** |
| *"Không dựng được phiếu in"* | Báo IT kèm ảnh chụp màn hình |

### 12.6. "Chưa gán trạm"

**Dấu hiệu:** cột bên phải hiện **`chưa gán trạm`** thay vì mã trạm → **không quét được**.

**Xử lý theo đúng thứ tự:**
1. Kiểm tra **DF Agent của đúng loại cân** còn chạy trên **chính máy này** không:
   - Cân nhỏ → service **DFAgentSmall**
   - Cân to → service **DFAgentLarge**
2. **Đợi khoảng 1 phút** cho Agent báo danh.
3. **Tải lại trang** (F5).
4. Vẫn không được → báo IT.

### 12.7. Trạm không đúng loại cho màn hình này

**Dấu hiệu:** `⚠️ Trạm "…" không có quyền cho màn hình này`

→ Chọn đúng trạm trong danh sách hiện ra, hoặc mở lại bằng **link riêng do IT cấp cho máy đó**. Không tự chọn bừa trạm khác — dữ liệu sẽ bị ghi sai tên trạm.

### 12.8. Bảng tra cứu nhanh cảnh báo trên màn cân

| Nhãn hiển thị | Nghĩa | Cân được không? |
|---|---|---|
| **● ỔN ĐỊNH** | Số cân đã đứng yên | ✅ Được |
| **○ CHỜ ỔN ĐỊNH** | Còn dao động | ❌ Chờ thêm |
| **✕ MẤT TÍN HIỆU** | Không đọc được cân | ❌ Dừng, xử lý sự cố |
| **⚡ Agent tại chỗ** | Đọc nhanh, bình thường | ✅ |
| **☁ qua máy chủ** | Đọc chậm hơn (~0,4–0,9 giây) | ✅ nhưng báo IT |
| **Bì …** | Đã chốt bì | ✅ |
| **chờ chốt bì** | Chưa chốt bì | ❌ Chờ thêm |

---

## 13. Những việc TUYỆT ĐỐI KHÔNG được làm

| # | Điều cấm | Lý do |
|---|---|---|
| 1 | **Xóa dữ liệu trình duyệt** (lịch sử, cache, "Clear browsing data") khi còn **mẻ chờ gửi** | **Mất vĩnh viễn** các mẻ đã cân và đã in phiếu nhưng chưa lên máy chủ |
| 2 | **Lưu số cân khi đang MẤT TÍN HIỆU** | Số đang hiện là số cũ → sai khối lượng thực tế |
| 3 | **Bỏ mẻ** khi chưa hỏi tổ trưởng | Mẻ không bao giờ lên máy chủ trong khi phiếu đã in → lệch sổ sách |
| 4 | **Dùng tài khoản của người khác để cân** | Hệ thống ghi sai người chịu trách nhiệm; sai lệch dung sai không truy được ai duyệt |
| 5 | **Bấm "Vẫn lưu" khi còn dòng chưa cân** nếu không cố ý | Các dòng đó bị chốt **KHÔNG ĐẠT** và **không cân lại được** |
| 6 | **Tự tắt / gỡ DF Agent**, tự đổi cấu hình cổng COM, tên máy in | Toàn trạm ngừng cân/in |
| 7 | **Tắt hoặc xóa file Excel VBA cũ** trong giai đoạn chạy song song | Chưa có biên bản nghiệm thu — mất đường lùi khi hệ thống Web có sự cố |
| 8 | **Gõ tay số cân thay vì cân thật** | Không có vết cân thật, vi phạm nguyên tắc truy vết |
| 9 | Tự ý mở màn hình của công đoạn khác | Hệ thống sẽ chặn; cố tình lách là vi phạm quy định vận hành |

---

## 14. Liên hệ hỗ trợ

| Tình huống | Báo cho ai | Thông tin cần chuẩn bị |
|---|---|---|
| Cân không lên số, mất tín hiệu | Tổ trưởng ca → IT | Mã trạm, ảnh chụp màn hình, giờ xảy ra |
| Máy in không ra tem | Tổ trưởng ca → IT | Mã trạm, tên máy in |
| Mẻ chờ gửi không tự hết | IT | Ảnh chụp danh sách mẻ chờ + cột trạng thái |
| Máy chủ từ chối mẻ | IT | Ảnh chụp thông báo lỗi đầy đủ |
| Đơn sai màu/mã hàng/máy | Tổ trưởng ca | Mã lô, mã màu, mã hàng |
| Phát hiện cân trùng | Tổ trưởng ca | Màu + Mã hàng + Máy + LV |
| Nghi ngờ Web và Excel lệch số | Tổ trưởng ca (ghi biên bản) | Cả 2 số, mã lô, giờ cân |

**Nguyên tắc chung khi gặp sự cố:**
1. **Không đoán** — dừng lại và báo.
2. **Chụp màn hình** trước khi tắt/tải lại trang.
3. **Ghi lại giờ** xảy ra.
4. Trong lúc chờ xử lý, dùng quy trình Excel cũ và **ghi tay số liệu** để đối chiếu sau.

---

**Ghi chú phiên bản**

| Phiên bản | Ngày | Nội dung thay đổi | Người sửa |
|---|---|---|---|
| 1.0 | 12/08/2026 | Ban hành lần đầu | Bùi Văn Thiều |
