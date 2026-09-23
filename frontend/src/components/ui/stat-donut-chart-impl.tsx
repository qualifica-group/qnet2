import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from "recharts"

import type { StatDonutItem } from "@/components/ui/stat-donut-chart"
import { CHART_SURFACE_RING_COLOR, CHART_TOOLTIP_CONTENT_STYLE } from "@/components/ui/stat-chart-tokens"
import { StatDistributionLegend } from "@/components/ui/stat-distribution-legend"

const INNER_RADIUS = "60%"
const OUTER_RADIUS = "85%"
/** Angular surface gap between adjacent wedges (marks-and-anatomy: the 2px gap, here in the ring). */
const WEDGE_GAP_ANGLE = 1

interface StatDonutChartImplProps {
  title: string
  items: StatDonutItem[]
  total: number
  formatValue?: (value: number) => string
}

function defaultFormatValue(value: number): string {
  return value.toLocaleString()
}

/** Recharts-backed donut chart. Loaded lazily by `StatDonutChart` — do not import it eagerly. */
export default function StatDonutChartImpl({
  title,
  items,
  total,
  formatValue = defaultFormatValue,
}: StatDonutChartImplProps) {
  return (
    <figure className="m-0 flex flex-col gap-2">
      <div aria-hidden className="relative h-40 w-full sm:h-48">
        <ResponsiveContainer width="100%" height="100%">
          <PieChart>
            <Pie
              data={items}
              dataKey="value"
              nameKey="label"
              innerRadius={INNER_RADIUS}
              outerRadius={OUTER_RADIUS}
              paddingAngle={WEDGE_GAP_ANGLE}
              stroke={CHART_SURFACE_RING_COLOR}
              strokeWidth={2}
              isAnimationActive={false}
            >
              {items.map((item) => (
                <Cell key={item.key} fill={item.color} />
              ))}
            </Pie>
            <Tooltip
              contentStyle={CHART_TOOLTIP_CONTENT_STYLE}
              itemStyle={{ color: "var(--popover-foreground)" }}
              formatter={(value, name) => [formatValue(Number(value)), String(name)]}
            />
          </PieChart>
        </ResponsiveContainer>
        {/* Total at the center (D-2, spec 0152); proportional figures, not tabular (marks-and-anatomy). */}
        <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
          <span className="text-base font-semibold sm:text-lg">{formatValue(total)}</span>
        </div>
      </div>
      <figcaption className="sr-only">{title}</figcaption>
      {/* The legend IS the accessible text list: label, value and percent as real text, never color-only. */}
      <StatDistributionLegend items={items} total={total} formatValue={formatValue} />
    </figure>
  )
}
