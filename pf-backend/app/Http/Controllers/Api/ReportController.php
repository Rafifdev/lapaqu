<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const CATEGORY_COLORS = [
        '#4880FF', // Blue
        '#00B69B', // Green / Teal
        '#FCBE2D', // Amber / Yellow
        '#FD5454', // Coral / Red
        '#8280FF', // Purple
        '#FF9066', // Orange
        '#38BDF8', // Sky Blue
        '#A855F7', // Violet
    ];

    public function salesSummary(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', now()->toDateString());
        $outletId = $request->query('outlet_id', $request->user()?->outlet_id ?? \App\Models\Outlet::first()?->id);

        $query = Order::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('payment_status', 'paid')
            ->where('status', '!=', 'refunded');

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        $grossSales = (int) $query->sum('total_amount');
        $totalDiscounts = (int) $query->sum('discount_amount');
        $netSales = (int) $query->sum('final_amount');
        $totalOrders = $query->count();
        $aov = $totalOrders > 0 ? (int) round($netSales / $totalOrders) : 0;

        // Payment Breakdown
        $orderIds = $query->pluck('id');
        $payments = Payment::whereIn('order_id', $orderIds)
            ->where('status', 'paid')
            ->select('payment_method', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as total_count'))
            ->groupBy('payment_method')
            ->get();

        return response()->json([
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'summary' => [
                'gross_sales' => $grossSales,
                'total_discounts' => $totalDiscounts,
                'net_sales' => $netSales,
                'total_orders' => $totalOrders,
                'average_order_value' => $aov,
            ],
            'payment_breakdown' => $payments,
        ]);
    }

    public function topItems(Request $request): JsonResponse
    {
        $now = Carbon::now();
        $period = $request->query('period');

        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');
        } elseif ($period === 'today') {
            $startDate = $now->toDateString();
            $endDate = $now->toDateString();
        } elseif ($period === 'week') {
            $startDate = $now->copy()->subDays(6)->toDateString();
            $endDate = $now->toDateString();
        } elseif ($period === 'year') {
            $startDate = $now->copy()->startOfYear()->toDateString();
            $endDate = $now->toDateString();
        } else {
            // Default month
            $startDate = $request->query('start_date', $now->copy()->startOfMonth()->toDateString());
            $endDate = $request->query('end_date', $now->toDateString());
        }

        $outletId = $request->query('outlet_id', $request->user()?->outlet_id ?? \App\Models\Outlet::first()?->id);
        $categoryId = $request->query('category_id');
        $limit = $request->query('limit') ? (int) $request->query('limit') : null;

        // Fetch all active menu items for tenant/outlet
        $menuItemsQuery = MenuItem::with('category')->where('is_available', true);
        if ($outletId) {
            $menuItemsQuery->where('outlet_id', $outletId);
        }
        $allMenuItems = $menuItemsQuery->get()->keyBy('id');

        // Aggregate actual sold order items in date range
        $orderItemsAgg = OrderItem::whereHas('order', function ($q) use ($startDate, $endDate, $outletId) {
            $q->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
              ->where('payment_status', 'paid')
              ->where('status', '!=', 'refunded');

            if ($outletId) {
                $q->where('outlet_id', $outletId);
            }
        })
        ->where('is_voided', false)
        ->select(
            'menu_item_id',
            'item_name_snapshot',
            DB::raw('SUM(quantity) as total_quantity'),
            DB::raw('SUM(subtotal) as total_revenue')
        )
        ->groupBy('menu_item_id', 'item_name_snapshot')
        ->get()
        ->keyBy('menu_item_id');

        $items = [];
        $totalSoldQty = 0;
        $totalTurnover = 0;

        foreach ($allMenuItems as $menuId => $menu) {
            $soldRecord = $orderItemsAgg->get($menuId);
            $qty = $soldRecord ? (int) $soldRecord->total_quantity : 0;
            $revenue = $soldRecord ? (int) $soldRecord->total_revenue : 0;

            $items[] = [
                'id' => $menu->id,
                'name' => $menu->name,
                'price' => (int) $menu->base_price,
                'imageUrl' => $menu->image_url ?: 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=500&auto=format&fit=crop&q=80',
                'categoryId' => $menu->category_id,
                'categoryName' => $menu->category?->name ?? 'Lainnya',
                'soldQty' => $qty,
                'turnover' => $revenue > 0 ? $revenue : ($qty * (int) $menu->base_price),
            ];

            $totalSoldQty += $qty;
            $totalTurnover += ($revenue > 0 ? $revenue : ($qty * (int) $menu->base_price));
        }

        // Sort by sold quantity descending, then by turnover descending
        usort($items, function ($a, $b) {
            if ($b['soldQty'] === $a['soldQty']) {
                return $b['turnover'] <=> $a['turnover'];
            }
            return $b['soldQty'] <=> $a['soldQty'];
        });

        // Assign true rank
        foreach ($items as $idx => &$item) {
            $item['rank'] = $idx + 1;
        }
        unset($item);

        // Calculate Category Breakdown
        $categoryMap = [];
        $catIdx = 0;
        foreach ($items as $it) {
            $catId = $it['categoryId'] ?: 'other';
            $catName = $it['categoryName'];
            if (!isset($categoryMap[$catId])) {
                $categoryMap[$catId] = [
                    'id' => $catId,
                    'name' => $catName,
                    'totalQty' => 0,
                    'totalTurnover' => 0,
                    'count' => 0,
                    'color' => self::CATEGORY_COLORS[$catIdx % count(self::CATEGORY_COLORS)],
                ];
                $catIdx++;
            }
            $categoryMap[$catId]['totalQty'] += $it['soldQty'];
            $categoryMap[$catId]['totalTurnover'] += $it['turnover'];
            $categoryMap[$catId]['count'] += 1;
        }

        $categoryBreakdown = array_values($categoryMap);
        usort($categoryBreakdown, fn($a, $b) => $b['totalTurnover'] <=> $a['totalTurnover']);

        // Top item and category
        $topItem = count($items) > 0 ? $items[0] : null;
        $topCategory = count($categoryBreakdown) > 0 ? $categoryBreakdown[0] : null;

        // Apply filters if requested
        $filteredItems = $items;
        if ($categoryId && $categoryId !== 'all') {
            $filteredItems = array_values(array_filter($filteredItems, fn($it) => $it['categoryId'] === $categoryId));
        }

        if ($limit && $limit > 0) {
            $filteredItems = array_slice($filteredItems, 0, $limit);
        }

        return response()->json([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'period_type' => $period ?: 'month',
            ],
            'summary' => [
                'total_sold_quantity' => $totalSoldQty,
                'total_turnover' => $totalTurnover,
                'top_item' => $topItem,
                'top_category' => $topCategory,
            ],
            'category_breakdown' => $categoryBreakdown,
            'top_items' => $filteredItems,
        ]);
    }

    public function hourlySales(Request $request): JsonResponse
    {
        $now = Carbon::now();
        $period = $request->query('period', 'today');
        $date = $request->query('date', $now->toDateString());
        $outletId = $request->query('outlet_id', $request->user()?->outlet_id ?? \App\Models\Outlet::first()?->id);

        $query = Order::with('items')
            ->where('payment_status', 'paid')
            ->where('status', '!=', 'refunded');

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        // Period Filtering
        switch ($period) {
            case 'today':
                $query->whereDate('created_at', $date);
                $periodLabel = 'Hari Ini (' . Carbon::parse($date)->translatedFormat('d M Y') . ')';
                break;
            case 'week':
                $start = $now->copy()->subDays(6)->startOfDay();
                $end = $now->copy()->endOfDay();
                $query->whereBetween('created_at', [$start, $end]);
                $periodLabel = '7 Hari Terakhir';
                break;
            case 'weekend':
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfDay();
                $query->whereBetween('created_at', [$start, $end])
                      ->whereRaw('EXTRACT(DOW FROM created_at) IN (0, 6)');
                $periodLabel = 'Akhir Pekan (Sabtu & Minggu)';
                break;
            case 'weekday':
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfDay();
                $query->whereBetween('created_at', [$start, $end])
                      ->whereRaw('EXTRACT(DOW FROM created_at) BETWEEN 1 AND 5');
                $periodLabel = 'Hari Kerja (Senin - Jumat)';
                break;
            case 'month':
            default:
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfDay();
                $query->whereBetween('created_at', [$start, $end]);
                $periodLabel = 'Bulan Ini (' . $now->translatedFormat('F Y') . ')';
                break;
        }

        $orders = $query->get();

        // Standard 15 operating slots (08:00 to 22:00)
        $operatingHours = [
            '08:00' => ['08:00 - 09:00', null],
            '09:00' => ['09:00 - 10:00', null],
            '10:00' => ['10:00 - 11:00', null],
            '11:00' => ['11:00 - 12:00', null],
            '12:00' => ['12:00 - 13:00', 'Makan Siang'],
            '13:00' => ['13:00 - 14:00', null],
            '14:00' => ['14:00 - 15:00', null],
            '15:00' => ['15:00 - 16:00', null],
            '16:00' => ['16:00 - 17:00', null],
            '17:00' => ['17:00 - 18:00', null],
            '18:00' => ['18:00 - 19:00', null],
            '19:00' => ['19:00 - 20:00', 'Makan Malam'],
            '20:00' => ['20:00 - 21:00', null],
            '21:00' => ['21:00 - 22:00', null],
            '22:00' => ['22:00 - 23:00', null],
        ];

        $hourly = [];
        foreach ($operatingHours as $hKey => [$range, $labelTag]) {
            $hourly[$hKey] = [
                'hour' => $hKey,
                'timeRange' => $range,
                'labelTag' => $labelTag,
                'ordersCount' => 0,
                'totalQty' => 0,
                'totalTurnover' => 0,
            ];
        }

        $maxOrders = 0;
        foreach ($orders as $order) {
            $h = sprintf('%02d:00', (int) $order->created_at->format('H'));
            if (isset($hourly[$h])) {
                $hourly[$h]['ordersCount'] += 1;
                $hourly[$h]['totalTurnover'] += (int) $order->final_amount;
                $hourly[$h]['totalQty'] += $order->items->sum('quantity');

                if ($hourly[$h]['ordersCount'] > $maxOrders) {
                    $maxOrders = $hourly[$h]['ordersCount'];
                }
            }
        }

        // Determine density tier dynamically
        foreach ($hourly as &$slot) {
            $ratio = $maxOrders > 0 ? ($slot['ordersCount'] / $maxOrders) : 0;
            if ($ratio >= 0.75 && $slot['ordersCount'] > 0) {
                $slot['densityKey'] = 'peak';
                $slot['densityLabel'] = 'Sangat Ramai';
            } elseif ($ratio >= 0.45 && $slot['ordersCount'] > 0) {
                $slot['densityKey'] = 'busy';
                $slot['densityLabel'] = 'Ramai';
            } elseif ($ratio >= 0.20 && $slot['ordersCount'] > 0) {
                $slot['densityKey'] = 'medium';
                $slot['densityLabel'] = 'Sedang';
            } else {
                $slot['densityKey'] = 'relax';
                $slot['densityLabel'] = 'Santai';
            }
        }
        unset($slot);

        $hourlySlots = array_values($hourly);

        // Time Zone Aggregation
        $pagi = array_filter($hourlySlots, fn($s) => in_array($s['hour'], ['08:00', '09:00', '10:00']));
        $siang = array_filter($hourlySlots, fn($s) => in_array($s['hour'], ['11:00', '12:00', '13:00', '14:00']));
        $sore = array_filter($hourlySlots, fn($s) => in_array($s['hour'], ['15:00', '16:00', '17:00']));
        $malam = array_filter($hourlySlots, fn($s) => in_array($s['hour'], ['18:00', '19:00', '20:00', '21:00', '22:00']));

        $sumTurnover = fn($arr) => array_reduce($arr, fn($acc, $cur) => $acc + $cur['totalTurnover'], 0);
        $sumOrders = fn($arr) => array_reduce($arr, fn($acc, $cur) => $acc + $cur['ordersCount'], 0);

        $totalRev = $sumTurnover($hourlySlots) ?: 1;
        $totalOrd = $sumOrders($hourlySlots) ?: 1;

        $timeZones = [
            [
                'id' => 'siang',
                'name' => 'Siang (11:00 - 15:00)',
                'shortName' => 'Siang',
                'color' => '#FD5454',
                'turnover' => $sumTurnover($siang),
                'orders' => $sumOrders($siang),
                'percentRev' => number_format(($sumTurnover($siang) / $totalRev) * 100, 1),
                'percentOrd' => number_format(($sumOrders($siang) / $totalOrd) * 100, 1),
            ],
            [
                'id' => 'malam',
                'name' => 'Malam (18:00 - 22:00)',
                'shortName' => 'Malam',
                'color' => '#FCBE2D',
                'turnover' => $sumTurnover($malam),
                'orders' => $sumOrders($malam),
                'percentRev' => number_format(($sumTurnover($malam) / $totalRev) * 100, 1),
                'percentOrd' => number_format(($sumOrders($malam) / $totalOrd) * 100, 1),
            ],
            [
                'id' => 'sore',
                'name' => 'Sore (15:00 - 18:00)',
                'shortName' => 'Sore',
                'color' => '#4880FF',
                'turnover' => $sumTurnover($sore),
                'orders' => $sumOrders($sore),
                'percentRev' => number_format(($sumTurnover($sore) / $totalRev) * 100, 1),
                'percentOrd' => number_format(($sumOrders($sore) / $totalOrd) * 100, 1),
            ],
            [
                'id' => 'pagi',
                'name' => 'Pagi (08:00 - 11:00)',
                'shortName' => 'Pagi',
                'color' => '#00B69B',
                'turnover' => $sumTurnover($pagi),
                'orders' => $sumOrders($pagi),
                'percentRev' => number_format(($sumTurnover($pagi) / $totalRev) * 100, 1),
                'percentOrd' => number_format(($sumOrders($pagi) / $totalOrd) * 100, 1),
            ],
        ];

        // Key stats
        $lunchOrders = $sumOrders($siang);
        $dinnerOrders = $sumOrders($malam);
        $rushRevenue = $sumTurnover($siang) + $sumTurnover($malam);
        $rushPercent = number_format(($rushRevenue / $totalRev) * 100, 1);

        // Find busiest hour
        $busiest = null;
        foreach ($hourlySlots as $s) {
            if (!$busiest || $s['ordersCount'] > $busiest['ordersCount']) {
                $busiest = $s;
            }
        }

        return response()->json([
            'period' => $period,
            'period_label' => $periodLabel,
            'summary' => [
                'total_orders' => $sumOrders($hourlySlots),
                'total_turnover' => $sumTurnover($hourlySlots),
                'lunch_orders' => $lunchOrders,
                'dinner_orders' => $dinnerOrders,
                'rush_revenue_percent' => $rushPercent . '%',
                'busiest_hour' => $busiest ? $busiest['hour'] : '19:00',
            ],
            'hourly_slots' => $hourlySlots,
            'time_zone_distribution' => $timeZones,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $startDate = $request->query('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', now()->toDateString());
        $outletId = $request->query('outlet_id', $request->user()?->outlet_id ?? \App\Models\Outlet::first()?->id);

        $query = Order::with(['outlet', 'table', 'payments'])
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        $orders = $query->orderBy('created_at', 'asc')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="laporan-penjualan-' . $startDate . '-sd-' . $endDate . '.csv"',
        ];

        return response()->stream(function () use ($orders) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['No. Order', 'Waktu', 'Outlet', 'Tipe', 'Meja', 'Pelanggan', 'Status Order', 'Status Bayar', 'Metode Bayar', 'Total (Rp)']);

            foreach ($orders as $order) {
                $paymentMethod = $order->payments->first()?->payment_method ?? '-';
                fputcsv($handle, [
                    $order->order_number,
                    $order->created_at->format('Y-m-d H:i:s'),
                    $order->outlet?->name ?? '-',
                    $order->order_type,
                    $order->table?->table_number ?? '-',
                    $order->customer_name,
                    $order->status,
                    $order->payment_status,
                    $paymentMethod,
                    $order->final_amount,
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
