<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useAuthStore } from '@/stores/auth'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppCard from '@/components/ui/AppCard.vue'

const router = useRouter()
const authStore = useAuthStore()

const email = ref('owner@kopisenopati.id')
const password = ref('RahasiaKopi123!')
const loading = ref(false)

const handleLogin = async () => {
  loading.value = true
  try {
    const res = await authStore.login(email.value, password.value)
    if (res.success) {
      if (res.require2FA) {
        router.push('/auth/verify-2fa')
        return
      }
      if (authStore.isKasir) {
        router.push('/pos/orders')
      } else if (authStore.isKitchen) {
        router.push('/kds/queue')
      } else {
        router.push('/dashboard')
      }
      return
    }
  } catch (e) {
    // fallback below
  } finally {
    loading.value = false
  }

  if (email.value.includes('kasir')) {
    authStore.setRole('kasir')
    router.push('/pos/orders')
  } else if (email.value.includes('kitchen')) {
    authStore.setRole('kitchen_staff')
    router.push('/kds/queue')
  } else {
    authStore.setRole('owner')
    router.push('/dashboard')
  }
}

const quickLogin = async (role: 'owner' | 'kasir' | 'kitchen_staff') => {
  loading.value = true
  const creds = {
    owner: { email: 'owner@kopisenopati.id', pass: 'RahasiaKopi123!', redirect: '/dashboard' },
    kasir: { email: 'kasir@kopisenopati.id', pass: 'RahasiaKopi123!', redirect: '/pos/orders' },
    kitchen_staff: { email: 'kitchen@kopisenopati.id', pass: 'RahasiaKopi123!', redirect: '/kds/queue' },
  }
  const c = creds[role]
  email.value = c.email
  password.value = c.pass
  try {
    await authStore.login(c.email, c.pass)
    router.push(c.redirect)
  } catch (e) {
    authStore.setRole(role)
    router.push(c.redirect)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppCard>
    <div class="mb-6">
      <h2 class="text-xl font-extrabold text-[#1E293B] dark:text-white">Masuk ke Akun</h2>
      <p class="text-xs text-[#475569] dark:text-[#94A3B8] mt-1 font-medium">Silakan masukkan email dan password terdaftar Anda</p>
    </div>

    <form @submit.prevent="handleLogin" class="space-y-4">
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

      <div>
        <AppInput
          v-model="password"
          label="Password"
          type="password"
          placeholder="••••••••"
          required
        >
          <template #prefix>
            <AppIcon name="lock" :size="18" />
          </template>
        </AppInput>
        <div class="flex justify-end mt-1.5">
          <router-link to="/auth/forgot-password" class="text-xs font-bold text-[#4880FF] hover:underline">
            Lupa password?
          </router-link>
        </div>
      </div>

      <AppButton
        type="submit"
        variant="primary"
        size="lg"
        block
        :loading="loading"
        class="mt-2"
      >
        <template #prefix>
          <AppIcon name="login" :size="20" />
        </template>
        Masuk Sekarang
      </AppButton>
    </form>

    <!-- Quick Demo Logins (Clean Google Icons, No Emojis) -->
    <div class="mt-6 pt-5 border-t border-[#E2E8F0] dark:border-[#334155]">
      <p class="text-xs font-bold text-[#475569] dark:text-[#94A3B8] mb-2.5 flex items-center gap-1.5">
        <AppIcon name="auto_awesome" :size="16" class="text-[#4880FF]" />
        Akses Cepat (Demo Mode):
      </p>
      <div class="grid grid-cols-3 gap-2">
        <button
          type="button"
          @click="quickLogin('owner')"
          class="px-2.5 py-2 rounded-lg bg-[#E2EAF8] dark:bg-[#334155] text-[#2563EB] dark:text-[#93C5FD] text-xs font-bold hover:bg-[#d0def5] dark:hover:bg-[#475569] transition-colors cursor-pointer flex items-center justify-center gap-1.5"
        >
          <AppIcon name="crown" :size="16" />
          Owner
        </button>
        <button
          type="button"
          @click="quickLogin('kasir')"
          class="px-2.5 py-2 rounded-lg bg-[#E2EAF8] dark:bg-[#334155] text-[#2563EB] dark:text-[#93C5FD] text-xs font-bold hover:bg-[#d0def5] dark:hover:bg-[#475569] transition-colors cursor-pointer flex items-center justify-center gap-1.5"
        >
          <AppIcon name="point_of_sale" :size="16" />
          Kasir POS
        </button>
        <button
          type="button"
          @click="quickLogin('kitchen_staff')"
          class="px-2.5 py-2 rounded-lg bg-[#E2EAF8] dark:bg-[#334155] text-[#2563EB] dark:text-[#93C5FD] text-xs font-bold hover:bg-[#d0def5] dark:hover:bg-[#475569] transition-colors cursor-pointer flex items-center justify-center gap-1.5"
        >
          <AppIcon name="skillet" :size="16" />
          Kitchen
        </button>
      </div>
    </div>
  </AppCard>
</template>
