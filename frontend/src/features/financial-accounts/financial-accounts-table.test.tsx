import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import FinancialAccountsPage from '@/pages/financial-accounts-page'
import { FinancialAccountsTable } from '@/features/financial-accounts/financial-accounts-table'
import { financialAccountColumnRenderers } from '@/features/financial-accounts/column-renderers'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

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

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const deleteMock = vi.fn()
vi.mock('@/features/financial-accounts/api', () => ({
  deleteFinancialAccount: (...args: unknown[]) => deleteMock(...args),
  fetchFinancialAccount: vi.fn(),
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

const ROW: TableRow = { id: 1, actions: ['delete'], name: 'Intesa' }

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

function renderWithProviders(ui: ReactNode) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>{ui}</MemoryRouter>
    </QueryClientProvider>,
  )
}

function axiosError(status: number, message: string) {
  return new AxiosError('error', String(status), undefined, undefined, {
    status,
    data: { success: false, message },
  } as never)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  deleteMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
})

describe('FinancialAccountsPage: permission gating (AC-024)', () => {
  it('shows the forbidden fallback without viewAny', () => {
    canMock.mockReturnValue(false)
    renderWithProviders(<FinancialAccountsPage />)

    expect(screen.getByText("You don't have permission to view financial accounts.")).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'table-financial-accounts' })).not.toBeInTheDocument()
  })

  it('mounts the financial-accounts table with viewAny', () => {
    canMock.mockImplementation((permission) => permission === 'financial-accounts.viewAny')
    renderWithProviders(<FinancialAccountsPage />)

    expect(screen.getByRole('region', { name: 'table-financial-accounts' })).toBeInTheDocument()
  })
})

describe('FinancialAccountsTable: new button and delete (AC-024)', () => {
  it('shows "New account" only with create', () => {
    canMock.mockImplementation((permission) => permission === 'financial-accounts.create')
    const { unmount } = renderWithProviders(<FinancialAccountsTable />)
    expect(screen.getByRole('button', { name: 'New account' })).toBeInTheDocument()
    unmount()

    canMock.mockReturnValue(false)
    renderWithProviders(<FinancialAccountsTable />)
    expect(screen.queryByRole('button', { name: 'New account' })).not.toBeInTheDocument()
  })

  it('toasts success after a delete', async () => {
    deleteMock.mockResolvedValue(undefined)
    renderWithProviders(<FinancialAccountsTable />)
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(deleteMock).toHaveBeenCalledWith(1))
    await waitFor(() =>
      expect(toastSuccessMock).toHaveBeenCalledWith('Financial account deleted successfully.'),
    )
  })

  it('shows the backend message on a 409', async () => {
    const message = 'This account has linked cards and cannot be deleted.'
    deleteMock.mockRejectedValue(axiosError(409, message))
    renderWithProviders(<FinancialAccountsTable />)
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(toastErrorMock).toHaveBeenCalledWith(message))
  })

  it('shows a forbidden toast on a 403 and a generic one otherwise', async () => {
    deleteMock.mockRejectedValueOnce(axiosError(403, 'Forbidden'))
    renderWithProviders(<FinancialAccountsTable />)
    screen.getByRole('button', { name: 'delete row' }).click()
    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith('You cannot delete this financial account.'),
    )

    deleteMock.mockRejectedValueOnce(new Error('network down'))
    screen.getByRole('button', { name: 'delete row' }).click()
    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith(
        'Unable to delete the financial account. Please try again.',
      ),
    )
  })
})

describe('financialAccountColumnRenderers (AC-024)', () => {
  it('renders the type column as its translated label', () => {
    const renderType = financialAccountColumnRenderers.type
    render(<>{renderType({ value: 'bank_account' } as never)}</>)

    expect(screen.getByText('Bank account')).toBeInTheDocument()
  })
})
