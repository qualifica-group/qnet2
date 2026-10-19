/**
 * Compact per-day list of approved absences (spec 0278 AC-021). Icon + text,
 * never colour alone. Renders nothing for a day without absences.
 */

import { useTranslation } from 'react-i18next'
import { CalendarOff } from 'lucide-react'
import { formatMinutesLabel } from '@/features/time-entries/time-entry-format'
import type { DayAbsence } from '@/features/time-entries/types'

interface TimeEntryDayAbsencesProps {
  absences: DayAbsence[]
}

export function TimeEntryDayAbsences({ absences }: TimeEntryDayAbsencesProps) {
  const { t } = useTranslation()

  if (absences.length === 0) {
    return null
  }

  return (
    <ul className="mt-3 space-y-1">
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
