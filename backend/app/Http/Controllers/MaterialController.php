<?php

namespace App\Http\Controllers;

use App\Models\Material;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    /**
     * List materials with pagination, search, and type filters.
     */
    public function index(Request $request)
    {
        $query = Material::query();

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        $materials = $query->orderBy('code', 'asc')->paginate(20);

        return response()->json($materials);
    }

    /**
     * Update material stock quantity or name.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:200',
            'stock_qty' => 'sometimes|required|numeric|min:0',
            'is_active' => 'sometimes|required|boolean',
        ]);

        $material = Material::findOrFail($id);
        $material->update($request->only(['name', 'stock_qty', 'is_active']));

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Material updated successfully',
            'data' => $material
        ]);
    }
}
