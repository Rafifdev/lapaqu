<?php

namespace App\Services\Billing;

use App\Models\BillingInvoice;
use App\Models\Subscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class XenditPlatformBillingService
{
    protected string $secretKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('services.xendit.secret_key', '');
        $this->baseUrl = config('services.xendit.base_url', 'https://api.xendit.co');
    }

    public function createSubscriptionInvoice(Subscription $subscription, ?int $customAmount = null): BillingInvoice
    {
        $tenant = $subscription->tenant;
        $plan = $subscription->plan;

        $amount = $customAmount ?? ($subscription->active_outlets_count * $plan->price_per_outlet_monthly);
        $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(Str::random(6));
        $dueDate = now()->addDays(3);

        $xenditInvoiceId = null;
        $invoiceUrl = null;

        // If secret key is provided and not empty, call Xendit API
        if (!empty($this->secretKey)) {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->baseUrl($this->baseUrl)
                ->post('/v2/invoices', [
                    'external_id' => $invoiceNumber,
                    'amount' => $amount,
                    'description' => "Langganan Lapaqu ({$plan->name}) - {$subscription->active_outlets_count} Outlet",
                    'invoice_duration' => 259200, // 3 days in seconds
                    'customer' => [
                        'given_names' => $tenant->name,
                        'email' => $tenant->users()->first()?->email ?? 'billing@' . $tenant->subdomain . '.lapaqu.id',
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $xenditInvoiceId = $data['id'] ?? null;
                $invoiceUrl = $data['invoice_url'] ?? null;
            }
        } else {
            // Mock sandbox Xendit Invoice ID for offline / test environments
            $xenditInvoiceId = 'xnd_inv_' . Str::random(24);
        }

        return BillingInvoice::create([
            'subscription_id' => $subscription->id,
            'xendit_invoice_id' => $xenditInvoiceId,
            'invoice_number' => $invoiceNumber,
            'amount' => $amount,
            'status' => 'pending',
            'due_date' => $dueDate,
        ]);
    }

    public function calculateOutletUpgrade(Subscription $subscription, int $additionalOutlets): array
    {
        $plan = $subscription->plan;
        $costPerOutlet = $plan->price_per_outlet_monthly;

        // Calculate days remaining in current period
        $now = now();
        $periodEnd = $subscription->current_period_end;
        $daysRemaining = max(0, $now->diffInDays($periodEnd, false));
        $totalDaysInMonth = 30;

        $prorateCost = (int) round(($costPerOutlet * $additionalOutlets * $daysRemaining) / $totalDaysInMonth);

        return [
            'additional_outlets' => $additionalOutlets,
            'cost_per_outlet_monthly' => $costPerOutlet,
            'days_remaining' => $daysRemaining,
            'prorated_amount' => $prorateCost,
            'new_monthly_total' => ($subscription->active_outlets_count + $additionalOutlets) * $costPerOutlet,
        ];
    }
}
