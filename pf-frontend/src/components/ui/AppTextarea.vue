<script setup lang="ts">
import AppIcon from '@/components/ui/AppIcon.vue'

interface Props {
  modelValue?: string | null
  label?: string
  placeholder?: string
  rows?: number | string
  error?: string
  hint?: string
  required?: boolean
  disabled?: boolean
  maxlength?: number | string
  textareaClass?: string
}

withDefaults(defineProps<Props>(), {
  modelValue: '',
  rows: 3,
  required: false,
  disabled: false,
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()

const handleInput = (e: Event) => {
  const target = e.target as HTMLTextAreaElement
  emit('update:modelValue', target.value)
}
</script>

<template>
  <div class="space-y-1.5 w-full">
    <label v-if="label" class="block text-xs sm:text-sm font-bold text-[#1E293B] dark:text-[#CBD5E1] tracking-tight">
      {{ label }}
      <span v-if="required" class="text-rose-500">*</span>
    </label>

    <textarea
      :value="modelValue ?? ''"
      :placeholder="placeholder"
      :rows="rows"
      :disabled="disabled"
      :required="required"
      :maxlength="maxlength"
      @input="handleInput"
      :class="[
        'w-full px-3.5 py-2.5 text-sm font-medium text-[#1E293B] dark:text-white bg-white dark:bg-[#1B2431] border rounded-xl focus:outline-none transition-all placeholder:text-[#94A3B8] resize-none disabled:opacity-50 disabled:bg-[#F1F5F9] dark:disabled:bg-[#334155]',
        error
          ? 'border-rose-500 '
          : 'border-[#CBD5E1] dark:border-[#334155] ',
        textareaClass
      ]"
    />

    <p v-if="error" class="text-xs font-semibold text-rose-500 flex items-center gap-1 mt-1">
      <AppIcon name="error" :size="14" />
      {{ error }}
    </p>
    <p v-else-if="hint" class="text-xs text-[#64748B] dark:text-[#94A3B8]">
      {{ hint }}
    </p>
  </div>
</template>
