<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { Plus, Download, RefreshCw, QrCode, Printer, MoreVertical, Edit2, Trash2 } from 'lucide-vue-next'
import { usePosStore } from '@/stores/pos'
import { useAuthStore } from '@/stores/auth'
import { useDashboardI18n } from '@/i18n'
import AppButton from '@/components/ui/AppButton.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppTableFloorItem from '@/components/pos/AppTableFloorItem.vue'
import type { TableStatus } from '@/components/pos/AppTableFloorItem.vue'
import type { TableItem } from '@/types'

const posStore = usePosStore()
const authStore = useAuthStore()
const { t, translate, locale } = useDashboardI18n()

onMounted(async () => {
  posStore.initRealtime()
  await Promise.all([
    posStore.fetchTables(),
    posStore.fetchOrders()
  ])
  window.addEventListener('kds:refresh', handleTablesRefresh)
})

onBeforeUnmount(() => {
  window.removeEventListener('kds:refresh', handleTablesRefresh)
})

const handleTablesRefresh = async () => {
  await Promise.all([
    posStore.fetchTables(),
    posStore.fetchOrders()
  ])
}

const tables = computed(() => {
  return posStore.tables.map(t => {
    const rawCode = t.code || t.tableCode
    const cleanNum = rawCode ? rawCode.replace(/[^0-9]/g, '') : ''

    // Cek apakah meja memiliki pesanan aktif di kasir / dapur
    const hasActiveOrders = posStore.orders.some(o =>
      (o.tableId === t.id || o.tableCode === t.tableCode || o.tableCode === cleanNum) &&
      ['confirmed', 'preparing', 'ready'].includes(o.status)
    )

    let currentStatus = t.status
    if (hasActiveOrders) {
      currentStatus = 'occupied'
    }

    return {
      ...t,
      status: currentStatus,
      code: t.tableCode || (cleanNum ? `T-${cleanNum.padStart(2, '0')}` : (t.code || 'T-01')),
      qrToken: t.qrToken || `tok-${t.id ? t.id.slice(-6) : '001'}`
    }
  })
})

const isQrModalOpen = ref(false)
const selectedTable = ref<any>(null)

const showQr = (table: any) => {
  selectedTable.value = table
  isQrModalOpen.value = true
}

const regenerate = async (table: any) => {
  if (table.id) {
    await posStore.regenerateTableQr(table.id)
  }
  table.qrToken = `tok-new-${Date.now().toString().slice(-4)}`
}

const printQr = () => {
  window.print()
}

// Base URL Self-Order Configuration (Domain / Ngrok)
const getInitialBaseUrl = () => {
  if (typeof window !== 'undefined') {
    const saved = localStorage.getItem('lapaqu_qr_base_url')
    if (saved) return saved.trim().replace(/\/+$/, '')
    const envUrl = import.meta.env.VITE_CUSTOMER_URL || import.meta.env.VITE_APP_URL
    if (envUrl) return envUrl.trim().replace(/\/+$/, '')
    return window.location.origin
  }
  return 'https://order.lapaqu.id'
}

const currentBaseUrl = ref(getInitialBaseUrl())


const getSelfOrderUrl = (table: any) => {
  if (!table) return ''
  const base = (currentBaseUrl.value || window.location.origin).replace(/\/+$/, '')
  const outletId = table.outletId || authStore.currentUser?.outletId || ''
  const code = encodeURIComponent(table.code || table.tableCode || 'M01')
  const token = encodeURIComponent(table.qrToken || '')
  return `${base}/order/${outletId}/${code}${token ? `?token=${token}` : ''}`
}

const getQrUrl = (table: any) => {
  const targetUrl = getSelfOrderUrl(table)
  return `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(targetUrl)}`
}

const downloadSvg = async (table: any) => {
  const targetUrl = getSelfOrderUrl(table)
  try {
    const url = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&format=svg&data=${encodeURIComponent(targetUrl)}`
    const res = await fetch(url)
    const blob = await res.blob()
    const blobUrl = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = blobUrl
    a.download = `QR-${table.code || 'Meja'}.svg`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(blobUrl)
  } catch (e) {
    window.open(`https://api.qrserver.com/v1/create-qr-code/?size=300x300&format=svg&data=${encodeURIComponent(targetUrl)}`, '_blank')
  }
}

const getTableStatus = (status?: string): TableStatus => {
  if (status === 'occupied' || status === 'filled') return 'filled'
  if (status === 'reserved') return 'reserved'
  return 'available'
}

const getTableBadgeConfig = (status?: string) => {
  const s = getTableStatus(status)
  if (s === 'filled') {
    return {
      label: t('tables.statusOccupied', 'Terisi'),
      classes: 'bg-[#0F172A] text-white dark:bg-white dark:text-[#0F172A] shadow-xs'
    }
  }
  if (s === 'reserved') {
    return {
      label: t('tables.statusReserved', 'Reservasi'),
      classes: 'bg-[#4880FF] text-white shadow-xs shadow-[#4880FF]/25'
    }
  }
  return {
    label: t('tables.statusAvailable', 'Tersedia'),
    classes: 'bg-[#E2E8F0] dark:bg-[#334155] text-[#1E293B] dark:text-white'
  }
}

// Modal Tambah Meja
const isAddModalOpen = ref(false)
const newTable = ref({
  tableNumber: '',
  capacity: 4
})

const openAddTable = () => {
  const nextNum = tables.value.length + 1
  newTable.value = {
    tableNumber: String(nextNum).padStart(2, '0'),
    capacity: 4
  }
  isAddModalOpen.value = true
}

const handleSaveTable = async () => {
  if (!newTable.value.tableNumber.trim()) return
  await posStore.createTable({
    table_number: `Meja ${newTable.value.tableNumber}`,
    capacity: Number(newTable.value.capacity) || 4
  })
  isAddModalOpen.value = false
}

// Action Menu State (Click outside to close)
const activeMenuTableId = ref<string | null>(null)

const toggleMenu = (tableId: string) => {
  activeMenuTableId.value = activeMenuTableId.value === tableId ? null : tableId
}

onMounted(() => {
  window.addEventListener('click', () => {
    activeMenuTableId.value = null
  })
})

// Modal Edit Meja
const isEditModalOpen = ref(false)
const tableToEdit = ref<any>(null)
const editTableForm = ref({
  tableNumber: '',
  capacity: 4
})

const openEditTable = (table: any) => {
  activeMenuTableId.value = null
  tableToEdit.value = table
  editTableForm.value = {
    tableNumber: table.code.replace(/[^0-9]/g, '') || table.code,
    capacity: table.capacity || 4
  }
  isEditModalOpen.value = true
}

const handleSaveEditTable = async () => {
  if (!tableToEdit.value || !editTableForm.value.tableNumber.trim()) return
  await posStore.updateTable(tableToEdit.value.id, {
    table_number: `Meja ${editTableForm.value.tableNumber}`,
    capacity: Number(editTableForm.value.capacity) || 4
  })
  isEditModalOpen.value = false
}

// Modal Hapus Meja
const isDeleteModalOpen = ref(false)
const tableToDelete = ref<any>(null)

const openDeleteTable = (table: any) => {
  activeMenuTableId.value = null
  tableToDelete.value = table
  isDeleteModalOpen.value = true
}

const handleConfirmDelete = async () => {
  if (!tableToDelete.value) return
  await posStore.deleteTable(tableToDelete.value.id)
  isDeleteModalOpen.value = false
}
</script>

<template>
  <div class="space-y-6 font-sans">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-[#202224] dark:text-white">{{ t('tables.pageTitle', 'Meja & QR Code') }}</h1>
      </div>

      <div class="flex items-center gap-2.5">
        <AppButton variant="primary" size="md" @click="openAddTable" icon="add" class="!rounded-lg shadow-xs">
          {{ t('tables.addTable', 'Tambah Meja') }}
        </AppButton>
      </div>
    </div>

    <!-- Table Cards Grid using Kasir Table Style (AppTableFloorItem) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
      <div v-for="table in tables" :key="table.id"
        class="bg-white dark:bg-[#273142] rounded-2xl p-5 shadow-xs border border-transparent dark:border-[#313D4F] flex flex-col justify-between space-y-4 hover:shadow-sm transition-all">
        <!-- Top Info: Capacity, Status Badge, & Menu Opsi (Edit / Hapus) -->
        <div class="flex items-center justify-between gap-2">
          <span class="text-xs font-semibold text-[#64748B] dark:text-[#94A3B8] truncate">
            {{ table.capacity || 4 }} {{ t('tables.chairUnit', 'Kursi') }} • {{ (table.capacity || 4) > 4 ? (locale ===
              'en'
              ? 'Large Table' : 'Meja Besar') : (locale === 'en' ? 'Regular Table' : 'Meja Normal') }}
          </span>

          <div class="flex items-center gap-1.5 shrink-0">
            <!-- Badge Status Sesuai Warna Legend -->
            <span :class="[
              'px-2.5 py-0.5 rounded-full text-xs font-bold whitespace-nowrap',
              getTableBadgeConfig(table.status).classes
            ]">
              {{ getTableBadgeConfig(table.status).label }}
            </span>

            <!-- Menu Titik Tiga (••• simetris kanan ala Shadcn UI) -->
            <div class="relative inline-flex" @click.stop>
              <button type="button" @click="toggleMenu(table.id)"
                class="w-7 h-7 rounded-lg flex items-center justify-center text-[#94A3B8] hover:text-[#1E293B] dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer"
                title="Opsi Meja">
                <MoreVertical class="w-4 h-4" />
              </button>

              <!-- Popover Dropdown Menu dengan Smooth Vue Transition ala Shadcn UI -->
              <Transition enter-active-class="transition duration-200 ease-out"
                enter-from-class="transform scale-95 opacity-0 -translate-y-1"
                enter-to-class="transform scale-100 opacity-100 translate-y-0"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="transform scale-100 opacity-100 translate-y-0"
                leave-to-class="transform scale-95 opacity-0 -translate-y-1">
                <div v-if="activeMenuTableId === table.id"
                  class="absolute right-0 top-full mt-1.5 w-44 bg-white dark:bg-[#1E293B] rounded-xl shadow-xl border border-[#E2E8F0] dark:border-[#334155] p-1.5 space-y-1 z-50 text-sm font-semibold origin-top-right will-change-transform">
                  <button type="button" @click="openEditTable(table)"
                    class="group w-full px-3 py-2.5 rounded-lg text-left text-[#202224] dark:text-[#E2E8F0] hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10 flex items-center gap-2.5 transition-all duration-150 ease-in-out cursor-pointer active:scale-[0.98]">
                    <Edit2
                      class="w-4 h-4 text-[#64748B] dark:text-[#94A3B8] group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors duration-150" />
                    <span>{{ t('common.edit', 'Edit') }} {{ t('tables.tableNumber', 'Meja') }}</span>
                  </button>
                  <button type="button" @click="openDeleteTable(table)"
                    class="group w-full px-3 py-2.5 rounded-lg text-left text-[#202224] dark:text-[#E2E8F0] hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 flex items-center gap-2.5 transition-all duration-150 ease-in-out cursor-pointer active:scale-[0.98]">
                    <Trash2
                      class="w-4 h-4 text-[#64748B] dark:text-[#94A3B8] group-hover:text-red-600 dark:group-hover:text-red-400 transition-colors duration-150" />
                    <span>{{ t('common.delete', 'Hapus') }} {{ t('tables.tableNumber', 'Meja') }}</span>
                  </button>
                </div>
              </Transition>
            </div>
          </div>
        </div>

        <!-- Table Floor Graphic (Visual Meja & Kursi dari Kasir) -->
        <div class="py-3 flex items-center justify-center w-full min-h-[110px]">
          <AppTableFloorItem :code="table.code" :status="getTableStatus(table.status)"
            :is-large="(table.capacity || 4) > 4" class="pointer-events-none" />
        </div>

        <!-- QR Token & Regenerate Button -->
        <div
          class="p-2.5 bg-[#F8FAFC] dark:bg-[#1E293B] rounded-xl flex items-center justify-between border border-[#F1F5F9] dark:border-[#334155]">
          <span class="text-[11px] font-mono font-bold text-[#64748B] dark:text-[#94A3B8] truncate max-w-[140px]">
            {{ table.qrToken }}
          </span>
          <button @click="regenerate(table)"
            class="text-[#4880FF] text-xs font-bold flex items-center gap-1 hover:underline cursor-pointer shrink-0 ml-2">
            <RefreshCw class="w-3 h-3" />
            {{ t('tables.regenerateToken', 'Perbarui') }}
          </button>
        </div>

        <!-- Bottom Action Buttons: Lihat QR & Lihat SVG (Enlarged + rounded-lg) -->
        <div class="flex gap-2.5 pt-1">
          <AppButton @click="showQr(table)" variant="primary" size="md"
            class="w-full !rounded-lg !py-2.5 !text-xs sm:!text-sm font-bold shadow-xs">
            <template #prefix>
              <QrCode class="w-4 h-4 shrink-0" />
            </template>
            {{ t('tables.viewQr', 'Lihat QR') }}
          </AppButton>
          <AppButton @click="downloadSvg(table)" variant="outline" size="md"
            class="w-fit !rounded-lg !py-2.5 !text-xs sm:!text-sm font-bold shadow-xs">
            <template #prefix>
              <Download class="w-4 h-4 shrink-0" />
            </template>
          </AppButton>
        </div>
      </div>
    </div>

    <!-- QR Preview Modal (Boarding Pass Ticket Card Style) -->
    <AppModal v-model="isQrModalOpen" maxWidth="sm" :footerBorder="false" contentClass="!pb-0">
      <div v-if="selectedTable" class="text-center space-y-3.5 pt-1 pb-0">
        <!-- Big QR Code (Direct on clean white area) -->
        <div class="flex items-center justify-center">
          <div
            class="w-56 h-56 sm:w-60 sm:h-60 bg-white p-3 rounded-2xl flex items-center justify-center border border-[#F1F5F9] dark:border-[#313D4F] shadow-xs">
            <img :src="getQrUrl(selectedTable)" alt="QR Code" class="w-full h-full object-contain" />
          </div>
        </div>

        <!-- Name / Title & Subtitle -->
        <div>
          <h3 class="text-xl font-bold text-[#1E293B] dark:text-white tracking-tight">
            {{ selectedTable.code }}
          </h3>
          <p class="text-xs font-medium text-[#64748B] dark:text-[#94A3B8] mt-1">
            {{ locale === 'en' ? 'Scan to open Customer Self-Order' : 'Scan untuk membuka Customer Self-Order' }}
          </p>
        </div>

        <!-- 3-Column Ticket Pill Container -->
        <div
          class="bg-[#F8FAFC] dark:bg-[#1E293B] border-0 rounded-lg py-3 px-2 grid grid-cols-3 divide-x divide-[#E2E8F0] dark:divide-[#334155] items-center text-center shadow-xs">
          <!-- Col 1: Flight no. -> No. Meja -->
          <div class="px-2">
            <span class="block text-[11px] font-medium text-[#64748B] dark:text-[#94A3B8] mb-0.5">
              {{ t('tables.tableNumber', 'No. Meja') }}
            </span>
            <span class="block text-sm font-bold text-[#1E293B] dark:text-white tracking-tight truncate">
              {{ selectedTable.code }}
            </span>
          </div>

          <!-- Col 2: Status (Menggunakan badge status bawaan) -->
          <div class="px-2 flex flex-col items-center justify-center">
            <span class="block text-[11px] font-medium text-[#64748B] dark:text-[#94A3B8] mb-0.5">
              {{ t('common.status', 'Status') }}
            </span>
            <span :class="[
              'inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-bold whitespace-nowrap',
              getTableBadgeConfig(selectedTable.status).classes
            ]">
              {{ getTableBadgeConfig(selectedTable.status).label }}
            </span>
          </div>

          <!-- Col 3: Kapasitas Kursi -->
          <div class="px-2">
            <span class="block text-[11px] font-medium text-[#64748B] dark:text-[#94A3B8] mb-0.5">
              {{ t('tables.capacity', 'Kapasitas') }}
            </span>
            <span class="block text-sm font-bold text-[#1E293B] dark:text-white tracking-tight truncate">
              {{ selectedTable.capacity || 4 }} {{ t('tables.chairUnit', 'Kursi') }}
            </span>
          </div>
        </div>
      </div>
      <template #footer>
        <div class="flex items-center justify-between w-full gap-2">
          <AppButton @click="isQrModalOpen = false" variant="outline" size="md" class="flex-1">
            {{ t('common.close', 'Tutup') }}
          </AppButton>
          <AppButton variant="primary" size="md" class="flex-1" @click="printQr">
            <template #prefix>
              <Printer class="w-4 h-4" />
            </template>
            {{ t('tables.printQr', 'Cetak QR') }}
          </AppButton>
        </div>
      </template>
    </AppModal>

    <!-- Modal Tambah Meja -->
    <AppModal v-model="isAddModalOpen" :title="t('tables.modalAddTitle', 'Tambah Meja Baru')" maxWidth="sm">
      <div class="space-y-4 py-2">
        <div>
          <label class="block text-xs font-bold text-[#1E293B] dark:text-white mb-1.5">{{ t('tables.formNumber', 'Nomor Meja') }}</label>
          <AppInput v-model="newTable.tableNumber" placeholder="05 atau T-05" />
        </div>
        <div>
          <label class="block text-xs font-bold text-[#1E293B] dark:text-white mb-1.5">{{ t('tables.formCapacity', 'Kapasitas Kursi') }}</label>
          <AppInput v-model="newTable.capacity" type="number" min="1" max="20" placeholder="4" />
        </div>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2 w-full">
          <AppButton variant="outline" size="md" @click="isAddModalOpen = false">{{ t('common.cancel', 'Batal') }}
          </AppButton>
          <AppButton variant="primary" size="md" @click="handleSaveTable">{{ t('common.save', 'Simpan') }}</AppButton>
        </div>
      </template>
    </AppModal>
    <!-- Modal Edit Meja -->
    <AppModal v-model="isEditModalOpen" :title="`Edit ${tableToEdit?.code || 'Meja'}`" maxWidth="sm">
      <div class="space-y-4 py-2">
        <div>
          <label class="block text-xs font-bold text-[#1E293B] dark:text-white mb-1.5">{{ t('tables.formNumber', 'Nomor Meja') }}</label>
          <AppInput v-model="editTableForm.tableNumber" placeholder="05 atau T-05" />
        </div>
        <div>
          <label class="block text-xs font-bold text-[#1E293B] dark:text-white mb-1.5">{{ t('tables.formCapacity', 'Kapasitas Kursi') }}</label>
          <AppInput v-model="editTableForm.capacity" type="number" min="1" max="20" placeholder="4" />
        </div>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2 w-full">
          <AppButton variant="outline" size="md" @click="isEditModalOpen = false">{{ t('common.cancel', 'Batal') }}
          </AppButton>
          <AppButton variant="primary" size="md" @click="handleSaveEditTable">{{ t('common.save', 'Simpan Perubahan') }}
          </AppButton>
        </div>
      </template>
    </AppModal>

    <!-- Modal Hapus Meja -->
    <AppModal v-model="isDeleteModalOpen" :title="t('common.delete', 'Hapus') + ' ' + t('tables.tableNumber', 'Meja')"
      maxWidth="sm">
      <div v-if="tableToDelete" class="space-y-3 py-1 text-sm">
        <p class="text-[#64748B] dark:text-[#94A3B8]">
          {{ locale === 'en' ? 'Are you sure you want to delete' : 'Apakah Anda yakin ingin menghapus' }} <span
            class="font-bold text-[#1E293B] dark:text-white">{{ tableToDelete.code
            }}</span>?
        </p>
        <p class="text-sm text-[#EF4444] bg-[#EF4444]/10 dark:bg-[#EF4444]/20 p-2.5 rounded-lg font-medium">
          {{ locale === 'en' ? 'This action cannot be undone. QR Code for this table will no longer be usable by customers.' : 'Tindakan ini tidak dapat dibatalkan. QR Code untuk meja ini tidak akan dapat digunakan lagi oleh pelanggan.' }}
        </p>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2 w-full">
          <AppButton variant="outline" size="md" @click="isDeleteModalOpen = false">{{ t('common.cancel', 'Batal') }}
          </AppButton>
          <AppButton variant="danger" size="md" @click="handleConfirmDelete">{{ t('common.delete', 'Ya, Hapus') }}
          </AppButton>
        </div>
      </template>
    </AppModal>
  </div>
</template>
