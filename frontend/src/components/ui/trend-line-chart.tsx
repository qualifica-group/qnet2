import * as React from "react"

import { Card, CardContent } from "@/components/ui/card"
import { Skeleton } from "@/components/ui/skeleton"
import { cn } from "@/lib/utils"

export interface TrendLineSeries {
  key: string
  label: string
  /** One value per month, same order as `months`; `null` leaves a gap in the line. */
  values: (number | null)[]
}

export interface TrendLineChartProps {
  title: string
  /** Category labels (already translated by the caller), one per point. */
  months: string[]
  series: TrendLineSeries[]
  formatValue?: (value: number) => string
  /** Discreet placeholder shown when there are no series or no months. Pass a translated string. */
  emptyLabel?: string
  className?: string
  /** Tag of the title, so the chart nests under the heading of the section hosting it. */
  headingLevel?: 2 | 3 | 4
}

/**
 * Charting library boundary (spec 0270 D-12): `recharts` is imported ONLY by
 * `trend-line-chart-impl`, code-split here so it never lands in the initial
 * page chunk, same discipline as `comparison-bar-chart.tsx`.
 */
const TrendLineChartImpl = React.lazy(() => import("@/components/ui/trend-line-chart-impl"))

/** Multi-series line chart over a time axis (lazy). Composes `Card`. */
function TrendLineChart({
  title,
  months,
  series,
  formatValue,
  emptyLabel = "—",
  className,
  headingLevel = 3,
}: TrendLineChartProps) {
  const Heading = `h${headingLevel}` as const
  const isEmpty = series.length === 0 || months.length === 0

  return (
    <Card className={cn("gap-2 py-3", className)}>
      <CardContent className="flex flex-col gap-2 px-3">
        <Heading className="truncate text-xs font-medium text-muted-foreground">{title}</Heading>
        {isEmpty ? (
          <p className="text-xs text-muted-foreground">{emptyLabel}</p>
        ) : (
          <React.Suspense fallback={<Skeleton className="h-48 w-full sm:h-64" />}>
            <TrendLineChartImpl title={title} months={months} series={series} formatValue={formatValue} />
          </React.Suspense>
        )}
      </CardContent>
    </Card>
  )
}

export { TrendLineChart }
