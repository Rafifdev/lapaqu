<script setup lang="ts">
import { ref } from 'vue'
import { Bell, CheckCheck } from 'lucide-vue-next'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'

const notifications = ref([
  { id: '1', title: 'Order Masuk #ORD-260830-001', message: 'Pelanggan di Meja M03 telah menyelesaikan pembayaran QRIS Rp 72.000.', time: '5 menit lalu', isRead: false },
  { id: '2', title: 'Pengingat Masa Aktif Paket', message: 'Paket langganan Anda aktif hingga 31 September 2026.', time: '1 hari lalu', isRead: true },
])

const markAllRead = () => {
  notifications.value.forEach(n => n.isRead = true)
}
</script>

<template>
  <div class="space-y-6 max-w-3xl">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-[#202224] dark:text-white">Pusat Notifikasi</h1>
      </div>

      <AppButton @click="markAllRead" variant="outline" size="sm">
        <template #prefix>
          <CheckCheck class="w-4 h-4" />
        </template>
        Tandai Semua Dibaca
      </AppButton>
    </div>

    <div class="space-y-3">
      <div
        v-for="n in notifications"
        :key="n.id"
        :class="[
          'p-4 rounded-2xl border flex items-start gap-4 transition-all',
          n.isRead ? 'bg-white dark:bg-[#273142] border-[#E8E8E8] dark:border-[#313D4F]' : 'bg-[#E2EAF8]/50 dark:bg-[#323D4E]/60 border-[#4880FF]',
        ]"
      >
        <div class="w-9 h-9 rounded-full bg-[#4880FF] text-white flex items-center justify-center shrink-0">
          <Bell class="w-4 h-4" />
        </div>
        <div class="flex-1 text-xs">
          <h4 class="font-bold text-[#202224] dark:text-white text-sm">{{ n.title }}</h4>
          <p class="text-[#606060] dark:text-[#E6E6E6]/70 mt-1">{{ n.message }}</p>
          <span class="text-[10px] text-[#606060]/60 mt-2 block">{{ n.time }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
