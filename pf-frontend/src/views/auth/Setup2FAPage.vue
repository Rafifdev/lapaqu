<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppCard from '@/components/ui/AppCard.vue'

const router = useRouter()
const code = ref('')
const loading = ref(false)

const handleActivate = () => {
  loading.value = true
  setTimeout(() => {
    loading.value = false
    router.push('/dashboard')
  }, 500)
}
</script>

<template>
  <AppCard>
    <div class="text-center mb-5">
      <div class="w-12 h-12 rounded-2xl bg-[#E2EAF8] dark:bg-[#4880FF]/25 text-[#2563EB] dark:text-[#93C5FD] flex items-center justify-center mx-auto mb-3">
        <AppIcon name="security" :size="28" />
      </div>
      <h2 class="text-2xl font-extrabold text-[#1E293B] dark:text-white">Aktifkan 2FA</h2>
      <p class="text-sm text-[#475569] dark:text-[#94A3B8] mt-1.5 font-medium">Scan QR code di bawah menggunakan Google Authenticator atau Authy</p>
    </div>

    <!-- Mock QR Code -->
    <div class="bg-white p-4 rounded-2xl border border-[#E2E8F0] dark:border-[#334155] w-48 h-48 mx-auto flex items-center justify-center mb-4 shadow-sm">
      <img
        src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=otpauth://totp/Lapaqu:owner@kopiceria.com?secret=JBSWY3DPEHPK3PXP&issuer=Lapaqu"
        alt="QR 2FA"
        class="w-full h-full object-contain"
      />
    </div>

    <div class="bg-[#F8FAFC] dark:bg-[#1E293B] p-3.5 rounded-xl text-center mb-5 border border-[#CBD5E1] dark:border-[#334155]">
      <p class="text-xs text-[#475569] dark:text-[#94A3B8] font-bold">Kode Manual Secret:</p>
      <p class="text-base font-mono font-extrabold text-[#4880FF] tracking-wider mt-0.5 select-all">JBSW Y3DP EHPK 3PXP</p>
    </div>

    <form @submit.prevent="handleActivate" class="space-y-4">
      <AppInput
        v-model="code"
        label="Kode Verifikasi 6 Digit"
        placeholder="123456"
        required
      />

      <AppButton
        type="submit"
        variant="primary"
        size="lg"
        block
        :loading="loading"
      >
        <template #prefix>
          <AppIcon name="check" :size="20" />
        </template>
        Konfirmasi & Selesaikan
      </AppButton>
    </form>
  </AppCard>
</template>
