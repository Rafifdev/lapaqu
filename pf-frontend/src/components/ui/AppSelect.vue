<script setup lang="ts">
import { computed } from 'vue'
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
  modelValue: string | number
  options?: Option[]
  groups?: OptionGroup[]
  placeholder?: string
  label?: string
  error?: string
  disabled?: boolean
  required?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  disabled: false,
  required: false,
  options: () => [],
})

defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()

// Support both direct groups or options with group field
const computedGroups = computed(() => {
  if (props.groups && props.groups.length > 0) {
    return props.groups
  }
  if (!props.options || props.options.length === 0) return []

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
</script>

<template>
  <div class="space-y-1.5 w-full">
    <label v-if="label" class="block text-sm font-bold text-[#1E293B] dark:text-[#CBD5E1] tracking-tight">
      {{ label }}
      <span v-if="required" class="text-rose-500">*</span>
    </label>

    <div class="relative flex items-center">
      <select
        :value="modelValue"
        :disabled="disabled"
        :required="required"
        @change="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
        :class="[
          'w-full appearance-none bg-white dark:bg-[#1B2431] border rounded-lg py-2.5 pl-3.5 pr-10 text-sm font-medium text-[#1E293B] dark:text-white transition-all focus:outline-none disabled:opacity-50 cursor-pointer',
          error
            ? 'border-rose-500 '
            : 'border-[#CBD5E1] dark:border-[#475569] ',
        ]"
      >
        <option v-if="placeholder" value="" disabled selected class="text-gray-400">
          {{ placeholder }}
        </option>

        <!-- If using optgroup -->
        <template v-if="computedGroups && computedGroups.length > 0">
          <optgroup
            v-for="grp in computedGroups"
            :key="grp.label"
            :label="grp.label"
            class="font-bold text-xs text-[#64748B] dark:text-[#94A3B8] dark:bg-[#1B2431]"
          >
            <option
              v-for="opt in grp.options"
              :key="opt.value"
              :value="opt.value"
              :disabled="opt.disabled"
              class="font-normal text-sm text-[#1E293B] dark:text-white dark:bg-[#1B2431]"
            >
              {{ opt.label }}
            </option>
          </optgroup>
        </template>

        <!-- Standard flat options -->
        <template v-else>
          <option
            v-for="opt in options"
            :key="opt.value"
            :value="opt.value"
            :disabled="opt.disabled"
            class="dark:bg-[#1B2431]"
          >
            {{ opt.label }}
          </option>
        </template>
      </select>

      <div class="absolute right-3 text-[#94A3B8] pointer-events-none flex items-center">
        <AppIcon name="expand_more" :size="18" />
      </div>
    </div>

    <p v-if="error" class="text-sm font-semibold text-rose-500 flex items-center gap-1 mt-1">
      <AppIcon name="error" :size="14" />
      {{ error }}
    </p>
  </div>
</template>
