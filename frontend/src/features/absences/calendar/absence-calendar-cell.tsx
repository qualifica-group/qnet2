import { useTranslation } from 'react-i18next'
import { CalendarOff, Plus } from 'lucide-react'
import { cn } from '@/lib/utils'
import { formatDate } from '@/lib/formatting/date-display'
import { formatMinutesLabel } from '@/features/time-entries/time-entry-format'
import { absenceTypeDotClass } from '@/features/absences/absence-type-color'
import type { AbsenceCalendarEntry } from '@/features/absences/types'
import { CALENDAR_STATUS_ICON, CALENDAR_STATUS_STYLE } from '@/features/absences/calendar/calendar-status'

const BOX_CLASS = 'relative mx-auto flex size-6 items-center justify-center overflow-hidden rounded-sm'
const FOCUS_CLASS = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring'

interface AbsenceCalendarCellProps {
  personName: string
  date: string
  entries: AbsenceCalendarEntry[]
  /** Name of the company closure that makes this a non-working day for the person. */
  closureName: string | null
  /** Empty working day the actor may create a request on. */
  canCreate: boolean
  onOpen: (requestId: number) => void
  onCreate: () => void
}

function EntryCell({ personName, date, entries, onOpen }: Pick<AbsenceCalendarCellProps, 'personName' | 'date' | 'entries' | 'onOpen'>) {
  const { t } = useTranslation()
  const entry = entries[0]
  const Icon = CALENDAR_STATUS_ICON[entry.status]
  const description = entries
    .map((item) => {
      const duration = item.day_part
        ? t(`absences.requests.form.dayParts.${item.day_part}`)
        : item.minutes === null
          ? t('absences.calendar.fullDay')
          : formatMinutesLabel(item.minutes)
      return `${item.absence_type_name} (${duration}), ${t(`absences.status.${item.status}`)}`
    })
    .join('; ')
  const label = `${personName}, ${formatDate(date)}: ${description}`
  const halfClass =
    entry.day_part === 'morning' ? 'w-1/2 left-0' : entry.day_part === 'afternoon' ? 'w-1/2 right-0' : 'inset-x-0'
  const className = cn(BOX_CLASS, 'border border-border bg-card')
  const content = (
    <span
      aria-hidden="true"
      className={cn(
        'absolute inset-y-0 flex items-center justify-center',
        halfClass,
        absenceTypeDotClass(entry.color),
        CALENDAR_STATUS_STYLE[entry.status],
      )}
    >
      <Icon className="size-3 text-white" />
    </span>
  )

  return entry.can_open ? (
    <button type="button" title={label} aria-label={label} onClick={() => onOpen(entry.request_id)} className={cn(className, FOCUS_CLASS)}>
      {content}
    </button>
  ) : (
    <span role="img" title={label} aria-label={label} className={className}>
      {content}
    </span>
  )
}

/**
 * One person-day: an absence (colour of the type, state by icon and border, half day as half a cell),
 * a company closure, an empty working day the actor can open a request on, or nothing.
 */
export function AbsenceCalendarCell({ personName, date, entries, closureName, canCreate, onOpen, onCreate }: AbsenceCalendarCellProps) {
  const { t } = useTranslation()

  if (entries.length > 0) return <EntryCell personName={personName} date={date} entries={entries} onOpen={onOpen} />

  if (closureName) {
    const label = `${personName}, ${formatDate(date)}: ${t('absences.calendar.closure')} - ${closureName}`
    return (
      <span role="img" title={label} aria-label={label} className={cn(BOX_CLASS, 'text-muted-foreground')}>
        <CalendarOff className="size-3.5" aria-hidden="true" />
      </span>
    )
  }

  if (!canCreate) return null

  const label = t('absences.calendar.createFor', { person: personName, date: formatDate(date) })
  return (
    <button
      type="button"
      title={label}
      aria-label={label}
      onClick={onCreate}
      className={cn(BOX_CLASS, 'text-transparent hover:bg-accent hover:text-muted-foreground', FOCUS_CLASS, 'focus-visible:text-muted-foreground')}
    >
      <Plus className="size-3.5" aria-hidden="true" />
    </button>
  )
}
