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

/** Reveals the Sede picker by choosing the balanced mode (needs no operator). */
function openAndPickBalanced() {
  openPopup()
  fireEvent.click(screen.getByRole('radio', { name: 'Balanced split' }))
}

/** Full balanced flow: open, pick mode, pick the Sede, then confirm. */
function assignBalanced() {
  openAndPickBalanced()
  fireEvent.click(screen.getByRole('button', { name: 'Site' }))
  fireEvent.click(screen.getByRole('button', { name: 'Assign' }))
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
        operational_site_id: SITE_PICK_ID,
        mode: 'balanced',
      }),
    )
    await waitFor(() => expect(refreshMock).toHaveBeenCalled())
    expect(clearSelectionMock).toHaveBeenCalled()
    expect(toast.success).toHaveBeenCalledWith('Operators assigned to 2 lead(s).')
  })

  it('toasts the noOperators message on a balanced-mode 422 and keeps the dialog open', async () => {
    assignLeadOperatorsMock.mockRejectedValue(
      new AxiosError('failed', '422', undefined, undefined, { status: 422 } as never),
    )
    renderTable()

    assignBalanced()

    await waitFor(() => expect(assignLeadOperatorsMock).toHaveBeenCalledTimes(1))
    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith('No operator found for the selected Site.'),
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

describe('LeadsTable — popup Sede precompile (AC-031)', () => {
  it('precompiles the Sede when every selected row shares one', () => {
    bulkActionSelection = {
      ids: [11, 22],
      rows: [
        leadRow({ id: 11, operational_site: { id: 5, label: 'Warehouse A' } }),
        leadRow({ id: 22, operational_site: { id: 5, label: 'Warehouse A' } }),
      ],
    }
    renderTable()

    openAndPickBalanced()

    expect(screen.getByRole('button', { name: 'Site' })).toHaveTextContent('5')
  })

  it('leaves the Sede unset when the selected rows have different sites', () => {
    bulkActionSelection = {
      ids: [11, 22],
      rows: [
        leadRow({ id: 11, operational_site: { id: 5, label: 'Warehouse A' } }),
        leadRow({ id: 22, operational_site: { id: 8, label: 'Warehouse B' } }),
      ],
    }
    renderTable()

    openAndPickBalanced()

    expect(screen.getByRole('button', { name: 'Site' })).toHaveTextContent('none')
  })

  it('leaves the Sede unset when any selected row has no site', () => {
    bulkActionSelection = {
      ids: [11, 22],
      rows: [
        leadRow({ id: 11, operational_site: { id: 5, label: 'Warehouse A' } }),
        leadRow({ id: 22, operational_site: null }),
      ],
    }
    renderTable()

    openAndPickBalanced()

    expect(screen.getByRole('button', { name: 'Site' })).toHaveTextContent('none')
  })
})
