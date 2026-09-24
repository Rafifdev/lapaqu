import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import type { CartItem, MenuItem, SelectedOption } from '@/types'

const STORAGE_KEY = 'lapaqu_pos_cart_state'

function loadSavedState() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (raw) {
      const parsed = JSON.parse(raw)
      if (Array.isArray(parsed?.items)) {
        parsed.items = parsed.items.map((it: any) => {
          if (!it.menuItem) {
            it.menuItem = {
              id: it.id || it.menu_item_id || 'item',
              name: it.name || it.item_name || 'Menu',
              price: Number(it.price || it.unitPrice || 0),
              imageUrl: it.imageUrl || it.image_url || '',
            }
          }
          return it
        })
      }
      return parsed
    }
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

export function isPendingOrderExpired(order: PendingOrder | null): boolean {
  if (!order || !order.id) return true
  if (order.expires_at) {
    return new Date(order.expires_at).getTime() <= Date.now()
  }
  if (order.created_at) {
    const isVA = order.payment_method?.startsWith('va_')
    const maxAgeMs = isVA ? 24 * 60 * 60 * 1000 : 15 * 60 * 1000
    return (Date.now() - new Date(order.created_at).getTime()) > maxAgeMs
  }
  return false
}

function loadSavedPendingOrder(): PendingOrder | null {
  try {
    const raw = localStorage.getItem(PENDING_STORAGE_KEY)
    if (raw) {
      const parsed = JSON.parse(raw)
      if (isPendingOrderExpired(parsed)) {
        localStorage.removeItem(PENDING_STORAGE_KEY)
        return null
      }
      return parsed
    }
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

  const clearPendingOrder = () => {
    pendingOrder.value = null
    try {
      localStorage.removeItem(PENDING_STORAGE_KEY)
    } catch (e) {}
  }

  const hasPendingOrder = computed(() => {
    if (!pendingOrder.value?.id) return false
    if (isPendingOrderExpired(pendingOrder.value)) {
      clearPendingOrder()
      return false
    }
    return true
  })

  const addItem = (menuItem: MenuItem, quantity = 1, selectedOptions: SelectedOption[] = [], notes = '') => {
    if (hasPendingOrder.value) {
      if (isPendingOrderExpired(pendingOrder.value)) {
        clearPendingOrder()
      } else {
        return
      }
    }
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
    if (hasPendingOrder.value) {
      if (isPendingOrderExpired(pendingOrder.value)) {
        clearPendingOrder()
      } else {
        return
      }
    }
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
    if (hasPendingOrder.value) {
      if (isPendingOrderExpired(pendingOrder.value)) {
        clearPendingOrder()
      } else {
        return
      }
    }
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

  const clearActiveOrderId = () => {
    try {
      localStorage.removeItem('lapaqu_active_order_id')
    } catch (e) {}
  }

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
