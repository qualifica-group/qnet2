import { CHART_SERIES_COLORS, OTHERS_SERIES_COLOR, toPercentage } from '@/components/ui/stat-chart-tokens'
import type { RequestDashboardCategory } from '@/features/request-management/dashboard-api'

/** Shared by the overview and the category tabs, so KPIs line up at every breakpoint. */
export const KPI_GRID_CLASS = 'grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-4 2xl:grid-cols-6'

/** Key of the share segment the categories beyond the fixed palette fold into (spec 0152 D-2/D-4). */
export const OTHERS_SHARE_KEY = '__others__'

export interface HeatmapColumn {
  key: string
  label: string
  /** Highest value of the column: intensity is normalized PER COLUMN (D-5), indicators have different scales. */
  max: number
}

export interface HeatmapRow {
  key: string
  label: string
  /** One cell per column, null where the category does not configure that indicator. */
  cells: Array<number | null>
}

export interface Heatmap {
  columns: HeatmapColumn[]
  rows: HeatmapRow[]
}

/**
 * Categories x indicators matrix of the overview (spec 0192 D-5): columns are
 * the union of the categories' configured indicators, in order of first
 * appearance — every category lists them in the report's canonical order.
 */
export function buildHeatmap(categories: RequestDashboardCategory[]): Heatmap {
  const labels = new Map<string, string>()
  for (const category of categories) {
    for (const item of category.summary) {
      if (!labels.has(item.key)) {
        labels.set(item.key, item.label)
      }
    }
  }

  const rows = categories.map((category) => {
    const values = new Map(category.summary.map((item) => [item.key, item.value]))
    return { key: category.key, label: category.label, cells: [...labels.keys()].map((key) => values.get(key) ?? null) }
  })

  const columns = [...labels].map(([key, label], index) => ({
    key,
    label,
    max: Math.max(0, ...rows.map((row) => row.cells[index] ?? 0)),
  }))

  return { columns, rows }
}

/** 0..1 share of the column maximum; 0 for an all-zero column. */
export function heatIntensity(value: number, max: number): number {
  return max > 0 ? value / max : 0
}

export interface CategoryShare {
  key: string
  label: string
  value: number
  percent: number
  color: string
  /** Categories behind the segment: 1, or the size of the folded "others" tail. */
  categoryCount: number
}

/** Contributors colored one by one before the rest folds into "others" (spec 0152 D-2/D-4: never cycled). */
const NAMED_SHARES_MAX = 4

/**
 * How one overall indicator splits across the categories that configure it
 * (spec 0192 D-6), largest first. Percentages are on the SUM of the
 * categories: the tile's own total is the union (spec 0107 D-8) and a request
 * under a parent and a child category counts in both, so the bar shows the
 * split, not the total. Zero contributions are dropped (an invisible
 * segment). Colors follow the RANK, not the category: with dozens of
 * subcategories a per-category color would leave most of them gray, while
 * the few that matter for this indicator are what the bar is for. Past the
 * palette the tail folds into one "others" segment.
 */
export function categoryShares(
  categories: RequestDashboardCategory[],
  indicatorKey: string,
  othersLabel: string,
): CategoryShare[] {
  const contributions = categories
    .flatMap((category) => {
      const value = category.summary.find((item) => item.key === indicatorKey)?.value ?? 0
      return value > 0 ? [{ key: category.key, label: category.label, value }] : []
    })
    .sort((a, b) => b.value - a.value || a.label.localeCompare(b.label))
  const total = contributions.reduce((sum, item) => sum + item.value, 0)
  // Five contributors fit the palette as they are; only a longer tail folds.
  const namedCount = contributions.length <= CHART_SERIES_COLORS.length ? contributions.length : NAMED_SHARES_MAX

  const shares = contributions.slice(0, namedCount).map((item, index) => ({
    ...item,
    percent: toPercentage(item.value, total),
    color: CHART_SERIES_COLORS[index],
    categoryCount: 1,
  }))
  const othersValue = contributions.slice(namedCount).reduce((sum, item) => sum + item.value, 0)

  return othersValue > 0
    ? [
        ...shares,
        {
          key: OTHERS_SHARE_KEY,
          label: othersLabel,
          value: othersValue,
          percent: toPercentage(othersValue, total),
          color: OTHERS_SERIES_COLOR,
          categoryCount: contributions.length - namedCount,
        },
      ]
    : shares
}
