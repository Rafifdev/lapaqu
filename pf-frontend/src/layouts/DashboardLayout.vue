<script setup lang="ts">
import { ref } from 'vue'
import AppSidebar from '@/components/layout/AppSidebar.vue'

const sidebarCollapsed = ref(false)

const toggleSidebar = () => {
  sidebarCollapsed.value = !sidebarCollapsed.value
}
</script>

<template>
  <div class="min-h-screen flex bg-[#F5F6FA] dark:bg-[#1B2431] text-[#1E293B] dark:text-white font-sans transition-colors duration-200">
    <!-- Desktop Sidebar -->
    <AppSidebar
      :collapsed="sidebarCollapsed"
      @toggle-collapse="toggleSidebar"
      class="hidden lg:flex"
    />

    <!-- Main Content Area (Topbar removed) -->
    <div class="flex-1 flex flex-col min-w-0">
      <main class="flex-1 p-4 md:p-6 max-w-[1380px] w-full mx-auto">
        <router-view v-slot="{ Component, route: currentRoute }">
          <transition name="page-fade" mode="out-in">
            <component :is="Component" :key="currentRoute.path" />
          </transition>
        </router-view>
      </main>
    </div>
  </div>
</template>
