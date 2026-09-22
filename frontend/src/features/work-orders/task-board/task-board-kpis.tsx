/**
 * The global KPI strip above the board (D-5): six compact card-tiles over
 * `computeBoardMetrics`'s own pure numbers. Minutes go through the SAME
 * duration formatter every other minutes readout in the app uses
 * (`formatMinutesLabel`, `time-entries/time-entry-format.ts`) rather than
 * the raw-minutes `kpi.minutesValue` i18n string, so "Stimato"/"Effettivo"
 * read as `7h 30m` here exactly like everywhere else.
 */

import { AlertTriangle, CalendarClock, CheckCircle2, Clock, ListChecks, Timer } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Progress } from '@/components/ui/progress'
import { cn } from '@/lib/utils'
import { formatMinutesLabel } from '@/features/time-entries/time-entry-format'
import type { TaskBoardMetrics } from '@/features/work-orders/task-board/task-board-metrics'

interface KpiTileProps {
  icon: typeof ListChecks
  label: string
  value: string
  accent?: boolean
  progress?: number
}

/** Raised white tiles on the board canvas; the icon sits in a tinted chip so each tile reads at a glance. */
const TILE_CLASS = 'flex min-w-0 flex-1 basis-36 items-start gap-3 rounded-xl bg-card p-3 shadow-sm ring-1 ring-border/70'
const ICON_CHIP_CLASS = 'flex size-8 shrink-0 items-center justify-center rounded-lg'

function KpiTile({ icon: Icon, label, value, accent = false, progress }: KpiTileProps) {
  return (
    <div className={cn(TILE_CLASS, accent && 'ring-destructive/40')}>
      <span
        aria-hidden="true"
        className={cn(ICON_CHIP_CLASS, accent ? 'bg-destructive/10 text-destructive' : 'bg-primary/10 text-primary')}
      >
        <Icon className="size-4" />
      </span>
      <div className="flex min-w-0 flex-1 flex-col gap-0.5">
        <span className="truncate text-xs text-muted-foreground">{label}</span>
        <span className={cn('text-lg leading-tight font-semibold tabular-nums', accent && 'text-destructive')}>{value}</span>
        {progress !== undefined ? <Progress value={progress} size="xs" className="mt-1" aria-label={label} /> : null}
      </div>
    </div>
  )
}

interface TaskBoardKpisProps {
  metrics: TaskBoardMetrics
}

export function TaskBoardKpis({ metrics }: TaskBoardKpisProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-wrap gap-3">
      <KpiTile icon={ListChecks} label={t('workOrders.taskBoard.kpi.total')} value={String(metrics.total)} />
      <KpiTile
        icon={CheckCircle2}
        label={t('workOrders.taskBoard.kpi.completion')}
        value={t('tasks.form.percentValue', { value: metrics.completionPercentage })}
        progress={metrics.completionPercentage}
      />
      <KpiTile
        icon={AlertTriangle}
        label={t('workOrders.taskBoard.kpi.overdue')}
        value={String(metrics.overdueCount)}
        accent={metrics.overdueCount > 0}
      />
      <KpiTile
        icon={CalendarClock}
        label={t('workOrders.taskBoard.kpi.dueToday')}
        value={String(metrics.dueTodayCount)}
      />
      <KpiTile icon={Timer} label={t('workOrders.taskBoard.kpi.estimated')} value={formatMinutesLabel(metrics.estimatedMinutes)} />
      <KpiTile icon={Clock} label={t('workOrders.taskBoard.kpi.actual')} value={formatMinutesLabel(metrics.actualMinutes)} />
    </div>
  )
}
