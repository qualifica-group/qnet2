import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { RequestManagementTable } from '@/features/request-management/request-management-table'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { BulkAction, TableSelection } from '@/features/table/use-bulk-actions-slot'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * "Trasferisci contatto" row + bulk action (spec 0079 AC-027/AC-030/AC-031):
 * both funnel into the SAME `AssignOperatorsDialog`, mounted with
 * `lockedMode="single"` — a row transfer is just a one-element selection.
 * `<TableView>` is stubbed (its own suites cover the generic slot machinery);
 * only its `AsyncPaginatedSelect` pickers are stubbed here too, mirroring
 * `leads-table-assign.test.tsx`, so the real dialog exercises the actual
 * mode-skip/canSubmit logic instead of a re-implementation of it.
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

const navigateMock = vi.fn()
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
  return { ...actual, useNavigate: () => navigateMock }
})

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const fetchRequestWorkPanelMock = vi.fn()
const deleteRequestMock = vi.fn()
const assignRequestOperatorsMock = vi.fn()
const transferRequestsMock = vi.fn()
const fetchRequestManagementCategoriesMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  fetchRequestWorkPanel: (...args: unknown[]) => fetchRequestWorkPanelMock(...args),
  updateRequestWork: vi.fn(),
  deleteRequest: (...args: unknown[]) => deleteRequestMock(...args),
  assignRequestOperators: (...args: unknown[]) => assignRequestOperatorsMock(...args),
  // Spec 0104: pulled in by the table's bulk GA1 flow; this suite
  // exercises neither, it only has to satisfy the module surface.
  assignRequestManagerGa1: vi.fn(),
  fetchCategoryManagerLabels: vi.fn(),
  transferRequests: (...args: unknown[]) => transferRequestsMock(...args),
  fetchRequestManagementCategories: (...args: unknown[]) => fetchRequestManagementCategoriesMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const refreshMock = vi.fn()
const clearSelectionMock = vi.fn()
let capturedBulkActions: ((selection: TableSelection) => BulkAction[]) | null = null

const ROW: TableRow = { id: 7, actions: ['view', 'transfer-contact'], name: 'Enterprise deal', operational_site: null }

function transferAction(): TableActionDefinition {
  return { key: 'transfer-contact', label: 'actions.transferContact', icon: 'arrow-right-left', type: 'action', confirm: false }
}

let bulkSelection: TableSelection = { ids: [11, 22], rows: [ROW, { ...ROW, id: 22 }] }

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void; clearSelection: () => void },
    {
      domain: string
      onAction: RowActionHandler
      getBulkActions?: (selection: TableSelection) => BulkAction[]
    }
  >(function TableViewStub({ domain, onAction, getBulkActions }, ref) {
    useImperativeHandle(ref, () => ({ refresh: refreshMock, clearSelection: clearSelectionMock }))
    capturedBulkActions = getBulkActions ?? null
    return (
      <div role="region" aria-label={`table-${domain}`}>
        <button type="button" onClick={() => onAction(transferAction(), ROW)}>
          trigger-row-transfer
        </button>
        {getBulkActions?.(bulkSelection).map((bulkAction) => (
          <button key={bulkAction.key} type="button" onClick={() => bulkAction.onSelect()}>
            {bulkAction.label}
          </button>
        ))}
      </div>
    )
  }),
}))

/** Mirrors `assign-operators-dialog.test.tsx`'s stub: a plain button per picker. */
const SITE_PICK_ID = 7
const OPERATOR_PICK_ID = 42
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    resource,
    value,
    onChange,
    labels,
  }: {
    resource: string
    value: number | null
    onChange: (value: number | null) => void
    labels: { triggerLabel: string }
  }) => (
    <button
      type="button"
      aria-label={labels.triggerLabel}
      onClick={() => onChange(resource === 'operational-sites' ? SITE_PICK_ID : OPERATOR_PICK_ID)}
    >
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

/** Full flow: open the (already visible) dialog, pick Sede then Operatore, confirm. */
function fillAndConfirm() {
  fireEvent.click(screen.getByRole('button', { name: 'Site' }))
  fireEvent.click(screen.getByRole('button', { name: 'Operator' }))
  fireEvent.click(screen.getByRole('button', { name: 'Assign' }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  navigateMock.mockReset()
  refreshMock.mockReset()
  clearSelectionMock.mockReset()
  capturedBulkActions = null
  fetchRequestWorkPanelMock.mockReset()
  deleteRequestMock.mockReset()
  assignRequestOperatorsMock.mockReset()
  transferRequestsMock.mockReset()
  fetchRequestManagementCategoriesMock.mockReset()
  fetchRequestManagementCategoriesMock.mockResolvedValue([])
  bulkSelection = { ids: [11, 22], rows: [ROW, { ...ROW, id: 22 }] }
  vi.mocked(toast.success).mockClear()
  vi.mocked(toast.error).mockClear()
})

describe('RequestManagementTable — "transfer-contact" row action (spec 0079 AC-030)', () => {
  it('opens the shared dialog on a one-element selection, skipping the mode step (AC-027)', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'trigger-row-transfer' }))

    expect(screen.getByRole('dialog')).toHaveTextContent('Transfer contact')
    expect(screen.queryByRole('radio', { name: 'Balanced split' })).not.toBeInTheDocument()
    expect(screen.queryByRole('radio', { name: 'Assign to operator' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Site' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Operator' })).toBeInTheDocument()
  })

  it('transfers only that row, refreshes the grid and toasts on success', async () => {
    transferRequestsMock.mockResolvedValue({ transferred: 1 })
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'trigger-row-transfer' }))
    fillAndConfirm()

    await waitFor(() =>
      expect(transferRequestsMock).toHaveBeenCalledWith({
        request_ids: [7],
        operational_site_id: SITE_PICK_ID,
        operator_id: OPERATOR_PICK_ID,
      }),
    )
    await waitFor(() => expect(refreshMock).toHaveBeenCalled())
    expect(clearSelectionMock).toHaveBeenCalled()
    expect(toast.success).toHaveBeenCalledWith('1 request(s) transferred.')
  })
})

describe('RequestManagementTable — bulk "transfer-contact" action (spec 0079 AC-031)', () => {
  it('is gated on request-management.update AND request-management.transferContact', () => {
    canMock.mockImplementation((permission) => permission !== 'request-management.transferContact')
    renderTable()

    // Spec 0104 added a third entry to the same menu, gated on its own
    // ability: denying `transferContact` drops that one entry alone.
    expect(capturedBulkActions?.(bulkSelection).map((entry) => entry.key)).toEqual([
      'assign-operators',
      'assign-manager-ga1',
    ])
  })

  it('transfers every selected id, refreshes the grid and clears the selection', async () => {
    transferRequestsMock.mockResolvedValue({ transferred: 2 })
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'Transfer contact' }))
    fillAndConfirm()

    await waitFor(() =>
      expect(transferRequestsMock).toHaveBeenCalledWith({
        request_ids: [11, 22],
        operational_site_id: SITE_PICK_ID,
        operator_id: OPERATOR_PICK_ID,
      }),
    )
    await waitFor(() => expect(refreshMock).toHaveBeenCalled())
    expect(clearSelectionMock).toHaveBeenCalled()
    expect(toast.success).toHaveBeenCalledWith('2 request(s) transferred.')
  })

  it('toasts the generic error and leaves the grid/selection untouched on failure', async () => {
    transferRequestsMock.mockRejectedValue(new Error('network down'))
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'Transfer contact' }))
    fillAndConfirm()

    await waitFor(() => expect(transferRequestsMock).toHaveBeenCalledTimes(1))
    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith('Unable to transfer the contact. Please try again.'),
    )
    expect(refreshMock).not.toHaveBeenCalled()
    expect(clearSelectionMock).not.toHaveBeenCalled()
  })
})
