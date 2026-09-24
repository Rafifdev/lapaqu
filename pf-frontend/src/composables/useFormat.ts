const idrFormatter = new Intl.NumberFormat('id-ID', {
  style: 'currency',
  currency: 'IDR',
  maximumFractionDigits: 0,
})

const numFormatter = new Intl.NumberFormat('id-ID')

export function useFormat() {
  const formatCurrency = (val: number): string => idrFormatter.format(val || 0)
  const formatNumber = (val: number): string => numFormatter.format(val || 0)

  const formatDate = (dateStr: string | Date): string => {
    if (!dateStr) return '-'
    const d = typeof dateStr === 'string' ? new Date(dateStr) : dateStr
    return d.toLocaleDateString('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  }

  const formatTimeOnly = (dateStr: string | Date): string => {
    if (!dateStr) return '-'
    const d = typeof dateStr === 'string' ? new Date(dateStr) : dateStr
    return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
  }

  const formatCustomerName = (name?: string): string => {
    if (!name || !name.trim()) return 'Pelanggan Umum'
    const trimmed = name.trim()
    if (/^pelanggan\s+(meja|manual)/i.test(trimmed)) {
      return 'Pelanggan Umum'
    }
    return trimmed
  }

  return {
    formatCurrency,
    formatNumber,
    formatDate,
    formatTimeOnly,
    formatCustomerName,
  }
}
