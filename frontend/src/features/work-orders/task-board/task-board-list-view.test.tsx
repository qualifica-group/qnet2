import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import type { ReactNode } from 'react'
import i18n from '@/i18n'
import { TooltipProvider } from '@/components/ui/tooltip'
import { boardTask, workOrderStage } from '@/features/work-orders/task-board/task-board-fixtures'
import { TaskBoardListView } from '@/features/work-orders/task-board/task-board-list-view'
import type { BoardStageGroup } from '@/features/work-orders/task-board/task-board-filters'

const moveMutateMock = vi.fn()
const reorderMutateMock = vi.fn()
const renameMutateMock = vi.fn()
const deleteMutateMock = vi.fn()
const closeMutateMock = vi.fn()
const reopenMutateMock = vi.fn()

vi.mock('@/features/work-orders/task-board/use-task-board-mutations', () => ({
  useMoveBoardTask: () => ({ mutate: moveMutateMock }),
  useReorderWorkOrderStages: () => ({ mutate: reorderMutateMock }),
  useRenameWorkOrderStage: () => ({ mutate: renameMutateMock }),
  useDeleteWorkOrderStage: () => ({ mutate: deleteMutateMock }),
  useCloseWorkOrderStage: () => ({ mutate: closeMutateMock }),
  useReopenWorkOrderStage: () => ({ mutate: reopenMutateMock }),
}))

const HANDLE_LABEL = 'Drag to move task'

function noop() {}

/** The end-date chip wraps itself in a `Tooltip`, whose Radix primitive requires an ancestor `TooltipProvider` (mounted once at `App.tsx` in the real app). */
function renderBoard(ui: ReactNode) {
  return render(<TooltipProvider>{ui}</TooltipProvider>)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  moveMutateMock.mockReset()
  reorderMutateMock.mockReset()
  renameMutateMock.mockReset()
  deleteMutateMock.mockReset()
  closeMutateMock.mockReset()
  reopenMutateMock.mockReset()
})

describe('TaskBoardListView — grouping (AC-026)', () => {
  it('renders one group per fase in sort_order, plus a trailing "Senza fase", each with its own counter', () => {
    const stageA = workOrderStage({ id: 1, name: 'Sopralluogo', sort_order: 0 })
    const stageB = workOrderStage({ id: 2, name: 'Esecuzione', sort_order: 1 })
    const groups: BoardStageGroup[] = [
      { stage: stageA, roots: [{ task: boardTask({ id: 101, work_order_stage_id: 1 }), children: [] }] },
      {
        stage: stageB,
        roots: [
          { task: boardTask({ id: 102, work_order_stage_id: 2 }), children: [] },
          { task: boardTask({ id: 103, work_order_stage_id: 2 }), children: [] },
        ],
      },
      { stage: null, roots: [{ task: boardTask({ id: 104, work_order_stage_id: null }), children: [] }] },
    ]

    const { container } = renderBoard(
      <TaskBoardListView
        workOrderId={9}
        groups={groups}
        allGroups={groups}
        today="2026-09-22"
        isReadOnly={false}
        selectedTaskIds={new Set()}
        onToggleSelection={noop}
        onOpenTask={noop}
        onAddTask={noop}
        unstagedLoggedMinutes={0}
      />,
    )

    const text = container.textContent ?? ''
    expect(text.indexOf('Sopralluogo')).toBeGreaterThanOrEqual(0)
    expect(text.indexOf('Sopralluogo')).toBeLessThan(text.indexOf('Esecuzione'))
    expect(text.indexOf('Esecuzione')).toBeLessThan(text.indexOf('No phase'))

    // Sopralluogo: 1 task, Esecuzione: 2 tasks, No phase: 1 task.
    expect(screen.getAllByText('2')).toHaveLength(1)
    expect(screen.getAllByText('1')).toHaveLength(2)
  })
})

describe('TaskBoardListView — read-only (AC-029)', () => {
  it('renders no drag handles, no checkboxes and no fase menu', () => {
    const stageA = workOrderStage({ id: 1, name: 'Sopralluogo', sort_order: 0 })
    const groups: BoardStageGroup[] = [
      { stage: stageA, roots: [{ task: boardTask({ id: 101, work_order_stage_id: 1 }), children: [] }] },
    ]

    renderBoard(
      <TaskBoardListView
        workOrderId={9}
        groups={groups}
        allGroups={groups}
        today="2026-09-22"
        isReadOnly
        selectedTaskIds={new Set()}
        onToggleSelection={noop}
        onOpenTask={noop}
        onAddTask={noop}
        unstagedLoggedMinutes={0}
      />,
    )

    expect(screen.queryByRole('button', { name: 'Drag to reorder phase' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: HANDLE_LABEL })).not.toBeInTheDocument()
    expect(screen.queryByRole('checkbox')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Task' })).not.toBeInTheDocument()
  })
})
