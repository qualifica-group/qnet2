import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { CellValueChangedEvent } from 'ag-grid-community'
import { useTableCellEdit } from '@/features/table/use-table-cell-edit'
import type { TableColumn, TableRow } from '@/features/table/types'

/**
 * Spec 0404: a row carrying `<columnId>_notes` (array of values) decides alone
 * whether the picked value needs a note, row-scoped like `<columnId>_options`.
 * Own file: `use-table-cell-edit.test.tsx` is at the file-size limit.
 */

const updateTableCellMock = vi.fn()

vi.mock('@/features/table/api', () => ({
  updateTableCell: (...args: unknown[]) => updateTableCellMock(...args),
}))

vi.mock('sonner', () => ({ toast: { error: vi.fn() } }))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function row(overrides: Partial<TableRow> = {}): TableRow {
  return { id: 7, actions: [], editable: true, ...overrides }
}

function cellValueChangedEvent(overrides: {
  colId: string
  data: TableRow
  oldValue: unknown
  newValue: unknown
}): CellValueChangedEvent<TableRow> {
  return {
    column: { getColId: () => overrides.colId },
    data: overrides.data,
    oldValue: overrides.oldValue,
    newValue: overrides.newValue,
    node: { setData: vi.fn() },
  } as unknown as CellValueChangedEvent<TableRow>
}

beforeEach(() => {
  updateTableCellMock.mockReset()
})

describe('useTableCellEdit — requires_note from the row (`<columnId>_notes`)', () => {
  /** An options column that says "no note" everywhere: only the row's `_notes` may decide. */
  function systemStatusColumn(): TableColumn {
    return {
      id: 'quote_system_status',
      label: 'Status',
      type: 'text',
      visible: true,
      width: null,
      order: 0,
      sortable: true,
      filterable: true,
      editor: 'select',
      options: [
        { value: 1, label: 'Aperto', requires_note: false },
        { value: 4, label: 'Vinto', requires_note: false },
      ],
    }
  }

  it('holds the PATCH back when the picked value is in the row notes', () => {
    const { result } = renderHook(() => useTableCellEdit('quotes', [systemStatusColumn()]), { wrapper: wrapper() })
    const event = cellValueChangedEvent({
      colId: 'quote_system_status',
      data: row({ quote_system_status: { id: 1, name: 'Aperto' }, quote_system_status_notes: [4] }),
      oldValue: { id: 1, name: 'Aperto' },
      newValue: { id: 4, name: 'Vinto' },
    })

    act(() => result.current.handleCellValueChanged(event))

    expect(updateTableCellMock).not.toHaveBeenCalled()
    expect(result.current.noteDialogSlot).not.toBeNull()
  })

  it('PATCHes straight away when the value is not in the row notes, whatever the column options say', async () => {
    updateTableCellMock.mockResolvedValue(row())
    const column: TableColumn = {
      ...systemStatusColumn(),
      options: [{ value: 4, label: 'Vinto', requires_note: true }],
    }
    const { result } = renderHook(() => useTableCellEdit('quotes', [column]), { wrapper: wrapper() })
    const event = cellValueChangedEvent({
      colId: 'quote_system_status',
      data: row({ quote_system_status: { id: 1, name: 'Aperto' }, quote_system_status_notes: [] }),
      oldValue: { id: 1, name: 'Aperto' },
      newValue: { id: 4, name: 'Vinto' },
    })

    act(() => result.current.handleCellValueChanged(event))

    await waitFor(() =>
      expect(updateTableCellMock).toHaveBeenCalledWith('quotes', 7, { column: 'quote_system_status', value: 4 }),
    )
    expect(result.current.noteDialogSlot).toBeNull()
  })

})
