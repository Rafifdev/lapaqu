<script setup lang="ts">
import { ref } from 'vue'
import { Bell, CheckCheck, Inbox } from 'lucide-vue-next'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'

interface NotificationItem {
  id: string
  title: string
  message: string
  time: string
  isRead: boolean
}

const notifications = ref<NotificationItem[]>([])

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

      <AppButton
        v-if="notifications.length > 0"
        @click="markAllRead"
        variant="outline"
        size="sm"
      >
        <template #prefix>
          <CheckCheck class="w-4 h-4" />
        </template>
        Tandai Semua Dibaca
      </AppButton>
    </div>

    <!-- Empty State -->
    <div
      v-if="notifications.length === 0"
      class="p-12 text-center bg-white dark:bg-[#273142] rounded-2xl border border-[#E8E8E8] dark:border-[#313D4F] shadow-[0_4px_25px_rgba(0,0,0,0.06)] dark:shadow-none"
    >
      <div class="w-14 h-14 rounded-2xl bg-[#F5F6FA] dark:bg-[#1E293B] flex items-center justify-center mx-auto mb-3 text-[#64748B]">
        <Bell class="w-6 h-6 text-[#4880FF]" />
      </div>
      <h3 class="text-base font-bold text-[#202224] dark:text-white">Tidak Ada Notifikasi Baru</h3>
      <p class="text-sm text-[#64748B] dark:text-[#94A3B8] mt-1 max-w-sm mx-auto">
        Semua pembaruan pesanan transaksi, status meja, dan langganan akan muncul di sini.
      </p>
    </div>

    <!-- Notification List -->
    <div v-else class="space-y-3">
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
