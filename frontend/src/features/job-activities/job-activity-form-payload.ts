import type { JobActivityFormValues } from '@/features/job-activities/job-activity-schema'
import type { JobActivityDetail, JobActivityPayload, UpdateJobActivityPayload } from '@/features/job-activities/types'

/** A blank description is sent as `null`. */
function descriptionValue(description: string | null): string | null {
  return description === null || description.trim() === '' ? null : description
}

/** Builds the create payload: every field the form owns. */
export function buildCreatePayload(values: JobActivityFormValues): JobActivityPayload {
  return { name: values.name.trim(), description: descriptionValue(values.description) }
}

/** Builds a partial PATCH payload with only the fields that changed from the saved activity. */
export function buildUpdatePayload(values: JobActivityFormValues, original: JobActivityDetail): UpdateJobActivityPayload {
  const payload: UpdateJobActivityPayload = {}

  if (values.name.trim() !== original.name) {
    payload.name = values.name.trim()
  }
  if (descriptionValue(values.description) !== original.description) {
    payload.description = descriptionValue(values.description)
  }

  return payload
}
