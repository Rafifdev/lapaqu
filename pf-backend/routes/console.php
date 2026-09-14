<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Spatie\Health\Commands\RunHealthChecksCommand;
use Spatie\Health\Commands\ScheduleCheckHeartbeatCommand;
use Spatie\Health\Models\HealthCheckResultHistoryItem;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Spatie Health Check Automation
Schedule::command(RunHealthChecksCommand::class)->everyMinute();
Schedule::command(ScheduleCheckHeartbeatCommand::class)->everyMinute();

// Retention & Cleanup: Prune health check history older than 30 days
Schedule::call(function () {
    HealthCheckResultHistoryItem::where('created_at', '<', now()->subDays(30))->delete();
})->dailyAt('01:00');

// Auto-expire unpaid orders past payment deadline
Schedule::call(function () {
    $orders = \App\Models\Order::withoutGlobalScopes()
        ->where('status', 'pending_payment')
        ->whereIn('payment_status', ['unpaid', 'pending'])
        ->with(['payments', 'items.menuItem.recipes.ingredient'])
        ->get();

    if ($orders->isEmpty()) return;

    $stockService = app(\App\Services\IngredientStockService::class);

    foreach ($orders as $order) {
        $pendingPayment = $order->payments->firstWhere('status', 'pending');
        $expirationDate = $pendingPayment?->raw_payload['expiration_date'] ?? null;
        $isExpired = false;

        if ($expirationDate && now()->isAfter($expirationDate)) {
            $isExpired = true;
        } elseif (!$expirationDate && $order->created_at->addMinutes(15)->isPast()) {
            $isExpired = true;
        }

        if ($isExpired) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($order, $stockService, $pendingPayment) {
                $order->status = 'expired';
                $order->payment_status = 'expired';
                $order->save();

                if ($pendingPayment) {
                    $pendingPayment->status = 'expired';
                    $pendingPayment->save();
                }

                foreach ($order->items as $item) {
                    if (!$item->is_voided) {
                        $stockService->returnForVoidedItem($item);
                    }
                }

                if ($order->outlet_id) {
                    $stockService->syncMenuAvailability($order->outlet_id);
                }
            });

            try {
                event(new \App\Events\OrderStatusUpdatedEvent($order));
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }
})->everyMinute();
