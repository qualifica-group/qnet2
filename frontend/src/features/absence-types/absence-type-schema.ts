import { z } from 'zod'
import type { TFunction } from 'i18next'

/** Backend `name` limit (`max:100`, spec 0278). */
const NAME_MAX_LENGTH = 100

/** Zod schema of the absence type form, a factory so the messages are localized. */
export function buildAbsenceTypeSchema(t: TFunction) {
  return z.object({
    name: z
      .string()
      .trim()
      .min(1, t('absenceTypes.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('absenceTypes.form.nameMax')),
    deducts_balance: z.boolean(),
    requires_approval: z.boolean(),
    is_active: z.boolean(),
    sort_order: z
      .number({ error: t('absenceTypes.form.sortOrderInvalid') })
      .int(t('absenceTypes.form.sortOrderInvalid'))
      .min(0, t('absenceTypes.form.sortOrderInvalid')),
  })
}

export type AbsenceTypeFormValues = z.infer<ReturnType<typeof buildAbsenceTypeSchema>>
