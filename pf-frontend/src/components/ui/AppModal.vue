<script setup lang="ts">
import { computed } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { Motion, AnimatePresence } from 'motion-v'

interface Props {
  show?: boolean
  modelValue?: boolean
  title?: string
  maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl' | '4xl'
  size?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl' | '4xl'
  footerBorder?: boolean
  contentClass?: string
  footerClass?: string
}

const props = withDefaults(defineProps<Props>(), {
  show: undefined,
  modelValue: undefined,
  maxWidth: 'md',
  footerBorder: true,
  contentClass: '',
  footerClass: '',
})

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'update:modelValue', value: boolean): void
}>()

const isOpen = computed(() => {
  if (typeof props.modelValue === 'boolean') return props.modelValue
  if (typeof props.show === 'boolean') return props.show
  return false
})

const handleClose = () => {
  emit('close')
  emit('update:modelValue', false)
}

const maxWidthClasses = {
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-lg',
  xl: 'max-w-2xl',
  '2xl': 'max-w-4xl',
  '3xl': 'max-w-5xl',
  '4xl': 'max-w-6xl',
}
</script>

<template>
  <teleport to="body">
    <AnimatePresence>
      <Motion
        v-if="isOpen"
        :initial="{ opacity: 0 }"
        :animate="{ opacity: 1 }"
        :exit="{ opacity: 0 }"
        :transition="{ duration: 0.15, ease: 'easeOut' }"
        class="fixed inset-0 z-[99999] flex items-center justify-center p-4 bg-black/50 [transform:translateZ(0)]"
        @click.self="handleClose"
      >
        <!-- Modal Dialog with Butter-Smooth Hardware Acceleration -->
        <Motion
          :initial="{ opacity: 0, scale: 0.96, y: 8 }"
          :animate="{ opacity: 1, scale: 1, y: 0 }"
          :exit="{ opacity: 0, scale: 0.96, y: 6 }"
          :transition="{ duration: 0.18, ease: [0.16, 1, 0.3, 1] }"
          :class="[
            'w-full bg-white dark:bg-[#273142] rounded-2xl shadow-2xl border border-[#E2E8F0] dark:border-[#334155] ring-1 ring-black/[0.05] dark:ring-white/10 overflow-hidden [transform:translateZ(0)]',
            maxWidthClasses[props.size || maxWidth],
          ]"
        >
          <!-- Header -->
          <div
            v-if="title || $slots.header"
            class="flex items-center justify-between p-5 border-b border-[#F1F5F9] dark:border-[#334155]"
          >
            <slot name="header">
              <h3 class="text-lg font-extrabold text-[#1E293B] dark:text-white tracking-tight">
                {{ title }}
              </h3>
            </slot>
            <button
              type="button"
              @click="handleClose"
              class="w-8 h-8 rounded-lg flex items-center justify-center text-[#94A3B8] hover:text-[#1E293B] dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer"
            >
              <AppIcon name="close" :size="20" />
            </button>
          </div>

          <!-- Content -->
          <div :class="['p-5 max-h-[85vh] overflow-y-auto text-sm', contentClass]">
            <slot />
          </div>

          <!-- Footer -->
          <div
            v-if="$slots.footer"
            :class="[
              'flex items-center justify-end gap-3 p-5',
              footerBorder ? 'border-t border-[#F1F5F9] dark:border-[#334155]' : 'border-t-0',
              footerClass
            ]"
          >
            <slot name="footer" />
          </div>
        </Motion>
      </Motion>
    </AnimatePresence>
  </teleport>
</template>
