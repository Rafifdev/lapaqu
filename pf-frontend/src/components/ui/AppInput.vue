<script setup lang="ts">
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'

interface Props {
  modelValue?: string | number | null
  label?: string
  placeholder?: string
  type?: string
  icon?: string
  suffixIcon?: string
  error?: string
  hint?: string
  required?: boolean
  disabled?: boolean
  prefix?: string
  suffix?: string
  maxlength?: number | string
  min?: number | string
  max?: number | string
  step?: number | string
  clearable?: boolean
  inputClass?: string
  debounce?: number
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: '',
  type: 'text',
  required: false,
  disabled: false,
  clearable: false,
  debounce: 500, // Explicit default 500ms debounce
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: any): void
  (e: 'clear'): void
}>()

const innerValue = ref(props.modelValue ?? '')
let debounceTimer: any = null

watch(
  () => props.modelValue,
  (newVal) => {
    const val = newVal ?? ''
    // Only update innerValue from props if not currently waiting for a debounce timer
    if (!debounceTimer && innerValue.value !== val) {
      innerValue.value = val
    }
  }
)

const debounceTime = computed(() => {
  if (typeof props.debounce === 'number' && props.debounce >= 0) {
    return props.debounce
  }
  return 500
})

const handleInput = (e: Event) => {
  const target = e.target as HTMLInputElement
  const val = target.value
  innerValue.value = val

  if (debounceTimer) {
    clearTimeout(debounceTimer)
    debounceTimer = null
  }

  const delay = debounceTime.value
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
</script>

<template>
  <div class="space-y-1.5 w-full">
    <label v-if="label" class="block text-xs sm:text-sm font-bold text-[#1E293B] dark:text-[#CBD5E1] tracking-tight">
      {{ label }}
      <span v-if="required" class="text-rose-500">*</span>
    </label>

    <div class="relative flex items-center">
      <div v-if="icon" class="absolute left-3.5 text-[#94A3B8] pointer-events-none flex items-center">
        <AppIcon :name="icon" :size="18" />
      </div>

      <span v-if="prefix" class="absolute left-3.5 text-sm font-bold dark:text-[#ffffff] pointer-events-none">
        {{ prefix }}
      </span>

      <input
        :type="type"
        :value="innerValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :required="required"
        :maxlength="maxlength"
        :min="min"
        :max="max"
        :step="step"
        @input="handleInput"
        :class="[
          'w-full bg-white dark:bg-[#1B2431] border rounded-xl py-2.5 text-sm font-semibold text-[#1E293B] dark:text-white placeholder-[#94A3B8] placeholder:font-normal transition-all focus:outline-none disabled:opacity-50 disabled:bg-[#F1F5F9] dark:disabled:bg-[#334155]',
          error
            ? 'border-rose-500 '
            : 'border-[#CBD5E1] dark:border-[#334155] ',
          icon ? 'pl-10' : prefix ? 'pl-11' : 'pl-3.5',
          (clearable && innerValue) || suffix || suffixIcon ? 'pr-10' : 'pr-3.5',
          inputClass
        ]"
      />

      <button
        v-if="clearable && innerValue && !disabled"
        type="button"
        @click="handleClear"
        class="absolute right-3 text-[#94A3B8] hover:text-[#202224] dark:hover:text-white cursor-pointer flex items-center p-0.5 rounded transition-colors"
      >
        <AppIcon name="close" :size="16" />
      </button>

      <span v-else-if="suffix" class="absolute right-3.5 text-sm font-medium text-[#94A3B8] pointer-events-none">
        {{ suffix }}
      </span>

      <div v-else-if="suffixIcon" class="absolute right-3.5 text-[#94A3B8] pointer-events-none flex items-center">
        <AppIcon :name="suffixIcon" :size="18" />
      </div>
    </div>

    <p v-if="error" class="text-xs font-semibold text-rose-500 flex items-center gap-1 mt-1">
      <AppIcon name="error" :size="14" />
      {{ error }}
    </p>
    <p v-else-if="hint" class="text-xs text-[#64748B] dark:text-[#94A3B8]">
      {{ hint }}
    </p>
  </div>
</template>
