<?php

namespace App\Notifications;

use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RefundStatusUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(public RefundRequest $refundRequest, public string $decision, public ?string $reason = null)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'refund_status_updated',
            'title' => 'Status Pengajuan Refund Diperbarui',
            'decision' => $this->decision,
            'message' => "Pengajuan refund sebesar Rp " . number_format($this->refundRequest->amount, 0, ',', '.') . " untuk order {$this->refundRequest->order->order_number} telah " . ($this->decision === 'approved' ? 'disetujui' : 'ditolak') . ".",
            'refund_request_id' => $this->refundRequest->id,
            'order_id' => $this->refundRequest->order_id,
        ];
    }
}
