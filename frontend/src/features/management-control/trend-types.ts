import type { CostNature, ScopeFilters } from '@/features/management-control/types'

/* Spec 0270: GET /api/management-control/monthly-trend. */

/** `cost` already includes `labor` (internal hours valued at the hourly cost, spec 0272 D-4). */
export const TREND_MEASURES = ['revenue', 'cost', 'labor', 'margin'] as const
export type TrendMeasure = (typeof TREND_MEASURES)[number]

/** Key the server gives to the purchase costs tied to no work order. */
export const STRUCTURE_KEY = 'structure'

/** One value per month of `months`, same positions. */
export type TrendSeries = Record<TrendMeasure, number[]>

export interface FunctionTrendRow extends TrendSeries {
  key: string
  label: string | null
}

/** Timesheet minutes per month: `unpriced_minutes` are the part of `labor_minutes` with no hourly cost. */
export type TrendTotalsSeries = TrendSeries & {
  labor_minutes: number[]
  unpriced_minutes: number[]
  previous_year: TrendSeries
}

export interface NatureTrendRow {
  nature: CostNature | null
  cost: number[]
  /** Percent of the month revenue, one decimal; null when the month has no revenue. */
  share_of_revenue: (number | null)[]
}

export interface MonthlyTrend {
  applied: {
    date_from: string
    date_to: string
    company_id: number[]
    company_site_id: number[]
    operational_site_id: number[]
  }
  /** `YYYY-MM`, every month of the interval, in order. */
  months: string[]
  functions: FunctionTrendRow[]
  totals: TrendTotalsSeries
  by_nature: NatureTrendRow[]
}

/** Filter state of the monthly trend page: period and scope, as the other pages of the module. */
export type TrendFilters = ScopeFilters

/** Request query parameters of the monthly trend, named exactly as the contract. */
export interface TrendParams {
  date_from?: string
  date_to?: string
  company_id?: number[]
  company_site_id?: number[]
  operational_site_id?: number[]
}
