import type { WorkOrderWorkflowStatusRef } from '@/features/work-orders/types'

export const OPEN_WORKFLOW_STATUS: WorkOrderWorkflowStatusRef = {
  id: 1,
  name: 'Aperta',
  description: null,
  color: 'blue',
  group: 'open',
  system_key: 'open',
  progress_percentage: 0,
  sort_order: 1,
}

export const IN_PROGRESS_WORKFLOW_STATUS: WorkOrderWorkflowStatusRef = {
  id: 2,
  name: 'In corso',
  description: 'Lavorazione avviata',
  color: 'amber',
  group: 'in_progress',
  system_key: null,
  progress_percentage: 40,
  sort_order: 2,
}

/** Spread into a `WorkOrderDetail` fixture: the "Stato lavorazione" fields of spec 0322. */
export const WORKFLOW_STATUS_FIELDS = {
  work_order_workflow_status_id: OPEN_WORKFLOW_STATUS.id,
  work_order_workflow_status: OPEN_WORKFLOW_STATUS,
  work_order_workflow_statuses: [OPEN_WORKFLOW_STATUS, IN_PROGRESS_WORKFLOW_STATUS],
}
