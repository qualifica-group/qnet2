import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ColumnState, GridApi } from 'ag-grid-community'
import i18n from '@/i18n'
import { ExportDialog } from '@/features/exports/export-dialog'
import type { ExportRun } from '@/features/exports/types'
import type { TableColumn, TableRow } from '@/features/table/types'

/**
 * Spec 0014 AC-010: the generic export dialog, driven entirely through the
 * frozen `/exports/{domain}` contract. The API module is mocked (same
 * convention as `import-dialog.test.tsx`); every assertion queries by
 * accessible role/text, never `data-testid`.
 */

const createExportMock = vi.fn()
const getExportRunMock = vi.fn()
const downloadExportMock = vi.fn()

vi.mock('@/features/exports/api', () => ({
  createExport: (...args: unknown[]) => createExportMock(...args),
  getExportRun: (...args: unknown[]) => getExportRunMock(...args),
  downloadExport: (...args: unknown[]) => downloadExportMock(...args),
}))

const ACTIONS_COLUMN_ID = '__actions'

const COLUMNS: TableColumn[] = [
  {
    id: 'name',
    label: 'companies.columns.denomination',
    type: 'text',
    visible: true,
    width: null,
    order: 0,
    sortable: true,
    filterable: true,
  },
]

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  downloadExportMock.mockResolvedValue(undefined)
})

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function stubGridApi(
  columnState: ColumnState[],
  filterModel: Record<string, unknown> = {},
): GridApi<TableRow> {
  return {
    getColumnState: () => columnState,
    getFilterModel: () => filterModel,
  } as unknown as GridApi<TableRow>
}

function baseRun(overrides: Partial<ExportRun> = {}): ExportRun {
  return {
    id: 1,
    resource: 'companies',
    status: 'processing',
    format: 'csv',
    original_filename: 'companies-2026-07-03.csv',
    row_count: null,
    has_file: false,
    created_at: '2026-07-03T00:00:00Z',
    ...overrides,
  }
}

function renderDialog(
  gridApi: GridApi<TableRow> | null = stubGridApi([{ colId: 'name', hide: false } as ColumnState]),
  onOpenChange = vi.fn(),
  opportunityId?: number | null,
) {
  render(
    <ExportDialog
      domain="companies"
      open
      onOpenChange={onOpenChange}
      gridApi={gridApi}
      columns={COLUMNS}
      actionsColumnId={ACTIONS_COLUMN_ID}
      search=""
      opportunityId={opportunityId}
    />,
    { wrapper: wrapper() },
  )
}

describe('ExportDialog', () => {
  it('creates the export with the chosen format and current grid state, then polls to completed (AC-010)', async () => {
    createExportMock.mockResolvedValue(baseRun({ status: 'processing' }))
    getExportRunMock.mockResolvedValue(
      baseRun({ status: 'completed', row_count: 42, has_file: true }),
    )

    renderDialog()

    fireEvent.click(screen.getByRole('radio', { name: /excel/i }))
    fireEvent.click(screen.getByRole('button', { name: /^export$/i }))

    await waitFor(() =>
      expect(createExportMock).toHaveBeenCalledWith('companies', {
        format: 'xlsx',
        columns: [{ colId: 'name', header: 'Denomination' }],
        sortModel: undefined,
        filterModel: undefined,
        search: undefined,
      }),
    )

    expect(await screen.findByText('Completed', {}, { timeout: 3000 })).toBeInTheDocument()
    expect(screen.getByText('42 rows exported')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /download file/i })).toBeInTheDocument()
  }, 10000)

  it('downloads the generated file once the run is completed', async () => {
    createExportMock.mockResolvedValue(baseRun({ status: 'processing' }))
    getExportRunMock.mockResolvedValue(
      baseRun({ status: 'completed', row_count: 5, has_file: true }),
    )

    renderDialog()
    fireEvent.click(screen.getByRole('button', { name: /^export$/i }))

    await screen.findByRole('button', { name: /download file/i }, { timeout: 3000 })
    fireEvent.click(screen.getByRole('button', { name: /download file/i }))

    await waitFor(() => expect(downloadExportMock).toHaveBeenCalledWith('companies', 1))
  }, 10000)

  it('surfaces a localized error on a 403 create response (AC-002)', async () => {
    createExportMock.mockRejectedValue(
      new AxiosError('Forbidden', '403', undefined, undefined, {
        status: 403,
        data: { success: false, message: 'Forbidden' },
      } as never),
    )

    renderDialog()
    fireEvent.click(screen.getByRole('button', { name: /^export$/i }))

    expect(await screen.findByRole('alert')).toHaveTextContent(
      "You don't have permission to export this data.",
    )
  })

  it('surfaces a localized error on a 422 create response (AC-004)', async () => {
    createExportMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Invalid' },
      } as never),
    )

    renderDialog()
    fireEvent.click(screen.getByRole('button', { name: /^export$/i }))

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'The export request could not be validated. Please try again.',
    )
  })

  it('disables the export button while the grid api is not ready yet', () => {
    renderDialog(null)

    expect(screen.getByRole('button', { name: /^export$/i })).toBeDisabled()
  })

  // Spec 0067 D-5/AC-070: the Opportunity detail's Quotes panel scopes the
  // export payload with the row-set scope it was mounted with.
  it('includes opportunityId in the create payload when given (spec 0067)', async () => {
    createExportMock.mockResolvedValue(baseRun({ status: 'processing' }))

    renderDialog(
      stubGridApi([{ colId: 'name', hide: false } as ColumnState]),
      vi.fn(),
      7,
    )
    fireEvent.click(screen.getByRole('button', { name: /^export$/i }))

    await waitFor(() =>
      expect(createExportMock).toHaveBeenCalledWith(
        'companies',
        expect.objectContaining({ opportunityId: 7 }),
      ),
    )
  })
})
