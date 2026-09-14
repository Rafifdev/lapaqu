<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PasswordResetOtp;
use App\Models\User;
use App\Notifications\SendPasswordResetOtpNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetOtpService
{
    public const OTP_EXPIRY_MINUTES = 10;
    public const MAX_ATTEMPTS = 5;
    public const COOLDOWN_SECONDS = 60;

    public function getRemainingCooldown(string $email): int
    {
        $email = strtolower(trim($email));
        $cooldownKey = "otp_cooldown_{$email}";
        if (Cache::has($cooldownKey)) {
            $expiry = Cache::get("otp_cooldown_expiry_{$email}", time() + self::COOLDOWN_SECONDS);
            return max(0, $expiry - time());
        }

        return 0;
    }

    public function sendOtp(string $email): array
    {
        $email = strtolower(trim($email));

        $cooldownKey = "otp_cooldown_{$email}";
        if (Cache::has($cooldownKey)) {
            $remaining = Cache::get("otp_cooldown_expiry_{$email}", self::COOLDOWN_SECONDS) - time();
            $remaining = max(1, $remaining);
            return [
                'success' => false,
                'is_cooldown' => true,
                'remaining_seconds' => $remaining,
                'message' => "Mohon tunggu {$remaining} detik sebelum meminta kode OTP kembali.",
            ];
        }

        $user = User::where('email', $email)->first();

        // Always set cooldown to prevent email flooding
        Cache::put($cooldownKey, true, self::COOLDOWN_SECONDS);
        Cache::put("otp_cooldown_expiry_{$email}", time() + self::COOLDOWN_SECONDS, self::COOLDOWN_SECONDS);

        if ($user) {
            // Delete previous active OTPs
            PasswordResetOtp::where('email', $email)->delete();

            // Generate secure 6-digit integer string
            $plainOtp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            PasswordResetOtp::create([
                'email' => $email,
                'otp_hash' => Hash::make($plainOtp),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
            ]);

            $user->notifyNow(new SendPasswordResetOtpNotification($plainOtp));
        }

        return [
            'success' => true,
            'is_cooldown' => false,
            'message' => 'Jika email terdaftar, kode verifikasi OTP telah dikirimkan ke kotak masuk email Anda.',
        ];
    }

    public function verifyOtp(string $email, string $otp): array
    {
        $email = strtolower(trim($email));
        $otp = trim($otp);

        $record = PasswordResetOtp::where('email', $email)->latest()->first();

        if (! $record || $record->isExpired()) {
            if ($record) {
                $record->delete();
            }
            return [
                'success' => false,
                'message' => 'Kode OTP salah atau sudah kedaluwarsa.',
            ];
        }

        if ($record->hasExceededMaxAttempts(self::MAX_ATTEMPTS)) {
            $record->delete();
            return [
                'success' => false,
                'message' => 'Batas percobaan verifikasi telah habis. Silakan minta kode OTP baru.',
            ];
        }

        if (! Hash::check($otp, $record->otp_hash)) {
            $record->increment('attempts');
            if ($record->attempts >= self::MAX_ATTEMPTS) {
                $record->delete();
                return [
                    'success' => false,
                    'message' => 'Batas percobaan verifikasi telah habis. Silakan minta kode OTP baru.',
                ];
            }

            return [
                'success' => false,
                'message' => 'Kode OTP salah atau sudah kedaluwarsa.',
            ];
        }

        // OTP is correct -> One-time use deletion
        $record->delete();

        // Generate temporary signed reset token valid for 10 minutes
        $resetToken = Str::random(64);
        Cache::put("password_reset_token_{$email}", $resetToken, now()->addMinutes(10));

        return [
            'success' => true,
            'reset_token' => $resetToken,
            'message' => 'Kode OTP berhasil diverifikasi.',
        ];
    }

    public function resetPassword(string $email, string $token, string $newPassword): array
    {
        $email = strtolower(trim($email));
        $cachedToken = Cache::get("password_reset_token_{$email}");

        if (! $cachedToken || ! hash_equals($cachedToken, $token)) {
            return [
                'success' => false,
                'message' => 'Sesi reset password tidak valid atau sudah kedaluwarsa. Silakan ulangi proses.',
            ];
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return [
                'success' => false,
                'message' => 'Pengguna tidak ditemukan.',
            ];
        }

        $user->password = Hash::make($newPassword);
        $user->setRememberToken(Str::random(60));
        $user->save();

        // Invalidate Sanctum tokens & sessions
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        try {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        } catch (\Throwable) {
            // sessions table might be database or file
        }

        // Remove verification token
        Cache::forget("password_reset_token_{$email}");

        // Record Audit Log
        AuditLog::create([
            'tenant_id' => $user->tenant_id,
            'actor_user_id' => $user->id,
            'action' => 'password_reset_completed',
            'target_type' => 'user',
            'target_id' => $user->id,
            'metadata' => [
                'method' => 'email_otp',
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
            'created_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => 'Password berhasil diperbarui! Silakan masuk dengan password baru Anda.',
        ];
    }
}
