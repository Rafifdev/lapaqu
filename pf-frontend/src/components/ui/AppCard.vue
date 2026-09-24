<script setup lang="ts">
interface Props {
  title?: string
  subtitle?: string
  noPadding?: boolean
  hoverable?: boolean
  loading?: boolean
  syncing?: boolean
}

withDefaults(defineProps<Props>(), {
  noPadding: false,
  hoverable: false,
  loading: false,
  syncing: false,
})
</script>

<template>
  <div :class="[
    'bg-white dark:bg-[#273142] rounded-[14px] shadow-[6px_6px_54px_0_rgba(0,0,0,0.05)] dark:shadow-none border border-transparent dark:border-[#313D4F] transition-all duration-200',
    hoverable ? 'hover:shadow-lg' : '',
    noPadding ? 'p-0' : 'p-5 md:p-6',
  ]">
    <div v-if="title || subtitle || $slots.header || $slots.action || syncing" class="flex items-center justify-between gap-4 mb-5 pb-3.5">
      <div>
        <slot name="header">
          <h3 class="text-base md:text-lg font-extrabold text-[#1E293B] dark:text-white tracking-tight">
            {{ title }}
          </h3>
          <p v-if="subtitle" class="text-xs text-[#64748B] dark:text-[#94A3B8] font-medium mt-0.5">
            {{ subtitle }}
          </p>
        </slot>
      </div>
      <div class="flex items-center gap-3 shrink-0">
        <svg
          v-if="syncing"
          class="animate-spin w-5 h-5 text-[#4880FF] shrink-0"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
        >
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <div v-if="$slots.action">
          <slot name="action" />
        </div>
      </div>
    </div>

    <!-- Skeleton Loading State (Default Built-in Skeleton) -->
    <div v-if="loading" class="animate-pulse space-y-4">
      <slot name="skeleton">
        <div class="h-4 bg-[#E2E8F0] dark:bg-[#334155] rounded-md w-3/4"></div>
        <div class="h-32 bg-[#E2E8F0] dark:bg-[#334155] rounded-xl w-full"></div>
        <div class="h-4 bg-[#E2E8F0] dark:bg-[#334155] rounded-md w-1/2"></div>
      </slot>
    </div>

    <!-- Real Content -->
    <slot v-else />

    <div v-if="$slots.footer" class="mt-5 pt-3.5">
      <slot name="footer" />
    </div>
  </div>
</template>
