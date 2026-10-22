import { minutesToTimeValue, timeValueToMinutes } from '@/features/time-entries/time-entry-format'
import type {
  AbsenceRequest,
  AbsenceTypeOption,
  CreateAbsenceRequestPayload,
  PreviewAbsenceRequestPayload,
  UpdateAbsenceRequestPayload,
} from '@/features/absences/types'
import {
  ABSENCE_MODES,
  type AbsenceMode,
  type AbsenceRequestFormValues,
} from '@/features/absences/requests/absence-request-schema'

export const EMPTY_ABSENCE_FORM: AbsenceRequestFormValues = {
  absence_type_id: null,
  user_id: null,
  mode: 'full_day',
  day_part: 'morning',
  starts_on: '',
  ends_on: '',
  requested_minutes: '',
  note: '',
}

const MODE_FLAG: Record<AbsenceMode, keyof Pick<AbsenceTypeOption, 'allows_full_day' | 'allows_half_day' | 'allows_hourly'>> = {
  full_day: 'allows_full_day',
  half_day: 'allows_half_day',
  hours: 'allows_hourly',
}

/** Modes the type admits (all of them while no type is picked yet). */
export function allowedAbsenceModes(type: AbsenceTypeOption | undefined): AbsenceMode[] {
  return type ? ABSENCE_MODES.filter((mode) => type[MODE_FLAG[mode]]) : [...ABSENCE_MODES]
}

/** Form values of an existing draft; the mode is read back from the request. */
export function absenceFormValues(request: AbsenceRequest): AbsenceRequestFormValues {
  return {
    absence_type_id: request.absence_type.id,
    user_id: null,
    mode: request.is_full_day ? 'full_day' : request.day_part !== null ? 'half_day' : 'hours',
    day_part: request.day_part ?? 'morning',
    starts_on: request.starts_on,
    ends_on: request.ends_on,
    requested_minutes: request.requested_minutes === null ? '' : minutesToTimeValue(request.requested_minutes),
    note: request.note ?? '',
  }
}

type WireFields = Omit<UpdateAbsenceRequestPayload, 'note'>

/** Wire fields of the mode: half day and hourly are one day (`ends_on = starts_on`); only the hourly one carries minutes. */
function toWireFields(values: AbsenceRequestFormValues, typeId: number): WireFields {
  const base = { absence_type_id: typeId, starts_on: values.starts_on }
  if (values.mode === 'full_day') {
    return { ...base, is_full_day: true, ends_on: values.ends_on }
  }
  if (values.mode === 'half_day') {
    return { ...base, is_full_day: false, day_part: values.day_part, ends_on: values.starts_on }
  }
  return {
    ...base,
    is_full_day: false,
    ends_on: values.starts_on,
    requested_minutes: timeValueToMinutes(values.requested_minutes),
  }
}

export function toAbsencePayload(values: AbsenceRequestFormValues): UpdateAbsenceRequestPayload | null {
  if (values.absence_type_id === null) {
    return null
  }
  const note = values.note.trim() === '' ? null : values.note.trim()
  return { ...toWireFields(values, values.absence_type_id), note }
}

/** Creation body: the person is sent only when somebody else is chosen. */
export function toCreatePayload(values: AbsenceRequestFormValues, submit: boolean): CreateAbsenceRequestPayload | null {
  const payload = toAbsencePayload(values)
  if (payload === null) {
    return null
  }
  return values.user_id === null ? { ...payload, submit } : { ...payload, user_id: values.user_id, submit }
}

/** The mode's own fields are filled: a lighter bar than the schema, just enough for the server to compute. */
function hasMinimumFields(values: AbsenceRequestFormValues): boolean {
  if (values.absence_type_id === null || values.starts_on === '') {
    return false
  }
  if (values.mode === 'full_day') {
    return values.ends_on !== '' && values.ends_on >= values.starts_on
  }
  return values.mode === 'half_day' || (timeValueToMinutes(values.requested_minutes) ?? 0) > 0
}

/** Body of the live preview, or null while the minimum fields are not valid. */
export function toPreviewPayload(
  values: AbsenceRequestFormValues,
  requestId: number | null,
): PreviewAbsenceRequestPayload | null {
  if (values.absence_type_id === null || !hasMinimumFields(values)) {
    return null
  }
  return {
    ...toWireFields(values, values.absence_type_id),
    ...(values.user_id !== null ? { user_id: values.user_id } : {}),
    ...(requestId !== null ? { request_id: requestId } : {}),
  }
}
