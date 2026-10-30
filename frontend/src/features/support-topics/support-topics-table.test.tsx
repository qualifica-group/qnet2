import { beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import '@/i18n'
import { SupportTopicsTable } from '@/features/support-topics/support-topics-table'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/** Spec 0334 AC-018: the page mounts the `support-topics` table, gates "new" by permission and deletes a row. */

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const deleteMock = vi.fn()
vi.mock('@/features/support-topics/api', () => ({
  deleteSupportTopic: (...args: unknown[]) => deleteMock(...args),
  fetchSupportTopic: vi.fn(),
}))
const toastSuccessMock = vi.fn()
vi.mock('sonner', () => ({ toast: { success: (...args: unknown[]) => toastSuccessMock(...args), error: vi.fn() } }))

const DELETE_ACTION: TableActionDefinition = { key: 'delete', label: 'actions.delete', icon: 'trash', type: 'danger', confirm: true }
const ROW: TableRow = { id: 5, actions: ['delete'], name: 'Stampante' }

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
        <SupportTopicsTable />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeEach(() => {
  canMock.mockReset()
  deleteMock.mockReset()
  toastSuccessMock.mockReset()
})

describe('SupportTopicsTable', () => {
  it('mounts the support-topics table and offers "new" only with support-topics.create', () => {
    canMock.mockReturnValue(false)
    const { unmount } = renderTable()
    expect(screen.getByRole('region', { name: 'table-support-topics' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Nuovo tipo di problema/ })).not.toBeInTheDocument()
    unmount()

    canMock.mockImplementation((permission) => permission === 'support-topics.create')
    renderTable()
    expect(screen.getByRole('button', { name: /Nuovo tipo di problema/ })).toBeInTheDocument()
  })

  it('deletes the row and confirms with a toast', async () => {
    canMock.mockReturnValue(true)
    deleteMock.mockResolvedValue(undefined)
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'delete row' }))

    await waitFor(() => expect(deleteMock).toHaveBeenCalledWith(5))
    await waitFor(() => expect(toastSuccessMock).toHaveBeenCalled())
  })
})
