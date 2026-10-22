import type { TFunction } from 'i18next'
import { formatDate } from '@/lib/formatting/date-display'
import { formatAbsenceMinutes } from '@/features/absences/absence-duration-format'
import type { AbsenceRequest } from '@/features/absences/types'

/** `dd/mm/yyyy` for a single day, `from - to` for a range. */
export function formatAbsencePeriod(request: Pick<AbsenceRequest, 'starts_on' | 'ends_on'>): string {
  return request.starts_on === request.ends_on
    ? formatDate(request.starts_on)
    : `${formatDate(request.starts_on)} - ${formatDate(request.ends_on)}`
}

/** Days + hours of the person's day: fixed minutes once submitted; the asked minutes for an hourly draft; otherwise "calculated on submit". */
export function formatAbsenceDuration(
  request: Pick<AbsenceRequest, 'minutes' | 'requested_minutes' | 'is_full_day' | 'daily_minutes'>,
  t: TFunction,
): string {
  const minutes = request.minutes ?? (request.is_full_day ? null : request.requested_minutes)
  return minutes === null ? t('absences.requests.detail.durationPending') : formatAbsenceMinutes(minutes, request.daily_minutes, t)
}
