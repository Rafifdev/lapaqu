<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useCartStore } from '@/stores/cart'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppCard from '@/components/ui/AppCard.vue'

const router = useRouter()
const route = useRoute()
const cartStore = useCartStore()

const isLoading = ref(true)

onMounted(async () => {
  try {
    await new Promise(r => setTimeout(r, 350))
  } finally {
    isLoading.value = false
  }
})

const outletId = computed(() => (route.params.outletId as string) || cartStore.outletId || '')
const tableCode = computed(() => (route.params.tableCode as string) || cartStore.tableCode || '')
const menuUrl = computed(() => `/order/${outletId.value}/${tableCode.value}`)
</script>

<template>
  <div class="flex flex-col items-center justify-center text-center py-6 select-none">
    <!-- SKELETON LOADING STATE -->
    <div v-if="isLoading" class="w-full flex flex-col items-center">
      <div class="w-24 h-24 rounded-3xl bg-[#E2E8F0] dark:bg-[#334155] animate-pulse mb-6" />
      <div class="h-6 w-36 rounded-full bg-[#E2E8F0] dark:bg-[#334155] animate-pulse mb-3" />
      <div class="h-7 w-64 rounded-lg bg-[#E2E8F0] dark:bg-[#334155] animate-pulse mb-2" />
      <div class="h-4 w-48 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse mb-4" />
      <div class="h-3.5 w-72 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
      <div class="h-3.5 w-60 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse mt-2" />

      <!-- Table Info Card Skeleton -->
      <div class="mt-6 w-full p-4 rounded-3xl border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#273142] flex items-center justify-between shadow-xs">
        <div class="space-y-2 text-left">
          <div class="h-3 w-24 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
          <div class="h-6 w-20 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
        </div>
        <div class="h-6 w-24 rounded-full bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
      </div>

      <!-- Action Button Skeleton -->
      <div class="mt-8 w-full h-12 rounded-full bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
    </div>

    <!-- REAL DATA STATE -->
    <template v-else>
      <div class="w-24 h-24 rounded-3xl bg-gradient-to-tr from-[#4880FF] to-[#709eff] text-white flex items-center justify-center shadow-xl mb-6 transform hover:scale-105 transition-transform">
        <AppIcon name="restaurant" :size="48" />
      </div>

      <AppBadge variant="primary" size="md" icon="auto_awesome" class="mb-3">
        Customer Self-Order
      </AppBadge>

      <h1 class="text-2xl font-black text-[#1E293B] dark:text-white tracking-tight">
        Selamat Datang di <br />
        <span class="text-[#4880FF]">Kopi Ceria Nusantara</span>
      </h1>

      <p class="text-xs text-[#475569] dark:text-[#94A3B8] mt-2 max-w-xs leading-relaxed font-medium">
        Pesan kopi dan menu favorit Anda langsung dari meja tanpa perlu mengantri di kasir.
      </p>

      <!-- Table Info Card (AppCard & AppBadge Reusable Components) -->
      <AppCard class="mt-6 w-full text-left !p-4 border border-[#E2E8F0] dark:border-[#334155]">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs text-[#64748B] dark:text-[#94A3B8] font-bold">Nomor Meja Anda:</p>
            <p class="text-xl font-black text-[#4880FF]">Meja {{ tableCode }}</p>
          </div>
          <AppBadge variant="success" size="sm" dot>
            Sesi Baru Aktif
          </AppBadge>
        </div>
      </AppCard>

      <!-- Action Button (AppButton Reusable Component) -->
      <AppButton
        @click="router.push(menuUrl)"
        variant="primary"
        size="lg"
        block
        iconRight="arrow_forward"
        class="mt-8 shadow-lg shadow-[#4880FF]/30"
      >
        Lihat Buku Menu
      </AppButton>
    </template>
  </div>
</template>
