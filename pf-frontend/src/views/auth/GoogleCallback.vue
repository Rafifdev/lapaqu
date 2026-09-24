<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { initSessionExpiration } from '@/utils/session'
import apiClient from '@/services/api'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const statusMessage = ref('Menghubungkan Akun...')
const errorMessage = ref('')

onMounted(async () => {
  const token = route.query.token as string

  if (!token) {
    errorMessage.value = 'Token autentikasi tidak ditemukan.'
    setTimeout(() => {
      window.location.href = '/auth/login?error=token_missing'
    }, 1500)
    return
  }

  try {
    statusMessage.value = 'Memuat profil akun...'

    // 1. Simpan token & inisialisasi sesi sebelum request
    localStorage.setItem('lapaqu_token', token)
    initSessionExpiration()
    apiClient.defaults.headers.common['Authorization'] = `Bearer ${token}`

    // 2. Fetch user profile from /auth/me
    const res = await apiClient.get('/auth/me', {
      headers: {
        Authorization: `Bearer ${token}`
      }
    })
    const userData = res.data.user || res.data

    // 3. Simpan ke auth store Pinia
    authStore.setAuthData(token, userData)

    statusMessage.value = 'Berhasil! Mengalihkan ke dashboard...'
    setTimeout(() => {
      window.location.href = '/dashboard'
    }, 300)
  } catch (err: any) {
    console.error('Google callback error:', err)
    errorMessage.value = err?.response?.data?.message || err?.message || 'Gagal memuat profil setelah login.'
    setTimeout(() => {
      window.location.href = '/auth/login?error=profile_fetch_failed'
    }, 2500)
  }
})
</script>

<template>
  <div class="flex items-center justify-center min-h-screen bg-slate-50 dark:bg-[#1B2431] px-4">
    <div class="max-w-md w-full text-center p-8 bg-white dark:bg-[#273142] rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800">
      <div v-if="!errorMessage" class="flex flex-col items-center">
        <div class="animate-spin w-12 h-12 border-4 border-emerald-500 border-t-transparent rounded-full mb-4"></div>
        <h2 class="text-lg font-bold text-slate-800 dark:text-white">{{ statusMessage }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Harap tunggu sebentar, sedang memproses sesi Anda.</p>
      </div>

      <div v-else class="flex flex-col items-center text-red-500">
        <div class="w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center mb-4 text-red-600 font-bold text-xl">
          !
        </div>
        <h2 class="text-lg font-bold text-slate-800 dark:text-white">Gagal Masuk</h2>
        <p class="text-sm text-red-500 mt-1">{{ errorMessage }}</p>
        <p class="text-xs text-slate-400 mt-2">Mengalihkan kembali ke halaman login...</p>
      </div>
    </div>
  </div>
</template>
