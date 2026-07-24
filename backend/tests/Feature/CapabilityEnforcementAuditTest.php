<?php
// backend/tests/Feature/CapabilityEnforcementAuditTest.php
//
// Audit độc lập kiến trúc Operations Client — Capability — Device (2026-07-17).
// Test này CHỨNG MINH bằng thực nghiệm (không suy diễn từ đọc code) rằng một
// Operations Client chỉ được cấp capability SMALL_SCALE vẫn gọi thành công các
// API thuộc capability khác (PRINT/QR_LABEL_PRINTING) vì nhiều route chỉ được
// bảo vệ bởi KioskAuthenticationMiddleware (chỉ xác thực "có phiên hợp lệ"),
// KHÔNG có middleware workstation.guard:<ACTION> kiểm tra ĐÚNG capability.
//
// Đây là bằng chứng cho phát hiện P0 trong audit kiến trúc — xem báo cáo audit
// gửi kèm phiên làm việc 2026-07-17.

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\OperationClient;
use App\Models\Capability;
use App\Models\ProductionBatch;
use App\Models\Machine;
use App\Models\Tank;
use App\Models\MachineDispatch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;

class CapabilityEnforcementAuditTest extends TestCase
{
    use DatabaseTransactions;

    private function makeKioskClient(string $code, array $capabilityCodes): array
    {
        foreach ($capabilityCodes as $cc) {
            Capability::firstOrCreate(['code' => $cc], ['name' => $cc, 'category' => 'BUSINESS']);
        }

        $client = new OperationClient();
        $client->code = $code;
        $client->name = 'Audit Client ' . $code;
        $client->type = $capabilityCodes[0];
        $client->workstation_type = $capabilityCodes[0];
        $client->location = 'Audit';
        $client->status = 'ACTIVE';
        $plainToken = 'audit_token_' . Str::random(20);
        $client->kiosk_token_hash = hash('sha256', $plainToken);
        $client->kiosk_token_active = true;
        $client->kiosk_token_expires_at = now()->addDays(7);
        $client->save();

        $capIds = Capability::whereIn('code', $capabilityCodes)->pluck('id')->toArray();
        $client->capabilities()->attach($capIds, ['enabled' => true]);

        $session = $this->postJson('/api/kiosk/session', [
            'client_code' => $code,
            'kiosk_token' => $plainToken,
        ]);
        $session->assertStatus(200);

        return [$client, $session->json('session_token')];
    }

    /**
     * P0: client CHỈ có capability SMALL_SCALE — theo đúng yêu cầu audit mục 7
     * ("gọi API print phải bị từ chối") — nhưng POST /print-jobs hiện KHÔNG có
     * middleware workstation.guard nào, nên request vẫn được xử lý (không phải
     * 403 THIẾU CAPABILITY như kỳ vọng).
     */
    public function test_small_scale_only_client_can_still_call_print_jobs_api_no_capability_gate(): void
    {
        [$client, $sessionToken] = $this->makeKioskClient('AUDIT-SMALLSCALE-01', ['SMALL_SCALE', 'WEIGH']);

        $machine = Machine::create(['code' => 'VD-AUDIT', 'name' => 'Audit Machine']);
        $batch = ProductionBatch::create([
            'legacy_batch_id' => 'AUDIT-' . uniqid(),
            'color' => 'BLK',
            'product_code' => 'PAUDIT',
            'machine_id' => $machine->id,
            'status' => 'APPROVED',
        ]);

        $headers = [
            'Authorization' => 'Bearer ' . $sessionToken,
            'X-Kiosk-Session-Token' => $sessionToken,
        ];

        $response = $this->postJson('/api/print-jobs', [
            'batch_id' => $batch->id,
            'workstation_id' => $client->code,
        ], $headers);

        // GHI NHẬN HÀNH VI THẬT (không phải hành vi mong muốn): request được backend
        // xử lý bình thường (không 403) dù client không có capability PRINT/QR_LABEL_PRINTING.
        // Nếu dòng assert dưới đây FAIL trong tương lai (tức trả về 403), nghĩa là ai đó đã
        // thêm workstation.guard cho route này — hãy cập nhật lại test + đóng finding P0 tương ứng.
        $this->assertNotEquals(403, $response->status(),
            'GHI CHÚ: nếu route /print-jobs đã được thêm capability gate, đây là tín hiệu TỐT — ' .
            'cập nhật test này để assert 403 thay vì assertNotEquals, và đóng finding P0 trong audit.'
        );
    }

    /**
     * P0: tương tự, client chỉ có SMALL_SCALE vẫn confirm được machine-dispatch
     * (thuộc luồng QR_LABEL_PRINTING) vì /machine-dispatches/{id}/confirm không có
     * workstation.guard.
     */
    public function test_small_scale_only_client_can_still_confirm_dispatch_no_capability_gate(): void
    {
        [$client, $sessionToken] = $this->makeKioskClient('AUDIT-SMALLSCALE-02', ['SMALL_SCALE', 'WEIGH']);

        $machine = Machine::create(['code' => 'VD-AUDIT2', 'name' => 'Audit Machine 2']);
        $tank = Tank::create(['code' => '1A', 'name' => 'Tank 1A Audit']);
        $batch = ProductionBatch::create([
            'legacy_batch_id' => 'AUDIT2-' . uniqid(),
            'color' => 'RED',
            'product_code' => 'PAUDIT2',
            'machine_id' => $machine->id,
            'tank_id' => $tank->id,
            'level_code' => '100',
            'status' => 'APPROVED',
        ]);
        $dispatch = MachineDispatch::create([
            'batch_id' => $batch->id,
            'queue_state' => 'WAITING',
            'source_table' => 'tbl_ToSend2',
            'legacy_id' => rand(100, 999),
            'legacy_row_no' => rand(1000, 9999),
        ]);

        $headers = [
            'Authorization' => 'Bearer ' . $sessionToken,
            'X-Kiosk-Session-Token' => $sessionToken,
        ];

        $response = $this->postJson("/api/machine-dispatches/{$dispatch->id}/confirm", [
            'idempotency_key' => 'audit_' . Str::uuid(),
            'workstation_id' => $client->code,
        ], $headers);

        $this->assertNotEquals(403, $response->status(),
            'GHI CHÚ: nếu route confirm đã được thêm capability gate, cập nhật test này thành assert 403.'
        );
    }

    /**
     * Kịch bản 6 (bắt buộc): Kiosk session KHÔNG được gọi được API Admin.
     * Xác nhận CheckRole (role:ADMIN) chặn đúng vì KioskAuthenticationMiddleware không
     * gọi Auth::login() cho phiên kiosk-only, nên $request->user() = null tại CheckRole.
     */
    public function test_kiosk_session_cannot_access_admin_api(): void
    {
        [, $sessionToken] = $this->makeKioskClient('AUDIT-KIOSK-ADMIN-01', ['SMALL_SCALE']);

        $headers = [
            'Authorization' => 'Bearer ' . $sessionToken,
            'X-Kiosk-Session-Token' => $sessionToken,
        ];

        $response = $this->getJson('/api/admin/workstations', $headers);

        $response->assertStatus(401);
    }

    /**
     * P1: kiosk_token_hash / registration_token_hash không được có trong $hidden của
     * OperationClient — bằng chứng: GET /api/admin/workstations (dùng bởi WorkstationAdmin.vue,
     * poll 5s/lần) trả thẳng $client->toArray() không lọc field, rò rỉ hash token ra frontend.
     */
    /**
     * A-02 (audit 2026-07-17) — ĐÃ SỬA 2026-07-18: thêm $hidden vào OperationClient model.
     * Test này trước đây xác nhận hành vi RÒ RỈ thật (kiosk_token_hash có mặt trong JSON);
     * nay đổi thành regression test xác nhận đã ẩn đúng.
     */
    public function test_admin_workstations_list_does_not_leak_token_hashes_to_frontend(): void
    {
        $admin = User::factory()->create(['username' => 'admin_leak_test']);
        $adminRole = Role::firstOrCreate(['code' => 'ADMIN'], ['name' => 'Admin']);
        $admin->roles()->attach($adminRole->id);

        [$client] = $this->makeKioskClient('AUDIT-LEAK-01', ['SMALL_SCALE']);

        $response = $this->actingAs($admin)->getJson('/api/admin/workstations');
        $response->assertStatus(200);

        $found = collect($response->json('data'))->firstWhere('code', 'AUDIT-LEAK-01');
        $this->assertNotNull($found);

        $this->assertArrayNotHasKey('kiosk_token_hash', $found);
        $this->assertArrayNotHasKey('registration_token_hash', $found);
    }
}
