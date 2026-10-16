import type { JobRoleFormValues } from '@/features/job-roles/job-role-schema'
import type { JobRoleDetail, JobRolePayload, UpdateJobRolePayload } from '@/features/job-roles/types'

/** A blank description is sent as `null`. */
function descriptionValue(description: string | null): string | null {
  return description === null || description.trim() === '' ? null : description
}

/** Same set of ids regardless of the order they were picked in. */
function sameIds(a: number[], b: number[]): boolean {
  const sortedB = [...b].sort((x, y) => x - y)
  return a.length === b.length && [...a].sort((x, y) => x - y).every((id, index) => id === sortedB[index])
}

/** Builds the create payload: every field the form owns. */
export function buildCreatePayload(values: JobRoleFormValues): JobRolePayload {
  return {
    name: values.name.trim(),
    description: descriptionValue(values.description),
    job_activity_ids: values.job_activity_ids,
  }
}

/** Builds a partial PATCH payload with only the fields that changed from the saved job role. */
export function buildUpdatePayload(values: JobRoleFormValues, original: JobRoleDetail): UpdateJobRolePayload {
  const payload: UpdateJobRolePayload = {}

  if (values.name.trim() !== original.name) {
    payload.name = values.name.trim()
  }
  if (descriptionValue(values.description) !== original.description) {
    payload.description = descriptionValue(values.description)
  }
  if (!sameIds(values.job_activity_ids, original.job_activity_ids)) {
    payload.job_activity_ids = values.job_activity_ids
  }

  return payload
}
