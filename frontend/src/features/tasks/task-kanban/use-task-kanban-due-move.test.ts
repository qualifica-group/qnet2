import { beforeEach, describe, expect, it, vi } from 'vitest'
import { act, renderHook } from '@testing-library/react'
import { useTaskKanbanDueMove } from '@/features/tasks/task-kanban/use-task-kanban-due-move'
import { updateTask } from '@/features/tasks/api'
import type { TaskKanbanRow } from '@/features/tasks/task-kanban/task-kanban-types'

vi.mock('@/features/tasks/api', () => ({ updateTask: vi.fn() }))
vi.mock('sonner', () => ({ toast: { error: vi.fn() } }))

const TODAY = '2026-09-24'

function row(id: number): TaskKanbanRow {
  return {
    id,
    actions: [],
    title: 'Task',
    task_status: null,
    task_priority: null,
    start_date: null,
    end_date: null,
    completion_percentage: 0,
    estimated_minutes: null,
    actual_minutes: 0,
    is_blocked: false,
    assignees: [],
    has_subtasks: false,
  }
}

beforeEach(() => vi.mocked(updateTask).mockReset())

describe('useTaskKanbanDueMove (spec 0157 D-2/D-4)', () => {
  it('PATCHes end_date to the bucket own drop date', () => {
    vi.mocked(updateTask).mockResolvedValue({} as never)
    const onMutated = vi.fn()
    const { result } = renderHook(() => useTaskKanbanDueMove({ today: TODAY, onMutated }))

    act(() => result.current.moveToBucket(row(1), 'tomorrow'))

    expect(updateTask).toHaveBeenCalledWith(1, { end_date: '2026-09-25' })
  })

  it('does nothing for a bucket with no drop date (overdue/completed)', () => {
    const onMutated = vi.fn()
    const { result } = renderHook(() => useTaskKanbanDueMove({ today: TODAY, onMutated }))

    act(() => result.current.moveToBucket(row(1), 'overdue'))
    act(() => result.current.moveToBucket(row(1), 'completed'))

    expect(updateTask).not.toHaveBeenCalled()
  })
})
