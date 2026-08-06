import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ColDef, ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { OpportunityQuotesDetailRenderer } from '@/features/opportunities/opportunity-quotes-detail-renderer'
import type {
  TableActionDefinition,
  TableColumn,
  TableConfig,
  TableRow,
  TableRowsPayload,
  TableRowsResponse,
} from '@/features/table/types'

/**
 * The Opportunities master/detail panel (user directive 2026-08-06): expanding
 * an Opportunity row lists its Offerte with the COLUMNS AND VALUES of the
 * Offerte table, and the SAME row actions. Only the network boundary
 * (`fetchTableConfig`/`fetchTableRows`) and AG Grid itself are stubbed; the
 * config/rows hooks and the shared `useQuoteRowActions` behavior run for real.
 */

const fetchTableConfigMock = vi.fn<(domain: string) => Promise<TableConfig>>()
const fetchTableRowsMock =
  vi.fn<(domain: string, payload: TableRowsPayload) => Promise<TableRowsResponse>>()

vi.mock('@/features/table/api', () => ({
  fetchTableConfig: (...args: [string]) => fetchTableConfigMock(...args),
  fetchTableRows: (...args: [string, TableRowsPayload]) => fetchTableRowsMock(...args),
}))

// Enterprise bootstrap is a module-load side effect irrelevant to this suite.
vi.mock('@/components/data-table/ag-grid-setup', () => ({ setupAgGrid: () => {} }))

vi.mock('@/features/appearance/ui-scale-context', () => ({ useUiScale: () => ({ factor: 1 }) }))

vi.mock('@/features/activity-log/resource-activity-dialog', () => ({
  ResourceActivityDialog: () => null,
}))

/**
 * AG Grid stands in as a plain semantic table that still invokes the real
 * `cellRenderer` of every colDef — so the assertions below run against the
 * actual Offerte renderers and the actual row-actions cell, not a placeholder.
 */
vi.mock('ag-grid-react', () => ({
  AgGridReact: ({ columnDefs, rowData }: { columnDefs: ColDef[]; rowData: TableRow[] }) => (
    <table>
      <thead>
        <tr>
          {columnDefs.map((def) => (
            <th key={def.colId}>{def.headerName}</th>
          ))}
        </tr>
      </thead>
      <tbody>
        {rowData.map((row) => (
          <tr key={row.id}>
            {columnDefs.map((def) => (
              <td key={def.colId}>
                {def.cellRenderer
                  ? (def.cellRenderer as (params: ICellRendererParams) => React.ReactNode)({
                      data: row,
                      value: row[String(def.colId)],
                    } as ICellRendererParams)
                  : String(row[String(def.colId)] ?? '')}
              </td>
            ))}
          </tr>
        ))}
      </tbody>
    </table>
  ),
}))

const openViewMock = vi.fn()
vi.mock('@/features/modules/use-module-opener', () => ({
  useModuleOpener: () => ({
    openCreate: vi.fn(),
    openCreateWith: vi.fn(),
    openView: openViewMock,
    openEdit: vi.fn(),
    openDuplicate: vi.fn(),
    sheet: null,
  }),
}))

const deleteQuoteMock = vi.fn()
vi.mock('@/features/quotes/api', () => ({
  QUOTES_DOMAIN: 'quotes',
  deleteQuote: (...args: unknown[]) => deleteQuoteMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const VIEW_ACTION: TableActionDefinition = {
  key: 'view',
  label: 'actions.view',
  icon: 'eye',
  type: 'action',
  confirm: false,
}

const DELETE_ACTION: TableActionDefinition = {
  key: 'delete',
  label: 'actions.delete',
  icon: 'trash',
  type: 'danger',
  confirm: false,
}

function column(id: string, label: string, overrides: Partial<TableColumn> = {}): TableColumn {
  return {
    id,
    label,
    type: 'text',
    visible: true,
    width: null,
    order: 1,
    sortable: true,
    filterable: true,
    editable: false,
    ...overrides,
  } as TableColumn
}

const QUOTES_CONFIG = {
  columns: [column('code', 'quotes.columns.code'), column('title', 'quotes.columns.title')],
  actions: [VIEW_ACTION, DELETE_ACTION],
  searchable: [],
  advancedFilters: [],
  appliedAdvancedFilters: {},
  filterState: {},
  customized: false,
  filtersCustomized: false,
  defaultPagination: { limit: 25 },
} as unknown as TableConfig

const QUOTE_ROW: TableRow = { id: 7, code: 'OFF-0007', title: 'Fornitura uffici', actions: ['view', 'delete'] }

const OPPORTUNITY_ROW: TableRow = { id: 42, actions: [] }

function rowsResponse(items: TableRow[], total = items.length): TableRowsResponse {
  return { items, export_link: null, pagination: { total, offset: 0, limit: 25, total_pages: 1 } }
}

function renderPanel(client: QueryClient, params?: Partial<ICellRendererParams<TableRow>>) {
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ConfirmDialogProvider>
          <OpportunityQuotesDetailRenderer
            {...({ data: OPPORTUNITY_ROW, ...params } as ICellRendererParams<TableRow>)}
          />
        </ConfirmDialogProvider>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

function newClient() {
  return new QueryClient({ defaultOptions: { queries: { retry: false } } })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchTableConfigMock.mockReset()
  fetchTableRowsMock.mockReset()
  openViewMock.mockReset()
  deleteQuoteMock.mockReset()
  fetchTableConfigMock.mockResolvedValue(QUOTES_CONFIG)
})

describe('OpportunityQuotesDetailRenderer — columns and values of the Offerte table', () => {
  it('lists the opportunity’s quotes with the quotes table columns', async () => {
    fetchTableRowsMock.mockResolvedValue(rowsResponse([QUOTE_ROW]))
    renderPanel(newClient())

    await waitFor(() => expect(screen.getByText('OFF-0007')).toBeInTheDocument())

    // Columns come from the `quotes` config, not from a client-side list.
    expect(fetchTableConfigMock).toHaveBeenCalledWith('quotes', undefined)
    expect(screen.getByRole('columnheader', { name: 'Code' })).toBeInTheDocument()
    expect(screen.getByText('Fornitura uffici')).toBeInTheDocument()
  })

  it('requests the rows scoped to the expanded opportunity only', async () => {
    fetchTableRowsMock.mockResolvedValue(rowsResponse([QUOTE_ROW]))
    renderPanel(newClient())

    await waitFor(() => expect(fetchTableRowsMock).toHaveBeenCalledTimes(1))
    expect(fetchTableRowsMock).toHaveBeenCalledWith(
      'quotes',
      expect.objectContaining({ opportunityId: 42, sortModel: [], filterModel: {} }),
    )
  })

  it('does not refetch when the row collapses and re-expands', async () => {
    fetchTableRowsMock.mockResolvedValue(rowsResponse([QUOTE_ROW]))
    const client = newClient()

    const first = renderPanel(client)
    await waitFor(() => expect(screen.getByText('OFF-0007')).toBeInTheDocument())
    first.unmount()

    renderPanel(client)
    await waitFor(() => expect(screen.getByText('OFF-0007')).toBeInTheDocument())

    expect(fetchTableRowsMock).toHaveBeenCalledTimes(1)
  })

  it('reports how many quotes are hidden when the panel caps the list', async () => {
    fetchTableRowsMock.mockResolvedValue(rowsResponse([QUOTE_ROW], 40))
    renderPanel(newClient())

    await waitFor(() =>
      expect(screen.getByText('Showing 1 of 40 quotes.')).toBeInTheDocument(),
    )
  })
})

describe('OpportunityQuotesDetailRenderer — row actions stay the Offerte ones', () => {
  it('renders the quotes action catalog and routes view/delete through it', async () => {
    fetchTableRowsMock.mockResolvedValue(rowsResponse([QUOTE_ROW]))
    deleteQuoteMock.mockResolvedValue(undefined)
    renderPanel(newClient())

    await waitFor(() => expect(screen.getByText('OFF-0007')).toBeInTheDocument())

    fireEvent.click(screen.getByRole('button', { name: 'View' }))
    expect(openViewMock).toHaveBeenCalledWith(QUOTE_ROW)

    fireEvent.click(screen.getByRole('button', { name: 'Delete' }))
    await waitFor(() => expect(deleteQuoteMock).toHaveBeenCalledWith(7))
  })
})

describe('OpportunityQuotesDetailRenderer — loading, error and empty states', () => {
  it('shows an error message and a retry action instead of an infinite spinner', async () => {
    fetchTableRowsMock.mockRejectedValueOnce(new Error('network down'))
    renderPanel(newClient())

    await waitFor(() =>
      expect(
        screen.getByText('Unable to load this opportunity’s quotes.'),
      ).toBeInTheDocument(),
    )

    fetchTableRowsMock.mockResolvedValueOnce(rowsResponse([QUOTE_ROW]))
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))

    await waitFor(() => expect(screen.getByText('OFF-0007')).toBeInTheDocument())
  })

  it('shows the translated empty state when the opportunity has no quote', async () => {
    fetchTableRowsMock.mockResolvedValue(rowsResponse([]))
    renderPanel(newClient())

    await waitFor(() => expect(screen.getByText('No quotes yet')).toBeInTheDocument())
  })
})

describe('OpportunityQuotesDetailRenderer — auto-height re-measure on lazy load', () => {
  it('pushes the loaded content height back to the grid so the first expand is not clipped', async () => {
    let observed = false
    class FakeResizeObserver {
      cb: () => void
      constructor(cb: () => void) {
        this.cb = cb
      }
      observe() {
        observed = true
        this.cb()
      }
      disconnect() {}
      unobserve() {}
    }
    const original = globalThis.ResizeObserver
    globalThis.ResizeObserver = FakeResizeObserver as unknown as typeof ResizeObserver

    try {
      fetchTableRowsMock.mockResolvedValue(rowsResponse([QUOTE_ROW]))
      const setRowHeight = vi.fn()
      const onRowHeightChanged = vi.fn()

      renderPanel(newClient(), {
        node: { setRowHeight } as never,
        api: { onRowHeightChanged } as never,
      })

      await waitFor(() => expect(screen.getByText('OFF-0007')).toBeInTheDocument())

      expect(observed).toBe(true)
      expect(setRowHeight).toHaveBeenCalled()
      expect(onRowHeightChanged).toHaveBeenCalled()
    } finally {
      globalThis.ResizeObserver = original
    }
  })
})
