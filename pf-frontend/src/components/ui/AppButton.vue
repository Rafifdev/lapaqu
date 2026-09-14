<script setup lang="ts">
import AppIcon from '@/components/ui/AppIcon.vue'

interface Props {
  variant?: 'primary' | 'secondary' | 'outline' | 'danger' | 'success' | 'ghost'
  size?: 'sm' | 'md' | 'lg'
  icon?: string
  iconRight?: string
  loading?: boolean
  disabled?: boolean
  block?: boolean
  type?: 'button' | 'submit' | 'reset'
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'primary',
  size: 'md',
  loading: false,
  disabled: false,
  block: false,
  type: 'button',
})

const variantClasses = {
  primary: 'bg-[#4880FF] hover:bg-[#3971F0] text-white shadow-sm hover:shadow active:bg-[#2F64E0] border border-transparent',
  secondary: 'bg-[#F1F5F9] hover:bg-[#E2E8F0] dark:bg-[#334155] dark:hover:bg-[#475569] text-[#1E293B] dark:text-white border border-[#E2E8F0] dark:border-[#475569]',
  outline: 'bg-transparent border border-[#CBD5E1] dark:border-[#475569] hover:bg-[#F8FAFC] dark:hover:bg-[#334155] text-[#334155] dark:text-[#E2E8F0]',
  danger: 'bg-[#EF4444] hover:bg-[#DC2626] text-white shadow-sm active:bg-[#B91C1C] border border-transparent',
  success: 'bg-[#00B69B] hover:bg-[#009E86] text-white shadow-sm active:bg-[#008772] border border-transparent',
  ghost: 'bg-transparent hover:bg-[#F1F5F9] dark:hover:bg-[#334155] text-[#475569] dark:text-[#CBD5E1]',
}

const sizeClasses = {
  sm: 'px-3 py-1 text-xs rounded-lg gap-2',
  md: 'px-4 py-2 text-sm rounded-lg gap-2',
  lg: 'px-6 py-3 text-base rounded-lg gap-2',
}
</script>

<template>
  <button :type="type" :disabled="disabled || loading" :class="[
    'inline-flex items-center justify-center font-bold tracking-tight transition-all duration-150 active:scale-[0.98] cursor-pointer select-none focus:outline-none focus:ring-2 focus:ring-[#4880FF]/30 disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100',
    variantClasses[variant],
    sizeClasses[size],
    block ? 'w-full' : '',
  ]">
    <div v-if="loading"
      class="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin shrink-0" />
    <slot v-else-if="$slots.prefix" name="prefix" />
    <AppIcon v-else-if="icon" :name="icon" :size="size === 'sm' ? 16 : size === 'lg' ? 22 : 18" class="shrink-0" />

    <span v-if="$slots.default">
      <slot />
    </span>

    <AppIcon v-if="iconRight && !loading" :name="iconRight" :size="size === 'sm' ? 16 : size === 'lg' ? 22 : 18"
      class="shrink-0" />
  </button>
</template>
