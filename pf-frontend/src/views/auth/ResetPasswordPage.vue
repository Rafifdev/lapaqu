<script setup lang="ts">
import { ref, inject, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { Eye, EyeOff, ArrowLeft, CheckCircle2 } from 'lucide-vue-next'
import AppSkeleton from '@/components/ui/AppSkeleton.vue'
import apiClient from '@/services/api'

const router = useRouter()
const route = useRoute()

// State loading skeleton dari parent layout
const isPageLoading = inject('isAuthPageLoading', ref(false))

const password = ref('')
const confirmPassword = ref('')
const showPassword = ref(false)
const showConfirmPassword = ref(false)
const loading = ref(false)
const success = ref(false)
const errorMessage = ref('')

const passwordTouched = ref(false)
const confirmPasswordTouched = ref(false)
const passwordError = ref('')
const confirmPasswordError = ref('')

const validatePassword = () => {
  if (!password.value) {
    passwordError.value = 'Silakan masukkan kata sandi baru'
    return false
  }
  if (password.value.length < 8) {
    passwordError.value = 'Kata sandi minimal 8 karakter'
    return false
  }
  passwordError.value = ''
  return true
}

const validateConfirmPassword = () => {
  if (!confirmPassword.value) {
    confirmPasswordError.value = 'Silakan konfirmasi kata sandi baru Anda'
    return false
  }
  if (confirmPassword.value !== password.value) {
    confirmPasswordError.value = 'Konfirmasi kata sandi tidak cocok'
    return false
  }
  confirmPasswordError.value = ''
  return true
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

const handlePasswordInput = () => {
  if (errorMessage.value) errorMessage.value = ''
  if (passwordTouched.value) validatePassword()
  if (confirmPasswordTouched.value) validateConfirmPassword()
}

const handleConfirmPasswordInput = () => {
  if (errorMessage.value) errorMessage.value = ''
  if (confirmPasswordTouched.value) validateConfirmPassword()
}

onMounted(() => {
  password.value = ''
  confirmPassword.value = ''
  passwordTouched.value = false
  confirmPasswordTouched.value = false
  passwordError.value = ''
  confirmPasswordError.value = ''
  errorMessage.value = ''
  success.value = false
})

const handleReset = async () => {
  errorMessage.value = ''
  passwordTouched.value = true
  confirmPasswordTouched.value = true

  const isPValid = validatePassword()
  const isCValid = validateConfirmPassword()
  if (!isPValid || !isCValid) return

  loading.value = true
  try {
    const token = (route.query.token as string) || ''
    const email = (route.query.email as string) || ''
    
    await apiClient.post('/reset-password', {
      email,
      token,
      password: password.value,
      password_confirmation: confirmPassword.value,
    })
    success.value = true
    setTimeout(() => {
      router.push('/auth/login')
    }, 2000)
  } catch (err: any) {
    errorMessage.value = err?.response?.data?.message || 'Gagal mengatur ulang kata sandi. Tautan mungkin telah kadaluarsa.'
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
        <AppSkeleton width="180px" height="28px" rounded="md" />
        <AppSkeleton width="300px" height="14px" rounded="md" class="mt-2" />
      </template>
      <template v-else>
        <h1 class="text-2xl sm:text-3xl font-bold text-[#1E293B] dark:text-white tracking-tight leading-tight">
          Reset Kata Sandi
        </h1>
        <p class="text-sm sm:text-base text-[#64748B] dark:text-[#94A3B8] font-normal mt-1.5 leading-relaxed">
          {{ success ? 'Kata sandi berhasil diperbarui.' : 'Masukkan kata sandi baru untuk mengamankan akun Anda.' }}
        </p>
      </template>
    </div>

    <!-- Success State -->
    <div v-if="success" class="mt-2 space-y-4">
      <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 text-left space-y-1.5">
        <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400 font-bold text-sm">
          <CheckCircle2 class="w-5 h-5 shrink-0" />
          <span>Kata Sandi Diperbarui!</span>
        </div>
        <p class="text-xs text-[#64748B] dark:text-[#94A3B8] leading-relaxed">
          Kata sandi baru Anda berhasil disimpan. Anda akan dialihkan ke halaman masuk secara otomatis...
        </p>
      </div>
      <router-link to="/auth/login"
        class="w-full h-11 rounded-xl bg-[#4880FF] hover:bg-[#3B6FE8] text-white text-sm sm:text-base font-bold flex items-center justify-center gap-2 transition-all shadow-sm cursor-pointer">
        <span>Masuk Sekarang</span>
      </router-link>
    </div>

    <!-- Form State -->
    <form v-else @submit.prevent="handleReset" autocomplete="off" class="space-y-2">
      <!-- Error Slot for general message -->
      <div class="h-5 sm:h-6 flex items-center">
        <p v-if="!isPageLoading"
          :class="errorMessage ? 'opacity-100' : 'opacity-0 select-none pointer-events-none'"
          class="text-xs sm:text-sm text-[#EC4453] font-medium leading-tight transition-opacity duration-150">
          {{ errorMessage || '&nbsp;' }}
        </p>
      </div>

      <!-- Password Field -->
      <div class="space-y-1.5">
        <template v-if="isPageLoading">
          <AppSkeleton width="110px" height="16px" rounded="md" />
          <AppSkeleton width="100%" height="44px" rounded="xl" />
        </template>
        <template v-else>
          <label for="password" class="block text-sm sm:text-[15px] font-bold text-[#1E293B] dark:text-white cursor-pointer">
            Kata Sandi Baru
          </label>
          <div class="relative">
            <input id="password" name="password" v-model="password" required :type="showPassword ? 'text' : 'password'"
              autocomplete="new-password" @blur="handlePasswordBlur" @input="handlePasswordInput"
              placeholder="Minimal 8 karakter..."
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
          <p v-if="!isPageLoading"
            :class="passwordError ? 'opacity-100' : 'opacity-0 select-none pointer-events-none'"
            class="text-xs sm:text-sm text-[#EC4453] font-medium leading-tight transition-opacity duration-150">
            {{ passwordError || '&nbsp;' }}
          </p>
        </div>
      </div>

      <!-- Confirm Password Field -->
      <div class="space-y-1.5 !mt-1">
        <template v-if="isPageLoading">
          <AppSkeleton width="140px" height="16px" rounded="md" />
          <AppSkeleton width="100%" height="44px" rounded="xl" />
        </template>
        <template v-else>
          <label for="confirmPassword" class="block text-sm sm:text-[15px] font-bold text-[#1E293B] dark:text-white cursor-pointer">
            Konfirmasi Kata Sandi Baru
          </label>
          <div class="relative">
            <input id="confirmPassword" name="confirmPassword" v-model="confirmPassword" required :type="showConfirmPassword ? 'text' : 'password'"
              autocomplete="new-password" @blur="handleConfirmPasswordBlur" @input="handleConfirmPasswordInput"
              placeholder="Ulangi kata sandi baru..."
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

      <!-- Submit Button: h-10 (40px) -->
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
            <span>{{ loading ? 'Menyimpan...' : 'Simpan Kata Sandi Baru' }}</span>
          </button>
        </template>
      </div>
    </form>
  </div>
</template>

<style scoped>
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
}
</style>
