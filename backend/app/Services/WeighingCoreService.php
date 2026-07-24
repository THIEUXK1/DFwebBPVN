<?php
// backend/app/Services/WeighingCoreService.php

namespace App\Services;

use App\Models\WeighingJobItem;
use App\Models\WeighingSample;
use App\Models\WeighingResult;
use App\Models\WeighingEvent;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Exception;

class WeighingCoreService
{
    /**
     * Cleans raw weight string from scale COM port.
     * Takes the LAST numeric token as per VBA ExtractLastNumber.
     */
    public function cleanWeight(string $raw): ?float
    {
        // Extract all numeric tokens (including signs and decimals)
        preg_match_all('/[-+]?\d*\.?\d+/', $raw, $matches);
        if (!empty($matches[0])) {
            // Take the last numeric value in the string
            return (float) end($matches[0]);
        }
        return null;
    }

    /**
     * Checks if weight has stabilized.
     * Stable if the last N string readings are identical.
     */
    public function checkStability(array $rawReadings, int $requiredConsecutive = 2): bool
    {
        if (count($rawReadings) < $requiredConsecutive) {
            return false;
        }

        $slice = array_slice($rawReadings, -$requiredConsecutive);
        $first = (string) $slice[0];
        
        foreach ($slice as $reading) {
            if ((string) $reading !== $first) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Validates if a weight is within the planned tolerance range.
     */
    public function checkTolerance(float $actual, float $planned, float $minus, float $plus): string
    {
        $minAllowed = $planned - $minus;
        $maxAllowed = $planned + $plus;

        if ($actual >= $minAllowed && $actual <= $maxAllowed) {
            return 'ACCEPTED';
        }
        return 'REJECTED';
    }

    /**
     * Processes a new raw sample from the Agent.
     */
    public function addSample(string $jobItemId, string $deviceId, int $sequenceNo, string $rawValue, ?string $deviceTimestamp = null): WeighingSample
    {
        $cleaned = $this->cleanWeight($rawValue);
        
        // Fetch previous samples to check stability
        $previous = WeighingSample::where('job_item_id', $jobItemId)
            ->where('device_id', $deviceId)
            ->orderBy('sequence_no', 'asc')
            ->pluck('raw_value')
            ->toArray();
            
        $previous[] = $rawValue;
        $isStable = $this->checkStability($previous, 2);

        $sample = WeighingSample::create([
            'job_item_id' => $jobItemId,
            'device_id' => $deviceId,
            'sequence_no' => $sequenceNo,
            'device_timestamp' => $deviceTimestamp ? datetime($deviceTimestamp) : null,
            'agent_timestamp' => now(),
            'server_received_at' => now(),
            'raw_value' => $rawValue,
            'cleaned_value' => $cleaned,
            'unit' => 'g',
            'is_stable' => $isStable,
            'quality_code' => $cleaned !== null ? 'OK' : 'GARBAGE',
            'scale_algorithm_version' => 'v1.0',
        ]);

        if ($isStable) {
            WeighingEvent::create([
                'job_item_id' => $jobItemId,
                'event_type' => 'WEIGH_STABLE_REACHED',
                'occurred_at' => now(),
            ]);
        }

        return $sample;
    }

    /**
     * Saves the final weighing result.
     */
    public function postResult(string $jobItemId, float $value, ?string $stableSampleId = null): WeighingResult
    {
        $item = WeighingJobItem::findOrFail($jobItemId);
        $tolerance = $this->checkTolerance($value, $item->planned_weight, $item->tolerance_minus, $item->tolerance_plus);

        $result = DB::transaction(function () use ($item, $stableSampleId, $value, $tolerance) {
            $res = WeighingResult::create([
                'job_item_id' => $item->id,
                'stable_reading_id' => $stableSampleId,
                'final_value' => $value,
                'tolerance_status' => $tolerance,
                'is_override' => false,
                'posted_at' => now(),
                'policy_version' => 'v1.0',
            ]);

            $item->actual_weight = $value;
            $item->status = $tolerance === 'ACCEPTED' ? 'COMPLETED' : 'REJECTED';
            $item->completed_at = now();
            $item->save();

            WeighingEvent::create([
                'job_item_id' => $item->id,
                'event_type' => $tolerance === 'ACCEPTED' ? 'WEIGH_ACCEPTED' : 'WEIGH_REJECTED',
                'occurred_at' => now(),
            ]);

            return $res;
        });

        return $result;
    }

    /**
     * Overrides a rejected weight result.
     */
    public function overrideResult(string $jobItemId, string $reason, string $userId): WeighingResult
    {
        $item = WeighingJobItem::findOrFail($jobItemId);
        $result = WeighingResult::where('job_item_id', $jobItemId)->orderBy('created_at', 'desc')->first();
        if (!$result) {
            throw new Exception("NO_RESULT_TO_OVERRIDE");
        }

        return DB::transaction(function () use ($item, $result, $reason, $userId) {
            $result->is_override = true;
            $result->override_reason = $reason;
            $result->override_by_user_id = $userId;
            $result->save();

            $item->status = 'COMPLETED'; // override forces completion
            $item->save();

            WeighingEvent::create([
                'job_item_id' => $item->id,
                'event_type' => 'WEIGH_OVERRIDDEN',
                'actor_user_id' => $userId,
                'occurred_at' => now(),
            ]);

            AuditLog::create([
                'user_id' => $userId,
                'action' => 'WEIGH_OVERRIDDEN',
                'entity_type' => 'weighing_job_items',
                'entity_id' => $item->id,
                'after_data' => [
                    'final_value' => $result->final_value,
                    'reason' => $reason,
                ]
            ]);

            return $result;
        });
    }
}
