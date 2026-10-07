import type { TFunction } from 'i18next'
import { money, plainNumber } from '@/features/work-order-contract-data/contract-data-format'
import type { ContractDataLine, ContractSupplierCommission } from '@/features/work-order-contract-data/types'

/**
 * The readable explanation of how a line's "effective revenue" came out
 * (spec 0201 D-1/D-8/D-9, spec 0202 D-8/D-13), assembled from the figures the
 * server already persisted: nothing here is calculated. The wording follows
 * the direction frozen on the line: RECEIVED = the commission is the revenue,
 * PAID = the revenue is the net amount and the commission is a cost, none =
 * the net amount alone.
 */
export function revenueFormula(line: ContractDataLine, commissionsVisible: boolean, t: TFunction): string {
  const revenue = money(line.effective_revenue)
  const direction = line.supplier_commission_direction
  const commission = line.supplier_commission

  if (direction === 'RECEIVED') {
    return receivedFormula(commission, commissionsVisible, revenue, t)
  }

  const netPart = t('workOrders.contractData.formula.net', {
    quantity: plainNumber(line.quantity),
    unitPrice: money(line.unit_price),
    net: money(line.net_amount),
    revenue,
  })
  if (direction === 'PAID' && commissionsVisible && commission !== null) {
    return t('workOrders.contractData.formula.paid', { net: netPart, commission: commissionDetail(commission, 'paid', t) })
  }
  return netPart
}

function commissionDetail(commission: ContractSupplierCommission, kind: 'received' | 'paid', t: TFunction): string {
  if (commission.commission_type === 'PERCENTAGE' && commission.base_amount !== null) {
    return t(`workOrders.contractData.formula.${kind}Percentage`, {
      rate: plainNumber(commission.value),
      base: money(commission.base_amount),
      amount: money(commission.amount),
    })
  }
  return t(`workOrders.contractData.formula.${kind}Amount`, { amount: money(commission.amount) })
}

function receivedFormula(
  commission: ContractSupplierCommission | null,
  commissionsVisible: boolean,
  revenue: string,
  t: TFunction,
): string {
  if (!commissionsVisible) {
    return t('workOrders.contractData.formula.receivedHidden', { revenue })
  }
  if (commission === null) {
    return t('workOrders.contractData.formula.receivedMissing', { revenue })
  }
  return t('workOrders.contractData.formula.received', {
    commission: commissionDetail(commission, 'received', t),
    revenue,
  })
}

/** "Net - commissions = result", only when the actor sees the commissions. */
export function netOfCommissionsFormula(line: ContractDataLine, t: TFunction): string | null {
  if (line.commissions_amount === null || line.net_of_commissions === null) {
    return null
  }
  return t('workOrders.contractData.formula.netOfCommissions', {
    net: money(line.net_amount),
    commissions: money(line.commissions_amount),
    result: money(line.net_of_commissions),
  })
}
