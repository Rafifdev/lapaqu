<script setup lang="ts">
interface Props {
  title?: string
  subtitle?: string
  noPadding?: boolean
  hoverable?: boolean
  loading?: boolean
}

withDefaults(defineProps<Props>(), {
  noPadding: false,
  hoverable: false,
  loading: false,
})
</script>

<template>
  <div :class="[
    'bg-white dark:bg-[#273142] rounded-[14px] shadow-[6px_6px_54px_0_rgba(0,0,0,0.05)] dark:shadow-none border border-transparent dark:border-[#313D4F] transition-all duration-200',
    hoverable ? 'hover:shadow-lg' : '',
    noPadding ? 'p-0' : 'p-5 md:p-6',
  ]">
    <div v-if="title || subtitle || $slots.header || $slots.action" class="flex items-center justify-between gap-4 mb-5 pb-3.5">
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
      <div v-if="$slots.action" class="shrink-0">
        <slot name="action" />
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
