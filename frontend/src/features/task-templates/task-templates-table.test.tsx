import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import TaskTemplatesPage from '@/pages/task-templates-page'
import { TaskTemplatesTable } from '@/features/task-templates/task-templates-table'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * The generic `<TableView>` (AG Grid + SSRM) and the app chrome (`PageHeader`)
 * are framework pieces outside this microtask's ownership: they are stubbed
 * so the suite stays focused on what THIS adapter is responsible for —
 * wiring `<Can>` around the table, mounting `<TableView domain=
 * "task-templates">`, and the delete flow, including the 409 "in use" guard
 * with the SERVER's own message (D-5). Mirrors `ProductTypologiesTable`'s
 * suite.
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

const deleteTaskTemplateMock = vi.fn()
vi.mock('@/features/task-templates/api', () => ({
  deleteTaskTemplate: (...args: unknown[]) => deleteTaskTemplateMock(...args),
  fetchTaskTemplate: vi.fn(),
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

const ROW: TableRow = { id: 1, actions: ['delete'], name: 'Standard onboarding' }

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
        <TaskTemplatesPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <TaskTemplatesTable />
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
  deleteTaskTemplateMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
})

describe('TaskTemplatesPage — permission gating', () => {
  it('shows the forbidden fallback and does not mount the table without viewAny', () => {
    canMock.mockReturnValue(false)

    renderPage()

    expect(screen.getByText('You do not have permission to view task templates.')).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'table-task-templates' })).not.toBeInTheDocument()
  })

  it('mounts <TableView domain="task-templates"> with viewAny', () => {
    canMock.mockImplementation((permission) => permission === 'task-templates.viewAny')

    renderPage()

    expect(screen.getByRole('region', { name: 'table-task-templates' })).toBeInTheDocument()
  })
})

describe('TaskTemplatesTable — new button', () => {
  it('shows "New task template" with create, hides it without', () => {
    canMock.mockImplementation((permission) => permission === 'task-templates.create')

    renderTable()

    expect(screen.getByRole('button', { name: 'New task template' })).toBeInTheDocument()
  })

  it('hides the create button without the permission', () => {
    canMock.mockReturnValue(false)

    renderTable()

    expect(screen.queryByRole('button', { name: 'New task template' })).not.toBeInTheDocument()
  })
})

describe('TaskTemplatesTable — delete (D-5: 409 "in use" guard, server message)', () => {
  it('shows the success toast and refreshes on a successful delete', async () => {
    deleteTaskTemplateMock.mockResolvedValue(undefined)

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(deleteTaskTemplateMock).toHaveBeenCalledWith(1))
    await waitFor(() =>
      expect(toastSuccessMock).toHaveBeenCalledWith('Task template deleted successfully.'),
    )
  })

  it('shows a forbidden toast on a 403', async () => {
    deleteTaskTemplateMock.mockRejectedValue(
      new AxiosError('Forbidden', '403', undefined, undefined, {
        status: 403,
        data: { success: false, message: 'Forbidden' },
      } as never),
    )

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith('You cannot delete this task template.'),
    )
  })

  it('shows the SERVER 409 message verbatim when the template is used by a Commessa (D-5)', async () => {
    deleteTaskTemplateMock.mockRejectedValue(
      new AxiosError('Conflict', '409', undefined, undefined, {
        status: 409,
        data: {
          success: false,
          message: 'This task template was used to generate Commesse and cannot be deleted. Deactivate it instead.',
        },
      } as never),
    )

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith(
        'This task template was used to generate Commesse and cannot be deleted. Deactivate it instead.',
      ),
    )
  })

  it('falls back to the generic error message on any other failure', async () => {
    deleteTaskTemplateMock.mockRejectedValue(new Error('network down'))

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith('Unable to delete the task template. Please retry.'),
    )
  })
})
