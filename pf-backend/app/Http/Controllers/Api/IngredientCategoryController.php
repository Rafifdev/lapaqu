<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngredientCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $outletId = $request->query('outlet_id', $request->user()?->outlet_id);
        
        $query = IngredientCategory::query();
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        $categories = $query->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->withCount('ingredients')
            ->get();

        return response()->json(['categories' => $categories]);
    }

    public function store(Request $request): JsonResponse
    {
        $outletId = $request->outlet_id ?: ($request->user()?->outlet_id ?: Outlet::first()?->id);
        $request->merge(['outlet_id' => $outletId]);

        $request->validate([
            'outlet_id' => ['required', 'uuid', 'exists:outlets,id'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $category = IngredientCategory::create([
            'outlet_id' => $request->outlet_id,
            'name' => $request->name,
            'description' => $request->description,
            'sort_order' => $request->input('sort_order', 0),
        ]);

        return response()->json([
            'message' => 'Kategori bahan baku berhasil ditambahkan.',
            'category' => $category,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $category = IngredientCategory::withCount('ingredients')->findOrFail($id);
        return response()->json(['category' => $category]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $category = IngredientCategory::findOrFail($id);

        $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $category->update($request->only(['name', 'description', 'sort_order']));

        return response()->json([
            'message' => 'Kategori bahan baku berhasil diperbarui.',
            'category' => $category,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $category = IngredientCategory::findOrFail($id);
        $category->delete();

        return response()->json(['message' => 'Kategori bahan baku berhasil dihapus.']);
    }
}
