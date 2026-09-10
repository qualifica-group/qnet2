import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { LeadsTable } from '@/features/leads/leads-table'
import type { BulkAction, TableSelection } from '@/features/table/use-bulk-actions-slot'
import type { TableRow } from '@/features/table/types'
import type { ModuleFormScreenMode, OpenMode } from '@/features/modules/types'

/**
 * The bulk "Assegna operatori" action (spec 0048 AC-040/AC-041): the row
 * checkbox predicate and the extra bulk-action slot the Leads adapter wires
 * into the generic `<TableView>`. `<TableView>` itself is stubbed (its own
 * behavior is covered by `table-view`/`data-table` suites); this suite is
 * about what `LeadsTable` does with `isRowSelectable`/`getBulkActions`
 * and the real `AssignOperatorsDialog` (only its `AsyncPaginatedSelect`
 * pickers are stubbed, mirroring `assign-operators-dialog.test.tsx`).
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

let opportunitiesOpenMode: OpenMode = 'modal'
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: (domain: string) => (domain === 'opportunities' ? opportunitiesOpenMode : 'modal'),
}))

vi.mock('@/features/opportunities/opportunity-screens', () => ({
  moduleScreen: {
    domain: 'opportunities',
    basePath: '/opportunities',
    defaultMode: 'modal',
    labelKey: 'navigation.opportunities',
    DetailScreen: () => null,
    FormScreen: ({ mode }: { mode: ModuleFormScreenMode }) => <div>{`opportunity-form-${mode.type}`}</div>,
  },
}))

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
  return { ...actual, useNavigate: () => vi.fn() }
})

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

vi.mock('@/features/leads/lead-form', () => ({
  LeadForm: () => null,
}))

const fetchLeadMock = vi.fn()
const deleteLeadMock = vi.fn()
const assignLeadOperatorsMock = vi.fn()

vi.mock('@/features/leads/api', () => ({
  fetchLead: () => fetchLeadMock(),
  deleteLead: (...args: unknown[]) => deleteLeadMock(...args),
  leadDetailQueryKey: (id: number | null) => ['leads', 'detail', id],
  assignLeadOperators: (...args: unknown[]) => assignLeadOperatorsMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const refreshMock = vi.fn()
const clearSelectionMock = vi.fn()
let capturedIsRowSelectable: ((row: TableRow) => boolean) | undefined
let capturedGetBulkActions: ((selection: TableSelection) => BulkAction[]) | undefined

/** Row fixture used to drive `getBulkActions`; each test sets its own before rendering. */
function leadRow(overrides: Partial<TableRow> = {}): TableRow {
  return { id: 1, actions: [], operational_site: null, ...overrides }
}

/** Selection fed into `getBulkActions` (ids + row data — AC-031). Reset per test. */
let bulkActionSelection: TableSelection = { ids: [11, 22], rows: [leadRow({ id: 11 }), leadRow({ id: 22 })] }

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void; clearSelection: () => void },
    {
      domain: string
      isRowSelectable?: (row: TableRow) => boolean
      getBulkActions?: (selection: TableSelection) => BulkAction[]
    }
  >(function TableViewStub({ domain, isRowSelectable, getBulkActions }, ref) {
    useImperativeHandle(ref, () => ({ refresh: refreshMock, clearSelection: clearSelectionMock }))
    capturedIsRowSelectable = isRowSelectable
    capturedGetBulkActions = getBulkActions
    // The real slot renders these descriptors inside one dropdown; the stub
    // renders them as plain buttons so the assign-flow assertions still reach
    // the action by its accessible label.
    return (
      <div role="region" aria-label={`table-${domain}`}>
        {getBulkActions?.(bulkActionSelection).map((action) => (
          <button key={action.key} type="button" onClick={() => action.onSelect()}>
            {action.label}
          </button>
        ))}
      </div>
    )
  }),
}))

/**
 * Mirrors `assign-operators-dialog.test.tsx`'s stub: a plain button per
 * picker, surfacing both the `params` the scope drives (spec 0110/0113) and
 * whether the picker is usable at all (AC-028/AC-034).
 */
const OPERATOR_PICK_ID = 42
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    params,
    disabled,
    labels,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    params?: Record<string, string | number | string[] | number[]>
    disabled?: boolean
    labels: { triggerLabel: string }
  }) => (
    <button
      type="button"
      aria-label={labels.triggerLabel}
      data-params={params ? JSON.stringify(params) : ''}
      disabled={disabled}
      onClick={() => onChange(OPERATOR_PICK_ID)}
    >
      {value ?? 'none'}
    </button>
  ),
}))

// `useAssignmentScope` (spec 0110 AC-041, spec 0113) resolves the selection's
// categories, shared Sede and campaigns through this endpoint; every test
// drives it explicitly.
const RESOLVED_SITE_ID = 7
const fetchAssignmentScopeMock = vi.fn()
vi.mock('@/features/assignment/api', () => ({
  fetchAssignmentScope: (...args: unknown[]) => fetchAssignmentScopeMock(...args),
}))

/** The `POST /assignment/selection-scope` envelope, defaulted to a resolved single-campaign selection. */
function scope(overrides: Partial<{
  product_category_ids: number[]
  operational_site_id: number | null
  campaign_ids: number[]
}> = {}) {
  return {
    product_category_ids: [],
    operational_site_id: RESOLVED_SITE_ID,
    campaign_ids: [3],
    ...overrides,
  }
}

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <LeadsTable />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

// The shared popup is mode-first: the Sede/Operatore pickers stay hidden until
// a mode radio is chosen, and a single "Assign" action confirms the pick.
function openPopup() {
  fireEvent.click(screen.getByRole('button', { name: 'Assign operators' }))
}

/** Picks the balanced mode, which since spec 0113 needs nothing else. */
function openAndPickBalanced() {
  openPopup()
  fireEvent.click(screen.getByRole('radio', { name: 'Balanced split' }))
}

/** Full balanced flow: open, pick the mode, confirm — there is no Sede step. */
function assignBalanced() {
  openAndPickBalanced()
  fireEvent.click(screen.getByRole('button', { name: 'Assign' }))
}

/** Opens the popup on the single-operator mode, where the Operatore picker shows. */
function openAndPickSingle() {
  openPopup()
  fireEvent.click(screen.getByRole('radio', { name: 'Assign to operator' }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  opportunitiesOpenMode = 'modal'
  refreshMock.mockReset()
  clearSelectionMock.mockReset()
  fetchLeadMock.mockReset()
  deleteLeadMock.mockReset()
  assignLeadOperatorsMock.mockReset()
  fetchAssignmentScopeMock.mockReset()
  fetchAssignmentScopeMock.mockResolvedValue(scope())
  capturedIsRowSelectable = undefined
  capturedGetBulkActions = undefined
  bulkActionSelection = { ids: [11, 22], rows: [leadRow({ id: 11 }), leadRow({ id: 22 })] }
  vi.mocked(toast.success).mockClear()
  vi.mocked(toast.error).mockClear()
})

describe('LeadsTable — row selectability', () => {
  // Directive 2026-07-21: an already-associated lead must stay checkable so it
  // can still be bulk-deleted. The adapter therefore no longer restricts row
  // selectability (reverting AC-040's `isRowSelectable` gate).
  it('does not restrict which leads can be selected', () => {
    renderTable()

    expect(capturedIsRowSelectable).toBeUndefined()
  })
})

describe('LeadsTable — bulk "Assign operators" button (AC-041)', () => {
  // Spec 0071 added a second, independently gated bulk action (the mass
  // conversion): losing leads.update no longer empties the whole slot, it
  // only drops this entry from it.
  it('is wired only with leads.update', () => {
    canMock.mockImplementation((permission) => permission !== 'leads.update')
    renderTable()

    expect(capturedGetBulkActions?.(bulkActionSelection).map((action) => action.key)).toEqual([
      'convert-to-opportunities',
    ])
    expect(screen.queryByRole('button', { name: 'Assign operators' })).not.toBeInTheDocument()
  })

  it('leaves the slot unwired when the actor has neither bulk ability', () => {
    canMock.mockImplementation(
      (permission) => permission !== 'leads.update' && permission !== 'opportunities.create',
    )
    renderTable()

    expect(capturedGetBulkActions).toBeUndefined()
  })

  it('renders the button and opens the shared popup', () => {
    renderTable()

    expect(screen.getByRole('button', { name: 'Assign operators' })).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Assign operators' }))

    expect(screen.getByText('2 lead(s) selected.')).toBeInTheDocument()
  })

  it('assigns, refreshes the grid, clears the selection and toasts on success', async () => {
    assignLeadOperatorsMock.mockResolvedValue({ assigned: 2 })
    renderTable()

    assignBalanced()

    await waitFor(() =>
      expect(assignLeadOperatorsMock).toHaveBeenCalledWith({
        lead_ids: [11, 22],
        mode: 'balanced',
      }),
    )
    await waitFor(() => expect(refreshMock).toHaveBeenCalled())
    expect(clearSelectionMock).toHaveBeenCalled()
    expect(toast.success).toHaveBeenCalledWith('Operators assigned to 2 lead(s).')
  })

  // Spec 0113 removed the "this Sede has no operators" 422, so a 422 here no
  // longer has a known cause: the toast must stay generic rather than blame the
  // Sede. Previously this asserted the opposite - requirement change, declared.
  it('toasts the generic message on a balanced-mode 422 and keeps the dialog open', async () => {
    assignLeadOperatorsMock.mockRejectedValue(
      new AxiosError('failed', '422', undefined, undefined, { status: 422 } as never),
    )
    renderTable()

    assignBalanced()

    await waitFor(() => expect(assignLeadOperatorsMock).toHaveBeenCalledTimes(1))
    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith('Unable to assign the operators. Please try again.'),
    )
    expect(screen.getByText('2 lead(s) selected.')).toBeInTheDocument()
    expect(refreshMock).not.toHaveBeenCalled()
    expect(clearSelectionMock).not.toHaveBeenCalled()
  })

  it('toasts the generic error message on any other failure', async () => {
    assignLeadOperatorsMock.mockRejectedValue(new Error('network down'))
    renderTable()

    assignBalanced()

    await waitFor(() => expect(assignLeadOperatorsMock).toHaveBeenCalledTimes(1))
    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith('Unable to assign the operators. Please try again.'),
    )
  })
})

describe('LeadsTable — no Sede field on the assignment popup (spec 0113 AC-026)', () => {
  it('never renders the Sede picker, in either mode', () => {
    renderTable()

    openAndPickBalanced()
    expect(screen.queryByRole('button', { name: 'Site' })).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('radio', { name: 'Assign to operator' }))
    expect(screen.queryByRole('button', { name: 'Site' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Operator' })).toBeInTheDocument()
  })

  it('confirms a balanced split on the mode alone', () => {
    renderTable()

    openAndPickBalanced()

    expect(screen.getByRole('button', { name: 'Assign' })).toBeEnabled()
  })

  // Spec 0113 rev.2 (user directive 2026-09-10): the Lead table now follows the
  // import wizard's rule. This block previously asserted the opposite — that no
  // card is ever disabled here — which was decision D-5 before it was reversed.
  it('disables "Assign to operator" with a reason when the selection spans several campaigns', async () => {
    fetchAssignmentScopeMock.mockResolvedValue(scope({ campaign_ids: [3, 8] }))
    renderTable()

    openPopup()

    await waitFor(() => expect(fetchAssignmentScopeMock).toHaveBeenCalledTimes(1))
    await waitFor(() =>
      expect(screen.getByRole('radio', { name: 'Assign to operator' })).toHaveAttribute(
        'aria-disabled',
        'true',
      ),
    )
    expect(
      screen.getByText('Unavailable: the selection spans records from different campaigns.'),
    ).toBeInTheDocument()
    // Balanced works lead by lead, so mixed campaigns never block it.
    expect(screen.getByRole('radio', { name: 'Balanced split' })).not.toHaveAttribute(
      'aria-disabled',
    )
  })

  it('disables no card when the selection shares one campaign', async () => {
    fetchAssignmentScopeMock.mockResolvedValue(scope({ campaign_ids: [3] }))
    renderTable()

    openPopup()

    await waitFor(() => expect(fetchAssignmentScopeMock).toHaveBeenCalledTimes(1))
    expect(screen.getByRole('radio', { name: 'Assign to operator' })).not.toHaveAttribute(
      'aria-disabled',
    )
    expect(screen.getByRole('radio', { name: 'Balanced split' })).not.toHaveAttribute(
      'aria-disabled',
    )
  })

  it('disables no card while the scope is still resolving, claiming no mixed campaigns', async () => {
    fetchAssignmentScopeMock.mockReturnValue(new Promise(() => {}))
    renderTable()

    openPopup()

    expect(screen.getByRole('radio', { name: 'Assign to operator' })).not.toHaveAttribute(
      'aria-disabled',
    )
    expect(
      screen.queryByText('Unavailable: the selection spans records from different campaigns.'),
    ).not.toBeInTheDocument()
  })
})

/**
 * Spec 0110 AC-041/AC-044 extended by spec 0113 AC-028/AC-034: the Lead table
 * resolves the scope of its own selection — categories AND the Sede its
 * campaigns share — hands it to the shared popup, and its feedback names the
 * leads a balanced split left without a competent operator.
 */
describe('LeadsTable — scope-aware assignment (spec 0110/0113)', () => {
  it('resolves the scope of the selected leads only once the popup is open', async () => {
    renderTable()

    expect(fetchAssignmentScopeMock).not.toHaveBeenCalled()

    openPopup()

    await waitFor(() =>
      expect(fetchAssignmentScopeMock).toHaveBeenCalledWith({ domain: 'leads', ids: [11, 22] }),
    )
  })

  it('narrows the operator picker to the derived Sede and the competent users (AC-028)', async () => {
    fetchAssignmentScopeMock.mockResolvedValue(scope({ product_category_ids: [4, 9] }))
    renderTable()

    openAndPickSingle()

    await waitFor(() =>
      expect(screen.getByRole('button', { name: 'Operator' })).toHaveAttribute(
        'data-params',
        JSON.stringify({
          operational_site_id: RESOLVED_SITE_ID,
          competence_category_ids: [4, 9],
        }),
      ),
    )
    expect(screen.getByRole('button', { name: 'Operator' })).toBeEnabled()
  })

  it('applies no competence filter when the selection expresses no requirement', async () => {
    renderTable()

    openAndPickSingle()

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

    openAndPickSingle()

    const picker = screen.getByRole('button', { name: 'Operator' })
    expect(picker).toBeDisabled()
    expect(picker).toHaveAttribute('data-params', '')
  })

  it('keeps the operator picker disabled when the scope fails to resolve (AC-034)', async () => {
    fetchAssignmentScopeMock.mockRejectedValue(new Error('scope down'))
    renderTable()

    openAndPickSingle()

    await waitFor(() => expect(fetchAssignmentScopeMock).toHaveBeenCalledTimes(1))
    const picker = screen.getByRole('button', { name: 'Operator' })
    expect(picker).toBeDisabled()
    expect(picker).toHaveAttribute('data-params', '')
  })

  it('reports the leads left without a competent operator (AC-044)', async () => {
    assignLeadOperatorsMock.mockResolvedValue({ assigned: 1, skipped: 1 })
    renderTable()

    assignBalanced()

    await waitFor(() =>
      expect(toast.success).toHaveBeenCalledWith(
        'Operators assigned to 1 lead(s). 1 left without a competent operator.',
      ),
    )
  })

  it('keeps the plain feedback when nothing was skipped', async () => {
    assignLeadOperatorsMock.mockResolvedValue({ assigned: 2, skipped: 0 })
    renderTable()

    assignBalanced()

    await waitFor(() =>
      expect(toast.success).toHaveBeenCalledWith('Operators assigned to 2 lead(s).'),
    )
  })
})
