import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { LeadsTable } from '@/features/leads/leads-table'
import type { BulkAction, TableSelection } from '@/features/table/use-bulk-actions-slot'
import type { TableRow } from '@/features/table/types'
import type { ModuleFormScreenMode, OpenMode } from '@/features/modules/types'

/**
 * Spec 0110 AC-041/AC-044 extended by spec 0113 AC-028/AC-034: the Lead table
 * resolves the scope of its own selection — categories AND the Sede its
 * campaigns share — hands it to the shared popup, and its feedback names the
 * leads a balanced split left without a competent operator. Split out of
 * `leads-table-assign.test.tsx` (spec 0168) to keep both files under the size
 * thresholds; the mock/fixture setup below mirrors it verbatim.
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
  >(function TableViewStub({ domain, getBulkActions }, ref) {
    useImperativeHandle(ref, () => ({ refresh: refreshMock, clearSelection: clearSelectionMock }))
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

/**
 * The `POST /assignment/selection-scope` envelope, defaulted to a resolved
 * single-campaign selection. `balanced_groups` defaults to one Sede with the
 * same `OPERATOR_PICK_ID` the Operatore picker stub selects (spec 0168), so
 * the balanced flow's default "everyone selected" always resolves to a
 * non-empty `operators_by_site`.
 */
function scope(overrides: Partial<{
  product_category_ids: number[]
  operational_site_id: number | null
  campaign_ids: number[]
  balanced_groups: ReturnType<typeof balancedGroup>[]
  balanced_unassignable_count: number
}> = {}) {
  return {
    product_category_ids: [],
    operational_site_id: RESOLVED_SITE_ID,
    campaign_ids: [3],
    balanced_groups: [balancedGroup()],
    balanced_unassignable_count: 0,
    ...overrides,
  }
}

/** One `balanced_groups` entry (spec 0168), the Sede the default `scope()` resolves to. */
function balancedGroup() {
  return {
    operational_site_id: RESOLVED_SITE_ID,
    operational_site_label: 'Napoli',
    record_count: 2,
    operators: [{ id: OPERATOR_PICK_ID, label: 'Mario Rossi', avatar_url: null, load: 0 }],
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

/**
 * Full balanced flow: open, pick the mode, confirm — there is no Sede step.
 * Confirm only enables once the scope (incl. `balanced_groups`, spec 0168)
 * resolves and leaves at least one operator selected (the default), so this
 * awaits it rather than clicking a still-disabled button.
 */
async function assignBalanced() {
  openAndPickBalanced()
  await waitFor(() => expect(screen.getByRole('button', { name: 'Assign' })).toBeEnabled())
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
  bulkActionSelection = { ids: [11, 22], rows: [leadRow({ id: 11 }), leadRow({ id: 22 })] }
  vi.mocked(toast.success).mockClear()
  vi.mocked(toast.error).mockClear()
})

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

    await assignBalanced()

    await waitFor(() =>
      expect(toast.success).toHaveBeenCalledWith(
        'Operators assigned to 1 lead(s). 1 left without a competent operator.',
      ),
    )
  })

  it('keeps the plain feedback when nothing was skipped', async () => {
    assignLeadOperatorsMock.mockResolvedValue({ assigned: 2, skipped: 0 })
    renderTable()

    await assignBalanced()

    await waitFor(() =>
      expect(toast.success).toHaveBeenCalledWith('Operators assigned to 2 lead(s).'),
    )
  })
})
