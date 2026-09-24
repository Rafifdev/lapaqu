import { getEcho } from '@/services/echo'
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { Order, MenuItem, MenuCategory, TableItem, OrderStatus, Ingredient, MenuItemRecipe, IngredientCategory, IngredientStockLog, StockOpname, StockOpnameItem } from '@/types'
import { useAuthStore } from './auth'
import apiClient from '@/services/api'

export const usePosStore = defineStore('pos', () => {
  const authStore = useAuthStore()
  const isUuid = (str?: string) => Boolean(str && /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(str))

  // Dynamic state loaded strictly from API
  const orders = ref<Order[]>([])
  const pendingKdsItemUpdates = new Map<string, { status: string; timestamp: number }>()

  const setPendingKdsItem = (itemId: string, status: string) => {
    pendingKdsItemUpdates.set(itemId, { status, timestamp: Date.now() })
  }

  const getPendingKdsItemStatus = (itemId: string): string | null => {
    const entry = pendingKdsItemUpdates.get(itemId)
    if (!entry) return null
    if (Date.now() - entry.timestamp > 5000) {
      pendingKdsItemUpdates.delete(itemId)
      return null
    }
    return entry.status
  }
  const menuItems = ref<MenuItem[]>([])
  const ingredients = ref<Ingredient[]>([])
  const ingredientCategories = ref<IngredientCategory[]>([])
  const categories = ref<MenuCategory[]>([])
  const tables = ref<TableItem[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  // Getters
  const incomingOrders = computed(() => {
    return [...orders.value]
      .filter(o => ['awaiting_payment', 'pending_payment', 'confirmed', 'preparing', 'ready'].includes(o.status))
      .sort((a, b) => {
        const parseD = (d: any) => {
          if (!d) return 0
          const s = typeof d === 'string' && d.includes(' ') && !d.includes('T') ? d.replace(' ', 'T') : d
          return new Date(s).getTime() || 0
        }
        return parseD(a.createdAt) - parseD(b.createdAt)
      })
  })

  const completedOrders = computed(() => {
    return orders.value.filter(o => o.status === 'completed')
  })

  const activeTablesCount = computed(() => {
    return tables.value.filter(t => t.status === 'occupied').length
  })

  const getTableByCode = (code?: string) => {
    if (!code) return undefined
    return tables.value.find(t => t.tableCode === code || t.code === code || t.id === code)
  }

  // Actions: Categories (Real Backend API)
  const fetchCategories = async (targetOutletId?: string) => {
    try {
      const activeOutletId = targetOutletId || localStorage.getItem('lapaqu_outlet_id') || undefined
      const params: any = {}
      if (activeOutletId) params.outlet_id = activeOutletId
      const res = await apiClient.get('/menu-categories', { params })
      const data = res.data
      if (data.categories && Array.isArray(data.categories)) {
        categories.value = data.categories.map((c: any) => ({
          id: c.id,
          outletId: c.outlet_id || activeOutletId || '',
          name: c.name,
          sortOrder: c.sort_order || 0,
          itemCount: Number(c.menu_items_count ?? c.items_count ?? (c.items ? c.items.length : 0)),
        }))
      }
    } catch (err: any) {
      console.error('Failed to fetch categories:', err?.message)
    }
  }

  const createCategory = async (payload: { name: string; sort_order?: number }) => {
    const res = await apiClient.post('/menu-categories', payload)
    await fetchCategories()
    return res.data
  }

  const updateCategory = async (id: string, payload: { name?: string; sort_order?: number }) => {
    const res = await apiClient.put(`/menu-categories/${id}`, payload)
    await fetchCategories()
    return res.data
  }

  const deleteCategory = async (id: string) => {
    const res = await apiClient.delete(`/menu-categories/${id}`)
    await fetchCategories()
    return res.data
  }

  // Actions: Menu Items (Real Backend API)
  const fetchMenuItems = async (targetOutletId?: string) => {
    try {
      const activeOutletId = targetOutletId || localStorage.getItem('lapaqu_outlet_id') || undefined
      const params: any = {}
      if (activeOutletId) params.outlet_id = activeOutletId
      const res = await apiClient.get('/menu-items', { params })
      const data = res.data
      if (data.items && Array.isArray(data.items)) {
        menuItems.value = data.items.map((m: any) => ({
          id: m.id,
          categoryId: m.category_id,
          category: m.category ? {
            id: m.category.id,
            name: m.category.name,
            slug: m.category.slug,
          } : undefined,
          outletId: m.outlet_id || activeOutletId || '',
          name: m.name,
          description: m.description,
          price: Number(m.price ?? m.base_price ?? 0),
          imageUrl: m.image_url || 'https://images.unsplash.com/photo-1541167760496-1628856ab772?w=500&auto=format&fit=crop&q=80',
          isAvailable: m.is_available ?? true,
          stockQty: m.stock_qty ?? 50,
          maxServings: m.max_servings !== undefined ? m.max_servings : null,
          recipes: m.recipes ? m.recipes.map((r: any) => ({
            id: r.id,
            menuItemId: r.menu_item_id,
            ingredientId: r.ingredient_id,
            quantityNeeded: Number(r.quantity_needed),
            ingredient: r.ingredient ? {
              id: r.ingredient.id,
              outletId: r.ingredient.outlet_id,
              name: r.ingredient.name,
              unit: r.ingredient.unit,
              baseUnit: r.ingredient.base_unit,
              currentStock: Number(r.ingredient.current_stock ?? 0),
              displayStock: Number(r.ingredient.display_stock ?? r.ingredient.current_stock ?? 0),
              lowStockThreshold: r.ingredient.low_stock_threshold ? Number(r.ingredient.low_stock_threshold) : null,
              costPerUnit: r.ingredient.cost_per_unit ? Number(r.ingredient.cost_per_unit) : null,
              isActive: r.ingredient.is_active ?? true,
              isLowStock: Boolean(r.ingredient.is_low_stock),
            } : undefined
          })) : []
        }))
      }
    } catch (err: any) {
      console.error('Failed to fetch menu items:', err?.message)
    }
  }

  const createMenuItem = async (payload: { name: string; price: number; category_id: string; description?: string; image_url?: string }) => {
    const res = await apiClient.post('/menu-items', {
      name: payload.name,
      base_price: payload.price,
      price: payload.price,
      category_id: payload.category_id,
      description: payload.description || null,
      image_url: payload.image_url || null,
    })
    await Promise.all([fetchMenuItems(), fetchCategories()])
    return res.data
  }

  const updateMenuItem = async (id: string, payload: { name?: string; price?: number; category_id?: string; description?: string; image_url?: string }) => {
    const body: any = { ...payload }
    if (payload.price !== undefined) {
      body.base_price = payload.price
    }
    const res = await apiClient.put(`/menu-items/${id}`, body)
    await Promise.all([fetchMenuItems(), fetchCategories()])
    return res.data
  }

  const deleteMenuItem = async (id: string) => {
    const res = await apiClient.delete(`/menu-items/${id}`)
    await Promise.all([fetchMenuItems(), fetchCategories()])
    return res.data
  }

  // Actions: Ingredients & Recipes (Real Backend API)
  // Actions: Ingredient Categories
  const fetchIngredientCategories = async (outletId?: string) => {
    try {
      if (!localStorage.getItem('lapaqu_token')) {
        await authStore.ensureToken()
      }
      const params: any = {}
      if (outletId) params.outlet_id = outletId
      const res = await apiClient.get('/ingredient-categories', { params })
      if (res.data.categories && Array.isArray(res.data.categories)) {
        ingredientCategories.value = res.data.categories.map((c: any) => ({
          id: c.id,
          outletId: c.outlet_id,
          name: c.name,
          description: c.description,
          sortOrder: Number(c.sort_order ?? 0),
          ingredientsCount: Number(c.ingredients_count ?? 0),
          createdAt: c.created_at,
        }))
      }
      return ingredientCategories.value
    } catch (err: any) {
      console.error('Failed to fetch ingredient categories:', err?.message)
      return []
    }
  }

  const createIngredientCategory = async (payload: {
    name: string
    description?: string
    sort_order?: number
    outlet_id?: string
  }) => {
    const res = await apiClient.post('/ingredient-categories', payload)
    await fetchIngredientCategories()
    return res.data
  }

  const updateIngredientCategory = async (
    id: string,
    payload: { name?: string; description?: string; sort_order?: number }
  ) => {
    const res = await apiClient.put(`/ingredient-categories/${id}`, payload)
    await fetchIngredientCategories()
    return res.data
  }

  const deleteIngredientCategory = async (id: string) => {
    const res = await apiClient.delete(`/ingredient-categories/${id}`)
    await fetchIngredientCategories()
    return res.data
  }

  // Actions: Ingredients & Recipes (Real Backend API)
  const fetchIngredients = async (params?: {
    outletId?: string
    search?: string
    categoryId?: string
    lowStockOnly?: boolean
  }) => {
    try {
      if (!localStorage.getItem('lapaqu_token')) {
        await authStore.ensureToken()
      }
      const queryParams: any = {}
      if (params?.outletId) queryParams.outlet_id = params.outletId
      if (params?.search) queryParams.search = params.search
      if (params?.categoryId) queryParams.category_id = params.categoryId
      if (params?.lowStockOnly) queryParams.low_stock_only = true

      const res = await apiClient.get('/ingredients', { params: queryParams })
      const data = res.data
      if (data.ingredients && Array.isArray(data.ingredients)) {
        ingredients.value = data.ingredients.map((i: any) => ({
          id: i.id,
          outletId: i.outlet_id,
          categoryId: i.category_id,
          category: i.category ? { id: i.category.id, name: i.category.name } : null,
          name: i.name,
          unit: i.unit,
          baseUnit: i.base_unit,
          currentStock: Number(i.display_stock ?? i.current_stock ?? 0),
          displayStock: Number(i.display_stock ?? i.current_stock ?? 0),
          lowStockThreshold: i.display_low_stock_threshold !== undefined && i.display_low_stock_threshold !== null
            ? Number(i.display_low_stock_threshold)
            : (i.low_stock_threshold ? Number(i.low_stock_threshold) : null),
          displayLowStockThreshold: i.display_low_stock_threshold !== undefined && i.display_low_stock_threshold !== null
            ? Number(i.display_low_stock_threshold)
            : (i.low_stock_threshold ? Number(i.low_stock_threshold) : null),
          costPerUnit: i.cost_per_unit ? Number(i.cost_per_unit) : null,
          purchasePrice: i.purchase_price !== undefined && i.purchase_price !== null ? Number(i.purchase_price) : null,
          purchaseUnit: i.purchase_unit || i.unit,
          baseCostPerUnit: i.base_cost_per_unit !== undefined && i.base_cost_per_unit !== null ? Number(i.base_cost_per_unit) : null,
          baseUnitDisplay: i.base_unit_display || null,
          isActive: i.is_active ?? true,
          isLowStock: Boolean(i.is_low_stock),
        }))
      }
      return ingredients.value
    } catch (err: any) {
      console.error('Failed to fetch ingredients:', err?.message)
      return []
    }
  }

  const createIngredient = async (payload: {
    outlet_id?: string
    category_id?: string | null
    name: string
    unit: string
    current_stock?: number
    low_stock_threshold?: number
    cost_per_unit?: number
  }) => {
    const res = await apiClient.post('/ingredients', payload)
    await Promise.all([fetchIngredients(), fetchMenuItems()])
    return res.data
  }

  const updateIngredient = async (id: string, payload: {
    category_id?: string | null
    name?: string
    unit?: string
    low_stock_threshold?: number | null
    cost_per_unit?: number | null
    is_active?: boolean
  }) => {
    const res = await apiClient.put(`/ingredients/${id}`, payload)
    await Promise.all([fetchIngredients(), fetchMenuItems()])
    return res.data
  }

  const deleteIngredient = async (id: string) => {
    const res = await apiClient.delete(`/ingredients/${id}`)
    await Promise.all([fetchIngredients(), fetchMenuItems()])
    return res.data
  }

  const adjustIngredientStock = async (id: string, payload: { quantity: number; type: 'restock' | 'set'; notes?: string }) => {
    const res = await apiClient.post(`/ingredients/${id}/adjust-stock`, payload)
    await Promise.all([fetchIngredients(), fetchMenuItems()])
    return res.data
  }

  // Actions: Stock Opname & Stock Logs
  const fetchStockLogs = async (params?: {
    outlet_id?: string
    ingredient_id?: string
    type?: string
    start_date?: string
    end_date?: string
    search?: string
    page?: number
    per_page?: number
  }) => {
    try {
      if (!localStorage.getItem('lapaqu_token')) {
        await authStore.ensureToken()
      }
      const res = await apiClient.get('/stock-logs', { params })
      return res.data
    } catch (err: any) {
      console.error('Failed to fetch stock logs:', err?.message)
      return { data: [], total: 0 }
    }
  }

  const fetchStockOpnames = async (params?: { outlet_id?: string; page?: number; per_page?: number }) => {
    try {
      if (!localStorage.getItem('lapaqu_token')) {
        await authStore.ensureToken()
      }
      const res = await apiClient.get('/stock-opnames', { params })
      return res.data
    } catch (err: any) {
      console.error('Failed to fetch stock opnames:', err?.message)
      return { data: [], total: 0 }
    }
  }

  const getStockOpname = async (id: string) => {
    const res = await apiClient.get(`/stock-opnames/${id}`)
    return res.data?.opname
  }

  const submitStockOpname = async (payload: {
    outlet_id?: string
    notes?: string
    items: Array<{ ingredient_id: string; physical_stock: number; notes?: string }>
  }) => {
    const res = await apiClient.post('/stock-opnames', payload)
    await Promise.all([fetchIngredients(), fetchMenuItems()])
    return res.data
  }

  const updateMenuItemRecipe = async (menuItemId: string, recipes: Array<{ ingredient_id: string; quantity_needed: number }>) => {
    const res = await apiClient.put(`/menu-items/${menuItemId}/recipe`, { recipes })
    await Promise.all([fetchMenuItems(), fetchIngredients()])
    return res.data
  }

  // Actions: Tables
  const fetchTables = async (targetOutletId?: string) => {
    try {
      if (!localStorage.getItem('lapaqu_token')) {
        await authStore.ensureToken()
      }
      const activeOutletId = targetOutletId || localStorage.getItem('lapaqu_outlet_id') || undefined
      const params: any = {}
      if (activeOutletId) params.outlet_id = activeOutletId
      const res = await apiClient.get('/tables', { params })
      const data = res.data
      if (data.tables && Array.isArray(data.tables) && data.tables.length > 0) {
        tables.value = data.tables.map((t: any) => ({
          id: t.id,
          code: t.table_number.startsWith('T-') ? t.table_number : `T-${t.table_number.replace(/[^0-9]/g, '').padStart(2, '0')}`,
          tableCode: t.table_number,
          zone: t.zone || 'indoor',
          capacity: t.capacity || 4,
          status: t.status || 'available',
          activeSessionId: t.active_session_id,
          sessionStartedAt: t.session_started_at,
          ordersCount: t.orders_count || 0,
          outletId: t.outlet_id,
          qrToken: t.qr_code_token || t.qrToken,
        }))
      } else {
        tables.value = []
      }
    } catch (err: any) {
      console.error('Failed to load tables from backend:', err?.message)
      tables.value = []
    }
  }

  const mapBackendOrder = (o: any): Order => {
    const rawT = o.table?.table_number || o.tableCode || ''
    const num = rawT.replace(/[^0-9]/g, '')
    const resolvedTableCode = num ? `T-${num.padStart(2, '0')}` : rawT

    return {
      id: o.id,
      orderNumber: o.order_number || o.orderNumber,
      outletId: o.outlet_id || o.outletId,
      tableId: o.table_id || o.tableId,
      tableCode: resolvedTableCode,
      orderType: (o.order_type || o.orderType || (o.table_id || o.tableCode || o.table?.table_number ? 'dine_in' : 'takeaway')) as ('dine_in' | 'takeaway'),
      source: o.source || 'manual_kasir',
      customerName: o.customer_name || 'Pelanggan Manual',
      status: o.status === 'processing' ? 'preparing' : (o.status || 'confirmed'),
      paymentStatus: o.payment_status || 'unpaid',
      paymentMethod: o.payments?.[0]?.payment_method || o.paymentMethod || 'cash',
      items: (o.items || []).map((it: any) => {
        const resolvedName = it.item_name_snapshot || it.menu_item?.name || it.name || 'Menu Item'
        const resolvedPrice = Number(it.base_price_snapshot || it.price || it.unit_price || 0)
        return {
          id: it.id,
          menuItemId: it.menu_item_id || it.id,
          menuItemName: resolvedName,
          name: resolvedName,
          unitPrice: resolvedPrice,
          price: resolvedPrice,
          subtotal: Number(it.subtotal || (resolvedPrice * (it.quantity || 1))),
          quantity: it.quantity || 1,
          notes: it.notes || '',
          selectedOptions: (it.options || []).map((opt: any) => ({
            optionId: opt.id || '',
            optionName: opt.option_name_snapshot || opt.name || '',
            priceModifier: Number(opt.price_modifier_snapshot || opt.price_modifier || 0),
          })),
          status: getPendingKdsItemStatus(it.id) || it.status || 'pending',
        }
      }),
      subtotal: Number(o.subtotal || 0),
      taxAmount: Number(o.tax_amount || 0),
      discountAmount: Number(o.discount_amount || 0),
      totalAmount: Number(o.total_amount || 0),
      ...(o.cash_received ? { cashReceived: Number(o.cash_received) } : {}),
      ...(o.change_given ? { changeGiven: Number(o.change_given) } : {}),
      createdAt: o.created_at || new Date().toISOString(),
      updatedAt: o.updated_at || new Date().toISOString(),
    }
  }

  const reconcileOrders = (freshOrdersRaw: any[]) => {
    const freshMapped = freshOrdersRaw.map(mapBackendOrder)
    const freshMap = new Map<string, Order>()
    freshMapped.forEach(o => freshMap.set(o.id, o))

    const currentOrders = orders.value
    const updatedList: Order[] = []
    const seenIds = new Set<string>()

    for (const current of currentOrders) {
      const fresh = freshMap.get(current.id)
      if (fresh) {
        seenIds.add(current.id)
        if (current.status !== fresh.status) current.status = fresh.status
        if (current.paymentStatus !== fresh.paymentStatus) current.paymentStatus = fresh.paymentStatus
        if (current.paymentMethod !== fresh.paymentMethod) current.paymentMethod = fresh.paymentMethod
        if (current.customerName !== fresh.customerName) current.customerName = fresh.customerName
        if (current.tableCode !== fresh.tableCode) current.tableCode = fresh.tableCode
        if (current.totalAmount !== fresh.totalAmount) current.totalAmount = fresh.totalAmount

        // Reconcile items in place
        if (fresh.items && fresh.items.length) {
          if (current.items.length !== fresh.items.length) {
            current.items = fresh.items
          } else {
            for (let idx = 0; idx < current.items.length; idx++) {
              const ci = current.items[idx]
              const fi = fresh.items[idx]
              if (ci && fi && ci.id === fi.id) {
                const pendingStatus = getPendingKdsItemStatus(ci.id)
                if (pendingStatus !== null) {
                  ci.status = pendingStatus as any
                } else if (ci.status !== fi.status) {
                  ci.status = fi.status
                }
                if (ci.quantity !== fi.quantity) ci.quantity = fi.quantity
              } else {
                current.items = fresh.items
                break
              }
            }
          }
        }
        // Preserves exact same object reference for unchanged orders
        updatedList.push(current)
      }
    }

    // Add newly created orders
    for (const fresh of freshMapped) {
      if (!seenIds.has(fresh.id)) {
        updatedList.push(fresh)
      }
    }

    const isListChanged =
      updatedList.length !== currentOrders.length ||
      updatedList.some((o, idx) => o.id !== currentOrders[idx]?.id)

    if (isListChanged) {
      orders.value = updatedList
    }
  }

  // Actions: Orders
  const fetchOrders = async (isBackground = false) => {
    try {
      if (!isBackground && orders.value.length === 0) {
        isLoading.value = true
      }
      await authStore.ensureToken()
      const res = await apiClient.get('/pos/orders')
      const data = res.data
      if (data.orders && Array.isArray(data.orders)) {
        reconcileOrders(data.orders)

        if (!currentSubscribedOutletId) {
          const firstOutlet = orders.value.find(o => isUuid(o.outletId))?.outletId
          if (firstOutlet) {
            initRealtime(firstOutlet)
          }
        }
      }
    } catch (err: any) {
      console.error('Failed to load orders from backend:', err?.message)
    } finally {
      isLoading.value = false
    }
  }

  // Create Order
  const createManualOrder = async (orderPayload: {
    customer_name?: string
    customerName?: string
    table_id?: string
    tableId?: string
    order_type?: 'dine_in' | 'takeaway'
    orderType?: 'dine_in' | 'takeaway'
    payment_method?: string
    paymentMethod?: string
    cash_received?: number
    cashReceived?: number
    outlet_id?: string
    outletId?: string
    source?: string
    items: any[]
    [key: string]: any
  }) => {
    try {
      const activeOutletId =
        orderPayload.outlet_id ||
        orderPayload.outletId ||
        authStore.currentUser?.outletId ||
        (menuItems.value.length > 0 ? menuItems.value[0].outletId : undefined) ||
        '01a04c28-547a-710a-a0a1-73440c26b8fe'

      const mappedItems = (orderPayload.items || []).map((it: any) => {
        const menuItemId = it.menu_item_id || it.menuItem?.id || it.id
        const rawOptions = it.selected_option_ids || it.selectedOptions || []
        const selectedOptionIds = rawOptions
          .map((opt: any) => (typeof opt === 'string' ? opt : opt.optionId || opt.id))
          .filter(Boolean)

        return {
          menu_item_id: menuItemId,
          quantity: Number(it.quantity || 1),
          notes: it.notes || null,
          selected_option_ids: selectedOptionIds.length > 0 ? selectedOptionIds : undefined,
        }
      })

      const payload = {
        outlet_id: activeOutletId,
        order_type: orderPayload.order_type || orderPayload.orderType || 'dine_in',
        table_id: orderPayload.table_id || orderPayload.tableId || undefined,
        customer_name: (orderPayload.customer_name || orderPayload.customerName || 'Pelanggan Manual').trim(),
        payment_method: (orderPayload.payment_method || orderPayload.paymentMethod || 'cash').toLowerCase(),
        cash_received: Number(orderPayload.cash_received ?? orderPayload.cashReceived ?? 0),
        items: mappedItems,
      }

      const res = await apiClient.post('/pos/orders', payload)
      await Promise.all([
        fetchOrders(),
        fetchTables(),
        fetchMenuItems(),
      ])
      window.dispatchEvent(new CustomEvent('kds:refresh'))
      return res.data.order
    } catch (err: any) {
      console.error('Error createManualOrder:', err)
      throw new Error(err.response?.data?.message || err.message || 'Gagal membuat pesanan')
    }
  }

  // Pay Cash
  const payCashOrder = async (orderId: string, cashReceived: number) => {
    try {
      const res = await apiClient.post(`/pos/orders/${orderId}/pay-cash`, {
        cash_received: cashReceived
      })
      await fetchOrders(true)
      window.dispatchEvent(new CustomEvent('kds:refresh'))
      return res.data
    } catch (err: any) {
      console.error('Error payCashOrder:', err)
      throw new Error(err.response?.data?.message || err.message || 'Gagal memproses pembayaran')
    }
  }

  // Void Item
  const voidOrderItem = async (orderId: string, orderItemId: string, reason: string) => {
    try {
      const res = await apiClient.post(`/pos/orders/${orderId}/void-item`, {
        order_item_id: orderItemId,
        void_reason: reason || 'Dibatalkan oleh kasir'
      })
      await fetchOrders(true)
      return res.data
    } catch (err: any) {
      console.error('Error voidOrderItem:', err)
      throw new Error(err.response?.data?.message || err.message || 'Gagal membatalkan item')
    }
  }

  // Update Status
  const updateOrderStatus = async (orderId: string, nextStatus: OrderStatus) => {
    // Instant optimistic update in local state
    const o = orders.value.find(ord => ord.id === orderId)
    const prevStatus = o ? o.status : null
    if (o) {
      o.status = nextStatus
    }
    try {
      const apiStatus = nextStatus === 'preparing' ? 'processing' : nextStatus
      const res = await apiClient.patch(`/pos/orders/${orderId}/status`, { status: apiStatus })
      if (res.data?.order && o) {
        const fresh = mapBackendOrder(res.data.order)
        if (o.status !== fresh.status) o.status = fresh.status
        if (o.paymentStatus !== fresh.paymentStatus) o.paymentStatus = fresh.paymentStatus
        // In-place update items without breaking array references to prevent card flickering
        if (fresh.items && fresh.items.length) {
          if (o.items.length !== fresh.items.length) {
            o.items = fresh.items
          } else {
            for (let i = 0; i < o.items.length; i++) {
              const ci = o.items[i]
              const fi = fresh.items[i]
              if (ci && fi && ci.id === fi.id) {
                if (ci.status !== fi.status) ci.status = fi.status
                if (ci.quantity !== fi.quantity) ci.quantity = fi.quantity
              } else {
                o.items = fresh.items
                break
              }
            }
          }
        }
      }
      // Broadcast to other components with orderId so they avoid re-rendering untouched cards
      window.dispatchEvent(new CustomEvent('kds:refresh', { detail: { orderId, status: nextStatus } }))
    } catch (err: any) {
      console.error('Error updateOrderStatus:', err)
      if (o && prevStatus) {
        o.status = prevStatus
      }
    }
  }

  // Close Table Session
  const closeTableSession = async (tableIdentifier: string) => {
    try {
      const targetTable = tables.value.find(t => t.id === tableIdentifier || t.tableCode === tableIdentifier || t.code === tableIdentifier)
      const targetId = targetTable ? targetTable.id : tableIdentifier
      if (targetTable) {
        targetTable.status = 'available'
      }
      await apiClient.post(`/pos/tables/${targetId}/close-session`)
      await fetchTables()
      await fetchOrders(true)
    } catch (err: any) {
      console.error('Error closeTableSession:', err)
    }
  }

  // Toggle Menu Availability
  const toggleMenuItemAvailability = async (itemId: string) => {
    try {
      const idx = menuItems.value.findIndex(m => m.id === itemId)
      if (idx !== -1) {
        menuItems.value[idx].isAvailable = !menuItems.value[idx].isAvailable
      }
      await apiClient.patch(`/menu-items/${itemId}/toggle-availability`)
    } catch (err: any) {
      console.error('Error toggleMenuItemAvailability:', err)
    }
  }

  // Table Management (CRUD)
  const createTable = async (tableData: { table_number: string; capacity: number }) => {
    try {
      await apiClient.post('/tables', tableData)
      await fetchTables()
    } catch (err: any) {
      console.error('Error createTable:', err)
    }
  }

  const updateTable = async (id: string, tableData: { table_number?: string; capacity?: number; is_active?: boolean }) => {
    try {
      await apiClient.put(`/tables/${id}`, tableData)
      await fetchTables()
    } catch (err: any) {
      console.error('Error updateTable:', err)
    }
  }

  const deleteTable = async (id: string) => {
    try {
      await apiClient.delete(`/tables/${id}`)
      await fetchTables()
    } catch (err: any) {
      console.error('Error deleteTable:', err)
    }
  }

  const reserveTable = (tableCode: string) => {
    const tIdx = tables.value.findIndex(t => t.tableCode === tableCode || t.code === tableCode || t.id === tableCode)
    if (tIdx !== -1) {
      tables.value[tIdx].status = 'reserved'
    }
  }

  const regenerateTableQr = async (id: string) => {
    try {
      await apiClient.post(`/tables/${id}/regenerate-qr`)
      await fetchTables()
    } catch (err: any) {
      console.error('Error regenerateTableQr:', err)
    }
  }

  let currentSubscribedOutletId: string | null = null
  const initRealtime = (targetOutletId?: string) => {
    const token = localStorage.getItem('lapaqu_token')
    if (!token) return

    const echo = getEcho()
    if (!echo) return

    let user: any = null
    try {
      user = JSON.parse(localStorage.getItem('lapaqu_user') || '{}')
    } catch (e) {}

    const outletCandidate =
      (isUuid(targetOutletId) ? targetOutletId : null) ||
      (isUuid(authStore.currentUser?.outletId) ? authStore.currentUser?.outletId : null) ||
      (isUuid(user?.outletId) ? user.outletId : null) ||
      (isUuid(user?.outlet_id) ? user.outlet_id : null) ||
      (isUuid(user?.outlets?.[0]?.id) ? user.outlets[0].id : null) ||
      orders.value.find(o => isUuid(o.outletId))?.outletId ||
      tables.value.find(t => isUuid(t.outletId))?.outletId ||
      (categories.value.length > 0 && isUuid((categories.value[0] as any)?.outletId) ? (categories.value[0] as any)?.outletId : null) ||
      (categories.value.length > 0 && isUuid((categories.value[0] as any)?.outlet_id) ? (categories.value[0] as any)?.outlet_id : null)

    if (!outletCandidate || !isUuid(outletCandidate)) {
      return
    }

    if (currentSubscribedOutletId === outletCandidate) {
      return
    }

    if (currentSubscribedOutletId) {
      try {
        echo.leave('outlet.' + currentSubscribedOutletId)
      } catch (e) {}
    }

    currentSubscribedOutletId = outletCandidate
    console.log('[Echo Reverb] Subscribed to channel: outlet.' + outletCandidate)
    let debounceTimer: any = null
    const debouncedRefresh = (eventType: string, payload: any) => {
      console.log('[Echo Reverb] Batched event (' + eventType + '):', payload)
      if (debounceTimer) clearTimeout(debounceTimer)
      debounceTimer = setTimeout(() => {
        fetchOrders(true)
        window.dispatchEvent(new CustomEvent('kds:refresh'))
      }, 250)
    }

    echo.private('outlet.' + outletCandidate)
      .listen('.order.created', (data: any) => debouncedRefresh('order.created', data))
      .listen('.order.status.updated', (data: any) => {
        const updatedOrder = data?.order
        if (updatedOrder && updatedOrder.id) {
          const target = orders.value.find(ord => ord.id === updatedOrder.id)
          if (target) {
            const mapped = mapBackendOrder(updatedOrder)
            if (target.status !== mapped.status) target.status = mapped.status
            if (target.paymentStatus !== mapped.paymentStatus) target.paymentStatus = mapped.paymentStatus
            if (mapped.items && mapped.items.length) {
              if (target.items.length !== mapped.items.length) {
                target.items = mapped.items
              } else {
                for (let i = 0; i < target.items.length; i++) {
                  const ci = target.items[i]
                  const fi = mapped.items[i]
                  if (ci && fi && ci.id === fi.id) {
                    if (ci.status !== fi.status) ci.status = fi.status
                    if (ci.quantity !== fi.quantity) ci.quantity = fi.quantity
                  } else {
                    target.items = mapped.items
                    break
                  }
                }
              }
            }
            window.dispatchEvent(new CustomEvent('kds:refresh', { detail: { orderId: updatedOrder.id, status: mapped.status } }))
            return
          }
        }
        debouncedRefresh('order.status.updated', data)
      })
      .listen('.order.paid', (data: any) => debouncedRefresh('order.paid', data))
      .listen('.kitchen.item.status.updated', (data: any) => {
        const item = data?.item
        if (item && item.order_id) {
          const target = orders.value.find(ord => ord.id === item.order_id)
          if (target) {
            const it = target.items.find(i => i.id === item.id)
            if (it) {
              const resolved = item.status === 'ready' || item.status === 'served' ? item.status : 'cooking'
              it.status = resolved
              setPendingKdsItem(item.id, resolved)
              window.dispatchEvent(new CustomEvent('kds:refresh', { detail: { orderId: item.order_id, itemId: item.id, itemStatus: it.status } }))
              return
            }
          }
        }
        debouncedRefresh('kitchen.item.status.updated', data)
      })
  }

  return {
    initRealtime,
    setPendingKdsItem,
    
    orders,
    menuItems,
    categories,
    tables,
    isLoading,
    error,
    incomingOrders,
    completedOrders,
    activeTablesCount,
    getTableByCode,
    fetchCategories,
    createCategory,
    updateCategory,
    deleteCategory,
    ingredients,
    ingredientCategories,
    fetchIngredientCategories,
    createIngredientCategory,
    updateIngredientCategory,
    deleteIngredientCategory,
    fetchIngredients,
    createIngredient,
    updateIngredient,
    deleteIngredient,
    adjustIngredientStock,
    fetchStockLogs,
    fetchStockOpnames,
    getStockOpname,
    submitStockOpname,
    updateMenuItemRecipe,
    fetchMenuItems,
    createMenuItem,
    updateMenuItem,
    deleteMenuItem,
    fetchTables,
    fetchOrders,
    createManualOrder,
    payCashOrder,
    voidOrderItem,
    updateOrderStatus,
    closeTableSession,
    toggleMenuItemAvailability,
    createTable,
    updateTable,
    deleteTable,
    reserveTable,
    regenerateTableQr,
  }
})