import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import WorkOrdersPage from '@/pages/work-orders-page'
import { WorkOrdersTable } from '@/features/work-orders/work-orders-table'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * The generic `<TableView>` (AG Grid + SSRM) and the app chrome (`PageHeader`)
 * are outside this adapter's ownership: they are stubbed so the suite stays
 * focused on what THIS adapter is responsible for — the page's `viewAny`
 * gate (AC-070), the "New" button gate, mounting
 * `<TableView domain="work-orders">`, and the delete flow's toast mapping.
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

// Force the resolved open mode so the delete-flow tests do not depend on the
// user's preference resolution (spec 0042, D-6).
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

// The "Responsabili e Partecipanti" dialog (spec 0405) reads the record.
vi.mock('@/features/work-orders/api', () => ({
  WORK_ORDERS_DOMAIN: 'work-orders',
  fetchWorkOrder: () => new Promise(() => {}),
  deleteWorkOrder: vi.fn(),
  workOrderDetailQueryKey: (id: number) => ['work-orders', 'detail', id],
}))

const TEAM_ACTION: TableActionDefinition = {
  key: 'team',
  label: 'workOrders.actions.team.label',
  icon: 'contact',
  type: 'action',
  confirm: false,
}

const ROW: TableRow = { id: 1, actions: ['team'], title: 'Installazione impianto' }

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void },
    { domain: string; scope?: { attributeColumnsFromFilters?: boolean }; onAction?: (action: TableActionDefinition, row: TableRow) => void }
  >(function TableViewStub({ domain, scope, onAction }, ref) {
    useImperativeHandle(ref, () => ({ refresh: () => {} }))
    return (
      <div role="region" aria-label={`table-${domain}`}>
        <p>{scope?.attributeColumnsFromFilters ? 'attr-columns-from-filters' : 'no-attr-columns'}</p>
        <button type="button" onClick={() => onAction?.(TEAM_ACTION, ROW)}>
          team row
        </button>
      </div>
    )
  }),
}))

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <WorkOrdersPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <WorkOrdersTable />
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
})

describe('WorkOrdersPage — permission gating (AC-070)', () => {
  it('shows the forbidden fallback and does not mount the grid without viewAny', () => {
    canMock.mockReturnValue(false)

    renderPage()

    expect(screen.queryByRole('region', { name: 'table-work-orders' })).not.toBeInTheDocument()
    expect(screen.getByText(/permission to view work orders/i)).toBeInTheDocument()
  })

  it('mounts <TableView domain="work-orders"> with viewAny', () => {
    canMock.mockImplementation((permission) => permission === 'work-orders.viewAny')

    renderPage()

    expect(screen.getByRole('region', { name: 'table-work-orders' })).toBeInTheDocument()
    expect(screen.getByText('attr-columns-from-filters')).toBeInTheDocument()
  })
})

describe('WorkOrdersTable — no create affordance', () => {
  // Requisito cambiato (spec 0093 D-13): la creazione NON e' disponibile dalla
  // pagina elenco. Il test asserisce l'assenza anche CON il permesso, cosi' il
  // bottone non puo' rientrare per errore scambiato per una dimenticanza.
  it('never renders a create button, even with work-orders.create', () => {
    canMock.mockReturnValue(true)

    renderTable()

    expect(screen.queryByRole('button', { name: /new work order/i })).not.toBeInTheDocument()
  })
})

describe('WorkOrdersTable — legacy row actions (spec 0405)', () => {
  it('opens the "Responsabili e Partecipanti" dialog of the row', async () => {
    renderTable()
    fireEvent.click(screen.getByRole('button', { name: 'team row' }))

    expect(await screen.findByRole('heading', { name: 'Supervisors and Participants' })).toBeInTheDocument()
  })
})
