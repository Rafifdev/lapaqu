import { ref } from 'vue'

export type SettingsTab = 'general' | 'account' | 'branding' | 'payment' | 'billing'

const isOpen = ref(false)
const activeTab = ref<SettingsTab>('general')
const searchQuery = ref('')

export function useSettingsModal() {
  const openSettingsModal = (tab?: SettingsTab) => {
    if (tab) {
      activeTab.value = tab
    }
    isOpen.value = true
  }

  const closeSettingsModal = () => {
    isOpen.value = false
    searchQuery.value = ''
  }

  const toggleSettingsModal = () => {
    isOpen.value = !isOpen.value
  }

  const setTab = (tab: SettingsTab) => {
    activeTab.value = tab
  }

  return {
    isOpen,
    activeTab,
    searchQuery,
    openSettingsModal,
    closeSettingsModal,
    toggleSettingsModal,
    setTab
  }
}
