import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { contractStatuses as contractStatusesEn } from '@/i18n/locales/en-contract-statuses'
import { ContractStatusesTable } from '@/features/contract-statuses/contract-statuses-table'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * The generic `<TableView>` (AG Grid + SSRM) and the app chrome
 * (`PageHeader`) are framework pieces outside this microtask's ownership:
 * they are stubbed so the suite stays focused on what THIS adapter is
 * responsible for — wiring `<Can>` around the reorder toggle/create button,
 * mounting `<TableView domain="contract-statuses">`, and the delete flow
 * (surfacing the backend's exact 409 message).
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

// Default modal behaviour; force the resolved open mode (spec 0042).
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const deleteContractStatusMock = vi.fn()
vi.mock('@/features/contract-statuses/api', () => ({
  deleteContractStatus: (...args: unknown[]) => deleteContractStatusMock(...args),
  fetchContractStatus: vi.fn(),
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

const ROW: TableRow = { id: 1, actions: ['delete'], name: 'Da programmare' }

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

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ContractStatusesTable />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  // `contract-statuses` is registered by another microtask (spec 0072 MT-09,
  // i18n wiring): register the real bundle directly on the shared instance
  // so this suite exercises the actual rendered copy, independent of that
  // wiring's timing.
  i18n.addResourceBundle('en', 'translation', { contractStatuses: contractStatusesEn }, true, true)
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  deleteContractStatusMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
})

describe('ContractStatusesTable — reorder toggle (D-4)', () => {
  it('shows the reorder button with contract-statuses.update', () => {
    canMock.mockImplementation((permission) => permission === 'contract-statuses.update')

    renderTable()

    expect(screen.getByRole('button', { name: 'Reorder' })).toBeInTheDocument()
  })

  it('hides the reorder button without contract-statuses.update', () => {
    canMock.mockReturnValue(false)

    renderTable()

    expect(screen.queryByRole('button', { name: 'Reorder' })).not.toBeInTheDocument()
  })
})

describe('ContractStatusesTable — mount', () => {
  it('mounts <TableView domain="contract-statuses">', () => {
    renderTable()

    expect(screen.getByRole('region', { name: 'table-contract-statuses' })).toBeInTheDocument()
  })
})

describe('ContractStatusesTable — delete', () => {
  it('shows the backend message on a 409 (status still in use)', async () => {
    deleteContractStatusMock.mockRejectedValue(
      new AxiosError('Conflict', '409', undefined, undefined, {
        status: 409,
        data: {
          success: false,
          message: 'This contract status is used by a contract and cannot be deleted.',
        },
      } as never),
    )

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(deleteContractStatusMock).toHaveBeenCalledWith(1))
    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith(
        'This contract status is used by a contract and cannot be deleted.',
      ),
    )
  })

  it('shows a forbidden toast on a 403', async () => {
    deleteContractStatusMock.mockRejectedValue(
      new AxiosError('Forbidden', '403', undefined, undefined, {
        status: 403,
        data: { success: false, message: 'Forbidden' },
      } as never),
    )

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith('You cannot delete this contract status.'),
    )
  })

  it('shows the success toast and refreshes on a successful delete', async () => {
    deleteContractStatusMock.mockResolvedValue(undefined)

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastSuccessMock).toHaveBeenCalledWith('Contract status deleted successfully.'),
    )
  })
})
