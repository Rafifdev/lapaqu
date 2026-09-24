<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppToggle from '@/components/ui/AppToggle.vue'
import AppSelect from '@/components/ui/AppSelect.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppSearchInput from '@/components/ui/AppSearchInput.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { Motion } from 'motion-v'
import { useFormat } from '@/composables/useFormat'
import { useDashboardI18n, usePosKdsI18n, useCustomerI18n, SUPPORTED_LOCALES } from '@/i18n'
import { useTheme } from '@/composables/useTheme'
import soundService from '@/services/soundService'
import notificationService from '@/services/notificationService'
import { useMotion } from '@/composables/useMotion'
import { useNotyf } from '@/composables/useNotyf'
import { useAuthStore } from '@/stores/auth'
import { useSettingsModal, type SettingsTab } from '@/composables/useSettingsModal'
import apiClient from '@/services/api'
import avatarManager from '@/assets/roles/avatar-manager.jpg'
import avatarCashier from '@/assets/roles/avatar-cashier.jpg'
import avatarKitchen from '@/assets/roles/avatar-kitchen.jpg'

const { isOpen, activeTab, searchQuery, closeSettingsModal, setTab } = useSettingsModal()
const { formatCurrency } = useFormat()
const { isDark, toggleTheme, themePreference, setTheme } = useTheme()
const authStore = useAuthStore()
const customAvatarUrl = ref(localStorage.getItem('lapaqu_custom_avatar') || '')
const avatarFileInputRef = ref<HTMLInputElement | null>(null)
const isUploadingAvatar = ref(false)

const userAvatar = computed(() => {
  if (customAvatarUrl.value) return customAvatarUrl.value
  if (authStore.currentUser?.avatarUrl) return authStore.currentUser.avatarUrl
  const role = authStore.currentUser?.role
  if (role === 'kasir') return avatarCashier
  if (role === 'kitchen_staff') return avatarKitchen
  return avatarManager
})

const handleAvatarChange = async (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return

  if (!file.type.startsWith('image/')) {
    notyf.error('Format file harus berupa gambar (PNG, JPG, WEBP).', 2000)
    return
  }

  if (file.size > 2 * 1024 * 1024) {
    notyf.error('Ukuran gambar maksimal 2MB.', 2000)
    return
  }

  const reader = new FileReader()
  reader.onload = async (event) => {
    const dataUrl = event.target?.result as string
    customAvatarUrl.value = dataUrl
    localStorage.setItem('lapaqu_custom_avatar', dataUrl)

    if (authStore.currentUser) {
      authStore.currentUser.avatarUrl = dataUrl
    }

    window.dispatchEvent(new CustomEvent('lapaqu:avatar-updated', {
      detail: { avatarUrl: dataUrl }
    }))

    notyf.success('Foto profil berhasil diperbarui!', 2000)
  }
  reader.readAsDataURL(file)
}

// ==========================================
// NAVIGATION
// ==========================================
interface NavItem {
  key: SettingsTab
  label: string
  icon: string
}


// Sliding pill indicator state for modal sidebar (matching AppSidebar & Topbar)
const navContainerRef = ref<HTMLElement | null>(null)
const indicatorTop = ref(0)
const indicatorLeft = ref(0)
const indicatorWidth = ref(0)
const indicatorHeight = ref(44)
const isIndicatorVisible = ref(false)

const updateNavIndicator = () => {
  if (!navContainerRef.value) return
  const activeEl = navContainerRef.value.querySelector<HTMLElement>('[data-active="true"]')
  if (activeEl) {
    indicatorTop.value = activeEl.offsetTop
    indicatorLeft.value = activeEl.offsetLeft
    indicatorWidth.value = activeEl.offsetWidth
    indicatorHeight.value = activeEl.offsetHeight
    isIndicatorVisible.value = true
  } else {
    isIndicatorVisible.value = false
  }
}

const navItems = computed<NavItem[]>(() => [
  { key: 'general', label: t('settings.tabs.general'), icon: 'settings' },
  { key: 'account', label: t('settings.tabs.account'), icon: 'person' },
  { key: 'branding', label: t('settings.tabs.branding'), icon: 'storefront' },
  { key: 'payment', label: t('settings.tabs.payment'), icon: 'credit_card' },
  { key: 'billing', label: t('settings.tabs.billing'), icon: 'arrow_circle_up' },
])

const filteredNavItems = computed(() => {
  if (!searchQuery.value.trim()) return navItems.value
  const q = searchQuery.value.toLowerCase()
  return navItems.value.filter(item => item.label.toLowerCase().includes(q))
})

// Close with ESC key
const handleKeyDown = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && isOpen.value) {
    closeSettingsModal()
  }
}

// Cegah scroll pada halaman di belakang modal saat modal aktif (multi-layered scroll lock)
const preventBackgroundScroll = (e: Event) => {
  const target = e.target as HTMLElement | null
  if (!target) return
  // Izinkan scroll hanya jika target berada di dalam modal settings atau sub-modal aktif
  const isInsideModal = target.closest('.settings-modal-dialog, [role="dialog"]')
  if (!isInsideModal) {
    e.preventDefault()
  }
}

watch(isOpen, (val) => {
  if (typeof document !== 'undefined') {
    if (val) {
      document.documentElement.style.overflow = 'hidden'
      document.body.style.overflow = 'hidden'
      window.addEventListener('wheel', preventBackgroundScroll, { passive: false })
      window.addEventListener('touchmove', preventBackgroundScroll, { passive: false })
    } else {
      document.documentElement.style.overflow = ''
      document.body.style.overflow = ''
      window.removeEventListener('wheel', preventBackgroundScroll)
      window.removeEventListener('touchmove', preventBackgroundScroll)
    }
  }
}, { immediate: true })

onMounted(() => {
  loadSavedSettings()
  window.addEventListener('resize', updateNavIndicator)
  window.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown)
  window.removeEventListener('resize', updateNavIndicator)
  if (typeof document !== 'undefined') {
    document.documentElement.style.overflow = ''
    document.body.style.overflow = ''
    window.removeEventListener('wheel', preventBackgroundScroll)
    window.removeEventListener('touchmove', preventBackgroundScroll)
  }
})

// ==========================================
// GENERAL TAB — PROFILE
// ==========================================
const fullName = ref((authStore.currentUser as any)?.name || '')
const callName = ref((authStore.currentUser as any)?.name ? (authStore.currentUser as any).name.split(' ')[0] : '')
const displayRole = computed(() => {
  const r = (authStore.currentUser as any)?.role
  if (r === 'owner' || r === 'superadmin') return 'Owner'
  if (r === 'store_manager') return 'Store Manager'
  if (r === 'kasir') return 'Kasir'
  if (r === 'kitchen_staff') return 'Kitchen Staff'
  return r ? String(r).toUpperCase() : 'Owner'
})

// ==========================================
// GENERAL TAB — PREFERENCES
// ==========================================
const { motionPreference, setMotionPreference } = useMotion()

const setAppearance = (mode: 'system' | 'light' | 'dark') => {
  setTheme(mode)
}

// ==========================================
// GENERAL TAB — 3 INTERFACE LANGUAGES
// ==========================================
const { t, translate, locale: dashboardLocale, setLocale: setDashboardLocale } = useDashboardI18n()
const { locale: posKdsLocale, setLocale: setPosKdsLocale } = usePosKdsI18n()
const { locale: customerLocale, setLocale: setCustomerLocale } = useCustomerI18n()

const selectedLangDashboard = ref(dashboardLocale.value)
const selectedLangPosKds = ref(posKdsLocale.value)
const selectedLangCustomer = ref(customerLocale.value)
const languageOptions = SUPPORTED_LOCALES

const saveLangDashboard = () => {
  setDashboardLocale(selectedLangDashboard.value as any)
}

const saveLangPosKds = () => {
  setPosKdsLocale(selectedLangPosKds.value as any)
}

const saveLangCustomer = () => {
  setCustomerLocale(selectedLangCustomer.value as any)
}


const tenantIdDisplay = computed(() => {
  const u = authStore.currentUser as any
  let parsedUser: any = null
  try {
    const raw = sessionStorage.getItem('lapaqu_user') || localStorage.getItem('lapaqu_user')
    if (raw) parsedUser = JSON.parse(raw)
  } catch { }

  return (
    u?.id ||
    parsedUser?.id ||
    u?.tenantId ||
    u?.tenant_id ||
    parsedUser?.tenantId ||
    parsedUser?.tenant_id ||
    (authStore.currentUser as any)?.tenant?.id ||
    localStorage.getItem('lapaqu_tenant_id') ||
    '-'
  )
})

const currentOutletId = ref(localStorage.getItem('lapaqu_outlet_id') || '')

const outletIdDisplay = computed(() => {
  if (currentOutletId.value) return currentOutletId.value
  const u = authStore.currentUser as any
  let parsedUser: any = null
  try {
    const raw = sessionStorage.getItem('lapaqu_user') || localStorage.getItem('lapaqu_user')
    if (raw) parsedUser = JSON.parse(raw)
  } catch { }

  return (
    localStorage.getItem('lapaqu_outlet_id') ||
    u?.outletId ||
    u?.outlet_id ||
    parsedUser?.outletId ||
    parsedUser?.outlet_id ||
    (authStore.availableOutlets && authStore.availableOutlets[0]?.id) ||
    u?.outlet?.id ||
    '-'
  )
})


// ==========================================
// GENERAL TAB — NOTIFICATIONS
// ==========================================
const notifyOrderSound = ref(true)
const notifyBrowserPush = ref(true)
const notifyKdsVoid = ref(true)
const notifyWaDaily = ref(true)
const waAdminNumber = ref('')
const notifyEmailWeekly = ref(true)
const isSavingNotif = ref(false)

const notyf = useNotyf()


const getNotifSnapshot = () => JSON.stringify({
  notifyOrderSound: notifyOrderSound.value,
  notifyBrowserPush: notifyBrowserPush.value,
  notifyKdsVoid: notifyKdsVoid.value,
  notifyWaDaily: notifyWaDaily.value,
  waAdminNumber: waAdminNumber.value,
  notifyEmailWeekly: notifyEmailWeekly.value,
})

const lastSavedNotif = ref('')

const saveNotificationSettings = (silent = false) => {
  const currentSnapshot = getNotifSnapshot()
  if (currentSnapshot === lastSavedNotif.value) {
    return
  }
  lastSavedNotif.value = currentSnapshot

  localStorage.setItem('lapaqu_notif_settings', currentSnapshot)
  if (!silent) {
    notyf.success('Tersimpan', 2000)
  }
}

const saveNotificationSwitch = () => {
  saveNotificationSettings(true)
}

const onToggleOrderSound = () => {
  saveNotificationSwitch()
  if (notifyOrderSound.value) {
    soundService.playOrderChime(true)
  }
}

const onToggleBrowserPush = async () => {
  if (notifyBrowserPush.value) {
    const perm = await notificationService.requestPermission()
    if (perm !== 'granted') {
      notifyBrowserPush.value = false
      notyf.error('Izin notifikasi ditolak oleh browser. Mohon izinkan notifikasi di setelan browser.')
    } else {
      notyf.success('Notifikasi browser aktif')
      notificationService.showOrderNotification({
        orderNumber: 'DEMO-01',
        customerName: 'Pelanggan Meja 1',
        totalAmount: 35000,
        tableNumber: '1',
      })
    }
  }
  saveNotificationSwitch()
}

const onToggleKdsVoid = () => {
  saveNotificationSwitch()
  if (notifyKdsVoid.value) {
    soundService.playVoidAlert(true)
  }
}

// Handled by saveLangDashboard, saveLangPosKds, saveLangCustomer

const lastSavedAccount = ref({
  fullName: (authStore.currentUser as any)?.name || '',
  callName: (authStore.currentUser as any)?.name ? (authStore.currentUser as any).name.split(' ')[0] : '',
})

const saveAccountSettings = async (fieldName?: string) => {
  const isChanged =
    fullName.value !== lastSavedAccount.value.fullName ||
    callName.value !== lastSavedAccount.value.callName

  if (!isChanged) {
    return
  }

  lastSavedAccount.value = {
    fullName: fullName.value,
    callName: callName.value,
  }

  if (authStore.currentUser && fullName.value) {
    authStore.currentUser.name = fullName.value
  }
  localStorage.setItem('lapaqu_user_name', fullName.value)
  localStorage.setItem('lapaqu_call_name', callName.value)

  try {
    await apiClient.put('/auth/profile', {
      name: fullName.value,
    })
    notyf.success('Nama profil berhasil disimpan!', 2000)
  } catch (err: any) {
    notyf.error(err.response?.data?.message || 'Gagal menyimpan profil ke server.')
  }
}

// ==========================================
// ACCOUNT TAB
// ==========================================
const userEmail = ref((authStore.currentUser as any)?.email || '')
const currentPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const passwordError = ref('')
const isChangingPassword = ref(false)
const isChangePasswordOpen = ref(false)
const isLogoutConfirmOpen = ref(false)

const cancelChangePassword = () => {
  isChangePasswordOpen.value = false
  passwordError.value = ''
  currentPassword.value = ''
  newPassword.value = ''
  confirmPassword.value = ''
}

const copyTenantId = () => {
  const tid = (authStore.currentUser as any)?.tenant_id || (authStore.currentUser as any)?.tenant?.id || localStorage.getItem('lapaqu_tenant_id') || ''
  if (tid) navigator.clipboard.writeText(tid)
  notyf.success('Tenant ID berhasil disalin!')
}

const handleChangePassword = async () => {
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

  try {
    isChangingPassword.value = true
    const res = await apiClient.put('/auth/password', {
      current_password: currentPassword.value,
      new_password: newPassword.value,
    })
    currentPassword.value = ''
    newPassword.value = ''
    confirmPassword.value = ''
    isChangePasswordOpen.value = false
    notyf.success(res.data?.message || 'Kata sandi berhasil diperbarui!', 2000)
  } catch (err: any) {
    passwordError.value =
      err.response?.data?.message ||
      err.response?.data?.errors?.current_password?.[0] ||
      'Gagal mengubah kata sandi.'
  } finally {
    isChangingPassword.value = false
  }
}

const handleLogout = () => {
  closeSettingsModal()
  authStore.logout()
  window.location.href = '/auth/login'
}

// ==========================================
// BRANDING TAB & ADDRESS APPROVAL
// ==========================================
const tenantName = ref(localStorage.getItem('lapaqu_tenant_name') || (authStore.currentUser as any)?.tenant?.name || 'Lapaqu')
const outletName = ref(localStorage.getItem('lapaqu_outlet_name') || (authStore.currentUser as any)?.outlet?.name || '')
const outletSlogan = ref('')
const outletAddress = ref('')
const restaurantLogo = ref(localStorage.getItem('lapaqu_restaurant_logo') || '')
const logoFileInputRef = ref<HTMLInputElement | null>(null)
const isUploadingLogo = ref(false)

const handleLogoChange = async (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return

  if (!file.type.startsWith('image/')) {
    notyf.error('Format file harus berupa gambar (PNG, JPG, WEBP, SVG).', 2000)
    return
  }

  if (file.size > 2 * 1024 * 1024) {
    notyf.error('Ukuran gambar maksimal 2MB.', 2000)
    return
  }

  const reader = new FileReader()
  reader.onload = async (event) => {
    const dataUrl = event.target?.result as string
    restaurantLogo.value = dataUrl
    localStorage.setItem('lapaqu_restaurant_logo', dataUrl)

    window.dispatchEvent(new CustomEvent('lapaqu:branding-updated', {
      detail: {
        logo: dataUrl,
        tenantName: tenantName.value,
        outletName: outletName.value,
      }
    }))

    const targetOutletId = currentOutletId.value || (authStore.availableOutlets?.[0]?.id || '')
    if (targetOutletId) {
      try {
        isUploadingLogo.value = true
        const formData = new FormData()
        formData.append('logo', file)
        const res = await apiClient.post(`/outlets/${targetOutletId}/logo`, formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
        if (res.data?.logo_url) {
          restaurantLogo.value = res.data.logo_url
          localStorage.setItem('lapaqu_restaurant_logo', res.data.logo_url)
        }
        notyf.success('Logo restoran berhasil diperbarui!', 2000)
      } catch (err: any) {
        try {
          await apiClient.put(`/outlets/${targetOutletId}`, { logo_url: dataUrl })
          notyf.success('Logo restoran berhasil disimpan!', 2000)
        } catch {
          notyf.success('Logo restoran tersimpan di browser!', 2000)
        }
      } finally {
        isUploadingLogo.value = false
      }
    } else {
      notyf.success('Logo restoran tersimpan di browser!', 2000)
    }
  }
  reader.readAsDataURL(file)
}

const handleRemoveLogo = async () => {
  restaurantLogo.value = ''
  localStorage.removeItem('lapaqu_restaurant_logo')
  if (logoFileInputRef.value) logoFileInputRef.value.value = ''

  window.dispatchEvent(new CustomEvent('lapaqu:branding-updated', {
    detail: {
      logo: '',
      tenantName: tenantName.value,
      outletName: outletName.value,
    }
  }))

  const targetOutletId = currentOutletId.value || (authStore.availableOutlets?.[0]?.id || '')
  if (targetOutletId) {
    try {
      await apiClient.put(`/outlets/${targetOutletId}`, { logo_url: '' })
    } catch { }
  }
  notyf.success('Logo restoran berhasil dihapus.', 2000)
}


const outletPhone = ref('')
const subdomain = ref(localStorage.getItem('lapaqu_tenant_name')?.toLowerCase().replace(/\s+/g, '') || '')
const enableTax = ref(false)
const taxRate = ref(0)
const enableServiceCharge = ref(false)
const serviceChargeRate = ref(0)
const tableTimeoutMinutes = ref(90)
const timeoutSelectValue = ref<number | string>(90)

const timeoutOptions = computed(() => [
  { value: 30, label: t('settings.branding.timeout30') },
  { value: 60, label: t('settings.branding.timeout60') },
  { value: 90, label: t('settings.branding.timeout90') },
  { value: 120, label: t('settings.branding.timeout120') },
  { value: 'custom', label: t('settings.branding.timeoutCustom') },
])

const syncTimeoutSelect = () => {
  const current = Number(tableTimeoutMinutes.value)
  if ([30, 60, 90, 120].includes(current)) {
    timeoutSelectValue.value = current
  } else {
    timeoutSelectValue.value = 'custom'
  }
}

const handleTimeoutSelectChange = (val: string | number) => {
  if (val === 'custom') {
    timeoutSelectValue.value = 'custom'
    if (!tableTimeoutMinutes.value) {
      tableTimeoutMinutes.value = 90
    }
  } else {
    timeoutSelectValue.value = Number(val)
    tableTimeoutMinutes.value = Number(val)
    saveBrandingSettings(false)
  }
}
const isSavingBranding = ref(false)

const getBrandingSnapshot = () => JSON.stringify({
  tenantName: tenantName.value,
  outletName: outletName.value,
  outletSlogan: outletSlogan.value,
  subdomain: subdomain.value,
  outletAddress: outletAddress.value,
  outletPhone: outletPhone.value,
  enableTax: enableTax.value,
  taxRate: taxRate.value,
  enableServiceCharge: enableServiceCharge.value,
  serviceChargeRate: serviceChargeRate.value,
  tableTimeoutMinutes: tableTimeoutMinutes.value,
})

const lastSavedBranding = ref('')

const saveBrandingSettings = async (silent = false) => {
  const currentSnapshot = getBrandingSnapshot()
  if (currentSnapshot === lastSavedBranding.value) {
    return
  }
  lastSavedBranding.value = currentSnapshot

  localStorage.setItem('lapaqu_branding_settings', currentSnapshot)
  if (tenantName.value) {
    localStorage.setItem('lapaqu_tenant_name', tenantName.value)
    if ((authStore.currentUser as any)?.tenant) {
      (authStore.currentUser as any).tenant.name = tenantName.value
    }
  }
  if (outletName.value) {
    localStorage.setItem('lapaqu_outlet_name', outletName.value)
    if ((authStore.currentUser as any)?.outlet) {
      (authStore.currentUser as any).outlet.name = outletName.value
    }
  }

  // Real-time broadcast update to AppSidebar
  window.dispatchEvent(new CustomEvent('lapaqu:branding-updated', {
    detail: {
      tenantName: tenantName.value,
      outletName: outletName.value,
    }
  }))

  const targetOutletId =
    currentOutletId.value ||
    (outletIdDisplay.value && outletIdDisplay.value !== '-' ? outletIdDisplay.value : '') ||
    (authStore.availableOutlets?.[0]?.id || '')

  if (targetOutletId) {
    try {
      isSavingBranding.value = true
      const res = await apiClient.put(`/outlets/${targetOutletId}`, {
        tenant_name: tenantName.value,
        name: outletName.value,
        slogan: outletSlogan.value,
        subdomain: subdomain.value,
        address: outletAddress.value,
        phone: outletPhone.value,
        enable_tax: enableTax.value,
        tax_percentage: Number(taxRate.value) || 0,
        enable_service_charge: enableServiceCharge.value,
        service_charge_percentage: Number(serviceChargeRate.value) || 0,
        table_timeout: Number(tableTimeoutMinutes.value) || 90,
        logo_url: restaurantLogo.value || undefined,
      })
      if (res.data?.outlet?.id) {
        currentOutletId.value = res.data.outlet.id
        localStorage.setItem('lapaqu_outlet_id', res.data.outlet.id)
      }
      if (!silent) {
        notyf.success('Pengaturan outlet & pajak berhasil disimpan ke server!', 2000)
      }
    } catch (err: any) {
      if (!silent) {
        notyf.error(err.response?.data?.message || 'Gagal menyimpan ke server', 2000)
      }
    } finally {
      isSavingBranding.value = false
    }
  } else {
    if (!silent) {
      notyf.success('Tersimpan di browser', 2000)
    }
  }
}

const saveBrandingSwitch = () => {
  saveBrandingSettings(true)
}

const loadSavedSettings = () => {
  try {
    const savedNotif = localStorage.getItem('lapaqu_notif_settings')
    if (savedNotif) {
      const parsed = JSON.parse(savedNotif)
      if (parsed.notifyOrderSound !== undefined) notifyOrderSound.value = parsed.notifyOrderSound
      if (parsed.notifyBrowserPush !== undefined) notifyBrowserPush.value = parsed.notifyBrowserPush
          if (parsed.notifyKdsVoid !== undefined) notifyKdsVoid.value = parsed.notifyKdsVoid
      if (parsed.notifyWaDaily !== undefined) notifyWaDaily.value = parsed.notifyWaDaily
      if (parsed.waAdminNumber !== undefined) waAdminNumber.value = parsed.waAdminNumber
      if (parsed.notifyEmailWeekly !== undefined) notifyEmailWeekly.value = parsed.notifyEmailWeekly
    }


    const savedBankPending = localStorage.getItem('lapaqu_bank_change_pending')
    if (savedBankPending) {
      try {
        const parsed = JSON.parse(savedBankPending)
        if (parsed.pending) {
          isBankPendingApproval.value = true
          pendingBankInfo.value = parsed.bankInfo || ''
        }
      } catch (e) { }
    }

    const savedBranding = localStorage.getItem('lapaqu_branding_settings')
    if (savedBranding) {
      const parsed = JSON.parse(savedBranding)
      if (parsed.outletSlogan !== undefined) outletSlogan.value = parsed.outletSlogan
      if (parsed.outletAddress !== undefined) outletAddress.value = parsed.outletAddress
      if (parsed.outletPhone !== undefined) outletPhone.value = parsed.outletPhone
      if (parsed.enableTax !== undefined) enableTax.value = parsed.enableTax
      if (parsed.taxRate !== undefined) taxRate.value = parsed.taxRate
      if (parsed.enableServiceCharge !== undefined) enableServiceCharge.value = parsed.enableServiceCharge
      if (parsed.serviceChargeRate !== undefined) serviceChargeRate.value = parsed.serviceChargeRate
      if (parsed.tableTimeoutMinutes !== undefined) {
        tableTimeoutMinutes.value = parsed.tableTimeoutMinutes
        syncTimeoutSelect()
      }
    }
    selectedLangDashboard.value = dashboardLocale.value
    selectedLangPosKds.value = posKdsLocale.value
    selectedLangCustomer.value = customerLocale.value

    lastSavedNotif.value = getNotifSnapshot()
    lastSavedBranding.value = getBrandingSnapshot()
    lastSavedAccount.value = {
      fullName: fullName.value,
      callName: callName.value,
    }
  } catch (e) {
    console.error('Failed to load saved settings', e)
  }
}

// ==========================================
// PAYMENT TAB (Real Backend Integration)
// ==========================================
const isGatewayConnected = ref(false)
const gatewayErrorMessage = ref('Koneksi gateway pembayaran terputus atau kredensial API belum valid.')
const hasFetchedPayment = ref(false)
const isChangeModalOpen = ref(false)
const bankName = ref('')
const accountNumber = ref('')
const accountHolder = ref('')
const xenditSubAccountId = ref('')
const readyToSettleAmount = ref(0)
const pendingSettlementAmount = ref(0)
const isLoadingPayment = ref(false)


const reqBankName = ref('')
const reqAccountNumber = ref('')
const reqAccountHolder = ref('')
const reqNotes = ref('')
const isSubmittingRequest = ref(false)
const requestSubmittedSuccess = ref(false)
const isBankPendingApproval = ref(false)
const pendingBankInfo = ref('')

const copySubAccount = () => {
  navigator.clipboard.writeText(xenditSubAccountId.value || 'sub_acc_xnd_8829104')
  notyf.success('Sub-Account ID berhasil disalin!')
}

const openChangeModal = () => {
  if (isBankPendingApproval.value) {
    notyf.warning('Pengajuan perubahan rekening bank sebelumnya masih menunggu persetujuan tim Lapaqu.')
    return
  }
  reqBankName.value = bankName.value
  reqAccountNumber.value = accountNumber.value.replace(/\*/g, '')
  reqAccountHolder.value = accountHolder.value
  reqNotes.value = ''
  requestSubmittedSuccess.value = false
  isChangeModalOpen.value = true
}

const fetchPaymentAccount = async () => {
  try {
    isLoadingPayment.value = true
    const res = await apiClient.get('/payment-account')
    if (res.data) {
      isGatewayConnected.value = Boolean(res.data.is_configured)
      if (res.data.payment_account) {
        bankName.value = res.data.payment_account.bank_code || 'BCA'
        accountNumber.value = res.data.payment_account.bank_account_number || ''
        accountHolder.value = res.data.payment_account.bank_account_holder_name || ''
        if (res.data.payment_account.xendit_sub_account_id) {
          xenditSubAccountId.value = res.data.payment_account.xendit_sub_account_id
        }
      }
      if (res.data.balances) {
        if (res.data.balances.ready_to_settle !== undefined) {
          readyToSettleAmount.value = res.data.balances.ready_to_settle
        }
        if (res.data.balances.pending_settlement !== undefined) {
          pendingSettlementAmount.value = res.data.balances.pending_settlement
        }
      }
    }
  } catch (err: any) {
    console.error('Error fetching payment account:', err)
    isGatewayConnected.value = false
    gatewayErrorMessage.value = err?.response?.data?.message || 'Koneksi gateway pembayaran terputus atau kredensial API belum valid.'
  } finally {
    isLoadingPayment.value = false
    hasFetchedPayment.value = true
  }
}

const fetchSettlementLogs = async () => {
  try {
    const res = await apiClient.get('/payment-account/settlement-logs')
    if (res.data?.settlement_logs?.data && res.data.settlement_logs.data.length > 0) {
      settlements.value = res.data.settlement_logs.data.map((item: any) => ({
        id: item.id ? item.id.substring(0, 8).toUpperCase() : '-',
        date: item.created_at ? new Date(item.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '-',
        bank: bankName.value || 'BCA',
        net: item.amount || 0,
      }))
    }
  } catch (err) {
    console.error('Error fetching settlement logs:', err)
  }
}

const handleSubmitChangeRequest = async () => {
  if (!reqBankName.value || !reqAccountNumber.value || !reqAccountHolder.value) {
    notyf.error('Lengkapi semua kolom bank, nomor rekening, dan nama pemilik.')
    return
  }
  try {
    isSubmittingRequest.value = true
    const res = await apiClient.post('/payment-account', {
      bank_code: reqBankName.value.toUpperCase(),
      bank_account_number: reqAccountNumber.value,
      bank_account_holder_name: reqAccountHolder.value,
    })

    if (res.data?.payment_account) {
      bankName.value = res.data.payment_account.bank_code
      accountHolder.value = res.data.payment_account.bank_account_holder_name
      if (res.data.payment_account.xendit_sub_account_id) {
        xenditSubAccountId.value = res.data.payment_account.xendit_sub_account_id
      }
      const raw = reqAccountNumber.value
      accountNumber.value = raw.length > 4 ? '*'.repeat(raw.length - 4) + raw.slice(-4) : raw
      isGatewayConnected.value = true
    }
    requestSubmittedSuccess.value = true
    notyf.success('Rekening pembayaran berhasil diperbarui!', 2000)
    setTimeout(() => {
      isChangeModalOpen.value = false
    }, 1200)
  } catch (err: any) {
    notyf.error(err.response?.data?.message || 'Gagal memperbarui rekening pembayaran.')
  } finally {
    isSubmittingRequest.value = false
  }
}

interface SettlementItem {
  id: string
  date: string
  bank: string
  net: number
}

interface InvoiceItem {
  id: string
  date: string
  period: string
  amount: number
}

const settlements = ref<SettlementItem[]>([])

// ==========================================
// BILLING TAB (Real Backend Integration)
// ==========================================
const getInitialPlan = (): 'basic' | 'pro' => {
  try {
    const saved = localStorage.getItem('lapaqu_current_plan')
    if (saved === 'basic' || saved === 'pro') return saved
  } catch {}
  return 'pro'
}

const getInitialBillingDate = (): string => {
  try {
    const saved = localStorage.getItem('lapaqu_next_billing_date')
    if (saved) return saved
  } catch {}
  return '30 September 2026'
}

const currentPlanCode = ref<'basic' | 'pro'>(getInitialPlan())
const nextBillingDate = ref(getInitialBillingDate())
const isLoadingBilling = ref(false)
const hasFetchedBilling = ref(false)

const fetchBillingSubscription = async () => {
  try {
    isLoadingBilling.value = true
    const res = await apiClient.get('/billing/subscription')
    if (res.data?.subscription) {
      const code = res.data.subscription.plan?.code?.toLowerCase() === 'pro' ? 'pro' : 'basic'
      currentPlanCode.value = code
      try {
        localStorage.setItem('lapaqu_current_plan', code)
      } catch {}
      if (res.data.subscription.next_billing_date) {
        const d = new Date(res.data.subscription.next_billing_date)
        const dateFormatted = d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
        nextBillingDate.value = dateFormatted
        try {
          localStorage.setItem('lapaqu_next_billing_date', dateFormatted)
        } catch {}
      }
    }
  } catch (err) {
    console.error('Error fetching subscription:', err)
  } finally {
    isLoadingBilling.value = false
    hasFetchedBilling.value = true
  }
}

const fetchInvoices = async () => {
  try {
    const res = await apiClient.get('/billing/invoices')
    if (res.data?.invoices && res.data.invoices.length > 0) {
      invoices.value = res.data.invoices.map((inv: any) => ({
        id: inv.invoice_number,
        date: new Date(inv.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }),
        period: new Date(inv.created_at).toLocaleDateString('id-ID', { month: 'short', year: 'numeric' }),
        amount: inv.amount,
      }))
    }
  } catch (err) {
    console.error('Error fetching invoices:', err)
  }
}

const changePlan = async (code: 'basic' | 'pro') => {
  if (currentPlanCode.value === code) return
  try {
    const res = await apiClient.post('/billing/change-plan', { plan_code: code })
    currentPlanCode.value = code
    try {
      localStorage.setItem('lapaqu_current_plan', code)
    } catch {}
    notyf.success(res.data?.message || `Berhasil beralih ke paket ${code === 'basic' ? 'Basic Plan' : 'Pro Plan'}`)
    fetchBillingSubscription()
    fetchInvoices()
  } catch (err: any) {
    notyf.error(err.response?.data?.message || 'Gagal mengubah paket langganan.')
  }
}

const invoices = ref<InvoiceItem[]>([])

const fetchOutletSettings = async () => {
  try {
    const res = await apiClient.get('/outlets')
    if (res.data?.outlets && res.data.outlets.length > 0) {
      const currentSavedId = currentOutletId.value || localStorage.getItem('lapaqu_outlet_id')
      const outlet = res.data.outlets.find((o: any) => o.id === currentSavedId)
        || res.data.outlets.find((o: any) => o.is_main)
        || res.data.outlets[0]

      if (outlet) {
        currentOutletId.value = outlet.id
        localStorage.setItem('lapaqu_outlet_id', outlet.id)

        if (outlet.logo_url) {
          restaurantLogo.value = outlet.logo_url
          localStorage.setItem('lapaqu_restaurant_logo', outlet.logo_url)
        } else if (outlet.tenant?.logo_url) {
          restaurantLogo.value = outlet.tenant.logo_url
          localStorage.setItem('lapaqu_restaurant_logo', outlet.tenant.logo_url)
        }

        if (outlet.tenant?.name) {
          tenantName.value = outlet.tenant.name
          localStorage.setItem('lapaqu_tenant_name', outlet.tenant.name)
        }
        if (outlet.name) {
          outletName.value = outlet.name
          localStorage.setItem('lapaqu_outlet_name', outlet.name)
        }
        if (outlet.slogan !== undefined && outlet.slogan !== null) outletSlogan.value = outlet.slogan
        if (outlet.address !== undefined && outlet.address !== null) outletAddress.value = outlet.address
        if (outlet.phone !== undefined && outlet.phone !== null) outletPhone.value = outlet.phone
        if (outlet.enable_tax !== undefined) enableTax.value = Boolean(outlet.enable_tax)
        if (outlet.tax_percentage !== undefined) taxRate.value = Number(outlet.tax_percentage)
        if (outlet.enable_service_charge !== undefined) enableServiceCharge.value = Boolean(outlet.enable_service_charge)
        if (outlet.service_charge_percentage !== undefined) serviceChargeRate.value = Number(outlet.service_charge_percentage)
        if (outlet.table_timeout !== undefined) {
          tableTimeoutMinutes.value = Number(outlet.table_timeout)
          syncTimeoutSelect()
        }
      }
    }
  } catch (e) {
    console.error('Failed to load outlet settings from server:', e)
  }
}


watch([isOpen, activeTab, filteredNavItems], () => {
  nextTick(() => {
    updateNavIndicator()
    const timers = [40, 100, 180, 300]
    timers.forEach(t => setTimeout(updateNavIndicator, t))
  })
}, { immediate: true })

// Auto-fetch live data when modal opens or tab switches
watch([isOpen, activeTab], ([open, tab]) => {
  if (open) {
    fetchBillingSubscription()
    if (tab === 'branding') {
      fetchOutletSettings()
    } else if (tab === 'payment') {
      fetchOutletSettings()
      fetchPaymentAccount()
      fetchSettlementLogs()
    } else if (tab === 'billing') {
      fetchInvoices()
    }
  }
}, { immediate: true })
</script>

<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <!-- BACKDROP -->
      <div v-if="isOpen"
        class="fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-4 md:p-6 lg:p-0 overscroll-contain">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-xs transition-opacity" @click="closeSettingsModal"
          @wheel.prevent @touchmove.prevent />

        <!-- MODAL CONTAINER (Responsive dengan breakpoint Tailwind: sm, md, lg, xl) -->
        <div
          class="settings-modal-dialog modal-dialog-content relative w-full sm:w-[580px] md:w-[760px] lg:w-[940px] xl:w-[1044px] h-[92vh] sm:h-[680px] md:h-[740px] lg:h-[780px] xl:h-[829px] max-w-full max-h-[calc(100vh-24px)] bg-white dark:bg-[#1E293B] text-[#202224] dark:text-white rounded-2xl border border-[#E2E8F0] dark:border-[#334155] flex flex-col md:flex-row overflow-hidden z-10 font-sans transition-all duration-150 overscroll-contain"
          role="dialog" aria-modal="true" @click.stop>

          <!-- LEFT SIDEBAR NAVIGATION (Sidebar Style & Font) -->
          <div
            class="w-full md:w-[240px] xl:w-[260px] max-h-[190px] md:max-h-none overflow-y-auto bg-[#F8FAFC] dark:bg-[#151D2A] overscroll-contain border-b md:border-b-0 md:border-r border-[#E2E8F0] dark:border-[#334155] p-3.5 md:p-4 flex flex-col shrink-0 transition-colors">
            <!-- Search -->
            <AppSearchInput v-model="searchQuery" :placeholder="t('settings.searchPlaceholder')" size="sm" rounded="lg"
              class="mb-4 w-full max-w-none" />

            <!-- Section Label -->
            <div class="px-3 pt-2 pb-1.5">
              <span class="text-xs font-bold uppercase tracking-wider text-[#64748B] dark:text-[#94A3B8]">
                {{ t('sidebar.sectionSystem') }}
              </span>
            </div>

            <!-- Nav Items (Exact AppSidebar navigation buttons & iOS Spring Pill) -->
            <div ref="navContainerRef" class="flex-1 overflow-y-auto space-y-1 pr-1 custom-scroll relative select-none">
              <!-- Shared iOS Spring-Physics Sliding Pill -->
              <div v-show="isIndicatorVisible"
                class="absolute rounded-xl bg-[#4880FF] shadow-sm pointer-events-none transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)] z-0"
                :style="{
                  top: `${indicatorTop}px`,
                  left: `${indicatorLeft}px`,
                  width: `${indicatorWidth}px`,
                  height: `${indicatorHeight}px`,
                }">
              </div>

              <button v-for="item in filteredNavItems" :key="item.key" type="button" @click="setTab(item.key)"
                :data-active="activeTab === item.key ? 'true' : undefined"
                :class="[
                  'w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm transition-colors duration-150 text-left cursor-pointer group relative z-10',
                  activeTab === item.key
                    ? 'text-white font-bold'
                    : 'text-[#475569] dark:text-[#CBD5E1] hover:text-slate-900 dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155] font-semibold'
                ]">
                <AppIcon :name="item.icon" :size="18" :class="[
                  'shrink-0 transition-colors duration-150',
                  activeTab === item.key
                    ? 'text-white'
                    : 'text-[#64748B] dark:text-[#94A3B8] group-hover:text-slate-900 dark:group-hover:text-white'
                ]" />
                <span class="truncate">{{ item.label }}</span>
              </button>
            </div>
          </div>

          <!-- RIGHT CONTENT PANEL -->
          <div class="flex-1 flex flex-col bg-white dark:bg-[#1E293B] overflow-hidden min-w-0 transition-colors">
            <!-- Right Header (Sejajar rapi dengan baris Search di sidebar kiri) -->
            <div class="h-[68px] flex items-center justify-end px-6 md:px-8 shrink-0">
              <button type="button" @click="closeSettingsModal"
                class="w-9 h-9 rounded-lg flex items-center justify-center text-[#64748B] dark:text-[#94A3B8] hover:text-[#202224] dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer"
                :title="t('settings.closeTooltip')">
                <AppIcon name="close" :size="20" />
              </button>
            </div>

            <!-- Content Scrollable -->
            <div
              class="flex-1 overflow-y-auto [scrollbar-gutter:stable] px-6 pb-8 md:px-8 md:pb-10 custom-scroll overscroll-contain">

              <!-- TAB: GENERAL -->
              <div v-if="activeTab === 'general'" class="space-y-0">

                <!-- SECTION: Preferences -->
                <h2 class="text-sm font-bold text-[#1E293B] dark:text-white mb-1">{{ t('settings.general.preferences')
                  }}</h2>
                <div class="divide-y divide-[#E2E8F0] dark:divide-[#334155]/60 text-sm">
                  <!-- Appearance (Tampilan Tema) -->
                  <div class="flex items-center justify-between py-4">
                    <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{ t('settings.general.appearance') }}</div>
                    <div
                      class="relative flex items-center bg-[#F1F5F9] dark:bg-[#0F172A] border border-[#E2E8F0] dark:border-[#334155] p-1 rounded-lg h-10">
                      <button
                        type="button"
                        @click="setAppearance('system')"
                        class="relative h-full px-2.5 rounded-md cursor-pointer flex items-center justify-center transition-colors z-10"
                        :class="themePreference === 'system' ? 'text-[#202224] dark:text-white font-medium' : 'text-[#64748B] dark:text-[#94A3B8] hover:text-[#1E293B] dark:hover:text-white'"
                        :title="t('settings.general.themeSystem')"
                      >
                        <Motion
                          v-if="themePreference === 'system'"
                          layoutId="activeThemePill"
                          class="absolute inset-0 bg-white dark:bg-[#1E293B] rounded-md shadow-xs -z-10"
                          :transition="{ type: 'spring', stiffness: 500, damping: 35 }"
                        />
                        <AppIcon name="desktop_windows" :size="18" />
                      </button>
                      <button
                        type="button"
                        @click="setAppearance('light')"
                        class="relative h-full px-2.5 rounded-md cursor-pointer flex items-center justify-center transition-colors z-10"
                        :class="themePreference === 'light' ? 'text-[#202224] dark:text-white font-medium' : 'text-[#64748B] dark:text-[#94A3B8] hover:text-[#1E293B] dark:hover:text-white'"
                        :title="t('settings.general.themeLight')"
                      >
                        <Motion
                          v-if="themePreference === 'light'"
                          layoutId="activeThemePill"
                          class="absolute inset-0 bg-white dark:bg-[#1E293B] rounded-md shadow-xs -z-10"
                          :transition="{ type: 'spring', stiffness: 500, damping: 35 }"
                        />
                        <AppIcon name="light_mode" :size="18" />
                      </button>
                      <button
                        type="button"
                        @click="setAppearance('dark')"
                        class="relative h-full px-2.5 rounded-md cursor-pointer flex items-center justify-center transition-colors z-10"
                        :class="themePreference === 'dark' ? 'text-[#202224] dark:text-white font-medium' : 'text-[#64748B] dark:text-[#94A3B8] hover:text-[#1E293B] dark:hover:text-white'"
                        :title="t('settings.general.themeDark')"
                      >
                        <Motion
                          v-if="themePreference === 'dark'"
                          layoutId="activeThemePill"
                          class="absolute inset-0 bg-white dark:bg-[#1E293B] rounded-md shadow-xs -z-10"
                          :transition="{ type: 'spring', stiffness: 500, damping: 35 }"
                        />
                        <AppIcon name="dark_mode" :size="18" />
                      </button>
                    </div>
                  </div>

                  <!-- Motion (Animasi & Gerakan) -->
                  <div class="flex items-center justify-between py-4">
                    <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{ t('settings.general.motion') }}</div>
                    <div
                      class="relative flex items-center bg-[#F1F5F9] dark:bg-[#0F172A] border border-[#E2E8F0] dark:border-[#334155] p-1 rounded-lg h-10 text-xs">
                      <button
                        type="button"
                        @click="setMotionPreference('system')"
                        class="relative h-full px-3.5 rounded-md font-medium cursor-pointer transition-colors z-10 flex items-center justify-center text-center"
                        :class="motionPreference === 'system' ? 'text-[#202224] dark:text-white' : 'text-[#64748B] dark:text-[#94A3B8] hover:text-[#1E293B] dark:hover:text-white'"
                      >
                        <Motion
                          v-if="motionPreference === 'system'"
                          layoutId="activeMotionPill"
                          class="absolute inset-0 bg-white dark:bg-[#1E293B] rounded-md shadow-xs -z-10"
                          :transition="{ type: 'spring', stiffness: 500, damping: 35 }"
                        />
                        {{ t('settings.general.motionSystem') }}
                      </button>
                      <button
                        type="button"
                        @click="setMotionPreference('reduced')"
                        class="relative h-full px-3.5 rounded-md font-medium cursor-pointer transition-colors z-10 flex items-center justify-center text-center"
                        :class="motionPreference === 'reduced' ? 'text-[#202224] dark:text-white' : 'text-[#64748B] dark:text-[#94A3B8] hover:text-[#1E293B] dark:hover:text-white'"
                      >
                        <Motion
                          v-if="motionPreference === 'reduced'"
                          layoutId="activeMotionPill"
                          class="absolute inset-0 bg-white dark:bg-[#1E293B] rounded-md shadow-xs -z-10"
                          :transition="{ type: 'spring', stiffness: 500, damping: 35 }"
                        />
                        {{ t('settings.general.motionReduced') }}
                      </button>
                    </div>
                  </div>
                </div>

                <!-- SECTION: Language -->
                <h2 class="text-sm font-bold text-[#1E293B] dark:text-white mt-8 mb-1">{{
                  t('settings.general.langSection') }}</h2>
                <div class="divide-y divide-[#E2E8F0] dark:divide-[#334155]/60 text-sm">
                  <!-- Bahasa Antarmuka Dashboard -->
                  <div class="flex flex-col sm:flex-row sm:items-center justify-between py-4 gap-2">
                    <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{
                      t('settings.general.langDashboardTitle') }}</div>
                    <div class="w-full sm:w-[320px] shrink-0">
                      <AppSelect v-model="selectedLangDashboard" :options="languageOptions"
                        @change="saveLangDashboard" />
                    </div>
                  </div>

                  <!-- Bahasa Antarmuka POS & KDS -->
                  <div class="flex flex-col sm:flex-row sm:items-center justify-between py-4 gap-2">
                    <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{
                      t('settings.general.langPosKdsTitle') }}</div>
                    <div class="w-full sm:w-[320px] shrink-0">
                      <AppSelect v-model="selectedLangPosKds" :options="languageOptions" @change="saveLangPosKds" />
                    </div>
                  </div>

                  <!-- Bahasa Antarmuka Self Order -->
                  <div class="flex flex-col sm:flex-row sm:items-center justify-between py-4 gap-2">
                    <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{
                      t('settings.general.langCustomerTitle') }}</div>
                    <div class="w-full sm:w-[320px] shrink-0">
                      <AppSelect v-model="selectedLangCustomer" :options="languageOptions" @change="saveLangCustomer" />
                    </div>
                  </div>

                  <!-- Format Mata Uang -->
                  <div class="flex items-center justify-between py-4">
                    <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{
                      t('settings.general.currencyTitle') }}</div>
                    <div
                      class="text-sm font-bold text-[#202224] dark:text-white bg-[#F1F5F9] dark:bg-[#0F172A] px-3 py-1.5 rounded-lg border border-[#E2E8F0] dark:border-[#334155]">
                      IDR (Rp)
                    </div>
                  </div>
                </div>

                <!-- SECTION: Notifications -->
                <h2 class="text-sm font-bold text-[#1E293B] dark:text-white mt-8 mb-1">{{
                  t('settings.general.notificationSection') }}</h2>
                <div class="divide-y divide-[#E2E8F0] dark:divide-[#334155]/60 text-sm">
                  <!-- Suara pesanan baru -->
                  <div class="py-4">
                    <AppToggle v-model="notifyOrderSound" :label="t('settings.notifications.soundOrder')"
                      @change="onToggleOrderSound" />
                  </div>

                  <!-- Browser Push -->
                  <div class="py-4">
                    <AppToggle v-model="notifyBrowserPush" :label="t('settings.notifications.browserPush')"
                      @change="onToggleBrowserPush" />
                  </div>

                  <!-- Alert Void KDS -->
                  <div class="py-4">
                    <AppToggle v-model="notifyKdsVoid" :label="t('settings.notifications.kdsVoid')"
                      @change="onToggleKdsVoid" />
                  </div>

                  <!-- WA Harian -->
                  <div class="py-4">
                    <AppToggle v-model="notifyWaDaily" :label="t('settings.notifications.waDaily')"
                      @change="saveNotificationSwitch" />

                    <!-- Nomor WA (conditional: smooth grid accordion tanpa bounce) -->
                    <div class="grid transition-all duration-200 ease-out"
                      :class="notifyWaDaily ? 'grid-rows-[1fr] opacity-100 mt-2' : 'grid-rows-[0fr] opacity-0 mt-0 pointer-events-none'">
                      <div class="overflow-hidden">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-1">
                          <div class="flex-1 min-w-0 pr-4">
                            <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{
                              t('settings.notifications.waNumber') }}</div>
                          </div>
                          <div class="w-full sm:w-[320px] shrink-0">
                            <input v-model="waAdminNumber" type="text" @blur="saveNotificationSettings(false)"
                              @keydown.enter="($event.target as HTMLElement).blur()"
                              class="w-full h-10 px-3.5 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm text-[#202224] dark:text-white focus:outline-none focus:border-[#4880FF]"
                              placeholder="08123456789" />
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Email Mingguan -->
                  <div class="py-4">
                    <AppToggle v-model="notifyEmailWeekly" :label="t('settings.notifications.emailWeekly')"
                      @change="saveNotificationSwitch" />
                  </div>
                </div>




              </div>

              <!-- TAB: ACCOUNT -->
              <div v-else-if="activeTab === 'account'" class="space-y-0">
                <h2 class="text-sm font-bold text-[#1E293B] dark:text-white mb-1">{{ t('settings.account.title') }}</h2>
                <div class="divide-y divide-[#E2E8F0] dark:divide-[#334155]/60 text-sm">
                  <!-- Foto Profil (Persis Seperti Style Logo Restoran) -->
                  <div class="flex flex-col sm:flex-row sm:items-center justify-between py-4 gap-4">
                    <div class="flex-1 min-w-0 pr-4">
                      <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{ t('settings.account.avatar') }}</div>
                      <div class="text-xs text-[#64748B] dark:text-[#94A3B8] mt-0.5 leading-relaxed">{{ t('settings.account.avatarDesc') }}</div>
                    </div>
                    <div class="w-full sm:w-[320px] shrink-0 flex items-center justify-end">
                      <!-- Hidden file input for avatar -->
                      <input ref="avatarFileInputRef" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="handleAvatarChange" />

                      <!-- Avatar Image Kotak Rounded-lg (Persis seperti style Logo Restoran) -->
                      <img
                        v-if="userAvatar"
                        :src="userAvatar"
                        :alt="fullName || 'Foto Profil'"
                        @click="avatarFileInputRef?.click()"
                        :class="[
                          'w-12 h-12 rounded-lg object-cover border border-[#E2E8F0] dark:border-[#334155] shadow-xs shrink-0 cursor-pointer hover:border-[#4880FF] hover:opacity-90 active:scale-95 transition-all',
                          isUploadingAvatar ? 'animate-pulse opacity-50 pointer-events-none' : ''
                        ]"
                        />
                      <div
                        v-else
                        @click="avatarFileInputRef?.click()"
                        :class="[
                          'w-12 h-12 rounded-lg bg-[#4880FF]/15 text-[#4880FF] flex items-center justify-center font-bold text-sm shrink-0 border border-[#4880FF]/25 cursor-pointer hover:bg-[#4880FF]/25 hover:border-[#4880FF] active:scale-95 transition-all',
                          isUploadingAvatar ? 'animate-pulse opacity-50 pointer-events-none' : ''
                        ]"
                        >
                        {{ (fullName || 'U').slice(0, 2).toUpperCase() }}
                      </div>
                    </div>
                  </div>

                  <!-- Full name -->
                  <div class="flex flex-col sm:flex-row sm:items-center justify-between py-4 gap-4">
                    <div class="flex-1 min-w-0 pr-4">
                      <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{ t('settings.account.fullName')
                        }}</div>
                    </div>
                    <div class="w-full sm:w-[320px] shrink-0">
                      <input v-model="fullName" type="text" @blur="saveAccountSettings('Nama Lengkap')"
                        @keydown.enter="($event.target as HTMLElement).blur()"
                        class="w-full h-10 px-3.5 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm text-[#202224] dark:text-white focus:outline-none focus:border-[#4880FF]" />
                    </div>
                  </div>

                  <!-- What should Lapaqu call you? -->
                  <div class="flex flex-col sm:flex-row sm:items-center justify-between py-4 gap-4">
                    <div class="flex-1 min-w-0 pr-4">
                      <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{ t('settings.account.callName')
                        }}</div>
                    </div>
                    <div class="w-full sm:w-[320px] shrink-0">
                      <input v-model="callName" type="text" @blur="saveAccountSettings('Nama Panggilan')"
                        @keydown.enter="($event.target as HTMLElement).blur()"
                        class="w-full h-10 px-3.5 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm text-[#202224] dark:text-white focus:outline-none focus:border-[#4880FF]" />
                    </div>
                  </div>

                  <!-- Posisi -->
                  <div class="flex items-center justify-between py-4">
                    <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{ t('settings.account.role') }}
                    </div>
                    <div class="text-sm font-normal text-[#202224] dark:text-white">
                      {{ displayRole }}
                    </div>
                  </div>

                  <!-- Email Address -->
                  <div class="flex items-center justify-between py-4">
                    <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{ t('settings.account.email') }}
                    </div>
                    <div class="text-sm font-normal text-[#202224] dark:text-white">
                      {{ userEmail }}
                    </div>
                  </div>

                  <!-- ID Akun -->
                  <div class="flex items-center justify-between py-4">
                    <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{ t('settings.account.accountId')
                      }}</div>
                    <div class="text-sm font-normal text-[#202224] dark:text-white">
                      {{ tenantIdDisplay }}
                    </div>
                  </div>

                  <!-- ID Outlet -->
                  <div class="flex items-center justify-between py-4">
                    <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{ t('settings.account.outletId') }}
                    </div>
                    <div class="text-sm font-normal text-[#202224] dark:text-white">
                      {{ outletIdDisplay }}
                    </div>
                  </div>

                  <!-- Ubah Password -->
                  <div class="py-4">
                    <div class="flex items-center justify-between">
                      <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{
                        t('settings.account.changePassword') }}</div>
                      <AppButton v-if="!isChangePasswordOpen" variant="secondary" size="sm"
                        @click="isChangePasswordOpen = true">
                        {{ t('settings.account.changePassword') }}
                      </AppButton>
                    </div>

                    <!-- Input Kata Sandi (conditional: smooth grid accordion tanpa bounce) -->
                    <div class="grid transition-all duration-200 ease-out"
                      :class="isChangePasswordOpen ? 'grid-rows-[1fr] opacity-100 mt-3' : 'grid-rows-[0fr] opacity-0 mt-0 pointer-events-none'">
                      <div class="overflow-hidden">
                        <div class="space-y-3 pt-1">
                          <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                            <input v-model="currentPassword" type="password"
                              :placeholder="t('settings.account.currentPassword')"
                              class="px-3 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm text-[#202224] dark:text-white focus:outline-none focus:border-[#4880FF]" />
                            <input v-model="newPassword" type="password"
                              :placeholder="t('settings.account.newPassword')"
                              class="px-3 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm text-[#202224] dark:text-white focus:outline-none focus:border-[#4880FF]" />
                            <input v-model="confirmPassword" type="password"
                              :placeholder="t('settings.account.confirmPassword')"
                              class="px-3 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm text-[#202224] dark:text-white focus:outline-none focus:border-[#4880FF]" />
                          </div>

                          <div v-if="passwordError" class="text-sm text-[#FD5454] font-medium">{{ passwordError }}</div>
                          <div class="flex justify-end gap-2 pt-1">
                            <AppButton variant="secondary" size="sm" @click="cancelChangePassword">
                              {{ t('settings.account.cancel') }}
                            </AppButton>
                            <AppButton variant="primary" size="sm" :loading="isChangingPassword"
                              @click="handleChangePassword">
                              {{ t('settings.account.updatePassword') }}
                            </AppButton>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>


                  <!-- Log out -->
                  <div class="flex items-center justify-between py-4">
                    <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{ t('settings.account.logout') }}
                    </div>
                    <AppButton variant="danger" size="sm" @click="isLogoutConfirmOpen = true">
                      {{ t('settings.account.logoutBtn') }}
                    </AppButton>
                  </div>
                </div>
              </div>

              <!-- TAB: BRANDING -->
              <div v-else-if="activeTab === 'branding'" class="space-y-0">
                <h2 class="text-sm font-bold text-[#1E293B] dark:text-white mb-1">{{ t('settings.branding.title') }}
                </h2>
                <div class="divide-y divide-[#E2E8F0] dark:divide-[#334155]/60 text-sm">
                  <!-- Logo Restoran -->
                  <div class="flex flex-col sm:flex-row sm:items-center justify-between py-4 gap-4">
                    <div class="flex-1 min-w-0 pr-4">
                      <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{ t('settings.branding.logo') }}
                      </div>
                      <div class="text-xs text-[#64748B] dark:text-[#94A3B8] mt-0.5 leading-relaxed">{{
                        t('settings.branding.logoDesc') }}</div>
                    </div>
                    <div class="w-full sm:w-[320px] shrink-0 flex items-center justify-end">
                      <!-- Hidden file input for logo -->
                      <input ref="logoFileInputRef" type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="hidden" @change="handleLogoChange" />

                      <!-- Logo Image Kotak Rounded-lg -->
                      <img
                        v-if="restaurantLogo"
                        :src="restaurantLogo"
                        alt="Logo Restoran"
                        @click="logoFileInputRef?.click()"
                        :class="[
                          'w-12 h-12 rounded-lg object-cover border border-[#E2E8F0] dark:border-[#334155] shadow-xs shrink-0 cursor-pointer hover:border-[#4880FF] hover:opacity-90 active:scale-95 transition-all',
                          isUploadingLogo ? 'animate-pulse opacity-50 pointer-events-none' : ''
                        ]"
                        />
                      <div
                        v-else
                        @click="logoFileInputRef?.click()"
                        :class="[
                          'w-12 h-12 rounded-lg bg-[#4880FF]/15 text-[#4880FF] flex items-center justify-center font-bold text-sm shrink-0 border border-[#4880FF]/25 cursor-pointer hover:bg-[#4880FF]/25 hover:border-[#4880FF] active:scale-95 transition-all',
                          isUploadingLogo ? 'animate-pulse opacity-50 pointer-events-none' : ''
                        ]"
                        >
                        {{ (tenantName || outletName || 'LQ').slice(0, 2).toUpperCase() }}
                      </div>
                    </div>
                  </div>

                  <!-- Nama Bisnis / Usaha (Tenant Name) -->
                  <div class="flex flex-col sm:flex-row sm:items-center justify-between py-4 gap-4">
                    <div class="flex-1 min-w-0 pr-4">
                      <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{
                        t('settings.branding.tenantName') }}</div>
                    </div>
                    <div class="w-full sm:w-[320px] shrink-0">
                      <input v-model="tenantName" type="text" :placeholder="t('settings.branding.tenantPlaceholder')"
                        @blur="saveBrandingSettings(false)" @keydown.enter="($event.target as HTMLElement).blur()"
                        class="w-full h-10 px-3.5 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm text-[#202224] dark:text-white focus:outline-none focus:border-[#4880FF]" />
                    </div>
                  </div>

                  <!-- Nama Outlet / Cabang -->
                  <div class="flex flex-col sm:flex-row sm:items-center justify-between py-4 gap-4">
                    <div class="flex-1 min-w-0 pr-4">
                      <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{
                        t('settings.branding.outletName') }}</div>
                    </div>
                    <div class="w-full sm:w-[320px] shrink-0">
                      <input v-model="outletName" type="text" :placeholder="t('settings.branding.outletPlaceholder')"
                        @blur="saveBrandingSettings(false)" @keydown.enter="($event.target as HTMLElement).blur()"
                        class="w-full h-10 px-3.5 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm text-[#202224] dark:text-white focus:outline-none focus:border-[#4880FF]" />
                    </div>
                  </div>

                  <!-- Slogan -->
                  <div class="flex flex-col sm:flex-row sm:items-center justify-between py-4 gap-4">
                    <div class="flex-1 min-w-0 pr-4">
                      <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{ t('settings.branding.slogan')
                        }}</div>
                    </div>
                    <div class="w-full sm:w-[320px] shrink-0">
                      <input v-model="outletSlogan" type="text" :placeholder="t('settings.branding.sloganPlaceholder')"
                        @blur="saveBrandingSettings(false)" @keydown.enter="($event.target as HTMLElement).blur()"
                        class="w-full h-10 px-3.5 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm text-[#202224] dark:text-white focus:outline-none focus:border-[#4880FF]" />
                    </div>
                  </div>

                  <!-- Tenant Subdomain -->
                  <div class="flex flex-col sm:flex-row sm:items-center justify-between py-4 gap-4">
                    <div class="flex-1 min-w-0 pr-4">
                      <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{
                        t('settings.branding.subdomain') }}</div>
                    </div>
                    <div class="w-full sm:w-[320px] shrink-0">
                      <div
                        class="w-full h-10 flex items-center rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] focus-within:border-[#4880FF] overflow-hidden">
                        <input v-model="subdomain" type="text" @blur="saveBrandingSettings(false)"
                          @keydown.enter="($event.target as HTMLElement).blur()"
                          class="flex-1 min-w-0 h-full px-3.5 text-sm text-[#202224] dark:text-white bg-transparent focus:outline-none" />
                        <span
                          class="px-3 text-xs text-[#64748B] dark:text-[#94A3B8] bg-slate-50 dark:bg-[#1E293B] border-l border-[#E2E8F0] dark:border-[#334155] h-full flex items-center select-none shrink-0 font-semibold">.lapaqu.id</span>
                      </div>
                    </div>
                  </div>

                  <!-- Alamat Lengkap Outlet (Fixed Size Textarea di Sisi Kanan) -->
                  <div class="flex flex-col sm:flex-row sm:items-start justify-between py-4 gap-4">
                    <div class="flex-1 min-w-0 pr-4">
                      <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{ t('settings.branding.address') }}</div>
                      <div class="text-xs text-[#64748B] dark:text-[#94A3B8] mt-0.5 leading-relaxed">{{ t('settings.branding.addressDesc') }}</div>
                    </div>
                    <div class="w-full sm:w-[320px] shrink-0">
                      <textarea
                        v-model="outletAddress"
                        rows="3"
                        :placeholder="t('settings.branding.addressPlaceholder')"
                        @blur="saveBrandingSettings(false)"
                        class="w-full h-20 px-3.5 py-2.5 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm text-[#202224] dark:text-white resize-none focus:outline-none focus:border-[#4880FF] leading-relaxed custom-scroll"
                      />
                    </div>
                  </div>

                  <!-- Telepon -->
                  <div class="flex flex-col sm:flex-row sm:items-center justify-between py-4 gap-4">
                    <div class="flex-1 min-w-0 pr-4">
                      <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{ t('settings.branding.phone') }}
                      </div>
                    </div>
                    <div class="w-full sm:w-[320px] shrink-0">
                      <input v-model="outletPhone" type="text" placeholder="0812345678"
                        @blur="saveBrandingSettings(false)" @keydown.enter="($event.target as HTMLElement).blur()"
                        class="w-full h-10 px-3.5 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm text-[#202224] dark:text-white focus:outline-none focus:border-[#4880FF]" />
                    </div>
                  </div>

                  <!-- Pajak Restoran (PB1) -->
                  <div class="flex flex-col sm:flex-row sm:items-center justify-between py-4 gap-4">
                    <div class="flex-1 min-w-0 pr-4">
                      <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{ t('settings.branding.taxTitle')
                        }}</div>
                      <div class="text-xs text-[#64748B] dark:text-[#94A3B8] mt-0.5 leading-relaxed">{{
                        t('settings.branding.taxDesc') }}</div>
                    </div>
                    <div class="w-full sm:w-[320px] shrink-0">
                      <div class="relative w-full">
                        <input v-model.number="taxRate" type="number" min="0" max="100" placeholder="0"
                          @blur="saveBrandingSettings(false)" @keydown.enter="($event.target as HTMLElement).blur()"
                          class="w-full h-10 px-3.5 py-2 pr-8 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm text-[#202224] dark:text-white focus:outline-none focus:border-[#4880FF]" />
                        <span
                          class="absolute right-3.5 top-1/2 -translate-y-1/2 text-sm text-[#64748B] dark:text-[#94A3B8] font-medium pointer-events-none">%</span>
                      </div>
                    </div>
                  </div>

                  <!-- Biaya Layanan (Service Charge) -->
                  <div class="flex flex-col sm:flex-row sm:items-center justify-between py-4 gap-4">
                    <div class="flex-1 min-w-0 pr-4">
                      <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{
                        t('settings.branding.serviceTitle') }}</div>
                      <div class="text-xs text-[#64748B] dark:text-[#94A3B8] mt-0.5 leading-relaxed">{{
                        t('settings.branding.serviceDesc') }}</div>
                    </div>
                    <div class="w-full sm:w-[320px] shrink-0">
                      <div class="relative w-full">
                        <input v-model.number="serviceChargeRate" type="number" min="0" max="100" placeholder="0"
                          @blur="saveBrandingSettings(false)" @keydown.enter="($event.target as HTMLElement).blur()"
                          class="w-full h-10 px-3.5 py-2 pr-8 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm text-[#202224] dark:text-white focus:outline-none focus:border-[#4880FF]" />
                        <span
                          class="absolute right-3.5 top-1/2 -translate-y-1/2 text-sm text-[#64748B] dark:text-[#94A3B8] font-medium pointer-events-none">%</span>
                      </div>
                    </div>
                  </div>

                  <!-- Timeout Sesi Meja -->
                  <div class="flex flex-col sm:flex-row sm:items-start justify-between py-4 gap-4">
                    <div class="flex-1 min-w-0 pr-4 sm:h-10 flex items-center">
                      <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{
                        t('settings.branding.timeoutTitle') }}</div>
                    </div>
                    <div class="w-full sm:w-[320px] shrink-0">
                      <!-- Dropdown Pilihan Timeout (Menit Saja & Opsi Kustom) -->
                      <AppSelect v-model="timeoutSelectValue" :options="timeoutOptions"
                        @change="handleTimeoutSelectChange" placement="top" />

                      <!-- Input Manual Kustom (smooth grid accordion seperti di nomor hp) -->
                      <div class="grid transition-all duration-200 ease-out"
                        :class="timeoutSelectValue === 'custom' ? 'grid-rows-[1fr] opacity-100 mt-2' : 'grid-rows-[0fr] opacity-0 mt-0 pointer-events-none'">
                        <div class="overflow-hidden">
                          <div class="relative w-full">
                            <input v-model.number="tableTimeoutMinutes" type="number" min="1" max="1440"
                              :placeholder="t('settings.branding.timeoutCustomPlaceholder')"
                              @blur="saveBrandingSettings(false)" @keydown.enter="($event.target as HTMLElement).blur()"
                              class="w-full h-10 px-3.5 py-2 pr-16 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-sm text-[#202224] dark:text-white focus:outline-none focus:border-[#4880FF]" />
                            <span
                              class="absolute right-3.5 top-1/2 -translate-y-1/2 text-sm text-[#64748B] dark:text-[#94A3B8] font-medium pointer-events-none">
                              {{ t('settings.branding.minutes') }}
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>


              </div>

              <!-- TAB: PAYMENT -->
              <div v-else-if="activeTab === 'payment'" class="space-y-0">
                <h2 class="text-sm font-bold text-[#1E293B] dark:text-white mb-1">{{ t('settings.payment.title') }}</h2>
                <div class="divide-y divide-[#E2E8F0] dark:divide-[#334155]/60 text-sm">
                  <!-- Gateway Status -->
                  <div class="flex items-center justify-between py-4">
                    <div>
                      <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{ t('settings.payment.gateway')
                        }}</div>
                      <!-- Loading Skeleton / Spinner saat pertama kali mengecek -->
                      <div v-if="isLoadingPayment && !hasFetchedPayment" class="flex items-center gap-1.5 mt-1 text-xs text-[#64748B] dark:text-[#94A3B8]">
                        <div class="w-3 h-3 rounded-full border-2 border-slate-400 border-t-transparent animate-spin"></div>
                        <span>Memeriksa status gateway...</span>
                      </div>
                      <!-- Error Message HANYA jika sudah selesai fetch dan terbukti belum terkoneksi -->
                      <div v-else-if="hasFetchedPayment && !isGatewayConnected" class="text-xs text-[#FD5454] mt-1 font-medium">
                        {{ gatewayErrorMessage }}
                      </div>
                    </div>
                    <div class="flex items-center shrink-0">
                      <!-- Loading spinner -->
                      <div v-if="isLoadingPayment && !hasFetchedPayment" class="w-6 h-6 flex items-center justify-center">
                        <div class="w-4 h-4 rounded-full border-2 border-slate-300 dark:border-slate-600 border-t-transparent animate-spin"></div>
                      </div>
                      <!-- Ceklis Verifikasi Hijau (persis seperti Outlet Utama) -->
                      <svg v-else-if="isGatewayConnected" class="w-6 h-6 text-emerald-500 drop-shadow-2xs shrink-0"
                        viewBox="0 0 24 24" fill="currentColor">
                        <path fill-rule="evenodd"
                          d="M8.603 3.799A4.49 4.49 0 0112 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 013.498 1.307 4.491 4.491 0 011.307 3.497A4.49 4.49 0 0121.75 12a4.49 4.49 0 01-1.549 3.397 4.491 4.491 0 01-1.307 3.497 4.491 4.491 0 01-3.497 1.307A4.49 4.49 0 0112 21.75a4.49 4.49 0 01-3.397-1.549 4.49 4.49 0 01-3.498-1.306 4.491 4.491 0 01-1.307-3.498A4.49 4.49 0 012.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 011.307-3.497 4.49 4.49 0 013.497-1.307zm7.007 6.387a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z"
                          clip-rule="evenodd" />
                      </svg>
                      <!-- Tanda Seru Merah jika Belum Terkoneksi (setelah selesai load) -->
                      <svg v-else-if="hasFetchedPayment && !isGatewayConnected" class="w-6 h-6 text-[#FD5454] shrink-0" viewBox="0 0 24 24" fill="currentColor">
                        <path fill-rule="evenodd"
                          d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z"
                          clip-rule="evenodd" />
                      </svg>
                    </div>
                  </div>

                  <!-- Saldo Tersedia -->
                  <div class="flex items-center justify-between py-4">
                    <div>
                      <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{
                        t('settings.payment.readyToSettle') }}</div>
                    </div>
                    <div class="text-sm font-bold text-[#202224] dark:text-white">{{ formatCurrency(readyToSettleAmount)
                    }}</div>
                  </div>

                  <!-- Menunggu Kliring -->
                  <div class="flex items-center justify-between py-4">
                    <div>
                      <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{
                        t('settings.payment.pendingSettlement') }}</div>
                    </div>
                    <div class="text-sm font-bold text-[#202224] dark:text-white">{{
                      formatCurrency(pendingSettlementAmount) }}</div>
                  </div>

                  <!-- Rekening Bank Tujuan (Pengajuan & Persetujuan) -->
                  <div class="flex items-center justify-between py-4">
                    <div>
                      <div class="text-sm font-normal text-[#1E293B] dark:text-white">{{
                        t('settings.payment.bankAccount') }}</div>
                      <div v-if="bankName && accountNumber" class="text-[#64748B] dark:text-[#94A3B8] mt-0.5">{{ bankName }} • {{ accountNumber }} • a.n.
                        {{ accountHolder }}</div>
                      <div v-else class="text-xs text-[#94A3B8] mt-0.5">
                        {{ t('settings.payment.noBankAccount') }}
                      </div>
                      <!-- Status Notifikasi jika Rekening Menunggu Persetujuan -->
                      <div v-if="isBankPendingApproval"
                        class="mt-1.5 text-xs text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                          </circle>
                          <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                          </path>
                        </svg>
                        <span>Pengajuan perubahan rekening ke {{ pendingBankInfo }} sedang ditinjau.</span>
                      </div>
                    </div>

                    <div>
                      <!-- Tombol Menunggu Persetujuan Rekening -->
                      <button v-if="isBankPendingApproval" type="button" disabled
                        class="px-3.5 py-2 rounded-xl text-xs font-bold bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-300/60 dark:border-amber-700/60 flex items-center gap-2 cursor-not-allowed opacity-90 shadow-2xs">
                        <svg class="w-3.5 h-3.5 animate-spin shrink-0" viewBox="0 0 24 24" fill="none">
                          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                          </circle>
                          <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                          </path>
                        </svg>
                        <span>{{ t('settings.branding.pendingApprovalBtn') }}</span>
                      </button>

                      <!-- Tombol Hubungkan / Ubah Rekening jika belum pending -->
                      <AppButton v-else variant="primary" size="sm" @click="openChangeModal">
                        {{ bankName && accountNumber ? t('settings.payment.requestBankChange') : 'Hubungkan Rekening' }}
                      </AppButton>
                    </div>
                  </div>
                </div>

                <!-- Settlement History -->
                <h3 class="text-sm font-bold text-[#202224] dark:text-white mt-6 mb-3">{{
                  t('settings.payment.historyTitle') }}</h3>
                <div class="overflow-x-auto rounded-lg border border-[#E2E8F0] dark:border-[#334155]">
                  <table class="w-full text-sm">
                    <thead>
                      <tr class="bg-[#F8FAFC] dark:bg-[#0F172A] text-[#64748B] dark:text-[#94A3B8]">
                        <th class="text-left px-4 py-2.5 font-normal">{{ t('settings.payment.settlementId') }}</th>
                        <th class="text-left px-4 py-2.5 font-normal">{{ t('settings.billing.date') }}</th>
                        <th class="text-left px-4 py-2.5 font-normal">{{ t('settings.payment.accountCol') }}</th>
                        <th class="text-right px-4 py-2.5 font-normal">{{ t('settings.payment.netAmount') }}</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0] dark:divide-[#334155]">
                      <tr v-if="settlements.length === 0">
                        <td colspan="4" class="px-4 py-6 text-center text-sm text-[#64748B] dark:text-[#94A3B8]">
                          {{ t('settings.payment.noSettlements') }}
                        </td>
                      </tr>
                      <tr v-else v-for="s in settlements" :key="s.id" class="text-[#202224] dark:text-white">
                        <td class="px-4 py-2.5 font-normal">{{ s.id }}</td>
                        <td class="px-4 py-2.5">{{ s.date }}</td>
                        <td class="px-4 py-2.5">{{ s.bank }}</td>
                        <td class="px-4 py-2.5 text-right font-normal">{{ formatCurrency(s.net) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- TAB: BILLING -->
              <div v-else-if="activeTab === 'billing'" class="space-y-6">
                <div>
                  <h2 class="text-sm font-bold text-[#1E293B] dark:text-white mb-1">{{ t('settings.billing.title') }}
                  </h2>
                  <p class="text-xs text-[#64748B] dark:text-[#94A3B8]">
                    {{ t('settings.billing.nextPaymentOn') }} <span
                      class="font-medium text-[#1E293B] dark:text-white">{{
                        nextBillingDate }}</span> {{ t('settings.billing.paidViaXendit') }}
                  </p>
                </div>

                <!-- 2 CARDS PRICING GRID (Sesuai Dokumentasi Pengembangan: Basic Plan & Pro Plan) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                  <!-- CARD 1: BASIC PLAN -->
                  <div :class="[
                    'rounded-2xl p-6 flex flex-col justify-between transition-all relative',
                    currentPlanCode === 'basic'
                      ? 'bg-white dark:bg-[#0F172A] border-2 border-[#4880FF] shadow-md'
                      : 'bg-[#F8FAFC]/70 dark:bg-[#0F172A]/40 border border-[#E2E8F0] dark:border-[#334155]'
                  ]">
                    <!-- Badge Aktif (Hanya jika aktif) -->
                    <div v-if="currentPlanCode === 'basic'" class="absolute -top-3 right-5">
                      <span class="bg-[#4880FF] text-white text-xs font-bold px-3 py-0.5 rounded-full shadow-xs">
                        {{ t('settings.billing.activePlan') }}
                      </span>
                    </div>

                    <div>
                      <h3 class="text-xl font-bold tracking-tight text-[#1E293B] dark:text-white">Basic Plan</h3>
                      <p class="text-xs text-[#64748B] dark:text-[#94A3B8] mt-1">
                        {{ t('settings.billing.basicDesc') }}
                      </p>

                      <!-- Price -->
                      <div class="mt-4">
                        <div class="text-2xl font-bold text-[#1E293B] dark:text-white">Rp 99.000</div>
                        <p class="text-xs text-[#64748B] dark:text-[#94A3B8] mt-0.5">{{ t('settings.billing.perMonth')
                          }}</p>
                      </div>

                      <!-- Capacity Grid (Tanpa stroke dan tanpa rounded lg) -->
                      <div class="grid grid-cols-3 gap-2 my-4 py-2 text-center">
                        <div>
                          <div class="text-sm font-bold text-[#1E293B] dark:text-white">1</div>
                          <div class="text-xs text-[#64748B] dark:text-[#94A3B8]">{{ t('settings.billing.colOutlet') }}</div>
                        </div>
                        <div>
                          <div class="text-sm font-bold text-[#1E293B] dark:text-white">20</div>
                          <div class="text-xs text-[#64748B] dark:text-[#94A3B8]">{{ t('settings.billing.colTables') }}</div>
                        </div>
                        <div>
                          <div class="text-sm font-bold text-[#1E293B] dark:text-white">3</div>
                          <div class="text-xs text-[#64748B] dark:text-[#94A3B8]">{{ t('settings.billing.colStaff') }}</div>
                        </div>
                      </div>

                      <!-- CTA Button -->
                      <div class="mb-5">
                        <button v-if="currentPlanCode === 'basic'" type="button"
                          class="w-full py-2.5 px-4 rounded-xl text-xs font-bold bg-[#4880FF] text-white transition-all text-center cursor-default shadow-xs">
                          {{ t('settings.billing.currentPlan') }}
                        </button>
                        <button v-else type="button" @click="changePlan('basic')"
                          class="w-full py-2.5 px-4 rounded-xl text-xs font-semibold border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#1E293B] text-[#1E293B] dark:text-white hover:bg-slate-100 dark:hover:bg-[#334155] transition-all text-center cursor-pointer">
                          {{ t('settings.billing.chooseBasic') }}
                        </button>
                      </div>

                      <!-- Features -->
                      <div class="border-t border-[#E2E8F0] dark:border-[#334155] pt-4 space-y-2.5">
                        <p class="text-xs font-semibold text-[#1E293B] dark:text-white mb-1">{{
                          t('settings.billing.featuresGot') }}</p>
                        <div class="flex items-start gap-2 text-xs"
                          :class="currentPlanCode === 'basic' ? 'text-[#1E293B] dark:text-slate-200' : 'text-[#64748B] dark:text-[#94A3B8]'">
                          <svg class="w-3.5 h-3.5 shrink-0 mt-0.5"
                            :class="currentPlanCode === 'basic' ? 'text-emerald-500' : 'text-[#94A3B8] dark:text-[#64748B]'"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                          </svg>
                          <span>{{ t('settings.billing.featMenuDigital') }}</span>
                        </div>
                        <div class="flex items-start gap-2 text-xs"
                          :class="currentPlanCode === 'basic' ? 'text-[#1E293B] dark:text-slate-200' : 'text-[#64748B] dark:text-[#94A3B8]'">
                          <svg class="w-3.5 h-3.5 shrink-0 mt-0.5"
                            :class="currentPlanCode === 'basic' ? 'text-emerald-500' : 'text-[#94A3B8] dark:text-[#64748B]'"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                          </svg>
                          <span>{{ t('settings.billing.featMaxTables') }}</span>
                        </div>
                        <div class="flex items-start gap-2 text-xs"
                          :class="currentPlanCode === 'basic' ? 'text-[#1E293B] dark:text-slate-200' : 'text-[#64748B] dark:text-[#94A3B8]'">
                          <svg class="w-3.5 h-3.5 shrink-0 mt-0.5"
                            :class="currentPlanCode === 'basic' ? 'text-emerald-500' : 'text-[#94A3B8] dark:text-[#64748B]'"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                          </svg>
                          <span>{{ t('settings.billing.featPosPrint') }}</span>
                        </div>
                        <div class="flex items-start gap-2 text-xs"
                          :class="currentPlanCode === 'basic' ? 'text-[#1E293B] dark:text-slate-200' : 'text-[#64748B] dark:text-[#94A3B8]'">
                          <svg class="w-3.5 h-3.5 shrink-0 mt-0.5"
                            :class="currentPlanCode === 'basic' ? 'text-emerald-500' : 'text-[#94A3B8] dark:text-[#64748B]'"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                          </svg>
                          <span>{{ t('settings.billing.featKdsRealtime') }}</span>
                        </div>
                        <div class="flex items-start gap-2 text-xs"
                          :class="currentPlanCode === 'basic' ? 'text-[#1E293B] dark:text-slate-200' : 'text-[#64748B] dark:text-[#94A3B8]'">
                          <svg class="w-3.5 h-3.5 shrink-0 mt-0.5"
                            :class="currentPlanCode === 'basic' ? 'text-emerald-500' : 'text-[#94A3B8] dark:text-[#64748B]'"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                          </svg>
                          <span>{{ t('settings.billing.featStaffLimit') }}</span>
                        </div>
                        <div class="flex items-start gap-2 text-xs"
                          :class="currentPlanCode === 'basic' ? 'text-[#1E293B] dark:text-slate-200' : 'text-[#64748B] dark:text-[#94A3B8]'">
                          <svg class="w-3.5 h-3.5 shrink-0 mt-0.5"
                            :class="currentPlanCode === 'basic' ? 'text-emerald-500' : 'text-[#94A3B8] dark:text-[#64748B]'"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                          </svg>
                          <span>{{ t('settings.billing.featBasicReports') }}</span>
                        </div>
                        <div class="flex items-start gap-2 text-xs"
                          :class="currentPlanCode === 'basic' ? 'text-[#1E293B] dark:text-slate-200' : 'text-[#64748B] dark:text-[#94A3B8]'">
                          <svg class="w-3.5 h-3.5 shrink-0 mt-0.5"
                            :class="currentPlanCode === 'basic' ? 'text-emerald-500' : 'text-[#94A3B8] dark:text-[#64748B]'"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                          </svg>
                          <span>{{ t('settings.billing.featCustomTaxService') }}</span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- CARD 2: PRO PLAN -->
                  <div :class="[
                    'rounded-2xl p-6 flex flex-col justify-between transition-all relative',
                    currentPlanCode === 'pro'
                      ? 'bg-white dark:bg-[#0F172A] border-2 border-[#4880FF] shadow-md'
                      : 'bg-[#F8FAFC]/70 dark:bg-[#0F172A]/40 border border-[#E2E8F0] dark:border-[#334155]'
                  ]">
                    <!-- Badge Aktif (Hanya jika aktif) -->
                    <div v-if="currentPlanCode === 'pro'" class="absolute -top-3 right-5">
                      <span class="bg-[#4880FF] text-white text-xs font-bold px-3 py-0.5 rounded-full shadow-xs">
                        {{ t('settings.billing.activePlan') }}
                      </span>
                    </div>

                    <div>
                      <h3 class="text-xl font-bold tracking-tight text-[#1E293B] dark:text-white">Pro Plan</h3>
                      <p class="text-xs text-[#64748B] dark:text-[#94A3B8] mt-1">
                        {{ t('settings.billing.proDesc') }}
                      </p>

                      <!-- Price -->
                      <div class="mt-4">
                        <div class="text-2xl font-bold text-[#1E293B] dark:text-white">Rp 199.000</div>
                        <p class="text-xs text-[#64748B] dark:text-[#94A3B8] mt-0.5">{{ t('settings.billing.perMonth')
                          }}</p>
                      </div>

                      <!-- Capacity Grid (Ikon Unlimited & tanpa stroke / rounded lg) -->
                      <div class="grid grid-cols-3 gap-2 my-4 py-2 text-center">
                        <div>
                          <div class="flex items-center justify-center h-5">
                            <svg class="w-4 h-4 text-[#1E293B] dark:text-white" viewBox="0 0 24 24" fill="none"
                              stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                              <path
                                d="M18.178 8c5.096 0 5.096 8 0 8-5.095 0-7.133-8-12.356-8-5.096 0-5.096 8 0 8 5.223 0 7.261-8 12.356-8z" />
                            </svg>
                          </div>
                          <div class="text-xs text-[#64748B] dark:text-[#94A3B8]">{{ t('settings.billing.proOutlet') }}</div>
                        </div>
                        <div>
                          <div class="flex items-center justify-center h-5">
                            <svg class="w-4 h-4 text-[#1E293B] dark:text-white" viewBox="0 0 24 24" fill="none"
                              stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                              <path
                                d="M18.178 8c5.096 0 5.096 8 0 8-5.095 0-7.133-8-12.356-8-5.096 0-5.096 8 0 8 5.223 0 7.261-8 12.356-8z" />
                            </svg>
                          </div>
                          <div class="text-xs text-[#64748B] dark:text-[#94A3B8]">{{ t('settings.billing.proTables') }}</div>
                        </div>
                        <div>
                          <div class="flex items-center justify-center h-5">
                            <svg class="w-4 h-4 text-[#1E293B] dark:text-white" viewBox="0 0 24 24" fill="none"
                              stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                              <path
                                d="M18.178 8c5.096 0 5.096 8 0 8-5.095 0-7.133-8-12.356-8-5.096 0-5.096 8 0 8 5.223 0 7.261-8 12.356-8z" />
                            </svg>
                          </div>
                          <div class="text-xs text-[#64748B] dark:text-[#94A3B8]">{{ t('settings.billing.proStaff') }}</div>
                        </div>
                      </div>

                      <!-- CTA Button -->
                      <div class="mb-5">
                        <button v-if="currentPlanCode === 'pro'" type="button"
                          class="w-full py-2.5 px-4 rounded-xl text-xs font-bold bg-[#4880FF] text-white transition-all text-center cursor-default shadow-xs">
                          {{ t('settings.billing.currentPlan') }}
                        </button>
                        <button v-else type="button" @click="changePlan('pro')"
                          class="w-full py-2.5 px-4 rounded-xl text-xs font-semibold border border-[#4880FF] text-[#4880FF] hover:bg-[#4880FF] hover:text-white dark:hover:text-white transition-all text-center cursor-pointer">
                          {{ t('settings.billing.upgradePro') }}
                        </button>
                      </div>

                      <!-- Features -->
                      <div class="border-t border-[#E2E8F0] dark:border-[#334155] pt-4 space-y-2.5">
                        <p class="text-xs font-semibold text-[#1E293B] dark:text-white mb-1">{{
                          t('settings.billing.allBasicFeatures') }}</p>
                        <div class="flex items-start gap-2 text-xs"
                          :class="currentPlanCode === 'pro' ? 'text-[#1E293B] dark:text-slate-200' : 'text-[#64748B] dark:text-[#94A3B8]'">
                          <svg class="w-3.5 h-3.5 shrink-0 mt-0.5"
                            :class="currentPlanCode === 'pro' ? 'text-[#4880FF]' : 'text-[#94A3B8] dark:text-[#64748B]'"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                          </svg>
                          <span>{{ t('settings.billing.featMultiOutlet') }}</span>
                        </div>
                        <div class="flex items-start gap-2 text-xs"
                          :class="currentPlanCode === 'pro' ? 'text-[#1E293B] dark:text-slate-200' : 'text-[#64748B] dark:text-[#94A3B8]'">
                          <svg class="w-3.5 h-3.5 shrink-0 mt-0.5"
                            :class="currentPlanCode === 'pro' ? 'text-[#4880FF]' : 'text-[#94A3B8] dark:text-[#64748B]'"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                          </svg>
                          <span>{{ t('settings.billing.featUnlimitedTables') }}</span>
                        </div>
                        <div class="flex items-start gap-2 text-xs"
                          :class="currentPlanCode === 'pro' ? 'text-[#1E293B] dark:text-slate-200' : 'text-[#64748B] dark:text-[#94A3B8]'">
                          <svg class="w-3.5 h-3.5 shrink-0 mt-0.5"
                            :class="currentPlanCode === 'pro' ? 'text-[#4880FF]' : 'text-[#94A3B8] dark:text-[#64748B]'"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                          </svg>
                          <span>{{ t('settings.billing.featUnlimitedStaff') }}</span>
                        </div>
                        <div class="flex items-start gap-2 text-xs"
                          :class="currentPlanCode === 'pro' ? 'text-[#1E293B] dark:text-slate-200' : 'text-[#64748B] dark:text-[#94A3B8]'">
                          <svg class="w-3.5 h-3.5 shrink-0 mt-0.5"
                            :class="currentPlanCode === 'pro' ? 'text-[#4880FF]' : 'text-[#94A3B8] dark:text-[#64748B]'"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                          </svg>
                          <span>{{ t('settings.billing.featStockRecipe') }}</span>
                        </div>
                        <div class="flex items-start gap-2 text-xs"
                          :class="currentPlanCode === 'pro' ? 'text-[#1E293B] dark:text-slate-200' : 'text-[#64748B] dark:text-[#94A3B8]'">
                          <svg class="w-3.5 h-3.5 shrink-0 mt-0.5"
                            :class="currentPlanCode === 'pro' ? 'text-[#4880FF]' : 'text-[#94A3B8] dark:text-[#64748B]'"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                          </svg>
                          <span>{{ t('settings.billing.featAnalytics') }}</span>
                        </div>
                        <div class="flex items-start gap-2 text-xs"
                          :class="currentPlanCode === 'pro' ? 'text-[#1E293B] dark:text-slate-200' : 'text-[#64748B] dark:text-[#94A3B8]'">
                          <svg class="w-3.5 h-3.5 shrink-0 mt-0.5"
                            :class="currentPlanCode === 'pro' ? 'text-[#4880FF]' : 'text-[#94A3B8] dark:text-[#64748B]'"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                          </svg>
                          <span>{{ t('settings.billing.featWaEmailNotif') }}</span>
                        </div>
                        <div class="flex items-start gap-2 text-xs"
                          :class="currentPlanCode === 'pro' ? 'text-[#1E293B] dark:text-slate-200' : 'text-[#64748B] dark:text-[#94A3B8]'">
                          <svg class="w-3.5 h-3.5 shrink-0 mt-0.5"
                            :class="currentPlanCode === 'pro' ? 'text-[#4880FF]' : 'text-[#94A3B8] dark:text-[#64748B]'"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                          </svg>
                          <span>{{ t('settings.billing.featPrioritySupport') }}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Invoice History -->
                <h3 class="text-sm font-bold text-[#202224] dark:text-white mt-6 mb-3">{{
                  t('settings.billing.historyTitle') }}</h3>
                <div class="overflow-x-auto rounded-lg border border-[#E2E8F0] dark:border-[#334155]">
                  <table class="w-full text-sm">
                    <thead>
                      <tr class="bg-[#F8FAFC] dark:bg-[#0F172A] text-[#64748B] dark:text-[#94A3B8]">
                        <th class="text-left px-4 py-2.5 font-normal">{{ t('settings.billing.invoiceNo') }}</th>
                        <th class="text-left px-4 py-2.5 font-normal">{{ t('settings.billing.date') }}</th>
                        <th class="text-left px-4 py-2.5 font-normal">{{ t('settings.billing.period') }}</th>
                        <th class="text-right px-4 py-2.5 font-normal">{{ t('settings.billing.amount') }}</th>
                        <th class="text-center px-4 py-2.5 font-normal">{{ t('settings.billing.action') }}</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0] dark:divide-[#334155]">
                      <tr v-if="invoices.length === 0">
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-[#64748B] dark:text-[#94A3B8]">
                          {{ t('settings.billing.noInvoices') }}
                        </td>
                      </tr>
                      <tr v-else v-for="inv in invoices" :key="inv.id" class="text-[#202224] dark:text-white">
                        <td class="px-4 py-2.5 font-normal">{{ inv.id }}</td>
                        <td class="px-4 py-2.5">{{ inv.date }}</td>
                        <td class="px-4 py-2.5">{{ inv.period }}</td>
                        <td class="px-4 py-2.5 text-right font-normal">{{ formatCurrency(inv.amount) }}</td>
                        <td class="px-4 py-2.5 text-center">
                          <button type="button"
                            class="text-[#4880FF] hover:text-blue-600 cursor-pointer flex items-center justify-center mx-auto"
                            :title="t('settings.billing.download')">
                            <AppIcon name="download" :size="16" />
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
    </Transition>



    <!-- SUB-MODAL: AJUKAN GANTI REKENING -->
    <AppModal v-model="isChangeModalOpen" :title="t('settings.submodal.bankTitle')" maxWidth="md">
      <div class="space-y-3 text-sm">
        <p class="text-[#64748B] dark:text-[#94A3B8]">
          {{ t('settings.submodal.bankDesc') }}
        </p>
        <div>
          <label class="block mb-1 font-normal text-[#64748B] dark:text-[#94A3B8]">{{ t('settings.submodal.bankName')
            }}</label>
          <input v-model="reqBankName" type="text"
            class="w-full px-3 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-[#202224] dark:text-white focus:outline-none" />
        </div>
        <div>
          <label class="block mb-1 font-normal text-[#64748B] dark:text-[#94A3B8]">{{ t('settings.submodal.bankNumber')
            }}</label>
          <input v-model="reqAccountNumber" type="text"
            class="w-full px-3 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-[#202224] dark:text-white focus:outline-none" />
        </div>
        <div>
          <label class="block mb-1 font-normal text-[#64748B] dark:text-[#94A3B8]">{{ t('settings.submodal.bankHolder')
            }}</label>
          <input v-model="reqAccountHolder" type="text"
            class="w-full px-3 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-[#202224] dark:text-white focus:outline-none" />
        </div>
        <div>
          <div class="flex items-center justify-between mb-1">
            <label class="font-normal text-[#1E293B] dark:text-white">{{ t('settings.submodal.bankReason') }} <span
                class="text-rose-500">*</span></label>
            <span class="text-xs text-rose-500 font-medium">{{ t('settings.submodal.addressRequired') }}</span>
          </div>
          <textarea v-model="reqNotes" rows="2" placeholder="Pembaruan rekening operasional resmi outlet restoran"
            class="w-full px-3 py-2 rounded-lg border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#0F172A] text-[#202224] dark:text-white resize-none focus:outline-none focus:border-[#4880FF]" />
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" size="sm" @click="isChangeModalOpen = false">{{ t('settings.account.cancel') }}
        </AppButton>
        <AppButton variant="primary" size="sm" :loading="isSubmittingRequest" @click="handleSubmitChangeRequest">
          {{ t('settings.submodal.submitRequest') }}
        </AppButton>
      </template>
    </AppModal>

    <!-- SUB-MODAL: LOGOUT CONFIRM -->
    <AppModal v-model="isLogoutConfirmOpen" :title="t('settings.submodal.logoutTitle')" maxWidth="sm">
      <p class="text-sm text-[#64748B] dark:text-[#94A3B8]">
        {{ t('settings.submodal.logoutDesc') }}
      </p>

      <template #footer>
        <AppButton variant="secondary" size="md" @click="isLogoutConfirmOpen = false">{{ t('settings.account.cancel') }}
        </AppButton>
        <AppButton variant="danger" size="md" @click="handleLogout">{{ t('settings.submodal.yesLogout') }}</AppButton>
      </template>
    </AppModal>


  </Teleport>
</template>

<style scoped>
/* Modal overlay backdrop: fade in & fade out */
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.2s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

/* Modal dialog card pop in/out */
.modal-fade-enter-active .modal-dialog-content,
.modal-fade-leave-active .modal-dialog-content {
  transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.2s ease;
}

.modal-fade-enter-from .modal-dialog-content,
.modal-fade-leave-to .modal-dialog-content {
  opacity: 0;
  transform: scale(0.96);
}

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
