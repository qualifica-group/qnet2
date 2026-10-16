import {
  CartesianGrid,
  Legend,
  Line,
  LineChart,
  ReferenceLine,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts"

import type { TrendLineChartProps } from "@/components/ui/trend-line-chart"
import {
  CHART_AXIS_TICK,
  CHART_SERIES_COLORS,
  CHART_TOOLTIP_CONTENT_STYLE,
} from "@/components/ui/stat-chart-tokens"

/** Room for half of the last category label, centred on the axis end, which would hang past the svg. */
const CHART_MARGIN = { top: 4, right: 24, bottom: 0, left: 4 } as const
/** Width recharts reserves for the value-axis labels (formatted amounts), in px. */
const VALUE_AXIS_WIDTH = 80
const LEGEND_STYLE = { fontSize: "0.75rem" } as const
/** Keeps zero inside the value axis so negative values draw below the baseline. */
const VALUE_AXIS_DOMAIN: [(min: number) => number, (max: number) => number] = [
  (min) => Math.min(0, min),
  (max) => Math.max(0, max),
]

function defaultFormatValue(value: number): string {
  return value.toLocaleString()
}

/** Series colors rotate over the `--chart-N` slots when there are more series than tokens. */
function seriesColor(index: number): string {
  return CHART_SERIES_COLORS[index % CHART_SERIES_COLORS.length]
}

/**
 * Recharts-backed line chart, one `Line` per series over the month categories.
 * Loaded lazily by `TrendLineChart`: do not import it eagerly.
 */
export default function TrendLineChartImpl({
  title,
  months,
  series,
  formatValue = defaultFormatValue,
}: Pick<TrendLineChartProps, "title" | "months" | "series" | "formatValue">) {
  const rows = months.map((month, index) => ({
    month,
    values: Object.fromEntries(series.map((item) => [item.key, item.values[index] ?? null])),
  }))
  const formatOptional = (value: number | null | undefined): string =>
    value === null || value === undefined ? "—" : formatValue(value)

  return (
    <figure className="m-0 flex flex-col">
      <div
        role="img"
        aria-label={`${title} (${series.map((item) => item.label).join(" / ")})`}
        // overflow-hidden: svg text is not clipped by default, so a long axis label at the
        // edge would add horizontal scroll to the whole page instead of staying in the card.
        className="h-48 w-full overflow-hidden sm:h-64"
      >
        <ResponsiveContainer width="100%" height="100%">
          <LineChart data={rows} margin={CHART_MARGIN}>
            <CartesianGrid stroke="var(--border)" strokeDasharray="3 3" vertical={false} />
            <XAxis dataKey="month" tick={CHART_AXIS_TICK} tickLine={false} axisLine={false} />
            <YAxis
              type="number"
              domain={VALUE_AXIS_DOMAIN}
              width={VALUE_AXIS_WIDTH}
              tick={CHART_AXIS_TICK}
              tickLine={false}
              axisLine={false}
              tickFormatter={(value: number) => formatValue(value)}
            />
            <ReferenceLine y={0} stroke="var(--border)" />
            <Tooltip
              contentStyle={CHART_TOOLTIP_CONTENT_STYLE}
              labelStyle={{ color: "var(--muted-foreground)" }}
              itemStyle={{ color: "var(--popover-foreground)" }}
              formatter={(value) => formatOptional(typeof value === "number" ? value : null)}
            />
            <Legend iconSize={10} wrapperStyle={LEGEND_STYLE} />
            {series.map((item, index) => (
              <Line
                key={item.key}
                type="monotone"
                name={item.label}
                dataKey={(row: (typeof rows)[number]) => row.values[item.key]}
                stroke={seriesColor(index)}
                strokeWidth={2}
                dot={{ r: 2 }}
                connectNulls={false}
                isAnimationActive={false}
              />
            ))}
          </LineChart>
        </ResponsiveContainer>
      </div>
      <figcaption className="sr-only">{title}</figcaption>
      <ul aria-label={title} className="sr-only">
        {rows.map((row) => (
          <li key={row.month}>
            {`${row.month}: ${series.map((item) => `${item.label} ${formatOptional(row.values[item.key])}`).join(", ")}`}
          </li>
        ))}
      </ul>
    </figure>
  )
}
