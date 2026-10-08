import type { SupplierCommissionDirection } from '@/features/product-typologies/types'
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

/**
 * Spec 0202 D-8: a REVENUE row's margin BEFORE its imputed costs, from the
 * direction frozen on the row. RECEIVED: the Supplier commission is the row's
 * revenue (`s - p`); PAID: it is a cost (`n - p - s`); null: the commission
 * does not exist (`n - p`). Imputed costs and generic costs are subtracted by
 * the caller, so row margin and offer margin share this one formula.
 */
export function calculateLineMarginBeforeCosts(
  net: number,
  supplierCommission: number,
  otherCommissions: number,
  direction: SupplierCommissionDirection | null | undefined,
): number {
  if (direction === 'RECEIVED') {
    return roundCommission(supplierCommission - otherCommissions)
  }
  return roundCommission(net - otherCommissions - (direction === 'PAID' ? supplierCommission : 0))
}

type CommissionableLine = {
  quantity: number | null
  unit_price: number | null
  /** Spec 0145: resolves this row's own imputed costs via `costLines` below (D-1). */
  client_key?: string | null
  /** Spec 0202 D-12: frozen on a saved row, taken from the defaults response on a new one. */
  supplier_commission_direction?: SupplierCommissionDirection | null
  commissions?: Array<{ recipient_role: CommissionRole; commission_type: CommissionType; value: number }>
}

type CostLineInput = { offer_line_key?: string | null; quantity: number | null; unit_price: number | null }

/** One row's net and its commission amount per role, on the D-1 base. */
function lineCommissionAmounts(line: CommissionableLine, costNetByKey: Map<string, number>) {
  const net = roundCommission((line.quantity ?? 0) * (line.unit_price ?? 0))
  const allocatedCostNet = line.client_key ? costNetByKey.get(line.client_key) ?? 0 : 0
  const base = calculateCommissionBaseNet(net, allocatedCostNet)
  const perRole: CommissionTotals = { commercial: 0, reporter: 0, supervisor: 0, supplier: 0 }
  for (const commission of line.commissions ?? []) {
    const key = commission.recipient_role.toLowerCase() as Lowercase<CommissionRole>
    perRole[key] = roundCommission(
      perRole[key] + calculateCommissionAmount(commission.commission_type, commission.value, base),
    )
  }
  return { net, perRole }
}

export function calculateCommissionTotals(
  lines: CommissionableLine[],
  costLines: CostLineInput[] = [],
): CommissionTotals {
  const costNetByKey = allocatedCostNetByOfferLineKey(costLines)
  const totals: CommissionTotals = { commercial: 0, reporter: 0, supervisor: 0, supplier: 0 }
  for (const line of lines) {
    const { perRole } = lineCommissionAmounts(line, costNetByKey)
    for (const key of Object.keys(totals) as Array<keyof CommissionTotals>) {
      totals[key] = roundCommission(totals[key] + perRole[key])
    }
  }
  return totals
}

/**
 * Spec 0202 D-8: the live offer margin before costs, i.e. the sum of every
 * row's `calculateLineMarginBeforeCosts`. The caller subtracts ALL cost rows
 * (imputed + generic) once. Without a `commissions` block on the row (channel
 * or permission without commissions) the direction cannot apply: `n - 0 - 0`.
 */
export function calculateMarginBeforeCosts(lines: CommissionableLine[], costLines: CostLineInput[]): number {
  const costNetByKey = allocatedCostNetByOfferLineKey(costLines)
  return lines.reduce((sum, line) => {
    const { net, perRole } = lineCommissionAmounts(line, costNetByKey)
    const direction = line.commissions ? line.supplier_commission_direction : null
    const others = roundCommission(perRole.commercial + perRole.reporter + perRole.supervisor)
    return roundCommission(sum + calculateLineMarginBeforeCosts(net, perRole.supplier, others, direction))
  }, 0)
}
