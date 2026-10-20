import type { ResourcePermissions } from '@/features/authorization/types'

/** Domain key shared by the table, the module registry and the ability checks. */
export const STAFF_COURSES_DOMAIN = 'staff-courses'

/** Compact job role reference carried by a course: `{ id, name }`. */
export interface StaffCourseJobRole {
  id: number
  name: string
}

/** Single course returned by GET/POST/PATCH /staff-courses (envelope `data`, spec 0284). */
export interface StaffCourse {
  id: number
  name: string
  description: string | null
  duration_minutes: number | null
  validity_months: number | null
  is_active: boolean
  job_roles: StaffCourseJobRole[]
  trainings_count: number
  created_at: string
  updated_at: string
}

/** A `StaffCourse` carrying the authorization metadata the detail's provider reads. */
export interface StaffCourseDetail extends StaffCourse {
  permissions: ResourcePermissions
}

/** POST body of the catalogue. */
export interface StaffCoursePayload {
  name: string
  description: string | null
  duration_minutes: number | null
  validity_months: number | null
  is_active: boolean
  job_role_ids: number[]
}

export type UpdateStaffCoursePayload = Partial<StaffCoursePayload>

/** Discriminated form mode shared by the form hook and the detail/create components. */
export type StaffCourseFormMode = { type: 'create' } | { type: 'edit'; course: StaffCourseDetail }
