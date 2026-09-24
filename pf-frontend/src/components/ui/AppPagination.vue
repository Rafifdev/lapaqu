<script setup lang="ts">
import { computed } from 'vue'
import { ChevronLeft, ChevronRight, MoreHorizontal } from 'lucide-vue-next'

interface Props {
  modelValue?: number
  page?: number
  totalPages?: number
  total?: number
  pageSize?: number
  disabled?: boolean
  previousLabel?: string
  nextLabel?: string
  size?: 'sm' | 'default'
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: 1,
  page: undefined,
  totalPages: undefined,
  total: 0,
  pageSize: 10,
  disabled: false,
  previousLabel: 'Previous',
  nextLabel: 'Next',
  size: 'default',
})

const emit = defineEmits<{
  (e: 'update:modelValue', page: number): void
  (e: 'change', page: number): void
}>()

const currentPage = computed(() => props.page ?? props.modelValue ?? 1)

const calculatedTotalPages = computed(() => {
  if (props.totalPages !== undefined) return Math.max(1, props.totalPages)
  if (props.pageSize > 0) return Math.max(1, Math.ceil(props.total / props.pageSize))
  return 1
})

const setPage = (p: number) => {
  if (props.disabled) return
  if (p < 1 || p > calculatedTotalPages.value) return
  if (p === currentPage.value) return
  emit('update:modelValue', p)
  emit('change', p)
}

// Generate visible pages like shadcn/ui
const pages = computed(() => {
  const current = currentPage.value
  const total = calculatedTotalPages.value
  const items: (number | 'ellipsis')[] = []

  if (total <= 5) {
    for (let i = 1; i <= total; i++) items.push(i)
  } else {
    items.push(1)
    if (current > 3) items.push('ellipsis')

    const start = Math.max(2, current - 1)
    const end = Math.min(total - 1, current + 1)
    for (let i = start; i <= end; i++) {
      items.push(i)
    }

    if (current < total - 2) items.push('ellipsis')
    items.push(total)
  }
  return items
})
</script>

<template>
  <nav role="navigation" aria-label="pagination" class="flex items-center">
    <ul class="flex flex-row items-center gap-1">
      <!-- Previous Button (shadcn/ui style) -->
      <li>
        <button
          type="button"
          aria-label="Go to previous page"
          :disabled="currentPage <= 1 || disabled"
          @click="setPage(currentPage - 1)"
          :class="[
            'inline-flex items-center justify-center whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring cursor-pointer select-none',
            size === 'sm' ? 'h-8 gap-1 pl-2 pr-2.5' : 'h-9 gap-1 pl-2.5 pr-3',
            currentPage <= 1 || disabled
              ? 'pointer-events-none opacity-40 text-[#94A3B8] dark:text-[#64748B] cursor-not-allowed'
              : 'text-[#1E293B] dark:text-[#F1F5F9] hover:bg-[#F1F5F9] dark:hover:bg-[#334155] active:bg-[#E2E8F0] dark:active:bg-[#475569]'
          ]"
        >
          <ChevronLeft :class="size === 'sm' ? 'h-3.5 w-3.5' : 'h-4 w-4'" />
          <span v-if="previousLabel" class="hidden sm:inline">{{ previousLabel }}</span>
        </button>
      </li>

      <!-- Page Numbers & Ellipsis -->
      <li v-for="(p, index) in pages" :key="index">
        <span
          v-if="p === 'ellipsis'"
          aria-hidden="true"
          :class="[
            'flex items-center justify-center text-[#94A3B8] dark:text-[#64748B]',
            size === 'sm' ? 'h-8 w-8' : 'h-9 w-9'
          ]"
        >
          <MoreHorizontal :class="size === 'sm' ? 'h-3.5 w-3.5' : 'h-4 w-4'" />
          <span class="sr-only">More pages</span>
        </span>

        <button
          v-else
          type="button"
          :aria-current="currentPage === p ? 'page' : undefined"
          :disabled="disabled"
          @click="setPage(p)"
          :class="[
            'inline-flex items-center justify-center whitespace-nowrap rounded-md text-xs sm:text-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring cursor-pointer select-none tabular-nums',
            size === 'sm' ? 'h-8 min-w-[32px] px-2' : 'h-9 min-w-[36px] px-3',
            currentPage === p
              ? 'border border-[#CBD5E1] dark:border-[#475569] bg-white dark:bg-[#1E293B] text-[#0F172A] dark:text-white font-semibold shadow-2xs'
              : 'text-[#64748B] dark:text-[#94A3B8] font-medium hover:text-[#0F172A] dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155]'
          ]"
        >
          {{ p }}
        </button>
      </li>

      <!-- Next Button (shadcn/ui style) -->
      <li>
        <button
          type="button"
          aria-label="Go to next page"
          :disabled="currentPage >= calculatedTotalPages || disabled"
          @click="setPage(currentPage + 1)"
          :class="[
            'inline-flex items-center justify-center whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring cursor-pointer select-none',
            size === 'sm' ? 'h-8 gap-1 pl-2.5 pr-2' : 'h-9 gap-1 pl-3 pr-2.5',
            currentPage >= calculatedTotalPages || disabled
              ? 'pointer-events-none opacity-40 text-[#94A3B8] dark:text-[#64748B] cursor-not-allowed'
              : 'text-[#1E293B] dark:text-[#F1F5F9] hover:bg-[#F1F5F9] dark:hover:bg-[#334155] active:bg-[#E2E8F0] dark:active:bg-[#475569]'
          ]"
        >
          <span v-if="nextLabel" class="hidden sm:inline">{{ nextLabel }}</span>
          <ChevronRight :class="size === 'sm' ? 'h-3.5 w-3.5' : 'h-4 w-4'" />
        </button>
      </li>
    </ul>
  </nav>
</template>
