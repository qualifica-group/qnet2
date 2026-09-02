import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type {
  CellValueChangedEvent,
  IRowNode,
  IServerSideGetRowsParams,
} from 'ag-grid-community'
import i18n from '@/i18n'
import '@/features/imports/wizard/i18n'
import { useReviewRows } from '@/features/imports/wizard/use-review-rows'
import type { ImportRunRowItem, ImportRunRowUpdateResult } from '@/features/imports/wizard/types'

/**
 * Spec 0033 AC-023: the review grid's SSRM datasource loads staged rows, and
 * an inline edit PATCHes just the edited field, swapping the row for the
 * server's re-validated copy (or reverting it on failure). Spec 0036 AC-009:
 * choosing a duplicate row's resolution PATCHes it the same way, and errors
 * are notified via toast. Spec 0038 AC-011/014: the geo popup applies the 4
 * geo ids as a single block.
 *
 * The operator/site popup apply and combined bulk-assign coverage lives in
 * `use-review-rows-assign.test.tsx`, and the products popup apply
 * (spec 0094 AC-055) in `use-review-rows-products.test.tsx` — split out to
 * stay within the engineering size limits (`.claude/rules/engineering.md` §6).
 */

const getImportRunRowsMock = vi.fn()
const updateImportRunRowMock = vi.fn()
const resolveImportRunRowMock = vi.fn()
const bulkAssignImportRowMock = vi.fn()
const toastErrorMock = vi.fn()
const toastSuccessMock = vi.fn()

vi.mock('@/features/imports/wizard/api', () => ({
  getImportRunRows: (...args: unknown[]) => getImportRunRowsMock(...args),
  updateImportRunRow: (...args: unknown[]) => updateImportRunRowMock(...args),
  resolveImportRunRow: (...args: unknown[]) => resolveImportRunRowMock(...args),
  bulkAssignImportRow: (...args: unknown[]) => bulkAssignImportRowMock(...args),
}))

vi.mock('sonner', () => ({
  toast: {
    error: (...args: unknown[]) => toastErrorMock(...args),
    success: (...args: unknown[]) => toastSuccessMock(...args),
  },
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function wrapper(client: QueryClient = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })) {
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function rowItem(overrides: Partial<ImportRunRowItem> = {}): ImportRunRowItem {
  return {
    id: 10,
    row_number: 1,
    status: 'error',
    is_edited: false,
    duplicate_of_id: null,
    operator_id: null,
    operator: null,
    operational_site_id: null,
    operational_site: null,
    product_ids: null,
    products: [],
    values: { email: 'bad-email' },
    messages: ['Invalid email format'],
    ...overrides,
  }
}

function ssrmParams(overrides: Partial<IServerSideGetRowsParams['request']> = {}) {
  const success = vi.fn()
  const fail = vi.fn()
  return {
    params: {
      request: { startRow: 0, endRow: 25, sortModel: [], filterModel: null, ...overrides },
      success,
      fail,
    } as unknown as IServerSideGetRowsParams<ImportRunRowItem>,
    success,
    fail,
  }
}

function cellValueChangedEvent(overrides: {
  colId: string
  data: ImportRunRowItem
  oldValue: string
  newValue: string
}): CellValueChangedEvent<ImportRunRowItem> {
  return {
    column: { getColId: () => overrides.colId },
    data: overrides.data,
    oldValue: overrides.oldValue,
    newValue: overrides.newValue,
    node: { setData: vi.fn() },
  } as unknown as CellValueChangedEvent<ImportRunRowItem>
}

function duplicateRowItem(overrides: Partial<ImportRunRowItem> = {}): ImportRunRowItem {
  return rowItem({
    status: 'duplicate',
    duplicate_of_id: 5,
    duplicate_meta: { registry_id: 5, registry_name: 'Mario Rossi', lead_id: null, matched_on: ['email'] },
    resolution: null,
    messages: [],
    ...overrides,
  })
}

beforeEach(() => {
  getImportRunRowsMock.mockReset()
  updateImportRunRowMock.mockReset()
  resolveImportRunRowMock.mockReset()
  bulkAssignImportRowMock.mockReset()
  toastErrorMock.mockReset()
  toastSuccessMock.mockReset()
})

describe('useReviewRows — datasource', () => {
  it('forwards the SSRM request and resolves with rowData/rowCount', async () => {
    getImportRunRowsMock.mockResolvedValue({
      items: [rowItem()],
      pagination: { total: 1, offset: 0, limit: 25, total_pages: 1 },
    })

    const { result } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated: vi.fn() }),
      { wrapper: wrapper() },
    )

    const { params, success } = ssrmParams({ sortModel: [{ colId: 'status', sort: 'asc' }] })
    await act(async () => result.current.datasource.getRows(params))

    expect(getImportRunRowsMock).toHaveBeenCalledWith('leads', 7, {
      startRow: 0,
      endRow: 25,
      sortModel: [{ colId: 'status', sort: 'asc' }],
      filterModel: {},
    })
    expect(success).toHaveBeenCalledWith({ rowData: [rowItem()], rowCount: 1 })
  })

  it('fails the SSRM request when the API call rejects', async () => {
    getImportRunRowsMock.mockRejectedValue(new Error('network error'))

    const { result } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated: vi.fn() }),
      { wrapper: wrapper() },
    )

    const { params, fail } = ssrmParams()
    await act(async () => result.current.datasource.getRows(params))

    expect(fail).toHaveBeenCalledTimes(1)
  })
})

describe('useReviewRows — inline edit', () => {
  it('PATCHes the edited field and swaps the row for the server copy, bubbling counts', async () => {
    const updatedRow = rowItem({ status: 'valid', is_edited: true, values: { email: 'mario@example.com' }, messages: [] })
    const counts = { total: 3, valid_rows: 2, warning_rows: 0, error_rows: 0, duplicate_rows: 0, modified_rows: 1 }
    const result: ImportRunRowUpdateResult = { row: updatedRow, counts }
    updateImportRunRowMock.mockResolvedValue(result)
    const onRowUpdated = vi.fn()

    const { result: hookResult } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated }),
      { wrapper: wrapper() },
    )

    const event = cellValueChangedEvent({
      colId: 'field:email',
      data: rowItem(),
      oldValue: 'bad-email',
      newValue: 'mario@example.com',
    })

    act(() => hookResult.current.handleCellValueChanged(event))

    await waitFor(() =>
      expect(updateImportRunRowMock).toHaveBeenCalledWith('leads', 7, 10, { values: { email: 'mario@example.com' } }),
    )
    await waitFor(() => expect(event.node.setData).toHaveBeenCalledWith(updatedRow))
    expect(onRowUpdated).toHaveBeenCalledWith(updatedRow, counts)
  })

  it('reverts the cell to its previous value when the PATCH fails', async () => {
    updateImportRunRowMock.mockRejectedValue(new Error('validation failed'))

    const { result: hookResult } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated: vi.fn() }),
      { wrapper: wrapper() },
    )

    const data = rowItem()
    const event = cellValueChangedEvent({ colId: 'field:email', data, oldValue: 'bad-email', newValue: 'still-bad' })

    act(() => hookResult.current.handleCellValueChanged(event))

    await waitFor(() =>
      expect(event.node.setData).toHaveBeenCalledWith({ ...data, values: { email: 'bad-email' } }),
    )
  })

  it('does nothing for a read-only column (status)', () => {
    const onRowUpdated = vi.fn()
    const { result: hookResult } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated }),
      { wrapper: wrapper() },
    )

    const event = cellValueChangedEvent({ colId: 'status', data: rowItem(), oldValue: 'error', newValue: 'valid' })
    act(() => hookResult.current.handleCellValueChanged(event))

    expect(updateImportRunRowMock).not.toHaveBeenCalled()
    expect(onRowUpdated).not.toHaveBeenCalled()
  })

  it('does nothing when the value did not actually change', () => {
    const { result: hookResult } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated: vi.fn() }),
      { wrapper: wrapper() },
    )

    const event = cellValueChangedEvent({
      colId: 'field:email',
      data: rowItem(),
      oldValue: 'bad-email',
      newValue: 'bad-email',
    })
    act(() => hookResult.current.handleCellValueChanged(event))

    expect(updateImportRunRowMock).not.toHaveBeenCalled()
  })
})

describe('useReviewRows — resolution', () => {
  it('PATCHes the chosen resolution, swaps the row, bubbles counts and invalidates the summary query', async () => {
    const resolvedRow = duplicateRowItem({ resolution: 'update', is_edited: false })
    const counts = { total: 3, valid_rows: 1, warning_rows: 0, error_rows: 0, duplicate_rows: 1, modified_rows: 0 }
    const result: ImportRunRowUpdateResult = { row: resolvedRow, counts }
    resolveImportRunRowMock.mockResolvedValue(result)
    const onRowUpdated = vi.fn()
    const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
    const invalidateSpy = vi.spyOn(client, 'invalidateQueries')

    const { result: hookResult } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated }),
      { wrapper: wrapper(client) },
    )

    const row = duplicateRowItem()
    const node = { setData: vi.fn() } as unknown as IRowNode<ImportRunRowItem>
    act(() => hookResult.current.handleResolutionChange(row, 'update', node))

    await waitFor(() => expect(resolveImportRunRowMock).toHaveBeenCalledWith('leads', 7, 10, 'update'))
    await waitFor(() => expect(node.setData).toHaveBeenCalledWith(resolvedRow))
    expect(onRowUpdated).toHaveBeenCalledWith(resolvedRow, counts)
    expect(invalidateSpy).toHaveBeenCalledWith({
      queryKey: ['imports', 'wizard', 'leads', 7, 'summary'],
    })
  })

  it('reverts the row and notifies a toast error when the resolution PATCH fails', async () => {
    resolveImportRunRowMock.mockRejectedValue({ isAxiosError: true, response: { status: 422 } })

    const { result: hookResult } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated: vi.fn() }),
      { wrapper: wrapper() },
    )

    const row = duplicateRowItem()
    const node = { setData: vi.fn() } as unknown as IRowNode<ImportRunRowItem>
    act(() => hookResult.current.handleResolutionChange(row, 'skip', node))

    await waitFor(() => expect(node.setData).toHaveBeenCalledWith(row))
    expect(toastErrorMock).toHaveBeenCalledTimes(1)
  })
})

describe('useReviewRows — geo popup apply (spec 0038)', () => {
  it('PATCHes the 4 geo ids as a single `geo` block and swaps the row for the server copy, bubbling counts (AC-011)', async () => {
    const updatedRow = rowItem({ status: 'valid', is_edited: true, values: { country: 'Italy' }, messages: [] })
    const counts = { total: 3, valid_rows: 2, warning_rows: 0, error_rows: 0, duplicate_rows: 0, modified_rows: 1 }
    updateImportRunRowMock.mockResolvedValue({ row: updatedRow, counts })
    const onRowUpdated = vi.fn()

    const { result: hookResult } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated }),
      { wrapper: wrapper() },
    )

    const row = rowItem()
    const node = { setData: vi.fn() } as unknown as IRowNode<ImportRunRowItem>
    const geo = { country_id: 1, state_id: 10, province_id: 51, city_id: 200 }

    await act(async () => {
      await hookResult.current.handleApplyGeo(row, geo, node)
    })

    expect(updateImportRunRowMock).toHaveBeenCalledWith('leads', 7, 10, { geo })
    expect(node.setData).toHaveBeenCalledWith(updatedRow)
    expect(onRowUpdated).toHaveBeenCalledWith(updatedRow, counts)
  })

  it('rejects without touching the row when the geo PATCH fails, so the caller can surface the error (AC-014)', async () => {
    updateImportRunRowMock.mockRejectedValue({ isAxiosError: true, response: { status: 422 } })
    const onRowUpdated = vi.fn()

    const { result: hookResult } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated }),
      { wrapper: wrapper() },
    )

    const row = rowItem()
    const node = { setData: vi.fn() } as unknown as IRowNode<ImportRunRowItem>
    const geo = { country_id: 1, state_id: 10, province_id: 51, city_id: 200 }

    await expect(
      act(async () => {
        await hookResult.current.handleApplyGeo(row, geo, node)
      }),
    ).rejects.toBeTruthy()

    expect(node.setData).not.toHaveBeenCalled()
    expect(onRowUpdated).not.toHaveBeenCalled()
  })
})
