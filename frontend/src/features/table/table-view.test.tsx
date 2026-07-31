import type { ComponentProps, ReactNode } from 'react'
import { act, render, screen, waitFor } from '@testing-library/react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { IServerSideDatasource, IServerSideGetRowsParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { TooltipProvider } from '@/components/ui/tooltip'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { UiScaleContext } from '@/features/appearance/ui-scale-context'
import { TableView } from '@/features/table/table-view'
import { fetchTableConfig, fetchTableRows } from '@/features/table/api'
import type { TableConfig, TableRow } from '@/features/table/types'

/**
 * Spec 0067 F1: the two additive `TableView` props (`rowScope`,
 * `onRowCountChanged`). `DataTable` (AG Grid) and `ExportDialog` (own suite:
 * `export-dialog.test.tsx`) are stubbed so this suite exercises only
 * `TableView`'s OWN wiring — capturing exactly the props it hands them —
 * without mounting the real grid (jsdom has no AG Grid runtime).
 */

const dataTablePropsSpy = vi.fn()
const exportDialogPropsSpy = vi.fn()

interface CapturedDataTableProps {
  domain: string
  opportunityId?: number
  datasource: IServerSideDatasource<TableRow>
  onRowCountChanged?: (count: number) => void
}

vi.mock('@/components/data-table/data-table', () => ({
  ACTIONS_COLUMN_ID: '__actions',
  DataTable: (props: CapturedDataTableProps) => {
    dataTablePropsSpy(props)
    return <div role="grid" />
  },
}))

vi.mock('@/features/exports/export-dialog', () => ({
  ExportDialog: (props: Record<string, unknown>) => {
    exportDialogPropsSpy(props)
    return null
  },
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: () => true,
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

vi.mock('@/features/table/api', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/features/table/api')>()
  return { ...actual, fetchTableConfig: vi.fn(), fetchTableRows: vi.fn() }
})

const fetchTableConfigMock = vi.mocked(fetchTableConfig)
const fetchTableRowsMock = vi.mocked(fetchTableRows)

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
}

/** Builds a minimal SSRM `getRows` params stub, mirroring `ssrm-datasource.test.ts`. */
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

/** `TableView` sizes its grid off the UI scale, so the context is mandatory. */
function withProviders(client: QueryClient, node: ReactNode) {
  return (
    <QueryClientProvider client={client}>
      <UiScaleContext.Provider value={{ scale: 40, factor: 1, setScale: vi.fn() }}>
        <TooltipProvider>
          <ConfirmDialogProvider>{node}</ConfirmDialogProvider>
        </TooltipProvider>
      </UiScaleContext.Provider>
    </QueryClientProvider>
  )
}

function renderTableView(props: Partial<ComponentProps<typeof TableView>> = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(withProviders(client, <TableView domain="quotes" onAction={vi.fn()} {...props} />))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  dataTablePropsSpy.mockReset()
  exportDialogPropsSpy.mockReset()
  fetchTableConfigMock.mockReset().mockResolvedValue(CONFIG)
  fetchTableRowsMock.mockReset().mockResolvedValue({
    items: [],
    export_link: null,
    pagination: { total: 0, offset: 0, limit: 25, total_pages: 0 },
  })
})

describe('TableView — rowScope (spec 0067 D-1)', () => {
  // AC-072: the mandatory non-regression — every existing caller (no
  // `rowScope`, no `onRowCountChanged`) keeps sending byte-identical payloads.
  it('omits opportunityId from the rows, values-chain and export props when rowScope is not given', async () => {
    renderTableView()
    await screen.findByRole('grid')

    const dataTableProps = dataTablePropsSpy.mock.calls.at(-1)?.[0] as CapturedDataTableProps
    expect(dataTableProps.opportunityId).toBeUndefined()

    await dataTableProps.datasource.getRows(stubParams())
    expect(fetchTableRowsMock).toHaveBeenCalledTimes(1)
    expect(fetchTableRowsMock.mock.calls[0][1]).not.toHaveProperty('opportunityId')

    await waitFor(() => expect(exportDialogPropsSpy).toHaveBeenCalled())
    const exportProps = exportDialogPropsSpy.mock.calls.at(-1)?.[0] as { opportunityId?: number }
    expect(exportProps.opportunityId).toBeUndefined()
  })

  // AC-030 (rows part) / AC-071: rowScope reaches the SSRM rows request AND
  // the DataTable's opportunityId twin prop (feeding the Set Filter values
  // chain, unit-tested end to end in column-filters.test.ts).
  it('forwards rowScope.opportunityId into the rows request and the DataTable prop', async () => {
    renderTableView({ rowScope: { opportunityId: 7 } })
    await screen.findByRole('grid')

    const dataTableProps = dataTablePropsSpy.mock.calls.at(-1)?.[0] as CapturedDataTableProps
    expect(dataTableProps.opportunityId).toBe(7)

    await dataTableProps.datasource.getRows(stubParams())
    expect(fetchTableRowsMock).toHaveBeenCalledWith(
      'quotes',
      expect.objectContaining({ opportunityId: 7 }),
    )
  })

  // AC-070: rowScope reaches the export payload via the ExportDialog prop
  // (create-payload assembly unit-tested in export-dialog.test.tsx).
  it('forwards rowScope.opportunityId into the ExportDialog prop', async () => {
    renderTableView({ rowScope: { opportunityId: 7 } })
    await screen.findByRole('grid')

    await waitFor(() => expect(exportDialogPropsSpy).toHaveBeenCalled())
    const exportProps = exportDialogPropsSpy.mock.calls.at(-1)?.[0] as { opportunityId?: number }
    expect(exportProps.opportunityId).toBe(7)
  })

  // Reads `rowScope` as a primitive: a fresh object literal every render must
  // not rebuild the datasource (same precaution as `scope`, table-view.tsx).
  it('does not rebuild the datasource when rowScope is a fresh object with the same opportunityId', async () => {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    const { rerender } = render(
      withProviders(
        client,
        <TableView domain="quotes" onAction={vi.fn()} rowScope={{ opportunityId: 7 }} />,
      ),
    )
    await screen.findByRole('grid')
    const firstDatasource = (dataTablePropsSpy.mock.calls.at(-1)?.[0] as CapturedDataTableProps)
      .datasource

    rerender(
      withProviders(
        client,
        <TableView domain="quotes" onAction={vi.fn()} rowScope={{ opportunityId: 7 }} />,
      ),
    )
    await waitFor(() => expect(dataTablePropsSpy.mock.calls.length).toBeGreaterThan(1))
    const secondDatasource = (dataTablePropsSpy.mock.calls.at(-1)?.[0] as CapturedDataTableProps)
      .datasource

    expect(secondDatasource).toBe(firstDatasource)
  })
})

describe('TableView — onRowCountChanged (spec 0067 D-9)', () => {
  it('composes with the toolbar counter: both update on the same grid event', async () => {
    const onRowCountChanged = vi.fn()
    renderTableView({ onRowCountChanged })
    await screen.findByRole('grid')

    const dataTableProps = dataTablePropsSpy.mock.calls.at(-1)?.[0] as CapturedDataTableProps
    act(() => {
      dataTableProps.onRowCountChanged?.(5)
    })

    expect(onRowCountChanged).toHaveBeenCalledWith(5)
    expect(await screen.findByText('5 rows')).toBeInTheDocument()
  })

  // AC-072: omitted, the toolbar counter still works exactly as before.
  it('the toolbar counter keeps working when onRowCountChanged is not given', async () => {
    renderTableView()
    await screen.findByRole('grid')

    const dataTableProps = dataTablePropsSpy.mock.calls.at(-1)?.[0] as CapturedDataTableProps
    act(() => {
      dataTableProps.onRowCountChanged?.(3)
    })

    expect(await screen.findByText('3 rows')).toBeInTheDocument()
  })
})
