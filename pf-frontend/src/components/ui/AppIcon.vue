<script setup lang="ts">
interface Props {
  name: string
  size?: number | string
  filled?: boolean
  class?: any
}

const props = withDefaults(defineProps<Props>(), {
  size: 20,
  filled: false,
})
</script>

<template>
  <span
    class="material-symbols-rounded select-none notranslate"
    translate="no"
    :class="props.class"
    :style="{
      fontFamily: `'Material Symbols Rounded', sans-serif`,
      fontSize: typeof size === 'number' ? `${size}px` : size,
      width: typeof size === 'number' ? `${size}px` : size,
      height: typeof size === 'number' ? `${size}px` : size,
      fontVariationSettings: `'FILL' ${filled ? 1 : 0}, 'wght' 500, 'GRAD' 0, 'opsz' 24`,
    }"
  >{{ name }}</span>
</template>
