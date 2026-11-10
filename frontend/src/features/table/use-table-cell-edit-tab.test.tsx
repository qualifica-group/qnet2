import { describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { CellValueChangedEvent } from 'ag-grid-community'
import { useTableCellEdit } from '@/features/table/use-table-cell-edit'
import type { TableRow } from '@/features/table/types'

/**
 * Spec 0382 D-11: a cell edited in a category tab carries that tab in its
 * PATCH, so the server knows which positions an "Altri gestori account"
 * cell holds. Outside a tab the body is unchanged (use-table-cell-edit.test.tsx).
 */

const updateTableCellMock = vi.fn()

vi.mock('@/features/table/api', () => ({
  updateTableCell: (...args: unknown[]) => updateTableCellMock(...args),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

const ROW: TableRow = { id: 7, actions: [], editable: true, name: 'before' }

function nameChanged(): CellValueChangedEvent<TableRow> {
  return {
    column: { getColId: () => 'name' },
    data: ROW,
    oldValue: 'before',
    newValue: 'after',
    node: { setData: vi.fn() },
  } as unknown as CellValueChangedEvent<TableRow>
}

describe('useTableCellEdit category tab', () => {
  it.each([
    [{ productCategoryId: 4 }, { product_category_id: 4 }],
    [{ productCategoryBranchId: 9 }, { product_category_branch_id: 9 }],
  ])('carries the tab %o in the PATCH', async (tab, body) => {
    updateTableCellMock.mockResolvedValue({ ...ROW, name: 'after' })

    const { result } = renderHook(() => useTableCellEdit('request-management', [], undefined, tab), {
      wrapper: wrapper(),
    })

    act(() => result.current.handleCellValueChanged(nameChanged()))

    await waitFor(() =>
      expect(updateTableCellMock).toHaveBeenCalledWith('request-management', 7, {
        column: 'name',
        value: 'after',
        ...body,
      }),
    )
  })
})
