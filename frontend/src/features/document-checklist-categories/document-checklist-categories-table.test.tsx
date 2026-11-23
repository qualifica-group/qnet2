import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, type AxiosResponse } from 'axios'
import i18n from '@/i18n'
import { DocumentChecklistCategoriesTable } from '@/features/document-checklist-categories/document-checklist-categories-table'
import { documentChecklistCategoryColumnRenderers } from '@/features/document-checklist-categories/column-renderers'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import type { RowActionState } from '@/features/table/row-actions'

const canMock = vi.fn<(permission: string) => boolean>()

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const syncMock = vi.fn()
const removeMock = vi.fn()
vi.mock('@/features/document-checklist-categories/api', () => ({
  syncDocumentChecklistCategory: (...args: unknown[]) => syncMock(...args),
  removeDocumentChecklistCategory: (...args: unknown[]) => removeMock(...args),
}))

const toastSuccessMock = vi.fn()
const toastErrorMock = vi.fn()
vi.mock('sonner', () => ({
  toast: {
    success: (...args: unknown[]) => toastSuccessMock(...args),
    error: (...args: unknown[]) => toastErrorMock(...args),
  },
}))

const SYNC_ACTION: TableActionDefinition = { key: 'sync', label: 'x', icon: 'refresh-cw', type: 'action', confirm: false }
const DELETE_ACTION: TableActionDefinition = { key: 'delete', label: 'x', icon: 'trash', type: 'action', confirm: false }
const ROW: TableRow = { id: 7, actions: ['sync', 'delete'] }

const refreshMock = vi.fn()
type ResolveState = (action: TableActionDefinition, row: TableRow) => RowActionState | undefined

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void },
    { onAction?: (action: TableActionDefinition, row: TableRow) => void; resolveActionState?: ResolveState }
  >(function TableViewStub({ onAction, resolveActionState }, ref) {
    useImperativeHandle(ref, () => ({ refresh: refreshMock }))
    return (
      <div>
        <button type="button" onClick={() => onAction?.(SYNC_ACTION, ROW)}>
          {resolveActionState?.(SYNC_ACTION, ROW)?.label ?? ''}
        </button>
        <button type="button" onClick={() => onAction?.(DELETE_ACTION, ROW)}>
          delete row
        </button>
      </div>
    )
  }),
}))

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <DocumentChecklistCategoriesTable />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

function unprocessable(data: unknown): AxiosError {
  const response = { status: 422, data } as AxiosResponse
  return new AxiosError('Unprocessable', 'ERR_BAD_REQUEST', undefined, undefined, response)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
  canMock.mockReturnValue(true)
})

describe('DocumentChecklistCategoriesTable (spec 0410 AC-019)', () => {
  it('offers the create button only with document-checklists.create', () => {
    canMock.mockImplementation((permission) => permission !== 'document-checklists.create')
    renderTable()

    expect(
      screen.queryByRole('button', { name: i18n.t('documentChecklistCategories.form.newCategory') }),
    ).not.toBeInTheDocument()
  })

  it('re-syncs the row and refreshes the grid', async () => {
    syncMock.mockResolvedValue({})
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'Synchronize again' }))

    await waitFor(() => expect(syncMock).toHaveBeenCalledWith(7))
    await waitFor(() => expect(refreshMock).toHaveBeenCalled())
    expect(toastSuccessMock).toHaveBeenCalledWith(i18n.t('documentChecklistCategories.toast.syncAgainQueued'))
  })

  it('deletes the row and refreshes the grid', async () => {
    removeMock.mockResolvedValue(undefined)
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'delete row' }))

    await waitFor(() => expect(removeMock).toHaveBeenCalledWith(7))
    await waitFor(() => expect(refreshMock).toHaveBeenCalled())
    expect(toastSuccessMock).toHaveBeenCalledWith(i18n.t('documentChecklistCategories.form.deleted'))
  })

  it('toasts the server message of the 422 on category when checklists block the delete', async () => {
    const message = 'The category still contains 2 checklists: move or delete them first.'
    removeMock.mockRejectedValue(unprocessable({ message: 'Invalid', errors: { category: [message] } }))
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'delete row' }))

    await waitFor(() => expect(toastErrorMock).toHaveBeenCalledWith(message))
    expect(refreshMock).not.toHaveBeenCalled()
  })

  it('falls back to the generic message for an unexpected failure', async () => {
    removeMock.mockRejectedValue(new Error('boom'))
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'delete row' }))

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith(i18n.t('documentChecklistCategories.form.deleteError')),
    )
  })
})

describe('documentChecklistCategoryColumnRenderers', () => {
  it('renders the sync state as a text badge', () => {
    const renderer = documentChecklistCategoryColumnRenderers.sync_status
    render(<>{renderer({ value: 'failed', data: { sync_error: 'Remote down' } } as never)}</>)

    expect(screen.getByText('Failed')).toBeInTheDocument()
  })
})
