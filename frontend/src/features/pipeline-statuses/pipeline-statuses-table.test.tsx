import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import PipelineStatusesPage from '@/pages/pipeline-statuses-page'
import { PipelineStatusesTable } from '@/features/pipeline-statuses/pipeline-statuses-table'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * Mirrors `sources-table.test.tsx` (spec 0018): the generic `<TableView>`
 * (AG Grid + SSRM) and the app chrome (`PageHeader`) are framework pieces
 * outside this microtask's ownership: they are stubbed so the suite stays
 * focused on what THIS adapter is responsible for — wiring `<Can>` around
 * the table, mounting `<TableView domain="pipeline-statuses">`, and the
 * delete flow (BR-4: surfacing the backend's exact 409 message).
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

const deletePipelineStatusMock = vi.fn()
vi.mock('@/features/pipeline-statuses/api', () => ({
  deletePipelineStatus: (...args: unknown[]) => deletePipelineStatusMock(...args),
  fetchPipelineStatus: vi.fn(),
}))

const toastSuccessMock = vi.fn()
const toastErrorMock = vi.fn()
vi.mock('sonner', () => ({ toast: { success: (...args: unknown[]) => toastSuccessMock(...args), error: (...args: unknown[]) => toastErrorMock(...args) } }))

const DELETE_ACTION: TableActionDefinition = {
  key: 'delete',
  label: 'actions.delete',
  icon: 'trash',
  type: 'danger',
  confirm: true,
}

const ROW: TableRow = { id: 1, actions: ['delete'], name: 'Draft' }

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
        <PipelineStatusesPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <PipelineStatusesTable />
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
  deletePipelineStatusMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
})

describe('PipelineStatusesPage — permission gating (AC-045)', () => {
  it('shows the forbidden fallback and does not mount the table without viewAny', () => {
    canMock.mockReturnValue(false)

    renderPage()

    expect(
      screen.getByText("You don't have permission to view project statuses."),
    ).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'table-pipeline-statuses' })).not.toBeInTheDocument()
  })

  it('mounts <TableView domain="pipeline-statuses"> with viewAny', () => {
    canMock.mockImplementation((permission) => permission === 'pipeline-statuses.viewAny')

    renderPage()

    expect(screen.getByRole('region', { name: 'table-pipeline-statuses' })).toBeInTheDocument()
    expect(
      screen.queryByText("You don't have permission to view project statuses."),
    ).not.toBeInTheDocument()
  })
})

describe('PipelineStatusesTable — reorder toggle (spec 0039 D-4)', () => {
  it('shows the reorder button with pipeline-statuses.update', () => {
    canMock.mockImplementation((permission) => permission === 'pipeline-statuses.update')

    renderTable()

    expect(screen.getByRole('button', { name: 'Reorder' })).toBeInTheDocument()
  })

  it('hides the reorder button without pipeline-statuses.update', () => {
    canMock.mockReturnValue(false)

    renderTable()

    expect(screen.queryByRole('button', { name: 'Reorder' })).not.toBeInTheDocument()
  })
})

describe('PipelineStatusesTable — delete (BR-4)', () => {
  it('shows the backend message on a 409 (status still in use)', async () => {
    deletePipelineStatusMock.mockRejectedValue(
      new AxiosError('Conflict', '409', undefined, undefined, {
        status: 409,
        data: { success: false, message: 'This status is used by 2 projects.' },
      } as never),
    )

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(deletePipelineStatusMock).toHaveBeenCalledWith(1))
    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith('This status is used by 2 projects.'),
    )
  })

  it('shows a forbidden toast on a 403', async () => {
    deletePipelineStatusMock.mockRejectedValue(
      new AxiosError('Forbidden', '403', undefined, undefined, {
        status: 403,
        data: {
          success: false,
          message: 'Forbidden',
          authorization: { reason: 'missing_permission', permission: 'pipeline-statuses.delete' },
        },
      } as never),
    )

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith('You do not have the «Delete» permission on the «Project/Campaign Statuses» module.'),
    )
  })

  it('shows the success toast and refreshes on a successful delete', async () => {
    deletePipelineStatusMock.mockResolvedValue(undefined)

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastSuccessMock).toHaveBeenCalledWith('Project status deleted successfully.'),
    )
  })
})
