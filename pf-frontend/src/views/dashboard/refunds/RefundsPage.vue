<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { MoreVertical, CheckCircle, XCircle, Search, AlertCircle, RefreshCw } from 'lucide-vue-next'
import { useFormat } from '@/composables/useFormat'
import { useDashboardI18n } from '@/i18n'
import { useAuthStore } from '@/stores/auth'
import apiClient from '@/services/api'
import AppTable from '@/components/ui/AppTable.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppFilterDropdown from '@/components/ui/AppFilterDropdown.vue'
import AppModal from '@/components/ui/AppModal.vue'

interface RefundRecord {
  id: string
  orderId: string
  orderNumber: string
  customerName: string
  tableNumber: string
  amount: number
  reason: string
  requestedBy: string
  reviewedBy?: string
  status: 'pending' | 'approved' | 'rejected'
  createdAt: string
  rawItem?: any
}

const authStore = useAuthStore()
const { formatCurrency } = useFormat()
const { t, translate, locale } = useDashboardI18n()
const refunds = ref<RefundRecord[]>([])
const isLoading = ref(true)
const isSubmitting = ref(false)

// Table Search & Filter
const searchQuery = ref('')
const selectedStatusFilter = ref('all')

const statusOptions = computed(() => [
  { value: 'all', label: locale.value === 'en' ? 'All Statuses' : 'Semua Status' },
  { value: 'pending', label: t('refunds.filterPending', 'Menunggu Persetujuan') },
  { value: 'approved', label: t('refunds.filterApproved', 'Disetujui') },
  { value: 'rejected', label: t('refunds.filterRejected', 'Ditolak') },
])

const columns = computed(() => [
  { key: 'orderNumber', label: t('refunds.colOrder', 'ID Order'), width: '20%' },
  { key: 'amount', label: t('refunds.colAmount', 'Nominal'), align: 'right' as const, width: '16%' },
  { key: 'reason', label: t('refunds.colReason', 'Alasan Komplain'), width: '26%' },
  { key: 'requestedBy', label: t('refunds.colRequestedBy', 'Diajukan Oleh'), width: '16%' },
  { key: 'status', label: t('common.status', 'Status'), align: 'center' as const, width: '12%' },
  { key: 'actions', label: t('common.action', 'Aksi'), align: 'center' as const, width: '10%' },
])

const activeMenuRefundId = ref<string | null>(null)
const toggleMenu = (id: string) => {
  activeMenuRefundId.value = activeMenuRefundId.value === id ? null : id
}
const closeMenu = () => {
  activeMenuRefundId.value = null
}

// Rejection Modal State
const isRejectModalOpen = ref(false)
const selectedRefundToReject = ref<RefundRecord | null>(null)
const rejectionReason = ref('')
const rejectionError = ref('')

const openRejectModal = (record: RefundRecord) => {
  closeMenu()
  selectedRefundToReject.value = record
  rejectionReason.value = ''
  rejectionError.value = ''
  isRejectModalOpen.value = true
}

const closeRejectModal = () => {
  isRejectModalOpen.value = false
  selectedRefundToReject.value = null
}

// Fetch refunds from real API
const fetchRefunds = async () => {
  isLoading.value = true
  try {
    await authStore.ensureToken()
    const res = await apiClient.get('/refunds')
    const data = res.data?.refund_requests?.data || res.data?.refund_requests || []
    refunds.value = data.map((item: any) => ({
      id: item.id,
      orderId: item.order_id,
      orderNumber: item.order?.order_number || item.order_id || '-',
      customerName: item.order?.customer_name || 'Pelanggan',
      tableNumber: item.order?.table?.table_number || '-',
      amount: item.amount,
      reason: item.reason,
      requestedBy: item.requested_by?.name || 'Staff Kasir',
      reviewedBy: item.reviewed_by?.name,
      status: item.status,
      createdAt: item.created_at,
      rawItem: item,
    }))
  } catch (err) {
    console.error('Failed to load refunds from backend:', err)
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  window.addEventListener('click', closeMenu)
  fetchRefunds()
})

onBeforeUnmount(() => {
  window.removeEventListener('click', closeMenu)
})

// Approve Refund API Call
const approveRefund = async (record: RefundRecord) => {
  closeMenu()
  isSubmitting.value = true
  try {
    await authStore.ensureToken()
    await apiClient.post(`/refunds/${record.id}/approve`)
    record.status = 'approved'
  } catch (err: any) {
    console.error('Failed to approve refund:', err)
    alert(err?.response?.data?.message || 'Gagal menyetujui refund.')
  } finally {
    isSubmitting.value = false
  }
}

// Submit Reject Refund API Call
const submitRejectRefund = async () => {
  if (!selectedRefundToReject.value) return
  if (!rejectionReason.value.trim()) {
    rejectionError.value = 'Mohon masukkan alasan penolakan.'
    return
  }

  isSubmitting.value = true
  rejectionError.value = ''
  try {
    await authStore.ensureToken()
    await apiClient.post(`/refunds/${selectedRefundToReject.value.id}/reject`, {
      rejection_reason: rejectionReason.value.trim(),
    })
    selectedRefundToReject.value.status = 'rejected'
    selectedRefundToReject.value.reason += ` [Alasan Penolakan: ${rejectionReason.value.trim()}]`
    closeRejectModal()
  } catch (err: any) {
    console.error('Failed to reject refund:', err)
    rejectionError.value = err?.response?.data?.message || 'Gagal menolak refund.'
  } finally {
    isSubmitting.value = false
  }
}

// Filtered refunds table data
const filteredRefunds = computed(() => {
  let list = [...refunds.value]

  // Filter status
  if (selectedStatusFilter.value !== 'all') {
    list = list.filter(r => r.status === selectedStatusFilter.value)
  }

  // Filter search
  const q = searchQuery.value.toLowerCase().trim()
  if (q) {
    list = list.filter(r =>
      r.orderNumber.toLowerCase().includes(q) ||
      r.reason.toLowerCase().includes(q) ||
      r.requestedBy.toLowerCase().includes(q) ||
      r.customerName.toLowerCase().includes(q)
    )
  }

  return list
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-[#202224] dark:text-white tracking-tight">{{ t('refunds.pageTitle', 'Persetujuan Refund') }} Pesanan</h1>
      </div>
    </div>

    <AppTable title="Daftar Permintaan Refund" :columns="columns" :data="filteredRefunds" :loading="isLoading"
      showNumbering numberingLabel="No" emptyMessage="Belum ada permintaan refund saat ini.">
      <template #actions>
        <div class="flex flex-wrap sm:flex-nowrap items-center gap-2.5">
          <!-- Status Filter Dropdown -->
          <div class="flex items-center p-1 bg-[#F1F4F9] dark:bg-[#1E293B] rounded-xl">
            <AppFilterDropdown v-model="selectedStatusFilter" :options="statusOptions" width="w-52" />
          </div>

          <!-- Search Input -->
          <div class="w-full sm:w-[220px] shrink-0">
            <AppInput v-model="searchQuery" placeholder="Cari ID order/alasan..." suffixIcon="search" clearable
              inputClass="!h-[38px] !text-xs sm:!text-sm" />
          </div>
        </div>
      </template>

      <template #cell-orderNumber="{ row, value }">
        <div>
          <span class="font-bold text-sm text-[#4880FF] hover:underline cursor-pointer block">{{ value }}</span>
          <span class="text-xs text-[#64748B] dark:text-[#94A3B8]">{{ row.customerName }} • {{ row.tableNumber }}</span>
        </div>
      </template>

      <template #cell-amount="{ value }">
        <span class="font-bold text-sm text-[#202224] dark:text-white">{{ formatCurrency(value) }}</span>
      </template>

      <template #cell-reason="{ value }">
        <span class="text-sm max-w-xs block truncate text-[#64748B] dark:text-[#94A3B8]" :title="value">{{ value
        }}</span>
      </template>

      <template #cell-requestedBy="{ value }">
        <span class="text-sm font-semibold text-[#202224] dark:text-white">{{ value }}</span>
      </template>

      <template #cell-status="{ value }">
        <div class="flex justify-center">
          <AppBadge :variant="value === 'approved' ? 'success' : value === 'pending' ? 'warning' : 'danger'" size="md"
            rounded="full">
            {{ value === 'approved' ? 'Disetujui' : value === 'pending' ? 'Menunggu' : 'Ditolak' }}
          </AppBadge>
        </div>
      </template>

      <!-- Cell: Aksi (Menu Titik Tiga simetris kanan ala Shadcn UI) -->
      <template #cell-actions="{ row }">
        <div class="flex items-center justify-center">
          <template v-if="row.status === 'pending'">
            <div class="relative inline-flex" @click.stop>
              <button type="button" @click="toggleMenu(row.id)"
                class="w-7 h-7 rounded-lg flex items-center justify-center text-[#94A3B8] hover:text-[#1E293B] dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#334155] transition-colors cursor-pointer"
                title="Opsi Refund">
                <MoreVertical class="w-4 h-4" />
              </button>

              <!-- Popover Dropdown Menu dengan Smooth Vue Transition ala Shadcn UI -->
              <Transition enter-active-class="transition duration-200 ease-out"
                enter-from-class="transform scale-95 opacity-0 -translate-y-1"
                enter-to-class="transform scale-100 opacity-100 translate-y-0"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="transform scale-100 opacity-100 translate-y-0"
                leave-to-class="transform scale-95 opacity-0 -translate-y-1">
                <div v-if="activeMenuRefundId === row.id"
                  class="absolute right-0 top-full mt-1.5 w-48 bg-white dark:bg-[#1E293B] rounded-xl shadow-xl border border-[#E2E8F0] dark:border-[#334155] p-1.5 space-y-1 z-50 text-sm font-semibold origin-top-right will-change-transform">
                  <!-- 1. Setujui Refund (Success on hover) -->
                  <button type="button" @click="approveRefund(row)"
                    class="group w-full px-3 py-2.5 rounded-lg text-left text-[#202224] dark:text-[#E2E8F0] hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 flex items-center gap-2.5 transition-all duration-150 ease-in-out cursor-pointer active:scale-[0.98]">
                    <CheckCircle
                      class="w-4 h-4 text-[#64748B] dark:text-[#94A3B8] group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors duration-150" />
                    <span>Setujui Refund</span>
                  </button>

                  <!-- 2. Tolak Refund (Danger on hover) -->
                  <button type="button" @click="openRejectModal(row)"
                    class="group w-full px-3 py-2.5 rounded-lg text-left text-[#202224] dark:text-[#E2E8F0] hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 flex items-center gap-2.5 transition-all duration-150 ease-in-out cursor-pointer active:scale-[0.98]">
                    <XCircle
                      class="w-4 h-4 text-[#64748B] dark:text-[#94A3B8] group-hover:text-red-600 dark:group-hover:text-red-400 transition-colors duration-150" />
                    <span>Tolak Refund</span>
                  </button>
                </div>
              </Transition>
            </div>
          </template>
          <span v-else class="text-xs font-semibold text-[#94A3B8] italic px-2">Selesai</span>
        </div>
      </template>
    </AppTable>

    <!-- Modal Konfirmasi Penolakan Refund -->
    <AppModal :show="isRejectModalOpen" title="Tolak Pengajuan Refund" @close="closeRejectModal">
      <div class="space-y-4">
        <div
          class="p-3 bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 rounded-xl flex items-start gap-3 text-amber-700 dark:text-amber-300 text-sm">
          <AlertCircle class="w-4 h-4 shrink-0 mt-0.5" />
          <span>Pengajuan refund untuk pesanan <strong>{{ selectedRefundToReject?.orderNumber }}</strong> senilai
            <strong>{{
              formatCurrency(selectedRefundToReject?.amount || 0) }}</strong> akan ditolak. Berikan alasan penolakan di
            bawah.</span>
        </div>

        <div>
          <label class="block text-xs font-bold text-[#202224] dark:text-white mb-1.5">Alasan Penolakan</label>
          <textarea v-model="rejectionReason" rows="3"
            class="w-full px-3 py-2 text-sm bg-white dark:bg-[#1E293B] border border-[#E2E8F0] dark:border-[#334155] rounded-xl text-[#202224] dark:text-white focus:outline-none resize-none"
            placeholder="Contoh: Makanan sudah dikonsumsi lebih dari separuh / di luar batas waktu komplain..."></textarea>
          <p v-if="rejectionError" class="text-xs text-red-500 mt-1 font-semibold">{{ rejectionError }}</p>
        </div>

        <div class="flex items-center justify-end gap-2.5 pt-2">
          <AppButton type="button" variant="secondary" size="md" @click="closeRejectModal" :disabled="isSubmitting">
            Batal
          </AppButton>
          <AppButton type="button" variant="danger" size="md" @click="submitRejectRefund" :loading="isSubmitting">
            Tolak Refund
          </AppButton>
        </div>
      </div>
    </AppModal>
  </div>
</template>
