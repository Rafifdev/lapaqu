<script setup lang="ts">
interface Props {
  modelValue: boolean
  label?: string
  disabled?: boolean
  description?: string
  labelClass?: string
  descriptionClass?: string
  color?: 'primary' | 'blue' | 'success'
}

const emit = defineEmits<{
  (e: 'update:modelValue', val: boolean): void
  (e: 'change', val: boolean): void
}>()

const props = withDefaults(defineProps<Props>(), {
  disabled: false,
  color: 'primary',
})

const toggle = () => {
  if (props.disabled) return
  const newVal = !props.modelValue
  emit('update:modelValue', newVal)
  emit('change', newVal)
}
</script>

<template>
  <div class="flex items-center justify-between cursor-pointer select-none" @click="toggle">
    <div v-if="label" class="mr-3">
      <p :class="labelClass || 'text-[#1E293B] dark:text-white'">{{ label }}</p>
      <p v-if="description" :class="descriptionClass || 'text-sm text-[#64748B] dark:text-[#94A3B8]'">{{ description }}
      </p>
    </div>
    <div :class="[
      'w-12 h-6 flex items-center rounded-full p-1 transition-colors duration-200 ease-in-out shrink-0',
      modelValue
        ? (color === 'success' ? 'bg-[#00B69B]' : 'bg-[#4880FF]')
        : 'bg-[#CBD5E1] dark:bg-[#334155]',
      disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer',
    ]">
      <div :class="[
        'bg-white w-4 h-4 rounded-full shadow-md transform transition-transform duration-200 ease-in-out',
        modelValue ? 'translate-x-6' : 'translate-x-0',
      ]" />
    </div>
  </div>
</template>
