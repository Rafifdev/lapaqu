<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { useTheme } from '@/composables/useTheme'
import { useSettingsModal } from '@/composables/useSettingsModal'
import SettingsModal from '@/components/settings/SettingsModal.vue'

const { applyTheme } = useTheme()
const { openSettingsModal } = useSettingsModal()

const handleGlobalShortcut = (e: KeyboardEvent) => {
  // Ctrl + , or Cmd + , or Ctrl + Shift + ,
  if ((e.ctrlKey || e.metaKey) && (e.key === ',' || e.key === '<')) {
    e.preventDefault()
    openSettingsModal()
  }
}

onMounted(() => {
  applyTheme()
  window.addEventListener('keydown', handleGlobalShortcut)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalShortcut)
})
</script>

<template>
  <div class="min-h-screen text-[#202224] dark:text-white bg-[#F5F6FA] dark:bg-[#1B2431] font-sans antialiased transition-colors duration-200">
    <!-- Root Router View with Pure Smooth SPA Transition -->
    <router-view v-slot="{ Component, route }">
      <transition name="page-fade" mode="out-in">
        <component :is="Component" :key="route.matched[0]?.path || route.path" />
      </transition>
    </router-view>

    <!-- Global Settings Modal (Claude/Desktop-style dialog) -->
    <SettingsModal />
  </div>
</template>

<style>
/* Smooth SPA Page Transition */
.page-fade-enter-active,
.page-fade-leave-active {
  transition: opacity 0.2s cubic-bezier(0.16, 1, 0.3, 1), transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.page-fade-enter-from {
  opacity: 0;
  transform: translateY(4px);
}

.page-fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
