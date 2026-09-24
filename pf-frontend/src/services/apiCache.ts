const memoryCache = new Map<string, { data: any; timestamp: number }>()

export const apiCache = {
  get(key: string, maxAgeMs = 10 * 60 * 1000): any | null {
    // 1. Check memory cache
    const mem = memoryCache.get(key)
    if (mem && Date.now() - mem.timestamp < maxAgeMs) {
      return mem.data
    }

    // 2. Check sessionStorage
    try {
      const stored = sessionStorage.getItem(`lapaqu_swr_${key}`)
      if (stored) {
        const parsed = JSON.parse(stored)
        if (Date.now() - parsed.timestamp < maxAgeMs) {
          memoryCache.set(key, parsed)
          return parsed.data
        }
      }
    } catch {
      // ignore
    }
    return null
  },

  set(key: string, data: any): void {
    if (!key || data === undefined) return
    const entry = { data, timestamp: Date.now() }
    memoryCache.set(key, entry)
    try {
      sessionStorage.setItem(`lapaqu_swr_${key}`, JSON.stringify(entry))
    } catch {
      // Storage quota or error safe guard
    }
  },

  clear(): void {
    memoryCache.clear()
    try {
      const keys = Object.keys(sessionStorage)
      for (const k of keys) {
        if (k.startsWith('lapaqu_swr_')) {
          sessionStorage.removeItem(k)
        }
      }
    } catch {
      // ignore
    }
  },

  invalidate(pattern?: string | RegExp): void {
    if (!pattern) {
      this.clear()
      return
    }
    for (const k of memoryCache.keys()) {
      if (typeof pattern === 'string' ? k.includes(pattern) : pattern.test(k)) {
        memoryCache.delete(k)
      }
    }
    try {
      const keys = Object.keys(sessionStorage)
      for (const k of keys) {
        if (k.startsWith('lapaqu_swr_')) {
          const rawKey = k.replace('lapaqu_swr_', '')
          if (typeof pattern === 'string' ? rawKey.includes(pattern) : pattern.test(rawKey)) {
            sessionStorage.removeItem(k)
          }
        }
      }
    } catch {
      // ignore
    }
  },
}

export default apiCache
