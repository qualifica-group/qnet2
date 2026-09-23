import type { CSSProperties } from "react"

/**
 * Shared visual constants for every recharts-backed stat widget (spec 0026,
 * extended spec 0152). Every color is a CSS variable so the charts follow
 * dark mode; kept in one place so the tooltip/grid/margin styling never
 * drifts between `stat-chart-impl`, `stat-column-chart-impl` and
 * `stat-donut-chart-impl`.
 */

/** The fixed `--chart-1..5` order (D-4, spec 0152): assigned in sequence, never cycled. */
export const CHART_SERIES_COLORS = [
  "var(--chart-1)",
  "var(--chart-2)",
  "var(--chart-3)",
  "var(--chart-4)",
  "var(--chart-5)",
] as const

/** Color of the "Others" bucket an over-the-limit distribution folds into (D-2). */
export const OTHERS_SERIES_COLOR = "var(--muted-foreground)"

/** A trend widget's `tone` (1..5) resolved to its `--chart-N` theme slot. */
export function chartToneColor(tone: 1 | 2 | 3 | 4 | 5): string {
  return CHART_SERIES_COLORS[tone - 1]
}

export const CHART_AXIS_TICK = { fontSize: 10, fill: "var(--muted-foreground)" } as const

export const CHART_TOOLTIP_CONTENT_STYLE: CSSProperties = {
  backgroundColor: "var(--popover)",
  border: "1px solid var(--border)",
  borderRadius: "var(--radius-md)",
  color: "var(--popover-foreground)",
  fontSize: "0.75rem",
  padding: "0.25rem 0.5rem",
}

export const CHART_MARGIN = { top: 4, right: 4, bottom: 0, left: 0 } as const

/** Surface-color ring/gap drawn around marks that overlap (marks-and-anatomy: the 2px ring/gap). */
export const CHART_SURFACE_RING_COLOR = "var(--card)"

export const PERCENT_MAX = 100

/** Shared percentage math for every distribution rendering (bars, donut, stacked). */
export function toPercentage(value: number, total: number): number {
  if (total <= 0) {
    return 0
  }

  const percent = (value / total) * PERCENT_MAX

  return Math.min(Math.max(percent, 0), PERCENT_MAX)
}
