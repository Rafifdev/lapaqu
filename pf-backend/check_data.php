<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = \App\Models\User::count();
$orders = \App\Models\Order::count();
$paidOrders = \App\Models\Order::where('payment_status', 'paid')->count();
$pendingOrders = \App\Models\Order::whereIn('status', ['awaiting_payment', 'confirmed', 'preparing'])->count();
$totalSales = \App\Models\Order::where('payment_status', 'paid')->sum('final_amount');
$tables = \App\Models\Table::count();
$menuItems = \App\Models\MenuItem::count();
$refunds = \App\Models\RefundRequest::count();
$recentOrders = \App\Models\Order::with(['table', 'items'])->latest()->take(5)->get();

echo json_encode([
    'users' => $users,
    'total_orders' => $orders,
    'paid_orders' => $paidOrders,
    'pending_orders' => $pendingOrders,
    'total_sales' => $totalSales,
    'tables' => $tables,
    'menu_items' => $menuItems,
    'refunds' => $refunds,
    'recent_orders_sample' => $recentOrders->toArray()
], JSON_PRETTY_PRINT);
