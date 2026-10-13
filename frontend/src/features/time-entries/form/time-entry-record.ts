import { useWatch, type Control } from 'react-hook-form'
import type { UseFormReturn } from 'react-hook-form'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { ForSelectItem } from '@/features/for-select/types'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { OPPORTUNITIES_FOR_SELECT_RESOURCE } from '@/features/opportunities/for-select-api'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { TASKS_FOR_SELECT_RESOURCE, WORK_ORDERS_FOR_SELECT_RESOURCE } from '@/features/tasks/for-select-api'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'
import { workOrderRefOf } from '@/features/time-entries/form/time-entry-context-fields'
import type { TimeEntryFormValues } from '@/features/time-entries/form/time-entry-schema'
import { useTimeEntryTypeOptions } from '@/features/time-entries/form/time-entry-type-picker'
import { useTimeEntryWorkOrderStageOptions } from '@/features/time-entries/form/use-time-entry-work-order-stage-options'
import type { TimeEntry } from '@/features/time-entries/types'

/** Shown while a label resolves: never the bare id. */
const PENDING_LABEL = '…'

/** Stable empty id list: a fresh array per render would break the label hooks' memo. */
const NO_IDS: number[] = []

/**
 * What the closed rows display (spec 0226 D-2): the persisted record on the
 * detail, the draft's ids resolved to labels on create.
 */
export interface TimeEntryRecordValues {
  title: string
  date: string
  task_type: { name: string; color: string; icon: string | null } | null
  start_time: string | null
  end_time: string | null
  minutes: number | null
  notes: string | null
  registry: RelationFieldRef | null
  opportunity: RelationFieldRef | null
  work_order: RelationFieldRef | null
  task: RelationFieldRef | null
  work_order_stage: RelationFieldRef | null
}

/** The persisted entry as the detail's rows read it. */
export function timeEntryRecordValues(entry: TimeEntry): TimeEntryRecordValues {
  return {
    title: entry.title,
    date: entry.date,
    task_type: entry.task_type,
    start_time: entry.start_time,
    end_time: entry.end_time,
    minutes: entry.minutes,
    notes: entry.notes,
    registry: entry.registry,
    opportunity: entry.opportunity,
    work_order: workOrderRefOf(entry.work_order),
    task: entry.task ? { id: entry.task.id, name: entry.task.title } : null,
    work_order_stage: entry.work_order_stage,
  }
}

function idsOf(id: number | null | undefined): number[] {
  return id != null ? [id] : NO_IDS
}

function refOf(labels: Map<number, ForSelectItem>, id: number | null | undefined): RelationFieldRef | null {
  return id != null ? { id, name: labels.get(id)?.label ?? PENDING_LABEL } : null
}

/**
 * The create draft as the closed rows read it: the form holds ids only, so
 * every link is named through `useForSelectLabels` (the cache the pickers fill
 * for their own trigger), the type through the cached type options and the
 * fase through the commessa's stage list. With a task linked its title stands
 * in for the draft's own (spec 0122 D-5).
 */
export function useTimeEntryDraftValues(
  control: Control<TimeEntryFormValues>,
  linkedTask: TaskDetailWithPermissions | undefined,
): TimeEntryRecordValues {
  const draft = useWatch({ control })
  const { options: typeOptions } = useTimeEntryTypeOptions()
  const registries = useForSelectLabels({ resource: REGISTRIES_FOR_SELECT_RESOURCE, ids: idsOf(draft.registry_id) })
  const opportunities = useForSelectLabels({
    resource: OPPORTUNITIES_FOR_SELECT_RESOURCE,
    ids: idsOf(draft.opportunity_id),
  })
  const workOrders = useForSelectLabels({ resource: WORK_ORDERS_FOR_SELECT_RESOURCE, ids: idsOf(draft.work_order_id) })
  const tasks = useForSelectLabels({ resource: TASKS_FOR_SELECT_RESOURCE, ids: idsOf(draft.task_id) })
  const { options: stageOptions } = useTimeEntryWorkOrderStageOptions(draft.work_order_id ?? null, null)
  const type = typeOptions.find((option) => option.id === draft.task_type_id)
  const stage = stageOptions.find((option) => option.id === draft.work_order_stage_id)
  const isTaskLinked = draft.task_id != null

  return {
    title: isTaskLinked ? (linkedTask?.title ?? PENDING_LABEL) : (draft.title ?? ''),
    date: draft.date ?? '',
    task_type: type ? { name: type.label, color: type.meta.color, icon: type.meta.icon } : null,
    start_time: draft.start_time ?? null,
    end_time: draft.end_time ?? null,
    minutes: draft.minutes ?? null,
    notes: draft.notes ?? null,
    registry: refOf(registries, draft.registry_id),
    opportunity: refOf(opportunities, draft.opportunity_id),
    work_order: refOf(workOrders, draft.work_order_id),
    task: refOf(tasks, draft.task_id),
    // With a task the server decides the fase from it: the draft has none to show.
    work_order_stage: !isTaskLinked && stage ? stage : null,
  }
}

/** The cascade handlers and task lock the "Contesto segnatempo" editor needs (`useTimeEntryForm`'s own). */
export interface TimeEntryContextControls {
  isTaskLinked: boolean
  linkedTask: TaskDetailWithPermissions | undefined
  /** The persisted fase pinned in the select's options (detail only). */
  persistedStage: RelationFieldRef | null
  onRegistryChange: () => void
  onOpportunityItemChange: (item: ForSelectItem | null) => void
  onWorkOrderItemChange: (item: ForSelectItem | null) => void
  onStartTimeChange: (value: string) => void
  onEndTimeChange: (value: string) => void
}

/** Everything a section of rows reads: the displayed values, the form, the inline state and the cascades. */
export interface TimeEntryRecordSectionProps {
  values: TimeEntryRecordValues
  form: UseFormReturn<TimeEntryFormValues>
  inline: InlineEdit
  controls: TimeEntryContextControls
  /** `false` without the entry's `permissions.update` (D-4): no row offers a pencil. */
  canEdit: boolean
}
