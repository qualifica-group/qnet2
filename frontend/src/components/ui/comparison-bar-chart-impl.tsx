import {
  Bar,
  BarChart,
  CartesianGrid,
  Legend,
  ReferenceLine,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts"

import type { ComparisonBarChartProps } from "@/components/ui/comparison-bar-chart"
import {
  CHART_AXIS_TICK,
  CHART_SERIES_COLORS,
  CHART_TOOLTIP_CONTENT_STYLE,
} from "@/components/ui/stat-chart-tokens"

const BUDGET_COLOR = CHART_SERIES_COLORS[0]
const ACTUAL_COLOR = CHART_SERIES_COLORS[1]
/**
 * The right margin leaves room for half of the last value-axis label (centred on
 * the axis end, e.g. "-22.424,35 €"), which would otherwise hang past the svg.
 */
const CHART_MARGIN = { top: 4, right: 48, bottom: 0, left: 4 } as const
/** Width recharts reserves for the category axis labels, in px. */
const CATEGORY_AXIS_WIDTH = 96
/** Vertical room per category (a pair of bars plus its label) and the fixed part (axis, legend), in px. */
const POINT_HEIGHT = 32
const CHART_CHROME_HEIGHT = 56
const LEGEND_STYLE = { fontSize: "0.75rem" } as const
/** Keeps zero inside the value axis so negative values draw below the baseline. */
const VALUE_AXIS_DOMAIN: [(min: number) => number, (max: number) => number] = [
  (min) => Math.min(0, min),
  (max) => Math.max(0, max),
]

function defaultFormatValue(value: number): string {
  return value.toLocaleString()
}

/**
 * Recharts-backed grouped bar chart (`layout="vertical"`): one pair of bars
 * (budget, actual) per point. Loaded lazily by `ComparisonBarChart`: do not
 * import it eagerly.
 */
export default function ComparisonBarChartImpl({
  title,
  points,
  seriesLabels,
  formatValue = defaultFormatValue,
}: Omit<ComparisonBarChartProps, "emptyLabel" | "className">) {
  return (
    <figure className="m-0 flex flex-col">
      <div
        role="img"
        aria-label={`${title} (${seriesLabels.budget} / ${seriesLabels.actual})`}
        // overflow-hidden: svg text is not clipped by default, so a long axis label at the
        // edge would add horizontal scroll to the whole page instead of staying in the card.
        className="h-48 w-full overflow-hidden sm:h-64"
        // Content-driven minimum on the inner box only: with many categories the bars would shrink to
        // slivers and recharts would drop axis labels. h-48/sm:h-64 stay the floor for few points.
        style={{ minHeight: points.length * POINT_HEIGHT + CHART_CHROME_HEIGHT }}
      >
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={points} layout="vertical" margin={CHART_MARGIN}>
            <CartesianGrid stroke="var(--border)" strokeDasharray="3 3" horizontal={false} />
            <XAxis
              type="number"
              domain={VALUE_AXIS_DOMAIN}
              tick={CHART_AXIS_TICK}
              tickLine={false}
              axisLine={false}
              tickFormatter={(value: number) => formatValue(value)}
            />
            <YAxis
              type="category"
              dataKey="label"
              width={CATEGORY_AXIS_WIDTH}
              tick={CHART_AXIS_TICK}
              tickLine={false}
              axisLine={false}
            />
            <ReferenceLine x={0} stroke="var(--border)" />
            <Tooltip
              cursor={{ fill: "var(--muted)" }}
              contentStyle={CHART_TOOLTIP_CONTENT_STYLE}
              labelStyle={{ color: "var(--muted-foreground)" }}
              itemStyle={{ color: "var(--popover-foreground)" }}
              formatter={(value) => formatValue(Number(value))}
            />
            <Legend iconSize={10} wrapperStyle={LEGEND_STYLE} />
            <Bar
              dataKey="budget"
              name={seriesLabels.budget}
              fill={BUDGET_COLOR}
              radius={[0, 4, 4, 0]}
              isAnimationActive={false}
            />
            <Bar
              dataKey="actual"
              name={seriesLabels.actual}
              fill={ACTUAL_COLOR}
              radius={[0, 4, 4, 0]}
              isAnimationActive={false}
            />
          </BarChart>
        </ResponsiveContainer>
      </div>
      <figcaption className="sr-only">{title}</figcaption>
      <ul aria-label={title} className="sr-only">
        {points.map((point) => (
          <li key={point.label}>
            {`${point.label}: ${seriesLabels.budget} ${formatValue(point.budget)}, ${seriesLabels.actual} ${formatValue(point.actual)}`}
          </li>
        ))}
      </ul>
    </figure>
  )
}
