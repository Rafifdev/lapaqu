<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePosStore } from '@/stores/pos'
import { useCartStore, isPendingOrderExpired } from '@/stores/cart'
import { useFormat } from '@/composables/useFormat'
import AppButton from '@/components/ui/AppButton.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppTextarea from '@/components/ui/AppTextarea.vue'
import type { MenuItem, SelectedOption } from '@/types'

const route = useRoute()
const router = useRouter()
const posStore = usePosStore()
const cartStore = useCartStore()
const { formatCurrency } = useFormat()

const item = ref<MenuItem | null>(null)
const isLoading = ref(true)
const quantity = ref(1)
const notes = ref('')

// Selected options: single (radio) map group_id -> option_id, multi (checkbox) map group_id -> option_id[]
const selectedSingle = ref<Record<string, string>>({})
const selectedMulti = ref<Record<string, string[]>>({})

onMounted(async () => {
  const itemId = route.params.id as string
  const outletId = (route.params.outletId as string) || (route.query.outlet_id as string) || ''
  isLoading.value = true
  try {
    if (posStore.menuItems.length === 0) {
      await posStore.fetchMenuItems(outletId)
    }
    const found = posStore.menuItems.find(i => i.id === itemId)
    if (found) {
      item.value = found
      // Pre-select default first options for single choice groups
      found.variantGroups?.forEach(group => {
        if (group.type === 'single' && group.options.length > 0) {
          selectedSingle.value[group.id] = group.options[0].id
        }
      })
    }
  } catch (err) {
    console.error('Failed to load item:', err)
  } finally {
    isLoading.value = false
  }
})

const toggleMultiOption = (groupId: string, optionId: string) => {
  const current = selectedMulti.value[groupId] || []
  if (current.includes(optionId)) {
    selectedMulti.value[groupId] = current.filter(id => id !== optionId)
  } else {
    selectedMulti.value[groupId] = [...current, optionId]
  }
}

// Compute total selected options
const computedSelectedOptions = computed<SelectedOption[]>(() => {
  const result: SelectedOption[] = []
  if (!item.value?.variantGroups) return result

  item.value.variantGroups.forEach(group => {
    if (group.type === 'single') {
      const optId = selectedSingle.value[group.id]
      const opt = group.options.find(o => o.id === optId)
      if (opt) {
        result.push({
          groupId: group.id,
          groupName: group.name,
          optionId: opt.id,
          optionName: opt.name,
          priceModifier: opt.priceModifier,
        })
      }
    } else {
      const optIds = selectedMulti.value[group.id] || []
      optIds.forEach(id => {
        const opt = group.options.find(o => o.id === id)
        if (opt) {
          result.push({
            groupId: group.id,
            groupName: group.name,
            optionId: opt.id,
            optionName: opt.name,
            priceModifier: opt.priceModifier,
          })
        }
      })
    }
  })
  return result
})

const unitPrice = computed(() => {
  const base = item.value?.price || 0
  const modifiers = computedSelectedOptions.value.reduce((acc, opt) => acc + opt.priceModifier, 0)
  return base + modifiers
})

const subtotal = computed(() => unitPrice.value * quantity.value)

const addToCart = () => {
  if (!item.value) return
  const outletId = (route.params.outletId as string) || cartStore.outletId || ''
  const tableCode = (route.params.tableCode as string) || cartStore.tableCode || ''
  if (cartStore.hasPendingOrder) {
    if (isPendingOrderExpired(cartStore.pendingOrder)) {
      cartStore.clearPendingOrder()
    } else {
      router.push(`/order/${outletId}/${tableCode}/my-order?openQris=1`)
      return
    }
  }
  cartStore.addItem(item.value, quantity.value, computedSelectedOptions.value, notes.value)
  router.push(`/order/${outletId}/${tableCode}/my-order`)
}
</script>

<template>
  <!-- SKELETON LOADING MODE (Bawaan Tailwind CSS animate-pulse) -->
  <div v-if="isLoading" class="space-y-4">
    <!-- Back Button Skeleton -->
    <div class="h-6 w-32 rounded-lg bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />

    <!-- Hero Card Skeleton -->
    <div class="overflow-hidden rounded-2xl border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#273142]">
      <!-- 1:1 Aspect Ratio Food Image Skeleton -->
      <div class="w-full aspect-square bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
      <div class="p-4 space-y-3">
        <div class="h-6 w-3/4 rounded-md bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
        <div class="h-4 w-full rounded-md bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
        <div class="h-4 w-2/3 rounded-md bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
        <div class="h-6 w-1/3 rounded-md bg-[#E2E8F0] dark:bg-[#334155] animate-pulse pt-1" />
      </div>
    </div>

    <!-- Variant Groups Skeleton -->
    <div class="p-4 space-y-4 rounded-2xl border border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#273142]">
      <div class="flex items-center justify-between">
        <div class="h-4 w-28 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
        <div class="h-4 w-20 rounded bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
      </div>
      <div class="grid grid-cols-2 gap-2 pt-1">
        <div class="h-12 rounded-xl bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
        <div class="h-12 rounded-xl bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
      </div>
    </div>

    <!-- Bottom Actions Skeleton -->
    <div class="pt-2 flex items-center gap-3">
      <div class="w-28 h-11 rounded-xl bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
      <div class="flex-1 h-11 rounded-xl bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
    </div>
  </div>

  <div v-else-if="!item" class="py-12 text-center text-xs font-bold text-rose-500">
    Menu tidak ditemukan.
  </div>

  <div v-else class="space-y-4">
    <!-- Back Button (AppButton Reusable Component) -->
    <div>
      <AppButton @click="router.back()" variant="ghost" size="sm" icon="arrow_back"
        class="!p-0 text-[#606060] dark:text-[#E6E6E6] hover:text-[#4880FF]">
        Kembali ke Menu
      </AppButton>
    </div>

    <!-- Hero Card with Image and Info (AppCard Reusable Component) -->
    <AppCard noPadding class="overflow-hidden border border-[#E2E8F0] dark:border-[#334155]">
      <div class="w-full aspect-square bg-[#D8D8D8] dark:bg-[#1E293B]">
        <img :src="item.imageUrl" :alt="item.name" class="w-full h-full object-cover" />
      </div>

      <div class="p-4 space-y-2">
        <h2 class="text-xl font-black text-[#202224] dark:text-white leading-snug">
          {{ item.name }}
        </h2>
        <p class="text-xs text-[#606060] dark:text-[#E6E6E6]/70 leading-relaxed font-medium">
          {{ item.description }}
        </p>
        <p class="text-xl font-bold text-[#1E293B] dark:text-white pt-1">
          {{ formatCurrency(unitPrice) }}
        </p>
      </div>
    </AppCard>

    <!-- Variant Groups Card (AppCard & AppBadge Reusable Components) -->
    <AppCard v-if="item.variantGroups && item.variantGroups.length > 0"
      class="!p-4 space-y-4 border border-[#E2E8F0] dark:border-[#334155]">
      <div v-for="group in item.variantGroups" :key="group.id" class="space-y-2">
        <div class="flex items-center justify-between">
          <span class="text-xs font-extrabold text-[#202224] dark:text-white">
            {{ group.name }}
          </span>
          <AppBadge :variant="group.isRequired ? 'primary' : 'neutral'" size="sm">
            {{ group.isRequired ? 'Wajib Pilih 1' : 'Opsional' }}
          </AppBadge>
        </div>

        <!-- Single Choice Options -->
        <div v-if="group.type === 'single'" class="grid grid-cols-2 gap-2">
          <button v-for="opt in group.options" :key="opt.id" type="button" @click="selectedSingle[group.id] = opt.id"
            :class="[
              'p-3 rounded-xl text-left border text-xs font-bold transition-all flex flex-col justify-between cursor-pointer',
              selectedSingle[group.id] === opt.id
                ? 'border-[#4880FF] bg-[#4880FF]/10 text-[#4880FF] dark:text-white ring-1 ring-[#4880FF]'
                : 'border-[#E2E8F0] dark:border-[#334155] bg-[#F8FAFC] dark:bg-[#1E293B] text-[#202224] dark:text-white hover:border-[#4880FF]/50',
            ]">
            <span>{{ opt.name }}</span>
            <span class="text-[11px] font-semibold text-[#64748B] dark:text-[#94A3B8] mt-1">
              {{ opt.priceModifier > 0 ? `+${formatCurrency(opt.priceModifier)}` : 'Termasuk' }}
            </span>
          </button>
        </div>

        <!-- Multi Choice Options -->
        <div v-else class="space-y-2">
          <button v-for="opt in group.options" :key="opt.id" type="button" @click="toggleMultiOption(group.id, opt.id)"
            :class="[
              'w-full p-3 rounded-xl text-left border text-xs font-bold transition-all flex items-center justify-between cursor-pointer',
              (selectedMulti[group.id] || []).includes(opt.id)
                ? 'border-[#4880FF] bg-[#4880FF]/10 text-[#4880FF] dark:text-white ring-1 ring-[#4880FF]'
                : 'border-[#E2E8F0] dark:border-[#334155] bg-[#F8FAFC] dark:bg-[#1E293B] text-[#202224] dark:text-white hover:border-[#4880FF]/50',
            ]">
            <div class="flex items-center gap-2">
              <div :class="[
                'w-4 h-4 rounded-md flex items-center justify-center text-white text-[10px]',
                (selectedMulti[group.id] || []).includes(opt.id) ? 'bg-[#4880FF]' : 'border border-[#CBD5E1] dark:border-[#475569]',
              ]">
                <AppIcon v-if="(selectedMulti[group.id] || []).includes(opt.id)" name="check" :size="12" />
              </div>
              <span>{{ opt.name }}</span>
            </div>
            <span class="text-[11px] font-semibold text-[#4880FF]">
              +{{ formatCurrency(opt.priceModifier) }}
            </span>
          </button>
        </div>
      </div>
    </AppCard>

    <!-- Quantity & Add to Cart Bottom Sticky (AppButton Reusable Component) -->
    <div class="pt-2 flex items-center gap-3">
      <!-- Stepper Controls -->
      <div
        class="flex items-center gap-2 bg-[#F1F5F9] dark:bg-[#1E293B] p-1 rounded-xl border border-[#E2E8F0] dark:border-[#334155]">
        <AppButton variant="secondary" size="sm" icon="remove" class="!w-8 !h-8 !p-0 !rounded-lg"
          @click="quantity > 1 ? quantity-- : 1" />
        <span class="text-sm font-black text-[#202224] dark:text-white w-6 text-center tabular-nums">
          {{ quantity }}
        </span>
        <AppButton variant="secondary" size="sm" icon="add" class="!w-8 !h-8 !p-0 !rounded-lg" @click="quantity++" />
      </div>

      <!-- Add to Cart Button -->
      <AppButton v-if="!cartStore.hasPendingOrder" @click="addToCart" variant="primary" size="lg" block icon="shopping_bag"
        class="flex-1 shadow-lg shadow-[#4880FF]/25">
        Tambah ({{ formatCurrency(subtotal) }})
      </AppButton>
      <AppButton v-else @click="addToCart" variant="secondary" size="lg" block icon="schedule"
        class="flex-1 shadow-lg shadow-amber-500/25">
        Lanjutkan Pembayaran
      </AppButton>
    </div>
  </div>
</template>
