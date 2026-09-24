<script setup lang="ts">
import managerImg from '@/assets/roles/role-manager.jpg'
import cashierImg from '@/assets/roles/role-cashier.jpg'
import kitchenImg from '@/assets/roles/role-kitchen.jpg'

defineProps<{
  role: string
}>()
</script>

<template>
  <div class="w-full h-full flex items-center justify-center select-none pointer-events-none">
    <img
      v-if="role === 'store_manager'"
      :src="managerImg"
      alt="Store Manager"
      class="max-h-full max-w-full object-contain rounded-xl select-none"
    />
    <img
      v-else-if="role === 'kasir'"
      :src="cashierImg"
      alt="Kasir POS"
      class="max-h-full max-w-full object-contain rounded-xl select-none"
    />
    <img
      v-else
      :src="kitchenImg"
      alt="Kitchen / Dapur"
      class="max-h-full max-w-full object-contain rounded-xl select-none"
    />
  </div>
</template>
