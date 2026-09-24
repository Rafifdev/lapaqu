import { ref, computed, watch } from 'vue'

export type MotionMode = 'system' | 'reduced'

const getSystemPrefersReducedMotion = (): boolean => {
  if (typeof window === 'undefined' || !window.matchMedia) return false
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

const getInitialMotionPreference = (): MotionMode => {
  if (typeof window === 'undefined') return 'system'
  const saved = localStorage.getItem('lapaqu_motion') as MotionMode | null
  if (saved === 'reduced' || saved === 'system') {
    return saved
  }
  return 'system'
}

// Global reactive states
const motionPreference = ref<MotionMode>(getInitialMotionPreference())
const systemPrefersReduced = ref<boolean>(getSystemPrefersReducedMotion())

// Computed boolean: apakah animasi diminimalkan saat ini
export const isReducedMotion = computed(() => {
  if (motionPreference.value === 'system') {
    return systemPrefersReduced.value
  }
  return motionPreference.value === 'reduced'
})

export function applyMotion() {
  if (typeof document === 'undefined') return
  const root = document.documentElement
  if (isReducedMotion.value) {
    root.classList.add('reduce-motion')
  } else {
    root.classList.remove('reduce-motion')
  }
}

// Watcher untuk sinkronisasi class DOM secara otomatis saat isReducedMotion berubah
if (typeof window !== 'undefined') {
  watch(isReducedMotion, () => {
    applyMotion()
  }, { immediate: true })

  // Listen to OS prefers-reduced-motion in real time
  const mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)')
  const handleMediaChange = (e: MediaQueryListEvent) => {
    systemPrefersReduced.value = e.matches
    if (motionPreference.value === 'system') {
      applyMotion()
    }
  }
  if (mediaQuery.addEventListener) {
    mediaQuery.addEventListener('change', handleMediaChange)
  } else if ('addListener' in mediaQuery) {
    (mediaQuery as any).addListener(handleMediaChange)
  }
}

export function useMotion() {
  const setMotionPreference = (mode: MotionMode) => {
    motionPreference.value = mode
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem('lapaqu_motion', mode)
    }
    applyMotion()
  }

  return {
    isReducedMotion,
    motionPreference,
    setMotionPreference,
    applyMotion,
  }
}
