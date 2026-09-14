<?php

namespace App\Filament\Pages\Auth;

use App\Services\PasswordResetOtpService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\PasswordResetResponse;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Locked;
use SensitiveParameter;

class ResetPasswordWithOtp extends ResetPassword
{
    protected string $view = 'filament.pages.auth.reset-password-with-otp';

    #[Locked]
    public ?string $email = null;

    #[Locked]
    public ?string $token = null;

    public function mount(?string $email = null, #[SensitiveParameter] ?string $token = null): void
    {
        $this->email = $email ?? request()->query('email', session('password_reset_verified_email', ''));
        $this->token = $token ?? request()->query('token', session('password_reset_token', ''));

        if (empty($this->email) || empty($this->token)) {
            Notification::make()
                ->title('Akses Ditolak')
                ->body('Silakan lakukan verifikasi OTP terlebih dahulu.')
                ->danger()
                ->send();

            $this->redirect(filament()->getRequestPasswordResetUrl(), navigate: false);
            return;
        }

        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Password Baru')
            ->placeholder('Password baru')
            ->password()
            ->autocomplete('new-password')
            ->revealable()
            ->required()
            ->rule(PasswordRule::min(8)->letters()->numbers())
            ->helperText('Minimal 8 karakter, kombinasi huruf dan angka')
            ->same('passwordConfirmation')
            ->autofocus();
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return TextInput::make('passwordConfirmation')
            ->label('Konfirmasi Password Baru')
            ->placeholder('Konfirmasi password baru')
            ->password()
            ->autocomplete('new-password')
            ->revealable()
            ->required()
            ->dehydrated(false);
    }

    public function resetPassword(): ?PasswordResetResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->title('Terlalu Banyak Percobaan')
                ->body("Mohon tunggu {$exception->secondsUntilAvailable} detik sebelum mencoba kembali.")
                ->danger()
                ->send();

            return null;
        }

        $data = $this->form->getState();
        $password = $data['password'] ?? '';

        $service = app(PasswordResetOtpService::class);
        $result = $service->resetPassword($this->email, $this->token, $password);

        if (! $result['success']) {
            Notification::make()
                ->title('Gagal Mengubah Password')
                ->body($result['message'])
                ->danger()
                ->send();

            $this->redirect(filament()->getRequestPasswordResetUrl(), navigate: false);
            return null;
        }

        session()->forget(['password_reset_verified_email', 'password_reset_token']);

        Notification::make()
            ->title('Password Berhasil Diubah!')
            ->body('Silakan masuk ke platform menggunakan password baru Anda.')
            ->success()
            ->send();

        $this->redirect(filament()->getLoginUrl(), navigate: false);
        return app(PasswordResetResponse::class);
    }

    public function getResetPasswordFormAction(): Action
    {
        return Action::make('resetPassword')
            ->label('Simpan Password Baru')
            ->submit('resetPassword')
            ->color('primary')
            ->extraAttributes([
                'style' => 'width: 100%; height: 44px; font-weight: 600; border-radius: 10px; background-color: #2563eb;',
            ]);
    }

    public function getTitle(): string | Htmlable
    {
        return 'Set New Password';
    }

    public function getHeading(): string | Htmlable | null
    {
        return null;
    }

    public function getSubheading(): string | Htmlable | null
    {
        return null;
    }

    public function hasLogo(): bool
    {
        return false;
    }
}
