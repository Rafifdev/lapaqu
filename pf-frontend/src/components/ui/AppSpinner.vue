<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  size?: number | string
  color?: string
}

const props = withDefaults(defineProps<Props>(), {
  size: 16,
  color: 'text-current',
})

const sizeClass = computed(() => {
  if (typeof props.size === 'string' && (props.size.includes('w-') || props.size.includes('h-'))) {
    return props.size
  }
  return ''
})

const sizePx = computed(() => {
  if (typeof props.size === 'number') return `${props.size}px`
  if (typeof props.size === 'string' && !isNaN(Number(props.size))) return `${props.size}px`
  return undefined
})
</script>

<template>
  <svg
    :class="['animate-spin shrink-0', color, sizeClass]"
    :style="sizePx ? { width: sizePx, height: sizePx } : {}"
    xmlns="http://www.w3.org/2000/svg"
    fill="none"
    viewBox="0 0 24 24"
  >
    <circle
      class="opacity-25"
      cx="12"
      cy="12"
      r="10"
      stroke="currentColor"
      stroke-width="4"
    />
    <path
      class="opacity-75"
      fill="currentColor"
      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
    />
  </svg>
</template>
