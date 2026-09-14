<x-filament-panels::page.simple>
    <style>
        :root {
            --auth-heading-color: #111827;
            --auth-text-muted: #6b7280;
            --auth-strong-color: #111827;
            --auth-input-bg: #f9fafb;
            --auth-input-border: #d1d5db;
            --auth-input-text: #111827;
            --auth-divider: #e5e7eb;
        }
        .dark {
            --auth-heading-color: #ffffff;
            --auth-text-muted: #9ca3af;
            --auth-strong-color: #f3f4f6;
            --auth-input-bg: rgba(255, 255, 255, 0.05);
            --auth-input-border: rgba(255, 255, 255, 0.15);
            --auth-input-text: #ffffff;
            --auth-divider: rgba(75, 85, 99, 0.3);
        }
        .fi-simple-main {
            padding-bottom: 1.25rem !important;
        }
        .fi-simple-page-content {
            padding-bottom: 0 !important;
        }
        .otp-boxes-wrapper {
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
            gap: 8px !important;
            margin: 16px 0 36px 0 !important;
            width: 100% !important;
        }
        .otp-single-box {
            width: 44px !important;
            height: 54px !important;
            text-align: center !important;
            font-size: 22px !important;
            font-weight: 700 !important;
            border-radius: 10px !important;
            border: 1.5px solid var(--auth-input-border) !important;
            background-color: var(--auth-input-bg) !important;
            color: var(--auth-input-text) !important;
            outline: none !important;
            transition: all 0.15s ease !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08) !important;
        }
        .otp-single-box:focus {
            border-color: #3b82f6 !important;
            background-color: rgba(59, 130, 246, 0.1) !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25) !important;
        }
        .otp-single-box.error {
            border-color: #ef4444 !important;
            color: #ef4444 !important;
            background-color: rgba(239, 68, 68, 0.1) !important;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2) !important;
        }
        .otp-single-box.success {
            border-color: #22c55e !important;
            color: #22c55e !important;
            background-color: rgba(34, 197, 94, 0.1) !important;
        }
        @keyframes subtle-shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }
        .shake-animation {
            animation: subtle-shake 0.35s ease-in-out;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @media (max-width: 480px) {
            .otp-single-box {
                width: 38px !important;
                height: 48px !important;
                font-size: 18px !important;
                border-radius: 8px !important;
            }
            .otp-boxes-wrapper {
                gap: 5px !important;
            }
        }
    </style>

    @php
        $remainingCooldown = app(\App\Services\PasswordResetOtpService::class)->getRemainingCooldown($email ?? '');
    @endphp

    <div
        x-data="{
            digits: ['', '', '', '', '', ''],
            otp: '',
            isSubmitting: false,
            hasError: false,
            isSuccess: false,
            errorMessage: '',
            cooldown: {{ $remainingCooldown }},
            timer: null,

            init() {
                if (this.cooldown > 0) {
                    this.startCooldown();
                }
                this.$nextTick(() => {
                    this.$refs.box0?.focus();
                });

                window.addEventListener('otp-verification-failed', (event) => {
                    this.hasError = true;
                    this.errorMessage = event.detail?.message || 'Kode OTP salah atau sudah kedaluwarsa.';
                    this.digits = ['', '', '', '', '', ''];
                    this.isSubmitting = false;
                    this.$nextTick(() => {
                        this.$refs.box0?.focus();
                    });
                    setTimeout(() => {
                        this.hasError = false;
                    }, 3000);
                });

                window.addEventListener('otp-verification-success', () => {
                    this.isSuccess = true;
                });

                window.addEventListener('otp-resend-started', () => {
                    this.cooldown = 60;
                    this.startCooldown();
                });
            },

            startCooldown() {
                if (this.timer) clearInterval(this.timer);
                this.timer = setInterval(() => {
                    if (this.cooldown > 0) {
                        this.cooldown--;
                    }
                    if (this.cooldown <= 0) {
                        this.cooldown = 0;
                        clearInterval(this.timer);
                    }
                }, 1000);
            },

            handleInput(index, event) {
                const val = event.target.value.replace(/[^0-9]/g, '');
                if (val.length > 0) {
                    this.digits[index] = val.slice(-1);
                    if (index < 5) {
                        this.$refs['box' + (index + 1)]?.focus();
                    }
                } else {
                    this.digits[index] = '';
                }
                this.syncAndCheck();
            },

            handleKeydown(index, event) {
                if (event.key === 'Backspace') {
                    if (!this.digits[index] && index > 0) {
                        this.$refs['box' + (index - 1)]?.focus();
                        this.digits[index - 1] = '';
                        this.syncAndCheck();
                    }
                }
            },

            handlePaste(event) {
                event.preventDefault();
                const pasteData = (event.clipboardData || window.clipboardData).getData('text').trim();
                const numbers = pasteData.replace(/[^0-9]/g, '').slice(0, 6);
                if (numbers.length > 0) {
                    for (let i = 0; i < 6; i++) {
                        this.digits[i] = numbers[i] || '';
                    }
                    const nextIndex = Math.min(numbers.length, 5);
                    this.$refs['box' + nextIndex]?.focus();
                    this.syncAndCheck();
                }
            },

            syncAndCheck() {
                this.otp = this.digits.join('');
                if (this.otp.length === 6 && !this.isSubmitting) {
                    this.submit();
                }
            },

            submit() {
                if (this.otp.length === 6 && !this.isSubmitting) {
                    this.isSubmitting = true;
                    $wire.verify(this.otp);
                }
            }
        }"
        style="width: 100%;"
    >
        {{-- Header --}}
        <div style="margin-bottom: 20px; text-align: left;">
            <h1 style="font-size: 24px; font-weight: 800; color: var(--auth-heading-color); margin: 0 0 6px 0; letter-spacing: -0.02em;">
                Enter OTP Code
            </h1>
            <p style="font-size: 13px; color: var(--auth-text-muted); line-height: 1.5; margin: 0;">
                Cek email kamu! Kami telah mengirimkan kode verifikasi 6 digit ke <strong style="color: var(--auth-strong-color);">{{ $email }}</strong>. Masukkan kode di bawah untuk memverifikasi akun Anda.
            </p>
        </div>

        {{-- Form & Segmented Inputs --}}
        <form @submit.prevent="submit" style="width: 100%; display: flex; flex-direction: column;">
            {{-- 6 Pill Segmented Boxes --}}
            <div>
                <div
                    class="otp-boxes-wrapper"
                    :class="{ 'shake-animation': hasError }"
                >
                    @for ($i = 0; $i < 6; $i++)
                        <input
                            x-ref="box{{ $i }}"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="1"
                            :value="digits[{{ $i }}]"
                            @input="handleInput({{ $i }}, $event)"
                            @keydown="handleKeydown({{ $i }}, $event)"
                            @paste="handlePaste($event)"
                            class="otp-single-box"
                            :class="{
                                'error': hasError,
                                'success': isSuccess
                            }"
                        />
                    @endfor
                </div>

                {{-- Error State Message --}}
                <div x-show="hasError" x-cloak style="text-align: left; margin-top: -24px; margin-bottom: 24px;">
                    <p x-text="errorMessage" style="font-size: 12px; font-weight: 600; color: #ef4444; margin: 0;"></p>
                </div>

                {{-- Row below boxes: Left = Countdown, Right = Kirim ulang --}}
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; margin-bottom: 24px; font-size: 13px;">
                    <div style="color: var(--auth-text-muted); font-size: 13px;">
                        <span x-show="cooldown > 0" x-cloak>
                            Kirim ulang kode dalam <span x-text="cooldown"></span> detik
                        </span>
                        <span x-show="cooldown === 0" x-cloak style="color: var(--auth-text-muted);">
                            Kode belum sampai?
                        </span>
                    </div>

                    <div>
                        <button
                            type="button"
                            @click="if (cooldown === 0) { $wire.resendOtp(); }"
                            :disabled="cooldown > 0"
                            style="font-size: 13px; font-weight: 600; background: transparent; border: none; padding: 0; transition: all 0.15s ease;"
                            :style="cooldown > 0 ? 'color: var(--auth-text-muted); cursor: not-allowed; opacity: 0.5;' : 'color: #3b82f6; cursor: pointer; text-decoration: underline;'"
                        >
                            kirim ulang
                        </button>
                    </div>
                </div>
            </div>

            {{-- Submit Button --}}
            <div style="width: 100%;">
                <button
                    type="submit"
                    :disabled="digits.join('').length < 6 || isSubmitting"
                    style="width: 100%; height: 44px; min-height: 44px; max-height: 44px; padding: 0 16px; background-color: #2563eb; color: #ffffff; font-size: 14px; font-weight: 600; border-radius: 10px; border: none; cursor: pointer; display: flex !important; flex-direction: row !important; align-items: center !important; justify-content: center !important; gap: 8px !important; white-space: nowrap !important; transition: background-color 0.15s ease;"
                    onmouseover="if (!this.disabled) this.style.backgroundColor='#1d4ed8';"
                    onmouseout="if (!this.disabled) this.style.backgroundColor='#2563eb';"
                >
                    <template x-if="!isSubmitting">
                        <span style="display: flex; align-items: center; justify-content: center; line-height: 1; font-weight: 600; color: #ffffff;">
                            Verifikasi Kode
                        </span>
                    </template>
                    <template x-if="isSubmitting">
                        <span style="display: flex; flex-direction: row; align-items: center; justify-content: center; gap: 8px; line-height: 1; font-weight: 600; color: #ffffff;">
                            <svg style="width: 16px; height: 16px; animation: spin 1s linear infinite; flex-shrink: 0;" fill="none" viewBox="0 0 24 24">
                                <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span>Memverifikasi...</span>
                        </span>
                    </template>
                </button>
            </div>
        </form>

        {{-- Sub-actions Centered below divider --}}
        <div style="text-align: center; margin-top: 20px; padding-top: 14px; border-top: 1px solid var(--auth-divider); width: 100%; padding-bottom: 2px;">
            <a
                href="{{ filament()->getRequestPasswordResetUrl() }}"
                style="font-size: 12px; color: var(--auth-text-muted); text-decoration: none; transition: color 0.15s ease;"
                onmouseover="this.style.color='#3b82f6'; this.style.textDecoration='underline';"
                onmouseout="this.style.color='var(--auth-text-muted)'; this.style.textDecoration='none';"
            >
                Ubah Alamat Email
            </a>
        </div>
    </div>
</x-filament-panels::page.simple>
