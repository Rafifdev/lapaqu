/**
 * Browser Web Notification API Service
 * Memunculkan push notifikasi desktop / browser untuk pesanan baru, panggilan pelayan, dan void.
 */

class NotificationService {
  private isSupported(): boolean {
    return typeof window !== 'undefined' && 'Notification' in window
  }

  private isEnabled(key = 'notifyBrowserPush', defaultVal = true): boolean {
    try {
      const saved = localStorage.getItem('lapaqu_notif_settings')
      if (saved) {
        const parsed = JSON.parse(saved)
        if (parsed[key] !== undefined) return Boolean(parsed[key])
      }
    } catch {
      // fallback
    }
    return defaultVal
  }

  /**
   * Minta izin notifikasi browser ke user
   */
  public async requestPermission(): Promise<NotificationPermission> {
    if (!this.isSupported()) return 'denied'
    try {
      const permission = await Notification.requestPermission()
      return permission
    } catch (err) {
      console.warn('Error requesting notification permission:', err)
      return 'denied'
    }
  }

  public hasPermission(): boolean {
    return this.isSupported() && Notification.permission === 'granted'
  }

  /**
   * Tampilkan Notifikasi Pesanan Baru
   */
  public showOrderNotification(payload: {
    orderNumber?: string
    customerName?: string
    totalAmount?: number
    tableNumber?: string | number
  }): void {
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent('app:order-notification', { detail: payload }))
    }

    if (!this.isSupported() || !this.hasPermission() || !this.isEnabled('notifyBrowserPush')) {
      return
    }

    try {
      const orderNum = payload.orderNumber || 'Baru'
      const customer = payload.customerName || 'Pelanggan'
      const location = payload.tableNumber ? `Meja ${payload.tableNumber}` : 'Takeaway'
      const amountStr = payload.totalAmount
        ? ` • Rp ${Number(payload.totalAmount).toLocaleString('id-ID')}`
        : ''

      const notif = new Notification(`🔔 Pesanan Baru: #${orderNum}`, {
        body: `${customer} (${location})${amountStr}`,
        icon: '/favicon.ico',
        badge: '/favicon.ico',
        tag: `order-${orderNum}-${Date.now()}`,
      })

      notif.onclick = () => {
        window.focus()
        notif.close()
      }
    } catch (err) {
      console.warn('Error dispatching browser notification:', err)
    }
  }

  /**
   * Tampilkan Notifikasi Panggilan Pelayan / Bel Meja
   */
  public showWaiterNotification(tableNumber: string | number): void {
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent('app:waiter-notification', { detail: { tableNumber } }))
    }

    if (!this.isSupported() || !this.hasPermission() || !this.isEnabled('notifyWaiterBell')) {
      return
    }

    try {
      const notif = new Notification(`🛎️ Panggilan Pelayan: Meja ${tableNumber}`, {
        body: `Pelanggan di Meja ${tableNumber} membutuhkan bantuan/pelayanan.`,
        icon: '/favicon.ico',
        tag: `waiter-table-${tableNumber}`,
      })

      notif.onclick = () => {
        window.focus()
        notif.close()
      }
    } catch (err) {
      console.warn('Error dispatching waiter notification:', err)
    }
  }

  /**
   * Tampilkan Notifikasi Pembatalan / Void
   */
  public showVoidNotification(orderNumber: string): void {
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent('app:void-notification', { detail: { orderNumber } }))
    }

    if (!this.isSupported() || !this.hasPermission() || !this.isEnabled('notifyKdsVoid')) {
      return
    }

    try {
      const notif = new Notification(`⚠️ Pesanan Dibatalkan: #${orderNumber}`, {
        body: `Pesanan #${orderNumber} telah dibatalkan / void.`,
        icon: '/favicon.ico',
        tag: `void-order-${orderNumber}`,
      })

      notif.onclick = () => {
        window.focus()
        notif.close()
      }
    } catch (err) {
      console.warn('Error dispatching void notification:', err)
    }
  }
}

export const notificationService = new NotificationService()
export default notificationService
