import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import ContractTypesPage from '@/features/contract-types/contract-types-page'
import { ContractTypesTable } from '@/features/contract-types/contract-types-table'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * The generic `<TableView>` and the app chrome are stubbed so the suite stays on what this
 * adapter owns: `<Can>` gating, mounting `<TableView domain="contract-types">` and the delete
 * flow, including the 409 "in use" guard distinct from 403/generic.
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

vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const deleteMock = vi.fn()
vi.mock('@/features/contract-types/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/contract-types/api')>()),
  deleteContractType: (...args: unknown[]) => deleteMock(...args),
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

const ROW: TableRow = { id: 1, actions: ['delete'], name: 'Tempo determinato' }

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

function renderWith(node: ReactNode) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>{node}</MemoryRouter>
    </QueryClientProvider>,
  )
}

function axiosError(status: number, data: Record<string, unknown> = { success: false, message: 'server message' }) {
  return new AxiosError('error', String(status), undefined, undefined, {
    status,
    data,
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

describe('ContractTypesPage - permission gating', () => {
  it('shows the forbidden fallback and does not mount the table without view', () => {
    canMock.mockReturnValue(false)

    renderWith(<ContractTypesPage />)

    expect(screen.getByText('You do not have permission to view contract types.')).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'table-contract-types' })).not.toBeInTheDocument()
  })

  it('mounts <TableView domain="contract-types"> with view', () => {
    canMock.mockImplementation((permission) => permission === 'contract-types.view')

    renderWith(<ContractTypesPage />)

    expect(screen.getByRole('region', { name: 'table-contract-types' })).toBeInTheDocument()
  })
})

describe('ContractTypesTable - new button', () => {
  it('shows "New contract type" with create and hides it without', () => {
    canMock.mockImplementation((permission) => permission === 'contract-types.create')
    const { unmount } = renderWith(<ContractTypesTable />)
    expect(screen.getByRole('button', { name: 'New contract type' })).toBeInTheDocument()
    unmount()

    canMock.mockReturnValue(false)
    renderWith(<ContractTypesTable />)
    expect(screen.queryByRole('button', { name: 'New contract type' })).not.toBeInTheDocument()
  })
})

describe('ContractTypesTable - delete', () => {
  it('shows the success toast on a successful delete', async () => {
    deleteMock.mockResolvedValue(undefined)

    renderWith(<ContractTypesTable />)
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(deleteMock).toHaveBeenCalledWith(1))
    await waitFor(() => expect(toastSuccessMock).toHaveBeenCalledWith('Contract type deleted successfully.'))
  })

  it('shows a forbidden toast on a 403', async () => {
    deleteMock.mockRejectedValue(axiosError(403, { success: false, message: 'Forbidden', authorization: { reason: 'missing_permission', permission: 'contract-types.delete' } }))

    renderWith(<ContractTypesTable />)
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(toastErrorMock).toHaveBeenCalledWith('You do not have the «Delete» permission on the «Contract types» module.'))
  })

  it('shows a distinct "in use" message on a 409', async () => {
    deleteMock.mockRejectedValue(axiosError(409))

    renderWith(<ContractTypesTable />)
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith(
        'Cannot delete: the type is used by at least one contract condition. Deactivate it instead.',
      ),
    )
  })

  it('falls back to the generic error message on any other failure', async () => {
    deleteMock.mockRejectedValue(new Error('network down'))

    renderWith(<ContractTypesTable />)
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(toastErrorMock).toHaveBeenCalledWith('Unable to delete the contract type. Please try again.'))
  })
})
