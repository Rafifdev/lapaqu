<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\Billing\XenditPlatformBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(protected XenditPlatformBillingService $billingService)
    {
    }

    public function currentSubscription(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if (!$tenant) {
            return response()->json(['message' => 'Tenant context not found.'], 404);
        }

        $subscription = Subscription::where('tenant_id', $tenant->id)->with('plan')->latest('created_at')->first();

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'subdomain' => $tenant->subdomain,
                'status' => $tenant->status,
                'trial_ends_at' => $tenant->trial_ends_at,
            ],
            'subscription' => $subscription,
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if (!$tenant) {
            return response()->json(['message' => 'Tenant context not found.'], 404);
        }

        $subscription = Subscription::where('tenant_id', $tenant->id)->latest('created_at')->first();
        $invoices = $subscription ? $subscription->invoices()->orderByDesc('created_at')->get() : [];

        return response()->json(['invoices' => $invoices]);
    }

    public function createInvoice(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $subscription = $tenant?->currentSubscription;

        if (!$subscription) {
            return response()->json(['message' => 'Subscription record not found.'], 404);
        }

        $invoice = $this->billingService->createSubscriptionInvoice($subscription);

        return response()->json([
            'message' => 'Invoice langganan berhasil dibuat.',
            'invoice' => $invoice,
        ], 201);
    }
    public function changePlan(Request $request): JsonResponse
    {
        $request->validate([
            'plan_code' => ['required', 'string', 'in:basic,pro'],
        ]);

        $tenant = $request->user()->tenant;
        if (!$tenant) {
            return response()->json(['message' => 'Tenant context not found.'], 404);
        }

        $plan = \App\Models\Plan::where('code', $request->plan_code)->firstOrFail();
        
        $subscription = \App\Models\Subscription::firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan_id' => $plan->id,
                'status' => 'active',
                'active_outlets_count' => 1,
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
                'next_billing_date' => now()->addMonth(),
            ]
        );

        $subscription->update([
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $invoice = $this->billingService->createSubscriptionInvoice($subscription);

        return response()->json([
            'message' => 'Paket langganan berhasil diperbarui ke ' . $plan->name,
            'subscription' => $subscription->fresh()->load('plan'),
            'invoice' => $invoice,
        ]);
    }
}
