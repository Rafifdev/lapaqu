<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import { Motion, AnimatePresence } from 'motion-v'
import AppButton from '@/components/ui/AppButton.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppTextarea from '@/components/ui/AppTextarea.vue'
import AppMenuCard from '@/components/ui/AppMenuCard.vue'
import emptyMenuIllustration from '@/assets/empty_state/empty-menu.svg'
import { useAuthStore } from '@/stores/auth'

import { useCartStore } from '@/stores/cart'
import { usePosStore } from '@/stores/pos'
import { useFormat } from '@/composables/useFormat'
import type { MenuItem } from '@/types'

const cartStore = useCartStore()
const authStore = useAuthStore()
const posStore = usePosStore()
const route = useRoute()
const { formatCurrency } = useFormat()

onMounted(async () => {
  await Promise.all([
    posStore.fetchCategories(),
    posStore.fetchMenuItems(),
    posStore.fetchTables(),
  ])

  if (route.query.table) {
    selectedTable.value = getTableDisplayNumber(String(route.query.table))
    orderType.value = 'dine_in'
    if (route.query.openModal === '1') {
      isTableModalOpen.value = true
    }
  }
})

// Outlet info for receipt printing
const outletInfo = {
  name: 'Lapaqu POS',
  address: 'Kopi Senopati Group',
  phone: '0812-9876-5432',
  taxId: '',
}

// State
const searchQuery = ref('')
const selectedCategory = ref('all')
const selectedTable = computed({
  get: () => cartStore.tableCode || '-',
  set: (val: string) => { cartStore.tableCode = val },
})
const customerName = computed({
  get: () => cartStore.customerName || '',
  set: (val: string) => { cartStore.customerName = val },
})
const getTableDisplayNumber = (code: string) => {
  const num = parseInt(code.replace(/\D/g, '') || '1')
  return `T-${String(num).padStart(2, '0')}`
}

const handleSelectTable = (tableCode: string, status: string = 'available') => {
  if (status !== 'available') return
  const tableDisplay = getTableDisplayNumber(tableCode)
  if (selectedTable.value === tableDisplay) {
    selectedTable.value = '-'
  } else {
    selectedTable.value = tableDisplay
  }
}

const allTables = computed(() => {
  return posStore.tables.map((t) => {
    const rawCode = t.code || t.tableCode || t.tableNumber || ''
    const tableDisplay = getTableDisplayNumber(rawCode)
    const isSelected = selectedTable.value === tableDisplay || selectedTable.value === rawCode

    let status: 'available' | 'reserved' | 'filled' = 'available'
    if (t.status === 'occupied') {
      status = 'filled'
    } else if (t.status === 'reserved') {
      status = 'reserved'
    }

    return {
      id: t.id,
      code: tableDisplay,
      rawCode: t.tableCode || rawCode,
      status,
      isLarge: (t.capacity || 4) > 4,
      capacity: t.capacity || 4,
      isSelected,
    }
  })
})

const indoorTables = computed(() => allTables.value.slice(0, 6))
const outdoorTables = computed(() => allTables.value.slice(6))
const orderType = computed({
  get: () => cartStore.orderType || 'dine_in',
  set: (val: 'dine_in' | 'takeaway') => { cartStore.orderType = val },
})

// Right order panel collapse state
const isOrderPanelExpanded = ref(true)

// Note editing modal state
const isNoteModalOpen = ref(false)
const editingItemId = ref<string | null>(null)
const editingItemNote = ref('')

// Table selection modal state
const isTableModalOpen = ref(false)

// Cashier Place Order Payment Modal State
const isPaymentModalOpen = ref(false)
const isSuccessModalOpen = ref(false)

const paymentMethod = ref<'cash' | 'qris' | 'debit'>('cash')
const cashReceived = ref<number>(0)
const lastOrderData = ref<{
  orderNumber: string
  customerName?: string
  orderType: string
  table: string
  items: any[]
  subtotal: number
  tax: number
  total: number
  paymentMethod: string
  cashReceived: number
  change: number
  timestamp: string
} | null>(null)

const filteredItems = computed(() => {
  return posStore.menuItems.filter(item => {
    const matchesCategory = selectedCategory.value === 'all' || item.categoryId === selectedCategory.value
    const matchesSearch = searchQuery.value === '' ||
      item.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (item.description && item.description.toLowerCase().includes(searchQuery.value.toLowerCase()))
    return matchesCategory && matchesSearch
  })
})

const subtotal = computed(() => cartStore.totalPrice)
const tax = computed(() => Math.round(subtotal.value * 0.1))
const grandTotal = computed(() => subtotal.value + tax.value)

const changeAmount = computed(() => {
  return (cashReceived.value || 0) - grandTotal.value
})

const isCashEnough = computed(() => {
  if (paymentMethod.value !== 'cash') return true
  return (cashReceived.value || 0) >= grandTotal.value
})

const setPresetCash = (amount: number) => {
  cashReceived.value = amount
}

// State & Logic Warning Meja (Motion Dev Shake & Red Highlight)
const isTableWarning = ref(false)
const tableShakeCount = ref(0)
let tableWarningTimeout: ReturnType<typeof setTimeout> | null = null

const triggerTableShake = () => {
  tableShakeCount.value++
  isTableWarning.value = false
  nextTick(() => {
    isTableWarning.value = true
  })
  if (tableWarningTimeout) {
    clearTimeout(tableWarningTimeout)
  }
  tableWarningTimeout = setTimeout(() => {
    isTableWarning.value = false
  }, 350)
}

const handleTableClick = () => {
  if (orderType.value === 'dine_in') {
    isTableWarning.value = false
    isTableModalOpen.value = true
  }
}

watch([selectedTable, orderType], () => {
  if (selectedTable.value !== '-' || orderType.value !== 'dine_in') {
    isTableWarning.value = false
  }
})

const proceedToPayment = () => {
  if (cartStore.items.length === 0) return

  // Validasi: jika dine in tetapi belum memilih meja, berikan efek shake getar & warna merah
  if (orderType.value === 'dine_in' && (selectedTable.value === '-' || !selectedTable.value)) {
    triggerTableShake()
    return
  }

  paymentMethod.value = 'cash'
  cashReceived.value = grandTotal.value
  isPaymentModalOpen.value = true
}

const handleConfirmPayment = async () => {
  if (!isCashEnough.value && paymentMethod.value === 'cash') return

  const targetTable = (orderType.value === 'dine_in' && selectedTable.value !== '-')
    ? posStore.tables.find(t => {
        const cleanSelected = selectedTable.value.replace(/[^0-9]/g, '')
        const cleanTableNum = (t.tableNumber || '').replace(/[^0-9]/g, '')
        const cleanCode = (t.tableCode || t.code || '').replace(/[^0-9]/g, '')
        return (
          t.id === selectedTable.value ||
          t.tableCode === selectedTable.value ||
          t.code === selectedTable.value ||
          t.tableNumber === selectedTable.value ||
          getTableDisplayNumber(t.tableCode) === selectedTable.value ||
          (Boolean(cleanSelected) && (cleanTableNum === cleanSelected || cleanCode === cleanSelected))
        )
      })
    : undefined

  try {
    const finalCustomerName = customerName.value.trim() || 'Pelanggan Manual'
    const createdOrder = await posStore.createManualOrder({
      orderType: orderType.value,
      tableId: targetTable?.id,
      tableCode: targetTable?.tableCode || (selectedTable.value !== '-' ? selectedTable.value : undefined),
      customerName: finalCustomerName,
      paymentMethod: (paymentMethod.value === 'debit' ? 'card' : paymentMethod.value) as any,
      cashReceived: paymentMethod.value === 'cash' ? (cashReceived.value || grandTotal.value) : grandTotal.value,
      items: [...cartStore.items],
    })

    const orderNum = createdOrder?.order_number || createdOrder?.orderNumber || `ORD-${Date.now().toString().slice(-6)}`

    lastOrderData.value = {
      orderNumber: orderNum,
      customerName: finalCustomerName,
      orderType: orderType.value === 'dine_in' ? 'Dine In' : 'Take Away',
      table: orderType.value === 'dine_in' ? (targetTable?.code || targetTable?.tableCode || (selectedTable.value !== '-' ? selectedTable.value : '-')) : '-',
      items: [...cartStore.items],
      subtotal: subtotal.value,
      tax: tax.value,
      total: grandTotal.value,
      paymentMethod: paymentMethod.value.toUpperCase(),
      cashReceived: paymentMethod.value === 'cash' ? (cashReceived.value || grandTotal.value) : grandTotal.value,
      change: paymentMethod.value === 'cash' ? Math.max(0, changeAmount.value) : 0,
      timestamp: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }),
    }

    isPaymentModalOpen.value = false
    cartStore.clearCart()
    customerName.value = ''
    selectedTable.value = '-'
    isSuccessModalOpen.value = true
  } catch (err: any) {
    alert(err?.response?.data?.message || err?.message || 'Gagal memproses pesanan ke server backend.')
  }
}

const handlePrintReceipt = () => {
  if (!lastOrderData.value) return

  const o = lastOrderData.value
  const fmt = (val: number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val)

  const dateStr = new Date().toLocaleDateString('id-ID', {
    weekday: 'short', day: '2-digit', month: 'short', year: 'numeric',
  })

  // Build item rows HTML
  const itemRows = o.items.map(item => `
    <tr>
      <td class="item-name">
        ${item.menuItem.name}
        ${item.notes ? `<br><span class="item-note">* ${item.notes}</span>` : ''}
      </td>
      <td class="center">${item.quantity}</td>
      <td class="right">${fmt(item.unitPrice)}</td>
      <td class="right">${fmt(item.subtotal)}</td>
    </tr>
  `).join('')

  const cashRows = o.paymentMethod === 'CASH' ? `
    <tr><td>Uang Diterima</td><td class="right">${fmt(o.cashReceived)}</td></tr>
    <tr class="bold"><td>Kembalian</td><td class="right">${fmt(o.change)}</td></tr>
  ` : ''

  const html = `<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Struk Pembayaran - ${o.orderNumber}</title>
  <style>
    @page { size: 80mm auto; margin: 0; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Courier New', Courier, monospace;
      font-size: 8pt;
      line-height: 1.45;
      color: #000;
      background: #fff;
      width: 80mm;
      padding: 4mm 3mm;
    }
    .center { text-align: center; }
    .right { text-align: right; }
    .bold { font-weight: 700; }
    .header { text-align: center; margin-bottom: 2mm; }
    .header .outlet-name { font-size: 11pt; font-weight: 700; margin-bottom: 1mm; }
    .header .sub { font-size: 7pt; }
    .divider-dashed { border: none; border-top: 1px dashed #000; margin: 2mm 0; }
    .divider-solid { border: none; border-top: 1px solid #000; margin: 1.5mm 0; }
    .info-table { width: 100%; font-size: 7.5pt; }
    .info-table td:last-child { text-align: right; }
    .items-table { width: 100%; font-size: 7.5pt; border-collapse: collapse; }
    .items-table th { font-size: 7pt; text-transform: uppercase; font-weight: 700; padding-bottom: 1mm; }
    .items-table th.right, .items-table td.right { text-align: right; }
    .items-table th.center, .items-table td.center { text-align: center; }
    .items-table td.item-name { padding-right: 1mm; }
    .item-note { font-size: 6.5pt; color: #444; font-style: italic; }
    .totals-table { width: 100%; font-size: 8pt; }
    .totals-table td:last-child { text-align: right; }
    .totals-table tr td { line-height: 1.6; }
    .grand-total td { font-size: 10pt; font-weight: 700; }
    .footer { text-align: center; font-size: 7pt; margin-top: 3mm; }
    .footer p { margin-bottom: 0.5mm; }
    .footer .powered { margin-top: 2mm; font-size: 6.5pt; opacity: 0.5; }
  </style>
</head>
<body>
  <!-- HEADER -->
  <div class="header">
    <div class="outlet-name">${outletInfo.name}</div>
    <div class="sub">${outletInfo.address}</div>
    <div class="sub">Telp: ${outletInfo.phone}</div>
    ${outletInfo.taxId ? `<div class="sub">NPWP: ${outletInfo.taxId}</div>` : ''}
  </div>

  <div class="divider-dashed"></div>

  <!-- ORDER INFO -->
  <table class="info-table">
    <tr><td>No. Pesanan</td><td class="bold">${o.orderNumber}</td></tr>
    <tr><td>Kasir</td><td>${authStore.currentUser?.name ?? '-'}</td></tr>
    <tr><td>Tanggal</td><td>${dateStr}</td></tr>
    <tr><td>Jam</td><td>${o.timestamp}</td></tr>
    <tr><td>Tipe</td><td class="bold">${o.orderType === 'dine_in' ? 'Dine In' : 'Takeaway'}</td></tr>
    ${o.orderType === 'dine_in' ? `<tr><td>Meja</td><td class="bold">${o.table}</td></tr>` : ''}
  </table>

  <div class="divider-dashed"></div>

  <!-- ITEMS -->
  <table class="items-table">
    <thead>
      <tr>
        <th style="text-align:left">Item</th>
        <th class="center">Qty</th>
        <th class="right">Harga</th>
        <th class="right">Subtotal</th>
      </tr>
    </thead>
    <tbody>
      <tr><td colspan="4"><div class="divider-solid" style="margin:0"></div></td></tr>
      ${itemRows}
    </tbody>
  </table>

  <div class="divider-dashed"></div>

  <!-- TOTALS -->
  <table class="totals-table">
    <tr><td>Subtotal</td><td>${fmt(o.subtotal)}</td></tr>
    <tr><td>Pajak (PPN 11%)</td><td>${fmt(o.tax)}</td></tr>
    <tr><td colspan="2"><div class="divider-solid"></div></td></tr>
    <tr class="grand-total"><td>TOTAL</td><td>${fmt(o.total)}</td></tr>
    <tr><td colspan="2"><div class="divider-dashed"></div></td></tr>
    <tr><td>Metode Bayar</td><td class="bold">${o.paymentMethod}</td></tr>
    ${cashRows}
  </table>

  <div class="divider-dashed"></div>

  <!-- FOOTER -->
  <div class="footer">
    <p>Terima kasih telah berkunjung!</p>
    <p>Simpan struk ini sebagai bukti pembayaran</p>
    <p class="powered">Powered by Lapaqu</p>
  </div>
</body>
</html>`

  // Gunakan invisible iframe agar dialog print muncul langsung tanpa popup window duplikat di background
  let printFrame = document.getElementById('receipt-print-frame') as HTMLIFrameElement
  if (!printFrame) {
    printFrame = document.createElement('iframe')
    printFrame.id = 'receipt-print-frame'
    printFrame.style.position = 'fixed'
    printFrame.style.right = '0'
    printFrame.style.bottom = '0'
    printFrame.style.width = '0'
    printFrame.style.height = '0'
    printFrame.style.border = '0'
    printFrame.style.visibility = 'hidden'
    document.body.appendChild(printFrame)
  }

  const frameDoc = printFrame.contentDocument || printFrame.contentWindow?.document
  if (frameDoc) {
    frameDoc.open()
    frameDoc.write(html)
    frameDoc.close()

    setTimeout(() => {
      printFrame.contentWindow?.focus()
      printFrame.contentWindow?.print()
    }, 250)
  }
}

const handleNewOrder = () => {
  isSuccessModalOpen.value = false
  lastOrderData.value = null
  selectedTable.value = '-'
  customerName.value = ''
}

const getItemQuantity = (itemId: string): number => {
  return cartStore.items
    .filter(i => i.menuItem.id === itemId)
    .reduce((total, i) => total + i.quantity, 0)
}

const handleIncrement = (menuItem: MenuItem) => {
  if (menuItem.maxServings !== null && menuItem.maxServings !== undefined) {
    const currentQty = getItemQuantity(menuItem.id)
    if (currentQty >= menuItem.maxServings) {
      alert(`Stok bahan untuk '${menuItem.name}' hanya tersisa ${menuItem.maxServings} porsi.`)
      return
    }
  }
  cartStore.addItem(menuItem, 1)
}

const handleDecrement = (menuItem: MenuItem) => {
  const cartItem = cartStore.items.find(i => i.menuItem.id === menuItem.id)
  if (cartItem) {
    cartStore.updateQuantity(cartItem.id, -1)
  }
}

const openEditNote = (cartItemId: string) => {
  const item = cartStore.items.find(i => i.id === cartItemId)
  if (item) {
    editingItemId.value = cartItemId
    editingItemNote.value = item.notes || ''
    isNoteModalOpen.value = true
  }
}

const saveNote = () => {
  if (editingItemId.value) {
    const item = cartStore.items.find(i => i.id === editingItemId.value)
    if (item) {
      item.notes = editingItemNote.value
    }
    isNoteModalOpen.value = false
    editingItemId.value = null
    editingItemNote.value = ''
  }
}

const handleImageError = (e: Event) => {
  const img = e.target as HTMLImageElement
  img.src = 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=100&auto=format&fit=crop&q=80'
}
</script>

<template>
  <div class="h-full flex-1 flex flex-col min-h-0 font-sans relative">
    <!-- Header: Order Manual -->
    <div class="flex items-center justify-between gap-4 mb-4 shrink-0">
      <h1 class="text-2xl font-bold text-[#202224] dark:text-white">Order Manual</h1>
    </div>

    <!-- Main Two-Column Layout -->
    <div class="flex-1 flex flex-col lg:flex-row gap-6 overflow-hidden min-h-0 relative">
      <!-- LEFT SECTION: Menu Catalog & Categories -->
      <div class="flex-1 flex flex-col min-w-0 bg-transparent overflow-hidden h-full">
        <!-- Search & Category Filter Wrapper Div Card -->
        <div class="bg-white dark:bg-[#273142] p-3 rounded-2xl shadow-xs shrink-0 mb-4">


          <!-- Category Filter Pills Row (Stroke Only with Skeleton) -->
          <div v-if="posStore.isLoading" class="flex items-center gap-3 overflow-x-auto no-scrollbar p-1 animate-pulse">
            <div v-for="n in 6" :key="n" class="h-10 w-24 bg-slate-200 dark:bg-slate-700/60 rounded-xl shrink-0" />
          </div>
          <div v-else class="flex items-center gap-3 overflow-x-auto no-scrollbar p-1">
            <!-- All Menu Pill -->
            <button type="button" @click="selectedCategory = 'all'" :class="[
              'px-5 py-2.5 rounded-xl text-sm font-bold whitespace-nowrap transition-colors cursor-pointer border',
              selectedCategory === 'all'
                ? 'border-[#4880FF] text-[#4880FF] bg-white dark:bg-[#273142] ring-1 ring-[#4880FF]'
                : 'border-[#EAEAEA] dark:border-[#313D4F] bg-white dark:bg-[#273142] text-[#4A5568] dark:text-[#94A3B8] hover:text-[#4880FF] hover:border-[#4880FF]'
            ]">
              All Menu
            </button>

            <!-- Dynamic Category Pills -->
            <button v-for="cat in posStore.categories" :key="cat.id" type="button" @click="selectedCategory = cat.id"
              :class="[
                'px-5 py-2.5 rounded-xl text-sm font-bold whitespace-nowrap transition-colors cursor-pointer border',
                selectedCategory === cat.id
                  ? 'border-[#4880FF] text-[#4880FF] bg-white dark:bg-[#273142] ring-1 ring-[#4880FF]'
                  : 'border-[#EAEAEA] dark:border-[#313D4F] bg-white dark:bg-[#273142] text-[#4A5568] dark:text-[#94A3B8] hover:text-[#4880FF] hover:border-[#4880FF]'
              ]">
              {{ cat.name }}
            </button>
          </div>
        </div>

        <!-- Food Menu Cards Grid (Scrollable with padding to prevent edge clipping on zoom) -->
        <div class="flex-1 overflow-y-auto p-2 sm:p-3 -m-2 sm:-m-3 min-h-0 [scrollbar-gutter:stable]">
          <!-- Skeleton Loading Grid -->
          <div v-if="posStore.isLoading" :class="[
            'grid gap-4 p-1',
            isOrderPanelExpanded
              ? 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-4'
              : 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6'
          ]">
            <AppMenuCard v-for="n in 8" :key="n" loading />
          </div>

          <!-- Real Menu Cards Grid -->
          <div v-else-if="filteredItems.length > 0" :class="[
            'grid gap-4 p-1 select-none',
            isOrderPanelExpanded
              ? 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-4'
              : 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6'
          ]">
            <AppMenuCard v-for="item in filteredItems" :key="item.id" :item="item" :quantity="getItemQuantity(item.id)"
              @increment="handleIncrement" @decrement="handleDecrement" />
          </div>

          <!-- Empty State if search/category has no items (Layout disesuaikan untuk desktop & tablet) -->
          <div v-else
            class="min-h-[300px] sm:min-h-[340px] md:min-h-[380px] flex flex-col items-center justify-center text-center px-4 py-6">
            <div class="relative flex items-center justify-center -mb-2 sm:-mb-3 md:-mb-4 pointer-events-none">
              <img :src="emptyMenuIllustration" alt="Menu Tidak Ditemukan"
                class="w-48 h-48 sm:w-56 sm:h-56 md:w-60 md:h-60 lg:w-64 lg:h-64 object-contain drop-shadow-xs" />
            </div>
            <h3 class="text-xl sm:text-2xl md:text-[26px] font-black text-[#1E293B] dark:text-white tracking-tight">
              Whoops! :(
            </h3>
            <p
              class="text-xs sm:text-base font-medium text-[#64748B] dark:text-[#94A3B8] mt-1.5 max-w-[280px] sm:max-w-xs md:max-w-sm leading-relaxed">
              Menu yang anda cari tidak ditemukan. Silahkan pilih kategori lain
            </p>
          </div>
        </div>
      </div>

      <!-- RIGHT SECTION: Current Order Panel (Framer Motion Spring Expand & Collapse) -->
      <Motion
        :initial="false"
        :animate="isOrderPanelExpanded ? {
          opacity: 1,
          x: 0,
          scale: 1,
        } : {
          opacity: 0,
          x: 28,
          scale: 0.98,
        }"
        :transition="{
          type: 'spring',
          damping: 24,
          stiffness: 240,
          mass: 0.8
        }"
        :class="[
          'bg-white dark:bg-[#273142] rounded-2xl shadow-sm flex flex-col shrink-0 overflow-hidden h-full transition-[width,padding,margin] duration-350 ease-[cubic-bezier(0.34,1.2,0.64,1)] [transform:translateZ(0)]',
          isOrderPanelExpanded
            ? 'w-full lg:w-[360px] 2xl:w-[380px] p-5'
            : 'w-0 p-0 m-0 pointer-events-none'
        ]"
      >
        <div class="w-full h-full flex flex-col min-w-[320px] 2xl:min-w-[340px]">
          <!-- Header Order Info with Collapse Button -->
          <div class="flex items-center justify-between pb-3 border-b border-[#F1F4F9] dark:border-[#313D4F] shrink-0">

            <!-- Left: Customer Name & Table Number (Interactive on Dine In, Disabled & Dark on Takeaway) -->
            <div class="flex items-center gap-3">
              <Motion
                :key="`table-indicator-${tableShakeCount}`"
                :initial="false"
                :animate="tableShakeCount > 0 ? {
                  x: [0, -5, 5, -4, 4, -2.5, 2.5, -1, 1, 0]
                } : { x: 0 }"
                :transition="{
                  duration: 0.32,
                  ease: 'easeInOut'
                }"
                :class="[
                  'text-left',
                  orderType === 'dine_in' ? 'cursor-pointer group' : 'cursor-default pointer-events-none'
                ]"
                @click="handleTableClick"
                :title="orderType === 'dine_in' ? 'Klik untuk ubah nama pelanggan / nomor meja' : undefined">
                <span
                  :class="[
                    'text-xs font-bold block truncate max-w-[200px] transition-colors',
                    isTableWarning
                      ? '!text-[#EF4444]'
                      : (orderType === 'dine_in' ? 'text-[#4880FF]' : 'text-[#202224] dark:text-white')
                  ]">
                  {{ customerName.trim() ? customerName.trim() : 'No. Meja' }}
                </span>
                <h2
                  :class="[
                    'text-2xl font-bold tracking-tight mt-0.5 transition-colors',
                    isTableWarning
                      ? '!text-[#EF4444]'
                      : (orderType === 'dine_in' ? 'text-[#4880FF] group-hover:underline' : 'text-[#202224] dark:text-white')
                  ]">
                  {{ orderType === 'dine_in' ? selectedTable : '-' }}
                </h2>
              </Motion>
            </div>

            <!-- Collapse Panel Button with Micro Interaction -->
            <button type="button" @click="isOrderPanelExpanded = false"
              class="w-10 h-10 rounded-xl flex items-center justify-center text-[#64748B] hover:text-[#4880FF] dark:text-[#94A3B8] dark:hover:text-white hover:bg-[#F1F5F9] dark:hover:bg-[#313D4F] transition-all active:scale-90 cursor-pointer"
              title="Sembunyikan Panel Pesanan">
              <AppIcon name="chevron_right" :size="30" />
            </button>
          </div>

          <!-- Dine In / Take Away Segmented Switch -->
          <div class="relative bg-[#F1F4F9] dark:bg-[#1B2431] p-1 rounded-full flex mt-4 shrink-0">
            <!-- Sliding Dynamic Indicator -->
            <div
              class="absolute top-1 bottom-1 w-[calc(50%-4px)] rounded-full pointer-events-none transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)] z-0"
              :class="orderType === 'dine_in'
                ? 'bg-[#4880FF] shadow-sm'
                : 'bg-white dark:bg-[#273142] shadow-[0_2px_8px_rgba(0,0,0,0.06)] dark:shadow-none'" :style="{
                  left: orderType === 'dine_in' ? '4px' : 'calc(50%)'
                }" />

            <!-- Dine In Button -->
            <button type="button" @click="orderType = 'dine_in'"
              class="relative z-10 flex-1 py-2.5 text-sm font-bold rounded-full transition-colors duration-200 text-center cursor-pointer"
              :class="orderType === 'dine_in' ? 'text-white font-extrabold' : 'text-[#64748B] dark:text-[#94A3B8] hover:text-[#4880FF]'">
              Dine In
            </button>

            <!-- Take Away Button -->
            <button type="button" @click="orderType = 'takeaway'"
              class="relative z-10 flex-1 py-2.5 text-sm font-bold rounded-full transition-colors duration-200 text-center cursor-pointer"
              :class="orderType === 'takeaway' ? 'text-[#202224] dark:text-white font-extrabold' : 'text-[#64748B] dark:text-[#94A3B8] hover:text-[#202224] dark:hover:text-white'">
              Take Away
            </button>
          </div>



          <!-- Cart Item List (Smooth Scrollable Center Area) -->
          <div class="flex-1 overflow-y-auto py-4 space-y-3.5 pr-1 min-h-0">
            <!-- Empty State -->
            <div v-if="cartStore.items.length === 0"
              class="h-full flex flex-col items-center justify-center text-center p-6 text-[#94A3B8]">
              <div class="w-14 h-14 rounded-2xl bg-[#F1F4F9] dark:bg-[#1B2431] flex items-center justify-center mb-3">
                <AppIcon name="shopping_bag" :size="28" class="text-[#94A3B8]" />
              </div>
              <h4 class="text-sm font-bold text-[#202224] dark:text-white">Belum Ada Item Terpilih</h4>
              <p class="text-xs text-[#64748B] dark:text-[#94A3B8] max-w-[200px] mt-1">
                Klik kartu menu makanan atau minuman untuk menambahkan ke pesanan.
              </p>
            </div>

            <!-- Cart Row Items -->
            <div v-for="item in cartStore.items" :key="item.id"
              class="flex items-start justify-between gap-3 pb-3 border-b border-[#F1F4F9] dark:border-[#313D4F]/60">
              <!-- Left: Thumbnail with Minus Icon & Red Tint for Decrementing -->
              <button type="button" @click="handleDecrement(item.menuItem)"
                class="relative w-14 h-14 rounded-xl overflow-hidden shrink-0 group cursor-pointer border border-rose-200/80 dark:border-rose-900/50 active:scale-95 transition-transform"
                title="Klik gambar untuk mengurangi pesanan">
                <!-- Food Image -->
                <img
                  :src="item.menuItem.imageUrl || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=100&auto=format&fit=crop&q=80'"
                  :alt="item.menuItem.name" @error="handleImageError" class="w-full h-full object-cover bg-[#D8D8D8]" />
                <!-- Slight Red Overlay & Center Minus Icon -->
                <div
                  class="absolute inset-0 bg-rose-500/25 group-hover:bg-rose-500/40 flex items-center justify-center transition-colors">
                  <div class="w-6 h-6 rounded-full bg-rose-500 text-white flex items-center justify-center shadow-xs">
                    <AppIcon name="remove" :size="16" />
                  </div>
                </div>
              </button>

              <!-- Middle: Name, Quantity & Note -->
              <div class="flex-1 min-w-0">
                <h4 class="text-base font-bold text-[#202224] dark:text-white truncate">
                  {{ item.menuItem.name }}
                </h4>
                <span class="text-sm font-bold text-[#4880FF] mt-0.5 block">
                  x{{ item.quantity }}
                </span>
                <p class="text-sm text-[#64748B] dark:text-[#94A3B8] truncate mt-0.5">
                  Note : {{ item.notes || '-' }}
                </p>
              </div>

              <!-- Right: Edit Note & Price -->
              <div class="flex flex-col items-end justify-between h-14 shrink-0">
                <!-- Edit Note Icon -->
                <button type="button" @click="openEditNote(item.id)"
                  class="text-[#94A3B8] hover:text-[#4880FF] transition-colors cursor-pointer p-0.5"
                  title="Edit Catatan Pesanan">
                  <AppIcon name="edit_note" :size="18" />
                </button>

                <!-- Subtotal Price -->
                <span class="text-base font-bold tabular-nums">
                  {{ formatCurrency(item.subtotal) }}
                </span>
              </div>
            </div>
          </div>

          <!-- Payment Summary Section (Pinned at Bottom) -->
          <div class="pt-3 border-t border-[#F1F4F9] dark:border-[#313D4F] shrink-0">
            <h3 class="text-sm font-bold text-[#202224] dark:text-white mb-2.5">
              Payment Summary
            </h3>

            <div class="space-y-2 text-sm">
              <!-- Sub Total -->
              <div class="flex justify-between items-center text-[#64748B] dark:text-[#94A3B8]">
                <span>Sub Total</span>
                <span class="font-bold tabular-nums">{{ formatCurrency(subtotal) }}</span>
              </div>

              <!-- Tax -->
              <div class="flex justify-between items-center text-[#64748B] dark:text-[#94A3B8]">
                <span>Tax (10%)</span>
                <span class="font-bold tabular-nums">{{ formatCurrency(tax) }}</span>
              </div>

              <!-- Dashed Line Divider -->
              <div class="border-b border-dashed border-[#E2E8F0] dark:border-[#313D4F] my-2" />

              <!-- Total Payment -->
              <div class="flex justify-between items-center pt-0.5">
                <span class="text-xs font-semibold text-[#64748B] dark:text-[#94A3B8]">Total Payment</span>
                <span class="text-base font-bold tabular-nums">{{ formatCurrency(grandTotal) }}</span>
              </div>
            </div>

            <!-- Place Order (AppButton Reusable) -->
            <AppButton variant="primary" size="md" block :disabled="cartStore.items.length === 0"
              @click="proceedToPayment" class="mt-4 !rounded-lg !py-3 !text-base !font-bold">
              Place Order
            </AppButton>
          </div>
        </div>
      </Motion>
    </div>

    <!-- FLOATING EXPAND BUTTON: Framer Motion Spring Pop Animation -->
    <AnimatePresence>
      <Motion
        v-if="!isOrderPanelExpanded"
        :initial="{ opacity: 0, y: 36, scale: 0.85 }"
        :animate="{ opacity: 1, y: 0, scale: 1 }"
        :exit="{ opacity: 0, y: 36, scale: 0.85 }"
        :transition="{ type: 'spring', damping: 18, stiffness: 240 }"
        class="fixed bottom-6 right-6 z-40"
      >
        <button
          type="button"
          @click="isOrderPanelExpanded = true"
          class="flex items-center gap-4 px-6 py-4 bg-[#4880FF] hover:bg-[#3971F0] text-white rounded-2xl shadow-xl hover:shadow-2xl active:scale-95 transition-all cursor-pointer border border-white/20"
        >
          <div class="relative flex items-center justify-center">
            <AppIcon name="shopping_bag" :size="28" />
            <span
              v-if="cartStore.items.length > 0"
              class="absolute -top-2 -right-2.5 w-5.5 h-5.5 rounded-full bg-rose-500 text-xs font-black text-white flex items-center justify-center border-2 border-[#4880FF]"
            >
              {{ cartStore.items.length }}
            </span>
          </div>
          <div class="text-left">
            <span class="text-xs font-bold opacity-90 block">Buka Pesanan</span>
            <span class="text-base font-bold tabular-nums">{{ formatCurrency(grandTotal) }}</span>
          </div>
          <div class="w-8 h-8 rounded-xl bg-white/20 flex items-center justify-center ml-1">
            <AppIcon name="chevron_left" :size="24" />
          </div>
        </button>
      </Motion>
    </AnimatePresence>

    <!-- 1. Edit Note Modal -->
    <AppModal :show="isNoteModalOpen" v-model="isNoteModalOpen" title="Tambah / Edit Catatan Menu" maxWidth="sm">
      <div class="space-y-3 text-xs">
        <p class="text-[#64748B] dark:text-[#94A3B8]">
          Masukkan catatan pesanan khusus (  <em>Spicy Lv.5</em>, <em>Less Ice</em>, dll.):
        </p>
        <AppTextarea
          v-model="editingItemNote"
          :rows="3"
          placeholder="Tulis catatan di sini..."
        />
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <AppButton variant="outline" size="sm" @click="isNoteModalOpen = false">
            Batal
          </AppButton>
          <AppButton variant="primary" size="sm" @click="saveNote">
            Simpan Catatan
          </AppButton>
        </div>
      </template>
    </AppModal>

    <!-- 2. Change Table Modal (With Indoor / Outdoor Sections & Exact Table Status from Manage Table) -->
    <AppModal :show="isTableModalOpen" v-model="isTableModalOpen" title="Pilih Nomor Meja & Pelanggan" maxWidth="2xl">
      <div class="space-y-4 pt-1">
        <!-- Input Nama Pelanggan di Modal (Reusable AppInput) -->
        <div class="px-2">
          <AppInput
            v-model="customerName"
            label="Nama Pelanggan"
            placeholder="Masukkan nama pelanggan (opsional)"
            icon="person"
            clearable
          />
        </div>

        <div class="border-t border-[#F1F4F9] dark:border-[#313D4F] pt-2">
          <div class="flex flex-wrap items-center justify-between gap-2 px-2 mb-3">
            <label class="block text-xs font-bold text-[#1E293B] dark:text-white">
              Pilih Meja
            </label>
            <!-- Legend -->
            <div class="flex items-center flex-wrap gap-2.5 sm:gap-3 text-[11px] font-bold text-[#64748B] dark:text-[#94A3B8]">
              <div class="flex items-center gap-1.5">
                <div class="w-3 h-3 rounded-full bg-[#E2E8F0] dark:bg-[#334155] border border-[#CBD5E1]/40" />
                <span>Available</span>
              </div>
              <div class="flex items-center gap-1.5">
                <div class="w-3 h-3 rounded-full bg-[#0F172A]" />
                <span class="text-[#0F172A] dark:text-white">Filled</span>
              </div>
              <div class="flex items-center gap-1.5">
                <div class="w-3 h-3 rounded-full bg-[#4880FF]" />
                <span class="text-[#4880FF]">Reserved</span>
              </div>
            </div>
          </div>

          <!-- Floor Plan Layout with Indoor and Outdoor Sections -->
          <div class="max-h-[420px] overflow-y-auto overflow-x-auto space-y-6 py-2 px-3 [scrollbar-gutter:stable]">
            <!-- Indoor Area -->
            <div>
              <div class="text-center mb-4">
                <span class="inline-block text-xs font-black uppercase tracking-widest text-[#475569] dark:text-[#94A3B8]">
                  Indoor
                </span>
              </div>
              <div class="grid grid-cols-6 gap-y-9 gap-x-3 sm:gap-x-4 items-center justify-items-center w-full min-w-[500px]">
                <div
                  v-for="t in indoorTables"
                  :key="t.id"
                  :class="t.isLarge ? 'col-span-2' : 'col-span-1'"
                  class="w-full flex justify-center"
                >
                  <!-- Big Table (4 Top & 4 Bottom Chairs) -->
                  <div
                    v-if="t.isLarge"
                    @click="handleSelectTable(t.rawCode, t.status)"
                    :class="[
                      'group flex flex-col items-center justify-center py-1 w-full max-w-[220px] sm:max-w-[240px] transition-all',
                      t.status !== 'available'
                        ? 'cursor-not-allowed pointer-events-none'
                        : 'cursor-pointer active:scale-95'
                    ]"
                  >
                    <!-- Top 4 Chairs -->
                    <div class="w-full flex items-center justify-between px-3 sm:px-4 mb-1">
                      <div v-for="c in 4" :key="'top-' + c" class="flex flex-col items-center gap-0.5">
                        <div :class="[
                          'w-4 sm:w-5 h-1.5 rounded-full transition-colors duration-200',
                          t.status === 'filled'
                            ? 'bg-[#0F172A]'
                            : t.status === 'reserved'
                            ? 'bg-[#1D4ED8]'
                            : t.isSelected
                            ? 'bg-[#1E40AF]'
                            : 'bg-[#94A3B8] dark:bg-[#64748B] group-hover:bg-[#4880FF]'
                        ]" />
                        <div :class="[
                          'w-4 sm:w-5 h-2 rounded-t-sm transition-colors duration-200',
                          t.status === 'filled'
                            ? 'bg-[#334155]'
                            : t.status === 'reserved'
                            ? 'bg-[#60A5FA]'
                            : t.isSelected
                            ? 'bg-[#3B82F6]'
                            : 'bg-[#CBD5E1] dark:bg-[#475569] group-hover:bg-[#93C5FD]'
                        ]" />
                      </div>
                    </div>

                    <!-- Center Big Table -->
                    <div :class="[
                      'w-full h-14 sm:h-16 rounded-2xl flex items-center justify-center font-black text-sm sm:text-base tracking-tight transition-all duration-200 shadow-2xs tabular-nums',
                      t.status === 'filled'
                        ? 'bg-[#0F172A] text-white shadow-xs'
                        : t.status === 'reserved'
                        ? 'bg-[#4880FF] text-white shadow-sm shadow-[#4880FF]/25'
                        : t.isSelected
                        ? 'bg-[#1D4ED8] text-white shadow-md'
                        : 'bg-[#E2E8F0] dark:bg-[#334155] text-[#1E293B] dark:text-white group-hover:bg-[#CBD5E1] dark:group-hover:bg-[#475569] border border-[#CBD5E1]/40 dark:border-transparent'
                    ]">
                      {{ t.code }}
                    </div>

                    <!-- Bottom 4 Chairs -->
                    <div class="w-full flex items-center justify-between px-3 sm:px-4 mt-1">
                      <div v-for="c in 4" :key="'bot-' + c" class="flex flex-col items-center gap-0.5">
                        <div :class="[
                          'w-4 sm:w-5 h-2 rounded-b-sm transition-colors duration-200',
                          t.status === 'filled'
                            ? 'bg-[#334155]'
                            : t.status === 'reserved'
                            ? 'bg-[#60A5FA]'
                            : t.isSelected
                            ? 'bg-[#3B82F6]'
                            : 'bg-[#CBD5E1] dark:bg-[#475569] group-hover:bg-[#93C5FD]'
                        ]" />
                        <div :class="[
                          'w-4 sm:w-5 h-1.5 rounded-full transition-colors duration-200',
                          t.status === 'filled'
                            ? 'bg-[#0F172A]'
                            : t.status === 'reserved'
                            ? 'bg-[#1D4ED8]'
                            : t.isSelected
                            ? 'bg-[#1E40AF]'
                            : 'bg-[#94A3B8] dark:bg-[#64748B] group-hover:bg-[#4880FF]'
                        ]" />
                      </div>
                    </div>
                  </div>

                  <!-- Normal Table (2 Top & 2 Bottom Chairs) -->
                  <div
                    v-else
                    @click="handleSelectTable(t.rawCode, t.status)"
                    :class="[
                      'group flex flex-col items-center justify-center py-1 w-full max-w-[100px] sm:max-w-[115px] transition-all',
                      t.status !== 'available'
                        ? 'cursor-not-allowed pointer-events-none'
                        : 'cursor-pointer active:scale-95'
                    ]"
                  >
                    <!-- Top 2 Chairs -->
                    <div class="flex items-center justify-center gap-2 sm:gap-2.5 mb-1 w-full">
                      <div v-for="c in 2" :key="'top-' + c" class="flex flex-col items-center gap-0.5">
                        <div :class="[
                          'w-4 sm:w-5 h-1.5 rounded-full transition-colors duration-200',
                          t.status === 'filled'
                            ? 'bg-[#0F172A]'
                            : t.status === 'reserved'
                            ? 'bg-[#1D4ED8]'
                            : t.isSelected
                            ? 'bg-[#1E40AF]'
                            : 'bg-[#94A3B8] dark:bg-[#64748B] group-hover:bg-[#4880FF]'
                        ]" />
                        <div :class="[
                          'w-4 sm:w-5 h-2 rounded-t-sm transition-colors duration-200',
                          t.status === 'filled'
                            ? 'bg-[#334155]'
                            : t.status === 'reserved'
                            ? 'bg-[#60A5FA]'
                            : t.isSelected
                            ? 'bg-[#3B82F6]'
                            : 'bg-[#CBD5E1] dark:bg-[#475569] group-hover:bg-[#93C5FD]'
                        ]" />
                      </div>
                    </div>

                    <!-- Center Normal Table -->
                    <div :class="[
                      'w-full h-14 sm:h-16 rounded-2xl flex items-center justify-center font-black text-sm sm:text-base tracking-tight transition-all duration-200 shadow-2xs tabular-nums',
                      t.status === 'filled'
                        ? 'bg-[#0F172A] text-white shadow-xs'
                        : t.status === 'reserved'
                        ? 'bg-[#4880FF] text-white shadow-sm shadow-[#4880FF]/25'
                        : t.isSelected
                        ? 'bg-[#1D4ED8] text-white shadow-md'
                        : 'bg-[#E2E8F0] dark:bg-[#334155] text-[#1E293B] dark:text-white group-hover:bg-[#CBD5E1] dark:group-hover:bg-[#475569] border border-[#CBD5E1]/40 dark:border-transparent'
                    ]">
                      {{ t.code }}
                    </div>

                    <!-- Bottom 2 Chairs -->
                    <div class="flex items-center justify-center gap-2 sm:gap-2.5 mt-1 w-full">
                      <div v-for="c in 2" :key="'bot-' + c" class="flex flex-col items-center gap-0.5">
                        <div :class="[
                          'w-4 sm:w-5 h-2 rounded-b-sm transition-colors duration-200',
                          t.status === 'filled'
                            ? 'bg-[#334155]'
                            : t.status === 'reserved'
                            ? 'bg-[#60A5FA]'
                            : t.isSelected
                            ? 'bg-[#3B82F6]'
                            : 'bg-[#CBD5E1] dark:bg-[#475569] group-hover:bg-[#93C5FD]'
                        ]" />
                        <div :class="[
                          'w-4 sm:w-5 h-1.5 rounded-full transition-colors duration-200',
                          t.status === 'filled'
                            ? 'bg-[#0F172A]'
                            : t.status === 'reserved'
                            ? 'bg-[#1D4ED8]'
                            : t.isSelected
                            ? 'bg-[#1E40AF]'
                            : 'bg-[#94A3B8] dark:bg-[#64748B] group-hover:bg-[#4880FF]'
                        ]" />
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Outdoor Area -->
            <div v-if="outdoorTables.length > 0">
              <div class="border-t border-[#F1F4F9] dark:border-[#334155] my-4" />
              <div class="text-center mb-4">
                <span class="inline-block text-xs font-black uppercase tracking-widest text-[#475569] dark:text-[#94A3B8]">
                  Outdoor
                </span>
              </div>
              <div class="grid grid-cols-6 gap-y-9 gap-x-3 sm:gap-x-4 items-center justify-items-center w-full min-w-[500px]">
                <div
                  v-for="t in outdoorTables"
                  :key="t.id"
                  :class="t.isLarge ? 'col-span-2' : 'col-span-1'"
                  class="w-full flex justify-center"
                >
                  <!-- Big Table (4 Top & 4 Bottom Chairs) -->
                  <div
                    v-if="t.isLarge"
                    @click="handleSelectTable(t.rawCode, t.status)"
                    :class="[
                      'group flex flex-col items-center justify-center py-1 w-full max-w-[220px] sm:max-w-[240px] transition-all',
                      t.status !== 'available'
                        ? 'cursor-not-allowed pointer-events-none'
                        : 'cursor-pointer active:scale-95'
                    ]"
                  >
                    <!-- Top 4 Chairs -->
                    <div class="w-full flex items-center justify-between px-3 sm:px-4 mb-1">
                      <div v-for="c in 4" :key="'top-' + c" class="flex flex-col items-center gap-0.5">
                        <div :class="[
                          'w-4 sm:w-5 h-1.5 rounded-full transition-colors duration-200',
                          t.status === 'filled'
                            ? 'bg-[#0F172A]'
                            : t.status === 'reserved'
                            ? 'bg-[#1D4ED8]'
                            : t.isSelected
                            ? 'bg-[#1E40AF]'
                            : 'bg-[#94A3B8] dark:bg-[#64748B] group-hover:bg-[#4880FF]'
                        ]" />
                        <div :class="[
                          'w-4 sm:w-5 h-2 rounded-t-sm transition-colors duration-200',
                          t.status === 'filled'
                            ? 'bg-[#334155]'
                            : t.status === 'reserved'
                            ? 'bg-[#60A5FA]'
                            : t.isSelected
                            ? 'bg-[#3B82F6]'
                            : 'bg-[#CBD5E1] dark:bg-[#475569] group-hover:bg-[#93C5FD]'
                        ]" />
                      </div>
                    </div>

                    <!-- Center Big Table -->
                    <div :class="[
                      'w-full h-14 sm:h-16 rounded-2xl flex items-center justify-center font-black text-sm sm:text-base tracking-tight transition-all duration-200 shadow-2xs tabular-nums',
                      t.status === 'filled'
                        ? 'bg-[#0F172A] text-white shadow-xs'
                        : t.status === 'reserved'
                        ? 'bg-[#4880FF] text-white shadow-sm shadow-[#4880FF]/25'
                        : t.isSelected
                        ? 'bg-[#1D4ED8] text-white shadow-md'
                        : 'bg-[#E2E8F0] dark:bg-[#334155] text-[#1E293B] dark:text-white group-hover:bg-[#CBD5E1] dark:group-hover:bg-[#475569] border border-[#CBD5E1]/40 dark:border-transparent'
                    ]">
                      {{ t.code }}
                    </div>

                    <!-- Bottom 4 Chairs -->
                    <div class="w-full flex items-center justify-between px-3 sm:px-4 mt-1">
                      <div v-for="c in 4" :key="'bot-' + c" class="flex flex-col items-center gap-0.5">
                        <div :class="[
                          'w-4 sm:w-5 h-2 rounded-b-sm transition-colors duration-200',
                          t.status === 'filled'
                            ? 'bg-[#334155]'
                            : t.status === 'reserved'
                            ? 'bg-[#60A5FA]'
                            : t.isSelected
                            ? 'bg-[#3B82F6]'
                            : 'bg-[#CBD5E1] dark:bg-[#475569] group-hover:bg-[#93C5FD]'
                        ]" />
                        <div :class="[
                          'w-4 sm:w-5 h-1.5 rounded-full transition-colors duration-200',
                          t.status === 'filled'
                            ? 'bg-[#0F172A]'
                            : t.status === 'reserved'
                            ? 'bg-[#1D4ED8]'
                            : t.isSelected
                            ? 'bg-[#1E40AF]'
                            : 'bg-[#94A3B8] dark:bg-[#64748B] group-hover:bg-[#4880FF]'
                        ]" />
                      </div>
                    </div>
                  </div>

                  <!-- Normal Table (2 Top & 2 Bottom Chairs) -->
                  <div
                    v-else
                    @click="handleSelectTable(t.rawCode, t.status)"
                    :class="[
                      'group flex flex-col items-center justify-center py-1 w-full max-w-[100px] sm:max-w-[115px] transition-all',
                      t.status !== 'available'
                        ? 'cursor-not-allowed pointer-events-none'
                        : 'cursor-pointer active:scale-95'
                    ]"
                  >
                    <!-- Top 2 Chairs -->
                    <div class="flex items-center justify-center gap-2 sm:gap-2.5 mb-1 w-full">
                      <div v-for="c in 2" :key="'top-' + c" class="flex flex-col items-center gap-0.5">
                        <div :class="[
                          'w-4 sm:w-5 h-1.5 rounded-full transition-colors duration-200',
                          t.status === 'filled'
                            ? 'bg-[#0F172A]'
                            : t.status === 'reserved'
                            ? 'bg-[#1D4ED8]'
                            : t.isSelected
                            ? 'bg-[#1E40AF]'
                            : 'bg-[#94A3B8] dark:bg-[#64748B] group-hover:bg-[#4880FF]'
                        ]" />
                        <div :class="[
                          'w-4 sm:w-5 h-2 rounded-t-sm transition-colors duration-200',
                          t.status === 'filled'
                            ? 'bg-[#334155]'
                            : t.status === 'reserved'
                            ? 'bg-[#60A5FA]'
                            : t.isSelected
                            ? 'bg-[#3B82F6]'
                            : 'bg-[#CBD5E1] dark:bg-[#475569] group-hover:bg-[#93C5FD]'
                        ]" />
                      </div>
                    </div>

                    <!-- Center Normal Table -->
                    <div :class="[
                      'w-full h-14 sm:h-16 rounded-2xl flex items-center justify-center font-black text-sm sm:text-base tracking-tight transition-all duration-200 shadow-2xs tabular-nums',
                      t.status === 'filled'
                        ? 'bg-[#0F172A] text-white shadow-xs'
                        : t.status === 'reserved'
                        ? 'bg-[#4880FF] text-white shadow-sm shadow-[#4880FF]/25'
                        : t.isSelected
                        ? 'bg-[#1D4ED8] text-white shadow-md'
                        : 'bg-[#E2E8F0] dark:bg-[#334155] text-[#1E293B] dark:text-white group-hover:bg-[#CBD5E1] dark:group-hover:bg-[#475569] border border-[#CBD5E1]/40 dark:border-transparent'
                    ]">
                      {{ t.code }}
                    </div>

                    <!-- Bottom 2 Chairs -->
                    <div class="flex items-center justify-center gap-2 sm:gap-2.5 mt-1 w-full">
                      <div v-for="c in 2" :key="'bot-' + c" class="flex flex-col items-center gap-0.5">
                        <div :class="[
                          'w-4 sm:w-5 h-2 rounded-b-sm transition-colors duration-200',
                          t.status === 'filled'
                            ? 'bg-[#334155]'
                            : t.status === 'reserved'
                            ? 'bg-[#60A5FA]'
                            : t.isSelected
                            ? 'bg-[#3B82F6]'
                            : 'bg-[#CBD5E1] dark:bg-[#475569] group-hover:bg-[#93C5FD]'
                        ]" />
                        <div :class="[
                          'w-4 sm:w-5 h-1.5 rounded-full transition-colors duration-200',
                          t.status === 'filled'
                            ? 'bg-[#0F172A]'
                            : t.status === 'reserved'
                            ? 'bg-[#1D4ED8]'
                            : t.isSelected
                            ? 'bg-[#1E40AF]'
                            : 'bg-[#94A3B8] dark:bg-[#64748B] group-hover:bg-[#4880FF]'
                        ]" />
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <AppButton variant="primary" size="md" @click="isTableModalOpen = false" class="!rounded-lg px-6 !font-bold">
            Selesai
          </AppButton>
        </div>
      </template>
    </AppModal>

    <!-- 3. Cashier Place Order Payment Modal (2 Columns: Left = Order Items, Right = Payment Form) -->
    <AppModal :show="isPaymentModalOpen" v-model="isPaymentModalOpen" title="Konfirmasi & Pembayaran Pesanan"
      maxWidth="3xl">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-8 text-xs min-h-[420px]">
        <!-- LEFT COLUMN: Order Details & Item List -->
        <div
          class="flex flex-col border-b md:border-b-0 md:border-r border-[#F1F4F9] dark:border-[#313D4F] md:pr-8 pb-4 md:pb-0 h-full">
          <!-- Order Type & Table Header -->
          <div
            class="flex items-center justify-between pb-3 border-b border-[#F1F4F9] dark:border-[#313D4F] mb-3 shrink-0">
            <div>
              <span class="text-[11px] text-[#64748B] dark:text-[#94A3B8] font-medium block">
                {{ customerName.trim() ? customerName.trim() : 'Pelanggan Manual' }}
              </span>
              <span class="font-bold text-sm text-[#1E293B] dark:text-white">
                {{ orderType === 'dine_in' ? (selectedTable !== '-' ? `Dine In (${selectedTable})` : 'Dine In') : 'Take Away' }}
              </span>
            </div>
            <AppBadge variant="primary" size="md">
              {{ cartStore.items.length }} Item
            </AppBadge>
          </div>

          <!-- Order Items List -->
          <div class="flex-1 overflow-y-auto max-h-[260px] space-y-3 pr-1">
            <div v-for="item in cartStore.items" :key="item.id"
              class="flex items-start justify-between gap-3 pb-2.5 border-b border-[#F1F4F9] dark:border-[#313D4F]/50">
              <!-- Thumbnail -->
              <img
                :src="item.menuItem.imageUrl || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=100&auto=format&fit=crop&q=80'"
                :alt="item.menuItem.name" @error="handleImageError"
                class="w-12 h-12 rounded-xl object-cover bg-[#D8D8D8] shrink-0" />

              <!-- Middle: Title & Note -->
              <div class="flex-1 min-w-0">
                <h4 class="text-xs sm:text-sm font-bold text-[#1E293B] dark:text-white truncate">
                  {{ item.menuItem.name }}
                </h4>
                <p class="text-sm text-[#64748B] dark:text-[#94A3B8] truncate mt-0.5">
                  Note : {{ item.notes || '-' }}
                </p>
              </div>

              <!-- Right: Quantity at Top Right (x1) & Price at Bottom Right -->
              <div class="flex flex-col items-end justify-between h-12 shrink-0">
                <span class="text-xs font-bold text-[#4880FF]">
                  x{{ item.quantity }}
                </span>
                <span class="text-xs sm:text-sm font-bold text-[#1E293B] dark:text-white tabular-nums">
                  {{ formatCurrency(item.subtotal) }}
                </span>
              </div>
            </div>
          </div>

          <!-- Order Summary Calculation (Subtotal, Tax, Total) -->
          <div class="pt-3 border-t border-[#F1F4F9] dark:border-[#313D4F] space-y-1.5 mt-auto shrink-0">
            <div class="flex justify-between text-[#64748B] dark:text-[#94A3B8]">
              <span>Sub Total</span>
              <span class="font-bold tabular-nums">{{ formatCurrency(subtotal) }}</span>
            </div>
            <div class="flex justify-between text-[#64748B] dark:text-[#94A3B8]">
              <span>Tax (10%)</span>
              <span class="font-bold tabular-nums">{{ formatCurrency(tax) }}</span>
            </div>
            <div class="border-b border-dashed border-[#E2E8F0] dark:border-[#313D4F] my-1" />
            <div class="flex justify-between items-center pt-0.5">
              <span class="font-bold text-[#1E293B] dark:text-white">Total Payment</span>
              <span class="text-base font-bold tabular-nums">{{ formatCurrency(grandTotal) }}</span>
            </div>
          </div>
        </div>

        <!-- RIGHT COLUMN: Payment Method & Cash Nominal Input -->
        <div class="flex flex-col h-full justify-between">
          <div class="space-y-4 flex-1 flex flex-col min-h-0">
            <!-- Payment Method Tabs with Sliding Spring Indicator -->
            <div>
              <label class="font-bold text-[#1E293B] dark:text-white block mb-2 text-sm">Metode Pembayaran</label>
              <div
                class="relative bg-[#F1F5F9] dark:bg-[#1B2431] p-1 rounded-2xl flex border border-[#E2E8F0] dark:border-[#313D4F]">
                <!-- Sliding Blue Pill Indicator (iOS Spring Curve) -->
                <div
                  class="absolute top-1 bottom-1 rounded-xl bg-[#4880FF] shadow-sm pointer-events-none transition-all duration-350 ease-[cubic-bezier(0.34,1.3,0.64,1)] z-0"
                  :style="{
                    width: 'calc(33.333% - 2.66px)',
                    left: paymentMethod === 'cash'
                      ? '4px'
                      : paymentMethod === 'qris'
                        ? 'calc(33.333% + 1.33px)'
                        : 'calc(66.666% - 1.33px)'
                  }" />

                <button type="button" @click="paymentMethod = 'cash'"
                  class="relative z-10 flex-1 py-2.5 px-2 rounded-xl text-xs font-bold transition-colors duration-200 flex flex-col items-center gap-1 cursor-pointer"
                  :class="paymentMethod === 'cash'
                    ? 'text-white font-extrabold'
                    : 'text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF] dark:hover:text-white'">
                  <AppIcon name="payments" :size="18" />
                  <span>Cash</span>
                </button>

                <button type="button" @click="paymentMethod = 'qris'"
                  class="relative z-10 flex-1 py-2.5 px-2 rounded-xl text-xs font-bold transition-colors duration-200 flex flex-col items-center gap-1 cursor-pointer"
                  :class="paymentMethod === 'qris'
                    ? 'text-white font-extrabold'
                    : 'text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF] dark:hover:text-white'">
                  <AppIcon name="qr_code" :size="18" />
                  <span>QRIS</span>
                </button>

                <button type="button" @click="paymentMethod = 'debit'"
                  class="relative z-10 flex-1 py-2.5 px-2 rounded-xl text-xs font-bold transition-colors duration-200 flex flex-col items-center gap-1 cursor-pointer"
                  :class="paymentMethod === 'debit'
                    ? 'text-white font-extrabold'
                    : 'text-[#475569] dark:text-[#CBD5E1] hover:text-[#4880FF] dark:hover:text-white'">
                  <AppIcon name="credit_card" :size="18" />
                  <span>Debit</span>
                </button>
              </div>
            </div>

            <!-- Cash Payment Section -->
            <div v-if="paymentMethod === 'cash'" class="space-y-3 pt-1">
              <div>
                <AppInput
                  v-model.number="cashReceived"
                  type="number"
                  label="Nominal Uang Diterima (Rp)"
                  prefix="Rp"
                  min="0"
                  step="1000"
                  placeholder="0"
                />
              </div>

              <!-- Quick Cash Presets with Header (Grid 4 columns, 8 items evenly spaced) -->
              <div>
                <label class="font-bold text-[#1E293B] dark:text-white block mb-1.5 text-sm">
                  Pecahan Uang
                </label>
                <div class="grid grid-cols-4 gap-2 w-full">
                  <button type="button" @click="setPresetCash(grandTotal)"
                    class="py-2.5 px-1 rounded-xl border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#1E293B] text-xs font-bold hover:bg-[#F1F5F9] dark:hover:bg-[#334155] text-[#1E293B] dark:text-white cursor-pointer transition-all active:scale-95 text-center flex items-center justify-center">
                    Uang Pas
                  </button>
                  <button type="button" @click="setPresetCash(20000)"
                    class="py-2.5 px-1 rounded-xl border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#1E293B] text-xs font-bold hover:bg-[#F1F5F9] dark:hover:bg-[#334155] text-[#1E293B] dark:text-white cursor-pointer transition-all active:scale-95 text-center flex items-center justify-center tabular-nums">
                    Rp 20.000
                  </button>
                  <button type="button" @click="setPresetCash(50000)"
                    class="py-2.5 px-1 rounded-xl border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#1E293B] text-xs font-bold hover:bg-[#F1F5F9] dark:hover:bg-[#334155] text-[#1E293B] dark:text-white cursor-pointer transition-all active:scale-95 text-center flex items-center justify-center tabular-nums">
                    Rp 50.000
                  </button>
                  <button type="button" @click="setPresetCash(100000)"
                    class="py-2.5 px-1 rounded-xl border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#1E293B] text-xs font-bold hover:bg-[#F1F5F9] dark:hover:bg-[#334155] text-[#1E293B] dark:text-white cursor-pointer transition-all active:scale-95 text-center flex items-center justify-center tabular-nums">
                    Rp 100.000
                  </button>
                  <button type="button" @click="setPresetCash(200000)"
                    class="py-2.5 px-1 rounded-xl border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#1E293B] text-xs font-bold hover:bg-[#F1F5F9] dark:hover:bg-[#334155] text-[#1E293B] dark:text-white cursor-pointer transition-all active:scale-95 text-center flex items-center justify-center tabular-nums">
                    Rp 200.000
                  </button>
                  <button type="button" @click="setPresetCash(500000)"
                    class="py-2.5 px-1 rounded-xl border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#1E293B] text-xs font-bold hover:bg-[#F1F5F9] dark:hover:bg-[#334155] text-[#1E293B] dark:text-white cursor-pointer transition-all active:scale-95 text-center flex items-center justify-center tabular-nums">
                    Rp 500.000
                  </button>
                  <button type="button" @click="setPresetCash(1000000)"
                    class="py-2.5 px-1 rounded-xl border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#1E293B] text-xs font-bold hover:bg-[#F1F5F9] dark:hover:bg-[#334155] text-[#1E293B] dark:text-white cursor-pointer transition-all active:scale-95 text-center flex items-center justify-center tabular-nums">
                    Rp 1.000.000
                  </button>
                  <button type="button" @click="setPresetCash(2000000)"
                    class="py-2.5 px-1 rounded-xl border border-[#CBD5E1] dark:border-[#334155] bg-white dark:bg-[#1E293B] text-xs font-bold hover:bg-[#F1F5F9] dark:hover:bg-[#334155] text-[#1E293B] dark:text-white cursor-pointer transition-all active:scale-95 text-center flex items-center justify-center tabular-nums">
                    Rp 2.000.000
                  </button>
                </div>
              </div>


            </div>

            <!-- QRIS Dynamic Live QR View (Clean QR Code Only) -->
            <div v-else-if="paymentMethod === 'qris'"
              class="flex-1 min-h-[220px] p-4 bg-[#F8FAFC] dark:bg-[#1E293B] rounded-2xl text-center flex flex-col items-center justify-center border border-[#E2E8F0] dark:border-[#334155]">
              <!-- QR Code Card -->
              <div class="bg-white p-3.5 rounded-2xl border border-[#E2E8F0] dark:border-[#313D4F] shadow-xs flex items-center justify-center">
                <div class="w-48 h-48 sm:w-52 sm:h-52 bg-white flex items-center justify-center rounded-xl overflow-hidden">
                  <img
                    :src="`https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=00020101021226580016ID.CO.XENDIT.WWW0118936009180000000000021500000000000000051440014ID.LINKAJA.WWW0215000000000000000520458125303360540${grandTotal}`"
                    alt="QRIS Code"
                    class="w-full h-full object-contain"
                  />
                </div>
              </div>
            </div>

            <!-- Debit / EDC Info (Full height filling empty vertical space) -->
            <div v-else-if="paymentMethod === 'debit'"
              class="flex-1 min-h-[220px] p-6 bg-[#F8FAFC] dark:bg-[#1E293B] rounded-2xl text-center flex flex-col items-center justify-center space-y-3 border border-[#E2E8F0] dark:border-[#334155]">
              <div class="w-16 h-16 rounded-2xl bg-[#4880FF]/10 text-[#4880FF] flex items-center justify-center">
                <AppIcon name="credit_card" :size="36" />
              </div>
              <div class="space-y-1 max-w-xs">
                <p class="text-base font-bold text-[#1E293B] dark:text-white">Gesek / Masukkan Kartu di Mesin EDC</p>
                <p class="text-xs text-[#64748B] dark:text-[#94A3B8]">
                  Pastikan transaksi di mesin EDC berhasil sebelum menekan tombol konfirmasi.
                </p>
              </div>
            </div>
          </div>

          <!-- Change / Kembalian Row (Style identical to Total Payment with horizontal dashed line, placed directly above action buttons) -->
          <div v-if="paymentMethod === 'cash'" class="pt-2 mt-auto">
            <div class="border-b border-dashed border-[#E2E8F0] dark:border-[#313D4F] my-2" />
            <div class="flex justify-between items-center pt-0.5">
              <span class="font-bold text-sm sm:text-base text-[#1E293B] dark:text-white"
                :class="{ '!text-[#FD5454]': changeAmount < 0 }">
                {{ changeAmount >= 0 ? 'Kembalian' : 'Uang Kurang' }}
              </span>
              <span class="text-base sm:text-lg font-black tabular-nums text-[#1E293B] dark:text-white"
                :class="{ '!text-[#FD5454]': changeAmount < 0 }">
                {{ formatCurrency(Math.abs(changeAmount)) }}
              </span>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex justify-end items-center gap-2.5 pt-3 shrink-0"
            :class="{ 'mt-auto': paymentMethod !== 'cash' }">
            <AppButton variant="outline" size="md" @click="isPaymentModalOpen = false" class="!rounded-lg">
              Batal
            </AppButton>
            <AppButton variant="primary" size="md" :disabled="!isCashEnough" @click="handleConfirmPayment"
              class="!rounded-lg !font-bold">
              Konfirmasi & Bayar
            </AppButton>
          </div>
        </div>
      </div>
    </AppModal>

    <!-- 4. Payment Success & Receipt Modal with Motion dev Animations -->
    <AppModal :show="isSuccessModalOpen" v-model="isSuccessModalOpen" title="Transaksi Berhasil!" maxWidth="sm">
      <div v-if="lastOrderData" class="space-y-4 text-center py-2">
        <!-- 1. Success Check Circle Badge (Spring Pop & Rotate In) -->
        <Motion :initial="{ scale: 0, opacity: 0, rotate: -45 }" :animate="{ scale: 1, opacity: 1, rotate: 0 }"
          :transition="{ type: 'spring', damping: 10, stiffness: 180, delay: 0.05 }"
          class="w-16 h-16 rounded-full bg-[#E6F9F5] text-[#00B69B] flex items-center justify-center mx-auto shadow-sm">
          <AppIcon name="check_circle" :size="36" />
        </Motion>

        <!-- 2. Order Header & Timestamp (Fade Up) -->
        <Motion :initial="{ opacity: 0, y: 12 }" :animate="{ opacity: 1, y: 0 }"
          :transition="{ type: 'spring', damping: 15, stiffness: 120, delay: 0.15 }">
          <h4 class="text-xl font-black text-[#1E293B] dark:text-white tracking-tight">
            {{ lastOrderData.orderNumber }}
          </h4>
          <p class="text-xs text-[#64748B] dark:text-[#94A3B8] mt-0.5">
            {{ lastOrderData.orderType }} â€¢ {{ lastOrderData.timestamp }}
          </p>
        </Motion>

        <!-- 3. Receipt Summary Box (Slide & Scale in after icon) -->
        <Motion :initial="{ opacity: 0, y: 20, scale: 0.96 }" :animate="{ opacity: 1, y: 0, scale: 1 }"
          :transition="{ type: 'spring', damping: 14, stiffness: 120, delay: 0.25 }"
          class="p-4 bg-[#F8FAFC] dark:bg-[#1E293B] rounded-2xl text-left space-y-2 text-xs border border-[#E2E8F0] dark:border-[#334155] shadow-xs">
          <div class="flex justify-between">
            <span class="text-[#64748B] dark:text-[#94A3B8]">Total Tagihan:</span>
            <span class="font-bold tabular-nums text-sm text-[#1E293B] dark:text-white">{{
              formatCurrency(lastOrderData.total) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-[#64748B] dark:text-[#94A3B8]">Metode:</span>
            <span class="font-bold text-[#1E293B] dark:text-white">{{ lastOrderData.paymentMethod }}</span>
          </div>
          <div v-if="lastOrderData.paymentMethod === 'CASH'" class="flex justify-between">
            <span class="text-[#64748B] dark:text-[#94A3B8]">Uang Diterima:</span>
            <span class="font-bold tabular-nums text-[#1E293B] dark:text-white">{{
              formatCurrency(lastOrderData.cashReceived) }}</span>
          </div>
          <div v-if="lastOrderData.paymentMethod === 'CASH'"
            class="flex justify-between text-[#00B69B] font-bold border-t border-[#CBD5E1] dark:border-[#334155] pt-1.5 mt-1">
            <span>Kembalian:</span>
            <span class="tabular-nums font-bold text-sm">{{ formatCurrency(lastOrderData.change) }}</span>
          </div>
        </Motion>
      </div>

      <template #footer>
        <!-- 4. Enlarged Action Buttons with Motion Spring Reveal -->
        <Motion :initial="{ opacity: 0, y: 15 }" :animate="{ opacity: 1, y: 0 }"
          :transition="{ type: 'spring', damping: 14, stiffness: 120, delay: 0.35 }"
          class="flex items-center justify-between w-full gap-3">
          <AppButton variant="outline" size="md" icon="print" @click="handlePrintReceipt"
            class="!rounded-lg flex-1 !h-11 !text-sm !font-bold">
            Cetak Struk
          </AppButton>
          <AppButton variant="primary" size="md" icon="add" @click="handleNewOrder"
            class="!rounded-lg flex-1 !h-11 !text-sm !font-bold">
            Pesanan Baru
          </AppButton>
        </Motion>
      </template>
    </AppModal>
  </div>

</template>