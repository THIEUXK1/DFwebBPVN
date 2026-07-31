<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Device;
use App\Models\OperationClient;
use App\Models\Capability;
use App\Models\KioskSession;
use App\Models\WeighingJob;
use App\Models\WeighingJobItem;
use App\Models\ProductionBatch;
use App\Models\MaterialLabel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KioskOperationTest extends TestCase
{
    use DatabaseTransactions;

    private OperationClient $client;
    private User $supervisor;
    private User $admin;
    private string $kioskToken;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup roles and users
        $opRole = Role::firstOrCreate(['code' => 'OPERATOR'], ['name' => 'Operator']);
        $svRole = Role::firstOrCreate(['code' => 'SUPERVISOR'], ['name' => 'Supervisor']);
        $adminRole = Role::firstOrCreate(['code' => 'ADMIN'], ['name' => 'Admin']);

        $this->supervisor = new User();
        $this->supervisor->id = (string) Str::uuid();
        $this->supervisor->username = 'sv_test_pin';
        $this->supervisor->display_name = 'Supervisor Tester';
        $this->supervisor->password_hash = password_hash('password123', PASSWORD_BCRYPT);
        $this->supervisor->pin = password_hash('4321', PASSWORD_BCRYPT);
        $this->supervisor->is_active = true;
        $this->supervisor->save();
        $this->supervisor->roles()->attach($svRole->id);

        $this->admin = new User();
        $this->admin->id = (string) Str::uuid();
        $this->admin->username = 'admin_test_kiosk';
        $this->admin->display_name = 'Admin Tester';
        $this->admin->password_hash = password_hash('password123', PASSWORD_BCRYPT);
        $this->admin->is_active = true;
        $this->admin->save();
        $this->admin->roles()->attach($adminRole->id);

        // 2. Setup capabilities
        $smallScaleCap = Capability::firstOrCreate(['code' => 'SMALL_SCALE'], ['name' => 'Trạm Cân Nhỏ', 'category' => 'BUSINESS', 'enabled' => true]);
        $weighCap = Capability::firstOrCreate(['code' => 'WEIGH'], ['name' => 'Cân Đo', 'category' => 'DEVICE', 'enabled' => true]);
        $printCap = Capability::firstOrCreate(['code' => 'PRINT'], ['name' => 'In Ấn', 'category' => 'DEVICE', 'enabled' => true]);

        // 3. Setup physical operation client
        $this->client = new OperationClient();
        $this->client->code = 'CLIENT-TEST-01';
        $this->client->name = 'Test Client Station';
        $this->client->type = 'SMALL_SCALE';
        $this->client->workstation_type = 'SMALL_SCALE';
        $this->client->location = 'Khu Test';
        $this->client->status = 'ACTIVE';
        $this->client->kiosk_token_hash = hash('sha256', 'plain_test_token_123');
        $this->client->kiosk_token_active = true;
        $this->client->kiosk_token_expires_at = now()->addDays(7);
        $this->client->save();

        $this->kioskToken = 'plain_test_token_123';

        // Bind capabilities
        $this->client->capabilities()->attach([$smallScaleCap->id, $weighCap->id, $printCap->id], ['enabled' => true]);

        // 4. Setup Devices and bind to client
        $scale = Device::firstOrCreate(['code' => 'SCALE_TEST_01'], [
            'device_type' => 'SCALE',
            'status' => 'ONLINE',
            'connection_settings' => ['port' => 'COM3']
        ]);
        $printer = Device::firstOrCreate(['code' => 'PRINTER_TEST_01'], [
            'device_type' => 'PRINTER',
            'status' => 'ONLINE',
            'connection_settings' => ['ip' => '192.168.1.50']
        ]);

        $this->client->devices()->attach($scale->id, [
            'device_role' => 'PRIMARY_SCALE',
            'is_default' => true,
            'priority' => 1,
            'enabled' => true
        ]);
        $this->client->devices()->attach($printer->id, [
            'device_role' => 'PRIMARY_PRINTER',
            'is_default' => true,
            'priority' => 1,
            'enabled' => true
        ]);
    }

    public function test_kiosk_session_initialization_succeeds_with_valid_token(): void
    {
        $response = $this->postJson('/api/kiosk/session', [
            'client_code' => $this->client->code,
            'kiosk_token' => $this->kioskToken,
            'browser_fingerprint' => 'PHPUnit Test Agent'
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'SUCCESS');
        $response->assertJsonStructure(['session_token', 'client']);
        
        $sessionToken = $response->json('session_token');
        $this->assertNotEmpty($sessionToken);

        $this->assertDatabaseHas('app.kiosk_sessions', [
            'operation_client_id' => $this->client->id,
            'token' => $sessionToken,
            'status' => 'ACTIVE'
        ]);
    }

    public function test_kiosk_session_initialization_fails_with_invalid_token(): void
    {
        $response = $this->postJson('/api/kiosk/session', [
            'client_code' => $this->client->code,
            'kiosk_token' => 'invalid_plain_token',
            'browser_fingerprint' => 'PHPUnit Test Agent'
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('status', 'ERROR');
    }

    public function test_kiosk_mode_saves_out_of_tolerance_weight_and_labels_it_rejected(): void
    {
        // 1. Establish session
        $sessionRes = $this->postJson('/api/kiosk/session', [
            'client_code' => $this->client->code,
            'kiosk_token' => $this->kioskToken,
            'browser_fingerprint' => 'PHPUnit Test Agent'
        ]);
        $sessionToken = $sessionRes->json('session_token');

        // 2. Create test batch & weighing job
        $batch = new ProductionBatch();
        $batch->id = (string) Str::uuid();
        $batch->legacy_batch_id = 'BATCH-K-01';
        $batch->color = 'RED';
        $batch->product_code = 'P01';
        $batch->cloth_weight = 500.0;
        $batch->status = 'PLAN';
        $batch->save();

        $job = new WeighingJob();
        $job->id = (string) Str::uuid();
        $job->production_batch_id = $batch->id;
        $job->job_type = 'CHEMICAL';
        $job->workstation_type = 'SMALL_SCALE';
        $job->status = 'PENDING';
        $job->save();

        DB::table('app.materials')->insert([
            'code' => 'MAT01',
            'name' => 'Test Material 01',
            'type' => 'CHEMICAL',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $item = new WeighingJobItem();
        $item->id = (string) Str::uuid();
        $item->weighing_job_id = $job->id;
        $item->sequence_no = 1;
        $item->material_code = 'MAT01';
        $item->planned_weight = 1000.0;
        $item->tolerance_minus = 10.0;
        $item->tolerance_plus = 10.0;
        $item->status = 'PENDING';
        $item->save();

        $headers = [
            'Authorization' => 'Bearer ' . $sessionToken,
            'X-Kiosk-Session-Token' => $sessionToken
        ];

        // 1000g target, actual 1200g (ngoài dung sai) — từ 2026-07-30 KHÔNG còn bị chặn:
        // port đúng VBA btnSave_Click, mọi lần cân đều lưu được, hệ thống chỉ gắn nhãn
        // ĐẠT/KHÔNG ĐẠT. Không còn PIN Giám sát, không còn 422/403.
        $weighRes = $this->postJson("/api/weighing-jobs/items/{$item->id}/weigh", [
            'weight' => 1200.0,
            'scale_device_id' => 'SCALE_TEST_01',
            'stable' => true,
        ], $headers);

        $weighRes->assertStatus(200);
        $weighRes->assertJsonPath('status', 'SUCCESS');
        $weighRes->assertJsonPath('data.item.process_status', 'REJECTED');

        $item->refresh();
        $this->assertEquals('COMPLETED', $item->status);
        $this->assertEquals(1200.0, $item->actual_weight);
        $this->assertEquals('REJECTED', $item->process_status);
    }
}
