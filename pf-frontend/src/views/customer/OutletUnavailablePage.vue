<script setup lang="ts">
import AppEmptyState from '@/components/ui/AppEmptyState.vue'
</script>

<template>
  <div class="min-h-[70vh] flex items-center justify-center p-4">
    <AppEmptyState
      icon="storefront"
      title="Outlet Sedang Tidak Tersedia"
      description="Maaf, saat ini layanan pesan mandiri untuk outlet ini sedang dinonaktifkan sementara. Silakan hubungi staff atau kasir resto."
    />
  </div>
</template>
