import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { IRowNode } from 'ag-grid-community'
import i18n from '@/i18n'
import '@/features/imports/wizard/i18n'
import { useReviewRows } from '@/features/imports/wizard/use-review-rows'
import type { ImportRunRowItem } from '@/features/imports/wizard/types'

/**
 * Spec 0094 AC-055: the products popup apply (`PATCH .../rows/{row}` with
 * `product_ids`), mirroring `handleApplySite` — `null` reverts a row
 * override back to the run's global default, `[]` is a distinct explicit
 * "no products on this row". Split out of `use-review-rows.test.tsx` to stay
 * within the engineering size limits (`.claude/rules/engineering.md` §6),
 * mirroring `use-review-rows-assign.test.tsx`.
 */

const updateImportRunRowMock = vi.fn()

vi.mock('@/features/imports/wizard/api', () => ({
  getImportRunRows: vi.fn(),
  updateImportRunRow: (...args: unknown[]) => updateImportRunRowMock(...args),
  resolveImportRunRow: vi.fn(),
  bulkAssignImportRow: vi.fn(),
}))

vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function rowItem(overrides: Partial<ImportRunRowItem> = {}): ImportRunRowItem {
  return {
    id: 10,
    row_number: 1,
    status: 'valid',
    is_edited: false,
    duplicate_of_id: null,
    operator_id: null,
    operator: null,
    operational_site_id: null,
    operational_site: null,
    product_ids: null,
    products: [],
    values: {},
    messages: [],
    ...overrides,
  }
}

beforeEach(() => {
  updateImportRunRowMock.mockReset()
})

describe('useReviewRows — products popup apply (spec 0094 AC-055)', () => {
  it('PATCHes `product_ids` and swaps the row for the server copy, bubbling counts', async () => {
    const updatedRow = rowItem({ product_ids: [5], products: [{ id: 5, label: 'Widget' }] })
    const counts = { total: 3, valid_rows: 2, warning_rows: 0, error_rows: 0, duplicate_rows: 0, modified_rows: 1 }
    updateImportRunRowMock.mockResolvedValue({ row: updatedRow, counts })
    const onRowUpdated = vi.fn()

    const { result: hookResult } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated }),
      { wrapper: wrapper() },
    )

    const row = rowItem()
    const node = { setData: vi.fn() } as unknown as IRowNode<ImportRunRowItem>

    await act(async () => {
      await hookResult.current.handleApplyProducts(row, [5], node)
    })

    expect(updateImportRunRowMock).toHaveBeenCalledWith('leads', 7, 10, { product_ids: [5] })
    expect(node.setData).toHaveBeenCalledWith(updatedRow)
    expect(onRowUpdated).toHaveBeenCalledWith(updatedRow, counts)
  })

  it('PATCHes `product_ids: null` to revert a row override back to the run global default', async () => {
    const revertedRow = rowItem({ product_ids: null, products: [] })
    const counts = { total: 3, valid_rows: 2, warning_rows: 0, error_rows: 0, duplicate_rows: 0, modified_rows: 0 }
    updateImportRunRowMock.mockResolvedValue({ row: revertedRow, counts })

    const { result: hookResult } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated: vi.fn() }),
      { wrapper: wrapper() },
    )

    const row = rowItem({ product_ids: [5], products: [{ id: 5, label: 'Widget' }] })
    const node = { setData: vi.fn() } as unknown as IRowNode<ImportRunRowItem>

    await act(async () => {
      await hookResult.current.handleApplyProducts(row, null, node)
    })

    expect(updateImportRunRowMock).toHaveBeenCalledWith('leads', 7, 10, { product_ids: null })
  })

  it('PATCHes `product_ids: []` as an explicit "no products" override, distinct from `null`', async () => {
    const clearedRow = rowItem({ product_ids: [], products: [] })
    const counts = { total: 3, valid_rows: 2, warning_rows: 0, error_rows: 0, duplicate_rows: 0, modified_rows: 1 }
    updateImportRunRowMock.mockResolvedValue({ row: clearedRow, counts })

    const { result: hookResult } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated: vi.fn() }),
      { wrapper: wrapper() },
    )

    const row = rowItem({ product_ids: [5], products: [{ id: 5, label: 'Widget' }] })
    const node = { setData: vi.fn() } as unknown as IRowNode<ImportRunRowItem>

    await act(async () => {
      await hookResult.current.handleApplyProducts(row, [], node)
    })

    expect(updateImportRunRowMock).toHaveBeenCalledWith('leads', 7, 10, { product_ids: [] })
  })

  it('rejects without touching the row when the PATCH fails, so the caller can surface the error', async () => {
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
        await hookResult.current.handleApplyProducts(row, [5], node)
      }),
    ).rejects.toBeTruthy()

    expect(node.setData).not.toHaveBeenCalled()
    expect(onRowUpdated).not.toHaveBeenCalled()
  })
})
