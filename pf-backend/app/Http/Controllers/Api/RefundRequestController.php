<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\RefundRequest;
use App\Models\SettlementLog;
use App\Notifications\RefundStatusUpdatedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RefundRequestController extends Controller
{
    public function store(Request $request, string $orderId): JsonResponse
    {
        $order = Order::with('payments')->findOrFail($orderId);

        if ($order->payment_status !== 'paid') {
            return response()->json(['message' => 'Hanya pesanan yang sudah lunas yang dapat diajukan refund.'], 422);
        }

        if ($order->status === 'refunded') {
            return response()->json(['message' => 'Pesanan ini sudah di-refund sebelumnya.'], 422);
        }

        $existingPending = RefundRequest::where('order_id', $order->id)
            ->where('status', 'pending')
            ->exists();

        if ($existingPending) {
            return response()->json(['message' => 'Masih ada pengajuan refund yang menunggu persetujuan untuk pesanan ini.'], 422);
        }

        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'amount' => ['nullable', 'integer', 'min:1', 'max:' . $order->final_amount],
        ]);

        $refundAmount = $request->input('amount', $order->final_amount);

        $refund = RefundRequest::create([
            'order_id' => $order->id,
            'requested_by_user_id' => $request->user()->id,
            'amount' => $refundAmount,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Pengajuan refund berhasil dikirim. Menunggu persetujuan owner.',
            'refund_request' => $refund->load(['order', 'requestedBy']),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');

        $query = RefundRequest::with(['order.table', 'order.outlet', 'requestedBy', 'reviewedBy'])
            ->whereHas('order', function ($q) use ($request) {
                if ($request->user()->tenant_id) {
                    $q->where('tenant_id', $request->user()->tenant_id);
                }
            });

        if ($status) {
            $query->where('status', $status);
        }

        $refunds = $query->orderByDesc('created_at')->paginate(20);

        return response()->json(['refund_requests' => $refunds]);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $refund = RefundRequest::with(['order.payments', 'requestedBy'])->findOrFail($id);

        if ($refund->status !== 'pending') {
            return response()->json(['message' => 'Pengajuan refund ini sudah diproses sebelumnya.'], 422);
        }

        $user = $request->user();

        DB::transaction(function () use ($refund, $user) {
            $refund->update([
                'status' => 'approved',
                'reviewed_by_user_id' => $user->id,
                'reviewed_at' => now(),
                'processed_at' => now(),
                'xendit_refund_id' => 'rf_' . Str::random(24),
            ]);

            $order = $refund->order;
            $order->update([
                'status' => 'refunded',
            ]);

            foreach ($order->payments as $payment) {
                $payment->update([
                    'status' => 'refunded',
                ]);
            }

            // Adjust settlement log
            $paymentAccount = $order->tenant->paymentAccount;
            if ($paymentAccount) {
                SettlementLog::create([
                    'tenant_payment_account_id' => $paymentAccount->id,
                    'order_id' => $order->id,
                    'gross_amount' => -$refund->amount,
                    'platform_fee' => 0,
                    'net_amount' => -$refund->amount,
                    'status' => 'settled',
                    'type' => 'refund_reversal',
                    'settled_at' => now(),
                ]);
            }

            // Audit Trail
            AuditLog::create([
                'tenant_id' => $order->tenant_id,
                'actor_user_id' => $user->id,
                'action' => 'order.refund.approved',
                'target_type' => 'Order',
                'target_id' => $order->id,
                'metadata' => [
                    'refund_request_id' => $refund->id,
                    'amount' => $refund->amount,
                    'reason' => $refund->reason,
                ],
                'created_at' => now(),
            ]);
        });

        if ($refund->requestedBy) {
            $refund->requestedBy->notify(new RefundStatusUpdatedNotification($refund, 'approved'));
        }

        return response()->json([
            'message' => 'Pengajuan refund berhasil disetujui.',
            'refund_request' => $refund->fresh(['order.payments', 'reviewedBy']),
        ]);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $refund = RefundRequest::with('requestedBy')->findOrFail($id);

        if ($refund->status !== 'pending') {
            return response()->json(['message' => 'Pengajuan refund ini sudah diproses sebelumnya.'], 422);
        }

        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $user = $request->user();

        $refund->update([
            'status' => 'rejected',
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'reason' => $refund->reason . ' [Alasan Penolakan: ' . $request->rejection_reason . ']',
        ]);

        // Audit Trail
        AuditLog::create([
            'tenant_id' => $refund->order->tenant_id,
            'actor_user_id' => $user->id,
            'action' => 'order.refund.rejected',
            'target_type' => 'RefundRequest',
            'target_id' => $refund->id,
            'metadata' => [
                'rejection_reason' => $request->rejection_reason,
            ],
            'created_at' => now(),
        ]);

        if ($refund->requestedBy) {
            $refund->requestedBy->notify(new RefundStatusUpdatedNotification($refund, 'rejected', $request->rejection_reason));
        }

        return response()->json([
            'message' => 'Pengajuan refund telah ditolak.',
            'refund_request' => $refund->fresh(['reviewedBy']),
        ]);
    }
}
