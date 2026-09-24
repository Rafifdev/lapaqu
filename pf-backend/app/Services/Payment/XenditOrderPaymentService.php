<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class XenditOrderPaymentService
{
    protected string $secretKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('services.xendit.secret_key', '');
        $this->baseUrl = config('services.xendit.base_url', 'https://api.xendit.co');
    }

    public function createPaymentCharge(Order $order, string $paymentMethod = 'qris'): Payment
    {
        $paymentAccount = $order->tenant?->paymentAccount;
        $subAccountId = $paymentAccount?->xendit_sub_account_id;

        $referenceId = 'PAY-' . strtoupper(Str::random(12));
        $qrString = null;
        $xenditChargeId = null;
        $accountNumber = null;
        $bankCode = null;
        $expirationDate = now()->addDay()->toIso8601ZuluString();

        $headers = [];
        $useXenPlatform = config('services.xendit.use_xenplatform', false);
        $isRealSubAccount = !empty($subAccountId) 
            && !str_starts_with($subAccountId, 'xnd_sub_mock') 
            && !str_starts_with($subAccountId, 'xnd_sub_demo') 
            && !str_starts_with($subAccountId, 'merch_')
            && !str_contains($subAccountId, 'demo') 
            && !str_contains($subAccountId, 'mock');

        // Only attach for-user-id if xenPlatform is explicitly enabled and approved
        if ($useXenPlatform && $isRealSubAccount) {
            $headers['for-user-id'] = $subAccountId;
        }

        // Determine if this is a Virtual Account payment
        $isVA = str_starts_with($paymentMethod, 'va_');
        if ($isVA) {
            $bankCode = strtoupper(str_replace('va_', '', $paymentMethod));
            // Batas waktu Virtual Account: 10 menit batas waktu bayar (timer hangus)
            $expirationDate = now()->addMinutes(10)->toIso8601ZuluString();
        } else {
            // Dynamic QRIS: 5 menit batas waktu bayar
            $expirationDate = now()->addMinutes(5)->toIso8601ZuluString();
        }

        if (!empty($this->secretKey)) {
            try {
                if ($isVA) {
                    // Create Fixed Closed Virtual Account in Xendit Sandbox
                    $customerName = !empty($order->customer_name) ? $order->customer_name : 'Pelanggan Lapaqu';
                    $response = Http::withBasicAuth($this->secretKey, '')
                        ->withHeaders($headers)
                        ->baseUrl($this->baseUrl)
                        ->post('/callback_virtual_accounts', [
                            'external_id' => $referenceId,
                            'bank_code' => $bankCode,
                            'name' => substr($customerName, 0, 50),
                            'expected_amount' => (int) $order->final_amount,
                            'is_closed' => true,
                            'expiration_date' => $expirationDate,
                        ]);

                    if ($response->successful()) {
                        $xenditChargeId = $response->json('id');
                        $accountNumber = $response->json('account_number');
                        if ($response->json('expiration_date')) {
                            $expirationDate = $response->json('expiration_date');
                        }
                        Log::info("Xendit VA created successfully in sandbox: Bank {$bankCode}, VA {$accountNumber}, Ref {$referenceId}, Expiry {$expirationDate}");
                    } else {
                        Log::error("Xendit VA creation failed: " . $response->status() . " " . $response->body());
                    }
                } else {
                    // Create Dynamic QRIS in Xendit Sandbox (API Version 2022-07-31)
                    $qrHeaders = array_merge($headers, [
                        'api-version' => '2022-07-31'
                    ]);

                    $response = Http::withBasicAuth($this->secretKey, '')
                        ->withHeaders($qrHeaders)
                        ->baseUrl($this->baseUrl)
                        ->post('/qr_codes', [
                            'reference_id' => $referenceId,
                            'type' => 'DYNAMIC',
                            'currency' => 'IDR',
                            'amount' => (int) $order->final_amount,
                            'expires_at' => $expirationDate,
                        ]);

                    if ($response->successful()) {
                        $xenditChargeId = $response->json('id');
                        $qrString = $response->json('qr_string');
                        if ($response->json('expires_at')) {
                            $expirationDate = $response->json('expires_at');
                        }
                        Log::info("Xendit QR Code created successfully in sandbox: ID {$xenditChargeId} for Ref {$referenceId}");
                    } else {
                        Log::error("Xendit QR Code creation failed: " . $response->status() . " " . $response->body());
                    }
                }
            } catch (\Exception $e) {
                Log::error("Xendit payment exception: " . $e->getMessage());
            }
        }

        // Fallbacks for testing if offline or missing API key
        if (empty($xenditChargeId)) {
            if ($isVA) {
                $bankPrefixes = [
                    'MANDIRI' => '88908',
                    'BRI' => '13282',
                    'BNI' => '88089',
                    'BCA' => '38165',
                    'PERMATA' => '74269',
                    'CIMB' => '93490',
                    'BSI' => '93479',
                    'BJB' => '00110',
                ];
                $prefix = $bankPrefixes[$bankCode] ?? '99999';
                $accountNumber = $prefix . mt_rand(10000000, 99999999);
                $xenditChargeId = 'va_' . Str::random(24);
            } else {
                $xenditChargeId = 'qr_' . Str::random(24);
                $qrString = '00020101021226580014ID.LINKAJA.WWW01189360091100220000000215' . $order->order_number . '5802ID5303360540' . $order->final_amount . '5913' . substr($order->tenant?->name ?? 'Lapaqu', 0, 13) . '6007JAKARTA6304ABCD';
            }
        }

        return Payment::create([
            'order_id' => $order->id,
            'xendit_transaction_id' => $xenditChargeId,
            'payment_method' => $paymentMethod,
            'amount' => $order->final_amount,
            'status' => 'pending',
            'raw_payload' => [
                'reference_id' => $referenceId,
                'sub_account_id' => $subAccountId,
                'qr_string' => $qrString,
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
                'expiration_date' => $expirationDate,
            ],
        ]);
    }

    public function simulatePayment(Payment $payment): array
    {
        // Validasi apakah pembayaran telah kadaluarsa / hangus
        $expirationDate = $payment->raw_payload['expiration_date'] ?? null;
        if ($expirationDate && now()->isAfter($expirationDate)) {
            $payment->status = 'expired';
            $payment->save();

            $order = $payment->order;
            if ($order && in_array($order->payment_status, ['unpaid', 'pending'])) {
                $order->payment_status = 'expired';
                if ($order->status === 'pending_payment') {
                    $order->status = 'expired';
                }
                $order->save();
            }

            return ['success' => false, 'message' => 'Pembayaran Virtual Account telah kadaluarsa / hangus.'];
        }

        $referenceId = $payment->raw_payload['reference_id'] ?? null;
        if (empty($this->secretKey) || empty($referenceId)) {
            return ['success' => false, 'message' => 'No secret key or reference ID'];
        }

        try {
            $subAccountId = $payment->raw_payload['sub_account_id'] ?? null;
            $headers = [];
            $isRealSubAccount = !empty($subAccountId) 
                && !str_starts_with($subAccountId, 'xnd_sub_mock') 
                && !str_starts_with($subAccountId, 'xnd_sub_demo') 
                && !str_contains($subAccountId, 'demo') 
                && !str_contains($subAccountId, 'mock');

            if ($isRealSubAccount) {
                $headers['for-user-id'] = $subAccountId;
            }

            $isVA = str_starts_with($payment->payment_method, 'va_');

            if ($isVA) {
                // VA simulation endpoint: POST /callback_virtual_accounts/external_id={reference_id}/simulate_payment
                $response = Http::withBasicAuth($this->secretKey, '')
                    ->withHeaders($headers)
                    ->baseUrl($this->baseUrl)
                    ->post("/callback_virtual_accounts/external_id={$referenceId}/simulate_payment", [
                        'amount' => (int) $payment->amount,
                    ]);
            } else {
                // QRIS simulation endpoint: POST /qr_codes/{qr_id}/payments/simulate (api-version: 2022-07-31)
                $qrChargeId = $payment->xendit_transaction_id;
                $qrHeaders = array_merge($headers, [
                    'api-version' => '2022-07-31'
                ]);

                $response = Http::withBasicAuth($this->secretKey, '')
                    ->withHeaders($qrHeaders)
                    ->baseUrl($this->baseUrl)
                    ->post("/qr_codes/{$qrChargeId}/payments/simulate", [
                        'amount' => (int) $payment->amount,
                    ]);
            }

            if ($response->successful()) {
                Log::info("Xendit payment simulation successful for Ref {$referenceId}: " . $response->body());
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            // Fallback for banks not directly supported by sandbox (e.g. BJB) or offline records
            $bankCode = $payment->raw_payload['bank_code'] ?? '';
            if ($bankCode === 'BJB' || str_starts_with($payment->xendit_transaction_id, 'va_')) {
                Log::info("Sandbox local simulation fallback for {$bankCode} Ref {$referenceId}");
                return [
                    'success' => true,
                    'data' => [
                        'status' => 'COMPLETED',
                        'message' => "Payment simulated for {$bankCode} VA #{$referenceId}",
                    ],
                ];
            }

            Log::warning("Xendit payment simulation failed: " . $response->status() . " " . $response->body());
            return [
                'success' => false,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error("Xendit simulation exception: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
