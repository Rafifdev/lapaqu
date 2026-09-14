<?php

use App\Models\AuditLog;
use App\Models\PasswordResetOtp;
use App\Models\User;
use App\Notifications\SendPasswordResetOtpNotification;
use App\Services\PasswordResetOtpService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('forgot password link is accessible and loads request page', function () {
    $response = $this->get('/pf-admin/password-reset/request');
    $response->assertOk();
});

test('requesting otp sends notification and stores hashed otp in database', function () {
    Notification::fake();

    $service = app(PasswordResetOtpService::class);
    $result = $service->sendOtp('admin@lapaqu.id');

    expect($result['success'])->toBeTrue();

    // Verify OTP record exists in DB
    $otpRecord = PasswordResetOtp::where('email', 'admin@lapaqu.id')->first();
    expect($otpRecord)->not->toBeNull()
        ->and($otpRecord->attempts)->toBe(0)
        ->and($otpRecord->expires_at->isFuture())->toBeTrue();

    // Verify Notification dispatched
    $user = User::where('email', 'admin@lapaqu.id')->first();
    Notification::assertSentTo($user, SendPasswordResetOtpNotification::class);
});

test('requesting otp for non-existent email returns generic message without error', function () {
    Notification::fake();

    $service = app(PasswordResetOtpService::class);
    $result = $service->sendOtp('fake-user-not-exists@lapaqu.id');

    expect($result['success'])->toBeTrue();
    expect(PasswordResetOtp::where('email', 'fake-user-not-exists@lapaqu.id')->count())->toBe(0);
});

test('requesting otp enforces 60-second cooldown rate limit', function () {
    Notification::fake();

    $service = app(PasswordResetOtpService::class);
    
    $first = $service->sendOtp('admin@lapaqu.id');
    expect($first['success'])->toBeTrue();

    // Immediate second request should be blocked by cooldown
    $second = $service->sendOtp('admin@lapaqu.id');
    expect($second['success'])->toBeFalse()
        ->and($second['is_cooldown'])->toBeTrue();
});

test('verifying invalid otp increments attempt counter and fails', function () {
    $service = app(PasswordResetOtpService::class);
    
    // Seed OTP directly
    $plainOtp = '654321';
    PasswordResetOtp::create([
        'email' => 'admin@lapaqu.id',
        'otp_hash' => Hash::make($plainOtp),
        'attempts' => 0,
        'expires_at' => now()->addMinutes(10),
    ]);

    $result = $service->verifyOtp('admin@lapaqu.id', '000000');
    expect($result['success'])->toBeFalse();

    $record = PasswordResetOtp::where('email', 'admin@lapaqu.id')->first();
    expect($record->attempts)->toBe(1);
});

test('verifying invalid otp 5 times destroys the otp record', function () {
    $service = app(PasswordResetOtpService::class);
    
    PasswordResetOtp::create([
        'email' => 'admin@lapaqu.id',
        'otp_hash' => Hash::make('654321'),
        'attempts' => 4,
        'expires_at' => now()->addMinutes(10),
    ]);

    $result = $service->verifyOtp('admin@lapaqu.id', '000000');
    expect($result['success'])->toBeFalse();

    // After 5th failed attempt, record is deleted
    expect(PasswordResetOtp::where('email', 'admin@lapaqu.id')->count())->toBe(0);
});

test('verifying valid otp generates reset token and deletes otp record', function () {
    $service = app(PasswordResetOtpService::class);
    
    $plainOtp = '889900';
    PasswordResetOtp::create([
        'email' => 'admin@lapaqu.id',
        'otp_hash' => Hash::make($plainOtp),
        'attempts' => 0,
        'expires_at' => now()->addMinutes(10),
    ]);

    $result = $service->verifyOtp('admin@lapaqu.id', $plainOtp);

    expect($result['success'])->toBeTrue()
        ->and($result['reset_token'])->toBeString();

    // Single-use guarantee: OTP record is deleted immediately
    expect(PasswordResetOtp::where('email', 'admin@lapaqu.id')->count())->toBe(0);
});

test('reset password updates user password and records audit log', function () {
    $service = app(PasswordResetOtpService::class);
    
    // 1. Verify OTP first
    $plainOtp = '112233';
    PasswordResetOtp::create([
        'email' => 'admin@lapaqu.id',
        'otp_hash' => Hash::make($plainOtp),
        'attempts' => 0,
        'expires_at' => now()->addMinutes(10),
    ]);

    $verifyResult = $service->verifyOtp('admin@lapaqu.id', $plainOtp);
    $token = $verifyResult['reset_token'];

    // 2. Perform Password Reset
    $newPassword = 'NewSecretPassword123!';
    $resetResult = $service->resetPassword('admin@lapaqu.id', $token, $newPassword);

    expect($resetResult['success'])->toBeTrue();

    // Verify user can login with new password
    $user = User::where('email', 'admin@lapaqu.id')->first();
    expect(Hash::check($newPassword, $user->password))->toBeTrue();

    // Verify AuditLog recorded
    $log = AuditLog::where('actor_user_id', $user->id)
        ->where('action', 'password_reset_completed')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->metadata['method'])->toBe('email_otp');
});

test('direct unsigned access to reset password page is protected', function () {
    $response = $this->get('/pf-admin/password-reset/reset');
    // Filament signed route returns 403 or redirects when signature is missing
    expect(in_array($response->getStatusCode(), [302, 403]))->toBeTrue();
});
