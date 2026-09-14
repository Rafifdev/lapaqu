<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\BillingInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlatformBillingWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $configuredToken = config('services.xendit.callback_token');
        $incomingToken = $request->header('x-callback-token');

        if (!empty($configuredToken) && $incomingToken !== $configuredToken) {
            return response()->json(['message' => 'Invalid callback verification token.'], 403);
        }

        $payload = $request->all();
        $status = strtoupper($payload['status'] ?? '');
        $externalId = $payload['external_id'] ?? null;
        $xenditInvoiceId = $payload['id'] ?? null;

        $invoice = BillingInvoice::where('invoice_number', $externalId)
            ->orWhere('xendit_invoice_id', $xenditInvoiceId)
            ->first();

        if (!$invoice) {
            return response()->json(['message' => 'Invoice not found.'], 404);
        }

        if (in_array($status, ['PAID', 'SETTLED'])) {
            $invoice->status = 'paid';
            $invoice->paid_at = now();
            $invoice->save();

            $subscription = $invoice->subscription;
            if ($subscription) {
                $subscription->status = 'active';
                $subscription->current_period_start = now();
                $subscription->current_period_end = now()->addMonth();
                $subscription->next_billing_date = now()->addMonth();
                $subscription->save();

                $tenant = $subscription->tenant;
                if ($tenant) {
                    $tenant->status = 'active';
                    $tenant->save();
                }
            }
        } elseif ($status === 'EXPIRED') {
            $invoice->status = 'expired';
            $invoice->save();

            $subscription = $invoice->subscription;
            if ($subscription && $subscription->status !== 'active') {
                // If subscription was already past trial/grace, mark suspended
                if ($subscription->next_billing_date->isPast()) {
                    $subscription->status = 'suspended';
                    $subscription->save();

                    $tenant = $subscription->tenant;
                    if ($tenant) {
                        $tenant->status = 'suspended';
                        $tenant->save();
                    }
                }
            }
        }

        return response()->json([
            'message' => 'Webhook processed successfully.',
            'invoice_id' => $invoice->id,
            'status' => $invoice->status,
        ]);
    }
}
