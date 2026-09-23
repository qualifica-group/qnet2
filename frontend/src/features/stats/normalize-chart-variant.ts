import type { ChartTone, DistributionChartVariant, TrendChartVariant } from '@/features/stats/types'

/** Contract defaults (spec 0152 D-1/data_contract): absent or unknown -> these. */
export const DEFAULT_DISTRIBUTION_CHART: DistributionChartVariant = 'bars'
export const DEFAULT_TREND_CHART: TrendChartVariant = 'area'
export const DEFAULT_CHART_TONE: ChartTone = 1

const DISTRIBUTION_CHART_VARIANTS: readonly DistributionChartVariant[] = [
  'bars',
  'columns',
  'donut',
  'stacked',
]

const TREND_CHART_VARIANTS: readonly TrendChartVariant[] = ['area', 'columns', 'line']

const CHART_TONES: readonly ChartTone[] = [1, 2, 3, 4, 5]

function isKnownVariant<T extends string>(variants: readonly T[], value: string): value is T {
  return (variants as readonly string[]).includes(value)
}

/**
 * Resolves a `distribution` widget's `chart` field to a known variant. A
 * missing or unrecognized value degrades to the current rendering (`bars`),
 * so a backend ahead of the deployed frontend never breaks the panel.
 */
export function normalizeDistributionChart(chart: string | undefined): DistributionChartVariant {
  if (chart !== undefined && isKnownVariant(DISTRIBUTION_CHART_VARIANTS, chart)) {
    return chart
  }

  return DEFAULT_DISTRIBUTION_CHART
}

/** Resolves a `trend` widget's `chart` field the same way (-> `area`). */
export function normalizeTrendChart(chart: string | undefined): TrendChartVariant {
  if (chart !== undefined && isKnownVariant(TREND_CHART_VARIANTS, chart)) {
    return chart
  }

  return DEFAULT_TREND_CHART
}

/** Resolves a `trend` widget's `tone` field to a valid `--chart-N` slot (-> `1`). */
export function normalizeChartTone(tone: number | undefined): ChartTone {
  if (tone !== undefined && (CHART_TONES as readonly number[]).includes(tone)) {
    return tone as ChartTone
  }

  return DEFAULT_CHART_TONE
}
