import * as React from "react"

import { Card, CardContent } from "@/components/ui/card"
import { Skeleton } from "@/components/ui/skeleton"
import { cn } from "@/lib/utils"

export interface StatColumnItem {
  key: string
  label: string
  value: number
  /** Resolved CSS color (DB token or `--chart-N` theme slot — never a raw hex). */
  color: string
}

export interface StatColumnChartProps {
  title: string
  items: StatColumnItem[]
  formatValue?: (value: number) => string
  /** Discreet placeholder shown when `items` is empty. Pass a translated string. */
  emptyLabel?: string
  className?: string
}

/**
 * Charting library boundary: `recharts` is imported ONLY by
 * `stat-column-chart-impl`, code-split here so it never lands in the initial
 * page chunk (same pattern as `stat-chart`, spec 0152).
 */
const StatColumnChartImpl = React.lazy(() => import("@/components/ui/stat-column-chart-impl"))

/** Distribution widget rendered as vertical columns, one color per category. Composes `Card`. */
function StatColumnChart({ title, items, formatValue, emptyLabel = "—", className }: StatColumnChartProps) {
  return (
    <Card className={cn("gap-2 py-3", className)}>
      <CardContent className="flex flex-col gap-2 px-3">
        <h3 className="truncate text-xs font-medium text-muted-foreground">{title}</h3>
        {items.length === 0 ? (
          <p className="text-xs text-muted-foreground">{emptyLabel}</p>
        ) : (
          <React.Suspense fallback={<Skeleton className="h-40 w-full sm:h-48" />}>
            <StatColumnChartImpl title={title} items={items} formatValue={formatValue} />
          </React.Suspense>
        )}
      </CardContent>
    </Card>
  )
}

export { StatColumnChart }
