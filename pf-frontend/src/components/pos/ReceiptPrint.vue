<template>
  <!-- Hidden receipt wrapper: only visible when printing -->
  <div id="receipt-print-wrapper" class="receipt-wrapper">
    <div class="receipt">
      <!-- ===== HEADER ===== -->
      <div class="receipt-header">
        <div class="outlet-name">{{ outletInfo.name }}</div>
        <div class="outlet-address">{{ outletInfo.address }}</div>
        <div class="outlet-phone">Telp: {{ outletInfo.phone }}</div>
        <div v-if="outletInfo.taxId" class="outlet-taxid">NPWP: {{ outletInfo.taxId }}</div>
      </div>

      <div class="divider-dashed"></div>

      <!-- ===== ORDER INFO ===== -->
      <div class="order-info-row">
        <span>No. Pesanan</span><span class="bold">{{ order.orderNumber }}</span>
      </div>
      <div class="order-info-row">
        <span>Kasir</span><span>{{ order.cashierName || '-' }}</span>
      </div>
      <div class="order-info-row">
        <span>Tanggal</span><span>{{ formattedDate }}</span>
      </div>
      <div class="order-info-row">
        <span>Jam</span><span>{{ order.timestamp }}</span>
      </div>
      <div class="order-info-row">
        <span>Tipe</span>
        <span class="bold">{{ order.orderType === 'dine_in' ? 'Dine In' : 'Takeaway' }}</span>
      </div>
      <div v-if="order.orderType === 'dine_in'" class="order-info-row">
        <span>Meja</span><span class="bold">{{ order.table }}</span>
      </div>

      <div class="divider-dashed"></div>

      <!-- ===== ITEMS ===== -->
      <div class="items-header-row">
        <span class="item-col-name">Item</span>
        <span class="item-col-qty">Qty</span>
        <span class="item-col-price">Harga</span>
        <span class="item-col-subtotal">Subtotal</span>
      </div>
      <div class="divider-solid-thin"></div>

      <div v-for="item in order.items" :key="item.id" class="item-row">
        <span class="item-col-name">
          {{ item.menuItem.name }}
          <span v-if="item.notes" class="item-note">* {{ item.notes }}</span>
        </span>
        <span class="item-col-qty">{{ item.quantity }}</span>
        <span class="item-col-price">{{ fmt(item.unitPrice) }}</span>
        <span class="item-col-subtotal">{{ fmt(item.subtotal) }}</span>
      </div>

      <div class="divider-dashed"></div>

      <!-- ===== TOTALS ===== -->
      <div class="total-row">
        <span>Subtotal</span><span>{{ fmt(order.subtotal) }}</span>
      </div>
      <div class="total-row">
        <span>Pajak (PPN 11%)</span><span>{{ fmt(order.tax) }}</span>
      </div>
      <div class="divider-solid-thin"></div>
      <div class="total-row grand-total-row">
        <span>TOTAL</span><span>{{ fmt(order.total) }}</span>
      </div>

      <div class="divider-dashed"></div>

      <!-- ===== PAYMENT ===== -->
      <div class="total-row">
        <span>Metode Bayar</span><span class="bold">{{ order.paymentMethod }}</span>
      </div>
      <template v-if="order.paymentMethod === 'CASH'">
        <div class="total-row">
          <span>Uang Diterima</span><span>{{ fmt(order.cashReceived) }}</span>
        </div>
        <div class="total-row">
          <span>Kembalian</span><span class="bold">{{ fmt(order.change) }}</span>
        </div>
      </template>

      <div class="divider-dashed"></div>

      <!-- ===== FOOTER ===== -->
      <div class="receipt-footer">
        <p>Terima kasih telah berkunjung!</p>
        <p>Simpan struk ini sebagai bukti pembayaran</p>
        <p class="footer-app">Powered by Lapaqu</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { CartItem } from "@/types"

interface OrderData {
  orderNumber: string
  orderType: string
  table: string
  items: CartItem[]
  subtotal: number
  tax: number
  total: number
  paymentMethod: string
  cashReceived: number
  change: number
  timestamp: string
  cashierName?: string
}

interface OutletInfo {
  name: string
  address: string
  phone: string
  taxId?: string
}

defineProps<{
  order: OrderData
  outletInfo: OutletInfo
}>()

const fmt = (val: number) =>
  new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    minimumFractionDigits: 0,
  }).format(val)

const formattedDate = new Date().toLocaleDateString("id-ID", {
  weekday: "short",
  day: "2-digit",
  month: "short",
  year: "numeric",
})
</script>

<style scoped>
/* Hidden by default on screen */
.receipt-wrapper {
  display: none;
}

@media print {
  :global(@page) {
    size: 80mm auto;
    margin: 0;
  }

  :global(body) {
    background: white !important;
    margin: 0 !important;
    padding: 0 !important;
  }

  /* Hide everything else on the page */
  :global(body > *:not(#receipt-print-wrapper)) {
    display: none !important;
  }

  .receipt-wrapper {
    display: block !important;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    background: white;
  }

  .receipt {
    width: 76mm;
    margin: 0 auto;
    padding: 3mm 2mm;
    font-family: "Courier New", Courier, monospace;
    font-size: 8pt;
    line-height: 1.4;
    color: #000;
    background: #fff;
  }

  /* HEADER */
  .receipt-header {
    text-align: center;
    margin-bottom: 2mm;
  }
  .outlet-name {
    font-size: 11pt;
    font-weight: 700;
    margin-bottom: 1mm;
  }
  .outlet-address,
  .outlet-phone,
  .outlet-taxid {
    font-size: 7pt;
  }

  /* DIVIDERS */
  .divider-dashed {
    border: none;
    border-top: 1px dashed #000;
    margin: 2mm 0;
  }
  .divider-solid-thin {
    border: none;
    border-top: 1px solid #000;
    margin: 1mm 0;
  }

  /* ORDER INFO */
  .order-info-row {
    display: flex;
    justify-content: space-between;
    font-size: 7.5pt;
    line-height: 1.5;
  }
  .bold {
    font-weight: 700;
  }

  /* ITEM TABLE */
  .items-header-row {
    display: flex;
    font-size: 7pt;
    font-weight: 700;
    text-transform: uppercase;
  }

  .item-row {
    display: flex;
    font-size: 7.5pt;
    line-height: 1.4;
    align-items: flex-start;
  }

  .item-col-name {
    flex: 1;
    padding-right: 1mm;
    word-break: break-word;
    display: flex;
    flex-direction: column;
  }
  .item-col-qty {
    width: 7mm;
    text-align: center;
    flex-shrink: 0;
  }
  .item-col-price {
    width: 19mm;
    text-align: right;
    flex-shrink: 0;
  }
  .item-col-subtotal {
    width: 21mm;
    text-align: right;
    flex-shrink: 0;
  }

  .item-note {
    font-size: 6.5pt;
    color: #444;
    font-style: italic;
  }

  /* TOTALS */
  .total-row {
    display: flex;
    justify-content: space-between;
    font-size: 8pt;
    line-height: 1.55;
  }
  .grand-total-row {
    font-size: 10pt;
    font-weight: 700;
  }

  /* FOOTER */
  .receipt-footer {
    text-align: center;
    font-size: 7pt;
    margin-top: 2mm;
  }
  .receipt-footer p {
    margin-bottom: 0.5mm;
  }
  .footer-app {
    margin-top: 2mm;
    font-size: 6.5pt;
    opacity: 0.5;
  }
}
</style>
