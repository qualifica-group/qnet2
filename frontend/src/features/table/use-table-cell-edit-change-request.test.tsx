import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { CellValueChangedEvent } from 'ag-grid-community'
import { useTableCellEdit } from '@/features/table/use-table-cell-edit'
import type { TableColumn, TableRow } from '@/features/table/types'

/**
 * Spec 0078 AC-045/046: a column marked `change_request` never PATCHes — the
 * commit reverts the cell locally and opens the generic field-change-request
 * proposal dialog instead (E4); every other column keeps PATCHing exactly as
 * before (`use-table-cell-edit.test.tsx` for the rest of the hook's coverage).
 */

const updateTableCellMock = vi.fn()
const requestFieldChangeMock = vi.fn()

vi.mock('@/features/table/api', () => ({
  updateTableCell: (...args: unknown[]) => updateTableCellMock(...args),
}))

vi.mock('sonner', () => ({
  toast: { error: vi.fn() },
}))

vi.mock('@/features/field-change-requests/use-field-change-request-dialog', () => ({
  useRequestFieldChange: () => ({ requestFieldChange: requestFieldChangeMock }),
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

/** A relation column intercepted into a field-change-request proposal (spec 0078 D-2). */
function sourceColumn(): TableColumn {
  return {
    id: 'source',
    label: 'requestManagement.columns.source',
    type: 'text',
    visible: true,
    width: null,
    order: 0,
    sortable: true,
    filterable: true,
    editable: true,
    change_request: { resource: 'request-management', field: 'source_id' },
  }
}

beforeEach(() => {
  updateTableCellMock.mockReset()
  requestFieldChangeMock.mockReset()
})

describe('useTableCellEdit change_request interception', () => {
  it('never PATCHes a change_request column, reverts the cell and opens the proposal dialog (AC-045)', () => {
    const { result } = renderHook(
      () => useTableCellEdit('request-management', [sourceColumn()]),
      { wrapper: wrapper() },
    )
    const data = row({ source: { id: 3, name: 'Web' } })
    const event = cellValueChangedEvent({
      colId: 'source',
      data,
      oldValue: { id: 3, name: 'Web' },
      newValue: { id: 9, name: 'Referral' },
    })

    act(() => result.current.handleCellValueChanged(event))

    expect(updateTableCellMock).not.toHaveBeenCalled()
    expect(event.node.setData).toHaveBeenCalledWith({ ...data, source: { id: 3, name: 'Web' } })
    expect(requestFieldChangeMock).toHaveBeenCalledWith({
      resource: 'request-management',
      subjectId: 7,
      field: 'source_id',
      requestedValue: 9,
      currentLabel: 'Web',
      requestedLabel: 'Referral',
      fieldLabelKey: 'requestManagement.columns.source',
    })
  })

  it('PATCHes normally and never opens a proposal for a column without change_request (AC-046)', async () => {
    updateTableCellMock.mockResolvedValue(row({ name: 'after' }))
    const { result } = renderHook(() => useTableCellEdit('opportunities', []), { wrapper: wrapper() })
    const event = cellValueChangedEvent({ colId: 'name', data: row(), oldValue: 'before', newValue: 'after' })

    act(() => result.current.handleCellValueChanged(event))

    await waitFor(() =>
      expect(updateTableCellMock).toHaveBeenCalledWith('opportunities', 7, { column: 'name', value: 'after' }),
    )
    expect(requestFieldChangeMock).not.toHaveBeenCalled()
  })
})
