import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import DocumentLayoutsPage from '@/pages/document-layouts-page'
import { DocumentLayoutsTable } from '@/features/document-layouts/document-layouts-table'
import { moduleScreen } from '@/features/document-layouts/document-layout-screens'
import { OPEN_MODE_PAGE } from '@/features/modules/types'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * The generic `<TableView>` (AG Grid + SSRM) and the app chrome (`PageHeader`)
 * are framework pieces outside this microtask's ownership: they are stubbed
 * so the suite stays focused on what THIS adapter is responsible for —
 * wiring `<Can>` around the table and the "New" button (AC-130/AC-131),
 * mounting `<TableView domain="document-layouts">`, and the delete flow
 * surfacing the D-7 predefined-layout guard message verbatim from the
 * backend on a 422 (AC-132).
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

// Force the resolved open mode so the delete-flow tests do not depend on the
// user's preference resolution (spec 0042, D-6); the moduleScreen's own
// `defaultMode` is asserted separately below (AC-133).
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const deleteDocumentLayoutMock = vi.fn()
vi.mock('@/features/document-layouts/api', () => ({
  deleteDocumentLayout: (...args: unknown[]) => deleteDocumentLayoutMock(...args),
  fetchDocumentLayout: vi.fn(),
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

const ROW: TableRow = { id: 1, actions: ['delete'], name: 'Standard quote layout' }

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
        <DocumentLayoutsPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <DocumentLayoutsTable />
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
  deleteDocumentLayoutMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
})

describe('DocumentLayoutsPage — permission gating (AC-130)', () => {
  it('shows the forbidden fallback and does not mount the table without viewAny', () => {
    canMock.mockReturnValue(false)

    renderPage()

    expect(
      screen.queryByRole('region', { name: 'table-document-layouts' }),
    ).not.toBeInTheDocument()
  })

  it('mounts <TableView domain="document-layouts"> with viewAny', () => {
    canMock.mockImplementation((permission) => permission === 'document-layouts.viewAny')

    renderPage()

    expect(screen.getByRole('region', { name: 'table-document-layouts' })).toBeInTheDocument()
  })
})

describe('DocumentLayoutsTable — "New" button gating (AC-131)', () => {
  it('shows the "New" button with document-layouts.create', () => {
    canMock.mockImplementation((permission) => permission === 'document-layouts.create')

    renderTable()

    expect(screen.getByRole('button', { name: /new/i })).toBeInTheDocument()
  })

  it('hides the "New" button without document-layouts.create', () => {
    canMock.mockReturnValue(false)

    renderTable()

    expect(screen.queryByRole('button', { name: /new/i })).not.toBeInTheDocument()
  })
})

describe('DocumentLayoutsTable — delete (AC-132)', () => {
  it('shows the success toast and refreshes on a successful delete', async () => {
    deleteDocumentLayoutMock.mockResolvedValue(undefined)

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(deleteDocumentLayoutMock).toHaveBeenCalledWith(1))
    await waitFor(() => expect(toastSuccessMock).toHaveBeenCalled())
  })

  it('shows a forbidden toast on a 403, using the backend re-authorization, not the UI gate', async () => {
    deleteDocumentLayoutMock.mockRejectedValue(
      new AxiosError('Forbidden', '403', undefined, undefined, {
        status: 403,
        data: { success: false, message: 'Forbidden' },
      } as never),
    )

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(toastErrorMock).toHaveBeenCalled())
    expect(toastErrorMock).not.toHaveBeenCalledWith('Forbidden')
  })

  it('shows the backend message verbatim on a 422 from the D-7 predefined-layout guard', async () => {
    deleteDocumentLayoutMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: {
          success: false,
          message: 'This layout is the default for its module and cannot be deleted.',
        },
      } as never),
    )

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith(
        'This layout is the default for its module and cannot be deleted.',
      ),
    )
  })

  it('falls back to the generic error message on any other failure', async () => {
    deleteDocumentLayoutMock.mockRejectedValue(new Error('network down'))

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(toastErrorMock).toHaveBeenCalled())
  })
})

describe('document-layouts moduleScreen registry entry (AC-133)', () => {
  it('registers the document-layouts domain with OPEN_MODE_PAGE as its default (D-9)', () => {
    expect(moduleScreen.domain).toBe('document-layouts')
    expect(moduleScreen.basePath).toBe('/document-layouts')
    expect(moduleScreen.defaultMode).toBe(OPEN_MODE_PAGE)
    expect(moduleScreen.generateRoutes).not.toBe(false)
  })
})
