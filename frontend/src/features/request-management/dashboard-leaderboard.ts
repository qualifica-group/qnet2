import type { RequestDashboardChart } from '@/features/request-management/dashboard-api'

/**
 * Key of the "Non assegnato" entry in the operator list (backend
 * `ReportOperatorFilter::UNASSIGNED_KEY`): its server label is how the
 * ranking recognises that row, since chart points carry names only.
 */
export const UNASSIGNED_OPERATOR_KEY = 'unassigned'

/** Sorting by operator name instead of by an indicator column. */
export const LEADERBOARD_LABEL_SORT = '__label__'

export type LeaderboardSortDirection = 'asc' | 'desc'

export interface LeaderboardSort {
  key: string
  direction: LeaderboardSortDirection
}

export interface LeaderboardColumn {
  key: string
  label: string
  /** Highest value in the column: the inline bars are proportional to it. */
  max: number
}

export interface LeaderboardRow {
  label: string
  values: Record<string, number>
  /** The "Non assegnato" GA2 row (spec 0106 D-13): never ranked, always last (D-3). */
  unassigned: boolean
}

export interface Leaderboard {
  columns: LeaderboardColumn[]
  rows: LeaderboardRow[]
}

/**
 * Pivots a category's `operator` charts — one per indicator, one point per
 * GA2 — into ONE operators x indicators table (spec 0192 D-3). Points carry
 * no key, only the operator's name, so the name is the row identity, exactly
 * as each chart already uses it. Null when the category has no operator
 * chart (`row_mode = total_only`, or no GA2 row at all).
 */
export function buildLeaderboard(
  charts: RequestDashboardChart[],
  unassignedLabel: string | undefined,
): Leaderboard | null {
  const operatorCharts = charts.filter((chart) => chart.scope === 'operator' && chart.indicator_key !== null)
  if (operatorCharts.length === 0) {
    return null
  }

  const rows = new Map<string, LeaderboardRow>()
  const columns = operatorCharts.map((chart) => {
    const key = chart.indicator_key as string
    for (const point of chart.points) {
      const row = rows.get(point.label) ?? { label: point.label, values: {}, unassigned: point.label === unassignedLabel }
      row.values[key] = point.value
      rows.set(point.label, row)
    }

    return {
      key,
      label: chart.indicator_label ?? key,
      max: Math.max(0, ...chart.points.map((point) => point.value)),
    }
  })

  return { columns, rows: [...rows.values()] }
}

/** The opening order: first indicator, highest first (the order the old charts used, AC-007). */
export function defaultLeaderboardSort(leaderboard: Leaderboard): LeaderboardSort {
  return { key: leaderboard.columns[0]?.key ?? LEADERBOARD_LABEL_SORT, direction: 'desc' }
}

/**
 * Rows in the requested order, ties broken by name ascending; "Non assegnato"
 * stays at the bottom whatever the order, so the ranking is only ever about
 * real operators.
 */
export function sortLeaderboard(rows: LeaderboardRow[], sort: LeaderboardSort): LeaderboardRow[] {
  const factor = sort.direction === 'asc' ? 1 : -1

  return [...rows].sort((a, b) => {
    if (a.unassigned !== b.unassigned) {
      return a.unassigned ? 1 : -1
    }
    const byName = a.label.localeCompare(b.label)
    if (sort.key === LEADERBOARD_LABEL_SORT) {
      return byName * factor
    }

    return ((a.values[sort.key] ?? 0) - (b.values[sort.key] ?? 0)) * factor || byName
  })
}
