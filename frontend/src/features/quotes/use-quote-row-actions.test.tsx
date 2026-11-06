import { describe, expect, it, vi } from 'vitest'
import { act, render, renderHook, screen } from '@testing-library/react'
import { useQuoteRowActions } from '@/features/quotes/use-quote-row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/** Spec 0363: an offer's `documents` row action shows its parent Opportunity's files, read-only. */

vi.mock('@/features/modules/use-module-opener', () => ({
  useModuleOpener: () => ({ openCreate: vi.fn(), openCreateWith: vi.fn(), openView: vi.fn(), sheet: null }),
}))

vi.mock('@/features/quotes/use-quote-document', () => ({
  useQuoteDocument: () => ({ generate: vi.fn(), isGenerating: () => false }),
}))

vi.mock('@/features/attachments/documents-dialog', () => ({
  DocumentsDialog: ({ resource, id, readOnly }: { resource: string; id: number | null; readOnly?: boolean }) =>
    id === null ? null : <div role="dialog" aria-label={`documents-${resource}-${id}${readOnly ? '-read-only' : ''}`} />,
}))

const DOCUMENTS_ACTION = { key: 'documents', label: 'actions.documents', icon: 'paperclip', type: 'action', confirm: false } as TableActionDefinition

function rowWith(opportunity: unknown): TableRow {
  return { id: 5, code: 'QUO-0005', actions: ['documents'], opportunity } as TableRow
}

describe('useQuoteRowActions — documents (spec 0363)', () => {
  it("opens the parent opportunity's documents read-only", () => {
    const { result } = renderHook(() => useQuoteRowActions({ onMutated: vi.fn() }))

    act(() => result.current.handleAction(DOCUMENTS_ACTION, rowWith({ id: 12, name: 'Acme' })))
    render(<>{result.current.documentsDialog}</>)

    expect(screen.getByRole('dialog', { name: 'documents-opportunity-12-read-only' })).toBeInTheDocument()
  })

  it('opens nothing when the row carries no opportunity', () => {
    const { result } = renderHook(() => useQuoteRowActions({ onMutated: vi.fn() }))

    act(() => result.current.handleAction(DOCUMENTS_ACTION, rowWith(null)))
    render(<>{result.current.documentsDialog}</>)

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })
})
