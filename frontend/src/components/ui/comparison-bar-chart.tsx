import * as React from "react"

import { Card, CardContent } from "@/components/ui/card"
import { Skeleton } from "@/components/ui/skeleton"
import { cn } from "@/lib/utils"

export interface ComparisonBarChartPoint {
  label: string
  budget: number
  actual: number
}

export interface ComparisonBarChartProps {
  title: string
  points: ComparisonBarChartPoint[]
  seriesLabels: { budget: string; actual: string }
  formatValue?: (value: number) => string
  /** Discreet placeholder shown when `points` is empty. Pass a translated string. */
  emptyLabel?: string
  /** Tag of the title, so the chart nests under the heading of the section hosting it. */
  headingLevel?: 2 | 3 | 4
  className?: string
}

/**
 * Charting library boundary (spec 0268 D-20): `recharts` is imported ONLY by
 * `comparison-bar-chart-impl`, code-split here so it never lands in the initial
 * page chunk, same discipline as `report-bar-chart.tsx`.
 */
const ComparisonBarChartImpl = React.lazy(() => import("@/components/ui/comparison-bar-chart-impl"))

/** Two-series horizontal bar chart (expected vs actual, lazy). Composes `Card`. */
function ComparisonBarChart({
  title,
  points,
  seriesLabels,
  formatValue,
  emptyLabel = "—",
  headingLevel = 3,
  className,
}: ComparisonBarChartProps) {
  const Heading = `h${headingLevel}` as const

  return (
    <Card className={cn("gap-2 py-3", className)}>
      <CardContent className="flex flex-col gap-2 px-3">
        <Heading className="truncate text-xs font-medium text-muted-foreground">{title}</Heading>
        {points.length === 0 ? (
          <p className="text-xs text-muted-foreground">{emptyLabel}</p>
        ) : (
          <React.Suspense fallback={<Skeleton className="h-48 w-full sm:h-64" />}>
            <ComparisonBarChartImpl
              title={title}
              points={points}
              seriesLabels={seriesLabels}
              formatValue={formatValue}
            />
          </React.Suspense>
        )}
      </CardContent>
    </Card>
  )
}

export { ComparisonBarChart }
