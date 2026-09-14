<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\MenuItemRecipe;
use App\Models\Outlet;
use App\Models\MenuItemVariantGroup;
use App\Models\MenuItemVariantOption;
use App\Services\IngredientStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MenuItemController extends Controller
{
    public function __construct(protected IngredientStockService $stockService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $outletId = $request->query('outlet_id') ?: ($request->user()?->outlet_id ?: Outlet::first()?->id);
        $categoryId = $request->query('category_id');
        $search = $request->query('search');

        $query = MenuItem::with(['category', 'variantGroups.options', 'recipes.ingredient']);

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $items = $query->orderBy('name', 'asc')->get();

        // Append max_servings to each item
        $items->each(function ($item) {
            $item->append('max_servings');
        });

        return response()->json(['items' => $items]);
    }

    public function show(string $id): JsonResponse
    {
        $item = MenuItem::with(['category', 'variantGroups.options', 'recipes.ingredient'])->findOrFail($id);
        $item->append('max_servings');

        return response()->json(['item' => $item]);
    }

    public function store(Request $request): JsonResponse
    {
        $outletId = $request->outlet_id ?: ($request->user()?->outlet_id ?: Outlet::first()?->id);
        $basePrice = $request->input('base_price', $request->input('price', 0));
        $request->merge([
            'outlet_id' => $outletId,
            'base_price' => $basePrice,
        ]);

        $request->validate([
            'outlet_id' => ['required', 'uuid', 'exists:outlets,id'],
            'category_id' => ['required', 'uuid', 'exists:menu_categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'integer', 'min:0'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'is_available' => ['nullable', 'boolean'],
            'variant_groups' => ['nullable', 'array'],
            'variant_groups.*.name' => ['required', 'string', 'max:100'],
            'variant_groups.*.is_required' => ['nullable', 'boolean'],
            'variant_groups.*.min_selection' => ['nullable', 'integer', 'min:0'],
            'variant_groups.*.max_selection' => ['nullable', 'integer', 'min:1'],
            'variant_groups.*.options' => ['required', 'array', 'min:1'],
            'variant_groups.*.options.*.name' => ['required', 'string', 'max:100'],
            'variant_groups.*.options.*.price_modifier' => ['nullable', 'integer', 'min:0'],
            'variant_groups.*.options.*.is_available' => ['nullable', 'boolean'],
            // Recipe validation
            'recipes' => ['nullable', 'array'],
            'recipes.*.ingredient_id' => ['required', 'uuid', 'exists:ingredients,id'],
            'recipes.*.quantity_needed' => ['required', 'numeric', 'min:0.01'],
        ]);

        $item = DB::transaction(function () use ($request) {
            $menuItem = MenuItem::create([
                'outlet_id' => $request->outlet_id,
                'category_id' => $request->category_id,
                'name' => $request->name,
                'description' => $request->description,
                'base_price' => $request->base_price,
                'image_url' => $request->image_url,
                'is_available' => $request->input('is_available', true),
            ]);

            if ($request->has('variant_groups')) {
                foreach ($request->variant_groups as $groupData) {
                    $group = MenuItemVariantGroup::create([
                        'menu_item_id' => $menuItem->id,
                        'name' => $groupData['name'],
                        'is_required' => $groupData['is_required'] ?? false,
                        'min_selection' => $groupData['min_selection'] ?? 0,
                        'max_selection' => $groupData['max_selection'] ?? 1,
                    ]);

                    foreach ($groupData['options'] as $optionData) {
                        MenuItemVariantOption::create([
                            'variant_group_id' => $group->id,
                            'name' => $optionData['name'],
                            'price_modifier' => $optionData['price_modifier'] ?? 0,
                            'is_available' => $optionData['is_available'] ?? true,
                        ]);
                    }
                }
            }

            // Create recipes
            if ($request->has('recipes')) {
                foreach ($request->recipes as $recipeData) {
                    MenuItemRecipe::create([
                        'menu_item_id' => $menuItem->id,
                        'ingredient_id' => $recipeData['ingredient_id'],
                        'quantity_needed' => $recipeData['quantity_needed'],
                    ]);
                }
            }

            return $menuItem;
        });

        $item->load(['category', 'variantGroups.options', 'recipes.ingredient']);
        $item->append('max_servings');

        return response()->json([
            'message' => 'Menu berhasil ditambahkan.',
            'item' => $item,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $item = MenuItem::findOrFail($id);

        if ($request->has('price') && !$request->has('base_price')) {
            $request->merge(['base_price' => $request->input('price')]);
        }

        $request->validate([
            'category_id' => ['sometimes', 'required', 'uuid', 'exists:menu_categories,id'],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'base_price' => ['sometimes', 'required', 'integer', 'min:0'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'is_available' => ['nullable', 'boolean'],
        ]);

        $item->update($request->only(['category_id', 'name', 'description', 'base_price', 'image_url', 'is_available']));
        $item->load(['category', 'variantGroups.options', 'recipes.ingredient']);
        $item->append('max_servings');

        return response()->json([
            'message' => 'Menu berhasil diperbarui.',
            'item' => $item,
        ]);
    }

    public function toggleAvailability(string $id): JsonResponse
    {
        $item = MenuItem::findOrFail($id);
        $item->is_available = !$item->is_available;
        $item->save();

        return response()->json([
            'message' => $item->is_available ? 'Menu sekarang tersedia.' : 'Menu ditandai habis (stok kosong).',
            'is_available' => $item->is_available,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $item = MenuItem::findOrFail($id);
        $item->delete();

        return response()->json(['message' => 'Menu berhasil dihapus.']);
    }

    /**
     * Get recipe for a menu item.
     */
    public function getRecipe(string $id): JsonResponse
    {
        $item = MenuItem::with('recipes.ingredient')->findOrFail($id);

        return response()->json([
            'menu_item_id' => $item->id,
            'menu_item_name' => $item->name,
            'recipes' => $item->recipes,
            'max_servings' => $item->maxServings,
        ]);
    }

    /**
     * Update recipe for a menu item (replace all).
     */
    public function updateRecipe(Request $request, string $id): JsonResponse
    {
        $item = MenuItem::findOrFail($id);

        $request->validate([
            'recipes' => ['required', 'array'],
            'recipes.*.ingredient_id' => ['required', 'uuid', 'exists:ingredients,id'],
            'recipes.*.quantity_needed' => ['required', 'numeric', 'min:0.01'],
        ]);

        DB::transaction(function () use ($item, $request) {
            // Delete existing recipes
            $item->recipes()->delete();

            // Create new ones
            foreach ($request->recipes as $recipeData) {
                MenuItemRecipe::create([
                    'menu_item_id' => $item->id,
                    'ingredient_id' => $recipeData['ingredient_id'],
                    'quantity_needed' => $recipeData['quantity_needed'],
                ]);
            }
        });

        $item->load('recipes.ingredient');

        // Sync availability after recipe change
        $this->stockService->syncMenuAvailability($item->outlet_id);

        return response()->json([
            'message' => 'Resep menu berhasil diperbarui.',
            'recipes' => $item->recipes,
            'max_servings' => $item->maxServings,
        ]);
    }
}
