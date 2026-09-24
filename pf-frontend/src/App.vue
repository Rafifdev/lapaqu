<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useTheme } from '@/composables/useTheme'
import { MotionConfig } from 'motion-v'
import { useMotion, isReducedMotion } from '@/composables/useMotion'
import { useSettingsModal } from '@/composables/useSettingsModal'
import { useAuthStore } from '@/stores/auth'
import SettingsModal from '@/components/settings/SettingsModal.vue'
import AppPageTransition from '@/components/ui/AppPageTransition.vue'


const { applyTheme } = useTheme()
const { applyMotion } = useMotion()
const { openSettingsModal } = useSettingsModal()
const authStore = useAuthStore()
const router = useRouter()

const handleGlobalShortcut = (e: KeyboardEvent) => {
  // Ctrl + , or Cmd + , or Ctrl + Shift + ,
  if ((e.ctrlKey || e.metaKey) && (e.key === ',' || e.key === '<')) {
    e.preventDefault()
    openSettingsModal()
  }
}

onMounted(() => {
  applyTheme()
  applyMotion()
  window.addEventListener('keydown', handleGlobalShortcut)

  // Inisialisasi pemantau kedaluwarsa sesi realtime (otomatis logout di jam 23.59)
  authStore.startSessionMonitoring(() => {
    router.push({
      path: '/auth/login',
      
    })
  })
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalShortcut)
})
</script>

<template>
  <MotionConfig :reducedMotion="isReducedMotion ? 'always' : 'user'">
    <div class="min-h-screen text-[#202224] dark:text-white bg-[#F5F6FA] dark:bg-[#1B2431] font-sans antialiased transition-colors duration-200">
    <!-- Root Router View with Pure Smooth SPA Transition -->
    <router-view v-slot="{ Component, route }">
      <AppPageTransition>
        <component :is="Component" :key="route.matched[0]?.path || route.path" />
      </AppPageTransition>
    </router-view>

    <!-- Global Settings Modal (Claude/Desktop-style dialog) -->
    <SettingsModal />
    </div>
  </MotionConfig>
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
