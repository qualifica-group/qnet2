import * as React from "react"

import { Card, CardContent } from "@/components/ui/card"
import { Skeleton } from "@/components/ui/skeleton"
import { cn } from "@/lib/utils"

export interface ReportBarChartPoint {
  label: string
  value: number
}

export interface ReportBarChartProps {
  title: string
  points: ReportBarChartPoint[]
  formatValue?: (value: number) => string
  /** Discreet placeholder shown when `points` is empty. Pass a translated string. */
  emptyLabel?: string
  className?: string
}

/**
 * Charting library boundary (spec 0107 D-7): `recharts` is imported ONLY by
 * `report-bar-chart-impl`, code-split here so it never lands in the initial
 * page chunk — same discipline `components/ui/stat-chart.tsx` already
 * enforces for its own lazy `AreaChart` (spec 0026 AC-013).
 */
const ReportBarChartImpl = React.lazy(() => import("@/components/ui/report-bar-chart-impl"))

/** Horizontal bar chart (lazy), for series whose labels are category/person names. Composes `Card`. */
function ReportBarChart({ title, points, formatValue, emptyLabel = "—", className }: ReportBarChartProps) {
  return (
    <Card className={cn("gap-2 py-3", className)}>
      <CardContent className="flex flex-col gap-2 px-3">
        <h3 className="truncate text-xs font-medium text-muted-foreground">{title}</h3>
        {points.length === 0 ? (
          <p className="text-xs text-muted-foreground">{emptyLabel}</p>
        ) : (
          <React.Suspense fallback={<Skeleton className="h-48 w-full sm:h-64" />}>
            <ReportBarChartImpl title={title} points={points} formatValue={formatValue} />
          </React.Suspense>
        )}
      </CardContent>
    </Card>
  )
}

export { ReportBarChart }
