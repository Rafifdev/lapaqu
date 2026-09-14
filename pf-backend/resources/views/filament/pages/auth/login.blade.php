<style>
        :root {
            --auth-heading-color: #111827;
            --auth-text-muted: #6b7280;
            --auth-strong-color: #111827;
            --auth-divider: #e5e7eb;
        }
        .dark {
            --auth-heading-color: #ffffff;
            --auth-text-muted: #9ca3af;
            --auth-strong-color: #f3f4f6;
            --auth-divider: rgba(75, 85, 99, 0.3);
        }
    </style>
<x-filament-panels::page.simple>
    <div style="width: 100%;">
        {{-- Header matching sample layout --}}
        <div style="margin-bottom: 24px; text-align: left;">
            <h1 style="font-size: 24px; font-weight: 800; color: var(--auth-heading-color, #111827); margin: 0 0 6px 0; letter-spacing: -0.02em;">
                Sign in to Lapaqu Platform.
            </h1>
            <p style="font-size: 13px; color: var(--auth-text-muted, #6b7280); margin: 0; line-height: 1.5;">
                Portal Superadmin & Manajemen Platform
            </p>
        </div>

        {{-- Filament Form & Actions --}}
        {{ $this->content }}
    </div>
</x-filament-panels::page.simple>
