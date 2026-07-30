<?php

namespace App\Http\Controllers;

use App\Models\PrintJob;
use App\Models\PrintAttempt;
use App\Models\OperationClient;
use App\Models\Device;
use App\Models\OperationClientDevice;
use App\Services\PrintJobEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AgentJobsController extends Controller
{
    public function __construct(protected PrintJobEventService $eventService)
    {
    }

    /**
     * Local Agent báo cáo danh sách máy in đã cài trên chính máy tính đó (Windows
     * Print Spooler) + máy in mặc định hệ thống — để màn hình Print Station cho
     * người vận hành CHỌN từ danh sách thật thay vì gõ tay tên máy in (dễ gõ sai,
     * khiến RawPrinterHelper.OpenPrinter thất bại âm thầm). Lưu vào cột
     * app.operation_clients.configuration (jsonb, có sẵn, chưa dùng cho việc gì khác).
     */
    public function reportPrinters(Request $request, $workstationId)
    {
        $request->validate([
            'printers' => 'required|array',
            'printers.*' => 'string',
            'default_printer' => 'sometimes|nullable|string',
        ]);

        $client = OperationClient::where('code', $workstationId)->first();
        if (!$client) {
            return response()->json(['status' => 'ERROR', 'message' => 'Workstation not found'], 404);
        }

        $config = $client->configuration ?? [];
        $config['available_printers'] = $request->input('printers');
        $config['default_printer'] = $request->input('default_printer');
        $config['printers_reported_at'] = now()->toIso8601String();
        $client->configuration = $config;
        $client->save();

        // Tu dong gan may in chinh (PRIMARY_PRINTER) neu tram chua co (2026-07-30,
        // "cai la dung thoi") — truoc day chi ghi vao cot configuration (dung cho
        // /print-station qua resolvedPrinter), hoan toan tach biet voi
        // assigned_printer_device_id (dung cho /weighing-station qua QrScanPanel.vue),
        // khien /weighing-station van bao "chua gan may in chinh" du Agent da bao cao
        // may in that. Ap dung dung co che da co san o
        // WorkstationLocalConfigController::updateDeviceConfig (nut cau hinh thu cong),
        // chi tu dong hoa buoc gan.
        $hasPrinter = OperationClientDevice::where('operation_client_id', $client->id)
            ->where('device_role', 'PRIMARY_PRINTER')
            ->exists();

        $printerName = $request->input('default_printer') ?: ($request->input('printers')[0] ?? null);

        if (!$hasPrinter && $printerName) {
            $device = Device::firstOrCreate(
                ['code' => $printerName],
                ['device_type' => 'PRINTER', 'status' => 'ACTIVE']
            );

            OperationClientDevice::create([
                'operation_client_id' => $client->id,
                'device_id' => $device->id,
                'device_role' => 'PRIMARY_PRINTER',
                'is_default' => true,
                'priority' => 1,
                'enabled' => true,
            ]);
        }

        return response()->json(['status' => 'SUCCESS']);
    }

    /**
     * Get pending print jobs for local agent. Ghi AGENT_CLAIMED đúng 1 lần cho mỗi
     * job (không log lại mỗi vòng poll 500ms nếu job vẫn PENDING do đang chờ retry).
     */
    public function getJobs($workstationId)
    {
        $jobs = PrintJob::where('workstation_id', $workstationId)
            ->where('status', 'PENDING')
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($jobs as $job) {
            if (!$this->eventService->hasLogged($job->id, 'AGENT_CLAIMED')) {
                $this->eventService->log($job->id, 'AGENT_CLAIMED', [
                    'dispatch_id' => $job->dispatch_id,
                    'station_id' => $workstationId,
                    'printer_name' => $job->printer_address,
                    'correlation_id' => $job->correlation_id,
                ]);
            }
        }

        $mappedJobs = $jobs->map(function ($job) {
            return [
                'Id' => $job->id,
                'LabelPayload' => $job->label_payload,
                'PrinterConnectionType' => $job->printer_connection_type,
                'PrinterAddress' => $job->printer_address,
            ];
        });

        return response()->json($mappedJobs);
    }

    /**
     * Ghi nhận kết quả in thật từ Local Agent — nguồn xác nhận duy nhất cho "C. Lịch
     * sử in thực tế" (print_attempts) và PRINT_SUCCEEDED/PRINT_FAILED. Trước bản vá
     * này, endpoint chấp nhận status tự do (Worker.cs gửi chuỗi "SUCCESS" không khớp
     * quy ước PENDING/PRINTED/FAILED của hệ thống) và không hề tạo PrintAttempt — tức
     * là "đã in bao nhiêu lần, lần nào lỗi" chưa từng được ghi lại dù Agent đã in
     * thật. Chuẩn hóa theo route AgentController::acknowledgePrintJob (đã có cùng ý
     * tưởng nhưng KHÔNG được Worker.cs gọi tới — đã gỡ bỏ route trùng đó).
     */
    public function acknowledgeJob(Request $request, $jobId)
    {
        $request->validate([
            'status' => 'required|string|in:PRINTED,FAILED,SUCCESS',
            'error_detail' => 'sometimes|nullable|string',
        ]);

        $job = PrintJob::findOrFail($jobId);
        // Worker.cs hiện gửi "SUCCESS" cho lần in thành công — quy về đúng quy ước
        // PENDING/PRINTED/FAILED dùng chung toàn hệ thống (badge trạng thái, tier C).
        $status = $request->input('status') === 'SUCCESS' ? 'PRINTED' : $request->input('status');
        $errorDetail = $request->input('error_detail');

        DB::transaction(function () use ($job, $status, $errorDetail) {
            $attemptNo = PrintAttempt::where('print_job_id', $job->id)->count() + 1;
            PrintAttempt::create([
                'print_job_id' => $job->id,
                'attempt_no' => $attemptNo,
                'status' => $status,
                'started_at' => now(),
                'finished_at' => now(),
                'error_detail' => $errorDetail,
            ]);

            $job->status = $status;
            $job->processed_at = Carbon::now();
            $job->save();

            $context = [
                'dispatch_id' => $job->dispatch_id,
                'station_id' => $job->workstation_id,
                'printer_name' => $job->printer_address,
                'correlation_id' => $job->correlation_id,
                'error_message' => $errorDetail,
            ];
            $this->eventService->log($job->id, 'SENT_TO_PRINTER', $context);
            $this->eventService->log($job->id, $status === 'PRINTED' ? 'PRINT_SUCCEEDED' : 'PRINT_FAILED', $context);
        });

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Job acknowledgment recorded',
            'data' => $job
        ]);
    }
}
