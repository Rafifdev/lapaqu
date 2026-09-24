<script setup lang="ts">
import { ref, computed, onMounted, onActivated, onUnmounted } from 'vue'
import { Motion, AnimatePresence } from 'motion-v'
import { Plus } from 'lucide-vue-next'
import AppMenuCard from '@/components/ui/AppMenuCard.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppTextarea from '@/components/ui/AppTextarea.vue'
import AppSelect from '@/components/ui/AppSelect.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { usePosStore } from '@/stores/pos'
import emptyMenuIllustration from '@/assets/empty_state/empty-menu.svg'
import type { MenuItem } from '@/types'

const posStore = usePosStore()

const isLoading = ref(posStore.menuItems.length === 0)
const isSubmitting = ref(false)
const searchQuery = ref('')
const selectedCategory = ref('all')

// Fetch real dynamic categories & menu items from backend API (Instant 0ms render on revisit)
onMounted(async () => {
  if (posStore.menuItems.length > 0) {
    isLoading.value = false
    Promise.all([
      posStore.fetchCategories(),
      posStore.fetchMenuItems()
    ])
  } else {
    isLoading.value = true
    try {
      await Promise.all([
        posStore.fetchCategories(),
        posStore.fetchMenuItems()
      ])
    } catch (err: any) {
      console.error('Failed to load menu data:', err)
    } finally {
      isLoading.value = false
    }
  }
})

onActivated(async () => {
  await Promise.all([
    posStore.fetchCategories(),
    posStore.fetchMenuItems()
  ])
})

const categories = computed(() => {
  return posStore.categories
})

const categoryOptions = computed(() => {
  return categories.value.map(cat => ({
    value: cat.id,
    label: cat.name
  }))
})

const items = computed(() => {
  return posStore.menuItems
})

const filteredItems = computed(() => {
  return items.value.filter(i => {
    const matchCat = selectedCategory.value === 'all' || i.categoryId === selectedCategory.value
    const matchSearch = i.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (i.description && i.description.toLowerCase().includes(searchQuery.value.toLowerCase()))
    return matchCat && matchSearch
  })
})

const getCategoryItemCount = (catId: string) => {
  return items.value.filter(i => i.categoryId === catId).length
}

// Add / Edit Modal State
const isModalOpen = ref(false)
const isEditing = ref(false)
const editingId = ref<string | null>(null)

const formName = ref('')
const formPrice = ref(25000)
const formCategoryId = ref('')
const formDescription = ref('')
const modalErrorMessage = ref('')

// Delete Confirm Modal State
const isDeleteModalOpen = ref(false)
const itemToDelete = ref<MenuItem | null>(null)
const isDeleting = ref(false)

// Action Feedback Overlay
const isFeedbackActive = ref(false)
const feedbackMessage = ref('Menu berhasil dibuat')
const feedbackIcon = ref('check_circle')
const feedbackColor = ref('text-[#00B69B]')
let feedbackTimer: any = null

const triggerActionFeedback = (message: string, icon: 'check_circle' | 'delete' = 'check_circle') => {
  feedbackMessage.value = message
  feedbackIcon.value = icon
  feedbackColor.value = icon === 'delete' ? 'text-rose-500' : 'text-[#00B69B]'
  isFeedbackActive.value = true
  if (feedbackTimer) clearTimeout(feedbackTimer)
  feedbackTimer = setTimeout(() => {
    isFeedbackActive.value = false
  }, 1300)
}

onUnmounted(() => {
  if (feedbackTimer) clearTimeout(feedbackTimer)
})

const openAdd = () => {
  isEditing.value = false
  editingId.value = null
  formName.value = ''
  formPrice.value = 25000
  formCategoryId.value = categories.value[0]?.id || ''
  formDescription.value = ''
  modalErrorMessage.value = ''
  isModalOpen.value = true
}

const openEdit = (item: MenuItem) => {
  isEditing.value = true
  editingId.value = item.id
  formName.value = item.name
  formPrice.value = item.price
  formCategoryId.value = item.categoryId
  formDescription.value = item.description || ''
  modalErrorMessage.value = ''
  isModalOpen.value = true
}

const saveItem = async () => {
  if (!formName.value.trim() || !formCategoryId.value || isSubmitting.value) return

  modalErrorMessage.value = ''
  isSubmitting.value = true

  const priceVal = Number(formPrice.value) || 0

  try {
    if (isEditing.value && editingId.value) {
      await posStore.updateMenuItem(editingId.value, {
        name: formName.value.trim(),
        price: priceVal,
        category_id: formCategoryId.value,
        description: formDescription.value.trim(),
      })
      isModalOpen.value = false
      triggerActionFeedback('Menu berhasil diubah', 'check_circle')
    } else {
      await posStore.createMenuItem({
        name: formName.value.trim(),
        price: priceVal,
        category_id: formCategoryId.value,
        description: formDescription.value.trim(),
        image_url: 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=500&auto=format&fit=crop&q=80',
      })
      isModalOpen.value = false
      triggerActionFeedback('Menu berhasil dibuat', 'check_circle')
    }
  } catch (err: any) {
    console.error('Error saving menu item:', err)
    modalErrorMessage.value = err?.response?.data?.message || err?.message || 'Gagal menyimpan menu.'
  } finally {
    isSubmitting.value = false
  }
}


const confirmDelete = (item: MenuItem) => {
  itemToDelete.value = item
  isDeleteModalOpen.value = true
}

const executeDelete = async () => {
  if (!itemToDelete.value || isDeleting.value) return

  isDeleting.value = true
  try {
    await posStore.deleteMenuItem(itemToDelete.value.id)
    isDeleteModalOpen.value = false
    itemToDelete.value = null
    triggerActionFeedback('Menu berhasil dihapus', 'delete')
  } catch (err: any) {
    console.error('Error deleting menu item:', err)
  } finally {
    isDeleting.value = false
  }
}
</script>

<template>
  <div class="space-y-6 pb-12">
    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-[#202224] dark:text-white">
          Daftar Menu Makanan
        </h1>
      </div>

      <div class="flex items-center gap-2 shrink-0">
        <div v-if="isLoading" class="h-10 w-44 bg-[#E2E8F0] dark:bg-[#334155] rounded-xl animate-pulse"></div>
        <AppButton v-else @click="openAdd" variant="primary" size="md" icon="add_circle" class="!rounded-lg !font-bold shrink-0">
          Tambah Menu Baru
        </AppButton>
      </div>
    </div>

    <!-- Category Filter Card Wrapper -->
    <div class="bg-white dark:bg-[#273142] p-3 rounded-2xl shadow-xs shrink-0">
      <!-- Category Skeletons -->
      <div v-if="isLoading" class="flex items-center gap-3 overflow-x-auto no-scrollbar p-1 animate-pulse">
        <div v-for="n in 6" :key="n" class="h-10 w-32 bg-[#E2E8F0] dark:bg-[#334155] rounded-xl shrink-0"></div>
      </div>

      <!-- Real Categories Pills -->
      <div v-else class="flex items-center gap-3 overflow-x-auto no-scrollbar p-1">
        <!-- All Menu Pill -->
        <button type="button" @click="selectedCategory = 'all'" :class="[
          'px-5 py-2.5 rounded-xl text-sm font-bold whitespace-nowrap transition-colors cursor-pointer border',
          selectedCategory === 'all'
            ? 'border-[#4880FF] text-[#4880FF] bg-white dark:bg-[#273142] ring-1 ring-[#4880FF]'
            : 'border-[#EAEAEA] dark:border-[#313D4F] bg-white dark:bg-[#273142] text-[#4A5568] dark:text-[#94A3B8] hover:text-[#4880FF] hover:border-[#4880FF]'
        ]">
          Semua Menu ({{ items.length }})
        </button>

        <!-- Dynamic Category Pills from Real DB -->
        <button v-for="cat in categories" :key="cat.id" type="button" @click="selectedCategory = cat.id" :class="[
          'px-5 py-2.5 rounded-xl text-sm font-bold whitespace-nowrap transition-colors cursor-pointer border',
          selectedCategory === cat.id
            ? 'border-[#4880FF] text-[#4880FF] bg-white dark:bg-[#273142] ring-1 ring-[#4880FF]'
            : 'border-[#EAEAEA] dark:border-[#313D4F] bg-white dark:bg-[#273142] text-[#4A5568] dark:text-[#94A3B8] hover:text-[#4880FF] hover:border-[#4880FF]'
        ]">
          {{ cat.name }} ({{ getCategoryItemCount(cat.id) }})
        </button>
      </div>
    </div>

    <!-- 1. Skeleton Loading Grid (Leveraging AppMenuCard Built-in Skeleton) -->
    <div v-if="isLoading"
      class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-4">
      <AppMenuCard v-for="n in 6" :key="n" loading />
    </div>

    <!-- 2. Real Food Menu Cards Grid from Backend Database -->
    <div v-else-if="filteredItems.length > 0"
      class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-4">
      <AppMenuCard v-for="item in filteredItems" :key="item.id" :item="item" :showStepper="false"
        @click="openEdit(item)">

        <!-- Bottom-Right Actions (Edit & Delete Buttons) -->
        <template #actions>
          <div class="flex items-center gap-1.5">
            <!-- Edit Button -->
            <button type="button" @click.stop="openEdit(item)"
              class="w-7 h-7 rounded-full bg-[#F1F4F9] dark:bg-[#323D4E] shadow-xs text-[#64748B] dark:text-[#94A3B8] hover:text-[#4880FF] hover:border-[#4880FF] hover:bg-[#F8FAFC] dark:hover:bg-[#334155] flex items-center justify-center transition-all cursor-pointer shadow-2xs active:scale-90"
              title="Edit Menu">
              <AppIcon name="edit" :size="15" />
            </button>

            <!-- Delete Button -->
            <button type="button" @click.stop="confirmDelete(item)"
              class="w-7 h-7 rounded-full bg-[#F1F4F9] dark:bg-[#323D4E] shadow-xs text-[#64748B] dark:text-[#94A3B8] hover:text-rose-500 hover:border-rose-300 hover:bg-rose-50 dark:hover:bg-rose-950/30 flex items-center justify-center transition-all cursor-pointer shadow-2xs active:scale-90"
              title="Hapus Menu">
              <AppIcon name="delete" :size="15" />
            </button>
          </div>
        </template>
      </AppMenuCard>
    </div>

    <!-- 3. Empty State -->
    <div v-else class="min-h-[340px] md:min-h-[400px] flex flex-col items-center justify-center text-center px-4 py-8">
      <div class="relative flex items-center justify-center -mb-2 pointer-events-none">
        <img :src="emptyMenuIllustration" alt="Menu Tidak Ditemukan"
          class="w-48 h-48 sm:w-56 sm:h-56 md:w-60 md:h-60 object-contain drop-shadow-xs" />
      </div>
      <h3 class="text-xl sm:text-2xl font-black text-[#1E293B] dark:text-white tracking-tight">
        Whoops! :(
      </h3>
      <p class="text-sm font-medium text-[#64748B] dark:text-[#94A3B8] mt-1.5 max-w-sm leading-relaxed">
        Menu yang anda cari tidak ditemukan. Silahkan coba kata kunci lain atau pilih kategori berbeda.
      </p>
    </div>

    <!-- Modal Tambah / Edit Menu -->
    <AppModal v-model="isModalOpen" :title="isEditing ? 'Edit Menu' : 'Tambah Menu Baru'" maxWidth="md">
      <form @submit.prevent="saveItem" class="space-y-4 text-xs py-1">
        <div v-if="modalErrorMessage" class="p-3 bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900 rounded-xl text-xs text-rose-600 font-medium">
          {{ modalErrorMessage }}
        </div>

        <!-- input - Nama Menu (Full Width) -->
        <AppInput v-model="formName" label="Nama Menu" placeholder="Beef Black Pepper Rice Bowl" required />

        <!-- input - Deskripsi Menu (Full Width) -->
        <AppTextarea v-model="formDescription" label="Deskripsi Menu (opsional)"
          placeholder="Jelaskan rasa, komposisi bahan, atau keunikan hidangan..." :rows="3" />

        <!-- input - Kategori Menu (Full Width) -->
        <AppSelect v-model="formCategoryId" label="Kategori Menu" :options="categoryOptions"
          placeholder="Pilih Kategori Menu" required />

        <!-- input - Harga Jual (Full Width) -->
        <AppInput v-model.number="formPrice" type="number" label="Harga Jual" prefix="Rp" placeholder="25000" min="0"
          step="1000" required />
      </form>

      <template #footer>
        <div class="flex items-center justify-end gap-2.5">
          <AppButton type="button" @click="isModalOpen = false" variant="outline" size="md"
            class="!rounded-lg px-5 font-bold" :disabled="isSubmitting">
            Batal
          </AppButton>
          <AppButton type="button" @click="saveItem" variant="primary" size="md" class="!rounded-lg px-6 font-bold"
            :disabled="!formName.trim() || !formCategoryId || isSubmitting">
            {{ isSubmitting ? 'Menyimpan...' : (isEditing ? 'Simpan Perubahan' : 'Tambah Menu') }}
          </AppButton>
        </div>
      </template>
    </AppModal>

    <!-- Modal Konfirmasi Hapus Menu -->
    <AppModal v-model="isDeleteModalOpen" title="Hapus Menu Makanan" maxWidth="sm">
      <div class="space-y-3 text-sm">
        <p class="text-[#475569] dark:text-[#CBD5E1] leading-relaxed">
          Apakah Anda yakin ingin menghapus menu <strong class="text-[#1E293B] dark:text-white">{{ itemToDelete?.name
          }}</strong>? Tindakan ini tidak dapat dibatalkan.
        </p>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <AppButton @click="isDeleteModalOpen = false" variant="outline" size="md" class="font-bold" :disabled="isDeleting">
            Batal
          </AppButton>
          <AppButton @click="executeDelete" variant="danger" size="md" class="font-bold" :disabled="isDeleting">
            {{ isDeleting ? 'Menghapus...' : 'Ya, Hapus Menu' }}
          </AppButton>
        </div>
      </template>
    </AppModal>

    <!-- Action Feedback Overlay -->
    <AnimatePresence>
      <Motion v-if="isFeedbackActive" :initial="{ opacity: 0 }" :animate="{ opacity: 1 }" :exit="{ opacity: 0 }"
        :transition="{ duration: 0.18, ease: 'easeOut' }"
        class="fixed inset-0 z-[99999] flex items-center justify-center pointer-events-none bg-black/45 [transform:translateZ(0)]">
        <Motion :initial="{ scale: 0.4, rotate: -20, opacity: 0 }" :animate="{ scale: 1, rotate: 0, opacity: 1 }"
          :exit="{ scale: 0.8, opacity: 0 }" :transition="{ type: 'spring', damping: 14, stiffness: 260 }"
          class="flex flex-col items-center gap-3.5 [transform:translateZ(0)] select-none">
          <!-- Circle Icon Container -->
          <div :class="[
            'w-24 h-24 sm:w-28 sm:h-28 rounded-full bg-white dark:bg-[#1E293B] shadow-2xl border border-white/20 dark:border-[#334155] flex items-center justify-center',
            feedbackColor
          ]">
            <AppIcon :name="feedbackIcon" :size="feedbackIcon === 'delete' ? 56 : 64" />
          </div>

          <!-- Simple Explanation Text -->
          <div
            class="px-5 py-2 rounded-full bg-white/95 dark:bg-[#1E293B]/95 shadow-xl border border-white/20 dark:border-[#334155] text-[#1E293B] dark:text-white font-bold text-sm sm:text-base tracking-wide flex items-center justify-center">
            {{ feedbackMessage }}
          </div>
        </Motion>
      </Motion>
    </AnimatePresence>
  </div>
</template>
