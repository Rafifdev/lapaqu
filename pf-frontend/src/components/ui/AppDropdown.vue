<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { MoreVertical } from 'lucide-vue-next'

export interface DropdownItem {
  label: string
  icon?: any
  iconColor?: string
  danger?: boolean
  variant?: 'default' | 'warning' | 'danger' | 'success' | 'primary'
  onClick: () => void
  show?: boolean
}

const props = withDefaults(
  defineProps<{
    items?: DropdownItem[]
    width?: string
    align?: 'right' | 'left'
  }>(),
  {
    width: 'w-44',
    align: 'right',
  }
)

const isOpen = ref(false)
const dropdownRef = ref<HTMLElement | null>(null)

const toggle = () => {
  isOpen.value = !isOpen.value
}

const close = () => {
  isOpen.value = false
}

const handleClickOutside = (e: MouseEvent) => {
  if (dropdownRef.value && !dropdownRef.value.contains(e.target as Node)) {
    close()
  }
}

onMounted(() => {
  window.addEventListener('click', handleClickOutside)
})

onBeforeUnmount(() => {
  window.removeEventListener('click', handleClickOutside)
})

const getItemHoverClasses = (item: DropdownItem) => {
  if (item.danger || item.variant === 'danger') {
    return 'hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10'
  }
  if (item.variant === 'warning') {
    return 'hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10'
  }
  if (item.variant === 'success') {
    return 'hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-500/10'
  }
  return 'hover:text-blue-600 dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-500/10'
}

const getIconHoverClasses = (item: DropdownItem) => {
  if (item.danger || item.variant === 'danger') {
    return 'group-hover:text-red-600 dark:group-hover:text-red-400'
  }
  if (item.variant === 'warning') {
    return 'group-hover:text-amber-600 dark:group-hover:text-amber-400'
  }
  if (item.variant === 'success') {
    return 'group-hover:text-emerald-600 dark:group-hover:text-emerald-400'
  }
  return 'group-hover:text-blue-600 dark:group-hover:text-blue-400'
}

defineExpose({ open: () => (isOpen.value = true), close, toggle })
</script>

<template>
  <div ref="dropdownRef" class="relative inline-flex items-center justify-center" @click.stop>
    <!-- Trigger Button (Shadcn UI style) -->
    <slot name="trigger" :toggle="toggle" :isOpen="isOpen">
      <button
        type="button"
        @click="toggle"
        class="w-7 h-7 rounded-lg flex items-center justify-center text-[#94A3B8] hover:text-[#1E293B] dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer"
        title="Opsi"
      >
        <MoreVertical class="w-4 h-4" />
      </button>
    </slot>

    <!-- Popover Dropdown Menu dengan Smooth Vue Transition ala Shadcn UI -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="transform scale-95 opacity-0 -translate-y-1"
      enter-to-class="transform scale-100 opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="transform scale-100 opacity-100 translate-y-0"
      leave-to-class="transform scale-95 opacity-0 -translate-y-1"
    >
      <div
        v-if="isOpen"
        :class="[
          'absolute top-full mt-1.5 z-50 bg-white dark:bg-[#1E293B] rounded-xl shadow-xl border border-[#E2E8F0] dark:border-[#334155] p-1.5 space-y-1 text-sm font-semibold will-change-transform',
          align === 'right' ? 'right-0 origin-top-right' : 'left-0 origin-top-left',
          width || 'w-44',
        ]"
      >
        <slot :close="close">
          <template v-for="(item, idx) in items" :key="idx">
            <button
              v-if="item.show !== false"
              type="button"
              @click="item.onClick(); close()"
              :class="[
                'group w-full px-3 py-2.5 rounded-lg text-left text-[#202224] dark:text-[#E2E8F0] flex items-center gap-2.5 transition-all duration-150 ease-in-out cursor-pointer active:scale-[0.98] whitespace-nowrap',
                getItemHoverClasses(item),
              ]"
            >
              <component
                v-if="item.icon"
                :is="item.icon"
                :class="[
                  'w-4 h-4 shrink-0 text-[#64748B] dark:text-[#94A3B8] transition-colors duration-150',
                  item.iconColor,
                  getIconHoverClasses(item),
                ]"
              />
              <span>{{ item.label }}</span>
            </button>
          </template>
        </slot>
      </div>
    </Transition>
  </div>
</template>
