<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use PragmaRX\Google2FALaravel\Support\Authenticator;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower($request->email);
        $throttleKey = 'login:' . $email . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'message' => 'Terlalu banyak percobaan login. Silakan coba lagi dalam ' . $seconds . ' detik.',
                'retry_after_seconds' => $seconds,
            ], 429);
        }

        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, 300); // 5 minutes decay
            return response()->json([
                'message' => 'Kombinasi email dan password tidak valid.',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Akun Anda sedang dinonaktifkan. Hubungi admin.',
            ], 403);
        }

        RateLimiter::clear($throttleKey);

        $isOwnerOrSuperadmin = $user->hasAnyRole(['owner', 'superadmin'], 'web') || $user->hasAnyRole(['owner', 'superadmin'], 'sanctum');

        // Check 2FA requirement for Owner and Superadmin
        if ($isOwnerOrSuperadmin) {
            if ($user->two_factor_secret && $user->two_factor_confirmed_at) {
                // Generate a temporary short-lived token for 2FA challenge
                $tempToken = $user->createToken('2fa-challenge', ['2fa:challenge'], now()->addMinutes(5))->plainTextToken;
                return response()->json([
                    'requires_2fa' => true,
                    'temp_token' => $tempToken,
                    'message' => 'Masukkan kode autentikasi 2FA (TOTP).',
                ]);
            }
        }

        $token = $user->createToken('auth-token', ['*'], now()->addDays(7))->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'tenant_id' => $user->tenant_id,
                'outlet_id' => $user->outlet_id ?? $user->tenant?->outlets()->first()?->id ?? \App\Models\Outlet::where('tenant_id', $user->tenant_id)->first()?->id ?? \App\Models\Outlet::first()?->id,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'two_factor_enabled' => (bool) $user->two_factor_confirmed_at,
            ],
            'tenant' => $user->tenant ? [
                'id' => $user->tenant->id,
                'name' => $user->tenant->name,
                'subdomain' => $user->tenant->subdomain,
                'status' => $user->tenant->status,
            ] : null,
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function challenge2FA(Request $request): JsonResponse
    {
        $request->validate([
            'temp_token' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($request->temp_token);

        if (!$tokenModel || !$tokenModel->can('2fa:challenge') || $tokenModel->expires_at->isPast()) {
            return response()->json(['message' => 'Token sesi 2FA tidak valid atau sudah kedaluwarsa.'], 401);
        }

        /** @var User $user */
        $user = $tokenModel->tokenable;

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($user->two_factor_secret, $request->code);

        if (!$valid) {
            return response()->json(['message' => 'Kode 2FA tidak valid.'], 422);
        }

        // Delete temporary challenge token
        $tokenModel->delete();

        // Issue permanent session token
        $token = $user->createToken('auth-token', ['*'], now()->addDays(7))->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'tenant_id' => $user->tenant_id,
                'outlet_id' => $user->outlet_id ?? $user->tenant?->outlets()->first()?->id ?? \App\Models\Outlet::where('tenant_id', $user->tenant_id)->first()?->id ?? \App\Models\Outlet::first()?->id,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'two_factor_enabled' => true,
            ],
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function setup2FA(Request $request): JsonResponse
    {
        $user = $request->user();
        $google2fa = new Google2FA();

        $secret = $google2fa->generateSecretKey();
        $user->two_factor_secret = $secret;
        $user->two_factor_confirmed_at = null;
        $user->save();

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name', 'Lapaqu'),
            $user->email,
            $secret
        );

        return response()->json([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'message' => 'Scan QR code dengan aplikasi Google Authenticator / Authy, lalu verifikasi kode.',
        ]);
    }

    public function verify2FA(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        if (!$user->two_factor_secret) {
            return response()->json(['message' => '2FA belum diinisialisasi.'], 400);
        }

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($user->two_factor_secret, $request->code);

        if (!$valid) {
            return response()->json(['message' => 'Kode verifikasi 2FA tidak valid.'], 422);
        }

        $user->two_factor_confirmed_at = now();
        $user->save();

        return response()->json([
            'message' => '2FA berhasil diaktifkan.',
            'two_factor_enabled' => true,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'tenant_id' => $user->tenant_id,
                'outlet_id' => $user->outlet_id ?? $user->tenant?->outlets()->first()?->id ?? \App\Models\Outlet::where('tenant_id', $user->tenant_id)->first()?->id ?? \App\Models\Outlet::first()?->id,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'two_factor_enabled' => (bool) $user->two_factor_confirmed_at,
            ],
            'tenant' => $user->tenant ? [
                'id' => $user->tenant->id,
                'name' => $user->tenant->name,
                'subdomain' => $user->tenant->subdomain,
                'status' => $user->tenant->status,
                'trial_ends_at' => $user->tenant->trial_ends_at,
            ] : null,
            'outlet' => $user->outlet ? [
                'id' => $user->outlet->id,
                'name' => $user->outlet->name,
                'timezone' => $user->outlet->timezone,
            ] : null,
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->user()->currentAccessToken()->delete();

        $token = $user->createToken('auth-token', ['*'], now()->addDays(7))->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower($request->email);
        $throttleKey = 'forgot-password:' . $email;

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'message' => 'Terlalu banyak permintaan reset password. Coba lagi dalam ' . ceil($seconds / 60) . ' menit.',
            ], 429);
        }

        RateLimiter::hit($throttleKey, 3600); // 1 hour decay

        $user = User::where('email', $email)->first();

        if ($user) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                [
                    'token' => Hash::make($token),
                    'created_at' => now(),
                ]
            );

            // In production: send reset email. For MVP response / testing, return success.
        }

        return response()->json([
            'message' => 'Jika email Anda terdaftar, instruksi reset password telah dikirim ke email.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $email = strtolower($request->email);
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$record || !Hash::check($request->token, $record->token)) {
            return response()->json(['message' => 'Token reset password tidak valid.'], 422);
        }

        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return response()->json(['message' => 'Token reset password sudah kedaluwarsa (berlaku 60 menit).'], 422);
        }

        $user = User::where('email', $email)->first();
        if ($user) {
            $user->password = Hash::make($request->password);
            $user->save();
            DB::table('password_reset_tokens')->where('email', $email)->delete();
        }

        return response()->json([
            'message' => 'Password berhasil direset. Silakan login kembali.',
        ]);
    }
}
