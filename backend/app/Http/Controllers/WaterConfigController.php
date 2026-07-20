<?php

namespace App\Http\Controllers;

use App\Models\WaterConfig;
use Illuminate\Http\Request;

class WaterConfigController extends Controller
{
    /**
     * List all water configurations.
     */
    public function index()
    {
        $configs = WaterConfig::orderBy('machine_line', 'asc')
            ->orderBy('process_code', 'asc')
            ->get();
        return response()->json($configs);
    }

    /**
     * Update a specific water configuration.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'ratio_coefficient' => 'required|numeric|min:0',
            'liquor_ratio' => 'required|numeric|min:0',
        ]);

        $config = WaterConfig::findOrFail($id);
        $config->update($request->only(['ratio_coefficient', 'liquor_ratio']));

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Water configuration updated successfully',
            'data' => $config
        ]);
    }
}
