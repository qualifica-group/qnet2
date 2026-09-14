import { useTranslation } from 'react-i18next'
import { Info } from 'lucide-react'
import { useWatch, type Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { EMPTY_VALUE, SUMMARY_LIST_CLASS, SummaryRow } from '@/components/record-form/record-summary'
import { formatDate } from '@/lib/formatting/date-display'
import { TaskLookupBadge } from '@/features/tasks/task-lookup-badge'
import { formatMinutesLabel } from '@/features/time-entries/time-entry-format'
import type { TaskFormValues } from '@/features/tasks/task-schema'
import type { TaskDetail, TaskLookupRef } from '@/features/tasks/types'

interface TaskFormSummaryProps {
  control: Control<TaskFormValues>
  /** The persisted task in edit mode, `null` on create: the only source of a badge for the ids the form holds. */
  task: TaskDetail | null
}

/** The persisted lookup, only while the form still holds that same id (a changed pick names itself in its picker). */
function persistedLookup(ref: TaskLookupRef | null | undefined, id: number | null): TaskLookupRef | null {
  return ref && ref.id === id ? ref : null
}

/**
 * The form's side-column recap, in the same `SummaryRow` card the
 * Opportunita' form uses. Every row is read LIVE from the form, so it recaps
 * what is about to be saved and never contradicts the fields while they are
 * being edited.
 */
export function TaskFormSummary({ control, task }: TaskFormSummaryProps) {
  const { t } = useTranslation()
  const [statusId, priorityId, endDate, estimatedMinutes, assigneeIds, recurrence] = useWatch({
    control,
    name: ['task_status_id', 'task_priority_id', 'end_date', 'estimated_minutes', 'assignee_ids', 'recurrence'],
  })

  const status = persistedLookup(task?.task_status, statusId)
  const priority = persistedLookup(task?.task_priority, priorityId)

  return (
    <FormSection
      icon={Info}
      title={t('tasks.form.summary.title')}
      description={t('tasks.form.summary.description')}
      className="min-w-0"
    >
      <dl className={SUMMARY_LIST_CLASS}>
        {task ? (
          <SummaryRow label={t('tasks.form.status')}>
            {status ? <TaskLookupBadge value={status} /> : EMPTY_VALUE}
          </SummaryRow>
        ) : null}
        <SummaryRow label={t('tasks.form.priority')}>
          {priority ? <TaskLookupBadge value={priority} /> : EMPTY_VALUE}
        </SummaryRow>
        <SummaryRow label={t('tasks.form.assignees')}>
          {assigneeIds.length > 0
            ? t('tasks.form.summary.assigneesCount', { count: assigneeIds.length })
            : EMPTY_VALUE}
        </SummaryRow>
        <SummaryRow label={t('tasks.form.endDate')}>{formatDate(endDate) || EMPTY_VALUE}</SummaryRow>
        <SummaryRow label={t('tasks.form.estimatedMinutes')}>
          {estimatedMinutes !== null ? formatMinutesLabel(estimatedMinutes) : EMPTY_VALUE}
        </SummaryRow>
        <SummaryRow label={t('tasks.form.sections.recurrence.title')}>
          {recurrence.enabled && recurrence.frequency !== null
            ? t(`tasks.form.recurrence.frequencyOption.${recurrence.frequency}`)
            : t('tasks.form.summary.recurrenceOff')}
        </SummaryRow>
      </dl>
    </FormSection>
  )
}
