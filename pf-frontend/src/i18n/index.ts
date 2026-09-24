import { ref, computed } from 'vue'
import type { SupportedLocale } from './types'
import dashboardId from './locales/dashboard/id'
import dashboardEn from './locales/dashboard/en'
import posKdsId from './locales/pos_kds/id'
import posKdsEn from './locales/pos_kds/en'
import customerId from './locales/customer/id'
import customerEn from './locales/customer/en'

export * from './types'

// Helper function to resolve nested keys like 'sidebar.overview'
function resolveKey(obj: any, path: string): string {
  const parts = path.split('.')
  let current = obj
  for (const part of parts) {
    if (current && typeof current === 'object' && part in current) {
      current = current[part]
    } else {
      return path // fallback to key itself
    }
  }
  return typeof current === 'string' ? current : path
}

// -------------------------------------------------------------
// 1. DASHBOARD I18N
// -------------------------------------------------------------
const dashboardLocale = ref<SupportedLocale>(
  (localStorage.getItem('lapaqu_lang_dashboard') as SupportedLocale) || 
  (localStorage.getItem('lapaqu_language') as SupportedLocale) || 
  'id'
)

const dashboardMessages: Record<SupportedLocale, any> = {
  id: dashboardId,
  en: dashboardEn,
}

export function useDashboardI18n() {
  const t = (
    key: string,
    fallbackOrParams?: string | Record<string, string | number>,
    params?: Record<string, string | number>
  ): string => {
    const fallback = typeof fallbackOrParams === 'string' ? fallbackOrParams : undefined
    const p = typeof fallbackOrParams === 'object' ? fallbackOrParams : params
    const activeDict = dashboardMessages[dashboardLocale.value] || dashboardMessages.id
    let raw = resolveKey(activeDict, key)
    if (raw === key && dashboardLocale.value !== 'id') {
      raw = resolveKey(dashboardMessages.id, key)
    }
    if (raw === key && fallback !== undefined) {
      raw = fallback
    }
    if (!p) return raw
    return Object.entries(p).reduce(
      (acc, [k, v]) => acc.replace(new RegExp(`\\{${k}\\}`, 'g'), String(v)),
      raw
    )
  }

  // Readable alias requested by user
  const translate = t

  const setLocale = (locale: SupportedLocale) => {
    dashboardLocale.value = locale
    localStorage.setItem('lapaqu_lang_dashboard', locale)
    localStorage.setItem('lapaqu_language', locale)
    window.dispatchEvent(new CustomEvent('lapaqu:language-changed', {
      detail: { scope: 'dashboard', locale }
    }))
  }

  return {
    locale: computed(() => dashboardLocale.value),
    setLocale,
    t,
    translate,
  }
}

// -------------------------------------------------------------
// 2. POS & KDS I18N
// -------------------------------------------------------------
const posKdsLocale = ref<SupportedLocale>(
  (localStorage.getItem('lapaqu_lang_pos_kds') as SupportedLocale) || 
  (localStorage.getItem('lapaqu_language') as SupportedLocale) || 
  'id'
)

const posKdsMessages: Record<SupportedLocale, any> = {
  id: posKdsId,
  en: posKdsEn,
}

export function usePosKdsI18n() {
  const t = (
    key: string,
    fallbackOrParams?: string | Record<string, string | number>,
    params?: Record<string, string | number>
  ): string => {
    const fallback = typeof fallbackOrParams === 'string' ? fallbackOrParams : undefined
    const p = typeof fallbackOrParams === 'object' ? fallbackOrParams : params
    const activeDict = posKdsMessages[posKdsLocale.value] || posKdsMessages.id
    let raw = resolveKey(activeDict, key)

    // Fallback across default ID or adjacent scopes if missing
    if (raw === key) {
      const dicts = [activeDict, posKdsMessages.id]
      for (const dict of dicts) {
        const direct = resolveKey(dict, key)
        if (direct !== key) { raw = direct; break }
        if (key.startsWith('pos.')) {
          const sub = key.slice(4)
          if (dict.layout?.[sub]) { raw = dict.layout[sub]; break }
          if (dict.common?.[sub]) { raw = dict.common[sub]; break }
        }
        if (key.startsWith('kds.')) {
          const sub = key.slice(4)
          if (dict.layout?.[sub]) { raw = dict.layout[sub]; break }
          if (dict.common?.[sub]) { raw = dict.common[sub]; break }
        }
      }
    }

    if (raw === key && fallback !== undefined) {
      raw = fallback
    }
    if (!p) return raw
    return Object.entries(p).reduce(
      (acc, [k, v]) => acc.replace(new RegExp(`\\{${k}\\}`, 'g'), String(v)),
      raw
    )
  }

  const translate = t

  const setLocale = (locale: SupportedLocale) => {
    posKdsLocale.value = locale
    localStorage.setItem('lapaqu_lang_pos_kds', locale)
    window.dispatchEvent(new CustomEvent('lapaqu:language-changed', {
      detail: { scope: 'pos_kds', locale }
    }))
  }

  return {
    locale: computed(() => posKdsLocale.value),
    setLocale,
    t,
    translate,
  }
}

// -------------------------------------------------------------
// 3. CUSTOMER / SELF ORDER I18N
// -------------------------------------------------------------
const customerLocale = ref<SupportedLocale>(
  (localStorage.getItem('lapaqu_lang_customer') as SupportedLocale) || 
  (localStorage.getItem('lapaqu_language') as SupportedLocale) || 
  'id'
)

const customerMessages: Record<SupportedLocale, any> = {
  id: customerId,
  en: customerEn,
}

export function useCustomerI18n() {
  const t = (
    key: string,
    fallbackOrParams?: string | Record<string, string | number>,
    params?: Record<string, string | number>
  ): string => {
    const fallback = typeof fallbackOrParams === 'string' ? fallbackOrParams : undefined
    const p = typeof fallbackOrParams === 'object' ? fallbackOrParams : params
    const activeDict = customerMessages[customerLocale.value] || customerMessages.id
    let raw = resolveKey(activeDict, key)
    if (raw === key && customerLocale.value !== 'id') {
      raw = resolveKey(customerMessages.id, key)
    }
    if (raw === key && fallback !== undefined) {
      raw = fallback
    }
    if (!p) return raw
    return Object.entries(p).reduce(
      (acc, [k, v]) => acc.replace(new RegExp(`\\{${k}\\}`, 'g'), String(v)),
      raw
    )
  }

  const translate = t

  const setLocale = (locale: SupportedLocale) => {
    customerLocale.value = locale
    localStorage.setItem('lapaqu_lang_customer', locale)
    window.dispatchEvent(new CustomEvent('lapaqu:language-changed', {
      detail: { scope: 'customer', locale }
    }))
  }

  return {
    locale: computed(() => customerLocale.value),
    setLocale,
    t,
    translate,
  }
}

// Synchronize all reactive locales on cross-scope language changes
if (typeof window !== 'undefined') {
  window.addEventListener('lapaqu:language-changed', (e: any) => {
    const locale = e.detail?.locale
    if (!locale) return
    if (e.detail?.scope === 'dashboard') {
      dashboardLocale.value = locale
    } else if (e.detail?.scope === 'pos_kds') {
      posKdsLocale.value = locale
    } else if (e.detail?.scope === 'customer') {
      customerLocale.value = locale
    } else {
      dashboardLocale.value = locale
      posKdsLocale.value = locale
      customerLocale.value = locale
    }
  })
}
