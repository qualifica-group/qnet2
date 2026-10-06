import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { CalendarClock, ClipboardList, Tags, Users } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordField, RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import {
  TASK_CATEGORIES_FOR_SELECT_RESOURCE,
  TASK_IMPORTANCES_FOR_SELECT_RESOURCE,
  TASK_PRIORITIES_FOR_SELECT_RESOURCE,
  TASK_STATUSES_FOR_SELECT_RESOURCE,
  TASK_TYPES_FOR_SELECT_RESOURCE,
  TASKS_FOR_SELECT_RESOURCE,
} from '@/features/tasks/for-select-api'
import {
  TaskCategoryField,
  TaskImportanceField,
  TaskPriorityField,
  TaskStatusField,
  TaskTypeField,
} from '@/features/tasks/task-classification-section'
import { TaskCreateLinkSections } from '@/features/tasks/task-create-link-sections'
import {
  DateDisplay,
  ForSelectLabelDisplay,
  LookupBadgeDisplay,
  MinutesDisplay,
  PeopleDisplay,
  RichTextDisplay,
  TextDisplay,
  YesNoDisplay,
} from '@/features/tasks/task-create-displays'
import { peopleOf, requesterRefOf } from '@/features/tasks/task-form-hydration'
import { TaskFormSubtasksSection } from '@/features/tasks/task-form-subtasks-section'
import { TaskDescriptionField, TaskParentField, TaskTitleField } from '@/features/tasks/task-identity-section'
import { TaskInlineField } from '@/features/tasks/task-inline-field'
import { TaskPerson } from '@/features/tasks/task-people-list'
import {
  TaskAssigneesField,
  TaskIsPrivateField,
  TaskRequesterField,
  TaskSuppressNotificationsField,
  TaskWatchersField,
} from '@/features/tasks/task-people-section'
import { TaskDateField, TaskEstimatedMinutesField, TaskTimeField } from '@/features/tasks/task-planning-section'
import type { TaskDetail } from '@/features/tasks/types'
import type { TaskFormState } from '@/features/tasks/use-task-form'
import type { TaskInlineEdit } from '@/features/tasks/use-task-inline-edit'

interface TaskCreateSectionsProps {
  taskForm: TaskFormState
  /** The create draft's row state (`useTaskDraftEdit`): rows start closed, like the detail's. */
  draft: TaskInlineEdit
  /** The "clona" source (spec 0156 D-4), `null` on a bare create: feeds the pickers' label hydration only. */
  source: TaskDetail | null
  /** "Crea sotto-task": the parent arrives prefilled and must not be changed (AC-085). */
  parentLocked: boolean
}

/**
 * The create form as a replica of the task detail (spec 0195 D-8, user
 * directives 2026-10-06): the detail's sections, rows, labels and order, every
 * row CLOSED until clicked — empty or prefilled — and opening on the same
 * field component the detail edits in place. Nothing is saved per row: the
 * header's Salva validates and creates the whole draft. Rows the detail shows
 * read-only that cannot exist yet (Bloccato, Data completamento, Feedback di
 * chiusura) are left out; the creator is the connected actor.
 *
 * Hydration: a duplicate reads its source for the labels (never the parent
 * link, "il padre non si copia"). Part two (links, closure, recurrence) lives
 * in `task-create-link-sections.tsx` for the size budget, as on the detail.
 */
export function TaskCreateSections({ taskForm, draft, source, parentLocked }: TaskCreateSectionsProps) {
  const { t } = useTranslation()
  const {
    form,
    currentUserRef,
    handleParentChange,
    handleStatusItemChange,
  } = taskForm
  const { control } = form
  const values = useWatch({ control })
  const requester = requesterRefOf(source, currentUserRef)

  return (
    <RecordSectionsGrid>
      <RecordSection title={t('tasks.detail.sections.identity')} icon={<ClipboardList />}>
        <RecordFieldList>
          <TaskInlineField field="title" label={t('tasks.form.title')} inline={draft} editor={<TaskTitleField control={control} />}>
            <TextDisplay value={values.title ?? null} />
          </TaskInlineField>
          <TaskInlineField
            field="description"
            label={t('tasks.detail.description')}
            inline={draft}
            editor={<TaskDescriptionField control={control} />}
          >
            <RichTextDisplay html={values.description ?? null} />
          </TaskInlineField>
          <TaskInlineField
            field="parent_task_id"
            label={t('tasks.form.parentTask')}
            inline={draft}
            canEdit={!parentLocked}
            editor={
              <TaskParentField control={control} parentTask={null} parentLocked={parentLocked} onParentChange={handleParentChange} />
            }
          >
            <ForSelectLabelDisplay resource={TASKS_FOR_SELECT_RESOURCE} id={values.parent_task_id ?? null} />
          </TaskInlineField>
        </RecordFieldList>
      </RecordSection>

      <RecordSection title={t('tasks.form.sections.classification.title')} icon={<Tags />}>
        <RecordFieldList>
          <TaskInlineField
            field="task_status_id"
            label={t('tasks.form.status')}
            inline={draft}
            editor={<TaskStatusField control={control} task={null} onStatusItemChange={handleStatusItemChange} />}
          >
            <LookupBadgeDisplay resource={TASK_STATUSES_FOR_SELECT_RESOURCE} id={values.task_status_id ?? null} />
          </TaskInlineField>
          <TaskInlineField field="task_type_id" label={t('tasks.form.type')} inline={draft} editor={<TaskTypeField control={control} task={null} />}>
            <LookupBadgeDisplay resource={TASK_TYPES_FOR_SELECT_RESOURCE} id={values.task_type_id ?? null} />
          </TaskInlineField>
          <TaskInlineField
            field="task_priority_id"
            label={t('tasks.form.priority')}
            inline={draft}
            editor={<TaskPriorityField control={control} task={null} />}
          >
            <LookupBadgeDisplay resource={TASK_PRIORITIES_FOR_SELECT_RESOURCE} id={values.task_priority_id ?? null} />
          </TaskInlineField>
          <TaskInlineField
            field="task_importance_id"
            label={t('tasks.form.importance')}
            inline={draft}
            editor={<TaskImportanceField control={control} task={null} />}
          >
            <LookupBadgeDisplay resource={TASK_IMPORTANCES_FOR_SELECT_RESOURCE} id={values.task_importance_id ?? null} />
          </TaskInlineField>
          <TaskInlineField
            field="task_category_id"
            label={t('tasks.form.category')}
            inline={draft}
            editor={<TaskCategoryField control={control} task={null} />}
          >
            <LookupBadgeDisplay resource={TASK_CATEGORIES_FOR_SELECT_RESOURCE} id={values.task_category_id ?? null} />
          </TaskInlineField>
        </RecordFieldList>
      </RecordSection>

      <RecordSection title={t('tasks.detail.sections.people')} icon={<Users />}>
        <RecordFieldList>
          {/* Set server-side from the actor (D-10): whoever is creating it. */}
          <RecordField label={t('tasks.detail.creator')}>
            {currentUserRef ? <TaskPerson person={currentUserRef} /> : <DetailEmpty />}
          </RecordField>
          <TaskInlineField
            field="requester_id"
            label={t('tasks.detail.requester')}
            inline={draft}
            editor={<TaskRequesterField control={control} requester={requester} />}
          >
            <PeopleDisplay
              ids={values.requester_id != null ? [values.requester_id] : []}
              known={requester ? [requester] : undefined}
            />
          </TaskInlineField>
          <TaskInlineField
            field="assignee_ids"
            label={t('tasks.detail.assignees')}
            inline={draft}
            editor={
              <>
                <TaskAssigneesField control={control} assignees={peopleOf(source?.assignees)} />
                <TaskSuppressNotificationsField control={control} isCreate />
              </>
            }
          >
            <PeopleDisplay ids={(values.assignee_ids ?? []).filter(isId)} known={peopleOf(source?.assignees)} />
          </TaskInlineField>
          <TaskInlineField
            field="watcher_ids"
            label={t('tasks.detail.watchers')}
            inline={draft}
            editor={
              <TaskWatchersField control={control} watchers={peopleOf(source?.watchers)} creatorId={currentUserRef?.id ?? null} />
            }
          >
            <PeopleDisplay ids={(values.watcher_ids ?? []).filter(isId)} known={peopleOf(source?.watchers)} />
          </TaskInlineField>
          <TaskInlineField field="is_private" label={t('tasks.form.isPrivate')} inline={draft} editor={<TaskIsPrivateField control={control} />}>
            <YesNoDisplay value={values.is_private ?? false} />
          </TaskInlineField>
        </RecordFieldList>
      </RecordSection>

      <RecordSection title={t('tasks.detail.sections.planning')} icon={<CalendarClock />}>
        <RecordFieldList>
          <TaskInlineField
            field="start_date"
            label={t('tasks.detail.startDate')}
            inline={draft}
            editor={<TaskDateField control={control} name="start_date" />}
          >
            <DateDisplay value={values.start_date ?? null} />
          </TaskInlineField>
          <TaskInlineField
            field="start_time"
            label={t('tasks.detail.startTime')}
            inline={draft}
            editor={<TaskTimeField control={control} name="start_time" />}
          >
            <TextDisplay value={values.start_time ?? null} />
          </TaskInlineField>
          <TaskInlineField
            field="end_date"
            label={t('tasks.detail.endDate')}
            inline={draft}
            editor={<TaskDateField control={control} name="end_date" />}
          >
            <DateDisplay value={values.end_date ?? null} />
          </TaskInlineField>
          <TaskInlineField
            field="end_time"
            label={t('tasks.detail.endTime')}
            inline={draft}
            editor={<TaskTimeField control={control} name="end_time" />}
          >
            <TextDisplay value={values.end_time ?? null} />
          </TaskInlineField>
          <TaskInlineField
            field="estimated_minutes"
            label={t('tasks.detail.estimatedMinutes')}
            inline={draft}
            editor={<TaskEstimatedMinutesField control={control} />}
          >
            <MinutesDisplay value={values.estimated_minutes ?? null} />
          </TaskInlineField>
        </RecordFieldList>
      </RecordSection>

      <TaskCreateLinkSections taskForm={taskForm} draft={draft} source={source} />

      <TaskFormSubtasksSection control={control} />
    </RecordSectionsGrid>
  )
}

/** `useWatch` types a watched array's items as possibly undefined (DeepPartial). */
function isId(value: number | undefined): value is number {
  return value !== undefined
}
