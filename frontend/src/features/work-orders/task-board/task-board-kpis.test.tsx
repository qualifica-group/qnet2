import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import '@/i18n'
import { TaskBoardKpis } from '@/features/work-orders/task-board/task-board-kpis'
import type { TaskBoardMetrics } from '@/features/work-orders/task-board/task-board-metrics'

function metrics(overrides: Partial<TaskBoardMetrics> = {}): TaskBoardMetrics {
  return {
    total: 10,
    completionPercentage: 40,
    overdueCount: 0,
    dueTodayCount: 2,
    estimatedMinutes: 480,
    actualMinutes: 90,
    ...overrides,
  }
}

describe('TaskBoardKpis', () => {
  it('renders every tile value', () => {
    render(<TaskBoardKpis metrics={metrics()} />)

    expect(screen.getByText('10')).toBeInTheDocument()
    expect(screen.getByText('40%')).toBeInTheDocument()
    expect(screen.getByText('2')).toBeInTheDocument()
    expect(screen.getByText('8h 00m')).toBeInTheDocument()
    expect(screen.getByText('1h 30m')).toBeInTheDocument()
  })

  it('accents the "Scaduti" tile only when the count is positive', () => {
    const { rerender } = render(<TaskBoardKpis metrics={metrics({ overdueCount: 0 })} />)
    expect(screen.getByText('Scaduti').closest('div.rounded-xl')).not.toHaveClass('ring-destructive/40')

    rerender(<TaskBoardKpis metrics={metrics({ overdueCount: 3 })} />)
    expect(screen.getByText('Scaduti').closest('div.rounded-xl')).toHaveClass('ring-destructive/40')
  })
})
