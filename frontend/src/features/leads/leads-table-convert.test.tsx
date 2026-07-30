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
import type { ModuleFormScreenMode } from '@/features/modules/types'

/**
 * The bulk "Convert to opportunities" action (spec 0071): what the Leads
 * adapter wires into the generic `<TableView>` and what it does once the
 * popup reports a converted batch. `<TableView>` is stubbed (its own behavior
 * lives in the table-view/data-table suites), the real `ConvertLeadsDialog`
 * is rendered — the popup's own states are covered by its suite.
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
  useModuleOpenMode: () => 'modal',
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

const convertLeadsToOpportunitiesMock = vi.fn()

vi.mock('@/features/leads/api', () => ({
  fetchLead: vi.fn(),
  deleteLead: vi.fn(),
  leadDetailQueryKey: (id: number | null) => ['leads', 'detail', id],
  assignLeadOperators: vi.fn(),
  convertLeadsToOpportunities: (...args: unknown[]) => convertLeadsToOpportunitiesMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: () => null,
}))

const refreshMock = vi.fn()
const clearSelectionMock = vi.fn()

function leadRow(overrides: Partial<TableRow> = {}): TableRow {
  return { id: 1, actions: [], operational_site: null, registry: { id: 1, name: 'Acme' }, ...overrides }
}

let bulkActionSelection: TableSelection = { ids: [11, 22], rows: [leadRow({ id: 11 }), leadRow({ id: 22 })] }

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void; clearSelection: () => void },
    { domain: string; getBulkActions?: (selection: TableSelection) => BulkAction[] }
  >(function TableViewStub({ domain, getBulkActions }, ref) {
    useImperativeHandle(ref, () => ({ refresh: refreshMock, clearSelection: clearSelectionMock }))
    // The real slot renders these descriptors inside one dropdown; the stub
    // renders them as plain buttons so the action stays reachable by label.
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

const BULK_ACTION = 'Convert to opportunities'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  refreshMock.mockReset()
  clearSelectionMock.mockReset()
  convertLeadsToOpportunitiesMock.mockReset()
  bulkActionSelection = { ids: [11, 22], rows: [leadRow({ id: 11 }), leadRow({ id: 22 })] }
  vi.mocked(toast.success).mockClear()
  vi.mocked(toast.error).mockClear()
})

describe('LeadsTable — bulk conversion', () => {
  it('AC-030: offers the mass conversion to an actor who can create opportunities', () => {
    renderTable()

    expect(screen.getByRole('button', { name: BULK_ACTION })).toBeInTheDocument()
  })

  it('AC-031: hides it from an actor who cannot create opportunities', () => {
    canMock.mockImplementation((permission) => permission !== 'opportunities.create')
    renderTable()

    expect(screen.queryByRole('button', { name: BULK_ACTION })).not.toBeInTheDocument()
  })

  it('AC-031: keeps the operator assignment reachable on its own ability', () => {
    canMock.mockImplementation((permission) => permission !== 'leads.update')
    renderTable()

    expect(screen.queryByRole('button', { name: 'Assign operators' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: BULK_ACTION })).toBeInTheDocument()
  })

  it('AC-032: opens the confirm popup on the current selection', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: BULK_ACTION }))

    expect(screen.getByRole('dialog')).toHaveTextContent('2 selected lead(s)')
    expect(convertLeadsToOpportunitiesMock).not.toHaveBeenCalled()
  })

  it('AC-034/AC-035: converts the selection, then reports, refreshes and clears it', async () => {
    convertLeadsToOpportunitiesMock.mockResolvedValue({ converted: 2, opportunity_ids: [5, 6] })
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: BULK_ACTION }))
    fireEvent.click(screen.getByRole('button', { name: 'Convert' }))

    await waitFor(() =>
      expect(convertLeadsToOpportunitiesMock).toHaveBeenCalledWith({ lead_ids: [11, 22] }),
    )
    expect(toast.success).toHaveBeenCalledWith('2 opportunity(ies) created.')
    expect(refreshMock).toHaveBeenCalled()
    expect(clearSelectionMock).toHaveBeenCalled()
  })
})
