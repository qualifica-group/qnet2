import { CHART_SERIES_COLORS, OTHERS_SERIES_COLOR } from '@/components/ui/stat-chart-tokens'
import { resolveDistributionColor } from '@/features/stats/resolve-distribution-color'
import type { DistributionItem } from '@/features/stats/types'

/** Max distinguishable slices a donut/stacked/columns rendering shows before folding (D-2). */
const MAX_VISIBLE_SLICES = 6

export interface ResolvedDistributionItem {
  key: string
  label: string
  value: number
  /** Always a resolved CSS color/var — never `null` (D-4: token, theme slot, or "Others" gray). */
  color: string
}

/**
 * Resolves each item's display color and folds whatever does not fit into a
 * single "Others" bucket (D-2/D-4, spec 0152): a DB color token is kept as-is
 * (unlimited); an item without one draws the next unused `--chart-1..5` slot,
 * in fixed order, never cycled. Once 6 slices are kept, or the fixed palette
 * is exhausted, the remaining items' values are summed into "Others" (gray).
 * Used by the categorical renderings (columns, donut, stacked) — `bars`
 * keeps its own single default color (a ranking list, not a part-to-whole read).
 */
export function groupDistributionSeries(
  items: DistributionItem[],
  othersLabel: string,
): ResolvedDistributionItem[] {
  const kept: ResolvedDistributionItem[] = []
  let othersValue = 0
  let paletteIndex = 0

  for (const item of items) {
    const tokenColor = resolveDistributionColor(item.color)
    const color =
      tokenColor ?? (paletteIndex < CHART_SERIES_COLORS.length ? CHART_SERIES_COLORS[paletteIndex] : null)

    if (kept.length < MAX_VISIBLE_SLICES && color !== null) {
      kept.push({ key: item.key, label: item.label, value: item.value, color })
      if (!tokenColor) {
        paletteIndex += 1
      }
    } else {
      othersValue += item.value
    }
  }

  if (othersValue > 0) {
    kept.push({ key: '__others__', label: othersLabel, value: othersValue, color: OTHERS_SERIES_COLOR })
  }

  return kept
}
