import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider, useQuery } from '@tanstack/react-query'
import { approveTask, bulkTaskAction, completeTask } from '@/features/tasks/api'
import { taskDetailWithPermissions } from '@/features/tasks/task-fixtures'
import { useTaskBulkMutation } from '@/features/tasks/use-task-bulk-mutation'
import { useApproveTask, useCompleteTask } from '@/features/tasks/use-task-mutations'
import { fetchWorkOrder, workOrderDetailQueryKey } from '@/features/work-orders/api'
import { workOrderProgressQueryKey } from '@/features/work-orders/progress-api'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

/**
 * Spec 0323 AC-021: closing a task positively can move the commessa's Stato
 * lavorazione and add a progress entry, so complete, approve and the bulk
 * actions refresh the work order data they leave behind.
 */

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, completeTask: vi.fn(), approveTask: vi.fn(), bulkTaskAction: vi.fn() }
})
vi.mock('@/features/work-orders/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/work-orders/api')>('@/features/work-orders/api')
  return { ...actual, fetchWorkOrder: vi.fn() }
})

const WORK_ORDER_ID = 4
const FRESH_DETAIL = { id: WORK_ORDER_ID, code: 'COM-0004' } as WorkOrderDetailWithPermissions
const COMPLETE_PAYLOAD = { time_entry: { date: '2026-09-14', task_type_id: 2, minutes: 30 } }

function setup() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const Wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
  client.setQueryData(workOrderProgressQueryKey(WORK_ORDER_ID), [])
  client.setQueryData(workOrderDetailQueryKey(WORK_ORDER_ID), { id: WORK_ORDER_ID, code: 'STALE' })
  return { client, Wrapper }
}

const isStale = (client: QueryClient, key: readonly unknown[]) => client.getQueryState(key)?.isInvalidated === true

beforeEach(() => {
  vi.mocked(completeTask).mockReset().mockResolvedValue(taskDetailWithPermissions())
  vi.mocked(approveTask).mockReset().mockResolvedValue(taskDetailWithPermissions())
  vi.mocked(bulkTaskAction).mockReset().mockResolvedValue({ affected: 2 })
  vi.mocked(fetchWorkOrder).mockReset().mockResolvedValue(FRESH_DETAIL)
})

describe('work order refresh after a task closes', () => {
  it('invalidates the progress entries and the cached detail after complete', async () => {
    const { client, Wrapper } = setup()
    const { result } = renderHook(() => useCompleteTask({ taskId: 90 }), { wrapper: Wrapper })

    result.current.mutate(COMPLETE_PAYLOAD)

    await waitFor(() => expect(isStale(client, workOrderProgressQueryKey(WORK_ORDER_ID))).toBe(true))
    expect(isStale(client, workOrderDetailQueryKey(WORK_ORDER_ID))).toBe(true)
  })

  it('invalidates them after approve', async () => {
    const { client, Wrapper } = setup()
    const { result } = renderHook(() => useApproveTask({ taskId: 90 }), { wrapper: Wrapper })

    result.current.mutate()

    await waitFor(() => expect(isStale(client, workOrderProgressQueryKey(WORK_ORDER_ID))).toBe(true))
    expect(isStale(client, workOrderDetailQueryKey(WORK_ORDER_ID))).toBe(true)
  })

  it('invalidates them after a bulk action', async () => {
    const { client, Wrapper } = setup()
    const { result } = renderHook(() => useTaskBulkMutation(), { wrapper: Wrapper })

    result.current.mutate({ action: 'complete', task_ids: [1, 2] })

    await waitFor(() => expect(isStale(client, workOrderProgressQueryKey(WORK_ORDER_ID))).toBe(true))
    expect(isStale(client, workOrderDetailQueryKey(WORK_ORDER_ID))).toBe(true)
  })

  it('writes a fresh detail into the cache when the record is open, without flipping it to loading', async () => {
    const { client, Wrapper } = setup()
    const { result } = renderHook(
      () => ({
        detail: useQuery({
          queryKey: workOrderDetailQueryKey(WORK_ORDER_ID),
          queryFn: () => Promise.resolve({ id: WORK_ORDER_ID, code: 'STALE' }),
          staleTime: Infinity,
        }),
        complete: useCompleteTask({ taskId: 90 }),
      }),
      { wrapper: Wrapper },
    )

    result.current.complete.mutate(COMPLETE_PAYLOAD)

    await waitFor(() => expect(client.getQueryData(workOrderDetailQueryKey(WORK_ORDER_ID))).toEqual(FRESH_DETAIL))
    expect(fetchWorkOrder).toHaveBeenCalledWith(WORK_ORDER_ID)
    expect(result.current.detail.isFetching).toBe(false)
  })

  it('leaves the task action successful when the refresh fails', async () => {
    vi.mocked(fetchWorkOrder).mockRejectedValue(new Error('boom'))
    const { Wrapper } = setup()
    const { result } = renderHook(
      () => ({
        detail: useQuery({
          queryKey: workOrderDetailQueryKey(WORK_ORDER_ID),
          queryFn: () => Promise.resolve({ id: WORK_ORDER_ID, code: 'STALE' }),
          staleTime: Infinity,
        }),
        complete: useCompleteTask({ taskId: 90 }),
      }),
      { wrapper: Wrapper },
    )

    result.current.complete.mutate(COMPLETE_PAYLOAD)

    await waitFor(() => expect(result.current.complete.isSuccess).toBe(true))
  })
})
