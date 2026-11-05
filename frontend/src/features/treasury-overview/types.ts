/** Spec 0362: payload of `GET /api/treasury-overview`. Amounts are 2-decimal strings. */
export type FinancialAccountKind = 'bank_account' | 'card' | 'cash'

export interface OverviewAccount {
  id: number
  name: string
  type: FinancialAccountKind
  company: { id: number; name: string } | null
  balance: string
}

export type OverdueBucket = 'overdue_1_30' | 'overdue_31_60' | 'overdue_61_90' | 'overdue_over_90'

export interface ScheduleSide {
  open_total: string
  not_due_total: string
  overdue_total: string
  overdue: Record<OverdueBucket, string>
}

export interface ForecastRow {
  days: 30 | 60 | 90
  until: string
  receivable: string
  payable: string
  expected_balance: string
}

export interface TreasuryOverview {
  today: string
  company_id: number | null
  liquidity: {
    total: string
    by_type: Record<FinancialAccountKind, string>
    accounts: OverviewAccount[]
  }
  receivable: ScheduleSide
  payable: ScheduleSide
  net_position: string
  forecast: ForecastRow[]
  to_verify: {
    unassigned: { count: number; collections: string; payments: string }
    adjustments: { month: string; count: number; inflow: string; outflow: string }
    /** Spec 0363: rows not ticked up to today, per account (only accounts with count > 0, count desc). */
    unreconciled: { count: number; accounts: Array<{ id: number; name: string; count: number }> }
  }
}
