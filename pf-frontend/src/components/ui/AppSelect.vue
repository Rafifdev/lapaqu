<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'

export interface Option {
  value: string | number
  label: string
  group?: string
  disabled?: boolean
}

export interface OptionGroup {
  label: string
  options: Option[]
}

interface Props {
  modelValue?: string | number | null
  options?: Option[]
  groups?: OptionGroup[]
  placeholder?: string
  label?: string
  error?: string
  disabled?: boolean
  required?: boolean
  selectClass?: string
  width?: string
  placement?: 'bottom' | 'top'
}

const props = withDefaults(defineProps<Props>(), {
  disabled: false,
  required: false,
  options: () => [],
  placement: 'bottom',
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: string | number): void
  (e: 'change', value: string | number): void
}>()

const isOpen = ref(false)
const dropdownRef = ref<HTMLElement | null>(null)

// Support both direct groups or options with group field
const computedGroups = computed(() => {
  if (props.groups && props.groups.length > 0) {
    return props.groups
  }
  if (!props.options || props.options.length === 0) return null

  const hasAnyGroup = props.options.some(o => !!o.group)
  if (!hasAnyGroup) return null

  const groupMap = new Map<string, Option[]>()
  props.options.forEach(opt => {
    const grp = opt.group || 'Lainnya'
    if (!groupMap.has(grp)) groupMap.set(grp, [])
    groupMap.get(grp)!.push(opt)
  })

  return Array.from(groupMap.entries()).map(([label, options]) => ({
    label,
    options
  }))
})

const allOptions = computed<Option[]>(() => {
  if (computedGroups.value && computedGroups.value.length > 0) {
    return computedGroups.value.flatMap(g => g.options)
  }
  return props.options || []
})

const selectedOption = computed(() => {
  return allOptions.value.find(o => String(o.value) === String(props.modelValue))
})

const displayLabel = computed(() => {
  if (selectedOption.value) return selectedOption.value.label
  return props.placeholder || 'Pilih opsi...'
})

const toggle = () => {
  if (props.disabled) return
  isOpen.value = !isOpen.value
}

const close = () => {
  isOpen.value = false
}

const selectOption = (opt: Option) => {
  if (opt.disabled) return
  isOpen.value = false
  emit('update:modelValue', opt.value)
  emit('change', opt.value)
}

const handleClickOutside = (e: MouseEvent) => {
  if (isOpen.value && dropdownRef.value && !dropdownRef.value.contains(e.target as Node)) {
    close()
  }
}

const handleKeyDown = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && isOpen.value) {
    close()
  }
}

onMounted(() => {
  window.addEventListener('click', handleClickOutside)
  window.addEventListener('keydown', handleKeyDown)
})

onBeforeUnmount(() => {
  window.removeEventListener('click', handleClickOutside)
  window.removeEventListener('keydown', handleKeyDown)
})

defineExpose({ open: () => (isOpen.value = true), close, toggle })
</script>

<template>
  <div ref="dropdownRef" class="w-full relative">
    <!-- Form Label -->
    <label v-if="label" class="block text-sm font-bold text-[#1E293B] dark:text-[#CBD5E1] tracking-tight mb-1.5">
      {{ label }}
      <span v-if="required" class="text-rose-500">*</span>
    </label>

    <!-- Trigger Button: Input-style dengan Icon Chevron & Status Border -->
    <div
      role="combobox"
      :aria-expanded="isOpen"
      :aria-disabled="disabled"
      tabindex="0"
      @click="toggle"
      @keydown.enter.prevent="toggle"
      @keydown.space.prevent="toggle"
      :class="[
        'w-full h-10 bg-white dark:bg-[#1B2431] border rounded-lg px-3.5 text-sm font-medium transition-colors duration-150 flex items-center justify-between relative select-none box-border shrink-0',
        error
          ? 'border-rose-500 focus:ring-2 focus:ring-rose-500/20'
          : isOpen
            ? 'border-[#4880FF] ring-2 ring-[#4880FF]/20 dark:border-[#4880FF]'
            : 'border-[#CBD5E1] dark:border-[#334155] hover:border-slate-400 dark:hover:border-slate-500',
        disabled ? 'opacity-50 cursor-not-allowed bg-slate-50 dark:bg-slate-800/50' : 'cursor-pointer',
        selectClass
      ]"
    >
      <span
        :class="[
          'truncate',
          selectedOption ? 'text-[#202224] dark:text-white font-medium' : 'text-[#94A3B8] font-normal'
        ]"
      >
        {{ displayLabel }}
      </span>

      <!-- Chevron Arrow with Smooth Rotation -->
      <div
        class="absolute right-3 text-[#94A3B8] pointer-events-none flex items-center transition-transform duration-200"
        :class="{ 'rotate-180': isOpen }"
      >
        <AppIcon name="expand_more" :size="18" />
      </div>
    </div>

    <!-- Animated Floating Dropdown Menu Popover -->
    <Transition
      enter-active-class="transition duration-150 ease-out"
      :enter-from-class="placement === 'top' ? 'transform scale-95 opacity-0 translate-y-1' : 'transform scale-95 opacity-0 -translate-y-1'"
      enter-to-class="transform scale-100 opacity-100 translate-y-0"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="transform scale-100 opacity-100 translate-y-0"
      :leave-to-class="placement === 'top' ? 'transform scale-95 opacity-0 translate-y-1' : 'transform scale-95 opacity-0 -translate-y-1'"
    >
      <div
        v-if="isOpen"
        :class="[
          'absolute left-0 bg-white dark:bg-[#1E293B] rounded-xl shadow-xl border border-[#E2E8F0] dark:border-[#334155] p-1.5 space-y-0.5 z-[999] text-sm font-semibold max-h-60 overflow-y-auto custom-scroll will-change-transform',
          placement === 'top' ? 'bottom-full mb-1.5' : 'top-full mt-1.5',
          width || 'w-full'
        ]"
      >
        <!-- If grouped options -->
        <template v-if="computedGroups && computedGroups.length > 0">
          <div v-for="grp in computedGroups" :key="grp.label" class="mb-1.5 last:mb-0">
            <div class="px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-[#64748B] dark:text-[#94A3B8]">
              {{ grp.label }}
            </div>
            <button
              v-for="opt in grp.options"
              :key="opt.value"
              type="button"
              :disabled="opt.disabled"
              @click="selectOption(opt)"
              :class="[
                'w-full px-3 py-2 rounded-lg text-left flex items-center justify-between transition-colors cursor-pointer text-sm',
                String(modelValue) === String(opt.value)
                  ? 'bg-blue-50 dark:bg-blue-950/40 text-[#4880FF] dark:text-[#93C5FD] font-bold'
                  : 'text-[#202224] dark:text-[#E2E8F0] hover:bg-[#F1F5F9] dark:hover:bg-[#334155] font-medium',
                opt.disabled ? 'opacity-40 cursor-not-allowed' : ''
              ]"
            >
              <span class="truncate">{{ opt.label }}</span>
              <AppIcon
                v-if="String(modelValue) === String(opt.value)"
                name="check"
                :size="16"
                class="text-[#4880FF] dark:text-[#93C5FD] shrink-0 ml-2"
              />
            </button>
          </div>
        </template>

        <!-- Standard flat options -->
        <template v-else>
          <div v-if="options.length === 0" class="px-3 py-2 text-center text-sm text-[#94A3B8]">
            Tidak ada opsi
          </div>
          <button
            v-else
            v-for="opt in options"
            :key="opt.value"
            type="button"
            :disabled="opt.disabled"
            @click="selectOption(opt)"
            :class="[
              'w-full px-3 py-2 rounded-lg text-left flex items-center justify-between transition-colors cursor-pointer text-sm',
              String(modelValue) === String(opt.value)
                ? 'bg-blue-50 dark:bg-blue-950/40 text-[#4880FF] dark:text-[#93C5FD] font-bold'
                : 'text-[#202224] dark:text-[#E2E8F0] hover:bg-[#F1F5F9] dark:hover:bg-[#334155] font-medium',
              opt.disabled ? 'opacity-40 cursor-not-allowed' : ''
            ]"
          >
            <span class="truncate">{{ opt.label }}</span>
            <AppIcon
              v-if="String(modelValue) === String(opt.value)"
              name="check"
              :size="16"
              class="text-[#4880FF] dark:text-[#93C5FD] shrink-0 ml-2"
            />
          </button>
        </template>
      </div>
    </Transition>

    <!-- Error message -->
    <p v-if="error" class="text-sm font-semibold text-rose-500 flex items-center gap-1 mt-1">
      <AppIcon name="error" :size="14" />
      {{ error }}
    </p>
  </div>
</template>

<style scoped>
.custom-scroll::-webkit-scrollbar {
  width: 5px;
}
.custom-scroll::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scroll::-webkit-scrollbar-thumb {
  background: rgba(100, 116, 139, 0.2);
  border-radius: 9999px;
}
.custom-scroll::-webkit-scrollbar-thumb:hover {
  background: rgba(100, 116, 139, 0.4);
}
</style>
