import { useTranslation } from 'react-i18next'
import { ListChecks, Repeat, ShieldAlert } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { CompletionBar } from '@/components/completion-bar'
import { cn } from '@/lib/utils'
import type { TaskStatusGroupValue } from '@/features/status-reorder/types'
import { formatDate } from '@/lib/formatting/date-display'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader, RecordStat, RecordStatStrip } from '@/components/detail/record-panel'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { BADGE_BASE, BADGE_COLOR_CLASSES } from '@/features/table/cell-renderers'
import { TaskEndDate } from '@/features/tasks/task-end-date'
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
  // A field the actor's role hides stays out of the band too, not only out of its section row.
  const { field } = useResourcePermissions()
  const isVisible = (key: string) => field(key).visible

  return (
    <RecordCardHeader
      media={
        <DetailMonogram name={task.title} icon={<ListChecks />} className="size-10 text-base [&>svg]:size-5" />
      }
      title={task.title}
      subtitle={
        task.parent_task && isVisible('parent_task_id')
          ? t('tasks.detail.childOf', { title: task.parent_task.title })
          : undefined
      }
      badges={
        <>
          {isVisible('task_status_id') ? <TaskLookupBadge value={task.task_status} /> : null}
          {isVisible('task_type_id') ? <TaskLookupBadge value={task.task_type} /> : null}
          {isVisible('task_priority_id') ? <TaskLookupBadge value={task.task_priority} /> : null}
          {isVisible('task_importance_id') ? <TaskLookupBadge value={task.task_importance} /> : null}
          {isVisible('task_category_id') ? <TaskLookupBadge value={task.task_category} /> : null}
          {task.is_blocked ? (
            <Badge variant="secondary" className={cn(BADGE_BASE, 'gap-1.5', BADGE_COLOR_CLASSES.red)}>
              <ShieldAlert className="size-3.5 shrink-0" aria-hidden="true" />
              {t('tasks.detail.blocked')}
            </Badge>
          ) : null}
          {task.recurrence && isVisible('recurrence') ? (
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
      statusGroup={task.task_status.group}
      estimatedMinutes={task.estimated_minutes}
    />
  )
}

interface TaskStatsStripProps {
  /** `null` while no status is known (a create with no status picked yet). */
  completionPercentage: number | null
  startDate: string | null
  endDate: string | null
  /** The persisted status phase (detail): a closed task's end date is never late. */
  statusGroup?: TaskStatusGroupValue | null
  estimatedMinutes: number | null
}

/** The strip itself, fed by the persisted task (detail) or the live form values (create, spec 0195 D-8). */
export function TaskStatsStrip({
  completionPercentage,
  startDate,
  endDate,
  statusGroup = null,
  estimatedMinutes,
}: TaskStatsStripProps) {
  const { t } = useTranslation()
  const { field } = useResourcePermissions()
  const formattedStart = formatDate(startDate)

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
      {field('start_date').visible ? (
        <RecordStat label={t('tasks.detail.startDate')} value={formattedStart || <DetailEmpty />} />
      ) : null}
      {field('end_date').visible ? (
        <RecordStat label={t('tasks.detail.endDate')} value={<TaskEndDate endDate={endDate} statusGroup={statusGroup} />} />
      ) : null}
      {field('estimated_minutes').visible ? (
        <RecordStat
          label={t('tasks.detail.estimatedMinutes')}
          value={estimatedMinutes !== null ? formatMinutesLabel(estimatedMinutes) : <DetailEmpty />}
        />
      ) : null}
    </RecordStatStrip>
  )
}
