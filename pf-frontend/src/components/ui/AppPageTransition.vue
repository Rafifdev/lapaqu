<script setup lang="ts">
import type { Component as VueComponent } from 'vue'
import type { RouteLocationNormalizedLoaded } from 'vue-router'
import { isReducedMotion } from '@/composables/useMotion'

withDefaults(
  defineProps<{
    component?: VueComponent
    route?: RouteLocationNormalizedLoaded
    mode?: 'out-in' | 'default' | 'in-out'
    name?: string
  }>(),
  {
    mode: 'out-in',
    name: 'page-fade',
  }
)
</script>

<template>
  <Transition :name="isReducedMotion ? '' : name" :mode="isReducedMotion ? undefined : mode">
    <component v-if="component" :is="component" :key="route?.fullPath || route?.path" />
    <slot v-else />
  </Transition>
</template>

<style>
/* Konsisten Page Transition di Seluruh Aplikasi: Smooth Page Fade */
.page-fade-enter-active,
.page-fade-leave-active {
  transition: opacity 0.2s cubic-bezier(0.16, 1, 0.3, 1), transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.page-fade-enter-from {
  opacity: 0;
  transform: translateY(4px);
}

.page-fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
