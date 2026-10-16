import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schema of the job activity form, built as a factory so the messages are
 * localized. Mirrors the frozen backend contract (spec 0271) 1:1.
 */

/** Backend `name` column limit (`max:191`, unique). */
const NAME_MAX_LENGTH = 191
/** Backend `description` limit (`max:1000`). */
const DESCRIPTION_MAX_LENGTH = 1000

export function buildJobActivitySchema(t: TFunction) {
  return z.object({
    name: z.string().min(1, t('jobActivities.form.nameRequired')).max(NAME_MAX_LENGTH, t('jobActivities.form.nameMax')),
    description: z.string().max(DESCRIPTION_MAX_LENGTH, t('jobActivities.form.descriptionMax')).nullable(),
  })
}

export type JobActivityFormValues = z.infer<ReturnType<typeof buildJobActivitySchema>>
