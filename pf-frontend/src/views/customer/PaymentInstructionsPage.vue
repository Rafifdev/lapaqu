<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCartStore } from '@/stores/cart'
import AppIcon from '@/components/ui/AppIcon.vue'
import { ALL_VA_BANKS, getBankVAInfo } from '@/composables/useBankVA'

const route = useRoute()
const router = useRouter()
const cartStore = useCartStore()

const bankQuery = computed(() => (route.query.bank as string || 'bri').toLowerCase())
const bankInfo = computed(() => getBankVAInfo(bankQuery.value) || ALL_VA_BANKS[0])

const bankTitle = computed(() => bankInfo.value?.code || 'BRI')

interface GuideSection {
  title: string
  steps: string[]
}

const guideData: Record<string, GuideSection[]> = {
  bri: [
    {
      title: 'Cara membayar VA di BRImo',
      steps: [
        'Buka aplikasi BRImo dan login ke akun Anda',
        'Pilih menu "Tagihan" lalu pilih "BRIVA"',
        'Pilih "Tambah Transaksi Baru"',
        'Masukkan nomor BRI Virtual Account',
        'Periksa nominal dan nama tagihan, masukkan PIN BRImo',
        'Pembayaran selesai',
      ],
    },
    {
      title: 'Cara membayar VA di Internet Banking BRI',
      steps: [
        'Login ke Internet Banking BRI',
        'Pilih menu "Pembayaran" > "BRIVA"',
        'Masukkan nomor BRI Virtual Account',
        'Masukkan password dan mToken BRI untuk verifikasi',
        'Pembayaran selesai',
      ],
    },
    {
      title: 'Cara membayar VA di ATM BRI',
      steps: [
        'Masukkan kartu ATM BRI dan PIN Anda',
        'Pilih "Transaksi Lain" > "Pembayaran" > "Lainnya" > "BRIVA"',
        'Masukkan nomor BRI Virtual Account',
        'Periksa detail tagihan pada layar, lalu tekan "Ya"',
        'Pembayaran selesai',
      ],
    },
  ],
  mandiri: [
    {
      title: 'Cara membayar VA di Livin\' by Mandiri',
      steps: [
        'Buka aplikasi Livin\' by Mandiri dan lakukan login',
        'Pilih menu "Bayar" lalu pilih "Virtual Account"',
        'Pilih penyedia jasa atau masukkan nomor Mandiri Virtual Account',
        'Periksa nominal dan detail tagihan',
        'Konfirmasi pembayaran dengan PIN Livin\' Anda',
        'Pembayaran selesai',
      ],
    },
    {
      title: 'Cara membayar VA di ATM Mandiri',
      steps: [
        'Masukkan kartu ATM Mandiri dan PIN Anda',
        'Pilih menu "Bayar/Beli" > "Lainnya" > "Multi Payment"',
        'Masukkan nomor Mandiri Virtual Account',
        'Periksa detail tagihan pada layar ATM, tekan "1" lalu "Ya"',
        'Pembayaran selesai',
      ],
    },
  ],
  bni: [
    {
      title: 'Cara membayar VA di BNI Mobile Banking',
      steps: [
        'Buka aplikasi BNI Mobile Banking dan lakukan login',
        'Pilih menu "Transfer" lalu pilih "Virtual Account Billing"',
        'Pilih "Input Baru" dan masukkan nomor BNI Virtual Account',
        'Periksa informasi tagihan di layar konfirmasi',
        'Masukkan Password Transaksi Anda',
        'Pembayaran selesai',
      ],
    },
    {
      title: 'Cara membayar VA di ATM BNI',
      steps: [
        'Masukkan kartu ATM BNI dan PIN Anda',
        'Pilih menu "Menu Lain" > "Transfer" > "Virtual Account Billing"',
        'Masukkan nomor BNI Virtual Account',
        'Periksa rincian tagihan di layar, tekan "Ya" untuk konfirmasi',
        'Pembayaran selesai',
      ],
    },
  ],
}

const currentGuideSections = computed<GuideSection[]>(() => {
  const code = bankTitle.value.toLowerCase()
  if (guideData[code]) {
    return guideData[code]
  }
  // Generic fallback from useBankVA composable
  return [
    {
      title: `Cara membayar VA di Mobile Banking ${bankTitle.value}`,
      steps: bankInfo.value?.guide?.mbanking || [
        `Buka aplikasi Mobile Banking ${bankTitle.value} Anda`,
        'Pilih menu Transfer atau Pembayaran Virtual Account',
        `Masukkan nomor ${bankTitle.value} Virtual Account`,
        'Konfirmasi nominal dan masukkan PIN untuk menyelesaikan',
        'Pembayaran selesai',
      ],
    },
    {
      title: `Cara membayar VA di ATM ${bankTitle.value}`,
      steps: bankInfo.value?.guide?.atm || [
        `Masukkan kartu ATM ${bankTitle.value} dan PIN Anda`,
        'Pilih menu Transfer / Pembayaran > Virtual Account',
        `Masukkan nomor ${bankTitle.value} Virtual Account`,
        'Periksa rincian tagihan lalu tekan "Ya" untuk menyelesaikan',
        'Pembayaran selesai',
      ],
    },
  ]
})

const handleClose = () => {
  const outletId = (route.params.outletId as string) || cartStore.outletId || ''
  const tableCode = (route.params.tableCode as string) || cartStore.tableCode || ''
  router.push({
    name: 'customer-my-order',
    params: { outletId, tableCode },
    query: { openQris: '1' },
  })
}
</script>

<template>
  <div class="min-h-screen bg-white dark:bg-[#273142] text-[#1E293B] dark:text-white px-5 sm:px-6 pt-6 pb-10 font-sans transition-colors">
    <!-- Header: Close Button -->
    <div class="flex items-center justify-between pb-3.5 border-b border-[#F1F5F9] dark:border-[#334155]/60 mb-7">
      <button
        type="button"
        @click="handleClose"
        class="w-10 h-10 -ml-2 rounded-full flex items-center justify-center text-[#1E293B] dark:text-white hover:bg-slate-100 dark:hover:bg-slate-800 active:scale-90 transition-all cursor-pointer"
        title="Tutup Petunjuk"
      >
        <AppIcon name="close" :size="24" />
      </button>

      <span class="text-xs font-semibold text-[#64748B] dark:text-[#94A3B8]">
        Panduan Pembayaran
      </span>
      <div class="w-8"></div>
    </div>

    <!-- Judul Halaman Font Standar Aplikasi (Sans Bold) -->
    <div class="max-w-xl mx-auto pb-10">
      <h1 class="text-2xl sm:text-3xl font-bold text-[#1E293B] dark:text-white tracking-tight leading-snug mb-7">
        Petunjuk Pembayaran melalui {{ bankTitle }} Virtual Account
      </h1>

      <!-- Daftar Cara Pembayaran Berdasarkan Metode -->
      <div class="space-y-7">
        <div
          v-for="(section, idx) in currentGuideSections"
          :key="idx"
          class="border-b border-[#F1F5F9] dark:border-[#334155]/80 pb-6 last:border-b-0 last:pb-0"
        >
          <!-- Section Title (misal: "Cara membayar VA di BRImo") -->
          <h2 class="text-base sm:text-lg font-bold text-[#1E293B] dark:text-white mb-4 tracking-tight">
            {{ section.title }}
          </h2>

          <!-- Numbered Steps List Sesuai Desain Terkini -->
          <div class="space-y-3.5">
            <div
              v-for="(step, sIdx) in section.steps"
              :key="sIdx"
              class="flex items-start gap-3.5"
            >
              <!-- Circle Number Badge (Tanpa Border & Tanpa Shadow) -->
              <div class="w-7 h-7 rounded-full bg-slate-100 dark:bg-[#1E293B] text-[#1E293B] dark:text-white text-sm font-bold flex items-center justify-center shrink-0 mt-0.5">
                {{ sIdx + 1 }}
              </div>
              <p class="text-sm sm:text-base text-[#475569] dark:text-slate-300 leading-relaxed pt-0.5">
                {{ step }}
              </p>
            </div>
          </div>

          <!-- Note Sesuai Sistem Aplikasi Lapaqu -->
          <p class="text-sm text-[#64748B] dark:text-[#94A3B8] mt-4 leading-relaxed">
            Setelah melakukan pembayaran, pesanan Anda akan otomatis diverifikasi oleh sistem dan diteruskan ke bagian dapur untuk disiapkan.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
