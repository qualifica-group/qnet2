import type { ReactNode } from 'react'
import type { Control } from 'react-hook-form'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { TaskLookupBadge } from '@/features/tasks/task-lookup-badge'
import { useTaskSelectLabels } from '@/features/tasks/task-select-labels'
import type { ForSelectItem } from '@/features/for-select/types'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { TaskFormValues } from '@/features/tasks/task-schema'
import type { TaskLookupRef } from '@/features/tasks/types'

/** The five configurable lookups of a task, each backed by a colored/iconed configuration row. */
export type TaskLookupFieldName =
  | 'task_status_id'
  | 'task_type_id'
  | 'task_category_id'
  | 'task_priority_id'
  | 'task_importance_id'

/** The badge attributes every lookup for-select projects in `meta` (`Task*ForSelectResource`). */
interface LookupBadgeMeta {
  color?: string
  icon?: string | null
}

/** Rebuilds the badge input from a for-select option: label from the item, color/icon from its `meta`. */
function lookupRefOfItem(item: ForSelectItem): TaskLookupRef {
  const meta = (item as ForSelectItem & { meta?: LookupBadgeMeta }).meta
  return { id: item.id, name: item.label, color: meta?.color ?? '', icon: meta?.icon ?? null }
}

function renderLookupBadge(item: ForSelectItem) {
  return <TaskLookupBadge value={lookupRefOfItem(item)} />
}

/** Carries the persisted row's color/icon onto the hydrated option, so the trigger is a badge before any fetch. */
function hydrationRefOf(value: TaskLookupRef | null | undefined): RelationFieldRef | null {
  return value ? { id: value.id, name: value.name, meta: { color: value.color, icon: value.icon } } : null
}

interface TaskLookupSelectFieldProps {
  control: Control<TaskFormValues>
  name: TaskLookupFieldName
  label: string
  resource: string
  searchPlaceholder: string
  /** The persisted lookup in edit mode; `null` on create. */
  selected: TaskLookupRef | null | undefined
  required?: boolean
  forceDisabled?: boolean
  onItemChange?: (item: ForSelectItem | null) => void
  isItemDisabled?: (item: ForSelectItem) => boolean
  /**
   * Overrides the default plain badge (`renderLookupBadge`) — the category
   * field (spec 0154 D-1) passes its own tree-indented renderer here instead
   * of duplicating the whole field.
   */
  renderItem?: (item: ForSelectItem) => ReactNode
}

/**
 * A task lookup picker whose trigger and options render the SAME colored
 * badge the grid and the detail show (`TaskLookupBadge`), instead of a bare
 * text label. Pure presentation over `RelationSelectField`: authorization,
 * search, pagination and hydration stay those of the generic picker.
 */
export function TaskLookupSelectField({
  selected,
  name,
  renderItem = renderLookupBadge,
  ...props
}: TaskLookupSelectFieldProps) {
  const selectLabels = useTaskSelectLabels()

  return (
    <RelationSelectField
      name={name}
      metaKey={name}
      selected={hydrationRefOf(selected)}
      renderItem={renderItem}
      {...selectLabels}
      {...props}
    />
  )
}
