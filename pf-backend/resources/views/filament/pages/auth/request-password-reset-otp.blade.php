<x-filament-panels::page.simple>
    <style>
        :root {
            --auth-heading-color: #111827;
            --auth-text-muted: #6b7280;
            --auth-divider: #e5e7eb;
        }
        .dark {
            --auth-heading-color: #ffffff;
            --auth-text-muted: #9ca3af;
            --auth-divider: rgba(75, 85, 99, 0.3);
        }
        .fi-simple-main {
            padding-bottom: 1.25rem !important;
        }
        .fi-simple-page-content {
            padding-bottom: 0 !important;
        }
    </style>

    <div style="width: 100%;">
        {{-- Header --}}
        <div style="margin-bottom: 24px; text-align: left;">
            <h1 style="font-size: 24px; font-weight: 800; color: var(--auth-heading-color); margin: 0 0 6px 0; letter-spacing: -0.02em;">
                Reset Password.
            </h1>
            <p style="font-size: 13px; color: var(--auth-text-muted); margin: 0; line-height: 1.5;">
                Masukkan email akun Anda untuk menerima kode OTP verifikasi.
            </p>
        </div>

        {{-- Filament Form & Actions --}}
        {{ $this->content }}

        {{-- Sub-actions Centered below divider --}}
        <div style="text-align: center; margin-top: 20px; padding-top: 14px; border-top: 1px solid var(--auth-divider); width: 100%; padding-bottom: 2px;">
            <a
                href="{{ filament()->getLoginUrl() }}"
                style="font-size: 12px; color: var(--auth-text-muted); text-decoration: none; transition: color 0.15s ease;"
                onmouseover="this.style.color='#3b82f6'; this.style.textDecoration='underline';"
                onmouseout="this.style.color='var(--auth-text-muted)'; this.style.textDecoration='none';"
            >
                Kembali ke Halaman Masuk
            </a>
        </div>
    </div>
</x-filament-panels::page.simple>
