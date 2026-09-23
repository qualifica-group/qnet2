import { describe, expect, it } from 'vitest'
import {
  normalizeChartTone,
  normalizeDistributionChart,
  normalizeTrendChart,
} from '@/features/stats/normalize-chart-variant'

/**
 * Spec 0152 D-1/data_contract — a `chart`/`tone` field absent or outside the
 * allow-list degrades to the pre-existing rendering, never crashes.
 */
describe('normalizeDistributionChart', () => {
  it.each(['bars', 'columns', 'donut', 'stacked'] as const)('keeps a known value (%s)', (chart) => {
    expect(normalizeDistributionChart(chart)).toBe(chart)
  })

  it('defaults to bars when the field is absent', () => {
    expect(normalizeDistributionChart(undefined)).toBe('bars')
  })

  it('defaults to bars for an unknown value', () => {
    expect(normalizeDistributionChart('pie-3d')).toBe('bars')
  })
})

describe('normalizeTrendChart', () => {
  it.each(['area', 'columns', 'line'] as const)('keeps a known value (%s)', (chart) => {
    expect(normalizeTrendChart(chart)).toBe(chart)
  })

  it('defaults to area when the field is absent', () => {
    expect(normalizeTrendChart(undefined)).toBe('area')
  })

  it('defaults to area for an unknown value', () => {
    expect(normalizeTrendChart('waterfall')).toBe('area')
  })
})

describe('normalizeChartTone', () => {
  it.each([1, 2, 3, 4, 5] as const)('keeps a known tone (%s)', (tone) => {
    expect(normalizeChartTone(tone)).toBe(tone)
  })

  it('defaults to 1 when the field is absent', () => {
    expect(normalizeChartTone(undefined)).toBe(1)
  })

  it('defaults to 1 for an out-of-range tone', () => {
    expect(normalizeChartTone(0)).toBe(1)
    expect(normalizeChartTone(6)).toBe(1)
  })
})
