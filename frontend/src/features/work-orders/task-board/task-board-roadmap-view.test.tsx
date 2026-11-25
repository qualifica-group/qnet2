import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import { boardTask, workOrderStage } from '@/features/work-orders/task-board/task-board-fixtures'
import { buildBoardTree, groupRootsByStage } from '@/features/work-orders/task-board/task-board-filters'
import { TaskBoardRoadmapView } from '@/features/work-orders/task-board/task-board-roadmap-view'
import type { BoardTask } from '@/features/work-orders/task-board/types'

const TODAY = '2026-11-25'

function renderRoadmap(tasks: BoardTask[], { isReadOnly = false, onOpenTask = vi.fn(), onToggleSelection = vi.fn() } = {}) {
  const groups = groupRootsByStage([workOrderStage({ id: 1, name: 'Analisi' }), workOrderStage({ id: 2, name: 'Sviluppo', sort_order: 1 })], buildBoardTree(tasks))
  render(
    <TaskBoardRoadmapView
      groups={groups}
      allGroups={groups}
      today={TODAY}
      isReadOnly={isReadOnly}
      selectedTaskIds={new Set()}
      onToggleSelection={onToggleSelection}
      onOpenTask={onOpenTask}
    />,
  )
  return { onOpenTask, onToggleSelection }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('TaskBoardRoadmapView', () => {
  it('groups the tasks under their phase and hides an empty "No phase"', () => {
    renderRoadmap([
      boardTask({ id: 10, title: 'Requisiti', work_order_stage_id: 1, start_date: '2026-11-23', end_date: '2026-11-27' }),
      boardTask({ id: 11, title: 'Build', work_order_stage_id: 2, start_date: '2026-12-01', end_date: '2026-12-10' }),
    ])

    expect(within(screen.getByRole('group', { name: 'Analisi' })).getByRole('button', { name: 'Requisiti' })).toBeInTheDocument()
    expect(within(screen.getByRole('group', { name: 'Sviluppo' })).getByRole('button', { name: 'Build' })).toBeInTheDocument()
    expect(screen.queryByRole('group', { name: 'No phase' })).not.toBeInTheDocument()
  })

  it('opens the task from its bar, whose name tells the dates and the state', () => {
    const { onOpenTask } = renderRoadmap([
      boardTask({ id: 10, title: 'Requisiti', start_date: '2026-11-02', end_date: '2026-11-06' }),
    ])

    const bar = screen.getByRole('button', { name: /^Requisiti: .+, In lavorazione, Overdue$/ })
    fireEvent.click(bar)

    expect(onOpenTask).toHaveBeenCalledWith(10)
  })

  it('keeps an undated task on screen, marked "No dates"', () => {
    renderRoadmap([boardTask({ id: 10, title: 'Da pianificare', start_date: null, end_date: null })])

    expect(screen.getByRole('button', { name: 'Da pianificare' })).toBeInTheDocument()
    expect(screen.getByText('No dates')).toBeInTheDocument()
  })

  it('nests sub-tasks under their parent and selects only roots', () => {
    const { onToggleSelection } = renderRoadmap([
      boardTask({ id: 10, title: 'Padre', end_date: '2026-11-27' }),
      boardTask({ id: 11, title: 'Figlio', parent_task_id: 10, work_order_stage_id: null, end_date: '2026-11-26' }),
    ])

    expect(screen.getByRole('button', { name: 'Figlio' })).toBeInTheDocument()
    expect(screen.getAllByRole('checkbox')).toHaveLength(1)
    fireEvent.click(screen.getByRole('checkbox', { name: 'Padre' }))
    expect(onToggleSelection).toHaveBeenCalledWith(10)
  })

  it('shows no checkbox on a read-only board', () => {
    renderRoadmap([boardTask({ id: 10, title: 'Padre' })], { isReadOnly: true })

    expect(screen.queryByRole('checkbox')).not.toBeInTheDocument()
  })
})
