<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Workstation;
use App\Models\Capability;
use App\Models\OperationClientCapability;
use Symfony\Component\HttpFoundation\Response;

/**
 * Xác thực Local Agent (không phải người dùng) — dùng lại registration_token của
 * app.workstations (đã có sẵn cho handshake trình duyệt kiosk qua WorkstationGuard)
 * làm credential rút gọn cho Agent, thay vì bảng device_credentials riêng theo
 * local-agent-architecture.md Mục 4.2 (đề xuất đầy đủ — chưa xây: chưa có credential
 * theo từng thiết bị vật lý, chưa rotation, chưa HMAC ký payload).
 *
 * Header: X-Workstation-Token (cùng tên với WorkstationGuard để 1 token workstation
 * dùng chung được cho cả trình duyệt kiosk lẫn Local Agent chạy trên cùng máy).
 */
class AgentAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // Cùng quy ước với WorkstationGuard: cho qua trong môi trường test trừ khi
        // ép buộc bằng header, để không phá vỡ các test hiện có dựa vào hành vi cũ.
        if (app()->environment('testing') && !$request->hasHeader('X-Enforce-Workstation-Guard')) {
            return $next($request);
        }

        $token = $request->header('X-Workstation-Token');
        $claimedId = $request->route('workstation_id')
            ?? $request->route('device_id')
            ?? $request->input('workstation_id');

        if ($token) {
            $workstation = Workstation::where('registration_token_hash', hash('sha256', $token))->first();

            if (!$workstation) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Token workstation không hợp lệ.'
                ], 401);
            }

            if (!$workstation->active) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => "Máy trạm '{$workstation->code}' đã bị vô hiệu hóa."
                ], 403);
            }

            // Whitelist: workstation_id gửi trong route/body phải khớp đúng workstation
            // sở hữu token này — chống trường hợp token của trạm A bị lộ và dùng để ghi
            // dữ liệu giả mạo dưới workstation_id của trạm B.
            if ($claimedId !== null && (string)$claimedId !== (string)$workstation->code && (string)$claimedId !== (string)$workstation->id) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'workstation_id không khớp với token đã xác thực.'
                ], 403);
            }

            $request->attributes->set('agent_workstation', $workstation);

            return $next($request);
        }

        // Khong co token: tu dang ky/nhan workstation theo workstation_id (2026-07-29,
        // theo yeu cau trien khai MSI zero-config — 3 ma tram co dinh chon qua dropdown
        // luc cai, khong go tay Token). Danh doi bao mat da duoc nguoi dung xac nhan ro:
        // bat ky may nao trong LAN cham duoc backend deu co the tu xung danh 1 trong cac
        // workstation_id nay ma khong con duoc token bao ve — xem session-log.md.
        if ($claimedId) {
            // 3 ma tram co dinh chon luc build MSI (xem agent/installer/appsettings.*.json)
            // — gan dung type/default_capability ngay tu luc tu dang ky de QR order scan
            // (ScannerController::handleOrderScan, chi chap nhan DYE_WEIGHING/
            // CHEMICAL_WEIGHING/A11_WEIGHING/DLG_WEIGHING) khong bi 403 "chi duoc phep quet
            // tai cac Tram Can san xuat" nhu voi type='AUTO_REGISTERED' chung chung truoc day
            // (loi thuc te tren WS-WEIGH-SCALE, 2026-07-30). Ma la kh ong nam trong danh
            // sach nay (vd tram tu tao ngoai du kien) van roi ve AUTO_REGISTERED nhu cu.
            $knownStationDefaults = [
                'WS-WEIGH-SCALE' => ['type' => 'DYE_WEIGHING', 'default_capability' => 'SMALL_SCALE', 'default_route' => '/weighing-station', 'caps' => ['SMALL_SCALE', 'WEIGH', 'PRINT']],
                'WS-WEIGH-PRINTER' => ['type' => 'QR_LABEL_PRINTING', 'default_capability' => 'QR_LABEL_PRINTING', 'default_route' => '/print-station', 'caps' => ['QR_LABEL_PRINTING', 'PRINT']],
                'WS-PRINT-STATION' => ['type' => 'QR_LABEL_PRINTING', 'default_capability' => 'QR_LABEL_PRINTING', 'default_route' => '/print-station', 'caps' => ['QR_LABEL_PRINTING', 'PRINT']],
            ];
            $defaults = $knownStationDefaults[(string) $claimedId] ?? null;

            // Ma tram SINH TU TEN MAY (2026-08-01, "may nao cung dung duoc co Agent la duoc"):
            // Agent de trong Workstation:Id thi tu sinh "WS-SCALE-<TEN-MAY>" (Worker.
            // ResolveWorkstationId), nen moi may ra mot ma khac nhau va KHONG the co trong danh
            // sach 3 ma co dinh o tren. Suy dinh dang tram tu truong `role` Agent gui kem thay vi
            // liet ke ma — neu khong, tram moi roi ve type AUTO_REGISTERED va bi
            // ScannerController::handleOrderScan chan 403 "chi duoc quet tai cac Tram Can".
            if (! $defaults) {
                $roleDefaults = [
                    'SCALE_ONLY' => ['type' => 'DYE_WEIGHING', 'default_capability' => 'SMALL_SCALE', 'default_route' => '/weighing-station-v2', 'caps' => ['SMALL_SCALE', 'WEIGH', 'PRINT']],
                    'PRINT_ONLY' => ['type' => 'QR_LABEL_PRINTING', 'default_capability' => 'QR_LABEL_PRINTING', 'default_route' => '/print-station', 'caps' => ['QR_LABEL_PRINTING', 'PRINT']],
                ];
                $defaults = $roleDefaults[(string) $request->input('role')] ?? null;
            }

            // Gan ro status='ACTIVE' ngay trong create-attributes (khong dua vao default
            // cua cot DB) — phat hien loi thuc te 2026-07-30: firstOrCreate() KHONG tu
            // refresh attribute status tu DB sau INSERT, nen $workstation->active (accessor
            // dua vao status) tra false o LAN GOI DAU TIEN cua 1 tram moi tu dang ky, gay
            // 403 oan du tram hoan toan binh thuong — cac lan goi sau moi dung vi da co
            // status that trong DB khi fetch lai.
            $workstation = Workstation::firstOrCreate(
                ['code' => (string) $claimedId],
                [
                    // Ten may that (Agent gui kem) de nguoi quan tri nhin ra ngay may nao la may
                    // nao trong man hinh Quan ly Workstation, thay vi doc ma tram da bi chuan hoa.
                    'name' => trim((string) $request->input('machine_name')) ?: (string) $claimedId,
                    'type' => $defaults['type'] ?? 'AUTO_REGISTERED',
                    'workstation_type' => $defaults['type'] ?? 'AUTO_REGISTERED',
                    'default_capability' => $defaults['default_capability'] ?? null,
                    'default_route' => $defaults['default_route'] ?? null,
                    'status' => 'ACTIVE',
                ]
            );

            if ($workstation->wasRecentlyCreated && $defaults) {
                foreach (array_merge($defaults['caps'], ['SCAN_QR', 'LOCAL_AGENT']) as $capCode) {
                    $cap = Capability::where('code', $capCode)->first();
                    if ($cap) {
                        OperationClientCapability::updateOrCreate(
                            ['operation_client_id' => $workstation->id, 'capability_id' => $cap->id],
                            ['enabled' => true]
                        );
                    }
                }
            }

            if (!$workstation->active) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => "Máy trạm '{$workstation->code}' đã bị vô hiệu hóa."
                ], 403);
            }

            $request->attributes->set('agent_workstation', $workstation);
        }

        return $next($request);
    }
}
