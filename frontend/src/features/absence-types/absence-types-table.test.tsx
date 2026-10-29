import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import AbsenceTypesPage from '@/features/absence-types/absence-types-page'
import { AbsenceTypesTable } from '@/features/absence-types/absence-types-table'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * The generic `<TableView>` and the app chrome are stubbed so the suite stays on what this
 * adapter owns: `<Can>` gating, mounting `<TableView domain="absence-types">` and the delete
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
vi.mock('@/features/absence-types/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/absence-types/api')>()),
  deleteAbsenceType: (...args: unknown[]) => deleteMock(...args),
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

const ROW: TableRow = { id: 1, actions: ['delete'], name: 'Ferie' }

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

function axiosError(status: number) {
  return new AxiosError('error', String(status), undefined, undefined, {
    status,
    data: { success: false, message: 'server message' },
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

describe('AbsenceTypesPage - permission gating', () => {
  it('shows the forbidden fallback and does not mount the table without view', () => {
    canMock.mockReturnValue(false)

    renderWith(<AbsenceTypesPage />)

    expect(screen.getByText('You do not have permission to view absence types.')).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'table-absence-types' })).not.toBeInTheDocument()
  })

  it('mounts <TableView domain="absence-types"> with view', () => {
    canMock.mockImplementation((permission) => permission === 'absence-types.view')

    renderWith(<AbsenceTypesPage />)

    expect(screen.getByRole('region', { name: 'table-absence-types' })).toBeInTheDocument()
  })
})

describe('AbsenceTypesTable - new button', () => {
  it('shows "New absence type" with create and hides it without', () => {
    canMock.mockImplementation((permission) => permission === 'absence-types.create')
    const { unmount } = renderWith(<AbsenceTypesTable />)
    expect(screen.getByRole('button', { name: 'New absence type' })).toBeInTheDocument()
    unmount()

    canMock.mockReturnValue(false)
    renderWith(<AbsenceTypesTable />)
    expect(screen.queryByRole('button', { name: 'New absence type' })).not.toBeInTheDocument()
  })
})

describe('AbsenceTypesTable - delete', () => {
  it('shows the success toast on a successful delete', async () => {
    deleteMock.mockResolvedValue(undefined)

    renderWith(<AbsenceTypesTable />)
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(deleteMock).toHaveBeenCalledWith(1))
    await waitFor(() => expect(toastSuccessMock).toHaveBeenCalledWith('Absence type deleted successfully.'))
  })

  it('shows a forbidden toast on a 403', async () => {
    deleteMock.mockRejectedValue(axiosError(403))

    renderWith(<AbsenceTypesTable />)
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(toastErrorMock).toHaveBeenCalledWith('server message'))
  })

  it('shows a distinct "in use" message on a 409', async () => {
    deleteMock.mockRejectedValue(axiosError(409))

    renderWith(<AbsenceTypesTable />)
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith(
        'Cannot delete: the type is used by at least one request. Deactivate it instead.',
      ),
    )
  })

  it('falls back to the generic error message on any other failure', async () => {
    deleteMock.mockRejectedValue(new Error('network down'))

    renderWith(<AbsenceTypesTable />)
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(toastErrorMock).toHaveBeenCalledWith('Unable to delete the absence type. Please try again.'))
  })
})
