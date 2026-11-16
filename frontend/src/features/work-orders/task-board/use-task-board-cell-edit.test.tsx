import { beforeEach, describe, expect, it, vi } from 'vitest'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { updateTableCell } from '@/features/table/api'
import { uncompleteTask } from '@/features/tasks/api'
import { boardTask } from '@/features/work-orders/task-board/task-board-fixtures'
import { useTaskBoardCellEdit } from '@/features/work-orders/task-board/use-task-board-cell-edit'

const WORK_ORDER_ID = 9
const CLOSING_STATUS_ID = 99

const completeRowMock = vi.fn()
const interceptCommitMock = vi.fn()

vi.mock('@/features/table/api', () => ({ updateTableCell: vi.fn() }))

vi.mock('@/features/tasks/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/tasks/api')>()),
  uncompleteTask: vi.fn(),
}))

vi.mock('@/features/tasks/use-task-domain-row-actions', () => ({
  useTaskDomainRowActions: () => ({ handleAction: vi.fn(), completeRow: completeRowMock, dialogSlot: null }),
}))

vi.mock('@/features/tasks/use-task-status-cell-intercept', () => ({
  useTaskStatusCellIntercept: () => ({ interceptCommit: interceptCommitMock, dialogSlot: null }),
}))

function wrapper({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>
}

beforeEach(() => {
  vi.mocked(updateTableCell).mockReset().mockResolvedValue({ id: 100, actions: [] })
  vi.mocked(uncompleteTask).mockReset()
  completeRowMock.mockReset()
  interceptCommitMock.mockReset().mockImplementation(({ newValue }) => (newValue as { id: number }).id === CLOSING_STATUS_ID)
})

describe('useTaskBoardCellEdit (spec 0401)', () => {
  it('writes a cell through the Task grid PATCH (AC-003)', async () => {
    const { result } = renderHook(() => useTaskBoardCellEdit(WORK_ORDER_ID), { wrapper })

    act(() => result.current.cellEdit.commit(boardTask({ id: 100 }), 'task_priority', 7))

    await waitFor(() => expect(updateTableCell).toHaveBeenCalledWith('tasks', 100, { column: 'task_priority', value: 7 }))
  })

  it('hands a closing status to the complete flow instead of writing it (AC-005)', () => {
    const task = boardTask({ id: 100 })
    const { result } = renderHook(() => useTaskBoardCellEdit(WORK_ORDER_ID), { wrapper })

    act(() => result.current.cellEdit.commit(task, 'task_status', CLOSING_STATUS_ID))

    expect(interceptCommitMock).toHaveBeenCalledWith(
      expect.objectContaining({ columnId: 'task_status', oldValue: task.task_status, newValue: { id: CLOSING_STATUS_ID } }),
    )
    expect(updateTableCell).not.toHaveBeenCalled()
  })

  it('writes an open status directly', async () => {
    const { result } = renderHook(() => useTaskBoardCellEdit(WORK_ORDER_ID), { wrapper })

    act(() => result.current.cellEdit.commit(boardTask({ id: 100 }), 'task_status', 4))

    await waitFor(() => expect(updateTableCell).toHaveBeenCalledWith('tasks', 100, { column: 'task_status', value: 4 }))
  })

  it('completes through the grid row action and reopens through uncomplete (AC-002)', async () => {
    vi.mocked(uncompleteTask).mockResolvedValue({} as Awaited<ReturnType<typeof uncompleteTask>>)
    const { result } = renderHook(() => useTaskBoardCellEdit(WORK_ORDER_ID), { wrapper })

    act(() => result.current.cellEdit.complete(boardTask({ id: 100 })))
    expect(completeRowMock).toHaveBeenCalledWith({ id: 100, actions: [] })

    act(() => result.current.cellEdit.reopen(boardTask({ id: 100 })))
    await waitFor(() => expect(uncompleteTask).toHaveBeenCalledWith(100))
  })
})
