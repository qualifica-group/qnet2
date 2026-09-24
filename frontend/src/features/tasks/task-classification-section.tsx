import { useTranslation } from 'react-i18next'
import { Tags } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { taskCategoryPathLabel, useTaskCategoryTree } from '@/features/task-categories/use-task-category-tree'
import {
  TASK_CATEGORIES_FOR_SELECT_RESOURCE,
  TASK_IMPORTANCES_FOR_SELECT_RESOURCE,
  TASK_PRIORITIES_FOR_SELECT_RESOURCE,
  TASK_STATUSES_FOR_SELECT_RESOURCE,
  TASK_TYPES_FOR_SELECT_RESOURCE,
} from '@/features/tasks/for-select-api'
import {
  isTaskStatusOptionDisabled,
  isTaskStatusOptionDisabledOnCreate,
} from '@/features/tasks/task-status-option-availability'
import { TaskCompletionReadout } from '@/features/tasks/task-completion-readout'
import { TaskLookupBadge } from '@/features/tasks/task-lookup-badge'
import { TaskLookupSelectField } from '@/features/tasks/task-lookup-select-field'
import type { ReactNode } from 'react'
import type { TaskCategoryTreeNode } from '@/features/task-categories/use-task-category-tree'
import type { ForSelectItem } from '@/features/for-select/types'
import type { TaskFormValues } from '@/features/tasks/task-schema'
import type { TaskDetail, TaskLookupRef } from '@/features/tasks/types'

/** Pixels of indent per tree depth level (spec 0154 D-1) — compact, per `ui-design.md` §2. */
const CATEGORY_INDENT_STEP_PX = 12

/** The badge attributes a category for-select item carries in `meta` (D-1 adds `parent_id`/`depth`). */
interface CategoryOptionMeta {
  color?: string
  icon?: string | null
  depth?: number
}

/**
 * Renders one category option (and the trigger's selected value, the SAME
 * function per `AsyncPaginatedSelect`) indented by its own `meta.depth`
 * (D-1: the for-select already returns the whole catalog in depth-first
 * order) and labelled with its full ancestor path ("Padre / Figlio") when
 * `nodes` — the best-effort full-catalog fetch, see `useTaskCategoryTree` —
 * already resolved it; falls back to the item's own name otherwise, never a
 * crash. Built once per render, not memoized: it is a plain render prop, not
 * a dependency of another hook (`react-hooks.md`).
 */
function renderCategoryOption(nodes: TaskCategoryTreeNode[]) {
  return function CategoryOption(item: ForSelectItem): ReactNode {
    const meta = (item as ForSelectItem & { meta?: CategoryOptionMeta }).meta
    const path = taskCategoryPathLabel(nodes, item.id)
    const badgeValue: TaskLookupRef = {
      id: item.id,
      name: path ?? item.label,
      color: meta?.color ?? '',
      icon: meta?.icon ?? null,
    }
    return (
      <span
        className="flex min-w-0 items-center"
        style={{ paddingLeft: (meta?.depth ?? 0) * CATEGORY_INDENT_STEP_PX }}
      >
        <TaskLookupBadge value={badgeValue} />
      </span>
    )
  }
}

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

/** Stable module-level default: a fresh `[]` per render would break the `renderCategoryOption` closure's identity. */
const EMPTY_CATEGORY_NODES: TaskCategoryTreeNode[] = []

/**
 * "Classificazione": Stato plus the four lookups (D-1/D-8: category, type,
 * priority and importance are now all four required, the latter three
 * precompiled from their catalog's default row by `useTaskForm`) and the
 * DERIVED completion percentage.
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
 * the form shows the same pill the grid and the detail show. The category
 * picker additionally renders its own tree (D-1): `renderCategoryOption`
 * indents by `meta.depth` and labels each row with its full ancestor path,
 * both selectable — a parent category is not excluded, only visually
 * distinguished from its children.
 *
 * Spec 0118 D-3/spec 0154 D-10: the Stato picker now renders on CREATE too —
 * a manually picked initial status is `sometimes` on POST (D-10), still
 * derived server-side (0118 D-4) when left unpicked. `isItemDisabled` swaps
 * between the create-time rule (`isTaskStatusOptionDisabledOnCreate`: only
 * `open`/`pending` are reachable, no `close_via_status` exception — a new
 * task carries no action mandate yet) and the existing PATCH rule.
 *
 * Spec 0123 D-4/D-5: on PATCH, the picker shows every option but disables the
 * ones a PATCH could never reach (`isTaskStatusOptionDisabled`, read off
 * `useResourcePermissions()` already scoped by `ResourcePermissionsProvider`
 * — no extra prop needed for `close_via_status`, the same context every
 * other `MetaField`/`RelationSelectField` in this form already reads from).
 *
 * Spec 0126 D-4/AC-013: on PATCH, the picker as a WHOLE is disabled when
 * `permissions.actions.change_status` is false (blocked task, or the task is
 * not in an `open`/`pending` phase) via `RelationSelectField.forceDisabled`
 * — the same escape hatch every other derived/linked field in this form
 * already uses, so it composes with the field-permission ceiling `MetaField`
 * applies instead of bypassing it. On create there is no persisted
 * permissions block yet, so `change_status` falls back to permissive (never
 * force-disabled).
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
  const categoryTree = useTaskCategoryTree()
  const categoryNodes = categoryTree.data ?? EMPTY_CATEGORY_NODES

  return (
    <FormSection
      icon={Tags}
      title={t('tasks.form.sections.classification.title')}
      description={t('tasks.form.sections.classification.description')}
    >
      <div className="grid min-w-0 items-start gap-4 rounded-lg border bg-muted/40 p-3 @xl:grid-cols-2">
        <TaskLookupSelectField
          control={control}
          name="task_status_id"
          label={t('tasks.form.status')}
          required={isEdit}
          resource={TASK_STATUSES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('tasks.form.statusSearch')}
          selected={task?.task_status}
          onItemChange={onStatusItemChange}
          isItemDisabled={(item) =>
            isEdit ? isTaskStatusOptionDisabled(item, closeViaStatus) : isTaskStatusOptionDisabledOnCreate(item)
          }
          forceDisabled={isEdit && !changeStatus}
        />
        <TaskCompletionReadout percentage={completionPercentage} />
      </div>

      <div className={LOOKUP_GRID_CLASS}>
        <TaskLookupSelectField
          control={control}
          name="task_type_id"
          label={t('tasks.form.type')}
          required
          resource={TASK_TYPES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('tasks.form.typeSearch')}
          selected={task?.task_type}
        />

        <TaskLookupSelectField
          control={control}
          name="task_priority_id"
          label={t('tasks.form.priority')}
          required
          resource={TASK_PRIORITIES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('tasks.form.prioritySearch')}
          selected={task?.task_priority}
        />

        <TaskLookupSelectField
          control={control}
          name="task_importance_id"
          label={t('tasks.form.importance')}
          required
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
          renderItem={renderCategoryOption(categoryNodes)}
        />
      </div>
    </FormSection>
  )
}
