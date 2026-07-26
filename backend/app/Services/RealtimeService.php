<?php

namespace App\Services;

use App\Models\RealtimeEvent;
use App\Models\AlertRule;
use App\Models\Alert;
use App\Models\ProductionBatch;
use App\Models\MaterialTransport;
use App\Models\MachineDispatch;
use App\Events\RealtimeEventBroadcast;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class RealtimeService
{
    /**
     * Publish a realtime event to the transactional outbox table, và bắn qua Reverb
     * (kênh public "dashboard-events") để Dashboard.vue tự làm mới ngay thay vì phải
     * giữ 1 kết nối SSE sống mãi (xem RealtimeEventBroadcast).
     */
    public static function publish(
        string $eventType,
        string $entityType,
        string $entityId,
        ?array $payload = null,
        ?string $actorId = null,
        ?int $machineId = null,
        ?string $batchId = null
    ): RealtimeEvent {
        $event = RealtimeEvent::create([
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload' => $payload,
            'actor_id' => $actorId,
            'machine_id' => $machineId,
            'batch_id' => $batchId,
            'occurred_at' => Carbon::now(),
        ]);

        RealtimeEventBroadcast::dispatch([
            'id' => $event->id,
            'event_type' => $event->event_type,
            'entity_type' => $event->entity_type,
            'entity_id' => $event->entity_id,
            'payload' => $event->payload,
            'machine_id' => $event->machine_id,
            'batch_id' => $event->batch_id,
        ]);

        return $event;
    }

    /**
     * Trigger a new alert in the database if not duplicate/cooldown active.
     */
    public static function triggerAlert(
        string $ruleCode,
        string $message,
        ?string $batchId = null,
        ?int $machineId = null
    ): ?Alert {
        // Check if there is an active alert already open
        $existing = Alert::where('rule_code', $ruleCode)
            ->whereIn('status', ['OPEN', 'ACKNOWLEDGED'])
            ->where(function($q) use ($batchId, $machineId) {
                $q->where('batch_id', $batchId)
                  ->where('machine_id', $machineId);
            })
            ->first();

        if ($existing) {
            return null; // Duplicate warning suppressed
        }

        $rule = AlertRule::where('rule_code', $ruleCode)->first();
        $severity = $rule ? $rule->severity : 'WARNING';

        $alert = Alert::create([
            'rule_code' => $ruleCode,
            'severity' => $severity,
            'message' => $message,
            'batch_id' => $batchId,
            'machine_id' => $machineId,
            'status' => 'OPEN',
            'created_at' => Carbon::now(),
        ]);

        // Publish alert created event
        self::publish(
            'alert.created',
            'Alert',
            (string)$alert->id,
            [
                'rule_code' => $ruleCode,
                'severity' => $severity,
                'message' => $message,
                'status' => 'OPEN'
            ],
            null,
            $machineId,
            $batchId
        );

        return $alert;
    }

    /**
     * Run the automated alerting Rule Engine.
     */
    public static function checkAlertRules(): array
    {
        $triggered = [];

        // 1. Check weighing tasks start delay (WEIGH_START_DELAY)
        $startRule = AlertRule::where('rule_code', 'WEIGH_START_DELAY')->first();
        if ($startRule && $startRule->is_enabled) {
            $threshold = $startRule->threshold_seconds ?? 1800; // default 30 mins
            $overdueBatches = ProductionBatch::where('status', 'READY_TO_WEIGH')
                ->where('created_at', '<', Carbon::now()->subSeconds($threshold))
                ->get();

            foreach ($overdueBatches as $batch) {
                $msg = "Lô {$batch->legacy_batch_id} đã sẵn sàng cân hơn " . round($threshold/60) . " phút nhưng chưa được bắt đầu cân.";
                $alert = self::triggerAlert('WEIGH_START_DELAY', $msg, $batch->id, $batch->machine_id);
                if ($alert) $triggered[] = $alert;
            }
        }

        // 2. Check weighing task completion delay (WEIGH_COMP_DELAY)
        $compRule = AlertRule::where('rule_code', 'WEIGH_COMP_DELAY')->first();
        if ($compRule && $compRule->is_enabled) {
            $threshold = $compRule->threshold_seconds ?? 3600; // default 60 mins
            $stuckWeighing = ProductionBatch::where('status', 'WEIGHING')
                ->where('created_at', '<', Carbon::now()->subSeconds($threshold))
                ->get();

            foreach ($stuckWeighing as $batch) {
                $msg = "Lô {$batch->legacy_batch_id} đang cân kéo dài vượt quá " . round($threshold/60) . " phút.";
                $alert = self::triggerAlert('WEIGH_COMP_DELAY', $msg, $batch->id, $batch->machine_id);
                if ($alert) $triggered[] = $alert;
            }
        }

        // 3. Check material transport SLA breach (TRANS_SLA_BREACH)
        $transRule = AlertRule::where('rule_code', 'TRANS_SLA_BREACH')->first();
        if ($transRule && $transRule->is_enabled) {
            $transports = MaterialTransport::where('status', 'IN_TRANSIT')->get();
            foreach ($transports as $t) {
                $started = $t->started_at ? Carbon::parse($t->started_at) : Carbon::now();
                $elapsed = $started->diffInMinutes(Carbon::now());
                if ($elapsed > $t->sla_minutes) {
                    $batch = ProductionBatch::find($t->batch_id);
                    $legacyId = $batch ? $batch->legacy_batch_id : $t->batch_id;
                    $msg = "Mẻ vận chuyển nguyên liệu của Lô {$legacyId} đã vượt quá thời gian SLA quy định ({$elapsed} phút > {$t->sla_minutes} phút).";
                    $alert = self::triggerAlert('TRANS_SLA_BREACH', $msg, $t->batch_id, $batch ? $batch->machine_id : null);
                    if ($alert) $triggered[] = $alert;
                }
            }
        }

        // 4. Check locked records timeout (RECORD_LOCKED_TIMEOUT)
        $lockRule = AlertRule::where('rule_code', 'RECORD_LOCKED_TIMEOUT')->first();
        if ($lockRule && $lockRule->is_enabled) {
            $threshold = $lockRule->threshold_seconds ?? 300; // default 5 mins
            $lockedDispatches = MachineDispatch::whereNotNull('locked_at')
                ->where('locked_at', '<', Carbon::now()->subSeconds($threshold))
                ->get();

            foreach ($lockedDispatches as $d) {
                $msg = "Lệnh hàng chờ của Lô {$d->batch_id} đã bị khóa thao tác quá lâu (hơn " . round($threshold/60) . " phút).";
                $alert = self::triggerAlert('RECORD_LOCKED_TIMEOUT', $msg, $d->batch_id, null);
                if ($alert) $triggered[] = $alert;
            }
        }

        // 5. Check scale agent connection status (SCALE_AGENT_OFFLINE)
        $scaleOfflineRule = AlertRule::where('rule_code', 'SCALE_AGENT_OFFLINE')->first();
        if ($scaleOfflineRule && $scaleOfflineRule->is_enabled) {
            $threshold = $scaleOfflineRule->threshold_seconds ?? 60; // 1 min
            $workstations = ['WS-01', 'WS-02'];
            foreach ($workstations as $wsId) {
                $lastReading = Cache::get("scale_live_weight_timestamp_{$wsId}");
                $isOffline = false;
                if (!$lastReading) {
                    // Check if we have scale weight but no timestamp
                    if (!Cache::has("scale_live_weight_{$wsId}")) {
                        $isOffline = true;
                    }
                } else {
                    $diff = time() - (int)$lastReading;
                    if ($diff > $threshold) {
                        $isOffline = true;
                    }
                }

                if ($isOffline) {
                    $msg = "Trạm cân {$wsId} mất tín hiệu nhịp tim kết nối (Heartbeat Agent offline).";
                    $alert = self::triggerAlert('SCALE_AGENT_OFFLINE', $msg, null, null);
                    if ($alert) $triggered[] = $alert;
                }
            }
        }

        return $triggered;
    }
}
