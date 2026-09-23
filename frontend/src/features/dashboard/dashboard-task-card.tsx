import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { CircleCheck, Clock4 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Card } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import {
  DASHBOARD_TASK_TO_VALIDATE_CHIP_CLASS,
  type DashboardTaskCardTone,
} from '@/features/dashboard/dashboard-task-card-config'
import { formatStatValue } from '@/features/stats/format-stat-value'
import { cn } from '@/lib/utils'
import { formatMinutesLabel } from '@/features/time-entries/time-entry-format'

interface DashboardTaskCardToValidate {
  count: number
  href: string
}

interface DashboardTaskCardProps {
  title: string
  description: string
  value: number
  totalMinutes: number
  href: string
  icon: ReactNode
  tone: DashboardTaskCardTone
  toValidate?: DashboardTaskCardToValidate
}

/**
 * One "Attività da completare" metric card (spec 0151 AC-009): whole tile is
 * clickable (D-2 link to the filtered Task list), plus an OPTIONAL second link
 * for the "da validare" chip. The two links are siblings, not nested — an
 * absolutely positioned stretched link behind the content, and the chip link
 * raised on top of it with its own stacking context — so the markup stays
 * valid HTML (no `<a>` inside `<a>`, unlike q-net's own implementation).
 */
export function DashboardTaskCard({
  title,
  description,
  value,
  totalMinutes,
  href,
  icon,
  tone,
  toValidate,
}: DashboardTaskCardProps) {
  const { t, i18n } = useTranslation()

  return (
    <Card
      className={cn(
        'relative isolate flex h-full flex-col gap-3 overflow-hidden p-4 transition-all hover:-translate-y-0.5 hover:shadow-md motion-reduce:transition-none motion-reduce:hover:translate-y-0',
        tone.accent,
      )}
    >
      <div aria-hidden="true" className={cn('pointer-events-none absolute inset-0 -z-10 bg-gradient-to-br via-card to-card', tone.gradient)} />
      <Link
        to={href}
        aria-label={title}
        className="absolute inset-0 z-0 rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
      />

      <div className="flex items-start justify-between gap-3">
        <span className={cn('text-xs font-semibold', tone.title)}>{title}</span>
        <span
          className={cn(
            'flex size-9 shrink-0 items-center justify-center rounded-xl border shadow-sm [&_svg]:size-4',
            tone.iconBox,
          )}
        >
          {icon}
        </span>
      </div>

      <span className="text-2xl font-semibold tabular-nums">{formatStatValue(value, 'number', i18n.language)}</span>

      <div className="mt-auto flex flex-col gap-2">
        <TooltipProvider>
          <div className="flex flex-wrap items-center gap-2">
            <Tooltip>
              <TooltipTrigger asChild>
                <Badge variant="outline" className={tone.chip}>
                  <Clock4 aria-hidden="true" />
                  {formatMinutesLabel(totalMinutes)}
                </Badge>
              </TooltipTrigger>
              <TooltipContent>{t('dashboard.tasksSection.estimatedTime')}</TooltipContent>
            </Tooltip>

            {toValidate && toValidate.count > 0 ? (
              <Tooltip>
                <TooltipTrigger asChild>
                  <Link to={toValidate.href} className="relative z-10">
                    <Badge variant="outline" className={DASHBOARD_TASK_TO_VALIDATE_CHIP_CLASS}>
                      <CircleCheck aria-hidden="true" />
                      {formatStatValue(toValidate.count, 'number', i18n.language)}
                    </Badge>
                  </Link>
                </TooltipTrigger>
                <TooltipContent>{t('dashboard.tasksSection.toValidate')}</TooltipContent>
              </Tooltip>
            ) : null}
          </div>
        </TooltipProvider>

        <p className="text-xs text-muted-foreground">{description}</p>
      </div>
    </Card>
  )
}

/** Placeholder tile shaped like `DashboardTaskCard`, shown while the counters load. */
export function DashboardTaskCardSkeleton() {
  return (
    <Card className="flex h-full flex-col gap-3 p-4">
      <div className="flex items-start justify-between gap-3">
        <Skeleton className="h-3 w-20" />
        <Skeleton className="size-8 rounded-md" />
      </div>
      <Skeleton className="h-7 w-16" />
      <div className="mt-auto flex flex-col gap-2">
        <Skeleton className="h-5 w-24 rounded-full" />
        <Skeleton className="h-3 w-full" />
      </div>
    </Card>
  )
}
