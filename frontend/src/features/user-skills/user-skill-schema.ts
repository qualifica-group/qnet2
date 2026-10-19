import { z } from 'zod'
import type { TFunction } from 'i18next'
import { dayOf } from '@/features/absences/calendar/calendar-month'
import { SKILL_LEVELS } from '@/features/skills/skill-level'

/** Backend `notes` limit (`max:2000`, spec 0286). */
export const NOTES_MAX_LENGTH = 2000

/** Zod schema of the person skill form, mirroring the server rules: person, skill, level 1..4, assessment day not after today, notes. */
export function buildUserSkillSchema(t: TFunction) {
  return z
    .object({
      user_id: z.number().nullable(),
      skill_id: z.number().nullable(),
      level: z.number().nullable(),
      assessed_on: z.string(),
      notes: z.string().max(NOTES_MAX_LENGTH, t('userSkills.form.notesTooLong')),
    })
    .superRefine((values, ctx) => {
      if (values.user_id === null) {
        ctx.addIssue({ code: 'custom', path: ['user_id'], message: t('userSkills.form.userRequired') })
      }
      if (values.skill_id === null) {
        ctx.addIssue({ code: 'custom', path: ['skill_id'], message: t('userSkills.form.skillRequired') })
      }
      if (values.level === null || !SKILL_LEVELS.some((level) => level === values.level)) {
        ctx.addIssue({ code: 'custom', path: ['level'], message: t('userSkills.form.levelRequired') })
      }
      if (values.assessed_on === '') {
        ctx.addIssue({ code: 'custom', path: ['assessed_on'], message: t('userSkills.form.dateRequired') })
      } else if (values.assessed_on > dayOf(new Date())) {
        ctx.addIssue({ code: 'custom', path: ['assessed_on'], message: t('userSkills.form.dateInFuture') })
      }
    })
}

export type UserSkillFormValues = z.infer<ReturnType<typeof buildUserSkillSchema>>
