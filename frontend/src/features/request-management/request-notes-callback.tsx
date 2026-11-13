import { useTranslation } from 'react-i18next'
import { CalendarClock } from 'lucide-react'
import { formatDateTimeOptionalTime } from '@/lib/formatting/date-display'

/**
 * The request's callback date above its notes (ticket #427847), so the
 * operator writing a note sees when the contact is due back. Same format as
 * the grid's "Data richiamo" column: the hour only when one was set.
 */
export function RequestNotesCallback({ value }: { value: string | null }) {
  const { t } = useTranslation()
  const formatted = formatDateTimeOptionalTime(value)

  return (
    <p className="flex items-center gap-1.5 text-muted-foreground">
      <CalendarClock aria-hidden="true" className="size-3.5 shrink-0" />
      {formatted
        ? t('requestManagement.notesDialog.callback', { date: formatted })
        : t('requestManagement.notesDialog.callbackNone')}
    </p>
  )
}
