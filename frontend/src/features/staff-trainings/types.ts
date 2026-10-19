/**
 * Staff training register and compliance. Source of truth: the frozen `data_contract` of
 * spec 0284. Dates are `Y-m-d` calendar days.
 */

export const STAFF_TRAININGS_DOMAIN = 'staff-trainings'

export const STAFF_TRAINING_OUTCOMES = ['passed', 'failed'] as const
export type StaffTrainingOutcome = (typeof STAFF_TRAINING_OUTCOMES)[number]

export const COMPLIANCE_STATUSES = ['valid', 'expiring', 'expired', 'missing'] as const
export type ComplianceStatus = (typeof COMPLIANCE_STATUSES)[number]

/** Tabs of the page. */
export const STAFF_TRAINING_VIEWS = ['register', 'compliance'] as const
export type StaffTrainingView = (typeof STAFF_TRAINING_VIEWS)[number]

export interface StaffTrainingAttachment {
  id: number
  name: string
  size: number
}

/** A course done by a person (`/staff-trainings`, envelope `data`). */
export interface StaffTraining {
  id: number
  user: { id: number; name: string }
  course: { id: number; name: string; validity_months: number | null }
  completed_on: string
  /** Snapshot of completion + course validity at save time; `null` when the course never expires. */
  expires_on: string | null
  outcome: StaffTrainingOutcome
  notes: string | null
  attachment: StaffTrainingAttachment | null
  recorded_by: { id: number; name: string }
  created_at: string
  updated_at: string
}

/** POST/PATCH body of a training. */
export interface StaffTrainingPayload {
  user_id: number
  staff_course_id: number
  completed_on: string
  outcome: StaffTrainingOutcome
  notes: string | null
}

/** Query of `GET /staff-trainings/compliance`. */
export interface ComplianceParams {
  status?: ComplianceStatus
  business_function_id?: number
  staff_course_id?: number
}

export interface ComplianceRow {
  user: { id: number; name: string }
  course: { id: number; name: string }
  required: boolean
  status: ComplianceStatus
  completed_on: string | null
  expires_on: string | null
  training_id: number | null
}

export interface Compliance {
  expiring_days: number
  rows: ComplianceRow[]
}
