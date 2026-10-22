import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import type { AbsenceCalendar, AbsenceCalendarEntry } from '@/features/absences/types'
import { AbsenceCalendarCell } from '@/features/absences/calendar/absence-calendar-cell'
import { formatDayHeader } from '@/features/absences/calendar/calendar-month'

const NON_WORKING_CLASS = 'bg-muted/40'
const NO_ENTRIES: AbsenceCalendarEntry[] = []

interface AbsenceCalendarGridProps {
  calendar: AbsenceCalendar
  today: string
  onOpen: (requestId: number) => void
  /** Whether the actor may request an absence for that person (own row, or any with manageAll). */
  canCreateFor: (personId: number) => boolean
  onCreate: (date: string, person: { id: number; name: string }) => void
}

/** Entries of one person keyed by date (a day may hold two hourly requests). */
function entriesByDate(entries: AbsenceCalendarEntry[]): Map<string, AbsenceCalendarEntry[]> {
  const byDate = new Map<string, AbsenceCalendarEntry[]>()
  for (const entry of entries) {
    byDate.set(entry.date, [...(byDate.get(entry.date) ?? []), entry])
  }
  return byDate
}

/** People absent on each day (one per person even with two hourly requests). */
function countAbsentPerDay(calendar: AbsenceCalendar): Map<string, number> {
  const counts = new Map<string, number>()
  for (const person of calendar.people) {
    for (const date of new Set(person.absences.map((entry) => entry.date))) {
      counts.set(date, (counts.get(date) ?? 0) + 1)
    }
  }
  return counts
}

/**
 * People x days table: the name column stays fixed while the days scroll
 * inside their own container, so the page never scrolls sideways.
 */
export function AbsenceCalendarGrid({ calendar, today, onOpen, canCreateFor, onCreate }: AbsenceCalendarGridProps) {
  const { t, i18n } = useTranslation()
  const absentPerDay = countAbsentPerDay(calendar)

  return (
    <div className="overflow-x-auto rounded-lg border border-border bg-card">
      <table className="w-full border-collapse text-xs">
        <caption className="sr-only">{t('absences.calendar.caption')}</caption>
        <thead>
          <tr>
            <th
              scope="col"
              className="sticky left-0 z-10 min-w-36 max-w-48 border-b border-border bg-card px-2 py-1 text-left font-medium"
            >
              {t('absences.calendar.person')}
            </th>
            {calendar.days.map((day) => {
              const { day: dayNumber, weekday } = formatDayHeader(day.date, i18n.language)
              return (
                <th
                  key={day.date}
                  scope="col"
                  title={day.is_holiday ? t('absences.calendar.holiday') : undefined}
                  className={cn(
                    'min-w-8 border-b border-border px-0.5 py-1 text-center font-medium',
                    day.is_non_working_day && NON_WORKING_CLASS,
                    day.is_holiday && 'text-destructive',
                    day.date === today && 'bg-primary/15',
                  )}
                >
                  <span className="block text-[10px] text-muted-foreground">{weekday}</span>
                  <span className="block">{dayNumber}</span>
                </th>
              )
            })}
          </tr>
        </thead>
        <tbody>
          {calendar.people.map((person) => {
            const byDate = entriesByDate(person.absences)
            const closures = new Map(person.closed_days.map((closed) => [closed.date, closed.name]))
            const canCreate = canCreateFor(person.id)
            return (
              <tr key={person.id} className="hover:bg-muted/40">
                <th
                  scope="row"
                  className="sticky left-0 z-10 max-w-48 truncate border-b border-border bg-card px-2 py-1 text-left font-normal"
                >
                  {person.name}
                </th>
                {calendar.days.map((day) => (
                  <td
                    key={day.date}
                    className={cn(
                      'border-b border-border p-0.5',
                      (day.is_non_working_day || closures.has(day.date)) && NON_WORKING_CLASS,
                    )}
                  >
                    <AbsenceCalendarCell
                      personName={person.name}
                      date={day.date}
                      entries={byDate.get(day.date) ?? NO_ENTRIES}
                      closureName={closures.get(day.date) ?? null}
                      canCreate={canCreate && !day.is_non_working_day}
                      onOpen={onOpen}
                      onCreate={() => onCreate(day.date, { id: person.id, name: person.name })}
                    />
                  </td>
                ))}
              </tr>
            )
          })}
        </tbody>
        <tfoot>
          <tr>
            <th scope="row" className="sticky left-0 z-10 bg-card px-2 py-1 text-left font-medium">
              {t('absences.calendar.absentRow')}
            </th>
            {calendar.days.map((day) => (
              <td key={day.date} className="px-0.5 py-1 text-center tabular-nums text-muted-foreground">
                {absentPerDay.get(day.date) ?? ''}
              </td>
            ))}
          </tr>
        </tfoot>
      </table>
    </div>
  )
}
