import type { MenuItem, MenuCategory, TableItem, Order, User, SubscriptionPlan, Invoice, RefundRequest } from '@/types'

export const mockCategories: MenuCategory[] = []
export const mockMenuItems: MenuItem[] = []
export const mockTables: TableItem[] = []
export const mockOrders: Order[] = []
export const mockStaffUsers: User[] = []
export const mockDashboardStats = {
  totalRevenue: 0,
  activeOrdersCount: 0,
  occupiedTablesCount: 0,
  averageOrderValue: 0,
  totalOrdersCount: 0,
  completedOrdersCount: 0,
}
export const mockRefunds: RefundRequest[] = []
export const mockPlans: SubscriptionPlan[] = []
export const mockInvoices: Invoice[] = []
