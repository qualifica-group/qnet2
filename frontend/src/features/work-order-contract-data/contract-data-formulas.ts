import type { TFunction } from 'i18next'
import { money, plainNumber } from '@/features/work-order-contract-data/contract-data-format'
import type { ContractDataLine } from '@/features/work-order-contract-data/types'

/**
 * The readable explanation of how a line's "effective revenue" came out
 * (spec 0201 D-1/D-8/D-9), assembled from the figures the server already
 * persisted: nothing here is calculated.
 */
export function revenueFormula(line: ContractDataLine, commissionsVisible: boolean, t: TFunction): string {
  const revenue = money(line.effective_revenue)

  if (!line.is_institution) {
    return t('workOrders.contractData.formula.consultancy', {
      quantity: plainNumber(line.quantity),
      unitPrice: money(line.unit_price),
      net: money(line.net_amount),
      revenue,
    })
  }

  if (!commissionsVisible) {
    return t('workOrders.contractData.formula.institutionHidden', { revenue })
  }

  const commission = line.supplier_commission
  if (commission === null) {
    return t('workOrders.contractData.formula.institutionMissing', { revenue })
  }

  if (commission.commission_type === 'FIXED_AMOUNT') {
    return t('workOrders.contractData.formula.institutionFixed', { amount: money(commission.amount), revenue })
  }

  if (commission.base_amount === null) {
    return t('workOrders.contractData.formula.institutionPercentageNoBase', {
      rate: plainNumber(commission.value),
      amount: money(commission.amount),
      revenue,
    })
  }

  return t('workOrders.contractData.formula.institutionPercentage', {
    rate: plainNumber(commission.value),
    base: money(commission.base_amount),
    amount: money(commission.amount),
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
