<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payment\XenditSubAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantPaymentAccountController extends Controller
{
    public function __construct(protected XenditSubAccountService $subAccountService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if (!$tenant) {
            return response()->json(['message' => 'Tenant context not found.'], 404);
        }

        $account = \App\Models\TenantPaymentAccount::where('tenant_id', $tenant->id)->first();

        if (!$account) {
            return response()->json([
                'is_configured' => false,
                'balances' => [
                    'ready_to_settle' => 0,
                    'pending_settlement' => 0,
                ],
                'payment_account' => null,
            ]);
        }

        // Mask account number for security: e.g. "******7890"
        $rawNumber = $account->bank_account_number;
        $maskedNumber = strlen($rawNumber) > 4
            ? str_repeat('*', strlen($rawNumber) - 4) . substr($rawNumber, -4)
            : $rawNumber;

        // Calculate live balances from real orders table
        $totalRevenue = (int) \App\Models\Order::where('tenant_id', $tenant->id)
            ->where(function ($q) {
                $q->where('payment_status', 'paid')->orWhere('status', 'completed');
            })
            ->whereNotIn('status', ['cancelled', 'voided', 'refunded'])
            ->sum('final_amount');

        $pendingAmount = (int) \App\Models\Order::where('tenant_id', $tenant->id)
            ->whereIn('status', ['pending_payment', 'confirmed', 'processing', 'preparing', 'cooking', 'ready'])
            ->sum('final_amount');

        return response()->json([
            'is_configured' => true,
            'balances' => [
                'ready_to_settle' => $totalRevenue,
                'pending_settlement' => $pendingAmount,
            ],
            'payment_account' => [
                'id' => $account->id,
                'bank_code' => $account->bank_code,
                'bank_account_number' => $maskedNumber,
                'bank_account_holder_name' => $account->bank_account_holder_name,
                'xendit_sub_account_id' => $account->xendit_sub_account_id,
                'is_active' => $account->is_active,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'bank_code' => ['required', 'string', 'max:20', 'in:BCA,BNI,BRI,MANDIRI,PERMATA,CIMB,BSI'],
            'bank_account_number' => ['required', 'string', 'min:8', 'max:30'],
            'bank_account_holder_name' => ['required', 'string', 'max:150'],
        ]);

        $tenant = $request->user()->tenant;
        if (!$tenant) {
            return response()->json(['message' => 'Tenant context not found.'], 404);
        }

        $account = $this->subAccountService->setupPaymentAccount($tenant, $request->all());

        return response()->json([
            'message' => 'Rekening pembayaran toko & sub-account berhasil dikonfigurasi.',
            'payment_account' => [
                'id' => $account->id,
                'bank_code' => $account->bank_code,
                'bank_account_holder_name' => $account->bank_account_holder_name,
                'xendit_sub_account_id' => $account->xendit_sub_account_id,
            ],
        ], 201);
    }

    public function settlementLogs(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $account = $tenant?->paymentAccount;

        $logs = $account ? $account->settlementLogs()->orderByDesc('created_at')->paginate(20) : [];

        return response()->json(['settlement_logs' => $logs]);
    }
}
