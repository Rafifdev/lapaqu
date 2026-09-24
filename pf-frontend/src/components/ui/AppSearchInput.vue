<script setup lang="ts">
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useDashboardI18n } from '@/i18n'

interface Props {
  modelValue: string
  placeholder?: string
  borderless?: boolean
  rounded?: 'lg' | 'xl' | 'full'
  size?: 'sm' | 'md'
  icon?: string
  hideSearchIcon?: boolean
  inputClass?: string
  debounce?: number
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: 'Cari sesuatu...',
  borderless: false,
  rounded: 'xl',
  size: 'md',
  icon: 'search',
  hideSearchIcon: false,
  inputClass: '',
  debounce: 300,
  disabled: false,
})

const { t } = useDashboardI18n()

const computedPlaceholder = computed(() => {
  if (props.placeholder && props.placeholder !== 'Cari sesuatu...') return props.placeholder
  return t('common.searchSomething', 'Cari sesuatu...')
})

const emit = defineEmits<{
  (e: 'update:modelValue', val: string): void
  (e: 'clear'): void
}>()

const inputEl = ref<HTMLInputElement | null>(null)
const innerValue = ref(props.modelValue ?? '')
let debounceTimer: ReturnType<typeof setTimeout> | null = null

watch(
  () => props.modelValue,
  (newVal) => {
    const val = newVal ?? ''
    if (!debounceTimer && innerValue.value !== val) {
      innerValue.value = val
    }
  }
)

const handleInput = (e: Event) => {
  const target = e.target as HTMLInputElement
  const val = target.value
  innerValue.value = val

  if (debounceTimer) {
    clearTimeout(debounceTimer)
    debounceTimer = null
  }

  const delay = props.debounce ?? 300
  if (delay > 0) {
    debounceTimer = setTimeout(() => {
      debounceTimer = null
      emit('update:modelValue', val)
    }, delay)
  } else {
    emit('update:modelValue', val)
  }
}

const handleClear = () => {
  if (debounceTimer) {
    clearTimeout(debounceTimer)
    debounceTimer = null
  }
  innerValue.value = ''
  emit('update:modelValue', '')
  emit('clear')
}

onBeforeUnmount(() => {
  if (debounceTimer) {
    clearTimeout(debounceTimer)
    debounceTimer = null
  }
})

defineExpose({
  focus: () => inputEl.value?.focus(),
  inputEl,
})
</script>

<template>
  <div class="relative w-full max-w-sm">
    <div
      v-if="!hideSearchIcon"
      :class="[
        'absolute top-1/2 -translate-y-1/2 text-[#64748B] dark:text-[#94A3B8] pointer-events-none flex items-center',
        size === 'sm' ? 'left-2.5' : 'left-3.5'
      ]"
    >
      <AppIcon :name="icon || 'search'" :size="size === 'sm' ? 16 : 18" />
    </div>
    <input
      ref="inputEl"
      type="text"
      :value="innerValue"
      :placeholder="computedPlaceholder"
      :disabled="disabled"
      @input="handleInput"
      :class="[
        'w-full font-semibold text-[#1E293B] dark:text-white transition-all placeholder:font-normal placeholder:text-[#94A3B8] dark:placeholder:text-[#64748B] focus:outline-none',
        size === 'sm' ? 'h-10 text-sm sm:text-base' : 'h-10.5 text-sm sm:text-base',
        hideSearchIcon
          ? 'pl-3.5 pr-8'
          : (size === 'sm' ? 'pl-9 pr-8' : 'pl-10.5 pr-9'),
        props.borderless
          ? 'bg-[#F1F5F9] dark:bg-[#1E293B] border-0 focus:outline-none'
          : 'bg-[#F8FAFC] dark:bg-[#1E293B] border border-[#CBD5E1] dark:border-[#334155] focus:outline-none',
        props.rounded === 'lg' ? 'rounded-lg' : (props.rounded === 'full' ? 'rounded-full' : 'rounded-xl'),
        disabled ? 'opacity-60 cursor-not-allowed' : '',
        props.inputClass,
      ]"
    />
    <button
      v-if="innerValue"
      type="button"
      @click="handleClear"
      :class="[
        'absolute top-1/2 -translate-y-1/2 text-[#64748B] hover:text-[#1E293B] dark:text-[#94A3B8] dark:hover:text-white cursor-pointer flex items-center',
        size === 'sm' ? 'right-2' : 'right-3'
      ]"
    >
      <AppIcon name="close" :size="size === 'sm' ? 14 : 16" />
    </button>
  </div>
</template>
