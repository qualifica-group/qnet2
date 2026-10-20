import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { formatDate } from '@/lib/formatting/date-display'
import { formatMinutesLabel } from '@/features/time-entries/time-entry-format'
import type { AbsenceCalendarEntry } from '@/features/absences/types'
import { CALENDAR_STATUS_CLASS, CALENDAR_STATUS_ICON } from '@/features/absences/calendar/calendar-status'

interface AbsenceCalendarCellProps {
  personName: string
  date: string
  entries: AbsenceCalendarEntry[]
  onOpen: (requestId: number) => void
}

/** One person-day: empty, or the absence of that day (openable when spec 0278 lets the actor see it). */
export function AbsenceCalendarCell({ personName, date, entries, onOpen }: AbsenceCalendarCellProps) {
  const { t } = useTranslation()
  const entry = entries[0]
  if (!entry) return null

  const Icon = CALENDAR_STATUS_ICON[entry.status]
  const description = entries
    .map((item) => {
      const duration = item.minutes === null ? t('absences.calendar.fullDay') : formatMinutesLabel(item.minutes)
      return `${item.absence_type_name} (${duration}), ${t(`absences.status.${item.status}`)}`
    })
    .join('; ')
  const label = `${personName}, ${formatDate(date)}: ${description}`
  const className = cn(
    'mx-auto flex size-6 items-center justify-center rounded-sm',
    CALENDAR_STATUS_CLASS[entry.status],
  )

  return entry.can_open ? (
    <button
      type="button"
      title={label}
      aria-label={label}
      onClick={() => onOpen(entry.request_id)}
      className={cn(className, 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring')}
    >
      <Icon className="size-3.5" aria-hidden="true" />
    </button>
  ) : (
    <span role="img" title={label} aria-label={label} className={className}>
      <Icon className="size-3.5" aria-hidden="true" />
    </span>
  )
}
