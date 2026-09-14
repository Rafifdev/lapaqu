<script setup lang="ts">
import { ref } from 'vue'
import { Store, Plus, MapPin, Phone } from 'lucide-vue-next'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppBadge from '@/components/ui/AppBadge.vue'

const outlets = ref([
  { id: 'outlet-001', name: 'Kopi Ceria - Cabang Tebet', address: 'Jl. Tebet Raya No. 45, Jakarta Selatan', phone: '0812-3344-5566', isActive: true },
])
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-[#202224] dark:text-white">Cabang & Outlet</h1>
      </div>

      <AppButton variant="primary" size="md">
        <template #prefix>
          <Plus class="w-4 h-4" />
        </template>
        + Tambah Cabang Baru
      </AppButton>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div v-for="o in outlets" :key="o.id" class="dash-card p-5 space-y-3">
        <div class="flex items-start justify-between">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-[#E2EAF8] dark:bg-[#323D4E] text-[#4880FF] flex items-center justify-center">
              <Store class="w-5 h-5" />
            </div>
            <div>
              <h3 class="text-base font-bold text-[#202224] dark:text-white">{{ o.name }}</h3>
              <p class="text-xs text-[#606060] dark:text-[#E6E6E6]/60 flex items-center gap-1 mt-0.5">
                <MapPin class="w-3.5 h-3.5" />
                {{ o.address }}
              </p>
            </div>
          </div>
          <AppBadge variant="success" size="sm">Aktif</AppBadge>
        </div>

        <div class="pt-3 border-t border-[#E8E8E8] dark:border-[#313D4F] flex justify-between items-center text-xs">
          <span class="text-[#606060] flex items-center gap-1">
            <Phone class="w-3.5 h-3.5" />
            {{ o.phone }}
          </span>
          <AppButton variant="outline" size="sm">Kelola Cabang</AppButton>
        </div>
      </div>
    </div>
  </div>
</template>
