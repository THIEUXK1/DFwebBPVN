<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MachinesAndTanksSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable foreign key checks
        if (DB::connection()->getDriverName() !== 'sqlite') { DB::statement('SET CONSTRAINTS ALL DEFERRED'); } else { DB::statement('PRAGMA defer_foreign_keys = ON'); }

        // Clean tables
        DB::table('tanks')->delete();
        DB::table('machines')->delete();

        $lines = [
            ['code' => 'L1', 'name' => 'Máy nhuộm đai L1'],
            ['code' => 'L2', 'name' => 'Máy nhuộm đai L2'],
            ['code' => 'L3', 'name' => 'Máy nhuộm đai L3'],
            ['code' => 'L4', 'name' => 'Máy nhuộm đai L4'],
            ['code' => 'T5', 'name' => 'Máy nhuộm thùng T5'],
            ['code' => 'T6', 'name' => 'Máy nhuộm thùng T6'],
            ['code' => 'T7', 'name' => 'Máy nhuộm thùng T7'],
            ['code' => 'T8', 'name' => 'Máy nhuộm thùng T8'],
        ];

        foreach ($lines as $line) {
            $machineId = DB::table('machines')->insertGetId([
                'code' => $line['code'],
                'name' => $line['name'],
                'is_active' => true
            ]);

            // Add Tank 1A and Tank 2B for this machine
            DB::table('tanks')->insert([
                [
                    'machine_id' => $machineId,
                    'code' => $line['code'] . '-1A',
                    'name' => 'Bồn phụ 1A - ' . $line['name']
                ],
                [
                    'machine_id' => $machineId,
                    'code' => $line['code'] . '-2B',
                    'name' => 'Bồn phụ 2B - ' . $line['name']
                ]
            ]);
        }

        // Seed legacy machines VD001-VD018 (dải máy nhuộm đai thật dùng cho màn hình
        // Nhập đơn sản xuất — xác nhận qua 2 mẫu QR MES thật ngày 2026-07-18: phiếu
        // BEST PACIFIC dùng VD04, ảnh chụp MainForm VBA đang chạy quét ra VD02. Trước
        // đây seeder này chỉ tạo VD006-VD013 (dải áp quy tắc 250L) khiến các máy khác
        // không resolve được. Đồng thời seed luôn tank "1A/2B/3C/4D/FB" cho MỖI máy VD —
        // đúng danh sách formselect1.frm (Box5 quick-pick) trong VBA — vì trước đó
        // KHÔNG có tank nào cho dải VD, khiến quy tắc 250L (VD006-013 + tank 1A/2B +
        // level<250) không bao giờ kích hoạt được.
        $legacyMachines = [];
        for ($i = 1; $i <= 18; $i++) {
            $legacyMachines[] = 'VD' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
        }
        $vdTankCodes = ['1A', '2B', '3C', '4D', 'FB'];

        foreach ($legacyMachines as $code) {
            $machineId = DB::table('machines')->insertGetId([
                'code' => $code,
                'name' => 'Máy nhuộm ' . $code,
                'is_active' => true
            ]);

            $tankRows = [];
            foreach ($vdTankCodes as $tankCode) {
                $tankRows[] = [
                    'machine_id' => $machineId,
                    'code' => $tankCode,
                    'name' => "Thùng {$tankCode} - {$code}",
                ];
            }
            DB::table('tanks')->insert($tankRows);
        }

        $this->command->info('Successfully seeded machines and tanks matching water config lines.');
    }
}
