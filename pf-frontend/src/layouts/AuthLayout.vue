<script setup lang="ts">
import AppIcon from '@/components/ui/AppIcon.vue'
import { useTheme } from '@/composables/useTheme'

const { isDark, toggleTheme } = useTheme()
</script>

<template>
  <div
    class="min-h-screen bg-[#F5F6FA] dark:bg-[#1B2431] flex flex-col justify-center items-center p-4 font-sans relative transition-colors duration-200">
    <!-- Top Theme Toggle -->
    <div class="absolute top-6 right-6">
      <button @click="toggleTheme"
        class="w-10 h-10 rounded-full flex items-center justify-center bg-white dark:bg-[#273142] border border-[#E2E8F0] dark:border-[#334155] text-[#475569] dark:text-[#CBD5E1] shadow-sm hover:scale-105 transition-all cursor-pointer">
        <AppIcon v-if="isDark" name="light_mode" :size="20" class="text-[#FBBF24]" />
        <AppIcon v-else name="dark_mode" :size="20" />
      </button>
    </div>

    <!-- Centered Box -->
    <div class="w-full max-w-md">
      <!-- Logo Branding -->
      <div class="text-center mb-8 flex flex-col items-center">
        <img src="@/assets/brand_logo/lapaqu-logo.png" alt="Lapaqu" class="w-16 h-16 object-contain mb-3 drop-shadow-md" />
        <h1 class="text-3xl md:text-4xl font-black text-[#1E293B] dark:text-white tracking-tight">
          <span class="text-[#4880FF]">Lapa</span>qu
        </h1>
        <p class="text-xs font-bold uppercase tracking-widest text-[#4880FF] mt-0.5">POS SaaS Platform</p>
        <p class="text-xs md:text-sm text-[#475569] dark:text-[#94A3B8] mt-2 font-medium">Platform Kasir & Self-Order
          Terintegrasi</p>
      </div>

      <router-view />
    </div>
  </div>
</template>
