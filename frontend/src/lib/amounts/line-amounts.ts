/**
 * Client-side PREVIEW of document and cost amounts (spec 0253 D-8): the server
 * recomputes everything on save with the bcmath engine (LineAmountCalculator).
 * Integer math only (BigInt for the quantity x price product), every rounding
 * half away from zero, so preview and saved values are identical.
 */

/** Amounts of a line or of a document, in integer cents. */
export interface AmountsCents {
  net: number
  vat: number
  total: number
}

/** Input line of the engine. */
export interface AmountLineInput {
  quantity: number
  unitPrice: number
  /** VAT rate percentage (22 for 22%); null or 0 means no VAT. */
  vatRate: number | null
}

export interface DocumentAmounts {
  /** Per-line amounts, VAT apportioned from the per-rate document VAT. */
  lines: AmountsCents[]
  totals: AmountsCents
}

/** Decimals kept of the VAT rate percentage. */
const RATE_DECIMALS = 2
const CENTS_DECIMALS = 2

export const EMPTY_AMOUNTS_CENTS: AmountsCents = { net: 0, vat: 0, total: 0 }

/** Decimal number -> integer at `decimals` places, half away from zero, via the decimal string (no 1.005 * 100 drift). */
function toScaledInt(value: number, decimals: number): bigint {
  if (!Number.isFinite(value)) {
    return 0n
  }
  const abs = Math.abs(value)
  // Exponent-notation strings ("1e-7") cannot take another exponent suffix: fall back to plain math.
  const shifted = Number(`${abs}e${decimals}`)
  const rounded = BigInt(Math.round(Number.isNaN(shifted) ? abs * 10 ** decimals : shifted))
  return value < 0 ? -rounded : rounded
}

/** numerator / 10^shift rounded half away from zero. */
function divideRounded(numerator: bigint, shift: number): bigint {
  const divisor = 10n ** BigInt(shift)
  const abs = numerator < 0n ? -numerator : numerator
  const rounded = (abs * 2n + divisor) / (divisor * 2n)
  return numerator < 0n ? -rounded : rounded
}

/** Decimal amount -> integer cents. */
export function toCents(amount: number): number {
  return Number(toScaledInt(amount, CENTS_DECIMALS))
}

/** Integer cents -> decimal amount with 2 decimals of precision. */
export function fromCents(cents: number): number {
  return cents / 100
}

/** Rounds a decimal amount to 2 decimals (half away from zero). */
export function round2(amount: number): number {
  return fromCents(toCents(amount))
}

function netCents(quantity: number, unitPrice: number, quantityScale: number): bigint {
  const scaledQuantity = toScaledInt(quantity, quantityScale)
  const priceCents = toScaledInt(unitPrice, CENTS_DECIMALS)
  return divideRounded(scaledQuantity * priceCents, quantityScale)
}

function vatOfNet(net: bigint, vatRate: number | null): bigint {
  if (vatRate === null) {
    return 0n
  }
  return divideRounded(net * toScaledInt(vatRate, RATE_DECIMALS), RATE_DECIMALS + 2)
}

/**
 * One line on its own: quantity rounded to the document scale, unit price to
 * cents (as the server engine does), net = qty x price rounded to cents, VAT = net x rate rounded to cents (null rate = 0).
 */
export function lineAmounts(
  quantity: number,
  unitPrice: number,
  vatRate: number | null,
  quantityScale: number,
): AmountsCents {
  const net = netCents(quantity, unitPrice, quantityScale)
  const vat = vatOfNet(net, vatRate)
  return { net: Number(net), vat: Number(vat), total: Number(net + vat) }
}

/**
 * Fiscal document: VAT computed once per rate on the summed net (as in the
 * electronic invoice) and apportioned to the lines; the cents of difference go
 * to the line with the largest net of that rate (the first on ties).
 */
export function documentAmountsPerRate(lines: readonly AmountLineInput[], quantityScale: number): DocumentAmounts {
  const nets = lines.map((line) => netCents(line.quantity, line.unitPrice, quantityScale))
  const vats = lines.map((line, i) => vatOfNet(nets[i], line.vatRate))

  const indexesByRate = new Map<number, number[]>()
  lines.forEach((line, i) => {
    const rate = line.vatRate ?? 0
    indexesByRate.set(rate, [...(indexesByRate.get(rate) ?? []), i])
  })

  for (const [rate, indexes] of indexesByRate) {
    const rateNet = indexes.reduce((sum, i) => sum + nets[i], 0n)
    const rateVat = vatOfNet(rateNet, rate)
    const remainder = rateVat - indexes.reduce((sum, i) => sum + vats[i], 0n)
    const target = indexes.reduce((best, i) => (nets[i] > nets[best] ? i : best), indexes[0])
    vats[target] += remainder
  }

  const amounts = lines.map((_, i) => ({ net: Number(nets[i]), vat: Number(vats[i]), total: Number(nets[i] + vats[i]) }))
  const totals = amounts.reduce<AmountsCents>(
    (acc, line) => ({ net: acc.net + line.net, vat: acc.vat + line.vat, total: acc.total + line.total }),
    EMPTY_AMOUNTS_CENTS,
  )
  return { lines: amounts, totals }
}
