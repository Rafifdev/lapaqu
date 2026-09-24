<script setup lang="ts">
interface Props {
  name: string
  size?: number | string
  filled?: boolean
  weight?: number | string
  class?: any
}

const props = withDefaults(defineProps<Props>(), {
  size: 20,
  filled: false,
  weight: 400,
})
</script>

<template>
  <span
    class="material-symbols-rounded select-none notranslate font-normal inline-flex items-center justify-center leading-none"
    translate="no"
    :class="props.class"
    :style="{
      fontFamily: `'Material Symbols Rounded', sans-serif`,
      fontSize: typeof size === 'number' ? `${size}px` : size,
      width: typeof size === 'number' ? `${size}px` : size,
      height: typeof size === 'number' ? `${size}px` : size,
      fontWeight: '400',
      fontVariationSettings: `'FILL' ${filled ? 1 : 0}, 'wght' ${weight || 400}, 'GRAD' 0, 'opsz' 24`,
    }"
  >{{ name }}</span>
</template>
