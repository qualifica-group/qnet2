import type { TFunction } from 'i18next'
import type { QuoteWorkflowDetail, QuoteWorkflowStatusItem, WorkflowStatusFormRow } from '@/features/quote-workflows/types'

/**
 * The three MANDATORY pinned system rows before a workflow exists (AC-004):
 * editable from the start, pre-filled with the default open / closed-won /
 * closed-lost labels, in pinned order (open first, then the closed-won ->
 * closed-lost tail). The user may rename them up front; they are sent in the
 * create payload so the backend seeds the auto-created rows with these names.
 */
export function initialSystemStatusRows(t: TFunction): WorkflowStatusFormRow[] {
  return [
    {
      id: 'system-open',
      name: t('quoteWorkflows.form.statuses.defaultOpenName'),
      description: null,
      color: null,
      group: 'open',
      system_key: 'open',
      requires_note: false,
    },
    {
      id: 'system-closed-won',
      name: t('quoteWorkflows.form.statuses.defaultClosedWonName'),
      description: null,
      color: null,
      group: 'closed_won',
      system_key: 'closed_won',
      requires_note: false,
    },
    {
      id: 'system-closed-lost',
      name: t('quoteWorkflows.form.statuses.defaultClosedLostName'),
      description: null,
      color: null,
      group: 'closed_lost',
      system_key: 'closed_lost',
      requires_note: false,
    },
  ]
}

/** Hydrates the editable status rows from persisted statuses (already ordered by `sort_order`). */
export function statusRowsFromItems(statuses: QuoteWorkflowStatusItem[]): WorkflowStatusFormRow[] {
  return statuses.map((status) => ({
    id: String(status.id),
    statusId: status.id,
    name: status.name,
    description: status.description,
    color: status.color,
    group: status.group,
    system_key: status.system_key,
    requires_note: status.requires_note,
  }))
}

export function statusRowsFromDetail(quoteWorkflow: QuoteWorkflowDetail): WorkflowStatusFormRow[] {
  return statusRowsFromItems(quoteWorkflow.statuses)
}

/** A fresh, still-unnamed custom row; `sequence` keeps its `<SortableList>` identity unique. */
export function newCustomRow(sequence: number): WorkflowStatusFormRow {
  return {
    id: `custom-${sequence}`,
    name: '',
    description: null,
    color: null,
    group: 'pending',
    system_key: null,
    requires_note: false,
  }
}
