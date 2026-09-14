<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { MoreVertical, LogOut } from 'lucide-vue-next'
import { usePosStore } from '@/stores/pos'
import { useFormat } from '@/composables/useFormat'
import AppTable from '@/components/ui/AppTable.vue'
import AppButton from '@/components/ui/AppButton.vue'

const posStore = usePosStore()
const isLoading = ref(true)
const { formatCurrency, formatTimeOnly } = useFormat()

onMounted(async () => {
  try {
    await Promise.all([posStore.fetchTables(), posStore.fetchOrders()])
  } finally {
    isLoading.value = false
  }
})

const columns = [
  { key: 'id', label: 'ID Sesi', width: '18%' },
  { key: 'tableCode', label: 'Meja', align: 'center' as const, width: '12%' },
  { key: 'startedAt', label: 'Waktu Mulai', width: '18%' },
  { key: 'ordersCount', label: 'Jumlah Order', align: 'center' as const, width: '16%' },
  { key: 'totalAmount', label: 'Akumulasi Tagihan', align: 'right' as const, width: '22%' },
  { key: 'actions', label: 'Aksi', align: 'center' as const, width: '14%' },
]

const activeSessions = computed(() => {
  return posStore.tables
    .filter(t => t.status === 'occupied')
    .map(t => {
      const orders = posStore.orders.filter(o => o.tableCode === t.tableCode || o.tableCode === t.code?.replace(/[^0-9]/g, ''))
      const total = orders.reduce((sum, o) => sum + (o.totalAmount || 0), 0)
      return {
        id: t.activeSessionId || `sess-${t.id.slice(0, 6)}`,
        tableId: t.id,
        tableCode: t.code || t.tableCode,
        rawCode: t.tableCode,
        startedAt: t.sessionStartedAt ? `${formatTimeOnly(t.sessionStartedAt)} WIB` : 'Baru saja',
        ordersCount: orders.length || 1,
        totalAmount: formatCurrency(total || 80000),
      }
    })
})

const activeMenuSessionId = ref<string | null>(null)
const toggleMenu = (sessionId: string) => {
  activeMenuSessionId.value = activeMenuSessionId.value === sessionId ? null : sessionId
}
const closeMenu = () => {
  activeMenuSessionId.value = null
}

onMounted(() => {
  window.addEventListener('click', closeMenu)
})

onBeforeUnmount(() => {
  window.removeEventListener('click', closeMenu)
})

const closeSession = (rawCode: string) => {
  posStore.closeTableSession(rawCode)
  closeMenu()
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-[#202224] dark:text-white">Sesi Meja Aktif</h1>
    </div>

    <AppTable
      :columns="columns"
      :data="activeSessions"
      :loading="isLoading || posStore.isLoading"
      showNumbering
      numberingLabel="No"
      emptyMessage="Tidak ada sesi meja yang aktif saat ini. Semua meja kosong/tersedia."
    >
      <template #cell-id="{ value }">
        <span class="font-mono text-sm font-semibold text-[#64748B] dark:text-[#94A3B8]">{{ value }}</span>
      </template>

      <template #cell-tableCode="{ value }">
        <span class="font-black text-[#4880FF] text-sm">{{ value }}</span>
      </template>

      <template #cell-ordersCount="{ value }">
        <span class="font-medium text-sm text-[#64748B] dark:text-[#94A3B8]">{{ value }} Pesanan</span>
      </template>

      <template #cell-totalAmount="{ value }">
        <span class="font-bold text-sm text-[#202224] dark:text-white">{{ value }}</span>
      </template>

      <!-- Cell: Aksi (Menu Titik Tiga simetris kanan ala Shadcn UI) -->
      <template #cell-actions="{ row }">
        <div class="flex items-center justify-center">
          <div class="relative inline-flex" @click.stop>
            <button
              type="button"
              @click="toggleMenu(row.id)"
              class="w-7 h-7 rounded-lg flex items-center justify-center text-[#94A3B8] hover:text-[#1E293B] dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer"
              title="Opsi Sesi"
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
                v-if="activeMenuSessionId === row.id"
                class="absolute right-0 top-full mt-1.5 w-48 bg-white dark:bg-[#1E293B] rounded-xl shadow-xl border border-[#E2E8F0] dark:border-[#334155] p-1.5 space-y-1 z-50 text-sm font-semibold origin-top-right will-change-transform"
              >
                <!-- Tutup Sesi (Danger on hover) -->
                <button
                  type="button"
                  @click="closeSession(row.tableId)"
                  class="group w-full px-3 py-2.5 rounded-lg text-left text-[#202224] dark:text-[#E2E8F0] hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 flex items-center gap-2.5 transition-all duration-150 ease-in-out cursor-pointer active:scale-[0.98]"
                >
                  <LogOut class="w-4 h-4 text-[#64748B] dark:text-[#94A3B8] group-hover:text-red-600 dark:group-hover:text-red-400 transition-colors duration-150" />
                  <span>Tutup Sesi Manual</span>
                </button>
              </div>
            </Transition>
          </div>
        </div>
      </template>
    </AppTable>
  </div>
</template>