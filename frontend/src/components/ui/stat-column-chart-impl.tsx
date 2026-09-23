import { Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts"

import type { StatColumnItem } from "@/components/ui/stat-column-chart"
import { CHART_AXIS_TICK, CHART_MARGIN, CHART_TOOLTIP_CONTENT_STYLE } from "@/components/ui/stat-chart-tokens"

/** Bar/column mark cap (marks-and-anatomy: <=24px thick, never fill the slot). */
const COLUMN_BAR_SIZE = 20

interface StatColumnChartImplProps {
  title: string
  items: StatColumnItem[]
  formatValue?: (value: number) => string
}

function defaultFormatValue(value: number): string {
  return value.toLocaleString()
}

/** Recharts-backed vertical bar chart. Loaded lazily by `StatColumnChart` — do not import it eagerly. */
export default function StatColumnChartImpl({
  title,
  items,
  formatValue = defaultFormatValue,
}: StatColumnChartImplProps) {
  return (
    <figure className="m-0 flex flex-col">
      {/* The chart itself is decorative for assistive tech: the data is exposed as text below. */}
      <div aria-hidden className="h-40 w-full sm:h-48">
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={items} margin={CHART_MARGIN}>
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
            <Bar dataKey="value" radius={[4, 4, 0, 0]} maxBarSize={COLUMN_BAR_SIZE} isAnimationActive={false}>
              {items.map((item) => (
                <Cell key={item.key} fill={item.color} />
              ))}
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      </div>
      <figcaption className="sr-only">{title}</figcaption>
      <ul className="sr-only">
        {items.map((item) => (
          <li key={item.key}>{`${item.label}: ${formatValue(item.value)}`}</li>
        ))}
      </ul>
    </figure>
  )
}
