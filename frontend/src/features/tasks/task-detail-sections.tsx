import { useTranslation } from 'react-i18next'
import { CalendarClock, ClipboardList, Tags, Users } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RichTextContent } from '@/components/rich-text/rich-text-content'
import { formatDate } from '@/lib/formatting/date-display'
import { useResourcePermissions } from '@/features/authorization/permissions'
import {
  TaskCategoryField,
  TaskImportanceField,
  TaskPriorityField,
  TaskStatusField,
  TaskTypeField,
} from '@/features/tasks/task-classification-section'
import { TaskDescriptionField, TaskParentField, TaskTitleField } from '@/features/tasks/task-identity-section'
import { useTaskCascadeEditable } from '@/features/tasks/task-inline-cascade'
import { TaskEndDate } from '@/features/tasks/task-end-date'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { TaskLookupBadge } from '@/features/tasks/task-lookup-badge'
import { TaskPeopleList, TaskPerson } from '@/features/tasks/task-people-list'
import {
  TaskAssigneesField,
  TaskIsPrivateField,
  TaskRequesterField,
  TaskSuppressNotificationsField,
  TaskWatchersField,
} from '@/features/tasks/task-people-section'
import { TaskDateField, TaskEstimatedMinutesField, TaskTimeField } from '@/features/tasks/task-planning-section'
import { parentRefOf, peopleOf } from '@/features/tasks/task-form-hydration'
import { formatMinutesLabel } from '@/features/time-entries/time-entry-format'
import type { TaskLookupRef, TaskDetailWithPermissions } from '@/features/tasks/types'
import type { TaskDetailEditor } from '@/features/tasks/use-task-inline-edit'

/**
 * The editable sections of the task detail (spec 0195), part one: the same
 * rows the read-only detail always showed, each one a `RecordInlineField` whose
 * editor is the create form's own field component. Part two (links, closure,
 * recurrence) lives in `task-detail-link-sections.tsx` for the size budget.
 */

export interface TaskDetailSectionProps {
  task: TaskDetailWithPermissions
  editor: TaskDetailEditor
}

function lookupDisplay(value: TaskLookupRef | null) {
  return value ? <TaskLookupBadge value={value} /> : <DetailEmpty />
}

export function TaskDetailIdentitySection({ task, editor }: TaskDetailSectionProps) {
  const { t } = useTranslation()
  const { form, inline, handleParentChange } = editor
  const cascadeEditable = useTaskCascadeEditable(task)

  return (
    <RecordSection title={t('tasks.detail.sections.identity')} icon={<ClipboardList />}>
      <RecordFieldList>
        <RecordInlineField
          field="title"
          label={t('tasks.form.title')}
          inline={inline}
          editor={<TaskTitleField control={form.control} />}
        >
          {task.title}
        </RecordInlineField>
        <RecordInlineField
          field="description"
          label={t('tasks.detail.description')}
          inline={inline}
          editor={<TaskDescriptionField control={form.control} />}
        >
          {task.description ? <RichTextContent html={task.description} /> : <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="parent_task_id"
          label={t('tasks.form.parentTask')}
          inline={inline}
          canEdit={cascadeEditable('parent_task_id')}
          editor={
            <TaskParentField
              control={form.control}
              parentTask={parentRefOf(task)}
              excludeTaskId={task.id}
              onParentChange={handleParentChange}
            />
          }
        >
          {task.parent_task ? (
            <RecordLink domain="tasks" id={task.parent_task.id}>
              {task.parent_task.title}
            </RecordLink>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
        {/* Written only by the Blocca/Sblocca domain actions (AC-086). */}
        <RecordField label={t('tasks.detail.isBlocked')}>{t(task.is_blocked ? 'common.yes' : 'common.no')}</RecordField>
      </RecordFieldList>
    </RecordSection>
  )
}

export function TaskDetailClassificationSection({ task, editor }: TaskDetailSectionProps) {
  const { t } = useTranslation()
  const { canAction } = useResourcePermissions()
  const { form, inline, handleStatusItemChange } = editor

  return (
    <RecordSection title={t('tasks.form.sections.classification.title')} icon={<Tags />}>
      <RecordFieldList>
        <RecordInlineField
          field="task_status_id"
          label={t('tasks.form.status')}
          inline={inline}
          // Spec 0126 D-4: a blocked task, or one out of the open/pending phases, keeps its status.
          canEdit={canAction('change_status')}
          editor={<TaskStatusField control={form.control} task={task} onStatusItemChange={handleStatusItemChange} />}
        >
          <TaskLookupBadge value={task.task_status} />
        </RecordInlineField>
        <RecordInlineField
          field="task_type_id"
          label={t('tasks.form.type')}
          inline={inline}
          editor={<TaskTypeField control={form.control} task={task} />}
        >
          {lookupDisplay(task.task_type)}
        </RecordInlineField>
        <RecordInlineField
          field="task_priority_id"
          label={t('tasks.form.priority')}
          inline={inline}
          editor={<TaskPriorityField control={form.control} task={task} />}
        >
          {lookupDisplay(task.task_priority)}
        </RecordInlineField>
        <RecordInlineField
          field="task_importance_id"
          label={t('tasks.form.importance')}
          inline={inline}
          editor={<TaskImportanceField control={form.control} task={task} />}
        >
          {lookupDisplay(task.task_importance)}
        </RecordInlineField>
        <RecordInlineField
          field="task_category_id"
          label={t('tasks.form.category')}
          inline={inline}
          editor={<TaskCategoryField control={form.control} task={task} />}
        >
          {lookupDisplay(task.task_category)}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

export function TaskDetailPeopleSection({ task, editor }: TaskDetailSectionProps) {
  const { t } = useTranslation()
  const { form, inline } = editor

  return (
    <RecordSection title={t('tasks.detail.sections.people')} icon={<Users />}>
      <RecordFieldList>
        {/* Set server-side from the actor and immutable (D-10). */}
        <RecordField label={t('tasks.detail.creator')}>
          <TaskPerson person={task.creator} />
        </RecordField>
        <RecordInlineField
          field="requester_id"
          label={t('tasks.detail.requester')}
          inline={inline}
          editor={<TaskRequesterField control={form.control} requester={task.requester} />}
        >
          {task.requester ? <TaskPerson person={task.requester} /> : <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="assignee_ids"
          label={t('tasks.detail.assignees')}
          inline={inline}
          editor={
            // D-5: the per-save notification instruction belongs with the assignees it is about.
            <>
              <TaskAssigneesField control={form.control} assignees={peopleOf(task.assignees)} />
              <TaskSuppressNotificationsField control={form.control} isCreate={false} />
            </>
          }
        >
          <TaskPeopleList people={task.assignees} />
        </RecordInlineField>
        <RecordInlineField
          field="watcher_ids"
          label={t('tasks.detail.watchers')}
          inline={inline}
          editor={
            <TaskWatchersField control={form.control} watchers={peopleOf(task.watchers)} creatorId={task.creator.id} />
          }
        >
          <TaskPeopleList people={task.watchers} />
        </RecordInlineField>
        <RecordInlineField
          field="is_private"
          label={t('tasks.form.isPrivate')}
          inline={inline}
          editor={<TaskIsPrivateField control={form.control} />}
        >
          {t(task.is_private ? 'common.yes' : 'common.no')}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

export function TaskDetailPlanningSection({ task, editor }: TaskDetailSectionProps) {
  const { t } = useTranslation()
  const { field } = useResourcePermissions()
  const { form, inline } = editor

  return (
    <RecordSection title={t('tasks.detail.sections.planning')} icon={<CalendarClock />}>
      <RecordFieldList>
        <RecordInlineField
          field="start_date"
          label={t('tasks.detail.startDate')}
          inline={inline}
          editor={<TaskDateField control={form.control} name="start_date" />}
        >
          {formatDate(task.start_date) || <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="start_time"
          label={t('tasks.detail.startTime')}
          inline={inline}
          editor={<TaskTimeField control={form.control} name="start_time" />}
        >
          {task.start_time ?? <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="end_date"
          label={t('tasks.detail.endDate')}
          inline={inline}
          editor={<TaskDateField control={form.control} name="end_date" />}
        >
          <TaskEndDate endDate={task.end_date} statusGroup={task.task_status.group} />
        </RecordInlineField>
        <RecordInlineField
          field="end_time"
          label={t('tasks.detail.endTime')}
          inline={inline}
          editor={<TaskTimeField control={form.control} name="end_time" />}
        >
          {task.end_time ?? <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="estimated_minutes"
          label={t('tasks.detail.estimatedMinutes')}
          inline={inline}
          editor={<TaskEstimatedMinutesField control={form.control} />}
        >
          {task.estimated_minutes !== null ? formatMinutesLabel(task.estimated_minutes) : <DetailEmpty />}
        </RecordInlineField>
        {/* Set by the completion flow (Completa), never by a form (D-6). */}
        {field('completion_date').visible ? (
          <RecordField label={t('tasks.detail.completionDate')}>
            {formatDate(task.completion_date) || <DetailEmpty />}
          </RecordField>
        ) : null}
      </RecordFieldList>
    </RecordSection>
  )
}
