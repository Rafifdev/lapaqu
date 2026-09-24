import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import type { CartItem, MenuItem, SelectedOption } from '@/types'

const STORAGE_KEY = 'lapaqu_pos_cart_state'

function loadSavedState() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (raw) return JSON.parse(raw)
  } catch (e) {
    console.error('Failed to load cart state from localStorage', e)
  }
  return null
}

export interface PendingOrder {
  id: string
  order_number: string
  total_amount: number
  total_items: number
  payment_method: string
  payment?: any
  order?: any
  qrisDataUrl?: string
  expires_at?: string
  created_at?: string
}

const PENDING_STORAGE_KEY = 'lapaqu_pending_order_state'

function loadSavedPendingOrder(): PendingOrder | null {
  try {
    const raw = localStorage.getItem(PENDING_STORAGE_KEY)
    if (raw) return JSON.parse(raw)
  } catch (e) {
    console.error('Failed to load pending order from localStorage', e)
  }
  return null
}

export const useCartStore = defineStore('cart', () => {
  const saved = loadSavedState()
  const pendingOrder = ref<PendingOrder | null>(loadSavedPendingOrder())

  const items = ref<CartItem[]>(saved?.items || [])
  const tableCode = ref<string>(saved?.tableCode || '-')
  const outletId = ref<string>(saved?.outletId || '')
  const activeSessionId = ref<string>(saved?.activeSessionId || '')
  const customerName = ref<string>(saved?.customerName || '')
  const customerPhone = ref<string>(saved?.customerPhone || '')
  const orderType = ref<'dine_in' | 'takeaway'>(saved?.orderType || 'dine_in')
  const searchQuery = ref<string>('')
  const isSearchOpen = ref<boolean>(false)

  watch(
    [items, tableCode, customerName, customerPhone, orderType],
    () => {
      try {
        localStorage.setItem(
          STORAGE_KEY,
          JSON.stringify({
            items: items.value,
            tableCode: tableCode.value,
            customerName: customerName.value,
            customerPhone: customerPhone.value,
            orderType: orderType.value,
            outletId: outletId.value,
            activeSessionId: activeSessionId.value,
          })
        )
      } catch (e) {
        console.error('Failed to save cart state to localStorage', e)
      }
    },
    { deep: true }
  )

  const totalItemsCount = computed(() => items.value.reduce((acc, it) => acc + it.quantity, 0))
  const totalPrice = computed(() => items.value.reduce((acc, it) => acc + it.subtotal, 0))

  const addItem = (menuItem: MenuItem, quantity = 1, selectedOptions: SelectedOption[] = [], notes = '') => {
    if (hasPendingOrder.value) return
    const optionsPrice = selectedOptions.reduce((acc, opt) => acc + opt.priceModifier, 0)
    const unitPrice = menuItem.price + optionsPrice
    const optionsKey = selectedOptions.map(o => o.optionId).sort().join('-')
    const cartItemId = `${menuItem.id}_${optionsKey}_${notes}`

    const existingIndex = items.value.findIndex(i => i.id === cartItemId)
    if (existingIndex > -1) {
      const existing = items.value[existingIndex]
      if (existing) {
        existing.quantity += quantity
        existing.subtotal = existing.quantity * existing.unitPrice
      }
    } else {
      items.value.push({
        id: cartItemId,
        menuItem,
        quantity,
        notes,
        selectedOptions,
        unitPrice,
        subtotal: unitPrice * quantity,
      })
    }
  }

  const updateQuantity = (cartItemId: string, delta: number) => {
    if (hasPendingOrder.value) return
    const idx = items.value.findIndex(i => i.id === cartItemId)
    if (idx === -1) return
    const item = items.value[idx]
    if (!item) return
    const newQty = item.quantity + delta
    if (newQty <= 0) {
      items.value.splice(idx, 1)
    } else {
      item.quantity = newQty
      item.subtotal = item.unitPrice * newQty
    }
  }

  const removeItem = (cartItemId: string) => {
    if (hasPendingOrder.value) return
    items.value = items.value.filter(i => i.id !== cartItemId)
  }

  const clearCart = () => {
    items.value = []
    tableCode.value = '-'
    customerName.value = ''
    orderType.value = 'dine_in'
    try {
      localStorage.removeItem(STORAGE_KEY)
    } catch (e) {}
  }

  const setPendingOrder = (orderData: PendingOrder) => {
    pendingOrder.value = orderData
    try {
      localStorage.setItem(PENDING_STORAGE_KEY, JSON.stringify(orderData))
    } catch (e) {}
  }

  const clearPendingOrder = () => {
    pendingOrder.value = null
    try {
      localStorage.removeItem(PENDING_STORAGE_KEY)
    } catch (e) {}
  }

  const clearActiveOrderId = () => {
    try {
      localStorage.removeItem('lapaqu_active_order_id')
    } catch (e) {}
  }

  const hasPendingOrder = computed(() => !!pendingOrder.value?.id)

  return {
    items,
    tableCode,
    outletId,
    activeSessionId,
    customerName,
    customerPhone,
    orderType,
    totalItemsCount,
    totalPrice,
    addItem,
    updateQuantity,
    removeItem,
    clearCart,
    searchQuery,
    isSearchOpen,
    pendingOrder,
    setPendingOrder,
    clearPendingOrder,
    clearActiveOrderId,
    hasPendingOrder,
  }
})
