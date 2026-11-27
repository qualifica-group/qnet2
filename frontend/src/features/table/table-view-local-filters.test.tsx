import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { IServerSideDatasource, IServerSideGetRowsParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { TooltipProvider } from '@/components/ui/tooltip'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { UiScaleContext } from '@/features/appearance/ui-scale-context'
import { AuthContext, type AuthContextValue } from '@/features/auth/auth-context'
import type { User } from '@/features/auth/types'
import { TableView } from '@/features/table/table-view'
import { fetchTableConfig, fetchTableRows } from '@/features/table/api'
import { tableLocalFiltersKey } from '@/features/table/table-local-filters-storage'
import { advancedFiltersOpenKey } from '@/features/table/table-advanced-filters-open-storage'
import type { FilterRules, TableConfig, TableRow } from '@/features/table/types'

/**
 * The quick search and the active custom filter survive a reload: TableView
 * restores them from the browser at mount (so the FIRST rows request is
 * already filtered) and writes them back on every change. `DataTable` is
 * stubbed, as in `table-view.test.tsx`: jsdom has no AG Grid runtime.
 */

const dataTablePropsSpy = vi.fn()

interface CapturedDataTableProps {
  datasource: IServerSideDatasource<TableRow>
}

vi.mock('@/components/data-table/data-table', () => ({
  ACTIONS_COLUMN_ID: '__actions',
  DataTable: (props: CapturedDataTableProps) => {
    dataTablePropsSpy(props)
    return <div role="grid" />
  },
}))

vi.mock('@/features/exports/export-dialog', () => ({ ExportDialog: () => null }))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/features/table/api', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/features/table/api')>()
  return { ...actual, fetchTableConfig: vi.fn(), fetchTableRows: vi.fn(), saveTableFilters: vi.fn() }
})

const fetchTableConfigMock = vi.mocked(fetchTableConfig)
const fetchTableRowsMock = vi.mocked(fetchTableRows)

const USER_ID = 7
const KEY = tableLocalFiltersKey({ userId: USER_ID, domain: 'quotes' })
const RULES: FilterRules = { and: [{ field: 'code', operator: 'contains', value: 'Q-' }], or: [] }

const CONFIG: TableConfig = {
  resource: 'quotes',
  columns: [
    {
      id: 'code',
      label: 'quotes.columns.code',
      type: 'text',
      visible: true,
      width: null,
      order: 0,
      sortable: true,
      filterable: true,
    },
  ],
  filters: [],
  actions: [],
  defaultSort: [],
  defaultPagination: { limit: 25 },
  customized: false,
  searchable: ['code'],
}

const AUTH = { user: { id: USER_ID } as User, isAuthenticated: true } as AuthContextValue

function stubParams(): IServerSideGetRowsParams<TableRow> {
  return {
    request: {
      startRow: 0,
      endRow: 25,
      rowGroupCols: [],
      valueCols: [],
      pivotCols: [],
      pivotMode: false,
      groupKeys: [],
      filterModel: null,
      sortModel: [],
    },
    success: vi.fn(),
    fail: vi.fn(),
  } as unknown as IServerSideGetRowsParams<TableRow>
}

function Providers({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return (
    <QueryClientProvider client={client}>
      <AuthContext.Provider value={AUTH}>
        <UiScaleContext.Provider value={{ scale: 40, factor: 1, setScale: vi.fn() }}>
          <TooltipProvider>
            <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
          </TooltipProvider>
        </UiScaleContext.Provider>
      </AuthContext.Provider>
    </QueryClientProvider>
  )
}

function renderTableView(pinnedFilters = false) {
  render(
    <Providers>
      <TableView domain="quotes" onAction={vi.fn()} pinnedFilters={pinnedFilters} />
    </Providers>,
  )
}

async function firstRowsRequest() {
  await screen.findByRole('grid')
  const { datasource } = dataTablePropsSpy.mock.calls.at(-1)?.[0] as CapturedDataTableProps
  await datasource.getRows(stubParams())
  return fetchTableRowsMock.mock.calls.at(-1)?.[1]
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  window.localStorage.clear()
  dataTablePropsSpy.mockReset()
  fetchTableConfigMock.mockReset().mockResolvedValue(CONFIG)
  fetchTableRowsMock.mockReset().mockResolvedValue({
    items: [],
    export_link: null,
    pagination: { total: 0, offset: 0, limit: 25, total_pages: 0 },
  })
})

describe('TableView — filters kept across a reload', () => {
  it('restores the stored search into the box and the first rows request', async () => {
    window.localStorage.setItem(KEY, JSON.stringify({ search: 'rossi', customFilter: null }))
    renderTableView()

    expect(await firstRowsRequest()).toEqual(expect.objectContaining({ search: 'rossi' }))
    expect(screen.getByRole('searchbox')).toHaveValue('rossi')
  })

  // User request 2026-11-27 (the Commesse page): pinned filters replace the quick search.
  it('has no quick search with pinned filters, nor sends a stored term it could not clear', async () => {
    window.localStorage.setItem(KEY, JSON.stringify({ search: 'rossi', customFilter: null }))
    renderTableView(true)

    expect(await firstRowsRequest()).not.toHaveProperty('search')
    expect(screen.queryByRole('searchbox')).not.toBeInTheDocument()
  })

  // User request 2026-11-27: the funnel beside the three dots shows/hides the pinned filters, kept open across visits.
  it('shows and hides pinned filters from the funnel, keeping the choice for the next visit', async () => {
    fetchTableConfigMock.mockResolvedValue({
      ...CONFIG,
      advancedFilters: [
        { name: 'title', label: 'Title', type: 'text', order: 1, required: false, visible: true, width: 'md', multiple: false },
      ],
    })
    renderTableView(true)

    const funnel = await screen.findByRole('button', { name: 'Show filters' })
    expect(screen.queryByRole('textbox', { name: 'Title' })).not.toBeInTheDocument()

    fireEvent.click(funnel)

    expect(await screen.findByRole('textbox', { name: 'Title' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Hide filters' })).toHaveAttribute('aria-pressed', 'true')
    expect(window.localStorage.getItem(advancedFiltersOpenKey(USER_ID, 'quotes'))).toBe('1')
  })

  it('puts the applied-filter chips of pinned filters in the table header, not in a row of their own', async () => {
    window.localStorage.setItem(KEY, JSON.stringify({ search: '', customFilter: { rules: RULES, viewId: 3, name: 'Mine' } }))
    fetchTableConfigMock.mockResolvedValue({
      ...CONFIG,
      advancedFilters: [
        { name: 'title', label: 'Title', type: 'text', order: 1, required: false, visible: true, width: 'md', multiple: false },
      ],
    })
    renderTableView(true)

    const header = (await screen.findByRole('button', { name: 'Show filters' })).parentElement?.parentElement
    const chips = await screen.findAllByRole('list', { name: 'Active filters' })

    expect(chips).toHaveLength(1)
    expect(header).toContainElement(chips[0])
    expect(header).toContainElement(screen.getByRole('button', { name: 'Clear all' }))
  })

  it('restores the stored custom filter into the first rows request', async () => {
    window.localStorage.setItem(
      KEY,
      JSON.stringify({ search: '', customFilter: { rules: RULES, viewId: 3, name: 'Mine' } }),
    )
    renderTableView()

    expect(await firstRowsRequest()).toEqual(expect.objectContaining({ customFilterRules: RULES }))
  })

  it('sends no search or custom filter when nothing is stored', async () => {
    renderTableView()

    const request = await firstRowsRequest()
    expect(request).not.toHaveProperty('search')
    expect(request).not.toHaveProperty('customFilterRules')
  })

  it('does not apply filters stored by another user on the same browser', async () => {
    window.localStorage.setItem(
      tableLocalFiltersKey({ userId: USER_ID + 1, domain: 'quotes' }),
      JSON.stringify({ search: 'rossi', customFilter: null }),
    )
    renderTableView()

    expect(await firstRowsRequest()).not.toHaveProperty('search')
  })

  it('does not send a restored term shorter than the domain minimum (spec 0179)', async () => {
    fetchTableConfigMock.mockResolvedValue({ ...CONFIG, searchMinLength: 3 })
    window.localStorage.setItem(KEY, JSON.stringify({ search: 'ro', customFilter: null }))
    renderTableView()

    expect(await firstRowsRequest()).not.toHaveProperty('search')
    expect(screen.getByRole('searchbox')).toHaveAttribute(
      'placeholder',
      expect.stringContaining('(min. 3 characters)'),
    )
  })

  it('stores the typed search and removes the entry once it is cleared', async () => {
    renderTableView()
    const box = await screen.findByRole('searchbox')

    fireEvent.change(box, { target: { value: 'bianchi' } })
    await waitFor(() =>
      expect(JSON.parse(window.localStorage.getItem(KEY) ?? '{}')).toEqual({
        search: 'bianchi',
        customFilter: null,
      }),
    )

    fireEvent.change(box, { target: { value: '' } })
    await waitFor(() => expect(window.localStorage.getItem(KEY)).toBeNull())
  })
})
