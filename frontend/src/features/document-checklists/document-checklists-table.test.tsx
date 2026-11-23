import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'

import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { DocumentChecklistsTable } from '@/features/document-checklists/document-checklists-table'
import { documentChecklistColumnRenderers } from '@/features/document-checklists/column-renderers'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import type { RowActionState } from '@/features/table/row-actions'

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

vi.mock('@/features/document-checklists/archivio-smart-connection-button', () => ({
  ArchivioSmartConnectionButton: () => <button type="button">connection</button>,
}))

const syncMock = vi.fn()
const syncAllMock = vi.fn()
const activationMock = vi.fn()
vi.mock('@/features/document-checklists/api', () => ({
  syncDocumentChecklist: (...args: unknown[]) => syncMock(...args),
  syncAllDocumentChecklists: (...args: unknown[]) => syncAllMock(...args),
  setDocumentChecklistActivation: (...args: unknown[]) => activationMock(...args),
  removeDocumentChecklist: vi.fn(),
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
const ACTIVATE_ACTION: TableActionDefinition = { key: 'activate', label: 'x', icon: 'power', type: 'action', confirm: false }
const ROW: TableRow = { id: 7, is_active: true, actions: ['sync', 'activate'] }

const refreshMock = vi.fn()
type ResolveState = (action: TableActionDefinition, row: TableRow) => RowActionState | undefined

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void },
    {
      onAction?: (action: TableActionDefinition, row: TableRow) => void
      resolveActionState?: ResolveState
    }
  >(function TableViewStub({ onAction, resolveActionState }, ref) {
    useImperativeHandle(ref, () => ({ refresh: refreshMock }))
    const activateLabel = resolveActionState?.(ACTIVATE_ACTION, ROW)?.label ?? ''
    const syncLabel = resolveActionState?.(SYNC_ACTION, ROW)?.label ?? ''
    return (
      <div>
        <button type="button" onClick={() => onAction?.(SYNC_ACTION, ROW)}>
          {syncLabel}
        </button>
        <button type="button" onClick={() => onAction?.(ACTIVATE_ACTION, ROW)}>
          {activateLabel}
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
        <DocumentChecklistsTable />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
  canMock.mockReturnValue(true)
})

describe('DocumentChecklistsTable toolbar', () => {
  it('hides "Sync all" without the update permission', () => {
    canMock.mockImplementation((permission) => permission !== 'document-checklists.update')
    renderTable()

    expect(screen.queryByRole('button', { name: 'Sync all' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'connection' })).toBeInTheDocument()
  })

  it('queues categories then checklists and toasts both counts (spec 0410 AC-020)', async () => {
    syncAllMock.mockResolvedValue({ queued: 3, queued_categories: 2 })
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'Sync all' }))

    await waitFor(() =>
      expect(toastSuccessMock).toHaveBeenCalledWith(i18n.t('documentChecklists.toast.syncAllQueued', { checklists: 3, categories: 2 })),
    )
    expect(refreshMock).toHaveBeenCalled()
  })
})

describe('DocumentChecklistsTable row actions', () => {
  it('re-syncs the row and refreshes the grid', async () => {
    syncMock.mockResolvedValue({})
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'Sync again' }))

    await waitFor(() => expect(syncMock).toHaveBeenCalledWith(7))
    await waitFor(() => expect(refreshMock).toHaveBeenCalled())
    expect(toastSuccessMock).toHaveBeenCalledWith(i18n.t('documentChecklists.toast.syncAgainQueued'))
  })

  it('labels the activation action by row state and toggles it', async () => {
    activationMock.mockResolvedValue({ is_active: false })
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'Deactivate' }))

    await waitFor(() => expect(activationMock).toHaveBeenCalledWith(7, false))
    await waitFor(() => expect(refreshMock).toHaveBeenCalled())
  })
})

describe('documentChecklistColumnRenderers', () => {
  it('shows the failure reason as tooltip trigger with a text badge', () => {
    const renderer = documentChecklistColumnRenderers.sync_status
    render(
      <>
        {renderer({ value: 'failed', data: { sync_error: 'Remote down' } } as never)}
      </>,
    )

    expect(screen.getByText('Failed')).toBeInTheDocument()
  })
})
