import { describe, expect, it } from 'vitest'
import type { RequestDashboardChart } from '@/features/request-management/dashboard-api'
import {
  buildLeaderboard,
  defaultLeaderboardSort,
  LEADERBOARD_LABEL_SORT,
  sortLeaderboard,
} from '@/features/request-management/dashboard-leaderboard'

/** Spec 0192 D-3: the per-indicator operator charts pivoted into one ranking. */

const CHARTS: RequestDashboardChart[] = [
  {
    id: 'indicator-gol',
    scope: 'indicator',
    indicator_key: null,
    indicator_label: null,
    points: [{ label: 'Telefonate', value: 9 }],
  },
  {
    id: 'operator-gol-telefonate',
    scope: 'operator',
    indicator_key: 'telefonate',
    indicator_label: 'Telefonate',
    points: [
      { label: 'Non assegnato', value: 6 },
      { label: 'Ada Rossi', value: 2 },
      { label: 'Bea Neri', value: 2 },
    ],
  },
  {
    id: 'operator-gol-associati',
    scope: 'operator',
    indicator_key: 'associati',
    indicator_label: 'Associati',
    points: [
      { label: 'Bea Neri', value: 3 },
      { label: 'Ada Rossi', value: 1 },
      { label: 'Non assegnato', value: 0 },
    ],
  },
]

describe('buildLeaderboard', () => {
  it('pivots one column per operator chart and one row per operator, ignoring the indicator chart', () => {
    const leaderboard = buildLeaderboard(CHARTS, 'Non assegnato')

    expect(leaderboard?.columns).toEqual([
      { key: 'telefonate', label: 'Telefonate', max: 6 },
      { key: 'associati', label: 'Associati', max: 3 },
    ])
    expect(leaderboard?.rows.find((row) => row.label === 'Bea Neri')).toEqual({
      label: 'Bea Neri',
      values: { telefonate: 2, associati: 3 },
      unassigned: false,
    })
    expect(leaderboard?.rows.find((row) => row.label === 'Non assegnato')?.unassigned).toBe(true)
  })

  it('is null without any operator chart (row mode "total only")', () => {
    expect(buildLeaderboard(CHARTS.slice(0, 1), 'Non assegnato')).toBeNull()
  })
})

describe('sortLeaderboard', () => {
  const leaderboard = buildLeaderboard(CHARTS, 'Non assegnato')!
  const names = (sort: Parameters<typeof sortLeaderboard>[1]) =>
    sortLeaderboard(leaderboard.rows, sort).map((row) => row.label)

  it('opens on the first indicator, highest first, ties by name, unassigned last', () => {
    expect(names(defaultLeaderboardSort(leaderboard))).toEqual(['Ada Rossi', 'Bea Neri', 'Non assegnato'])
  })

  it('sorts by another column in either direction, unassigned still last', () => {
    expect(names({ key: 'associati', direction: 'desc' })).toEqual(['Bea Neri', 'Ada Rossi', 'Non assegnato'])
    expect(names({ key: 'associati', direction: 'asc' })).toEqual(['Ada Rossi', 'Bea Neri', 'Non assegnato'])
  })

  it('sorts by name', () => {
    expect(names({ key: LEADERBOARD_LABEL_SORT, direction: 'desc' })).toEqual(['Bea Neri', 'Ada Rossi', 'Non assegnato'])
  })
})
