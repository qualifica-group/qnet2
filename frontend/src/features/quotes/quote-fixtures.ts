import type { QuoteWorkflowStatusRef } from '@/features/quotes/types'

/**
 * The `open` row every resolved quote workflow set carries (spec 0083): the
 * status a freshly created quote lands on, and the one the opportunity falls
 * back to when it has no quotes at all.
 *
 * Shared by the quote test suites so the shape stays in one place — five
 * fixtures were repeating it verbatim, and every field added to
 * `QuoteWorkflowStatusRef` had to be pasted into all of them.
 */
export const WORKFLOW_STATUS_OPEN: QuoteWorkflowStatusRef = {
  id: 1,
  name: 'Bozza',
  color: 'slate',
  description: null,
  group: 'open',
  requires_note: false,
}

/** A row that DEMANDS a transition note (AC-023): the negative case of the same set. */
export const WORKFLOW_STATUS_REQUIRES_NOTE: QuoteWorkflowStatusRef = {
  id: 2,
  name: 'Accettata',
  color: 'green',
  description: null,
  group: 'closed_won',
  requires_note: true,
}
