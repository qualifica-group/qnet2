import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
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

const deleteWorkOrderMock = vi.fn()
vi.mock('@/features/work-orders/api', () => ({
  WORK_ORDERS_DOMAIN: 'work-orders',
  deleteWorkOrder: (...args: unknown[]) => deleteWorkOrderMock(...args),
  fetchWorkOrder: vi.fn(),
}))

const toastSuccessMock = vi.fn()
const toastErrorMock = vi.fn()
vi.mock('sonner', () => ({
  toast: {
    success: (...args: unknown[]) => toastSuccessMock(...args),
    error: (...args: unknown[]) => toastErrorMock(...args),
  },
}))

const DELETE_ACTION: TableActionDefinition = {
  key: 'delete',
  label: 'actions.delete',
  icon: 'trash',
  type: 'danger',
  confirm: true,
}

const ROW: TableRow = { id: 1, actions: ['delete'], title: 'Installazione impianto' }

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void },
    { domain: string; onAction?: (action: TableActionDefinition, row: TableRow) => void }
  >(function TableViewStub({ domain, onAction }, ref) {
    useImperativeHandle(ref, () => ({ refresh: () => {} }))
    return (
      <div role="region" aria-label={`table-${domain}`}>
        <button type="button" onClick={() => onAction?.(DELETE_ACTION, ROW)}>
          delete row
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
  deleteWorkOrderMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
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

describe('WorkOrdersTable — delete', () => {
  it('shows the success toast and refreshes on a successful delete', async () => {
    deleteWorkOrderMock.mockResolvedValue(undefined)

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(deleteWorkOrderMock).toHaveBeenCalledWith(1))
    await waitFor(() => expect(toastSuccessMock).toHaveBeenCalled())
  })

  it('shows a forbidden toast on a 403, using the backend re-authorization, not the UI gate', async () => {
    deleteWorkOrderMock.mockRejectedValue(
      new AxiosError('Forbidden', '403', undefined, undefined, {
        status: 403,
        data: { success: false, message: 'Forbidden' },
      } as never),
    )

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(toastErrorMock).toHaveBeenCalled())
    expect(toastErrorMock).not.toHaveBeenCalledWith('Forbidden')
  })

  it('shows the generic conflict message on a 409 (D-11 future guard)', async () => {
    deleteWorkOrderMock.mockRejectedValue(
      new AxiosError('Conflict', '409', undefined, undefined, {
        status: 409,
        data: { success: false, message: 'Conflict' },
      } as never),
    )

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith('Cannot delete: the work order is used elsewhere.'),
    )
  })
})
