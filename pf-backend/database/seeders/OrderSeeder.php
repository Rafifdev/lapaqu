<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemOption;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::first();
        if (!$tenant) return;

        $outlet = Outlet::where('tenant_id', $tenant->id)->first();
        if (!$outlet) return;

        $cashier = User::where('tenant_id', $tenant->id)->first();
        $menuItems = MenuItem::where('tenant_id', $tenant->id)->with('variantGroups.options')->get()->keyBy('name');
        $tables = Table::where('outlet_id', $outlet->id)->get()->keyBy('table_number');

        if ($menuItems->isEmpty()) return;

        // 1. SEED INCOMING / ACTIVE ORDERS
        $incomingOrdersData = [
            [
                'order_number' => 'ORD-20260901-0044',
                'customer_name' => 'Dimas Aditya',
                'order_type' => 'dine_in',
                'table_num' => 'Meja 01',
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'payment_method' => 'qris',
                'minutes_ago' => 8,
                'items' => [
                    ['name' => 'Kopi Kenangan Mantan', 'qty' => 2, 'notes' => 'Less ice'],
                    ['name' => 'French Fries Truffle Oil', 'qty' => 2, 'notes' => null],
                    ['name' => 'Croissant Butter Crispy', 'qty' => 2, 'notes' => null],
                ],
            ],
            [
                'order_number' => 'ORD-20260901-0045',
                'customer_name' => 'Sarah Amanda',
                'order_type' => 'takeaway',
                'table_num' => null,
                'status' => 'preparing',
                'payment_status' => 'paid',
                'payment_method' => 'cash',
                'minutes_ago' => 14,
                'items' => [
                    ['name' => 'Matcha Espresso Latte', 'qty' => 1, 'notes' => 'Extra hot'],
                    ['name' => 'Cinnamon Roll Glaze', 'qty' => 1, 'notes' => null],
                ],
            ],
            [
                'order_number' => 'ORD-20260901-0046',
                'customer_name' => 'Rian Pratama',
                'order_type' => 'dine_in',
                'table_num' => 'Meja 03',
                'status' => 'ready',
                'payment_status' => 'paid',
                'payment_method' => 'qris',
                'minutes_ago' => 22,
                'items' => [
                    ['name' => 'Nasi Goreng Kampung Spesial', 'qty' => 2, 'notes' => 'Satu pedas, satu sedang'],
                    ['name' => 'Earl Grey Milk Tea', 'qty' => 2, 'notes' => null],
                ],
            ],
            [
                'order_number' => 'ORD-20260901-0047',
                'customer_name' => 'Bintang Kusuma',
                'order_type' => 'takeaway',
                'table_num' => null,
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'payment_method' => 'card',
                'minutes_ago' => 5,
                'items' => [
                    ['name' => 'Caramel Macchiato', 'qty' => 2, 'notes' => null],
                    ['name' => 'Croissant Butter Crispy', 'qty' => 1, 'notes' => null],
                ],
            ],
        ];

        foreach ($incomingOrdersData as $data) {
            $table = $data['table_num'] ? ($tables[$data['table_num']] ?? null) : null;
            $tableSession = $table ? TableSession::where('table_id', $table->id)->where('status', 'active')->first() : null;

            $createdAt = Carbon::now()->subMinutes($data['minutes_ago']);

            $order = Order::updateOrCreate(
                ['order_number' => $data['order_number']],
                [
                    'tenant_id' => $tenant->id,
                    'outlet_id' => $outlet->id,
                    'table_id' => $table?->id,
                    'table_session_id' => $tableSession?->id,
                    'customer_name' => $data['customer_name'],
                    'order_type' => $data['order_type'],
                    'status' => $data['status'],
                    'payment_status' => $data['payment_status'],
                    'total_amount' => 0,
                    'discount_amount' => 0,
                    'final_amount' => 0,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]
            );

            // Seed items
            $totalAmount = 0;
            $order->items()->delete();

            foreach ($data['items'] as $it) {
                $menuItem = $menuItems[$it['name']] ?? null;
                if (!$menuItem) continue;

                $subtotal = $menuItem->base_price * $it['qty'];
                $totalAmount += $subtotal;

                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $menuItem->id,
                    'item_name_snapshot' => $menuItem->name,
                    'base_price_snapshot' => $menuItem->base_price,
                    'quantity' => $it['qty'],
                    'subtotal' => $subtotal,
                    'status' => $data['status'] === 'ready' ? 'ready' : ($data['status'] === 'preparing' ? 'cooking' : 'pending'),
                    'notes' => $it['notes'],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }

            $order->update([
                'total_amount' => $totalAmount,
                'final_amount' => $totalAmount,
            ]);

            // Seed Payment if paid
            if ($data['payment_status'] === 'paid') {
                Payment::updateOrCreate(
                    ['order_id' => $order->id],
                    [
                        'payment_method' => $data['payment_method'],
                        'amount' => $totalAmount,
                        'status' => 'paid',
                        'paid_at' => $createdAt,
                        'raw_payload' => ['cashier_id' => $cashier?->id],
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]
                );
            }
        }

        // 2. SEED COMPLETED TRANSACTIONS (RIWAYAT KASIR)
        $completedTransactionsData = [
            [
                'order_number' => 'POS-20260901-7891',
                'customer_name' => 'Budi Santoso',
                'order_type' => 'dine_in',
                'table_num' => 'Meja 02',
                'payment_method' => 'cash',
                'minutes_ago' => 45,
                'items' => [
                    ['name' => 'Kopi Kenangan Mantan', 'qty' => 2],
                    ['name' => 'Croissant Butter Crispy', 'qty' => 2],
                ],
            ],
            [
                'order_number' => 'POS-20260901-7892',
                'customer_name' => 'Clara Novita',
                'order_type' => 'dine_in',
                'table_num' => 'Meja 04',
                'payment_method' => 'qris',
                'minutes_ago' => 70,
                'items' => [
                    ['name' => 'Matcha Espresso Latte', 'qty' => 2],
                    ['name' => 'French Fries Truffle Oil', 'qty' => 1],
                    ['name' => 'Cinnamon Roll Glaze', 'qty' => 1],
                ],
            ],
            [
                'order_number' => 'POS-20260901-7893',
                'customer_name' => 'Eko Prasetyo',
                'order_type' => 'takeaway',
                'table_num' => null,
                'payment_method' => 'card',
                'minutes_ago' => 110,
                'items' => [
                    ['name' => 'Nasi Goreng Kampung Spesial', 'qty' => 1],
                    ['name' => 'Signature Chocolate Ice', 'qty' => 1],
                ],
            ],
            [
                'order_number' => 'POS-20260901-7894',
                'customer_name' => 'Jessica Tan',
                'order_type' => 'dine_in',
                'table_num' => 'Meja 06',
                'payment_method' => 'qris',
                'minutes_ago' => 150,
                'items' => [
                    ['name' => 'Mie Goreng Seafood', 'qty' => 2],
                    ['name' => 'Earl Grey Milk Tea', 'qty' => 2],
                ],
            ],
            [
                'order_number' => 'POS-20260901-7895',
                'customer_name' => 'Hendri Wijaya',
                'order_type' => 'takeaway',
                'table_num' => null,
                'payment_method' => 'cash',
                'minutes_ago' => 210,
                'items' => [
                    ['name' => 'Caramel Macchiato', 'qty' => 1],
                    ['name' => 'Croissant Butter Crispy', 'qty' => 1],
                ],
            ],
            [
                'order_number' => 'POS-20260901-7896',
                'customer_name' => 'Maya Anggraini',
                'order_type' => 'dine_in',
                'table_num' => 'Meja 08',
                'payment_method' => 'card',
                'minutes_ago' => 260,
                'items' => [
                    ['name' => 'Kopi Kenangan Mantan', 'qty' => 4],
                    ['name' => 'French Fries Truffle Oil', 'qty' => 2],
                ],
            ],
            [
                'order_number' => 'POS-20260901-7897',
                'customer_name' => 'Rizky Ramadhan',
                'order_type' => 'takeaway',
                'table_num' => null,
                'payment_method' => 'qris',
                'minutes_ago' => 310,
                'items' => [
                    ['name' => 'Signature Chocolate Ice', 'qty' => 2],
                ],
            ],
        ];

        foreach ($completedTransactionsData as $data) {
            $table = $data['table_num'] ? ($tables[$data['table_num']] ?? null) : null;
            $createdAt = Carbon::now()->subMinutes($data['minutes_ago']);

            $order = Order::updateOrCreate(
                ['order_number' => $data['order_number']],
                [
                    'tenant_id' => $tenant->id,
                    'outlet_id' => $outlet->id,
                    'table_id' => $table?->id,
                    'customer_name' => $data['customer_name'],
                    'order_type' => $data['order_type'],
                    'status' => 'completed',
                    'payment_status' => 'paid',
                    'total_amount' => 0,
                    'discount_amount' => 0,
                    'final_amount' => 0,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]
            );

            $totalAmount = 0;
            $order->items()->delete();

            foreach ($data['items'] as $it) {
                $menuItem = $menuItems[$it['name']] ?? null;
                if (!$menuItem) continue;

                $subtotal = $menuItem->base_price * $it['qty'];
                $totalAmount += $subtotal;

                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $menuItem->id,
                    'item_name_snapshot' => $menuItem->name,
                    'base_price_snapshot' => $menuItem->base_price,
                    'quantity' => $it['qty'],
                    'subtotal' => $subtotal,
                    'status' => 'served',
                    'notes' => null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }

            $order->update([
                'total_amount' => $totalAmount,
                'final_amount' => $totalAmount,
            ]);

            Payment::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'payment_method' => $data['payment_method'],
                    'amount' => $totalAmount,
                    'status' => 'paid',
                    'paid_at' => $createdAt,
                    'raw_payload' => ['cashier_id' => $cashier?->id],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]
            );
        }
    }
}
