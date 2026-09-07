import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { RequestManagementTable } from '@/features/request-management/request-management-table'
import type { ManagerLabels } from '@/features/request-management/types'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { BulkAction, TableSelection } from '@/features/table/use-bulk-actions-slot'
import type { TableRow } from '@/features/table/types'

/**
 * Bulk "Assegna GA3" action (spec 0104, AC-020 -> AC-025): the Sede-less
 * sibling of the operators assignment. `<TableView>` is stubbed (its own
 * suites cover the generic bulk-actions slot) and so is `AsyncPaginatedSelect`,
 * exactly as the transfer suite does, so the REAL dialog runs its own
 * clear-vs-assign logic instead of a re-implementation of it.
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

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'page',
}))

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
  return { ...actual, useNavigate: () => vi.fn() }
})

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const assignRequestManagerGa3Mock = vi.fn()
const fetchRequestManagementCategoriesMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  fetchRequestWorkPanel: vi.fn(),
  updateRequestWork: vi.fn(),
  deleteRequest: vi.fn(),
  assignRequestOperators: vi.fn(),
  assignRequestManagerGa3: (...args: unknown[]) => assignRequestManagerGa3Mock(...args),
  transferRequests: vi.fn(),
  fetchCategoryManagerLabels: vi.fn(),
  fetchRequestManagementCategories: (...args: unknown[]) =>
    fetchRequestManagementCategoriesMock(...args),
}))

/** The active tab's G.A. labels, the source of the slot's own name (AC-021). */
let managerLabels: ManagerLabels | undefined
vi.mock('@/features/request-management/use-active-category-manager-labels', () => ({
  useActiveCategoryManagerLabels: () => ({ data: managerLabels }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const refreshMock = vi.fn()
const clearSelectionMock = vi.fn()

const ROW: TableRow = { id: 11, actions: ['view'], name: 'Enterprise deal', operational_site: null }
const SELECTION: TableSelection = { ids: [11, 22], rows: [ROW, { ...ROW, id: 22 }] }

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void; clearSelection: () => void },
    {
      domain: string
      onAction: RowActionHandler
      getBulkActions?: (selection: TableSelection) => BulkAction[]
    }
  >(function TableViewStub({ domain, getBulkActions }, ref) {
    useImperativeHandle(ref, () => ({ refresh: refreshMock, clearSelection: clearSelectionMock }))
    return (
      <div role="region" aria-label={`table-${domain}`}>
        {getBulkActions?.(SELECTION).map((bulkAction) => (
          <button key={bulkAction.key} type="button" onClick={() => bulkAction.onSelect()}>
            {bulkAction.label}
          </button>
        ))}
      </div>
    )
  }),
}))

/** Mirrors the transfer suite's stub: a plain button that picks a fixed user. */
const USER_PICK_ID = 42
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    labels,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    labels: { triggerLabel: string }
  }) => (
    <button type="button" aria-label={labels.triggerLabel} onClick={() => onChange(USER_PICK_ID)}>
      {value ?? 'none'}
    </button>
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
  clearSelectionMock.mockReset()
  assignRequestManagerGa3Mock.mockReset()
  assignRequestManagerGa3Mock.mockResolvedValue({ assigned: 2 })
  fetchRequestManagementCategoriesMock.mockReset()
  fetchRequestManagementCategoriesMock.mockResolvedValue([])
  managerLabels = { '3': 'Referente GOL' }
  vi.mocked(toast.success).mockClear()
  vi.mocked(toast.error).mockClear()
})

describe('RequestManagementTable — bulk GA3 assignment (spec 0104)', () => {
  it('is gated on request-management.update AND request-management.assignManagerGa3 (AC-020)', () => {
    canMock.mockImplementation((permission) => permission !== 'request-management.assignManagerGa3')
    const { unmount } = renderTable()
    expect(screen.queryByRole('button', { name: 'Assign Referente GOL' })).not.toBeInTheDocument()
    unmount()

    canMock.mockImplementation((permission) => permission !== 'request-management.update')
    renderTable()
    expect(screen.queryByRole('button', { name: 'Assign Referente GOL' })).not.toBeInTheDocument()
  })

  it('names the action after the active category label for position 3 (AC-021)', () => {
    renderTable()

    expect(screen.getByRole('button', { name: 'Assign Referente GOL' })).toBeInTheDocument()
  })

  it('falls back to the column label when the category defines no position 3 (AC-021)', () => {
    managerLabels = { '2': 'Operatore' }
    renderTable()

    expect(screen.getByRole('button', { name: 'Assign Tutor' })).toBeInTheDocument()
  })

  it('opens a popup with ONE user field, no Sede and no mode picker (AC-022)', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'Assign Referente GOL' }))

    const dialog = screen.getByRole('dialog')
    expect(dialog).toHaveTextContent('Assign Referente GOL')
    expect(screen.getByRole('button', { name: 'Referente GOL' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Site' })).not.toBeInTheDocument()
    expect(screen.queryByRole('radio', { name: 'Balanced split' })).not.toBeInTheDocument()
    expect(screen.queryByRole('radio', { name: 'Assign to operator' })).not.toBeInTheDocument()
  })

  it('assigns the picked user to the whole selection, then refreshes and clears it (AC-024)', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'Assign Referente GOL' }))
    fireEvent.click(screen.getByRole('button', { name: 'Referente GOL' }))
    fireEvent.click(screen.getByRole('button', { name: 'Assign' }))

    // Asserted on the first argument alone: TanStack hands the mutationFn a
    // second, internal context object.
    await waitFor(() => expect(assignRequestManagerGa3Mock).toHaveBeenCalled())
    expect(assignRequestManagerGa3Mock.mock.calls[0][0]).toEqual({
      request_ids: [11, 22],
      manager_ga3_id: USER_PICK_ID,
    })
    await waitFor(() => expect(refreshMock).toHaveBeenCalled())
    expect(clearSelectionMock).toHaveBeenCalled()
    expect(toast.success).toHaveBeenCalledWith('Assignment updated on 2 request(s).')
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('confirming with no user picked clears the slot on the selection (AC-023)', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'Assign Referente GOL' }))
    expect(screen.getByRole('dialog')).toHaveTextContent(
      'No user selected: confirming clears the slot on every selected request.',
    )
    fireEvent.click(screen.getByRole('button', { name: 'Assign' }))

    await waitFor(() => expect(assignRequestManagerGa3Mock).toHaveBeenCalled())
    expect(assignRequestManagerGa3Mock.mock.calls[0][0]).toEqual({
      request_ids: [11, 22],
      manager_ga3_id: null,
    })
  })

  it('keeps the popup open with the current pick when the write fails (AC-024)', async () => {
    assignRequestManagerGa3Mock.mockRejectedValue(new Error('boom'))
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'Assign Referente GOL' }))
    fireEvent.click(screen.getByRole('button', { name: 'Referente GOL' }))
    fireEvent.click(screen.getByRole('button', { name: 'Assign' }))

    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith('Unable to complete the assignment. Try again.'),
    )
    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(refreshMock).not.toHaveBeenCalled()
  })
})
