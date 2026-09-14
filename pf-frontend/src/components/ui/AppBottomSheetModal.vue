<script setup lang="ts">
import { ref, watch, onBeforeUnmount, nextTick } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'

interface Props {
  modelValue?: boolean
  title?: string
  showCloseButton?: boolean
  height?: string
  maxWidth?: string
  teleportTo?: string | null
  scrollable?: boolean
  modalStyle?: Record<string, any> | string
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: false,
  title: '',
  showCloseButton: false,
  height: 'h-auto max-h-[92dvh] sm:max-h-[90dvh]',
  maxWidth: 'w-full md:max-w-md',
  teleportTo: 'body',
  scrollable: true,
  modalStyle: undefined
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'close'): void
}>()

const modalRef = ref<HTMLElement | null>(null)
const scrollBodyRef = ref<HTMLElement | null>(null)

// Drag gesture state
const isDragging = ref(false)
const isClosingByDrag = ref(false)
const dragTranslateY = ref(0)
let dragStartY = 0
let dragStartX = 0
let dragStartTime = 0
let isTrackingTouch = false

// Reset all drag states whenever modal opens or closes
const resetDragState = () => {
  isDragging.value = false
  isClosingByDrag.value = false
  dragTranslateY.value = 0
  isTrackingTouch = false
}

// Watch modelValue to always ensure clean state on open
watch(
  () => props.modelValue,
  (val) => {
    if (val) {
      resetDragState()
    }
  },
  { immediate: true }
)

const handleClose = () => {
  resetDragState()
  emit('update:modelValue', false)
  emit('close')
}

const finishDrag = () => {
  const timeElapsed = Date.now() - dragStartTime
  const velocity = dragTranslateY.value / Math.max(1, timeElapsed)

  // Dragged down past threshold or fast flick
  if (dragTranslateY.value > 70 || (velocity > 0.3 && dragTranslateY.value > 25)) {
    isDragging.value = false
    isClosingByDrag.value = true

    setTimeout(() => {
      emit('update:modelValue', false)
      emit('close')
      nextTick(() => {
        resetDragState()
      })
    }, 240)
  } else {
    // Snap back
    isDragging.value = false
    dragTranslateY.value = 0
  }
}

// Touch events for mobile
const onTouchStart = (e: TouchEvent) => {
  if (e.touches.length !== 1) return
  const target = e.target as HTMLElement | null
  const scrollable = scrollBodyRef.value

  // If body is scrollable and user is scrolled down, allow normal scrolling inside
  if (props.scrollable && scrollable && scrollable.contains(target) && scrollable.scrollTop > 0) {
    isTrackingTouch = false
    return
  }

  const touch = e.touches[0]
  dragStartY = touch.clientY
  dragStartX = touch.clientX
  dragStartTime = Date.now()
  isTrackingTouch = true
}

const onTouchMove = (e: TouchEvent) => {
  if (!isTrackingTouch || e.touches.length !== 1) return
  const touch = e.touches[0]
  const deltaY = touch.clientY - dragStartY
  const deltaX = touch.clientX - dragStartX

  // If body is scrollable and user has scrolled down, do not drag sheet
  if (props.scrollable) {
    const scrollable = scrollBodyRef.value
    if (scrollable && scrollable.scrollTop > 0) {
      return
    }
  }

  if (isDragging.value) {
    dragTranslateY.value = Math.max(0, deltaY)
    if (e.cancelable) e.preventDefault()
    return
  }

  // If mostly horizontal movement, cancel touch tracking
  if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 8) {
    isTrackingTouch = false
    return
  }

  // Drag downward initiates sheet dismissal
  if (deltaY > 6) {
    isDragging.value = true
    dragTranslateY.value = Math.max(0, deltaY)
    if (e.cancelable) e.preventDefault()
  }
}

const onTouchEnd = (e: TouchEvent) => {
  if (!isTrackingTouch) return
  isTrackingTouch = false

  if (isDragging.value) {
    e.stopPropagation()
    finishDrag()
  }
}

// Pointer events for desktop / mouse drag
const onPointerDown = (e: PointerEvent) => {
  if (e.button !== 0) return
  const target = e.target as HTMLElement | null
  if (target && target.closest('button, input, textarea, a, label')) return

  if (props.scrollable) {
    const scrollable = scrollBodyRef.value
    if (scrollable && scrollable.contains(target) && scrollable.scrollTop > 0) {
      return
    }
  }

  dragStartY = e.clientY
  dragStartX = e.clientX
  dragStartTime = Date.now()

  const onPointerMove = (pe: PointerEvent) => {
    const deltaY = pe.clientY - dragStartY
    const deltaX = pe.clientX - dragStartX

    if (props.scrollable) {
      const scrollable = scrollBodyRef.value
      if (scrollable && scrollable.scrollTop > 0) {
        return
      }
    }

    if (isDragging.value) {
      dragTranslateY.value = Math.max(0, deltaY)
      return
    }

    if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 8) {
      cleanupPointer()
      return
    }

    if (deltaY > 6) {
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

onBeforeUnmount(() => {
  resetDragState()
})

defineExpose({
  scrollBodyRef,
  modalRef,
  resetDragState
})
</script>

<template>
  <component :is="teleportTo ? 'teleport' : 'div'" :to="teleportTo || undefined">
    <!-- Backdrop Overlay -->
    <Transition :name="isClosingByDrag ? '' : 'fade'">
      <div
        v-if="modelValue"
        @click="handleClose"
        @touchmove.prevent
        :class="[
          'fixed inset-0 bg-black/60 z-50 cursor-pointer touch-none transition-opacity duration-240 ease-in-out',
          isClosingByDrag ? 'opacity-0 pointer-events-none' : 'opacity-100'
        ]"
      />
    </Transition>

    <!-- Draggable Modal Container (Drag-to-Close aktif di seluruh area jika dropdown tidak aktif) -->
    <Transition :name="isClosingByDrag ? '' : 'sheet-modal'">
      <div
        ref="modalRef"
        v-if="modelValue"
        @touchstart="onTouchStart"
        @touchmove="onTouchMove"
        @touchend="onTouchEnd"
        @touchcancel="onTouchEnd"
        @pointerdown="onPointerDown"
        :style="[
          modalStyle,
          isClosingByDrag ? {
            transform: 'translateY(100%)',
            transition: 'transform 240ms cubic-bezier(0.25, 1, 0.5, 1)'
          } : (isDragging || dragTranslateY > 0) ? {
            transform: `translateY(${dragTranslateY}px)`,
            transition: isDragging ? 'none' : 'transform 240ms cubic-bezier(0.25, 1, 0.5, 1)'
          } : undefined
        ]"
        :class="[
          'fixed inset-x-0 bottom-0 mx-auto z-50 bg-white dark:bg-[#273142] rounded-t-3xl shadow-2xl select-none overscroll-contain transform-gpu flex flex-col',
          maxWidth,
          height
        ]"
      >
        <!-- 1. FIXED HEADER (TANPA HORIZONTAL LINE PEMBATAS & TANPA BUTTON X SECARA DEFAULT) -->
        <div class="px-5 pt-3 pb-2 shrink-0 select-none bg-white dark:bg-[#273142] rounded-t-3xl z-10">
          <!-- Top Drag Handle -->
          <div
            class="pt-1 pb-3 shrink-0 flex items-center justify-center cursor-grab active:cursor-grabbing select-none w-full"
            title="Tarik ke bawah untuk menutup"
          >
            <div class="w-12 h-1 bg-[#CBD5E1] dark:bg-[#475569] rounded-full pointer-events-none" />
          </div>

          <!-- Header Content -->
          <slot name="header">
            <div class="flex items-center justify-between pb-1">
              <h3 class="text-lg font-bold text-[#1E293B] dark:text-white">
                {{ title }}
              </h3>
              <!-- Button X (Hanya muncul jika props.showCloseButton === true) -->
              <button
                v-if="showCloseButton"
                type="button"
                @click="handleClose"
                class="w-7 h-7 rounded-full bg-[#F1F4F9] dark:bg-[#323D4E] flex items-center justify-center text-[#64748B] dark:text-[#94A3B8] hover:text-[#1E293B] dark:hover:text-white transition-all cursor-pointer"
                title="Tutup"
              >
                <AppIcon name="close" :size="16" />
              </button>
            </div>
          </slot>
        </div>

        <!-- 2. INTERNAL BODY (SCROLLABLE HANYA JIKA PROPS.SCROLLABLE === TRUE) -->
        <div
          ref="scrollBodyRef"
          :class="[
            'flex-1 overscroll-contain transition-all no-scrollbar',
            scrollable ? 'overflow-y-auto' : 'overflow-hidden'
          ]"
        >
          <slot />
        </div>

        <!-- 3. FIXED BOTTOM FOOTER / ACTION BAR -->
        <div v-if="$slots.footer" class="shrink-0 z-10">
          <slot name="footer" />
        </div>
      </div>
    </Transition>
  </component>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.24s ease-in-out;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
