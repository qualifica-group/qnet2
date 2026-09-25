import { describe, expect, it, vi } from 'vitest'
import { act, renderHook } from '@testing-library/react'
import type { DragEndEvent, DragStartEvent } from '@dnd-kit/core'
import { taskKanbanColumnDroppableId, useTaskKanbanDnd } from '@/features/tasks/task-kanban/use-task-kanban-dnd'
import type { TaskKanbanGroup, TaskKanbanRow } from '@/features/tasks/task-kanban/task-kanban-types'

function row(id: number): TaskKanbanRow {
  return {
    id,
    actions: [],
    title: `Task ${id}`,
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

function group(key: string, rows: TaskKanbanRow[], overrides: Partial<TaskKanbanGroup> = {}): TaskKanbanGroup {
  return { key, label: key, color: null, rows, droppable: true, draggable: true, ...overrides }
}

function dragEndEvent(activeId: string, overId: string | null): DragEndEvent {
  return {
    active: { id: activeId, data: { current: {} } },
    over: overId ? { id: overId, data: { current: {} } } : null,
  } as unknown as DragEndEvent
}

describe('useTaskKanbanDnd', () => {
  it('calls onDrop when a card lands on a different, droppable column', () => {
    const rowA = row(1)
    const groups = [group('a', [rowA]), group('b', [])]
    const onDrop = vi.fn()
    const { result } = renderHook(() => useTaskKanbanDnd({ groups, onDrop }))

    result.current.handleDragEnd(dragEndEvent('1', taskKanbanColumnDroppableId('b')))

    expect(onDrop).toHaveBeenCalledWith(rowA, 'b', groups[1])
  })

  it('does nothing when dropped back on its own column', () => {
    const groups = [group('a', [row(1)])]
    const onDrop = vi.fn()
    const { result } = renderHook(() => useTaskKanbanDnd({ groups, onDrop }))

    result.current.handleDragEnd(dragEndEvent('1', taskKanbanColumnDroppableId('a')))

    expect(onDrop).not.toHaveBeenCalled()
  })

  it('refuses a drop onto a non-droppable column', () => {
    const groups = [group('a', [row(1)]), group('b', [], { droppable: false })]
    const onDrop = vi.fn()
    const { result } = renderHook(() => useTaskKanbanDnd({ groups, onDrop }))

    result.current.handleDragEnd(dragEndEvent('1', taskKanbanColumnDroppableId('b')))

    expect(onDrop).not.toHaveBeenCalled()
  })

  it('refuses to drag a card OUT of a non-draggable column', () => {
    const groups = [group('a', [row(1)], { draggable: false }), group('b', [])]
    const onDrop = vi.fn()
    const { result } = renderHook(() => useTaskKanbanDnd({ groups, onDrop }))

    result.current.handleDragEnd(dragEndEvent('1', taskKanbanColumnDroppableId('b')))

    expect(onDrop).not.toHaveBeenCalled()
  })

  it('does nothing when dropped outside any droppable (over is null)', () => {
    const groups = [group('a', [row(1)])]
    const onDrop = vi.fn()
    const { result } = renderHook(() => useTaskKanbanDnd({ groups, onDrop }))

    result.current.handleDragEnd(dragEndEvent('1', null))

    expect(onDrop).not.toHaveBeenCalled()
  })

  it('tracks the active row from drag start, and clears it on drag end', () => {
    const rowA = row(1)
    const groups = [group('a', [rowA]), group('b', [])]
    const { result } = renderHook(() => useTaskKanbanDnd({ groups, onDrop: vi.fn() }))

    act(() => result.current.handleDragStart({ active: { id: '1' } } as unknown as DragStartEvent))
    expect(result.current.activeRow).toEqual(rowA)

    act(() => result.current.handleDragEnd(dragEndEvent('1', taskKanbanColumnDroppableId('b'))))
    expect(result.current.activeRow).toBeNull()
  })
})
