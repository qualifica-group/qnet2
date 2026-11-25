/** Mirrors the frozen contract of spec 0427 (`GET .../learner-statement`). */

export type CommissionScenario = 'economic' | 'financial'

export type PaymentState = 'none' | 'advance_only' | 'balance_only' | 'advance_and_balance'

export type ClassStatus = 'ok' | 'empty' | 'unavailable'

export interface HoursMinutes {
  hours: number
  minutes: number
}

/** Minutes per phase of a two-phase course (training / internship). */
export interface PhaseMinutes {
  presence_training: number
  presence_internship: number
  absence_training: number
  absence_internship: number
}

export interface StatementClass {
  code: string | null
  title: string
  status: string | null
  start_date: string | null
  end_date: string | null
  total_hours: number | null
  two_phase: boolean
  site_name: string | null
  site_address: string | null
  site_city: string | null
  site_code: string | null
  learners_total: number
  learners_passed: number
  learners_failed: number
  passed_percentage: number
}

export interface Tranche {
  amount: number
  weight: number
  costs: number
  partial_profit: number
  commissions: number
  mol: number
  profit_per_learner: number
}

export interface StatementSummary {
  learners_count: number
  learner_revenue: number
  training_revenue: number
  total_revenue: number
  partial_profit: number
  commissions: number
  mol: number
  profit_per_learner: number
  advance: Tranche | null
  forecast_balance: Tranche | null
  balance: Tranche | null
  forecast_total: {
    total_revenue: number
    total_costs: number
    partial_profit: number
    commissions: number
    mol: number
    profit_per_learner: number
  } | null
  actual_total: {
    total_revenue: number
    difference: number
    total_costs: number
    partial_profit: number
    commissions: number
    mol: number
    profit_per_learner: number
  } | null
  single_payment: {
    learner_revenue: number
    training_revenue: number
    balance: number
    total_revenue: number
    difference: number
    partial_profit: number
    commissions: number
    mol: number
    profit_per_learner: number
  } | null
}

export interface StatementLearner {
  id: number | null
  first_name: string
  last_name: string
  tax_code: string
  presence: HoursMinutes
  absence: HoursMinutes
  phases: PhaseMinutes | null
  /** null = outcome not known yet (legacy "??"). */
  passed: boolean | null
  cost_hours: number
  cost: number
  revenue_hours: number
  revenue: number
  margin: number
  partner: string | null
  percentage: number
  amount: number
  commission: number
  learner_order: { id: number; date: string | null } | null
}

export interface PartnerLearner {
  name: string
  percentage: number
  manual_amount: number
  commission: number
  type: 'percentage' | 'amount'
}

export interface StatementPartner {
  key: string
  name: string
  learners_count: number
  total: number
  supplier: { id: number; name: string | null } | null
  purchase_order: { id: number; number: string; status: string; total: number } | null
  learners: PartnerLearner[]
}

export interface StatementPartners {
  partial_profit: number
  profit_per_learner: number
  total: number
  tranche: {
    advance_weight: number
    advance_commissions: number
    balance_weight: number
    balance_commissions: number
    forecast_balance_weight: number
    forecast_balance_commissions: number
  }
  items: StatementPartner[]
}

export interface LearnerStatement {
  edition_id: number
  class_status: ClassStatus
  can_manage: boolean
  class: StatementClass | null
  rates: { cost_per_hour: number; revenue_per_hour: number }
  commission_scenario: CommissionScenario
  financial_available: boolean
  payment_state: PaymentState
  costs: { learners: number; other_lines: { title: string; note: string; amount: number }[]; other_total: number; total: number }
  summary: StatementSummary
  learners: StatementLearner[]
  totals: {
    presence: HoursMinutes
    absence: HoursMinutes
    phases: PhaseMinutes | null
    learner_costs: number
    learner_revenue: number
  }
  partners: StatementPartners
  progress: { items: { year: number; month: number; percentage: number; revenue: number }[]; total_percentage: number; total_revenue: number }
}

/** A learner's commission edit: a percentage of the profit per learner, or a fixed amount. */
export type CommissionPayload = { percentage: number } | { amount: number }

export type StatementExportVariant = 'full' | 'partners'
