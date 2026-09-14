<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppCard from '@/components/ui/AppCard.vue'

const router = useRouter()
const password = ref('')
const confirmPassword = ref('')
const loading = ref(false)

const handleReset = () => {
  loading.value = true
  setTimeout(() => {
    loading.value = false
    router.push('/auth/login')
  }, 500)
}
</script>

<template>
  <AppCard>
    <div class="mb-6">
      <h2 class="text-xl font-extrabold text-[#1E293B] dark:text-white">Reset Password Baru</h2>
      <p class="text-xs text-[#475569] dark:text-[#94A3B8] mt-1 font-medium">Masukkan kata sandi baru untuk akun Anda</p>
    </div>

    <form @submit.prevent="handleReset" class="space-y-4">
      <AppInput
        v-model="password"
        label="Password Baru"
        type="password"
        placeholder="Minimal 8 karakter"
        required
      >
        <template #prefix>
          <AppIcon name="lock" :size="18" />
        </template>
      </AppInput>

      <AppInput
        v-model="confirmPassword"
        label="Konfirmasi Password Baru"
        type="password"
        placeholder="Ulangi password"
        required
      >
        <template #prefix>
          <AppIcon name="lock" :size="18" />
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
          <AppIcon name="check" :size="20" />
        </template>
        Simpan Password Baru
      </AppButton>
    </form>
  </AppCard>
</template>
