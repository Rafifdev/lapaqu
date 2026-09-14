import { ref } from 'vue'

const getInitialTheme = () => {
  if (typeof window === 'undefined') return false
  const saved = localStorage.getItem('lapaqu_theme')
  if (saved) return saved === 'dark'
  return document.documentElement.classList.contains('dark')
}

const isDark = ref(getInitialTheme())

export function useTheme() {
  const applyTheme = () => {
    if (typeof document === 'undefined') return
    if (isDark.value) {
      document.documentElement.classList.add('dark')
      document.documentElement.classList.remove('light')
    } else {
      document.documentElement.classList.remove('dark')
      document.documentElement.classList.add('light')
    }
  }

  const toggleTheme = () => {
    isDark.value = !isDark.value
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem('lapaqu_theme', isDark.value ? 'dark' : 'light')
    }
    applyTheme()
  }

  return {
    isDark,
    toggleTheme,
    applyTheme,
  }
}
