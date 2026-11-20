import type { TFunction } from 'i18next'
import type { HintContent } from '@/components/calculation-hint'
import { money, plainNumber } from '@/features/work-order-contract-data/contract-data-format'
import type { ContractDataLine, ContractDataTotals } from '@/features/work-order-contract-data/types'

/**
 * The explanations shown in the calculation hints (spec 0201 D-1/D-8/D-9),
 * assembled from the figures the server already persisted: nothing here is
 * calculated. Per line only the amount (spec 0403); the commissions and the
 * revenue are explained on the totals.
 */

function netAmountLine(line: ContractDataLine, t: TFunction): string {
  return t('workOrders.contractData.hint.netAmountFormula', {
    quantity: plainNumber(line.quantity),
    unitPrice: money(line.unit_price),
    net: money(line.net_amount),
  })
}

export function netAmountHint(line: ContractDataLine, t: TFunction): HintContent {
  return {
    title: t('workOrders.contractData.fields.amount'),
    lines: [t('workOrders.contractData.hint.netAmountRule'), netAmountLine(line, t)],
  }
}

export type TotalsHintKind = 'revenue' | 'commissions' | 'netOfCommissions'

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
