<script setup lang="ts">
interface Props {
  width?: string
  height?: string
  rounded?: 'none' | 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl' | 'full'
  circle?: boolean
}

withDefaults(defineProps<Props>(), {
  rounded: 'md',
  circle: false,
})
</script>

<template>
  <div
    :class="[
      'animate-pulse bg-[#E2E8F0] dark:bg-[#334155] shrink-0',
      circle ? 'rounded-full' : {
        'none': 'rounded-none',
        'sm': 'rounded-sm',
        'md': 'rounded-md',
        'lg': 'rounded-lg',
        'xl': 'rounded-xl',
        '2xl': 'rounded-2xl',
        '3xl': 'rounded-3xl',
        'full': 'rounded-full'
      }[rounded] || 'rounded-md'
    ]"
    :style="{
      width: width || (circle ? height : undefined),
      height: height || (circle ? width : undefined)
    }"
  />
</template>
