import type { TFunction } from 'i18next'
import type { HintContent } from '@/components/calculation-hint'
import {
  calculateCommissionAmount,
  calculateCommissionBaseNet,
} from '@/features/quotes/commission-calculator'
import type { ProductMarginRow } from '@/features/quotes/quote-product-margins-calc'
import { formatQuoteAmount as money } from '@/features/quotes/quote-summary'
import type { QuoteLineCommission } from '@/features/quotes/types'

/**
 * The explanations behind every amount of the quote's "Dettaglio provvigioni"
 * and "Margine per prodotto" (user directive 2026-10-23, the work order's
 * calculation hints): assembled from the amounts already shown, the same
 * formulas the server and `commission-calculator.ts` apply (spec 0145 D-1,
 * spec 0202 D-8).
 */

/** "a + b + c = total", or the lone amount when there is only one. */
function sumFormula(amounts: number[], total: number): string {
  return amounts.length > 1 ? `${amounts.map(money).join(' + ')} = ${money(total)}` : money(total)
}

interface CommissionHintInput {
  commission: QuoteLineCommission
  title: string
  lineNet: number
  allocatedCostNet: number
}

/** Percentage: the base (net minus imputed costs, never below zero) and the rate on it; fixed: the amount itself. */
export function commissionAmountHint({ commission, title, lineNet, allocatedCostNet }: CommissionHintInput, t: TFunction): HintContent {
  const saved = Number(commission.calculated_amount)
  if (commission.commission_type !== 'PERCENTAGE') {
    return { title, lines: [t('quotes.calculationHints.fixedAmount', { amount: money(saved) })] }
  }

  const base = calculateCommissionBaseNet(lineNet, allocatedCostNet)
  const computed = calculateCommissionAmount('PERCENTAGE', Number(commission.value), base)
  const lines = [
    t('quotes.calculationHints.baseRule'),
    t('quotes.calculationHints.baseFormula', { net: money(lineNet), costs: money(allocatedCostNet), base: money(base) }),
    t('quotes.calculationHints.percentageFormula', { rate: Number(commission.value), base: money(base), amount: money(computed) }),
  ]
  if (computed !== saved) {
    lines.push(t('quotes.calculationHints.savedAmount', { amount: money(saved) }))
  }
  return { title, lines }
}

/** A total: what it adds up and the sum. */
export function sumHint(title: string, rule: string, amounts: number[], total: number): HintContent {
  return { title, lines: [rule, sumFormula(amounts, total)] }
}

/** Revenue of a product row: its net amount, or the received Supplier commission (spec 0202 D-8). */
export function revenueHint(row: ProductMarginRow, t: TFunction): HintContent {
  const title = t('quotes.columns.revenueNet')
  if (row.supplierCommissionDirection === 'RECEIVED') {
    return {
      title,
      lines: [
        t('quotes.calculationHints.receivedRevenue', { amount: money(row.revenueNet) }),
        t('quotes.calculationHints.receivedRevenueNet', { net: money(row.lineNet) }),
      ],
    }
  }
  return { title, lines: [t('quotes.calculationHints.lineNetRevenue', { amount: money(row.revenueNet) })] }
}

export function costHint(row: ProductMarginRow, t: TFunction): HintContent {
  return { title: t('quotes.columns.costNet'), lines: [t('quotes.calculationHints.imputedCosts', { amount: money(row.costNet) })] }
}

/** Commissions of a product row: on a received row only the paid ones; on a paid one the Supplier commission is among them. */
export function commissionsHint(row: ProductMarginRow, t: TFunction): HintContent {
  const title = t('quotes.columns.commissionsNet')
  if (row.supplierCommissionDirection === 'RECEIVED') {
    return { title, lines: [t('quotes.calculationHints.paidCommissionsOnly', { amount: money(row.commissionsNet) })] }
  }
  const lines = [t('quotes.calculationHints.rowCommissions', { amount: money(row.commissionsNet) })]
  if (row.supplierCommissionDirection === 'PAID' && row.supplierCommissionNet > 0) {
    lines.push(t('quotes.calculationHints.paidSupplierIncluded', { amount: money(row.supplierCommissionNet) }))
  }
  return { title, lines }
}

export function marginHint(row: ProductMarginRow, t: TFunction): HintContent {
  return {
    title: t('quotes.columns.marginNet'),
    lines: [
      t('quotes.calculationHints.marginRule'),
      t('quotes.calculationHints.marginFormula', {
        revenue: money(row.revenueNet),
        costs: money(row.costNet),
        commissions: money(row.commissionsNet),
        margin: money(row.margin),
      }),
    ],
  }
}
