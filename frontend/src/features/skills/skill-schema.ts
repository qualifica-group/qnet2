import { z } from 'zod'
import type { TFunction } from 'i18next'
import { SKILL_LEVELS } from '@/features/skills/skill-level'

/** Backend limits (spec 0286): `name` max 255 unique, `category` max 100. */
const NAME_MAX_LENGTH = 255
const CATEGORY_MAX_LENGTH = 100

/**
 * Zod schema of the skill form, a factory so the messages are localized. A job role row without a
 * job role, or the same job role twice, is refused on `job_roles` (the server refuses it too).
 */
export function buildSkillSchema(t: TFunction) {
  return z.object({
    name: z
      .string()
      .trim()
      .min(1, t('skills.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('skills.form.nameMax')),
    category: z.string().max(CATEGORY_MAX_LENGTH, t('skills.form.categoryMax')).nullable(),
    description: z.string().nullable(),
    is_active: z.boolean(),
    job_roles: z
      .array(
        z.object({
          job_role_id: z.number().nullable(),
          min_level: z.union(SKILL_LEVELS.map((level) => z.literal(level)) as [z.ZodLiteral<1>, z.ZodLiteral<2>, z.ZodLiteral<3>, z.ZodLiteral<4>]),
        }),
      )
      .superRefine((rows, ctx) => {
        if (rows.some((row) => row.job_role_id === null)) {
          ctx.addIssue({ code: 'custom', message: t('skills.form.jobRoleRequired') })
        }
        const ids = rows.flatMap((row) => (row.job_role_id === null ? [] : [row.job_role_id]))
        if (new Set(ids).size !== ids.length) {
          ctx.addIssue({ code: 'custom', message: t('skills.form.jobRoleDuplicate') })
        }
      }),
  })
}

export type SkillFormValues = z.infer<ReturnType<typeof buildSkillSchema>>
export type SkillJobRoleRow = SkillFormValues['job_roles'][number]
