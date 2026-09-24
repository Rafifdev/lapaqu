<script setup lang="ts">
import { ref, inject, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { Eye, EyeOff, ArrowLeft } from 'lucide-vue-next'
import AppSkeleton from '@/components/ui/AppSkeleton.vue'
import { useNotyf } from '@/composables/useNotyf'
import { useAuthStore } from '@/stores/auth'
import apiClient from '@/services/api'

const router = useRouter()
const notyf = useNotyf()
const authStore = useAuthStore()

// State loading skeleton dari parent layout
const isPageLoading = inject('isAuthPageLoading', ref(false))

// Steps: 1 = Form Pendaftaran, 2 = Verifikasi OTP Email
const step = ref<1 | 2>(1)
const setOtpStep = inject<(val: boolean) => void>('setOtpStep', () => { })
watch(step, (newVal) => {
  setOtpStep(newVal === 2)
}, { immediate: true })

const name = ref('')
const email = ref('')
const password = ref('')
const confirmPassword = ref('')
const showPassword = ref(false)
const showConfirmPassword = ref(false)
const loading = ref(false)
const errorMessage = ref('')

// OTP State
const otp = ref(['', '', '', '', '', ''])
const otpError = ref('')
const otpLoading = ref(false)
const cooldown = ref(0)
let cooldownTimer: any = null

const nameTouched = ref(false)
const emailTouched = ref(false)
const passwordTouched = ref(false)
const confirmPasswordTouched = ref(false)

const nameError = ref('')
const emailError = ref('')
const passwordError = ref('')
const confirmPasswordError = ref('')

const validateName = () => {
  const val = name.value.trim()
  if (!val) {
    nameError.value = 'Silakan masukkan nama lengkap Anda'
    return false
  }
  nameError.value = ''
  return true
}

const validateEmail = () => {
  const val = email.value.trim()
  if (!val) {
    emailError.value = 'Silakan masukkan alamat email yang valid'
    return false
  }
  if (!val.includes('@') || !val.includes('.')) {
    emailError.value = 'Format alamat email tidak valid'
    return false
  }
  emailError.value = ''
  return true
}

const validatePassword = () => {
  if (!password.value) {
    passwordError.value = 'Silakan masukkan kata sandi yang valid'
    return false
  }
  if (password.value.length < 8) {
    passwordError.value = 'Kata sandi minimal 8 karakter'
    return false
  }
  if (!/[a-zA-Z]/.test(password.value) || !/[0-9]/.test(password.value)) {
    passwordError.value = 'Kata sandi harus kombinasi huruf dan angka'
    return false
  }
  passwordError.value = ''
  return true
}

const validateConfirmPassword = () => {
  if (!confirmPassword.value) {
    confirmPasswordError.value = 'Silakan konfirmasi kata sandi Anda'
    return false
  }
  if (confirmPassword.value !== password.value) {
    confirmPasswordError.value = 'Konfirmasi kata sandi tidak cocok'
    return false
  }
  confirmPasswordError.value = ''
  return true
}

const handleNameBlur = () => {
  nameTouched.value = true
  validateName()
}

const handleEmailBlur = () => {
  emailTouched.value = true
  validateEmail()
}

const handlePasswordBlur = () => {
  passwordTouched.value = true
  validatePassword()
  if (confirmPasswordTouched.value) {
    validateConfirmPassword()
  }
}

const handleConfirmPasswordBlur = () => {
  confirmPasswordTouched.value = true
  validateConfirmPassword()
}

const handleNameInput = () => {
  if (errorMessage.value) errorMessage.value = ''
  if (nameTouched.value || nameError.value) {
    if (name.value.trim()) {
      nameError.value = ''
    }
  }
}

const handleEmailInput = () => {
  if (errorMessage.value) errorMessage.value = ''
  if (emailTouched.value || emailError.value) {
    const val = email.value.trim()
    if (val && val.includes('@')) {
      emailError.value = ''
    }
  }
}

const handlePasswordInput = () => {
  if (errorMessage.value) errorMessage.value = ''
  if (passwordTouched.value || passwordError.value) {
    if (password.value && password.value.length >= 8) {
      passwordError.value = ''
    }
  }
  if (confirmPasswordTouched.value && confirmPassword.value) {
    validateConfirmPassword()
  }
}

const handleConfirmPasswordInput = () => {
  if (errorMessage.value) errorMessage.value = ''
  if (confirmPasswordTouched.value || confirmPasswordError.value) {
    if (confirmPassword.value === password.value) {
      confirmPasswordError.value = ''
    }
  }
}

const startCooldown = (seconds = 60) => {
  cooldown.value = seconds
  if (cooldownTimer) clearInterval(cooldownTimer)
  cooldownTimer = setInterval(() => {
    if (cooldown.value > 0) {
      cooldown.value--
    } else {
      clearInterval(cooldownTimer)
      cooldownTimer = null
    }
  }, 1000)
}

const focusFirstOtp = () => {
  nextTick(() => {
    const el = document.getElementById('reg-otp-0') as HTMLInputElement | null
    el?.focus()
  })
}

// Step 1: Submit -> Request OTP
const handleSendOtp = async () => {
  errorMessage.value = ''
  nameTouched.value = true
  emailTouched.value = true
  passwordTouched.value = true
  confirmPasswordTouched.value = true

  const isNameValid = validateName()
  const isEmailValid = validateEmail()
  const isPasswordValid = validatePassword()
  const isConfirmValid = validateConfirmPassword()

  if (!isNameValid || !isEmailValid || !isPasswordValid || !isConfirmValid) {
    return
  }

  loading.value = true
  try {
    const res = await apiClient.post('/onboarding/send-otp', {
      name: name.value.trim(),
      email: email.value.trim(),
    })

    if (res?.data) {
      notyf.success('Kode verifikasi OTP telah dikirimkan ke email Anda.', 3000)
      step.value = 2
      otp.value = ['', '', '', '', '', '']
      otpError.value = ''
      startCooldown(60)
      focusFirstOtp()
    }
  } catch (err: any) {
    const errors = err?.response?.data?.errors
    if (errors?.email) {
      emailError.value = errors.email[0]
    } else {
      errorMessage.value = err?.response?.data?.message || err?.message || 'Gagal mengirim kode verifikasi OTP. Silakan coba lagi.'
      notyf.error(errorMessage.value, 3000)
    }
  } finally {
    loading.value = false
  }
}

// Resend OTP
const handleResendOtp = async () => {
  if (cooldown.value > 0 || otpLoading.value) return

  otpLoading.value = true
  otpError.value = ''
  try {
    const res = await apiClient.post('/onboarding/send-otp', {
      name: name.value.trim(),
      email: email.value.trim(),
    })

    if (res?.data) {
      notyf.success('Kode OTP baru telah dikirimkan ke email Anda.', 3000)
      startCooldown(60)
      focusFirstOtp()
    }
  } catch (err: any) {
    const msg = err?.response?.data?.message || 'Gagal mengirim ulang kode OTP.'
    otpError.value = msg
    notyf.error(msg, 3000)
  } finally {
    otpLoading.value = false
  }
}

// Step 2: Handle OTP input
const handleOtpInput = (idx: number, e: Event) => {
  otpError.value = ''
  const target = e.target as HTMLInputElement
  const val = target.value.replace(/\D/g, '')

  if (val.length > 0) {
    otp.value[idx] = val.slice(-1)
    if (idx < 5) {
      const next = document.getElementById(`reg-otp-${idx + 1}`) as HTMLInputElement | null
      next?.focus()
    }
  } else {
    otp.value[idx] = ''
  }

  // Auto-submit saat semua 6 digit terisi
  if (otp.value.every((d) => d !== '')) {
    handleRegister()
  }
}

const handleOtpKeydown = (idx: number, e: KeyboardEvent) => {
  if (e.key === 'Backspace' && !otp.value[idx] && idx > 0) {
    const prev = document.getElementById(`reg-otp-${idx - 1}`) as HTMLInputElement | null
    prev?.focus()
  }
}

const handleOtpPaste = (e: ClipboardEvent) => {
  e.preventDefault()
  otpError.value = ''
  const text = e.clipboardData?.getData('text') || ''
  const clean = text.replace(/\D/g, '').slice(0, 6)

  if (clean.length > 0) {
    for (let i = 0; i < 6; i++) {
      otp.value[i] = clean[i] || ''
    }
    const targetIdx = Math.min(clean.length, 5)
    const el = document.getElementById(`reg-otp-${targetIdx}`) as HTMLInputElement | null
    el?.focus()

    if (clean.length === 6) {
      handleRegister()
    }
  }
}

// Final Submit
const handleRegister = async () => {
  const fullOtp = otp.value.join('')
  if (fullOtp.length !== 6) {
    otpError.value = 'Silakan masukkan 6 digit kode OTP secara lengkap'
    return
  }

  otpLoading.value = true
  otpError.value = ''
  try {
    const cleanSubdomain = name.value.toLowerCase().replace(/[^a-z0-9]/g, '') || 'resto'
    const res = await apiClient.post('/onboarding/register', {
      restaurant_name: `${name.value}'s Outlet`,
      subdomain: `${cleanSubdomain}-${Date.now().toString().slice(-4)}`,
      owner_name: name.value.trim(),
      owner_email: email.value.trim(),
      owner_password: password.value,
      otp: fullOtp,
    })

    if (res?.data) {
      const data = res.data
      if (data.token && data.user) {
        if (data.tenant?.name) {
          localStorage.setItem('lapaqu_tenant_name', data.tenant.name)
        }
        const outlets = data.outlet ? [data.outlet] : []
        localStorage.setItem('lapaqu_available_outlets', JSON.stringify(outlets))
        if (outlets.length > 0) {
          localStorage.setItem('lapaqu_outlet_id', String(outlets[0].id))
          localStorage.setItem('lapaqu_outlet_name', outlets[0].name)
        }
        authStore.setAuthData(data.token, data.user, outlets)
      }
      notyf.success('Pendaftaran akun berhasil! Selamat datang di Lapaqu.', 3000)
      window.location.href = '/dashboard'
    }
  } catch (err: any) {
    const msg =
      err?.response?.data?.errors?.otp?.[0] ||
      err?.response?.data?.message ||
      err?.message ||
      'Verifikasi OTP gagal. Silakan periksa kembali kode Anda.'
    otpError.value = msg
    notyf.error(msg, 3500)
  } finally {
    otpLoading.value = false
  }
}

const backToStep1 = () => {
  step.value = 1
  otpError.value = ''
  errorMessage.value = ''
}

onMounted(() => {
  name.value = ''
  email.value = ''
  password.value = ''
  confirmPassword.value = ''
  nameTouched.value = false
  emailTouched.value = false
  passwordTouched.value = false
  confirmPasswordTouched.value = false
  nameError.value = ''
  emailError.value = ''
  passwordError.value = ''
  confirmPasswordError.value = ''
  errorMessage.value = ''
  step.value = 1
})

onUnmounted(() => {
  setOtpStep(false)
  if (cooldownTimer) clearInterval(cooldownTimer)
})
</script>

<template>
  <div class="w-full flex flex-col justify-center">

    <!-- Heading: Sama persis seperti Login / Register -->
    <div class="text-left shrink-0" :class="step === 1 ? 'mb-1.5' : 'mb-0'">
      <template v-if="isPageLoading">
        <AppSkeleton width="110px" height="28px" rounded="md" />
        <AppSkeleton width="320px" height="14px" rounded="md" class="mt-2" />
      </template>
      <template v-else>
        <h1 class="text-2xl sm:text-3xl font-bold text-[#1E293B] dark:text-white tracking-tight leading-tight">
          {{ step === 1 ? 'Daftar' : 'Verifikasi Email' }}
        </h1>
        <p class="text-sm sm:text-base text-[#64748B] dark:text-[#94A3B8] font-normal mt-1.5 leading-relaxed">
          <template v-if="step === 1">
            Buat akun untuk mulai mengelola bisnis POS Anda bersama Lapaqu.
          </template>
          <template v-else>
            Masukkan 6 digit kode OTP yang telah dikirim ke <span class="font-bold text-[#1E293B] dark:text-white">{{
              email }}</span>.
          </template>
        </p>
      </template>
    </div>

    <!-- Reserved Error Slot -->
    <div class="flex items-center" :class="step === 1 ? 'h-5 sm:h-6' : 'h-6 sm:h-7'">
      <p v-if="!isPageLoading"
        :class="(step === 1 ? errorMessage : otpError) ? 'opacity-100' : 'opacity-0 select-none pointer-events-none'"
        class="text-xs sm:text-sm text-[#EC4453] font-medium leading-tight transition-opacity duration-150">
        {{ (step === 1 ? errorMessage : otpError) || '&nbsp;' }}
      </p>
    </div>

    <!-- ================= STEP 1: FORM REGISTER ================= -->
    <form v-if="step === 1" @submit.prevent="handleSendOtp" autocomplete="off" class="space-y-1">
      <!-- 1. Name Field -->
      <div class="space-y-1">
        <template v-if="isPageLoading">
          <AppSkeleton width="80px" height="16px" rounded="md" />
          <AppSkeleton width="100%" height="44px" rounded="xl" />
        </template>
        <template v-else>
          <label for="name"
            class="block text-sm sm:text-[15px] font-bold text-[#1E293B] dark:text-white cursor-pointer">
            Nama Lengkap
          </label>
          <input id="name" name="name" v-model="name" type="text" required autocomplete="off" @blur="handleNameBlur"
            @input="handleNameInput" placeholder="Masukkan nama lengkap Anda..."
            class="w-full h-11 px-4 rounded-xl bg-white dark:bg-[#273142] text-sm sm:text-base text-[#1E293B] dark:text-white placeholder-[#94A3B8] focus:outline-none transition-colors duration-150"
            :class="[
              nameError
                ? 'border border-[#EC4453] dark:border-[#EC4453] hover:border-[#EC4453] dark:hover:border-[#EC4453] focus:border-[#EC4453] focus:ring-1 focus:ring-[#EC4453] dark:focus:border-[#EC4453] dark:focus:ring-[#EC4453]'
                : 'border border-[#E2E8F0] dark:border-[#334155] hover:bg-[#F5F7FA] dark:hover:bg-[#222B3A] hover:border-[#CBCFD7] dark:hover:border-[#475569] focus:bg-white dark:focus:bg-[#273142] focus:border-[#4880FF] focus:ring-1 focus:ring-[#4880FF] dark:focus:border-[#4880FF] dark:focus:ring-[#4880FF]'
            ]" />
        </template>
        <div class="h-5 flex items-start">
          <p v-if="!isPageLoading" :class="nameError ? 'opacity-100' : 'opacity-0 select-none pointer-events-none'"
            class="text-xs sm:text-sm text-[#EC4453] font-medium leading-tight transition-opacity duration-150">
            {{ nameError || '&nbsp;' }}
          </p>
        </div>
      </div>

      <!-- 2. Email Field -->
      <div class="space-y-1">
        <template v-if="isPageLoading">
          <AppSkeleton width="90px" height="16px" rounded="md" />
          <AppSkeleton width="100%" height="44px" rounded="xl" />
        </template>
        <template v-else>
          <label for="email"
            class="block text-sm sm:text-[15px] font-bold text-[#1E293B] dark:text-white cursor-pointer">
            Alamat Email
          </label>
          <input id="email" name="email" v-model="email" type="email" required autocomplete="off"
            @blur="handleEmailBlur" @input="handleEmailInput" placeholder="Masukkan alamat email Anda..."
            class="w-full h-11 px-4 rounded-xl bg-white dark:bg-[#273142] text-sm sm:text-base text-[#1E293B] dark:text-white placeholder-[#94A3B8] focus:outline-none transition-colors duration-150"
            :class="[
              emailError
                ? 'border border-[#EC4453] dark:border-[#EC4453] hover:border-[#EC4453] dark:hover:border-[#EC4453] focus:border-[#EC4453] focus:ring-1 focus:ring-[#EC4453] dark:focus:border-[#EC4453] dark:focus:ring-[#EC4453]'
                : 'border border-[#E2E8F0] dark:border-[#334155] hover:bg-[#F5F7FA] dark:hover:bg-[#222B3A] hover:border-[#CBCFD7] dark:hover:border-[#475569] focus:bg-white dark:focus:bg-[#273142] focus:border-[#4880FF] focus:ring-1 focus:ring-[#4880FF] dark:focus:border-[#4880FF] dark:focus:ring-[#4880FF]'
            ]" />
        </template>
        <div class="h-5 flex items-start">
          <p v-if="!isPageLoading" :class="emailError ? 'opacity-100' : 'opacity-0 select-none pointer-events-none'"
            class="text-xs sm:text-sm text-[#EC4453] font-medium leading-tight transition-opacity duration-150">
            {{ emailError || '&nbsp;' }}
          </p>
        </div>
      </div>

      <!-- 3. Password Field -->
      <div class="space-y-1">
        <template v-if="isPageLoading">
          <AppSkeleton width="65px" height="16px" rounded="md" />
          <AppSkeleton width="100%" height="44px" rounded="xl" />
        </template>
        <template v-else>
          <label class="block text-sm sm:text-[15px] font-bold text-[#1E293B] dark:text-white">
            Kata Sandi
          </label>
          <div class="relative">
            <input id="password" name="password" v-model="password" required :type="showPassword ? 'text' : 'password'"
              autocomplete="new-password" @blur="handlePasswordBlur" @input="handlePasswordInput"
              placeholder="Minimal 8 karakter (huruf & angka)..."
              class="w-full h-11 pl-4 pr-11 rounded-xl bg-white dark:bg-[#273142] text-sm sm:text-base text-[#1E293B] dark:text-white placeholder-[#94A3B8] focus:outline-none transition-colors duration-150"
              :class="[
                passwordError
                  ? 'border border-[#EC4453] dark:border-[#EC4453] hover:border-[#EC4453] dark:hover:border-[#EC4453] focus:border-[#EC4453] focus:ring-1 focus:ring-[#EC4453] dark:focus:border-[#EC4453] dark:focus:ring-[#EC4453]'
                  : 'border border-[#E2E8F0] dark:border-[#334155] hover:bg-[#F5F7FA] dark:hover:bg-[#222B3A] hover:border-[#CBCFD7] dark:hover:border-[#475569] focus:bg-white dark:focus:bg-[#273142] focus:border-[#4880FF] focus:ring-1 focus:ring-[#4880FF] dark:focus:border-[#4880FF] dark:focus:ring-[#4880FF]'
              ]" />
            <button type="button" @click="showPassword = !showPassword"
              class="absolute right-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8] hover:text-[#4880FF] transition-colors cursor-pointer"
              tabindex="-1">
              <EyeOff v-if="!showPassword" class="w-5 h-5" />
              <Eye v-else class="w-5 h-5" />
            </button>
          </div>
        </template>
        <div class="h-5 flex items-start">
          <p v-if="!isPageLoading" :class="passwordError ? 'opacity-100' : 'opacity-0 select-none pointer-events-none'"
            class="text-xs sm:text-sm text-[#EC4453] font-medium leading-tight transition-opacity duration-150">
            {{ passwordError || '&nbsp;' }}
          </p>
        </div>
      </div>

      <!-- 4. Confirm Password Field -->
      <div class="space-y-1">
        <template v-if="isPageLoading">
          <AppSkeleton width="115px" height="16px" rounded="md" />
          <AppSkeleton width="100%" height="44px" rounded="xl" />
        </template>
        <template v-else>
          <label class="block text-sm sm:text-[15px] font-bold text-[#1E293B] dark:text-white">
            Konfirmasi Kata Sandi
          </label>
          <div class="relative">
            <input id="confirmPassword" name="confirmPassword" v-model="confirmPassword" required
              :type="showConfirmPassword ? 'text' : 'password'" autocomplete="new-password"
              @blur="handleConfirmPasswordBlur" @input="handleConfirmPasswordInput"
              placeholder="Konfirmasi kata sandi Anda..."
              class="w-full h-11 pl-4 pr-11 rounded-xl bg-white dark:bg-[#273142] text-sm sm:text-base text-[#1E293B] dark:text-white placeholder-[#94A3B8] focus:outline-none transition-colors duration-150"
              :class="[
                confirmPasswordError
                  ? 'border border-[#EC4453] dark:border-[#EC4453] hover:border-[#EC4453] dark:hover:border-[#EC4453] focus:border-[#EC4453] focus:ring-1 focus:ring-[#EC4453] dark:focus:border-[#EC4453] dark:focus:ring-[#EC4453]'
                  : 'border border-[#E2E8F0] dark:border-[#334155] hover:bg-[#F5F7FA] dark:hover:bg-[#222B3A] hover:border-[#CBCFD7] dark:hover:border-[#475569] focus:bg-white dark:focus:bg-[#273142] focus:border-[#4880FF] focus:ring-1 focus:ring-[#4880FF] dark:focus:border-[#4880FF] dark:focus:ring-[#4880FF]'
              ]" />
            <button type="button" @click="showConfirmPassword = !showConfirmPassword"
              class="absolute right-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8] hover:text-[#4880FF] transition-colors cursor-pointer"
              tabindex="-1">
              <EyeOff v-if="!showConfirmPassword" class="w-5 h-5" />
              <Eye v-else class="w-5 h-5" />
            </button>
          </div>
        </template>
        <div class="h-5 flex items-start">
          <p v-if="!isPageLoading"
            :class="confirmPasswordError ? 'opacity-100' : 'opacity-0 select-none pointer-events-none'"
            class="text-xs sm:text-sm text-[#EC4453] font-medium leading-tight transition-opacity duration-150">
            {{ confirmPasswordError || '&nbsp;' }}
          </p>
        </div>
      </div>

      <!-- Submit Button: Kirim Kode OTP -->
      <div class="!mt-2">
        <template v-if="isPageLoading">
          <AppSkeleton width="100%" height="44px" rounded="xl" />
        </template>
        <template v-else>
          <button type="submit" :disabled="loading"
            class="w-full h-11 rounded-xl bg-[#4880FF] hover:bg-[#3B6FE8] active:scale-[0.99] text-white text-sm sm:text-base font-bold transition-all shadow-sm flex items-center justify-center gap-2 cursor-pointer disabled:opacity-70 disabled:cursor-not-allowed">
            <svg v-if="loading" class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
              </path>
            </svg>
            <span>{{ loading ? 'Mengirim Kode OTP...' : 'Daftar Sekarang' }}</span>
          </button>
        </template>
      </div>
    </form>

    <!-- ================= STEP 2: FORM VERIFIKASI OTP ================= -->
    <form v-else @submit.prevent="handleRegister" autocomplete="off" class="flex flex-col gap-6">
      <!-- 6-digit OTP Inputs (Responsif di semua mode layar, desktop sm:w-18 sm:h-22) -->
      <div class="flex justify-center gap-1.5 min-[360px]:gap-2 min-[390px]:gap-2.5 min-[480px]:gap-3 sm:gap-3.5 w-full py-1.5" @paste="handleOtpPaste">
        <input v-for="(digit, idx) in otp" :key="idx" :id="`reg-otp-${idx}`" type="text" inputmode="numeric"
          maxlength="1" :value="digit" @input="handleOtpInput(idx, $event)" @keydown="handleOtpKeydown(idx, $event)"
          class="w-10 h-14 min-[360px]:w-11 min-[360px]:h-16 min-[390px]:w-12 min-[390px]:h-[68px] min-[480px]:w-14 min-[480px]:h-20 sm:w-18 sm:h-22 text-center text-lg min-[360px]:text-xl min-[390px]:text-2xl sm:text-3xl font-black font-mono uppercase rounded-lg min-[360px]:rounded-xl sm:rounded-2xl bg-white dark:bg-[#273142] text-[#1E293B] dark:text-white transition-all duration-150 focus:outline-none shadow-sm"
          :class="[
            otpError
              ? 'border-2 border-[#EC4453] dark:border-[#EC4453] focus:ring-2 focus:ring-[#EC4453]/20'
              : 'border border-[#CBD5E1] dark:border-[#334155] hover:border-[#94A3B8] dark:hover:border-[#475569] focus:border-[#4880FF] focus:ring-2 focus:ring-[#4880FF]/25'
          ]" />
      </div>

      <!-- Action Links: Ubah Data (kiri) & Kirim Ulang OTP (kanan) -->
      <div class="flex items-center justify-between text-sm sm:text-base">
        <button type="button" @click="backToStep1"
          class="inline-flex items-center gap-1.5 font-semibold text-[#64748B] dark:text-[#94A3B8] hover:text-[#4880FF] dark:hover:text-[#4880FF] transition-colors cursor-pointer">
          <ArrowLeft class="w-4 h-4" />
          <span>Ubah data</span>
        </button>

        <p class="text-[#64748B] dark:text-[#94A3B8]">
          Tidak menerima kode?
          <button type="button" @click="handleResendOtp" :disabled="cooldown > 0 || otpLoading"
            class="font-bold text-[#4880FF] hover:underline ml-1 disabled:opacity-50 disabled:no-underline cursor-pointer disabled:cursor-not-allowed">
            <span v-if="cooldown > 0">{{ cooldown }} detik</span>
            <span v-else>Kirim Ulang</span>
          </button>
        </p>
      </div>

      <!-- Submit Verification Button -->
      <div>
        <button type="submit" :disabled="otpLoading || otp.join('').length !== 6"
          class="w-full h-11 rounded-xl bg-[#4880FF] hover:bg-[#3B6FE8] active:scale-[0.99] text-white text-sm sm:text-base font-bold transition-all shadow-sm flex items-center justify-center gap-2 cursor-pointer disabled:opacity-70 disabled:cursor-not-allowed">
          <svg v-if="otpLoading" class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor"
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
            </path>
          </svg>
          <span>{{ otpLoading ? 'Memverifikasi...' : 'Verifikasi Kode OTP' }}</span>
        </button>
      </div>
    </form>

  </div>
</template>

<style scoped>
/* Override browser default autofill bluish tint to match hover color #F5F7FA */
input:-webkit-autofill,
input:-webkit-autofill:hover,
input:-webkit-autofill:focus,
input:-webkit-autofill:active {
  -webkit-box-shadow: 0 0 0 1000px #F5F7FA inset !important;
  box-shadow: 0 0 0 1000px #F5F7FA inset !important;
  -webkit-text-fill-color: #1E293B !important;
  caret-color: #1E293B !important;
  transition: background-color 5000s ease-in-out 0s;
}

:global(.dark) input:-webkit-autofill,
:global(.dark) input:-webkit-autofill:hover,
:global(.dark) input:-webkit-autofill:focus,
:global(.dark) input:-webkit-autofill:active {
  -webkit-box-shadow: 0 0 0 1000px #222B3A inset !important;
  box-shadow: 0 0 0 1000px #222B3A inset !important;
  -webkit-text-fill-color: #FFFFFF !important;
  caret-color: #FFFFFF !important;
  transition: background-color 5000s ease-in-out 0s;
}
</style>
