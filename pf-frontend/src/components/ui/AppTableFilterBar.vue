<script setup lang="ts">
import { computed } from 'vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppFilterDropdown, { type FilterOption } from '@/components/ui/AppFilterDropdown.vue'
import { useDashboardI18n } from '@/i18n'

export interface FilterBarItem {
  key: string
  label?: string
  options: FilterOption[]
  width?: string
  placeholder?: string
}

interface Props {
  // Search config
  search?: string
  modelValue?: string
  searchPlaceholder?: string
  showSearch?: boolean
  searchWidth?: string

  // Date Range config
  showDateRange?: boolean
  startDate?: string
  endDate?: string
  dateRangeWidth?: string

  // Dropdowns config
  filters?: FilterBarItem[]
  filterValues?: Record<string, string | number>
}

const props = withDefaults(defineProps<Props>(), {
  search: '',
  modelValue: '',
  searchPlaceholder: 'Cari data...',
  showSearch: true,
  searchWidth: 'w-full sm:w-[250px]',

  showDateRange: true,
  startDate: '',
  endDate: '',
  dateRangeWidth: 'w-full sm:w-[250px]',

  filters: () => [],
  filterValues: () => ({}),
})

const emit = defineEmits<{
  (e: 'update:search', value: string): void
  (e: 'update:modelValue', value: string): void
  (e: 'update:startDate', value: string): void
  (e: 'update:endDate', value: string): void
  (e: 'update:filterValues', values: Record<string, string | number>): void
  (e: 'filter-change', key: string, value: string | number): void
  (e: 'reset-dates'): void
}>()

const { t } = useDashboardI18n()

const computedSearchPlaceholder = computed(() => {
  if (props.searchPlaceholder && props.searchPlaceholder !== 'Cari...') return props.searchPlaceholder
  return t('common.search', 'Cari...')
})

// Unified search model
const searchVal = computed({
  get: () => (props.search !== undefined && props.search !== '' ? props.search : props.modelValue || ''),
  set: (val: string) => {
    emit('update:search', val)
    emit('update:modelValue', val)
  },
})

// Helper untuk filter dropdown dinamis
const getFilterVal = (item: FilterBarItem) => {
  if (props.filterValues && item.key in props.filterValues) {
    return props.filterValues[item.key]
  }
  return item.options[0]?.value ?? 'all'
}

const setFilterVal = (key: string, val: string | number) => {
  if (props.filterValues) {
    const next = { ...props.filterValues, [key]: val }
    emit('update:filterValues', next)
  }
  emit('filter-change', key, val)
}

const onStartDateInput = (e: Event) => {
  const target = e.target as HTMLInputElement
  emit('update:startDate', target.value)
}

const onEndDateInput = (e: Event) => {
  const target = e.target as HTMLInputElement
  emit('update:endDate', target.value)
}

const clearDates = () => {
  emit('update:startDate', '')
  emit('update:endDate', '')
  emit('reset-dates')
}
</script>

<template>
  <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 w-full">
    <!-- Left: Segmented Dropdown Filters Capsule Container -->
    <div
      v-if="(filters && filters.length > 0) || $slots.filters || $slots.left"
      class="flex items-center gap-1.5 p-1 bg-[#F1F4F9] dark:bg-[#1E293B] rounded-xl flex-wrap sm:flex-nowrap"
    >
      <slot name="left" />

      <slot name="filters">
        <AppFilterDropdown
          v-for="item in filters"
          :key="item.key"
          :modelValue="getFilterVal(item)"
          :options="item.options"
          :width="item.width || 'w-44'"
          :placeholder="item.placeholder || 'Pilih...'"
          @update:modelValue="(val) => setFilterVal(item.key, val)"
        />
      </slot>
    </div>

    <!-- Right: Search Input & Native Date Range Filter (ml-auto to align right) -->
    <div class="flex items-center gap-2.5 ml-auto flex-wrap sm:flex-nowrap w-full lg:w-auto justify-end">
      <!-- 1. Search Input (Reusable AppInput matching filter height 38px) -->
      <div v-if="showSearch" :class="[searchWidth, 'shrink-0']">
        <AppInput
          v-model="searchVal"
          :placeholder="computedSearchPlaceholder"
          suffixIcon="search"
          clearable
          inputClass="!h-[38px] !text-xs sm:!text-sm"
        />
      </div>

      <!-- 2. Native Date Range Filter Input (Matching filter height of 38px) -->
      <div
        v-if="showDateRange"
        :class="[
          'h-[38px] flex items-center justify-between gap-1 bg-white dark:bg-[#1B2431] border border-[#CBD5E1] dark:border-[#334155] rounded-xl px-2.5 text-sm font-semibold text-[#1E293B] dark:text-white transition-all focus-within:ring-2 focus-within:ring-[#4880FF]/25 focus-within:border-[#4880FF] shrink-0',
          dateRangeWidth
        ]"
      >
        <input
          type="date"
          :value="startDate"
          @input="onStartDateInput"
          class="min-w-0 w-full bg-transparent border-0 text-xs sm:text-[13px] font-semibold text-[#1E293B] dark:text-white focus:outline-none cursor-pointer [color-scheme:light] dark:[color-scheme:dark]"
        />
        <span class="text-[#94A3B8] text-xs font-bold px-0.5 shrink-0">-</span>
        <input
          type="date"
          :value="endDate"
          :min="startDate || undefined"
          @input="onEndDateInput"
          class="min-w-0 w-full bg-transparent border-0 text-xs sm:text-[13px] font-semibold text-[#1E293B] dark:text-white focus:outline-none cursor-pointer [color-scheme:light] dark:[color-scheme:dark]"
        />
        <button
          v-if="startDate || endDate"
          type="button"
          @click="clearDates"
          :title="t('common.clearDateRange', 'Hapus filter rentang tanggal')"
          class="text-[#94A3B8] hover:text-[#202224] dark:hover:text-white cursor-pointer p-0.5 rounded transition-colors flex items-center shrink-0 ml-0.5"
        >
          <AppIcon name="close" :size="15" />
        </button>
      </div>

      <!-- Optional Extra Action Buttons Slot (e.g. Export, Add) -->
      <slot name="actions" />
    </div>
  </div>
</template>
