<?php
// backend/tests/Feature/WorkstationListEndpointTest.php
//
// Regression test cho bug thật 2026-07-18: GET /api/workstations trả 500
// "Call to undefined method App\Models\Workstation::getWorkstationTypeAttribute()"
// vì Workstation::$appends liệt kê 'workstation_type'/'type' — 2 CỘT THẬT trên bảng,
// không phải virtual attribute, nên không có accessor tương ứng. Người dùng phát hiện
// qua triệu chứng "danh sách trạm trống" trên giao diện chọn trạm (AppLayout.vue) —
// lỗi 500 bị nuốt im lặng ở fetchWorkstations() (chỉ console.error), UI chỉ thấy rỗng.

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Workstation;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class WorkstationListEndpointTest extends TestCase
{
    use DatabaseTransactions;

    public function test_workstations_list_endpoint_returns_200_not_500(): void
    {
        $user = User::factory()->create(['username' => 'ws_list_test_' . uniqid()]);
        Workstation::create(['code' => 'WS-LIST-TEST-01', 'name' => 'Test', 'type' => 'SMALL_SCALE', 'workstation_type' => 'SMALL_SCALE', 'active' => true]);

        $response = $this->actingAs($user)->getJson('/api/workstations');

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'SUCCESS');

        $found = collect($response->json('data'))->firstWhere('code', 'WS-LIST-TEST-01');
        $this->assertNotNull($found, 'Trạm vừa tạo phải xuất hiện trong danh sách (không bị 500 nuốt mất)');
        $this->assertEquals('SMALL_SCALE', $found['workstation_type']);
        $this->assertEquals('SMALL_SCALE', $found['type']);
    }
}
