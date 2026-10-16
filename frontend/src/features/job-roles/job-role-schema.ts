import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schema of the job role form, built as a factory so the messages are
 * localized. Mirrors the frozen backend contract (spec 0271) 1:1.
 */

/** Backend `name` column limit (`max:191`, unique). */
const NAME_MAX_LENGTH = 191
/** Backend `description` limit (`max:1000`). */
const DESCRIPTION_MAX_LENGTH = 1000

export function buildJobRoleSchema(t: TFunction) {
  return z.object({
    name: z.string().min(1, t('jobRoles.form.nameRequired')).max(NAME_MAX_LENGTH, t('jobRoles.form.nameMax')),
    description: z.string().max(DESCRIPTION_MAX_LENGTH, t('jobRoles.form.descriptionMax')).nullable(),
    // Multiselect activities (for-select standard): ids, full replace.
    job_activity_ids: z.array(z.number()),
  })
}

export type JobRoleFormValues = z.infer<ReturnType<typeof buildJobRoleSchema>>
