import * as React from "react"

import { Card, CardContent } from "@/components/ui/card"
import { PERCENT_MAX, toPercentage } from "@/components/ui/stat-chart-tokens"
import { cn } from "@/lib/utils"

/** Theme token used when the backend does not provide a per-item color. */
const DEFAULT_BAR_COLOR = "var(--chart-1)"

export interface StatBarItem {
  key: string
  label: string
  value: number
  color?: string | null
}

export interface StatBarListProps {
  title: string
  items: StatBarItem[]
  /** Denominator of the percentages. 0 is allowed and yields 0% (no division by zero). */
  total: number
  formatValue?: (value: number) => string
  /** Discreet placeholder shown when `items` is empty. Pass a translated string. */
  emptyLabel?: string
  className?: string
}

function defaultFormatValue(value: number): string {
  return value.toLocaleString()
}

/** Compact distribution widget: one proportional bar per item. Composes `Card`. */
function StatBarList({
  title,
  items,
  total,
  formatValue = defaultFormatValue,
  emptyLabel = "—",
  className,
}: StatBarListProps) {
  const baseId = React.useId()

  return (
    <Card className={cn("gap-2 py-3", className)}>
      <CardContent className="flex flex-col gap-2 px-3">
        <h3 className="truncate text-xs font-medium text-muted-foreground">{title}</h3>
        {items.length === 0 ? (
          <p className="text-xs text-muted-foreground">{emptyLabel}</p>
        ) : (
          <ul className="flex flex-col gap-2">
            {items.map((item) => {
              const percent = toPercentage(item.value, total)
              const roundedPercent = Math.round(percent)
              const labelId = `${baseId}-${item.key}`
              const formattedValue = formatValue(item.value)

              return (
                <li key={item.key} className="flex min-w-0 flex-col gap-1">
                  <div className="flex min-w-0 items-baseline justify-between gap-2 text-xs">
                    <span id={labelId} className="min-w-0 truncate font-medium">
                      {item.label}
                    </span>
                    <span className="shrink-0 tabular-nums">
                      {formattedValue}
                      <span className="ml-1.5 text-muted-foreground">{roundedPercent}%</span>
                    </span>
                  </div>
                  <div
                    role="meter"
                    aria-labelledby={labelId}
                    aria-valuenow={roundedPercent}
                    aria-valuemin={0}
                    aria-valuemax={PERCENT_MAX}
                    aria-valuetext={`${formattedValue} (${roundedPercent}%)`}
                    className="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                  >
                    <div
                      className="h-full rounded-full"
                      style={{
                        width: `${percent}%`,
                        backgroundColor: item.color ?? DEFAULT_BAR_COLOR,
                      }}
                    />
                  </div>
                </li>
              )
            })}
          </ul>
        )}
      </CardContent>
    </Card>
  )
}

export { StatBarList }
