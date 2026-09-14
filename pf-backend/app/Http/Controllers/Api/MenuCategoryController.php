<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\Outlet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $outletId = $request->query('outlet_id') ?: ($request->user()?->outlet_id ?: Outlet::first()?->id);
        
        $query = MenuCategory::query();
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        $categories = $query->orderBy('sort_order', 'asc')
            ->withCount('menuItems')
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
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category = MenuCategory::create([
            'outlet_id' => $request->outlet_id,
            'name' => $request->name,
            'sort_order' => $request->input('sort_order', 0),
            'is_active' => $request->input('is_active', true),
        ]);

        return response()->json([
            'message' => 'Kategori menu berhasil ditambahkan.',
            'category' => $category,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $category = MenuCategory::findOrFail($id);

        $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category->update($request->only(['name', 'sort_order', 'is_active']));

        return response()->json([
            'message' => 'Kategori menu berhasil diperbarui.',
            'category' => $category,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $category = MenuCategory::findOrFail($id);
        $category->delete();

        return response()->json(['message' => 'Kategori menu berhasil dihapus.']);
    }
}
