import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { useTaskForm } from '@/features/tasks/use-task-form'
import { TaskClassificationSection } from '@/features/tasks/task-classification-section'
import { TaskClosureSection } from '@/features/tasks/task-closure-section'
import { TaskIdentitySection } from '@/features/tasks/task-identity-section'
import { TaskLinksSection } from '@/features/tasks/task-links-section'
import { TaskPeopleSection } from '@/features/tasks/task-people-section'
import { TaskPlanningSection } from '@/features/tasks/task-planning-section'
import { TaskRegistrySection } from '@/features/tasks/task-registry-section'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { TaskDetail, TaskFormMode, TaskNamedRef } from '@/features/tasks/types'

/** Stable module-level references: a fresh `[]`/`null` per render would break memo/dep stability. */
const EMPTY_PEOPLE: RelationFieldRef[] = []

/** Separator between a commessa's code and title, matching `WorkOrderForSelectResource::LABEL_SEPARATOR`. */
const WORK_ORDER_LABEL_SEPARATOR = ' — '

/** The persisted task in edit mode, `null` on create — the single source both hydration and locking read. */
function persistedTask(mode: TaskFormMode): TaskDetail | null {
  return mode.type === 'edit' ? mode.task : null
}

/** `{id, title}` projected onto the `{id, name}` shape every relation picker hydrates from. */
function parentRefOf(task: TaskDetail | null): RelationFieldRef | null {
  return task?.parent_task ? { id: task.parent_task.id, name: task.parent_task.title } : null
}

/**
 * `{id, code, title}` projected onto the `{id, name}` shape the picker
 * hydrates from, composed EXACTLY like `WorkOrderForSelectResource` composes
 * its own `label` — empty-title fallback to the bare code included — so the
 * trigger label and the list options can never read differently.
 *
 * This hydration comes from the TASK's own detail, not from the for-select,
 * which matters: `GET /api/work-orders/for-select` is narrowed by
 * `WorkOrderVisibilityScope`, and `ids[]` deliberately does not bypass it (a
 * blanket bypass would turn the endpoint into an enumeration oracle). A user
 * without visibility on the linked commessa still sees its correct label
 * here, because D-9 does not obscure linked-record labels.
 *
 * The same ref is ALSO passed as the picker's `pinned` option, so the
 * persisted value stays selectable after the user changes the pick — the
 * option list alone would have dropped it. Nothing new is disclosed: it is
 * the value this form already loaded.
 */
function workOrderRefOf(task: TaskDetail | null): RelationFieldRef | null {
  const workOrder = task?.work_order
  if (!workOrder) {
    return null
  }
  const title = workOrder.title.trim()
  const name = title === '' ? workOrder.code : `${workOrder.code}${WORK_ORDER_LABEL_SEPARATOR}${title}`
  return { id: workOrder.id, name }
}

function peopleOf(refs: TaskNamedRef[] | undefined): RelationFieldRef[] {
  return refs && refs.length > 0 ? refs : EMPTY_PEOPLE
}

interface TaskFormBodyProps {
  mode: TaskFormMode
  onSuccess: (task: TaskDetail) => void
  onCancel: () => void
}

/**
 * The task create/edit form UI. Every field is wrapped in `MetaField` (spec
 * 0004): hidden means absent, non-editable means disabled, `required` comes
 * from the resolved `ResourcePermissions`. The two fields the backend
 * prohibits — `creator_id` and `completion_percentage` (D-6/D-10) — have no
 * control at all: the former is shown only in the detail, the latter is a
 * read-only readout inside the classification section.
 *
 * Pure composition: every non-render concern lives in `useTaskForm`, and each
 * group of fields lives in its own section file so no file here approaches
 * the engineering size limits.
 */
export function TaskFormBody({ mode, onSuccess, onCancel }: TaskFormBodyProps) {
  const { t } = useTranslation()
  const {
    form,
    serverError,
    onSubmit,
    handleRegistryChange,
    handleStatusItemChange,
    completionPercentage,
    statusSystemKey,
  } = useTaskForm({ mode, onSuccess })

  const task = persistedTask(mode)
  // AC-085: opened as "crea sotto-task", the parent arrives prefilled and locked.
  const parentLocked = mode.type === 'create' && (mode.parentTaskId ?? null) !== null

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4 p-4" noValidate>
          <TaskIdentitySection
            control={form.control}
            parentTask={parentRefOf(task)}
            excludeTaskId={task?.id}
            parentLocked={parentLocked}
          />

          <TaskClassificationSection
            control={form.control}
            task={task}
            onStatusItemChange={handleStatusItemChange}
            completionPercentage={completionPercentage}
          />

          <TaskRegistrySection
            control={form.control}
            registry={task?.registry ?? null}
            referent={task?.referent ?? null}
            onRegistryChange={handleRegistryChange}
          />

          <TaskPeopleSection
            control={form.control}
            requester={task?.requester ?? null}
            assignees={peopleOf(task?.assignees)}
            watchers={peopleOf(task?.watchers)}
          />

          <TaskPlanningSection control={form.control} />

          <TaskLinksSection
            control={form.control}
            opportunity={task?.opportunity ?? null}
            workOrder={workOrderRefOf(task)}
          />

          <TaskClosureSection control={form.control} statusSystemKey={statusSystemKey} />

          {serverError ? (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          ) : null}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              className="bg-card"
              onClick={onCancel}
              disabled={form.formState.isSubmitting}
            >
              {t('tasks.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('tasks.form.saving') : t('tasks.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
