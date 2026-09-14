import { useTranslation } from 'react-i18next'
import { Tags } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { useResourcePermissions } from '@/features/authorization/permissions'
import {
  TASK_CATEGORIES_FOR_SELECT_RESOURCE,
  TASK_IMPORTANCES_FOR_SELECT_RESOURCE,
  TASK_PRIORITIES_FOR_SELECT_RESOURCE,
  TASK_STATUSES_FOR_SELECT_RESOURCE,
  TASK_TYPES_FOR_SELECT_RESOURCE,
} from '@/features/tasks/for-select-api'
import { isTaskStatusOptionDisabled } from '@/features/tasks/task-status-option-availability'
import { TaskCompletionReadout } from '@/features/tasks/task-completion-readout'
import { TaskLookupSelectField } from '@/features/tasks/task-lookup-select-field'
import type { ForSelectItem } from '@/features/for-select/types'
import type { TaskFormValues } from '@/features/tasks/task-schema'
import type { TaskDetail } from '@/features/tasks/types'

interface TaskClassificationSectionProps {
  control: Control<TaskFormValues>
  /** The persisted task in edit mode, used to hydrate the five pickers; `null` on create. */
  task: TaskDetail | null
  /** Re-derives the read-only percentage (AC-084). */
  onStatusItemChange: (item: ForSelectItem | null) => void
  /** Derived from the picked status, never a form value; `null` while unpicked. */
  completionPercentage: number | null
}

/** Lookup grid: one column on a narrow panel, two, then three as the main column widens. */
const LOOKUP_GRID_CLASS = 'grid min-w-0 items-start gap-4 @xl:grid-cols-2 @3xl:grid-cols-3'

/**
 * "Classificazione": the required Stato plus the four optional lookups and
 * the DERIVED completion percentage.
 *
 * AC-084: the percentage is a read-only readout, NOT a form field — it has no
 * RHF binding and no key in `TaskFormValues`, so it is structurally
 * impossible to submit (D-6: `tasks` has no such column).
 *
 * AC-086/AC-045 (spec 0116 D-6): "Bloccato/contestato" is NOT a form field
 * any more — it left `TaskFormValues` entirely, writable only by the
 * `/block`/`/unblock` domain actions. This section no longer renders it; the
 * read model still shows it as a badge in `task-detail.tsx`.
 *
 * Every lookup renders as a colored badge picker (`TaskLookupSelectField`), so
 * the form shows the same pill the grid and the detail show.
 *
 * Spec 0118 D-3: the Stato picker does not render on create — the server
 * derives the initial status from the assignees (D-4), and `task_status_id`
 * is `prohibited` on POST, so offering the control would only invite a 422.
 * `task === null` is already the create signal every other section in this
 * form reads, so no new prop was added for it.
 *
 * Spec 0123 D-4/D-5: the picker shows every option but disables the ones a
 * PATCH could never reach (`isTaskStatusOptionDisabled`, read off
 * `useResourcePermissions()` already scoped by `ResourcePermissionsProvider`
 * — no extra prop needed for `close_via_status`, the same context every
 * other `MetaField`/`RelationSelectField` in this form already reads from).
 *
 * Spec 0126 D-4/AC-013: the picker as a WHOLE is disabled when
 * `permissions.actions.change_status` is false (blocked task, or the task is
 * not in an `open`/`pending` phase) via `RelationSelectField.forceDisabled`
 * — the same escape hatch every other derived/linked field in this form
 * already uses, so it composes with the field-permission ceiling `MetaField`
 * applies instead of bypassing it.
 */
export function TaskClassificationSection({
  control,
  task,
  onStatusItemChange,
  completionPercentage,
}: TaskClassificationSectionProps) {
  const { t } = useTranslation()
  const { canAction } = useResourcePermissions()
  const closeViaStatus = canAction('close_via_status')
  const changeStatus = canAction('change_status')
  const isEdit = task !== null

  return (
    <FormSection
      icon={Tags}
      title={t('tasks.form.sections.classification.title')}
      description={t('tasks.form.sections.classification.description')}
    >
      {isEdit ? (
        <div className="grid min-w-0 items-start gap-4 rounded-lg border bg-muted/40 p-3 @xl:grid-cols-2">
          <TaskLookupSelectField
            control={control}
            name="task_status_id"
            label={t('tasks.form.status')}
            required
            resource={TASK_STATUSES_FOR_SELECT_RESOURCE}
            searchPlaceholder={t('tasks.form.statusSearch')}
            selected={task.task_status}
            onItemChange={onStatusItemChange}
            isItemDisabled={(item) => isTaskStatusOptionDisabled(item, closeViaStatus)}
            forceDisabled={!changeStatus}
          />
          <TaskCompletionReadout percentage={completionPercentage} />
        </div>
      ) : null}

      <div className={LOOKUP_GRID_CLASS}>
        <TaskLookupSelectField
          control={control}
          name="task_type_id"
          label={t('tasks.form.type')}
          resource={TASK_TYPES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('tasks.form.typeSearch')}
          selected={task?.task_type}
        />

        <TaskLookupSelectField
          control={control}
          name="task_priority_id"
          label={t('tasks.form.priority')}
          resource={TASK_PRIORITIES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('tasks.form.prioritySearch')}
          selected={task?.task_priority}
        />

        <TaskLookupSelectField
          control={control}
          name="task_importance_id"
          label={t('tasks.form.importance')}
          resource={TASK_IMPORTANCES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('tasks.form.importanceSearch')}
          selected={task?.task_importance}
        />

        <TaskLookupSelectField
          control={control}
          name="task_category_id"
          label={t('tasks.form.category')}
          resource={TASK_CATEGORIES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('tasks.form.categorySearch')}
          selected={task?.task_category}
        />
      </div>
    </FormSection>
  )
}
