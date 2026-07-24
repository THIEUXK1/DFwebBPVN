<?php

namespace App\Models;

class Workstation extends OperationClient
{
    protected $table = 'operation_clients';

    // Virtual attributes for backward compatibility — CHỈ liệt kê thuộc tính THẬT SỰ ảo
    // (có get{Attr}Attribute() tương ứng). 'workstation_type' và 'type' là CỘT THẬT trên
    // bảng (đã có sẵn trong SELECT *), không phải virtual — để lẫn vào đây làm Eloquent
    // gọi getWorkstationTypeAttribute()/getTypeAttribute() (không tồn tại) mỗi khi
    // serialize ra JSON, crash 500 toàn bộ endpoint trả danh sách/1 workstation (phát
    // hiện qua GET /api/workstations trả lỗi rỗng cho người dùng, 2026-07-18).
    protected $appends = [
        'assigned_scale_device_id',
        'assigned_printer_device_id',
        'allowed_actions',
        'active',
        'default_screen',
        'capability_codes',
    ];

    /**
     * Mã capability thô (QR_LABEL_PRINTING, PRODUCTION_ORDER...) — khác với
     * allowed_actions (danh sách hành động đã suy diễn). Frontend cần trường này để
     * đối chiếu 1 trạm có đúng quyền cho 1 màn hình hay không (AppLayout.vue), dùng
     * chung công thức với client.capabilities trả về từ /api/kiosk/session — trước
     * bản vá này endpoint /api/workstations không lộ capability thô, khiến
     * AppLayout không thể tự phát hiện khi 1 trạm không có quyền cho màn hình đang mở.
     */
    public function getCapabilityCodesAttribute()
    {
        return $this->capabilities()->pluck('code')->toArray();
    }

    public function getAssignedScaleDeviceIdAttribute()
    {
        $scale = $this->devices()
            ->where('device_type', 'SCALE')
            ->where('operation_client_devices.is_default', true)
            ->first();
        if (!$scale) {
            $scale = $this->devices()
                ->where('device_type', 'SCALE')
                ->first();
        }
        return $scale ? $scale->code : null;
    }

    public function getAssignedPrinterDeviceIdAttribute()
    {
        $printer = $this->devices()
            ->where('device_type', 'PRINTER')
            ->where('operation_client_devices.is_default', true)
            ->first();
        if (!$printer) {
            $printer = $this->devices()
                ->where('device_type', 'PRINTER')
                ->first();
        }
        return $printer ? $printer->code : null;
    }

    public function getDefaultScreenAttribute()
    {
        return $this->default_route;
    }

    public function setDefaultScreenAttribute($value)
    {
        $this->default_route = $value;
    }



    public function getAllowedActionsAttribute()
    {
        $caps = $this->capabilities()->pluck('code')->toArray();
        $actions = [];
        if (in_array('SMALL_SCALE', $caps) || in_array('LARGE_SCALE', $caps)) {
            $actions = array_merge($actions, ['SCAN_ORDER', 'WEIGH_ITEM', 'PRINT_LABEL']);
        }
        if (in_array('QR_LABEL_PRINTING', $caps)) {
            $actions = array_merge($actions, ['SCAN_LABEL', 'REPRINT_LABEL']);
        }
        if (in_array('CHEMICAL_CALL', $caps)) {
            $actions = array_merge($actions, ['CALL_CHEMICAL', 'VERIFY_READINESS', 'CONFIRM_FEED']);
        }
        if (in_array('PRODUCTION_ORDER', $caps)) {
            $actions = array_merge($actions, ['SCAN_ORDER']);
        }
        return $actions;
    }

    public function getActiveAttribute()
    {
        return $this->status === 'ACTIVE';
    }

    public function setActiveAttribute($value)
    {
        $this->status = $value ? 'ACTIVE' : 'INACTIVE';
    }

    public function allowedActionItems()
    {
        return $this->hasMany(WorkstationAllowedAction::class, 'workstation_id');
    }

    public function roleAssignments()
    {
        return $this->hasMany(WorkstationRoleAssignment::class, 'workstation_id');
    }

    public function deviceAssignments()
    {
        return $this->hasMany(DeviceAssignment::class, 'workstation_id');
    }
}
