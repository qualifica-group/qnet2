import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { QuotesTable } from '@/features/quotes/quotes-table'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * The "notes" row action of the Offerte grid (spec 0085): it reuses the SAME
 * `NotesDialog`/`NotesSection` the Opportunity grid opens — no dedicated notes
 * surface for Offerte. Two things make it the Offerta's own history:
 * `entityId` is the PARENT Opportunity (the notes thread lives there, under
 * the `request-management` entity_type — passing `quotes` would 422 the note
 * endpoints), and `lockedQuoteId` is the Offerta, which filters the list and
 * pins the composer to it.
 *
 * `<TableView>`, `useModuleOpener` and `NotesSection` are stubbed: their own
 * behavior is covered by their own suites, this one is about what the Quotes
 * adapter does with the action and the dialog's open state.
 */

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

vi.mock('@/features/modules/use-module-opener', () => ({
  useModuleOpener: () => ({
    openCreate: vi.fn(),
    openCreateWith: vi.fn(),
    openView: vi.fn(),
    openEdit: vi.fn(),
    openDuplicate: vi.fn(),
    sheet: null,
  }),
}))

vi.mock('@/features/quotes/api', () => ({
  QUOTES_DOMAIN: 'quotes',
  deleteQuote: vi.fn(),
  fetchQuote: vi.fn(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const notesSectionMock = vi.fn()
vi.mock('@/features/notes/notes-section', () => ({
  NotesSection: (props: { entityType: string; entityId: number; lockedQuoteId?: number | null }) => {
    notesSectionMock(props)
    return <div>{`notes-section:${props.entityType}:${props.entityId}:${props.lockedQuoteId}`}</div>
  },
}))

const NOTES_ACTION: TableActionDefinition = {
  key: 'notes',
  label: 'actions.notes',
  icon: 'message-square',
  type: 'action',
  confirm: false,
}

const ROW: TableRow = {
  id: 3,
  actions: ['view', 'notes'],
  code: 'QUO-0003',
  opportunity: { id: 42, name: 'Opportunita Acme' },
}

/** An Offerta row whose parent relation column is hidden for the actor. */
const ROW_WITHOUT_OPPORTUNITY: TableRow = { id: 4, actions: ['notes'], code: 'QUO-0004' }

const refreshMock = vi.fn()
let capturedOnAction: RowActionHandler | null = null

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, { domain: string; onAction: RowActionHandler }>(
    function TableViewStub({ domain, onAction }, ref) {
      useImperativeHandle(ref, () => ({ refresh: refreshMock }))
      capturedOnAction = onAction
      return (
        <div role="region" aria-label={`table-${domain}`}>
          <button type="button" onClick={() => onAction(NOTES_ACTION, ROW)}>
            trigger-notes
          </button>
          <button type="button" onClick={() => onAction(NOTES_ACTION, ROW_WITHOUT_OPPORTUNITY)}>
            trigger-notes-orphan
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
        <QuotesTable />
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
  capturedOnAction = null
})

describe('QuotesTable — "notes" row action (spec 0085)', () => {
  it("opens the shared notes dialog on the parent Opportunity's thread, locked to the Offerta", () => {
    renderTable()

    expect(capturedOnAction).not.toBeNull()
    fireEvent.click(screen.getByRole('button', { name: 'trigger-notes' }))

    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(screen.getByText('notes-section:request-management:42:3')).toBeInTheDocument()
    expect(notesSectionMock).toHaveBeenCalledWith(
      expect.objectContaining({ entityType: 'request-management', entityId: 42, lockedQuoteId: 3 }),
    )
  })

  it("titles the dialog with the Offerta's code, so the scope is visible", () => {
    renderTable()
    fireEvent.click(screen.getByRole('button', { name: 'trigger-notes' }))

    expect(screen.getByRole('dialog', { name: 'QUO-0003' })).toBeInTheDocument()
  })

  it('closes without refreshing the grid: no Offerta cell derives from the thread', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'trigger-notes' }))
    fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Escape' })

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(refreshMock).not.toHaveBeenCalled()
  })

  it('does nothing on a row without its parent Opportunity: there is no thread to open', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'trigger-notes-orphan' }))

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(notesSectionMock).not.toHaveBeenCalled()
  })
})
