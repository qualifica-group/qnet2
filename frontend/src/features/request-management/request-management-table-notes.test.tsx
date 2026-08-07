import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RequestManagementTable } from '@/features/request-management/request-management-table'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * The `notes` row action of the request-management adapter (direttiva utente
 * 2026-08-07): same wiring the Offerte grid uses (`quotes-table-notes.test.tsx`)
 * — `entityId` is the PARENT Opportunity, where the thread lives (passing the
 * Offerta id would 422 the note endpoints), and `lockedQuoteId` is the row's
 * OWN Offerta, which filters the list and pins the composer to it.
 *
 * `<TableView>` and `NotesSection` are stubbed: their own behavior is covered
 * by their suites, this one is about what the adapter passes to the dialog.
 */

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'page',
}))

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const notesSectionMock = vi.fn()
vi.mock('@/features/notes/notes-section', () => ({
  NotesSection: (props: { entityType: string; entityId: number; lockedQuoteId?: number | null }) => {
    notesSectionMock(props)
    return <div>{`notes-section:${props.entityType}:${props.entityId}:${props.lockedQuoteId}`}</div>
  },
}))

// `opportunity_id` deliberately DIFFERENT from `id` (the row's Offerta id):
// an inverted wiring fails the assertions instead of passing by coincidence.
const ROW: TableRow = { id: 7, opportunity_id: 99, actions: ['view', 'notes'], notes_count: 2 }

const NOTES_ACTION: TableActionDefinition = {
  key: 'notes',
  label: 'actions.notes',
  icon: 'messages-square',
  type: 'action',
  confirm: false,
  count_field: 'notes_count',
}

const refreshMock = vi.fn()

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, { domain: string; onAction: RowActionHandler }>(
    function TableViewStub({ domain, onAction }, ref) {
      useImperativeHandle(ref, () => ({ refresh: refreshMock }))
      return (
        <div role="region" aria-label={`table-${domain}`}>
          <button type="button" onClick={() => onAction(NOTES_ACTION, ROW)}>
            trigger-notes
          </button>
        </div>
      )
    },
  ),
}))

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <RequestManagementTable />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  refreshMock.mockReset()
  notesSectionMock.mockReset()
})

describe('RequestManagementTable — "notes" row action', () => {
  it('opens the thread of the parent Opportunity locked on the row\'s Offerta', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'trigger-notes' }))

    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(screen.getByText('notes-section:request-management:99:7')).toBeInTheDocument()
    expect(notesSectionMock).toHaveBeenCalledWith(
      expect.objectContaining({ entityType: 'request-management', entityId: 99, lockedQuoteId: 7 }),
    )
  })

  it('refreshes the grid when the dialog closes', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'trigger-notes' }))
    fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Escape' })

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(refreshMock).toHaveBeenCalledTimes(1)
  })
})
