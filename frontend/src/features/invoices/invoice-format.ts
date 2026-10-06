const EURO_FORMAT = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' })

/** Money in it-IT EUR; server decimal strings and numbers both accepted, empty for missing/NaN. */
export function formatEuro(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') {
    return ''
  }
  const amount = typeof value === 'number' ? value : Number(value)
  return Number.isNaN(amount) ? '' : EURO_FORMAT.format(amount)
}

/** Local calendar date as `YYYY-MM-DD` (never the UTC slice, which flips the day near midnight). */
export function todayIso(): string {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${now.getFullYear()}-${month}-${day}`
}

/** `<input type="number">` text -> number; empty -> `empty` (NaN for required, null for optional). */
export function parseNumberInput<T extends number | null>(raw: string, empty: T): number | T {
  return raw === '' ? empty : Number(raw)
}
