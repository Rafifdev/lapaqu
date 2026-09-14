<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Services\IngredientStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngredientController extends Controller
{
    public function __construct(protected IngredientStockService $stockService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $outletId = $request->query('outlet_id', $request->user()?->outlet_id);
        $search = $request->query('search');
        $categoryId = $request->query('category_id');
        $lowStockOnly = $request->boolean('low_stock_only', false);

        $query = Ingredient::query()->with('category:id,name');

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $ingredients = $query->orderBy('name', 'asc')->get();

        if ($lowStockOnly) {
            $ingredients = $ingredients->filter(fn ($i) => $i->is_low_stock);
        }

        return response()->json(['ingredients' => $ingredients->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $outletId = $request->outlet_id ?: ($request->user()?->outlet_id ?: Outlet::first()?->id);
        $request->merge(['outlet_id' => $outletId]);

        $request->validate([
            'outlet_id' => ['required', 'uuid', 'exists:outlets,id'],
            'category_id' => ['nullable', 'uuid', 'exists:ingredient_categories,id'],
            'name' => ['required', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:20'],
            'current_stock' => ['nullable', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'numeric', 'min:0'],
            'cost_per_unit' => ['nullable', 'integer', 'min:0'],
        ]);

        $unit = strtolower($request->unit);
        $baseUnit = Ingredient::resolveBaseUnit($unit);

        // Convert initial stock from display unit to base unit
        $stockInBaseUnit = 0;
        if ($request->current_stock) {
            $stockInBaseUnit = Ingredient::toBaseUnit((float) $request->current_stock, $unit);
        }

        $thresholdInBaseUnit = null;
        if ($request->low_stock_threshold !== null) {
            $thresholdInBaseUnit = Ingredient::toBaseUnit((float) $request->low_stock_threshold, $unit);
        }

        $ingredient = Ingredient::create([
            'outlet_id' => $outletId,
            'category_id' => $request->category_id,
            'name' => $request->name,
            'unit' => $unit,
            'base_unit' => $baseUnit,
            'current_stock' => $stockInBaseUnit,
            'low_stock_threshold' => $thresholdInBaseUnit,
            'cost_per_unit' => $request->cost_per_unit,
        ]);

        // If initial stock was provided, create an initial stock log
        if ($stockInBaseUnit > 0) {
            \App\Models\IngredientStockLog::create([
                'tenant_id' => $ingredient->tenant_id,
                'outlet_id' => $ingredient->outlet_id,
                'ingredient_id' => $ingredient->id,
                'user_id' => $request->user()?->id,
                'type' => 'manual_adjustment',
                'quantity' => (float) $request->current_stock,
                'unit' => $unit,
                'balance_before' => 0,
                'balance_after' => (float) $request->current_stock,
                'notes' => 'Stok awal bahan baku baru',
            ]);
        }

        return response()->json([
            'message' => 'Bahan baku berhasil ditambahkan.',
            'ingredient' => $ingredient->load('category:id,name'),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $ingredient = Ingredient::with(['category:id,name', 'recipes.menuItem:id,name'])->findOrFail($id);

        return response()->json(['ingredient' => $ingredient]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $ingredient = Ingredient::findOrFail($id);

        $request->validate([
            'category_id' => ['nullable', 'uuid', 'exists:ingredient_categories,id'],
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'unit' => ['sometimes', 'required', 'string', 'max:20'],
            'low_stock_threshold' => ['nullable', 'numeric', 'min:0'],
            'cost_per_unit' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data = $request->only(['category_id', 'name', 'cost_per_unit', 'is_active']);

        if ($request->has('unit')) {
            $unit = strtolower($request->unit);
            $data['unit'] = $unit;
            $data['base_unit'] = Ingredient::resolveBaseUnit($unit);
        }

        if ($request->has('low_stock_threshold') && $request->low_stock_threshold !== null) {
            $unit = $data['unit'] ?? $ingredient->unit;
            $data['low_stock_threshold'] = Ingredient::toBaseUnit((float) $request->low_stock_threshold, $unit);
        } elseif ($request->has('low_stock_threshold')) {
            $data['low_stock_threshold'] = null;
        }

        $ingredient->update($data);

        return response()->json([
            'message' => 'Bahan baku berhasil diperbarui.',
            'ingredient' => $ingredient->fresh()->load('category:id,name'),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $ingredient = Ingredient::findOrFail($id);

        // Check if ingredient is used in any recipe
        if ($ingredient->recipes()->exists()) {
            return response()->json([
                'message' => 'Bahan baku tidak dapat dihapus karena masih digunakan dalam resep menu.',
            ], 422);
        }

        $ingredient->delete();

        return response()->json(['message' => 'Bahan baku berhasil dihapus.']);
    }

    /**
     * Adjust (restock / set) ingredient stock with automated stock logging.
     */
    public function adjustStock(Request $request, string $id): JsonResponse
    {
        $ingredient = Ingredient::findOrFail($id);

        $request->validate([
            'quantity' => ['required', 'numeric'],
            'type' => ['required', 'string', 'in:restock,set'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $type = $request->type;
        $quantity = (float) $request->quantity;
        $notes = $request->notes;

        $this->stockService->adjustStock($ingredient, $quantity, $type, $notes);

        return response()->json([
            'message' => $type === 'restock'
                ? "Stok bahan '{$ingredient->name}' berhasil ditambah."
                : "Stok bahan '{$ingredient->name}' berhasil diatur.",
            'ingredient' => $ingredient->fresh()->load('category:id,name'),
        ]);
    }
}
