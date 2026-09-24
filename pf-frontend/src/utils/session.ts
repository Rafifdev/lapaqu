export const SESSION_EXPIRES_KEY = 'lapaqu_session_expires_at'

/**
 * Menghitung timestamp batas kedaluwarsa sesi:
 * - isPersistent = true (Ingat Saya aktif): berlaku 30 hari ke depan
 * - isPersistent = false: berlaku sampai pukul 23:59:00 hari ini
 */
export const calculateSessionExpiration = (isPersistent = false): number => {
  if (isPersistent) {
    return Date.now() + 30 * 24 * 60 * 60 * 1000
  }
  const now = new Date()
  const exp = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 23, 59, 0, 0)
  // Jika saat login sudah lewat jam 23:59:00, berlaku sampai jam 23:59:00 besok
  if (now.getTime() >= exp.getTime()) {
    exp.setDate(exp.getDate() + 1)
  }
  return exp.getTime()
}

/**
 * Menyimpan batas kedaluwarsa sesi baru
 */
export const initSessionExpiration = (isTemporary = false, isPersistent = false): void => {
  const exp = calculateSessionExpiration(isPersistent)
  if (isTemporary) {
    sessionStorage.setItem(SESSION_EXPIRES_KEY, String(exp))
    localStorage.removeItem(SESSION_EXPIRES_KEY)
  } else {
    localStorage.setItem(SESSION_EXPIRES_KEY, String(exp))
    sessionStorage.removeItem(SESSION_EXPIRES_KEY)
  }
}

/**
 * Memeriksa apakah sesi saat ini sudah kedaluwarsa
 */
export const isSessionExpired = (): boolean => {
  const token = sessionStorage.getItem('lapaqu_token') || localStorage.getItem('lapaqu_token')
  if (!token) return true

  const expStr = sessionStorage.getItem(SESSION_EXPIRES_KEY) || localStorage.getItem(SESSION_EXPIRES_KEY)
  if (!expStr) {
    return true
  }

  const expTime = Number(expStr)
  return isNaN(expTime) || Date.now() >= expTime
}

/**
 * Membersihkan data sesi login
 */
export const clearSessionStorage = (): void => {
  sessionStorage.removeItem('lapaqu_token')
  sessionStorage.removeItem('lapaqu_user')
  sessionStorage.removeItem(SESSION_EXPIRES_KEY)
  localStorage.removeItem('lapaqu_token')
  localStorage.removeItem('lapaqu_user')
  localStorage.removeItem(SESSION_EXPIRES_KEY)
}
