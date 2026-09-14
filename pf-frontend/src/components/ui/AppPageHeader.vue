<script setup lang="ts">
interface Props {
  title: string
  subtitle?: string
}

defineProps<Props>()
</script>

<template>
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
      <h1 class="text-2xl md:text-[32px] font-extrabold text-[#1E293B] dark:text-white tracking-tight leading-tight">{{ title }}</h1>
      <p v-if="subtitle" class="text-sm font-medium text-[#475569] dark:text-[#94A3B8] mt-1">{{ subtitle }}</p>
    </div>
    <div v-if="$slots.action" class="flex items-center gap-3 shrink-0 flex-wrap">
      <slot name="action" />
    </div>
  </div>
</template>
