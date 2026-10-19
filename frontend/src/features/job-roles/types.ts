/**
 * Job roles CRUD types. Source of truth: the frozen `data_contract` of spec
 * 0271 (HR org structure). A job role groups people and is linked
 * to 0..n job activities.
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/** Domain key shared by the table, the module registry and the ability checks. */
export const JOB_ROLES_DOMAIN = 'job-roles'

/** Compact activity reference carried by the job role: `{ id, name }`. */
export interface JobRoleActivity {
  id: number
  name: string
}

/** Single job role returned by GET/POST/PATCH /job-roles (envelope `data`). */
export interface JobRoleDetail {
  id: number
  name: string
  description: string | null
  job_activity_ids: number[]
  job_activities: JobRoleActivity[]
  users_count: number
  created_at: string
  updated_at: string
  abilities: { update: boolean; delete: boolean }
}

/** A `JobRoleDetail` carrying the authorization metadata the detail's provider reads. */
export interface JobRoleDetailWithPermissions extends JobRoleDetail {
  permissions: ResourcePermissions
}

/** Payload for POST /job-roles. */
export interface JobRolePayload {
  name: string
  description: string | null
  job_activity_ids: number[]
}

/** Payload for PATCH /job-roles/{id}: only the fields that changed. */
export type UpdateJobRolePayload = Partial<JobRolePayload>

/** Discriminated form mode shared by the form hook and the detail/create components. */
export type JobRoleFormMode =
  | { type: 'create' }
  | { type: 'edit'; jobRole: JobRoleDetailWithPermissions }
