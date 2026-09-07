/**
 * Margine di un prodotto: la differenza prezzo/costo che il form ricapitola
 * mentre la si digita e che la scheda espone come KPI. Vive in un modulo
 * proprio perche' entrambe le superfici leggono lo STESSO calcolo — un
 * secondo `price - cost` scritto a mano in una delle due sarebbe libero di
 * divergere.
 */

/** Money columns arrive as `decimal:2` strings from Laravel and as numbers once the form normalized them. */
export interface ProductMargin {
  /** `price - cost`, in the same unit as both. */
  amount: number
  /** The margin over the price, in percent; `null` when the price is zero (the ratio has no meaning). */
  percent: number | null
}

/** Narrows the wire/form value to a finite number, or `null` when it carries no amount at all. */
function toAmount(value: unknown): number | null {
  if (typeof value === 'number') {
    return Number.isFinite(value) ? value : null
  }
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value)
    return Number.isFinite(parsed) ? parsed : null
  }
  return null
}

/** `null` when either side is missing: a margin on a half-filled record would be a guess, not a number. */
export function computeProductMargin(cost: unknown, price: unknown): ProductMargin | null {
  const costAmount = toAmount(cost)
  const priceAmount = toAmount(price)
  if (costAmount === null || priceAmount === null) {
    return null
  }

  const amount = priceAmount - costAmount

  return {
    amount,
    percent: priceAmount === 0 ? null : (amount / priceAmount) * 100,
  }
}
