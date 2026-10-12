import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schema of the cost type form, built as a factory so the messages are
 * localized. Mirrors the frozen backend contract (spec 0210) 1:1.
 */

/** Backend `name` column limit (`max:100`, unique). */
const NAME_MAX_LENGTH = 100
/** Backend `description` column limit (`max:500`). */
const DESCRIPTION_MAX_LENGTH = 500

export function buildCostTypeSchema(t: TFunction) {
  return z.object({
    name: z
      .string()
      .min(1, t('costTypes.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('costTypes.form.nameMax')),
    description: z.string().max(DESCRIPTION_MAX_LENGTH, t('costTypes.form.descriptionMax')).nullable(),
    sort_order: z
      .number(t('costTypes.form.sortOrderInvalid'))
      .int(t('costTypes.form.sortOrderInvalid'))
      .min(0, t('costTypes.form.sortOrderInvalid')),
    is_active: z.boolean(),
  })
}

export type CostTypeFormValues = z.infer<ReturnType<typeof buildCostTypeSchema>>
