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

function group(key: string, overrides: Partial<TaskKanbanGroup> = {}): TaskKanbanGroup {
  return {
    key,
    label: key,
    color: null,
    droppable: true,
    draggable: true,
    kanbanGroup: { by: 'status', key: Number(key) || 0 },
    ...overrides,
  }
}

function dragStartEvent(activeId: string, data: Record<string, unknown>): DragStartEvent {
  return { active: { id: activeId, data: { current: data } } } as unknown as DragStartEvent
}

function dragEndEvent(
  activeId: string,
  data: Record<string, unknown>,
  overId: string | null,
): DragEndEvent {
  return {
    active: { id: activeId, data: { current: data } },
    over: overId ? { id: overId, data: { current: {} } } : null,
  } as unknown as DragEndEvent
}

describe('useTaskKanbanDnd (spec 0164 D-1: origin travels on the drag data, not a client-side rows list)', () => {
  it('calls onDrop with the row and origin/target keys when it lands on a different, droppable column', () => {
    const rowA = row(1)
    const groups = [group('a'), group('b')]
    const onDrop = vi.fn()
    const { result } = renderHook(() => useTaskKanbanDnd({ groups, onDrop }))

    result.current.handleDragEnd(
      dragEndEvent('1', { row: rowA, groupKey: 'a' }, taskKanbanColumnDroppableId('b')),
    )

    expect(onDrop).toHaveBeenCalledWith(rowA, 'a', 'b', groups[1])
  })

  it('does nothing when dropped back on its own column', () => {
    const groups = [group('a')]
    const onDrop = vi.fn()
    const { result } = renderHook(() => useTaskKanbanDnd({ groups, onDrop }))

    result.current.handleDragEnd(
      dragEndEvent('1', { row: row(1), groupKey: 'a' }, taskKanbanColumnDroppableId('a')),
    )

    expect(onDrop).not.toHaveBeenCalled()
  })

  it('refuses a drop onto a non-droppable column', () => {
    const groups = [group('a'), group('b', { droppable: false })]
    const onDrop = vi.fn()
    const { result } = renderHook(() => useTaskKanbanDnd({ groups, onDrop }))

    result.current.handleDragEnd(
      dragEndEvent('1', { row: row(1), groupKey: 'a' }, taskKanbanColumnDroppableId('b')),
    )

    expect(onDrop).not.toHaveBeenCalled()
  })

  it('refuses to drag a card OUT of a non-draggable column', () => {
    const groups = [group('a', { draggable: false }), group('b')]
    const onDrop = vi.fn()
    const { result } = renderHook(() => useTaskKanbanDnd({ groups, onDrop }))

    result.current.handleDragEnd(
      dragEndEvent('1', { row: row(1), groupKey: 'a' }, taskKanbanColumnDroppableId('b')),
    )

    expect(onDrop).not.toHaveBeenCalled()
  })

  it('does nothing when dropped outside any droppable (over is null)', () => {
    const groups = [group('a')]
    const onDrop = vi.fn()
    const { result } = renderHook(() => useTaskKanbanDnd({ groups, onDrop }))

    result.current.handleDragEnd(dragEndEvent('1', { row: row(1), groupKey: 'a' }, null))

    expect(onDrop).not.toHaveBeenCalled()
  })

  it('does nothing when the drag carries no data (no origin known)', () => {
    const groups = [group('a'), group('b')]
    const onDrop = vi.fn()
    const { result } = renderHook(() => useTaskKanbanDnd({ groups, onDrop }))

    result.current.handleDragEnd(dragEndEvent('1', {}, taskKanbanColumnDroppableId('b')))

    expect(onDrop).not.toHaveBeenCalled()
  })

  it('tracks the active row from drag start, and clears it on drag end', () => {
    const rowA = row(1)
    const groups = [group('a'), group('b')]
    const { result } = renderHook(() => useTaskKanbanDnd({ groups, onDrop: vi.fn() }))

    act(() => result.current.handleDragStart(dragStartEvent('1', { row: rowA, groupKey: 'a' })))
    expect(result.current.activeRow).toEqual(rowA)

    act(() =>
      result.current.handleDragEnd(
        dragEndEvent('1', { row: rowA, groupKey: 'a' }, taskKanbanColumnDroppableId('b')),
      ),
    )
    expect(result.current.activeRow).toBeNull()
  })
})
