<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AlertRule;

class AlertRulesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rules = [
            [
                'rule_code' => 'WEIGH_START_DELAY',
                'name' => 'Nhiệm vụ cân chưa bắt đầu đúng hạn',
                'severity' => 'WARNING',
                'threshold_seconds' => 1800, // 30 mins
                'is_enabled' => true,
            ],
            [
                'rule_code' => 'WEIGH_COMP_DELAY',
                'name' => 'Nhiệm vụ cân chưa hoàn thành đúng hạn',
                'severity' => 'WARNING',
                'threshold_seconds' => 3600, // 60 mins
                'is_enabled' => true,
            ],
            [
                'rule_code' => 'OUT_OF_TOLERANCE',
                'name' => 'Khối lượng cân thực tế ngoài dung sai ±1%',
                'severity' => 'CRITICAL',
                'threshold_seconds' => 0,
                'is_enabled' => true,
            ],
            [
                'rule_code' => 'TRANS_SLA_BREACH',
                'name' => 'Thời gian vận chuyển nguyên liệu vượt SLA định mức',
                'severity' => 'WARNING',
                'threshold_seconds' => 0,
                'is_enabled' => true,
            ],
            [
                'rule_code' => 'MACHINE_WAIT_MATERIAL',
                'name' => 'Máy nhuộm chờ nạp hóa chất quá hạn định mức',
                'severity' => 'CRITICAL',
                'threshold_seconds' => 1200, // 20 mins
                'is_enabled' => true,
            ],
            [
                'rule_code' => 'WATER_LEVEL_MISMATCH',
                'name' => 'Yêu cầu mở van nạp liệu khi mực nước chưa xác thực',
                'severity' => 'CRITICAL',
                'threshold_seconds' => 0,
                'is_enabled' => true,
            ],
            [
                'rule_code' => 'LABEL_PRINT_FAILED',
                'name' => 'Lệnh in tem nhãn QR của mẻ bị lỗi hoặc hủy bỏ',
                'severity' => 'WARNING',
                'threshold_seconds' => 0,
                'is_enabled' => true,
            ],
            [
                'rule_code' => 'SCALE_AGENT_OFFLINE',
                'name' => 'Scale Agent (Trạm cân) mất kết nối heartbeat',
                'severity' => 'CRITICAL',
                'threshold_seconds' => 60, // 1 min
                'is_enabled' => true,
            ],
            [
                'rule_code' => 'PRINT_AGENT_OFFLINE',
                'name' => 'Print Agent (Máy in) mất kết nối heartbeat',
                'severity' => 'WARNING',
                'threshold_seconds' => 60, // 1 min
                'is_enabled' => true,
            ],
            [
                'rule_code' => 'RECORD_LOCKED_TIMEOUT',
                'name' => 'Bản ghi hàng chờ điều phối bị khóa thao tác quá lâu',
                'severity' => 'WARNING',
                'threshold_seconds' => 300, // 5 mins
                'is_enabled' => true,
            ],
            [
                'rule_code' => 'BATCH_STUCK',
                'name' => 'Mẻ nhuộm bị đứng trạng thái bất thường',
                'severity' => 'WARNING',
                'threshold_seconds' => 7200, // 2 hours
                'is_enabled' => true,
            ],
        ];

        foreach ($rules as $r) {
            AlertRule::updateOrCreate(
                ['rule_code' => $r['rule_code']],
                $r
            );
        }
    }
}
