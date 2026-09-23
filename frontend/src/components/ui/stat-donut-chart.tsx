import * as React from "react"

import { Card, CardContent } from "@/components/ui/card"
import { Skeleton } from "@/components/ui/skeleton"
import { cn } from "@/lib/utils"

export interface StatDonutItem {
  key: string
  label: string
  value: number
  /** Resolved CSS color (DB token, `--chart-N` theme slot, or "Others" gray). */
  color: string
}

export interface StatDonutChartProps {
  title: string
  items: StatDonutItem[]
  /** Denominator of the legend percentages (0 allowed). */
  total: number
  formatValue?: (value: number) => string
  /** Discreet placeholder shown when `items` is empty. Pass a translated string. */
  emptyLabel?: string
  className?: string
}

/**
 * Charting library boundary: `recharts` is imported ONLY by
 * `stat-donut-chart-impl`, code-split here so it never lands in the initial
 * page chunk (same pattern as `stat-chart`, spec 0152).
 */
const StatDonutChartImpl = React.lazy(() => import("@/components/ui/stat-donut-chart-impl"))

/**
 * Part-to-whole distribution widget: a donut with the total at its center and
 * a visible legend (label + value + percent) — never color-only. Composes `Card`.
 */
function StatDonutChart({
  title,
  items,
  total,
  formatValue,
  emptyLabel = "—",
  className,
}: StatDonutChartProps) {
  return (
    <Card className={cn("gap-2 py-3", className)}>
      <CardContent className="flex flex-col gap-2 px-3">
        <h3 className="truncate text-xs font-medium text-muted-foreground">{title}</h3>
        {items.length === 0 ? (
          <p className="text-xs text-muted-foreground">{emptyLabel}</p>
        ) : (
          <React.Suspense fallback={<Skeleton className="h-40 w-full sm:h-48" />}>
            <StatDonutChartImpl title={title} items={items} total={total} formatValue={formatValue} />
          </React.Suspense>
        )}
      </CardContent>
    </Card>
  )
}

export { StatDonutChart }
