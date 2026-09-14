<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\StockOpname;
use App\Services\IngredientStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockOpnameController extends Controller
{
    public function __construct(protected IngredientStockService $stockService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $outletId = $request->query('outlet_id', $request->user()?->outlet_id);

        $query = StockOpname::query();
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        $opnames = $query->with(['creator:id,name', 'items.ingredient:id,name,unit,base_unit'])
            ->withCount('items')
            ->orderBy('opname_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json($opnames);
    }

    public function store(Request $request): JsonResponse
    {
        $outletId = $request->outlet_id ?: ($request->user()?->outlet_id ?: Outlet::first()?->id);
        $userId = $request->user()?->id;

        $request->validate([
            'outlet_id' => ['nullable', 'uuid', 'exists:outlets,id'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'uuid', 'exists:ingredients,id'],
            'items.*.physical_stock' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $opname = $this->stockService->processStockOpname(
                $outletId,
                $request->items,
                $request->notes,
                $userId
            );

            return response()->json([
                'message' => "Stok opname {$opname->opname_number} berhasil diproses dan stok disesuaikan.",
                'opname' => $opname,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal memproses stok opname: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        $opname = StockOpname::with(['creator:id,name', 'items.ingredient:id,name,unit,base_unit'])
            ->findOrFail($id);

        return response()->json(['opname' => $opname]);
    }
}
