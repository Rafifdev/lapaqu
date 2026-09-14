<script setup lang="ts">
import { ref } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppCard from '@/components/ui/AppCard.vue'

const email = ref('')
const loading = ref(false)
const sent = ref(false)

const handleSend = () => {
  loading.value = true
  setTimeout(() => {
    loading.value = false
    sent.value = true
  }, 500)
}
</script>

<template>
  <AppCard>
    <div class="mb-6">
      <h2 class="text-xl font-extrabold text-[#1E293B] dark:text-white">Lupa Password?</h2>
      <p class="text-xs text-[#475569] dark:text-[#94A3B8] mt-1 font-medium">Kami akan mengirimkan instruksi reset password ke email Anda</p>
    </div>

    <div v-if="sent" class="p-4 bg-[#CCFBF1] dark:bg-[#00B69B]/20 rounded-xl text-[#0D9488] dark:text-[#5EEAD4] text-center mb-4 border border-[#00B69B]/30">
      <p class="text-sm font-bold">Email Berhasil Terkirim!</p>
      <p class="text-xs mt-1">Silakan periksa kotak masuk atau folder spam email Anda.</p>
    </div>

    <form v-else @submit.prevent="handleSend" class="space-y-4">
      <AppInput
        v-model="email"
        label="Alamat Email"
        type="email"
        placeholder="nama@outlet.com"
        required
      >
        <template #prefix>
          <AppIcon name="mail" :size="18" />
        </template>
      </AppInput>

      <AppButton
        type="submit"
        variant="primary"
        size="lg"
        block
        :loading="loading"
      >
        <template #prefix>
          <AppIcon name="send" :size="18" />
        </template>
        Kirim Link Reset
      </AppButton>
    </form>

    <div class="mt-5 text-center">
      <router-link to="/auth/login" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF]">
        <AppIcon name="arrow_back" :size="16" />
        Kembali ke Login
      </router-link>
    </div>
  </AppCard>
</template>
