import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import AgentFiscalProfilesPage from '@/pages/agent-fiscal-profiles-page'
import { AgentFiscalProfilesTable } from '@/features/agent-fiscal-profiles/agent-fiscal-profiles-table'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

const canMock = vi.fn<(permission: string) => boolean>()
const deleteMock = vi.fn()
const toastSuccessMock = vi.fn()
const toastErrorMock = vi.fn()

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

vi.mock('@/features/agent-fiscal-profiles/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/agent-fiscal-profiles/api')>()),
  deleteAgentFiscalProfile: (...args: unknown[]) => deleteMock(...args),
}))

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

const ROW: TableRow = { id: 4, actions: ['delete'], referent: 'Mario Rossi' }

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
  canMock.mockReset().mockReturnValue(true)
  deleteMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
})

describe('AgentFiscalProfilesPage - permission gating', () => {
  it('shows the forbidden fallback and no table without viewAny', () => {
    canMock.mockReturnValue(false)

    renderWith(<AgentFiscalProfilesPage />)

    expect(screen.getByText("You don't have permission to view agent fiscal profiles.")).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'table-agent-fiscal-profiles' })).not.toBeInTheDocument()
  })

  it('mounts <TableView domain="agent-fiscal-profiles"> with viewAny', () => {
    renderWith(<AgentFiscalProfilesPage />)

    expect(canMock).toHaveBeenCalledWith('agent-fiscal-profiles.viewAny')
    expect(screen.getByRole('region', { name: 'table-agent-fiscal-profiles' })).toBeInTheDocument()
  })
})

describe('AgentFiscalProfilesTable', () => {
  it('shows "New fiscal profile" only with create', () => {
    const { unmount } = renderWith(<AgentFiscalProfilesTable />)
    expect(screen.getByRole('button', { name: 'New fiscal profile' })).toBeInTheDocument()
    unmount()

    canMock.mockReturnValue(false)
    renderWith(<AgentFiscalProfilesTable />)
    expect(screen.queryByRole('button', { name: 'New fiscal profile' })).not.toBeInTheDocument()
  })

  it('deletes through the profile endpoint and confirms with a toast', async () => {
    deleteMock.mockResolvedValue(undefined)

    renderWith(<AgentFiscalProfilesTable />)
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(deleteMock).toHaveBeenCalledWith(4))
    await waitFor(() => expect(toastSuccessMock).toHaveBeenCalledWith('Fiscal profile deleted successfully.'))
  })

  it('shows a forbidden toast on a 403 and a generic one otherwise', async () => {
    deleteMock.mockRejectedValueOnce(axiosError(403)).mockRejectedValueOnce(new Error('network down'))

    renderWith(<AgentFiscalProfilesTable />)
    screen.getByRole('button', { name: 'delete row' }).click()
    await waitFor(() => expect(toastErrorMock).toHaveBeenCalledWith('server message'))

    screen.getByRole('button', { name: 'delete row' }).click()
    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith('Unable to delete the fiscal profile. Please try again.'),
    )
  })
})
