<script setup lang="ts">
import { computed } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useFormat } from '@/composables/useFormat'

interface Props {
  label?: string
  totalPrice: number | string
  buttonText: string
  buttonIcon?: string
  loading?: boolean
  disabled?: boolean
  isFixed?: boolean
  buttonVariant?: 'primary' | 'warning'
}

const props = withDefaults(defineProps<Props>(), {
  label: 'Total',
  buttonIcon: '',
  loading: false,
  disabled: false,
  isFixed: true,
  buttonVariant: 'primary',
})

defineEmits<{
  (e: 'click'): void
}>()

const { formatCurrency } = useFormat()

const formattedPrice = computed(() => {
  if (typeof props.totalPrice === 'number') {
    return formatCurrency(props.totalPrice)
  }
  return props.totalPrice
})
</script>

<template>
  <!-- Wrapper Div: Ditinggikan se-tinggi floating cart (h-[88px] sm:h-[92px]) -->
  <div
    :class="[
      isFixed
        ? 'fixed bottom-0 inset-x-0 w-full md:max-w-md mx-auto bg-white dark:bg-[#273142] rounded-t-3xl px-6 pt-6 pb-8 z-40 border-t border-[#E2E8F0]/70 dark:border-[#334155]/70 shadow-[0_-4px_22px_rgba(0,0,0,0.08)]'
        : 'w-full bg-transparent select-none py-1'
    ]"
    class="flex items-center justify-between gap-4 select-none"
  >
    <!-- Kiri: Total Label & Harga (Tetap ukuran semula: text-base sm:text-lg) -->
    <div class="flex flex-col text-left min-w-0 shrink-0">
      <span class="text-[11px] sm:text-xs font-medium text-[#64748B] dark:text-[#94A3B8] leading-tight">
        {{ label }}
      </span>
      <span class="text-lg sm:text-xl font-extrabold text-[#1E293B] dark:text-white tabular-nums tracking-tight mt-0.5 truncate">
        {{ formattedPrice }}
      </span>
    </div>

    <!-- Kanan: Tombol Pill Utama (Ukuran button TETAP SAMA tidak berubah: h-11 sm:h-12) -->
    <button
      type="button"
      :disabled="disabled || loading"
      @click="$emit('click')"
      :class="[
        'h-[52px] sm:h-[54px] flex-1 max-w-[260px] sm:max-w-[285px] rounded-full active:scale-[0.98] disabled:opacity-50 text-white font-bold text-sm sm:text-base tracking-tight shadow-md flex items-center justify-center gap-2 transition-all cursor-pointer shrink-0',
        buttonVariant === 'warning'
          ? 'bg-amber-500 hover:bg-amber-600 active:bg-amber-700 shadow-amber-500/25'
          : 'bg-[#4880FF] hover:bg-[#3971F0] active:bg-[#2F64E0] shadow-[#4880FF]/25'
      ]"
    >
      <AppIcon v-if="buttonIcon" :name="buttonIcon" :size="19" />
      <span v-if="!loading" class="truncate">{{ buttonText }}</span>
      <span v-else>Memproses...</span>
    </button>
  </div>
</template>
