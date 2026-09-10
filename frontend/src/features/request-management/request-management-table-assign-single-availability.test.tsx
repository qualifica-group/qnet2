import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RequestManagementTable } from '@/features/request-management/request-management-table'
import type { BulkAction, TableSelection } from '@/features/table/use-bulk-actions-slot'
import type { TableRow } from '@/features/table/types'

/**
 * "Assegna a operatore" disabled when no single operator covers every selected
 * Offerta (user directive 2026-09-10, spec 0113 rev.3). Competence is an OR
 * over the required categories, so a picker filtered on their union offers
 * operators valid for only SOME of the selection — and since the server
 * rejects `mode=single` unless the operator covers them all, the user would
 * pick from the list and collect a 422. `single_operator_available` is the
 * server's answer to exactly that question; `undefined` (unresolved or failed)
 * is an unknown state, never a negative one.
 * `<TableView>` and the dialog's pickers are stubbed, mirroring
 * `request-management-table-assign-competence.test.tsx`.
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
  fetchRequestManagementCategories: (...args: unknown[]) =>
    fetchRequestManagementCategoriesMock(...args),
}))

const fetchAssignmentScopeMock = vi.fn()
vi.mock('@/features/assignment/api', () => ({
  fetchAssignmentScope: (...args: unknown[]) => fetchAssignmentScopeMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

/** The `POST /assignment/selection-scope` envelope, availability included. */
function scope(
  overrides: Partial<{
    product_category_ids: number[]
    operational_site_id: number | null
    campaign_ids: number[]
    single_operator_available: boolean
  }> = {},
) {
  return {
    product_category_ids: [],
    operational_site_id: 5,
    campaign_ids: [],
    single_operator_available: true,
    ...overrides,
  }
}

/** Wording of the disabled card, asserted verbatim: it must name the cause. */
const NO_COMMON_OPERATOR_REASON =
  'Unavailable: no operator is enabled for every selected request, which differ by Site or products.'

const ROW: TableRow = { id: 11, actions: [], name: 'Enterprise deal', operational_site: null }
const SELECTION: TableSelection = { ids: [11, 22], rows: [ROW, { ...ROW, id: 22 }] }

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void; clearSelection: () => void },
    { domain: string; getBulkActions?: (selection: TableSelection) => BulkAction[] }
  >(function TableViewStub({ domain, getBulkActions }, ref) {
    useImperativeHandle(ref, () => ({ refresh: vi.fn(), clearSelection: vi.fn() }))
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

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    disabled,
    labels,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    disabled?: boolean
    labels: { triggerLabel: string; empty: string }
  }) => (
    <button type="button" aria-label={labels.triggerLabel} disabled={disabled} onClick={() => onChange(7)}>
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

function singleCard() {
  return screen.getByRole('radio', { name: 'Assign to operator' })
}

function balancedCard() {
  return screen.getByRole('radio', { name: 'Balanced split' })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  window.localStorage.clear()
  canMock.mockReset()
  canMock.mockReturnValue(true)
  assignRequestOperatorsMock.mockReset()
  assignRequestOperatorsMock.mockResolvedValue({ assigned: 2, skipped: 0 })
  fetchRequestManagementCategoriesMock.mockReset()
  fetchRequestManagementCategoriesMock.mockResolvedValue([])
  fetchAssignmentScopeMock.mockReset()
  fetchAssignmentScopeMock.mockResolvedValue(scope())
})

describe('RequestManagementTable — single-operator availability on the assignment popup', () => {
  it('disables "Assign to operator" with a readable reason when no operator covers the selection', async () => {
    fetchAssignmentScopeMock.mockResolvedValue(scope({ single_operator_available: false }))
    renderTable()

    openAssignPopup()

    await waitFor(() => expect(singleCard()).toHaveAttribute('aria-disabled', 'true'))
    expect(screen.getByText(NO_COMMON_OPERATOR_REASON)).toBeInTheDocument()
    expect(singleCard()).toHaveAttribute(
      'aria-describedby',
      screen.getByText(NO_COMMON_OPERATOR_REASON).getAttribute('id'),
    )
  })

  it('ignores a click on the disabled card: the operator step never opens', async () => {
    fetchAssignmentScopeMock.mockResolvedValue(scope({ single_operator_available: false }))
    renderTable()

    openAssignPopup()
    await waitFor(() => expect(singleCard()).toHaveAttribute('aria-disabled', 'true'))

    fireEvent.click(singleCard())

    expect(singleCard()).toHaveAttribute('aria-checked', 'false')
    expect(screen.queryByRole('button', { name: 'Operator' })).not.toBeInTheDocument()
  })

  it('keeps "Balanced split" selectable and confirmable on the very same selection', async () => {
    fetchAssignmentScopeMock.mockResolvedValue(scope({ single_operator_available: false }))
    renderTable()

    openAssignPopup()
    await waitFor(() => expect(singleCard()).toHaveAttribute('aria-disabled', 'true'))

    expect(balancedCard()).not.toHaveAttribute('aria-disabled')
    fireEvent.click(balancedCard())
    fireEvent.click(screen.getByRole('button', { name: 'Assign' }))

    await waitFor(() => expect(assignRequestOperatorsMock).toHaveBeenCalled())
    expect(assignRequestOperatorsMock.mock.calls[0][0]).toEqual({
      request_ids: [11, 22],
      mode: 'balanced',
    })
  })

  it('disables no card when one operator covers the whole selection', async () => {
    renderTable()

    openAssignPopup()

    await waitFor(() => expect(fetchAssignmentScopeMock).toHaveBeenCalledTimes(1))
    expect(singleCard()).not.toHaveAttribute('aria-disabled')
    expect(balancedCard()).not.toHaveAttribute('aria-disabled')
    expect(screen.queryByText(NO_COMMON_OPERATOR_REASON)).not.toBeInTheDocument()
  })

  it('states nothing while the scope is still resolving: unknown is not a negative answer', () => {
    fetchAssignmentScopeMock.mockReturnValue(new Promise(() => {}))
    renderTable()

    openAssignPopup()

    expect(singleCard()).not.toHaveAttribute('aria-disabled')
    expect(balancedCard()).not.toHaveAttribute('aria-disabled')
    expect(screen.queryByText(NO_COMMON_OPERATOR_REASON)).not.toBeInTheDocument()
  })

  it('states nothing when the scope fails to resolve either', async () => {
    fetchAssignmentScopeMock.mockRejectedValue(new Error('scope down'))
    renderTable()

    openAssignPopup()

    await waitFor(() => expect(fetchAssignmentScopeMock).toHaveBeenCalledTimes(1))
    expect(singleCard()).not.toHaveAttribute('aria-disabled')
    expect(screen.queryByText(NO_COMMON_OPERATOR_REASON)).not.toBeInTheDocument()
  })

  it('leaves the transfer popup untouched: no mode cards, Sede still picked by the user', async () => {
    fetchAssignmentScopeMock.mockResolvedValue(scope({ single_operator_available: false }))
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'Transfer contact' }))

    await waitFor(() =>
      expect(fetchAssignmentScopeMock).toHaveBeenCalledWith({ domain: 'quotes', ids: [11, 22] }),
    )
    expect(screen.queryByRole('radio')).not.toBeInTheDocument()
    expect(screen.queryByText(NO_COMMON_OPERATOR_REASON)).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Site' })).toBeInTheDocument()
  })
})
