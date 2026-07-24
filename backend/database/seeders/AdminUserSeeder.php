<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Roles
        $roles = [
            ['code' => 'OPERATOR', 'name' => 'Nhân viên vận hành'],
            ['code' => 'SUPERVISOR', 'name' => 'Giám sát ca'],
            ['code' => 'TECHNOLOGIST', 'name' => 'Kỹ thuật viên công nghệ'],
            ['code' => 'ADMIN', 'name' => 'Quản trị hệ thống']
        ];

        foreach ($roles as $r) {
            DB::table('roles')->updateOrInsert(
                ['code' => $r['code']],
                ['name' => $r['name']]
            );
        }

        // 2. Seed Admin User
        $adminId = 'a1111111-1111-1111-1111-111111111111';
        $adminUsername = 'admin';
        
        DB::table('users')->updateOrInsert(
            ['username' => $adminUsername],
            [
                'id' => $adminId,
                'display_name' => 'Quản trị hệ thống',
                'password_hash' => '$2y$10$CiGGqoiY4iYxGHbdJNsGc.OKJaC/Wm3b/yMqiTbO.gwaTmVH69hJC', // password: admin123
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        );

        // 3. Assign ADMIN role
        $role = DB::table('roles')->where('code', 'ADMIN')->first();
        if ($role) {
            DB::table('user_roles')->updateOrInsert(
                [
                    'user_id' => $adminId,
                    'role_id' => $role->id
                ]
            );
        }

        $this->command->info('Admin user and roles seeded successfully.');
    }
}
