import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { summaryRows, type SummaryRow } from '@/integrations/formalab/learner-statement/summary-rows'
import { buildStatement, buildTranche } from '@/integrations/formalab/learner-statement/test-fixtures'

const t = i18n.getFixedT('it', 'formalab')

const headers = (rows: SummaryRow[]) => rows.flatMap((row) => (row.kind === 'header' ? [row.title] : []))

describe('summaryRows', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('it')
  })

  it('shows only the economic scenario, with MOL, when nothing was received', () => {
    const rows = summaryRows(buildStatement(), t)

    expect(headers(rows)).toEqual(['SCENARIO ECONOMICO'])
    expect(rows.some((row) => row.kind === 'amount' && row.label === 'FOR_Docenti (Docente uno)')).toBe(true)
    expect(rows.some((row) => row.kind === 'amount' && row.label === 'MOL Qualifica' && row.value === 34240.96)).toBe(true)
    expect(rows.at(-1)).toMatchObject({ label: 'Utile a discente (10)', value: 4028.347 })
  })

  it('hides commissions and MOL when there are no commissions', () => {
    const statement = buildStatement()
    const rows = summaryRows({ ...statement, summary: { ...statement.summary, commissions: 0 } }, t)

    expect(rows.some((row) => row.kind === 'amount' && row.label.startsWith('MOL'))).toBe(false)
  })

  it('state A: actual advance, forecast balance and forecast total', () => {
    const statement = buildStatement({ payment_state: 'advance_only' })
    const summary = { ...statement.summary, advance: buildTranche(), forecast_balance: buildTranche({ amount: 32523.67 }), forecast_total: { total_revenue: 52523.67, total_costs: 12240.2, partial_profit: 40283.47, commissions: 0, mol: 40283.47, profit_per_learner: 4028.347 } }

    expect(headers(summaryRows({ ...statement, summary }, t))).toEqual([
      'SCENARIO ECONOMICO',
      'SCENARIO FINANZIARIO 1ª TRANCHE — ACCONTO REALE',
      'SCENARIO ECONOMICO 2ª TRANCHE — SALDO PREVISTO',
      'TOTALE ECONOMICO PREVISIONALE',
    ])
  })

  it('state B: the single payment block with the difference from the forecast', () => {
    const statement = buildStatement({ payment_state: 'balance_only' })
    const summary = { ...statement.summary, single_payment: { learner_revenue: 3363.67, training_revenue: 49116.01, balance: 52479.68, total_revenue: 52479.68, difference: -43.99, partial_profit: 40239.48, commissions: 6035.91, mol: 34203.57, profit_per_learner: 4023.948 } }
    const rows = summaryRows({ ...statement, summary }, t)

    expect(headers(rows)).toEqual(['SCENARIO ECONOMICO', 'SCENARIO FINANZIARIO'])
    const total = rows.find((row) => row.kind === 'amount' && row.label === 'TOTALE RICAVI' && row.value === 52479.68)
    expect(total?.kind === 'amount' ? total.notes?.[0] : null).toMatch(/^\(-.*43,99.*vs previsto\)$/)
  })

  it('state C: advance, balance and actual financial total', () => {
    const statement = buildStatement({ payment_state: 'advance_and_balance' })
    const summary = { ...statement.summary, advance: buildTranche(), balance: buildTranche(), actual_total: { total_revenue: 52479.68, difference: 0, total_costs: 12240.2, partial_profit: 40239.48, commissions: 6035.91, mol: 34203.57, profit_per_learner: 4023.948 } }

    expect(headers(summaryRows({ ...statement, summary }, t))).toEqual([
      'SCENARIO ECONOMICO',
      'SCENARIO FINANZIARIO 1ª TRANCHE — ACCONTO',
      'SCENARIO FINANZIARIO 2ª TRANCHE — SALDO',
      'TOTALE FINANZIARIO REALE',
    ])
  })
})
