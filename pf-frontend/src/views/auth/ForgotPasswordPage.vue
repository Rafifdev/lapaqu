<script setup lang="ts">
import { ref, inject, onMounted } from 'vue'
import AppSkeleton from '@/components/ui/AppSkeleton.vue'
import { useNotyf } from '@/composables/useNotyf'
import apiClient from '@/services/api'

// State loading skeleton dari parent layout
const isPageLoading = inject('isAuthPageLoading', ref(false))
const notyf = useNotyf()

const email = ref('')
const loading = ref(false)
const sent = ref(false)
const errorMessage = ref('')
const isFailed = ref(false)

const emailTouched = ref(false)
const emailError = ref('')

const validateEmail = () => {
  const val = email.value.trim()
  if (!val) {
    emailError.value = 'Silakan masukkan alamat email yang terdaftar'
    return false
  }
  if (!val.includes('@')) {
    emailError.value = 'Silakan masukkan alamat email yang valid'
    return false
  }
  emailError.value = ''
  return true
}

const handleEmailBlur = () => {
  emailTouched.value = true
  validateEmail()
}

const handleEmailInput = () => {
  if (errorMessage.value) {
    errorMessage.value = ''
    isFailed.value = false
  }
  if (emailTouched.value || emailError.value) {
    const val = email.value.trim()
    if (val && val.includes('@')) {
      emailError.value = ''
    }
  }
}

onMounted(() => {
  email.value = ''
  emailTouched.value = false
  emailError.value = ''
  errorMessage.value = ''
  isFailed.value = false
  sent.value = false
})

const handleSend = async () => {
  errorMessage.value = ''
  isFailed.value = false
  emailTouched.value = true

  if (!validateEmail()) {
    return
  }

  loading.value = true
  try {
    const res = await apiClient.post('/forgot-password', { email: email.value.trim() })
    if (res.data) {
      if (sent.value) {
        notyf.success('Tautan reset telah dikirim ulang ke email Anda.', 2000)
      } else {
        notyf.success('Tautan reset kata sandi telah dikirim ke email Anda.', 2000)
      }
      sent.value = true
    }
  } catch (err: any) {
    if (err?.response?.status === 429) {
      errorMessage.value = err?.response?.data?.message || 'Terlalu banyak percobaan. Silakan coba lagi nanti.'
      isFailed.value = true
      notyf.error(errorMessage.value, 2000)
    } else {
      const msg = err?.response?.data?.message
      if (msg) {
        errorMessage.value = msg
        isFailed.value = true
        notyf.error(msg, 2000)
      } else {
        if (sent.value) {
          notyf.success('Tautan reset telah dikirim ulang ke email Anda.', 2000)
        } else {
          notyf.success('Tautan reset kata sandi telah dikirim ke email Anda.', 2000)
        }
        sent.value = true
      }
    }
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
        <AppSkeleton width="160px" height="28px" rounded="md" />
        <AppSkeleton width="320px" height="14px" rounded="md" class="mt-2" />
      </template>
      <template v-else>
        <h1 class="text-2xl sm:text-3xl font-bold text-[#1E293B] dark:text-white tracking-tight leading-tight">
          Lupa Kata Sandi
        </h1>
        <p class="text-sm sm:text-base text-[#64748B] dark:text-[#94A3B8] font-normal mt-1.5 leading-relaxed">
          Masukkan email terdaftar Anda untuk menerima tautan pemulihan kata sandi.
        </p>
      </template>
    </div>

    <!-- Form State (Selalu ditampilkan, feedback via Toaster & pergantian tombol) -->
    <form @submit.prevent="handleSend" autocomplete="off" class="space-y-2">
      <!-- Email Field -->
      <div class="space-y-1.5">
        <!-- Reserved Error Slot for Error / Rate Limit Message -->
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
          <input id="email" name="email" v-model="email" type="email" required autocomplete="email"
            @blur="handleEmailBlur" @input="handleEmailInput" placeholder="Masukkan alamat email terdaftar Anda..."
            class="w-full h-11 px-4 rounded-xl bg-white dark:bg-[#273142] text-sm sm:text-base text-[#1E293B] dark:text-white placeholder-[#94A3B8] focus:outline-none transition-colors duration-150"
            :class="[
              (emailError || isFailed)
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

      <!-- Submit Button: h-10 (40px) -> Berubah menjadi 'Kirim Ulang Email' saat tautan telah dikirim -->
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
            <span>
              {{ loading ? (sent ? 'Mengirim ulang...' : 'Mengirim tautan...') : (sent ? 'Kirim Ulang Email' : 'Kirim Tautan Reset') }}
            </span>
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
}
</style>
