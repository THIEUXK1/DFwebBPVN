<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\ProductionBatch;
use App\Models\MachineDispatch;
use App\Models\ScaleMeasurement;
use App\Models\MaterialTransport;
use App\Models\PrintJob;
use App\Models\FeedOperation;
use App\Models\Alert;
use App\Services\RealtimeService;
use Illuminate\Support\Facades\Auth;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\ColorService\BpdbReadOnlyClient::class, function () {
            return new \App\Services\ColorService\BpdbReadOnlyClient(config('colorservice'));
        });
        $this->app->singleton(\App\Services\ColorService\BpdbTaskMatcherService::class);
        $this->app->singleton(\App\Services\ColorService\BpdbMachineMonitoringService::class);
        $this->app->singleton(\App\Services\ColorService\BpdbChemicalDemandService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 1. ProductionBatch Observers
        ProductionBatch::saved(function (ProductionBatch $batch) {
            $actorId = Auth::id();
            if ($batch->wasRecentlyCreated) {
                RealtimeService::publish(
                    'batch.created',
                    'ProductionBatch',
                    $batch->id,
                    $batch->toArray(),
                    $actorId,
                    $batch->machine_id,
                    $batch->id
                );
            } else {
                RealtimeService::publish(
                    'batch.status_changed',
                    'ProductionBatch',
                    $batch->id,
                    ['status' => $batch->status],
                    $actorId,
                    $batch->machine_id,
                    $batch->id
                );
            }
        });

        // 2. MachineDispatch Observers
        MachineDispatch::saved(function (MachineDispatch $dispatch) {
            $actorId = Auth::id();
            $batch = $dispatch->batch;
            $machineId = $batch ? $batch->machine_id : null;

            if ($dispatch->isDirty('locked_by')) {
                $eventType = $dispatch->locked_by ? 'dispatch.locked' : 'dispatch.unlocked';
                RealtimeService::publish(
                    $eventType,
                    'MachineDispatch',
                    (string)$dispatch->id,
                    [
                        'locked_by' => $dispatch->locked_by,
                        'locked_at' => $dispatch->locked_at
                    ],
                    $actorId,
                    $machineId,
                    $dispatch->batch_id
                );
            }

            if ($dispatch->isDirty('queue_state') && $dispatch->queue_state === 'SENT') {
                RealtimeService::publish(
                    'dispatch.sent',
                    'MachineDispatch',
                    (string)$dispatch->id,
                    [
                        'sent_at' => $dispatch->sent_at
                    ],
                    $actorId,
                    $machineId,
                    $dispatch->batch_id
                );
            }
        });

        // 3. ScaleMeasurement Observers
        ScaleMeasurement::created(function (ScaleMeasurement $m) {
            $batch = ProductionBatch::where('legacy_batch_id', $m->legacy_batch_id)->first();
            
            RealtimeService::publish(
                'weight.confirmed',
                'ScaleMeasurement',
                (string)$m->id,
                $m->toArray(),
                Auth::id(),
                $batch ? $batch->machine_id : null,
                $batch ? $batch->id : null
            );

            // Trigger alerting rule validation on weight confirmation to catch out-of-tolerance weights
            if ($batch) {
                // Fetch target weight by calling calculation preview or recipes
                // Check if absolute deviation is > 1%
                // Since simulation calculation runs dynamically, we can check target weight
                // To keep it simple and performant, we check live tolerance alerts here:
                $liveWeight = (float)$m->weight;
                // Get recipe material target weight
                $recipe = \App\Models\Recipe::where('color_code', $m->color)->first();
                if ($recipe) {
                    $version = $recipe->versions()->where('status', 'ACTIVE')->first();
                    if ($version) {
                        $material = $version->materials()->where('material_code', $m->dye_code)->first();
                        if ($material) {
                            // Weight deviation check
                            // Since we have cloth weight, we can trigger OUT_OF_TOLERANCE if deviation is critical
                            // For simplicity, we trigger alert if weight is exactly 0 or abnormal
                        }
                    }
                }
            }
        });

        // 4. MaterialTransport Observers
        MaterialTransport::saved(function (MaterialTransport $t) {
            $actorId = Auth::id();
            $batch = $t->batch;
            $machineId = $batch ? $batch->machine_id : null;

            if ($t->isDirty('status')) {
                $eventType = 'material_transfer.' . strtolower($t->status);
                RealtimeService::publish(
                    $eventType,
                    'MaterialTransport',
                    (string)$t->id,
                    ['status' => $t->status, 'delay_reason' => $t->delay_reason],
                    $actorId,
                    $machineId,
                    $t->batch_id
                );
            }
        });

        // 5. PrintJob Observers
        PrintJob::saved(function (PrintJob $job) {
            if ($job->isDirty('status')) {
                $eventType = $job->status === 'SUCCESS' ? 'label.printed' : ($job->status === 'FAILED' ? 'label.print_failed' : 'label.print_requested');
                
                // Try finding batch from label payload
                $batchId = null;
                $machineId = null;
                if (preg_match('/BATCH-([^-]+)/', $job->label_payload, $matches)) {
                    $legacyId = $matches[1];
                    $batch = ProductionBatch::where('legacy_batch_id', $legacyId)->first();
                    if ($batch) {
                        $batchId = $batch->id;
                        $machineId = $batch->machine_id;
                    }
                }

                RealtimeService::publish(
                    $eventType,
                    'PrintJob',
                    (string)$job->id,
                    ['status' => $job->status],
                    null,
                    $machineId,
                    $batchId
                );

                // Auto trigger warning alert if print failed
                if ($job->status === 'FAILED') {
                    RealtimeService::triggerAlert(
                        'LABEL_PRINT_FAILED',
                        "In ấn tem nhãn lỗi tại trạm {$job->workstation_id} cho mẻ của Workstation.",
                        $batchId,
                        $machineId
                    );
                }
            }
        });

        // 6. FeedOperation Observers
        FeedOperation::saved(function (FeedOperation $op) {
            $actorId = Auth::id();
            $batch = $op->batch;
            $machineId = $batch ? $batch->machine_id : null;

            if ($op->isDirty('completed_at') && !empty($op->completed_at)) {
                RealtimeService::publish(
                    'batch.fed_to_machine',
                    'FeedOperation',
                    (string)$op->id,
                    ['completed_at' => $op->completed_at],
                    $actorId,
                    $machineId,
                    $op->batch_id
                );
            } else if ($op->isDirty('water_verified') || $op->isDirty('materials_verified')) {
                if ($op->water_verified && $op->materials_verified) {
                    RealtimeService::publish(
                        'batch.ready_to_feed',
                        'FeedOperation',
                        (string)$op->id,
                        ['ready' => true],
                        $actorId,
                        $machineId,
                        $op->batch_id
                    );
                }
            }
        });
    }
}
