/** Person skills and coverage. Source of truth: the frozen `data_contract` of spec 0286. Dates are `Y-m-d`. */

import type { SkillLevel } from '@/features/skills/skill-level'

export const USER_SKILLS_DOMAIN = 'user-skills'

export const COVERAGE_STATUSES = ['covered', 'below', 'missing'] as const
export type CoverageStatus = (typeof COVERAGE_STATUSES)[number]

/** Tabs of the page. */
export const USER_SKILL_VIEWS = ['people', 'coverage'] as const
export type UserSkillView = (typeof USER_SKILL_VIEWS)[number]

/** A skill assigned to a person (`/user-skills`, envelope `data`). */
export interface UserSkill {
  id: number
  user: { id: number; name: string }
  skill: { id: number; name: string; category: string | null }
  level: SkillLevel
  assessed_on: string
  notes: string | null
  assessed_by: { id: number; name: string }
  /** The actor may edit and remove this row (manager of the person, or `manage`); the server enforces it. */
  can_edit: boolean
  created_at: string
  updated_at: string
}

/** POST/PATCH body of a person skill. */
export interface UserSkillPayload {
  user_id: number
  skill_id: number
  level: SkillLevel
  assessed_on: string
  notes: string | null
}

/** Query of `GET /user-skills/coverage`. */
export interface CoverageParams {
  status?: CoverageStatus
  business_function_id?: number
  skill_id?: number
}

export interface CoverageRow {
  user: { id: number; name: string }
  skill: { id: number; name: string; category: string | null }
  required_level: SkillLevel
  level: SkillLevel | null
  status: CoverageStatus
  user_skill_id: number | null
}

export interface Coverage {
  rows: CoverageRow[]
}
