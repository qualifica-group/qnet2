import { describe, expect, it } from 'vitest'
import { CHART_SERIES_COLORS, OTHERS_SERIES_COLOR } from '@/components/ui/stat-chart-tokens'
import type { RequestDashboardCategory } from '@/features/request-management/dashboard-api'
import {
  buildHeatmap,
  categoryShares,
  heatIntensity,
  OTHERS_SHARE_KEY,
} from '@/features/request-management/dashboard-overview'

/** Spec 0192 D-5/D-6: the overview's heatmap and per-category split. */

function category(key: string, values: Record<string, number>): RequestDashboardCategory {
  return {
    key,
    label: key.toUpperCase(),
    summary: Object.entries(values).map(([indicator, value]) => ({ key: indicator, label: indicator, value })),
    charts: [],
  }
}

describe('buildHeatmap', () => {
  it('unions the configured indicators in order of first appearance, null where not configured', () => {
    const heatmap = buildHeatmap([category('gol', { telefonate: 8, aule: 2 }), category('apl', { telefonate: 20, invio: 1 })])

    expect(heatmap.columns).toEqual([
      { key: 'telefonate', label: 'telefonate', max: 20 },
      { key: 'aule', label: 'aule', max: 2 },
      { key: 'invio', label: 'invio', max: 1 },
    ])
    expect(heatmap.rows).toEqual([
      { key: 'gol', label: 'GOL', cells: [8, 2, null] },
      { key: 'apl', label: 'APL', cells: [20, null, 1] },
    ])
  })

  it('normalizes intensity per column, 0 for an all-zero column', () => {
    expect(heatIntensity(5, 20)).toBe(0.25)
    expect(heatIntensity(0, 0)).toBe(0)
  })
})

describe('categoryShares', () => {
  it('splits an indicator over the sum of the categories, largest first, dropping zeros', () => {
    const shares = categoryShares(
      [category('apl', { telefonate: 1 }), category('dil', { telefonate: 0 }), category('gol', { telefonate: 3 })],
      'telefonate',
      'Others',
    )

    expect(shares).toEqual([
      { key: 'gol', label: 'GOL', value: 3, percent: 75, color: CHART_SERIES_COLORS[0], categoryCount: 1 },
      { key: 'apl', label: 'APL', value: 1, percent: 25, color: CHART_SERIES_COLORS[1], categoryCount: 1 },
    ])
  })

  it('keeps five contributors as they are, folds a longer tail into one "others" segment', () => {
    const five = ['a', 'b', 'c', 'd', 'e'].map((key) => category(key, { telefonate: 1 }))
    expect(categoryShares(five, 'telefonate', 'Others').map((share) => share.color)).toEqual([...CHART_SERIES_COLORS])

    const seven = [...five, category('f', { telefonate: 1 }), category('g', { telefonate: 9 })]
    const shares = categoryShares(seven, 'telefonate', 'Others')
    expect(shares.map((share) => share.key)).toEqual(['g', 'a', 'b', 'c', OTHERS_SHARE_KEY])
    expect(shares.at(-1)).toMatchObject({ label: 'Others', value: 3, color: OTHERS_SERIES_COLOR, categoryCount: 3 })
  })

  it('is empty when no category has a value for the indicator', () => {
    expect(categoryShares([category('gol', { telefonate: 0 })], 'telefonate', 'Others')).toEqual([])
  })
})
