<?php

namespace App\Http\Controllers\Api;

use App\Events\KitchenItemStatusUpdatedEvent;
use App\Events\OrderStatusUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KdsController extends Controller
{
    public function orders(Request $request): JsonResponse
    {
        $outletId = $request->query('outlet_id', $request->user()?->outlet_id);

        $query = Order::with(['table', 'items' => function ($q) {
            $q->where('is_voided', false)->orderBy('created_at', 'asc')->orderBy('id', 'asc')->with('options');
        }])
        ->whereIn('status', ['confirmed', 'processing', 'preparing', 'cooking', 'ready']);

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        $orders = $query->orderBy('created_at', 'asc')->get();

        return response()->json(['orders' => $orders]);
    }

    public function updateItemStatus(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:pending,cooking,ready,served'],
        ]);

        $item = OrderItem::with('order')->findOrFail($id);
        $item->status = $request->status;
        $item->save();

        event(new KitchenItemStatusUpdatedEvent($item));

        // Item status updated without automatically changing the parent order status
        // Order status changes to ready ONLY when the kitchen staff explicitly clicks "Siap Saji" 

        return response()->json([
            'message' => 'Status item dapur berhasil diperbarui.',
            'item' => $item,
        ]);
    }
}
