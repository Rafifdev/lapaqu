import { disconnectEcho } from '@/services/echo'
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { User, UserRole } from '@/types'
import apiClient from '@/services/api'

export const useAuthStore = defineStore('auth', () => {
  const getInitialUser = (): User => {
    try {
      const saved = localStorage.getItem('lapaqu_user')
      if (saved) return JSON.parse(saved)
    } catch {
      // ignore
    }
    return {
      id: 'usr-owner-001',
      name: 'Budi Santoso (Owner)',
      email: 'owner@kopisenopati.id',
      role: 'owner',
      tenantId: '',
      outletId: '',
      avatarUrl: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80',
      is2FAEnabled: false,
    }
  }

  const currentUser = ref<User | null>(getInitialUser())
  const token = ref<string | null>(localStorage.getItem('lapaqu_token'))

  const isAuthenticated = computed(() => !!token.value)
  const isOwner = computed(() => currentUser.value?.role === 'owner')
  const isKasir = computed(() => currentUser.value?.role === 'kasir' || currentUser.value?.role === 'owner')
  const isKitchen = computed(() => currentUser.value?.role === 'kitchen_staff' || currentUser.value?.role === 'owner')

  const setAuthData = (newToken: string, user: any) => {
    token.value = newToken
    localStorage.setItem('lapaqu_token', newToken)
    if (user) {
      currentUser.value = {
        id: user.id,
        name: user.name,
        email: user.email,
        role: (user.roles && user.roles[0]) ? user.roles[0] : (user.role || 'owner'),
        tenantId: user.tenant_id || user.tenantId,
        outletId: user.outlet_id || user.outletId,
        avatarUrl: user.avatar_url || currentUser.value?.avatarUrl,
        is2FAEnabled: !!user.is_2fa_enabled,
      }
      try {
        localStorage.setItem('lapaqu_user', JSON.stringify(currentUser.value))
      } catch {
        // ignore
      }
    }
  }

  const login = async (email: string, password: string): Promise<{ success: boolean; message?: string; require2FA?: boolean }> => {
    try {
      const res = await apiClient.post('/auth/login', { email, password })
      const data = res.data

      if (data.require_2fa) {
        return { success: true, require2FA: true }
      }

      if (data.token && data.user) {
        setAuthData(data.token, data.user)
        return { success: true }
      }

      return { success: false, message: 'Format response login tidak valid.' }
    } catch (err: any) {
      const message = err?.response?.data?.message || err?.message || 'Login gagal.'
      return { success: false, message }
    }
  }

  const ensureToken = async (preferredRole?: UserRole): Promise<string | null> => {
    if (token.value) return token.value
    const role = preferredRole || currentUser.value?.role || 'owner'
    await switchRoleAndLogin(role)
    return token.value
  }

  const switchRoleAndLogin = async (role: UserRole) => {
    const creds: Record<string, { email: string; pass: string }> = {
      owner: { email: 'owner@kopisenopati.id', pass: 'RahasiaKopi123!' },
      kasir: { email: 'kasir@kopisenopati.id', pass: 'RahasiaKopi123!' },
      kitchen_staff: { email: 'kitchen@kopisenopati.id', pass: 'RahasiaKopi123!' },
      manager: { email: 'owner@kopisenopati.id', pass: 'RahasiaKopi123!' },
    }
    const c = creds[role] || creds['owner']
    try {
      const res = await login(c.email, c.pass)
      if (res.success) return
    } catch (e) {
      console.warn('[authStore] Real login failed, using setRole fallback:', e)
    }
    setRole(role)
  }

  const setRole = (role: UserRole) => {
    if (!currentUser.value) {
      currentUser.value = {
        id: 'usr-001',
        name: 'User',
        email: 'user@kopisenopati.id',
        role: role,
        tenantId: 'tenant-kopi-senopati',
        outletId: 'outlet-001',
        avatarUrl: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80',
        is2FAEnabled: false,
      }
    }
    currentUser.value.role = role
    if (role === 'kasir') {
      currentUser.value.name = 'Siti Kasir'
      currentUser.value.email = 'kasir@kopisenopati.id'
    } else if (role === 'kitchen_staff') {
      currentUser.value.name = 'Chef Arnold (Kitchen)'
      currentUser.value.email = 'kitchen@kopisenopati.id'
    } else if (role === 'owner') {
      currentUser.value.name = 'Budi Santoso (Owner)'
      currentUser.value.email = 'owner@kopisenopati.id'
    }
    try {
      localStorage.setItem('lapaqu_user', JSON.stringify(currentUser.value))
    } catch {
      // ignore
    }
  }

  const logout = () => {
    disconnectEcho()
    token.value = null
    currentUser.value = null
    localStorage.removeItem('lapaqu_token')
    localStorage.removeItem('lapaqu_user')
  }

  return {
    currentUser,
    token,
    isAuthenticated,
    isOwner,
    isKasir,
    isKitchen,
    login,
    ensureToken,
    setAuthData,
    setRole,
    switchRoleAndLogin,
    logout,
  }
})