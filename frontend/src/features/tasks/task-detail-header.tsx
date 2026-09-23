import { useTranslation } from 'react-i18next'
import { ListChecks, Repeat, ShieldAlert } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Progress } from '@/components/ui/progress'
import { cn } from '@/lib/utils'
import { formatDate } from '@/lib/formatting/date-display'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader, RecordStat, RecordStatStrip } from '@/components/detail/record-panel'
import { RecordEditButton } from '@/components/detail/record-edit-button'
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
  /** Opens the module's existing edit surface; absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Identity band: monogram, title, parent subtitle, the configured lookup
 * badges, and the "Modifica" action on the card itself (as on Opportunita').
 *
 * AC-086: "Bloccato/contestato" is its OWN badge, visually and semantically
 * separate from the status pill — a flag, not a phase.
 */
export function TaskDetailHeader({ task, onEdit }: TaskDetailHeaderProps) {
  const { t, i18n } = useTranslation()
  const canEdit = task.permissions.resource.update

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
      actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
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
  const { t } = useTranslation()
  const startDate = formatDate(task.start_date)
  const endDate = formatDate(task.end_date)

  return (
    <RecordStatStrip className="border-t-0 bg-transparent">
      <RecordStat
        label={t('tasks.detail.completionPercentage')}
        value={
          <span className="flex items-center gap-2">
            <Progress
              value={task.completion_percentage}
              size="xs"
              className="w-16 shrink-0"
              aria-label={t('tasks.detail.completionPercentage')}
            />
            <span className="tabular-nums">
              {t('tasks.form.percentValue', { value: task.completion_percentage })}
            </span>
          </span>
        }
      />
      <RecordStat label={t('tasks.detail.startDate')} value={startDate || <DetailEmpty />} />
      <RecordStat label={t('tasks.detail.endDate')} value={endDate || <DetailEmpty />} />
      <RecordStat
        label={t('tasks.detail.estimatedMinutes')}
        value={task.estimated_minutes !== null ? formatMinutesLabel(task.estimated_minutes) : <DetailEmpty />}
      />
    </RecordStatStrip>
  )
}
