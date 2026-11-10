import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { AxiosError, AxiosHeaders } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ColumnState, GridApi } from 'ag-grid-community'
import i18n from '@/i18n'
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { AgentSalesReportExportMenu } from '@/features/agent-sales-report/export-menu'
import type { TableExportMenuContext } from '@/features/table/table-view-props'
import type { TableRow } from '@/features/table/types'

/**
 * Spec 0365 AC-015: the three-entry export menu. Sommario opens the generic
 * dialog; Dettagli and Righe dettaglio post the live grid state to their
 * endpoint and a 422 surfaces as a toast. The download call is mocked.
 */

const downloadMock = vi.fn()
const toastError = vi.fn()

vi.mock('sonner', () => ({ toast: { error: (...args: unknown[]) => toastError(...args) } }))
vi.mock('@/features/agent-sales-report/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/agent-sales-report/api')>()),
  downloadAgentSalesReportExport: (...args: unknown[]) => downloadMock(...args),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  downloadMock.mockReset()
  toastError.mockReset()
})

function stubGridApi(): GridApi<TableRow> {
  const columnState: ColumnState[] = [
    { colId: 'code', hide: false, sort: 'asc', sortIndex: 0 },
    { colId: 'title', hide: false },
  ]
  return {
    getColumnState: () => columnState,
    getFilterModel: () => ({ registry: { filterType: 'set', values: ['Acme'] } }),
  } as unknown as GridApi<TableRow>
}

function renderMenu(openGenericExport = vi.fn()) {
  const context: TableExportMenuContext = {
    gridApi: stubGridApi(),
    columns: [
      { id: 'code', label: 'agentSalesReport.columns.code', type: 'text', visible: true, width: null, order: 0, sortable: true, filterable: true },
      { id: 'title', label: 'agentSalesReport.columns.title', type: 'text', visible: true, width: null, order: 1, sortable: true, filterable: true },
    ],
    search: ' rossi ',
    advancedFilters: {},
    customFilterRules: null,
    openGenericExport,
  }
  const client = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
  render(
    <DropdownMenu open>
      <DropdownMenuTrigger>options</DropdownMenuTrigger>
      <DropdownMenuContent>
        <AgentSalesReportExportMenu context={context} />
      </DropdownMenuContent>
    </DropdownMenu>,
    { wrapper },
  )
  return { openGenericExport }
}

describe('AgentSalesReportExportMenu (spec 0365)', () => {
  it('lists the three export entries and the summary opens the generic export', () => {
    const { openGenericExport } = renderMenu()

    expect(screen.getByRole('menuitem', { name: 'Summary' })).toBeInTheDocument()
    expect(screen.getByRole('menuitem', { name: 'Details' })).toBeInTheDocument()
    expect(screen.getByRole('menuitem', { name: 'Detail lines' })).toBeInTheDocument()

    fireEvent.click(screen.getByRole('menuitem', { name: 'Summary' }))
    expect(openGenericExport).toHaveBeenCalledTimes(1)
    expect(downloadMock).not.toHaveBeenCalled()
  })

  it.each([
    ['Details', 'details'],
    ['Detail lines', 'lines'],
  ])('%s downloads from its endpoint with the live grid state', async (label, kind) => {
    downloadMock.mockResolvedValue(undefined)
    renderMenu()

    fireEvent.click(screen.getByRole('menuitem', { name: label }))

    await waitFor(() => expect(downloadMock).toHaveBeenCalledTimes(1))
    expect(downloadMock).toHaveBeenCalledWith(kind, {
      sortModel: [{ colId: 'code', sort: 'asc' }],
      filterModel: { registry: { filterType: 'set', values: ['Acme'] } },
      search: 'rossi',
      advancedFilters: {},
      customFilterRules: null,
    })
  })

  it('shows the server message (already translated) when the server answers 422', async () => {
    const body = new Blob([JSON.stringify({ success: false, message: 'Too many quotes to export.' })])
    downloadMock.mockRejectedValue(
      new AxiosError('fail', 'ERR_BAD_REQUEST', undefined, undefined, {
        status: 422,
        statusText: 'Unprocessable',
        data: body,
        headers: {},
        config: { headers: new AxiosHeaders() },
      }),
    )
    renderMenu()

    fireEvent.click(screen.getByRole('menuitem', { name: 'Details' }))

    await waitFor(() =>
      expect(toastError).toHaveBeenCalledWith('Too many quotes to export.'),
    )
  })

  it('shows a generic error for any other failure', async () => {
    downloadMock.mockRejectedValue(new Error('boom'))
    renderMenu()

    fireEvent.click(screen.getByRole('menuitem', { name: 'Detail lines' }))

    await waitFor(() => expect(toastError).toHaveBeenCalledWith('Export failed. Try again.'))
  })
})
