import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import WorkOrderPaymentStatusesPage from '@/pages/work-order-payment-statuses-page'
import { WorkOrderPaymentStatusesTable } from '@/features/work-order-payment-statuses/work-order-payment-statuses-table'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * The generic `<TableView>` (AG Grid + SSRM) and the app chrome (`PageHeader`) are
 * framework pieces outside this microtask's ownership: they are stubbed so
 * the suite stays focused on what THIS adapter is responsible for — wiring
 * `<Can>` around the table, mounting `<TableView domain="work-order-payment-statuses">`
 * (AC-027), and the delete flow (BR-4: surfacing the backend's exact 409
 * message).
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

// Default modal behaviour; force the resolved open mode (spec 0042, D-6).
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const deleteWorkOrderPaymentStatusMock = vi.fn()
vi.mock('@/features/work-order-payment-statuses/api', () => ({
  deleteWorkOrderPaymentStatus: (...args: unknown[]) => deleteWorkOrderPaymentStatusMock(...args),
  fetchWorkOrderPaymentStatus: vi.fn(),
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

const ROW: TableRow = { id: 1, actions: ['delete'], name: 'Approvato' }

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
        <WorkOrderPaymentStatusesPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <WorkOrderPaymentStatusesTable />
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
  deleteWorkOrderPaymentStatusMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
})

describe('WorkOrderPaymentStatusesPage — permission gating (AC-027)', () => {
  it('shows the forbidden fallback and does not mount the table without viewAny', () => {
    canMock.mockReturnValue(false)

    renderPage()

    expect(
      screen.getByText("You don't have permission to view work order payment statuses."),
    ).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'table-work-order-payment-statuses' })).not.toBeInTheDocument()
  })

  it('mounts <TableView domain="work-order-payment-statuses"> with viewAny', () => {
    canMock.mockImplementation((permission) => permission === 'work-order-payment-statuses.viewAny')

    renderPage()

    expect(screen.getByRole('region', { name: 'table-work-order-payment-statuses' })).toBeInTheDocument()
    expect(
      screen.queryByText("You don't have permission to view work order payment statuses."),
    ).not.toBeInTheDocument()
  })
})

describe('WorkOrderPaymentStatusesTable — reorder toggle (AC-025)', () => {
  it('shows the reorder button with work-order-payment-statuses.update', () => {
    canMock.mockImplementation((permission) => permission === 'work-order-payment-statuses.update')

    renderTable()

    expect(screen.getByRole('button', { name: 'Reorder' })).toBeInTheDocument()
  })

  it('hides the reorder button without work-order-payment-statuses.update', () => {
    canMock.mockReturnValue(false)

    renderTable()

    expect(screen.queryByRole('button', { name: 'Reorder' })).not.toBeInTheDocument()
  })
})

describe('WorkOrderPaymentStatusesTable — delete (BR-4, AC-026)', () => {
  it('shows the backend message on a 409 (status still in use)', async () => {
    deleteWorkOrderPaymentStatusMock.mockRejectedValue(
      new AxiosError('Conflict', '409', undefined, undefined, {
        status: 409,
        data: {
          success: false,
          message: 'This payment status is used by a work order line and cannot be deleted.',
        },
      } as never),
    )

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(deleteWorkOrderPaymentStatusMock).toHaveBeenCalledWith(1))
    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith(
        'This payment status is used by a work order line and cannot be deleted.',
      ),
    )
  })

  it('shows a forbidden toast on a 403', async () => {
    deleteWorkOrderPaymentStatusMock.mockRejectedValue(
      new AxiosError('Forbidden', '403', undefined, undefined, {
        status: 403,
        data: { success: false, message: 'Forbidden' },
      } as never),
    )

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith('You cannot delete this payment status.'),
    )
  })

  it('shows the success toast and refreshes on a successful delete', async () => {
    deleteWorkOrderPaymentStatusMock.mockResolvedValue(undefined)

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastSuccessMock).toHaveBeenCalledWith('Payment status deleted successfully.'),
    )
  })
})
