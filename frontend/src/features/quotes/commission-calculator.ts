import type { CommissionRole, CommissionType } from '@/features/commission-configurations/types'

export function roundCommission(value: number): number {
  return Number(`${Math.round(Number(`${value}e2`))}e-2`)
}

export function calculateCommissionAmount(
  type: CommissionType,
  value: number,
  lineNetAmount: number,
): number {
  return roundCommission(type === 'PERCENTAGE' ? (lineNetAmount * value) / 100 : value)
}

export type CommissionTotals = Record<Lowercase<CommissionRole>, number>

export function calculateCommissionTotals(
  lines: Array<{ quantity: number | null; unit_price: number | null; commissions?: Array<{ recipient_role: CommissionRole; commission_type: CommissionType; value: number }> }>,
): CommissionTotals {
  const totals: CommissionTotals = { commercial: 0, reporter: 0, supervisor: 0, supplier: 0 }
  for (const line of lines) {
    const net = roundCommission((line.quantity ?? 0) * (line.unit_price ?? 0))
    for (const commission of line.commissions ?? []) {
      const key = commission.recipient_role.toLowerCase() as Lowercase<CommissionRole>
      totals[key] = roundCommission(
        totals[key] + calculateCommissionAmount(commission.commission_type, commission.value, net),
      )
    }
  }
  return totals
}
