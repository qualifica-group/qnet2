/**
 * The "Overview" card (spec 0122 D-11, AC-034): header + 4 KPI tiles — Target
 * di periodo, Tracciato, Focus medio, Anomalie. Mirrors q-net's tile grid
 * inside `WorkActivitiesStatsPanels` (structure/density/colors, D-2), tinted
 * through `TIME_ENTRY_KPI_TONE_CLASSES` instead of inline Tailwind per tile.
 */

import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import {
  AlertTriangle,
  BarChart3,
  Clock,
  Target,
  TrendingDown,
  TrendingUp,
  type LucideIcon,
} from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { cn } from '@/lib/utils'
import { formatMinutesLabel } from '@/features/time-entries/time-entry-format'
import {
  TIME_ENTRY_ANOMALY_PILL_CLASSES,
  TIME_ENTRY_KPI_TONE_CLASSES,
  type TimeEntryKpiTone,
} from '@/features/time-entries/dashboard/time-entry-kpi-tone'
import type { OverviewStats } from '@/features/time-entries/types'

interface TimeEntriesOverviewTilesProps {
  overview?: OverviewStats
  isLoading?: boolean
  className?: string
}

function TileSkeleton() {
  return (
    <div className="flex h-full min-w-0 flex-col rounded-2xl border border-border bg-muted/20 p-4 shadow-sm">
      <div className="flex items-start justify-between gap-3">
        <Skeleton className="h-3 w-24" />
        <Skeleton className="size-10 rounded-xl" />
      </div>
      <div className="mt-auto space-y-1.5 pt-3">
        <Skeleton className="h-6 w-24" />
        <Skeleton className="h-3 w-32" />
      </div>
    </div>
  )
}

interface KpiTileProps {
  tone: TimeEntryKpiTone
  label: string
  icon: LucideIcon
  value: ReactNode
  hint: ReactNode
}

function KpiTile({ tone, label, icon: Icon, value, hint }: KpiTileProps) {
  const classes = TIME_ENTRY_KPI_TONE_CLASSES[tone]
  return (
    <div
      className={cn(
        'group relative flex h-full min-w-0 flex-col overflow-hidden rounded-2xl border p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md',
        classes.container,
      )}
    >
      <div
        aria-hidden="true"
        className={cn('pointer-events-none absolute -top-4 -right-4 size-20 rounded-full blur-2xl', classes.glow)}
      />
      <div className="relative flex items-start justify-between gap-3">
        <div className={cn('text-[11px] font-semibold tracking-wide uppercase', classes.label)}>{label}</div>
        <span className={cn('inline-flex size-10 shrink-0 items-center justify-center rounded-xl shadow-sm', classes.iconChip)}>
          <Icon className="size-5" aria-hidden="true" />
        </span>
      </div>
      <div className="relative mt-auto pt-3">
        <div className={cn('text-2xl font-bold tracking-tight break-words', classes.value)}>{value}</div>
        <div className={cn('mt-1 text-xs break-words', classes.hint)}>{hint}</div>
      </div>
    </div>
  )
}

export function TimeEntriesOverviewTiles({ overview, isLoading = false, className }: TimeEntriesOverviewTilesProps) {
  const { t } = useTranslation()

  return (
    <Card className={cn('gap-2 py-3 shadow-sm', className)}>
      <CardHeader>
        <CardTitle className="text-base">{t('timeEntries.kpi.title')}</CardTitle>
      </CardHeader>
      <CardContent className="px-3">
        <div className="grid gap-2 md:grid-cols-2 2xl:grid-cols-4">
          {isLoading || !overview ? (
            <>
              <TileSkeleton />
              <TileSkeleton />
              <TileSkeleton />
              <TileSkeleton />
            </>
          ) : (
            <>
              <KpiTile
                tone="sky"
                label={t('timeEntries.kpi.periodTarget')}
                icon={Target}
                value={
                  <>
                    {formatMinutesLabel(overview.period_target_minutes)}
                    <span className="ml-1.5 text-sm font-medium opacity-70">
                      {t('timeEntries.kpi.periodTargetPerDay', {
                        value: formatMinutesLabel(overview.daily_target_minutes),
                      })}
                    </span>
                  </>
                }
                hint={t('timeEntries.kpi.periodTargetCaption')}
              />

              <KpiTile
                tone="emerald"
                label={t('timeEntries.kpi.tracked')}
                icon={Clock}
                value={formatMinutesLabel(overview.tracked_minutes)}
                hint={
                  overview.tracked_days > 0
                    ? t('timeEntries.kpi.trackedDaysCount', { days: overview.tracked_days })
                    : t('timeEntries.pulse.noData')
                }
              />

              <KpiTile
                tone="violet"
                label={t('timeEntries.kpi.averageFocus')}
                icon={BarChart3}
                value={formatMinutesLabel(overview.average_daily_focus_minutes)}
                hint={t('timeEntries.kpi.averageFocusCaption')}
              />

              <KpiTile
                tone="rose"
                label={t('timeEntries.kpi.anomalies')}
                icon={AlertTriangle}
                value={overview.anomalies.over_target_days + overview.anomalies.under_target_days}
                hint={
                  <span className="flex flex-wrap gap-x-2 gap-y-1">
                    <span
                      className={cn(
                        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-medium',
                        TIME_ENTRY_ANOMALY_PILL_CLASSES.over,
                      )}
                    >
                      <TrendingUp className="size-3.5" aria-hidden="true" />
                      <span className="font-semibold">{overview.anomalies.over_target_days}</span>
                      <span>{t('timeEntries.kpi.anomaliesOverTarget')}</span>
                    </span>
                    <span
                      className={cn(
                        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-medium',
                        TIME_ENTRY_ANOMALY_PILL_CLASSES.under,
                      )}
                    >
                      <TrendingDown className="size-3.5" aria-hidden="true" />
                      <span className="font-semibold">{overview.anomalies.under_target_days}</span>
                      <span>{t('timeEntries.kpi.anomaliesUnderTarget')}</span>
                    </span>
                  </span>
                }
              />
            </>
          )}
        </div>
      </CardContent>
    </Card>
  )
}
