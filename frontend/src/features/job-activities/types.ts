/**
 * Job activities CRUD types. Source of truth: the frozen `data_contract` of
 * spec 0271 (HR org structure). The link to job roles is edited from the role
 * only; the activity shows it read-only.
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/** Domain key shared by the table, the module registry and the ability checks. */
export const JOB_ACTIVITIES_DOMAIN = 'job-activities'

/** Compact job role reference carried by the activity: `{ id, name }`. */
export interface JobActivityRole {
  id: number
  name: string
}

/** Single job activity returned by GET/POST/PATCH /job-activities (envelope `data`). */
export interface JobActivityDetail {
  id: number
  name: string
  description: string | null
  job_role_ids: number[]
  job_roles: JobActivityRole[]
  created_at: string
  updated_at: string
  abilities: { update: boolean; delete: boolean }
}

/** A `JobActivityDetail` carrying the authorization metadata the detail's provider reads. */
export interface JobActivityDetailWithPermissions extends JobActivityDetail {
  permissions: ResourcePermissions
}

/** Payload for POST /job-activities. */
export interface JobActivityPayload {
  name: string
  description: string | null
}

/** Payload for PATCH /job-activities/{id}: only the fields that changed. */
export type UpdateJobActivityPayload = Partial<JobActivityPayload>

/** Discriminated form mode shared by the form hook and the detail/create components. */
export type JobActivityFormMode =
  | { type: 'create' }
  | { type: 'edit'; jobActivity: JobActivityDetailWithPermissions }
