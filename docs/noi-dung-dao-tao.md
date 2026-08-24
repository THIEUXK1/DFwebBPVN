# NỘI DUNG ĐÀO TẠO — HỆ THỐNG DF CONNECTOR
## 培训内容 — DF Connector 系统

> **Cách dùng tài liệu này:** Đây là nội dung soạn sẵn để điền vào biểu mẫu **BPVN-HR-PR-030 — 教育训练记录表 / Biên bản đào tạo**. Mỗi mục dưới đây tương ứng với một ô trên biểu mẫu. Phần chữ Trung — Việt được viết song ngữ đúng phong cách biểu mẫu gốc.

---

## PHẦN 1 — THÔNG TIN ĐIỀN VÀO ĐẦU BIỂU MẪU

| Ô trên biểu mẫu | Nội dung điền |
|---|---|
| **日期 / Ngày tháng** | **10/08/2026** |
| **时间 / Thời gian** | **14:00 – 16:00** (2 giờ chiều đến 4 giờ chiều) |
| **地方 / Địa điểm** | 培训房间 / Phòng đào tạo |
| **讲师 / Người giảng** | **Bùi Văn Thiều** |
| **部門 / Bộ phận** | IT |
| **教程 / Giáo trình ĐT** | 《DF Connector 系统操作手册》<br>《Hướng dẫn sử dụng hệ thống DF Connector》 — `docs/huong-dan-van-hanh.md` |

---

## PHẦN 2 — 训练目的 / MỤC ĐÍCH ĐÀO TẠO

> **Điền vào ô "训练目的 / Mục đích ĐT":**

**使操作人员掌握 DF Connector 网页系统的正确操作方法，在与旧版 Excel 系统并行运行期间保证称重数据准确、可追溯，杜绝数据丢失与误操作。**

**Giúp nhân viên vận hành nắm vững cách sử dụng đúng hệ thống Web DF Connector; bảo đảm số liệu cân chính xác và truy vết được trong giai đoạn chạy song song với hệ thống Excel cũ; phòng ngừa mất dữ liệu và thao tác sai.**

*(Bản rút gọn nếu ô trên biểu mẫu hẹp):*
> 掌握 DF Connector 系统操作，保证称重数据准确可追溯，防止数据丢失。
> Nắm vững thao tác hệ thống DF Connector, bảo đảm số liệu cân chính xác — truy vết được, phòng ngừa mất dữ liệu.

---

## PHẦN 3 — 陪训內容 / NỘI DUNG ĐỐI THOẠI

### A. 主要内容 / Nội dung chính

> **8 mục dưới đây thay thế cho danh mục 6 chủ đề in sẵn trên biểu mẫu gốc** (thời giờ nghỉ ngơi, thang bảng lương…) — vì buổi đào tạo này là đào tạo kỹ thuật vận hành hệ thống.

| # | 内容 / Nội dung |
|---|---|
| **I** | **系统概述与各工序职责**<br>Tổng quan hệ thống và trách nhiệm từng công đoạn |
| **II** | **班前检查：电子秤、DF Agent、标签打印机、工位代码**<br>Kiểm tra đầu ca: cân điện tử, DF Agent, máy in tem, mã trạm làm việc |
| **III** | **生产工单录入（MainForm）与标签打印（TO_SEND / QR PRINTER）**<br>Nhập đơn sản xuất (MainForm) và in tem (TO_SEND / QR PRINTER) |
| **IV** | **小秤工位操作（6kg 以下）：扫码、去皮、DELTA、NEXT、SAVE**<br>Vận hành trạm cân nhỏ (dưới 6kg): quét mã, trừ bì, DELTA, TIẾP, LƯU |
| **V** | **大秤工位操作（6kg 以上）及 SEND OVER 6（OUT/IN 发送料架码）**<br>Vận hành trạm cân to (từ 6kg) và khối SEND OVER 6 (nút OUT/IN gửi mã rack) |
| **VI** | **称重履历查询、补打单据、重复称重的识别与上报**<br>Tra cứu lịch sử cân, in lại phiếu, nhận biết và báo cáo cân trùng |
| **VII** | **异常处理：秤信号丢失、断网、待发送批次、打印失败**<br>Xử lý sự cố: mất tín hiệu cân, mất mạng, mẻ chờ gửi, in lỗi |
| **VIII** | **数据安全九条禁令与并行运行期间的对账要求**<br>Chín điều cấm về an toàn dữ liệu và yêu cầu đối soát trong giai đoạn chạy song song |

---

### Chi tiết từng mục (dùng để giảng, không cần chép hết vào biểu mẫu)

#### I. 系统概述 / Tổng quan hệ thống

- DF Connector 是连接 **MES 系统** 与 **自动染色/配色系统** 的桥梁，取代原有 Excel VBA + Access 工具。
  DF Connector là cầu nối giữa **hệ thống MES** và **hệ thống nhuộm/pha màu tự động**, thay thế bộ công cụ Excel VBA + Access cũ.
- **浏览器绝不直接连接硬件**：所有读秤、打印都通过本机 **DF Agent**。
  **Trình duyệt tuyệt đối không nối trực tiếp với thiết bị**: mọi thao tác đọc cân và in tem đều đi qua **DF Agent** trên máy.
- **"一台电脑 = 一道工序"**：操作账号只能进入本工序画面，手动输入其他网址会被退回 —— 这是规定，不是故障。
  **"1 máy tính = 1 công đoạn"**: tài khoản vận hành chỉ vào được màn hình của công đoạn mình; gõ tay địa chỉ khác sẽ bị đưa về — đây là quy định, không phải lỗi.
- 界面支持 **越南语 / English / 中文** 三种语言，右上角切换。
  Giao diện có **3 ngôn ngữ VI / EN / ZH**, đổi ở góc trên bên phải.

#### II. 班前检查 / Kiểm tra đầu ca

四项必检 / Bốn hạng mục bắt buộc:

1. 电子秤已开机、信号线插牢 / Cân đã bật, dây tín hiệu cắm chắc
2. **DF Agent 正在运行**（系统托盘有图标）/ **DF Agent đang chạy** (có biểu tượng ở khay hệ thống)
3. 打印机已开机、有纸有碳带 / Máy in đã bật, có giấy và mực
4. 画面右上角显示 **本机正确的工位代码** / Góc trên bên phải hiện **đúng mã trạm** của máy mình

> 应看到 **⚡ 本地 Agent** 标识（约 70 毫秒）；若显示 **☁ 经服务器**（约 0.4–0.9 秒）仍可称重，但须报 IT。
> Cần thấy nhãn **⚡ Agent tại chỗ** (~70ms); nếu hiện **☁ qua máy chủ** (~0,4–0,9 giây) thì vẫn cân được nhưng phải báo IT.

#### III. 工单录入与标签打印 / Nhập đơn và in tem

- **MainForm**：光标放入 Box1 → 扫 MES 单据二维码 → 回车 → 系统自动拆分 **颜色 / 品号 / 机台 / 水位** → **CHECK** 查重 → **SAVE** → **PHE DUYET**（审批）。
  **MainForm**: đặt con trỏ vào Box1 → quét QR phiếu MES → Enter → hệ thống tự tách **Màu / Mã hàng / Máy / Mực nước** → **CHECK** kiểm tra trùng → **SAVE** → **PHE DUYET**.
- **CHECK 结果 YES = 订单已在队列中，禁止重复保存**，须报组长。
  **CHECK ra YES = đơn đã có trong hàng chờ, cấm lưu lại lần nữa**, phải báo tổ trưởng.
- 扫码掉字符时系统会 **锁住 SAVE**：必须 **CLEAR 后重新扫描**，严禁手工补录。
  Khi quét bị rớt ký tự, hệ thống **khóa nút SAVE**: phải **bấm CLEAR rồi quét lại**, nghiêm cấm gõ tay bù.
- **订单保存后不能再改机台** / **Sau khi lưu đơn thì không đổi được Máy**.
- **TO_SEND**：print → 核对预览 → 贴标签 → 勾选 scale_check → **OK** 确认（订单转入 SENT LOG）。
  **TO_SEND**: bấm print → kiểm tra phiếu xem trước → dán tem → tick scale_check → **OK** xác nhận (đơn chuyển sang SENT LOG).
- **QR PRINTER（9 行）**：txt_COLOR 扫码回车 → 核对 9 行 DYE + 9 行 CHEM → 设定纸张尺寸 → 打印 / SEND。
  **QR PRINTER (9 dòng)**: quét vào ô txt_COLOR rồi Enter → kiểm tra 9 dòng DYE + 9 dòng CHEM → chọn khổ giấy → in / SEND.

#### IV. 小秤工位 / Trạm cân nhỏ (< 6 kg)

六个主按钮 / Sáu nút chính: **XOÁ (CLEAR) · LƯU (SAVE) · TIẾP (NEXT) · IN (PRINT) · TRA CỨU (CHECK) · ĐÓNG (CLOSE)**

标准流程 / Quy trình chuẩn:
1. 光标放入 **COLOR** 栏 → 扫单据二维码 → 载入订单及各行目标值
   Đặt con trỏ vào ô **COLOR** → quét QR trên phiếu → nạp đơn kèm mục tiêu từng dòng
2. 按 **TIẾP** 开始第 1 格 / Bấm **TIẾP** để bắt đầu ô 1
3. 放料，等待 **● 稳定 / ● ỔN ĐỊNH**（不可在 ○ 等待稳定 时保存）
4. 读 **DELTA（已扣皮重）**，与目标值核对 / Đọc **DELTA (đã trừ bì)**, đối chiếu mục tiêu
5. 按 **TIẾP** 锁定本格并自动重新取皮重 / Bấm **TIẾP** để chốt ô này và tự lấy bì mới
6. 全部称完按 **LƯU** → 保存并打印单据 / Cân xong hết bấm **LƯU** → lưu và in phiếu

其他要点 / Các điểm khác:
- **手动称重（无订单）**：直接放料 → **LƯU** 即可打印保存；多项合并一张单据则先按 **TIẾP** 逐项称重。
  **Cân tay (không có đơn)**: đặt vật tư → bấm **LƯU** là in và lưu được; muốn gộp nhiều thứ vào một phiếu thì bấm **TIẾP** cho từng thứ.
- **皮重锁错** → 按 **BÌ LẠI**（重新去皮），下一次稳定读数自动取新皮重。
  **Chốt bì sai** → bấm **BÌ LẠI**, lần đọc ổn định kế tiếp tự lấy bì mới.
- **三种情况系统拒绝保存**：信号丢失 / 读数未稳定 / 秤盘为空 (0.00)。
  **Ba trường hợp hệ thống không cho LƯU**: mất tín hiệu / chưa ổn định / cân rỗng (0.00).
- ⚠ 提示"还有 N 行尚未称重，保存将判定为不合格且无法再称" —— **确认后不可撤销**。
  ⚠ Cảnh báo "Còn N dòng CHƯA CÂN, lưu sẽ chốt KHÔNG ĐẠT và không cân lại được" — **đã đồng ý là không quay lại được**.

#### V. 大秤工位与 SEND OVER 6 / Trạm cân to và khối SEND OVER 6

- 称重操作与小秤 **完全相同** / Thao tác cân **giống hệt** trạm cân nhỏ.
- 数据栏 / Cột dữ liệu: **RACK · DYE CODE · WEIGHT · PROCESS**
- **目标值只能在首次按 TIẾP 之前修改**；开始称重后须 **XOÁ 后重新扫码** 才能更改。
  **Chỉ sửa được mục tiêu trước khi bấm TIẾP lần đầu**; đã bắt đầu cân thì phải **XOÁ rồi quét lại** mới đổi được.
- **OUT** = 发送料架码至配色系统；**IN** = 发送接收指令；**COPY** = 复制第 1 批供 Ctrl+V 手工粘贴。
  **OUT** = gửi mã rack sang hệ pha màu; **IN** = gửi lệnh nhận; **COPY** = chép lô 1 để dán tay bằng Ctrl+V.
- **按 OUT 后须等待 Agent 确认，不可连续多次点击。**
  **Bấm OUT xong phải chờ Agent xác nhận, không bấm liên tục nhiều lần.**
- IN/OUT 功能依赖 **单独安装的"大秤 IN/OUT" Agent**，它运行在操作员的登录会话中 —— **Windows 注销即失效**。
  Chức năng IN/OUT cần bộ **DF Agent Cân to (IN/OUT)** cài riêng, chạy trong phiên đăng nhập của thợ — **đăng xuất Windows là ngừng**.

#### VI. 履历查询与补打 / Lịch sử cân và in lại phiếu

- 按秤台上的 **TRA CỨU / CHECK** 按钮 → 履历在 **新标签页** 打开，称重画面保持不变。
  Bấm nút **TRA CỨU / CHECK** trên màn cân → lịch sử mở ở **tab mới**, màn cân giữ nguyên.
- 筛选：日期区间 / 快速查找 / **🔎 全库查找** / 机台 / LV / 大秤·小秤 / 结果（有不合格行·全部合格）。
  Bộ lọc: khoảng ngày / tìm nhanh / **🔎 tìm toàn bộ lịch sử** / máy / LV / cân to·nhỏ / kết quả.
- **重复称重判定 = 颜色 + 品号 + 机台 + LV 四项全同** → 发现必须报组长，不得自行处理。
  **Cân trùng = giống cả 4: Màu + Mã hàng + Máy + LV** → phát hiện phải báo tổ trưởng, không tự xử lý.
- ⚠ **每次补打单据都会记入审计日志（Audit Log）并留下操作人姓名。**
  ⚠ **Mọi lần in lại phiếu đều được ghi Audit Log kèm tên người thực hiện.**

#### VII. 异常处理 / Xử lý sự cố

| 现象 / Hiện tượng | 处理 / Xử lý |
|---|---|
| **✕ 秤信号丢失 / MẤT TÍN HIỆU CÂN** | 显示的是旧数值，**绝对不可保存**。查秤电源→信号线→Agent→PuTTY→刷新页面(F5)，仍不行报 IT。<br>Số đang hiện là số cũ, **tuyệt đối không được lưu**. Kiểm tra nguồn cân → dây → Agent → PuTTY → F5; vẫn lỗi thì báo IT. |
| **⚠ 服务器连接中断 / MẤT KẾT NỐI MÁY CHỦ** | **数据不会丢失**，等待网络恢复；超过 15 分钟报 IT。<br>**Dữ liệu không mất**, chờ mạng; quá 15 phút thì báo IT. |
| **N 批待发送 / N mẻ chờ gửi** | 这些批次 **已称重、已打印单据**，只是未上传，机器每 15 秒自动重试。<br>Các mẻ này **đã cân xong và đã in phiếu**, chỉ chưa lên máy chủ, máy tự gửi lại mỗi 15 giây. |
| **丢弃批次 / BỎ MẺ** | ⚠ 丢弃后 **永不上传**，而单据已打印 —— **必须先问组长**。<br>⚠ Bỏ rồi thì **không bao giờ gửi lên máy chủ nữa** trong khi phiếu đã in — **phải hỏi tổ trưởng trước**. |
| **服务器拒绝批次 / Máy chủ từ chối mẻ** | 截图 + 报 IT，**不要丢弃**。<br>Chụp màn hình + báo IT, **đừng bỏ mẻ**. |
| **打印窗口被拦截 / Cửa sổ in bị chặn** | 允许本页 pop-up 后重按。<br>Cho phép pop-up cho trang này rồi bấm lại. |
| **"未分配工位 / chưa gán trạm"** | 检查本机对应 Agent 服务（DFAgentSmall / DFAgentLarge）→ 等约 1 分钟 → 刷新页面。<br>Kiểm tra service Agent đúng loại → đợi ~1 phút → tải lại trang. |

#### VIII. 数据安全禁令 / Các điều cấm về an toàn dữ liệu

**九条禁令 / Chín điều cấm:**

1. 队列中还有待发送批次时 **清除浏览器数据** —— 已称重、已打印的数据将 **永久丢失**。
   **Xóa dữ liệu trình duyệt** khi còn mẻ chờ gửi — dữ liệu đã cân, đã in phiếu sẽ **mất vĩnh viễn**.
2. **信号丢失时保存称重数据** / **Lưu số cân khi đang mất tín hiệu**.
3. **未经组长同意丢弃批次** / **Bỏ mẻ khi chưa hỏi tổ trưởng**.
4. **借用他人账号称重** —— 系统会记错责任人。
   **Dùng tài khoản người khác để cân** — hệ thống ghi sai người chịu trách nhiệm.
5. **在还有未称行时误按"仍要保存"** / **Bấm "Vẫn lưu" khi còn dòng chưa cân mà không cố ý**.
6. **自行关闭或卸载 DF Agent、擅改 COM 口/打印机设置**.
   **Tự tắt/gỡ DF Agent, tự đổi cổng COM hoặc cài đặt máy in**.
7. **并行运行期间关闭或删除旧 Excel VBA 文件** —— 未经验收，等于自断退路。
   **Tắt hoặc xóa file Excel VBA cũ trong giai đoạn chạy song song** — chưa nghiệm thu, mất đường lùi.
8. **手工输入数字代替实际称重** / **Gõ tay số cân thay vì cân thật**.
9. **擅自进入其他工序画面** / **Tự ý mở màn hình của công đoạn khác**.

**遇到异常的四条原则 / Bốn nguyên tắc khi gặp sự cố:**
1. 不猜测，先停下上报 / Không đoán — dừng lại và báo
2. 关闭或刷新页面前先 **截图** / **Chụp màn hình** trước khi tắt hoặc tải lại trang
3. 记录发生时间 / Ghi lại giờ xảy ra
4. 等待处理期间沿用旧 Excel 流程并 **手工记录数据** 以便事后对账
   Trong lúc chờ xử lý, dùng quy trình Excel cũ và **ghi tay số liệu** để đối chiếu sau

---

### B. 关于培训内容的问答 / Hỏi đáp về nội dung đối thoại

> **Điền vào ô "B. Hỏi đáp về nội dung đối thoại"** — chọn 5–8 câu phù hợp với thực tế buổi đào tạo, ghi kèm câu trả lời của học viên.

| # | 问 / Câu hỏi | 答 / Trả lời |
|---|---|---|
| 1 | 秤显示"信号丢失"时可以按保存吗？<br>Khi cân báo "mất tín hiệu" có được bấm LƯU không? | **不可以。** 显示的是旧数值，必须先排除故障。<br>**Không.** Số đang hiện là số cũ, phải xử lý sự cố trước. |
| 2 | 断网时称好的数据会丢失吗？<br>Mất mạng thì số đã cân có mất không? | **不会。** 批次存在本机队列中，单据照常打印，联网后自动上传。<br>**Không mất.** Mẻ nằm trong hàng đợi của máy, phiếu vẫn in, có mạng sẽ tự gửi. |
| 3 | 什么时候才可以按"丢弃批次"？<br>Khi nào mới được bấm "BỎ MẺ"? | 只有当已打印的单据 **同时作废**（扫错、改称其他批次），并且 **经组长同意** 才可以。<br>Chỉ khi phiếu đã in **cũng bị hủy** (quét nhầm, cân lại mẻ khác) và **được tổ trưởng đồng ý**. |
| 4 | 队列里还有待发送批次时，可以清理浏览器吗？<br>Còn mẻ chờ gửi thì có được dọn dẹp trình duyệt không? | **绝对不可以**，数据会永久丢失。<br>**Tuyệt đối không**, dữ liệu sẽ mất vĩnh viễn. |
| 5 | DELTA 和 RAW 有什么区别？<br>DELTA khác RAW ở chỗ nào? | **DELTA 是已扣皮重的净重**，也是保存并打印在标签上的数字。<br>**DELTA là số đã trừ bì** — chính là số được lưu và in lên tem. |
| 6 | 皮重锁错了怎么办？<br>Chốt bì sai thì làm sao? | 按 **BÌ LẠI（重新去皮）**，下一次稳定读数会自动取新皮重。<br>Bấm **BÌ LẠI**, lần đọc ổn định kế tiếp sẽ tự lấy bì mới. |
| 7 | 什么算重复称重？<br>Thế nào là cân trùng? | **颜色 + 品号 + 机台 + LV 四项全部相同**；发现须报组长。<br>**Giống cả 4: Màu + Mã hàng + Máy + LV**; phát hiện phải báo tổ trưởng. |
| 8 | 扫码掉字符导致 SAVE 被锁，可以手工补录吗？<br>Quét bị rớt ký tự làm khóa SAVE, có được gõ tay bù không? | **不可以。** 必须 CLEAR 后重新扫描 MES 单据。<br>**Không.** Phải bấm CLEAR rồi quét lại phiếu MES. |
| 9 | 大秤按 OUT 没反应可以连点吗？<br>Cân to bấm OUT chưa thấy gì có được bấm liên tục không? | **不可以**，须等 Agent 确认；长时间无响应报 IT。<br>**Không**, phải chờ Agent xác nhận; lâu không phản hồi thì báo IT. |
| 10 | 现在还能关掉旧的 Excel 文件吗？<br>Bây giờ đã tắt được file Excel cũ chưa? | **还不能。** 并行运行验收合格并有正式验收记录后才可以。<br>**Chưa.** Phải chạy song song đạt nghiệm thu và có biên bản chính thức. |
| 11 | 补打单据有记录吗？<br>In lại phiếu có bị ghi lại không? | **有**，每次补打都记入审计日志并留下操作人姓名。<br>**Có**, mỗi lần in lại đều ghi Audit Log kèm tên người thực hiện. |
| 12 | 画面显示"未分配工位"该怎么处理？<br>Màn hình báo "chưa gán trạm" thì xử lý thế nào? | 检查本机 Agent 服务是否运行 → 等约 1 分钟 → 刷新页面；仍不行报 IT。<br>Kiểm tra service Agent trên máy → đợi ~1 phút → tải lại trang; vẫn lỗi thì báo IT. |

---

## PHẦN 4 — 备注 / GHI CHÚ

> **Điền vào ô "备注 / Ghi chú":**

**培训期间为并行运行阶段（Phase 12），学员须同时使用网页系统与原 Excel 系统，每班对账一次；发现数值不一致须立即上报组长并做书面记录。**

**Thời gian đào tạo thuộc giai đoạn chạy song song (Phase 12), học viên phải dùng đồng thời hệ thống Web và hệ thống Excel cũ, đối soát mỗi ca một lần; phát hiện lệch số phải báo tổ trưởng ngay và ghi biên bản.**

---

> **Danh sách học viên tham gia:** lập theo biểu mẫu điểm danh riêng của bộ phận, đính kèm cùng biên bản này.

---

| **纪录 / Ghi chép** | **检查 / Kiểm tra** |
|---|---|
| *(Ký, ghi rõ họ tên)* | *(Ký, ghi rõ họ tên)* |
| <br><br><br> | <br><br><br> |
| ………………………………… | ………………………………… |

---

## PHẦN 5 — 双方部门确认 / XÁC NHẬN CỦA HAI BỘ PHẬN

| **培训部门 / Bộ phận đào tạo** | **接收部门 / Bộ phận tiếp nhận** |
|---|---|
| 部门 / Bộ phận: **IT** | 部门 / Bộ phận: ………………………………… |
| *(部门主管签名、姓名 / Trưởng bộ phận ký, ghi rõ họ tên)* | *(部门主管签名、姓名 / Trưởng bộ phận ký, ghi rõ họ tên)* |
| <br><br><br><br> | <br><br><br><br> |
| ………………………………… | ………………………………… |
| 日期 / Ngày ……/……/…… | 日期 / Ngày ……/……/…… |

---

*BPVN-HR-PR-030 A/1*
