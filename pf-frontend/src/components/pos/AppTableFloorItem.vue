<script setup lang="ts">
export type TableStatus = 'available' | 'reserved' | 'filled'

interface Props {
  code?: string
  status?: TableStatus
  isLarge?: boolean
  loading?: boolean
}

withDefaults(defineProps<Props>(), {
  code: '',
  status: 'available',
  isLarge: false,
  loading: false,
})

defineEmits<{
  (e: 'click'): void
}>()
</script>

<template>
  <!-- Skeleton Loading State (Default Built-in Skeleton) -->
  <div
    v-if="loading"
    :class="[
      isLarge ? 'col-span-2 max-w-[192px] sm:max-w-[236px] md:max-w-[256px] xl:max-w-[286px]' : 'col-span-1 max-w-[90px] sm:max-w-[110px] md:max-w-[120px] xl:max-w-[135px]',
      'flex flex-col items-center justify-center py-1 w-full animate-pulse'
    ]"
  >
    <!-- Top Chairs Skeleton -->
    <div class="w-full flex items-center justify-between px-2.5 sm:px-4 md:px-5 mb-1.5">
      <div v-for="c in (isLarge ? 4 : 2)" :key="'skel-top-' + c" class="flex flex-col items-center gap-0.5">
        <div class="w-4 sm:w-5 md:w-6 h-1.5 rounded-full bg-[#E2E8F0] dark:bg-[#334155]" />
        <div class="w-4 sm:w-5 md:w-6 h-2 sm:h-2.5 rounded-t-sm bg-[#E2E8F0] dark:bg-[#334155]" />
      </div>
    </div>

    <!-- Center Table Body Skeleton -->
    <div class="w-full h-14 sm:h-16 md:h-18 xl:h-20 rounded-2xl bg-[#E2E8F0] dark:bg-[#334155]" />

    <!-- Bottom Chairs Skeleton -->
    <div class="w-full flex items-center justify-between px-2.5 sm:px-4 md:px-5 mt-1.5">
      <div v-for="c in (isLarge ? 4 : 2)" :key="'skel-bot-' + c" class="flex flex-col items-center gap-0.5">
        <div class="w-4 sm:w-5 md:w-6 h-2 sm:h-2.5 rounded-b-sm bg-[#E2E8F0] dark:bg-[#334155]" />
        <div class="w-4 sm:w-5 md:w-6 h-1.5 rounded-full bg-[#E2E8F0] dark:bg-[#334155]" />
      </div>
    </div>
  </div>

  <!-- BIG TABLE (RESPONSIVE & SYMMETRICAL ON TABLET + DESKTOP) -->
  <div
    v-else-if="isLarge"
    @click="$emit('click')"
    class="col-span-2 flex flex-col items-center justify-center cursor-pointer py-1 w-full max-w-[192px] sm:max-w-[236px] md:max-w-[256px] xl:max-w-[286px]"
  >
    <!-- TOP CHAIRS (4 chairs with space-between) -->
    <div class="w-full flex items-center justify-between px-2.5 sm:px-4 md:px-5 mb-1.5">
      <div v-for="c in 4" :key="'top-' + c" class="flex flex-col items-center gap-0.5">
        <div :class="[
          'w-4 sm:w-5 md:w-6 h-1.5 rounded-full transition-colors duration-200',
          status === 'filled' ? 'bg-[#0F172A]' : status === 'reserved' ? 'bg-[#1D4ED8]' : 'bg-[#94A3B8] dark:bg-[#64748B]'
        ]" />
        <div :class="[
          'w-4 sm:w-5 md:w-6 h-2 sm:h-2.5 rounded-t-sm transition-colors duration-200',
          status === 'filled' ? 'bg-[#334155]' : status === 'reserved' ? 'bg-[#60A5FA]' : 'bg-[#CBD5E1] dark:bg-[#475569]'
        ]" />
      </div>
    </div>

    <!-- CENTER BIG TABLE BODY -->
    <div
      :class="[
        'w-full h-14 sm:h-16 md:h-18 xl:h-20 rounded-2xl flex items-center justify-center font-black text-sm sm:text-base md:text-lg tracking-tight transition-all duration-200 shadow-2xs tabular-nums',
        status === 'filled'
          ? 'bg-[#0F172A] text-white shadow-xs'
          : status === 'reserved'
          ? 'bg-[#4880FF] text-white shadow-sm shadow-[#4880FF]/25'
          : 'bg-[#E2E8F0] dark:bg-[#334155] text-[#1E293B] dark:text-white border border-[#CBD5E1]/40 dark:border-transparent'
      ]"
    >
      {{ code }}
    </div>

    <!-- BOTTOM CHAIRS (4 chairs with space-between) -->
    <div class="w-full flex items-center justify-between px-2.5 sm:px-4 md:px-5 mt-1.5">
      <div v-for="c in 4" :key="'bot-' + c" class="flex flex-col items-center gap-0.5">
        <div :class="[
          'w-4 sm:w-5 md:w-6 h-2 sm:h-2.5 rounded-b-sm transition-colors duration-200',
          status === 'filled' ? 'bg-[#334155]' : status === 'reserved' ? 'bg-[#60A5FA]' : 'bg-[#CBD5E1] dark:bg-[#475569]'
        ]" />
        <div :class="[
          'w-4 sm:w-5 md:w-6 h-1.5 rounded-full transition-colors duration-200',
          status === 'filled' ? 'bg-[#0F172A]' : status === 'reserved' ? 'bg-[#1D4ED8]' : 'bg-[#94A3B8] dark:bg-[#64748B]'
        ]" />
      </div>
    </div>
  </div>

  <!-- NORMAL TABLE (RESPONSIVE & SYMMETRICAL ON TABLET + DESKTOP) -->
  <div
    v-else
    @click="$emit('click')"
    class="col-span-1 flex flex-col items-center justify-center cursor-pointer py-1 w-full max-w-[90px] sm:max-w-[110px] md:max-w-[120px] xl:max-w-[135px]"
  >
    <!-- TOP CHAIRS (2 chairs) -->
    <div class="flex items-center justify-center gap-2 sm:gap-2.5 md:gap-3 mb-1.5 w-full">
      <div v-for="c in 2" :key="'top-' + c" class="flex flex-col items-center gap-0.5">
        <div :class="[
          'w-4 sm:w-5 md:w-6 h-1.5 rounded-full transition-colors duration-200',
          status === 'filled' ? 'bg-[#0F172A]' : status === 'reserved' ? 'bg-[#1D4ED8]' : 'bg-[#94A3B8] dark:bg-[#64748B]'
        ]" />
        <div :class="[
          'w-4 sm:w-5 md:w-6 h-2 sm:h-2.5 rounded-t-sm transition-colors duration-200',
          status === 'filled' ? 'bg-[#334155]' : status === 'reserved' ? 'bg-[#60A5FA]' : 'bg-[#CBD5E1] dark:bg-[#475569]'
        ]" />
      </div>
    </div>

    <!-- CENTER TABLE BODY -->
    <div
      :class="[
        'w-full h-14 sm:h-16 md:h-18 xl:h-20 rounded-2xl flex items-center justify-center font-black text-sm sm:text-base md:text-lg tracking-tight transition-all duration-200 shadow-2xs tabular-nums',
        status === 'filled'
          ? 'bg-[#0F172A] text-white shadow-xs'
          : status === 'reserved'
          ? 'bg-[#4880FF] text-white shadow-sm shadow-[#4880FF]/25'
          : 'bg-[#E2E8F0] dark:bg-[#334155] text-[#1E293B] dark:text-white border border-[#CBD5E1]/40 dark:border-transparent'
      ]"
    >
      {{ code }}
    </div>

    <!-- BOTTOM CHAIRS (2 chairs) -->
    <div class="flex items-center justify-center gap-2 sm:gap-2.5 md:gap-3 mt-1.5 w-full">
      <div v-for="c in 2" :key="'bot-' + c" class="flex flex-col items-center gap-0.5">
        <div :class="[
          'w-4 sm:w-5 md:w-6 h-2 sm:h-2.5 rounded-b-sm transition-colors duration-200',
          status === 'filled' ? 'bg-[#334155]' : status === 'reserved' ? 'bg-[#60A5FA]' : 'bg-[#CBD5E1] dark:bg-[#475569]'
        ]" />
        <div :class="[
          'w-4 sm:w-5 md:w-6 h-1.5 rounded-full transition-colors duration-200',
          status === 'filled' ? 'bg-[#0F172A]' : status === 'reserved' ? 'bg-[#1D4ED8]' : 'bg-[#94A3B8] dark:bg-[#64748B]'
        ]" />
      </div>
    </div>
  </div>
</template>
