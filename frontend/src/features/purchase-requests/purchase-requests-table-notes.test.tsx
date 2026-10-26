import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { PurchaseRequestsTable } from '@/features/purchase-requests/purchase-requests-table'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * The "notes" row action (spec 0312, same as Opportunita' and Anagrafiche): it opens the
 * agnostic `NotesDialog` on the row's `purchase-requests` thread and refreshes the grid
 * on every write inside it, so the `notes_count` badge stays current. The
 * generic `<TableView>` and `NotesSection` are stubbed.
 */

vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

vi.mock('@/features/notes/notes-section', () => ({
  NotesSection: (props: { entityType: string; entityId: number; onThreadChanged?: () => void }) => (
    <button type="button" onClick={props.onThreadChanged}>{`notes-section:${props.entityType}:${props.entityId}`}</button>
  ),
}))

const ROW: TableRow = { id: 42, actions: ['view', 'notes'], notes_count: 3 }
const NOTES_ACTION: TableActionDefinition = {
  key: 'notes',
  label: 'actions.notes',
  icon: 'messages-square',
  type: 'action',
  confirm: false,
  count_field: 'notes_count',
}

const refreshMock = vi.fn()
let capturedIconMap: Record<string, unknown> = {}

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, { onAction: RowActionHandler; iconMap?: Record<string, unknown> }>(
    function TableViewStub({ onAction, iconMap }, ref) {
      useImperativeHandle(ref, () => ({ refresh: refreshMock }))
      capturedIconMap = iconMap ?? {}
      return (
        <button type="button" onClick={() => onAction(NOTES_ACTION, ROW)}>
          trigger-notes
        </button>
      )
    },
  ),
}))

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <PurchaseRequestsTable />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  refreshMock.mockReset()
  capturedIconMap = {}
})

describe('PurchaseRequestsTable — "notes" row action (spec 0312)', () => {
  it('opens the notes dialog on the purchase-requests thread of the row and maps its icon', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'trigger-notes' }))

    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(screen.getByText('notes-section:purchase-requests:42')).toBeInTheDocument()
    expect(capturedIconMap).toHaveProperty('messages-square')
  })

  it('refreshes the grid on every write inside the thread', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'trigger-notes' }))
    fireEvent.click(screen.getByText('notes-section:purchase-requests:42'))

    expect(refreshMock).toHaveBeenCalledTimes(1)
  })
})
