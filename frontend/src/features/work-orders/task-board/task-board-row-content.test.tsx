import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, fireEvent, render, screen, within } from '@testing-library/react'
import type { ReactNode } from 'react'
import { TooltipProvider } from '@/components/ui/tooltip'
import i18n from '@/i18n'
import { FULL_ACCESS_PERMISSIONS } from '@/features/tasks/task-fixtures'
import { boardTask } from '@/features/work-orders/task-board/task-board-fixtures'
import { TaskBoardRowContent } from '@/features/work-orders/task-board/task-board-row-content'
import { TaskBoardCellEditContext, type TaskBoardCellEdit } from '@/features/work-orders/task-board/task-board-row-columns'
import { TaskBoardStageSummary } from '@/features/work-orders/task-board/task-board-task-meta'
import type { BoardTask } from '@/features/work-orders/task-board/types'

const TODAY = '2026-09-22'

const OPTIONS = [
  { id: 7, label: 'Alta', meta: { color: 'red', icon: null } },
  { id: 8, label: 'Bassa', meta: { color: 'gray', icon: null } },
]

vi.mock('@/features/for-select/use-for-select', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/use-for-select')>()),
  useForSelect: () => ({
    data: { pages: [{ items: OPTIONS, pagination: { offset: 0, limit: 20, total: 2 } }] },
    isPending: false,
    isError: false,
    refetch: vi.fn(),
    hasNextPage: false,
    isFetchingNextPage: false,
    fetchNextPage: vi.fn(),
  }),
}))

const cellEdit: TaskBoardCellEdit = { commit: vi.fn(), complete: vi.fn(), reopen: vi.fn() }

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(cellEdit.commit).mockReset()
  vi.mocked(cellEdit.complete).mockReset()
  vi.mocked(cellEdit.reopen).mockReset()
})

function renderRow(task: BoardTask, { editable = true }: { editable?: boolean } = {}) {
  const row = <TaskBoardRowContent task={task} today={TODAY} onOpen={vi.fn()} />
  const wrapped: ReactNode = editable ? <TaskBoardCellEditContext.Provider value={cellEdit}>{row}</TaskBoardCellEditContext.Provider> : row
  return render(<TooltipProvider>{wrapped}</TooltipProvider>)
}

/** A cell by its own (stacked-layout) label. */
function cell(label: string): HTMLElement {
  return screen.getByText(label, { selector: 'span' }).parentElement as HTMLElement
}

describe('TaskBoardRowContent — a Task grid row (spec 0401)', () => {
  it('shows one cell per column, completion included (AC-001)', () => {
    renderRow(
      boardTask({
        requester: { id: 21, name: 'Bruno Bianchi' },
        assignees: [
          { id: 31, name: 'Dario Dini' },
          { id: 32, name: 'Carla Conti' },
        ],
      }),
      { editable: false },
    )

    for (const label of ['Status', 'Type', 'Priority', 'Start date', 'End date', 'Requester', 'Assignees', 'Completion', 'Hours worked / estimated']) {
      expect(cell(label)).toBeInTheDocument()
    }
    expect(within(cell('Completion')).getByText('25%')).toBeInTheDocument()
    expect(within(cell('Requester')).getByRole('button', { name: /Bruno Bianchi/ })).toBeInTheDocument()
    expect(within(cell('Assignees')).getByRole('button', { name: /Carla Conti/ })).toBeInTheDocument()
  })

  it('shows the hours worked against the estimate, and says when there is none', () => {
    const { unmount } = renderRow(boardTask({ estimated_minutes: 60, actual_minutes: 90 }), { editable: false })
    expect(within(cell('Hours worked / estimated')).getByText(/1h 30m \/ 1h/)).toBeInTheDocument()
    expect(within(cell('Hours worked / estimated')).getByRole('img', { name: 'Over the estimate' })).toBeInTheDocument()
    unmount()

    renderRow(boardTask({ estimated_minutes: null, actual_minutes: 30 }), { editable: false })
    expect(within(cell('Hours worked / estimated')).getByText('(no estimate)')).toBeInTheDocument()
  })

  it('keeps the short description as the title tooltip', () => {
    renderRow(boardTask({ description_excerpt: 'Controllare quadro elettrico e sensori' }))

    expect(screen.getByRole('button', { name: 'Verificare impianto' })).toHaveAttribute('title', 'Controllare quadro elettrico e sensori')
  })

  it('wraps a passed end date in the overdue chip, and marks one falling today', () => {
    const { unmount } = renderRow(boardTask({ end_date: '2026-09-20' }), { editable: false })
    expect(within(cell('End date')).getByLabelText(/Overdue/)).toHaveClass('bg-destructive/10')
    unmount()

    renderRow(boardTask({ end_date: TODAY }), { editable: false })
    expect(within(cell('End date')).getByLabelText(/Due today/)).toBeInTheDocument()
  })
})

describe('TaskBoardRowContent — complete check (AC-002)', () => {
  it('opens the complete flow from the check of a completable task', () => {
    const task = boardTask({ permissions: { ...FULL_ACCESS_PERMISSIONS, actions: { complete: true } } })
    renderRow(task)

    fireEvent.click(screen.getByRole('button', { name: 'Complete' }))

    expect(cellEdit.complete).toHaveBeenCalledWith(task)
  })

  it('reopens a completed task from its filled check when the actor may', () => {
    const task = boardTask({
      task_status: { ...boardTask().task_status, group: 'closed_positive' },
      permissions: { ...FULL_ACCESS_PERMISSIONS, actions: { uncomplete: true } },
    })
    renderRow(task)

    fireEvent.click(screen.getByRole('button', { name: 'Reopen' }))

    expect(cellEdit.reopen).toHaveBeenCalledWith(task)
  })

  it('shows a completed task the actor may not reopen as a plain icon', () => {
    renderRow(boardTask({ task_status: { ...boardTask().task_status, group: 'closed_positive' } }))

    expect(screen.getByRole('img', { name: 'Completed' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Reopen' })).not.toBeInTheDocument()
  })
})

describe('TaskBoardRowContent — in-place editing (AC-003/AC-004)', () => {
  it('commits a picked priority as the grid column id', () => {
    const task = boardTask()
    renderRow(task)

    fireEvent.click(screen.getByRole('button', { name: /Edit Priority/ }))
    fireEvent.click(screen.getByRole('option', { name: /Alta/ }))

    expect(cellEdit.commit).toHaveBeenCalledWith(task, 'task_priority', 7)
  })

  it('commits an end date on Enter and drops it on Escape', () => {
    const task = boardTask()
    renderRow(task)

    fireEvent.click(screen.getByRole('button', { name: /Edit End date/ }))
    fireEvent.change(screen.getByLabelText('End date', { selector: 'input' }), { target: { value: '2026-10-01' } })
    fireEvent.keyDown(screen.getByLabelText('End date', { selector: 'input' }), { key: 'Enter' })
    expect(cellEdit.commit).toHaveBeenCalledWith(task, 'end_date', '2026-10-01')

    vi.mocked(cellEdit.commit).mockReset()
    fireEvent.click(screen.getByRole('button', { name: /Edit Start date/ }))
    const input = screen.getByLabelText('Start date', { selector: 'input' })
    fireEvent.change(input, { target: { value: '2026-09-01' } })
    fireEvent.keyDown(input, { key: 'Escape' })
    expect(cellEdit.commit).not.toHaveBeenCalled()
  })

  it('commits the assignee set when the picker is left with a click outside', async () => {
    const task = boardTask({ assignees: [{ id: 7, name: 'Alta' }] })
    renderRow(task)

    fireEvent.click(screen.getByRole('button', { name: /Edit Assignees/ }))
    fireEvent.click(screen.getByRole('option', { name: /Bassa/ }))
    // Radix registers its outside-press listener one tick after opening.
    await act(() => new Promise((resolve) => setTimeout(resolve, 0)))
    fireEvent.pointerDown(document.body)

    expect(cellEdit.commit).toHaveBeenCalledWith(task, 'assignees', [7, 8])
  })

  it('renames the task from the pencil, trimming the new title', () => {
    const task = boardTask()
    renderRow(task)

    fireEvent.click(screen.getByRole('button', { name: /Edit Title/ }))
    const input = screen.getByLabelText('Title', { selector: 'input' })
    fireEvent.change(input, { target: { value: '  Nuovo titolo  ' } })
    fireEvent.keyDown(input, { key: 'Enter' })

    expect(cellEdit.commit).toHaveBeenCalledWith(task, 'title', 'Nuovo titolo')
  })

  it('opens no editor on a field the actor may not edit', () => {
    const fieldLocked = { visible: true, hidden: false, editable: false, readonly: true, required: false, disabled: false }
    renderRow(boardTask({ permissions: { ...FULL_ACCESS_PERMISSIONS, fields: { task_priority_id: fieldLocked } } }))

    expect(screen.queryByRole('button', { name: /Edit Priority/ })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Edit Status/ })).toBeInTheDocument()
  })

  it('is read-only outside the cell-edit provider (the drag overlay)', () => {
    renderRow(boardTask(), { editable: false })

    expect(screen.queryByRole('button', { name: /Edit / })).not.toBeInTheDocument()
  })
})

describe('TaskBoardStageSummary', () => {
  // Spec 0401 D-2 (REQUIREMENT CHANGED): completion moved from the phase header to the task rows.
  it('shows the total hours worked against the total estimate, and no completion', () => {
    render(<TaskBoardStageSummary metrics={{ count: 2, estimatedMinutes: 120, actualMinutes: 45 }} loggedMinutes={90} />)

    expect(screen.queryByText('Completion')).not.toBeInTheDocument()
    expect(screen.getByText(/45m \/ 2h/)).toBeInTheDocument()
    expect(screen.queryByRole('img', { name: 'Over the estimate' })).not.toBeInTheDocument()
  })

  it('shows the segnatempo total, separate from the task-based hours (spec 0163 AC-007)', () => {
    render(
      <TaskBoardStageSummary metrics={{ count: 2, estimatedMinutes: 120, actualMinutes: 45 }} loggedMinutes={90} />,
    )

    const entry = screen.getByText('Logged minutes').parentElement as HTMLElement
    expect(within(entry).getByText('1h 30m')).toBeInTheDocument()
  })
})
