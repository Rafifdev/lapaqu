<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Table;
use Illuminate\Http\JsonResponse;

class PublicTableController extends Controller
{
    public function resolveTable(string $token): JsonResponse
    {
        // Public endpoint without auth: search without global tenant scope
        $table = Table::withoutGlobalScopes()
            ->with(['outlet.tenant'])
            ->where('qr_code_token', $token)
            ->first();

        if (!$table || !$table->is_active) {
            return response()->json(['message' => 'QR Code meja tidak valid atau meja tidak aktif.'], 404);
        }

        $tenant = $table->outlet->tenant;
        if (!$tenant || in_array($tenant->status, ['suspended', 'churned'])) {
            return response()->json(['message' => 'Restoran sedang tidak dapat menerima pesanan online.'], 403);
        }

        // Fetch menu categories & available items for this outlet
        $categories = MenuCategory::withoutGlobalScopes()
            ->where('outlet_id', $table->outlet_id)
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();

        $menuItems = MenuItem::withoutGlobalScopes()
            ->where('outlet_id', $table->outlet_id)
            ->where('is_available', true)
            ->with(['variantGroups' => function ($q) {
                $q->with(['options' => function ($optQ) {
                    $optQ->where('is_available', true);
                }]);
            }])
            ->get();

        return response()->json([
            'table' => [
                'id' => $table->id,
                'table_number' => $table->table_number,
                'capacity' => $table->capacity,
            ],
            'outlet' => [
                'id' => $table->outlet->id,
                'name' => $table->outlet->name,
                'address' => $table->outlet->address,
                'timezone' => $table->outlet->timezone,
            ],
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'subdomain' => $tenant->subdomain,
            ],
            'categories' => $categories,
            'menu_items' => $menuItems,
        ]);
    }
}
