export type UserRole = 'superadmin' | 'owner' | 'store_manager' | 'kasir' | 'kitchen_staff'

export interface User {
  id: string
  name: string
  email: string
  role: UserRole
  outletId?: string
  tenantId?: string
  avatarUrl?: string
  is2FAEnabled?: boolean
}

export interface Tenant {
  id: string
  name: string
  subdomain: string
  planId: string
  status: 'trial' | 'active' | 'overdue' | 'suspended' | 'churned'
  trialEndsAt?: string
  logoUrl?: string
  primaryColor?: string
}

export interface Outlet {
  id: string
  tenantId: string
  name: string
  address: string
  phone: string
  minOrderAmount: number
  defaultSessionTimeoutMinutes: number
  brandingDisplayName?: string
  brandingLogoUrl?: string
  isActive: boolean
}

export interface MenuItemVariantOption {
  id: string
  name: string
  priceModifier: number
}

export interface MenuItemVariantGroup {
  id: string
  name: string
  type: 'single' | 'multi'
  isRequired: boolean
  options: MenuItemVariantOption[]
}

export interface MenuItem {
  id: string
  categoryId: string
  category?: {
    id: string
    name: string
    slug?: string
  }
  outletId: string
  name: string
  description?: string
  price: number
  imageUrl?: string
  isAvailable: boolean
  stockQty?: number | null // null for unlimited
  maxServings?: number | null // computed from ingredient stock, null = unlimited
  variantGroups?: MenuItemVariantGroup[]
  recipes?: MenuItemRecipe[]
}

export interface MenuCategory {
  id: string
  outletId: string
  name: string
  sortOrder: number
  itemCount?: number
}

export interface TableItem {
  id: string
  outletId: string
  tableCode: string
  tableNumber?: string
  code?: string
  qrToken: string
  capacity: number
  status: 'available' | 'occupied' | 'reserved'
  activeSessionId?: string
  sessionStartedAt?: string
}

export interface TableSession {
  id: string
  tableId: string
  tableCode: string
  code?: string
  status: 'active' | 'closed'
  startedAt: string
  lastActivityAt: string
  totalOrders: number
  totalAmount: number
}

export type OrderStatus = 'awaiting_payment' | 'pending_payment' | 'confirmed' | 'preparing' | 'ready' | 'completed' | 'cancelled' | 'expired'
export type PaymentStatus = 'pending' | 'paid' | 'failed' | 'expired' | 'refunded'
export type PaymentMethod = 'qris' | 'xendit' | 'cash' | 'card'

export interface SelectedOption {
  groupId: string
  groupName: string
  optionId: string
  optionName: string
  priceModifier: number
}

export interface CartItem {
  id: string // unique cart item id
  menuItem: MenuItem
  quantity: number
  notes?: string
  selectedOptions: SelectedOption[]
  unitPrice: number
  subtotal: number
}

export interface OrderItem {
  id: string
  menuItemId: string
  menuItemName: string
  quantity: number
  unitPrice: number
  subtotal: number
  notes?: string
  selectedOptions: SelectedOption[]
  status: 'preparing' | 'ready' | 'served' | 'voided'
}

export interface Order {
  id: string
  orderNumber: string
  outletId: string
  tableId?: string
  tableCode?: string
  tableSessionId?: string
  orderType?: 'dine_in' | 'takeaway'
  source: 'qr' | 'manual_kasir'
  customerName?: string
  customerPhone?: string
  status: OrderStatus
  paymentStatus: PaymentStatus
  paymentMethod?: PaymentMethod
  items: OrderItem[]
  subtotal: number
  taxAmount: number
  discountAmount: number
  totalAmount: number
  cashPaid?: number
  cashChange?: number
  createdAt: string
  updatedAt: string
}

export interface RefundRequest {
  id: string
  orderId: string
  orderNumber: string
  amount: number
  reason: string
  paymentMethod: PaymentMethod
  status: 'pending' | 'approved' | 'rejected' | 'processed'
  requestedBy: string
  requestedAt: string
  reviewedBy?: string
  reviewedAt?: string
  rejectionReason?: string
}

export interface SubscriptionPlan {
  id: string
  name: 'Basic' | 'Pro'
  pricePerOutletMonthly: number
  maxTablesPerOutlet: number
  maxUsersPerOutlet: number
  maxOutlets: number
  features: string[]
}

export interface Invoice {
  id: string
  invoiceNumber: string
  amount: number
  planName: string
  activeOutletsCount: number
  status: 'paid' | 'pending' | 'overdue'
  periodStart: string
  periodEnd: string
  dueDate: string
  paymentLink?: string
}

export interface NotificationItem {
  id: string
  title: string
  message: string
  type: 'info' | 'warning' | 'success' | 'danger'
  isRead: boolean
  createdAt: string
  actionUrl?: string
}

export interface IngredientCategory {
  id: string
  outletId: string
  name: string
  description?: string | null
  sortOrder: number
  ingredientsCount?: number
  createdAt?: string
}

export interface Ingredient {
  id: string
  outletId: string
  categoryId?: string | null
  category?: { id: string; name: string } | null
  name: string
  unit: string
  baseUnit: string
  currentStock: number
  displayStock: number
  lowStockThreshold?: number | null
  displayLowStockThreshold?: number | null
  costPerUnit?: number | null
  purchasePrice?: number | null
  purchaseUnit?: string | null
  baseCostPerUnit?: number | null
  baseUnitDisplay?: string | null
  isActive: boolean
  isLowStock: boolean
}

export interface MenuItemRecipe {
  id: string
  menuItemId: string
  ingredientId: string
  quantityNeeded: number
  ingredient?: Ingredient
}

export interface IngredientStockLog {
  id: string
  tenantId: string
  outletId: string
  ingredientId: string
  userId?: string | null
  type: 'order_deduct' | 'restock' | 'stock_opname' | 'manual_adjustment' | 'waste' | 'cancellation_refund'
  referenceType?: string | null
  referenceId?: string | null
  quantity: number
  unit: string
  balanceBefore: number
  balanceAfter: number
  notes?: string | null
  createdAt: string
  ingredient?: {
    id: string
    name: string
    unit: string
    baseUnit?: string
  }
  user?: {
    id: string
    name: string
  }
}

export interface StockOpnameItem {
  id: string
  stockOpnameId: string
  ingredientId: string
  systemStock: number
  physicalStock: number
  difference: number
  unit: string
  notes?: string | null
  ingredient?: {
    id: string
    name: string
    unit: string
  }
}

export interface StockOpname {
  id: string
  tenantId: string
  outletId: string
  opnameNumber: string
  opnameDate: string
  notes?: string | null
  createdBy?: string | null
  createdAt: string
  creator?: {
    id: string
    name: string
  }
  items?: StockOpnameItem[]
  itemsCount?: number
}

