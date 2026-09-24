<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { ChevronDown, Check } from 'lucide-vue-next'

export interface FilterOption {
  value: string | number
  label: string
}

interface Props {
  modelValue: string | number
  options: FilterOption[]
  placeholder?: string
  width?: string
  align?: 'left' | 'right'
  variant?: 'pill' | 'white'
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: 'Pilih...',
  width: 'w-52',
  align: 'left',
  variant: 'pill',
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: string | number): void
  (e: 'change', value: string | number): void
}>()

const isOpen = ref(false)
const dropdownRef = ref<HTMLElement | null>(null)

// Local value untuk reaksi seketika 0ms saat opsi diklik (tanpa tunggu async parent)
const localValue = ref<string | number>(props.modelValue)
watch(() => props.modelValue, (v) => {
  localValue.value = v
})

const selectedLabel = computed(() => {
  const found = props.options.find((o) => o.value === localValue.value)
  return found ? found.label : props.placeholder
})

const toggle = () => {
  isOpen.value = !isOpen.value
}

const close = () => {
  isOpen.value = false
}

const selectOption = (val: string | number) => {
  localValue.value = val // Langsung set aktif seketika!
  close()
  emit('update:modelValue', val)
  emit('change', val)
}

const handleClickOutside = (e: MouseEvent) => {
  if (isOpen.value && dropdownRef.value && !dropdownRef.value.contains(e.target as Node)) {
    close()
  }
}

onMounted(() => {
  window.addEventListener('click', handleClickOutside)
})

onBeforeUnmount(() => {
  window.removeEventListener('click', handleClickOutside)
})

defineExpose({ open: () => (isOpen.value = true), close, toggle })
</script>

<template>
  <div ref="dropdownRef" class="relative inline-flex">
    <!-- Trigger Button: Tanpa stroke/border, hanya background putih + shadow-xs -->
    <button
      type="button"
      @click="toggle"
      :class="[
        'flex items-center justify-between gap-2.5 text-sm rounded-lg cursor-pointer transition-all duration-200 focus:outline-none whitespace-nowrap',
        variant === 'white'
          ? 'bg-white dark:bg-[#273142] border-0 text-[#202224] dark:text-white font-semibold px-3.5 py-2 shadow-sm hover:shadow-md'
          : 'bg-transparent hover:bg-white/60 dark:hover:bg-[#273142]/60 text-[#64748B] dark:text-[#94A3B8] font-bold px-3.5 py-2'
      ]"
    >
      <span>{{ selectedLabel }}</span>
      <ChevronDown
        :class="[
          'w-4 h-4 transition-transform duration-200 shrink-0',
          variant === 'white' ? 'text-[#202224]/70 dark:text-white/70' : 'text-[#94A3B8]',
          { 'rotate-180': isOpen }
        ]"
      />
    </button>

    <!-- Animated Floating Menu Popover (Style seperti di Riwayat Order) -->
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="transform scale-95 opacity-0 -translate-y-1"
      enter-to-class="transform scale-100 opacity-100 translate-y-0"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="transform scale-100 opacity-100 translate-y-0"
      leave-to-class="transform scale-95 opacity-0 -translate-y-1"
    >
      <div
        v-if="isOpen"
        :class="[
          'absolute top-full mt-1.5 bg-white dark:bg-[#1E293B] rounded-xl shadow-xl border border-[#E2E8F0] dark:border-[#334155] p-1.5 space-y-1 z-50 text-sm font-semibold will-change-transform',
          align === 'right' ? 'right-0 origin-top-right' : 'left-0 origin-top-left',
          width,
        ]"
      >
        <button
          v-for="opt in options"
          :key="opt.value"
          type="button"
          @click="selectOption(opt.value)"
          class="w-full px-3 py-2 rounded-lg text-left flex items-center justify-between transition-colors cursor-pointer whitespace-nowrap"
          :class="
            localValue === opt.value
              ? 'bg-[#4880FF]/10 text-[#4880FF] font-bold'
              : 'text-[#202224] dark:text-[#E2E8F0] hover:bg-[#F1F5F9] dark:hover:bg-[#334155]'
          "
        >
          <span>{{ opt.label }}</span>
          <Check v-if="localValue === opt.value" class="w-4 h-4 text-[#4880FF] shrink-0 ml-2" />
        </button>
      </div>
    </Transition>
  </div>
</template>
