import { z } from 'zod'
import type { TFunction } from 'i18next'
import { hoursTextToMinutes, isHoursText } from '@/features/absences/balances/balance-format'

/** Backend `name` limit (`max:255`, unique, spec 0284). */
const NAME_MAX_LENGTH = 255
/** Backend `validity_months` range (`1..240`). */
export const VALIDITY_MONTHS_MIN = 1
export const VALIDITY_MONTHS_MAX = 240

/**
 * Zod schema of the course form, a factory so the messages are localized. The duration is typed in
 * hours (decimal text) and sent as whole minutes (`min:1`); empty means "not specified".
 */
export function buildStaffCourseSchema(t: TFunction) {
  return z.object({
    name: z
      .string()
      .trim()
      .min(1, t('staffCourses.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('staffCourses.form.nameMax')),
    description: z.string().nullable(),
    duration_hours: z
      .string()
      .refine((value) => value.trim() === '' || (isHoursText(value) && hoursTextToMinutes(value) >= 1), {
        message: t('staffCourses.form.durationInvalid'),
      }),
    validity_months: z
      .number({ error: t('staffCourses.form.validityInvalid') })
      .int(t('staffCourses.form.validityInvalid'))
      .min(VALIDITY_MONTHS_MIN, t('staffCourses.form.validityInvalid'))
      .max(VALIDITY_MONTHS_MAX, t('staffCourses.form.validityInvalid'))
      .nullable(),
    is_active: z.boolean(),
    job_role_ids: z.array(z.number()),
  })
}

export type StaffCourseFormValues = z.infer<ReturnType<typeof buildStaffCourseSchema>>
