import { useTranslation } from 'react-i18next'
import { formatAbsenceMinutes } from '@/features/absences/absence-duration-format'
import { AbsenceRequestStatusBadge } from '@/features/absences/requests/absence-request-status-badge'
import { formatAbsencePeriod } from '@/features/absences/requests/absence-request-format'
import { AbsenceTypeChip } from '@/features/absences/summary/absence-type-chip'
import type { AbsenceSummaryUpcoming } from '@/features/absences/types'

interface AbsenceUpcomingItemProps {
  absence: AbsenceSummaryUpcoming
  /** Daily minutes of the person, for the days + hours duration. */
  dailyMinutes: number
  onOpen: (id: number) => void
}

/** One upcoming absence: type, period, mode (half day / hours), duration and state; clicking opens the detail. */
export function AbsenceUpcomingItem({ absence, dailyMinutes, onOpen }: AbsenceUpcomingItemProps) {
  const { t } = useTranslation()
  const mode = absence.is_full_day
    ? null
    : absence.day_part
      ? `${t('absences.requests.form.modes.half_day')} (${t(`absences.requests.form.dayParts.${absence.day_part}`)})`
      : t('absences.requests.form.modes.hours')

  return (
    <li>
      <button
        type="button"
        onClick={() => onOpen(absence.request_id)}
        className="flex w-full flex-wrap items-center gap-x-3 gap-y-1 rounded-md px-2 py-1.5 text-left text-xs outline-none hover:bg-muted/60 focus-visible:ring-[3px] focus-visible:ring-ring/50"
      >
        <AbsenceTypeChip name={absence.absence_type.name} color={absence.absence_type.color} />
        <span className="tabular-nums">{formatAbsencePeriod(absence)}</span>
        {mode ? <span className="text-muted-foreground">{mode}</span> : null}
        {absence.minutes !== null ? (
          <span className="text-muted-foreground">{formatAbsenceMinutes(absence.minutes, dailyMinutes, t)}</span>
        ) : null}
        <span className="ml-auto">
          <AbsenceRequestStatusBadge status={absence.status} />
        </span>
      </button>
    </li>
  )
}
