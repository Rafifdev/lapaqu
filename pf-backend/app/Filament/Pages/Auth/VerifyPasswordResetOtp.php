<?php

namespace App\Filament\Pages\Auth;

use App\Services\PasswordResetOtpService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Schemas\Concerns\RestrictsFileUploadsToSchemaComponents;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Locked;

/**
 * @property-read Schema $form
 */
class VerifyPasswordResetOtp extends SimplePage
{
    use RestrictsFileUploadsToSchemaComponents;
    use WithRateLimiting;

    protected string $view = 'filament.pages.auth.verify-password-reset-otp';

    #[Locked]
    public ?string $email = '';

    public ?string $otp = '';

    public ?array $data = [];

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }

        $this->email = request()->query('email', session('password_reset_email', ''));

        if (empty($this->email)) {
            $this->redirect(filament()->getRequestPasswordResetUrl(), navigate: false);
            return;
        }

        $this->form->fill();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function verify(?string $enteredOtp = null): void
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->dispatch('otp-verification-failed', message: "Mohon tunggu {$exception->secondsUntilAvailable} detik sebelum mencoba lagi.");
            Notification::make()
                ->title('Terlalu Banyak Percobaan')
                ->body("Mohon tunggu {$exception->secondsUntilAvailable} detik sebelum mencoba kembali.")
                ->danger()
                ->send();

            return;
        }

        $otp = trim($enteredOtp ?? $this->otp ?? ($this->data['otp'] ?? ''));

        $service = app(PasswordResetOtpService::class);
        $result = $service->verifyOtp($this->email, $otp);

        if (! $result['success']) {
            $this->dispatch('otp-verification-failed', message: $result['message']);
            Notification::make()
                ->title('Verifikasi Gagal')
                ->body($result['message'])
                ->danger()
                ->send();

            return;
        }

        $this->dispatch('otp-verification-success');

        // Store reset token and email in session
        session([
            'password_reset_verified_email' => $this->email,
            'password_reset_token' => $result['reset_token'],
        ]);

        Notification::make()
            ->title('OTP Terverifikasi')
            ->body('Silakan buat password baru untuk akun Anda.')
            ->success()
            ->send();

        // Generate signed route for password reset
        $resetUrl = URL::signedRoute('filament.pf-admin.auth.password-reset.reset', [
            'email' => $this->email,
            'token' => $result['reset_token'],
        ]);

        $this->redirect($resetUrl, navigate: false);
    }

    public function resendOtp(): void
    {
        $email = $this->email ?: session('password_reset_email', '') ?: request()->query('email', '');
        if (empty($email)) {
            Notification::make()
                ->title('Gagal Mengirim Ulang')
                ->body('Alamat email tidak ditemukan. Silakan ulangi proses.')
                ->danger()
                ->send();
            return;
        }

        $this->email = $email;
        $service = app(PasswordResetOtpService::class);
        $result = $service->sendOtp($email);

        if (! $result['success'] && ($result['is_cooldown'] ?? false)) {
            Notification::make()
                ->title('Tunggu Cooldown')
                ->body($result['message'])
                ->warning()
                ->send();

            return;
        }

        $this->dispatch('otp-resend-started');

        Notification::make()
            ->title('Kode Baru Dikirim')
            ->body($result['message'])
            ->success()
            ->send();
    }

    public function getTitle(): string | Htmlable
    {
        return 'Verifikasi OTP';
    }

    public function getHeading(): string | Htmlable | null
    {
        return null;
    }

    public function hasLogo(): bool
    {
        return false;
    }
}
