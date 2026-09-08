import * as React from "react"
import {
  Bar,
  BarChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts"

import type { ReportBarChartPoint } from "@/components/ui/report-bar-chart"

/** Every theme color comes from a CSS variable so the chart follows dark mode (spec 0107 constraint). */
const SERIES_COLOR = "var(--chart-1)"
const AXIS_TICK = { fontSize: 10, fill: "var(--muted-foreground)" } as const
const TOOLTIP_CONTENT_STYLE: React.CSSProperties = {
  backgroundColor: "var(--popover)",
  border: "1px solid var(--border)",
  borderRadius: "var(--radius-md)",
  color: "var(--popover-foreground)",
  fontSize: "0.75rem",
  padding: "0.25rem 0.5rem",
}
const CHART_MARGIN = { top: 4, right: 8, bottom: 0, left: 4 } as const
/** Width recharts reserves for the category axis's labels, in px. */
const CATEGORY_AXIS_WIDTH = 96

interface ReportBarChartImplProps {
  title: string
  points: ReportBarChartPoint[]
  formatValue?: (value: number) => string
}

function defaultFormatValue(value: number): string {
  return value.toLocaleString()
}

/**
 * Recharts-backed horizontal bar chart (`layout="vertical"`, spec 0107 D-7):
 * category/person names read left-to-right instead of rotated on an X axis.
 * Loaded lazily by `ReportBarChart` — do not import it eagerly.
 */
export default function ReportBarChartImpl({
  title,
  points,
  formatValue = defaultFormatValue,
}: ReportBarChartImplProps) {
  return (
    <figure className="m-0 flex flex-col">
      {/* The chart itself is decorative for assistive tech: the data is exposed as text below. */}
      <div aria-hidden className="h-48 w-full sm:h-64">
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={points} layout="vertical" margin={CHART_MARGIN}>
            <CartesianGrid stroke="var(--border)" strokeDasharray="3 3" horizontal={false} />
            <XAxis
              type="number"
              tick={AXIS_TICK}
              tickLine={false}
              axisLine={false}
              allowDecimals={false}
              tickFormatter={(value: number) => formatValue(value)}
            />
            <YAxis
              type="category"
              dataKey="label"
              width={CATEGORY_AXIS_WIDTH}
              tick={AXIS_TICK}
              tickLine={false}
              axisLine={false}
            />
            <Tooltip
              cursor={{ fill: "var(--muted)" }}
              contentStyle={TOOLTIP_CONTENT_STYLE}
              labelStyle={{ color: "var(--muted-foreground)" }}
              itemStyle={{ color: "var(--popover-foreground)" }}
              formatter={(value) => formatValue(Number(value))}
            />
            <Bar dataKey="value" fill={SERIES_COLOR} radius={[0, 4, 4, 0]} isAnimationActive={false} />
          </BarChart>
        </ResponsiveContainer>
      </div>
      <figcaption className="sr-only">{title}</figcaption>
      <ul className="sr-only">
        {points.map((point) => (
          <li key={point.label}>{`${point.label}: ${formatValue(point.value)}`}</li>
        ))}
      </ul>
    </figure>
  )
}
