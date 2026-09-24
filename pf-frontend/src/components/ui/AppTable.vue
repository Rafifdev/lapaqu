<script setup lang="ts" generic="T extends Record<string, any> = Record<string, any>">
import { ref, computed, watch } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppPagination from '@/components/ui/AppPagination.vue'
import { useDashboardI18n } from '@/i18n'
import defaultEmptyIllustration from '@/assets/empty_state/empty-order.svg'

export interface TableColumn {
  key: string
  label: string
  align?: 'left' | 'center' | 'right'
  width?: string
  class?: string
}

interface Props {
  columns: TableColumn[]
  data: T[]
  title?: string
  subtitle?: string
  pageSize?: number
  searchable?: boolean
  searchPlaceholder?: string
  emptyTitle?: string
  emptyMessage?: string
  emptyIllustration?: string
  showNumbering?: boolean
  numberingLabel?: string
  loading?: boolean
  syncing?: boolean
  skeletonRows?: number
  scrollable?: boolean
  maxHeight?: string
  minHeight?: string
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  subtitle: '',
  pageSize: 20,
  searchable: false,
  searchPlaceholder: 'Cari data...',
  emptyTitle: 'Whoops! :(',
  emptyMessage: 'Belum ada data tersedia saat ini',
  emptyIllustration: '',
  showNumbering: false,
  numberingLabel: 'No',
  loading: false,
  syncing: false,
  skeletonRows: 6,
  scrollable: true,
  maxHeight: 'max-h-[calc(100vh-290px)]',
  minHeight: 'min-h-[420px]',
})

const { t } = useDashboardI18n()

const emit = defineEmits<{
  (e: 'row-click', row: any): void
}>()

// Search state
const searchQuery = ref('')

// Filtered data based on search
const filteredData = computed(() => {
  const query = (searchQuery.value || '').toLowerCase().trim()
  if (!query) return props.data

  return props.data.filter((item) => {
    return Object.values(item).some((val) => {
      if (val === null || val === undefined) return false
      return String(val).toLowerCase().includes(query)
    })
  })
})

// Empty title & subtitle computed
const computedEmptyTitle = computed(() => {
  if (props.emptyTitle && props.emptyTitle !== 'Whoops! :(') return props.emptyTitle
  return t('common.noDataFound', 'Data Tidak Ditemukan')
})
// Skeleton logic: show skeleton whenever loading is true
const showSkeleton = computed(() => {
  return props.loading
})

const computedEmptySubtitle = computed(() => {
  if (searchQuery.value) {
    return 'Coba sesuaikan kata kunci pencarian Anda'
  }
  return props.emptyMessage || 'Belum ada data tersedia saat ini'
})

// Pagination
const currentPage = ref(1)

watch([() => props.data, searchQuery], () => {
  currentPage.value = 1
})

const totalPages = computed(() => {
  if (!props.pageSize || props.pageSize <= 0) return 1
  return Math.ceil(filteredData.value.length / props.pageSize) || 1
})

const paginatedData = computed(() => {
  if (!props.pageSize || props.pageSize <= 0) return filteredData.value
  const start = (currentPage.value - 1) * props.pageSize
  return filteredData.value.slice(start, start + props.pageSize)
})

const goToPage = (p: number | string) => {
  if (typeof p === 'number' && p >= 1 && p <= totalPages.value) {
    currentPage.value = p
  }
}

const visiblePages = computed(() => {
  const current = currentPage.value
  const total = totalPages.value
  const pages: (number | string)[] = []

  if (total <= 5) {
    for (let i = 1; i <= total; i++) pages.push(i)
  } else {
    pages.push(1)
    if (current > 3) pages.push('...')

    const start = Math.max(2, current - 1)
    const end = Math.min(total - 1, current + 1)
    for (let i = start; i <= end; i++) {
      pages.push(i)
    }

    if (current < total - 2) pages.push('...')
    pages.push(total)
  }
  return pages
})
</script>

<template>
  <div
    class="bg-white dark:bg-[#273142] rounded-[14px] p-5 sm:p-6 md:p-8 shadow-[6px_6px_54px_0_rgba(0,0,0,0.05)] dark:shadow-none border border-transparent dark:border-[#313D4F] flex flex-col min-h-0"
  >
    <!-- Card Header / Title / Subtitle / Action Slot (Matching AppCard size & style) -->
    <div v-if="title || subtitle || $slots.header || $slots.actions || searchable" class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 w-full shrink-0 mb-5 pb-3.5">
      <div v-if="title || subtitle || $slots.header" class="min-w-0 flex-1">
        <slot name="header">
          <h3 class="text-base md:text-lg font-extrabold text-[#1E293B] dark:text-white tracking-tight">
            {{ title }}
          </h3>
          <p v-if="subtitle" class="text-xs text-[#64748B] dark:text-[#94A3B8] font-medium mt-0.5">
            {{ subtitle }}
          </p>
        </slot>
      </div>

      <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
        <svg
          v-if="syncing"
          class="animate-spin w-5 h-5 text-[#4880FF] shrink-0"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
        >
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <!-- Search Input -->
        <div v-if="searchable" class="relative flex-1 sm:w-64">
          <input
            v-model="searchQuery"
            type="text"
            :placeholder="searchPlaceholder"
            class="w-full bg-[#F5F6FA] dark:bg-[#1B2431] border border-[#E8E8E8] dark:border-[#313D4F] rounded-xl pl-9 pr-3.5 py-2 text-xs font-semibold text-[#202224] dark:text-white placeholder-[#94A3B8] placeholder:font-normal focus:outline-none transition-all"
          />
          <div class="absolute left-3 top-1/2 -translate-y-1/2 text-[#94A3B8] pointer-events-none flex items-center">
            <AppIcon name="search" :size="16" />
          </div>
          <button
            v-if="searchQuery"
            @click="searchQuery = ''"
            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[#94A3B8] hover:text-[#202224] dark:hover:text-white"
          >
            <AppIcon name="close" :size="14" />
          </button>
        </div>

        <slot name="actions" />
      </div>
    </div>

    <!-- Table Container (Fixed / Stable Height to prevent card shrinking on empty search) -->
    <div
      :class="[
        'flex-1 overflow-x-auto rounded-xl transition-all',
        scrollable ? ('overflow-y-auto ' + (maxHeight || 'max-h-[calc(100vh-290px)]')) : 'overflow-y-hidden',
        minHeight || 'min-h-[420px]',
      ]"
    >
      <table class="w-full text-left border-separate border-spacing-0">
        <!-- Table Header (Sticky) -->
        <thead class="sticky top-0 z-10">
          <tr class="text-sm font-bold text-[#202224] dark:text-white">
            <!-- Optional Numbering Column -->
            <th v-if="showNumbering" class="py-3.5 px-4 rounded-l-xl text-center w-14 bg-[#F1F4F9] dark:bg-[#323D4E]">
              {{ numberingLabel }}
            </th>

            <!-- Data Columns -->
            <th
              v-for="(col, idx) in columns"
              :key="col.key"
              :class="[
                'py-3.5 px-4 font-bold tracking-tight bg-[#F1F4F9] dark:bg-[#323D4E]',
                !showNumbering && idx === 0 ? 'rounded-l-xl' : '',
                idx === columns.length - 1 ? 'rounded-r-xl' : '',
                col.align === 'center' ? 'text-center' : col.align === 'right' ? 'text-right' : 'text-left',
                col.class || '',
              ]"
              :style="{ width: col.width }"
            >
              <slot :name="`header-${col.key}`" :column="col">
                {{ col.label }}
              </slot>
            </th>
          </tr>
        </thead>

        <!-- Table Body -->
        <tbody class="divide-y divide-[#E8E8E8] dark:divide-[#313D4F] [&_tr>td]:border-b [&_tr>td]:border-[#E8E8E8] dark:[&_tr>td]:border-[#313D4F] [&_tr:last-child>td]:border-b-0 text-sm">
          <!-- 1. Skeleton Loading State (Only if loading and there is data) -->
          <template v-if="showSkeleton">
            <tr
              v-for="n in (skeletonRows || 6)"
              :key="`skeleton-${n}`"
              class="animate-pulse"
            >
              <!-- Numbering Skeleton -->
              <td v-if="showNumbering" class="py-4 px-4 text-center">
                <div class="h-4 w-6 bg-[#E2E8F0] dark:bg-[#334155] rounded-md mx-auto"></div>
              </td>

              <!-- Columns Skeleton -->
              <td
                v-for="col in columns"
                :key="col.key"
                class="py-4 px-4"
                :class="[
                  col.align === 'center' ? 'text-center' : col.align === 'right' ? 'text-right' : 'text-left',
                  col.class || ''
                ]"
              >
                <slot :name="`skeleton-${col.key}`" :column="col" :index="n">
                  <!-- Actions Column -->
                  <div
                    v-if="col.key === 'actions' || col.key === 'action' || col.key === 'opsi' || col.key === 'menu'"
                    class="flex items-center justify-center"
                  >
                    <div class="h-6 w-6 bg-[#E2E8F0] dark:bg-[#334155] rounded-md"></div>
                  </div>

                  <!-- Status / Badge Column -->
                  <div
                    v-else-if="col.key.toLowerCase().includes('status') || col.key.toLowerCase().includes('category') || col.key.toLowerCase().includes('kategori') || col.key.toLowerCase().includes('role') || col.key.toLowerCase().includes('tipe') || col.key.toLowerCase().includes('type')"
                    class="flex items-center"
                    :class="col.align === 'center' ? 'justify-center' : col.align === 'right' ? 'justify-end' : 'justify-start'"
                  >
                    <div class="h-5 w-20 bg-[#E2E8F0] dark:bg-[#334155] rounded-full"></div>
                  </div>

                  <!-- Price / Revenue / Currency Column -->
                  <div
                    v-else-if="col.key.toLowerCase().includes('price') || col.key.toLowerCase().includes('harga') || col.key.toLowerCase().includes('revenue') || col.key.toLowerCase().includes('omset') || col.key.toLowerCase().includes('total') || col.key.toLowerCase().includes('amount') || col.key.toLowerCase().includes('nominal')"
                    class="flex items-center"
                    :class="col.align === 'left' ? 'justify-start' : 'justify-end'"
                  >
                    <div class="h-4 w-20 bg-[#E2E8F0] dark:bg-[#334155] rounded-md"></div>
                  </div>

                  <!-- Count / Qty / Number / Sort / Rank Column -->
                  <div
                    v-else-if="col.key.toLowerCase().includes('qty') || col.key.toLowerCase().includes('stock') || col.key.toLowerCase().includes('count') || col.key === 'rank' || col.key === 'sortorder' || col.key === 'sortOrder' || col.key === 'piece' || col.key === 'tablecode' || col.key === 'tableCode'"
                    class="flex items-center"
                    :class="col.align === 'left' ? 'justify-start' : col.align === 'right' ? 'justify-end' : 'justify-center'"
                  >
                    <div class="h-4 w-12 bg-[#E2E8F0] dark:bg-[#334155] rounded-md"></div>
                  </div>

                  <!-- Date / Time Column -->
                  <div
                    v-else-if="col.key.toLowerCase().includes('date') || col.key.toLowerCase().includes('time') || col.key.toLowerCase().includes('created') || col.key.toLowerCase().includes('started')"
                    class="flex items-center"
                    :class="col.align === 'center' ? 'justify-center' : col.align === 'right' ? 'justify-end' : 'justify-start'"
                  >
                    <div class="h-4 w-28 bg-[#E2E8F0] dark:bg-[#334155] rounded-md"></div>
                  </div>

                  <!-- Default Text Column (with natural varying widths) -->
                  <div
                    v-else
                    :class="[
                      'h-4 bg-[#E2E8F0] dark:bg-[#334155] rounded-md',
                      col.align === 'center'
                        ? 'mx-auto w-20'
                        : col.align === 'right'
                        ? 'ml-auto w-24'
                        : (n % 3 === 0 ? 'w-36' : n % 2 === 0 ? 'w-24' : 'w-28')
                    ]"
                  ></div>
                </slot>
              </td>
            </tr>
          </template>

          <!-- 2. Real Data Rows -->
          <template v-else-if="paginatedData.length > 0">
            <tr
              v-for="(item, rowIdx) in paginatedData"
              :key="item.id || rowIdx"
              @click="emit('row-click', item)"
              class="hover:bg-[#F8FAFC] dark:hover:bg-[#334155]/30 transition-colors"
            >
              <!-- Numbering Cell -->
              <td v-if="showNumbering" class="py-4 px-4 text-center text-sm font-bold text-[#64748B] dark:text-[#94A3B8] tabular-nums">
                {{ (currentPage - 1) * pageSize + rowIdx + 1 }}
              </td>

              <!-- Data Cells -->
              <td
                v-for="col in columns"
                :key="col.key"
                :class="[
                  'py-4 px-4 font-semibold text-sm text-[#202224] dark:text-white',
                  col.align === 'center' ? 'text-center' : col.align === 'right' ? 'text-right' : 'text-left',
                  col.class || '',
                ]"
              >
                <slot
                  :name="`cell-${col.key}`"
                  :row="item"
                  :value="item[col.key]"
                  :index="(currentPage - 1) * pageSize + rowIdx"
                >
                  {{ item[col.key] ?? '-' }}
                </slot>
              </td>
            </tr>
          </template>

          <!-- Empty State (Matches exact IncomingOrdersPage empty state design) -->
          <tr v-else class="h-[360px] sm:h-[400px]">
            <td
              :colspan="columns.length + (showNumbering ? 1 : 0)"
              class="h-[360px] sm:h-[400px] text-center align-middle p-0"
            >
              <slot name="empty">
                <div
                  class="min-h-[300px] sm:min-h-[340px] md:min-h-[380px] flex flex-col items-center justify-center text-center px-4 py-8"
                >
                  <div class="relative flex items-center justify-center mb-4 sm:mb-5 pointer-events-none">
                    <img
                      :src="emptyIllustration || defaultEmptyIllustration"
                      :alt="computedEmptyTitle"
                      class="w-48 h-48 sm:w-56 sm:h-56 md:w-60 md:h-60 lg:w-64 lg:h-64 object-contain drop-shadow-xs"
                    />
                  </div>
                  <h3 class="text-lg sm:text-xl md:text-2xl font-bold text-[#1E293B] dark:text-white tracking-tight">
                    {{ computedEmptyTitle }}
                  </h3>
                  <p
                    class="text-xs sm:text-sm font-medium text-[#64748B] dark:text-[#94A3B8] mt-1.5 max-w-[280px] sm:max-w-xs md:max-w-sm leading-relaxed"
                  >
                    {{ computedEmptySubtitle }}
                  </p>
                </div>
              </slot>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Skeleton Pagination Footer -->
    <div
      v-if="showSkeleton && pageSize > 0"
      class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-3 mt-4 border-t border-[#F1F4F9] dark:border-[#313D4F]/50 shrink-0 animate-pulse"
    >
      <div class="h-4 w-44 bg-[#E2E8F0] dark:bg-[#334155] rounded-md"></div>
      <div class="flex items-center gap-1.5">
        <div class="h-8 w-8 rounded-lg bg-[#E2E8F0] dark:bg-[#334155]"></div>
        <div class="h-8 w-8 rounded-lg bg-[#E2E8F0] dark:bg-[#334155]"></div>
        <div class="h-8 w-8 rounded-lg bg-[#E2E8F0] dark:bg-[#334155]"></div>
      </div>
    </div>

    <!-- Pagination Footer (Retains structure & space even on 0 data to prevent height jumping) -->
    <div
      v-else-if="pageSize > 0"
      class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-3 mt-4 border-t border-[#F1F4F9] dark:border-[#313D4F]/50 shrink-0"
    >
      <!-- Result count -->
      <p class="text-xs font-semibold text-[#64748B] dark:text-[#94A3B8]">
        <template v-if="filteredData.length > 0">
          {{ t('common.showing', 'Menampilkan') }}
          <span class="font-bold text-[#202224] dark:text-white">
            {{ (currentPage - 1) * pageSize + 1 }}-{{ Math.min(currentPage * pageSize, filteredData.length) }}
          </span>
          {{ t('common.of', 'dari') }}
          <span class="font-bold text-[#202224] dark:text-white">{{ filteredData.length }}</span>
          {{ t('common.data', 'data') }}
        </template>
        <template v-else>
          {{ t('common.showing', 'Menampilkan') }} <span class="font-bold text-[#202224] dark:text-white">0</span> {{ t('common.data', 'data') }}
        </template>
      </p>

      <!-- shadcn/ui Pagination Component -->
      <AppPagination
        v-if="filteredData.length > 0"
        v-model="currentPage"
        :total="filteredData.length"
        :pageSize="pageSize"
        :previousLabel="t('common.prev', 'Previous')"
        :nextLabel="t('common.next', 'Next')"
        size="sm"
      />
      <div v-else class="h-8"></div>
    </div>
  </div>
</template>
