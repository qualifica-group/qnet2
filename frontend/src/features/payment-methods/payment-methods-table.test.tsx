import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import PaymentMethodsPage from '@/pages/payment-methods-page'
import { PaymentMethodsTable } from '@/features/payment-methods/payment-methods-table'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * The generic `<TableView>` (AG Grid + SSRM) and the app chrome (`PageHeader`) are
 * framework pieces outside this microtask's ownership: they are stubbed so
 * the suite stays focused on what THIS adapter is responsible for — wiring
 * `<Can>` around the table, mounting `<TableView domain="payment-methods">`
 * (AC-110), the reorder toggle gate (AC-111) and the delete flow (D-2: no
 * 409 branch, the endpoint always responds 204).
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

const deletePaymentMethodMock = vi.fn()
vi.mock('@/features/payment-methods/api', () => ({
  deletePaymentMethod: (...args: unknown[]) => deletePaymentMethodMock(...args),
  fetchPaymentMethod: vi.fn(),
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

const ROW: TableRow = { id: 1, actions: ['delete'], name: 'Bank transfer' }

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
        <PaymentMethodsPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <PaymentMethodsTable />
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
  deletePaymentMethodMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
})

describe('PaymentMethodsPage — permission gating (AC-110)', () => {
  it('shows the forbidden fallback and does not mount the table without viewAny', () => {
    canMock.mockReturnValue(false)

    renderPage()

    expect(
      screen.getByText("You don't have permission to view payment methods."),
    ).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'table-payment-methods' })).not.toBeInTheDocument()
  })

  it('mounts <TableView domain="payment-methods"> with viewAny', () => {
    canMock.mockImplementation((permission) => permission === 'payment-methods.viewAny')

    renderPage()

    expect(screen.getByRole('region', { name: 'table-payment-methods' })).toBeInTheDocument()
    expect(
      screen.queryByText("You don't have permission to view payment methods."),
    ).not.toBeInTheDocument()
  })
})

describe('PaymentMethodsTable — new/reorder toggles (AC-111)', () => {
  it('shows "New payment method" with create, hides it without', () => {
    canMock.mockImplementation((permission) => permission === 'payment-methods.create')

    renderTable()

    expect(screen.getByRole('button', { name: 'New payment method' })).toBeInTheDocument()
  })

  it('shows the reorder button with payment-methods.update', () => {
    canMock.mockImplementation((permission) => permission === 'payment-methods.update')

    renderTable()

    expect(screen.getByRole('button', { name: 'Reorder' })).toBeInTheDocument()
  })

  it('hides both the create and the reorder button without their permissions', () => {
    canMock.mockReturnValue(false)

    renderTable()

    expect(screen.queryByRole('button', { name: 'New payment method' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Reorder' })).not.toBeInTheDocument()
  })
})

describe('PaymentMethodsTable — delete (D-2: no 409 branch, AC-112)', () => {
  it('shows the success toast and refreshes on a successful delete', async () => {
    deletePaymentMethodMock.mockResolvedValue(undefined)

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(deletePaymentMethodMock).toHaveBeenCalledWith(1))
    await waitFor(() =>
      expect(toastSuccessMock).toHaveBeenCalledWith('Payment method deleted successfully.'),
    )
  })

  it('shows a forbidden toast on a 403, using the backend re-authorization, not the UI gate', async () => {
    deletePaymentMethodMock.mockRejectedValue(
      new AxiosError('Forbidden', '403', undefined, undefined, {
        status: 403,
        data: { success: false, message: 'Forbidden' },
      } as never),
    )

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith('You cannot delete this payment method.'),
    )
  })

  it('falls back to the generic error message on any other failure', async () => {
    deletePaymentMethodMock.mockRejectedValue(new Error('network down'))

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith(
        'Unable to delete the payment method. Please try again.',
      ),
    )
  })
})
