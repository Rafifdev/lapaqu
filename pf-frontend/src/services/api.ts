import axios from 'axios'
import { isSessionExpired, clearSessionStorage } from '@/utils/session'
import { apiCache } from './apiCache'

export const API_BASE = import.meta.env.VITE_API_BASE_URL || '/api'

export const apiClient = axios.create({
  baseURL: API_BASE,
  withCredentials: true,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
  timeout: 10000,
})

// Request Interceptor: Otomatis lampirkan Bearer token & Device token jika tersedia & periksa sesi 23.59
apiClient.interceptors.request.use(
  (config) => {
    const isAuthOrPublic =
      config.url?.includes('/auth/') ||
      config.url?.includes('/public/') ||
      config.url?.includes('/device/') ||
      config.url?.includes('/onboarding/') ||
      config.url?.includes('/tenant/')

    const token = sessionStorage.getItem('lapaqu_token') || localStorage.getItem('lapaqu_token')

    if (!isAuthOrPublic && token && isSessionExpired()) {
      clearSessionStorage()
      if (typeof window !== 'undefined' && !window.location.pathname.startsWith('/auth')) {
        window.location.href = '/auth/login'
      }
      return Promise.reject(new Error('Sesi login telah berakhir.'))
    }
    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`
    }

    const deviceToken = localStorage.getItem('lapaqu_device_token')
    if (deviceToken && config.headers) {
      config.headers['X-Device-Token'] = deviceToken
    }

    return config
  },
  (error) => Promise.reject(error)
)

// Response Interceptor: Cache GET responses, tangani error 401 & flush cache pada mutasi
apiClient.interceptors.response.use(
  (response) => {
    const config = response.config
    const method = (config.method || 'get').toLowerCase()

    if (method === 'get' && config.url) {
      const paramStr = config.params ? JSON.stringify(config.params) : ''
      const cacheKey = `${config.url}_${paramStr}`
      apiCache.set(cacheKey, response.data)
    } else if (['post', 'put', 'patch', 'delete'].includes(method)) {
      apiCache.clear()
    }
    return response
  },
  async (error) => {
    const originalRequest = error.config
    if (error.response?.status === 401 && !originalRequest?.url?.includes('/auth/login') && !originalRequest?.url?.includes('/device/login-pin')) {
      sessionStorage.removeItem('lapaqu_token')
      sessionStorage.removeItem('lapaqu_user')
      localStorage.removeItem('lapaqu_token')
      localStorage.removeItem('lapaqu_user')
      apiCache.clear()

      if (typeof window !== 'undefined') {
        const currentPath = window.location.pathname
        const isPublicOrOrder = currentPath.startsWith('/order') || currentPath.startsWith('/auth')
        const isPosOrKds = currentPath.startsWith('/pos') || currentPath.startsWith('/kds')

        if (!isPublicOrOrder) {
          if (isPosOrKds && localStorage.getItem('lapaqu_device_token')) {
            window.location.href = isPosOrKds ? currentPath : '/auth/login'
          } else {
            window.location.href = `/auth/login?redirect=${encodeURIComponent(currentPath)}`
          }
        }
      }
    }
    return Promise.reject(error)
  }
)

export { apiCache }
export default apiClient
