import { useTranslation } from 'react-i18next'
import {
  CalendarClock,
  ClipboardList,
  Contact,
  History,
  Link2,
  ListChecks,
  MessageSquareWarning,
  ShieldAlert,
  Users,
} from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Progress } from '@/components/ui/progress'
import { cn } from '@/lib/utils'
import { formatDate } from '@/lib/formatting/date-display'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCard,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { BADGE_BASE, BADGE_COLOR_CLASSES, formatDateTime } from '@/features/table/cell-renderers'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { TASKS_DOMAIN } from '@/features/tasks/api'
import { TaskLookupBadge } from '@/features/tasks/task-lookup-badge'
import { TaskPeopleList } from '@/features/tasks/task-people-list'
import { TaskSubtasksSection } from '@/features/tasks/task-subtasks-section'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'

interface TaskDetailViewProps {
  task: TaskDetailWithPermissions
  /** Opens a child task's own detail (AC-085). */
  onOpenSubtask: (subtaskId: number) => void
  /** Opens the create form with this task prefilled and locked as the parent (AC-085). */
  onCreateSubtask: () => void
}

/**
 * Read-only detail of a single task, on the same `RecordCanvas` kit as the
 * Commessa/Contratto records.
 *
 * AC-086: "Bloccato/contestato" is its OWN badge in the identity header,
 * visually and semantically separate from the status pill — a flag, not a
 * phase.
 *
 * AC-084: the percentage is rendered from `completion_percentage`, which the
 * backend DERIVES from the status at response time (D-6) — `tasks` has no
 * such column, so nothing here can drift from the configured status.
 */
export function TaskDetailView({ task, onOpenSubtask, onCreateSubtask }: TaskDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(task.created_at)
  const updatedAt = formatDateTime(task.updated_at)

  return (
    <RecordCanvas>
      <RecordCard>
        <RecordCardHeader
          media={<DetailMonogram name={task.title} icon={<ListChecks />} />}
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
                <Badge
                  variant="secondary"
                  className={cn(BADGE_BASE, 'gap-1.5', BADGE_COLOR_CLASSES.red)}
                >
                  <ShieldAlert className="size-3.5 shrink-0" aria-hidden="true" />
                  {t('tasks.detail.blocked')}
                </Badge>
              ) : null}
            </>
          }
        />

        <RecordSectionsGrid>
          <RecordSection title={t('tasks.detail.sections.identity')} icon={<ClipboardList />}>
            <RecordFieldList>
              <RecordField label={t('tasks.detail.status')}>
                <TaskLookupBadge value={task.task_status} />
              </RecordField>
              <RecordField label={t('tasks.detail.completionPercentage')}>
                <div className="flex items-center gap-2">
                  <Progress
                    value={task.completion_percentage}
                    size="xs"
                    className="w-24"
                    aria-label={t('tasks.detail.completionPercentage')}
                  />
                  <span className="text-xs tabular-nums">
                    {t('tasks.form.percentValue', { value: task.completion_percentage })}
                  </span>
                </div>
              </RecordField>
              <RecordField label={t('tasks.detail.isBlocked')}>
                {t(task.is_blocked ? 'common.yes' : 'common.no')}
              </RecordField>
              <RecordField label={t('tasks.detail.description')}>
                {task.description ? (
                  <span className="whitespace-pre-wrap">{task.description}</span>
                ) : (
                  <DetailEmpty />
                )}
              </RecordField>
            </RecordFieldList>
          </RecordSection>

          <RecordSection title={t('tasks.detail.sections.people')} icon={<Users />}>
            <RecordFieldList>
              <RecordField label={t('tasks.detail.creator')}>{task.creator.name}</RecordField>
              <RecordField label={t('tasks.detail.requester')}>
                {task.requester?.name ?? <DetailEmpty />}
              </RecordField>
              <RecordField label={t('tasks.detail.assignees')}>
                <TaskPeopleList people={task.assignees} />
              </RecordField>
              <RecordField label={t('tasks.detail.watchers')}>
                <TaskPeopleList people={task.watchers} />
              </RecordField>
            </RecordFieldList>
          </RecordSection>

          <RecordSection title={t('tasks.detail.sections.planning')} icon={<CalendarClock />}>
            <RecordFieldList>
              <RecordField label={t('tasks.detail.startDate')}>
                {formatDate(task.start_date) || <DetailEmpty />}
              </RecordField>
              <RecordField label={t('tasks.detail.endDate')}>
                {formatDate(task.end_date) || <DetailEmpty />}
              </RecordField>
              <RecordField label={t('tasks.detail.completionDate')}>
                {formatDate(task.completion_date) || <DetailEmpty />}
              </RecordField>
              <RecordField label={t('tasks.detail.startTime')}>
                {task.start_time ?? <DetailEmpty />}
              </RecordField>
              <RecordField label={t('tasks.detail.endTime')}>
                {task.end_time ?? <DetailEmpty />}
              </RecordField>
              <RecordField label={t('tasks.detail.estimatedMinutes')}>
                {task.estimated_minutes !== null ? (
                  t('tasks.detail.minutesValue', { value: task.estimated_minutes })
                ) : (
                  <DetailEmpty />
                )}
              </RecordField>
            </RecordFieldList>
          </RecordSection>

          <RecordSection title={t('tasks.detail.sections.links')} icon={<Link2 />}>
            <RecordFieldList>
              <RecordField label={t('tasks.detail.registry')} icon={<Contact />}>
                {task.registry?.name ?? <DetailEmpty />}
              </RecordField>
              <RecordField label={t('tasks.detail.referent')}>
                {task.referent?.name ?? <DetailEmpty />}
              </RecordField>
              <RecordField label={t('tasks.detail.opportunity')}>
                {task.opportunity?.name ?? <DetailEmpty />}
              </RecordField>
              <RecordField label={t('tasks.detail.workOrder')}>
                {task.work_order ? `${task.work_order.code} — ${task.work_order.title}` : <DetailEmpty />}
              </RecordField>
            </RecordFieldList>
          </RecordSection>

          {task.requires_closure_feedback ? (
            <RecordSection title={t('tasks.detail.sections.closure')} icon={<MessageSquareWarning />} full>
              <RecordFieldList>
                <RecordField label={t('tasks.detail.closureFeedback')}>
                  {task.closure_feedback ? (
                    <span className="whitespace-pre-wrap">{task.closure_feedback}</span>
                  ) : (
                    <DetailEmpty />
                  )}
                </RecordField>
              </RecordFieldList>
            </RecordSection>
          ) : null}

          <TaskSubtasksSection
            subtasks={task.subtasks}
            onOpen={onOpenSubtask}
            onCreate={onCreateSubtask}
          />
        </RecordSectionsGrid>

        {task.permissions.actions.view_activity ? (
          <div className="border-t p-4">
            <RecordSection title={t('activityLog.title')} icon={<History />}>
              <ActivityLogSection resource={TASKS_DOMAIN} id={task.id} />
            </RecordSection>
          </div>
        ) : null}
      </RecordCard>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('tasks.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('tasks.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
