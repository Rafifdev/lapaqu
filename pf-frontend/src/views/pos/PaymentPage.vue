<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useNotyf } from '@/composables/useNotyf'
import { ArrowLeft, CheckCircle2, Printer, DollarSign, QrCode } from 'lucide-vue-next'
import { useFormat } from '@/composables/useFormat'
import AppButton from '@/components/ui/AppButton.vue'
import AppCard from '@/components/ui/AppCard.vue'

const router = useRouter()
const route = useRoute()
const { formatCurrency } = useFormat()

const totalAmount = ref(Number(route.query.amount) || 0)
const paymentType = ref(route.query.type || 'cash')
const cashReceived = ref<number>(Number(route.query.amount) || 0)
const isPaid = ref(false)

const changeAmount = computed(() => Math.max(0, cashReceived.value - totalAmount.value))

const setQuickCash = (val: number) => {
  cashReceived.value = val
}

const notyf = useNotyf()

const completePayment = () => {
  isPaid.value = true
  notyf.success('Pembayaran lunas terkonfirmasi!')
}
</script>

<template>
  <div class="max-w-xl mx-auto space-y-6">
    <button
      @click="router.back()"
      class="flex items-center gap-1.5 text-xs font-bold text-[#606060] dark:text-[#E6E6E6] hover:text-[#4880FF]"
    >
      <ArrowLeft class="w-4 h-4" />
      Kembali ke Layar Kasir
    </button>

    <!-- Success Screen -->
    <AppCard v-if="isPaid">
      <div class="text-center py-6 space-y-4">
        <div class="w-16 h-16 rounded-full bg-[#E6F8F5] text-[#00B69B] flex items-center justify-center mx-auto shadow-md">
          <CheckCircle2 class="w-10 h-10" />
        </div>
        <h2 class="text-2xl font-black text-[#202224] dark:text-white">Pembayaran Sukses!</h2>
        <p class="text-xs text-[#606060] dark:text-[#E6E6E6]/70">Pesanan telah diteruskan otomatis ke KDS Layar Dapur</p>

        <div class="p-4 rounded-2xl bg-[#F5F6FA] dark:bg-[#202936] text-xs space-y-2 text-left">
          <div class="flex justify-between">
            <span class="text-[#606060]">Total Tagihan:</span>
            <span class="font-bold text-[#202224] dark:text-white">{{ formatCurrency(totalAmount) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-[#606060]">Uang Diterima:</span>
            <span class="font-bold text-[#202224] dark:text-white">{{ formatCurrency(cashReceived) }}</span>
          </div>
          <div class="flex justify-between text-sm font-bold text-[#00B69B] pt-2 border-t border-[#E8E8E8] dark:border-[#313D4F]">
            <span>Kembalian:</span>
            <span>{{ formatCurrency(changeAmount) }}</span>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3 pt-2">
          <AppButton @click="router.push('/pos/orders')" variant="primary" size="lg">
            Selesai & Ke Order Masuk
          </AppButton>
          <AppButton variant="outline" size="lg">
            <template #prefix>
              <Printer class="w-4 h-4" />
            </template>
            Cetak Struk
          </AppButton>
        </div>
      </div>
    </AppCard>

    <!-- Payment Calculation Form -->
    <AppCard v-else title="Pembayaran Kasir">
      <div class="space-y-5 text-xs">
        <!-- Total Tagihan -->
        <div class="p-4 rounded-2xl bg-[#E2EAF8] dark:bg-[#323D4E]/60 text-center">
          <p class="text-xs font-bold text-[#606060] dark:text-[#E6E6E6]/70">Total Yang Harus Dibayar:</p>
          <p class="text-3xl font-bold text-[#4880FF] mt-1">{{ formatCurrency(totalAmount) }}</p>
        </div>

        <!-- Cash Calculation Mode -->
        <div v-if="paymentType === 'cash'" class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-[#202224] dark:text-white mb-1.5">Uang Diterima dari Pelanggan (Rp):</label>
            <input
              v-model.number="cashReceived"
              type="number"
              class="w-full h-12 px-4 text-xl font-black text-[#202224] dark:text-white bg-[#F5F6FA] dark:bg-[#202936] border border-[#D5D5D5] dark:border-[#313D4F] rounded-xl focus:outline-none "
            />
          </div>

          <!-- Quick Cash Buttons -->
          <div>
            <p class="text-xs font-bold text-[#606060] dark:text-[#E6E6E6]/60 mb-2">Pecahan Cepat:</p>
            <div class="grid grid-cols-4 gap-2">
              <button
                type="button"
                @click="setQuickCash(totalAmount)"
                class="py-2 rounded-lg bg-[#F5F6FA] dark:bg-[#323D4E] font-bold text-xs hover:bg-[#E2EAF8] transition-colors cursor-pointer"
              >
                Uang Pas
              </button>
              <button
                type="button"
                @click="setQuickCash(50000)"
                class="py-2 rounded-lg bg-[#F5F6FA] dark:bg-[#323D4E] font-bold text-xs hover:bg-[#E2EAF8] transition-colors cursor-pointer"
              >
                50.000
              </button>
              <button
                type="button"
                @click="setQuickCash(100000)"
                class="py-2 rounded-lg bg-[#F5F6FA] dark:bg-[#323D4E] font-bold text-xs hover:bg-[#E2EAF8] transition-colors cursor-pointer"
              >
                100.000
              </button>
              <button
                type="button"
                @click="setQuickCash(200000)"
                class="py-2 rounded-lg bg-[#F5F6FA] dark:bg-[#323D4E] font-bold text-xs hover:bg-[#E2EAF8] transition-colors cursor-pointer"
              >
                200.000
              </button>
            </div>
          </div>

          <!-- Kembalian Display -->
          <div class="p-4 rounded-xl bg-[#E6F8F5] dark:bg-[#00B69B]/10 border border-[#00B69B]/30 flex justify-between items-center">
            <span class="font-bold text-[#00B69B]">Uang Kembalian:</span>
            <span class="text-xl font-bold text-[#00B69B]">{{ formatCurrency(changeAmount) }}</span>
          </div>

          <AppButton
            @click="completePayment"
            variant="success"
            size="lg"
            block
            :disabled="cashReceived < totalAmount"
          >
            Konfirmasi Pembayaran Lunas
          </AppButton>
        </div>

        <!-- Non-Cash QRIS Mode -->
        <div v-else class="space-y-4 text-center">
          <div class="w-48 h-48 bg-white p-4 rounded-2xl border border-[#E8E8E8] dark:border-[#313D4F] mx-auto flex items-center justify-center shadow-sm">
            <img
              src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=qris-demo-kopiceria"
              alt="QRIS"
              class="w-full h-full object-contain"
            />
          </div>
          <p class="text-xs text-[#606060] dark:text-[#E6E6E6]/70">Scan QRIS pelanggan melalui Gopay, OVO, Dana, BCA, dll</p>
          <AppButton @click="completePayment" variant="primary" size="lg" block>
            Verifikasi Selesai (Otomatis)
          </AppButton>
        </div>
      </div>
    </AppCard>
  </div>
</template>
