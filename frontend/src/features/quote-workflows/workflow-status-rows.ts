import type { TFunction } from 'i18next'
import {
  isTailWorkflowSystemKey,
  type QuoteWorkflowDetail,
  type WorkflowStatusFormRow,
  type WorkflowStatusRowPatch,
} from '@/features/quote-workflows/types'

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

/** Hydrates the editable status rows from a persisted workflow (already ordered by `sort_order`). */
export function statusRowsFromDetail(quoteWorkflow: QuoteWorkflowDetail): WorkflowStatusFormRow[] {
  return quoteWorkflow.statuses.map((status) => ({
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

/** Inserts a new custom row before the pinned closed tail (the tail always stays last). */
export function withCustomRow(rows: WorkflowStatusFormRow[], newRow: WorkflowStatusFormRow): WorkflowStatusFormRow[] {
  const tailIndex = rows.findIndex((row) => isTailWorkflowSystemKey(row.system_key))
  const insertAt = tailIndex === -1 ? rows.length : tailIndex
  return [...rows.slice(0, insertAt), newRow, ...rows.slice(insertAt)]
}

export function withoutRow(rows: WorkflowStatusFormRow[], id: string): WorkflowStatusFormRow[] {
  return rows.filter((row) => row.id !== id)
}

export function withPatchedRow(
  rows: WorkflowStatusFormRow[],
  id: string,
  patch: WorkflowStatusRowPatch,
): WorkflowStatusFormRow[] {
  return rows.map((row) => (row.id === id ? { ...row, ...patch } : row))
}

export function inOrder(rows: WorkflowStatusFormRow[], orderedIds: string[]): WorkflowStatusFormRow[] {
  const byId = new Map(rows.map((row) => [row.id, row]))
  return orderedIds
    .map((id) => byId.get(id))
    .filter((row): row is WorkflowStatusFormRow => row !== undefined)
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
