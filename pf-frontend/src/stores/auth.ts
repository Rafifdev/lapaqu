import { disconnectEcho } from '@/services/echo'
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { User, UserRole } from '@/types'
import apiClient from '@/services/api'
import {
  isSessionExpired,
  initSessionExpiration,
  clearSessionStorage,
} from '@/utils/session'

export const useAuthStore = defineStore('auth', () => {
  const getInitialUser = (): User | null => {
    try {
      // Periksa apakah sesi sebelumnya sudah kedaluwarsa (melewati jam 23.59)
      if (isSessionExpired()) {
        clearSessionStorage()
        return null
      }

      const token = sessionStorage.getItem('lapaqu_token') || localStorage.getItem('lapaqu_token')
      const saved = sessionStorage.getItem('lapaqu_user') || localStorage.getItem('lapaqu_user')
      if (token && saved) {
        return JSON.parse(saved)
      }
    } catch {
      // ignore
    }
    return null
  }

  const currentUser = ref<User | null>(getInitialUser())
  const token = ref<string | null>(isSessionExpired() ? null : (sessionStorage.getItem('lapaqu_token') || localStorage.getItem('lapaqu_token')))
  const availableOutlets = ref<any[]>(JSON.parse(localStorage.getItem('lapaqu_available_outlets') || '[]'))

  const isAuthenticated = computed(() => !!token.value && !!currentUser.value)
  const isOwner = computed(() => currentUser.value?.role === 'owner' || currentUser.value?.role === 'superadmin')
  const isStoreManager = computed(() => currentUser.value?.role === 'store_manager')
  const isKasir = computed(() => currentUser.value?.role === 'kasir' || isOwner.value || isStoreManager.value)
  const isKitchen = computed(() => currentUser.value?.role === 'kitchen_staff' || isOwner.value || isStoreManager.value)

  let sessionCheckTimer: any = null

  const setAuthData = (newToken: string, user: any, outlets?: any[], rememberMe = false) => {
    if (outlets && Array.isArray(outlets)) {
      availableOutlets.value = outlets
      localStorage.setItem('lapaqu_available_outlets', JSON.stringify(outlets))
      if (user?.outlet_id || user?.outletId) {
        const uOutletId = user?.outlet_id || user?.outletId
        const match = outlets.find((o: any) => o.id === uOutletId)
        localStorage.setItem('lapaqu_outlet_id', uOutletId)
        if (match?.name) localStorage.setItem('lapaqu_outlet_name', match.name)
      } else if (outlets.length === 1) {
        localStorage.setItem('lapaqu_outlet_id', outlets[0].id)
        localStorage.setItem('lapaqu_outlet_name', outlets[0].name)
      } else if (!localStorage.getItem('lapaqu_outlet_id') && outlets.length > 0) {
        const mainOutlet = outlets.find((o: any) => o.is_main) || outlets[0]
        localStorage.setItem('lapaqu_outlet_id', mainOutlet.id)
        localStorage.setItem('lapaqu_outlet_name', mainOutlet.name)
      }
    }
    token.value = newToken
    const userRole = (user && user.roles && user.roles[0]) ? user.roles[0] : (user?.role || 'owner')
    const isStaffSession = ['kasir', 'kitchen_staff', 'store_manager'].includes(userRole)
    const isTemporary = isStaffSession && !rememberMe

    if (isTemporary) {
      // Keamanan Sesi Staff: Simpan di Memory (Pinia) & sessionStorage saja jika tidak dicentang Ingat Saya
      sessionStorage.setItem('lapaqu_token', newToken)
      localStorage.removeItem('lapaqu_token')
      initSessionExpiration(true, false)
    } else {
      localStorage.setItem('lapaqu_token', newToken)
      sessionStorage.removeItem('lapaqu_token')
      initSessionExpiration(false, rememberMe)
    }

    if (user) {
      currentUser.value = {
        id: user.id,
        name: user.name,
        email: user.email,
        role: userRole,
        tenantId: user.tenant_id || user.tenantId,
        outletId: user.outlet_id || user.outletId,
        avatarUrl: user.avatar_url || currentUser.value?.avatarUrl,
        is2FAEnabled: !!user.is_2fa_enabled,
      }
      try {
        if (isTemporary) {
          sessionStorage.setItem('lapaqu_user', JSON.stringify(currentUser.value))
          localStorage.removeItem('lapaqu_user')
        } else {
          localStorage.setItem('lapaqu_user', JSON.stringify(currentUser.value))
          sessionStorage.removeItem('lapaqu_user')
        }
      } catch {
        // ignore
      }
    }

    startSessionMonitoring()
  }

  const login = async (email: string, password: string, rememberMe = false): Promise<{ success: boolean; message?: string; require2FA?: boolean; outlets?: any[] }> => {
    try {
      const res = await apiClient.post('/auth/login', {
        email,
        password,
        remember: rememberMe,
        remember_me: rememberMe,
      })
      const data = res.data

      if (data.require_2fa) {
        return { success: true, require2FA: true }
      }

      if (data.token && data.user) {
        if (data.tenant?.name) {
          localStorage.setItem('lapaqu_tenant_name', data.tenant.name)
        }
        if (data.outlets && Array.isArray(data.outlets)) {
          localStorage.setItem('lapaqu_available_outlets', JSON.stringify(data.outlets))
          if (data.user?.outlet_id || data.user?.outletId) {
            const uOutletId = data.user?.outlet_id || data.user?.outletId
            const match = data.outlets.find((o: any) => o.id === uOutletId)
            localStorage.setItem('lapaqu_outlet_id', uOutletId)
            if (match?.name) localStorage.setItem('lapaqu_outlet_name', match.name)
          } else if (data.outlets.length === 1) {
            localStorage.setItem('lapaqu_outlet_id', data.outlets[0].id)
            localStorage.setItem('lapaqu_outlet_name', data.outlets[0].name)
          } else if (!localStorage.getItem('lapaqu_outlet_id') && data.outlets.length > 0) {
            const mainOutlet = data.outlets.find((o: any) => o.is_main) || data.outlets[0]
            localStorage.setItem('lapaqu_outlet_id', mainOutlet.id)
            localStorage.setItem('lapaqu_outlet_name', mainOutlet.name)
          }
        }
        setAuthData(data.token, data.user, data.outlets, rememberMe)
        return { success: true, outlets: data.outlets || [] }
      }

      return { success: false, message: 'Format response login tidak valid.' }

    } catch (err: any) {
      const message = err?.response?.data?.message || err?.message || 'Kombinasi email dan password tidak valid.'
      return {
    success: false, message }
    }
  }

  const ensureToken = async (): Promise<string | null> => {
    if (isSessionExpired()) {
      await logout(true)
      return null
    }
    return token.value
  }

  const switchRoleAndLogin = async (role: UserRole) => {
    const creds: Record<string, { email: string; pass: string }> = {
      owner: { email: 'owner@kopisenopati.id', pass: 'RahasiaKopi123!' },
      kasir: { email: 'kasir@kopisenopati.id', pass: 'RahasiaKopi123!' },
      kitchen_staff: { email: 'kitchen@kopisenopati.id', pass: 'RahasiaKopi123!' },
      superadmin: { email: 'owner@kopisenopati.id', pass: 'RahasiaKopi123!' },
    }
    const c = creds[role] || creds['owner']
    const res = await login(c.email, c.pass)
    return res
  }

  const logout = async (isAutoExpired = false) => {
    try {
      if (token.value && !isAutoExpired) {
        await apiClient.post('/auth/logout').catch(() => {})
      }
    } finally {
      disconnectEcho()
      token.value = null
      currentUser.value = null
      clearSessionStorage()
      try {
        sessionStorage.clear()
      } catch (e) {}

      if (sessionCheckTimer) {
        clearInterval(sessionCheckTimer)
        sessionCheckTimer = null
      }
    }
  }

  // Monitor realtime: otomatis logout di jam 23.59 jika user lupa logout
  const startSessionMonitoring = (onExpired?: () => void) => {
    if (sessionCheckTimer) clearInterval(sessionCheckTimer)

    sessionCheckTimer = setInterval(async () => {
      if (token.value && isSessionExpired()) {
        await logout(true)
        if (onExpired) {
          onExpired()
        } else if (typeof window !== 'undefined' && !window.location.pathname.startsWith('/auth')) {
          window.location.href = '/auth/login'
        }
      }
    }, 10000) // Cek setiap 10 detik
  }

  // Jika sudah ada sesi aktif saat app dimuat, langsung nyalakan monitor
  if (token.value && !isSessionExpired()) {
    startSessionMonitoring()
  }

  return {
    availableOutlets,
    currentUser,
    token,
    isAuthenticated,
    isOwner,
    isStoreManager,
    isKasir,
    isKitchen,
    login,
    ensureToken,
    setAuthData,
    switchRoleAndLogin,
    logout,
    startSessionMonitoring,
  }
})
