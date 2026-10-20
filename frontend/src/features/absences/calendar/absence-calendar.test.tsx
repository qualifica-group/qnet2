import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import { AbsenceCalendar } from '@/features/absences/calendar/absence-calendar'
import { absenceWrapper } from '@/features/absences/requests/absence-request-fixtures'
import type { AbsenceCalendar as AbsenceCalendarData } from '@/features/absences/types'

const fetchAbsenceCalendar = vi.fn()
vi.mock('@/features/absences/api', () => ({
  fetchAbsenceCalendar: (...args: unknown[]) => fetchAbsenceCalendar(...args),
}))
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ labels }: { labels: { triggerLabel: string } }) => <span>{labels.triggerLabel}</span>,
}))

function buildCalendar(overrides: Partial<AbsenceCalendarData> = {}): AbsenceCalendarData {
  return {
    scope: 'team',
    days: [
      { date: '2026-12-07', is_non_working_day: false, is_holiday: false },
      { date: '2026-12-08', is_non_working_day: true, is_holiday: true },
      { date: '2026-12-09', is_non_working_day: false, is_holiday: false },
    ],
    people: [
      {
        id: 1,
        name: 'Anna Rossi',
        absences: [
          { request_id: 10, date: '2026-12-07', status: 'approved', absence_type_name: 'Ferie', is_full_day: true, minutes: null, can_open: true },
          { request_id: 11, date: '2026-12-09', status: 'submitted', absence_type_name: 'Permesso', is_full_day: false, minutes: 120, can_open: false },
        ],
      },
      { id: 2, name: 'Bruno Bianchi', absences: [] },
    ],
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(new Date(2026, 11, 15))
  fetchAbsenceCalendar.mockReset()
})

afterEach(() => {
  vi.useRealTimers()
})

describe('AbsenceCalendar', () => {
  it('requests the current month and renders people, days and both states with their labels', async () => {
    fetchAbsenceCalendar.mockResolvedValue(buildCalendar())
    render(<AbsenceCalendar onOpen={vi.fn()} />, { wrapper: absenceWrapper() })

    const table = await screen.findByRole('table', { name: i18n.t('absences.calendar.caption') })
    expect(fetchAbsenceCalendar).toHaveBeenCalledWith({ from: '2026-12-01', to: '2026-12-31' })
    expect(within(table).getByRole('rowheader', { name: 'Anna Rossi' })).toBeInTheDocument()
    expect(within(table).getByRole('rowheader', { name: 'Bruno Bianchi' })).toBeInTheDocument()
    expect(within(table).getByRole('columnheader', { name: /8/ })).toHaveAttribute('title', i18n.t('absences.calendar.holiday'))
    expect(within(table).getByRole('button', { name: /Anna Rossi, 07\/12\/2026: Ferie .*Approvata/ })).toBeInTheDocument()
    expect(within(table).getByRole('img', { name: /Anna Rossi, 09\/12\/2026: Permesso \(2h.*Inviata/ })).toBeInTheDocument()
  })

  it('opens the detail only from an openable cell', async () => {
    fetchAbsenceCalendar.mockResolvedValue(buildCalendar())
    const onOpen = vi.fn()
    render(<AbsenceCalendar onOpen={onOpen} />, { wrapper: absenceWrapper() })

    fireEvent.click(await screen.findByRole('button', { name: /Anna Rossi, 07\/12\/2026/ }))
    expect(onOpen).toHaveBeenCalledWith(10)
    expect(screen.queryByRole('button', { name: /09\/12\/2026/ })).not.toBeInTheDocument()
  })

  it('moves to the next month and back to the current one', async () => {
    fetchAbsenceCalendar.mockResolvedValue(buildCalendar())
    render(<AbsenceCalendar onOpen={vi.fn()} />, { wrapper: absenceWrapper() })
    await screen.findByRole('table')

    fireEvent.click(screen.getByRole('button', { name: i18n.t('absences.calendar.next') }))
    expect(fetchAbsenceCalendar).toHaveBeenLastCalledWith({ from: '2027-01-01', to: '2027-01-31' })

    fireEvent.click(screen.getByRole('button', { name: i18n.t('absences.calendar.currentMonth') }))
    expect(fetchAbsenceCalendar).toHaveBeenLastCalledWith({ from: '2026-12-01', to: '2026-12-31' })
  })

  it('offers the function filter only when the actor sees more than themselves', async () => {
    fetchAbsenceCalendar.mockResolvedValue(buildCalendar({ scope: 'own' }))
    render(<AbsenceCalendar onOpen={vi.fn()} />, { wrapper: absenceWrapper() })
    await screen.findByRole('table')

    expect(screen.queryByText(i18n.t('absences.calendar.functionFilter'))).not.toBeInTheDocument()
  })

  it('shows an error when the calendar cannot be loaded', async () => {
    fetchAbsenceCalendar.mockRejectedValue(new Error('boom'))
    render(<AbsenceCalendar onOpen={vi.fn()} />, { wrapper: absenceWrapper() })

    expect(await screen.findByRole('alert')).toHaveTextContent(i18n.t('absences.calendar.error'))
  })
})
