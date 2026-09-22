/**
 * "Margine per prodotto" pure calc (spec 0144, D-6): for each OFFER row that
 * carries a product, its net revenue, the net cost imputed to it and the
 * resulting margin (can be negative); every COST row with no (or a stale)
 * association falls into a single "Costi generici" bucket. Client-side only —
 * no new persisted field, no server round trip (D-6). Rendered by the sibling
 * `quote-product-margins.tsx` (kept a SEPARATE file, not same-basename, to
 * avoid an ambiguous `.ts`/`.tsx` extensionless import resolution).
 *
 * `computeProductMargins` is decoupled from both the form's
 * `QuoteLineFormValues` and the persisted `QuoteLine`, mirrors
 * `quote-totals.ts`'s own `QuoteLineForTotals` split: the mapping helpers
 * below bridge each of the two shapes onto it, so a live edit and an
 * already-saved quote render the exact same component from the exact same
 * numbers.
 */

import { allocatedCostNetByOfferLineKey, calculateCommissionAmount, calculateCommissionBaseNet } from '@/features/quotes/commission-calculator'
import { isPristineLineRow, lineClientKey } from '@/features/quotes/quote-line-values'
import { round2 } from '@/features/quotes/quote-totals'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { QuoteLine } from '@/features/quotes/types'

/** One OFFER row eligible for a margin bucket. */
export interface ProductMarginProductInput {
  /** Stable identity matching a cost's `offerLineKey` (`client_key`/`line-<id>`, spec 0144 D-7). */
  key: string
  productName: string | null
  /** 1-based position among the offer's rows, for the "riga N" label. */
  rowNumber: number
  net: number
  /**
   * Spec 0145 (D-9): this row's own commission total, already resolved on
   * the D-1 base by the caller (`productLinesFromFormOfferLines`'s live
   * recompute, `productLinesFromPersistedOfferLines`'s sum of persisted
   * `calculated_amount`). Without the `commissions` field permission the
   * whole block is hidden (`quote-product-margins.tsx`), never a margin
   * computed without them.
   */
  commissionsNet: number
}

/** One COST row's contribution, attributed or generic. */
export interface ProductMarginCostInput {
  /** `null` = generic cost; a key with no matching product input also falls back to generic (defensive). */
  offerLineKey: string | null
  net: number
}

export interface ProductMarginRow {
  key: string
  productName: string | null
  rowNumber: number
  revenueNet: number
  costNet: number
  commissionsNet: number
  margin: number
}

export interface ProductMarginsSummary {
  rows: ProductMarginRow[]
  genericCostNet: number
}

/**
 * Every cost's net lands EITHER on its product row's `costNet` OR on
 * `genericCostNet` — never dropped — so `sum(rows.margin) - genericCostNet`
 * always equals `revenue.net - cost.net` (the header's own margin, D-6),
 * whatever the association state of any individual cost.
 */
export function computeProductMargins(
  productLines: ProductMarginProductInput[],
  costLines: ProductMarginCostInput[],
): ProductMarginsSummary {
  const productKeys = new Set(productLines.map((line) => line.key))
  const costNetByKey = new Map<string, number>()
  let genericCostNet = 0

  for (const cost of costLines) {
    if (cost.offerLineKey !== null && productKeys.has(cost.offerLineKey)) {
      costNetByKey.set(cost.offerLineKey, round2((costNetByKey.get(cost.offerLineKey) ?? 0) + cost.net))
      continue
    }
    genericCostNet = round2(genericCostNet + cost.net)
  }

  const rows = productLines.map((line) => {
    const costNet = costNetByKey.get(line.key) ?? 0
    return {
      key: line.key,
      productName: line.productName,
      rowNumber: line.rowNumber,
      revenueNet: line.net,
      costNet,
      commissionsNet: line.commissionsNet,
      // Spec 0145 (D-9): net of commissions when known, unchanged (pre-0145)
      // when the caller has no visibility into them (D-9's documented gap).
      margin: round2(line.net - costNet - line.commissionsNet),
    }
  })

  return { rows, genericCostNet: round2(genericCostNet) }
}

/**
 * Live form mapping (spec 0144/0145): every OFFER row carrying a product,
 * net computed the same way the live summary does, plus its own commission
 * total recomputed on the D-1 base (this row's net minus the SAME costLines'
 * imputed net `computeProductMargins` below buckets onto `costNet` — kept in
 * sync via the shared `allocatedCostNetByOfferLineKey`, not duplicated math).
 */
export function productLinesFromFormOfferLines(
  offerLines: QuoteLineFormValues[],
  costLines: QuoteLineFormValues[],
  productNameFor: (productId: number) => string | null,
): ProductMarginProductInput[] {
  const costNetByKey = allocatedCostNetByOfferLineKey(costLines)

  return offerLines.reduce<ProductMarginProductInput[]>((lines, row, index) => {
    if (row.product_id === null) {
      return lines
    }
    const key = row.client_key ?? `index-${index}`
    const net = round2((row.quantity ?? 0) * (row.unit_price ?? 0))
    const base = calculateCommissionBaseNet(net, costNetByKey.get(key) ?? 0)
    const commissionsNet = round2(
      (row.commissions ?? []).reduce(
        (sum, commission) => round2(sum + calculateCommissionAmount(commission.commission_type, commission.value, base)),
        0,
      ),
    )
    lines.push({
      key,
      productName: productNameFor(row.product_id),
      rowNumber: index + 1,
      net,
      commissionsNet,
    })
    return lines
  }, [])
}

/** Live form mapping (spec 0144): every real (non-pristine) COST row, generic unless it carries a live `offer_line_key`. */
export function costLinesFromFormCostLines(costLines: QuoteLineFormValues[]): ProductMarginCostInput[] {
  return costLines
    .filter((row) => !isPristineLineRow(row))
    .map((row) => ({
      offerLineKey: row.offer_line_key ?? null,
      net: round2((row.quantity ?? 0) * (row.unit_price ?? 0)),
    }))
}

/**
 * Detail mapping (spec 0144/0145): persisted OFFER rows, using the
 * CONGEALED `net_amount` (never recomputed) and the sum of each row's
 * already server-computed `calculated_amount` — never recomputed FE-side, the
 * server ran D-1/D-6 at save time and its numbers are authoritative.
 */
export function productLinesFromPersistedOfferLines(offerLines: QuoteLine[]): ProductMarginProductInput[] {
  return offerLines.map((line, index) => ({
    key: lineClientKey(line.id),
    productName: line.product.name,
    rowNumber: index + 1,
    net: Number(line.net_amount),
    commissionsNet: round2((line.commissions ?? []).reduce((sum, commission) => sum + Number(commission.calculated_amount), 0)),
  }))
}

/** Detail mapping (spec 0144): persisted COST rows, `offer_line_id` resolved to the SAME `line-<id>` scheme as the offer row's own key. */
export function costLinesFromPersistedCostLines(costLines: QuoteLine[]): ProductMarginCostInput[] {
  return costLines.map((line) => ({
    offerLineKey: line.offer_line_id !== null && line.offer_line_id !== undefined ? lineClientKey(line.offer_line_id) : null,
    net: Number(line.net_amount),
  }))
}
