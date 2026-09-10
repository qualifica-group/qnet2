import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { RequestManagementTable } from '@/features/request-management/request-management-table'
import type { BulkAction, TableSelection } from '@/features/table/use-bulk-actions-slot'
import type { TableRow } from '@/features/table/types'

/**
 * Scope-aware bulk assignment on Gestione richieste (spec 0110
 * AC-041/AC-043/AC-044, spec 0113 AC-026/AC-028/AC-031/AC-034): the table
 * resolves what the selected offers require and which Sede they share
 * (`domain: 'quotes'`), hands it to the shared `AssignOperatorsDialog`, and
 * reports how many offers a balanced split left without a competent operator.
 * The transfer popup, on the same table, keeps its own user-picked Sede.
 * `<TableView>` is stubbed (its own suites cover the generic slot machinery)
 * and so are the dialog's pickers, mirroring
 * `request-management-table-transfer.test.tsx`.
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

const assignRequestOperatorsMock = vi.fn()
const fetchRequestManagementCategoriesMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  fetchRequestWorkPanel: vi.fn(),
  updateRequestWork: vi.fn(),
  deleteRequest: vi.fn(),
  assignRequestOperators: (...args: unknown[]) => assignRequestOperatorsMock(...args),
  assignRequestManagerGa1: vi.fn(),
  fetchCategoryManagerLabels: vi.fn(),
  transferRequests: vi.fn(),
  fetchRequestManagementCategories: (...args: unknown[]) => fetchRequestManagementCategoriesMock(...args),
}))

const fetchAssignmentScopeMock = vi.fn()
vi.mock('@/features/assignment/api', () => ({
  fetchAssignmentScope: (...args: unknown[]) => fetchAssignmentScopeMock(...args),
}))

/**
 * The `POST /assignment/selection-scope` envelope. `RESOLVED_SITE_ID` is
 * deliberately different from `SITE_PICK_ID`: that is what tells the derived
 * Sede of the assignment popup apart from the one the user picks in the
 * transfer popup.
 */
const RESOLVED_SITE_ID = 5
function scope(overrides: Partial<{
  product_category_ids: number[]
  operational_site_id: number | null
  campaign_ids: number[]
}> = {}) {
  return {
    product_category_ids: [],
    operational_site_id: RESOLVED_SITE_ID,
    campaign_ids: [],
    ...overrides,
  }
}

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const refreshMock = vi.fn()
const clearSelectionMock = vi.fn()

const ROW: TableRow = { id: 11, actions: [], name: 'Enterprise deal', operational_site: null }
const SELECTION: TableSelection = { ids: [11, 22], rows: [ROW, { ...ROW, id: 22 }] }

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void; clearSelection: () => void },
    { domain: string; getBulkActions?: (selection: TableSelection) => BulkAction[] }
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

/** Mirrors `assign-operators-dialog.test.tsx`'s stub, plus the competence `params`. */
const SITE_PICK_ID = 7
const OPERATOR_PICK_ID = 42
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    resource,
    value,
    onChange,
    disabled,
    params,
    labels,
  }: {
    resource: string
    value: number | null
    onChange: (value: number | null) => void
    disabled?: boolean
    params?: Record<string, string | number | string[] | number[]>
    labels: { triggerLabel: string; empty: string }
  }) => (
    <button
      type="button"
      aria-label={labels.triggerLabel}
      disabled={disabled}
      data-params={params ? JSON.stringify(params) : ''}
      data-empty={labels.empty}
      onClick={() => onChange(resource === 'operational-sites' ? SITE_PICK_ID : OPERATOR_PICK_ID)}
    >
      {value ?? 'none'}
    </button>
  ),
}))

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <RequestManagementTable />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

function openAssignPopup() {
  fireEvent.click(screen.getByRole('button', { name: 'Assign operators' }))
}

/** Balanced flow: open the popup, pick the mode, confirm — no Sede step left. */
function assignBalanced() {
  openAssignPopup()
  fireEvent.click(screen.getByRole('radio', { name: 'Balanced split' }))
  fireEvent.click(screen.getByRole('button', { name: 'Assign' }))
}

/** Opens the assignment popup on the single-operator mode, which shows the picker. */
function openAssignSingle() {
  openAssignPopup()
  fireEvent.click(screen.getByRole('radio', { name: 'Assign to operator' }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  window.localStorage.clear()
  canMock.mockReset()
  canMock.mockReturnValue(true)
  refreshMock.mockReset()
  clearSelectionMock.mockReset()
  assignRequestOperatorsMock.mockReset()
  assignRequestOperatorsMock.mockResolvedValue({ assigned: 2, skipped: 0 })
  fetchRequestManagementCategoriesMock.mockReset()
  fetchRequestManagementCategoriesMock.mockResolvedValue([])
  fetchAssignmentScopeMock.mockReset()
  fetchAssignmentScopeMock.mockResolvedValue(scope())
  vi.mocked(toast.success).mockClear()
  vi.mocked(toast.error).mockClear()
})

describe('RequestManagementTable — scope-aware assignment (spec 0110/0113)', () => {
  it('resolves the scope of the selected offers only once the popup is open', async () => {
    renderTable()

    expect(fetchAssignmentScopeMock).not.toHaveBeenCalled()

    openAssignPopup()

    await waitFor(() =>
      expect(fetchAssignmentScopeMock).toHaveBeenCalledWith({ domain: 'quotes', ids: [11, 22] }),
    )
  })

  it('never renders the Sede field on the assignment popup (AC-026)', () => {
    renderTable()

    openAssignPopup()
    fireEvent.click(screen.getByRole('radio', { name: 'Balanced split' }))
    expect(screen.queryByRole('button', { name: 'Site' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Assign' })).toBeEnabled()

    fireEvent.click(screen.getByRole('radio', { name: 'Assign to operator' }))
    expect(screen.queryByRole('button', { name: 'Site' })).not.toBeInTheDocument()
  })

  it('sends no operational_site_id: the server derives it from each offer', async () => {
    renderTable()

    assignBalanced()

    // TanStack v5 hands the mutation context as a second argument, so the
    // payload itself is asserted rather than the whole call.
    await waitFor(() => expect(assignRequestOperatorsMock).toHaveBeenCalled())
    expect(assignRequestOperatorsMock.mock.calls[0][0]).toEqual({
      request_ids: [11, 22],
      mode: 'balanced',
    })
  })

  it('narrows the operator picker to the derived Sede and the competent users (AC-028)', async () => {
    fetchAssignmentScopeMock.mockResolvedValue(scope({ product_category_ids: [4, 9] }))
    renderTable()

    openAssignSingle()

    await waitFor(() =>
      expect(screen.getByRole('button', { name: 'Operator' })).toHaveAttribute(
        'data-params',
        JSON.stringify({
          operational_site_id: RESOLVED_SITE_ID,
          competence_category_ids: [4, 9],
        }),
      ),
    )
    expect(screen.getByRole('button', { name: 'Operator' })).toHaveAttribute(
      'data-empty',
      'No operator is competent for the selected records.',
    )
  })

  it('applies no competence filter when the selection expresses no requirement', async () => {
    renderTable()

    openAssignSingle()

    await waitFor(() =>
      expect(screen.getByRole('button', { name: 'Operator' })).toHaveAttribute(
        'data-params',
        JSON.stringify({ operational_site_id: RESOLVED_SITE_ID }),
      ),
    )
    expect(fetchAssignmentScopeMock).toHaveBeenCalledTimes(1)
  })

  it('keeps the operator picker disabled and unscoped while the scope resolves (AC-028)', () => {
    fetchAssignmentScopeMock.mockReturnValue(new Promise(() => {}))
    renderTable()

    openAssignSingle()

    const picker = screen.getByRole('button', { name: 'Operator' })
    expect(picker).toBeDisabled()
    expect(picker).toHaveAttribute('data-params', '')
  })

  it('keeps the operator picker disabled when the scope fails to resolve (AC-034)', async () => {
    fetchAssignmentScopeMock.mockRejectedValue(new Error('scope down'))
    renderTable()

    openAssignSingle()

    await waitFor(() => expect(fetchAssignmentScopeMock).toHaveBeenCalledTimes(1))
    const picker = screen.getByRole('button', { name: 'Operator' })
    expect(picker).toBeDisabled()
    expect(picker).toHaveAttribute('data-params', '')
  })

  it('disables neither mode card, whatever campaigns the selection spans (AC-031)', async () => {
    fetchAssignmentScopeMock.mockResolvedValue(scope({ campaign_ids: [3, 8] }))
    renderTable()

    openAssignPopup()

    await waitFor(() => expect(fetchAssignmentScopeMock).toHaveBeenCalledTimes(1))
    expect(screen.getByRole('radio', { name: 'Assign to operator' })).not.toHaveAttribute(
      'aria-disabled',
    )
    expect(screen.getByRole('radio', { name: 'Balanced split' })).not.toHaveAttribute(
      'aria-disabled',
    )
  })

  // The transfer travels through its own endpoint but writes the SAME GA2
  // Operatore slot (`RequestTransferService::transferOne` ->
  // `RequestOperatorWriter::apply`), so it is a place where operators get
  // assigned and it filters identically. `lockedMode="single"` means UI-only
  // filtering, per the spec's own decision for `single`.
  it('keeps the transfer popup on its own user-picked Sede plus the same competence (AC-027)', async () => {
    fetchAssignmentScopeMock.mockResolvedValue(scope({ product_category_ids: [4] }))
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'Transfer contact' }))

    await waitFor(() =>
      expect(fetchAssignmentScopeMock).toHaveBeenCalledWith({ domain: 'quotes', ids: [11, 22] }),
    )

    // The Sede field is still there, and the picker follows THAT value, not
    // the Sede the offers were resolved to (`RESOLVED_SITE_ID`).
    fireEvent.click(screen.getByRole('button', { name: 'Site' }))

    await waitFor(() =>
      expect(screen.getByRole('button', { name: 'Operator' })).toHaveAttribute(
        'data-params',
        JSON.stringify({ operational_site_id: SITE_PICK_ID, competence_category_ids: [4] }),
      ),
    )
  })

  it('resolves nothing for the transfer popup until it is opened', () => {
    renderTable()

    expect(fetchAssignmentScopeMock).not.toHaveBeenCalled()
  })

  it('reports the offers left without a competent operator (AC-044)', async () => {
    assignRequestOperatorsMock.mockResolvedValue({ assigned: 1, skipped: 1 })
    renderTable()

    assignBalanced()

    await waitFor(() =>
      expect(toast.success).toHaveBeenCalledWith(
        'Operators assigned to 1 request(s). 1 left without a competent operator.',
      ),
    )
  })

  it('keeps the plain feedback when nothing was skipped', async () => {
    renderTable()

    assignBalanced()

    await waitFor(() =>
      expect(toast.success).toHaveBeenCalledWith('Operators assigned to 2 request(s).'),
    )
  })
})
