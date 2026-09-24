import { ref, computed, watch } from 'vue'

export type ThemeMode = 'system' | 'light' | 'dark'

const getSystemPrefersDark = (): boolean => {
  if (typeof window === 'undefined' || !window.matchMedia) return false
  return window.matchMedia('(prefers-color-scheme: dark)').matches
}

const getInitialThemePreference = (): ThemeMode => {
  if (typeof window === 'undefined') return 'system'
  const saved = localStorage.getItem('lapaqu_theme') as ThemeMode | null
  if (saved === 'dark' || saved === 'light' || saved === 'system') {
    return saved
  }
  return 'system' // Default: mengikuti preferensi tema sistem (OS)
}

// Global reactive states
const themePreference = ref<ThemeMode>(getInitialThemePreference())
const systemPrefersDark = ref<boolean>(getSystemPrefersDark())

// Computed boolean: apakah tampilan saat ini bernuansa gelap
const isDark = computed(() => {
  if (themePreference.value === 'system') {
    return systemPrefersDark.value
  }
  return themePreference.value === 'dark'
})

export function applyTheme() {
  if (typeof document === 'undefined') return
  const root = document.documentElement
  if (isDark.value) {
    root.classList.add('dark')
    root.classList.remove('light')
  } else {
    root.classList.remove('dark')
    root.classList.add('light')
  }
}

// Watcher untuk sinkronisasi class DOM secara otomatis saat isDark berubah
if (typeof window !== 'undefined') {
  watch(isDark, () => {
    applyTheme()
  }, { immediate: true })

  // Pasang listener perubahan preferensi tema OS secara realtime
  const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
  const handleMediaChange = (e: MediaQueryListEvent) => {
    systemPrefersDark.value = e.matches
    if (themePreference.value === 'system') {
      applyTheme()
    }
  }
  if (mediaQuery.addEventListener) {
    mediaQuery.addEventListener('change', handleMediaChange)
  } else if ('addListener' in mediaQuery) {
    (mediaQuery as any).addListener(handleMediaChange)
  }
}

export function useTheme() {
  const setTheme = (mode: ThemeMode) => {
    themePreference.value = mode
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem('lapaqu_theme', mode)
    }
    applyTheme()
  }

  const toggleTheme = () => {
    // Jika ditoggle manual (misal via tombol di AppTopbar), beralih ke kebalikan status efektif
    const nextMode: ThemeMode = isDark.value ? 'light' : 'dark'
    setTheme(nextMode)
  }

  return {
    isDark,
    themePreference,
    setTheme,
    toggleTheme,
    applyTheme,
  }
}
