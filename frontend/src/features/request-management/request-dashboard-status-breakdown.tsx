import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { HoverCard, HoverCardContent, HoverCardTrigger } from '@/components/ui/hover-card'
import type { RequestDashboardChartPoint } from '@/features/request-management/dashboard-api'
import { cn } from '@/lib/utils'

export interface DashboardStatusBreakdownProps {
  /** What the number counts (the tile label, or "operator - column"), to name the trigger. */
  label: string
  lines: RequestDashboardChartPoint[]
  /** The number, which becomes the hover/focus trigger. */
  children: ReactNode
  className?: string
}

/**
 * Task 427193: hovering (or focusing) a "Potenziali" number — a KPI tile or
 * an operator's cell of the ranking — opens its split by current working
 * status. The same lines are in a screen-reader list, since a hover card is
 * never announced.
 */
export function DashboardStatusBreakdown({ label, lines, children, className }: DashboardStatusBreakdownProps) {
  const { t } = useTranslation()
  const describe = (line: RequestDashboardChartPoint) =>
    t('requestManagement.dashboard.breakdown.item', { label: line.label, value: line.value.toLocaleString() })

  return (
    <>
      <HoverCard openDelay={150}>
        <HoverCardTrigger asChild>
          <button
            type="button"
            aria-label={t('requestManagement.dashboard.breakdown.trigger', { label })}
            className={cn(
              'cursor-help rounded-sm underline decoration-muted-foreground/50 decoration-dotted underline-offset-4 outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
              className,
            )}
          >
            {children}
          </button>
        </HoverCardTrigger>
        <HoverCardContent align="start" className="w-64 max-w-[calc(100vw-2rem)] p-3">
          <p className="mb-2 text-xs font-medium text-muted-foreground">
            {t('requestManagement.dashboard.breakdown.title')}
          </p>
          <ul className="flex flex-col gap-1 text-xs">
            {lines.map((line) => (
              <li key={line.label} className="flex items-center justify-between gap-3">
                <span className="min-w-0 truncate" title={line.label}>
                  {line.label}
                </span>
                <span className="shrink-0 font-medium tabular-nums">{line.value.toLocaleString()}</span>
              </li>
            ))}
          </ul>
        </HoverCardContent>
      </HoverCard>
      <ul className="sr-only" aria-label={t('requestManagement.dashboard.breakdown.title')}>
        {lines.map((line) => (
          <li key={line.label}>{describe(line)}</li>
        ))}
      </ul>
    </>
  )
}
