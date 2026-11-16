import { z } from 'zod'
import type { TFunction } from 'i18next'
import { QUOTE_SYSTEM_STATUS_BEHAVIORS } from '@/features/quote-system-statuses/types'

/** Backend `name` limit (`max:100`). */
const NAME_MAX_LENGTH = 100

/** Backend `color` limit (`max:32`). */
const COLOR_MAX_LENGTH = 32

/** Create/edit schema, a factory so messages are localized; the colour is required, like the server. */
export function buildQuoteSystemStatusSchema(t: TFunction) {
  return z.object({
    name: z
      .string()
      .trim()
      .min(1, t('quoteSystemStatuses.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('quoteSystemStatuses.form.nameMax')),
    color: z
      .string()
      .min(1, t('quoteSystemStatuses.form.colorRequired'))
      .max(COLOR_MAX_LENGTH, t('quoteSystemStatuses.form.colorMax')),
    behavior: z.enum(QUOTE_SYSTEM_STATUS_BEHAVIORS),
  })
}

export type QuoteSystemStatusFormValues = z.infer<ReturnType<typeof buildQuoteSystemStatusSchema>>
