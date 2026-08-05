import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestManagementTable } from '@/features/request-management/request-management-table'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import type { BulkAction } from '@/features/table/use-bulk-actions-slot'
import type { OpenMode } from '@/features/modules/types'
import type { RequestWorkPanelWithPermissions } from '@/features/request-management/types'

/**
 * Spec 0049 AC-060: the `request-management` adapter mounts `<TableView>`,
 * wires the "Lavora" (`view`) row action through `useModuleOpener`
 * (resolved open-mode: modal Sheet vs `/request-management/:id` page), and
 * exposes no create/edit affordance — the record IS the Opportunity (D-9),
 * that CRUD lives on `opportunities.*`. The `delete` row action and the two
 * bulk flows (delete + operator assignment) were added by the user directive
 * 2026-07-23 and are asserted here too. The generic `<TableView>` (AG Grid +
 * SSRM) is stubbed with buttons that fire `onAction` for a fixed row,
 * mirroring the other Sheet-based adapters' suites (e.g. `LeadsTable`). The
 * adapter's other row action (`documents`) has its own suite,
 * `request-management-table-documents.test.tsx`.
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

let requestManagementOpenMode: OpenMode = 'page'
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => requestManagementOpenMode,
}))

const navigateMock = vi.fn()
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
  return { ...actual, useNavigate: () => navigateMock }
})

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const ROW: TableRow = { id: 7, actions: ['view'], name: 'Enterprise deal' }

function action(key: string): TableActionDefinition {
  return { key, label: `actions.${key}`, icon: 'eye', type: 'link', confirm: false }
}

let capturedOnAction: RowActionHandler | null = null
let capturedBulkActions:
  | ((selection: { ids: number[]; rows: TableRow[] }) => BulkAction[])
  | null = null

let capturedScope: { productCategoryId?: number } | undefined

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void; clearSelection: () => void },
    {
      domain: string
      scope?: { productCategoryId?: number }
      onAction: RowActionHandler
      getBulkActions?: (selection: { ids: number[]; rows: TableRow[] }) => BulkAction[]
    }
  >(function TableViewStub({ domain, scope, onAction, getBulkActions }, ref) {
      useImperativeHandle(ref, () => ({ refresh: () => {}, clearSelection: () => {} }))
      capturedOnAction = onAction
      capturedBulkActions = getBulkActions ?? null
      capturedScope = scope
      return (
        <div role="region" aria-label={`table-${domain}`}>
          <button type="button" onClick={() => onAction(action('view'), ROW)}>
            trigger-view
          </button>
          <button type="button" onClick={() => onAction(action('edit'), ROW)}>
            trigger-edit
          </button>
          <button type="button" onClick={() => onAction(action('delete'), ROW)}>
            trigger-delete
          </button>
          <button
            type="button"
            onClick={() => getBulkActions?.({ ids: [ROW.id], rows: [ROW] })[0]?.onSelect()}
          >
            trigger-bulk-assign
          </button>
        </div>
      )
  }),
}))

function panel(): RequestWorkPanelWithPermissions {
  return {
    id: 7,
    name: 'Enterprise deal',
    registry: { id: 10, name: 'Acme S.p.A.' },
    referent: null,
    commercial: null,
    source_id: null,
    source: null,
    reporter_id: null,
    reporter: null,
    operator_id: null,
    operator: null,
    operational_site_id: null,
    operational_site: null,
    is_transferred: false,
    transferred_from: null,
    status: { source: 'workflow', distinct_count: 0, entries: [] },
    workflow_status: { id: 100, name: 'Open', color: 'blue', system_key: 'open', description: null, requires_note: false },
    workflow_statuses: [{ id: 100, name: 'Open', color: 'blue', system_key: 'open', description: null, requires_note: false }],
    product_lines: [],
    products_of_interest: [],
    client_identity: null,
    client_contacts: { owner: null, items: [] },
    client_address: null,
    referent_contacts: { owner: null, items: [] },
    applicable_attributes: [],
    attribute_values: {},
    next_callback_at: null,
    context: { estimated_value: null, expected_close_date: null, success_probability: null },
    permissions: {
      resource: { view: true, create: false, update: true, delete: false, export: false, import: false },
      fields: {},
      actions: {},
    },
  }
}

const fetchRequestWorkPanelMock = vi.fn()
const deleteRequestMock = vi.fn()
const assignRequestOperatorsMock = vi.fn()
const fetchRequestManagementCategoriesMock = vi.fn()
const transferRequestsMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  fetchRequestWorkPanel: (...args: unknown[]) => fetchRequestWorkPanelMock(...args),
  updateRequestWork: vi.fn(),
  deleteRequest: (...args: unknown[]) => deleteRequestMock(...args),
  assignRequestOperators: (...args: unknown[]) => assignRequestOperatorsMock(...args),
  transferRequests: (...args: unknown[]) => transferRequestsMock(...args),
  fetchRequestManagementCategories: (...args: unknown[]) => fetchRequestManagementCategoriesMock(...args),
}))

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      {/* The work panel's anagraphic section mounts `ContactsManager`, whose
          delete flow needs the app-level confirm dialog. */}
      <ConfirmDialogProvider>
        <MemoryRouter>
          <RequestManagementTable />
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
  requestManagementOpenMode = 'page'
  navigateMock.mockReset()
  capturedOnAction = null
  capturedBulkActions = null
  canMock.mockReset()
  canMock.mockReturnValue(true)
  capturedScope = undefined
  deleteRequestMock.mockReset()
  deleteRequestMock.mockResolvedValue(undefined)
  assignRequestOperatorsMock.mockReset()
  assignRequestOperatorsMock.mockResolvedValue({ assigned: 1 })
  transferRequestsMock.mockReset()
  transferRequestsMock.mockResolvedValue({ transferred: 1 })
  fetchRequestWorkPanelMock.mockReset()
  fetchRequestWorkPanelMock.mockResolvedValue(panel())
  fetchRequestManagementCategoriesMock.mockReset()
  fetchRequestManagementCategoriesMock.mockResolvedValue([])
})

describe('RequestManagementTable (spec 0049 AC-060)', () => {
  it('mounts <TableView domain="request-management">', () => {
    renderTable()

    expect(screen.getByRole('region', { name: 'table-request-management' })).toBeInTheDocument()
    expect(capturedOnAction).not.toBeNull()
  })

  it('navigates to the deep-link page on the view action in page mode', async () => {
    requestManagementOpenMode = 'page'
    renderTable()

    fireEvent.click(screen.getByText('trigger-view'))

    await waitFor(() => expect(navigateMock).toHaveBeenCalledWith('/request-management/7'))
    expect(fetchRequestWorkPanelMock).not.toHaveBeenCalled()
  })

  it('opens the modal Sheet with the work panel on the view action in modal mode, without navigating', async () => {
    requestManagementOpenMode = 'modal'
    renderTable()

    fireEvent.click(screen.getByText('trigger-view'))

    await waitFor(() => expect(fetchRequestWorkPanelMock).toHaveBeenCalledWith(7))
    expect(await screen.findAllByRole('heading', { name: 'Preliminary information' })).not.toHaveLength(0)
    expect(navigateMock).not.toHaveBeenCalled()
  })

  it('ignores the edit action key: still no create/edit affordance', () => {
    renderTable()

    fireEvent.click(screen.getByText('trigger-edit'))

    expect(navigateMock).not.toHaveBeenCalled()
    expect(fetchRequestWorkPanelMock).not.toHaveBeenCalled()
    expect(screen.queryByRole('button', { name: /^edit$/i })).not.toBeInTheDocument()
  })

  // Requirement changed (user directive 2026-07-23): `delete` used to be an
  // ignored key, it now runs the delete flow against its own endpoint.
  it('deletes the row through the request-management endpoint on the delete action', async () => {
    renderTable()

    fireEvent.click(screen.getByText('trigger-delete'))

    await waitFor(() => expect(deleteRequestMock).toHaveBeenCalledWith(7))
    expect(navigateMock).not.toHaveBeenCalled()
  })

  it('offers the bulk "assign operators" action, which opens the shared popup', async () => {
    renderTable()

    expect(capturedBulkActions).not.toBeNull()
    fireEvent.click(screen.getByText('trigger-bulk-assign'))

    expect(await screen.findByRole('dialog')).toHaveTextContent('Assign operators')
  })

  // User directive 2026-08-03: the popup writes the Sede AND the Operatore, so
  // it takes `assignOperator` on top of `update` — the same pair the endpoint
  // now gates on, for a role restricted on those two fields. Requirement
  // changed by spec 0079: the slot now carries a SECOND, independently gated
  // bulk action ("transfer-contact"), so losing `assignOperator` alone only
  // drops the "assign operators" entry, not the whole slot (mirrors
  // `LeadsTable`'s own two-bulk-action gating, `leads-table-assign.test.tsx`).
  it('drops only the "assign operators" entry for an actor without request-management.assignOperator', () => {
    canMock.mockImplementation((permission) => permission !== 'request-management.assignOperator')
    renderTable()

    expect(capturedBulkActions?.({ ids: [ROW.id], rows: [ROW] }).map((entry) => entry.key)).toEqual([
      'transfer-contact',
    ])
  })

  it('leaves the bulk slot unwired when the actor has neither assign nor transfer ability', () => {
    canMock.mockImplementation(
      (permission) =>
        permission !== 'request-management.assignOperator' && permission !== 'request-management.transferContact',
    )
    renderTable()

    expect(capturedBulkActions).toBeNull()
  })
})

/**
 * Spec 0064: the category tab strip above the table, and its wiring into
 * `<TableView scope>`. `<TableView>` stays stubbed (framework piece outside
 * this microtask's ownership boundary here); the strip itself, and the scope
 * it produces, are the real production components.
 */
describe('RequestManagementTable category tabs (spec 0064)', () => {
  it('mounts "Tutte" selected by default, plus one tab per returned category, with no scope on the table (AC-019)', async () => {
    fetchRequestManagementCategoriesMock.mockResolvedValue([
      { id: 12, name: 'GOL - Lombardia', requests_count: 128 },
    ])
    renderTable()

    expect(await screen.findByRole('tab', { name: /GOL - Lombardia/ })).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'All' })).toHaveAttribute('aria-selected', 'true')
    expect(capturedScope).toBeUndefined()
  })

  it('scopes the table to the selected category, and clears the scope back on "Tutte" (AC-020)', async () => {
    fetchRequestManagementCategoriesMock.mockResolvedValue([
      { id: 12, name: 'GOL - Lombardia', requests_count: 128 },
    ])
    renderTable()
    const categoryTab = await screen.findByRole('tab', { name: /GOL - Lombardia/ })

    fireEvent.mouseDown(categoryTab)

    await waitFor(() => expect(capturedScope).toEqual({ productCategoryId: 12 }))

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'All' }))

    await waitFor(() => expect(capturedScope).toBeUndefined())
  })

  it('survives a remount of the page (localStorage persistence, AC-021)', async () => {
    fetchRequestManagementCategoriesMock.mockResolvedValue([
      { id: 12, name: 'GOL - Lombardia', requests_count: 128 },
    ])
    const first = renderTable()
    fireEvent.mouseDown(await screen.findByRole('tab', { name: /GOL - Lombardia/ }))
    await waitFor(() => expect(capturedScope).toEqual({ productCategoryId: 12 }))
    first.unmount()

    renderTable()

    await waitFor(() =>
      expect(screen.getByRole('tab', { name: /GOL - Lombardia/ })).toHaveAttribute('aria-selected', 'true'),
    )
    expect(capturedScope).toEqual({ productCategoryId: 12 })
  })

  it('falls back to "Tutte" without error when the persisted category is gone from the live list', async () => {
    window.localStorage.setItem('request-management.category-tab', '999')
    fetchRequestManagementCategoriesMock.mockResolvedValue([
      { id: 12, name: 'GOL - Lombardia', requests_count: 128 },
    ])

    renderTable()

    await screen.findByRole('tab', { name: /GOL - Lombardia/ })
    expect(screen.getByRole('tab', { name: 'All' })).toHaveAttribute('aria-selected', 'true')
    expect(capturedScope).toBeUndefined()
  })
})
