<?php

namespace Database\Seeders;

use App\Models\Capability;
use App\Models\OperationClient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 2 tài khoản vận hành cho 2 trạm cân, theo mô hình "1 máy tính = 1 công đoạn" (WS-001):
 * đăng nhập xong CHỈ vào được đúng 1 màn hình của mình, không sidebar, không tự đổi trạm.
 *
 * Cơ chế khóa đã có sẵn, seeder này chỉ nối dây:
 *   users.operation_client_id  -> AuthController trả kèm `workstation` khi đăng nhập
 *   -> router/index.ts đá mọi route khác về `default_route` của trạm (tài khoản không phải ADMIN)
 *   -> AppLayout.vue ẩn hẳn sidebar + khóa nút đổi trạm (isLockedStation).
 *
 * Chạy riêng, KHÔNG gọi trong DatabaseSeeder: WorkstationsSeeder xoá sạch
 * operation_clients/devices trước khi seed lại, chạy cả bộ trên máy đang có dữ liệu là mất trạm.
 * Seeder này ngược lại — chỉ updateOrCreate, chạy lại bao nhiêu lần cũng không mất gì
 * (trừ mật khẩu 2 tài khoản dưới đây bị đặt lại về mặc định).
 *
 *   php artisan db:seed --class=ScaleOperatorUsersSeeder
 */
class ScaleOperatorUsersSeeder extends Seeder
{
    private const ACCOUNTS = [
        [
            'username' => 'cannho',
            'password' => 'cannho@123',
            'display_name' => 'Vận hành Cân nhỏ (< 6kg)',
            // Bản dựng lại workbook "4.semiauto-small scale.xlsm".
            'route' => '/weighing-station-v2',
            'workstation' => [
                'code' => 'WS-SMALL-01',
                'name' => 'Trạm Cân Nhỏ 1 (Dưới 6kg)',
                'location' => 'Khu cân nhỏ',
                'capability' => 'SMALL_SCALE',
                'kiosk_token' => 'WS-TOKEN-SMALL-01',
            ],
        ],
        [
            'username' => 'canto',
            'password' => 'canto@123',
            'display_name' => 'Vận hành Cân to (>= 6kg)',
            // Bản dựng lại workbook "5.Semiauto- lockmove SEND OVER6.xlsm" (có khối SEND OVER 6).
            'route' => '/weighing-station-large',
            'workstation' => [
                'code' => 'WS-LARGE-01',
                'name' => 'Trạm Cân Lớn 1 (Trên 6kg)',
                'location' => 'Khu cân lớn',
                'capability' => 'LARGE_SCALE',
                'kiosk_token' => 'WS-TOKEN-LARGE-01',
            ],
        ],
    ];

    public function run(): void
    {
        DB::table('roles')->updateOrInsert(
            ['code' => 'OPERATOR'],
            ['name' => 'Nhân viên vận hành']
        );
        $operatorRoleId = DB::table('roles')->where('code', 'OPERATOR')->value('id');

        foreach (self::ACCOUNTS as $acc) {
            $client = $this->ensureWorkstation($acc['workstation'], $acc['route']);

            $userId = DB::table('users')->where('username', $acc['username'])->value('id')
                ?? (string) Str::uuid();

            DB::table('users')->updateOrInsert(
                ['username' => $acc['username']],
                [
                    'id' => $userId,
                    'display_name' => $acc['display_name'],
                    'password_hash' => Hash::make($acc['password']),
                    'is_active' => true,
                    'operation_client_id' => $client->id,
                    'updated_at' => now(),
                ]
            );

            if ($operatorRoleId) {
                DB::table('user_roles')->updateOrInsert([
                    'user_id' => $userId,
                    'role_id' => $operatorRoleId,
                ]);
            }

            $this->command->info("{$acc['username']} -> {$client->code} ({$acc['route']})");
        }
    }

    /**
     * Trạm có thể chưa tồn tại: DB dev/production được dựng dần bằng đăng ký Agent theo IP chứ
     * không phải lúc nào cũng chạy WorkstationsSeeder, nên không dựa vào trạm có sẵn.
     */
    private function ensureWorkstation(array $ws, string $route): OperationClient
    {
        $client = OperationClient::updateOrCreate(
            ['code' => $ws['code']],
            [
                'name' => $ws['name'],
                'location' => $ws['location'],
                'type' => $ws['capability'],
                'workstation_type' => $ws['capability'],
                'default_capability' => $ws['capability'],
                'default_route' => $route,
                'status' => 'ACTIVE',
                'kiosk_mode' => true,
                'kiosk_token_hash' => hash('sha256', $ws['kiosk_token']),
                'kiosk_token_active' => true,
            ]
        );

        // AppLayout.vue chặn "trạm không có quyền cho màn hình này" bằng capability_codes —
        // thiếu capability là tài khoản đăng nhập xong chỉ thấy màn chặn, không thấy màn cân.
        $codes = [$ws['capability'], 'WEIGH', 'PRINT', 'SCAN_QR', 'LOCAL_AGENT'];
        foreach ($codes as $code) {
            $cap = Capability::where('code', $code)->first();
            if ($cap) {
                $client->capabilities()->syncWithoutDetaching([$cap->id => ['enabled' => true]]);
            }
        }

        return $client;
    }
}
