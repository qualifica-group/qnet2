import type { StockBalance } from '@/features/stock/types'

/** Decimal places the backend stores (`decimal(12,3)`). */
export const QUANTITY_DECIMALS = 3

const QUANTITY_PATTERN = new RegExp(`^\\d+([.,]\\d{1,${QUANTITY_DECIMALS}})?$`)

/** "3.000" -> "3", "2.500" -> "2.5": the quantity without trailing zeros (spec 0307 D-10). */
export function formatQuantity(value: string): string {
  if (!value.includes('.')) {
    return value
  }
  return value.replace(/0+$/, '').replace(/\.$/, '')
}

/** "3" + "pz" shown together. */
export function formatQuantityWithUnit(balance: Pick<StockBalance, 'quantity' | 'product'>): string {
  return `${formatQuantity(balance.quantity)} ${balance.product.unit_of_measure.code}`
}

/** True for a non-negative number with at most three decimals ("," accepted as separator). */
export function isValidQuantity(value: string): boolean {
  return QUANTITY_PATTERN.test(value.trim())
}

/** Wire form of a typed quantity: dot as decimal separator. */
export function toWireQuantity(value: string): string {
  return value.trim().replace(',', '.')
}

/** True when the typed quantity is strictly positive. */
export function isPositiveQuantity(value: string): boolean {
  return isValidQuantity(value) && Number(toWireQuantity(value)) > 0
}
