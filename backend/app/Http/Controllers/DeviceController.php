<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\OperationClient;
use App\Models\Device;
use App\Models\OperationClientDevice;
use App\Models\Workstation;

class DeviceController extends Controller
{
    /**
     * List all devices.
     */
    public function index()
    {
        return response()->json([
            'status' => 'SUCCESS',
            'data' => \App\Models\Device::all()
        ]);
    }

    /**
     * Store live scale reading from Local Scale Agent.
     */
    public function storeReading(Request $request)
    {
        $request->validate([
            'workstation_id' => 'required|string|max:50',
            'weight' => 'required|numeric',
            // Không bắt buộc để tương thích ngược với Agent cũ chưa cập nhật (nếu có) —
            // mặc định false (KHÔNG mặc định true, tránh lặp lại bug hard-code true cũ).
            'is_stable' => 'nullable|boolean',
            // Loại cân của bản Agent đang gửi (2026-08-03). Agent cũ không gửi ⇒ SMALL.
            'scale_kind' => 'nullable|string|in:SMALL,LARGE',
        ]);

        $workstationId = $request->input('workstation_id');
        $weight = $request->input('weight');
        $isStable = $request->boolean('is_stable', false);
        $scaleKind = self::scaleKind($request->input('scale_kind'));

        // PB-2 (đã sửa 2026-07-17): cache kèm is_stable thật từ ScaleReader.StableFilter
        // (Agent), thay vì chỉ cache weight rồi để frontend tự hard-code true.
        Cache::put("scale_live_weight_{$workstationId}", $weight, 15);
        Cache::put("scale_live_weight_stable_{$workstationId}", $isStable, 15);
        // microtime thay cho time(): getReading dùng mốc này để tính tuổi số đọc, mà ngưỡng
        // "còn tươi" của luồng chốt bì tính bằng vài trăm ms — độ phân giải 1 giây là vô dụng.
        // RealtimeService (SCALE_AGENT_OFFLINE) ép (int) về giây nên không bị ảnh hưởng.
        Cache::put("scale_live_weight_timestamp_{$workstationId}", microtime(true), 3600);

        // Ghi THÊM một bản theo IP máy gửi (2026-08-01). Lý do: bộ cài MSI đóng cứng
        // Workstation:Id = "WS-WEIGH-SCALE" cho MỌI máy, nên nếu 2 trạm cân cùng chạy thì cả
        // hai ghi đè lên đúng một khóa và mỗi màn hình đọc phải số cân của trạm kia. Trước đây
        // muốn tránh phải sửa tay appsettings.json trên từng máy sau khi cài.
        //
        // Agent và trình duyệt của thợ chạy trên CÙNG một máy trạm (mô hình "1 máy tính = 1
        // công đoạn"), và cả hai gọi thẳng http://<server>:8500 không qua proxy nào, nên IP
        // nguồn hai bên nhìn thấy là một — đủ để ghép cặp mà không cần cấu hình gì.
        //
        // Ghi thêm chứ KHÔNG thay thế: khóa theo mã trạm vẫn giữ nguyên cho Dashboard (xem
        // nhiều trạm cùng lúc) và cho các trạm đã cấu hình mã riêng.
        //
        // Khóa tách theo LOẠI CÂN (2026-08-03): từ khi có 2 bộ cài Agent độc lập, một máy có
        // thể chạy đồng thời cân nhỏ và cân to. Cùng IP mà chung khóa thì hai Agent ghi đè số
        // của nhau và màn hình Cân to hiện số của cân nhỏ — đúng loại lỗi "cân sai mà vẫn tô
        // xanh ĐẠT" nguy hiểm nhất. Cân nhỏ giữ khóa CŨ không hậu tố nên máy đang chạy không
        // mất số trong lúc deploy.
        $machineKey = self::machineKey($request->ip(), $scaleKind);
        Cache::put("scale_live_weight_{$machineKey}", $weight, 15);
        Cache::put("scale_live_weight_stable_{$machineKey}", $isStable, 15);
        Cache::put("scale_live_weight_timestamp_{$machineKey}", microtime(true), 3600);

        // Nhớ MÁY NÀY LÀ TRẠM NÀO, để trình duyệt chạy trên chính máy đó tự nhận ra trạm của
        // mình qua whoami() mà người dùng không phải chọn tay trong danh sách. TTL 12 giờ ~ trọn
        // một ca làm việc: Agent đẩy số liên tục nên mốc này được làm mới suốt ca.
        Cache::put("scale_machine_station_{$machineKey}", $workstationId, 43200);

        $this->ensureScaleDeviceAttached($workstationId);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Scale reading cached successfully'
        ]);
    }

    /**
     * "Máy này có Agent cân, nó là trạm X" — Agent báo danh, KHÔNG kèm số cân nào.
     *
     * Trước 2026-08-04, thứ DUY NHẤT tạo ra bản ghi trạm (middleware AgentAuth) và cặp
     * MÁY -> TRẠM dùng cho whoami() là chính request đẩy số cân. Hệ quả: máy nào Agent chưa đọc
     * được cân thì KHÔNG TỒN TẠI với hệ thống — không có trạm, whoami trả null, màn hình cân
     * không tự nhận được trạm của mình. Lỗi lộ ra ở bộ cài CÂN TO: nó đọc file log riêng
     * `putty_log_large.txt`, mà máy trạm ngoài xưởng chỉ có một PuTTY ghi vào `putty_log.txt`
     * (đường dẫn chuẩn cũ) — nên cân to không bao giờ tự hiện ra trạm, còn cân nhỏ thì chạy tốt.
     *
     * Tách hẳn hai chuyện: "máy này là trạm nào" (endpoint này, chỉ cần Agent sống) và "cân đang
     * đọc được gì" (storeReading + has_reading/age_ms). Trạm mất tín hiệu cân phải hiện ra kèm
     * cảnh báo MẤT TÍN HIỆU CÂN, chứ không được biến mất khỏi hệ thống.
     */
    public function hello(Request $request)
    {
        $request->validate([
            'workstation_id' => 'required|string|max:50',
            'scale_kind' => 'nullable|string|in:SMALL,LARGE',
        ]);

        $workstationId = $request->input('workstation_id');
        $machineKey = self::machineKey($request->ip(), self::scaleKind($request->input('scale_kind')));

        // CHỈ ghi cặp máy -> trạm. Tuyệt đối không đụng tới 3 khóa số cân: báo danh không phải
        // bằng chứng cân đang sống, ghi vào đó là dựng lại đúng cái "0.0 giả" mà TV6 đã gỡ bỏ.
        Cache::put("scale_machine_station_{$machineKey}", $workstationId, 43200);

        $this->ensureScaleDeviceAttached($workstationId);

        // Bản ghi trạm do middleware AgentAuth tạo/lấy ra (nhánh tự đăng ký theo role +
        // scale_kind) — trả về để Agent ghi log thấy ngay mình được nhận dạng thành trạm nào.
        $workstation = $request->attributes->get('agent_workstation');

        return response()->json([
            'status' => 'SUCCESS',
            'workstation_id' => $workstationId,
            'workstation_code' => $workstation?->code,
        ]);
    }

    /**
     * Tu dong gan thiet bi Can cho tram neu chua co (2026-07-30, "cai la dung thoi"):
     * truoc day nguoi dung phai tu bam "Cau hinh can ngay" tren UI (QrScanPanel.vue)
     * truoc khi xem duoc so can, du Agent da bao cao so that roi. Agent chay tren may tram
     * la du bang chung de tu tao/gan Device — giu nguyen co che Device/OperationClientDevice
     * hien co (giong WorkstationLocalConfigController) thay vi bo qua han buoc gan thiet bi.
     *
     * Chi tra theo code (Agent luon gui Workstation:Id dang chuoi ma tram, vd "WS-WEIGH-SCALE")
     * — KHONG orWhere('id', ...) vi id la cot bigint, Postgres loi ngay "invalid input syntax
     * for type bigint" khi so sanh voi chuoi khong phai so.
     */
    private function ensureScaleDeviceAttached(string $workstationId): void
    {
        $client = OperationClient::where('code', $workstationId)->first();
        if (! $client) {
            return;
        }

        $hasScale = OperationClientDevice::where('operation_client_id', $client->id)
            ->where('device_role', 'PRIMARY_SCALE')
            ->exists();

        if ($hasScale) {
            return;
        }

        $device = Device::firstOrCreate(
            ['code' => "SCALE_{$workstationId}"],
            ['device_type' => 'SCALE', 'status' => 'ACTIVE']
        );

        OperationClientDevice::create([
            'operation_client_id' => $client->id,
            'device_id' => $device->id,
            'device_role' => 'PRIMARY_SCALE',
            'is_default' => true,
            'priority' => 1,
            'enabled' => true,
        ]);
    }

    /**
     * Quy đổi tham số của frontend về ĐÚNG khóa cache mà Agent đã ghi.
     *
     * Agent luôn gửi `workstation_id` là MÃ trạm (`Workstation:Id` trong appsettings.json, vd
     * "WS-WEIGH-SCALE") nên cache nằm ở `scale_live_weight_WS-WEIGH-SCALE`. Nhưng frontend gọi
     * `/api/devices/readings/{id}` với `Workstation.id` là **khóa chính dạng SỐ** — tra vào
     * `scale_live_weight_5`, một khóa KHÔNG BAO GIỜ tồn tại. Hai bên chưa từng gặp nhau.
     *
     * Lỗi này bị che khuất suốt vì trước đây getReading trả mặc định `weight = 0.0` khi không
     * có cache: màn hình hiện "0.00" y như một cái cân rỗng đang chờ đặt vật tư, không ai nghi
     * ngờ gì. Chỉ tới khi thêm cờ `has_reading` (2026-08-01) nó mới lộ ra thành cảnh báo
     * "MẤT TÍN HIỆU CÂN".
     *
     * Sửa ở đây (thay vì sửa từng chỗ gọi bên frontend) để mọi màn hình cùng hưởng —
     * WeighingStation V1, V2 và Dashboard đều đang truyền id số.
     */
    private function resolveReadingKey(string $workstationId): string
    {
        // Không phải số ⇒ đã là mã trạm, dùng thẳng. Bắt buộc kiểm tra trước khi so với cột
        // `id` (bigint): Postgres lỗi ngay "invalid input syntax for type bigint" nếu đem so
        // với chuỗi không phải số.
        if (! ctype_digit($workstationId)) {
            return $workstationId;
        }

        $code = OperationClient::where('id', (int) $workstationId)->value('code');

        // Không tra được thì trả nguyên tham số — giữ hành vi cũ, không nuốt lỗi thành mã khác.
        return $code ?: $workstationId;
    }

    /**
     * "Máy tôi đang ngồi là trạm nào?" — trả về trạm mà Agent chạy trên CHÍNH máy này đã tự
     * đăng ký, nhận diện qua IP nguồn (Agent và trình duyệt cùng máy, cùng gọi thẳng backend).
     *
     * Nhờ endpoint này, cài Agent xong là máy tự có trạm riêng và màn hình cân tự chọn đúng
     * trạm đó — không phải khai tay trong Quản lý Workstation, cũng không phải chọn trong danh
     * sách mỗi lần mở máy. Trạm được tạo bởi middleware AgentAuth ngay ở request đẩy số cân
     * đầu tiên (xem AgentAuth::handle, nhánh tự đăng ký theo `role`).
     */
    public function whoami(Request $request)
    {
        // ?kind=LARGE — "trạm CÂN TO của máy này là trạm nào?". Máy chạy cả 2 Agent có 2 trạm
        // cùng lúc, nên phải hỏi rõ đang cần trạm nào. Thiếu tham số ⇒ SMALL, giữ nguyên hành
        // vi cũ cho các màn hình chưa cập nhật.
        $code = Cache::get('scale_machine_station_'.self::machineKey($request->ip(), self::scaleKind($request->query('kind'))));

        // Chưa có Agent nào đẩy số từ máy này (chưa cài, chưa chạy, hoặc PuTTY chưa bật) —
        // trả 200 kèm data=null để client tự lui về cách chọn trạm tay, KHÔNG trả 404 vì đây
        // là trạng thái bình thường chứ không phải lỗi.
        // Dùng model Workstation (không phải OperationClient) để response có đúng các trường
        // $appends mà frontend cần — nhất là `capability_codes`, thứ AppLayout/router dựa vào để
        // quyết định trạm có được vào màn hình cân hay không.
        $station = $code ? Workstation::where('code', $code)->first() : null;

        return response()->json([
            'status' => 'SUCCESS',
            'data' => $station,
        ]);
    }

    /**
     * Chuẩn hóa loại cân về đúng 2 giá trị SMALL/LARGE.
     *
     * Mặc định SMALL cho MỌI đầu vào lạ hoặc thiếu — Agent cũ (chưa có trường `scale_kind`)
     * và các màn hình chưa truyền `?kind=` đều phải rơi vào đúng nhánh cũ, nếu không thì bản
     * deploy này làm mất số cân của toàn bộ trạm đang chạy.
     */
    private static function scaleKind($raw): string
    {
        return strtoupper(trim((string) $raw)) === 'LARGE' ? 'LARGE' : 'SMALL';
    }

    /**
     * Khóa cache theo MÁY (địa chỉ IP nguồn) thay vì theo mã trạm cấu hình sẵn, tách riêng
     * theo LOẠI CÂN vì một máy có thể chạy cùng lúc 2 Agent (cân nhỏ + cân to).
     *
     * Tiền tố "machine_" để không bao giờ đụng một mã trạm thật (mã trạm theo quy ước là
     * WS-*). IPv6 chứa dấu ':' nên đổi hết ký tự không phải chữ/số thành '_' — cache theo
     * driver database lưu khóa vào cột chuỗi, dấu ':' không sai nhưng gây khó đọc khi soi log.
     *
     * SMALL KHÔNG có hậu tố — giữ nguyên khóa cũ (`machine_10_0_0_55`) để các trạm cân nhỏ
     * đang chạy không đứt số ở thời điểm deploy, và để không phải xóa cache khi lên bản mới.
     */
    private static function machineKey(?string $ip, string $scaleKind = 'SMALL'): string
    {
        $key = 'machine_' . preg_replace('/[^A-Za-z0-9]/', '_', (string) $ip);

        return $scaleKind === 'LARGE' ? $key . '_LARGE' : $key;
    }

    /**
     * Đọc trọn một bộ 3 khóa (giá trị / ổn định / thời điểm) của một nguồn cân.
     */
    private function readCacheSlot(string $key): array
    {
        // MỘT truy vấn cho cả 3 khoá thay vì 3: `DatabaseStore::many()` gom thành một
        // `WHERE key IN (...)`. Đáng làm vì màn hình cân gọi endpoint này 5 lần/giây và mỗi lần
        // đọc HAI bộ (theo mã trạm + theo IP máy) — 6 truy vấn xuống còn 2.
        //
        // Không đổi định dạng lưu, nên KHÔNG cần deploy đồng bộ với Agent: Agent vẫn ghi 3 khoá
        // rời như cũ.
        $v = Cache::many([
            "scale_live_weight_{$key}",
            "scale_live_weight_stable_{$key}",
            "scale_live_weight_timestamp_{$key}",
        ]);

        $readAt = $v["scale_live_weight_timestamp_{$key}"];

        return [
            'weight' => $v["scale_live_weight_{$key}"],
            // `many()` trả null cho khoá trống chứ không nhận giá trị mặc định như `get()` —
            // phải tự bù `false`, nếu không `(bool) null` lọt ra ngoài thành `is_stable` sai kiểu.
            'stable' => $v["scale_live_weight_stable_{$key}"] ?? false,
            'read_at' => $readAt === null ? null : (float) $readAt,
        ];
    }

    /**
     * Get live scale reading for Web App.
     *
     * Trả kèm TUỔI của số đọc (`age_ms`): Agent đã được vá để không đẩy "0.0 giả" khi đọc
     * lỗi/dữ liệu rác (TV6, xem Worker.cs), nhưng chính chỗ này lại bịa ra đúng con số đó khi
     * cache trống — client không có cách nào phân biệt "cân đang rỗng thật" với "mất tín hiệu
     * cân". Timestamp vốn đã được ghi sẵn ở storeReading nhưng chưa từng được đọc ra.
     *
     * Tuổi số đọc cũng là điều kiện bắt buộc cho luồng chốt bì tự động của /weighing-station-v2:
     * bì được lấy từ lần đọc ổn định ĐẦU TIÊN sau khi bấm NEXT, nên một số đọc cũ còn nằm trong
     * cache (TTL 15s) sẽ bị chốt nhầm làm bì nếu client không biết nó cũ.
     */
    public function getReading(Request $request, $workstationId)
    {
        $slot = $this->readCacheSlot($this->resolveReadingKey($workstationId));
        $source = 'WORKSTATION';

        // ?local=1 — "cho tôi cái cân ở ngay máy tôi đang ngồi". Chỉ các màn hình cân bật cờ
        // này; Dashboard KHÔNG bật vì nó xem số cân của nhiều trạm từ xa cùng lúc, ghép theo
        // IP ở đó sẽ trả nhầm cân của chính máy đang mở Dashboard cho mọi trạm.
        //
        // Máy đang ngồi THẮNG TUYỆT ĐỐI, không so xem bên nào tươi hơn. Đã thử cách so tươi và
        // nó SAI: mọi Agent đều ghi chung một khóa theo mã trạm nên khóa chung gần như luôn vừa
        // được máy khác cập nhật, tươi hơn khóa theo IP của chính máy này — kết quả là máy A vẫn
        // đọc phải số của máy B, đúng cái lỗi cần chữa (đo được bằng probe 2026-08-01).
        //
        // Mốc nhận diện là `read_at` (TTL 1 giờ) chứ không phải `weight` (TTL 15 giây): máy nào
        // đã từng báo số trong 1 giờ qua thì coi là máy CÓ CÂN. Nhờ vậy khi Agent/PuTTY chết,
        // màn hình báo thẳng "MẤT TÍN HIỆU CÂN" thay vì âm thầm tụt về hiển thị cân của trạm
        // khác — cân sai mà vẫn tô xanh ĐẠT là hỏng nguy hiểm hơn nhiều so với mất số.
        // ?kind=LARGE — lấy cái cân TO ở máy này, không phải cân nhỏ. Thiếu tham số ⇒ SMALL:
        // /weighing-station (V1) và Dashboard không truyền gì và phải chạy y như cũ.
        if ($request->boolean('local')) {
            $local = $this->readCacheSlot(self::machineKey($request->ip(), self::scaleKind($request->query('kind'))));

            if ($local['read_at'] !== null) {
                $slot = $local;
                $source = 'MACHINE';
            }
        }

        // timestamp có TTL 3600s (dài hơn hẳn TTL 15s của giá trị cân) nên vẫn còn ở đây khi
        // số cân đã hết hạn — đủ để nói "cân im bao lâu rồi", thay vì chỉ nói "không có số".
        $ageMs = $slot['read_at'] === null
            ? null
            : max(0, (int) round((microtime(true) - $slot['read_at']) * 1000));

        return response()->json([
            'status' => 'SUCCESS',
            'workstation_id' => $workstationId,
            // Giữ 0.0 cho tương thích ngược với /weighing-station (V1) đang chạy sản xuất —
            // client mới phải dùng has_reading/age_ms để biết số này có thật hay không.
            'weight' => (float) ($slot['weight'] ?? 0.0),
            'is_stable' => (bool) $slot['stable'],
            'has_reading' => $slot['weight'] !== null,
            'age_ms' => $ageMs,
            // Để soi khi đi hiện trường: số này đang lấy từ cân ở chính máy đang mở màn hình
            // (MACHINE) hay từ mã trạm cấu hình sẵn (WORKSTATION).
            'source' => $source,
        ]);
    }
}
