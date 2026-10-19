import { hoursTextToMinutes, minutesToHoursText } from '@/features/absences/balances/balance-format'
import type { StaffCourseFormValues } from '@/features/staff-courses/staff-course-schema'
import type { StaffCourse, StaffCoursePayload, UpdateStaffCoursePayload } from '@/features/staff-courses/types'

/** Form values of a saved course: minutes become the decimal hours the field shows. */
export function staffCourseFormValues(course: StaffCourse): StaffCourseFormValues {
  return {
    name: course.name,
    description: course.description,
    duration_hours: course.duration_minutes === null ? '' : minutesToHoursText(course.duration_minutes),
    validity_months: course.validity_months,
    is_active: course.is_active,
    job_role_ids: course.job_roles.map((role) => role.id),
  }
}

/** A blank description is sent as `null`. */
function descriptionValue(description: string | null): string | null {
  return description === null || description.trim() === '' ? null : description
}

function minutesValue(hours: string): number | null {
  return hours.trim() === '' ? null : hoursTextToMinutes(hours)
}

/** Same set of ids regardless of the order they were picked in. */
function sameIds(a: number[], b: number[]): boolean {
  const sortedB = [...b].sort((x, y) => x - y)
  return a.length === b.length && [...a].sort((x, y) => x - y).every((id, index) => id === sortedB[index])
}

/** Builds the create payload: every field the form owns. */
export function buildCreatePayload(values: StaffCourseFormValues): StaffCoursePayload {
  return {
    name: values.name.trim(),
    description: descriptionValue(values.description),
    duration_minutes: minutesValue(values.duration_hours),
    validity_months: values.validity_months,
    is_active: values.is_active,
    job_role_ids: values.job_role_ids,
  }
}

/** Builds a partial PATCH payload with only the fields that changed from the saved course. */
export function buildUpdatePayload(values: StaffCourseFormValues, original: StaffCourse): UpdateStaffCoursePayload {
  const next = buildCreatePayload(values)
  const payload: UpdateStaffCoursePayload = {}
  if (next.name !== original.name) payload.name = next.name
  if (next.description !== original.description) payload.description = next.description
  if (next.duration_minutes !== original.duration_minutes) payload.duration_minutes = next.duration_minutes
  if (next.validity_months !== original.validity_months) payload.validity_months = next.validity_months
  if (next.is_active !== original.is_active) payload.is_active = next.is_active
  if (!sameIds(next.job_role_ids, original.job_roles.map((role) => role.id))) payload.job_role_ids = next.job_role_ids
  return payload
}
