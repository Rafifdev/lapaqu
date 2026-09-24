<script setup lang="ts">
import { Motion, AnimatePresence } from 'motion-v'

interface Props {
  show?: boolean
  type?: 'collapse' | 'dropdown' | 'fade'
  duration?: number
  placement?: 'bottom' | 'top'
}

const props = withDefaults(defineProps<Props>(), {
  show: true,
  type: 'dropdown',
  duration: 0.2,
  placement: 'bottom',
})

const collapseVariants = {
  initial: { opacity: 0, height: 0, overflow: 'hidden' },
  animate: {
    opacity: 1,
    height: 'auto',
    overflow: 'hidden',
    transition: {
      height: { duration: props.duration, ease: [0.25, 1, 0.5, 1] },
      opacity: { duration: props.duration * 0.75, ease: 'easeOut' },
    },
  },
  exit: {
    opacity: 0,
    height: 0,
    overflow: 'hidden',
    transition: {
      height: { duration: props.duration * 0.85, ease: [0.25, 1, 0.5, 1] },
      opacity: { duration: props.duration * 0.45, ease: 'easeIn' },
    },
  },
}
</script>

<template>
  <!-- 1. Collapse with Motion V (for vertical expanding accordions like OrderStatusPage) -->
  <AnimatePresence v-if="type === 'collapse'" :initial="false">
    <Motion
      v-if="show"
      :initial="collapseVariants.initial"
      :animate="collapseVariants.animate"
      :exit="collapseVariants.exit"
      class="w-full [transform:translateZ(0)]"
    >
      <slot />
    </Motion>
  </AnimatePresence>

  <!-- 2. Dropdown with Vue Native Transition (Zero extra DOM nodes, zero layout shifts, retains all absolute positioning) -->
  <Transition
    v-else-if="type === 'dropdown'"
    enter-active-class="transition duration-180 ease-out"
    :enter-from-class="placement === 'top' ? 'transform scale-95 opacity-0 translate-y-1.5' : 'transform scale-95 opacity-0 -translate-y-1.5'"
    enter-to-class="transform scale-100 opacity-100 translate-y-0"
    leave-active-class="transition duration-120 ease-in"
    leave-from-class="transform scale-100 opacity-100 translate-y-0"
    :leave-to-class="placement === 'top' ? 'transform scale-95 opacity-0 translate-y-1.5' : 'transform scale-95 opacity-0 -translate-y-1.5'"
  >
    <slot />
  </Transition>

  <!-- 3. Fade Transition -->
  <Transition
    v-else
    enter-active-class="transition-opacity duration-150 ease-out"
    enter-from-class="opacity-0"
    enter-to-class="opacity-100"
    leave-active-class="transition-opacity duration-100 ease-in"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <slot />
  </Transition>
</template>
