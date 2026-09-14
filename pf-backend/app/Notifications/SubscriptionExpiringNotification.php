<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(public Subscription $subscription, public int $daysRemaining)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription_expiring',
            'title' => 'Masa Langganan Hampir Habis',
            'message' => "Masa aktif langganan toko Anda akan berakhir dalam {$this->daysRemaining} hari. Segera lakukan perpanjangan.",
            'subscription_id' => $this->subscription->id,
            'days_remaining' => $this->daysRemaining,
        ];
    }
}
