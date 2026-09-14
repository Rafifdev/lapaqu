import axios from 'axios'

export const API_BASE = import.meta.env.VITE_API_BASE_URL || '/api'

export const apiClient = axios.create({
  baseURL: API_BASE,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
  timeout: 10000,
})

const resolveDefaultCredentials = () => {
  try {
    const userStr = localStorage.getItem('lapaqu_user')
    const user = userStr ? JSON.parse(userStr) : null
    const role = user?.role
    const path = typeof window !== 'undefined' ? window.location.pathname : ''
    if (role === 'kasir' || path.startsWith('/pos')) {
      return { email: 'kasir@kopisenopati.id', password: 'RahasiaKopi123!' }
    }
    if (role === 'kitchen_staff' || path.startsWith('/kds')) {
      return { email: 'kitchen@kopisenopati.id', password: 'RahasiaKopi123!' }
    }
  } catch (e) {}
  return { email: 'owner@kopisenopati.id', password: 'RahasiaKopi123!' }
}

let acquirePromise: Promise<string | null> | null = null

export const acquireToken = async (): Promise<string | null> => {
  if (acquirePromise) return acquirePromise
  acquirePromise = (async () => {
    try {
      const creds = resolveDefaultCredentials()
      const res = await axios.post(`${API_BASE}/auth/login`, creds)
      if (res.data?.token) {
        const token = res.data.token
        localStorage.setItem('lapaqu_token', token)
        if (res.data.user) {
          localStorage.setItem('lapaqu_user', JSON.stringify({
            id: res.data.user.id,
            name: res.data.user.name,
            email: res.data.user.email,
            role: (res.data.user.roles && res.data.user.roles[0]) ? res.data.user.roles[0] : (res.data.user.role || 'owner'),
            tenantId: res.data.user.tenant_id,
            outletId: res.data.user.outlet_id,
          }))
        }
        return token
      }
    } catch (e) {
      console.warn('[apiClient] Auto acquire token failed:', e)
    } finally {
      acquirePromise = null
    }
    return null
  })()
  return acquirePromise
}

// Request Interceptor: Otomatis lampirkan Bearer token jika tersedia, atau auto-login jika belum ada token
apiClient.interceptors.request.use(
  async (config) => {
    let token = localStorage.getItem('lapaqu_token')
    const isPublicOrAuth = config.url?.includes('/auth/') || config.url?.includes('/public/')
    if (!token && !isPublicOrAuth) {
      token = await acquireToken()
    }
    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => Promise.reject(error)
)

// Response Interceptor: Penanganan error 401 dengan auto-refresh token
apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config
    if (error.response?.status === 401 && originalRequest && !originalRequest._retry && !originalRequest.url?.includes('/auth/')) {
      originalRequest._retry = true
      const newToken = await acquireToken()
      if (newToken) {
        if (!originalRequest.headers) originalRequest.headers = {}
        originalRequest.headers.Authorization = `Bearer ${newToken}`
        return apiClient(originalRequest)
      }
    }
    return Promise.reject(error)
  }
)

export default apiClient
