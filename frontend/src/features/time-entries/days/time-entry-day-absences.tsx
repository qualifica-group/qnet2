/**
 * Compact per-day list of approved absences (spec 0278 AC-021) and of the
 * company closure of the day (spec 0304 D-14). Icon + text, never colour
 * alone. Renders nothing for a day without either.
 */

import { useTranslation } from 'react-i18next'
import { CalendarOff } from 'lucide-react'
import { formatMinutesLabel } from '@/features/time-entries/time-entry-format'
import type { DaySummary } from '@/features/time-entries/types'

interface TimeEntryDayAbsencesProps {
  absences: DaySummary['absences']
  closure: DaySummary['closure']
}

export function TimeEntryDayAbsences({ absences, closure }: TimeEntryDayAbsencesProps) {
  const { t } = useTranslation()

  if (absences.length === 0 && closure === null) {
    return null
  }

  return (
    <ul className="mt-3 space-y-1">
      {closure !== null ? (
        <li className="flex items-center gap-1.5 text-xs font-medium text-foreground">
          <CalendarOff aria-hidden="true" className="size-3.5 shrink-0 text-muted-foreground" />
          <span className="min-w-0 truncate">{t('timeEntries.dayCard.closure', { name: closure.name })}</span>
        </li>
      ) : null}
      {absences.map((absence) => (
        <li
          className="flex items-center gap-1.5 text-xs font-medium text-foreground"
          key={absence.id}
        >
          <CalendarOff aria-hidden="true" className="size-3.5 shrink-0 text-muted-foreground" />
          <span className="min-w-0 truncate">
            {absence.absence_type_name}
            {' - '}
            {absence.is_full_day ? t('timeEntries.dayCard.absenceFullDay') : formatMinutesLabel(absence.minutes)}
          </span>
        </li>
      ))}
    </ul>
  )
}
