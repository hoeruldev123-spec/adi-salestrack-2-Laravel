export function useFormat() {
  const formatRupiah = (value: number | string | null | undefined): string => {
    const num = Number(value)
    if (value === null || value === undefined || Number.isNaN(num)) return '-'
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      maximumFractionDigits: 0,
    }).format(num)
  }

  const formatNumber = (value: number | string | null | undefined): string => {
    const num = Number(value)
    if (value === null || value === undefined || Number.isNaN(num)) return '-'
    return new Intl.NumberFormat('id-ID').format(num)
  }

  const formatPercent = (value: number | string | null | undefined, digits = 1): string => {
    const num = Number(value)
    if (value === null || value === undefined || Number.isNaN(num)) return '-'
    return `${num.toFixed(digits)}%`
  }

  const formatDate = (value: string | null | undefined, withTime = false): string => {
    if (!value) return '-'
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return value
    return new Intl.DateTimeFormat('id-ID', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
    }).format(date)
  }

  return { formatRupiah, formatNumber, formatPercent, formatDate }
}
