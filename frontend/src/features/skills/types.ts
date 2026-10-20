import type { ResourcePermissions } from '@/features/authorization/types'
import type { SkillLevel } from '@/features/skills/skill-level'

/** Domain key shared by the table, the module registry and the ability checks. */
export const SKILLS_DOMAIN = 'skills'

/** A job role requiring the skill, with the minimum level it asks for. */
export interface SkillJobRole {
  id: number
  name: string
  min_level: SkillLevel
}

/** Single skill returned by GET/POST/PATCH /skills (envelope `data`, spec 0286). */
export interface Skill {
  id: number
  name: string
  category: string | null
  description: string | null
  is_active: boolean
  job_roles: SkillJobRole[]
  users_count: number
  created_at: string
  updated_at: string
}

/** A `Skill` carrying the authorization metadata the detail's provider reads. */
export interface SkillDetail extends Skill {
  permissions: ResourcePermissions
}

/** POST body of the catalogue. */
export interface SkillPayload {
  name: string
  category: string | null
  description: string | null
  is_active: boolean
  job_roles: { id: number; min_level: SkillLevel }[]
}

export type UpdateSkillPayload = Partial<SkillPayload>

/** Discriminated form mode shared by the form hook and the detail/create components. */
export type SkillFormMode = { type: 'create' } | { type: 'edit'; skill: SkillDetail }
