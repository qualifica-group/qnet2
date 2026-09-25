import { beforeEach, describe, expect, it, vi } from 'vitest'
import { act, renderHook, waitFor } from '@testing-library/react'
import { useTaskKanbanStatusMove } from '@/features/tasks/task-kanban/use-task-kanban-status-move'
import { fetchTask, updateTask, uncompleteTask } from '@/features/tasks/api'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'
import type { TaskKanbanRow } from '@/features/tasks/task-kanban/task-kanban-types'

vi.mock('@/features/tasks/api', () => ({
  fetchTask: vi.fn(),
  updateTask: vi.fn(),
  uncompleteTask: vi.fn(),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function row(id: number, group: 'open' | 'pending' | 'in_validation' | 'closed_positive' | 'closed_negative'): TaskKanbanRow {
  return {
    id,
    actions: [],
    title: `Task ${id}`,
    task_status: { id: 1, name: 'x', color: 'blue', icon: null, group },
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

beforeEach(() => {
  vi.mocked(fetchTask).mockReset()
  vi.mocked(updateTask).mockReset()
  vi.mocked(uncompleteTask).mockReset()
})

describe('useTaskKanbanStatusMove (spec 0157 D-3)', () => {
  it('PATCHes task_status_id directly on an open<->open move', () => {
    vi.mocked(updateTask).mockResolvedValue({} as never)
    const onMutated = vi.fn()
    const { result } = renderHook(() => useTaskKanbanStatusMove({ onMutated }))

    act(() => result.current.moveToStatus(row(1, 'open'), 2, 'pending'))

    expect(updateTask).toHaveBeenCalledWith(1, { task_status_id: 2 })
    expect(fetchTask).not.toHaveBeenCalled()
    expect(uncompleteTask).not.toHaveBeenCalled()
  })

  it('opens "Completa" (fetches the task) on a move INTO a closing status, without writing anything yet', async () => {
    const task = { id: 1 } as TaskDetailWithPermissions
    vi.mocked(fetchTask).mockResolvedValue(task)
    const onMutated = vi.fn()
    const { result } = renderHook(() => useTaskKanbanStatusMove({ onMutated }))

    act(() => result.current.moveToStatus(row(1, 'open'), 3, 'closed_positive'))

    await waitFor(() => expect(result.current.completingTask).toBe(task))
    expect(updateTask).not.toHaveBeenCalled()
    expect(onMutated).not.toHaveBeenCalled()
  })

  it('cancelling the dialog applies nothing (the card falls back on its own, no mutation ever ran)', async () => {
    vi.mocked(fetchTask).mockResolvedValue({ id: 1 } as TaskDetailWithPermissions)
    const onMutated = vi.fn()
    const { result } = renderHook(() => useTaskKanbanStatusMove({ onMutated }))

    act(() => result.current.moveToStatus(row(1, 'open'), 3, 'closed_positive'))
    await waitFor(() => expect(result.current.completingTask).not.toBeNull())

    act(() => result.current.closeCompleteDialog())

    expect(result.current.completingTask).toBeNull()
    expect(updateTask).not.toHaveBeenCalled()
    expect(onMutated).not.toHaveBeenCalled()
  })

  it('calls uncomplete on a move OUT of a closed status, never a direct PATCH', () => {
    vi.mocked(uncompleteTask).mockResolvedValue({} as never)
    const onMutated = vi.fn()
    const { result } = renderHook(() => useTaskKanbanStatusMove({ onMutated }))

    act(() => result.current.moveToStatus(row(1, 'closed_positive'), 4, 'open'))

    expect(uncompleteTask).toHaveBeenCalledWith(1)
    expect(updateTask).not.toHaveBeenCalled()
  })
})
