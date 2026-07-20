<?php

namespace App\Http\Controllers;

use App\Models\ScaleMeasurement;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ScaleMeasurementController extends Controller
{
    /**
     * List completed scale measurements.
     */
    public function index(Request $request)
    {
        $query = ScaleMeasurement::query();

        if ($request->has('batch_id')) {
            $query->where('legacy_batch_id', $request->input('batch_id'));
        }

        if ($request->has('material_code')) {
            $query->where('dye_code', $request->input('material_code'));
        }

        $measurements = $query->orderBy('measured_at', 'desc')->get();
        return response()->json($measurements);
    }

    /**
     * Store a completed scale measurement from Weighing Station.
     */
    public function store(Request $request)
    {
        $request->validate([
            'legacy_batch_id' => 'required|string|max:100',
            'color' => 'required|string|max:100',
            'product_code' => 'required|string|max:100',
            'machine_code' => 'required|string|max:50',
            'dye_code' => 'required|string|max:100',
            'weight' => 'required|numeric',
            'process_code' => 'required|string|max:20',
            'material_type' => 'required|string|in:DYE,CHEMICAL',
        ]);

        $measurement = ScaleMeasurement::create([
            'legacy_source' => $request->input('legacy_source', 'WEB_WEIGH'),
            'legacy_id' => $request->input('legacy_id', time() + rand(1, 100000)),
            'legacy_batch_id' => $request->input('legacy_batch_id'),
            'color' => $request->input('color'),
            'product_code' => $request->input('product_code'),
            'machine_code' => $request->input('machine_code'),
            'level_code' => $request->input('level_code'),
            'rack_code' => $request->input('rack_code'),
            'dye_code' => $request->input('dye_code'),
            'weight' => $request->input('weight'),
            'process_code' => $request->input('process_code'),
            'material_type' => $request->input('material_type'),
            'measured_at' => Carbon::now(),
            'warehouse_done' => false,
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Scale measurement recorded successfully',
            'data' => $measurement
        ], 201);
    }
}
