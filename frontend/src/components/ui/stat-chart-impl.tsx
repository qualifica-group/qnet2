import * as React from "react"
import {
  Area,
  AreaChart,
  Bar,
  BarChart,
  CartesianGrid,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts"

import type { StatChartPoint, StatChartVariant } from "@/components/ui/stat-chart"
import {
  CHART_AXIS_TICK,
  CHART_MARGIN,
  CHART_SURFACE_RING_COLOR,
  CHART_TOOLTIP_CONTENT_STYLE,
  chartToneColor,
} from "@/components/ui/stat-chart-tokens"

/** Bar/column mark cap (marks-and-anatomy: <=24px thick, never fill the slot). */
const COLUMN_BAR_SIZE = 20
/** Marker radius >=4 (>=8px diameter), per the mark spec. */
const LINE_DOT_RADIUS = 4

interface StatChartImplProps {
  title: string
  points: StatChartPoint[]
  formatValue?: (value: number) => string
  variant: StatChartVariant
  tone: 1 | 2 | 3 | 4 | 5
}

function defaultFormatValue(value: number): string {
  return value.toLocaleString()
}

interface ChartBodyProps {
  points: StatChartPoint[]
  formatValue: (value: number) => string
  color: string
  gradientId: string
}

/**
 * Returns the `AreaChart` element itself (not a wrapping component): it must
 * be `ResponsiveContainer`'s DIRECT child, since recharts measures/clones
 * that exact element — a custom component in between never receives the
 * injected width/height and renders a 0x0 chart.
 */
function renderAreaChart({ points, formatValue, color, gradientId }: ChartBodyProps) {
  return (
    <AreaChart data={points} margin={CHART_MARGIN}>
      <defs>
        <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor={color} stopOpacity={0.35} />
          <stop offset="100%" stopColor={color} stopOpacity={0.02} />
        </linearGradient>
      </defs>
      <CartesianGrid stroke="var(--border)" strokeDasharray="3 3" vertical={false} />
      <XAxis
        dataKey="label"
        tick={CHART_AXIS_TICK}
        tickLine={false}
        axisLine={false}
        interval="preserveStartEnd"
        minTickGap={12}
      />
      <YAxis
        width={36}
        tick={CHART_AXIS_TICK}
        tickLine={false}
        axisLine={false}
        allowDecimals={false}
        tickFormatter={(value: number) => formatValue(value)}
      />
      <Tooltip
        cursor={{ stroke: "var(--border)" }}
        contentStyle={CHART_TOOLTIP_CONTENT_STYLE}
        labelStyle={{ color: "var(--muted-foreground)" }}
        itemStyle={{ color: "var(--popover-foreground)" }}
        formatter={(value) => formatValue(Number(value))}
      />
      <Area
        type="monotone"
        dataKey="value"
        stroke={color}
        strokeWidth={2}
        fill={`url(#${gradientId})`}
        isAnimationActive={false}
      />
    </AreaChart>
  )
}

/** Same constraint as `renderAreaChart`: returns the `BarChart` element directly. */
function renderColumnsChart({ points, formatValue, color }: ChartBodyProps) {
  return (
    <BarChart data={points} margin={CHART_MARGIN}>
      <CartesianGrid stroke="var(--border)" strokeDasharray="3 3" vertical={false} />
      <XAxis dataKey="label" tick={CHART_AXIS_TICK} tickLine={false} axisLine={false} />
      <YAxis
        width={36}
        tick={CHART_AXIS_TICK}
        tickLine={false}
        axisLine={false}
        allowDecimals={false}
        tickFormatter={(value: number) => formatValue(value)}
      />
      <Tooltip
        cursor={{ fill: "var(--muted)" }}
        contentStyle={CHART_TOOLTIP_CONTENT_STYLE}
        labelStyle={{ color: "var(--muted-foreground)" }}
        itemStyle={{ color: "var(--popover-foreground)" }}
        formatter={(value) => formatValue(Number(value))}
      />
      <Bar dataKey="value" fill={color} radius={[4, 4, 0, 0]} maxBarSize={COLUMN_BAR_SIZE} isAnimationActive={false} />
    </BarChart>
  )
}

/** Same constraint as `renderAreaChart`: returns the `LineChart` element directly. */
function renderLineChart({ points, formatValue, color }: ChartBodyProps) {
  return (
    <LineChart data={points} margin={CHART_MARGIN}>
      <CartesianGrid stroke="var(--border)" strokeDasharray="3 3" vertical={false} />
      <XAxis dataKey="label" tick={CHART_AXIS_TICK} tickLine={false} axisLine={false} />
      <YAxis
        width={36}
        tick={CHART_AXIS_TICK}
        tickLine={false}
        axisLine={false}
        allowDecimals={false}
        tickFormatter={(value: number) => formatValue(value)}
      />
      <Tooltip
        cursor={{ stroke: "var(--border)" }}
        contentStyle={CHART_TOOLTIP_CONTENT_STYLE}
        labelStyle={{ color: "var(--muted-foreground)" }}
        itemStyle={{ color: "var(--popover-foreground)" }}
        formatter={(value) => formatValue(Number(value))}
      />
      <Line
        type="monotone"
        dataKey="value"
        stroke={color}
        strokeWidth={2}
        dot={{ r: LINE_DOT_RADIUS, fill: color, stroke: CHART_SURFACE_RING_COLOR, strokeWidth: 2 }}
        activeDot={{ r: LINE_DOT_RADIUS + 1, fill: color, stroke: CHART_SURFACE_RING_COLOR, strokeWidth: 2 }}
        isAnimationActive={false}
      />
    </LineChart>
  )
}

/** Recharts-backed trend chart. Loaded lazily by `StatChart` — do not import it eagerly. */
export default function StatChartImpl({
  title,
  points,
  formatValue = defaultFormatValue,
  variant,
  tone,
}: StatChartImplProps) {
  const gradientId = `${React.useId()}-fill`
  const color = chartToneColor(tone)
  const bodyProps: ChartBodyProps = { points, formatValue, color, gradientId }

  return (
    <figure className="m-0 flex flex-col">
      {/* The chart itself is decorative for assistive tech: the data is exposed as text below. */}
      <div aria-hidden className="h-40 w-full sm:h-48">
        <ResponsiveContainer width="100%" height="100%">
          {variant === "columns"
            ? renderColumnsChart(bodyProps)
            : variant === "line"
              ? renderLineChart(bodyProps)
              : renderAreaChart(bodyProps)}
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
