<?php

namespace App\Http\Controllers;

use App\Models\PrintJob;
use App\Models\ProductionBatch;
use App\Models\Recipe;
use App\Models\AuditLog;
use App\Services\FormulaCalculationService;
use App\Services\PrintJobEventService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PrintJobController extends Controller
{
    protected FormulaCalculationService $calculationService;
    protected PrintJobEventService $eventService;

    public function __construct(FormulaCalculationService $calculationService, PrintJobEventService $eventService)
    {
        $this->calculationService = $calculationService;
        $this->eventService = $eventService;
    }

    /**
     * Create a new print job for a production batch with dynamic label size.
     */
    public function store(Request $request)
    {
        $request->validate([
            'batch_id' => 'required|exists:production_batches,id',
            'workstation_id' => 'required|string|max:50',
            'width' => 'sometimes|integer|min:20|max:150',
            'height' => 'sometimes|integer|min:20|max:150',
            'printer_connection_type' => 'sometimes|string|in:USB,LAN',
            'printer_address' => 'sometimes|string|max:100',
        ]);

        $batchId = $request->input('batch_id');
        $workstationId = $request->input('workstation_id');
        $width = $request->input('width', 40); // default 40mm
        $height = $request->input('height', 30); // default 30mm
        $connType = $request->input('printer_connection_type', 'USB');
        $printerAddr = $request->input('printer_address', 'TSC TE200');

        $batch = ProductionBatch::with(['machine', 'tank'])->findOrFail($batchId);

        // Calculate water volume for the batch
        $recipe = Recipe::where('color_code', $batch->color)->first();
        $waterVolume = 0.0;
        $materialsText = "";

        if ($recipe && $recipe->latestVersion) {
            $processCode = $this->calculationService->getProcessCode($batch->color);
            $isWhite = strtoupper(substr($batch->color, 0, 1)) === 'W';
            
            // Map machine line or default to L2
            $machineLine = 'L2';
            if ($batch->machine) {
                $code = strtoupper($batch->machine->code);
                if (str_starts_with($code, 'L1')) $machineLine = 'L1';
                elseif (str_starts_with($code, 'L2')) $machineLine = 'L2';
                elseif (str_starts_with($code, 'L3')) $machineLine = 'L3';
                elseif (str_starts_with($code, 'L4')) $machineLine = 'L4';
                elseif (str_starts_with($code, 'T5')) $machineLine = 'T5';
                elseif (str_starts_with($code, 'T6')) $machineLine = 'T6';
                elseif (str_starts_with($code, 'T7')) $machineLine = 'T7';
                elseif (str_starts_with($code, 'T8')) $machineLine = 'T8';
            }

            // Assume default cloth weight 6.5kg if not provided (mocking)
            $clothWeight = 6.5; 

            $waterVolume = $this->calculationService->calculateWater(
                $clothWeight,
                $machineLine,
                $processCode,
                $isWhite
            );

            // Generate text summary of ingredients
            $mats = [];
            foreach ($recipe->latestVersion->materials as $idx => $m) {
                if ($idx >= 3) break; // Limit to first 3 materials for label space
                $targetWeight = $this->calculationService->getPrecisionRoundedWeight($waterVolume, (float)$m->concentration);
                $mats[] = "{$m->material_code}: {$targetWeight}g";
            }
            $materialsText = implode(", ", $mats);
        }

        // Generate TSPL commands
        $qrData = "LOT:{$batch->legacy_batch_id}|COLOR:{$batch->color}|WATER:{$waterVolume}L";
        
        $tspl = "SIZE {$width} mm, {$height} mm\r\n";
        $tspl .= "GAP 2 mm, 0 mm\r\n";
        $tspl .= "DIRECTION 1,0\r\n";
        $tspl .= "REFERENCE 0,0\r\n";
        $tspl .= "CLS\r\n";
        
        // Dynamic positioning based on label size
        $tspl .= "TEXT 15,15,\"3\",0,1,1,\"LOT: {$batch->legacy_batch_id}\"\r\n";
        $tspl .= "TEXT 15,45,\"3\",0,1,1,\"MAU: {$batch->color}\"\r\n";
        $tspl .= "TEXT 15,75,\"3\",0,1,1,\"HANG: {$batch->product_code}\"\r\n";
        $tspl .= "TEXT 15,105,\"3\",0,1,1,\"NUOC: {$waterVolume}L\"\r\n";
        
        if (!empty($materialsText)) {
            $tspl .= "TEXT 15,135,\"2\",0,1,1,\"{$materialsText}\"\r\n";
        }
        
        // Put QR Code on the right side
        $qrX = $width * 8 - 100; // 8 dots per mm (203 dpi)
        if ($qrX < 150) $qrX = 150;
        $tspl .= "QRCODE {$qrX},15,H,4,A,0,\"{$qrData}\"\r\n";
        $tspl .= "PRINT 1,1\r\n";

        // Create PrintJob
        $job = PrintJob::create([
            'workstation_id' => $workstationId,
            'label_payload' => $tspl,
            'printer_connection_type' => $connType,
            'printer_address' => $printerAddr,
            'status' => 'PENDING'
        ]);

        // Đường tạo PrintJob độc lập với ConfirmDispatchService (nhãn cân, không phải QR
        // dispatch) — vẫn phải ghi JOB_CREATED/PRINT_REQUESTED để tier B đầy đủ cho MỌI
        // print job, không riêng luồng đơn sản xuất.
        $this->eventService->log($job->id, 'JOB_CREATED', [
            'station_id' => $workstationId,
            'printer_name' => $printerAddr,
        ]);
        $this->eventService->log($job->id, 'PRINT_REQUESTED', [
            'station_id' => $workstationId,
            'printer_name' => $printerAddr,
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Lệnh in đã được chuyển sang hàng chờ (PENDING)',
            'data' => $job
        ], 201);
    }

    /**
     * Hủy 1 lệnh in CHƯA in (status=PENDING) — Agent chỉ lấy job PENDING
     * (AgentJobsController::getJobs), nên chuyển sang CANCELLED là đủ để Agent không
     * bao giờ in job này nữa, không cần xóa. Yêu cầu 2026-07-18: hoàn thiện vòng đời
     * PrintJobEvent với event CANCELLED — trước đây không có thao tác nào tạo ra event
     * này.
     */
    public function cancel(Request $request, $id)
    {
        $request->validate([
            'reason' => 'sometimes|nullable|string|max:255',
        ]);

        $job = PrintJob::findOrFail($id);

        if ($job->status !== 'PENDING') {
            return response()->json([
                'status' => 'ERROR',
                'message' => "Chỉ có thể hủy lệnh in đang chờ (PENDING) — lệnh này hiện đang ở trạng thái {$job->status}.",
            ], 422);
        }

        $beforeStatus = $job->status;
        $job->status = 'CANCELLED';
        $job->processed_at = Carbon::now();
        $job->save();

        $this->eventService->log($job->id, 'CANCELLED', [
            'dispatch_id' => $job->dispatch_id,
            'station_id' => $job->workstation_id,
            'printer_name' => $job->printer_address,
            'correlation_id' => $job->correlation_id,
            'error_message' => $request->input('reason'),
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'PRINT_JOB_CANCELLED',
            'entity_type' => 'print_jobs',
            'entity_id' => $job->id,
            'before_data' => ['status' => $beforeStatus],
            'after_data' => ['status' => 'CANCELLED', 'reason' => $request->input('reason')],
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Đã hủy lệnh in.',
            'data' => $job,
        ]);
    }
}
