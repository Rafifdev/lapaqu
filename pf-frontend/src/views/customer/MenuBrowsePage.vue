<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppEmptyState from '@/components/ui/AppEmptyState.vue'
import { usePosStore } from '@/stores/pos'
import { useCartStore } from '@/stores/cart'
import { useCustomerI18n } from '@/i18n'
import { useFormat } from '@/composables/useFormat'
import type { MenuItem, SelectedOption } from '@/types'

const route = useRoute()
const router = useRouter()
const posStore = usePosStore()
const cartStore = useCartStore()
const { t, translate } = useCustomerI18n()
const { formatCurrency } = useFormat()

const outletId = computed(() => (route.params.outletId as string) || (route.query.outlet_id as string) || '')
const tableCode = computed(() => (route.params.tableCode as string) || cartStore.tableCode || 'M01')
const myOrderUrl = computed(() => `/order/${outletId.value || ''}/${tableCode.value}/my-order`)

const selectedCategoryId = ref<string>('all')
const categoryBarRef = ref<HTMLElement | null>(null)

const scrollToActiveCategory = (targetEl?: HTMLElement | null) => {
  nextTick(() => {
    if (!categoryBarRef.value) return
    const container = categoryBarRef.value
    const el = targetEl || (container.querySelector('[data-active="true"]') as HTMLElement | null)
    if (!el) {
      container.scrollTo({ left: 0, behavior: 'smooth' })
      return
    }

    const containerRect = container.getBoundingClientRect()
    const elRect = el.getBoundingClientRect()
    const currentScroll = container.scrollLeft
    const containerWidth = container.clientWidth

    // Posisi relatif elemen terhadap konten scrollable kontainer
    const targetLeftInContainer = elRect.left - containerRect.left + currentScroll
    const targetRightInContainer = targetLeftInContainer + elRect.width

    // Deteksi apakah tab terpotong di tepi kanan atau kiri layar (buffer 12px)
    const isClippedRight = targetRightInContainer > (currentScroll + containerWidth - 12)
    const isClippedLeft = targetLeftInContainer < (currentScroll + 12)
    const isClipped = isClippedRight || isClippedLeft

    // HANYA jika tab terpotong: geser otomatis agar posisinya berada di tengah
    if (isClipped) {
      const targetCenter = targetLeftInContainer + elRect.width / 2
      const targetScroll = targetCenter - containerWidth / 2

      container.scrollTo({
        left: Math.max(0, targetScroll),
        behavior: 'smooth'
      })
    }
  })
}

const selectCategory = (catId: string, event?: MouseEvent) => {
  selectedCategoryId.value = catId
  if (catId === 'all') {
    categoryBarRef.value?.scrollTo({ left: 0, behavior: 'smooth' })
    return
  }
  const targetEl = (event?.currentTarget as HTMLElement) || null
  scrollToActiveCategory(targetEl)
}

const searchQuery = computed({ get: () => cartStore.searchQuery, set: (v: string) => { cartStore.searchQuery = v } })
// Cache-first: Langsung render instan jika data sudah ada di cache store
const isLoading = ref(posStore.menuItems.length === 0)
const isScrolled = ref(false)

const handleScroll = () => {
  isScrolled.value = window.scrollY > 15
}

const loadMenuData = async (forceLoading = false) => {
  if (forceLoading || posStore.menuItems.length === 0) {
    isLoading.value = true
  }
  try {
    await Promise.all([
      posStore.fetchCategories(outletId.value),
      posStore.fetchMenuItems(outletId.value),
    ])
  } catch (err) {
    console.error('Failed to load menu items:', err)
  } finally {
    isLoading.value = false
  }
}

onMounted(async () => {
  window.addEventListener('scroll', handleScroll, { passive: true })
  handleScroll()
  await loadMenuData()
})

watch(() => route.params.outletId, async (newId) => {
  if (newId) {
    await loadMenuData()
  }
})

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll)
  if (typeof window !== 'undefined') {
    window.removeEventListener('wheel', preventBackgroundWheel)
    window.removeEventListener('touchmove', preventBackgroundTouch)
  }
})

const categories = computed(() => posStore.categories)

const formatTitleCase = (str: string) => {
  if (!str) return ''
  return str
    .toLowerCase()
    .split(' ')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}

const highlightMatch = (text: string, query: string) => {
  if (!query || !query.trim()) return text
  const q = query.trim()
  const regex = new RegExp(`(${q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi')
  return text.replace(regex, '<span class="text-[#4880FF]">$1</span>')
}

const selectedCategoryName = computed(() => {
  if (selectedCategoryId.value === 'all') return t('menu.allCategories')
  const cat = posStore.categories.find(c => c.id === selectedCategoryId.value)
  return cat ? cat.name : 'Menu'
})

// Search results computed for live search mode
const searchResults = computed(() => {
  if (!cartStore.searchQuery.trim()) return []
  const q = cartStore.searchQuery.toLowerCase().trim()
  return posStore.menuItems.filter(item => {
    const matchName = item.name.toLowerCase().includes(q)
    const matchDesc = item.description ? item.description.toLowerCase().includes(q) : false
    const matchCat = item.category ? item.category.name.toLowerCase().includes(q) : false
    return matchName || matchDesc || matchCat
  })
})

const getItemQuantity = (itemId: string): number => {
  return cartStore.items
    .filter(i => i.menuItem.id === itemId)
    .reduce((total, i) => total + i.quantity, 0)
}

const filteredItems = computed(() => {
  return posStore.menuItems.filter(item => {
    const matchCategory = selectedCategoryId.value === 'all' || item.categoryId === selectedCategoryId.value
    const matchSearch = item.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (item.description && item.description.toLowerCase().includes(searchQuery.value.toLowerCase()))
    return matchCategory && matchSearch
  })
})

// Bottom Sheet Detail Modal State & Drag to Close
const selectedItem = ref<MenuItem | null>(null)
const isDetailModalOpen = ref(false)
const modalQuantity = ref(1)
const modalNotes = ref('')
const selectedSingle = ref<Record<string, string>>({})
const selectedMulti = ref<Record<string, string[]>>({})

// Drag-to-close state (seluruh modal bisa ditarik ke bawah untuk menutup)
const modalRef = ref<HTMLElement | null>(null)
const modalScrollRef = ref<HTMLElement | null>(null)
const isDragging = ref(false)
const isClosingByDrag = ref(false)
const dragTranslateY = ref(0)
let dragStartY = 0
let dragStartX = 0
let dragStartTime = 0
let isTrackingTouch = false
let initialScrollTop = 0

// Prevent background scrolling while modal is open WITHOUT breaking position:sticky on header and categories
const preventBackgroundWheel = (e: WheelEvent) => {
  if (!modalScrollRef.value || !modalScrollRef.value.contains(e.target as Node)) {
    e.preventDefault()
  }
}

const preventBackgroundTouch = (e: TouchEvent) => {
  if (!modalRef.value || !modalRef.value.contains(e.target as Node)) {
    if (e.cancelable) e.preventDefault()
  }
}

watch(isDetailModalOpen, (isOpen) => {
  if (typeof window !== 'undefined') {
    if (isOpen) {
      window.addEventListener('wheel', preventBackgroundWheel, { passive: false })
      window.addEventListener('touchmove', preventBackgroundTouch, { passive: false })
    } else {
      window.removeEventListener('wheel', preventBackgroundWheel)
      window.removeEventListener('touchmove', preventBackgroundTouch)
    }
  }
})

const finishDrag = () => {
  const timeElapsed = Date.now() - dragStartTime
  const velocity = dragTranslateY.value / Math.max(1, timeElapsed)

  // If dragged down past 70px or flicked down fast
  if (dragTranslateY.value > 70 || (velocity > 0.3 && dragTranslateY.value > 25)) {
    isDragging.value = false
    isClosingByDrag.value = true

    // Luncurkan ke 100% off-screen langsung dari posisi drag sekarang
    setTimeout(() => {
      isDetailModalOpen.value = false
      selectedItem.value = null
      nextTick(() => {
        isClosingByDrag.value = false
        dragTranslateY.value = 0
      })
    }, 250)
  } else {
    // Snap back ke posisi atas (0px) jika belum melewati batas drag
    isDragging.value = false
    dragTranslateY.value = 0
  }
}

const onModalTouchStart = (e: TouchEvent) => {
  if (e.touches.length !== 1) return
  const touch = e.touches[0]
  dragStartY = touch.clientY
  dragStartX = touch.clientX
  dragStartTime = Date.now()
  initialScrollTop = modalScrollRef.value ? modalScrollRef.value.scrollTop : 0
  isTrackingTouch = true
  e.stopPropagation()
}

const onModalTouchMove = (e: TouchEvent) => {
  if (!isTrackingTouch || e.touches.length !== 1) return
  const touch = e.touches[0]
  const deltaY = touch.clientY - dragStartY
  const deltaX = touch.clientX - dragStartX

  // Stop propagation so background never shakes or scrolls
  e.stopPropagation()

  // If already dragging the modal, continue
  if (isDragging.value) {
    dragTranslateY.value = Math.max(0, deltaY)
    if (e.cancelable) e.preventDefault()
    return
  }

  // Cancel tracking if user intends horizontal swipe
  if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 8) {
    isTrackingTouch = false
    return
  }

  const currentScrollTop = modalScrollRef.value ? modalScrollRef.value.scrollTop : 0

  // Trigger modal drag if swiping downward and content is scrolled to top
  if (deltaY > 6 && currentScrollTop <= 0) {
    isDragging.value = true
    dragTranslateY.value = Math.max(0, deltaY)
    if (e.cancelable) e.preventDefault()
  }
}

const onModalTouchEnd = (e: TouchEvent) => {
  if (!isTrackingTouch) return
  isTrackingTouch = false
  e.stopPropagation()

  if (isDragging.value) {
    finishDrag()
  }
}

// Mouse / Pointer drag for desktop support
const onModalPointerDown = (e: PointerEvent) => {
  if (e.button !== 0) return
  // Don't intercept interactive controls
  const target = e.target as HTMLElement | null
  if (target && target.closest('button, input, textarea, a, label')) return

  dragStartY = e.clientY
  dragStartX = e.clientX
  dragStartTime = Date.now()
  initialScrollTop = modalScrollRef.value ? modalScrollRef.value.scrollTop : 0

  const onPointerMove = (pe: PointerEvent) => {
    const deltaY = pe.clientY - dragStartY
    const deltaX = pe.clientX - dragStartX

    if (isDragging.value) {
      dragTranslateY.value = Math.max(0, deltaY)
      return
    }

    if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 8) {
      cleanupPointer()
      return
    }

    const currentScrollTop = modalScrollRef.value ? modalScrollRef.value.scrollTop : 0
    if (deltaY > 6 && currentScrollTop <= 0) {
      isDragging.value = true
      dragTranslateY.value = Math.max(0, deltaY)
    }
  }

  const onPointerUp = () => {
    cleanupPointer()
    if (isDragging.value) {
      finishDrag()
    }
  }

  const cleanupPointer = () => {
    window.removeEventListener('pointermove', onPointerMove)
    window.removeEventListener('pointerup', onPointerUp)
    window.removeEventListener('pointercancel', onPointerUp)
  }

  window.addEventListener('pointermove', onPointerMove)
  window.addEventListener('pointerup', onPointerUp)
  window.addEventListener('pointercancel', onPointerUp)
}

const openDetail = (item: MenuItem) => {
  if (!item.isAvailable) return
  selectedItem.value = item
  modalQuantity.value = 1
  modalNotes.value = ''
  selectedSingle.value = {}
  selectedMulti.value = {}
  isClosingByDrag.value = false
  dragTranslateY.value = 0
  isDragging.value = false

  // Pre-select default first options for single choice groups
  item.variantGroups?.forEach(group => {
    if (group.type === 'single' && group.options.length > 0) {
      selectedSingle.value[group.id] = group.options[0].id
    }
  })

  isDetailModalOpen.value = true
}

const closeDetailModal = () => {
  isDetailModalOpen.value = false
  isClosingByDrag.value = false
  dragTranslateY.value = 0
  isDragging.value = false
  setTimeout(() => {
    selectedItem.value = null
  }, 300)
}

const toggleMultiOption = (groupId: string, optionId: string) => {
  const current = selectedMulti.value[groupId] || []
  if (current.includes(optionId)) {
    selectedMulti.value[groupId] = current.filter(id => id !== optionId)
  } else {
    selectedMulti.value[groupId] = [...current, optionId]
  }
}

// Compute selected options for modal item
const computedModalOptions = computed<SelectedOption[]>(() => {
  const result: SelectedOption[] = []
  if (!selectedItem.value?.variantGroups) return result

  selectedItem.value.variantGroups.forEach(group => {
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

const modalUnitPrice = computed(() => {
  const base = selectedItem.value?.price || 0
  const modifiers = computedModalOptions.value.reduce((acc, opt) => acc + opt.priceModifier, 0)
  return base + modifiers
})

const modalSubtotal = computed(() => modalUnitPrice.value * modalQuantity.value)

const handleAddToCartFromModal = () => {
  if (cartStore.hasPendingOrder) {
    closeDetailModal()
    router.push(`${myOrderUrl.value}?openQris=1`)
    return
  }
  if (!selectedItem.value) return
  cartStore.addItem(selectedItem.value, modalQuantity.value, computedModalOptions.value, '')
  closeDetailModal()
}

const handleQuickAdd = (event: Event, item: MenuItem) => {
  event.stopPropagation()
  if (!item.isAvailable) return
  if (cartStore.hasPendingOrder) {
    router.push(`${myOrderUrl.value}?openQris=1`)
    return
  }
  // If item has variants, open detail modal to select options
  if (item.variantGroups && item.variantGroups.length > 0) {
    openDetail(item)
  } else {
    cartStore.addItem(item, 1)
  }
}

const handleQuickRemove = (event: Event, item: MenuItem) => {
  event.stopPropagation()
  if (cartStore.hasPendingOrder) return

  // Cari item terakhir di keranjang yang cocok dengan item.id
  const matchingItems = cartStore.items.filter(i => i.menuItem.id === item.id)
  if (matchingItems.length > 0) {
    const targetItem = matchingItems[matchingItems.length - 1]
    cartStore.updateQuantity(targetItem.id, -1)
  }
}
</script>

<template>
  <div :class="['space-y-3', (cartStore.totalItemsCount > 0 || cartStore.hasPendingOrder) ? 'pb-32' : 'pb-1']">
    <!-- MODE 1: SEARCH RESULTS VIEW (ShopeeFood Style) -->
    <div v-if="cartStore.isSearchOpen && cartStore.searchQuery.trim()" class="-mx-4 px-3 pt-1 pb-4 space-y-4">
      <!-- Empty State jika tidak ada hasil -->
      <AppEmptyState
        v-if="searchResults.length === 0"
        icon="search_off"
        title="Menu Tidak Ditemukan"
        :description="`Tidak ada menu yang cocok dengan kata kunci '${cartStore.searchQuery}'.`"
        actionLabel="Hapus Pencarian"
        @action="cartStore.searchQuery = ''"
      />

      <!-- List Hasil Pencarian -->
      <div v-else class="space-y-4 pt-1">
        <div
          v-for="item in searchResults"
          :key="item.id"
          @click="openDetail(item)"
          class="flex items-start gap-3 cursor-pointer select-none"
        >
          <!-- Food Image (Left): Square 84x84 Rounded -->
          <div class="w-[84px] h-[84px] rounded-xl overflow-hidden relative bg-[#F8FAFC] dark:bg-[#1E293B] border border-[#E2E8F0]/70 dark:border-[#334155]/60 shrink-0">
            <img
              :src="item.imageUrl || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=500&auto=format&fit=crop&q=80'"
              :alt="item.name"
              class="w-full h-full object-cover"
            />
            <div
              v-if="!item.isAvailable"
              class="absolute inset-0 bg-black/60 flex items-center justify-center pointer-events-none"
            >
              <AppBadge variant="danger" solid size="sm">Habis</AppBadge>
            </div>


          </div>

          <!-- Food Details Column (Center & Right) -->
          <div class="flex-1 min-w-0 h-[84px] flex flex-col justify-between py-0.5">
            <div class="min-w-0">
              <h3
                class="text-[15px] font-bold text-[#1E293B] dark:text-white line-clamp-1 leading-snug tracking-tight uppercase"
                v-html="highlightMatch(item.name, cartStore.searchQuery)"
              />
              <p class="text-xs text-[#64748B] dark:text-[#94A3B8] mt-0.5 line-clamp-2 leading-snug font-medium">
                {{ item.description || (item.category ? item.category.name : 'Pilihan Menu Favorit') }}
              </p>
            </div>

            <!-- Bottom: Price on Left, Plus Button on Right -->
            <div class="flex items-center justify-between gap-2 mt-2">
              <p class="text-[15px] font-bold text-[#1E293B] dark:text-white tabular-nums">
                {{ formatCurrency(item.price) }}
              </p>

              <!-- Quick Add & Stepper Button -->
              <div v-if="item.isAvailable" class="flex items-center shrink-0">
                <!-- State 2: Terhubung Sesuai Gambar Sketsa jika qty > 0 (Pixel-perfect h-7.5) -->
                <div
                  v-if="getItemQuantity(item.id) > 0"
                  @click.stop
                  class="flex items-center"
                >
                  <button
                    type="button"
                    @click.stop="handleQuickRemove($event, item)"
                    class="w-7.5 h-7.5 rounded-lg border-2 border-[#4880FF] text-[#4880FF] bg-white dark:bg-[#273142] hover:bg-[#4880FF]/10 active:scale-90 flex items-center justify-center shadow-xs transition-all cursor-pointer shrink-0 z-10"
                    title="Kurangi"
                  >
                    <AppIcon name="remove" :size="15" />
                  </button>
                  <div class="h-7.5 px-3.5 -mx-1.5 bg-white dark:bg-[#273142] flex items-center justify-center z-0 shadow-xs border-0 select-none">
                    <span class="text-xs font-bold text-[#1E293B] dark:text-white tabular-nums text-center">
                      {{ getItemQuantity(item.id) }}
                    </span>
                  </div>
                  <button
                    type="button"
                    @click.stop="handleQuickAdd($event, item)"
                    class="w-7.5 h-7.5 rounded-lg bg-[#4880FF] hover:bg-[#3971F0] text-white flex items-center justify-center shadow-xs active:scale-90 transition-all cursor-pointer shrink-0 z-10"
                    title="Tambah"
                  >
                    <AppIcon name="add" :size="18" />
                  </button>
                </div>

                <!-- State 1: Button Plus Tunggal jika qty === 0 -->
                <button
                  v-else
                  type="button"
                  @click.stop="handleQuickAdd($event, item)"
                  class="w-7.5 h-7.5 rounded-lg bg-[#4880FF] hover:bg-[#3971F0] text-white flex items-center justify-center shadow-xs active:scale-90 transition-all cursor-pointer shrink-0"
                  title="Tambah ke Keranjang"
                >
                  <AppIcon name="add" :size="18" />
                </button>
              </div>
              <span v-else class="text-[11px] font-semibold text-rose-500 bg-rose-50 dark:bg-rose-950/30 px-2 py-0.5 rounded">
                Habis
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODE 2: TAMPILAN NORMAL (SEMUA KATEGORI & 2-COLUMN GRID) -->
    <template v-else>
      <!-- SKELETON LOADING MODE (Bawaan Tailwind CSS animate-pulse) -->
      <template v-if="isLoading">
        <!-- Sticky Category Tabs Skeleton -->
        <div class="sticky top-16 z-20 -mt-4 -mx-4 px-4 pt-3 pb-4 bg-white dark:bg-[#273142] flex items-center gap-3 overflow-x-hidden">
          <div v-for="n in 5" :key="n" class="h-8 w-24 rounded-full bg-[#E2E8F0] dark:bg-[#334155] animate-pulse shrink-0" />
        </div>



        <!-- 2-Column Menu Grid Skeleton (6 Card Item Menu Placeholder) -->
        <div class="grid grid-cols-2 gap-x-4 gap-y-8">
          <div v-for="n in 6" :key="n" class="flex flex-col space-y-3">
            <!-- 1:1 Aspect Ratio Food Image Skeleton -->
            <div class="aspect-square w-full rounded-2xl bg-[#E2E8F0] dark:bg-[#334155] animate-pulse border border-[#E2E8F0]/70 dark:border-[#334155]/60" />
            <!-- Food Title & Price Skeleton (Design System pt-3) -->
            <div class="pt-3 pl-1 space-y-2">
              <div class="h-4 w-4/5 rounded-md bg-[#E2E8F0] dark:bg-[#334155] animate-pulse" />
              <div class="h-3 w-3/5 rounded-md bg-[#E2E8F0] dark:bg-[#334155] animate-pulse mt-1" />
              <div class="h-4 w-1/2 rounded-md bg-[#E2E8F0] dark:bg-[#334155] animate-pulse mt-2" />
            </div>
          </div>
        </div>
      </template>

      <!-- REAL DATA MODE (Saat Data Selesai Dimuat) -->
      <template v-else>
        <!-- Category Filter Tabs (Sticky Top dengan Downward Shadow & Rounded Bottom) -->
        <div
          ref="categoryBarRef"
          :class="[
            'sticky top-16 z-20 -mt-4 -mx-4 px-4 pt-3 pb-4 bg-white dark:bg-[#273142] flex items-center gap-4 overflow-x-auto no-scrollbar transition-all duration-200',
            isScrolled ? 'shadow-[0_8px_20px_-4px_rgba(0,0,0,0.08)] dark:shadow-[0_8px_20px_-4px_rgba(0,0,0,0.5)]' : ''
          ]"
        >
          <button
            type="button"
            :data-active="selectedCategoryId === 'all'"
            @click="selectCategory('all', $event)"
            :class="[
              'text-sm sm:text-base font-semibold whitespace-nowrap transition-colors duration-150 cursor-pointer py-1 relative capitalize shrink-0',
              selectedCategoryId === 'all'
                ? 'text-[#4880FF]'
                : 'text-[#64748B] dark:text-[#94A3B8] hover:text-[#4880FF] dark:hover:text-[#4880FF]'
            ]"
          >
            <span>{{ t('menu.allCategories') }}</span>
            <div
              v-if="selectedCategoryId === 'all'"
              class="absolute bottom-0 left-0 right-0 h-0.5 bg-[#4880FF] rounded-full"
            />
          </button>

          <button
            v-for="cat in categories"
            :key="cat.id"
            type="button"
            :data-active="selectedCategoryId === cat.id"
            @click="selectCategory(cat.id, $event)"
            :class="[
              'text-sm sm:text-base font-semibold whitespace-nowrap transition-colors duration-150 cursor-pointer py-1 relative capitalize shrink-0',
              selectedCategoryId === cat.id
                ? 'text-[#4880FF]'
                : 'text-[#64748B] dark:text-[#94A3B8] hover:text-[#4880FF] dark:hover:text-[#4880FF]'
            ]"
          >
            <span>{{ formatTitleCase(cat.name) }}</span>
            <div
              v-if="selectedCategoryId === cat.id"
              class="absolute bottom-0 left-0 right-0 h-0.5 bg-[#4880FF] rounded-full"
            />
          </button>
        </div>



        <!-- Empty State with empty-menu.svg -->
        <AppEmptyState
          v-if="filteredItems.length === 0"
          svgType="menu"
          :title="t('menu.emptyTitle', 'Menu Belum Tersedia')"
          :description="t('menu.emptyDesc', 'Belum ada menu yang tersedia untuk kategori ini.')"
          :actionLabel="t('menu.allCategories', 'Semua Menu')"
          actionIcon="restaurant_menu"
          @action="selectCategory('all')"
        />

        <!-- 2-Column Menu Grid -->
        <div v-else class="grid grid-cols-2 gap-x-4 gap-y-8">
          <div
            v-for="item in filteredItems"
            :key="item.id"
            @click="openDetail(item)"
            class="flex flex-col cursor-pointer select-none"
          >
            <!-- Square Food Image Container (1:1 Aspect Ratio) -->
            <div class="aspect-square w-full rounded-2xl overflow-hidden relative bg-[#F8FAFC] dark:bg-[#1E293B] border border-[#E2E8F0]/70 dark:border-[#334155]/60 shadow-2xs">
              <img
                :src="item.imageUrl || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=500&auto=format&fit=crop&q=80'"
                :alt="item.name"
                class="w-full h-full object-cover"
              />

              <!-- "Habis" Dark Overlay (Centered) -->
              <div
                v-if="!item.isAvailable"
                class="absolute inset-0 bg-black/50 backdrop-blur-[1px] flex items-center justify-center pointer-events-none"
              >
                <AppBadge variant="danger" solid size="sm">Habis</AppBadge>
              </div>

              <!-- Quick Add & Stepper in Bottom-Right Corner of Image -->
              <!-- State 1: Hanya Button (+) jika belum dipilih (qty === 0) -->
              <button
                v-if="item.isAvailable && getItemQuantity(item.id) === 0"
                type="button"
                @click.stop="handleQuickAdd($event, item)"
                class="absolute bottom-2.5 right-2.5 w-9 h-9 rounded-xl bg-[#4880FF] hover:bg-[#3971F0] text-white flex items-center justify-center shadow-md active:scale-90 transition-all cursor-pointer z-10"
                title="Tambah ke Keranjang"
              >
                <AppIcon name="add" :size="22" />
              </button>

              <!-- State 2: Terhubung Sesuai Gambar Sketsa (Tinggi Pixel-Perfect h-9, Jarak Lega px-5) -->
              <div
                v-else-if="item.isAvailable && getItemQuantity(item.id) > 0"
                @click.stop
                class="absolute bottom-2.5 right-2.5 z-10 flex items-center"
              >
                <!-- Button Minus: Kotak Rounded-xl Utuh (w-9 h-9) Outlined Border Biru -->
                <button
                  type="button"
                  @click.stop="handleQuickRemove($event, item)"
                  class="w-9 h-9 rounded-xl border-2 border-[#4880FF] text-[#4880FF] bg-white dark:bg-[#273142] hover:bg-[#4880FF]/10 active:scale-90 flex items-center justify-center shadow-md transition-all cursor-pointer shrink-0 z-10"
                  title="Kurangi"
                >
                  <AppIcon name="remove" :size="20" />
                </button>

                <!-- Strip Tengah Penghubung: Background Putih FIT Tinggi Sama Rata (h-9) & Jarak Lega (px-5) -->
                <div
                  class="h-9 px-5 -mx-2.5 bg-white dark:bg-[#273142] flex items-center justify-center z-0 shadow-xs border-0 select-none"
                >
                  <span class="text-sm font-bold text-[#1E293B] dark:text-white tabular-nums tracking-wider text-center">
                    {{ getItemQuantity(item.id) }}
                  </span>
                </div>

                <!-- Button Plus: Kotak Rounded-xl Utuh Solid Biru (w-9 h-9, Posisi & Ukuran Tetap Sama) -->
                <button
                  type="button"
                  @click.stop="handleQuickAdd($event, item)"
                  class="w-9 h-9 rounded-xl bg-[#4880FF] hover:bg-[#3971F0] text-white flex items-center justify-center shadow-md active:scale-90 transition-all cursor-pointer shrink-0 z-10"
                  title="Tambah"
                >
                  <AppIcon name="add" :size="22" />
                </button>
              </div>
            </div>

            <!-- Food Details: Title & Price Below Image (Image ke Title pt-3) -->
            <div class="pt-3 pl-1 flex flex-col flex-1 text-left">
              <h3 class="text-sm sm:text-base font-bold text-[#1E293B] dark:text-white line-clamp-2 leading-snug">
                {{ formatTitleCase(item.name) }}
              </h3>
              <p v-if="item.description" class="text-xs text-[#64748B] dark:text-[#94A3B8] line-clamp-1 mt-1 leading-snug font-medium" :title="item.description">
                {{ item.description }}
              </p>
              <p class="text-sm sm:text-base font-bold text-[#1E293B] dark:text-white mt-2 tabular-nums">
                {{ formatCurrency(item.price) }}
              </p>
            </div>
          </div>
        </div>
      </template>
    </template>

  <!-- BOTTOM SHEET DETAIL MODAL (Muncul dari bawah, animasi bounce, latar belakang gelap) -->
  <Teleport to="body">
    <!-- Backdrop Hitam Gelap (Transisi Halus) -->
    <Transition :name="isClosingByDrag ? '' : 'fade'">
      <div
        v-if="isDetailModalOpen"
        @click="closeDetailModal"
        @touchmove.prevent
        :class="[
          'fixed inset-0 bg-black/60 z-50 cursor-pointer touch-none transition-opacity duration-240 ease-in-out',
          isClosingByDrag ? 'opacity-0 pointer-events-none' : 'opacity-100'
        ]"
      />
    </Transition>

    <!-- Bottom Sheet Modal Container (Seluruh modal bisa di-drag ke bawah untuk menutup) -->
    <Transition :name="isClosingByDrag ? '' : 'sheet-modal'">
      <div
        ref="modalRef"
        v-if="isDetailModalOpen && selectedItem"
        @touchstart="onModalTouchStart"
        @touchmove="onModalTouchMove"
        @touchend="onModalTouchEnd"
        @touchcancel="onModalTouchEnd"
        @pointerdown="onModalPointerDown"
        :style="isClosingByDrag ? {
          transform: 'translateY(100%)',
          transition: 'transform 240ms cubic-bezier(0.25, 1, 0.5, 1)'
        } : (isDragging || dragTranslateY > 0) ? {
          transform: `translateY(${dragTranslateY}px)`,
          transition: isDragging ? 'none' : 'transform 240ms cubic-bezier(0.25, 1, 0.5, 1)'
        } : undefined"
        class="fixed inset-x-0 bottom-0 w-full md:max-w-md mx-auto z-50 bg-white dark:bg-[#273142] rounded-t-3xl max-h-[88vh] flex flex-col shadow-[0_-8px_30px_rgba(0,0,0,0.18)] border-t border-[#E2E8F0] dark:border-[#334155] select-none overscroll-contain transform-gpu"
      >
        <!-- Top Drag Indicator (Simetris 16px dengan padding kiri-kanan px-4) -->
        <div
          class="pt-1.5 pb-1.5 shrink-0 flex items-center justify-center cursor-grab active:cursor-grabbing select-none w-full"
          title="Tarik ke bawah untuk menutup modal"
        >
          <div class="w-12 h-1 bg-[#CBD5E1] dark:bg-[#475569] rounded-full pointer-events-none" />
        </div>

        <!-- Scrollable Modal Content -->
        <div ref="modalScrollRef" class="overflow-y-auto px-4 pb-4 flex-1 space-y-4 overscroll-contain">
          <!-- Big Food Image (Square Rounded-2xl Sesuai Gambar Referensi 1:1) -->
          <div class="w-full aspect-square rounded-2xl overflow-hidden relative bg-[#F8FAFC] dark:bg-[#1E293B] border border-[#E2E8F0]/70 dark:border-[#334155]/60 shadow-2xs">
            <img
              :src="selectedItem.imageUrl || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=500&auto=format&fit=crop&q=80'"
              :alt="selectedItem.name"
              class="w-full h-full object-cover"
            />
            <div
              v-if="!selectedItem.isAvailable"
              class="absolute inset-0 bg-black/60 flex items-center justify-center pointer-events-none"
            >
              <AppBadge variant="danger" solid size="md">Habis</AppBadge>
            </div>
          </div>

          <!-- Title & Description -->
          <div>
            <h2 class="text-lg sm:text-xl font-extrabold text-[#1E293B] dark:text-white leading-tight">
              {{ formatTitleCase(selectedItem.name) }}
            </h2>
            <p v-if="selectedItem.description" class="text-xs sm:text-sm text-[#64748B] dark:text-[#94A3B8] mt-2 leading-relaxed font-medium">
              {{ selectedItem.description }}
            </p>
          </div>

          <!-- Variant Groups (Jika Menu Memiliki Pilihan Opsi) -->
          <div v-if="selectedItem.variantGroups && selectedItem.variantGroups.length > 0" class="space-y-4 pt-2">
            <div v-for="group in selectedItem.variantGroups" :key="group.id" class="space-y-2">
              <div class="flex items-center justify-between">
                <h4 class="text-xs sm:text-sm font-bold text-[#1E293B] dark:text-white">
                  {{ group.name }}
                </h4>
                <span class="text-[11px] font-semibold text-[#64748B] dark:text-[#94A3B8]">
                  {{ group.type === 'single' ? 'Pilih 1' : 'Bisa pilih lebih dari 1' }}
                </span>
              </div>

              <!-- Single Choice (Radio Pill) -->
              <div v-if="group.type === 'single'" class="grid grid-cols-2 gap-2">
                <button
                  v-for="opt in group.options"
                  :key="opt.id"
                  type="button"
                  @click="selectedSingle[group.id] = opt.id"
                  :class="[
                    'p-3 rounded-xl border text-xs font-semibold flex items-center justify-between transition-all cursor-pointer',
                    selectedSingle[group.id] === opt.id
                      ? 'border-[#4880FF] bg-[#4880FF]/10 text-[#4880FF] ring-1 ring-[#4880FF]'
                      : 'border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#1E293B] text-[#1E293B] dark:text-white hover:border-[#4880FF]/50'
                  ]"
                >
                  <span class="truncate">{{ opt.name }}</span>
                  <span class="text-[11px] font-bold shrink-0 ml-1">
                    {{ opt.priceModifier > 0 ? `+${formatCurrency(opt.priceModifier)}` : 'Termasuk' }}
                  </span>
                </button>
              </div>

              <!-- Multi Choice (Checkbox Pill) -->
              <div v-else class="grid grid-cols-2 gap-2">
                <button
                  v-for="opt in group.options"
                  :key="opt.id"
                  type="button"
                  @click="toggleMultiOption(group.id, opt.id)"
                  :class="[
                    'p-3 rounded-xl border text-xs font-semibold flex items-center justify-between transition-all cursor-pointer',
                    (selectedMulti[group.id] || []).includes(opt.id)
                      ? 'border-[#4880FF] bg-[#4880FF]/10 text-[#4880FF] ring-1 ring-[#4880FF]'
                      : 'border-[#E2E8F0] dark:border-[#334155] bg-white dark:bg-[#1E293B] text-[#1E293B] dark:text-white hover:border-[#4880FF]/50'
                  ]"
                >
                  <span class="truncate">{{ opt.name }}</span>
                  <span class="text-[11px] font-bold shrink-0 ml-1">
                    {{ opt.priceModifier > 0 ? `+${formatCurrency(opt.priceModifier)}` : '' }}
                  </span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Bottom Sticky Actions: Row 1 (Harga & Counter Qty Sejajar Seimbang), Row 2 (Tombol Full Width) -->
        <div class="p-4 pt-2 bg-white dark:bg-[#273142] flex flex-col gap-4 shrink-0">
          <!-- Row 1: Harga & Counter Qty Sejajar Seimbang -->
          <div class="flex items-center justify-between">
            <span class="text-lg sm:text-xl font-bold text-[#1E293B] dark:text-white tabular-nums tracking-tight">
              {{ formatCurrency(modalSubtotal) }}
            </span>

            <!-- Counter Qty Stepper (- 1 +) Sesuai Gaya Keranjang Pesanan -->
            <div class="flex items-center gap-2 shrink-0">
              <button
                type="button"
                @click="modalQuantity = Math.max(1, modalQuantity - 1)"
                class="w-7 h-7 rounded-full bg-[#F1F4F9] dark:bg-[#323D4E] shadow-xs flex items-center justify-center text-[#202224] dark:text-white hover:bg-[#E2E8F0] dark:hover:bg-[#3B4758] transition-all cursor-pointer active:scale-90"
                title="Kurangi"
              >
                <AppIcon name="remove" :size="15" />
              </button>
              <span class="text-sm font-bold text-[#202224] dark:text-white px-1 text-center tabular-nums min-w-[14px]">
                {{ modalQuantity }}
              </span>
              <button
                type="button"
                @click="modalQuantity++"
                class="w-7 h-7 rounded-full bg-[#4880FF] hover:bg-[#3971F0] text-white shadow-xs flex items-center justify-center transition-all cursor-pointer active:scale-90"
                title="Tambah"
              >
                <AppIcon name="add" :size="15" />
              </button>
            </div>
          </div>

          <!-- Row 2: Tombol Full Width -->
          <button
            v-if="!cartStore.hasPendingOrder"
            type="button"
            :disabled="!selectedItem.isAvailable"
            @click="handleAddToCartFromModal"
            class="w-full h-11.5 rounded-full bg-[#4880FF] hover:bg-[#3971F0] disabled:opacity-50 text-white font-bold text-sm sm:text-base flex items-center justify-center gap-2 shadow-md shadow-[#4880FF]/25 active:scale-[0.99] transition-all cursor-pointer"
          >
            <AppIcon name="shopping_bag" :size="19" />
            <span>Tambah Pesanan</span>
          </button>
          <button
            v-else
            type="button"
            @click="handleAddToCartFromModal"
            class="w-full h-11.5 rounded-full bg-amber-500 hover:bg-amber-600 text-white font-bold text-sm sm:text-base flex items-center justify-center gap-2 shadow-md shadow-amber-500/25 active:scale-[0.99] transition-all cursor-pointer"
          >
            <AppIcon name="schedule" :size="19" />
            <span>Lanjutkan Pembayaran</span>
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
  </div>
</template>

<style scoped>
/* Transisi Smooth Bottom Sheet Modal */
.sheet-modal-enter-active {
  transition: transform 380ms cubic-bezier(0.16, 1.18, 0.3, 1);
}
.sheet-modal-leave-active {
  transition: transform 240ms cubic-bezier(0.25, 1, 0.5, 1);
}
.sheet-modal-enter-from,
.sheet-modal-leave-to {
  transform: translateY(100%);
}

/* Transisi Backdrop */
.fade-enter-active {
  transition: opacity 300ms ease-out;
}
.fade-leave-active {
  transition: opacity 240ms ease-in-out;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
