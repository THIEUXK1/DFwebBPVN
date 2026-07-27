<?php
// backend/app/Http/Controllers/ChemicalDispatchLabelController.php

namespace App\Http\Controllers;

use App\Models\ChemicalDispatchLabel;
use App\Models\ChemicalWeightReference;
use App\Events\ChemicalChannelUpdated;
use Illuminate\Http\Request;

class ChemicalDispatchLabelController extends Controller
{
    /**
     * Toàn bộ bảng tra mã hóa chất phụ trợ -> khối lượng/đơn vị (tương đương sheet "semi"
     * cột E:F) — để form admin xem trước A6/A7 ngay khi gõ mã, không cần gọi API mỗi phím.
     */
    public function weightReferences()
    {
        return response()->json(
            ChemicalWeightReference::orderBy('code')->get(['code', 'unit_weight'])
        );
    }

    /**
     * Danh sách cấu hình "Báo phát AC" theo từng THÙNG (không phải theo máy — 1 máy có
     * nhiều thùng, mỗi thùng mang 1 cặp mã hóa chất khác nhau), kèm sẵn qr_text (9 dòng
     * giá trị thô đúng định dạng gốc TaoQR_chemical) để /chemical-call/monitor dùng ngay.
     */
    public function index()
    {
        $labels = ChemicalDispatchLabel::with('channel.machine')->get()->map(function (ChemicalDispatchLabel $label) {
            [$code1, $code2] = $label->splitChemicalCode();

            return [
                'id' => $label->id,
                'channel_id' => $label->channel_id,
                'machine_code' => $label->channel && $label->channel->machine ? $label->channel->machine->code : null,
                'channel_number' => $label->channel ? $label->channel->channel_number : null,
                'dosing_step_1' => $label->dosing_step_1,
                'dosing_step_2' => $label->dosing_step_2,
                'quantity' => $label->quantity,
                'chemical_code_1' => $code1,
                'total_weight_1' => $label->total_weight_1,
                'chemical_code_2' => $code2,
                'total_weight_2' => $label->total_weight_2,
                'qr_text' => $label->buildQrText(),
            ];
        });

        return response()->json($labels);
    }

    /**
     * Thêm cấu hình Báo phát AC cho 1 thùng — mỗi thùng chỉ có đúng 1 cấu hình (unique
     * channel_id). Mã hóa chất 1/2 KHÔNG nhập ở đây — suy ra từ chemical_code có sẵn của
     * chính thùng đó (xem ChemicalDispatchLabel::splitChemicalCode).
     */
    public function store(Request $request)
    {
        $request->validate([
            'channel_id' => 'required|exists:machine_chemical_channels,id',
            'dosing_step_1' => 'nullable|integer',
            'dosing_step_2' => 'nullable|integer',
            'quantity' => 'nullable|integer',
            'total_weight_1' => 'required|numeric',
            'total_weight_2' => 'required|numeric',
        ]);

        $exists = ChemicalDispatchLabel::where('channel_id', $request->input('channel_id'))->exists();
        if ($exists) {
            return response()->json([
                'error' => 'CHANNEL_ALREADY_HAS_LABEL',
                'message' => 'Thùng này đã có cấu hình Báo phát AC rồi, hãy sửa cấu hình hiện có.',
            ], 409);
        }

        $label = ChemicalDispatchLabel::create($request->only([
            'channel_id',
            'dosing_step_1',
            'dosing_step_2',
            'quantity',
            'total_weight_1',
            'total_weight_2',
        ]));

        event(new ChemicalChannelUpdated());

        return response()->json([
            'status' => 'SUCCESS',
            'data' => $label->fresh()->load('channel.machine'),
        ], 201);
    }

    /**
     * Sửa cấu hình Báo phát AC đã có của 1 thùng.
     */
    public function update(Request $request, $id)
    {
        $label = ChemicalDispatchLabel::findOrFail($id);

        $request->validate([
            'dosing_step_1' => 'nullable|integer',
            'dosing_step_2' => 'nullable|integer',
            'quantity' => 'nullable|integer',
            'total_weight_1' => 'required|numeric',
            'total_weight_2' => 'required|numeric',
        ]);

        $label->update($request->only([
            'dosing_step_1',
            'dosing_step_2',
            'quantity',
            'total_weight_1',
            'total_weight_2',
        ]));

        event(new ChemicalChannelUpdated());

        return response()->json([
            'status' => 'SUCCESS',
            'data' => $label->fresh()->load('channel.machine'),
        ]);
    }
}
