import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { OpportunitiesTable } from '@/features/opportunities/opportunities-table'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * The "notes" row action (user directive 2026-08-05: same actions as Gestione
 * Richieste): clicking it opens the agnostic `NotesDialog` on the row's
 * thread; the grid refreshes on every write inside it (`onThreadChanged`) and
 * again on close, so the `notes_count` badge stays current without waiting for
 * the dialog to be dismissed. The thread is registered under the `request-management`
 * entity_type (the notes registry maps the Opportunity record there), so that
 * is the slug the dialog must receive — passing `opportunities` would 422 the
 * note endpoints. The generic `<TableView>` is stubbed (its own behavior is
 * covered elsewhere) and so is `NotesSection`; this suite is only about what
 * the opportunities adapter does with the action and the dialog's open state.
 *
 * The `edit` action is gone from this adapter: `handleAction` must ignore it
 * (the detail surface owns the Edit button, `detailOwnsEditAction`).
 */

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
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

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/opportunities/api', () => ({
  deleteOpportunity: vi.fn(),
  OPPORTUNITIES_DOMAIN: 'opportunities',
  OPPORTUNITY_ATTACHABLE_ALIAS: 'opportunity',
}))

const notesSectionMock = vi.fn()
vi.mock('@/features/notes/notes-section', () => ({
  NotesSection: (props: { entityType: string; entityId: number }) => {
    notesSectionMock(props)
    return <div>{`notes-section:${props.entityType}:${props.entityId}`}</div>
  },
}))

const ROW: TableRow = {
  id: 42,
  actions: ['view', 'documents', 'notes', 'delete'],
  notes_count: 5,
}

function action(key: string): TableActionDefinition {
  return {
    key,
    label: `actions.${key}`,
    icon: key === 'notes' ? 'messages-square' : 'eye',
    type: 'action',
    confirm: false,
    count_field: key === 'notes' ? 'notes_count' : null,
  }
}

const refreshMock = vi.fn()
let capturedOnAction: RowActionHandler | null = null

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, { domain: string; onAction: RowActionHandler }>(
    function TableViewStub({ domain, onAction }, ref) {
      useImperativeHandle(ref, () => ({ refresh: refreshMock }))
      capturedOnAction = onAction
      return (
        <div role="region" aria-label={`table-${domain}`}>
          <button type="button" onClick={() => onAction(action('notes'), ROW)}>
            trigger-notes
          </button>
          <button type="button" onClick={() => onAction(action('edit'), ROW)}>
            trigger-edit
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
        <OpportunitiesTable />
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

describe('OpportunitiesTable — "notes" row action', () => {
  it('opens the notes dialog for the row on the request-management thread', () => {
    renderTable()

    expect(capturedOnAction).not.toBeNull()
    fireEvent.click(screen.getByRole('button', { name: 'trigger-notes' }))

    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(screen.getByText('notes-section:request-management:42')).toBeInTheDocument()
    expect(notesSectionMock).toHaveBeenCalledWith(
      expect.objectContaining({ entityType: 'request-management', entityId: 42 }),
    )
  })

  it('refreshes the grid when the dialog closes, so notes_count stays current', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'trigger-notes' }))
    expect(screen.getByRole('dialog')).toBeInTheDocument()

    fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Escape' })

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(refreshMock).toHaveBeenCalledTimes(1)
  })

  it('refreshes the grid as soon as the thread changes, without closing the dialog', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'trigger-notes' }))

    const props = notesSectionMock.mock.calls.at(-1)?.[0] as {
      onThreadChanged?: () => void
    }
    expect(props.onThreadChanged).toBeTypeOf('function')

    props.onThreadChanged?.()

    // The badge follows the write itself: no close needed, and the dialog stays open.
    expect(refreshMock).toHaveBeenCalledTimes(1)
    expect(screen.getByRole('dialog')).toBeInTheDocument()
  })

  it('ignores an "edit" action: the detail surface owns the Edit button', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'trigger-edit' }))

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })
})
