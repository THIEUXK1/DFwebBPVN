<?php
// backend/app/Http/Controllers/ChemicalFormulaGroupController.php

namespace App\Http\Controllers;

use App\Models\ChemicalFormulaGroup;

class ChemicalFormulaGroupController extends Controller
{
    /**
     * Danh sách công thức "Báo phát AC" đã xác nhận từ QR thật đang dán ở xưởng — CHƯA
     * gắn với máy nào (mapping máy<->công thức còn chờ xác nhận lại từ ảnh gốc, xem
     * migration 2026_07_28_000001).
     */
    public function index()
    {
        $groups = ChemicalFormulaGroup::orderBy('code_1')->orderBy('code_2')->get()->map(function (ChemicalFormulaGroup $g) {
            return [
                'id' => $g->id,
                'code_1' => $g->code_1,
                'code_2' => $g->code_2,
                'dosing_step' => $g->dosing_step,
                'quantity' => $g->quantity,
                'unit_weight_1' => $g->unit_weight_1,
                'total_weight_1' => $g->total_weight_1,
                'unit_weight_2' => $g->unit_weight_2,
                'total_weight_2' => $g->total_weight_2,
                'qr_text' => $g->buildQrText(),
            ];
        });

        return response()->json($groups);
    }
}
