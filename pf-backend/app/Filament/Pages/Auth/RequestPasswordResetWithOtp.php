<?php

namespace App\Filament\Pages\Auth;

use App\Services\PasswordResetOtpService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Schemas\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;

class RequestPasswordResetWithOtp extends RequestPasswordReset
{
    protected string $view = 'filament.pages.auth.request-password-reset-otp';

    public function request(): void
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->title('Terlalu Banyak Permintaan')
                ->body("Mohon tunggu {$exception->secondsUntilAvailable} detik sebelum mencoba lagi.")
                ->danger()
                ->send();

            return;
        }

        $data = $this->form->getState();
        $email = strtolower(trim($data['email'] ?? ''));

        $service = app(PasswordResetOtpService::class);
        $result = $service->sendOtp($email);

        session(['password_reset_email' => $email]);

        if (! $result['success'] && ($result['is_cooldown'] ?? false)) {
            Notification::make()
                ->title('Tunggu Cooldown')
                ->body($result['message'])
                ->warning()
                ->send();
        } else {
            Notification::make()
                ->title('Kode OTP Dikirim')
                ->body($result['message'])
                ->success()
                ->send();
        }

        $verifyUrl = route('filament.pf-admin.auth.password-reset.verify-otp', ['email' => $email]);
        $this->redirect($verifyUrl, navigate: false);
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email')
            ->placeholder('Email address')
            ->email()
            ->required()
            ->autocomplete()
            ->autofocus();
    }

    protected function getRequestFormAction(): Action
    {
        return Action::make('request')
            ->label('Kirim Kode OTP')
            ->submit('request')
            ->color('primary')
            ->extraAttributes([
                'style' => 'width: 100%; height: 44px; font-weight: 600; border-radius: 10px; background-color: #2563eb;',
            ]);
    }

    public function getTitle(): string | Htmlable
    {
        return 'Lupa Password';
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
