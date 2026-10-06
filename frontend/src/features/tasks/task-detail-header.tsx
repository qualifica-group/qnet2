import { useTranslation } from 'react-i18next'
import { ListChecks, Repeat, ShieldAlert } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { CompletionBar } from '@/components/completion-bar'
import { cn } from '@/lib/utils'
import { formatDate } from '@/lib/formatting/date-display'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader, RecordStat, RecordStatStrip } from '@/components/detail/record-panel'
import { BADGE_BASE, BADGE_COLOR_CLASSES } from '@/features/table/cell-renderers'
import { TaskLookupBadge } from '@/features/tasks/task-lookup-badge'
import { formatTaskRecurrenceRule } from '@/features/tasks/task-recurrence-format'
import { formatMinutesLabel } from '@/features/time-entries/time-entry-format'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'

/**
 * Identity band and KPI strip of the task record card, mirroring
 * `opportunity-detail-header.tsx`: both pieces read the same top-level fields
 * and are always mounted together.
 */

interface TaskDetailHeaderProps {
  task: TaskDetailWithPermissions
}

/**
 * Identity band: monogram, title, parent subtitle and the configured lookup
 * badges. No "Modifica" action: the fields below edit in place (spec 0195).
 *
 * AC-086: "Bloccato/contestato" is its OWN badge, visually and semantically
 * separate from the status pill — a flag, not a phase.
 */
export function TaskDetailHeader({ task }: TaskDetailHeaderProps) {
  const { t, i18n } = useTranslation()

  return (
    <RecordCardHeader
      media={
        <DetailMonogram name={task.title} icon={<ListChecks />} className="size-10 text-base [&>svg]:size-5" />
      }
      title={task.title}
      subtitle={task.parent_task ? t('tasks.detail.childOf', { title: task.parent_task.title }) : undefined}
      badges={
        <>
          <TaskLookupBadge value={task.task_status} />
          <TaskLookupBadge value={task.task_type} />
          <TaskLookupBadge value={task.task_priority} />
          <TaskLookupBadge value={task.task_importance} />
          <TaskLookupBadge value={task.task_category} />
          {task.is_blocked ? (
            <Badge variant="secondary" className={cn(BADGE_BASE, 'gap-1.5', BADGE_COLOR_CLASSES.red)}>
              <ShieldAlert className="size-3.5 shrink-0" aria-hidden="true" />
              {t('tasks.detail.blocked')}
            </Badge>
          ) : null}
          {task.recurrence ? (
            <Badge variant="secondary" className={cn(BADGE_BASE, 'gap-1.5', BADGE_COLOR_CLASSES.indigo)}>
              <Repeat className="size-3.5 shrink-0" aria-hidden="true" />
              {formatTaskRecurrenceRule(task.recurrence, t, i18n.language)}
            </Badge>
          ) : null}
        </>
      }
    />
  )
}

interface TaskDetailStatsProps {
  task: TaskDetailWithPermissions
}

/**
 * KPI strip: the derived completion (AC-084: `completion_percentage` is
 * computed server-side from the status, D-6), start and end dates, estimate.
 */
export function TaskDetailStats({ task }: TaskDetailStatsProps) {
  return (
    <TaskStatsStrip
      completionPercentage={task.completion_percentage}
      startDate={task.start_date}
      endDate={task.end_date}
      estimatedMinutes={task.estimated_minutes}
    />
  )
}

interface TaskStatsStripProps {
  /** `null` while no status is known (a create with no status picked yet). */
  completionPercentage: number | null
  startDate: string | null
  endDate: string | null
  estimatedMinutes: number | null
}

/** The strip itself, fed by the persisted task (detail) or the live form values (create, spec 0195 D-8). */
export function TaskStatsStrip({ completionPercentage, startDate, endDate, estimatedMinutes }: TaskStatsStripProps) {
  const { t } = useTranslation()
  const formattedStart = formatDate(startDate)
  const formattedEnd = formatDate(endDate)

  return (
    <RecordStatStrip className="border-t-0 bg-transparent">
      <RecordStat
        label={t('tasks.detail.completionPercentage')}
        value={
          completionPercentage !== null ? (
            <CompletionBar value={completionPercentage} label={t('tasks.detail.completionPercentage')} />
          ) : (
            <DetailEmpty />
          )
        }
      />
      <RecordStat label={t('tasks.detail.startDate')} value={formattedStart || <DetailEmpty />} />
      <RecordStat label={t('tasks.detail.endDate')} value={formattedEnd || <DetailEmpty />} />
      <RecordStat
        label={t('tasks.detail.estimatedMinutes')}
        value={estimatedMinutes !== null ? formatMinutesLabel(estimatedMinutes) : <DetailEmpty />}
      />
    </RecordStatStrip>
  )
}
