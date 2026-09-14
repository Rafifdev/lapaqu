<script setup lang="ts">
import AppIcon from '@/components/ui/AppIcon.vue'

export type BadgeVariant =
  | 'completed'
  | 'processing'
  | 'rejected'
  | 'on_hold'
  | 'in_transit'
  | 'success'
  | 'danger'
  | 'warning'
  | 'info'
  | 'purple'
  | 'indigo'
  | 'primary'
  | 'neutral'
  | 'gray'

interface Props {
  variant?: BadgeVariant
  solid?: boolean
  size?: 'sm' | 'md' | 'lg'
  rounded?: 'sm' | 'md' | 'lg' | 'full'
  icon?: string
  dot?: boolean
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'completed',
  solid: false,
  size: 'md',
  rounded: 'full',
  dot: false,
  loading: false,
})

// Subtle Classes
const subtleClasses: Record<BadgeVariant, string> = {
  completed: 'bg-[#00B69B]/15 dark:bg-[#00B69B]/20 text-[#00B69B]',
  success: 'bg-[#00B69B]/15 dark:bg-[#00B69B]/20 text-[#00B69B]',
  processing: 'bg-[#6226EF]/15 dark:bg-[#6226EF]/20 text-[#6226EF]',
  indigo: 'bg-[#6226EF]/15 dark:bg-[#6226EF]/20 text-[#6226EF]',
  rejected: 'bg-[#EF3826]/15 dark:bg-[#EF3826]/20 text-[#EF3826]',
  danger: 'bg-[#EF3826]/15 dark:bg-[#EF3826]/20 text-[#EF3826]',
  on_hold: 'bg-[#FFA756]/15 dark:bg-[#FFA756]/20 text-[#FFA756]',
  warning: 'bg-[#FFA756]/15 dark:bg-[#FFA756]/20 text-[#FFA756]',
  in_transit: 'bg-[#BA29FF]/15 dark:bg-[#BA29FF]/20 text-[#BA29FF]',
  purple: 'bg-[#BA29FF]/15 dark:bg-[#BA29FF]/20 text-[#BA29FF]',
  primary: 'bg-[#4880FF]/15 dark:bg-[#4880FF]/20 text-[#4880FF]',
  info: 'bg-[#4880FF]/15 dark:bg-[#4880FF]/20 text-[#4880FF]',
  neutral: 'bg-[#F1F5F9] dark:bg-[#334155] text-[#64748B] dark:text-[#94A3B8]',
  gray: 'bg-[#F1F5F9] dark:bg-[#334155] text-[#64748B] dark:text-[#94A3B8]',
}

// Solid Classes (Vibrant DashStack Style)
const solidClasses: Record<BadgeVariant, string> = {
  completed: 'bg-[#00B69B] text-white',
  success: 'bg-[#00B69B] text-white',
  processing: 'bg-[#6226EF] text-white',
  indigo: 'bg-[#6226EF] text-white',
  rejected: 'bg-[#EF3826] text-white',
  danger: 'bg-[#EF3826] text-white',
  on_hold: 'bg-[#FFA756] text-white',
  warning: 'bg-[#FFA756] text-white',
  in_transit: 'bg-[#BA29FF] text-white',
  purple: 'bg-[#BA29FF] text-white',
  primary: 'bg-[#4880FF] text-white',
  info: 'bg-[#4880FF] text-white',
  neutral: 'bg-[#64748B] text-white',
  gray: 'bg-[#64748B] text-white',
}

const dotColors: Record<BadgeVariant, string> = {
  completed: 'bg-[#00B69B]',
  success: 'bg-[#00B69B]',
  processing: 'bg-[#6226EF]',
  indigo: 'bg-[#6226EF]',
  rejected: 'bg-[#EF3826]',
  danger: 'bg-[#EF3826]',
  on_hold: 'bg-[#FFA756]',
  warning: 'bg-[#FFA756]',
  in_transit: 'bg-[#BA29FF]',
  purple: 'bg-[#BA29FF]',
  primary: 'bg-[#4880FF]',
  info: 'bg-[#4880FF]',
  neutral: 'bg-[#94A3B8]',
  gray: 'bg-[#94A3B8]',
}

const sizeClasses = {
  sm: 'px-2 py-0.5 text-xs font-bold gap-1 min-w-[64px] justify-center',
  md: 'px-4 py-1 text-sm font-bold gap-2 min-w-[88px] justify-center',
  lg: 'px-4 py-2 text-base font-bold gap-2 min-w-[112px] justify-center',
}

const roundedClasses = {
  sm: 'rounded',
  md: 'rounded-md',
  lg: 'rounded-lg',
  full: 'rounded-full',
}
</script>

<template>
  <!-- Skeleton Loading State (Bawaan Tailwind CSS animate-pulse) -->
  <span
    v-if="loading"
    :class="[
      'inline-flex items-center select-none animate-pulse bg-[#E2E8F0] dark:bg-[#334155]',
      sizeClasses[size],
      roundedClasses[rounded],
    ]"
  >
    <span class="opacity-0 invisible">Loading</span>
  </span>

  <span
    v-else
    :class="[
      'inline-flex items-center font-bold tracking-tight select-none whitespace-nowrap text-center transition-colors',
      solid ? (solidClasses[variant] || solidClasses.completed) : (subtleClasses[variant] || subtleClasses.completed),
      sizeClasses[size],
      roundedClasses[rounded],
    ]"
  >
    <span v-if="dot" :class="['w-1.5 h-1.5 rounded-full shrink-0 animate-pulse', dotColors[variant] || dotColors.completed]" />
    <AppIcon v-else-if="icon" :name="icon" :size="size === 'sm' ? 12 : 14" class="shrink-0" />
    <slot />
  </span>
</template>
