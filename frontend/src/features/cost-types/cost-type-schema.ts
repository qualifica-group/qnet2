import { z } from 'zod'
import type { TFunction } from 'i18next'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { COST_TYPE_NATURES } from '@/features/cost-types/types'

/**
 * Zod schema of the cost type form, built as a factory so the messages are
 * localized. Mirrors the frozen backend contract (specs 0210, 0249) 1:1.
 * `sort_order` is server-managed (reorder endpoint) and has no form field.
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
    nature: z.enum(COST_TYPE_NATURES, { message: t('costTypes.form.natureRequired') }),
    color: z
      .string()
      .min(1, t('costTypes.form.colorRequired'))
      .refine((token) => swatchClassFor(token) !== undefined, t('costTypes.form.colorInvalid')),
    is_active: z.boolean(),
  })
}

export type CostTypeFormValues = z.infer<ReturnType<typeof buildCostTypeSchema>>
