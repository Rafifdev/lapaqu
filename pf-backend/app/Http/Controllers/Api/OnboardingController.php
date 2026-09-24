<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\Outlet;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Table;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RegistrationOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class OnboardingController extends Controller
{
    public function sendOtp(Request $request, RegistrationOtpService $otpService): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'name' => ['required', 'string', 'max:150'],
        ], [
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'email.unique' => 'Alamat email ini sudah terdaftar. Silakan masuk atau gunakan email lain.',
            'name.required' => 'Nama lengkap wajib diisi.',
        ]);

        $result = $otpService->sendOtp($request->email, $request->name);

        if (! $result['success'] && ! empty($result['is_cooldown'])) {
            return response()->json([
                'message' => $result['message'],
                'remaining_seconds' => $result['remaining_seconds'],
            ], 429);
        }

        return response()->json([
            'message' => $result['message'],
        ]);
    }

    public function register(Request $request, RegistrationOtpService $otpService): JsonResponse
    {
        $request->validate([
            'restaurant_name' => ['required', 'string', 'max:150'],
            'subdomain' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                'unique:tenants,subdomain',
                'not_in:admin,api,app,pos,kds,owner,superadmin,billing,www,mail,auth,test',
            ],
            'owner_name' => ['required', 'string', 'max:150'],
            'owner_email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'owner_password' => ['required', 'string', Password::min(8)->letters()->numbers()],
            'otp' => ['required', 'string', 'size:6'],
            'phone' => ['nullable', 'string', 'max:30'],
            'plan_code' => ['nullable', 'string', 'exists:plans,code'],
        ], [
            'owner_email.unique' => 'Alamat email ini sudah terdaftar.',
            'otp.required' => 'Kode OTP wajib diisi.',
            'otp.size' => 'Kode OTP harus 6 digit angka.',
        ]);

        // Verifikasi OTP
        $otpVerification = $otpService->verifyOtp($request->owner_email, $request->otp);
        if (! $otpVerification['success']) {
            return response()->json([
                'message' => $otpVerification['message'],
                'errors' => [
                    'otp' => [$otpVerification['message']],
                ],
            ], 422);
        }

        $subdomain = strtolower($request->subdomain);
        $planCode = $request->input('plan_code', 'basic');
        $plan = Plan::where('code', $planCode)->firstOrFail();

        $result = DB::transaction(function () use ($request, $subdomain, $plan) {
            // 1. Create Tenant (Trial 14 Days)
            $tenant = Tenant::create([
                'name' => $request->restaurant_name,
                'subdomain' => $subdomain,
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(14),
            ]);

            // 2. Create Owner User
            $owner = User::create([
                'tenant_id' => $tenant->id,
                'name' => $request->owner_name,
                'email' => strtolower($request->owner_email),
                'password' => Hash::make($request->owner_password),
                'phone' => $request->phone,
                'is_active' => true,
            ]);
            $owner->email_verified_at = now();
            $owner->save();
            $owner->assignRole('owner');

            // 3. Create Subscription
            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => 'trial',
                'active_outlets_count' => 1,
                'current_period_start' => now(),
                'current_period_end' => now()->addDays(14),
                'next_billing_date' => now()->addDays(14),
            ]);

            // 4. Create Starter Outlet
            $outlet = Outlet::create([
                'tenant_id' => $tenant->id,
                'name' => 'Cabang Utama',
                'timezone' => 'Asia/Jakarta',
                'is_active' => true,
            ]);

            $owner->outlet_id = $outlet->id;
            $owner->save();

            // Starter staff tidak dibuat otomatis saat onboarding (staff ditambahkan mandiri oleh owner)

            // 5. Create 5 Default Tables (T1 - T5)
            for ($i = 1; $i <= 5; $i++) {
                Table::create([
                    'tenant_id' => $tenant->id,
                    'outlet_id' => $outlet->id,
                    'table_number' => 'T' . $i,
                    'capacity' => 4,
                    'qr_code_token' => Str::random(32),
                    'is_active' => true,
                ]);
            }

            // 6. Create Starter Menu Categories
            $categories = ['Makanan Utama', 'Minuman', 'Camilan'];
            foreach ($categories as $index => $catName) {
                MenuCategory::create([
                    'tenant_id' => $tenant->id,
                    'outlet_id' => $outlet->id,
                    'name' => $catName,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]);
            }

            // 7. Generate Sanctum Token
            $token = $owner->createToken('auth-token', ['*'], now()->addDays(7))->plainTextToken;

            return [
                'tenant' => $tenant,
                'user' => $owner,
                'outlet' => $outlet,
                'subscription' => $subscription,
                'token' => $token,
            ];
        });

        return response()->json([
            'message' => 'Registrasi resto berhasil! Masa trial 14 hari telah aktif.',
            'tenant' => [
                'id' => $result['tenant']->id,
                'name' => $result['tenant']->name,
                'subdomain' => $result['tenant']->subdomain,
                'status' => $result['tenant']->status,
                'trial_ends_at' => $result['tenant']->trial_ends_at,
            ],
            'user' => [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
                'roles' => $result['user']->getRoleNames(),
            ],
            'outlet' => [
                'id' => $result['outlet']->id,
                'name' => $result['outlet']->name,
            ],
            'token' => $result['token'],
            'token_type' => 'Bearer',
        ], 201);
    }
}
