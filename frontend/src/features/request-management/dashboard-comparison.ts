import type { RequestDashboardSummaryItem } from '@/features/request-management/dashboard-api'

/** Indicators where a HIGHER number is worse (D-4): requests left unhandled. */
const INVERSE_INDICATOR_PREFIX = 'unhandled_'

const PERCENT = 100

export type DashboardTrendDirection = 'up' | 'down' | 'flat'

/** Whether a change is good news for the business, which decides its tone, not its arrow. */
export type DashboardTrendSentiment = 'positive' | 'negative' | 'neutral'

export interface DashboardTrend {
  direction: DashboardTrendDirection
  /** Rounded change in percent; null when the previous value was 0 ("new"). */
  percent: number | null
  sentiment: DashboardTrendSentiment
  previous: number
}

export function isInverseIndicator(key: string): boolean {
  return key.startsWith(INVERSE_INDICATOR_PREFIX)
}

/** Previous-period values by indicator key, so tiles match on `key`, never on position. */
export function valuesByKey(items: RequestDashboardSummaryItem[] | undefined): Map<string, number> | undefined {
  return items ? new Map(items.map((item) => [item.key, item.value])) : undefined
}

/**
 * The change of one indicator against the previous period (D-4), or null
 * when there is nothing honest to show: no previous value for this key, or
 * zero in both periods.
 */
export function compareIndicator(key: string, current: number, previous: number | undefined): DashboardTrend | null {
  if (previous === undefined || (previous === 0 && current === 0)) {
    return null
  }

  const direction: DashboardTrendDirection = current > previous ? 'up' : current < previous ? 'down' : 'flat'
  const percent = previous === 0 ? null : Math.round(((current - previous) / previous) * PERCENT)
  const improves = isInverseIndicator(key) ? direction === 'down' : direction === 'up'
  const sentiment: DashboardTrendSentiment = direction === 'flat' ? 'neutral' : improves ? 'positive' : 'negative'

  return { direction, percent, sentiment, previous }
}
