<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Outlet;
use App\Models\Table;
use App\Models\MenuItem;

$tenant = Tenant::first();
$outlet = Outlet::first();
$tables = Table::all();
$menuItems = MenuItem::all();

if (!$tenant || !$outlet || $tables->isEmpty() || $menuItems->isEmpty()) {
    echo "Required master data missing!\n";
    exit(1);
}

OrderItem::truncate();
Payment::truncate();
Order::truncate();

$customerNames = [
    'Dimas Aditya', 'Siti Rahma', 'Hendra Pratama', 'Jessica Tan', 
    'Bambang Pamungkas', 'Rina Wulandari', 'Andi Wijaya', 'Farhan Kurnia',
    'Nadia Putri', 'Reza Rahadian', 'Maya Safitri', 'Kevin Sanjaya'
];

$orderStatuses = ['completed', 'completed', 'completed', 'completed', 'preparing', 'ready', 'awaiting_payment'];
$paymentMethods = ['qris', 'cash', 'xendit', 'qris'];

$totalRevenue = 0;
$todayOrdersCount = 0;

for ($i = 1; $i <= 48; $i++) {
    $table = $tables->random();
    $customer = $customerNames[array_rand($customerNames)];
    $status = $orderStatuses[array_rand($orderStatuses)];
    $paymentStatus = in_array($status, ['completed', 'preparing', 'ready']) ? 'paid' : 'pending';
    $paymentMethod = $paymentMethods[array_rand($paymentMethods)];

    $orderNum = 'ORD-' . date('ymd') . '-' . sprintf('%03d', $i);
    $hoursAgo = rand(0, 14);
    $minsAgo = rand(1, 59);
    $orderTime = now()->subHours($hoursAgo)->subMinutes($minsAgo);

    $order = Order::create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $outlet->id,
        'table_id' => $table->id,
        'order_number' => $orderNum,
        'order_type' => 'dine_in',
        'customer_name' => $customer,
        'customer_phone' => '08' . rand(1111111111, 9999999999),
        'status' => $status,
        'payment_status' => $paymentStatus,
        'total_amount' => 0,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'final_amount' => 0,
        'created_at' => $orderTime,
        'updated_at' => $orderTime,
    ]);

    $numItems = rand(1, 3);
    $subtotal = 0;
    for ($j = 0; $j < $numItems; $j++) {
        $menuItem = $menuItems->random();
        $qty = rand(1, 2);
        $itemTotal = $menuItem->base_price * $qty;
        $subtotal += $itemTotal;

        OrderItem::create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'item_name_snapshot' => $menuItem->name,
            'base_price_snapshot' => $menuItem->base_price,
            'quantity' => $qty,
            'subtotal' => $itemTotal,
            'status' => in_array($status, ['completed', 'ready']) ? 'ready' : 'preparing',
            'created_at' => $orderTime,
            'updated_at' => $orderTime,
        ]);
    }

    $order->update([
        'total_amount' => $subtotal,
        'final_amount' => $subtotal,
    ]);

    if ($paymentStatus === 'paid') {
        Payment::create([
            'order_id' => $order->id,
            'payment_method' => $paymentMethod,
            'payment_channel' => strtoupper($paymentMethod),
            'amount' => $subtotal,
            'status' => 'paid',
            'paid_at' => $orderTime,
            'created_at' => $orderTime,
            'updated_at' => $orderTime,
        ]);
        $totalRevenue += $subtotal;
    }
    $todayOrdersCount++;
}

echo json_encode([
    'message' => 'Successfully seeded realistic orders into pf-backend',
    'total_orders' => $todayOrdersCount,
    'total_sales' => $totalRevenue,
    'tables_count' => $tables->count(),
    'menu_items_count' => $menuItems->count()
], JSON_PRETTY_PRINT) . "\n";
