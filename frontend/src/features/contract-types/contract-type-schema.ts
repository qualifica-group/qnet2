import { z } from 'zod'
import type { TFunction } from 'i18next'

/** Backend `name` limit (`max:100`, spec 0290). */
const NAME_MAX_LENGTH = 100

/** Zod schema of the contract type form, a factory so the messages are localized. */
export function buildContractTypeSchema(t: TFunction) {
  return z.object({
    name: z
      .string()
      .trim()
      .min(1, t('contractTypes.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('contractTypes.form.nameMax')),
    is_active: z.boolean(),
    sort_order: z
      .number({ error: t('contractTypes.form.sortOrderInvalid') })
      .int(t('contractTypes.form.sortOrderInvalid'))
      .min(0, t('contractTypes.form.sortOrderInvalid')),
  })
}

export type ContractTypeFormValues = z.infer<ReturnType<typeof buildContractTypeSchema>>
