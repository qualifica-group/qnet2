import type { SkillFormValues } from '@/features/skills/skill-schema'
import type { Skill, SkillPayload, UpdateSkillPayload } from '@/features/skills/types'

/** Form values of a saved skill. */
export function skillFormValues(skill: Skill): SkillFormValues {
  return {
    name: skill.name,
    category: skill.category,
    description: skill.description,
    is_active: skill.is_active,
    job_roles: skill.job_roles.map((role) => ({ job_role_id: role.id, min_level: role.min_level })),
  }
}

/** A blank optional text is sent as `null`. */
function textValue(text: string | null): string | null {
  return text === null || text.trim() === '' ? null : text.trim()
}

/** Builds the create payload; rows without a job role (refused by the schema) are dropped. */
export function buildCreatePayload(values: SkillFormValues): SkillPayload {
  return {
    name: values.name.trim(),
    category: textValue(values.category),
    description: textValue(values.description),
    is_active: values.is_active,
    job_roles: values.job_roles.flatMap((row) =>
      row.job_role_id === null ? [] : [{ id: row.job_role_id, min_level: row.min_level }],
    ),
  }
}

function sameJobRoles(next: SkillPayload['job_roles'], original: Skill['job_roles']): boolean {
  const levels = new Map(original.map((role) => [role.id, role.min_level]))
  return next.length === original.length && next.every((role) => levels.get(role.id) === role.min_level)
}

/** Builds a partial PATCH payload with only the fields that changed from the saved skill. */
export function buildUpdatePayload(values: SkillFormValues, original: Skill): UpdateSkillPayload {
  const next = buildCreatePayload(values)
  const payload: UpdateSkillPayload = {}
  if (next.name !== original.name) payload.name = next.name
  if (next.category !== original.category) payload.category = next.category
  if (next.description !== original.description) payload.description = next.description
  if (next.is_active !== original.is_active) payload.is_active = next.is_active
  if (!sameJobRoles(next.job_roles, original.job_roles)) payload.job_roles = next.job_roles
  return payload
}
