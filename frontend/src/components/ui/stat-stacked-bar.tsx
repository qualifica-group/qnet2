import { Card, CardContent } from "@/components/ui/card"
import { toPercentage } from "@/components/ui/stat-chart-tokens"
import { StatDistributionLegend, type StatDistributionLegendItem } from "@/components/ui/stat-distribution-legend"
import { cn } from "@/lib/utils"

export type StatStackedItem = StatDistributionLegendItem

export interface StatStackedBarProps {
  title: string
  items: StatStackedItem[]
  /** Denominator of the percentages (0 allowed). */
  total: number
  formatValue?: (value: number) => string
  /** Discreet placeholder shown when `items` is empty. Pass a translated string. */
  emptyLabel?: string
  className?: string
}

function defaultFormatValue(value: number): string {
  return value.toLocaleString()
}

/**
 * Part-to-whole distribution widget: a single 100%-wide bar of proportional
 * segments (2px surface gap between them) plus a visible legend (label,
 * value, percent — never color-only). No `recharts`: plain DOM, so unlike
 * the other new renderings it does not need a lazy boundary. Composes `Card`.
 */
function StatStackedBar({
  title,
  items,
  total,
  formatValue = defaultFormatValue,
  emptyLabel = "—",
  className,
}: StatStackedBarProps) {
  return (
    <Card className={cn("gap-2 py-3", className)}>
      <CardContent className="flex flex-col gap-2 px-3">
        <h3 className="truncate text-xs font-medium text-muted-foreground">{title}</h3>
        {items.length === 0 ? (
          <p className="text-xs text-muted-foreground">{emptyLabel}</p>
        ) : (
          <figure className="m-0 flex flex-col gap-2">
            {/* The bar itself is decorative for assistive tech: the legend below carries the data as text. */}
            <div
              aria-hidden
              className="flex h-3 w-full gap-0.5 overflow-hidden rounded-full"
            >
              {items.map((item) => (
                <div
                  key={item.key}
                  className="h-full"
                  style={{
                    width: `${toPercentage(item.value, total)}%`,
                    backgroundColor: item.color,
                  }}
                />
              ))}
            </div>
            <figcaption className="sr-only">{title}</figcaption>
            <StatDistributionLegend items={items} total={total} formatValue={formatValue} />
          </figure>
        )}
      </CardContent>
    </Card>
  )
}

export { StatStackedBar }
