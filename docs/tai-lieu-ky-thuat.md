# TÀI LIỆU KỸ THUẬT — TRIỂN KHAI & VẬN HÀNH
## Hệ thống DF Connector

| | |
|---|---|
| **Đối tượng** | Nhân viên IT tiếp quản hệ thống |
| **Phiên bản** | 1.0 — 12/08/2026 |
| **Người biên soạn** | Bùi Văn Thiều — Nhân viên IT |

> **Trước khi làm bất cứ việc gì trên máy chủ, đọc [mục 10 — Quy tắc an toàn dữ liệu](#10-quy-tắc-an-toàn-dữ-liệu).** Có những lệnh không được chạy trong bất kỳ hoàn cảnh nào.

---

## MỤC LỤC

1. [Kiến trúc và thành phần](#1-kiến-trúc-và-thành-phần)
2. [Máy chủ và cổng dịch vụ](#2-máy-chủ-và-cổng-dịch-vụ)
3. [Các dịch vụ chạy nền](#3-các-dịch-vụ-chạy-nền)
4. [Cấu hình và biến môi trường](#4-cấu-hình-và-biến-môi-trường)
5. [Quy trình deploy](#5-quy-trình-deploy)
6. [Cơ sở dữ liệu](#6-cơ-sở-dữ-liệu)
7. [Sao lưu và khôi phục](#7-sao-lưu-và-khôi-phục)
8. [Local Agent trên máy trạm](#8-local-agent-trên-máy-trạm)
9. [Tác vụ định kỳ và lệnh artisan](#9-tác-vụ-định-kỳ-và-lệnh-artisan)
10. [Quy tắc an toàn dữ liệu](#10-quy-tắc-an-toàn-dữ-liệu)
11. [Xử lý sự cố](#11-xử-lý-sự-cố)
12. [Dựng lại môi trường từ đầu](#12-dựng-lại-môi-trường-từ-đầu)
13. [Những điểm cần biết trước khi sửa](#13-những-điểm-cần-biết-trước-khi-sửa)

---

## 1. Kiến trúc và thành phần

```
Trình duyệt (máy trạm / tablet)
        │ HTTPS/HTTP + Sanctum token
        ▼
Backend API (Laravel 12, cổng 8500) ──── PostgreSQL 15+ (cổng 5433)
        │                                        ▲
        │ WebSocket (Reverb, cổng 8080)          │ chỉ đọc
        ▼                                   SQL Server BPDB (1433)
Local Agent (.NET 8, Windows Service)            VN-MES (HTTPS)
        │ Serial / PuTTY log        │ TSPL
        ▼                           ▼
   Cân điện tử                 Máy in tem TSC
```

**Nguyên tắc bắt buộc:** trình duyệt **không bao giờ** nói chuyện trực tiếp với phần cứng. Mọi thao tác đọc cân và in tem đều đi qua Local Agent.

### Công nghệ

| Lớp | Công nghệ | Phiên bản |
|---|---|---|
| Backend | PHP + Laravel | PHP ≥ 8.2, Laravel 12 |
| | Gói chính | `laravel/reverb` (WebSocket), `laravel/sanctum` (auth token), `laravel/octane`, `barryvdh/laravel-dompdf` (xuất PDF), `maatwebsite/excel` (xuất Excel) |
| Frontend | Vue 3 + Vite + TypeScript | Vite 5, Vue 3.5 |
| | Gói chính | `pinia`, `vue-router`, `vue-i18n`, `axios`, `laravel-echo` + `pusher-js`, `qrcode`, `vis-timeline` |
| Agent | .NET 8 (self-contained) | Windows Service |
| CSDL | PostgreSQL | 15+ |

### Quy mô mã nguồn

| Thành phần | Số lượng |
|---|---|
| Controller (backend) | 34 |
| Model | 64 |
| Migration | 52 |
| Lệnh artisan tự viết | 5 |
| Test tự động | 37 file |
| Màn hình frontend | 41 |
| Bộ cài Agent | 3 |

---

## 2. Máy chủ và cổng dịch vụ

### Máy chủ production

| Mục | Giá trị |
|---|---|
| Tên | **CS-SERVER** |
| Địa chỉ | **10.0.60.209** |
| Truy cập | `ssh -i ~/.ssh/df_server_key color@10.0.60.209` |
| Thư mục mã nguồn | `C:\DFwebBPVN` |
| PHP CLI | `C:\web\tools\php` |
| PostgreSQL bin | `C:\web\tools\postgresql\bin` |
| Thư mục backup | `C:\web\tools\backups` |
| Thư mục log | `C:\DFwebBPVN\tools\logs` |

### Bảng cổng

| Cổng | Dịch vụ | Ghi chú |
|---|---|---|
| **8500** | Backend API (Laravel) | Toàn bộ `/api/*`. Frontend gọi tới cổng này |
| **8501** | Máy chủ tải file cài Agent | **Tách riêng có chủ đích** — xem [mục 13.2](#132-vì-sao-tải-file-cài-agent-phải-đi-cổng-riêng) |
| **8080** | Reverb (WebSocket) | Đẩy cập nhật thời gian thực xuống màn hình |
| **5433** | PostgreSQL | **Không phải 5432** |
| **1433** | SQL Server BPDB | Chỉ đọc, không phải hệ thống của mình |
| **8770** | Local Agent — cân nhỏ | Nghe trên `127.0.0.1` của **máy trạm**, không phải server |
| **8771** | Local Agent — cân to | Như trên |
| 3001 | Vite dev server | Chỉ dùng khi phát triển |

> Cổng 8770/8771 phải khớp với hằng số `CONG_AGENT_CUC_BO` trong `frontend/src/composables/useScaleFeed.ts`. Trang web không đọc được `appsettings.json` của Agent — đây là **quy ước hai bên tự biết**, đổi một bên mà quên bên kia là màn cân mất số.

---

## 3. Các dịch vụ chạy nền

Hệ thống **không dùng Windows Service cho phần web** mà dùng **Scheduled Task** trên CS-SERVER.

### 3.1. Dịch vụ ứng dụng

| Task | Nhiệm vụ | Restart khi sửa |
|---|---|---|
| `DFWeb-Backend` | Backend API cổng 8500 | `backend/**` |
| `DFWeb-Frontend` | Phục vụ bản build `frontend/dist` | `frontend/**` |
| `DFWeb-Reverb` | WebSocket cổng 8080 | Events / broadcast |
| `DFWeb-Queue` | Xử lý hàng đợi job | Events / broadcast / job |
| `DFWeb-Downloads` | Máy chủ tĩnh cổng 8501 phục vụ file `.msi` | Khi thay bộ cài Agent |

**Khởi động lại một dịch vụ:**

```bat
schtasks /end /tn DFWeb-Backend
schtasks /run /tn DFWeb-Backend
```

**Xem trạng thái:**

```bat
schtasks /query /tn DFWeb-Backend /v /fo LIST
```

> ⚠️ **Điểm yếu cần khắc phục:** các tệp `run-backend.bat`, `run-frontend.bat`, `run-downloads.bat` mà những task này gọi **chỉ tồn tại trên CS-SERVER, không có trong kho mã nguồn**. Nếu máy chủ hỏng, phải viết lại từ đầu. **Việc đầu tiên bên tiếp quản nên làm là copy 3 tệp này vào `tools/` của repo và commit.**

### 3.2. Tác vụ định kỳ

| Task | Lịch | Chạy gì | Log |
|---|---|---|---|
| `DFWeb-Backup` | Hằng ngày **02:00** | `tools\db-backup.bat` → `db-backup.ps1` | `tools\logs\db-backup.log` |
| `DFWeb-MesSync` | **Mỗi 15 phút** | `tools\mes-sync-batch.bat` | `tools\logs\mes-sync-batch.log` |

Cả hai chạy bằng tài khoản **SYSTEM** (chạy được khi không ai đăng nhập).

---

## 4. Cấu hình và biến môi trường

### 4.1. Tệp `.env` của backend

Đường dẫn trên server: **`C:\DFwebBPVN\backend\.env`** — **không nằm trong Git**, phải sao lưu riêng.

Các nhóm cấu hình quan trọng:

| Nhóm | Khóa | Ghi chú |
|---|---|---|
| Ứng dụng | `APP_KEY` | **Mất khóa này là không giải mã được dữ liệu đã mã hóa.** Sao lưu cùng `.env` |
| CSDL | `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT=5433`, `DB_DATABASE=production_web`, `DB_USERNAME`, `DB_PASSWORD` | |
| CSDL | `DB_HOST_CANDIDATES` | Xem [mục 4.2](#42-cơ-chế-tự-dò-host-csdl) |
| WebSocket | `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT=8080` | Frontend đọc lại qua các biến `VITE_REVERB_*` |
| BPDB | `BPDB_MONITOR_*` | Dùng tài khoản **`bpdb_monitor_readonly`**, tuyệt đối không dùng tài khoản `sysadmin`/`db_owner` |
| MES | `MES_BASE_URL`, `MES_USERNAME`, `MES_PASSWORD` | Chỉ đọc. Mật khẩu để trong **nháy đơn** để `phpdotenv` không diễn giải ký tự `$` |

> **Không commit `.env` vào Git.** Mẫu tham khảo nằm ở `backend/.env.example`.

### 4.2. Cơ chế tự dò host CSDL

`app/Services/DbHostResolver.php` đọc `DB_HOST_CANDIDATES` (danh sách host cách nhau dấu phẩy), **TCP-test lần lượt** và dùng host đầu tiên kết nối được, cache 20 giây.

```
DB_HOST_CANDIDATES=127.0.0.1,10.0.60.209,192.168.250.151
```

Nhờ vậy **cùng một tệp `.env` chạy đúng ở cả hai nơi**:
- Trên CS-SERVER: `127.0.0.1:5433` kết nối được ngay → dùng loopback cho nhanh.
- Trên máy dev: không có PostgreSQL cục bộ → tự rơi xuống `10.0.60.209` qua LAN.

Không cần sửa `.env` khi chuyển máy.

### 4.3. Thông tin đăng nhập nằm ngoài repo

| Tệp | Nội dung | Mẫu |
|---|---|---|
| `C:\web\tools\.pgpass` | Mật khẩu PostgreSQL cho `pg_dump` | — |
| `C:\web\tools\mes-batch-creds.bat` | Tài khoản MES cho tác vụ đồng bộ | `tools/mes-batch-creds.example.bat` |

Cả hai **cố ý để ngoài Git** (`.gitignore` chặn `tools/mes-batch-creds.bat`). Khi dựng server mới phải tạo lại thủ công.

---

## 5. Quy trình deploy

### 5.1. Trên máy dev — kiểm tra trước khi commit

| Đã sửa gì | Chạy lệnh gì |
|---|---|
| `frontend/**` | `npm run build` trong `frontend/` |
| `backend/**/*.php` | `php -l <từng file đã đổi>` |
| `agent/**` | `dotnet build` trong `agent/` |

**Build hoặc lint lỗi thì dừng — không commit code lỗi.**

### 5.2. Commit và push

```bash
git add <đúng các file liên quan>      # không dùng git add -A mù quáng
git commit -m "mo ta ngan gon, neu ro TAI SAO thay doi"
git push origin main
```

### 5.3. Trên CS-SERVER

```bash
ssh -i ~/.ssh/df_server_key color@10.0.60.209
```

```bat
cd C:\DFwebBPVN
git -c safe.directory=C:/DFwebBPVN pull origin main
```

Nếu có **migration mới**:

```bat
cd C:\DFwebBPVN\backend
php artisan migrate --force
```

> ⚠️ **Đọc kỹ migration trước khi chạy.** Nêu rõ nó thêm/xóa cột gì, có mất dữ liệu không. Có nghi ngờ thì backup trước.

Nếu có sửa `frontend/**`:

```bat
cd C:\DFwebBPVN\frontend
npm run build
```

Khởi động lại đúng dịch vụ bị ảnh hưởng (xem bảng ở [mục 3.1](#31-dịch-vụ-ứng-dụng)) — **không restart dịch vụ không liên quan**.

### 5.4. Kiểm tra sau deploy

```bat
powershell -Command "Get-Content C:\DFwebBPVN\tools\logs\backend.log -Tail 40"
```

Dịch vụ phải khởi động sạch, không có dòng lỗi. Sau đó mở thử một màn hình thật trên trình duyệt.

---

## 6. Cơ sở dữ liệu

### 6.1. Các schema

| Schema | Vai trò | Được phép sửa? |
|---|---|---|
| `app` | Dữ liệu chuẩn hóa của ứng dụng web | Có, qua migration |
| `legacy_df_data` | Dữ liệu thô nhập nguyên trạng từ `RECORD.accdb` | ❌ **Không bao giờ** |
| `legacy_df_scale` | Dữ liệu thô nhập nguyên trạng từ database cân | ❌ **Không bao giờ** |

> Hai schema `legacy_*` là **nguồn sự thật lịch sử phục vụ đối soát**. Sửa vào đó là mất khả năng chứng minh dữ liệu web khớp với hệ thống cũ.

### 6.2. Migration

```bat
php artisan migrate --force          # chạy migration mới
php artisan migrate:status           # xem migration nào đã chạy
```

**Không dùng `migrate:fresh`, `migrate:refresh`, `migrate:reset` trên server** — chúng xóa sạch dữ liệu.

### 6.3. Seeder

| Seeder | Tác dụng | An toàn chạy lại? |
|---|---|---|
| `AdminUserSeeder` | Tạo 4 vai trò (OPERATOR, SUPERVISOR, TECHNOLOGIST, ADMIN) và tài khoản `admin` | ✅ `updateOrInsert` |
| `ScaleOperatorUsersSeeder` | Tạo 2 tài khoản vận hành `cannho` / `canto` gắn cứng trạm | ✅ nhưng **đặt lại mật khẩu về mặc định** |
| `FoundationSeeder`, `MachinesAndTanksSeeder`, `RecipesSeeder`, `AlertRulesSeeder`, `TroubleshootingKnowledgeBaseSeeder` | Dữ liệu danh mục nền | Kiểm tra trước khi chạy |
| `WorkstationsSeeder` | Danh sách trạm làm việc | ❌ **XÓA SẠCH** `operation_clients`/`devices` trước khi seed lại |

> ⚠️ **`WorkstationsSeeder` không nằm trong `DatabaseSeeder` là có lý do.** Chạy cả bộ `php artisan db:seed` trên máy đang có dữ liệu là **mất toàn bộ trạm đã cấu hình**. Muốn chạy seeder nào thì gọi đích danh:
> ```bat
> php artisan db:seed --class=ScaleOperatorUsersSeeder
> ```

---

## 7. Sao lưu và khôi phục

### 7.1. Cơ chế sao lưu

Task `DFWeb-Backup` chạy **02:00 hằng ngày**, gọi `tools\db-backup.ps1`:

1. `pg_dump -Fc -Z 6` → `C:\web\tools\backups\production_web_<yyyyMMdd_HHmmss>.dump`
2. `pg_dumpall --globals-only` → `globals_<stamp>.sql` (**roles và phân quyền cấp cluster — thiếu tệp này thì restore sang máy mới sẽ mất hết user**)
3. Kiểm tra kích thước: dump nhỏ hơn **100 KB** coi như hỏng, báo lỗi và giữ lại để điều tra
4. Dọn bản cũ: giữ **14 ngày** gần nhất; riêng bản chạy **ngày mùng 1** giữ thêm tới **6 tháng**
5. Cảnh báo khi ổ đĩa còn dưới **5 GB**

**Chạy tay:**

```powershell
Start-ScheduledTask -TaskName 'DFWeb-Backup'
Get-Content C:\DFwebBPVN\tools\logs\db-backup.log -Tail 40
```

### 7.2. Kiểm chứng bản sao lưu

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File C:\DFwebBPVN\tools\db-backup-verify.ps1
```

Script **chỉ đọc**, kiểm tra 4 điều kiện của bản dump mới nhất: còn mới (≤ 26 giờ), kích thước hợp lý, đọc được mục lục bằng `pg_restore -l`, và số bảng có dữ liệu đạt ngưỡng tối thiểu (20).

> **Nên chạy định kỳ.** Backup không kiểm chứng chỉ là backup trên giấy.

### 7.3. Khôi phục

> ⚠️ **Không restore đè lên database production.** Restore đầy đủ cần tạo/xóa database, mà `DROP DATABASE` nằm trong danh sách lệnh bị cấm.

Quy trình đúng: copy tệp `.dump` **về máy dev**, restore ở đó, đối chiếu dữ liệu, rồi mới quyết định.

```bat
REM Tạo database rỗng trên MÁY DEV
createdb -h 127.0.0.1 -p 5433 -U postgres production_web_restore

REM Khôi phục roles trước (nếu restore sang cluster mới)
psql -h 127.0.0.1 -p 5433 -U postgres -f globals_<stamp>.sql

REM Khôi phục dữ liệu
pg_restore -h 127.0.0.1 -p 5433 -U postgres -d production_web_restore --clean --if-exists production_web_<stamp>.dump
```

Khôi phục **chọn lọc một bảng** (định dạng `-Fc` cho phép):

```bat
pg_restore -l production_web_<stamp>.dump > danhmuc.txt
REM sửa danhmuc.txt, giữ lại dòng cần khôi phục
pg_restore -h 127.0.0.1 -p 5433 -U postgres -d <db> -L danhmuc.txt production_web_<stamp>.dump
```

### 7.4. Những gì backup KHÔNG bao gồm

Phải sao lưu riêng, thủ công:

- `C:\DFwebBPVN\backend\.env` (**có `APP_KEY`**)
- `C:\web\tools\.pgpass`
- `C:\web\tools\mes-batch-creds.bat`
- `run-backend.bat`, `run-frontend.bat`, `run-downloads.bat`
- Các tệp `.accdb` và workbook `.xlsm` gốc của hệ thống cũ

---

## 8. Local Agent trên máy trạm

### 8.1. Ba bộ cài

| Bộ cài | Service Windows | Cổng cục bộ | Vai trò |
|---|---|---|---|
| `DFAgentSetup-CanNho.msi` | `DFAgentSmall` | 8770 | Đọc cân nhỏ (< 6 kg) + in tem |
| `DFAgentSetup-CanTo.msi` | `DFAgentLarge` | 8771 | Đọc cân to (≥ 6 kg) + in tem |
| `DFAgentSetup-CanTo-InOut.msi` | `DFAgentLargeInOut` | — | **Chỉ** gửi mã rack sang hệ pha màu (nút OUT/IN) |

Người vận hành tải bộ cài từ menu **TẢI CÔNG CỤ** trên sidebar của hệ thống web (đi qua cổng 8501).

### 8.2. Ba điểm dễ sai

**a) Hai bộ cân nhỏ và cân to chạy song song được trên cùng một máy** — khác tên service, khác cổng. Nhưng **bắt buộc khác cổng**; trùng cổng là một bộ chiếm mất của bộ kia.

**b) Bộ IN/OUT không chạy bằng service** mà chạy trong **phiên đăng nhập của thợ**, biểu tượng ở khay hệ thống. Đăng xuất Windows là chức năng OUT/IN ngừng. Từ bản 4.7.0.0 chuột phải vào biểu tượng để xem nhật ký hoặc thoát.

**c) Nguồn đọc cân mặc định là `PUTTY_LOG`**, không phải cổng COM trực tiếp:

| Bộ | Đường dẫn log |
|---|---|
| Cân nhỏ | `D:\scale\putty_log.txt` |
| Cân to | `D:\scale\putty_log_large.txt` (dự phòng: `D:\scale\putty_log.txt`) |

> **PuTTY phải đang chạy và đã bật Session Logging ghi đúng đường dẫn trên.** Agent **không tự bật PuTTY**. PuTTY tắt = màn cân báo "MẤT TÍN HIỆU CÂN".
>
> **Không được để hai Agent trỏ chung một `LogFilePath`** — nếu không, màn cân to sẽ hiện số của cân nhỏ. Agent cân to chỉ lùi về đường dẫn dự phòng khi tệp chính không tồn tại, và mỗi lần lùi đều ghi một dòng cảnh báo trong log.

### 8.3. Đường đọc cân cục bộ

Agent mở một endpoint HTTP **chỉ nghe trên `127.0.0.1`**; trình duyệt trên chính máy đó đọc số cân thẳng từ đấy (**~70 ms**) thay vì vòng qua backend (**400–900 ms**).

- `/weight` — hỏi vòng, giữ làm dự phòng và để kiểm tra nhanh bằng `curl`
- `/weight/stream` — SSE, Agent tự đẩy khi có số mới (từ bản 4.4.0.0)

Đặt `Local:Enabled = false` trong `appsettings.json` để quay về hoàn toàn đường backend. Đường cũ **vẫn giữ nguyên** — Agent vẫn đẩy số lên backend như trước.

### 8.4. Kiểm tra Agent

```powershell
Get-Service DFAgentSmall, DFAgentLarge          # trạng thái service
Invoke-WebRequest http://127.0.0.1:8770/weight  # thử đọc số cân (cân nhỏ)
```

Service được cài với chính sách tự khởi động lại 3 lần, mỗi lần cách 5 giây khi crash.

---

## 9. Tác vụ định kỳ và lệnh artisan

Chạy trong `C:\DFwebBPVN\backend`:

| Lệnh | Tác dụng | Ghi/Đọc |
|---|---|---|
| `php artisan mes:sync-batch-completions --days=3` | Đồng bộ giờ kết thúc nhuộm thật của mẻ từ VN-MES vào `app.mes_batch_completions` | Ghi |
| `php artisan mes:sync-color-swatches [--dry-run]` | Đồng bộ mã màu + màu RGB thật từ VN-MES vào `color_swatches` | Ghi |
| `php artisan colorservice:bpdb-sync` | Một vòng: khớp task mới + cập nhật trạng thái task đã khớp | Đọc BPDB, ghi DB mình |
| `php artisan colorservice:bpdb-match-tasks [--dispatch=<id>]` | Tìm và liên kết `SUP_Tasks` bên BPDB cho dispatch mode `PROCESS` | Chỉ đọc BPDB |
| `php artisan colorservice:bpdb-health` | Kiểm tra kết nối BPDB, hiện vài task gần nhất | **Chỉ đọc** — dùng để chẩn đoán |

> Mọi truy cập BPDB đều **chỉ đọc**. Nếu thấy lệnh nào ghi vào BPDB, đó là lỗi cần báo ngay.

**Chẩn đoán nhanh khi nghi mất kết nối BPDB:**

```bat
php artisan colorservice:bpdb-health
```

---

## 10. Quy tắc an toàn dữ liệu

### 10.1. Lệnh bị cấm tuyệt đối

| Lệnh | Vì sao |
|---|---|
| `DROP DATABASE ...` | Mất toàn bộ dữ liệu sản xuất |
| `DROP SCHEMA app CASCADE;` | Như trên |
| `php artisan migrate:fresh` / `:refresh` / `:reset` | Xóa sạch bảng rồi dựng lại |
| Xóa tệp `.accdb`, `.docx` gốc hoặc tệp `.sql` trong `sql_migration/` | Mất nguồn dữ liệu lịch sử để đối soát |
| `git push --force` trên nhánh chính | Mất lịch sử mã nguồn của người khác |
| `UPDATE`/`DELETE` trực tiếp vào `legacy_df_data`, `legacy_df_scale` | Mất nguồn sự thật lịch sử |

### 10.2. Nguyên tắc bắt buộc

1. **Không xóa vật lý** dữ liệu giao dịch hoặc lịch sử. Dùng xóa mềm (`deleted_at`) và trạng thái nghiệp vụ.
2. **Không đổi cấu trúc hoặc dữ liệu CSDL production** nếu chưa có phê duyệt và kế hoạch backup cụ thể.
3. **Môi trường dev/test phải cô lập** với mạng sản xuất thật.
4. **Audit Log bất biến** cho 100% các hành động sau — nếu sửa code đụng tới chúng, phải giữ nguyên việc ghi log:
   - Phê duyệt và phát hành phiên bản công thức sản xuất
   - Override dung sai cân (ghi rõ lý do và tài khoản QA/QC phê duyệt)
   - In lại tem (reprint) và giải phóng khóa điều phối thủ công (force unlock)
   - Thay đổi kho tri thức sự cố
   
   Dữ liệu thay đổi lưu dạng JSONB ở hai cột `before_data` và `after_data`.
5. **QR sinh nội bộ** — không gọi dịch vụ ngoài như `api.qrserver.com`. Hệ thống cũ từng làm vậy và đó là một trong những lý do phải thay.

---

## 11. Xử lý sự cố

### 11.1. Bảng tra nhanh

| Hiện tượng | Kiểm tra theo thứ tự |
|---|---|
| **Toàn bộ web không vào được** | Task `DFWeb-Backend` còn chạy? → `tools\logs\backend.log` → PostgreSQL cổng 5433 còn sống? |
| **Web vào được nhưng trắng trang / lỗi tài nguyên** | Task `DFWeb-Frontend` → đã `npm run build` sau lần sửa cuối chưa? |
| **Màn hình không tự cập nhật thời gian thực** | Task `DFWeb-Reverb` và `DFWeb-Queue` → cổng 8080 |
| **Một máy trạm mất số cân, các máy khác bình thường** | Service `DFAgentSmall`/`DFAgentLarge` trên **chính máy đó** → PuTTY còn chạy và ghi log? → dây cân |
| **Tất cả máy trạm mất số cân** | Backend cổng 8500 → mạng LAN |
| **Không tải được bộ cài Agent** | Task `DFWeb-Downloads` → cổng 8501 → firewall |
| **Báo cáo thiếu dữ liệu MES** | `tools\logs\mes-sync-batch.log` → task `DFWeb-MesSync` → tệp credential còn không? |
| **Màn hình BPDB/Gantt trống** | `php artisan colorservice:bpdb-health` |
| **Backup không chạy** | `tools\logs\db-backup.log` → `Get-ScheduledTaskInfo -TaskName DFWeb-Backup` |

### 11.2. Vị trí log

| Log | Đường dẫn |
|---|---|
| Backend | `C:\DFwebBPVN\tools\logs\backend.log` |
| Laravel | `C:\DFwebBPVN\backend\storage\logs\laravel.log` |
| Backup | `C:\DFwebBPVN\tools\logs\db-backup.log` |
| Đồng bộ MES | `C:\DFwebBPVN\tools\logs\mes-sync-batch.log` |
| Đồng bộ BPDB | `C:\DFwebBPVN\tools\logs\bpdb-sync.log` |
| Agent (máy trạm) | Event Log Windows + `agent_run.log` cạnh tệp thực thi |

### 11.3. Lỗi đã gặp và cách xử lý

| Lỗi | Nguyên nhân thật | Xử lý |
|---|---|---|
| Đăng nhập trả về HTTP 500 | Thiếu bảng `personal_access_tokens` do lệch kiểu dữ liệu UUID/bigint | Chạy migration tương ứng, kiểm tra lại bằng luồng đăng nhập thật |
| Tải tệp `.msi` bị đứt giữa chừng | `php artisan serve` đơn luồng, request khác chen vào cắt kết nối | Đã tách máy chủ tải file sang cổng 8501 |
| `pg_dumpall` treo vô hạn | `.pgpass` ghi cứng tên database, mà `pg_dumpall` nối vào database `postgres` | Script đã đặt cả `PGPASSWORD` lẫn `PGPASSFILE`, và dùng cờ `-w` để không bao giờ hỏi mật khẩu |
| Máy kiosk đời cũ crash `STATUS_ILLEGAL_INSTRUCTION` | Trình duyệt cũ không hỗ trợ ES module | `vite.config.ts` đã bật `@vitejs/plugin-legacy`, build ra bundle dự phòng kèm polyfill |
| Agent đọc được cân nhưng không gửi được số nào lên | Cấu hình còn trỏ địa chỉ backend cũ `10.0.200.248` | Sửa `Backend:Url` về `10.0.60.209:8500` |

---

## 12. Dựng lại môi trường từ đầu

### 12.1. Máy dev

```bash
git clone https://github.com/THIEUXK1/DFwebBPVN.git
cd DFwebBPVN/backend
composer install
cp .env.example .env          # rồi điền cấu hình thật
php artisan key:generate
php artisan migrate
php artisan db:seed --class=AdminUserSeeder
php artisan db:seed --class=ScaleOperatorUsersSeeder
php artisan serve --port=8500

cd ../frontend
npm install
npm run dev                   # cổng 3001
```

Máy dev **không cần PostgreSQL cục bộ** — `DbHostResolver` tự rơi xuống host LAN.

### 12.2. Máy chủ mới

1. Cài PHP ≥ 8.2 (`C:\web\tools\php`), PostgreSQL 15+ (cổng **5433**), Node.js, Git
2. `git clone` vào `C:\DFwebBPVN`
3. Khôi phục `.env` từ bản sao lưu ngoài Git (**bắt buộc có `APP_KEY` cũ**)
4. `composer install --no-dev --optimize-autoloader`
5. Khôi phục CSDL: `globals_*.sql` trước, rồi `production_web_*.dump`
6. `cd frontend && npm ci && npm run build`
7. Tạo lại `C:\web\tools\.pgpass` và `C:\web\tools\mes-batch-creds.bat`
8. Đăng ký các Scheduled Task:
   ```powershell
   powershell -NoProfile -ExecutionPolicy Bypass -File C:\DFwebBPVN\tools\register-db-backup-task.ps1
   powershell -NoProfile -ExecutionPolicy Bypass -File C:\DFwebBPVN\tools\register-mes-sync-task.ps1
   ```
   > Hai script này có tham số `-WhatIfOnly` để xem trước mà chưa ghi gì.
9. Tạo lại 5 task dịch vụ ứng dụng (**hiện chưa có script — xem cảnh báo ở [mục 3.1](#31-dịch-vụ-ứng-dụng)**)
10. Mở firewall các cổng 8500, 8501, 8080, 5433
11. Kiểm tra: mở web, đăng nhập, chạy `colorservice:bpdb-health`, chạy thử backup

---

## 13. Những điểm cần biết trước khi sửa

### 13.1. Khóa "1 máy tính = 1 công đoạn"

Tài khoản vận hành gắn cứng một trạm chỉ vào được đúng màn hình của công đoạn mình; gõ tay URL màn khác sẽ bị điều hướng về. Cơ chế nằm ở `frontend/src/router/index.ts`.

**Thêm màn hình vào danh sách `MAN_PHU_TRO` là NỚI QUYỀN** — phải cân nhắc như một thay đổi bảo mật, không thêm cho tiện. Hiện chỉ có đúng một ngoại lệ: hai trạm cân được mở màn Lịch sử cân, vì nút CHECK mở nó sang tab mới.

Tài khoản **ADMIN không bị chặn**.

### 13.2. Vì sao tải file cài Agent phải đi cổng riêng

Backend chạy bằng `php artisan serve` — **đơn luồng**. Trong lúc truyền tệp `.msi` 28 MB nó không xử lý được request nào khác, và ngược lại request khác chen vào làm đứt kết nối giữa chừng. Đó là lý do có máy chủ tĩnh riêng ở cổng 8501.

**Không gộp lại chung cổng 8500.**

### 13.3. Nạp lười toàn bộ view

Mọi view trong `router/index.ts` đều dùng `() => import(...)`. Trước 02/08/2026 có 29 view import tĩnh, dồn cả ứng dụng vào một chunk ~692 KB — mở **bất kỳ** trang nào cũng phải tải xong cả 29 màn hình.

**Không import tĩnh view mới.**

### 13.4. Hai trạm cân là hai màn hình riêng, cố ý không dùng chung component

`/weighing-station-v2` (cân nhỏ) và `/weighing-station-large` (cân to) là bản dựng lại của **hai workbook VBA vật lý khác nhau**. Cân to có thêm khối SEND OVER 6. Gộp lại là sai nghiệp vụ.

### 13.5. Đối soát Golden Master

Sau khi sửa logic tính toán, phải so kết quả giữa bản Web và bản Excel VBA trên cùng tập dữ liệu đầu vào.

**Sai số cho phép của trọng lượng: ±0.000001.**

### 13.6. Bảo toàn khóa tự nhiên khi di trú

Dữ liệu import giữ khóa cũ qua `legacy_id` và số dòng nguồn qua `legacy_row_no` để đối soát. Có lỗi lệch cột đã biết ở bảng `tbl_ToSend2` và `WAITING` — phải ánh xạ thủ công từng cột, **không dùng SQL động chung**.

### 13.7. Chạy test

```bat
php artisan test
```

Bộ test cần một PostgreSQL **`production_web_test`** ở `127.0.0.1:5433` (xem `backend/.env.testing`). Máy không có PostgreSQL cục bộ sẽ fail toàn bộ ở bước kết nối — đó là lỗi môi trường, không phải lỗi code.

---

## PHỤ LỤC — Tham chiếu nhanh

### Đường dẫn quan trọng

```
CS-SERVER (10.0.60.209)
├── C:\DFwebBPVN\                    mã nguồn
│   ├── backend\.env                 cấu hình (KHÔNG có trong Git)
│   ├── backend\storage\logs\        log Laravel
│   ├── frontend\dist\               bản build frontend
│   └── tools\logs\                  log dịch vụ và tác vụ định kỳ
└── C:\web\tools\
    ├── php\                         PHP CLI
    ├── postgresql\bin\              pg_dump, pg_restore, psql
    ├── backups\                     bản sao lưu CSDL
    ├── .pgpass                      mật khẩu PostgreSQL (KHÔNG có trong Git)
    └── mes-batch-creds.bat          tài khoản MES (KHÔNG có trong Git)

MÁY TRẠM
└── D:\scale\
    ├── putty_log.txt                log cân nhỏ
    └── putty_log_large.txt          log cân to
```

### Lệnh hay dùng

```bat
REM Khởi động lại backend
schtasks /end /tn DFWeb-Backend & schtasks /run /tn DFWeb-Backend

REM Xem log backend
powershell -Command "Get-Content C:\DFwebBPVN\tools\logs\backend.log -Tail 40"

REM Kiểm tra kết nối BPDB
cd C:\DFwebBPVN\backend & php artisan colorservice:bpdb-health

REM Chạy backup ngay
powershell -Command "Start-ScheduledTask -TaskName 'DFWeb-Backup'"

REM Kiểm chứng bản backup mới nhất
powershell -NoProfile -ExecutionPolicy Bypass -File C:\DFwebBPVN\tools\db-backup-verify.ps1

REM Trạng thái Agent trên máy trạm
powershell -Command "Get-Service DFAgentSmall, DFAgentLarge"
```

### Tài liệu liên quan trong repo

| Tệp | Nội dung |
|---|---|
| `.claude/CLAUDE.md` | Chỉ dẫn phát triển và quy tắc nghiệp vụ đặc thù |
| `.claude/architecture-decisions.md` | Các quyết định kiến trúc kèm lý do |
| `.claude/target-data-model.md`, `.claude/erd-target.md` | Mô hình dữ liệu đích |
| `.claude/source-traceability.md` | Ma trận truy vết: mã VBA/Access nào tương ứng chức năng web nào |
| `.claude/vba-migration-matrix.md` | Ma trận di trú chi tiết từng macro |
| `.claude/local-agent-architecture.md` | Kiến trúc Local Agent |
| `.claude/session-log.md` | Nhật ký từng phiên phát triển |
| `docs/huong-dan-van-hanh.md` | Hướng dẫn cho người vận hành |

---

**Ghi chú phiên bản**

| Phiên bản | Ngày | Nội dung | Người sửa |
|---|---|---|---|
| 1.0 | 12/08/2026 | Ban hành lần đầu, phục vụ bàn giao hệ thống | Bùi Văn Thiều |
