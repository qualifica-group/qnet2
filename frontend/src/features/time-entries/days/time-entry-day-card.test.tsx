import { beforeAll, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { TooltipProvider } from '@/components/ui/tooltip'
import { TimeEntryDayCard } from '@/features/time-entries/days/time-entry-day-card'
import type { DaySummary } from '@/features/time-entries/types'

// Isolates the card from AG Grid (tested via the row-mutations/columns of its
// own module) and keeps this test focused on the card's own wiring.
vi.mock('@/features/time-entries/days/time-entry-day-entries-table', () => ({
  TimeEntryDayEntriesTable: () => <div data-entries-table-stub />,
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <TooltipProvider>{children}</TooltipProvider>
    </QueryClientProvider>
  )
}

function daySummary(overrides: Partial<DaySummary> = {}): DaySummary {
  return {
    date: '2026-09-14',
    weekday: 1,
    is_holiday: false,
    is_non_working_day: false,
    is_active: true,
    target_minutes: 480,
    total_minutes: 450,
    utilization_percentage: 93.75,
    status: 'under_target',
    day_note: null,
    entries: [],
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('TimeEntryDayCard (AC-035)', () => {
  it("renders today expanded, with no collapse control, and shows the entries table", () => {
    const Wrapper = wrapper()
    render(
      <Wrapper>
        <TimeEntryDayCard
          canWrite
          day={daySummary()}
          isExpanded
          isToday
          onCreateForDate={vi.fn()}
          onEditEntry={vi.fn()}
          onToggle={vi.fn()}
        />
      </Wrapper>,
    )

    expect(screen.queryByRole('button', { name: 'Collapse' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Expand' })).not.toBeInTheDocument()
    expect(screen.getByText(/Under target/i)).toBeInTheDocument()
  })

  it('renders a past day collapsed, with a working expand toggle', () => {
    const onToggle = vi.fn()
    const Wrapper = wrapper()
    render(
      <Wrapper>
        <TimeEntryDayCard
          canWrite
          day={daySummary({ date: '2026-09-13' })}
          isExpanded={false}
          isToday={false}
          onCreateForDate={vi.fn()}
          onEditEntry={vi.fn()}
          onToggle={onToggle}
        />
      </Wrapper>,
    )

    const toggle = screen.getByRole('button', { name: 'Expand' })
    toggle.click()
    expect(onToggle).toHaveBeenCalledTimes(1)
  })

  it('shows the active badge and the tracked/utilization mini-boxes', () => {
    const Wrapper = wrapper()
    render(
      <Wrapper>
        <TimeEntryDayCard
          canWrite
          day={daySummary({ is_active: true, total_minutes: 90, utilization_percentage: 18.75 })}
          isExpanded={false}
          isToday={false}
          onCreateForDate={vi.fn()}
          onEditEntry={vi.fn()}
          onToggle={vi.fn()}
        />
      </Wrapper>,
    )

    expect(screen.getByText('Active')).toBeInTheDocument()
    expect(screen.getByText('1h 30m')).toBeInTheDocument()
    expect(screen.getByText('19%')).toBeInTheDocument()
  })
})
