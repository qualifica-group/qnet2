// @vitest-environment jsdom
import { act, renderHook } from '@testing-library/react'
import { beforeEach, describe, expect, it } from 'vitest'
import { useTaskBoardViewMode } from '@/features/work-orders/task-board/use-task-board-view-mode'

beforeEach(() => {
  window.localStorage.clear()
})

describe('useTaskBoardViewMode', () => {
  it('opens a project work order on the Roadmap and a processing one on Lista', () => {
    expect(renderHook(() => useTaskBoardViewMode('project')).result.current.viewMode).toBe('roadmap')
    expect(renderHook(() => useTaskBoardViewMode('processing')).result.current.viewMode).toBe('list')
  })

  it('remembers the choice per type, without touching the other type', () => {
    const project = renderHook(() => useTaskBoardViewMode('project'))
    act(() => project.result.current.setViewMode('kanban'))

    expect(renderHook(() => useTaskBoardViewMode('project')).result.current.viewMode).toBe('kanban')
    expect(renderHook(() => useTaskBoardViewMode('processing')).result.current.viewMode).toBe('list')
  })

  it('keeps a processing choice stored before the Roadmap existed', () => {
    window.localStorage.setItem('work-orders.task-board.view-mode', 'kanban')

    expect(renderHook(() => useTaskBoardViewMode('processing')).result.current.viewMode).toBe('kanban')
  })
})
