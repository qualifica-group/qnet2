/** Share in percent with one decimal, in the active UI language (the server sends 0..100). */
export function formatShare(value: number, locale: string): string {
  const formatted = new Intl.NumberFormat(locale, { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(value)
  return `${formatted}%`
}
