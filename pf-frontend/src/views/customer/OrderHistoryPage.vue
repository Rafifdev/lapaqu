<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useFormat } from '@/composables/useFormat'
import { useCartStore } from '@/stores/cart'
import AppButton from '@/components/ui/AppButton.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const router = useRouter()
const route = useRoute()
const cartStore = useCartStore()
const { formatCurrency } = useFormat()

const isLoading = ref(true)

onMounted(async () => {
  try {
    await new Promise(r => setTimeout(r, 350))
  } finally {
    isLoading.value = false
  }
})

const outletId = computed(() => (route.params.outletId as string) || 'outlet-001')
const tableCode = computed(() => (route.params.tableCode as string) || cartStore.tableCode || 'M03')
const menuUrl = computed(() => `/order/${outletId.value}/${tableCode.value}`)
</script>

<template>
  <div class="space-y-4 select-none">
    <!-- SKELETON LOADING STATE -->
    <div v-if="isLoading" class="space-y-4">
      <div class="space-y-2">
        <div class="h-6 w-48 rounded-lg bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
        <div class="h-3.5 w-64 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
      </div>

      <div class="space-y-3">
        <div v-for="n in 3" :key="n" class="p-4 rounded-3xl border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#273142] space-y-3 shadow-xs">
          <div class="flex items-center justify-between">
            <div class="h-4 w-28 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
            <div class="h-5 w-16 rounded-full bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
          </div>
          <div class="h-3.5 w-4/5 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
          <div class="flex justify-between items-center pt-2 border-t border-[#F1F5F9] dark:border-[#334155]">
            <div class="h-3 w-16 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
            <div class="h-4 w-20 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
          </div>
        </div>
      </div>

      <div class="h-11 w-full rounded-full bg-[#E2E8F0] dark:bg-[#334155] animate-pulse mt-6" />
    </div>

    <!-- REAL DATA STATE -->
    <template v-else>
      <div>
        <h2 class="text-lg font-black text-[#202224] dark:text-white">Riwayat Sesi Meja {{ tableCode }}</h2>
        <p class="text-xs text-[#64748B] dark:text-[#94A3B8] font-medium mt-0.5">Daftar semua pesanan yang telah dikirim dalam sesi kunjungan ini.</p>
      </div>

      <div class="space-y-3">
        <!-- Order History Card (AppCard & AppBadge Reusable Components) -->
        <AppCard class="!p-4 border border-[#E2E8F0] dark:border-[#334155] space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-black text-[#202224] dark:text-white">#ORD-260830-001</span>
            <AppBadge variant="warning" size="sm" dot>Diproses</AppBadge>
          </div>
          <p class="text-xs text-[#475569] dark:text-[#CBD5E1] font-medium">2x Kopi Susu Gula Aren, 1x Croissant Butter</p>
          <div class="flex justify-between items-center pt-2 border-t border-[#E2E8F0] dark:border-[#334155]">
            <span class="text-[11px] text-[#64748B] dark:text-[#94A3B8]">11:05 WIB</span>
            <span class="text-xs font-bold text-[#1E293B] dark:text-white tabular-nums">Rp 72.000</span>
          </div>
        </AppCard>
      </div>

      <AppButton
        @click="router.push(menuUrl)"
        variant="primary"
        size="md"
        block
        icon="restaurant_menu"
        class="mt-6"
      >
        Pesan Menu Lagi
      </AppButton>
    </template>
  </div>
</template>
