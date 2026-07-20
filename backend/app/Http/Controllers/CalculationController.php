<?php

namespace App\Http\Controllers;

use App\Services\FormulaCalculationService;
use Illuminate\Http\Request;

class CalculationController extends Controller
{
    private FormulaCalculationService $calculationService;

    public function __construct(FormulaCalculationService $calculationService)
    {
        $this->calculationService = $calculationService;
    }

    /**
     * Preview calculation of water volume and ingredient weights.
     */
    public function preview(Request $request)
    {
        $request->validate([
            'weight' => 'required|numeric|min:0',
            'machine_line' => 'required|string|max:50',
            'color_code' => 'required|string|max:100',
            'is_white' => 'sometimes|boolean',
            'water_ratio_override' => 'nullable|numeric',
            'materials' => 'required|array',
            'materials.*.material_code' => 'required|string',
            'materials.*.concentration' => 'required|numeric|min:0',
        ]);

        $weight = (float) $request->input('weight');
        $machineLine = $request->input('machine_line');
        $colorCode = $request->input('color_code');
        $isWhite = (bool) $request->input('is_white', false);
        $ratioOverride = $request->has('water_ratio_override') ? (float) $request->input('water_ratio_override') : null;

        $processCode = $this->calculationService->getProcessCode($colorCode);
        $waterVolume = $this->calculationService->calculateWater(
            $weight,
            $machineLine,
            $processCode,
            $isWhite,
            $ratioOverride
        );

        $calculatedMaterials = [];
        foreach ($request->input('materials') as $mat) {
            $concentration = (float) $mat['concentration'];
            $targetWeight = $this->calculationService->getPrecisionRoundedWeight($waterVolume, $concentration);
            
            $calculatedMaterials[] = [
                'material_code' => $mat['material_code'],
                'concentration' => $concentration,
                'target_weight' => $targetWeight
            ];
        }

        return response()->json([
            'status' => 'SUCCESS',
            'data' => [
                'color_code' => $colorCode,
                'process_code' => $processCode,
                'weight' => $weight,
                'machine_line' => $machineLine,
                'water_volume' => $waterVolume,
                'materials' => $calculatedMaterials
            ]
        ]);
    }
}
