<?php

namespace App\Services;

use App\Notifications\SendRegistrationOtpNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class RegistrationOtpService
{
    public const OTP_EXPIRY_MINUTES = 10;
    public const MAX_ATTEMPTS = 5;
    public const COOLDOWN_SECONDS = 60;

    public function getRemainingCooldown(string $email): int
    {
        $email = strtolower(trim($email));
        $cooldownKey = "reg_otp_cooldown_{$email}";
        if (Cache::has($cooldownKey)) {
            $expiry = Cache::get("reg_otp_cooldown_expiry_{$email}", time() + self::COOLDOWN_SECONDS);
            return max(0, $expiry - time());
        }

        return 0;
    }

    public function sendOtp(string $email, string $name = 'Pengguna'): array
    {
        $email = strtolower(trim($email));

        $remaining = $this->getRemainingCooldown($email);
        if ($remaining > 0) {
            return [
                'success' => false,
                'is_cooldown' => true,
                'remaining_seconds' => $remaining,
                'message' => "Mohon tunggu {$remaining} detik sebelum meminta kode OTP kembali.",
            ];
        }

        // Set cooldown
        Cache::put("reg_otp_cooldown_{$email}", true, self::COOLDOWN_SECONDS);
        Cache::put("reg_otp_cooldown_expiry_{$email}", time() + self::COOLDOWN_SECONDS, self::COOLDOWN_SECONDS);

        // Generate 6-digit OTP
        $plainOtp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store hashed OTP in cache
        Cache::put("reg_otp_data_{$email}", [
            'otp_hash' => Hash::make($plainOtp),
            'attempts' => 0,
        ], now()->addMinutes(self::OTP_EXPIRY_MINUTES));

        Log::info("[RegistrationOtp] Sent OTP for {$email}: {$plainOtp}");

        // Dispatch Notification via default Laravel Mailer
        try {
            Notification::route('mail', $email)->notify(new SendRegistrationOtpNotification($plainOtp, $name));
        } catch (\Throwable $e) {
            Log::error("[RegistrationOtp] Failed to send email to {$email}: " . $e->getMessage());
        }

        return [
            'success' => true,
            'is_cooldown' => false,
            'message' => 'Kode verifikasi OTP telah dikirimkan ke email Anda.',
        ];
    }

    public function verifyOtp(string $email, string $otp): array
    {
        $email = strtolower(trim($email));
        $otp = trim($otp);

        $data = Cache::get("reg_otp_data_{$email}");

        if (! $data || empty($data['otp_hash'])) {
            return [
                'success' => false,
                'message' => 'Kode OTP salah atau telah kedaluwarsa. Silakan minta kode baru.',
            ];
        }

        if (($data['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
            Cache::forget("reg_otp_data_{$email}");
            return [
                'success' => false,
                'message' => 'Batas percobaan verifikasi telah habis. Silakan minta kode OTP baru.',
            ];
        }

        if (! Hash::check($otp, $data['otp_hash'])) {
            $data['attempts'] = ($data['attempts'] ?? 0) + 1;
            if ($data['attempts'] >= self::MAX_ATTEMPTS) {
                Cache::forget("reg_otp_data_{$email}");
                return [
                    'success' => false,
                    'message' => 'Batas percobaan verifikasi telah habis. Silakan minta kode OTP baru.',
                ];
            }

            Cache::put("reg_otp_data_{$email}", $data, now()->addMinutes(self::OTP_EXPIRY_MINUTES));

            return [
                'success' => false,
                'message' => 'Kode OTP yang Anda masukkan salah.',
            ];
        }

        // OTP verified successfully -> clear cache
        Cache::forget("reg_otp_data_{$email}");
        Cache::forget("reg_otp_cooldown_{$email}");
        Cache::forget("reg_otp_cooldown_expiry_{$email}");

        return [
            'success' => true,
            'message' => 'Kode OTP berhasil diverifikasi.',
        ];
    }
}
