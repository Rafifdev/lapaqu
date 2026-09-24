<script setup lang="ts">
import { computed } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useFormat } from '@/composables/useFormat'
import type { MenuItem } from '@/types'

interface Props {
  item?: MenuItem
  quantity?: number
  subtitle?: string
  showStepper?: boolean
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  quantity: 0,
  showStepper: true,
  subtitle: undefined,
  loading: false,
})

const emit = defineEmits<{
  (e: 'increment', item: MenuItem): void
  (e: 'decrement', item: MenuItem): void
  (e: 'click', item: MenuItem): void
}>()

const { formatCurrency, formatNumber } = useFormat()

// Tampilkan deskripsi menu makanan
const displaySubtitle = computed(() => {
  if (props.subtitle) return props.subtitle
  if (props.item?.description && props.item.description.trim()) {
    return props.item.description
  }
  return 'Menu hidangan lezat pilihan'
})

// Hitung porsi riil: jika stok/bahan habis atau status tidak tersedia, tampilkan 0 Porsi.
// Berkurang secara dinamis saat menu masuk ke list pesanan (props.quantity).
const displayPortion = computed(() => {
  if (!props.item) return '0 Porsi'
  if (props.item.isAvailable === false) return '0 Porsi'
  if (props.item.maxServings === null || props.item.maxServings === undefined) return '0 Porsi'
  if (props.item.maxServings <= 0) return '0 Porsi'

  const remainingServings = Math.max(0, props.item.maxServings - (props.quantity || 0))
  return `${formatNumber(remainingServings)} Porsi`
})

const isMaxStockReached = computed(() => {
  if (!props.item) return false
  if (props.item.isAvailable === false) return true
  if (props.item.maxServings !== null && props.item.maxServings !== undefined) {
    return props.quantity >= props.item.maxServings
  }
  return false
})

const handleCardClick = () => {
  if (props.loading || !props.item) return
  emit('click', props.item)
  if (props.showStepper && props.item.isAvailable !== false && !isMaxStockReached.value) {
    emit('increment', props.item)
  }
}

const handleImageError = (e: Event) => {
  const target = e.target as HTMLImageElement
  if (target) {
    target.src = 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=500&auto=format&fit=crop&q=80'
  }
}
</script>

<template>
  <!-- Skeleton Loading State (Default Built-in Skeleton) -->
  <div
    v-if="loading"
    class="relative h-[275px] md:h-[285px] lg:h-[285px] xl:h-[275px] w-full bg-white dark:bg-[#273142] rounded-2xl shadow-xs flex flex-col justify-between overflow-hidden animate-pulse"
  >
    <!-- Top Image Skeleton -->
    <div class="h-[148px] md:h-[155px] lg:h-[155px] xl:h-[148px] w-full bg-[#E2E8F0] dark:bg-[#334155] shrink-0 rounded-t-2xl relative">
      <div class="absolute top-2.5 left-2.5 h-6 w-16 bg-white/40 dark:bg-slate-600/40 rounded-full"></div>
    </div>

    <!-- Bottom Content Skeleton (Design System 16dp / 4dp / 8dp) -->
    <div class="flex-1 px-4 pt-3 pb-3.5 flex flex-col justify-between bg-white dark:bg-[#273142] text-left">
      <div class="space-y-1">
        <div class="h-4 w-3/4 bg-[#E2E8F0] dark:bg-[#334155] rounded"></div>
        <div class="h-3 w-1/2 bg-[#E2E8F0] dark:bg-[#334155] rounded mt-1"></div>
      </div>
      <div class="flex items-center justify-between mt-2 pt-1">
        <div class="h-5 w-20 bg-[#E2E8F0] dark:bg-[#334155] rounded-md"></div>
        <div class="h-7 w-16 bg-[#E2E8F0] dark:bg-[#334155] rounded-full"></div>
      </div>
    </div>
  </div>

  <!-- Real Menu Card -->
  <div
    v-else-if="item"
    @click="handleCardClick"
    class="relative h-[275px] md:h-[285px] lg:h-[285px] xl:h-[275px] w-full bg-white dark:bg-[#273142] rounded-2xl border border-[#EAEAEA] dark:border-[#313D4F] shadow-xs flex flex-col justify-between overflow-hidden cursor-pointer select-none"
  >
    <!-- 1. Top Image (Dominant, appetizing image height) -->
    <div
      class="h-[148px] md:h-[155px] lg:h-[155px] xl:h-[148px] w-full bg-[#F8FAFC] dark:bg-[#1B2431] overflow-hidden shrink-0 relative rounded-t-2xl"
    >
      <img
        :src="item.imageUrl || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=500&auto=format&fit=crop&q=80'"
        :alt="item.name"
        draggable="false"
        @error="handleImageError"
        class="w-full h-full object-cover pointer-events-none select-none"
      />
      <div
        v-if="!item.isAvailable"
        class="absolute inset-0 bg-black/50 backdrop-blur-[1px] flex items-center justify-center pointer-events-none"
      >
        <span class="text-xs font-black text-white px-2.5 py-0.5 rounded-full bg-rose-500 uppercase tracking-wider">
          Habis
        </span>
      </div>

      <!-- Top-Left Badge: Portion Count with Transparent Blur Glassmorphism (Tanpa border & titik) -->
      <div class="absolute top-2.5 left-2.5 z-10 pointer-events-none">
        <span
          class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold text-white bg-black/45 dark:bg-black/60 backdrop-blur-md shadow-xs tabular-nums tracking-wide"
        >
          {{ displayPortion }}
        </span>
      </div>

      <!-- Top-Right Slot (Custom Actions/Badge) -->
      <div v-if="$slots['top-right']" class="absolute top-2.5 right-2.5 z-10">
        <slot name="top-right" />
      </div>


    </div>

    <!-- 2. Bottom Content Div (Design System: Image ke Title 16dp, Title ke Rating/Sub-teks 4dp, Title/Sub-teks ke Harga 8dp) -->
    <div class="flex-1 px-4 pt-3 pb-3.5 flex flex-col justify-between bg-white dark:bg-[#273142] relative text-left">
      <!-- Title & Subtitle (Left-aligned) -->
      <div class="flex flex-col text-left w-full min-w-0">
        <!-- Food Title (Image ke Title: 16dp via pt-4) -->
        <h3
          class="text-base font-bold text-[#1E293B] dark:text-white line-clamp-1 leading-snug text-left"
          :title="item.name"
        >
          {{ item.name }}
        </h3>

        <!-- Rating/Sub-teks (Title ke Rating/Sub-teks: 4dp via mt-1) -->
        <p class="text-sm text-[#94A3B8] font-medium text-left line-clamp-2 leading-snug mt-1" :title="displaySubtitle">
          {{ displaySubtitle }}
        </p>
      </div>

      <!-- Bottom Row: Price on Left, Stepper or Actions on Right (Title/Sub-teks ke Harga: 8dp via mt-2) -->
      <div class="flex items-center justify-between gap-2 mt-2 pt-1 w-full">
        <!-- Price -->
        <span class="text-base font-bold text-[#1E293B] dark:text-white tabular-nums">
          {{ formatCurrency(item.price) }}
        </span>

        <!-- Custom Actions Slot (e.g. Menu Management Edit / Delete) -->
        <div v-if="$slots.actions" class="flex items-center shrink-0 pointer-events-auto" @click.stop>
          <slot name="actions" />
        </div>

        <!-- Stepper Controls (Minus & Plus Individual Circular Buttons with shadow-xs) -->
        <div v-else-if="showStepper" class="hidden 2xl:flex items-center gap-2 shrink-0 pointer-events-auto">
          <!-- Minus Button [ - ] -->
          <button
            type="button"
            @click.stop="$emit('decrement', item)"
            :disabled="quantity === 0"
            class="w-7 h-7 rounded-full bg-[#F1F4F9] dark:bg-[#323D4E] shadow-xs flex items-center justify-center text-[#202224] dark:text-white hover:bg-[#E2E8F0] dark:hover:bg-[#3B4758] transition-all cursor-pointer active:scale-90 disabled:opacity-30 disabled:cursor-not-allowed"
            title="Kurangi"
          >
            <AppIcon name="remove" :size="15" />
          </button>

          <!-- Quantity Value -->
          <span class="text-sm font-bold text-[#202224] dark:text-white px-1 text-center tabular-nums min-w-[14px]">
            {{ quantity }}
          </span>

          <!-- Plus Button [ + ] -->
          <button
            type="button"
            @click.stop="!isMaxStockReached && $emit('increment', item)"
            :disabled="!item.isAvailable || isMaxStockReached"
            class="w-7 h-7 rounded-full bg-[#F1F4F9] dark:bg-[#323D4E] shadow-xs flex items-center justify-center text-[#202224] dark:text-white hover:bg-[#E2E8F0] dark:hover:bg-[#3B4758] transition-all cursor-pointer active:scale-90 disabled:opacity-30 disabled:cursor-not-allowed"
            :title="isMaxStockReached ? 'Stok bahan tidak cukup untuk porsi tambahan' : 'Tambah'"
          >
            <AppIcon name="add" :size="15" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Prevent image and card selection on rapid double click */
img {
  -webkit-user-drag: none;
  -khtml-user-drag: none;
  -moz-user-drag: none;
  -o-user-drag: none;
  user-select: none;
  -webkit-user-select: none;
}
</style>
