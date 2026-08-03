import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, fireEvent, render, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { CellValueChangedEvent } from 'ag-grid-community'
import i18n from '@/i18n'
import { useTableCellEdit } from '@/features/table/use-table-cell-edit'
import type { TableColumn, TableRow } from '@/features/table/types'

/**
 * Spec 0053 AC-018/019/020/021: the generic table's inline-edit hook PATCHes
 * the edited cell and swaps the row for the server's re-mapped copy on
 * success, reverts to the previous value and toasts the server's message on
 * failure, and never calls the network for a no-op edit. Mirrors the import
 * wizard's own cell-edit hook test (`use-review-rows.test.tsx`), the only
 * prior precedent for this cycle in the repo.
 *
 * Spec 0054 D-3/D-5/AC-018: extends coverage for a relation column's
 * `{id, name}` value (unwrapped to its id before PATCHing) and the
 * `requires_note` dialog flow (held-back PATCH, single request with `note`
 * on confirm, local revert with no request on cancel).
 */

const updateTableCellMock = vi.fn()
const toastErrorMock = vi.fn()

vi.mock('@/features/table/api', () => ({
  updateTableCell: (...args: unknown[]) => updateTableCellMock(...args),
}))

vi.mock('sonner', () => ({
  toast: { error: (...args: unknown[]) => toastErrorMock(...args) },
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function row(overrides: Partial<TableRow> = {}): TableRow {
  return { id: 7, actions: [], editable: true, name: 'before', ...overrides }
}

/** A badge column whose `won` option `requires_note` (spec 0054 D-5). */
function workflowStatusColumn(): TableColumn {
  return {
    id: 'workflow_status',
    label: 'Status',
    type: 'badge',
    visible: true,
    width: null,
    order: 0,
    sortable: true,
    filterable: true,
    badges: [
      { value: 'new', label: 'New', color: 'blue', icon: null },
      { value: 'won', label: 'Won', color: 'green', icon: null, requires_note: true },
    ],
  }
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
  toastErrorMock.mockReset()
})

describe('useTableCellEdit', () => {
  it('PATCHes the edited column and swaps the row for the server copy on success (AC-018/020)', async () => {
    const updatedRow = row({ name: 'after' })
    updateTableCellMock.mockResolvedValue(updatedRow)

    const { result } = renderHook(() => useTableCellEdit('opportunities', []), { wrapper: wrapper() })
    const event = cellValueChangedEvent({ colId: 'name', data: row(), oldValue: 'before', newValue: 'after' })

    act(() => result.current.handleCellValueChanged(event))

    await waitFor(() =>
      expect(updateTableCellMock).toHaveBeenCalledWith('opportunities', 7, { column: 'name', value: 'after' }),
    )
    await waitFor(() => expect(event.node.setData).toHaveBeenCalledWith(updatedRow))
  })

  it('reverts the cell and toasts the server message on failure (AC-019)', async () => {
    updateTableCellMock.mockRejectedValue({
      isAxiosError: true,
      response: { status: 403, data: { message: 'You cannot edit this field.' } },
    })

    const { result } = renderHook(() => useTableCellEdit('opportunities', []), { wrapper: wrapper() })
    const data = row()
    const event = cellValueChangedEvent({ colId: 'name', data, oldValue: 'before', newValue: 'attempted' })

    act(() => result.current.handleCellValueChanged(event))

    await waitFor(() => expect(event.node.setData).toHaveBeenCalledWith({ ...data, name: 'before' }))
    expect(toastErrorMock).toHaveBeenCalledWith('You cannot edit this field.')
  })

  it('falls back to a generic message when the server response carries none', async () => {
    updateTableCellMock.mockRejectedValue(new Error('network error'))
    await i18n.changeLanguage('en')

    const { result } = renderHook(() => useTableCellEdit('opportunities', []), { wrapper: wrapper() })
    const event = cellValueChangedEvent({ colId: 'name', data: row(), oldValue: 'before', newValue: 'attempted' })

    act(() => result.current.handleCellValueChanged(event))

    await waitFor(() => expect(toastErrorMock).toHaveBeenCalledWith('Unable to save the change.'))
  })

  it('does nothing when the value did not actually change (AC-021)', () => {
    const { result } = renderHook(() => useTableCellEdit('opportunities', []), { wrapper: wrapper() })
    const event = cellValueChangedEvent({ colId: 'name', data: row(), oldValue: 'before', newValue: 'before' })

    act(() => result.current.handleCellValueChanged(event))

    expect(updateTableCellMock).not.toHaveBeenCalled()
  })

  it('unwraps a relation column value to its id before PATCHing (spec 0054 D-3)', async () => {
    const updatedRow = row({ operator: { id: 9, name: 'Mario Rossi' } })
    updateTableCellMock.mockResolvedValue(updatedRow)

    const { result } = renderHook(() => useTableCellEdit('leads', []), { wrapper: wrapper() })
    const oldValue = { id: 3, name: 'Old Op' }
    const newValue = { id: 9, name: 'Mario Rossi' }
    const event = cellValueChangedEvent({
      colId: 'operator',
      data: row({ operator: oldValue }),
      oldValue,
      newValue,
    })

    act(() => result.current.handleCellValueChanged(event))

    await waitFor(() =>
      expect(updateTableCellMock).toHaveBeenCalledWith('leads', 7, { column: 'operator', value: 9 }),
    )
    await waitFor(() => expect(event.node.setData).toHaveBeenCalledWith(updatedRow))
  })

  it('does nothing for a relation value when old/new share the same reference (no pick made)', () => {
    const sameValue = { id: 3, name: 'Old Op' }
    const { result } = renderHook(() => useTableCellEdit('leads', []), { wrapper: wrapper() })
    const event = cellValueChangedEvent({
      colId: 'operator',
      data: row({ operator: sameValue }),
      oldValue: sameValue,
      newValue: sameValue,
    })

    act(() => result.current.handleCellValueChanged(event))

    expect(updateTableCellMock).not.toHaveBeenCalled()
  })

  // User directive 2026-07-23: a multiselect column's value is the whole
  // collection, so it unwraps element-wise and compares by id set (the editor
  // rebuilds the array on every toggle — identity would never match).
  it('unwraps a multiselect column value to its id collection before PATCHing', async () => {
    const updatedRow = row({ products_of_interest: [{ id: 9, name: 'Fibra' }] })
    updateTableCellMock.mockResolvedValue(updatedRow)

    const { result } = renderHook(() => useTableCellEdit('opportunities', []), { wrapper: wrapper() })
    const event = cellValueChangedEvent({
      colId: 'products_of_interest',
      data: row({ products_of_interest: [{ id: 3, name: 'ADSL' }] }),
      oldValue: [{ id: 3, name: 'ADSL' }],
      newValue: [{ id: 9, name: 'Fibra' }],
    })

    act(() => result.current.handleCellValueChanged(event))

    await waitFor(() =>
      expect(updateTableCellMock).toHaveBeenCalledWith('opportunities', 7, {
        column: 'products_of_interest',
        value: [9],
      }),
    )
    await waitFor(() => expect(event.node.setData).toHaveBeenCalledWith(updatedRow))
  })

  it('does nothing for a multiselect whose selection is unchanged, despite a new array reference', () => {
    const { result } = renderHook(() => useTableCellEdit('opportunities', []), { wrapper: wrapper() })
    const event = cellValueChangedEvent({
      colId: 'products_of_interest',
      data: row({ products_of_interest: [{ id: 3, name: 'ADSL' }] }),
      oldValue: [{ id: 3, name: 'ADSL' }],
      newValue: [{ id: 3, name: 'ADSL' }],
    })

    act(() => result.current.handleCellValueChanged(event))

    expect(updateTableCellMock).not.toHaveBeenCalled()
  })

  it('PATCHes an emptied multiselect as [] (the server owns the mandatory rule)', async () => {
    updateTableCellMock.mockResolvedValue(row({ products_of_interest: [] }))

    const { result } = renderHook(() => useTableCellEdit('opportunities', []), { wrapper: wrapper() })
    const event = cellValueChangedEvent({
      colId: 'products_of_interest',
      data: row({ products_of_interest: [{ id: 3, name: 'ADSL' }] }),
      oldValue: [{ id: 3, name: 'ADSL' }],
      newValue: [],
    })

    act(() => result.current.handleCellValueChanged(event))

    await waitFor(() =>
      expect(updateTableCellMock).toHaveBeenCalledWith('opportunities', 7, {
        column: 'products_of_interest',
        value: [],
      }),
    )
  })

  // Spec 0075 AC-015: a `product_lines` cell value is a collection of PAIRS —
  // only its `*_id` keys travel, and two equal collections never PATCH even
  // though the editor rebuilds the array on every pick.
  it('sends a product_lines cell as its id pairs, dropping the labels', async () => {
    const pair = (categoryId: number, categoryName: string) => ({
      business_function_id: 3,
      business_function_name: 'Energia',
      product_category_id: categoryId,
      product_category_name: categoryName,
    })
    updateTableCellMock.mockResolvedValue(row({ product_categories: [pair(9, 'Gas')] }))

    const { result } = renderHook(() => useTableCellEdit('request-management', []), { wrapper: wrapper() })
    const event = cellValueChangedEvent({
      colId: 'product_categories',
      data: row({ product_categories: [pair(7, 'Luce')] }),
      oldValue: [pair(7, 'Luce')],
      newValue: [pair(9, 'Gas')],
    })

    act(() => result.current.handleCellValueChanged(event))

    await waitFor(() =>
      expect(updateTableCellMock).toHaveBeenCalledWith('request-management', 7, {
        column: 'product_categories',
        value: [{ business_function_id: 3, product_category_id: 9 }],
      }),
    )
  })

  it('does nothing for a product_lines collection that comes back identical', () => {
    const pair = {
      business_function_id: 3,
      business_function_name: 'Energia',
      product_category_id: 7,
      product_category_name: 'Luce',
    }

    const { result } = renderHook(() => useTableCellEdit('request-management', []), { wrapper: wrapper() })
    const event = cellValueChangedEvent({
      colId: 'product_categories',
      data: row({ product_categories: [pair] }),
      oldValue: [pair],
      newValue: [{ ...pair }],
    })

    act(() => result.current.handleCellValueChanged(event))

    expect(updateTableCellMock).not.toHaveBeenCalled()
  })

  describe('requires_note dialog (spec 0054 D-5)', () => {
    it('holds back the PATCH and opens the note dialog for a value that requires one', () => {
      const { result } = renderHook(
        () => useTableCellEdit('request-management', [workflowStatusColumn()]),
        { wrapper: wrapper() },
      )
      const event = cellValueChangedEvent({
        colId: 'workflow_status',
        data: row({ workflow_status: 'new' }),
        oldValue: 'new',
        newValue: 'won',
      })

      act(() => result.current.handleCellValueChanged(event))

      expect(updateTableCellMock).not.toHaveBeenCalled()
      expect(result.current.noteDialogSlot).not.toBeNull()
    })

    it('confirming sends ONE request with {column, value, note} and swaps the row (AC-018)', async () => {
      const updatedRow = row({ workflow_status: 'won' })
      updateTableCellMock.mockResolvedValue(updatedRow)

      const { result } = renderHook(
        () => useTableCellEdit('request-management', [workflowStatusColumn()]),
        { wrapper: wrapper() },
      )
      const event = cellValueChangedEvent({
        colId: 'workflow_status',
        data: row({ workflow_status: 'new' }),
        oldValue: 'new',
        newValue: 'won',
      })
      act(() => result.current.handleCellValueChanged(event))

      const { getByLabelText, getByRole } = render(<>{result.current.noteDialogSlot}</>)
      fireEvent.change(getByLabelText(i18n.t('table.noteDialog.label')), {
        target: { value: 'Closed after final call' },
      })
      fireEvent.click(getByRole('button', { name: i18n.t('table.noteDialog.confirm') }))

      await waitFor(() =>
        expect(updateTableCellMock).toHaveBeenCalledWith('request-management', 7, {
          column: 'workflow_status',
          value: 'won',
          note: 'Closed after final call',
        }),
      )
      await waitFor(() => expect(event.node.setData).toHaveBeenCalledWith(updatedRow))
    })

    it('cancelling reverts the cell locally with no request (AC-018)', () => {
      const { result } = renderHook(
        () => useTableCellEdit('request-management', [workflowStatusColumn()]),
        { wrapper: wrapper() },
      )
      const data = row({ workflow_status: 'new' })
      const event = cellValueChangedEvent({
        colId: 'workflow_status',
        data,
        oldValue: 'new',
        newValue: 'won',
      })
      act(() => result.current.handleCellValueChanged(event))

      const { getByRole } = render(<>{result.current.noteDialogSlot}</>)
      fireEvent.click(getByRole('button', { name: i18n.t('table.noteDialog.cancel') }))

      expect(updateTableCellMock).not.toHaveBeenCalled()
      expect(event.node.setData).toHaveBeenCalledWith({ ...data, workflow_status: 'new' })
    })

    it('a 422 on confirm (e.g. note rejected server-side) reverts and toasts, same as any other failed edit (AC-019)', async () => {
      updateTableCellMock.mockRejectedValue({
        isAxiosError: true,
        response: { status: 422, data: { message: 'A note is required for this status.' } },
      })

      const { result } = renderHook(
        () => useTableCellEdit('request-management', [workflowStatusColumn()]),
        { wrapper: wrapper() },
      )
      const data = row({ workflow_status: 'new' })
      const event = cellValueChangedEvent({
        colId: 'workflow_status',
        data,
        oldValue: 'new',
        newValue: 'won',
      })
      act(() => result.current.handleCellValueChanged(event))

      const { getByLabelText, getByRole } = render(<>{result.current.noteDialogSlot}</>)
      fireEvent.change(getByLabelText(i18n.t('table.noteDialog.label')), { target: { value: 'x' } })
      fireEvent.click(getByRole('button', { name: i18n.t('table.noteDialog.confirm') }))

      await waitFor(() => expect(event.node.setData).toHaveBeenCalledWith(data))
      expect(toastErrorMock).toHaveBeenCalledWith('A note is required for this status.')
    })
  })
  describe('requires_note from backend options (spec 0055 D-5)', () => {
    /** A `select` column whose option 3 requires a note — the request-management shape. */
    function selectStatusColumn(): TableColumn {
      return {
        id: 'workflow_status',
        label: 'Status',
        type: 'text',
        visible: true,
        width: null,
        order: 0,
        sortable: true,
        filterable: true,
        editor: 'select',
        options: [
          { value: 1, label: 'Da contattare', requires_note: false },
          { value: 3, label: 'Chiusa', requires_note: true },
        ],
      }
    }

    it('holds the PATCH back when the picked OPTION requires a note, matching on the unwrapped id', () => {
      const { result } = renderHook(
        () => useTableCellEdit('request-management', [selectStatusColumn()]),
        { wrapper: wrapper() },
      )
      const data = row({ workflow_status: { id: 1, name: 'Da contattare' } })
      const event = cellValueChangedEvent({
        colId: 'workflow_status',
        data,
        oldValue: { id: 1, name: 'Da contattare' },
        newValue: { id: 3, name: 'Chiusa' },
      })

      act(() => result.current.handleCellValueChanged(event))

      expect(updateTableCellMock).not.toHaveBeenCalled()
      expect(result.current.noteDialogSlot).not.toBeNull()
    })

    it('PATCHes straight away when the picked option does NOT require a note', async () => {
      updateTableCellMock.mockResolvedValue(row())
      const { result } = renderHook(
        () => useTableCellEdit('request-management', [selectStatusColumn()]),
        { wrapper: wrapper() },
      )
      const event = cellValueChangedEvent({
        colId: 'workflow_status',
        data: row({ workflow_status: { id: 3, name: 'Chiusa' } }),
        oldValue: { id: 3, name: 'Chiusa' },
        newValue: { id: 1, name: 'Da contattare' },
      })

      act(() => result.current.handleCellValueChanged(event))

      await waitFor(() =>
        expect(updateTableCellMock).toHaveBeenCalledWith('request-management', 7, {
          column: 'workflow_status',
          value: 1,
        }),
      )
      expect(result.current.noteDialogSlot).toBeNull()
    })
  })
})
