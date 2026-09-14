<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  name?: string
  imageUrl?: string
  size?: 'sm' | 'md' | 'lg' | 'xl'
  status?: 'online' | 'offline' | 'busy'
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  size: 'md',
  loading: false,
})

const initials = computed(() => {
  if (!props.name) return '?'
  const parts = props.name.trim().split(' ')
  if (parts.length === 1) return (parts[0] || '').substring(0, 2).toUpperCase()
  return ((parts[0] || '')[0] + ((parts[1] || '')[0] || '')).toUpperCase()
})

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'w-8 h-8 text-xs'
    case 'md':
      return 'w-10 h-10 text-sm'
    case 'lg':
      return 'w-12 h-12 text-base'
    case 'xl':
      return 'w-16 h-16 text-xl'
    default:
      return 'w-10 h-10 text-sm'
  }
})
</script>

<template>
  <div v-if="loading" :class="['rounded-full bg-[#E2E8F0] dark:bg-[#334155] animate-pulse shrink-0 inline-block', sizeClasses]" />

  <div v-else class="relative inline-block shrink-0">
    <img
      v-if="imageUrl"
      :src="imageUrl"
      :alt="name"
      :class="['rounded-full object-cover border border-[#E8E8E8] dark:border-[#313D4F]', sizeClasses]"
    />
    <div
      v-else
      :class="[
        'rounded-full bg-[#E2EAF8] dark:bg-[#323D4E] text-[#4880FF] dark:text-[#709eff] font-bold flex items-center justify-center select-none border border-[#E8E8E8] dark:border-[#313D4F]',
        sizeClasses,
      ]"
    >
      {{ initials }}
    </div>
    <span
      v-if="status"
      :class="[
        'absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full border-2 border-white dark:border-[#273142]',
        status === 'online' ? 'bg-[#00B69B]' : '',
        status === 'offline' ? 'bg-[#D5D5D5]' : '',
        status === 'busy' ? 'bg-[#FD5454]' : '',
      ]"
    />
  </div>
</template>
