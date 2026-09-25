import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { toast } from 'sonner'
import { workOrderDetailQueryKey } from '@/features/work-orders/api'
import {
  useBulkBoardTaskAction,
  useInvalidateTaskBoard,
  useMoveBoardTask,
} from '@/features/work-orders/task-board/use-task-board-mutations'
import { taskBoardKeys } from '@/features/work-orders/task-board/query-keys'
import { boardTask, taskBoardPayload } from '@/features/work-orders/task-board/task-board-fixtures'

const moveBoardTaskMock = vi.fn()
const bulkBoardTaskActionMock = vi.fn()

vi.mock('@/features/work-orders/task-board/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/work-orders/task-board/api')>(
    '@/features/work-orders/task-board/api',
  )
  return {
    ...actual,
    moveBoardTask: (...args: unknown[]) => moveBoardTaskMock(...args),
    bulkBoardTaskAction: (...args: unknown[]) => bulkBoardTaskActionMock(...args),
  }
})

vi.mock('sonner', () => ({ toast: { error: vi.fn() } }))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const Wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
  return { client, Wrapper }
}

beforeEach(() => {
  moveBoardTaskMock.mockReset()
  bulkBoardTaskActionMock.mockReset()
  vi.mocked(toast.error).mockReset()
})

/** AC-027: the drop applies to the cache before the request even lands, then reconciles or rolls back on the outcome. */
describe('useMoveBoardTask', () => {
  it('applies the move optimistically, before the request resolves', async () => {
    const payload = taskBoardPayload({
      tasks: [
        boardTask({ id: 1, work_order_stage_id: 1, stage_position: 0 }),
        boardTask({ id: 2, work_order_stage_id: 2, stage_position: 0 }),
      ],
    })
    let resolveMove: (value: { tasks: [] }) => void = () => {}
    moveBoardTaskMock.mockReturnValue(new Promise((resolve) => (resolveMove = resolve)))

    const { client, Wrapper } = wrapper()
    client.setQueryData(taskBoardKeys.board(9), payload)

    const { result } = renderHook(() => useMoveBoardTask(9), { wrapper: Wrapper })
    result.current.mutate({ task_id: 1, work_order_stage_id: 2, position: 0 })

    await waitFor(() => {
      const cached = client.getQueryData<typeof payload>(taskBoardKeys.board(9))
      expect(cached?.tasks.find((task) => task.id === 1)?.work_order_stage_id).toBe(2)
    })

    resolveMove({ tasks: [] })
    await waitFor(() => expect(result.current.isSuccess).toBe(true))
  })

  it('reconciles with the server rows on success', async () => {
    const payload = taskBoardPayload({
      tasks: [boardTask({ id: 1, work_order_stage_id: 1, stage_position: 0 })],
    })
    moveBoardTaskMock.mockResolvedValue({
      tasks: [{ id: 1, work_order_stage_id: 2, stage_position: 3 }],
    })

    const { client, Wrapper } = wrapper()
    client.setQueryData(taskBoardKeys.board(9), payload)

    const { result } = renderHook(() => useMoveBoardTask(9), { wrapper: Wrapper })
    result.current.mutate({ task_id: 1, work_order_stage_id: 2, position: 3 })

    await waitFor(() => expect(result.current.isSuccess).toBe(true))

    const cached = client.getQueryData<typeof payload>(taskBoardKeys.board(9))
    expect(cached?.tasks[0]).toMatchObject({ work_order_stage_id: 2, stage_position: 3 })
  })

  /** AC-011: spec 0167, le voci segnatempo seguono la fase del task, cosi' i totali per fase tornano aggiornati dal refetch. */
  it('invalidates the board query on success, so the logged-minutes totals refetch', async () => {
    const payload = taskBoardPayload({
      tasks: [boardTask({ id: 1, work_order_stage_id: 1, stage_position: 0 })],
    })
    moveBoardTaskMock.mockResolvedValue({
      tasks: [{ id: 1, work_order_stage_id: 2, stage_position: 3 }],
    })

    const { client, Wrapper } = wrapper()
    client.setQueryData(taskBoardKeys.board(9), payload)

    const { result } = renderHook(() => useMoveBoardTask(9), { wrapper: Wrapper })
    result.current.mutate({ task_id: 1, work_order_stage_id: 2, position: 3 })

    await waitFor(() => expect(client.getQueryState(taskBoardKeys.board(9))?.isInvalidated).toBe(true))
  })

  it('rolls back to the pre-drag snapshot and shows a toast on failure', async () => {
    const payload = taskBoardPayload({
      tasks: [boardTask({ id: 1, work_order_stage_id: 1, stage_position: 0 })],
    })
    moveBoardTaskMock.mockRejectedValue(new Error('network error'))

    const { client, Wrapper } = wrapper()
    client.setQueryData(taskBoardKeys.board(9), payload)

    const { result } = renderHook(() => useMoveBoardTask(9), { wrapper: Wrapper })
    result.current.mutate({ task_id: 1, work_order_stage_id: 2, position: 0 })

    await waitFor(() => expect(result.current.isError).toBe(true))

    const cached = client.getQueryData<typeof payload>(taskBoardKeys.board(9))
    expect(cached?.tasks[0]).toMatchObject({ work_order_stage_id: 1, stage_position: 0 })
    expect(toast.error).toHaveBeenCalledTimes(1)
  })
})

describe('useBulkBoardTaskAction', () => {
  it('invalidates the board query on success', async () => {
    bulkBoardTaskActionMock.mockResolvedValue({ results: [], succeeded: 0, failed: 0 })

    const { client, Wrapper } = wrapper()
    client.setQueryData(taskBoardKeys.board(9), taskBoardPayload())

    const { result } = renderHook(() => useBulkBoardTaskAction(9), { wrapper: Wrapper })
    result.current.mutate({ action: 'uncomplete', task_ids: [1] })

    await waitFor(() => expect(client.getQueryState(taskBoardKeys.board(9))?.isInvalidated).toBe(true))
  })
})

/** Spec 0149 AC-017: a task change can move the commessa's computed status, so the detail refreshes with the board. */
describe('useInvalidateTaskBoard', () => {
  it('invalidates both the board and the work order detail', async () => {
    const { client, Wrapper } = wrapper()
    client.setQueryData(taskBoardKeys.board(9), taskBoardPayload())
    client.setQueryData(workOrderDetailQueryKey(9), { id: 9 })

    const { result } = renderHook(() => useInvalidateTaskBoard(9), { wrapper: Wrapper })
    await result.current()

    expect(client.getQueryState(taskBoardKeys.board(9))?.isInvalidated).toBe(true)
    expect(client.getQueryState(workOrderDetailQueryKey(9))?.isInvalidated).toBe(true)
  })

  it('is what a bulk action runs on success', async () => {
    bulkBoardTaskActionMock.mockResolvedValue({ results: [], succeeded: 0, failed: 0 })
    const { client, Wrapper } = wrapper()
    client.setQueryData(workOrderDetailQueryKey(9), { id: 9 })

    const { result } = renderHook(() => useBulkBoardTaskAction(9), { wrapper: Wrapper })
    result.current.mutate({ action: 'uncomplete', task_ids: [1] })

    await waitFor(() => expect(client.getQueryState(workOrderDetailQueryKey(9))?.isInvalidated).toBe(true))
  })
})
