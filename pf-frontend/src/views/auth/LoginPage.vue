<script setup lang="ts">
import { ref, inject, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { Eye, EyeOff } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import AppSkeleton from '@/components/ui/AppSkeleton.vue'
import apiClient from '@/services/api'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

// State loading skeleton dari parent layout
const isPageLoading = inject('isAuthPageLoading', ref(false))

const email = ref('')
const password = ref('')
const rememberMe = ref(false)
const showPassword = ref(false)
const loading = ref(false)
const errorMessage = ref('')
const isLoginFailed = ref(false)

const emailTouched = ref(false)
const passwordTouched = ref(false)
const emailError = ref('')
const passwordError = ref('')

const validateEmail = () => {
  const val = email.value.trim()
  if (!val) {
    emailError.value = 'Silakan masukkan alamat email yang terdaftar'
    return false
  }
  if (!val.includes('@')) {
    emailError.value = 'Silakan masukkan alamat email yang terdaftar'
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
  passwordError.value = ''
  return true
}

const handleEmailBlur = () => {
  emailTouched.value = true
  validateEmail()
}

const handlePasswordBlur = () => {
  passwordTouched.value = true
  validatePassword()
}

const handleEmailInput = () => {
  if (errorMessage.value) {
    errorMessage.value = ''
    isLoginFailed.value = false
  }
  if (emailTouched.value || emailError.value) {
    const val = email.value.trim()
    if (val && val.includes('@')) {
      emailError.value = ''
    }
  }
}

const handlePasswordInput = () => {
  if (errorMessage.value) {
    errorMessage.value = ''
    isLoginFailed.value = false
  }
  if (passwordTouched.value || passwordError.value) {
    if (password.value) {
      passwordError.value = ''
    }
  }
}

const redirectAfterLogin = (_outlets?: any[]) => {
  const redirectQuery = route.query.redirect as string
  let targetUrl = '/dashboard'

  if (redirectQuery && redirectQuery.startsWith('/') && !redirectQuery.startsWith('/auth')) {
    targetUrl = redirectQuery
  } else if (authStore.isKasir && !authStore.isOwner) {
    targetUrl = '/pos/orders'
  } else if (authStore.isKitchen && !authStore.isOwner) {
    targetUrl = '/kds/queue'
  }

  window.location.href = targetUrl
}

// Watch rememberMe checkbox: if unchecked, clear saved email
watch(rememberMe, (checked) => {
  if (!checked) {
    try {
      localStorage.removeItem('lapaqu_saved_email')
      localStorage.removeItem('remember_me')
    } catch {}
  }
})

onMounted(() => {
  // Load remembered email if exists
  try {
    const savedEmail = localStorage.getItem('lapaqu_saved_email')
    if (savedEmail) {
      email.value = savedEmail
      rememberMe.value = true
    } else {
      email.value = ''
      rememberMe.value = false
    }
  } catch {
    email.value = ''
    rememberMe.value = false
  }

  password.value = ''
  emailTouched.value = false
  passwordTouched.value = false
  emailError.value = ''
  passwordError.value = ''
  errorMessage.value = ''
  isLoginFailed.value = false

  // Clear delayed browser password autofill (preserve remembered email)
  const isRemembered = rememberMe.value
  setTimeout(() => {
    password.value = ''
    if (!isRemembered) {
      email.value = ''
    }
  }, 50)
  setTimeout(() => {
    password.value = ''
    if (!isRemembered) {
      email.value = ''
    }
  }, 250)
})

const handleLogin = async () => {
  errorMessage.value = ''
  isLoginFailed.value = false
  emailTouched.value = true
  passwordTouched.value = true

  const isEmailValid = validateEmail()
  const isPasswordValid = validatePassword()
  if (!isEmailValid || !isPasswordValid) {
    return
  }

  loading.value = true
  try {
    const res = await authStore.login(email.value, password.value, rememberMe.value)
    if (res.success) {
      // Save or remove remember me email
      try {
        if (rememberMe.value && email.value.trim()) {
          localStorage.setItem('lapaqu_saved_email', email.value.trim())
          localStorage.setItem('remember_me', 'true')
        } else {
          localStorage.removeItem('lapaqu_saved_email')
          localStorage.removeItem('remember_me')
        }
      } catch {}

      if (res.require2FA) {
        window.location.href = '/auth/verify-2fa'
      } else {
        redirectAfterLogin(res.outlets)
      }
    } else {
      isLoginFailed.value = true
      errorMessage.value = res.message || 'Email atau kata sandi tidak valid'
    }
  } catch (err: any) {
    isLoginFailed.value = true
    errorMessage.value = err?.response?.data?.message || err?.message || 'Gagal masuk. Silakan coba lagi.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="w-full flex flex-col justify-center">
    <!-- Heading -->
    <div class="text-left mb-2 shrink-0">
      <template v-if="isPageLoading">
        <AppSkeleton width="100px" height="28px" rounded="md" />
        <AppSkeleton width="320px" height="14px" rounded="md" class="mt-2" />
      </template>
      <template v-else>
        <h1 class="text-2xl sm:text-3xl font-bold text-[#1E293B] dark:text-white tracking-tight leading-tight">
          Masuk
        </h1>
        <p class="text-sm sm:text-base text-[#64748B] dark:text-[#94A3B8] font-normal mt-1.5 leading-relaxed">
          Masukkan email dan kata sandi Anda untuk mengakses akun.
        </p>
      </template>
    </div>

    <!-- Login Form: space-y-2 -->
    <form @submit.prevent="handleLogin" autocomplete="off" class="space-y-2">
      <!-- Email Field -->
      <div class="space-y-1.5">
        <!-- Reserved Error Slot for Salah Email/Password -->
        <div class="h-5 sm:h-6 flex items-center">
          <p v-if="!isPageLoading"
            :class="errorMessage ? 'opacity-100' : 'opacity-0 select-none pointer-events-none'"
            class="text-xs sm:text-sm text-[#EC4453] font-medium leading-tight transition-opacity duration-150">
            {{ errorMessage || '&nbsp;' }}
          </p>
        </div>

        <template v-if="isPageLoading">
          <AppSkeleton width="90px" height="16px" rounded="md" />
          <AppSkeleton width="100%" height="44px" rounded="xl" />
        </template>
        <template v-else>
          <label for="email" class="block text-sm sm:text-[15px] font-bold text-[#1E293B] dark:text-white cursor-pointer">
            Alamat Email
          </label>
          <input id="email" name="email" v-model="email" type="email" required autocomplete="off"
            @blur="handleEmailBlur" @input="handleEmailInput" placeholder="Masukkan alamat email terdaftar Anda..."
            class="w-full h-11 px-4 rounded-xl bg-white dark:bg-[#273142] text-sm sm:text-base text-[#1E293B] dark:text-white placeholder-[#94A3B8] focus:outline-none transition-colors duration-150"
            :class="[
              (emailError || isLoginFailed)
                ? 'border border-[#EC4453] dark:border-[#EC4453] hover:border-[#EC4453] dark:hover:border-[#EC4453] focus:border-[#EC4453] focus:ring-1 focus:ring-[#EC4453] dark:focus:border-[#EC4453] dark:focus:ring-[#EC4453]'
                : 'border border-[#E2E8F0] dark:border-[#334155] hover:bg-[#F5F7FA] dark:hover:bg-[#222B3A] hover:border-[#CBCFD7] dark:hover:border-[#475569] focus:bg-white dark:focus:bg-[#273142] focus:border-[#4880FF] focus:ring-1 focus:ring-[#4880FF] dark:focus:border-[#4880FF] dark:focus:ring-[#4880FF]'
            ]" />
        </template>
        <!-- Reserved Error Slot for Email Validation (tanpa !mt-1) -->
        <div class="h-5 flex items-start">
          <p v-if="!isPageLoading"
            :class="emailError ? 'opacity-100' : 'opacity-0 select-none pointer-events-none'"
            class="text-xs sm:text-sm text-[#EC4453] font-medium leading-tight transition-opacity duration-150">
            {{ emailError || '&nbsp;' }}
          </p>
        </div>
      </div>

      <!-- Password Field with Toggle Visibility -->
      <div class="space-y-1.5 !mt-1">
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
              placeholder="Masukkan kata sandi Anda..."
              class="w-full h-11 pl-4 pr-11 rounded-xl bg-white dark:bg-[#273142] text-sm sm:text-base text-[#1E293B] dark:text-white placeholder-[#94A3B8] focus:outline-none transition-colors duration-150"
              :class="[
                (passwordError || isLoginFailed)
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
        <!-- Reserved Error Slot for Password Validation (tanpa !mt-1) -->
        <div class="h-5 flex items-start">
          <p v-if="!isPageLoading"
            :class="passwordError ? 'opacity-100' : 'opacity-0 select-none pointer-events-none'"
            class="text-xs sm:text-sm text-[#EC4453] font-medium leading-tight transition-opacity duration-150">
            {{ passwordError || '&nbsp;' }}
          </p>
        </div>
      </div>

      <!-- Remember Me & Forgot Password -->
      <div class="flex items-center justify-between !mt-1">
        <template v-if="isPageLoading">
          <div class="flex items-center gap-2">
            <AppSkeleton width="16px" height="16px" rounded="sm" />
            <AppSkeleton width="95px" height="16px" rounded="md" />
          </div>
          <AppSkeleton width="140px" height="16px" rounded="md" />
        </template>
        <template v-else>
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input v-model="rememberMe" type="checkbox"
              class="w-4 h-4 rounded border-[#CBD5E1] dark:border-[#475569] text-[#4880FF] focus:ring-0 focus:outline-none cursor-pointer" />
            <span class="text-xs sm:text-sm font-medium text-[#64748B] dark:text-[#94A3B8]">
              Ingat Saya
            </span>
          </label>
          <router-link to="/auth/forgot-password" class="text-xs sm:text-sm font-bold text-[#4880FF] hover:underline">
            Lupa Kata Sandi?
          </router-link>
        </template>
      </div>

      <!-- Submit Login Button: h-10 (40px) -->
      <div class="!mt-3">
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
            <span>{{ loading ? 'Sedang masuk...' : 'Masuk' }}</span>
          </button>
        </template>
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
