<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppCard from '@/components/ui/AppCard.vue'

const router = useRouter()
const otp = ref(['', '', '', '', '', ''])
const loading = ref(false)

const handleInput = (idx: number, e: Event) => {
  const target = e.target as HTMLInputElement
  const val = target.value
  otp.value[idx] = val.slice(-1)
  if (val && idx < 5) {
    const next = document.getElementById(`otp-${idx + 1}`)
    next?.focus()
  }
}

const handleVerify = () => {
  loading.value = true
  setTimeout(() => {
    loading.value = false
    window.location.href = '/dashboard'
  }, 500)
}
</script>

<template>
  <AppCard>
    <div class="text-center mb-6">
      <div class="w-12 h-12 rounded-2xl bg-[#CCFBF1] dark:bg-[#00B69B]/25 text-[#0D9488] dark:text-[#5EEAD4] flex items-center justify-center mx-auto mb-3">
        <AppIcon name="verified_user" :size="28" />
      </div>
      <h2 class="text-2xl font-extrabold text-[#1E293B] dark:text-white">Verifikasi Dua Langkah (2FA)</h2>
      <p class="text-sm text-[#475569] dark:text-[#94A3B8] mt-1.5 font-medium">Masukkan 6 digit kode dari aplikasi Authenticator Anda</p>
    </div>

    <div class="flex justify-center gap-2 mb-6">
      <input
        v-for="(digit, idx) in otp"
        :key="idx"
        :id="`otp-${idx}`"
        type="text"
        inputmode="numeric"
        maxlength="1"
        :value="digit"
        @input="handleInput(idx, $event)"
        class="w-12 h-[68px] sm:w-15 sm:h-[82px] text-center text-2xl sm:text-3xl font-black rounded-xl sm:rounded-2xl bg-[#F8FAFC] dark:bg-[#1E293B] border border-[#CBD5E1] dark:border-[#334155] focus:outline-none text-[#1E293B] dark:text-white"
      />
    </div>

    <AppButton
      @click="handleVerify"
      variant="primary"
      size="lg"
      block
      :loading="loading"
    >
      Verifikasi & Masuk
    </AppButton>

    <div class="mt-4 text-center">
      <router-link to="/auth/login" class="inline-flex items-center gap-1.5 text-sm font-bold text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF]">
        <AppIcon name="arrow_back" :size="16" />
        Kembali ke Login
      </router-link>
    </div>
  </AppCard>
</template>
