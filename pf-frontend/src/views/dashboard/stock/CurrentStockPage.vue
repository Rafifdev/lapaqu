<script setup lang="ts">
import { useNotyf } from '@/composables/useNotyf'
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { PlusCircle, MoreVertical, History, AlertTriangle } from 'lucide-vue-next'
import { usePosStore } from '@/stores/pos'
import { useFormat } from '@/composables/useFormat'
import AppTable from '@/components/ui/AppTable.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppStatCard from '@/components/ui/AppStatCard.vue'
import AppFilterDropdown from '@/components/ui/AppFilterDropdown.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import type { Ingredient } from '@/types'

const router = useRouter()
const posStore = usePosStore()
const { formatNumber } = useFormat()

const isLoading = ref(true)
const isSubmitting = ref(false)
const searchQuery = ref('')
const selectedCategoryId = ref('all')
const statusFilter = ref<'all' | 'safe' | 'low' | 'empty'>('all')

// Notification
const notyf = useNotyf()

const showNotification = (type: 'success' | 'error', message: string) => {
  if (type === 'success') {
    notyf.success(message)
  } else {
    notyf.error(message)
  }
}

// Dropdown Titik Tiga (Aksi Bahan)
const activeMenuId = ref<string | null>(null)
const toggleMenu = (id: string) => {
  activeMenuId.value = activeMenuId.value === id ? null : id
}
const closeMenu = () => {
  activeMenuId.value = null
}

// Restock / Adjust Modal
const isAdjustModalOpen = ref(false)
const targetIngredient = ref<Ingredient | null>(null)
const adjustType = ref<'restock' | 'set'>('restock')
const adjustQty = ref<number | ''>('')
const adjustNotes = ref('')
const modalError = ref('')

const loadData = async () => {
  isLoading.value = true
  try {
    await Promise.all([
      posStore.fetchIngredients(),
      posStore.fetchIngredientCategories()
    ])
  } catch (err) {
    console.error('Error loading current stock:', err)
  } finally {
    isLoading.value = false
  }
}



onMounted(() => {
  window.addEventListener('click', closeMenu)
  loadData()
})

onBeforeUnmount(() => {
  window.removeEventListener('click', closeMenu)
})

const categoryOptions = computed(() => [
  { value: 'all', label: 'Semua Kategori' },
  ...posStore.ingredientCategories.map(c => ({ value: c.id, label: c.name }))
])

const statusOptions = [
  { value: 'all', label: 'Semua Status Stok' },
  { value: 'safe', label: 'Stok Aman' },
  { value: 'low', label: 'Stok Menipis' },
  { value: 'empty', label: 'Stok Habis' },
]

// KPI Metrics
const totalItemsCount = computed(() => posStore.ingredients.length)
const lowStockCount = computed(() => posStore.ingredients.filter(i => (i.displayStock ?? i.currentStock) > 0 && i.isLowStock).length)
const emptyStockCount = computed(() => posStore.ingredients.filter(i => (i.displayStock ?? i.currentStock) <= 0).length)
const safeStockCount = computed(() => posStore.ingredients.filter(i => (i.displayStock ?? i.currentStock) > 0 && !i.isLowStock).length)

const columns = [
  { key: 'name', label: 'Nama Bahan Baku', width: '27%' },
  { key: 'categoryName', label: 'Kategori', width: '18%' },
  { key: 'stock', label: 'Stok Fisik Tersedia', align: 'right' as const, width: '18%' },
  { key: 'threshold', label: 'Batas Minimum', align: 'right' as const, width: '15%' },
  { key: 'status', label: 'Status Stok', align: 'center' as const, width: '14%' },
  { key: 'actions', label: 'Aksi', align: 'center' as const, width: '8%' },
]

const filteredIngredients = computed(() => {
  let list = posStore.ingredients || []

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(i => i.name.toLowerCase().includes(q))
  }

  if (selectedCategoryId.value && selectedCategoryId.value !== 'all') {
    list = list.filter(i => i.categoryId === selectedCategoryId.value)
  }

  if (statusFilter.value === 'safe') {
    list = list.filter(i => (i.displayStock ?? i.currentStock) > 0 && !i.isLowStock)
  } else if (statusFilter.value === 'low') {
    list = list.filter(i => (i.displayStock ?? i.currentStock) > 0 && i.isLowStock)
  } else if (statusFilter.value === 'empty') {
    list = list.filter(i => (i.displayStock ?? i.currentStock) <= 0)
  }

  return list
})

const openAdjustModal = (item: Ingredient) => {
  closeMenu()
  targetIngredient.value = item
  adjustType.value = 'restock'
  adjustQty.value = ''
  adjustNotes.value = ''
  modalError.value = ''
  isAdjustModalOpen.value = true
}

const goToHistory = (item: Ingredient) => {
  closeMenu()
  router.push('/dashboard/stock/history')
}

const handleSaveAdjust = async () => {
  if (!targetIngredient.value || adjustQty.value === '' || isSubmitting.value) {
    modalError.value = 'Mohon masukkan jumlah kuantitas stok yang valid.'
    return
  }

  const num = Number(adjustQty.value)
  if (isNaN(num) || num < 0) {
    modalError.value = 'Jumlah kuantitas tidak boleh negatif.'
    return
  }

  modalError.value = ''
  isSubmitting.value = true

  try {
    await posStore.adjustIngredientStock(targetIngredient.value.id, {
      type: adjustType.value,
      quantity: num,
      notes: adjustNotes.value.trim() || undefined
    })

    showNotification('success', `Stok ${targetIngredient.value.name} berhasil diperbarui.`)
    isAdjustModalOpen.value = false
  } catch (err: any) {
    console.error('Adjustment error:', err)
    modalError.value = err?.response?.data?.message || err?.message || 'Gagal menyimpan penyesuaian stok.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header Page (Tanpa Sub Header Kecil) -->
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-[#202224] dark:text-white">Stok Bahan Saat Ini</h1>
    </div>

    <!-- KPI Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <AppStatCard title="Total Bahan Baku" :value="`${totalItemsCount} Jenis`" icon="inventory_2" variant="primary" :loading="isLoading" />
      <AppStatCard title="Stok Aman" :value="`${safeStockCount} Bahan`" icon="check_circle" variant="secondary" :loading="isLoading" />
      <AppStatCard title="Stok Menipis" :value="`${lowStockCount} Bahan`" icon="warning" variant="warning" :loading="isLoading" />
      <AppStatCard title="Stok Habis" :value="`${emptyStockCount} Bahan`" icon="cancel" variant="danger" :loading="isLoading" />
    </div>

    <!-- Reusable AppTable Component with Integrated Header -->
    <AppTable
      :columns="columns"
      :data="filteredIngredients"
      :loading="isLoading"
      :pageSize="20"
      :searchable="false"
      showNumbering
      numberingLabel="No"
      emptyMessage="Belum ada bahan baku yang sesuai kriteria pencarian"
    >
      <!-- Integrated Header (Segmented Pills & Reusable AppInput Search) -->
      <template #header>
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 w-full">
          <!-- Left: Segmented Pill Filter Container -->
          <div class="flex items-center gap-1.5 p-1 bg-[#F1F4F9] dark:bg-[#1E293B] rounded-xl">
            <!-- 1. Category Filter Dropdown -->
            <AppFilterDropdown v-model="selectedCategoryId" :options="categoryOptions" width="w-48" />

            <!-- 2. Status Filter Dropdown -->
            <AppFilterDropdown v-model="statusFilter" :options="statusOptions" width="w-44" />
          </div>

          <!-- Right: Reusable AppInput Search -->
          <div class="w-full sm:w-[250px] shrink-0">
            <AppInput
              v-model="searchQuery"
              placeholder="Cari bahan baku..."
              suffixIcon="search"
              clearable
              inputClass="!h-[38px] !text-xs sm:!text-sm"
            />
          </div>
        </div>
      </template>

      <!-- Cell: Nama Bahan -->
      <template #cell-name="{ row }">
        <span class="font-bold text-sm text-[#202224] dark:text-white leading-snug">{{ row.name }}</span>
      </template>

      <!-- Cell: Kategori (Teks Bersih Tanpa Badge) -->
      <template #cell-categoryName="{ row }">
        <span v-if="row.category?.name" class="text-sm font-semibold text-[#475569] dark:text-[#CBD5E1]">
          {{ row.category.name }}
        </span>
        <span v-else class="text-xs text-slate-400 italic">-</span>
      </template>

      <!-- Cell: Stok Fisik Tersedia -->
      <template #cell-stock="{ row }">
        <div class="flex items-center justify-end gap-1.5">
          <!-- Merah jika stok habis -->
          <span
            v-if="(row.displayStock ?? row.currentStock) <= 0"
            title="Stok Habis"
            class="inline-flex shrink-0 cursor-default"
          >
            <AlertTriangle class="w-4 h-4 text-red-500">
              <title>Stok Habis</title>
            </AlertTriangle>
          </span>

          <!-- Kuning jika stok menipis -->
          <span
            v-else-if="row.isLowStock || (row.lowStockThreshold && (row.displayStock ?? row.currentStock) <= row.lowStockThreshold)"
            title="Stok Menipis"
            class="inline-flex shrink-0 cursor-default"
          >
            <AlertTriangle class="w-4 h-4 text-amber-500">
              <title>Stok Menipis</title>
            </AlertTriangle>
          </span>

          <span class="font-bold text-sm text-[#202224] dark:text-white tabular-nums">
            {{ formatNumber(row.displayStock ?? row.currentStock) }}
          </span>
          <span class="text-xs font-semibold text-[#64748B] dark:text-[#94A3B8] ml-1.5">{{ row.unit }}</span>
        </div>
      </template>

      <!-- Cell: Batas Minimum -->
      <template #cell-threshold="{ row }">
        <div class="text-right">
          <template v-if="row.lowStockThreshold">
            <span class="font-bold text-sm text-[#202224] dark:text-white tabular-nums">
              {{ formatNumber(row.displayLowStockThreshold ?? row.lowStockThreshold) }}
            </span>
            <span class="text-xs font-semibold text-[#64748B] dark:text-[#94A3B8] ml-1.5">{{ row.unit }}</span>
          </template>
          <span v-else class="text-xs text-slate-300 dark:text-slate-600 italic">
            -
          </span>
        </div>
      </template>

      <!-- Cell: Status Stok -->
      <template #cell-status="{ row }">
        <div class="flex justify-center">
          <AppBadge v-if="(row.displayStock ?? row.currentStock) <= 0" variant="danger" size="md" rounded="full">
            Habis
          </AppBadge>
          <AppBadge v-else-if="row.isLowStock" variant="warning" size="md" rounded="full">
            Menipis
          </AppBadge>
          <AppBadge v-else variant="success" size="md" rounded="full">
            Aman
          </AppBadge>
        </div>
      </template>

      <!-- Cell: Aksi (Menu Titik Tiga simetris kanan ala Shadcn UI) -->
      <template #cell-actions="{ row }">
        <div class="flex items-center justify-center">
          <div class="relative inline-flex" @click.stop>
            <button
              type="button"
              @click="toggleMenu(row.id)"
              class="w-7 h-7 rounded-lg flex items-center justify-center text-[#94A3B8] hover:text-[#1E293B] dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer"
              title="Opsi Bahan"
            >
              <MoreVertical class="w-4 h-4" />
            </button>

            <!-- Popover Dropdown Menu dengan Smooth Vue Transition ala Shadcn UI -->
            <Transition
              enter-active-class="transition duration-200 ease-out"
              enter-from-class="transform scale-95 opacity-0 -translate-y-1"
              enter-to-class="transform scale-100 opacity-100 translate-y-0"
              leave-active-class="transition duration-150 ease-in"
              leave-from-class="transform scale-100 opacity-100 translate-y-0"
              leave-to-class="transform scale-95 opacity-0 -translate-y-1"
            >
              <div
                v-if="activeMenuId === row.id"
                class="absolute right-0 top-full mt-1.5 w-48 bg-white dark:bg-[#1E293B] rounded-xl shadow-xl border border-[#E2E8F0] dark:border-[#334155] p-1.5 space-y-1 z-50 text-sm font-semibold origin-top-right will-change-transform"
              >
                <!-- 1. Restock / Sesuaikan Stok -->
                <button
                  type="button"
                  @click="openAdjustModal(row)"
                  class="group w-full px-3 py-2.5 rounded-lg text-left text-[#202224] dark:text-[#E2E8F0] hover:text-[#4880FF] dark:hover:text-[#4880FF] hover:bg-[#4880FF]/10 flex items-center gap-2.5 transition-all duration-150 ease-in-out cursor-pointer active:scale-[0.98]"
                >
                  <PlusCircle class="w-4 h-4 text-[#64748B] dark:text-[#94A3B8] group-hover:text-[#4880FF] transition-colors duration-150" />
                  <span>Sesuaikan / Restock</span>
                </button>

                <!-- 2. Riwayat Mutasi -->
                <button
                  type="button"
                  @click="goToHistory(row)"
                  class="group w-full px-3 py-2.5 rounded-lg text-left text-[#202224] dark:text-[#E2E8F0] hover:text-[#4880FF] dark:hover:text-[#4880FF] hover:bg-[#4880FF]/10 flex items-center gap-2.5 transition-all duration-150 ease-in-out cursor-pointer active:scale-[0.98]"
                >
                  <History class="w-4 h-4 text-[#64748B] dark:text-[#94A3B8] group-hover:text-[#4880FF] transition-colors duration-150" />
                  <span>Riwayat Mutasi</span>
                </button>
              </div>
            </Transition>
          </div>
        </div>
      </template>
    </AppTable>

    <!-- Modal Restock / Penyesuaian -->
    <AppModal
      :show="isAdjustModalOpen"
      :title="`Sesuaikan Stok: ${targetIngredient?.name || ''}`"
      @close="isAdjustModalOpen = false"
    >
      <div class="space-y-4">
        <div class="p-3.5 bg-[#F8FAFC] dark:bg-[#1E293B] rounded-xl border border-[#E2E8F0] dark:border-[#334155] flex items-center justify-between text-xs">
          <span class="text-[#64748B] dark:text-[#94A3B8] font-medium">Stok Terkini:</span>
          <span class="font-bold text-[#202224] dark:text-white text-sm font-mono">
            {{ formatNumber(targetIngredient?.displayStock ?? targetIngredient?.currentStock ?? 0) }} {{ targetIngredient?.unit }}
          </span>
        </div>

        <div v-if="modalError" class="p-3 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-700 dark:text-red-400 rounded-xl text-xs font-medium">
          {{ modalError }}
        </div>

        <!-- Adjustment Mode Switch -->
        <div>
          <label class="block text-xs font-bold text-[#202224] dark:text-white mb-1.5">Jenis Penyesuaian</label>
          <div class="grid grid-cols-2 gap-2">
            <button
              type="button"
              @click="adjustType = 'restock'"
              class="py-2.5 px-3 text-xs font-bold rounded-xl border transition-all text-center cursor-pointer"
              :class="adjustType === 'restock' ? 'bg-[#4880FF] text-white border-[#4880FF] shadow-xs' : 'bg-white dark:bg-[#1E293B] text-[#64748B] dark:text-[#CBD5E1] border-[#E2E8F0] dark:border-[#334155] hover:bg-[#F8FAFC]'"
            >
              + Tambah Stok (Restock)
            </button>
            <button
              type="button"
              @click="adjustType = 'set'"
              class="py-2.5 px-3 text-xs font-bold rounded-xl border transition-all text-center cursor-pointer"
              :class="adjustType === 'set' ? 'bg-[#4880FF] text-white border-[#4880FF] shadow-xs' : 'bg-white dark:bg-[#1E293B] text-[#64748B] dark:text-[#CBD5E1] border-[#E2E8F0] dark:border-[#334155] hover:bg-[#F8FAFC]'"
            >
              = Setel Ulang Stok (Koreksi)
            </button>
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold text-[#202224] dark:text-white mb-1">
            {{ adjustType === 'restock' ? `Jumlah Tambahan (${targetIngredient?.unit})` : `Stok Akhir Sebenarnya (${targetIngredient?.unit})` }}
            <span class="text-red-500">*</span>
          </label>
          <AppInput v-model="adjustQty" type="number" step="any" min="0" placeholder="Masukkan angka..." />
        </div>

        <div>
          <label class="block text-xs font-bold text-[#202224] dark:text-white mb-1">Catatan / Alasan</label>
          <AppInput v-model="adjustNotes" placeholder="Pembelian dari supplier, koreksi timbangan..." />
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
          <AppButton variant="secondary" size="md" @click="isAdjustModalOpen = false">Batal</AppButton>
          <AppButton variant="primary" size="md" :loading="isSubmitting" @click="handleSaveAdjust">
            Simpan Stok
          </AppButton>
        </div>
      </div>
    </AppModal>
  </div>
</template>
