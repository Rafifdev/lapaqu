<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendPasswordResetOtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $otp
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable->name ?? 'Pengguna';

        return (new MailMessage)
            ->subject('[Lapaqu Platform] Kode OTP Reset Password Anda')
            ->greeting("Halo, {$name}!")
            ->line('Kami menerima permintaan untuk mereset password akun Anda di Lapaqu Platform.')
            ->line('Berikut adalah kode One-Time Password (OTP) Anda:')
            ->line("# **{$this->otp}**")
            ->line('Kode OTP ini hanya berlaku selama **10 menit**. Jangan berikan kode ini kepada siapapun demi keamanan akun Anda.')
            ->line('Jika Anda tidak pernah meminta reset password, abaikan email ini. Akun Anda tetap aman dan tidak ada perubahan yang terjadi.')
            ->salutation('Salam hormat,
Tim Keamanan Lapaqu Platform');
    }
}
