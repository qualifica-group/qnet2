import { minutesToTimeValue, timeValueToMinutes } from '@/features/time-entries/time-entry-format'
import type { AbsenceRequest, UpdateAbsenceRequestPayload } from '@/features/absences/types'
import type { AbsenceRequestFormValues } from '@/features/absences/requests/absence-request-schema'

export const EMPTY_ABSENCE_FORM: AbsenceRequestFormValues = {
  absence_type_id: null,
  is_full_day: true,
  starts_on: '',
  ends_on: '',
  requested_minutes: '',
  note: '',
}

/** Form values of an existing draft. */
export function absenceFormValues(request: AbsenceRequest): AbsenceRequestFormValues {
  return {
    absence_type_id: request.absence_type.id,
    is_full_day: request.is_full_day,
    starts_on: request.starts_on,
    ends_on: request.ends_on,
    requested_minutes: request.requested_minutes === null ? '' : minutesToTimeValue(request.requested_minutes),
    note: request.note ?? '',
  }
}

/** Wire payload: an hourly request is one day (`ends_on = starts_on`) with the minutes indicated. */
export function toAbsencePayload(values: AbsenceRequestFormValues): UpdateAbsenceRequestPayload | null {
  if (values.absence_type_id === null) {
    return null
  }
  const note = values.note.trim() === '' ? null : values.note.trim()
  if (values.is_full_day) {
    return {
      absence_type_id: values.absence_type_id,
      is_full_day: true,
      starts_on: values.starts_on,
      ends_on: values.ends_on,
      note,
    }
  }
  return {
    absence_type_id: values.absence_type_id,
    is_full_day: false,
    starts_on: values.starts_on,
    ends_on: values.starts_on,
    requested_minutes: timeValueToMinutes(values.requested_minutes),
    note,
  }
}
