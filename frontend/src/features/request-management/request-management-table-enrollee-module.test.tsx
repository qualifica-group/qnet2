import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestManagementTable } from '@/features/request-management/request-management-table'
import { ENROLLEE_MODULE, RequestModuleProvider } from '@/features/request-management/request-module'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * Spec 0130 AC-014/AC-016: with `ENROLLEE_MODULE` provided, the SAME
 * `RequestManagementTable` reads every domain/permission/API-path value from
 * `useRequestModule()` instead of the hard-coded Gestione Richieste
 * constants. The default-module (no provider) behaviour keeps its own
 * coverage in `request-management-table.test.tsx`/`request-management-table-
 * create.test.tsx` (AC-017); this suite only asserts the delta the module
 * config produces.
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
  useModuleOpenMode: () => 'page' as const,
}))

const navigateMock = vi.fn()
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
  return { ...actual, useNavigate: () => navigateMock }
})

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const ROW: TableRow = { id: 7, actions: ['view'], name: 'Enrollee deal' }

function action(key: string): TableActionDefinition {
  return { key, label: `actions.${key}`, icon: 'eye', type: 'link', confirm: false }
}

let capturedOnAction: RowActionHandler | null = null

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void; clearSelection: () => void },
    { domain: string; onAction: RowActionHandler }
  >(function TableViewStub({ domain, onAction }, ref) {
    useImperativeHandle(ref, () => ({ refresh: () => {}, clearSelection: () => {} }))
    capturedOnAction = onAction
    return (
      <div role="region" aria-label={`table-${domain}`}>
        <button type="button" onClick={() => onAction(action('delete'), ROW)}>
          trigger-delete
        </button>
      </div>
    )
  }),
}))

const deleteRequestMock = vi.fn()
const fetchRequestManagementCategoriesMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  fetchRequestWorkPanel: vi.fn(),
  updateRequestWork: vi.fn(),
  deleteRequest: (...args: unknown[]) => deleteRequestMock(...args),
  assignRequestOperators: vi.fn(),
  assignRequestManagerGa1: vi.fn(),
  fetchCategoryManagerLabels: vi.fn(),
  transferRequests: vi.fn(),
  fetchRequestManagementCategories: (...args: unknown[]) => fetchRequestManagementCategoriesMock(...args),
}))

vi.mock('@/features/assignment/api', () => ({
  fetchAssignmentScope: vi
    .fn()
    .mockResolvedValue({ product_category_ids: [], operational_site_id: null, campaign_ids: [] }),
}))

function renderEnrolleeTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <MemoryRouter>
          <RequestModuleProvider module={ENROLLEE_MODULE}>
            <RequestManagementTable />
          </RequestModuleProvider>
        </MemoryRouter>
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  window.localStorage.clear()
  navigateMock.mockReset()
  capturedOnAction = null
  canMock.mockReset()
  canMock.mockReturnValue(true)
  deleteRequestMock.mockReset()
  deleteRequestMock.mockResolvedValue(undefined)
  fetchRequestManagementCategoriesMock.mockReset()
  fetchRequestManagementCategoriesMock.mockResolvedValue([])
})

describe('RequestManagementTable under ENROLLEE_MODULE (spec 0130 AC-014)', () => {
  it('mounts <TableView domain="enrollee-management">', () => {
    renderEnrolleeTable()

    expect(screen.getByRole('region', { name: 'table-enrollee-management' })).toBeInTheDocument()
    expect(capturedOnAction).not.toBeNull()
  })

  it('calls the delete endpoint under /enrollee-management, never /request-management', async () => {
    renderEnrolleeTable()

    fireEvent.click(screen.getByText('trigger-delete'))

    await waitFor(() => expect(deleteRequestMock).toHaveBeenCalledWith('/enrollee-management', 7))
  })

  it('resolves the category tab query under the enrollee-management base path', async () => {
    renderEnrolleeTable()

    await waitFor(() => expect(fetchRequestManagementCategoriesMock).toHaveBeenCalledWith('/enrollee-management'))
  })

  it('gates the bulk operator assignment on enrollee-management.* abilities, never request-management.*', () => {
    renderEnrolleeTable()

    expect(canMock).toHaveBeenCalledWith('enrollee-management.update')
    expect(canMock).toHaveBeenCalledWith('enrollee-management.assignOperator')
    expect(canMock).not.toHaveBeenCalledWith('request-management.update')
    expect(canMock).not.toHaveBeenCalledWith('request-management.assignOperator')
  })

  it('persists the category tab preference under its own storage key, separate from Gestione Richieste', async () => {
    fetchRequestManagementCategoriesMock.mockResolvedValue([
      { id: 12, name: 'GOL - Lombardia', requests_count: 3 },
    ])
    renderEnrolleeTable()

    fireEvent.mouseDown(await screen.findByRole('tab', { name: /GOL - Lombardia/ }))

    await waitFor(() =>
      expect(window.localStorage.getItem('enrollee-management.category-tab')).toBe('12'),
    )
    expect(window.localStorage.getItem('request-management.category-tab')).toBeNull()
  })
})

describe('RequestManagementTable "New request" affordance under ENROLLEE_MODULE (spec 0130 AC-016)', () => {
  it('renders no create button even with every permission granted', () => {
    canMock.mockReturnValue(true)
    renderEnrolleeTable()

    expect(screen.queryByRole('button', { name: /new/i })).not.toBeInTheDocument()
    expect(canMock).not.toHaveBeenCalledWith('enrollee-management.create')
  })
})
