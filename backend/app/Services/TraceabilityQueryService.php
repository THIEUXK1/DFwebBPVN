<?php
// backend/app/Services/TraceabilityQueryService.php

namespace App\Services;

use App\Models\ProductionBatch;
use App\Models\MachineDispatch;
use App\Models\WeighingJob;
use App\Models\CorrelationLink;
use App\Models\AuditLog;
use App\Models\PrintJob;
use App\Models\ChemicalCallRequest;
use Exception;

class TraceabilityQueryService
{
    /**
     * Retrieves full end-to-end traceability data for a batch UUID or composite business key.
     */
    public function getTraceabilityReport(string $batchId): array
    {
        $batch = ProductionBatch::with(['machine', 'tank'])->find($batchId);
        if (!$batch) {
            // Try by legacy batch ID
            $batch = ProductionBatch::with(['machine', 'tank'])
                ->where('legacy_batch_id', $batchId)
                ->first();
            
            if (!$batch) {
                throw new Exception("BATCH_NOT_FOUND");
            }
        }

        $report = [
            'batch_info' => [
                'id' => $batch->id,
                'legacy_batch_id' => $batch->legacy_batch_id,
                'color' => $batch->color,
                'product_code' => $batch->product_code,
                'cloth_weight' => $batch->cloth_weight,
                'machine' => $batch->machine ? $batch->machine->code : null,
                'tank' => $batch->tank ? $batch->tank->code : null,
                'level' => $batch->level_code,
                'status' => $batch->status,
            ],
            'steps' => [],
        ];

        // Step 1: Chemical Calls (linked via machine and channel number / time window)
        $chemicalCalls = ChemicalCallRequest::with('channel')
            ->where('machine_id', $batch->machine_id)
            ->where('requested_at', '>=', $batch->created_at ? $batch->created_at->subHours(24) : now()->subHours(24))
            ->get()
            ->map(function ($cc) {
                return [
                    'channel_no' => $cc->channel ? $cc->channel->channel_number : null,
                    'chemical' => $cc->channel ? $cc->channel->chemical_code : null,
                    'status' => $cc->status,
                    'requested_at' => $cc->requested_at,
                    'confirmed_at' => $cc->confirmed_at,
                ];
            });
        
        $report['steps']['1_chemical_call'] = $chemicalCalls;

        // Step 2: Production Batch Details (Recipe assignment)
        $report['steps']['2_production_order'] = [
            'created_at' => $batch->created_at,
            'status' => $batch->status,
        ];

        // Step 3: Dispatch & Lock Info
        $dispatches = MachineDispatch::with('routingDecision')
            ->where('batch_id', $batch->id)
            ->get()
            ->map(function ($disp) {
                return [
                    'id' => $disp->id,
                    'queue_state' => $disp->queue_state,
                    'confirm_1' => $disp->confirm_1,
                    'confirm_2' => $disp->confirm_2,
                    'confirmed_at_1' => $disp->confirmed_at_1,
                    'confirmed_at_2' => $disp->confirmed_at_2,
                    'is_sent' => $disp->is_sent,
                    'sent_at' => $disp->sent_at,
                    'scale_checked' => $disp->scale_checked,
                    'routing_decision' => $disp->routingDecision ? [
                        'mode' => $disp->routingDecision->mode,
                        'route' => $disp->routingDecision->route,
                        'matched_rule' => $disp->routingDecision->matched_rule,
                        'needs_manual_review' => $disp->routingDecision->needs_manual_review,
                    ] : null,
                ];
            });
        
        $report['steps']['3_dispatch'] = $dispatches;

        // Step 4: Printing Jobs & Attempts
        $dispatchIds = $dispatches->pluck('id')->toArray();
        $printJobs = PrintJob::with('attempts')
            ->whereIn('dispatch_id', $dispatchIds)
            ->get()
            ->map(function ($job) {
                return [
                    'id' => $job->id,
                    'status' => $job->status,
                    'created_at' => $job->created_at,
                    'attempts' => $job->attempts->map(function ($att) {
                        return [
                            'attempt_no' => $att->attempt_no,
                            'status' => $att->status,
                            'started_at' => $att->started_at,
                            'finished_at' => $att->finished_at,
                            'error' => $att->error_detail,
                        ];
                    }),
                ];
            });
        
        $report['steps']['4_print_job'] = $printJobs;

        // Step 5: Weighing Results & Samples
        $weighingJobs = WeighingJob::with(['items.results.stableReading', 'items.samples'])
            ->where('production_batch_id', $batch->id)
            ->get()
            ->map(function ($job) {
                return [
                    'id' => $job->id,
                    'job_type' => $job->job_type,
                    'status' => $job->status,
                    'completed_at' => $job->completed_at,
                    'items' => $job->items->map(function ($item) {
                        return [
                            'material' => $item->material_code,
                            'planned_weight' => $item->planned_weight,
                            'actual_weight' => $item->actual_weight,
                            'status' => $item->status,
                            'result' => $item->results->map(function ($res) {
                                return [
                                    'value' => $res->final_value,
                                    'tolerance_status' => $res->tolerance_status,
                                    'is_override' => $res->is_override,
                                    'override_reason' => $res->override_reason,
                                    'posted_at' => $res->posted_at,
                                ];
                            }),
                            'samples_count' => $item->samples->count(),
                        ];
                    }),
                ];
            });
            
        $report['steps']['5_weighing'] = $weighingJobs;

        // Step 6: Soft Correlation Matches (Dispatch <=> Weighing correlation)
        $correlations = CorrelationLink::whereIn('dispatch_id', $dispatchIds)
            ->get()
            ->map(function ($link) {
                return [
                    'weighing_job_id' => $link->weighing_job_id,
                    'match_method' => $link->match_method,
                    'confidence' => $link->confidence,
                    'status' => $link->status,
                    'matched_on' => $link->matched_on,
                ];
            });
            
        $report['steps']['6_correlation'] = $correlations;

        // Step 7: System Audit Logs for this Batch
        $auditLogs = AuditLog::with('user')
            ->where('entity_type', 'production_batches')
            ->where('entity_id', $batch->id)
            ->orWhere(function ($query) use ($dispatchIds) {
                $query->where('entity_type', 'machine_dispatches')
                      ->whereIn('entity_id', $dispatchIds);
            })
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($log) {
                return [
                    'action' => $log->action,
                    'user' => $log->user ? $log->user->name : 'SYSTEM',
                    'created_at' => $log->created_at,
                    'after_data' => $log->after_data,
                ];
            });

        $report['steps']['7_audit'] = $auditLogs;

        return $report;
    }
}
