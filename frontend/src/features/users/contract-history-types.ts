import type { RelationshipType } from '@/features/users/types'

/** Salary periodicity of a contract condition (spec 0272 D-4). */
export const SALARY_PERIODS = ['monthly', 'yearly', 'hourly'] as const
export type SalaryPeriod = (typeof SALARY_PERIODS)[number]

/** Permission gating the salary and the payment account (spec 0272 D-5). */
export const MANAGE_COMPENSATION_PERMISSION = 'users.manageCompensation'

/** Permission gating the history actions (create / delete the last condition). */
export const UPDATE_USERS_PERMISSION = 'users.update'

/** One condition of a person's contract history (`GET /users/{user}/employment-contracts`). */
export interface EmploymentContract {
  id: number
  starts_on: string
  /** Derived server-side: the day before the next condition starts; null on the latest one. */
  ends_on: string | null
  is_current: boolean
  relationship_type: RelationshipType | null
  company: { id: number; label: string } | null
  collective_agreement_level: { id: number; label: string } | null
  /** `salary_*` travel only with `users.manageCompensation`. */
  salary_amount?: string | null
  salary_period?: SalaryPeriod | null
  change_reason: string | null
  created_at: string | null
}

/** Body of `POST /users/{user}/employment-contracts`. */
export interface CreateEmploymentContractPayload {
  starts_on: string
  relationship_type: RelationshipType
  company_id: number | null
  collective_agreement_level_id: number | null
  salary_amount?: number | null
  salary_period?: SalaryPeriod | null
  change_reason: string | null
}
