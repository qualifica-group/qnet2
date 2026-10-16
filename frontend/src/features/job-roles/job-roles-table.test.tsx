import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import JobRolesPage from '@/pages/job-roles-page'
import { JobRolesTable } from '@/features/job-roles/job-roles-table'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * The generic `<TableView>` and the app chrome are stubbed so the suite
 * stays on what this adapter owns: `<Can>` gating, mounting
 * `<TableView domain="job-roles">` and the delete flow, including the 409
 * "in use" guard distinct from 403/generic.
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

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const deleteJobRoleMock = vi.fn()
vi.mock('@/features/job-roles/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/job-roles/api')>()),
  deleteJobRole: (...args: unknown[]) => deleteJobRoleMock(...args),
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

const ROW: TableRow = { id: 1, actions: ['delete'], name: 'Fitti' }

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
  deleteJobRoleMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
})

describe('JobRolesPage - permission gating', () => {
  it('shows the forbidden fallback and does not mount the table without view', () => {
    canMock.mockReturnValue(false)

    renderWith(<JobRolesPage />)

    expect(screen.getByText("You don't have permission to view job roles.")).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'table-job-roles' })).not.toBeInTheDocument()
  })

  it('mounts <TableView domain="job-roles"> with view', () => {
    canMock.mockImplementation((permission) => permission === 'job-roles.view')

    renderWith(<JobRolesPage />)

    expect(screen.getByRole('region', { name: 'table-job-roles' })).toBeInTheDocument()
  })
})

describe('JobRolesTable - new button', () => {
  it('shows "New job role" with create and hides it without', () => {
    canMock.mockImplementation((permission) => permission === 'job-roles.create')
    const { unmount } = renderWith(<JobRolesTable />)
    expect(screen.getByRole('button', { name: 'New job role' })).toBeInTheDocument()
    unmount()

    canMock.mockReturnValue(false)
    renderWith(<JobRolesTable />)
    expect(screen.queryByRole('button', { name: 'New job role' })).not.toBeInTheDocument()
  })
})

describe('JobRolesTable - delete', () => {
  it('shows the success toast on a successful delete', async () => {
    deleteJobRoleMock.mockResolvedValue(undefined)

    renderWith(<JobRolesTable />)
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(deleteJobRoleMock).toHaveBeenCalledWith(1))
    await waitFor(() => expect(toastSuccessMock).toHaveBeenCalledWith('Job role deleted successfully.'))
  })

  it('shows a forbidden toast on a 403', async () => {
    deleteJobRoleMock.mockRejectedValue(axiosError(403))

    renderWith(<JobRolesTable />)
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(toastErrorMock).toHaveBeenCalledWith('You cannot delete this job role.'))
  })

  it('shows a distinct "in use" toast on a 409', async () => {
    deleteJobRoleMock.mockRejectedValue(axiosError(409))

    renderWith(<JobRolesTable />)
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith('Cannot delete: the job role is assigned to people.'),
    )
  })

  it('falls back to the generic error message on any other failure', async () => {
    deleteJobRoleMock.mockRejectedValue(new Error('network down'))

    renderWith(<JobRolesTable />)
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith('Unable to delete the job role. Please try again.'),
    )
  })
})
