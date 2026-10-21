import type { RelationshipType, WorkingTime } from '@/features/users/types'

/** Salary periodicity of a contract condition (spec 0272 D-4). */
export const SALARY_PERIODS = ['monthly', 'yearly', 'hourly'] as const
export type SalaryPeriod = (typeof SALARY_PERIODS)[number]

/** Permission gating the salary and the payment account (spec 0272 D-5). */
export const MANAGE_COMPENSATION_PERMISSION = 'users.manageCompensation'

/**
 * The `users` field key of the contract history and of the condition in force
 * (spec 0283): visible to read them, editable to add or delete a condition.
 */
export const CONTRACTS_FIELD = 'employment.contracts'

/** One condition of a person's contract history (`GET /users/{user}/employment-contracts`). */
export interface EmploymentContract {
  id: number
  starts_on: string
  /** Derived server-side: the day before the next condition starts; null on the latest one. */
  ends_on: string | null
  is_current: boolean
  relationship_type: RelationshipType | null
  contract_type: { id: number; name: string } | null
  working_time: WorkingTime | null
  /** `Y-m-d`; informative only, it never closes the condition (spec 0291 D-4). */
  contract_expires_on: string | null
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
  /** Required by the server when the relationship is `employee` (spec 0290 D-3). */
  contract_type_id: number | null
  /** Required by the server when the relationship is `employee` (spec 0291 D-3). */
  working_time: WorkingTime | null
  contract_expires_on: string | null
  company_id: number | null
  collective_agreement_level_id: number | null
  salary_amount?: number | null
  salary_period?: SalaryPeriod | null
  change_reason: string | null
}
