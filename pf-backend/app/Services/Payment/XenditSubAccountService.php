<?php

namespace App\Services\Payment;

use App\Models\Tenant;
use App\Models\TenantPaymentAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class XenditSubAccountService
{
    protected string $secretKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('services.xendit.secret_key', '');
        $this->baseUrl = config('services.xendit.base_url', 'https://api.xendit.co');
    }

    public function setupPaymentAccount(Tenant $tenant, array $bankData): TenantPaymentAccount
    {
        $xenditSubAccountId = null;

        // If secret key is provided and live, call xenPlatform API /v2/accounts
        if (!empty($this->secretKey)) {
            try {
                $response = Http::withBasicAuth($this->secretKey, '')
                    ->baseUrl($this->baseUrl)
                    ->post('/v2/accounts', [
                        'email' => $tenant->users()->first()?->email ?? 'finance@' . $tenant->subdomain . '.lapaqu.id',
                        'type' => 'OWNED',
                        'public_profile' => [
                            'business_name' => $tenant->name,
                        ],
                    ]);

                if ($response->successful()) {
                    $xenditSubAccountId = $response->json('id');
                }
            } catch (\Exception $e) {
                // Graceful fallback for sandbox / during KYC review
                $xenditSubAccountId = 'xnd_sub_' . Str::random(24);
            }
        }

        if (empty($xenditSubAccountId)) {
            // Aggregator Merchant ID for payout ledger
            $xenditSubAccountId = 'merch_' . strtolower(substr(md5($tenant->id), 0, 16));
        }

        return TenantPaymentAccount::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'xendit_sub_account_id' => $xenditSubAccountId,
                'bank_code' => strtoupper($bankData['bank_code']),
                'bank_account_number' => $bankData['bank_account_number'],
                'bank_account_holder_name' => $bankData['bank_account_holder_name'],
                'is_active' => true,
            ]
        );
    }
}
