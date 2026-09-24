<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { usePosStore } from '@/stores/pos'
import { useFormat } from '@/composables/useFormat'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppModal from '@/components/ui/AppModal.vue'
import { Motion, AnimatePresence } from 'motion-v'
import AppInput from '@/components/ui/AppInput.vue'
import AppTextarea from '@/components/ui/AppTextarea.vue'
import AppSelect from '@/components/ui/AppSelect.vue'
import AppTableFloorItem from '@/components/pos/AppTableFloorItem.vue'
import emptyTableIllustration from '@/assets/empty_state/empty-table.svg'
import type { TableStatus } from '@/components/pos/AppTableFloorItem.vue'

const router = useRouter()
const { formatCurrency, formatTimeOnly } = useFormat()
const posStore = usePosStore()

onMounted(async () => {
  await Promise.all([
    posStore.fetchTables(),
    posStore.fetchOrders()
  ])
})

export interface TableItemModel {
  id: string
  code: string
  status: TableStatus
  isLarge?: boolean
  capacity?: number
  customerName?: string
  customerPhone?: string
  time?: string
  totalAmount?: number
  guestCount?: number
  avatar?: string
  statusNote?: string
  notes?: string
}

// Dynamically map real tables from backend database via posStore
const allTables = computed<TableItemModel[]>(() => {
  return posStore.tables.map((t, idx) => {
    const rawCode = t.code || t.tableCode
    const cleanNum = rawCode.replace(/[^0-9]/g, '')
    const tableOrders = posStore.orders.filter(o =>
      o.tableCode === t.tableCode ||
      o.tableCode === cleanNum ||
      o.tableId === t.id
    )
    const latestOrder = tableOrders[0]
    const total = tableOrders.reduce((sum, o) => sum + (o.totalAmount || 0), 0)

    let status: TableStatus = 'available'
    if (t.status === 'occupied') {
      status = 'filled'
    } else if (t.status === 'reserved') {
      status = 'reserved'
    }

    return {
      id: t.id,
      code: t.tableCode || `T-${cleanNum.padStart(2, '0')}`,
      status,
      isLarge: (t.capacity || 4) > 4,
      capacity: t.capacity || 4,
      customerName: latestOrder?.customerName || (status === 'filled' ? 'Pelanggan Meja' : undefined),
      time: t.sessionStartedAt ? `${formatTimeOnly(t.sessionStartedAt)} WIB` : undefined,
      totalAmount: total || undefined,
      guestCount: t.capacity || 4,
    }
  })
})

const indoorTables = computed(() => allTables.value.slice(0, 6))
const outdoorTables = computed(() => allTables.value.slice(6))

const availableTables = computed(() => allTables.value.filter(t => t.status === 'available'))
const reservedTables = computed(() => allTables.value.filter(t => t.status === 'reserved'))
const filledTables = computed(() => allTables.value.filter(t => t.status === 'filled'))

// Select options
const tableSelectOptions = computed(() => {
  const normalTables = availableTables.value
    .filter(t => t.status === 'available' && !t.isLarge)
    .map(t => ({
      value: t.id,
      label: `Meja ${t.code}`,
      group: 'Meja Standar'
    }))

  const largeTables = availableTables.value
    .filter(t => t.status === 'available' && t.isLarge)
    .map(t => ({
      value: t.id,
      label: `Meja ${t.code}`,
      group: 'Meja Besar'
    }))

  return [...normalTables, ...largeTables]
})

// Left panel accordion states
const isAvailableOpen = ref(true)
const isStatusPanelExpanded = ref(true)
const isReservedOpen = ref(true)
const isFilledOpen = ref(true)
const isAvailableExpanded = ref(false)
const isReservedExpanded = ref(false)
const isFilledExpanded = ref(false)

const displayedAvailable = computed(() => {
  if (isAvailableExpanded.value) return availableTables.value
  return availableTables.value.slice(0, 4)
})

const displayedReserved = computed(() => {
  if (isReservedExpanded.value) return reservedTables.value
  return reservedTables.value.slice(0, 4)
})

const displayedFilled = computed(() => {
  if (isFilledExpanded.value) return filledTables.value
  return filledTables.value.slice(0, 4)
})

// Table detail modal state
const selectedTable = ref<TableItemModel | null>(null)
const isTableModalOpen = ref(false)

const openTableModal = (t: TableItemModel) => {
  selectedTable.value = t
  isTableModalOpen.value = true
}

// Reservation modal state & Progressive Multi-Step Form
const isReservationModalOpen = ref(false)
const isReservationSuccessOpen = ref(false)
const lastReservationData = ref<any>(null)
const currentReservationStep = ref(1)
const reservationForm = ref({
  customerName: '',
  customerPhone: '',
  tableId: '',
  reservationTime: '19:00',
  guestCount: 4,
  notes: ''
})

const selectedReservationTable = computed(() => {
  return allTables.value.find(t => t.id === reservationForm.value.tableId)
})

const handleGuestCountKeydown = (e: KeyboardEvent) => {
  if (['-', 'e', '+', '.'].includes(e.key)) {
    e.preventDefault()
  }
}

const openCreateReservation = () => {
  currentReservationStep.value = 1
  reservationForm.value = {
    customerName: '',
    customerPhone: '',
    tableId: availableTables.value[0]?.id || '',
    reservationTime: '19:00',
    guestCount: 4,
    notes: ''
  }
  isReservationModalOpen.value = true
}

const nextReservationStep = () => {
  if (currentReservationStep.value === 1) {
    if (!reservationForm.value.customerName.trim()) return
    currentReservationStep.value = 2
  } else if (currentReservationStep.value === 2) {
    if (!reservationForm.value.tableId) return
    currentReservationStep.value = 3
  }
}

const prevReservationStep = () => {
  if (currentReservationStep.value > 1) {
    currentReservationStep.value--
  }
}

const handleSaveReservation = () => {
  if (!reservationForm.value.customerName || !reservationForm.value.tableId) return
  const target = allTables.value.find(t => t.id === reservationForm.value.tableId)
  if (target) {
    const cleanCode = target.code.replace(/[^0-9]/g, '')
    posStore.reserveTable(cleanCode)
    target.status = 'reserved'
    target.customerName = reservationForm.value.customerName
    target.customerPhone = reservationForm.value.customerPhone
    target.guestCount = reservationForm.value.guestCount || 4
    target.time = `${reservationForm.value.reservationTime} WIB`
    if (reservationForm.value.notes) {
      target.notes = reservationForm.value.notes
    }

    lastReservationData.value = {
      customerName: reservationForm.value.customerName || 'Tamu Reservasi',
      customerPhone: reservationForm.value.customerPhone || '-',
      tableCode: target.code,
      capacity: `${target.capacity || 4} Kursi`,
      time: reservationForm.value.reservationTime,
      guestCount: reservationForm.value.guestCount || 4,
      notes: reservationForm.value.notes
    }
  }
  isReservationModalOpen.value = false
  isReservationSuccessOpen.value = true
}

const handleCheckIn = (t: TableItemModel) => {
  const cleanCode = t.code.replace(/[^0-9]/g, '')
  const target = posStore.tables.find(tbl => tbl.id === t.id || tbl.tableCode === t.code || tbl.tableCode === cleanCode)
  if (target) {
    target.status = 'occupied'
    target.sessionStartedAt = new Date().toISOString()
  }
  isTableModalOpen.value = false
}

const isCloseSuccess = ref(false)
let successTimer: any = null

const handleFreeTable = (t: TableItemModel) => {
  posStore.closeTableSession(t.id)
  isTableModalOpen.value = false
  isCloseSuccess.value = true
  if (successTimer) clearTimeout(successTimer)
  successTimer = setTimeout(() => {
    isCloseSuccess.value = false
  }, 1200)
}

const handleStartOrder = (t: TableItemModel) => {
  router.push({ path: '/pos/manual', query: { table: t.code, openModal: '1' } })
}
</script>

<template>
  <div class="h-full flex-1 flex flex-col min-h-0 font-sans relative">
    <!-- Header: Manage Table -->
    <div class="flex items-center justify-between gap-4 mb-4 shrink-0">
      <h1 class="text-2xl font-bold text-[#202224] dark:text-white">Manage Table</h1>
    </div>

    <!-- Main Two-Column Layout -->
    <div class="flex-1 flex flex-col lg:flex-row gap-6 overflow-hidden min-h-0 relative">
      <!-- Floor Plan Panel (Left/Main Side) -->
      <div
        class="flex-1 h-full flex flex-col min-w-0 bg-white dark:bg-[#273142] rounded-2xl shadow-sm p-5 sm:p-6 overflow-hidden">
        <!-- Header -->
        <div
          class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-[#F1F5F9] dark:border-[#334155] shrink-0">
          <h2 class="text-xl font-bold text-[#1E293B] dark:text-white">
            Table list
          </h2>

          <!-- Legend & Button -->
          <div
            class="flex items-center gap-4 sm:gap-6 flex-wrap text-sm font-extrabold text-[#334155] dark:text-[#CBD5E1]">
            <div class="flex items-center gap-2">
              <div class="w-3.5 h-3.5 rounded-full bg-[#E2E8F0] dark:bg-[#334155] shadow-xs" />
              <span>Available</span>
            </div>
            <div class="flex items-center gap-2">
              <div class="w-3.5 h-3.5 rounded-full bg-[#4880FF] shadow-xs" />
              <span class="text-[#4880FF]">Reserved</span>
            </div>
            <div class="flex items-center gap-2">
              <div class="w-3.5 h-3.5 rounded-full bg-[#0F172A] shadow-xs" />
              <span class="text-[#0F172A] dark:text-white">Filled</span>
            </div>
          </div>

          <!-- Buat Reservasi Button -->
          <AppButton variant="primary" size="sm" icon="add" @click="openCreateReservation"
            class="!rounded-lg !font-bold shadow-xs px-4 !py-2 !text-sm">
            Buat Reservasi
          </AppButton>
        </div>

        <!-- Floor Plan Grid Canvas (Natural Reflow without fade) -->
        <div class="flex-1 overflow-y-auto overflow-x-auto pr-1 pt-4 pb-2 space-y-6 min-h-0 [scrollbar-gutter:stable]">
          <!-- Empty State when no tables exist -->
          <div v-if="allTables.length === 0"
            class="min-h-[300px] sm:min-h-[340px] md:min-h-[380px] h-full flex flex-col items-center justify-center text-center px-4 py-8">
            <div class="relative flex items-center justify-center mb-1 sm:mb-1.5 pointer-events-none">
              <img :src="emptyTableIllustration" alt="Belum Ada Meja"
                class="w-48 h-48 sm:w-56 sm:h-56 md:w-60 md:h-60 lg:w-64 lg:h-64 object-contain drop-shadow-xs" />
            </div>
            <h3 class="text-xl sm:text-2xl md:text-3xl font-black text-[#1E293B] dark:text-white tracking-tight">
              Whoops! :(
            </h3>
            <p
              class="text-sm sm:text-base font-medium text-[#64748B] dark:text-[#94A3B8] mt-1.5 max-w-[280px] sm:max-w-xs md:max-w-sm leading-relaxed">
              Belum ada meja yang terdaftar saat ini
            </p>
          </div>

          <!-- Floor Plan Area if tables exist -->
          <template v-else>
            <!-- Indoor Area -->
            <div>
              <div class="text-center mb-5">
                <span
                  class="inline-block text-sm font-black uppercase tracking-widest text-[#475569] dark:text-[#94A3B8]">
                  Indoor
                </span>
              </div>
              <div :class="[
                'grid gap-y-9 sm:gap-y-10 gap-x-3 sm:gap-x-4 items-center justify-items-center w-full',
                isStatusPanelExpanded ? 'grid-cols-6 min-w-[460px]' : 'grid-cols-8 min-w-[620px]'
              ]">
                <AppTableFloorItem v-for="t in indoorTables" :key="t.id" :code="t.code" :status="t.status"
                  :is-large="t.isLarge" @click="openTableModal(t)" />
              </div>
            </div>

            <div v-if="outdoorTables.length > 0" class="border-t border-[#F1F5F9] dark:border-[#334155] my-5" />

            <!-- Outdoor Area -->
            <div v-if="outdoorTables.length > 0">
              <div class="text-center mb-5">
                <span
                  class="inline-block text-sm font-black uppercase tracking-widest text-[#475569] dark:text-[#94A3B8]">
                  outdoor
                </span>
              </div>
              <div :class="[
                'grid gap-y-9 sm:gap-y-10 gap-x-3 sm:gap-x-4 items-center justify-items-center w-full',
                isStatusPanelExpanded ? 'grid-cols-6 min-w-[460px]' : 'grid-cols-8 min-w-[620px]'
              ]">
                <AppTableFloorItem v-for="t in outdoorTables" :key="t.id" :code="t.code" :status="t.status"
                  :is-large="t.isLarge" @click="openTableModal(t)" />
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- Status Panel (Right Side: Smooth GPU-Accelerated Expand & Collapse) -->
      <div :class="[
        'bg-white dark:bg-[#273142] rounded-2xl shadow-sm flex flex-col shrink-0 overflow-hidden h-full transition-[width,padding,margin,opacity,transform] duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] [transform:translateZ(0)]',
        isStatusPanelExpanded
          ? 'w-full lg:w-[410px] 2xl:w-[450px] opacity-100 translate-x-0 p-5'
          : 'w-0 opacity-0 translate-x-12 p-0 m-0 pointer-events-none'
      ]">
        <div class="w-full h-full flex flex-col min-w-[340px] 2xl:min-w-[380px]">
          <!-- Header with Collapse Button (Exact match with ManualOrderPage) -->
          <div class="flex items-center justify-between pb-3 border-b border-[#F1F4F9] dark:border-[#313D4F] shrink-0">
            <h2 class="text-xl font-bold text-[#1E293B] dark:text-white">
              Table status
            </h2>
            <button type="button" @click="isStatusPanelExpanded = false"
              class="w-10 h-10 rounded-xl flex items-center justify-center text-[#64748B] hover:text-[#4880FF] dark:text-[#94A3B8] dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#313D4F] transition-all active:scale-90 cursor-pointer"
              title="Sembunyikan Panel Status">
              <AppIcon name="chevron_right" :size="30" />
            </button>
          </div>

          <!-- Right Panel Skeleton Loading -->
          <div v-if="posStore.isLoading" class="space-y-4 pr-1 pt-4 animate-pulse">
            <div v-for="n in 6" :key="n" class="pb-3.5 border-b border-[#F1F5F9] dark:border-[#334155] space-y-2">
              <div class="flex items-center justify-between">
                <div class="h-4 bg-slate-200 dark:bg-slate-700/60 rounded w-24" />
                <div class="h-4 bg-slate-200 dark:bg-slate-700/60 rounded-full w-16" />
              </div>
              <div class="h-3 bg-slate-200 dark:bg-slate-700/60 rounded w-36" />
            </div>
          </div>

          <div v-else class="flex-1 overflow-y-auto space-y-5 pr-1 pt-4 min-h-0 [scrollbar-gutter:stable]">
            <!-- Available Accordion -->
            <div>
              <div
                class="flex items-center justify-between cursor-pointer py-1.5 px-1.5 -mx-1.5 rounded-xl hover:bg-[#F8FAFC] dark:hover:bg-[#334155]/40 transition-colors"
                @click="isAvailableOpen = !isAvailableOpen">
                <div class="flex items-center gap-2">
                  <span class="font-extrabold text-sm text-[#1E293B] dark:text-white">Available</span>
                  <span
                    class="px-2 py-0.5 rounded-full text-sm font-black bg-[#E2E8F0] dark:bg-[#334155] text-[#1E293B] dark:text-white">
                    {{ availableTables.length }}
                  </span>
                </div>
                <button type="button"
                  class="text-[#64748B] dark:text-[#94A3B8] p-1 cursor-pointer flex items-center justify-center">
                  <AppIcon name="expand_more" :size="20"
                    :class="['transition-transform duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]', isAvailableOpen ? 'rotate-180' : 'rotate-0']" />
                </button>
              </div>

              <!-- Smooth Hardware-Accelerated Accordion Grid Transition -->
              <div class="grid transition-[grid-template-rows,opacity] duration-300 ease-[cubic-bezier(0.32,0.72,0,1)]"
                :class="isAvailableOpen ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0 pointer-events-none'">
                <div class="overflow-hidden">
                  <div class="mt-3 space-y-3.5 pt-0.5">
                    <div v-for="t in displayedAvailable" :key="t.id"
                      class="border-b border-[#F1F5F9] dark:border-[#334155] pb-3.5 last:border-b-0">
                      <div class="flex items-center justify-between gap-3">
                        <!-- Left: Table Info -->
                        <div class="min-w-0 flex-1">
                          <h4 class="font-extrabold text-sm text-[#1E293B] dark:text-white truncate">
                            {{ t.code }}
                          </h4>
                          <div
                            class="flex items-center gap-1.5 text-sm text-[#64748B] dark:text-[#94A3B8] mt-0.5 font-medium">
                            <AppIcon name="group" :size="14" class="shrink-0" />
                            <span>{{ t.capacity || 4 }} Kursi</span>
                            <span>•</span>
                            <span>{{ t.isLarge ? 'Meja Besar' : 'Meja Normal' }}</span>
                          </div>
                        </div>

                        <!-- Right: Status Pill -->
                        <div class="shrink-0">
                          <span
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-500 text-white shadow-2xs whitespace-nowrap">
                            <AppIcon name="check_circle" :size="10" />
                            Siap Digunakan
                          </span>
                        </div>
                      </div>
                    </div>

                    <button v-if="availableTables.length > 4" type="button"
                      @click="isAvailableExpanded = !isAvailableExpanded"
                      class="w-full py-2 text-sm font-bold text-[#64748B] dark:text-[#94A3B8] hover:text-[#4880FF] flex items-center justify-center gap-1.5 transition-colors cursor-pointer active:scale-95">
                      <span>{{ isAvailableExpanded ? 'See Less' : 'See More' }}</span>
                      <AppIcon name="expand_more" :size="16"
                        :class="['transition-transform duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]', isAvailableExpanded ? 'rotate-180' : 'rotate-0']" />
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Reserved Accordion -->
            <div class="pt-2 border-t border-[#F1F5F9] dark:border-[#334155]">
              <div
                class="flex items-center justify-between cursor-pointer py-1.5 px-1.5 -mx-1.5 rounded-xl hover:bg-[#F8FAFC] dark:hover:bg-[#334155]/40 transition-colors"
                @click="isReservedOpen = !isReservedOpen">
                <div class="flex items-center gap-2">
                  <span class="font-extrabold text-sm text-[#1E293B] dark:text-white">Reserved</span>
                  <span class="px-2.5 py-0.5 rounded-full text-sm font-black bg-[#4880FF] text-white shadow-2xs">
                    {{ reservedTables.length }}
                  </span>
                </div>
                <button type="button"
                  class="text-[#64748B] dark:text-[#94A3B8] p-1 cursor-pointer flex items-center justify-center">
                  <AppIcon name="expand_more" :size="20"
                    :class="['transition-transform duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]', isReservedOpen ? 'rotate-180' : 'rotate-0']" />
                </button>
              </div>

              <!-- Smooth Hardware-Accelerated Accordion Grid Transition -->
              <div class="grid transition-[grid-template-rows,opacity] duration-300 ease-[cubic-bezier(0.32,0.72,0,1)]"
                :class="isReservedOpen ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0 pointer-events-none'">
                <div class="overflow-hidden">
                  <div class="mt-3 space-y-3.5 pt-0.5">
                    <div v-for="t in displayedReserved" :key="t.id"
                      class="border-b border-[#F1F5F9] dark:border-[#334155] pb-3.5 last:border-b-0">
                      <div class="flex items-center justify-between gap-3">
                        <!-- Left: Reservation Info -->
                        <div class="min-w-0 flex-1">
                          <h4 class="font-extrabold text-sm text-[#1E293B] dark:text-white truncate">
                            {{ t.customerName || 'Tamu Reservasi' }}
                          </h4>
                          <div
                            class="flex items-center gap-1.5 text-sm text-[#64748B] dark:text-[#94A3B8] mt-0.5 font-medium">
                            <AppIcon name="group" :size="14" class="shrink-0" />
                            <span>{{ t.guestCount || t.capacity || 4 }} Orang</span>
                            <span>•</span>
                            <AppIcon name="schedule" :size="14" class="shrink-0" />
                            <span>{{ t.time }}</span>
                          </div>
                        </div>

                        <!-- Right: Status Pill -->
                        <div class="shrink-0">
                          <span
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-[#EF4444] text-white shadow-2xs whitespace-nowrap">
                            <AppIcon name="alarm" :size="10" />
                            {{ t.statusNote || 'In last 5 minutes' }}
                          </span>
                        </div>
                      </div>
                    </div>

                    <button v-if="reservedTables.length > 4" type="button"
                      @click="isReservedExpanded = !isReservedExpanded"
                      class="w-full py-2 text-sm font-bold text-[#64748B] dark:text-[#94A3B8] hover:text-[#4880FF] flex items-center justify-center gap-1.5 transition-colors cursor-pointer active:scale-95">
                      <span>{{ isReservedExpanded ? 'See Less' : 'See More' }}</span>
                      <AppIcon name="expand_more" :size="16"
                        :class="['transition-transform duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]', isReservedExpanded ? 'rotate-180' : 'rotate-0']" />
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Filled Accordion -->
            <div class="pt-2 border-t border-[#F1F4F9] dark:border-[#334155]">
              <div
                class="flex items-center justify-between cursor-pointer py-1.5 px-1.5 -mx-1.5 rounded-xl hover:bg-[#F8FAFC] dark:hover:bg-[#334155]/40 transition-colors"
                @click="isFilledOpen = !isFilledOpen">
                <div class="flex items-center gap-2">
                  <span class="font-extrabold text-sm text-[#1E293B] dark:text-white">Filled</span>
                  <span
                    class="px-2.5 py-0.5 rounded-full text-sm font-black bg-[#0F172A] text-white dark:bg-white dark:text-[#0F172A] shadow-2xs">
                    {{ filledTables.length }}
                  </span>
                </div>
                <button type="button"
                  class="text-[#64748B] dark:text-[#94A3B8] p-1 cursor-pointer flex items-center justify-center">
                  <AppIcon name="expand_more" :size="20"
                    :class="['transition-transform duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]', isFilledOpen ? 'rotate-180' : 'rotate-0']" />
                </button>
              </div>

              <!-- Smooth Hardware-Accelerated Accordion Grid Transition -->
              <div class="grid transition-[grid-template-rows,opacity] duration-300 ease-[cubic-bezier(0.32,0.72,0,1)]"
                :class="isFilledOpen ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0 pointer-events-none'">
                <div class="overflow-hidden">
                  <div class="mt-3 space-y-3.5 pt-0.5">
                    <div v-if="filledTables.length === 0" class="text-sm text-[#94A3B8] text-center py-2">
                      Tidak ada meja yang sedang terisi
                    </div>
                    <div v-for="t in displayedFilled" :key="t.id"
                      class="border-b border-[#F1F4F9] dark:border-[#334155] pb-3.5 last:border-b-0 cursor-pointer hover:opacity-90"
                      @click="openTableModal(t)">
                      <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0 flex-1">
                          <h4 class="font-extrabold text-sm text-[#1E293B] dark:text-white truncate">
                            {{ t.code }} • {{ t.customerName || 'Pelanggan Meja' }}
                          </h4>
                          <div
                            class="flex items-center gap-1.5 text-sm text-[#64748B] dark:text-[#94A3B8] mt-0.5 font-medium">
                            <AppIcon name="group" :size="14" class="shrink-0" />
                            <span>{{ t.capacity || 4 }} Kursi</span>
                            <span>•</span>
                            <span>{{ t.isLarge ? 'Meja Besar' : 'Meja Normal' }}</span>
                          </div>
                        </div>

                        <div class="shrink-0">
                          <span
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-[#0F172A] text-white shadow-2xs whitespace-nowrap">
                            <AppIcon name="person" :size="10" />
                            Terisi
                          </span>
                        </div>
                      </div>
                    </div>

                    <button v-if="filledTables.length > 4" type="button" @click="isFilledExpanded = !isFilledExpanded"
                      class="w-full py-2 text-sm font-bold text-[#64748B] dark:text-[#94A3B8] hover:text-[#4880FF] flex items-center justify-center gap-1.5 transition-colors cursor-pointer active:scale-95">
                      <span>{{ isFilledExpanded ? 'See Less' : 'See More' }}</span>
                      <AppIcon name="expand_more" :size="16"
                        :class="['transition-transform duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]', isFilledExpanded ? 'rotate-180' : 'rotate-0']" />
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Table Detail Modal (Clean Typography, No Heavy Div Cards, Split Left-Right matching Order Masuk) -->
    <AppModal :show="isTableModalOpen" v-model="isTableModalOpen"
      :title="selectedTable ? `Rincian ${selectedTable.code}` : 'Rincian Meja'" maxWidth="md">
      <div v-if="selectedTable" class="space-y-4 py-1">
        <!-- Header Info Row -->
        <div class="flex items-center justify-between pb-3.5 border-b border-[#F1F4F9] dark:border-[#313D4F]">
          <div>
            <h3 class="font-bold text-base text-[#1E293B] dark:text-white leading-tight">
              {{ selectedTable.code }}
            </h3>
            <div class="flex items-center gap-1.5 text-sm text-[#64748B] dark:text-[#94A3B8] mt-1 font-medium">
              <AppIcon name="group" :size="14" class="shrink-0" />
              <span>{{ selectedTable.capacity || 4 }} Kursi</span>
              <span>•</span>
              <span>{{ selectedTable.isLarge ? 'Meja Besar' : 'Meja Normal' }}</span>
            </div>
          </div>
          <AppBadge
            :variant="selectedTable.status === 'available' ? 'neutral' : selectedTable.status === 'reserved' ? 'primary' : 'danger'"
            size="md" rounded="full">
            {{ selectedTable.status === 'available' ? 'Available' : selectedTable.status === 'reserved' ? 'Reserved' :
              'Filled' }}
          </AppBadge>
        </div>

        <!-- Detail Information Rows (Clean Left-Right Typography) -->
        <div class="space-y-3 pt-1">
          <template v-if="selectedTable.status === 'reserved'">
            <div class="flex items-center justify-between text-sm">
              <span class="text-[#64748B] dark:text-[#94A3B8] font-medium">Nama Tamu:</span>
              <span class="font-bold text-[#1E293B] dark:text-white">{{ selectedTable.customerName || 'Tamu Reservasi'
              }}</span>
            </div>
            <div class="flex items-center justify-between text-sm">
              <span class="text-[#64748B] dark:text-[#94A3B8] font-medium">Jam Reservasi:</span>
              <span class="font-bold text-[#4880FF] tabular-nums">{{ selectedTable.time }}</span>
            </div>
            <div v-if="selectedTable.notes" class="flex items-center justify-between text-sm">
              <span class="text-[#64748B] dark:text-[#94A3B8] font-medium">Catatan:</span>
              <span class="font-bold text-[#1E293B] dark:text-white">{{ selectedTable.notes }}</span>
            </div>
          </template>

          <template v-else-if="selectedTable.status === 'filled'">
            <div class="flex items-center justify-between text-sm">
              <span class="text-[#64748B] dark:text-[#94A3B8] font-medium">Pelanggan:</span>
              <span class="font-bold text-[#1E293B] dark:text-white">{{ selectedTable.customerName || 'Pelanggan Meja'
              }}</span>
            </div>
            <div class="flex items-center justify-between text-sm">
              <span class="text-[#64748B] dark:text-[#94A3B8] font-medium">Waktu Mulai:</span>
              <span class="font-bold text-[#1E293B] dark:text-white tabular-nums">{{ selectedTable.time }}</span>
            </div>
            <div
              class="flex items-center justify-between text-sm pt-2 border-t border-dashed border-[#F1F4F9] dark:border-[#313D4F]">
              <span class="text-[#64748B] dark:text-[#94A3B8] font-medium">Tagihan Berjalan:</span>
              <span class="font-black text-base text-[#1E293B] dark:text-white tabular-nums">{{
                formatCurrency(selectedTable.totalAmount || 0) }}</span>
            </div>
          </template>

          <template v-else>
            <div class="flex items-center justify-between text-sm">
              <span class="text-[#64748B] dark:text-[#94A3B8] font-medium">Status Meja:</span>
              <span class="font-bold text-[#00B69B]">Siap Digunakan</span>
            </div>
            <div class="flex items-center justify-between text-sm">
              <span class="text-[#64748B] dark:text-[#94A3B8] font-medium">Area:</span>
              <span class="font-bold text-[#1E293B] dark:text-white uppercase">{{indoorTables.some(t => t.id ===
                selectedTable?.id) ? 'Indoor' : 'Outdoor'}}</span>
            </div>
          </template>
        </div>
      </div>

      <template #footer>
        <div class="flex items-center justify-between w-full gap-2">
          <template v-if="selectedTable?.status === 'available'">
            <AppButton variant="outline" size="md" @click="isTableModalOpen = false" class="flex-1 !rounded-lg">
              Tutup
            </AppButton>
            <AppButton variant="primary" size="md" icon="point_of_sale" @click="handleStartOrder(selectedTable!)"
              class="flex-1 !rounded-lg">
              Buka Pesanan
            </AppButton>
          </template>

          <template v-else-if="selectedTable?.status === 'reserved'">
            <AppButton variant="outline" size="md" @click="handleFreeTable(selectedTable!)"
              class="flex-1 !rounded-lg text-red-600 border-red-200">
              Batalkan
            </AppButton>
            <AppButton variant="primary" size="md" icon="login" @click="handleCheckIn(selectedTable!)"
              class="flex-1 !rounded-lg">
              Check-In Tamu
            </AppButton>
          </template>

          <template v-else>
            <AppButton variant="outline" size="md" @click="isTableModalOpen = false" class="flex-1 !rounded-lg">
              Tutup
            </AppButton>
            <AppButton variant="danger" size="md" icon="logout" @click="handleFreeTable(selectedTable!)"
              class="flex-1 !rounded-lg">
              Tutup Sesi
            </AppButton>
          </template>
        </div>
      </template>
    </AppModal>

    <!-- Progressive Multi-Step Reservation Modal -->
    <AppModal :show="isReservationModalOpen" v-model="isReservationModalOpen" title="Buat Reservasi Meja" maxWidth="lg">
      <div class="py-1">
        <!-- Step Progress Bar: Perfectly Sized (w-9 h-9) with Smooth Transitions -->
        <div class="px-4 sm:px-8">
          <div class="flex items-start justify-between w-full">
            <!-- Step 1: Informasi Pelanggan -->
            <div class="flex flex-col items-center shrink-0">
              <div :class="[
                'w-9 h-9 rounded-full flex items-center justify-center text-sm font-semibold transition-all duration-350 ease-out shadow-xs select-none',
                currentReservationStep >= 1
                  ? 'bg-[#4880FF] text-white scale-105'
                  : 'bg-blue-100 dark:bg-blue-950/60 text-[#4880FF] dark:text-blue-300 scale-100',
                currentReservationStep > 1 ? 'cursor-pointer active:scale-95' : ''
              ]" @click="currentReservationStep > 1 ? currentReservationStep = 1 : null">
                <AppIcon v-if="currentReservationStep > 1" name="check" :size="18"
                  class="transition-transform duration-300" />
                <span v-else class="transition-transform duration-300">1</span>
              </div>
              <span :class="[
                'text-sm font-semibold mt-1.5 transition-colors whitespace-nowrap',
                currentReservationStep >= 1 ? 'text-[#1E293B] dark:text-white' : 'text-[#94A3B8]'
              ]">
                Pelanggan
              </span>
            </div>

            <!-- Track 1 -> 2 (Strictly between Step 1 and Step 2) -->
            <div class="flex-1 mt-4.5 mx-3 h-0.5 rounded-full bg-blue-100/90 dark:bg-blue-950/70 overflow-hidden">
              <div class="h-full bg-[#4880FF] transition-all duration-400 ease-[cubic-bezier(0.34,1.3,0.64,1)]" :style="{
                width: currentReservationStep >= 2 ? '100%' : '0%'
              }" />
            </div>

            <!-- Step 2: Informasi Reservasi -->
            <div class="flex flex-col items-center shrink-0">
              <div :class="[
                'w-9 h-9 rounded-full flex items-center justify-center text-sm font-semibold transition-all duration-350 ease-out shadow-xs select-none',
                currentReservationStep >= 2
                  ? 'bg-[#4880FF] text-white scale-105'
                  : 'bg-blue-100 dark:bg-blue-950/60 text-[#4880FF] dark:text-blue-300 scale-100',
                currentReservationStep > 2 ? 'cursor-pointer active:scale-95' : ''
              ]" @click="currentReservationStep > 2 ? currentReservationStep = 2 : null">
                <AppIcon v-if="currentReservationStep > 2" name="check" :size="18"
                  class="transition-transform duration-300" />
                <span v-else class="transition-transform duration-300">2</span>
              </div>
              <span :class="[
                'text-sm font-semibold mt-1.5 transition-colors whitespace-nowrap',
                currentReservationStep >= 2 ? 'text-[#1E293B] dark:text-white' : 'text-[#94A3B8]'
              ]">
                Reservasi
              </span>
            </div>

            <!-- Track 2 -> 3 (Strictly between Step 2 and Step 3) -->
            <div class="flex-1 mt-4.5 mx-3 h-0.5 rounded-full bg-blue-100/90 dark:bg-blue-950/70 overflow-hidden">
              <div class="h-full bg-[#4880FF] transition-all duration-400 ease-[cubic-bezier(0.34,1.3,0.64,1)]" :style="{
                width: currentReservationStep >= 3 ? '100%' : '0%'
              }" />
            </div>

            <!-- Step 3: Review Informasi -->
            <div class="flex flex-col items-center shrink-0">
              <div :class="[
                'w-9 h-9 rounded-full flex items-center justify-center text-sm font-semibold transition-all duration-350 ease-out shadow-xs select-none',
                currentReservationStep === 3
                  ? 'bg-[#4880FF] text-white scale-105'
                  : 'bg-blue-100 dark:bg-blue-950/60 text-[#4880FF] dark:text-blue-300 scale-100'
              ]">
                <span class="transition-transform duration-300">3</span>
              </div>
              <span :class="[
                'text-sm font-semibold mt-1.5 transition-colors whitespace-nowrap',
                currentReservationStep === 3 ? 'text-[#1E293B] dark:text-white' : 'text-[#94A3B8]'
              ]">
                Review
              </span>
            </div>
          </div>
        </div>

        <!-- Dashed Horizontal Divider Line between Progressive Stepper and Form Input -->
        <div class="border-b border-dashed border-[#E2E8F0] dark:border-[#334155] my-5" />

        <!-- FORM CONTENT BY STEP -->
        <form @submit.prevent="currentReservationStep === 3 ? handleSaveReservation() : nextReservationStep()"
          class="space-y-4">
          <!-- STEP 1: INFORMASI PELANGGAN -->
          <div v-show="currentReservationStep === 1" class="space-y-4">
            <AppInput v-model="reservationForm.customerName" label="Nama Lengkap" placeholder="John Doe"
              maxlength="100" />

            <AppInput v-model="reservationForm.customerPhone" label="Nomor Telepon" placeholder="08123456789"
              maxlength="13" type="number" min="0" />

            <AppTextarea v-model="reservationForm.notes" label="Catatan Khusus (Opsional)"
              placeholder="Request dekat colokan / meja samping jendela" :rows="2" />
          </div>

          <!-- STEP 2: INFORMASI RESERVASI -->
          <div v-show="currentReservationStep === 2" class="space-y-4">
            <AppInput v-model="reservationForm.reservationTime" type="time" label="Jam Reservasi" />

            <AppInput v-model.number="reservationForm.guestCount" type="number" label="Jumlah Orang" min="1"
              @keydown="handleGuestCountKeydown" />

            <AppSelect v-model="reservationForm.tableId" :options="tableSelectOptions" label="Pilih Meja Tersedia" />
          </div>

          <!-- STEP 3: REVIEW INFORMASI (Clean Order Masuk Style) -->
          <div v-show="currentReservationStep === 3" class="py-1">
            <!-- Header: Customer Name & Table Tag -->
            <div class="flex items-start justify-between gap-2">
              <h3 class="text-base font-bold text-[#1E293B] dark:text-white leading-tight">
                {{ reservationForm.customerName || 'Tamu Reservasi' }}
              </h3>
              <span class="text-sm font-semibold text-[#94A3B8] dark:text-[#64748B] tabular-nums font-mono shrink-0">
                #{{ selectedReservationTable?.code || 'RES' }}
              </span>
            </div>

            <!-- Metadata Info Rows (2 Columns: Left = Time & Phone, Right = Table & Guests) -->
            <div class="grid grid-cols-2 gap-x-4 gap-y-2.5 mt-3">
              <!-- Left: Time Row -->
              <div
                class="flex items-center gap-2 text-sm text-[#64748B] dark:text-[#94A3B8] font-medium min-w-0">
                <AppIcon name="schedule" :size="16" class="text-[#94A3B8] dark:text-[#64748B] shrink-0" />
                <span class="truncate">{{ reservationForm.reservationTime || '18:00' }} WIB</span>
              </div>

              <!-- Right: Table Row (with Parenthesis) -->
              <div
                class="flex items-center gap-2 text-sm text-[#64748B] dark:text-[#94A3B8] font-medium min-w-0">
                <AppIcon name="restaurant" :size="16" class="text-[#94A3B8] dark:text-[#64748B] shrink-0" />
                <span class="truncate">{{ selectedReservationTable?.code || '-' }} ({{
                  selectedReservationTable?.isLarge ? '8 Kursi' : '4 Kursi' }})</span>
              </div>

              <!-- Left: Phone Row -->
              <div
                class="flex items-center gap-2 text-sm text-[#64748B] dark:text-[#94A3B8] font-medium min-w-0">
                <AppIcon name="call" :size="16" class="text-[#94A3B8] dark:text-[#64748B] shrink-0" />
                <span class="truncate">{{ reservationForm.customerPhone || '-' }}</span>
              </div>

              <!-- Right: Guest Count Row -->
              <div
                class="flex items-center gap-2 text-sm text-[#64748B] dark:text-[#94A3B8] font-medium min-w-0">
                <AppIcon name="group" :size="16" class="text-[#94A3B8] dark:text-[#64748B] shrink-0" />
                <span class="truncate">{{ reservationForm.guestCount || 4 }} Orang</span>
              </div>
            </div>

            <!-- Dashed Divider Line -->
            <div class="border-b border-dashed border-[#E2E8F0] dark:border-[#334155] my-4" />

            <!-- Special Notes or Confirmation Text -->
            <div class="space-y-1.5">
              <div class="flex items-center justify-between">
                <span class="text-sm font-bold text-[#1E293B] dark:text-white">
                  Catatan Khusus
                </span>
              </div>
              <p class="text-sm text-[#64748B] dark:text-[#CBD5E1] font-medium leading-relaxed">
                {{ reservationForm.notes || 'Tidak ada catatan tambahan' }}
              </p>
            </div>
          </div>

          <!-- MULTI-STEP MODAL FOOTER BUTTONS -->
          <div class="flex items-center justify-between gap-3 pt-4 border-t border-[#F1F5F9] dark:border-[#334155]">
            <!-- Left Action: Batal or Kembali -->
            <AppButton v-if="currentReservationStep === 1" type="button" variant="outline" size="md" class="!text-base"
              @click="isReservationModalOpen = false">
              Batal
            </AppButton>
            <AppButton v-else type="button" variant="outline" size="md" class="!text-base" icon="arrow_back"
              @click="prevReservationStep">
              Kembali
            </AppButton>

            <!-- Right Action: Lanjut or Konfirmasi -->
            <AppButton v-if="currentReservationStep < 3" type="button" variant="primary" size="md" class="!text-base"
              icon-right="arrow_forward"
              :disabled="currentReservationStep === 1 ? !reservationForm.customerName.trim() : !reservationForm.tableId"
              @click="nextReservationStep">
              {{ currentReservationStep === 1 ? 'Reservasi' : 'Review' }}
            </AppButton>
            <AppButton v-else type="submit" variant="primary" size="md" class="!text-base" icon="check">
              Konfirmasi & Buat Reservasi
            </AppButton>
          </div>
        </form>
      </div>
    </AppModal>

    <!-- Framer Motion: Reservation Success Modal -->
    <AppModal :show="isReservationSuccessOpen" v-model="isReservationSuccessOpen" title="Reservasi Berhasil!"
      maxWidth="sm">
      <div v-if="lastReservationData" class="space-y-4 text-center py-2">
        <!-- 1. Success Check Circle Badge (Framer Motion Spring Pop & Rotate In) -->
        <Motion :initial="{ scale: 0, opacity: 0, rotate: -45 }" :animate="{ scale: 1, opacity: 1, rotate: 0 }"
          :transition="{ type: 'spring', damping: 10, stiffness: 180, delay: 0.05 }"
          class="w-16 h-16 rounded-full bg-[#E6F9F5] text-[#00B69B] flex items-center justify-center mx-auto shadow-sm">
          <AppIcon name="check_circle" :size="36" />
        </Motion>

        <!-- 2. Header & Table Title (Framer Motion Fade Up) -->
        <Motion :initial="{ opacity: 0, y: 12 }" :animate="{ opacity: 1, y: 0 }"
          :transition="{ type: 'spring', damping: 15, stiffness: 120, delay: 0.15 }">
          <h4 class="text-xl font-bold text-[#1E293B] dark:text-white tracking-tight">
            {{ lastReservationData.tableCode }} Berhasil Direservasi
          </h4>
          <p class="text-sm text-[#64748B] dark:text-[#94A3B8] mt-1 font-medium">
            Atas nama <span class="font-bold text-[#1E293B] dark:text-white">{{ lastReservationData.customerName
            }}</span>
          </p>
        </Motion>

        <!-- 3. Reservation Details Card (shadow-xs, rounded-lg, text-sm, font-normal) -->
        <Motion :initial="{ opacity: 0, y: 20, scale: 0.96 }" :animate="{ opacity: 1, y: 0, scale: 1 }"
          :transition="{ type: 'spring', damping: 14, stiffness: 120, delay: 0.25 }"
          class="p-4 bg-[#F8FAFC] dark:bg-[#1E293B] rounded-lg text-left shadow-xs">
          <div class="grid grid-cols-2 gap-x-3 gap-y-2.5 text-sm">
            <div class="flex items-center gap-2 text-[#64748B] dark:text-[#94A3B8]">
              <AppIcon name="schedule" :size="16" class="shrink-0 text-[#4880FF]" />
              <span class="font-normal text-[#1E293B] dark:text-white truncate">{{ lastReservationData.time }}
                WIB</span>
            </div>
            <div class="flex items-center gap-2 text-[#64748B] dark:text-[#94A3B8]">
              <AppIcon name="restaurant" :size="16" class="shrink-0 text-[#4880FF]" />
              <span class="font-normal text-[#1E293B] dark:text-white truncate">{{ lastReservationData.tableCode }} ({{
                lastReservationData.capacity }})</span>
            </div>
            <div class="flex items-center gap-2 text-[#64748B] dark:text-[#94A3B8]">
              <AppIcon name="call" :size="16" class="shrink-0 text-[#4880FF]" />
              <span class="font-normal text-[#1E293B] dark:text-white truncate">{{ lastReservationData.customerPhone
              }}</span>
            </div>
            <div class="flex items-center gap-2 text-[#64748B] dark:text-[#94A3B8]">
              <AppIcon name="group" :size="16" class="shrink-0 text-[#4880FF]" />
              <span class="font-normal text-[#1E293B] dark:text-white truncate">{{ lastReservationData.guestCount }}
                Orang</span>
            </div>
          </div>

          <div v-if="lastReservationData.notes"
            class="border-t border-dashed border-[#E2E8F0] dark:border-[#334155] mt-2.5 pt-2 text-xs text-[#64748B] dark:text-[#94A3B8]">
            <span class="font-bold">Catatan:</span> {{ lastReservationData.notes }}
          </div>
        </Motion>
      </div>

      <template #footer>
        <!-- 4. Action Buttons (Framer Motion Staggered Spring Reveal) -->
        <Motion :initial="{ opacity: 0, y: 15 }" :animate="{ opacity: 1, y: 0 }"
          :transition="{ type: 'spring', damping: 14, stiffness: 120, delay: 0.35 }"
          class="flex items-center justify-end w-full gap-3">
          <AppButton variant="primary" size="md" icon="check" @click="isReservationSuccessOpen = false"
            class="w-full !rounded-lg !h-11 !text-sm !font-bold">
            Selesai
          </AppButton>
        </Motion>
      </template>
    </AppModal>
    <!-- FLOATING EXPAND BUTTON: Framer Motion Spring Pop Animation -->
    <AnimatePresence>
      <Motion v-if="!isStatusPanelExpanded" :initial="{ opacity: 0, y: 36, scale: 0.85 }"
        :animate="{ opacity: 1, y: 0, scale: 1 }" :exit="{ opacity: 0, y: 36, scale: 0.85 }"
        :transition="{ type: 'spring', damping: 18, stiffness: 240 }" class="fixed bottom-6 right-6 z-40">
        <button type="button" @click="isStatusPanelExpanded = true"
          class="flex items-center gap-4 px-6 py-4 bg-[#4880FF] hover:bg-[#3971F0] text-white rounded-2xl shadow-xl hover:shadow-2xl active:scale-95 transition-all cursor-pointer border border-white/20">
          <div class="relative flex items-center justify-center">
            <AppIcon name="table_restaurant" :size="28" />
          </div>
          <div class="text-left">
            <span class="text-sm font-bold opacity-90 block">Buka Status Meja</span>
            <span class="text-base font-bold tabular-nums">{{ availableTables.length }} Meja Kosong</span>
          </div>
          <div class="w-8 h-8 rounded-xl bg-white/20 flex items-center justify-center ml-1">
            <AppIcon name="chevron_left" :size="24" />
          </div>
        </button>
      </Motion>
    </AnimatePresence>
    <!-- Success Checkmark Overlay (GPU-Accelerated 60fps Smooth) -->
    <AnimatePresence>
      <Motion v-if="isCloseSuccess" :initial="{ opacity: 0 }" :animate="{ opacity: 1 }" :exit="{ opacity: 0 }"
        :transition="{ duration: 0.18, ease: 'easeOut' }"
        class="fixed inset-0 z-[99999] flex items-center justify-center pointer-events-none bg-black/45 [transform:translateZ(0)]">
        <Motion :initial="{ scale: 0.4, rotate: -20, opacity: 0 }" :animate="{ scale: 1, rotate: 0, opacity: 1 }"
          :exit="{ scale: 0.8, opacity: 0 }" :transition="{ type: 'spring', damping: 14, stiffness: 260 }"
          class="w-24 h-24 sm:w-28 sm:h-28 rounded-full bg-white dark:bg-[#1E293B] shadow-2xl border border-white/20 dark:border-[#334155] flex items-center justify-center text-[#00B69B] [transform:translateZ(0)]">
          <AppIcon name="check_circle" :size="64" />
        </Motion>
      </Motion>
    </AnimatePresence>
  </div>
</template>
