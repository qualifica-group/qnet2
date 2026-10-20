import { isSkillLevel } from '@/features/skills/skill-level'
import type { UserSkillFormValues } from '@/features/user-skills/user-skill-schema'
import type { UserSkill, UserSkillPayload } from '@/features/user-skills/types'

export const EMPTY_USER_SKILL_FORM: UserSkillFormValues = {
  user_id: null,
  skill_id: null,
  level: null,
  assessed_on: '',
  notes: '',
}

/** Form values of a saved person skill. */
export function userSkillFormValues(userSkill: UserSkill): UserSkillFormValues {
  return {
    user_id: userSkill.user.id,
    skill_id: userSkill.skill.id,
    level: userSkill.level,
    assessed_on: userSkill.assessed_on,
    notes: userSkill.notes ?? '',
  }
}

/** Wire payload; null only when the schema was bypassed (a required value missing). A blank note is sent as `null`. */
export function toUserSkillPayload(values: UserSkillFormValues): UserSkillPayload | null {
  if (values.user_id === null || values.skill_id === null || !isSkillLevel(values.level)) {
    return null
  }
  const notes = values.notes.trim()
  return {
    user_id: values.user_id,
    skill_id: values.skill_id,
    level: values.level,
    assessed_on: values.assessed_on,
    notes: notes === '' ? null : notes,
  }
}
