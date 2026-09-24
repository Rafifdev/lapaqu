<script setup lang="ts">
import { computed } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useDashboardI18n } from '@/i18n'
import emptyMenuIllustration from '@/assets/empty_state/empty-menu.svg'
import emptyOrderIllustration from '@/assets/empty_state/empty-order.svg'
import emptyRiwayatIllustration from '@/assets/empty_state/empty-riwayat.svg'
import emptyTableIllustration from '@/assets/empty_state/empty-table.svg'

type SvgPreset = 'menu' | 'order' | 'riwayat' | 'table'

interface Props {
  // SVG Illustration preset atau custom url
  svgType?: SvgPreset
  illustration?: string

  // Lucide / Material Icon fallback (jika tidak pakai ilustrasi svg)
  icon?: string

  title?: string
  description?: string
  actionLabel?: string
  actionIcon?: string
}

const props = withDefaults(defineProps<Props>(), {
  title: 'Tidak Ada Data',
  actionIcon: 'restaurant_menu',
})

const { t } = useDashboardI18n()

const displayTitle = computed(() => {
  if (props.title && props.title !== 'Tidak Ada Data') return props.title
  return t('common.noData', 'Tidak Ada Data')
})

defineEmits<{
  (e: 'action'): void
}>()

const illustrationSrc = computed(() => {
  if (props.illustration) return props.illustration
  if (props.svgType === 'menu') return emptyMenuIllustration
  if (props.svgType === 'order') return emptyOrderIllustration
  if (props.svgType === 'riwayat') return emptyRiwayatIllustration
  if (props.svgType === 'table') return emptyTableIllustration
  return null
})
</script>

<template>
  <div class="flex flex-col items-center justify-center text-center p-6 sm:p-8 md:p-10 select-none">
    <!-- SVG Illustration (Jika Disediakan) -->
    <div v-if="illustrationSrc" class="relative flex items-center justify-center mb-2 pointer-events-none">
      <img
        :src="illustrationSrc"
        :alt="title"
        class="w-44 h-44 sm:w-52 sm:h-52 md:w-56 md:h-56 object-contain drop-shadow-xs"
      />
    </div>

    <!-- Fallback Icon Box jika tidak memakai SVG -->
    <div
      v-else-if="icon"
      class="w-16 h-16 rounded-2xl bg-[#4880FF]/10 dark:bg-[#4880FF]/20 text-[#4880FF] flex items-center justify-center mb-4"
    >
      <AppIcon :name="icon" :size="32" />
    </div>

    <!-- Title -->
    <h4 class="text-base sm:text-lg font-bold text-[#1E293B] dark:text-white tracking-tight">
      {{ displayTitle }}
    </h4>

    <!-- Description -->
    <p v-if="description" class="text-sm text-[#64748B] dark:text-[#94A3B8] font-medium max-w-xs mt-1.5 mb-6 leading-relaxed">
      {{ description }}
    </p>

    <!-- Action Button -->
    <button
      v-if="actionLabel"
      type="button"
      @click="$emit('action')"
      class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-[#4880FF] hover:bg-[#3971F0] text-white text-sm font-bold shadow-md shadow-[#4880FF]/25 transition-all active:scale-95 cursor-pointer"
    >
      <AppIcon :name="actionIcon" :size="18" />
      <span>{{ actionLabel }}</span>
    </button>
  </div>
</template>
