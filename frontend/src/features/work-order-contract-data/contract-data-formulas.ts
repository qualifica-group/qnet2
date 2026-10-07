import type { TFunction } from 'i18next'
import type { HintContent } from '@/features/work-order-contract-data/calculation-hint'
import { money, plainNumber } from '@/features/work-order-contract-data/contract-data-format'
import type {
  ContractDataLine,
  ContractDataTotals,
  ContractSupplierCommission,
} from '@/features/work-order-contract-data/types'

/**
 * The explanations shown in the calculation hints (spec 0201 D-1/D-8/D-9, spec
 * 0202 D-8/D-13), assembled from the figures the server already persisted:
 * nothing here is calculated. The wording follows the direction frozen on the
 * line: RECEIVED = the commission is the revenue, PAID = the revenue is the net
 * amount and the commission is a cost, none = the net amount alone.
 */

function netAmountLine(line: ContractDataLine, t: TFunction): string {
  return t('workOrders.contractData.hint.netAmountFormula', {
    quantity: plainNumber(line.quantity),
    unitPrice: money(line.unit_price),
    net: money(line.net_amount),
  })
}

/** "10% of 2,000.00 = 200.00" or "fixed 300.00". */
function commissionDetail(commission: ContractSupplierCommission, t: TFunction): string {
  if (commission.commission_type === 'PERCENTAGE' && commission.base_amount !== null) {
    return t('workOrders.contractData.hint.commissionPercentage', {
      rate: plainNumber(commission.value),
      base: money(commission.base_amount),
      amount: money(commission.amount),
    })
  }
  return t('workOrders.contractData.hint.commissionFixed', { amount: money(commission.amount) })
}

export function netAmountHint(line: ContractDataLine, t: TFunction): HintContent {
  return {
    title: t('workOrders.contractData.columns.netAmount'),
    lines: [t('workOrders.contractData.hint.netAmountRule'), netAmountLine(line, t)],
  }
}

/** Direction, type (percentage/fixed), base (the line's margin), amount and the stale note; null without a commission. */
export function commissionHint(line: ContractDataLine, t: TFunction): HintContent | null {
  const commission = line.supplier_commission
  if (commission === null) {
    return null
  }
  const direction = line.supplier_commission_direction
  const title =
    direction === null
      ? t('workOrders.contractData.columns.supplierCommission')
      : t(`workOrders.contractData.hint.commissionTitle.${direction}`)
  const lines = [commissionDetail(commission, t)]
  if (commission.commission_type === 'PERCENTAGE') {
    lines.push(t('workOrders.contractData.hint.commissionBase'))
  }
  if (commission.is_stale) {
    lines.push(t('workOrders.contractData.warnings.staleCommissionBase'))
  }
  return { title, lines }
}

/** "Net - commissions = result", only when the actor sees the commissions. */
export function netOfCommissionsHint(line: ContractDataLine, t: TFunction): HintContent | null {
  if (line.commissions_amount === null || line.net_of_commissions === null) {
    return null
  }
  return {
    title: t('workOrders.contractData.columns.netOfCommissions'),
    lines: [
      t('workOrders.contractData.hint.netOfCommissionsRule'),
      t('workOrders.contractData.hint.netOfCommissionsFormula', {
        net: money(line.net_amount),
        commissions: money(line.commissions_amount),
        result: money(line.net_of_commissions),
      }),
    ],
  }
}

/** Why the revenue is what it is: the received commission, or the net amount (paid / not calculated). */
export function revenueHint(line: ContractDataLine, commissionsVisible: boolean, t: TFunction): HintContent {
  const revenue = money(line.effective_revenue)
  const direction = line.supplier_commission_direction
  const title = t('workOrders.contractData.columns.effectiveRevenue')

  if (direction === 'RECEIVED') {
    const commission = line.supplier_commission
    let detail = t('workOrders.contractData.hint.revenueOnly', { revenue })
    if (commissionsVisible) {
      detail =
        commission === null
          ? t('workOrders.contractData.hint.receivedMissing', { revenue })
          : t('workOrders.contractData.hint.revenueFrom', { detail: commissionDetail(commission, t), revenue })
    }
    return { title, lines: [t('workOrders.contractData.hint.revenueReceived'), detail] }
  }

  const reason = direction === 'PAID' ? 'revenuePaid' : 'revenueNone'
  return {
    title,
    lines: [
      t(`workOrders.contractData.hint.${reason}`),
      t('workOrders.contractData.hint.revenueFrom', { detail: netAmountLine(line, t), revenue }),
    ],
  }
}

export type TotalsHintKind = 'netAmount' | 'revenue' | 'commissions' | 'netOfCommissions'

export function totalsHint(kind: TotalsHintKind, totals: ContractDataTotals, t: TFunction): HintContent {
  const title = t(`workOrders.contractData.totals.${kind}`)
  const rule = t(`workOrders.contractData.hint.totals.${kind}`)
  if (kind === 'netOfCommissions' && totals.commissions_amount !== null && totals.net_of_commissions !== null) {
    return {
      title,
      lines: [
        rule,
        t('workOrders.contractData.hint.netOfCommissionsFormula', {
          net: money(totals.net_amount),
          commissions: money(totals.commissions_amount),
          result: money(totals.net_of_commissions),
        }),
      ],
    }
  }
  return { title, lines: [rule] }
}
