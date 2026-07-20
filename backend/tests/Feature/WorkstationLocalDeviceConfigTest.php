<?php
// backend/tests/Feature/WorkstationLocalDeviceConfigTest.php
//
// "Đơn giản hóa" 2026-07-18: người vận hành tự gán cân/máy in ngay tại trạm, không
// qua Admin trước — khác hẳn OperationClientAdminController::updateConfig (role:ADMIN).

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Workstation;
use App\Models\Device;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class WorkstationLocalDeviceConfigTest extends TestCase
{
    use DatabaseTransactions;

    public function test_non_admin_user_can_assign_scale_device_to_own_workstation(): void
    {
        $user = User::factory()->create(['username' => 'local_cfg_op_' . uniqid()]);
        $ws = Workstation::create(['code' => 'WS-LOCALCFG-01', 'name' => 'Test Local Cfg', 'type' => 'SMALL_SCALE', 'active' => true]);

        $response = $this->actingAs($user)->putJson("/api/workstations/{$ws->id}/local-device-config", [
            'scale_device_id' => 'SCALE-LOCAL-01',
            'scale_com_port' => 'COM5',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.assigned_scale_device_id', 'SCALE-LOCAL-01');

        $device = Device::where('code', 'SCALE-LOCAL-01')->firstOrFail();
        $this->assertEquals('COM5', $device->configuration['com_port']);

        $this->assertDatabaseHas('app.operation_client_devices', [
            'operation_client_id' => $ws->id,
            'device_id' => $device->id,
            'device_role' => 'PRIMARY_SCALE',
            'is_default' => true,
        ]);
    }

    public function test_reassigning_scale_device_replaces_previous_primary(): void
    {
        $user = User::factory()->create(['username' => 'local_cfg_op2_' . uniqid()]);
        $ws = Workstation::create(['code' => 'WS-LOCALCFG-02', 'name' => 'Test Local Cfg 2', 'type' => 'SMALL_SCALE', 'active' => true]);

        $this->actingAs($user)->putJson("/api/workstations/{$ws->id}/local-device-config", [
            'scale_device_id' => 'SCALE-OLD',
        ])->assertStatus(200);

        $this->actingAs($user)->putJson("/api/workstations/{$ws->id}/local-device-config", [
            'scale_device_id' => 'SCALE-NEW',
        ])->assertStatus(200);

        $this->assertEquals(1, \App\Models\OperationClientDevice::where('operation_client_id', $ws->id)
            ->where('device_role', 'PRIMARY_SCALE')->count());

        $newDevice = Device::where('code', 'SCALE-NEW')->firstOrFail();
        $this->assertDatabaseHas('app.operation_client_devices', [
            'operation_client_id' => $ws->id,
            'device_id' => $newDevice->id,
            'device_role' => 'PRIMARY_SCALE',
        ]);
    }

    public function test_assigns_printer_device_with_connection_config(): void
    {
        $user = User::factory()->create(['username' => 'local_cfg_op3_' . uniqid()]);
        $ws = Workstation::create(['code' => 'WS-LOCALCFG-03', 'name' => 'Test Local Cfg 3', 'type' => 'QR_LABEL_PRINTING', 'active' => true]);

        $response = $this->actingAs($user)->putJson("/api/workstations/{$ws->id}/local-device-config", [
            'printer_device_id' => 'PRINTER-LOCAL-01',
            'printer_connection_type' => 'LAN',
            'printer_address' => '192.168.1.99',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.assigned_printer_device_id', 'PRINTER-LOCAL-01');

        $device = Device::where('code', 'PRINTER-LOCAL-01')->firstOrFail();
        $this->assertEquals('LAN', $device->configuration['connection_type']);
        $this->assertEquals('192.168.1.99', $device->configuration['address']);
    }
}
