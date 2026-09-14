<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Plus, Eye, CheckCircle2, AlertCircle } from 'lucide-vue-next'
import { usePosStore } from '@/stores/pos'
import { useFormat } from '@/composables/useFormat'
import AppTable from '@/components/ui/AppTable.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppFilterDropdown from '@/components/ui/AppFilterDropdown.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import type { StockOpname } from '@/types'

const posStore = usePosStore()
const { formatNumber } = useFormat()

const isLoading = ref(true)
const isSubmitting = ref(false)
const opnamesList = ref<StockOpname[]>([])
const searchQuery = ref('')
const selectedStatusFilter = ref('all')
const selectedSort = ref('newest')

// Notification
const notification = ref<{ type: 'success' | 'error'; message: string } | null>(null)
let notificationTimer: ReturnType<typeof setTimeout> | null = null

const showNotification = (type: 'success' | 'error', message: string) => {
  if (notificationTimer) clearTimeout(notificationTimer)
  notification.value = { type, message }
  notificationTimer = setTimeout(() => {
    notification.value = null
  }, 4000)
}

// Opname Form Modal
const isCreateModalOpen = ref(false)
const opnameNotes = ref('')
const opnameRows = ref<Array<{
  ingredient_id: string
  name: string
  unit: string
  system_stock: number
  physical_stock: number | ''
  notes: string
}>>([])
const formError = ref('')

// View Detail Modal
const isDetailModalOpen = ref(false)
const selectedOpname = ref<StockOpname | null>(null)
const isDetailLoading = ref(false)

const loadOpnames = async () => {
  isLoading.value = true
  try {
    const res = await posStore.fetchStockOpnames()
    if (res && res.data) {
      opnamesList.value = res.data
    }
  } catch (err) {
    console.error('Error loading stock opnames:', err)
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadOpnames()
})

const statusOptions = [
  { value: 'all', label: 'Semua Status Selisih' },
  { value: 'diff', label: 'Ada Selisih' },
  { value: 'match', label: 'Semua Cocok' },
]

const sortOptions = [
  { value: 'newest', label: 'Audit Terbaru' },
  { value: 'oldest', label: 'Audit Terlama' },
]

const columns = [
  { key: 'opnameNumber', label: 'No. Opname', width: '22%' },
  { key: 'opnameDate', label: 'Waktu Audit', width: '20%' },
  { key: 'creator', label: 'Petugas Auditor', width: '18%' },
  { key: 'totalItems', label: 'Total Bahan', align: 'center' as const, width: '14%' },
  { key: 'status', label: 'Status Selisih', align: 'center' as const, width: '14%' },
  { key: 'actions', label: 'Aksi', align: 'center' as const, width: '12%' },
]

const filteredOpnames = computed(() => {
  let list = [...(opnamesList.value || [])]

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(o =>
      o.opnameNumber.toLowerCase().includes(q) ||
      (o.notes && o.notes.toLowerCase().includes(q)) ||
      (o.creator?.name && o.creator.name.toLowerCase().includes(q))
    )
  }

  if (selectedStatusFilter.value === 'diff') {
    list = list.filter(o => {
      const items = o.items || []
      return items.some(it => it.difference !== 0)
    })
  } else if (selectedStatusFilter.value === 'match') {
    list = list.filter(o => {
      const items = o.items || []
      return items.length > 0 && items.every(it => it.difference === 0)
    })
  }

  if (selectedSort.value === 'oldest') {
    list.sort((a, b) => new Date(a.createdAt).getTime() - new Date(b.createdAt).getTime())
  } else {
    list.sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime())
  }

  return list
})

const openCreateModal = async () => {
  formError.value = ''
  opnameNotes.value = ''

  await posStore.fetchIngredients()

  opnameRows.value = posStore.ingredients.map(ing => ({
    ingredient_id: ing.id,
    name: ing.name,
    unit: ing.unit,
    system_stock: ing.displayStock ?? ing.currentStock,
    physical_stock: ing.displayStock ?? ing.currentStock,
    notes: '',
  }))

  isCreateModalOpen.value = true
}

const openDetailModal = async (item: StockOpname) => {
  selectedOpname.value = item
  isDetailLoading.value = true
  isDetailModalOpen.value = true
  try {
    const detail = await posStore.getStockOpname(item.id)
    if (detail) {
      selectedOpname.value = detail
    }
  } catch (err) {
    console.error('Error loading opname detail:', err)
  } finally {
    isDetailLoading.value = false
  }
}

const formatDate = (dateStr?: string) => {
  if (!dateStr) return '-'
  return new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(dateStr))
}

const hasDiscrepancy = (op: StockOpname): boolean => {
  const items = op.items || []
  return items.some(it => it.difference !== 0)
}

const handleSubmitOpname = async () => {
  for (const row of opnameRows.value) {
    if (row.physical_stock === '' || Number(row.physical_stock) < 0) {
      formError.value = `Nilai stok fisik untuk '${row.name}' tidak boleh kosong atau negatif.`
      return
    }
  }

  formError.value = ''
  isSubmitting.value = true

  try {
    const payload = {
      notes: opnameNotes.value.trim() || undefined,
      items: opnameRows.value.map(r => ({
        ingredient_id: r.ingredient_id,
        physical_stock: Number(r.physical_stock),
        notes: r.notes.trim() || undefined,
      })),
    }

    const res = await posStore.submitStockOpname(payload)
    showNotification('success', res.message || 'Stok opname berhasil disimpan dan stok telah disinkronkan!')
    isCreateModalOpen.value = false
    await loadOpnames()
  } catch (err: any) {
    formError.value = err?.response?.data?.message || 'Gagal menyimpan stok opname.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Notification Banner (Toast) -->
    <transition enter-active-class="transition duration-300 ease-out transform"
      enter-from-class="-translate-y-2 opacity-0" enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-200 ease-in transform" leave-from-class="translate-y-0 opacity-100"
      leave-to-class="-translate-y-2 opacity-0">
      <div v-if="notification" :class="[
        'flex items-center justify-between gap-3 px-4 py-3 rounded-xl border text-sm font-semibold shadow-sm',
        notification.type === 'success'
          ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300'
          : 'bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300'
      ]">
        <div class="flex items-center gap-2">
          <AppIcon :name="notification.type === 'success' ? 'check_circle' : 'error'" :size="20" />
          <span>{{ notification.message }}</span>
        </div>
        <button type="button" @click="notification = null"
          class="text-current opacity-70 hover:opacity-100 transition-opacity cursor-pointer">
          <AppIcon name="close" :size="18" />
        </button>
      </div>
    </transition>

    <!-- Header Page (Tanpa Sub Header Kecil) -->
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-[#202224] dark:text-white">Stok Opname</h1>
      <div class="flex items-center gap-2 shrink-0">
        <AppButton @click="openCreateModal" variant="primary" size="md" icon="add" class="!rounded-lg !font-bold">
          Mulai Stok Opname
        </AppButton>
      </div>
    </div>

    <!-- Reusable AppTable Component with Integrated Header -->
    <AppTable
      :columns="columns"
      :data="filteredOpnames"
      :loading="isLoading"
      :pageSize="20"
      :searchable="false"
      showNumbering
      numberingLabel="No"
      emptyMessage="Belum ada riwayat stok opname yang tercatat"
    >
      <!-- Integrated Header (Segmented Pills & Reusable AppInput Search) -->
      <template #header>
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 w-full">
          <!-- Left: Segmented Pill Filter Container -->
          <div class="flex items-center gap-1.5 p-1 bg-[#F1F4F9] dark:bg-[#1E293B] rounded-xl">
            <!-- 1. Filter Status Selisih -->
            <AppFilterDropdown v-model="selectedStatusFilter" :options="statusOptions" width="w-52" />

            <!-- 2. Sort Dropdown -->
            <AppFilterDropdown v-model="selectedSort" :options="sortOptions" width="w-44" />
          </div>

          <!-- Right: Reusable AppInput Search -->
          <div class="w-full sm:w-[250px] shrink-0">
            <AppInput
              v-model="searchQuery"
              placeholder="Cari nomor opname / catatan..."
              suffixIcon="search"
              clearable
              inputClass="!h-[38px] !text-xs sm:!text-sm"
            />
          </div>
        </div>
      </template>

      <!-- Cell: Nomor Opname -->
      <template #cell-opnameNumber="{ row, value }">
        <div>
          <span class="font-bold text-sm text-[#4880FF] hover:underline cursor-pointer block" @click="openDetailModal(row)">
            {{ value }}
          </span>
          <span v-if="row.notes" class="text-xs text-[#64748B] dark:text-[#94A3B8] block truncate max-w-xs">
            {{ row.notes }}
          </span>
        </div>
      </template>

      <!-- Cell: Waktu Audit -->
      <template #cell-opnameDate="{ row }">
        <span class="text-xs font-semibold text-[#64748B] dark:text-[#94A3B8]">
          {{ formatDate(row.opnameDate || row.createdAt) }}
        </span>
      </template>

      <!-- Cell: Petugas Auditor -->
      <template #cell-creator="{ row }">
        <span class="text-sm font-semibold text-[#202224] dark:text-white">
          {{ row.creator?.name || 'Administrator' }}
        </span>
      </template>

      <!-- Cell: Total Bahan -->
      <template #cell-totalItems="{ row }">
        <span class="font-bold text-sm text-[#202224] dark:text-white tabular-nums">
          {{ row.items?.length || 0 }} Bahan
        </span>
      </template>

      <!-- Cell: Status Selisih -->
      <template #cell-status="{ row }">
        <div class="flex justify-center">
          <AppBadge v-if="hasDiscrepancy(row)" variant="warning" size="md" rounded="full">
            Ada Selisih
          </AppBadge>
          <AppBadge v-else variant="success" size="md" rounded="full">
            Semua Cocok
          </AppBadge>
        </div>
      </template>

      <!-- Cell: Aksi -->
      <template #cell-actions="{ row }">
        <div class="flex items-center justify-center">
          <AppButton variant="secondary" size="sm" @click="openDetailModal(row)" class="!rounded-lg !text-xs !font-bold">
            <Eye class="w-3.5 h-3.5 mr-1 text-[#4880FF]" />
            Detail
          </AppButton>
        </div>
      </template>
    </AppTable>

    <!-- Modal Form Stok Opname Baru -->
    <AppModal
      :show="isCreateModalOpen"
      title="Formulir Stok Opname Bahan"
      maxWidth="2xl"
      @close="isCreateModalOpen = false"
    >
      <div class="space-y-4">
        <div class="p-3 bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20 text-blue-800 dark:text-blue-300 rounded-xl text-xs font-medium">
          💡 Masukkan jumlah stok fisik aktual hasil perhitungan di lapangan. Jika ada perbedaan dengan stok sistem, mutasi selisih stok opname akan otomatis tercatat.
        </div>

        <div v-if="formError" class="p-3 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-700 dark:text-red-400 rounded-xl text-xs font-medium">
          {{ formError }}
        </div>

        <div>
          <label class="block text-xs font-bold text-[#202224] dark:text-white mb-1">Catatan Opname (Opsional)</label>
          <AppInput v-model="opnameNotes" placeholder="Contoh: Opname mingguan / Tutup shift malam..." />
        </div>

        <!-- Table of Ingredients to Audit -->
        <div class="border border-[#E2E8F0] dark:border-[#334155] rounded-xl overflow-hidden max-h-80 overflow-y-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead class="bg-[#F8FAFC] dark:bg-[#1E293B] sticky top-0 border-b border-[#E2E8F0] dark:border-[#334155] text-[#64748B] dark:text-[#94A3B8] font-bold">
              <tr>
                <th class="p-3">Bahan Baku</th>
                <th class="p-3 text-right">Stok Sistem</th>
                <th class="p-3 text-right w-28">Stok Fisik</th>
                <th class="p-3 text-center">Selisih</th>
                <th class="p-3 w-36">Keterangan</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#F1F5F9] dark:divide-[#334155]">
              <tr v-for="(row, idx) in opnameRows" :key="idx" class="hover:bg-[#F8FAFC] dark:hover:bg-[#1E293B]/50">
                <td class="p-3 font-semibold text-[#202224] dark:text-white">
                  {{ row.name }}
                </td>
                <td class="p-3 text-right text-[#64748B] dark:text-[#94A3B8] font-mono">
                  {{ formatNumber(row.system_stock) }} {{ row.unit }}
                </td>
                <td class="p-3 text-right">
                  <input
                    v-model="row.physical_stock"
                    type="number"
                    step="any"
                    min="0"
                    class="w-full px-2 py-1 text-xs bg-white dark:bg-[#0F172A] border border-[#CBD5E1] dark:border-[#475569] rounded-lg focus:outline-none text-right font-mono font-bold text-[#202224] dark:text-white"
                  />
                </td>
                <td class="p-3 text-center">
                  <span
                    v-if="row.physical_stock !== ''"
                    class="inline-block px-2 py-0.5 rounded-full text-[11px] font-bold font-mono"
                    :class="{
                      'bg-gray-100 dark:bg-gray-800 text-[#64748B] dark:text-[#94A3B8]': Number(row.physical_stock) === row.system_stock,
                      'bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400': Number(row.physical_stock) > row.system_stock,
                      'bg-red-100 dark:bg-red-500/20 text-red-700 dark:text-red-400': Number(row.physical_stock) < row.system_stock,
                    }"
                  >
                    {{ Number(row.physical_stock) - row.system_stock > 0 ? '+' : '' }}{{ formatNumber(Number(row.physical_stock) - row.system_stock) }}
                  </span>
                </td>
                <td class="p-3">
                  <input
                    v-model="row.notes"
                    type="text"
                    placeholder="Keterangan..."
                    class="w-full px-2 py-1 text-[11px] bg-white dark:bg-[#0F172A] border border-[#CBD5E1] dark:border-[#475569] rounded-lg focus:outline-none text-[#202224] dark:text-white"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
          <AppButton variant="secondary" size="md" @click="isCreateModalOpen = false">Batal</AppButton>
          <AppButton variant="primary" size="md" :loading="isSubmitting" @click="handleSubmitOpname">
            Konfirmasi & Simpan Stok
          </AppButton>
        </div>
      </div>
    </AppModal>

    <!-- Modal Detail Stok Opname -->
    <AppModal
      :show="isDetailModalOpen"
      :title="`Detail Opname: ${selectedOpname?.opnameNumber || ''}`"
      maxWidth="2xl"
      @close="isDetailModalOpen = false"
    >
      <div v-if="isDetailLoading" class="p-8 text-center text-[#64748B] dark:text-[#94A3B8]">
        <p class="text-xs font-semibold">Memuat detail opname...</p>
      </div>

      <div v-else class="space-y-4">
        <div class="grid grid-cols-2 gap-3 text-xs bg-[#F8FAFC] dark:bg-[#1E293B] p-3.5 rounded-xl border border-[#E2E8F0] dark:border-[#334155]">
          <div>
            <span class="text-[#64748B] dark:text-[#94A3B8] block mb-0.5">Tanggal Audit:</span>
            <span class="font-bold text-[#202224] dark:text-white">{{ formatDate(selectedOpname?.opnameDate || selectedOpname?.createdAt) }}</span>
          </div>
          <div>
            <span class="text-[#64748B] dark:text-[#94A3B8] block mb-0.5">Petugas Auditor:</span>
            <span class="font-bold text-[#202224] dark:text-white">{{ selectedOpname?.creator?.name || 'Administrator' }}</span>
          </div>
          <div class="col-span-2 pt-2 border-t border-[#E2E8F0] dark:border-[#334155]">
            <span class="text-[#64748B] dark:text-[#94A3B8] block mb-0.5">Catatan:</span>
            <span class="font-medium text-[#202224] dark:text-white">{{ selectedOpname?.notes || '-' }}</span>
          </div>
        </div>

        <div class="border border-[#E2E8F0] dark:border-[#334155] rounded-xl overflow-hidden max-h-72 overflow-y-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead class="bg-[#F8FAFC] dark:bg-[#1E293B] sticky top-0 border-b border-[#E2E8F0] dark:border-[#334155] text-[#64748B] dark:text-[#94A3B8] font-bold">
              <tr>
                <th class="p-3">Bahan</th>
                <th class="p-3 text-right">Stok Sistem</th>
                <th class="p-3 text-right">Stok Fisik</th>
                <th class="p-3 text-center">Selisih</th>
                <th class="p-3">Catatan</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#F1F5F9] dark:divide-[#334155]">
              <tr v-for="item in selectedOpname?.items" :key="item.id" class="hover:bg-[#F8FAFC] dark:hover:bg-[#1E293B]/50">
                <td class="p-3 font-semibold text-[#202224] dark:text-white">
                  {{ item.ingredient?.name || '-' }}
                </td>
                <td class="p-3 text-right text-[#64748B] dark:text-[#94A3B8] font-mono">
                  {{ formatNumber(item.systemStock) }} {{ item.unit }}
                </td>
                <td class="p-3 text-right font-mono font-bold text-[#202224] dark:text-white">
                  {{ formatNumber(item.physicalStock) }} {{ item.unit }}
                </td>
                <td class="p-3 text-center">
                  <span
                    class="inline-block px-2 py-0.5 rounded-full text-[11px] font-bold font-mono"
                    :class="{
                      'bg-gray-100 dark:bg-gray-800 text-[#64748B] dark:text-[#94A3B8]': item.difference === 0,
                      'bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400': item.difference > 0,
                      'bg-red-100 dark:bg-red-500/20 text-red-700 dark:text-red-400': item.difference < 0,
                    }"
                  >
                    {{ item.difference > 0 ? '+' : '' }}{{ formatNumber(item.difference) }}
                  </span>
                </td>
                <td class="p-3 text-[#64748B] dark:text-[#94A3B8] text-[11px]">
                  {{ item.notes || '-' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex items-center justify-end pt-2">
          <AppButton variant="secondary" size="md" @click="isDetailModalOpen = false">Tutup</AppButton>
        </div>
      </div>
    </AppModal>
  </div>
</template>
