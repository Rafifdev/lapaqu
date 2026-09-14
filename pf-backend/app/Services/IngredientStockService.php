<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\IngredientStockLog;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Illuminate\Support\Facades\DB;

class IngredientStockService
{
    /**
     * Check if all ingredients are available for the given order items.
     * Returns ['available' => true] or ['available' => false, 'errors' => [...]]
     */
    public function checkAvailability(array $orderItems, string $outletId): array
    {
        $errors = [];

        // Aggregate total ingredient needs across all items
        $ingredientNeeds = [];

        foreach ($orderItems as $item) {
            $menuItem = MenuItem::with('recipes.ingredient')
                ->where('id', $item['menu_item_id'])
                ->first();

            if (!$menuItem) {
                continue;
            }

            $recipes = $menuItem->recipes;
            if ($recipes->isEmpty()) {
                continue; // no recipe = not tracked
            }

            $quantity = $item['quantity'];

            foreach ($recipes as $recipe) {
                $ingredientId = $recipe->ingredient_id;
                $needed = (float) $recipe->quantity_needed * $quantity;

                if (!isset($ingredientNeeds[$ingredientId])) {
                    $ingredientNeeds[$ingredientId] = [
                        'ingredient' => $recipe->ingredient,
                        'total_needed' => 0,
                        'menu_items' => [],
                    ];
                }

                $ingredientNeeds[$ingredientId]['total_needed'] += $needed;
                $ingredientNeeds[$ingredientId]['menu_items'][] = $menuItem->name;
            }
        }

        // Check each ingredient
        foreach ($ingredientNeeds as $ingredientId => $need) {
            $ingredient = $need['ingredient'];
            if (!$ingredient || !$ingredient->is_active) {
                $errors[] = "Bahan '{$ingredient->name}' tidak aktif.";
                continue;
            }

            $currentStock = (float) $ingredient->current_stock;
            $totalNeeded = $need['total_needed'];

            if ($currentStock < $totalNeeded) {
                $unit = $ingredient->unit;
                $displayNeeded = round(Ingredient::fromBaseUnit($totalNeeded, $unit), 2);
                $displayStock = round(Ingredient::fromBaseUnit($currentStock, $unit), 2);
                $menuNames = implode(', ', array_unique($need['menu_items']));
                $errors[] = "Stok bahan '{$ingredient->name}' tidak cukup untuk menu {$menuNames}. Dibutuhkan: {$displayNeeded} {$unit}, tersedia: {$displayStock} {$unit}.";
            }
        }

        return [
            'available' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Deduct ingredient stock for a confirmed order.
     * Must be called within a DB transaction.
     */
    public function deductForOrder(Order $order): void
    {
        $order->loadMissing('items.menuItem.recipes.ingredient');

        foreach ($order->items as $orderItem) {
            if ($orderItem->is_voided) {
                continue;
            }

            $this->deductForOrderItem($orderItem);
        }

        // Sync menu availability for the outlet
        $this->syncMenuAvailability($order->outlet_id);
    }

    /**
     * Deduct ingredient stock for a single order item and write to stock log.
     */
    public function deductForOrderItem(OrderItem $orderItem): void
    {
        $orderItem->loadMissing('menuItem.recipes.ingredient');
        $menuItem = $orderItem->menuItem;

        if (!$menuItem || $menuItem->recipes->isEmpty()) {
            return;
        }

        foreach ($menuItem->recipes as $recipe) {
            $deductionInBase = (float) $recipe->quantity_needed * $orderItem->quantity;
            $ingredient = Ingredient::find($recipe->ingredient_id);

            if (!$ingredient) {
                continue;
            }

            $balanceBefore = (float) $ingredient->display_stock;
            $ingredient->decrement('current_stock', $deductionInBase);
            $ingredient->refresh();
            $balanceAfter = (float) $ingredient->display_stock;

            $displayDeduction = round(Ingredient::fromBaseUnit($deductionInBase, $ingredient->unit), 3);

            IngredientStockLog::create([
                'tenant_id' => $ingredient->tenant_id,
                'outlet_id' => $ingredient->outlet_id,
                'ingredient_id' => $ingredient->id,
                'type' => 'order_deduction',
                'quantity' => -$displayDeduction,
                'unit' => $ingredient->unit,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_id' => $orderItem->order?->order_number ?? (string) $orderItem->order_id,
                'notes' => "Pengurangan otomatis pesanan: {$menuItem->name} (x{$orderItem->quantity})",
                'created_by' => $orderItem->order?->customer_name ?? 'POS Kasir',
            ]);
        }
    }

    /**
     * Return ingredient stock when an order item is voided.
     */
    public function returnForVoidedItem(OrderItem $orderItem): void
    {
        $orderItem->loadMissing('menuItem.recipes.ingredient');
        $menuItem = $orderItem->menuItem;

        if (!$menuItem || $menuItem->recipes->isEmpty()) {
            return;
        }

        foreach ($menuItem->recipes as $recipe) {
            $returnAmountInBase = (float) $recipe->quantity_needed * $orderItem->quantity;
            $ingredient = Ingredient::find($recipe->ingredient_id);

            if (!$ingredient) {
                continue;
            }

            $balanceBefore = (float) $ingredient->display_stock;
            $ingredient->increment('current_stock', $returnAmountInBase);
            $ingredient->refresh();
            $balanceAfter = (float) $ingredient->display_stock;

            $displayReturn = round(Ingredient::fromBaseUnit($returnAmountInBase, $ingredient->unit), 3);

            IngredientStockLog::create([
                'tenant_id' => $ingredient->tenant_id,
                'outlet_id' => $ingredient->outlet_id,
                'ingredient_id' => $ingredient->id,
                'type' => 'restock',
                'quantity' => $displayReturn,
                'unit' => $ingredient->unit,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_id' => $orderItem->order?->order_number ?? (string) $orderItem->order_id,
                'notes' => "Pengembalian stok pembatalan item: {$menuItem->name} (x{$orderItem->quantity})",
                'created_by' => 'Kasir',
            ]);
        }

        // Sync availability since stock increased
        if ($orderItem->order) {
            $this->syncMenuAvailability($orderItem->order->outlet_id);
        }
    }

    /**
     * Manually adjust ingredient stock (restock, correction, etc).
     */
    public function adjustStock(Ingredient $ingredient, float $quantityInDisplayUnit, string $type = 'restock', ?string $notes = null): void
    {
        $balanceBefore = (float) $ingredient->display_stock;

        if ($type === 'purchase' || $type === 'restock') {
            $quantityInBaseUnit = Ingredient::toBaseUnit($quantityInDisplayUnit, $ingredient->unit);
            $ingredient->increment('current_stock', abs($quantityInBaseUnit));
        } else {
            // Set exact value
            $ingredient->current_stock = Ingredient::toBaseUnit(abs($quantityInDisplayUnit), $ingredient->unit);
            $ingredient->save();
        }

        $ingredient->refresh();
        $balanceAfter = (float) $ingredient->display_stock;

        $mutationQty = $type === 'restock' ? $quantityInDisplayUnit : ($balanceAfter - $balanceBefore);

        IngredientStockLog::create([
            'tenant_id' => $ingredient->tenant_id,
            'outlet_id' => $ingredient->outlet_id,
            'ingredient_id' => $ingredient->id,
            'type' => $type === 'restock' ? 'restock' : 'manual_set',
            'quantity' => $mutationQty,
            'unit' => $ingredient->unit,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reference_id' => null,
            'notes' => $notes ?: ($type === 'restock' ? 'Penambahan stok (Restock)' : 'Atur ulang stok manual'),
            'created_by' => auth()->user()?->name ?? 'Owner/Kasir',
        ]);

        $this->syncMenuAvailability($ingredient->outlet_id);
    }

    /**
     * Process a periodic stock opname session.
     */
    public function processStockOpname(string $outletId, array $items, ?string $notes = null, ?string $createdBy = null): StockOpname
    {
        return DB::transaction(function () use ($outletId, $items, $notes, $createdBy) {
            $date = now()->format('Y-m-d');
            $opnameNumber = 'OPN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

            $opname = StockOpname::create([
                'outlet_id' => $outletId,
                'opname_number' => $opnameNumber,
                'date' => $date,
                'status' => 'completed',
                'notes' => $notes,
                'total_items' => count($items),
                'total_variance_items' => 0,
                'created_by' => $createdBy ?: (auth()->user()?->name ?? 'Staff'),
            ]);

            $varianceCount = 0;

            foreach ($items as $itemData) {
                $ingredient = Ingredient::where('id', $itemData['ingredient_id'])
                    ->where('outlet_id', $outletId)
                    ->first();

                if (!$ingredient) {
                    continue;
                }

                $systemStock = (float) $ingredient->display_stock;
                $physicalStock = (float) $itemData['physical_stock'];
                $diff = $physicalStock - $systemStock;

                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'ingredient_id' => $ingredient->id,
                    'system_stock' => $systemStock,
                    'physical_stock' => $physicalStock,
                    'difference' => $diff,
                    'unit' => $ingredient->unit,
                    'notes' => $itemData['notes'] ?? null,
                ]);

                if (abs($diff) > 0.001) {
                    $varianceCount++;

                    // Update ingredient current stock to physical stock in base unit
                    $ingredient->current_stock = Ingredient::toBaseUnit($physicalStock, $ingredient->unit);
                    $ingredient->save();

                    // Log the adjustment
                    IngredientStockLog::create([
                        'tenant_id' => $ingredient->tenant_id,
                        'outlet_id' => $ingredient->outlet_id,
                        'ingredient_id' => $ingredient->id,
                        'type' => 'opname_adjustment',
                        'quantity' => $diff,
                        'unit' => $ingredient->unit,
                        'balance_before' => $systemStock,
                        'balance_after' => $physicalStock,
                        'reference_id' => $opname->opname_number,
                        'notes' => "Penyesuaian hasil Stok Opname #{$opname->opname_number}" . (!empty($itemData['notes']) ? ": {$itemData['notes']}" : ''),
                        'created_by' => $opname->created_by,
                    ]);
                }
            }

            $opname->update(['total_variance_items' => $varianceCount]);
            $this->syncMenuAvailability($outletId);

            return $opname->load('items.ingredient');
        });
    }

    /**
     * Auto-sync menu item availability based on ingredient stock.
     */
    public function syncMenuAvailability(string $outletId): void
    {
        $menuItems = MenuItem::where('outlet_id', $outletId)
            ->with('recipes.ingredient')
            ->get();

        foreach ($menuItems as $menuItem) {
            if ($menuItem->recipes->isEmpty()) {
                continue; // no recipe = not tracked, skip
            }

            $maxServings = $menuItem->maxServings;

            if ($maxServings !== null && $maxServings <= 0 && $menuItem->is_available) {
                // Auto-disable: stok habis
                $menuItem->is_available = false;
                $menuItem->save();
            } elseif ($maxServings !== null && $maxServings > 0 && !$menuItem->is_available) {
                // Auto-enable: stok sudah cukup lagi
                $menuItem->is_available = true;
                $menuItem->save();
            }
        }
    }
}
