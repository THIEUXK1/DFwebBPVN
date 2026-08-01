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
        ]);

        $workstationId = $request->input('workstation_id');
        $weight = $request->input('weight');
        $isStable = $request->boolean('is_stable', false);

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
        $machineKey = self::machineKey($request->ip());
        Cache::put("scale_live_weight_{$machineKey}", $weight, 15);
        Cache::put("scale_live_weight_stable_{$machineKey}", $isStable, 15);
        Cache::put("scale_live_weight_timestamp_{$machineKey}", microtime(true), 3600);

        // Nhớ MÁY NÀY LÀ TRẠM NÀO, để trình duyệt chạy trên chính máy đó tự nhận ra trạm của
        // mình qua whoami() mà người dùng không phải chọn tay trong danh sách. TTL 12 giờ ~ trọn
        // một ca làm việc: Agent đẩy số liên tục nên mốc này được làm mới suốt ca.
        Cache::put("scale_machine_station_{$machineKey}", $workstationId, 43200);

        // Tu dong gan thiet bi Can cho tram neu chua co (2026-07-30, "cai la dung thoi"):
        // truoc day nguoi dung phai tu bam "Cau hinh can ngay" tren UI (QrScanPanel.vue)
        // truoc khi xem duoc so can, du Agent da bao cao so that roi. Agent gui duoc so
        // can that qua day la du bang chung de tu tao/gan Device — giu nguyen co che
        // Device/OperationClientDevice hien co (giong WorkstationLocalConfigController)
        // thay vi bo qua han buoc gan thiet bi.
        // Chi tra theo code (Agent luon gui Workstation:Id dang chuoi ma tram, vd
        // "WS-WEIGH-SCALE") — KHONG orWhere('id', ...) vi id la cot bigint, Postgres loi
        // ngay "invalid input syntax for type bigint" khi so sanh voi chuoi khong phai so.
        $client = OperationClient::where('code', $workstationId)->first();
        if ($client) {
            $hasScale = OperationClientDevice::where('operation_client_id', $client->id)
                ->where('device_role', 'PRIMARY_SCALE')
                ->exists();

            if (!$hasScale) {
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
        }

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Scale reading cached successfully'
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
        $code = Cache::get('scale_machine_station_'.self::machineKey($request->ip()));

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
     * Khóa cache theo MÁY (địa chỉ IP nguồn) thay vì theo mã trạm cấu hình sẵn.
     *
     * Tiền tố "machine_" để không bao giờ đụng một mã trạm thật (mã trạm theo quy ước là
     * WS-*). IPv6 chứa dấu ':' nên đổi hết ký tự không phải chữ/số thành '_' — cache theo
     * driver database lưu khóa vào cột chuỗi, dấu ':' không sai nhưng gây khó đọc khi soi log.
     */
    private static function machineKey(?string $ip): string
    {
        return 'machine_' . preg_replace('/[^A-Za-z0-9]/', '_', (string) $ip);
    }

    /**
     * Đọc trọn một bộ 3 khóa (giá trị / ổn định / thời điểm) của một nguồn cân.
     */
    private function readCacheSlot(string $key): array
    {
        $readAt = Cache::get("scale_live_weight_timestamp_{$key}");

        return [
            'weight' => Cache::get("scale_live_weight_{$key}"),
            'stable' => Cache::get("scale_live_weight_stable_{$key}", false),
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
        if ($request->boolean('local')) {
            $local = $this->readCacheSlot(self::machineKey($request->ip()));

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
