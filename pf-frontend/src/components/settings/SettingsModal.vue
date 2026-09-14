<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import {
  Settings,
  User,
  Store,
  CreditCard,
  Sparkles,
  Bell,
  Search,
  X,
  Copy,
  Check,
  Download,
  Sun,
  Moon,
  Monitor,
  CheckCircle2,
  Palette
} from 'lucide-vue-next'
import { useFormat } from '@/composables/useFormat'
import { useTheme } from '@/composables/useTheme'
import { useAuthStore } from '@/stores/auth'
import { useSettingsModal, type SettingsTab } from '@/composables/useSettingsModal'

const { isOpen, activeTab, searchQuery, closeSettingsModal, setTab } = useSettingsModal()
const { formatCurrency } = useFormat()
const { isDark, toggleTheme } = useTheme()
const authStore = useAuthStore()

// ==========================================
// NAVIGATION
// ==========================================
interface NavItem {
  key: SettingsTab
  label: string
  icon: any
}

const navItems: NavItem[] = [
  { key: 'general', label: 'General', icon: Settings },
  { key: 'account', label: 'Account', icon: User },
  { key: 'branding', label: 'Branding', icon: Store },
  { key: 'payment', label: 'Payment', icon: CreditCard },
  { key: 'billing', label: 'Billing', icon: Sparkles },
]

const filteredNavItems = computed(() => {
  if (!searchQuery.value.trim()) return navItems
  const q = searchQuery.value.toLowerCase()
  return navItems.filter(item => item.label.toLowerCase().includes(q))
})

// Close with ESC key
const handleKeyDown = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && isOpen.value) {
    closeSettingsModal()
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown)
})

// ==========================================
// GENERAL TAB — PROFILE
// ==========================================
const fullName = ref('Yoa Pipp')
const callName = ref('Yoa')
const roleDesc = ref('owner')

// ==========================================
// GENERAL TAB — PREFERENCES
// ==========================================
const motionPreference = ref<'system' | 'reduced'>('system')
const themePreference = ref<'system' | 'light' | 'dark'>('dark')

const setAppearance = (mode: 'system' | 'light' | 'dark') => {
  themePreference.value = mode
  if (mode === 'light' && isDark.value) {
    toggleTheme()
  } else if (mode === 'dark' && !isDark.value) {
    toggleTheme()
  }
}

// ==========================================
// GENERAL TAB — LANGUAGE
// ==========================================
const selectedLanguage = ref('id')

// ==========================================
// GENERAL TAB — NOTIFICATIONS
// ==========================================
const notifyOrderSound = ref(true)
const notifyBrowserPush = ref(true)
const notifyWaiterBell = ref(true)
const notifyKdsVoid = ref(true)
const notifyWaDaily = ref(true)
const waAdminNumber = ref('0812-3456-7890')
const notifyEmailWeekly = ref(true)
const isSavingNotif = ref(false)
const notifSaved = ref(false)

const handleSaveNotif = () => {
  isSavingNotif.value = true
  setTimeout(() => {
    isSavingNotif.value = false
    notifSaved.value = true
    setTimeout(() => { notifSaved.value = false }, 2500)
  }, 500)
}

// ==========================================
// ACCOUNT TAB
// ==========================================
const userEmail = ref('yoa@kopikenangan.id')
const copiedTenantId = ref(false)
const currentPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const passwordError = ref('')
const passwordChangeSuccess = ref(false)
const isChangingPassword = ref(false)
const isLogoutConfirmOpen = ref(false)

const copyTenantId = () => {
  navigator.clipboard.writeText('ten_lapaqu_senopati01')
  copiedTenantId.value = true
  setTimeout(() => { copiedTenantId.value = false }, 2000)
}

const handleChangePassword = () => {
  passwordError.value = ''
  if (!currentPassword.value) {
    passwordError.value = 'Kata sandi saat ini harus diisi.'
    return
  }
  if (newPassword.value.length < 8) {
    passwordError.value = 'Kata sandi baru minimal 8 karakter.'
    return
  }
  if (newPassword.value !== confirmPassword.value) {
    passwordError.value = 'Konfirmasi kata sandi baru tidak cocok.'
    return
  }

  isChangingPassword.value = true
  setTimeout(() => {
    isChangingPassword.value = false
    passwordChangeSuccess.value = true
    currentPassword.value = ''
    newPassword.value = ''
    confirmPassword.value = ''
    setTimeout(() => { passwordChangeSuccess.value = false }, 3000)
  }, 700)
}

const handleLogout = () => {
  closeSettingsModal()
  authStore.logout()
  window.location.href = '/auth/login'
}

// ==========================================
// BRANDING TAB
// ==========================================
const outletName = ref('Kopi Kenangan Senopati')
const outletSlogan = ref('Specialty Artisan Coffee & Pastry')
const outletAddress = ref('Jl. Senopati No. 45, Kebayoran Baru, Jakarta Selatan 12190')
const outletPhone = ref('0812-3456-7890')
const subdomain = ref('kopisenopati')
const enableTax = ref(true)
const taxRate = ref(10)
const enableServiceCharge = ref(false)
const serviceChargeRate = ref(5)
const tableTimeoutMinutes = ref(90)
const isSavingBranding = ref(false)
const brandingSaved = ref(false)

const handleSaveBranding = () => {
  isSavingBranding.value = true
  setTimeout(() => {
    isSavingBranding.value = false
    brandingSaved.value = true
    setTimeout(() => { brandingSaved.value = false }, 2500)
  }, 600)
}

// ==========================================
// PAYMENT TAB
// ==========================================
const copiedSubAccountId = ref(false)
const isChangeModalOpen = ref(false)
const bankName = ref('Bank Central Asia (BCA)')
const accountNumber = ref('8899001122')
const accountHolder = ref('PT Kopi Kenangan Senopati')

const reqBankName = ref('')
const reqAccountNumber = ref('')
const reqAccountHolder = ref('')
const reqNotes = ref('')
const isSubmittingRequest = ref(false)
const requestSubmittedSuccess = ref(false)

const copySubAccount = () => {
  navigator.clipboard.writeText('sub_acc_xnd_8829104')
  copiedSubAccountId.value = true
  setTimeout(() => { copiedSubAccountId.value = false }, 2000)
}

const openChangeModal = () => {
  reqBankName.value = bankName.value
  reqAccountNumber.value = accountNumber.value
  reqAccountHolder.value = accountHolder.value
  reqNotes.value = ''
  requestSubmittedSuccess.value = false
  isChangeModalOpen.value = true
}

const handleSubmitChangeRequest = () => {
  if (!reqBankName.value || !reqAccountNumber.value || !reqAccountHolder.value) return
  isSubmittingRequest.value = true
  setTimeout(() => {
    isSubmittingRequest.value = false
    requestSubmittedSuccess.value = true
    setTimeout(() => {
      isChangeModalOpen.value = false
    }, 1500)
  }, 900)
}

const settlements = ref([
  { id: 'SET-2026-0830', date: '30 Agu 2026, 09:14', bank: 'BCA 8899001122', net: 4835000 },
  { id: 'SET-2026-0829', date: '29 Agu 2026, 09:02', bank: 'BCA 8899001122', net: 3905000 },
  { id: 'SET-2026-0828', date: '28 Agu 2026, 09:10', bank: 'BCA 8899001122', net: 5595000 },
  { id: 'SET-2026-0827', date: '27 Agu 2026, 09:15', bank: 'BCA 8899001122', net: 4105000 },
])

// ==========================================
// BILLING TAB
// ==========================================
const invoices = ref([
  { id: 'INV-XND-202608', date: '01 Agu 2026', period: '01 Agu - 31 Agu 2026', amount: 299000 },
  { id: 'INV-XND-202607', date: '01 Jul 2026', period: '01 Jul - 31 Jul 2026', amount: 299000 },
  { id: 'INV-XND-202606', date: '01 Jun 2026', period: '01 Jun - 30 Jun 2026', amount: 299000 },
])
</script>

<template>
  <Teleport to="body">
    <!-- BACKDROP -->
    <div
      v-if="isOpen"
      class="fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-5 md:p-8"
    >
      <div
        class="fixed inset-0 bg-black/50 backdrop-blur-xs transition-opacity"
        @click="closeSettingsModal"
      />

      <!-- MODAL CONTAINER -->
      <div
        class="relative w-full max-w-[1140px] h-[85vh] max-h-[780px] bg-white dark:bg-[#1E293B] text-[#202224] dark:text-white rounded-2xl border border-[#E2E8F0] dark:border-[#334155] shadow-2xl shadow-black/25 flex flex-col md:flex-row overflow-hidden z-10 font-sans transition-colors"
        @click.stop
      >
        <!-- CLOSE (X) BUTTON -->
        <button
          type="button"
          @click="closeSettingsModal"
          class="absolute top-5 right-6 z-30 p-1.5 rounded-lg text-[#64748B] dark:text-[#94A3B8] hover:text-[#202224] dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer"
          title="Tutup (Esc)"
        >
          <X class="w-5 h-5" />
        </button>

        <!-- ========================================= -->
        <!-- LEFT SIDEBAR NAVIGATION -->
        <!-- ========================================= -->
        <div class="w-full md:w-[260px] bg-[#F8FAFC] dark:bg-[#151D2A] border-b md:border-b-0 md:border-r border-[#E2E8F0] dark:border-[#334155] p-4 flex flex-col shrink-0 transition-colors">
          <!-- Search -->
          <div class="relative mb-4">
            <Search class="w-3.5 h-3.5 text-[#64748B] dark:text-[#94A3B8] absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search"
              class="w-full pl-8 pr-3 py-1.5 bg-white dark:bg-[#0F172A] border border-[#E2E8F0] dark:border-[#334155] rounded-lg text-xs text-[#202224] dark:text-white placeholder-[#64748B] dark:placeholder-[#94A3B8] focus:outline-none focus:border-[#4880FF] transition-all"
            />
          </div>

          <!-- "Settings" label -->
          <div class="text-[10px] font-bold tracking-wider text-[#64748B] dark:text-[#94A3B8] uppercase px-3 py-1 mb-1">
            Settings
          </div>

          <!-- Nav Items (flat list) -->
          <div class="flex-1 overflow-y-auto space-y-0.5 pr-1 custom-scroll">
            <button
              v-for="item in filteredNavItems"
              :key="item.key"
              type="button"
              @click="setTab(item.key)"
              :class="[
                'w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs transition-all text-left cursor-pointer',
                activeTab === item.key
                  ? 'bg-black/5 dark:bg-white/10 text-[#202224] dark:text-white font-bold'
                  : 'text-[#64748B] dark:text-[#94A3B8] hover:text-[#202224] dark:hover:text-white hover:bg-black/[0.03] dark:hover:bg-white/5 font-medium'
              ]"
            >
              <component
                :is="item.icon"
                :class="[
                  'w-4 h-4 shrink-0 transition-colors',
                  activeTab === item.key ? 'text-[#4880FF]' : 'text-[#64748B] dark:text-[#94A3B8]'
                ]"
              />
              <span class="truncate">{{ item.label }}</span>
            </button>
          </div>
        </div>

        <!-- ========================================= -->
        <!-- RIGHT CONTENT PANEL -->
        <!-- ========================================= -->
        <div class="flex-1 flex flex-col bg-white dark:bg-[#1E293B] overflow-hidden min-w-0 transition-colors">
          <div class="flex-1 overflow-y-auto p-7 md:p-10 custom-scroll">

            <!-- ===================================== -->
            <!-- TAB: GENERAL -->
            <!-- ===================================== -->
            <div v-if="activeTab === 'general'" class="space-y-0">

              <!-- SECTION: Profile -->
              <h2 class="text-base font-bold text-[#202224] dark:text-white mb-1">Profile</h2>
              <div class="divide-y divide-[#E2E8F0] dark:divide-[#334155]/60 text-xs">
                <!-- Full name -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between py-4 gap-2">
                  <div class="text-sm font-medium text-[#202224] dark:text-white">Full name</div>
                  <input
                    v-model="fullName"
                    type="text"
                    class="w-full sm:w-[280px] px-3 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm text-[#202224] dark:text-white focus:outline-none focus:border-[#4880FF]"
                  />
                </div>

                <!-- What should Lapaqu call you? -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between py-4 gap-2">
                  <div class="text-sm font-medium text-[#202224] dark:text-white">What should Lapaqu call you?</div>
                  <input
                    v-model="callName"
                    type="text"
                    class="w-full sm:w-[280px] px-3 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm text-[#202224] dark:text-white focus:outline-none focus:border-[#4880FF]"
                  />
                </div>

                <!-- What best describes your work? -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between py-4 gap-2">
                  <div class="text-sm font-medium text-[#202224] dark:text-white">What best describes your work?</div>
                  <select
                    v-model="roleDesc"
                    class="w-full sm:w-[280px] px-3 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm text-[#202224] dark:text-white focus:outline-none cursor-pointer"
                  >
                    <option value="owner">Restaurant Owner / Founder</option>
                    <option value="manager">Outlet Manager</option>
                    <option value="finance">Finance & Operations</option>
                  </select>
                </div>
              </div>

              <!-- SECTION: Preferences -->
              <h2 class="text-base font-bold text-[#202224] dark:text-white mt-8 mb-1">Preferences</h2>
              <div class="divide-y divide-[#E2E8F0] dark:divide-[#334155]/60 text-xs">
                <!-- Appearance -->
                <div class="flex items-center justify-between py-4">
                  <div class="text-sm font-medium text-[#202224] dark:text-white">Appearance</div>
                  <div class="flex items-center bg-[#F1F5F9] dark:bg-[#0F172A] border border-[#E2E8F0] dark:border-[#334155] p-0.5 rounded-lg">
                    <button
                      type="button"
                      @click="setAppearance('system')"
                      :class="[
                        'p-1.5 rounded-md transition-all cursor-pointer',
                        themePreference === 'system' ? 'bg-white dark:bg-[#1E293B] shadow-xs text-[#202224] dark:text-white' : 'text-[#64748B] dark:text-[#94A3B8]'
                      ]"
                      title="System"
                    >
                      <Monitor class="w-4 h-4" />
                    </button>
                    <button
                      type="button"
                      @click="setAppearance('light')"
                      :class="[
                        'p-1.5 rounded-md transition-all cursor-pointer',
                        themePreference === 'light' ? 'bg-white dark:bg-[#1E293B] shadow-xs text-[#202224] dark:text-white' : 'text-[#64748B] dark:text-[#94A3B8]'
                      ]"
                      title="Light"
                    >
                      <Sun class="w-4 h-4" />
                    </button>
                    <button
                      type="button"
                      @click="setAppearance('dark')"
                      :class="[
                        'p-1.5 rounded-md transition-all cursor-pointer',
                        themePreference === 'dark' ? 'bg-white dark:bg-[#1E293B] shadow-xs text-[#202224] dark:text-white' : 'text-[#64748B] dark:text-[#94A3B8]'
                      ]"
                      title="Dark"
                    >
                      <Moon class="w-4 h-4" />
                    </button>
                  </div>
                </div>

                <!-- Motion -->
                <div class="space-y-1.5 py-4">
                  <div class="flex items-center justify-between">
                    <div class="text-sm font-medium text-[#202224] dark:text-white">Motion</div>
                    <div class="flex items-center bg-[#F1F5F9] dark:bg-[#0F172A] border border-[#E2E8F0] dark:border-[#334155] p-0.5 rounded-lg text-xs">
                      <button
                        type="button"
                        @click="motionPreference = 'system'"
                        :class="[
                          'px-3 py-1 rounded-md font-medium transition-all cursor-pointer',
                          motionPreference === 'system' ? 'bg-white dark:bg-[#1E293B] shadow-xs text-[#202224] dark:text-white' : 'text-[#64748B] dark:text-[#94A3B8]'
                        ]"
                      >
                        System
                      </button>
                      <button
                        type="button"
                        @click="motionPreference = 'reduced'"
                        :class="[
                          'px-3 py-1 rounded-md font-medium transition-all cursor-pointer',
                          motionPreference === 'reduced' ? 'bg-white dark:bg-[#1E293B] shadow-xs text-[#202224] dark:text-white' : 'text-[#64748B] dark:text-[#94A3B8]'
                        ]"
                      >
                        Reduced
                      </button>
                    </div>
                  </div>
                  <p class="text-xs text-[#64748B] dark:text-[#94A3B8]">
                    Reduce animation in streaming responses and other interface elements.
                  </p>
                </div>
              </div>

              <!-- SECTION: Language -->
              <h2 class="text-base font-bold text-[#202224] dark:text-white mt-8 mb-1">Language</h2>
              <div class="divide-y divide-[#E2E8F0] dark:divide-[#334155]/60 text-xs">
                <!-- Bahasa Antarmuka -->
                <div class="flex items-center justify-between py-4">
                  <div>
                    <div class="text-sm font-medium text-[#202224] dark:text-white">Bahasa Antarmuka</div>
                    <div class="text-[#64748B] dark:text-[#94A3B8] mt-0.5">Bahasa standar menu, laporan, dan nota POS</div>
                  </div>
                  <select
                    v-model="selectedLanguage"
                    class="px-3.5 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-xs font-semibold text-[#202224] dark:text-white focus:outline-none cursor-pointer"
                  >
                    <option value="id">Bahasa Indonesia</option>
                    <option value="en">English (US)</option>
                  </select>
                </div>

                <!-- Format Mata Uang -->
                <div class="flex items-center justify-between py-4">
                  <div>
                    <div class="text-sm font-medium text-[#202224] dark:text-white">Format Mata Uang</div>
                    <div class="text-[#64748B] dark:text-[#94A3B8] mt-0.5">Penulisan desimal dan simbol nominal transaksi</div>
                  </div>
                  <div class="text-xs font-mono font-bold text-[#202224] dark:text-white bg-[#F1F5F9] dark:bg-[#0F172A] px-3 py-1.5 rounded-lg border border-[#E2E8F0] dark:border-[#334155]">
                    IDR (Rp)
                  </div>
                </div>
              </div>

              <!-- SECTION: Notifications -->
              <h2 class="text-base font-bold text-[#202224] dark:text-white mt-8 mb-1">Notifications</h2>
              <div class="divide-y divide-[#E2E8F0] dark:divide-[#334155]/60 text-xs">
                <!-- Suara pesanan baru -->
                <div class="flex items-center justify-between py-4">
                  <div>
                    <div class="text-sm font-medium text-[#202224] dark:text-white">Suara pesanan baru masuk (POS)</div>
                    <div class="text-[#64748B] dark:text-[#94A3B8] mt-0.5">Notifikasi audio saat order baru diterima sistem</div>
                  </div>
                  <button
                    type="button"
                    @click="notifyOrderSound = !notifyOrderSound"
                    :class="['w-11 h-6 rounded-full transition-colors relative cursor-pointer', notifyOrderSound ? 'bg-[#4880FF]' : 'bg-[#CBD5E1] dark:bg-[#334155]']"
                  >
                    <span :class="['w-4 h-4 rounded-full bg-white absolute top-1 transition-transform', notifyOrderSound ? 'right-1' : 'left-1']" />
                  </button>
                </div>

                <!-- Browser Push -->
                <div class="flex items-center justify-between py-4">
                  <div>
                    <div class="text-sm font-medium text-[#202224] dark:text-white">Browser push notification</div>
                    <div class="text-[#64748B] dark:text-[#94A3B8] mt-0.5">Notifikasi desktop browser untuk order masuk dan panggilan meja</div>
                  </div>
                  <button
                    type="button"
                    @click="notifyBrowserPush = !notifyBrowserPush"
                    :class="['w-11 h-6 rounded-full transition-colors relative cursor-pointer', notifyBrowserPush ? 'bg-[#4880FF]' : 'bg-[#CBD5E1] dark:bg-[#334155]']"
                  >
                    <span :class="['w-4 h-4 rounded-full bg-white absolute top-1 transition-transform', notifyBrowserPush ? 'right-1' : 'left-1']" />
                  </button>
                </div>

                <!-- Bel Panggilan Meja -->
                <div class="flex items-center justify-between py-4">
                  <div>
                    <div class="text-sm font-medium text-[#202224] dark:text-white">Bel Panggilan Meja (Waitstaff Call)</div>
                    <div class="text-[#64748B] dark:text-[#94A3B8] mt-0.5">Pemberitahuan suara khusus saat tombol bantuan meja ditekan</div>
                  </div>
                  <button
                    type="button"
                    @click="notifyWaiterBell = !notifyWaiterBell"
                    :class="['w-11 h-6 rounded-full transition-colors relative cursor-pointer', notifyWaiterBell ? 'bg-[#4880FF]' : 'bg-[#CBD5E1] dark:bg-[#334155]']"
                  >
                    <span :class="['w-4 h-4 rounded-full bg-white absolute top-1 transition-transform', notifyWaiterBell ? 'right-1' : 'left-1']" />
                  </button>
                </div>

                <!-- Alert Void KDS -->
                <div class="flex items-center justify-between py-4">
                  <div>
                    <div class="text-sm font-medium text-[#202224] dark:text-white">Alert Pembatalan Menu (KDS Dapur)</div>
                    <div class="text-[#64748B] dark:text-[#94A3B8] mt-0.5">Sinyal visual & audio ke dapur saat pesanan dibatalkan</div>
                  </div>
                  <button
                    type="button"
                    @click="notifyKdsVoid = !notifyKdsVoid"
                    :class="['w-11 h-6 rounded-full transition-colors relative cursor-pointer', notifyKdsVoid ? 'bg-[#4880FF]' : 'bg-[#CBD5E1] dark:bg-[#334155]']"
                  >
                    <span :class="['w-4 h-4 rounded-full bg-white absolute top-1 transition-transform', notifyKdsVoid ? 'right-1' : 'left-1']" />
                  </button>
                </div>

                <!-- WA Harian -->
                <div class="flex items-center justify-between py-4">
                  <div>
                    <div class="text-sm font-medium text-[#202224] dark:text-white">Ringkasan Omset Harian via WhatsApp</div>
                    <div class="text-[#64748B] dark:text-[#94A3B8] mt-0.5">Kirim ringkasan otomatis setiap penutupan shift (23:00 WIB)</div>
                  </div>
                  <button
                    type="button"
                    @click="notifyWaDaily = !notifyWaDaily"
                    :class="['w-11 h-6 rounded-full transition-colors relative cursor-pointer', notifyWaDaily ? 'bg-[#4880FF]' : 'bg-[#CBD5E1] dark:bg-[#334155]']"
                  >
                    <span :class="['w-4 h-4 rounded-full bg-white absolute top-1 transition-transform', notifyWaDaily ? 'right-1' : 'left-1']" />
                  </button>
                </div>

                <!-- Nomor WA (conditional) -->
                <div v-if="notifyWaDaily" class="flex flex-col sm:flex-row sm:items-center justify-between py-4 gap-2">
                  <div class="text-sm font-medium text-[#202224] dark:text-white">Nomor WhatsApp Penerima</div>
                  <input
                    v-model="waAdminNumber"
                    type="text"
                    class="w-full sm:w-[280px] px-3 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm text-[#202224] dark:text-white focus:outline-none focus:border-[#4880FF]"
                  />
                </div>

                <!-- Email Mingguan -->
                <div class="flex items-center justify-between py-4">
                  <div>
                    <div class="text-sm font-medium text-[#202224] dark:text-white">Email Analisis Performa Mingguan</div>
                    <div class="text-[#64748B] dark:text-[#94A3B8] mt-0.5">Kirim laporan penjualan dan menu terlaris ke email owner</div>
                  </div>
                  <button
                    type="button"
                    @click="notifyEmailWeekly = !notifyEmailWeekly"
                    :class="['w-11 h-6 rounded-full transition-colors relative cursor-pointer', notifyEmailWeekly ? 'bg-[#4880FF]' : 'bg-[#CBD5E1] dark:bg-[#334155]']"
                  >
                    <span :class="['w-4 h-4 rounded-full bg-white absolute top-1 transition-transform', notifyEmailWeekly ? 'right-1' : 'left-1']" />
                  </button>
                </div>
              </div>

              <!-- Save button for notifications -->
              <div class="flex items-center justify-end gap-3 pt-4">
                <span v-if="notifSaved" class="text-xs font-bold text-[#00B69B] flex items-center gap-1">
                  <Check class="w-3.5 h-3.5" /> Tersimpan!
                </span>
                <button
                  type="button"
                  @click="handleSaveNotif"
                  :disabled="isSavingNotif"
                  class="px-5 py-2.5 bg-[#4880FF] hover:bg-blue-600 text-white rounded-lg text-xs font-bold transition-colors cursor-pointer disabled:opacity-50"
                >
                  {{ isSavingNotif ? 'Menyimpan...' : 'Simpan Pengaturan' }}
                </button>
              </div>
            </div>

            <!-- ===================================== -->
            <!-- TAB: ACCOUNT -->
            <!-- ===================================== -->
            <div v-else-if="activeTab === 'account'" class="space-y-0">
              <h2 class="text-base font-bold text-[#202224] dark:text-white mb-1">Account</h2>
              <div class="divide-y divide-[#E2E8F0] dark:divide-[#334155]/60 text-xs">
                <!-- Email Address -->
                <div class="flex items-center justify-between py-4">
                  <div>
                    <div class="text-sm font-medium text-[#202224] dark:text-white">Email Address</div>
                    <div class="text-[#64748B] dark:text-[#94A3B8] mt-0.5">Email akun terdaftar untuk login dan pemulihan</div>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="text-xs font-mono text-[#202224] dark:text-white">{{ userEmail }}</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-[#00B69B]/15 text-[#00B69B]">Verified</span>
                  </div>
                </div>

                <!-- Tenant ID -->
                <div class="flex items-center justify-between py-4">
                  <div>
                    <div class="text-sm font-medium text-[#202224] dark:text-white">Tenant ID</div>
                    <div class="text-[#64748B] dark:text-[#94A3B8] mt-0.5">Pengenal organisasi unik pada sistem</div>
                  </div>
                  <div class="flex items-center gap-2">
                    <code class="text-xs font-mono px-2 py-1 rounded bg-[#F1F5F9] dark:bg-[#0F172A]">ten_lapaqu_senopati01</code>
                    <button
                      type="button"
                      @click="copyTenantId"
                      class="px-2.5 py-1 border border-[#E2E8F0] dark:border-[#334155] rounded-lg text-xs font-semibold text-[#202224] dark:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer"
                    >
                      {{ copiedTenantId ? 'Tersalin' : 'Salin' }}
                    </button>
                  </div>
                </div>

                <!-- Ubah Password -->
                <div class="py-4 space-y-3">
                  <div class="text-sm font-medium text-[#202224] dark:text-white">Ubah Kata Sandi</div>
                  <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                    <input
                      v-model="currentPassword"
                      type="password"
                      placeholder="Kata sandi saat ini"
                      class="px-3 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-xs text-[#202224] dark:text-white focus:outline-none focus:border-[#4880FF]"
                    />
                    <input
                      v-model="newPassword"
                      type="password"
                      placeholder="Kata sandi baru"
                      class="px-3 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-xs text-[#202224] dark:text-white focus:outline-none focus:border-[#4880FF]"
                    />
                    <input
                      v-model="confirmPassword"
                      type="password"
                      placeholder="Ulangi kata sandi"
                      class="px-3 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-xs text-[#202224] dark:text-white focus:outline-none focus:border-[#4880FF]"
                    />
                  </div>

                  <div v-if="passwordError" class="text-xs text-[#FD5454] font-medium">{{ passwordError }}</div>
                  <div v-if="passwordChangeSuccess" class="text-xs text-[#00B69B] font-medium flex items-center gap-1">
                    <Check class="w-3.5 h-3.5" /> Kata sandi berhasil diperbarui!
                  </div>

                  <div class="flex justify-end pt-1">
                    <button
                      type="button"
                      @click="handleChangePassword"
                      :disabled="isChangingPassword"
                      class="px-3.5 py-1.5 border border-[#E2E8F0] dark:border-[#334155] rounded-lg text-xs font-semibold text-[#202224] dark:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer disabled:opacity-50"
                    >
                      {{ isChangingPassword ? 'Memperbarui...' : 'Perbarui Kata Sandi' }}
                    </button>
                  </div>
                </div>

                <!-- Two-Factor Authentication -->
                <div class="flex items-center justify-between py-4">
                  <div>
                    <div class="text-sm font-medium text-[#202224] dark:text-white">Two-Factor Authentication (2FA)</div>
                    <div class="text-[#64748B] dark:text-[#94A3B8] mt-0.5">Proteksi akun ekstra menggunakan aplikasi Google Authenticator</div>
                  </div>
                  <span class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-[#00B69B]/15 text-[#00B69B]">Aktif</span>
                </div>

                <!-- Log out -->
                <div class="flex items-center justify-between py-4">
                  <div>
                    <div class="text-sm font-medium text-[#FD5454]">Log out</div>
                    <div class="text-[#64748B] dark:text-[#94A3B8] mt-0.5">Keluar dari sesi dashboard di perangkat ini.</div>
                  </div>
                  <button
                    type="button"
                    @click="isLogoutConfirmOpen = true"
                    class="px-3.5 py-1.5 border border-[#FD5454]/30 text-[#FD5454] hover:bg-[#FD5454]/10 rounded-lg text-xs font-bold transition-colors cursor-pointer"
                  >
                    Log out
                  </button>
                </div>
              </div>
            </div>

            <!-- ===================================== -->
            <!-- TAB: BRANDING -->
            <!-- ===================================== -->
            <div v-else-if="activeTab === 'branding'" class="space-y-0">
              <h2 class="text-base font-bold text-[#202224] dark:text-white mb-1">Branding</h2>
              <p class="text-xs text-[#64748B] dark:text-[#94A3B8] mb-2">
                Atur identitas restoran, parameter pajak, dan konfigurasi sesi meja.
              </p>
              <div class="divide-y divide-[#E2E8F0] dark:divide-[#334155]/60 text-xs">
                <!-- Logo Restoran -->
                <div class="flex items-center justify-between py-4">
                  <div>
                    <div class="text-sm font-medium text-[#202224] dark:text-white">Logo Restoran</div>
                    <div class="text-[#64748B] dark:text-[#94A3B8] mt-0.5">Tampil pada halaman menu digital dan struk belanja</div>
                  </div>
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-[#4880FF]/15 text-[#4880FF] flex items-center justify-center font-bold text-sm">
                      KK
                    </div>
                    <button
                      type="button"
                      class="px-3 py-1.5 border border-[#E2E8F0] dark:border-[#334155] rounded-lg text-xs font-semibold text-[#202224] dark:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer"
                    >
                      Ubah
                    </button>
                  </div>
                </div>

                <!-- Nama Restoran -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between py-4 gap-2">
                  <div class="text-sm font-medium text-[#202224] dark:text-white">Nama Restoran / Brand</div>
                  <input
                    v-model="outletName"
                    type="text"
                    class="w-full sm:w-[340px] px-3 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm text-[#202224] dark:text-white focus:outline-none focus:border-[#4880FF]"
                  />
                </div>

                <!-- Slogan -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between py-4 gap-2">
                  <div class="text-sm font-medium text-[#202224] dark:text-white">Slogan / Tagline</div>
                  <input
                    v-model="outletSlogan"
                    type="text"
                    class="w-full sm:w-[340px] px-3 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm text-[#202224] dark:text-white focus:outline-none focus:border-[#4880FF]"
                  />
                </div>

                <!-- Tenant Subdomain -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between py-4 gap-2">
                  <div>
                    <div class="text-sm font-medium text-[#202224] dark:text-white">Tenant Subdomain</div>
                    <div class="text-[#64748B] dark:text-[#94A3B8] mt-0.5">Alamat unik akses scan QR meja pelanggan</div>
                  </div>
                  <div class="flex items-center gap-1.5 w-full sm:w-[340px]">
                    <input
                      v-model="subdomain"
                      type="text"
                      class="flex-1 px-3 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm font-mono text-[#202224] dark:text-white focus:outline-none focus:border-[#4880FF]"
                    />
                    <span class="text-xs text-[#64748B] dark:text-[#94A3B8] font-mono">.lapaqu.id</span>
                  </div>
                </div>

                <!-- Alamat -->
                <div class="py-4 space-y-1.5">
                  <div class="text-sm font-medium text-[#202224] dark:text-white">Alamat Lengkap Outlet</div>
                  <textarea
                    v-model="outletAddress"
                    rows="2"
                    class="w-full px-3 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm text-[#202224] dark:text-white resize-none focus:outline-none focus:border-[#4880FF]"
                  />
                </div>

                <!-- Telepon -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between py-4 gap-2">
                  <div class="text-sm font-medium text-[#202224] dark:text-white">Nomor Telepon Outlet</div>
                  <input
                    v-model="outletPhone"
                    type="text"
                    class="w-full sm:w-[340px] px-3 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm text-[#202224] dark:text-white focus:outline-none focus:border-[#4880FF]"
                  />
                </div>

                <!-- Pajak PB1 -->
                <div class="flex items-center justify-between py-4">
                  <div>
                    <div class="text-sm font-medium text-[#202224] dark:text-white">Pajak Restoran (PB1)</div>
                    <div class="text-[#64748B] dark:text-[#94A3B8] mt-0.5">Dihitung otomatis 10% pada nota transaksi</div>
                  </div>
                  <button
                    type="button"
                    @click="enableTax = !enableTax"
                    :class="['w-11 h-6 rounded-full transition-colors relative cursor-pointer', enableTax ? 'bg-[#4880FF]' : 'bg-[#CBD5E1] dark:bg-[#334155]']"
                  >
                    <span :class="['w-4 h-4 rounded-full bg-white absolute top-1 transition-transform', enableTax ? 'right-1' : 'left-1']" />
                  </button>
                </div>

                <!-- Service Charge -->
                <div class="flex items-center justify-between py-4">
                  <div>
                    <div class="text-sm font-medium text-[#202224] dark:text-white">Biaya Layanan (Service Charge)</div>
                    <div class="text-[#64748B] dark:text-[#94A3B8] mt-0.5">Tambahan 5% untuk pesanan dine-in</div>
                  </div>
                  <button
                    type="button"
                    @click="enableServiceCharge = !enableServiceCharge"
                    :class="['w-11 h-6 rounded-full transition-colors relative cursor-pointer', enableServiceCharge ? 'bg-[#4880FF]' : 'bg-[#CBD5E1] dark:bg-[#334155]']"
                  >
                    <span :class="['w-4 h-4 rounded-full bg-white absolute top-1 transition-transform', enableServiceCharge ? 'right-1' : 'left-1']" />
                  </button>
                </div>

                <!-- Timeout Sesi Meja -->
                <div class="flex items-center justify-between py-4">
                  <div>
                    <div class="text-sm font-medium text-[#202224] dark:text-white">Durasi Timeout Sesi Meja</div>
                    <div class="text-[#64748B] dark:text-[#94A3B8] mt-0.5">Sesi meja otomatis ditutup setelah tidak ada aktivitas</div>
                  </div>
                  <select
                    v-model="tableTimeoutMinutes"
                    class="px-3 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm text-[#202224] dark:text-white focus:outline-none cursor-pointer"
                  >
                    <option :value="60">60 Menit</option>
                    <option :value="90">90 Menit (Standar)</option>
                    <option :value="120">120 Menit</option>
                  </select>
                </div>
              </div>

              <!-- Save -->
              <div class="flex items-center justify-end gap-3 pt-4">
                <span v-if="brandingSaved" class="text-xs font-bold text-[#00B69B] flex items-center gap-1">
                  <Check class="w-3.5 h-3.5" /> Tersimpan!
                </span>
                <button
                  type="button"
                  @click="handleSaveBranding"
                  :disabled="isSavingBranding"
                  class="px-5 py-2.5 bg-[#4880FF] hover:bg-blue-600 text-white rounded-lg text-xs font-bold transition-colors cursor-pointer disabled:opacity-50"
                >
                  {{ isSavingBranding ? 'Menyimpan...' : 'Simpan Pengaturan' }}
                </button>
              </div>
            </div>

            <!-- ===================================== -->
            <!-- TAB: PAYMENT -->
            <!-- ===================================== -->
            <div v-else-if="activeTab === 'payment'" class="space-y-0">
              <h2 class="text-base font-bold text-[#202224] dark:text-white mb-1">Payment</h2>
              <p class="text-xs text-[#64748B] dark:text-[#94A3B8] mb-2">
                Kelola rekening pencairan dana transaksi QRIS dan integrasi gateway Xendit xenPlatform.
              </p>
              <div class="divide-y divide-[#E2E8F0] dark:divide-[#334155]/60 text-xs">
                <!-- Gateway Status -->
                <div class="flex items-center justify-between py-4">
                  <div>
                    <div class="text-sm font-medium text-[#202224] dark:text-white">Gateway Pembayaran</div>
                    <div class="text-[#64748B] dark:text-[#94A3B8] mt-0.5">Xendit xenPlatform Terkoneksi (Sub-Account: <code class="text-xs font-mono font-bold">sub_acc_xnd_8829104</code>)</div>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-[#00B69B]/15 text-[#00B69B]">Terkoneksi</span>
                    <button
                      type="button"
                      @click="copySubAccount"
                      class="px-3 py-1.5 border border-[#E2E8F0] dark:border-[#334155] rounded-lg text-xs font-semibold text-[#202224] dark:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer"
                    >
                      {{ copiedSubAccountId ? 'Tersalin!' : 'Salin ID' }}
                    </button>
                  </div>
                </div>

                <!-- Saldo Tersedia -->
                <div class="flex items-center justify-between py-4">
                  <div>
                    <div class="text-sm font-medium text-[#202224] dark:text-white">Saldo Siap Cair (T+1)</div>
                    <div class="text-[#64748B] dark:text-[#94A3B8] mt-0.5">Otomatis ditransfer ke rekening bank setiap hari kerja pukul 09:00 WIB</div>
                  </div>
                  <div class="text-sm font-bold text-[#00B69B]">Rp 18.450.000</div>
                </div>

                <!-- Menunggu Kliring -->
                <div class="flex items-center justify-between py-4">
                  <div>
                    <div class="text-sm font-medium text-[#202224] dark:text-white">Menunggu Kliring</div>
                    <div class="text-[#64748B] dark:text-[#94A3B8] mt-0.5">Transaksi yang belum masuk siklus pencairan berikutnya</div>
                  </div>
                  <div class="text-sm font-bold text-[#F59E0B]">Rp 2.150.000</div>
                </div>

                <!-- Rekening Bank -->
                <div class="flex items-center justify-between py-4">
                  <div>
                    <div class="text-sm font-medium text-[#202224] dark:text-white">Rekening Bank Tujuan</div>
                    <div class="text-[#64748B] dark:text-[#94A3B8] mt-0.5">{{ bankName }} • {{ accountNumber }} • a.n. {{ accountHolder }}</div>
                  </div>
                  <button
                    type="button"
                    @click="openChangeModal"
                    class="px-3 py-1.5 border border-[#E2E8F0] dark:border-[#334155] rounded-lg text-xs font-semibold text-[#202224] dark:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer"
                  >
                    Ajukan Perubahan
                  </button>
                </div>
              </div>

              <!-- Settlement History -->
              <h3 class="text-sm font-bold text-[#202224] dark:text-white mt-6 mb-3">Riwayat Settlement</h3>
              <div class="overflow-x-auto rounded-lg border border-[#E2E8F0] dark:border-[#334155]">
                <table class="w-full text-xs">
                  <thead>
                    <tr class="bg-[#F8FAFC] dark:bg-[#0F172A] text-[#64748B] dark:text-[#94A3B8]">
                      <th class="text-left px-4 py-2.5 font-semibold">ID Settlement</th>
                      <th class="text-left px-4 py-2.5 font-semibold">Tanggal</th>
                      <th class="text-left px-4 py-2.5 font-semibold">Rekening</th>
                      <th class="text-right px-4 py-2.5 font-semibold">Nominal Bersih</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-[#E2E8F0] dark:divide-[#334155]">
                    <tr v-for="s in settlements" :key="s.id" class="text-[#202224] dark:text-white">
                      <td class="px-4 py-2.5 font-mono">{{ s.id }}</td>
                      <td class="px-4 py-2.5">{{ s.date }}</td>
                      <td class="px-4 py-2.5">{{ s.bank }}</td>
                      <td class="px-4 py-2.5 text-right font-semibold">{{ formatCurrency(s.net) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- ===================================== -->
            <!-- TAB: BILLING -->
            <!-- ===================================== -->
            <div v-else-if="activeTab === 'billing'" class="space-y-0">
              <h2 class="text-base font-bold text-[#202224] dark:text-white mb-1">Billing</h2>
              <p class="text-xs text-[#64748B] dark:text-[#94A3B8] mb-2">
                Status paket langganan Lapaqu POS, batas kuota, dan riwayat tagihan invoice.
              </p>
              <div class="divide-y divide-[#E2E8F0] dark:divide-[#334155]/60 text-xs">
                <!-- Paket Aktif -->
                <div class="flex items-center justify-between py-4">
                  <div>
                    <div class="text-sm font-medium text-[#202224] dark:text-white">Paket Langganan Aktif</div>
                    <div class="text-[#64748B] dark:text-[#94A3B8] mt-0.5">Rp 299.000 / bulan • Auto-renewal via Xendit pada 30 Sep 2026</div>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="px-2.5 py-1 rounded text-[10px] font-bold bg-[#4880FF]/15 text-[#4880FF]">Lapaqu Pro</span>
                    <button
                      type="button"
                      class="px-3 py-1.5 border border-[#E2E8F0] dark:border-[#334155] rounded-lg text-xs font-semibold text-[#202224] dark:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer"
                    >
                      Ubah Paket
                    </button>
                  </div>
                </div>

                <!-- Kapasitas Meja -->
                <div class="flex items-center justify-between py-4">
                  <div>
                    <div class="text-sm font-medium text-[#202224] dark:text-white">Kapasitas Meja QR</div>
                    <div class="text-[#64748B] dark:text-[#94A3B8] mt-0.5">Maksimal meja yang dapat memiliki barcode pesanan aktif</div>
                  </div>
                  <div class="text-sm font-semibold text-[#202224] dark:text-white">12 / 20 Meja</div>
                </div>

                <!-- Katalog Menu -->
                <div class="flex items-center justify-between py-4">
                  <div>
                    <div class="text-sm font-medium text-[#202224] dark:text-white">Katalog Menu</div>
                    <div class="text-[#64748B] dark:text-[#94A3B8] mt-0.5">Total item makanan & minuman yang aktif di katalog</div>
                  </div>
                  <div class="text-sm font-semibold text-[#00B69B]">45 Item (Unlimited)</div>
                </div>

                <!-- Staff Slot -->
                <div class="flex items-center justify-between py-4">
                  <div>
                    <div class="text-sm font-medium text-[#202224] dark:text-white">Slot Staff Aktif</div>
                    <div class="text-[#64748B] dark:text-[#94A3B8] mt-0.5">Kasir dan kitchen staff yang diundang ke outlet</div>
                  </div>
                  <div class="text-sm font-semibold text-[#202224] dark:text-white">3 / 10 Staff</div>
                </div>
              </div>

              <!-- Invoice History -->
              <h3 class="text-sm font-bold text-[#202224] dark:text-white mt-6 mb-3">Riwayat Invoice</h3>
              <div class="overflow-x-auto rounded-lg border border-[#E2E8F0] dark:border-[#334155]">
                <table class="w-full text-xs">
                  <thead>
                    <tr class="bg-[#F8FAFC] dark:bg-[#0F172A] text-[#64748B] dark:text-[#94A3B8]">
                      <th class="text-left px-4 py-2.5 font-semibold">Invoice</th>
                      <th class="text-left px-4 py-2.5 font-semibold">Tanggal</th>
                      <th class="text-left px-4 py-2.5 font-semibold">Periode</th>
                      <th class="text-right px-4 py-2.5 font-semibold">Nominal</th>
                      <th class="text-center px-4 py-2.5 font-semibold">Aksi</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-[#E2E8F0] dark:divide-[#334155]">
                    <tr v-for="inv in invoices" :key="inv.id" class="text-[#202224] dark:text-white">
                      <td class="px-4 py-2.5 font-mono">{{ inv.id }}</td>
                      <td class="px-4 py-2.5">{{ inv.date }}</td>
                      <td class="px-4 py-2.5">{{ inv.period }}</td>
                      <td class="px-4 py-2.5 text-right font-semibold">{{ formatCurrency(inv.amount) }}</td>
                      <td class="px-4 py-2.5 text-center">
                        <button type="button" class="text-[#4880FF] hover:text-blue-600 cursor-pointer" title="Download PDF">
                          <Download class="w-3.5 h-3.5 inline" />
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>

    <!-- SUB-MODAL: AJUKAN GANTI REKENING -->
    <div
      v-if="isChangeModalOpen"
      class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
    >
      <div class="w-full max-w-md bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-2xl p-6 space-y-4 text-[#202224] dark:text-white shadow-2xl">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-bold">Pengajuan Ganti Rekening Bank</h3>
          <button type="button" @click="isChangeModalOpen = false" class="text-[#64748B] hover:text-[#202224] dark:hover:text-white cursor-pointer">
            <X class="w-4 h-4" />
          </button>
        </div>

        <div v-if="requestSubmittedSuccess" class="p-3 bg-[#00B69B]/15 text-[#00B69B] rounded-xl text-xs font-semibold flex items-center gap-2">
          <CheckCircle2 class="w-4 h-4 shrink-0" />
          Pengajuan berhasil dikirim! Tim finance akan memverifikasi dalam 1x24 jam.
        </div>

        <div v-else class="space-y-3 text-xs">
          <p class="text-[#64748B] dark:text-[#94A3B8]">
            Perubahan nomor rekening bank memerlukan verifikasi keamanan tim finance Lapaqu.
          </p>
          <div>
            <label class="block mb-1 font-semibold text-[#64748B] dark:text-[#94A3B8]">Nama Bank Baru</label>
            <input v-model="reqBankName" type="text" class="w-full px-3 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-[#202224] dark:text-white focus:outline-none" />
          </div>
          <div>
            <label class="block mb-1 font-semibold text-[#64748B] dark:text-[#94A3B8]">Nomor Rekening Baru</label>
            <input v-model="reqAccountNumber" type="text" class="w-full px-3 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-[#202224] dark:text-white focus:outline-none" />
          </div>
          <div>
            <label class="block mb-1 font-semibold text-[#64748B] dark:text-[#94A3B8]">Nama Pemilik Rekening</label>
            <input v-model="reqAccountHolder" type="text" class="w-full px-3 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-[#202224] dark:text-white focus:outline-none" />
          </div>
          <div>
            <label class="block mb-1 font-semibold text-[#64748B] dark:text-[#94A3B8]">Alasan Perubahan</label>
            <textarea v-model="reqNotes" rows="2" class="w-full px-3 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-[#202224] dark:text-white resize-none focus:outline-none" />
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="isChangeModalOpen = false" class="px-3.5 py-1.5 border border-[#E2E8F0] dark:border-[#334155] rounded-lg text-xs font-semibold hover:bg-[#F1F5F9] dark:hover:bg-[#334155] cursor-pointer">Batal</button>
            <button type="button" @click="handleSubmitChangeRequest" :disabled="isSubmittingRequest" class="px-3.5 py-1.5 bg-[#4880FF] hover:bg-blue-600 text-white rounded-lg text-xs font-bold disabled:opacity-50 cursor-pointer">
              {{ isSubmittingRequest ? 'Mengirim...' : 'Kirim Pengajuan' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- SUB-MODAL: LOGOUT CONFIRM -->
    <div
      v-if="isLogoutConfirmOpen"
      class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
    >
      <div class="w-full max-w-sm bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-2xl p-6 space-y-4 text-[#202224] dark:text-white shadow-2xl">
        <h3 class="text-sm font-bold">Konfirmasi Keluar Akun</h3>
        <p class="text-xs text-[#64748B] dark:text-[#94A3B8]">
          Apakah Anda yakin ingin keluar dari akun Lapaqu di perangkat ini?
        </p>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" @click="isLogoutConfirmOpen = false" class="px-3.5 py-1.5 border border-[#E2E8F0] dark:border-[#334155] rounded-lg text-xs font-semibold hover:bg-[#F1F5F9] dark:hover:bg-[#334155] cursor-pointer">Batal</button>
          <button type="button" @click="handleLogout" class="px-3.5 py-1.5 bg-[#FD5454] hover:bg-red-600 rounded-lg text-xs font-bold text-white cursor-pointer">Ya, Keluar</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.custom-scroll::-webkit-scrollbar {
  width: 5px;
}
.custom-scroll::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scroll::-webkit-scrollbar-thumb {
  background: rgba(100, 116, 139, 0.2);
  border-radius: 9999px;
}
.custom-scroll::-webkit-scrollbar-thumb:hover {
  background: rgba(100, 116, 139, 0.4);
}
</style>
