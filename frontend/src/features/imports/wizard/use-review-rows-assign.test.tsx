import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { IRowNode } from 'ag-grid-community'
import i18n from '@/i18n'
import '@/features/imports/wizard/i18n'
import {
  buildBulkAssignPayload,
  buildBulkAssignProductsPayload,
  useReviewRows,
} from '@/features/imports/wizard/use-review-rows'
import type { ImportRunRowItem } from '@/features/imports/wizard/types'

/**
 * Operator/site per-row popup apply (mirrored 1:1, spec delta: site is
 * per-row-only, no global default) and the combined bulk-assign endpoint
 * (`PATCH .../rows/assign`). Split out of `use-review-rows.test.tsx` to stay
 * within the engineering size limits (`.claude/rules/engineering.md` §6).
 */

const updateImportRunRowMock = vi.fn()
const bulkAssignImportRowMock = vi.fn()
const toastErrorMock = vi.fn()
const toastSuccessMock = vi.fn()

vi.mock('@/features/imports/wizard/api', () => ({
  getImportRunRows: vi.fn(),
  updateImportRunRow: (...args: unknown[]) => updateImportRunRowMock(...args),
  resolveImportRunRow: vi.fn(),
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

beforeEach(() => {
  updateImportRunRowMock.mockReset()
  bulkAssignImportRowMock.mockReset()
  toastErrorMock.mockReset()
  toastSuccessMock.mockReset()
})

describe('useReviewRows — operator popup apply', () => {
  it('PATCHes `operator_id` with the chosen id, swaps the row, bubbles counts and invalidates the summary query', async () => {
    const updatedRow = rowItem({ operator_id: 42, operator: { id: 42, name: 'Mario Rossi' } })
    const counts = { total: 3, valid_rows: 2, warning_rows: 0, error_rows: 0, duplicate_rows: 0, modified_rows: 1 }
    updateImportRunRowMock.mockResolvedValue({ row: updatedRow, counts })
    const onRowUpdated = vi.fn()
    const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
    const invalidateSpy = vi.spyOn(client, 'invalidateQueries')

    const { result: hookResult } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated }),
      { wrapper: wrapper(client) },
    )

    const row = rowItem()
    const node = { setData: vi.fn() } as unknown as IRowNode<ImportRunRowItem>

    await act(async () => {
      await hookResult.current.handleApplyOperator(row, 42, node)
    })

    expect(updateImportRunRowMock).toHaveBeenCalledWith('leads', 7, 10, { operator_id: 42 })
    expect(node.setData).toHaveBeenCalledWith(updatedRow)
    expect(onRowUpdated).toHaveBeenCalledWith(updatedRow, counts)
    expect(invalidateSpy).toHaveBeenCalledWith({
      queryKey: ['imports', 'wizard', 'leads', 7, 'summary'],
    })
  })

  it('PATCHes `operator_id: null` to clear a row override back to the run default', async () => {
    const clearedRow = rowItem({ operator_id: null, operator: null })
    const counts = { total: 3, valid_rows: 2, warning_rows: 0, error_rows: 0, duplicate_rows: 0, modified_rows: 1 }
    updateImportRunRowMock.mockResolvedValue({ row: clearedRow, counts })
    const onRowUpdated = vi.fn()

    const { result: hookResult } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated }),
      { wrapper: wrapper() },
    )

    const row = rowItem({ operator_id: 42, operator: { id: 42, name: 'Mario Rossi' } })
    const node = { setData: vi.fn() } as unknown as IRowNode<ImportRunRowItem>

    await act(async () => {
      await hookResult.current.handleApplyOperator(row, null, node)
    })

    expect(updateImportRunRowMock).toHaveBeenCalledWith('leads', 7, 10, { operator_id: null })
    expect(node.setData).toHaveBeenCalledWith(clearedRow)
    expect(onRowUpdated).toHaveBeenCalledWith(clearedRow, counts)
  })

  it('rejects without touching the row when the operator PATCH fails', async () => {
    updateImportRunRowMock.mockRejectedValue({ isAxiosError: true, response: { status: 422 } })
    const onRowUpdated = vi.fn()

    const { result: hookResult } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated }),
      { wrapper: wrapper() },
    )

    const row = rowItem()
    const node = { setData: vi.fn() } as unknown as IRowNode<ImportRunRowItem>

    await expect(
      act(async () => {
        await hookResult.current.handleApplyOperator(row, 42, node)
      }),
    ).rejects.toBeTruthy()

    expect(node.setData).not.toHaveBeenCalled()
    expect(onRowUpdated).not.toHaveBeenCalled()
  })
})

describe('useReviewRows — site popup apply', () => {
  it('PATCHes `operational_site_id` with the chosen id, swaps the row, bubbles counts and invalidates the summary query', async () => {
    const updatedRow = rowItem({ operational_site_id: 42, operational_site: { id: 42, name: 'Milano' } })
    const counts = { total: 3, valid_rows: 2, warning_rows: 0, error_rows: 0, duplicate_rows: 0, modified_rows: 1 }
    updateImportRunRowMock.mockResolvedValue({ row: updatedRow, counts })
    const onRowUpdated = vi.fn()
    const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
    const invalidateSpy = vi.spyOn(client, 'invalidateQueries')

    const { result: hookResult } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated }),
      { wrapper: wrapper(client) },
    )

    const row = rowItem()
    const node = { setData: vi.fn() } as unknown as IRowNode<ImportRunRowItem>

    await act(async () => {
      await hookResult.current.handleApplySite(row, 42, node)
    })

    expect(updateImportRunRowMock).toHaveBeenCalledWith('leads', 7, 10, { operational_site_id: 42 })
    expect(node.setData).toHaveBeenCalledWith(updatedRow)
    expect(onRowUpdated).toHaveBeenCalledWith(updatedRow, counts)
    expect(invalidateSpy).toHaveBeenCalledWith({
      queryKey: ['imports', 'wizard', 'leads', 7, 'summary'],
    })
  })

  it('PATCHes `operational_site_id: null` to clear a row override', async () => {
    const clearedRow = rowItem({ operational_site_id: null, operational_site: null })
    const counts = { total: 3, valid_rows: 2, warning_rows: 0, error_rows: 0, duplicate_rows: 0, modified_rows: 1 }
    updateImportRunRowMock.mockResolvedValue({ row: clearedRow, counts })
    const onRowUpdated = vi.fn()

    const { result: hookResult } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated }),
      { wrapper: wrapper() },
    )

    const row = rowItem({ operational_site_id: 42, operational_site: { id: 42, name: 'Milano' } })
    const node = { setData: vi.fn() } as unknown as IRowNode<ImportRunRowItem>

    await act(async () => {
      await hookResult.current.handleApplySite(row, null, node)
    })

    expect(updateImportRunRowMock).toHaveBeenCalledWith('leads', 7, 10, { operational_site_id: null })
    expect(node.setData).toHaveBeenCalledWith(clearedRow)
    expect(onRowUpdated).toHaveBeenCalledWith(clearedRow, counts)
  })

  it('rejects without touching the row when the site PATCH fails', async () => {
    updateImportRunRowMock.mockRejectedValue({ isAxiosError: true, response: { status: 422 } })
    const onRowUpdated = vi.fn()

    const { result: hookResult } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated }),
      { wrapper: wrapper() },
    )

    const row = rowItem()
    const node = { setData: vi.fn() } as unknown as IRowNode<ImportRunRowItem>

    await expect(
      act(async () => {
        await hookResult.current.handleApplySite(row, 42, node)
      }),
    ).rejects.toBeTruthy()

    expect(node.setData).not.toHaveBeenCalled()
    expect(onRowUpdated).not.toHaveBeenCalled()
  })
})

describe('buildBulkAssignPayload', () => {
  it('mode "single": forwards operational_site_id, mode and operator_id', () => {
    expect(
      buildBulkAssignPayload(
        { selectAll: false, toggledNodes: ['3', '7'] },
        { operational_site_id: 84, mode: 'single', operator_id: 42 },
      ),
    ).toEqual({
      operational_site_id: 84,
      mode: 'single',
      operator_id: 42,
      select_all: false,
      row_ids: [3, 7],
    })
  })

  it('mode "balanced": forwards operational_site_id and mode, no operator_id', () => {
    expect(
      buildBulkAssignPayload(
        { selectAll: false, toggledNodes: ['3', '7'] },
        { operational_site_id: 84, mode: 'balanced' },
      ),
    ).toEqual({
      operational_site_id: 84,
      mode: 'balanced',
      select_all: false,
      row_ids: [3, 7],
    })
  })

  it('maps a select-all selection to select_all: true with the excluded row ids', () => {
    expect(
      buildBulkAssignPayload(
        { selectAll: true, toggledNodes: ['9'] },
        { operational_site_id: 84, mode: 'single', operator_id: 42 },
      ),
    ).toEqual({
      operational_site_id: 84,
      mode: 'single',
      operator_id: 42,
      select_all: true,
      row_ids: [9],
    })
  })
})

describe('buildBulkAssignProductsPayload (spec 0094 bulk delta)', () => {
  it('forwards product_ids, no operator/site/mode fields', () => {
    expect(buildBulkAssignProductsPayload({ selectAll: false, toggledNodes: ['3', '7'] }, [5, 9])).toEqual({
      product_ids: [5, 9],
      select_all: false,
      row_ids: [3, 7],
    })
  })

  it('maps a select-all selection to select_all: true with the excluded row ids', () => {
    expect(buildBulkAssignProductsPayload({ selectAll: true, toggledNodes: ['9'] }, [5])).toEqual({
      product_ids: [5],
      select_all: true,
      row_ids: [9],
    })
  })
})

describe('useReviewRows — bulk assign (operator + site)', () => {
  it('PATCHes the bulk payload, invalidates the summary query and toasts success', async () => {
    bulkAssignImportRowMock.mockResolvedValue({ updated: 5 })
    const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
    const invalidateSpy = vi.spyOn(client, 'invalidateQueries')

    const { result: hookResult } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated: vi.fn() }),
      { wrapper: wrapper(client) },
    )

    const payload = { operator_id: 42, operational_site_id: 84, select_all: false, row_ids: [1, 2] }
    const result = await act(async () => hookResult.current.handleBulkAssign(payload))

    expect(bulkAssignImportRowMock).toHaveBeenCalledWith('leads', 7, payload)
    expect(result).toEqual({ updated: 5 })
    expect(invalidateSpy).toHaveBeenCalledWith({
      queryKey: ['imports', 'wizard', 'leads', 7, 'summary'],
    })
    expect(toastSuccessMock).toHaveBeenCalledTimes(1)
  })

  it('PATCHes a bulk products payload (spec 0094 bulk delta) with the SAME success/invalidation handling', async () => {
    bulkAssignImportRowMock.mockResolvedValue({ updated: 3 })
    const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
    const invalidateSpy = vi.spyOn(client, 'invalidateQueries')

    const { result: hookResult } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated: vi.fn() }),
      { wrapper: wrapper(client) },
    )

    const payload = buildBulkAssignProductsPayload({ selectAll: false, toggledNodes: ['1', '2'] }, [5])
    const result = await act(async () => hookResult.current.handleBulkAssign(payload))

    expect(bulkAssignImportRowMock).toHaveBeenCalledWith('leads', 7, payload)
    expect(result).toEqual({ updated: 3 })
    expect(invalidateSpy).toHaveBeenCalledWith({
      queryKey: ['imports', 'wizard', 'leads', 7, 'summary'],
    })
    expect(toastSuccessMock).toHaveBeenCalledTimes(1)
  })

  // Spec 0110 AC-044: `balanced` can leave rows behind when no operator of
  // the Sede is competent for them, and the feedback has to say so.
  it('names the rows left without a competent operator when the response reports some', async () => {
    bulkAssignImportRowMock.mockResolvedValue({ updated: 4, skipped: 2 })

    const { result: hookResult } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated: vi.fn() }),
      { wrapper: wrapper() },
    )

    await act(async () =>
      hookResult.current.handleBulkAssign({
        operational_site_id: 84,
        mode: 'balanced',
        select_all: false,
        row_ids: [1, 2, 3, 4, 5, 6],
      }),
    )

    expect(toastSuccessMock).toHaveBeenCalledWith(
      'Assigned to 4 row(s). 2 left without a competent operator.',
    )
  })

  it('keeps the plain success feedback when nothing was skipped', async () => {
    bulkAssignImportRowMock.mockResolvedValue({ updated: 4, skipped: 0 })

    const { result: hookResult } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated: vi.fn() }),
      { wrapper: wrapper() },
    )

    await act(async () =>
      hookResult.current.handleBulkAssign({ select_all: false, row_ids: [1], operator_id: 42 }),
    )

    expect(toastSuccessMock).toHaveBeenCalledWith('Assigned to 4 row(s).')
  })

  it('toasts an error and rejects when the bulk PATCH fails', async () => {
    bulkAssignImportRowMock.mockRejectedValue({ isAxiosError: true, response: { status: 422 } })

    const { result: hookResult } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated: vi.fn() }),
      { wrapper: wrapper() },
    )

    const payload = { operator_id: 42, select_all: true, row_ids: [] }

    await expect(
      act(async () => hookResult.current.handleBulkAssign(payload)),
    ).rejects.toBeTruthy()

    expect(toastErrorMock).toHaveBeenCalledTimes(1)
    expect(toastSuccessMock).not.toHaveBeenCalled()
  })
})
