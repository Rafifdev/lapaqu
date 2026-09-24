<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendRegistrationOtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $otp,
        public string $name = 'Pengguna'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[Lapaqu] Kode Verifikasi Pendaftaran Akun')
            ->greeting("Halo, {$this->name}!")
            ->line('Terima kasih telah mendaftar di Lapaqu POS SaaS.')
            ->line('Gunakan kode One-Time Password (OTP) berikut untuk memverifikasi pendaftaran akun Anda:')
            ->line("# **{$this->otp}**")
            ->line('Kode OTP ini berlaku selama **10 menit**. Jangan berikan kode ini kepada siapapun demi keamanan.')
            ->line('Jika Anda tidak merasa melakukan pendaftaran di Lapaqu, silakan abaikan email ini.')
            ->salutation("Salam hangat,\nTim Lapaqu Platform");
    }
}
