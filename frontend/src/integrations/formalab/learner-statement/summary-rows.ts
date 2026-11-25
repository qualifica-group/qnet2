import type { TFunction } from 'i18next'
import type { LearnerStatement, Tranche } from '@/integrations/formalab/learner-statement/types'
import { euro, weight } from '@/integrations/formalab/learner-statement/format'

/** Colour family of a scenario block (legacy: green, amber, teal, blue, dark totals). */
export type SummaryTone = 'neutral' | 'economic' | 'advance' | 'forecastBalance' | 'balance' | 'forecastTotal' | 'actualTotal'

export type SummaryRow =
  | { kind: 'header'; title: string; subtitle?: string; badge?: string; tone: SummaryTone }
  | {
      kind: 'amount'
      label: string
      value: number
      /** Bold label and amount. */
      strong?: boolean
      /** Grey "minus" line (costs, commissions). */
      muted?: boolean
      /** Row tinted with its block colour (TOTALE RICAVI) or the MOL tint. */
      fill?: SummaryTone | 'mol' | 'total'
      /** Red when below zero (tranche profits). */
      signed?: boolean
      notes?: string[]
      tone: SummaryTone
    }

type T = TFunction<'formalab'>

const K = 'learnerStatement.summary.'

/**
 * The rows of the "Riepilogo Costi e Ricavi" column, in the legacy order:
 * costs, forecast, then the blocks of the payment state (spec 0427 AC-005).
 */
export function summaryRows(statement: LearnerStatement, t: T): SummaryRow[] {
  const { costs, summary } = statement
  const perLearner = t(`${K}profitPerLearner`, { count: summary.learners_count })
  const rows: SummaryRow[] = [
    { kind: 'amount', label: t(`${K}learnerCosts`), value: costs.learners, strong: true, tone: 'neutral' },
    ...costs.other_lines.map((line): SummaryRow => ({
      kind: 'amount',
      label: line.note !== '' ? `${line.title} (${line.note})` : line.title,
      value: line.amount,
      muted: true,
      tone: 'neutral',
    })),
    { kind: 'amount', label: t(`${K}otherCosts`), value: costs.other_total, strong: true, tone: 'neutral' },
    { kind: 'amount', label: t(`${K}totalCosts`), value: costs.total, strong: true, fill: 'total', tone: 'neutral' },
    { kind: 'header', title: t(`${K}economic`), tone: 'economic' },
    { kind: 'amount', label: t(`${K}learnerRevenue`), value: summary.learner_revenue, strong: true, tone: 'economic' },
    { kind: 'amount', label: t(`${K}trainingRevenue`), value: summary.training_revenue, tone: 'economic' },
    { kind: 'amount', label: t(`${K}totalRevenue`), value: summary.total_revenue, strong: true, fill: 'economic', tone: 'economic' },
    { kind: 'amount', label: t(`${K}partialProfit`), value: summary.partial_profit, strong: true, tone: 'economic' },
    ...commissionRows(t, summary.commissions, summary.mol, '', 'economic'),
    { kind: 'amount', label: perLearner, value: summary.profit_per_learner, strong: true, tone: 'economic' },
  ]

  const advanceOnly = statement.payment_state === 'advance_only'
  if (summary.advance) {
    rows.push(
      {
        kind: 'header',
        title: t(advanceOnly ? `${K}advanceActual` : `${K}advance`),
        subtitle: t(advanceOnly ? `${K}weightOnForecast` : `${K}weight`, { weight: weight(summary.advance.weight) }),
        tone: 'advance',
      },
      { kind: 'amount', label: t(`${K}advanceAmount`), value: summary.advance.amount, strong: true, tone: 'advance' },
      ...trancheRows(t, summary.advance, 'advance', 'tranche', perLearner),
    )
  }
  if (summary.forecast_balance) {
    rows.push(
      {
        kind: 'header',
        title: t(`${K}forecastBalance`),
        badge: t(`${K}forecastBadge`),
        subtitle: t(`${K}forecastBalanceWeight`, { weight: weight(summary.forecast_balance.weight) }),
        tone: 'forecastBalance',
      },
      {
        kind: 'amount',
        label: t(`${K}forecastBalanceAmount`),
        value: summary.forecast_balance.amount,
        strong: true,
        notes: [t(`${K}forecastBalanceNote`)],
        tone: 'forecastBalance',
      },
      ...trancheRows(t, summary.forecast_balance, 'forecastBalance', 'estimate', perLearner),
    )
  }
  if (summary.balance) {
    rows.push(
      { kind: 'header', title: t(`${K}balance`), subtitle: t(`${K}weight`, { weight: weight(summary.balance.weight) }), tone: 'balance' },
      { kind: 'amount', label: t(`${K}balanceAmount`), value: summary.balance.amount, strong: true, tone: 'balance' },
      ...trancheRows(t, summary.balance, 'balance', 'tranche', perLearner),
    )
  }
  if (summary.single_payment) {
    const block = summary.single_payment
    rows.push(
      { kind: 'header', title: t(`${K}singlePayment`), subtitle: t(`${K}singlePaymentSubtitle`), tone: 'balance' },
      { kind: 'amount', label: t(`${K}learnerRevenue`), value: block.learner_revenue, tone: 'balance' },
      { kind: 'amount', label: t(`${K}trainingRevenue`), value: block.training_revenue, tone: 'balance' },
      { kind: 'amount', label: t(`${K}balanceAmount`), value: block.balance, strong: true, fill: 'balance', tone: 'balance' },
      {
        kind: 'amount',
        label: t(`${K}totalRevenue`),
        value: block.total_revenue,
        strong: true,
        fill: 'balance',
        notes: differenceNotes(t, block.difference),
        tone: 'balance',
      },
      { kind: 'amount', label: t(`${K}partialProfit`), value: block.partial_profit, strong: true, tone: 'balance' },
      ...commissionRows(t, block.commissions, block.mol, '', 'balance'),
      { kind: 'amount', label: perLearner, value: block.profit_per_learner, strong: true, tone: 'balance' },
    )
  }
  if (summary.forecast_total) {
    const block = summary.forecast_total
    rows.push(
      { kind: 'header', title: t(`${K}forecastTotal`), subtitle: t(`${K}forecastTotalSubtitle`), tone: 'forecastTotal' },
      { kind: 'amount', label: t(`${K}totalRevenue`), value: block.total_revenue, strong: true, notes: [t(`${K}forecastTotalNote`)], tone: 'forecastTotal' },
      { kind: 'amount', label: t(`${K}minusTotalCosts`), value: block.total_costs, muted: true, tone: 'forecastTotal' },
      { kind: 'amount', label: t(`${K}partialProfit`), value: block.partial_profit, strong: true, tone: 'forecastTotal' },
      ...commissionRows(t, block.commissions, block.mol, 'Totals', 'forecastTotal'),
      { kind: 'amount', label: perLearner, value: block.profit_per_learner, strong: true, tone: 'forecastTotal' },
    )
  }
  if (summary.actual_total) {
    const block = summary.actual_total
    rows.push(
      { kind: 'header', title: t(`${K}actualTotal`), subtitle: t(`${K}actualTotalSubtitle`), tone: 'actualTotal' },
      {
        kind: 'amount',
        label: t(`${K}totalRevenue`),
        value: block.total_revenue,
        strong: true,
        notes: [t(`${K}actualTotalNote`), ...differenceNotes(t, block.difference)],
        tone: 'actualTotal',
      },
      { kind: 'amount', label: t(`${K}minusTotalCosts`), value: block.total_costs, muted: true, tone: 'actualTotal' },
      { kind: 'amount', label: t(`${K}partialProfit`), value: block.partial_profit, strong: true, signed: true, tone: 'actualTotal' },
      ...commissionRows(t, block.commissions, block.mol, 'Totals', 'actualTotal'),
      { kind: 'amount', label: perLearner, value: block.profit_per_learner, strong: true, tone: 'actualTotal' },
    )
  }

  return rows
}

/** "- Provvigioni Partner" + "MOL Qualifica", only when there are commissions. */
function commissionRows(t: T, commissions: number, mol: number, suffix: '' | 'Totals' | 'Tranche' | 'Estimate', tone: SummaryTone): SummaryRow[] {
  if (commissions <= 0) {
    return []
  }

  return [
    { kind: 'amount', label: t(`${K}commissions${suffix}`), value: commissions, muted: true, tone },
    { kind: 'amount', label: t(`${K}mol${suffix}`), value: mol, strong: true, fill: 'mol', tone },
  ]
}

function trancheRows(t: T, tranche: Tranche, tone: SummaryTone, kind: 'tranche' | 'estimate', perLearner: string): SummaryRow[] {
  return [
    { kind: 'amount', label: t(`${K}trancheCosts`, { weight: weight(tranche.weight) }), value: tranche.costs, muted: true, tone },
    { kind: 'amount', label: t(`${K}trancheProfit`), value: tranche.partial_profit, strong: true, signed: true, tone },
    ...commissionRows(t, tranche.commissions, tranche.mol, kind === 'tranche' ? 'Tranche' : 'Estimate', tone),
    { kind: 'amount', label: perLearner, value: tranche.profit_per_learner, strong: true, tone },
  ]
}

/** "(+€ x vs previsto)" when what was received differs from the forecast. */
function differenceNotes(t: T, difference: number): string[] {
  return difference === 0 ? [] : [t(`${K}difference`, { amount: `${difference >= 0 ? '+' : ''}${euro(difference)}` })]
}
