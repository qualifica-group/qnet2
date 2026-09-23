import { toPercentage } from "@/components/ui/stat-chart-tokens"

export interface StatDistributionLegendItem {
  key: string
  label: string
  value: number
  /** Resolved CSS color (DB token, `--chart-N` theme slot, or "Others" gray). */
  color: string
}

interface StatDistributionLegendProps {
  items: StatDistributionLegendItem[]
  /** Denominator of the percentages (0 allowed). */
  total: number
  formatValue: (value: number) => string
}

/**
 * Shared legend row (swatch + label + value + percent) for the part-to-whole
 * distributions (donut, stacked, spec 0152). Text never wears the series
 * color: identity comes from the swatch dot beside the label, never from
 * coloring the text itself (marks-and-anatomy).
 */
function StatDistributionLegend({ items, total, formatValue }: StatDistributionLegendProps) {
  return (
    <ul className="flex flex-col gap-1">
      {items.map((item) => {
        const percent = Math.round(toPercentage(item.value, total))

        return (
          <li key={item.key} className="flex min-w-0 items-center justify-between gap-2 text-xs">
            <span className="flex min-w-0 items-center gap-1.5">
              <span
                aria-hidden
                className="size-2.5 shrink-0 rounded-full"
                style={{ backgroundColor: item.color }}
              />
              <span className="min-w-0 truncate">{item.label}</span>
            </span>
            <span className="shrink-0 tabular-nums text-muted-foreground">
              {formatValue(item.value)}
              <span className="ml-1">({percent}%)</span>
            </span>
          </li>
        )
      })}
    </ul>
  )
}

export { StatDistributionLegend }
