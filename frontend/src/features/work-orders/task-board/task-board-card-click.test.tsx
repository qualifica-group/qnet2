import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import type { ReactNode } from 'react'
import { TooltipProvider } from '@/components/ui/tooltip'
import i18n from '@/i18n'
import { boardTask } from '@/features/work-orders/task-board/task-board-fixtures'
import { TaskBoardKanbanCard } from '@/features/work-orders/task-board/task-board-kanban-card'
import { TaskBoardTaskRow } from '@/features/work-orders/task-board/task-board-task-row'

const TODAY = '2026-09-22'
const TASK_ID = 101
const DESCRIPTION = 'Controllo quadro elettrico'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function renderBoard(ui: ReactNode) {
  return render(<TooltipProvider>{ui}</TooltipProvider>)
}

function node() {
  return { task: boardTask({ id: TASK_ID, description_excerpt: DESCRIPTION }), children: [] }
}

const variants = [
  {
    name: 'list row',
    renderCard: (onOpenTask: (id: number) => void, onToggleSelection: (id: number) => void) =>
      renderBoard(
        <ul>
          <TaskBoardTaskRow
            node={node()}
            today={TODAY}
            isReadOnly={false}
            isSelected={false}
            onToggleSelection={onToggleSelection}
            onOpenTask={onOpenTask}
          />
        </ul>,
      ),
  },
  {
    name: 'kanban card',
    renderCard: (onOpenTask: (id: number) => void, onToggleSelection: (id: number) => void) =>
      renderBoard(
        <ul>
          <TaskBoardKanbanCard
            node={node()}
            today={TODAY}
            isReadOnly={false}
            isSelected={false}
            onToggleSelection={onToggleSelection}
            onOpenTask={onOpenTask}
          />
        </ul>,
      ),
  },
]

describe.each(variants)('TaskBoard $name — whole card opens the task', ({ renderCard }) => {
  it('opens the task on a click anywhere in the card, not only on the title', () => {
    const onOpenTask = vi.fn()
    renderCard(onOpenTask, vi.fn())

    fireEvent.click(screen.getByText(DESCRIPTION))

    expect(onOpenTask).toHaveBeenCalledExactlyOnceWith(TASK_ID)
  })

  it('opens the task once from the title button', () => {
    const onOpenTask = vi.fn()
    renderCard(onOpenTask, vi.fn())

    fireEvent.click(screen.getByRole('button', { name: 'Verificare impianto' }))

    expect(onOpenTask).toHaveBeenCalledExactlyOnceWith(TASK_ID)
  })

  it('keeps the controls on their own click: checkbox and people do not open the task', () => {
    const onOpenTask = vi.fn()
    const onToggleSelection = vi.fn()
    renderCard(onOpenTask, onToggleSelection)

    fireEvent.click(screen.getByRole('checkbox'))
    fireEvent.click(screen.getByRole('button', { name: /Dario Dini/ }))

    expect(onToggleSelection).toHaveBeenCalledWith(TASK_ID)
    expect(onOpenTask).not.toHaveBeenCalled()
  })
})
