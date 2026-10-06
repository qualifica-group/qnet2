import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { TooltipProvider } from '@/components/ui/tooltip'
import { TaskEndDate } from '@/features/tasks/task-end-date'

/** User directive 2026-10-06: the task's end date reads like the commessa board's. */

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(new Date(2026, 9, 6, 10, 0))
})

afterEach(() => {
  vi.useRealTimers()
})

function renderEndDate(endDate: string | null, group: 'open' | 'closed_positive' | null) {
  return render(
    <TooltipProvider>
      <TaskEndDate endDate={endDate} statusGroup={group} />
    </TooltipProvider>,
  )
}

describe('TaskEndDate', () => {
  it('shows a passed end date on an open task as the red "Scaduto" chip', () => {
    renderEndDate('2026-10-01', 'open')

    expect(screen.getByLabelText(`01/10/2026, ${i18n.t('workOrders.taskBoard.task.overdue')}`)).toHaveClass('text-destructive')
  })

  it('shows today as the "Oggi" chip', () => {
    renderEndDate('2026-10-06', 'open')

    expect(screen.getByLabelText(`06/10/2026, ${i18n.t('workOrders.taskBoard.task.dueToday')}`)).toBeInTheDocument()
  })

  it('keeps the plain date on a closed task', () => {
    renderEndDate('2026-10-01', 'closed_positive')

    expect(screen.getByText('01/10/2026')).not.toHaveAttribute('aria-label')
  })
})
