import { useTranslation } from 'react-i18next'
import { Form } from '@/components/ui/form'
import { MAIN_COLUMN_CLASS, PANEL_GRID_CLASS, SIDE_COLUMN_CLASS } from '@/components/record-form/layout'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { useTaskForm } from '@/features/tasks/use-task-form'
import { TaskAttachmentStaging } from '@/features/tasks/task-attachment-staging'
import { TaskClassificationSection } from '@/features/tasks/task-classification-section'
import { TaskClosureSection } from '@/features/tasks/task-closure-section'
import { TaskFormHeader } from '@/features/tasks/task-form-header'
import { TaskFormSummary } from '@/features/tasks/task-form-summary'
import { TaskIdentitySection } from '@/features/tasks/task-identity-section'
import { TaskLinksSection } from '@/features/tasks/task-links-section'
import { TaskPeopleSection } from '@/features/tasks/task-people-section'
import { TaskPlanningSection } from '@/features/tasks/task-planning-section'
import { TaskRecurrenceSection } from '@/features/tasks/task-recurrence-section'
import { TaskRegistrySection } from '@/features/tasks/task-registry-section'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { TaskDetail, TaskFormMode, TaskNamedRef, TaskWorkOrderStageRef } from '@/features/tasks/types'

/** DOM id bridging the sticky header's and the footer's save actions to the RHF `<form>`. */
const TASK_FORM_ID = 'task-form'

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

/** The persisted fase (spec 0146 D-3), possibly closed — `useTaskWorkOrderStageOptions` keeps it selectable regardless. */
function workOrderStageOf(task: TaskDetail | null): TaskWorkOrderStageRef | null {
  return task?.work_order_stage ?? null
}

/** `{id, label}` (spec 0154 D-4) projected onto the `{id, name}` shape every relation picker hydrates from. */
function leadRefOf(task: TaskDetail | null): RelationFieldRef | null {
  return task?.lead ? { id: task.lead.id, name: task.lead.label } : null
}

function peopleOf(refs: TaskNamedRef[] | undefined): RelationFieldRef[] {
  return refs && refs.length > 0 ? refs : EMPTY_PEOPLE
}

/**
 * The Richiedente picker's hydration: the persisted requester in edit mode
 * (possibly `null` on a historical row, AC-008 — no retroactive sanatoria),
 * or the connected actor's own ref on create (D-1 prefill, `useTaskForm`).
 */
function requesterRefOf(
  task: TaskDetail | null,
  currentUserRef: RelationFieldRef | null,
): RelationFieldRef | null {
  return task ? (task.requester ?? null) : currentUserRef
}

/**
 * The creator id the watchers picker excludes (spec 0118 D-9/AC-035): the
 * persisted `task.creator.id` in edit mode, the connected actor's id on
 * create (`useTaskForm.currentUserRef`) — never a form value (D-10).
 */
function creatorIdOf(task: TaskDetail | null, currentUserRef: RelationFieldRef | null): number | null {
  return task ? task.creator.id : (currentUserRef?.id ?? null)
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
 * Same record-form skeleton as the Opportunita' form (`@/components/record-form`):
 * sticky identity bar with the actions, then two columns at `@4xl` — the side
 * column (live summary + closure flags) FIRST in the DOM so a narrow container
 * reads it before the long form, reordered to the right. The main column opens
 * with the title card, then the badge-rendered classification, people,
 * planning, links, recurrence and (create only) attachments.
 *
 * Pure composition: every non-render concern lives in `useTaskForm`, and each
 * group of fields lives in its own section file so no file here approaches
 * the engineering size limits.
 */
export function TaskFormBody({ mode, onSuccess, onCancel }: TaskFormBodyProps) {
  const { t } = useTranslation()
  const {
    form,
    isEdit,
    serverError,
    onSubmit,
    handleRegistryChange,
    handleWorkOrderChange,
    handleWorkOrderItemChange,
    handleOpportunityChange,
    handleParentChange,
    handleStatusItemChange,
    completionPercentage,
    currentUserRef,
    stagedAttachments,
    addStagedAttachments,
    removeStagedAttachment,
    parentPrefillRefs,
    workOrderPrefillRef,
  } = useTaskForm({ mode, onSuccess })

  const task = persistedTask(mode)
  // AC-085: opened as "crea sotto-task", the parent arrives prefilled and locked.
  const parentLocked = mode.type === 'create' && (mode.parentTaskId ?? null) !== null

  const { isSubmitting } = form.formState

  return (
    <div className="@container flex flex-1 flex-col overflow-y-auto bg-surface">
      {/* The provider wraps BOTH columns (it renders no DOM of its own): the
          side column's closure flags are form fields like any other. */}
      <Form {...form}>
        <TaskFormHeader
          control={form.control}
          task={task}
          formId={TASK_FORM_ID}
          isSubmitting={isSubmitting}
          submitError={serverError}
          onCancel={onCancel}
        />

        <div className={PANEL_GRID_CLASS}>
          <aside className={SIDE_COLUMN_CLASS}>
            <TaskFormSummary control={form.control} task={task} />
            <TaskClosureSection control={form.control} isCreate={!isEdit} />
          </aside>

          <div className={MAIN_COLUMN_CLASS}>
            {/* `display: contents`: this native `<form>` only scopes the HTML
                submit boundary, it must not become an extra flex box. */}
            <form id={TASK_FORM_ID} onSubmit={form.handleSubmit(onSubmit)} className="contents" noValidate>
              <TaskIdentitySection
                control={form.control}
                parentTask={parentRefOf(task)}
                excludeTaskId={task?.id}
                parentLocked={parentLocked}
                onParentChange={handleParentChange}
              />

              <TaskClassificationSection
                control={form.control}
                task={task}
                onStatusItemChange={handleStatusItemChange}
                completionPercentage={completionPercentage}
              />

              <TaskPeopleSection
                control={form.control}
                requester={requesterRefOf(task, currentUserRef)}
                assignees={peopleOf(task?.assignees)}
                watchers={peopleOf(task?.watchers)}
                creatorId={creatorIdOf(task, currentUserRef)}
                isCreate={!isEdit}
              />

              <TaskPlanningSection control={form.control} />

              <TaskRegistrySection
                control={form.control}
                registry={task?.registry ?? parentPrefillRefs.registry}
                referent={task?.referent ?? parentPrefillRefs.referent}
                onRegistryChange={handleRegistryChange}
              />

              <TaskLinksSection
                control={form.control}
                opportunity={task?.opportunity ?? parentPrefillRefs.opportunity}
                workOrder={workOrderRefOf(task) ?? workOrderPrefillRef ?? parentPrefillRefs.workOrder}
                lead={leadRefOf(task)}
                workOrderStage={workOrderStageOf(task)}
                onWorkOrderChange={handleWorkOrderChange}
                onWorkOrderItemChange={handleWorkOrderItemChange}
                onOpportunityChange={handleOpportunityChange}
              />

              <TaskRecurrenceSection control={form.control} />

              {mode.type === 'create' ? (
                <TaskAttachmentStaging
                  files={stagedAttachments}
                  onAdd={addStagedAttachments}
                  onRemove={removeStagedAttachment}
                />
              ) : null}

              {/* The same actions the identity bar carries, repeated where the
                  form ends: the operator finishes typing far from the sticky bar. */}
              <RecordFormActions
                formId={TASK_FORM_ID}
                isSubmitting={isSubmitting}
                submitLabel={t('tasks.form.save')}
                submittingLabel={t('tasks.form.saving')}
                cancel={{ label: t('tasks.form.cancel'), onCancel }}
              />
            </form>
          </div>
        </div>
      </Form>
    </div>
  )
}
