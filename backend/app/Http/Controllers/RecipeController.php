<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\RecipeMaterial;
use App\Models\ProcessParameter;
use App\Services\FormulaCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecipeController extends Controller
{
    private FormulaCalculationService $calculationService;

    public function __construct(FormulaCalculationService $calculationService)
    {
        $this->calculationService = $calculationService;
    }

    /**
     * List recipes with pagination and filters.
     */
    public function index(Request $request)
    {
        $query = Recipe::query()->with(['latestVersion.materials.material', 'latestVersion.parameters']);

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('color_code', 'like', "%{$search}%")
                  ->orWhere('product_code', 'like', "%{$search}%");
            });
        }

        $recipes = $query->orderBy('created_at', 'desc')->paginate(15);
        return response()->json($recipes);
    }

    /**
     * Store a new recipe and its first version.
     */
    public function store(Request $request)
    {
        $request->validate([
            'color_code' => 'required|string|max:100',
            'product_code' => 'required|string|max:100',
            'description' => 'nullable|string',
            'tension_value' => 'nullable|numeric',
            'water_ratio_override' => 'nullable|numeric',
            'materials' => 'required|array|min:1',
            'materials.*.material_code' => 'required|string|exists:materials,code',
            'materials.*.concentration' => 'required|numeric|min:0',
            'materials.*.process_code' => 'required|string|max:20',
            'materials.*.tank_number' => 'nullable|integer|min:1|max:10',
            'materials.*.temperature' => 'nullable|numeric',
        ]);

        return DB::transaction(function () use ($request) {
            // Check if recipe already exists
            $recipe = Recipe::where('color_code', $request->input('color_code'))
                ->where('product_code', $request->input('product_code'))
                ->first();

            $isNew = false;
            if (!$recipe) {
                $recipe = Recipe::create([
                    'color_code' => $request->input('color_code'),
                    'product_code' => $request->input('product_code'),
                    'description' => $request->input('description'),
                ]);
                $versionNumber = 1;
                $isNew = true;
            } else {
                // If it exists, create a new version incremented
                $latestVersion = RecipeVersion::where('recipe_id', $recipe->id)
                    ->orderBy('version', 'desc')
                    ->first();
                $versionNumber = $latestVersion ? $latestVersion->version + 1 : 1;
            }

            // Create Recipe Version
            $recipeVersion = RecipeVersion::create([
                'recipe_id' => $recipe->id,
                'version' => $versionNumber,
                'status' => 'ACTIVE', // Active by default, no workflow
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            // Create Recipe Materials
            foreach ($request->input('materials') as $matData) {
                RecipeMaterial::create([
                    'recipe_version_id' => $recipeVersion->id,
                    'material_code' => $matData['material_code'],
                    'concentration' => $matData['concentration'],
                    'process_code' => $matData['process_code'],
                    'tank_number' => $matData['tank_number'] ?? null,
                    'temperature' => $matData['temperature'] ?? null,
                ]);
            }

            // Create Process Parameters
            ProcessParameter::create([
                'recipe_version_id' => $recipeVersion->id,
                'tension_value' => $request->input('tension_value'),
                'water_ratio_override' => $request->input('water_ratio_override'),
            ]);

            return response()->json([
                'status' => 'SUCCESS',
                'message' => $isNew ? 'Recipe created successfully' : 'New version created successfully',
                'data' => $recipe->load(['latestVersion.materials.material', 'latestVersion.parameters'])
            ], 201);
        });
    }

    /**
     * Show details of a specific recipe.
     */
    public function show($id)
    {
        $recipe = Recipe::with(['versions.materials.material', 'versions.parameters'])->findOrFail($id);
        return response()->json($recipe);
    }
}
