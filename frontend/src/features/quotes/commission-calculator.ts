import type { CommissionRole, CommissionType } from '@/features/commission-configurations/types'

export function roundCommission(value: number): number {
  return Number(`${Math.round(Number(`${value}e2`))}e-2`)
}

/**
 * Spec 0145 (D-1/D-2): the base a PERCENTAGE commission applies to is the
 * revenue row's own net minus whatever COST net is imputed to it (spec
 * 0144's `offer_line_id`/`offer_line_key`) — clamped at zero, never negative.
 * FIXED_AMOUNT ignores the base entirely (`calculateCommissionAmount` below).
 * The ONE FE function for this rule (constraint: "una funzione pura riusata
 * da riepilogo, dialog e margini per prodotto") — see `calculateCommissionTotals`,
 * `quote-commissions-dialog.tsx` and `quote-product-margins-calc.ts`.
 */
export function calculateCommissionBaseNet(lineNet: number, allocatedCostNet: number): number {
  return Math.max(0, roundCommission(lineNet - allocatedCostNet))
}

export function calculateCommissionAmount(
  type: CommissionType,
  value: number,
  base: number,
): number {
  return roundCommission(type === 'PERCENTAGE' ? (base * value) / 100 : value)
}

export type CommissionTotals = Record<Lowercase<CommissionRole>, number>

/**
 * Spec 0145 (D-1): buckets each COST row's net onto its associated OFFER
 * row's `client_key` (spec 0144's `offer_line_key`), or drops it — a generic
 * (unassociated) cost never reduces any commission base. Shared by every FE
 * consumer of the base rule (`calculateCommissionTotals` below,
 * `quote-offer-tab.tsx`'s per-row dialog wiring, `quote-product-margins-calc.ts`).
 */
export function allocatedCostNetByOfferLineKey(
  costLines: Array<{ offer_line_key?: string | null; quantity: number | null; unit_price: number | null }>,
): Map<string, number> {
  const costNetByKey = new Map<string, number>()
  for (const cost of costLines) {
    if (!cost.offer_line_key) {
      continue
    }
    const net = roundCommission((cost.quantity ?? 0) * (cost.unit_price ?? 0))
    costNetByKey.set(cost.offer_line_key, roundCommission((costNetByKey.get(cost.offer_line_key) ?? 0) + net))
  }
  return costNetByKey
}

/** Spec 0145 (D-3): the live margin's own subtraction, over every role at once. */
export function sumCommissionTotals(totals: CommissionTotals): number {
  return roundCommission(totals.commercial + totals.reporter + totals.supervisor + totals.supplier)
}

export function calculateCommissionTotals(
  lines: Array<{
    quantity: number | null
    unit_price: number | null
    /** Spec 0145: resolves this row's own imputed costs via `costLines` below (D-1). */
    client_key?: string | null
    commissions?: Array<{ recipient_role: CommissionRole; commission_type: CommissionType; value: number }>
  }>,
  costLines: Array<{ offer_line_key?: string | null; quantity: number | null; unit_price: number | null }> = [],
): CommissionTotals {
  const costNetByKey = allocatedCostNetByOfferLineKey(costLines)
  const totals: CommissionTotals = { commercial: 0, reporter: 0, supervisor: 0, supplier: 0 }
  for (const line of lines) {
    const net = roundCommission((line.quantity ?? 0) * (line.unit_price ?? 0))
    const allocatedCostNet = line.client_key ? costNetByKey.get(line.client_key) ?? 0 : 0
    const base = calculateCommissionBaseNet(net, allocatedCostNet)
    for (const commission of line.commissions ?? []) {
      const key = commission.recipient_role.toLowerCase() as Lowercase<CommissionRole>
      totals[key] = roundCommission(
        totals[key] + calculateCommissionAmount(commission.commission_type, commission.value, base),
      )
    }
  }
  return totals
}
