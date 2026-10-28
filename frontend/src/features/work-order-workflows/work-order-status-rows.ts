import type { TFunction } from 'i18next'
import type {
  WorkOrderStatusFormRow,
  WorkOrderWorkflowDetail,
  WorkOrderWorkflowStatusItem,
} from '@/features/work-order-workflows/types'

/**
 * The three MANDATORY pinned system rows before a workflow exists: editable
 * from the start, pre-filled with the default labels, in pinned order (open
 * first, then the completed -> closed tail). They go in the create payload so
 * the backend seeds the auto-created rows with the name the user typed.
 */
export function initialSystemStatusRows(t: TFunction): WorkOrderStatusFormRow[] {
  const pinned = (id: string, key: 'open' | 'completed' | 'closed', name: string): WorkOrderStatusFormRow => ({
    id,
    name,
    description: null,
    color: null,
    group: key,
    system_key: key,
    progress_percentage: null,
  })

  return [
    pinned('system-open', 'open', t('workOrderWorkflows.form.statuses.defaultOpenName')),
    pinned('system-completed', 'completed', t('workOrderWorkflows.form.statuses.defaultCompletedName')),
    pinned('system-closed', 'closed', t('workOrderWorkflows.form.statuses.defaultClosedName')),
  ]
}

/** Hydrates the editable status rows from persisted statuses (already ordered by `sort_order`). */
export function statusRowsFromItems(statuses: WorkOrderWorkflowStatusItem[]): WorkOrderStatusFormRow[] {
  return statuses.map((status) => ({
    id: String(status.id),
    statusId: status.id,
    name: status.name,
    description: status.description,
    color: status.color,
    group: status.group,
    system_key: status.system_key,
    progress_percentage: status.progress_percentage,
  }))
}

export function statusRowsFromDetail(workOrderWorkflow: WorkOrderWorkflowDetail): WorkOrderStatusFormRow[] {
  return statusRowsFromItems(workOrderWorkflow.statuses)
}

/** A fresh, still-unnamed custom row; `sequence` keeps its `<SortableList>` identity unique. */
export function newCustomRow(sequence: number): WorkOrderStatusFormRow {
  return {
    id: `custom-${sequence}`,
    name: '',
    description: null,
    color: null,
    group: 'in_progress',
    system_key: null,
    progress_percentage: null,
  }
}
