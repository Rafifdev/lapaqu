<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Events\OrderPaidEvent;
use App\Events\OrderStatusUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SettlementLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderPaymentWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $callbackToken = config('services.xendit.callback_token');
        $headerToken = $request->header('x-callback-token');

        if (!empty($callbackToken) && $headerToken !== $callbackToken) {
            Log::warning("Xendit Webhook rejected: invalid token '{$headerToken}'");
            return response()->json(['message' => 'Unauthorized callback token.'], 403);
        }

        $payload = $request->all();
        Log::info("Xendit Webhook received: " . json_encode($payload));

        $chargeId = $payload['qr_code']['id'] ?? $payload['qr_id'] ?? $payload['id'] ?? null;
        $externalId = $payload['qr_code']['external_id'] ?? $payload['external_id'] ?? null;
        $status = strtoupper($payload['status'] ?? '');

        $payment = null;
        if ($chargeId) {
            $payment = Payment::where('xendit_transaction_id', $chargeId)->first();
        }
        if (!$payment && $externalId) {
            $payment = Payment::where('raw_payload->reference_id', $externalId)->first();
        }

        if (!$payment) {
            Log::warning("Xendit Webhook payment not found: chargeId={$chargeId}, externalId={$externalId}");
            return response()->json(['message' => 'Payment record not found.'], 404);
        }

        if ($payment->status === 'paid') {
            return response()->json(['message' => 'Payment already processed.'], 200);
        }

        if (in_array($status, ['EXPIRED', 'INACTIVE'])) {
            DB::transaction(function () use ($payment, $payload) {
                $payment->status = 'expired';
                $payment->raw_payload = array_merge($payment->raw_payload ?? [], $payload);
                $payment->save();

                $order = $payment->order;
                if ($order && in_array($order->payment_status, ['unpaid', 'pending'])) {
                    $order->payment_status = 'expired';
                    if ($order->status === 'pending_payment') {
                        $order->status = 'cancelled';
                    }
                    $order->save();
                    event(new OrderStatusUpdatedEvent($order));
                }
            });
            return response()->json(['message' => 'Payment marked as expired.', 'status' => 'expired']);
        }

        if (in_array($status, ['COMPLETED', 'PAID', 'SUCCEEDED', 'SETTLED'])) {
            DB::transaction(function () use ($payment, $payload) {
                $payment->status = 'paid';
                $payment->paid_at = now();
                $payment->raw_payload = array_merge($payment->raw_payload ?? [], $payload);
                $payment->save();

                $order = $payment->order;
                $order->payment_status = 'paid';
                if ($order->status === 'pending_payment') {
                    $order->status = 'confirmed';
                }
                $order->save();

                // Calculate MDR Platform Fee (0.7% QRIS)
                $feeAmount = (int) round($payment->amount * 0.007);
                $netAmount = $payment->amount - $feeAmount;

                $paymentAccount = $order->tenant->paymentAccount;
                if ($paymentAccount) {
                    SettlementLog::create([
                        'tenant_payment_account_id' => $paymentAccount->id,
                        'order_id' => $order->id,
                        'gross_amount' => $payment->amount,
                        'platform_fee' => $feeAmount,
                        'type' => 'customer_payment',
                        'net_amount' => $netAmount,
                        'status' => 'settled',
                        'settled_at' => now(),
                    ]);
                }

                event(new OrderPaidEvent($order));
                event(new OrderStatusUpdatedEvent($order));
            });

            return response()->json(['message' => 'Payment successfully recorded as paid.', 'status' => 'paid']);
        }

        return response()->json(['message' => 'Webhook received with status ' . $status]);
    }
}
