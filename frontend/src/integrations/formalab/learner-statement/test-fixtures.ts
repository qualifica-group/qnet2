import type { LearnerStatement, StatementLearner, Tranche } from '@/integrations/formalab/learner-statement/types'

export function buildTranche(overrides: Partial<Tranche> = {}): Tranche {
  return { amount: 20000, weight: 0.381, costs: 4664.74, partial_profit: 15335.26, commissions: 2300.29, mol: 13034.97, profit_per_learner: 1533.53, ...overrides }
}

export function buildLearner(overrides: Partial<StatementLearner> = {}): StatementLearner {
  return {
    id: 1,
    first_name: 'Maria',
    last_name: 'Rossi',
    tax_code: 'RSSMRA80A01H501X',
    presence: { hours: 381, minutes: 15 },
    absence: { hours: 18, minutes: 45 },
    phases: null,
    passed: true,
    cost_hours: 381,
    cost: 381,
    revenue_hours: 381.25,
    revenue: 324.06,
    margin: -56.94,
    partner: 'Partner A',
    percentage: 0,
    amount: 150,
    commission: 150,
    learner_order: null,
    ...overrides,
  }
}

/** A prospetto in the "no payment" state, one partner, one learner. */
export function buildStatement(overrides: Partial<LearnerStatement> = {}): LearnerStatement {
  return {
    edition_id: 926,
    class_status: 'ok',
    can_manage: true,
    class: {
      code: 'ED926',
      title: 'Operatore olistico',
      status: 'Chiusa',
      start_date: '2025-02-17',
      end_date: '2025-09-19',
      total_hours: 400,
      two_phase: false,
      site_name: 'Sede Frattamaggiore',
      site_address: 'Via Roma 1',
      site_city: 'Frattamaggiore',
      site_code: '183',
      learners_total: 1,
      learners_passed: 1,
      learners_failed: 0,
      passed_percentage: 100,
    },
    rates: { cost_per_hour: 1, revenue_per_hour: 0.85 },
    commission_scenario: 'economic',
    financial_available: false,
    payment_state: 'none',
    costs: { learners: 6000, other_lines: [{ title: 'FOR_Docenti', note: 'Docente uno', amount: 5200 }], other_total: 6240.2, total: 12240.2 },
    summary: {
      learners_count: 10,
      learner_revenue: 3363.67,
      training_revenue: 49160,
      total_revenue: 52523.67,
      partial_profit: 40283.47,
      commissions: 6042.51,
      mol: 34240.96,
      profit_per_learner: 4028.347,
      advance: null,
      forecast_balance: null,
      balance: null,
      forecast_total: null,
      actual_total: null,
      single_payment: null,
    },
    learners: [buildLearner()],
    totals: { presence: { hours: 381, minutes: 15 }, absence: { hours: 18, minutes: 45 }, phases: null, learner_costs: 6000, learner_revenue: 324.06 },
    partners: {
      partial_profit: 40283.47,
      profit_per_learner: 4028.347,
      total: 150,
      tranche: { advance_weight: 0, advance_commissions: 0, balance_weight: 0, balance_commissions: 0, forecast_balance_weight: 0, forecast_balance_commissions: 0 },
      items: [
        {
          key: 'partner_a',
          name: 'Partner A',
          learners_count: 1,
          total: 150,
          supplier: null,
          purchase_order: null,
          learners: [{ name: 'Maria Rossi', percentage: 0, manual_amount: 150, commission: 150, type: 'amount' }],
        },
      ],
    },
    progress: { items: [{ year: 2025, month: 3, percentage: 20, revenue: 873.6 }], total_percentage: 20, total_revenue: 873.6 },
    ...overrides,
  }
}
