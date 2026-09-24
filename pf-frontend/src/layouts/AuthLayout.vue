<script setup lang="ts">
import { ref, computed, provide, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Store } from 'lucide-vue-next'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppSkeleton from '@/components/ui/AppSkeleton.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppPageTransition from '@/components/ui/AppPageTransition.vue'
import { useTheme } from '@/composables/useTheme'
import loginBanner from '@/assets/thumbnail_login/thumbnail-login.png'
import selfOrderBanner from '@/assets/thumbnail_login/self-order.png'
import apiClient from '@/services/api'

const route = useRoute()
const router = useRouter()
const { isDark, toggleTheme } = useTheme()

// Rute auth yang menggunakan split-screen layout (Login, Register, Forgot Password, Reset Password)
const isAuthSplitPage = computed(() =>
  route.name === 'login' || route.path === '/auth/login' || route.path === '/login' ||
  route.name === 'register' || route.path === '/auth/register' || route.path === '/register' ||
  route.name === 'forgot-password' || route.path === '/auth/forgot-password' || route.path === '/forgot-password' ||
  route.name === 'reset-password' || route.path === '/auth/reset-password' || route.path === '/reset-password'
)

const isOtpStep = ref(false)
provide('setOtpStep', (val: boolean) => {
  isOtpStep.value = val
})

// Divider dan tombol sosial hanya untuk login dan register (sembunyikan di form OTP)
const isSocialLoginVisible = computed(() => {
  if (isOtpStep.value) return false
  return route.name === 'login' || route.path === '/auth/login' || route.path === '/login' ||
    route.name === 'register' || route.path === '/auth/register' || route.path === '/register'
})


// Outlet Staff Header Info & Logout
const isOutletStaffPage = computed(() =>
  route.name === 'outlet-staff' || route.path.includes('/outlet-staff') || route.path.includes('/outlet/staff')
)

const outletName = ref(localStorage.getItem('lapaqu_outlet_name') || '')
const tenantName = ref(localStorage.getItem('lapaqu_tenant_name') || '')

const syncOutletInfo = () => {
  let savedOutlet = localStorage.getItem('lapaqu_outlet_name') || ''
  let savedTenant = localStorage.getItem('lapaqu_tenant_name') || ''
  const paired = localStorage.getItem('lapaqu_paired_outlet')
  if (paired) {
    try {
      const parsed = JSON.parse(paired)
      if (parsed.name && !savedOutlet) savedOutlet = parsed.name
      if (parsed.tenant_name && !savedTenant) savedTenant = parsed.tenant_name
    } catch {}
  }
  outletName.value = savedOutlet || ''
  tenantName.value = savedTenant || 'Lapaqu'
}

const isLogoutModalOpen = ref(false)

const handleLogoutOutlet = () => {
  localStorage.removeItem('lapaqu_paired_outlet')
  localStorage.removeItem('lapaqu_device_token')
  localStorage.removeItem('lapaqu_active_order_id')
  sessionStorage.clear()
  isLogoutModalOpen.value = false
  router.push('/auth/outlet-login')
}

// Halaman outlet login (Style sama persis dengan sisi kiri, tapi sisi kanan TANPA div biru)
const isOutletPage = computed(() =>
  route.name === 'outlet-login' || route.path === '/auth/outlet-login' || route.path === '/outlet/login' ||
  route.name === 'outlet-staff' || route.path === '/auth/outlet-staff' || route.path === '/outlet/staff'
)

// Shared state loading skeleton untuk split page
const isPageLoading = ref(true)
provide('isAuthPageLoading', isPageLoading)

const imageCardRef = ref<HTMLElement | null>(null)
const imageWidth = ref<number | null>(null)

const updateImageWidth = () => {
  if (imageCardRef.value) {
    imageWidth.value = imageCardRef.value.offsetWidth
  }
}

let resizeObserver: ResizeObserver | null = null

onMounted(() => {
  syncOutletInfo()
  window.addEventListener('storage', syncOutletInfo)
  window.addEventListener('outlet-info-updated', syncOutletInfo)
  updateImageWidth()
  if (imageCardRef.value && typeof ResizeObserver !== 'undefined') {
    resizeObserver = new ResizeObserver(() => {
      updateImageWidth()
    })
    resizeObserver.observe(imageCardRef.value)
  }

  // Preload hero images
  const img1 = new Image()
  const img2 = new Image()
  let loaded = 0
  const onDone = () => {
    loaded++
    if (loaded >= 2) {
      setTimeout(() => {
        isPageLoading.value = false
        updateImageWidth()
      }, 350)
    }
  }
  img1.onload = onDone
  img1.onerror = onDone
  img2.onload = onDone
  img2.onerror = onDone
  img1.src = loginBanner
  img2.src = selfOrderBanner

  setTimeout(() => {
    isPageLoading.value = false
    updateImageWidth()
  }, 600)
})

onUnmounted(() => {
  resizeObserver?.disconnect()
  window.removeEventListener('storage', syncOutletInfo)
  window.removeEventListener('outlet-info-updated', syncOutletInfo)
})

const handleGoogleLogin = () => {
  window.location.href = `${apiClient.defaults.baseURL || '/api'}/auth/google`
}

const handleFacebookLogin = () => {
  window.location.href = `${apiClient.defaults.baseURL || '/api'}/auth/facebook`
}

const handleOutletLogin = () => {
  const saved = localStorage.getItem('lapaqu_paired_outlet')
  if (saved) {
    try {
      const parsed = JSON.parse(saved)
      if (parsed?.id) {
        router.push('/auth/outlet-staff')
        return
      }
    } catch {}
  }
  router.push('/auth/outlet-login')
}
</script>

<template>
  <div class="w-full min-h-screen overflow-hidden">
    <Transition name="auth-layout" mode="out-in">
      <!-- Dedicated Split-Screen Layout for Login & Register (Ada Div Biru di Sisi Kanan) -->
      <div v-if="isAuthSplitPage" key="auth-split-layout"
      class="h-screen w-full bg-white dark:bg-[#1B2431] flex flex-col justify-between p-6 md:p-8 transition-colors overflow-hidden">

    <!-- Main Content Container: Left Form + Right Hero Card -->
    <div class="w-full flex-1 min-h-0 flex flex-col lg:flex-row items-stretch gap-8 xl:gap-12">

      <!-- LEFT SIDE: Form & Branding Shell -->
      <div
        class="w-full lg:flex-1 min-w-0 flex flex-col justify-between max-w-xl mx-auto lg:mx-0 lg:max-w-none h-full overflow-hidden">
        <!-- Top Branding Logo -->
        <div class="flex items-center gap-3 shrink-0">
          <template v-if="isPageLoading">
            <AppSkeleton width="40px" height="40px" rounded="lg" />
            <AppSkeleton width="110px" height="28px" rounded="md" />
          </template>
          <template v-else>
            <img src="@/assets/brand_logo/lapaqu-logo.png" alt="Lapaqu"
              class="w-10 h-10 object-contain drop-shadow-xs" />
            <span class="text-2xl font-black tracking-tight text-[#1E293B] dark:text-white">
              <span class="text-[#4880FF]">Lapa</span>qu
            </span>
          </template>
        </div>

        <!-- Center Form Area: Form Berganti, Divider & Social Buttons Tetap Diam -->
        <div
          class="my-auto py-2 sm:py-3 max-w-[512px] w-full mx-auto flex flex-col justify-center overflow-y-auto no-scrollbar">
          <!-- Dynamic Form (Router View) -->
          <router-view v-slot="{ Component, route: currentRoute }">
            <AppPageTransition :component="Component" :route="currentRoute" />
          </router-view>

          <!-- Divider: Or Sign in / up with (Hanya tampil di Login & Register, sembunyikan di form OTP) -->
          <div v-if="isSocialLoginVisible" class="relative my-3 shrink-0">
            <template v-if="isPageLoading">
              <AppSkeleton width="100%" height="16px" rounded="md" />
            </template>
            <template v-else>
              <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-[#E2E8F0] dark:border-[#334155]"></div>
              </div>
              <div class="relative flex justify-center text-xs sm:text-sm">
                <span class="px-3 bg-white dark:bg-[#1B2431] text-[#94A3B8] font-medium">
                  {{ (route.name === 'register' || route.path.includes('/register')) ? 'Atau daftar dengan' : 'Atau masuk dengan' }}
                </span>
              </div>
            </template>
          </div>

          <!-- Social Login Buttons: Google, Facebook, Outlet (Hanya tampil di Login & Register, sembunyikan di form OTP) -->
          <div v-if="isSocialLoginVisible" class="grid grid-cols-3 gap-2.5 sm:gap-3 shrink-0">
            <template v-if="isPageLoading">
              <AppSkeleton width="100%" height="44px" rounded="xl" />
              <AppSkeleton width="100%" height="44px" rounded="xl" />
              <AppSkeleton width="100%" height="44px" rounded="xl" />
            </template>
            <template v-else>
              <!-- Google -->
              <button type="button" @click="handleGoogleLogin"
                class="h-11 px-2 sm:px-3 rounded-xl bg-white dark:bg-[#273142] border border-[#E2E8F0] dark:border-[#334155] hover:bg-[#F8FAFC] dark:hover:bg-[#334155] text-sm font-bold text-[#1E293B] dark:text-white flex items-center justify-center gap-1.5 sm:gap-2 transition-all cursor-pointer">
                <svg class="w-4.5 h-4.5 shrink-0" viewBox="0 0 24 24">
                  <path
                    d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                    fill="#4285F4" />
                  <path
                    d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                    fill="#34A853" />
                  <path
                    d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"
                    fill="#FBBC05" />
                  <path
                    d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"
                    fill="#EA4335" />
                </svg>
                <span>Google</span>
              </button>

              <!-- Facebook -->
              <button type="button" @click="handleFacebookLogin"
                class="h-11 px-2 sm:px-3 rounded-xl bg-white dark:bg-[#273142] border border-[#E2E8F0] dark:border-[#334155] hover:bg-[#F8FAFC] dark:hover:bg-[#334155] text-sm font-bold text-[#1E293B] dark:text-white flex items-center justify-center gap-1.5 sm:gap-2 transition-all cursor-pointer">
                <svg class="w-4.5 h-4.5 sm:w-5 sm:h-5 shrink-0 text-[#1877F2]" viewBox="0 0 24 24" fill="currentColor">
                  <path
                    d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                </svg>
                <span>Facebook</span>
              </button>

              <!-- Outlet Button -->
              <button type="button" @click="handleOutletLogin"
                class="group h-11 px-2 sm:px-3 rounded-xl bg-white dark:bg-[#273142] border border-[#E2E8F0] dark:border-[#334155] hover:bg-[#F8FAFC] dark:hover:bg-[#334155] hover:border-[#4880FF]/40 hover:text-[#4880FF] active:scale-95 text-sm font-bold text-[#1E293B] dark:text-white flex items-center justify-center gap-1.5 sm:gap-2 transition-all duration-200 cursor-pointer shadow-xs hover:shadow-sm">
                <Store class="w-4.5 h-4.5 sm:w-5 sm:h-5 shrink-0 text-[#1E293B] dark:text-white group-hover:text-[#4880FF] group-hover:scale-110 transition-all duration-200" />
                <span>Outlet</span>
              </button>
            </template>
          </div>

          <!-- Bottom Auth Switch Link -->
          <div v-if="!isOtpStep" class="mt-4 flex justify-center shrink-0">
            <template v-if="isPageLoading">
              <AppSkeleton width="220px" height="16px" rounded="md" />
            </template>
            <template v-else>
              <p v-if="route.name === 'register' || route.path.includes('/register')"
                class="text-xs sm:text-sm text-center text-[#64748B] dark:text-[#94A3B8] font-medium">
                Sudah punya akun?
                <router-link to="/auth/login" class="font-bold text-[#4880FF] hover:underline ml-1">
                  Masuk
                </router-link>
              </p>
              <p v-else-if="route.name === 'forgot-password' || route.path.includes('/forgot-password') || route.name === 'reset-password' || route.path.includes('/reset-password')"
                class="text-xs sm:text-sm text-center text-[#64748B] dark:text-[#94A3B8] font-medium">
                Kembali ke halaman
                <router-link to="/auth/login" class="font-bold text-[#4880FF] hover:underline ml-1">
                  Masuk
                </router-link>
              </p>
              <p v-else class="text-xs sm:text-sm text-center text-[#64748B] dark:text-[#94A3B8] font-medium">
                Belum punya akun?
                <router-link to="/auth/register" class="font-bold text-[#4880FF] hover:underline ml-1">
                  Daftar Sekarang
                </router-link>
              </p>
            </template>
          </div>
        </div>

        <!-- Bottom Copyright & Privacy Policy -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-2 pt-2 text-xs text-[#94A3B8] shrink-0">
          <template v-if="isPageLoading">
            <AppSkeleton width="190px" height="14px" rounded="md" />
            <AppSkeleton width="80px" height="14px" rounded="md" />
          </template>
          <template v-else>
            <p>Copyright © 2026 Lapaqu POS Platform.</p>
            <a href="#" class="hover:underline hover:text-[#4880FF] transition-colors">Kebijakan Privasi</a>
          </template>
        </div>
      </div>

      <!-- RIGHT SIDE: Modern Blue Hero Card with Subtle Top-Left White Gradient (HANYA DI LOGIN & REGISTER) -->
      <div
        class="hidden lg:flex w-full lg:w-[40%] bg-[#3B5BFF] dark:bg-[#2B4BEE] rounded-lg p-8 xl:p-10 flex-col justify-between relative overflow-hidden text-white h-full">

        <!-- Gradasi putih tipis di sepanjang sisi atas (Top Edge Soft White Gradient) -->
        <div
          class="absolute inset-x-0 top-0 h-48 bg-gradient-to-b from-white/25 via-white/8 to-transparent pointer-events-none z-0">
        </div>

        <!-- Gradasi agak hitam di sepanjang sisi bawah (Bottom Edge Dark Shading) -->
        <div
          class="absolute inset-x-0 bottom-0 h-40 bg-gradient-to-t from-black/15 via-black/5 to-transparent pointer-events-none z-0">
        </div>

        <!-- Hero Content Section -->
        <div class="relative z-10 w-full flex-1 min-h-0 flex flex-col justify-center items-center">
          <div class="w-fit max-w-full flex flex-col items-start">
            <!-- Hero Text -->
            <div class="space-y-2 mb-6 text-left shrink-0 w-full"
              :style="imageWidth ? { width: `${imageWidth}px` } : {}">
              <template v-if="isPageLoading">
                <div class="space-y-2.5">
                  <div class="w-[85%] h-7 xl:h-8 rounded-md bg-white/20 animate-pulse"></div>
                  <div class="w-[60%] h-7 xl:h-8 rounded-md bg-white/20 animate-pulse"></div>
                  <div class="w-[90%] h-4 rounded-md bg-white/20 animate-pulse mt-3"></div>
                </div>
              </template>
              <template v-else>
                <h2 class="text-2xl sm:text-2xl xl:text-3xl font-bold tracking-tight leading-[1.2] text-white">
                  Transformasi bisnis Anda bersama Lapaqu POS
                </h2>
                <p class="text-sm xl:text-base text-white/85 font-normal leading-relaxed">
                  Masuk untuk mengakses dashboard POS dan kelola usaha Anda
                </p>
              </template>
            </div>

            <!-- Double Mockup Composition -->
            <div class="relative inline-flex w-fit">
              <template v-if="isPageLoading">
                <div
                  class="w-[420px] max-w-full h-[320px] sm:h-[380px] max-h-[58vh] rounded-lg bg-white/20 animate-pulse p-2">
                </div>
              </template>
              <template v-else>
                <!-- Main Dashboard Card -->
                <div ref="imageCardRef" class="inline-flex w-fit rounded-lg bg-white p-2">
                  <img :src="loginBanner" alt="Lapaqu Dashboard Overview" @load="updateImageWidth"
                    class="block w-auto h-auto max-h-[58vh] rounded-md object-contain" />
                </div>

                <!-- Self-Order Mobile Mockup -->
                <div
                  class="absolute -bottom-4 -right-6 sm:-bottom-6 sm:-right-8 inline-flex w-fit rounded-lg bg-white p-1.5 shadow-[0_20px_45px_rgba(0,0,0,0.35)] drop-shadow-xl z-20">
                  <img :src="selfOrderBanner" alt="Customer Self-Order"
                    class="block w-auto h-auto max-h-[30vh] sm:max-h-[34vh] rounded-md object-contain" />
                </div>
              </template>
            </div>
          </div>
        </div>

      </div>

    </div>
  </div>

    <!-- Dedicated Outlet Login Layout (Style sama seperti saat ini, cuman di sisi kanan GADA DIV BIRU) -->
    <div v-else-if="isOutletPage" key="auth-outlet-layout"
      class="h-screen w-full bg-white dark:bg-[#1B2431] flex flex-col justify-between p-6 md:p-8 transition-colors overflow-hidden">
    <!-- Top Bar: Branding logo di kiri & Tombol Aksi di kanan -->
    <div class="flex items-center justify-between shrink-0 h-[60px] pb-2">
      <!-- Left: Logo & Brand with Tenant & Outlet Name (Persis seperti di Dashboard) -->
      <div v-if="isOutletStaffPage" class="flex items-center gap-2.5 overflow-hidden py-1 max-w-[280px] sm:max-w-md">
        <img src="@/assets/brand_logo/lapaqu-logo.png" alt="Lapaqu" class="w-9 h-9 sm:w-10 sm:h-10 object-contain shrink-0 drop-shadow-xs" />
        <div class="flex flex-col min-w-0 flex-1 justify-center">
          <span
            class="text-sm sm:text-[15px] font-black tracking-tight font-sans text-[#1E293B] dark:text-white leading-tight truncate"
            :title="tenantName">
            {{ tenantName }}
          </span>
          <span
            class="text-[10px] sm:text-[11px] font-semibold text-[#64748B] dark:text-[#94A3B8] leading-none mt-1 truncate"
            :title="outletName">
            {{ outletName }}
          </span>
        </div>
      </div>
      <!-- Default Logo untuk halaman outlet-login (sebelum pairing) -->
      <div v-else class="flex items-center gap-3">
        <img src="@/assets/brand_logo/lapaqu-logo.png" alt="Lapaqu" class="w-10 h-10 object-contain drop-shadow-xs" />
        <span class="text-2xl font-black tracking-tight text-[#1E293B] dark:text-white">
          <span class="text-[#4880FF]">Lapa</span>qu
        </span>
      </div>

      <!-- Right Actions: Dark Mode Switch & Logout Button -->
      <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
        <!-- Dark/Light Mode Switch -->
        <button type="button" @click="toggleTheme"
          class="w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-[#475569] dark:text-[#CBD5E1] hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer"
          title="Ganti Tema">
          <AppIcon v-if="isDark" name="light_mode" :size="18" class="text-[#FBBF24]" />
          <AppIcon v-else name="dark_mode" :size="18" />
        </button>

        <!-- Logout / Ganti Outlet Button (Hanya di halaman outlet staff) -->
        <button v-if="isOutletStaffPage" type="button" @click="isLogoutModalOpen = true"
          class="w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-[#FD5454] hover:bg-[#FFEBEB] dark:hover:bg-[#FD5454]/15 transition-colors cursor-pointer"
          title="Keluar / Putus Koneksi Outlet">
          <AppIcon name="logout" :size="18" />
        </button>
      </div>
    </div>

    <!-- Center Form Area: Form Outlet (Top-aligned untuk outlet-staff agar posisi header stabil) -->
    <div
      :class="[
        'w-full mx-auto flex flex-col overflow-y-auto no-scrollbar',
        (route.name === 'outlet-staff' || route.path.includes('/outlet-staff') || route.path.includes('/outlet/staff'))
          ? 'max-w-6xl flex-1 justify-start pt-6 sm:pt-10'
          : 'my-auto py-2 sm:py-3 max-w-[512px] justify-center'
      ]">
      <router-view v-slot="{ Component, route: currentRoute }">
        <AppPageTransition :component="Component" :route="currentRoute" />
      </router-view>
    </div>

    <!-- Bottom Copyright & Privacy Policy -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-2 pt-2 text-xs text-[#94A3B8] shrink-0">
      <p>Copyright © 2026 Lapaqu POS Platform.</p>
      <a href="#" class="hover:underline hover:text-[#4880FF] transition-colors">Kebijakan Privasi</a>
    </div>
  </div>

    <!-- Standard Centered Box Layout for other Auth Pages (2FA, Forgot Password, Reset Password) -->
    <div v-else key="auth-centered-layout"
      class="min-h-screen bg-[#F5F6FA] dark:bg-[#1B2431] flex flex-col justify-center items-center p-4 font-sans relative transition-colors duration-200">
    <!-- Top Theme Toggle for other auth pages -->
    <div class="absolute top-6 right-6">
      <button @click="toggleTheme"
        class="w-10 h-10 rounded-full flex items-center justify-center bg-white dark:bg-[#273142] border border-[#E2E8F0] dark:border-[#334155] text-[#475569] dark:text-[#CBD5E1] shadow-sm hover:scale-105 transition-all cursor-pointer">
        <AppIcon v-if="isDark" name="light_mode" :size="20" class="text-[#FBBF24]" />
        <AppIcon v-else name="dark_mode" :size="20" />
      </button>
    </div>

    <!-- Centered Box -->
    <div class="w-full max-w-lg">
      <!-- Logo Branding -->
      <div class="text-center mb-8 flex flex-col items-center">
        <img src="@/assets/brand_logo/lapaqu-logo.png" alt="Lapaqu"
          class="w-16 h-16 object-contain mb-3 drop-shadow-md" />
        <h1 class="text-3xl md:text-4xl font-black text-[#1E293B] dark:text-white tracking-tight">
          <span class="text-[#4880FF]">Lapa</span>qu
        </h1>
        <p class="text-xs font-bold uppercase tracking-widest text-[#4880FF] mt-0.5">
          POS SaaS Platform
        </p>
        <p class="text-xs md:text-sm text-[#475569] dark:text-[#94A3B8] mt-2 font-medium">
          Platform Kasir & Self-Order Terintegrasi
        </p>
      </div>

      <router-view v-slot="{ Component, route: currentRoute }">
        <AppPageTransition :component="Component" :route="currentRoute" />
      </router-view>
    </div>
  </div>
  </Transition>
  
    <!-- Alert Modal: Konfirmasi Keluar / Ganti Outlet (Persis seperti modal logout yang sudah ada di POS) -->
    <AppModal v-model="isLogoutModalOpen" title="Konfirmasi Keluar Outlet" maxWidth="sm">
      <div class="space-y-3 py-2 text-center">
        <div
          class="w-14 h-14 rounded-full bg-rose-50 dark:bg-rose-950/40 text-[#FD5454] flex items-center justify-center mx-auto mb-2">
          <AppIcon name="logout" :size="28" />
        </div>
        <h3 class="text-base font-bold text-[#202224] dark:text-white">Putus Koneksi Outlet?</h3>
        <p class="text-sm text-[#64748B] dark:text-[#94A3B8]">
          Perangkat ini akan diputus dari <strong class="text-slate-700 dark:text-slate-200">{{ outletName }}</strong>. Anda perlu memasukkan kode pairing baru untuk menghubungkannya kembali.
        </p>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-3 w-full">
          <AppButton variant="outline" size="md" @click="isLogoutModalOpen = false" class="!rounded-lg flex-1">
            Batal
          </AppButton>
          <AppButton variant="primary" size="md" @click="handleLogoutOutlet"
            class="!rounded-lg flex-1 !bg-[#FD5454] !text-white hover:!bg-[#E03E3E] !border-[#FD5454]">
            Ya, Keluar
          </AppButton>
        </div>
      </template>
    </AppModal>
  </div>
</template>

<style scoped>
/* Smooth transition between auth layouts (Split <-> Outlet <-> Centered) */
.auth-layout-enter-active,
.auth-layout-leave-active {
  transition: opacity 0.24s cubic-bezier(0.16, 1, 0.3, 1), transform 0.24s cubic-bezier(0.16, 1, 0.3, 1);
}

.auth-layout-enter-from {
  opacity: 0;
  transform: translateY(6px);
}

.auth-layout-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

@media (prefers-reduced-motion: reduce) {
  .auth-layout-enter-active,
  .auth-layout-leave-active {
    transition: none !important;
  }
}

/* Hide scrollbar for Chrome, Safari and Opera */
.no-scrollbar::-webkit-scrollbar {
  display: none;
}

/* Hide scrollbar for IE, Edge and Firefox */
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
