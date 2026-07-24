<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Drop old foreign keys referencing app.workstations
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['workstation_id']);
        });

        Schema::table('weighing_jobs', function (Blueprint $table) {
            $table->dropForeign(['assigned_workstation_id']);
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign(['workstation_id']);
        });

        // 2. Drop obsolete workstation tables
        Schema::dropIfExists('workstation_allowed_actions');
        Schema::dropIfExists('workstation_role_assignments');
        Schema::dropIfExists('workstation_sessions');
        Schema::dropIfExists('workstation_network_history');
        Schema::dropIfExists('device_assignments');

        // 3. Rename workstations to operation_clients
        DB::statement('ALTER TABLE workstations RENAME TO operation_clients');

        // 4. Add new columns to operation_clients
        Schema::table('operation_clients', function (Blueprint $table) {
            $table->string('status', 30)->default('ACTIVE');
            $table->string('default_capability', 100)->nullable();
            $table->boolean('kiosk_mode')->default(true);
            $table->string('kiosk_token_hash', 64)->nullable();
            $table->boolean('kiosk_token_active')->default(true);
            $table->timestampTz('kiosk_token_expires_at')->nullable();
        });

        // 5. Update app.users column
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('workstation_id', 'operation_client_id');
            $table->string('pin', 100)->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('operation_client_id')->references('id')->on('operation_clients')->onDelete('set null');
        });

        // 6. Update app.weighing_jobs column
        Schema::table('weighing_jobs', function (Blueprint $table) {
            $table->renameColumn('assigned_workstation_id', 'assigned_operation_client_id');
        });

        Schema::table('weighing_jobs', function (Blueprint $table) {
            $table->foreign('assigned_operation_client_id')->references('id')->on('operation_clients')->onDelete('set null');
        });

        // 7. Update app.devices column (remove FK workstation_id)
        Schema::table('devices', function (Blueprint $table) {
            $table->renameColumn('workstation_id', 'operation_client_id');
        });
        Schema::table('devices', function (Blueprint $table) {
            $table->foreign('operation_client_id')->references('id')->on('operation_clients')->onDelete('set null');
        });

        // 8. Create capabilities table
        Schema::create('capabilities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 50)->unique();
            $table->string('name', 200);
            $table->string('category', 30); // BUSINESS, DEVICE
            $table->timestamps();
        });

        // 9. Create operation_client_capabilities table
        Schema::create('operation_client_capabilities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('operation_client_id');
            $table->bigInteger('capability_id');
            $table->boolean('enabled')->default(true);
            $table->json('configuration_json')->nullable();
            $table->timestamps();

            $table->foreign('operation_client_id')->references('id')->on('operation_clients')->onDelete('cascade');
            $table->foreign('capability_id')->references('id')->on('capabilities')->onDelete('cascade');
            $table->unique(['operation_client_id', 'capability_id'], 'client_capability_unique');
        });

        // 10. Create operation_client_devices table
        Schema::create('operation_client_devices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('operation_client_id');
            $table->uuid('device_id');
            $table->string('device_role', 50); // PRIMARY_PRINTER, BACKUP_PRINTER, PRIMARY_SCALE, SCANNER, etc.
            $table->boolean('is_default')->default(false);
            $table->integer('priority')->default(1);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->foreign('operation_client_id')->references('id')->on('operation_clients')->onDelete('cascade');
            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
            $table->unique(['operation_client_id', 'device_id', 'device_role'], 'client_device_role_unique');
        });

        // 11. Create kiosk_sessions table
        Schema::create('kiosk_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('operation_client_id');
            $table->string('token', 80)->unique();
            $table->timestampTz('started_at')->useCurrent();
            $table->timestampTz('last_activity_at')->useCurrent();
            $table->timestampTz('expires_at');
            $table->string('status', 30)->default('ACTIVE');
            $table->string('remote_ip', 45)->nullable();
            $table->string('browser_fingerprint', 255)->nullable();
            $table->timestamps();

            $table->foreign('operation_client_id')->references('id')->on('operation_clients')->onDelete('cascade');
        });

        // 12. Modify app.audit_logs
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('actor_type', 30)->nullable();
            $table->string('actor_id', 100)->nullable();
            $table->bigInteger('kiosk_session_id')->nullable();
            $table->uuid('device_id')->nullable();
            $table->uuid('agent_id')->nullable();
            $table->string('correlation_id', 100)->nullable();
            $table->timestampTz('timestamp')->useCurrent();
        });

        // 13. Seed capabilities
        $capabilities = [
            ['code' => 'CHEMICAL_CALL', 'name' => 'Gọi hóa chất', 'category' => 'BUSINESS'],
            ['code' => 'PRODUCTION_ORDER', 'name' => 'Tạo đơn sản xuất', 'category' => 'BUSINESS'],
            ['code' => 'QR_LABEL_PRINTING', 'name' => 'Nhận đơn & In tem QR', 'category' => 'BUSINESS'],
            ['code' => 'SMALL_SCALE', 'name' => 'Trạm cân nhỏ', 'category' => 'BUSINESS'],
            ['code' => 'LARGE_SCALE', 'name' => 'Trạm cân lớn', 'category' => 'BUSINESS'],

            ['code' => 'PRINT', 'name' => 'In ấn', 'category' => 'DEVICE'],
            ['code' => 'WEIGH', 'name' => 'Cân đo', 'category' => 'DEVICE'],
            ['code' => 'SCAN_QR', 'name' => 'Quét mã QR', 'category' => 'DEVICE'],
            ['code' => 'LOCAL_AGENT', 'name' => 'Local Agent', 'category' => 'DEVICE'],
            ['code' => 'REMOTE_VIEW', 'name' => 'Xem từ xa', 'category' => 'DEVICE'],
        ];

        foreach ($capabilities as $cap) {
            DB::table('capabilities')->insert(array_merge($cap, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }

        // 14. Migrate existing workstations and set up initial client relationships
        $clients = DB::table('operation_clients')->get();
        foreach ($clients as $client) {
            // Determine client status from active boolean
            $status = (isset($client->active) && !$client->active) ? 'INACTIVE' : 'ACTIVE';
            DB::table('operation_clients')->where('id', $client->id)->update([
                'status' => $status,
                'kiosk_mode' => true,
            ]);

            // Assign business capability based on workstation_type
            $type = $client->workstation_type ?: ($client->type ?? '');
            $businessCap = null;
            $deviceCaps = ['SCAN_QR', 'LOCAL_AGENT'];

            if (in_array($type, ['DYE_WEIGHING', 'CHEMICAL_WEIGHING', 'A11_WEIGHING', 'DLG_WEIGHING'])) {
                $businessCap = 'SMALL_SCALE';
                $deviceCaps[] = 'WEIGH';
                $deviceCaps[] = 'PRINT';
            } elseif ($type === 'LABEL_PRINTING' || $type === 'PRINT_STATION') {
                $businessCap = 'QR_LABEL_PRINTING';
                $deviceCaps[] = 'PRINT';
            } elseif ($type === 'ORDER_SCAN' || $type === 'ORDER_DESK') {
                $businessCap = 'PRODUCTION_ORDER';
            } elseif ($type === 'TANK_RECEIVING' || $type === 'MACHINE_FEEDING') {
                $businessCap = 'CHEMICAL_CALL';
            }

            // Default route / screen
            $defaultRoute = $client->default_route ?: ($client->default_screen ?? null);
            if (!$defaultRoute) {
                if ($businessCap === 'SMALL_SCALE') {
                    $defaultRoute = '/weighing-station';
                } elseif ($businessCap === 'QR_LABEL_PRINTING') {
                    $defaultRoute = '/print-station';
                } elseif ($businessCap === 'PRODUCTION_ORDER') {
                    $defaultRoute = '/order-scan';
                } elseif ($businessCap === 'CHEMICAL_CALL') {
                    $defaultRoute = '/feeding-monitor';
                }
            }

            DB::table('operation_clients')->where('id', $client->id)->update([
                'default_capability' => $businessCap,
                'default_route' => $defaultRoute,
            ]);

            // Insert capabilities mappings
            if ($businessCap) {
                $capObj = DB::table('capabilities')->where('code', $businessCap)->first();
                if ($capObj) {
                    DB::table('operation_client_capabilities')->insert([
                        'operation_client_id' => $client->id,
                        'capability_id' => $capObj->id,
                        'enabled' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            foreach ($deviceCaps as $dc) {
                $capObj = DB::table('capabilities')->where('code', $dc)->first();
                if ($capObj) {
                    DB::table('operation_client_capabilities')->insert([
                        'operation_client_id' => $client->id,
                        'capability_id' => $capObj->id,
                        'enabled' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            // Migrate devices from assigned columns to operation_client_devices
            if (!empty($client->assigned_scale_device_id)) {
                $device = DB::table('devices')->where('code', $client->assigned_scale_device_id)->first();
                if ($device) {
                    DB::table('operation_client_devices')->insert([
                        'operation_client_id' => $client->id,
                        'device_id' => $device->id,
                        'device_role' => 'PRIMARY_SCALE',
                        'is_default' => true,
                        'priority' => 1,
                        'enabled' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            if (!empty($client->assigned_printer_device_id)) {
                $device = DB::table('devices')->where('code', $client->assigned_printer_device_id)->first();
                if ($device) {
                    DB::table('operation_client_devices')->insert([
                        'operation_client_id' => $client->id,
                        'device_id' => $device->id,
                        'device_role' => 'PRIMARY_PRINTER',
                        'is_default' => true,
                        'priority' => 1,
                        'enabled' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }

        // Set a default manager PIN (e.g. '1234') for all supervisors and admins
        $supervisors = DB::table('users')->get();
        foreach ($supervisors as $user) {
            DB::table('users')->where('id', $user->id)->update([
                'pin' => password_hash('1234', PASSWORD_BCRYPT)
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Simplify rollback
        Schema::dropIfExists('kiosk_sessions');
        Schema::dropIfExists('operation_client_devices');
        Schema::dropIfExists('operation_client_capabilities');
        Schema::dropIfExists('capabilities');

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['actor_type', 'actor_id', 'kiosk_session_id', 'device_id', 'agent_id', 'correlation_id', 'timestamp']);
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign(['operation_client_id']);
            $table->renameColumn('operation_client_id', 'workstation_id');
        });

        Schema::table('weighing_jobs', function (Blueprint $table) {
            $table->dropForeign(['assigned_operation_client_id']);
            $table->renameColumn('assigned_operation_client_id', 'assigned_workstation_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['operation_client_id']);
            $table->renameColumn('operation_client_id', 'workstation_id');
            $table->dropColumn('pin');
        });

        DB::statement('ALTER TABLE operation_clients RENAME TO workstations');

        // Recreate dropped tables for consistency
        Schema::create('workstation_allowed_actions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('workstation_id');
            $table->string('action_code', 100);
            $table->timestamps();
        });

        Schema::create('device_assignments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('workstation_id');
            $table->string('device_id', 100);
            $table->string('assignment_type', 50);
            $table->boolean('active')->default(true);
            $table->timestampTz('assigned_at')->useCurrent();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('workstation_id')->references('id')->on('workstations')->onDelete('set null');
        });

        Schema::table('weighing_jobs', function (Blueprint $table) {
            $table->foreign('assigned_workstation_id')->references('id')->on('workstations')->onDelete('set null');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->foreign('workstation_id')->references('id')->on('workstations')->onDelete('set null');
        });
    }
};
