import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useCompleteTask } from '@/features/tasks/use-task-mutations'
import { timeEntryKeys } from '@/features/time-entries/query-keys'
import { taskDetailWithPermissions } from '@/features/tasks/task-fixtures'

const completeTaskMock = vi.fn()

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, completeTask: (...args: unknown[]) => completeTaskMock(...args) }
})

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const Wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
  return { client, Wrapper }
}

beforeEach(() => {
  completeTaskMock.mockReset()
})

/**
 * Spec 0123 D-1/AC-040: the segnatempo `/complete` creates is not in the
 * response body, so the Task's own segnatempo query must be invalidated
 * explicitly after a successful completion.
 */
describe('useCompleteTask — segnatempo invalidation (AC-040)', () => {
  it('invalidates the task time entries query on success', async () => {
    completeTaskMock.mockResolvedValue(taskDetailWithPermissions())
    const { client, Wrapper } = wrapper()
    client.setQueryData(timeEntryKeys.taskEntries(90), { total_minutes: 0, can_create: true, items: [] })

    const { result } = renderHook(() => useCompleteTask({ taskId: 90 }), { wrapper: Wrapper })

    result.current.mutate({ time_entry: { date: '2026-09-14', task_type_id: 2, minutes: 30 } })

    await waitFor(() =>
      expect(client.getQueryState(timeEntryKeys.taskEntries(90))?.isInvalidated).toBe(true),
    )
  })
})
