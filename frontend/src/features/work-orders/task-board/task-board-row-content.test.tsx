import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import { TooltipProvider } from '@/components/ui/tooltip'
import i18n from '@/i18n'
import { boardTask } from '@/features/work-orders/task-board/task-board-fixtures'
import { TaskBoardRowContent } from '@/features/work-orders/task-board/task-board-row-content'
import { TaskBoardStageSummary } from '@/features/work-orders/task-board/task-board-task-meta'

const TODAY = '2026-09-22'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function field(label: string): HTMLElement {
  const term = screen.getByText(label, { selector: 'dt' })
  return term.parentElement as HTMLElement
}

describe('TaskBoardRowContent — labelled fields', () => {
  it('labels every value and shows people through the profile hover card', () => {
    render(
      <TaskBoardRowContent
        task={boardTask({
          requester: { id: 21, name: 'Bruno Bianchi' },
          assignees: [
            { id: 31, name: 'Dario Dini' },
            { id: 32, name: 'Carla Conti' },
          ],
          watchers: [],
        })}
        today={TODAY}
        onOpen={vi.fn()}
      />,
    )

    for (const label of ['Status', 'Type', 'Priority', 'End date', 'Requester', 'Assignees', 'Watchers', 'Completion']) {
      expect(screen.getByText(label, { selector: 'dt' })).toBeInTheDocument()
    }
    expect(within(field('Requester')).getByRole('button', { name: /Bruno Bianchi/ })).toBeInTheDocument()
    expect(within(field('Assignees')).getByRole('button', { name: /Dario Dini/ })).toBeInTheDocument()
    expect(within(field('Assignees')).getByRole('button', { name: /Carla Conti/ })).toBeInTheDocument()
    expect(within(field('Watchers')).getByText('Nobody')).toBeInTheDocument()
  })

  it('shows the completion percentage and the hours worked against the estimate', () => {
    render(
      <TaskBoardRowContent
        task={boardTask({ estimated_minutes: 60, actual_minutes: 90 })}
        today={TODAY}
        onOpen={vi.fn()}
      />,
    )

    expect(within(field('Completion')).getByText('25%')).toBeInTheDocument()
    const hours = field('Hours worked / estimated')
    expect(within(hours).getByText(/1h 30m \/ 1h/)).toBeInTheDocument()
    expect(within(hours).getByRole('img', { name: 'Over the estimate' })).toBeInTheDocument()
  })

  it('says when a task has no estimate', () => {
    render(
      <TaskBoardRowContent task={boardTask({ estimated_minutes: null, actual_minutes: 30 })} today={TODAY} onOpen={vi.fn()} />,
    )

    expect(within(field('Hours worked / estimated')).getByText('(no estimate)')).toBeInTheDocument()
  })
})

describe('TaskBoardStageSummary', () => {
  it('shows the phase completion and its total hours worked against the total estimate', () => {
    render(
      <TaskBoardStageSummary
        metrics={{ count: 2, completionPercentage: 40, estimatedMinutes: 120, actualMinutes: 45 }}
        loggedMinutes={90}
      />,
    )

    expect(screen.getByText('40%')).toBeInTheDocument()
    expect(screen.getByText(/45m \/ 2h/)).toBeInTheDocument()
    expect(screen.queryByRole('img', { name: 'Over the estimate' })).not.toBeInTheDocument()
  })

  it('shows the segnatempo total, separate from the task-based hours (spec 0163 AC-007)', () => {
    render(
      <TaskBoardStageSummary
        metrics={{ count: 2, completionPercentage: 40, estimatedMinutes: 120, actualMinutes: 45 }}
        loggedMinutes={90}
      />,
    )

    const entry = screen.getByText('Logged minutes').parentElement as HTMLElement
    expect(within(entry).getByText('1h 30m')).toBeInTheDocument()
  })
})

describe('TaskBoardRowContent — description and end date', () => {
  it('shows the short description under the title', () => {
    render(
      <TaskBoardRowContent
        task={boardTask({ description_excerpt: 'Controllare quadro elettrico e sensori' })}
        today={TODAY}
        onOpen={vi.fn()}
      />,
    )

    expect(screen.getByText('Controllare quadro elettrico e sensori')).toBeInTheDocument()
  })

  it('wraps a passed end date in the overdue chip with its tooltip', async () => {
    render(
      <TooltipProvider>
        <TaskBoardRowContent task={boardTask({ end_date: '2026-09-20' })} today={TODAY} onOpen={vi.fn()} />
      </TooltipProvider>,
    )

    const chip = within(field('End date')).getByLabelText(/Overdue/)
    expect(chip).toHaveClass('bg-destructive/10')
    fireEvent.focus(chip)
    expect(await screen.findByRole('tooltip')).toHaveTextContent('Overdue')
  })

  it('marks an end date falling today, and leaves a future one plain', () => {
    const { rerender } = render(
      <TooltipProvider>
        <TaskBoardRowContent task={boardTask({ end_date: TODAY })} today={TODAY} onOpen={vi.fn()} />
      </TooltipProvider>,
    )
    expect(within(field('End date')).getByLabelText(/Due today/)).toBeInTheDocument()

    rerender(
      <TooltipProvider>
        <TaskBoardRowContent task={boardTask({ end_date: '2026-09-30' })} today={TODAY} onOpen={vi.fn()} />
      </TooltipProvider>,
    )
    expect(within(field('End date')).queryByLabelText(/Overdue|Due today/)).not.toBeInTheDocument()
  })
})
