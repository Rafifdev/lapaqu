<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useTheme } from '@/composables/useTheme'
import { useAuthStore } from '@/stores/auth'
import AppAvatar from '@/components/ui/AppAvatar.vue'
import AppInput from '@/components/ui/AppInput.vue'

defineProps<{
  sidebarCollapsed?: boolean
}>()

const router = useRouter()
const { isDark, toggleTheme } = useTheme()
const authStore = useAuthStore()

const profileDropdownOpen = ref(false)
const notificationDropdownOpen = ref(false)
const searchQuery = ref('')

const switchRole = async (role: 'owner' | 'kasir' | 'kitchen_staff') => {
  profileDropdownOpen.value = false
  await authStore.switchRoleAndLogin(role)
  if (role === 'kasir') {
    router.push('/pos/orders')
  } else if (role === 'kitchen_staff') {
    router.push('/kds/queue')
  } else {
    router.push('/dashboard')
  }
}
</script>

<template>
  <header class="h-[70px] bg-white dark:bg-[#273142] border-b border-[#E2E8F0] dark:border-[#334155] px-4 md:px-8 flex items-center justify-between sticky top-0 z-20 transition-colors duration-200">
    <!-- Left: Search Box (Using reusable AppInput from Kasir Riwayat Order) -->
    <div class="flex items-center flex-1 max-w-md">
      <AppInput
        v-model="searchQuery"
        placeholder="Cari menu, pesanan, meja..."
        suffixIcon="search"
        clearable
        inputClass="!h-[42px]"
      />
    </div>

    <!-- Right Actions: Theme Toggle, Notifications, User Profile -->
    <div class="flex items-center gap-2 md:gap-4">
      <!-- Dark/Light Mode Switch -->
      <button
        @click="toggleTheme"
        class="w-10 h-10 rounded-full flex items-center justify-center text-[#475569] dark:text-[#CBD5E1] hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer"
        title="Ganti Tema"
      >
        <AppIcon v-if="isDark" name="light_mode" :size="20" class="text-[#FBBF24]" />
        <AppIcon v-else name="dark_mode" :size="20" />
      </button>

      <!-- Notification Bell -->
      <div class="relative">
        <button
          @click="notificationDropdownOpen = !notificationDropdownOpen"
          class="w-10 h-10 rounded-full flex items-center justify-center text-[#475569] dark:text-[#CBD5E1] hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors relative cursor-pointer"
        >
          <AppIcon name="notifications" :size="22" />
          <span class="absolute top-2 right-2 w-2.5 h-2.5 rounded-full bg-[#E11D48] border-2 border-white dark:border-[#273142]" />
        </button>

        <!-- Notification Dropdown -->
        <div
          v-if="notificationDropdownOpen"
          class="absolute right-0 mt-2 w-80 bg-white dark:bg-[#273142] rounded-2xl shadow-2xl border border-[#E2E8F0] dark:border-[#334155] py-3 z-50"
        >
          <div class="px-4 pb-2 border-b border-[#E2E8F0] dark:border-[#334155] flex items-center justify-between">
            <span class="text-base font-bold text-[#1E293B] dark:text-white">Notifikasi</span>
            <span class="text-sm text-[#4880FF] font-semibold cursor-pointer">Tandai Baca</span>
          </div>
          <div class="divide-y divide-[#E2E8F0] dark:divide-[#334155] max-h-64 overflow-y-auto">
            <div class="p-3 hover:bg-[#F8FAFC] dark:hover:bg-[#334155]/60 cursor-pointer">
              <p class="text-sm font-bold text-[#1E293B] dark:text-white">Order Masuk #ORD-001</p>
              <p class="text-xs text-[#475569] dark:text-[#94A3B8] mt-0.5">Meja M03 baru saja memesan 2 item via QR.</p>
              <span class="text-[10px] text-[#94A3B8] mt-1 block">1 menit yang lalu</span>
            </div>
            <div class="p-3 hover:bg-[#F8FAFC] dark:hover:bg-[#334155]/60 cursor-pointer">
              <p class="text-sm font-bold text-[#00B69B]">Pembayaran Berhasil</p>
              <p class="text-xs text-[#475569] dark:text-[#94A3B8] mt-0.5">QRIS Rp 72.000 terverifikasi otomatis Xendit.</p>
              <span class="text-[10px] text-[#94A3B8] mt-1 block">5 menit yang lalu</span>
            </div>
          </div>
        </div>
      </div>

      <!-- User Profile & Role Switcher -->
      <div class="relative">
        <button
          @click="profileDropdownOpen = !profileDropdownOpen"
          class="flex items-center gap-3 p-1.5 rounded-xl hover:bg-[#F8FAFC] dark:hover:bg-[#334155] transition-colors cursor-pointer"
        >
          <AppAvatar
            :name="authStore.currentUser?.name || 'User'"
            :image-url="authStore.currentUser?.avatarUrl"
            size="md"
            status="online"
          />
          <div class="hidden md:flex flex-col text-left">
            <span class="text-base font-bold text-[#1E293B] dark:text-white leading-tight">
              {{ authStore.currentUser?.name || 'Pengguna' }}
            </span>
            <span class="text-sm font-medium capitalize text-[#4880FF]">
              {{ authStore.currentUser?.role === 'kitchen_staff' ? 'Kitchen Staff' : authStore.currentUser?.role }}
            </span>
          </div>
          <AppIcon name="expand_more" :size="18" class="text-[#64748B] dark:text-[#94A3B8] hidden md:block" />
        </button>

        <!-- Dropdown Menu -->
        <div
          v-if="profileDropdownOpen"
          class="absolute right-0 mt-2 w-64 bg-white dark:bg-[#273142] rounded-2xl shadow-2xl border border-[#E2E8F0] dark:border-[#334155] p-3 z-50 space-y-2"
        >
          <div class="px-2 py-1.5 border-b border-[#E2E8F0] dark:border-[#334155]">
            <p class="text-xs text-[#64748B] dark:text-[#94A3B8] font-bold">Ganti Role (Preview):</p>
            <div class="grid grid-cols-3 gap-1 mt-2">
              <button
                @click="switchRole('owner')"
                :class="[
                  'px-2 py-1.5 rounded-lg text-xs font-bold text-center cursor-pointer transition-colors flex items-center justify-center gap-1',
                  authStore.currentUser?.role === 'owner' ? 'bg-[#4880FF] text-white' : 'bg-[#F8FAFC] dark:bg-[#334155] text-[#1E293B] dark:text-white',
                ]"
              >
                <AppIcon name="crown" :size="14" />
                Owner
              </button>
              <button
                @click="switchRole('kasir')"
                :class="[
                  'px-2 py-1.5 rounded-lg text-xs font-bold text-center cursor-pointer transition-colors flex items-center justify-center gap-1',
                  authStore.currentUser?.role === 'kasir' ? 'bg-[#4880FF] text-white' : 'bg-[#F8FAFC] dark:bg-[#334155] text-[#1E293B] dark:text-white',
                ]"
              >
                <AppIcon name="point_of_sale" :size="14" />
                Kasir
              </button>
              <button
                @click="switchRole('kitchen_staff')"
                :class="[
                  'px-2 py-1.5 rounded-lg text-xs font-bold text-center cursor-pointer transition-colors flex items-center justify-center gap-1',
                  authStore.currentUser?.role === 'kitchen_staff' ? 'bg-[#4880FF] text-white' : 'bg-[#F8FAFC] dark:bg-[#334155] text-[#1E293B] dark:text-white',
                ]"
              >
                <AppIcon name="skillet" :size="14" />
                Kitchen
              </button>
            </div>
          </div>

          <button
            @click="router.push('/dashboard/outlets'); profileDropdownOpen = false"
            class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold text-[#1E293B] dark:text-white hover:bg-[#F8FAFC] dark:hover:bg-[#334155] flex items-center gap-2 cursor-pointer"
          >
            <AppIcon name="storefront" :size="16" class="text-[#4880FF]" />
            Cabang & Outlet
          </button>

          <button
            @click="authStore.logout(); router.push('/auth/login'); profileDropdownOpen = false"
            class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold text-[#E11D48] hover:bg-[#FEE2E2] dark:hover:bg-[#FD5454]/15 cursor-pointer flex items-center gap-2"
          >
            <AppIcon name="logout" :size="16" />
            Keluar Akun
          </button>
        </div>
      </div>
    </div>
  </header>
</template>
