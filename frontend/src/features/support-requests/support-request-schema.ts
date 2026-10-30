import { z } from 'zod'
import type { TFunction } from 'i18next'

/** Server limits (spec 0334 `data_contract`): `description` 1..5000, `attachments` max 5. */
export const SUPPORT_REQUEST_DESCRIPTION_MAX = 5000
export const SUPPORT_REQUEST_MAX_ATTACHMENTS = 5

/**
 * Zod schema of the support request form, a factory so messages are localized.
 * `support_topic_id` is nullable in the form state (nothing picked yet) and
 * refined to a number on submit.
 */
export function buildSupportRequestSchema(t: TFunction) {
  return z.object({
    support_topic_id: z
      .number()
      .nullable()
      .refine((id): id is number => id !== null, t('supportRequests.topicRequired')),
    description: z
      .string()
      .trim()
      .min(1, t('supportRequests.descriptionRequired'))
      .max(SUPPORT_REQUEST_DESCRIPTION_MAX, t('supportRequests.descriptionMax')),
    is_urgent: z.boolean(),
    attachments: z
      .array(z.instanceof(File))
      .max(SUPPORT_REQUEST_MAX_ATTACHMENTS, t('supportRequests.attachmentsMax', { max: SUPPORT_REQUEST_MAX_ATTACHMENTS })),
  })
}

export type SupportRequestFormValues = z.input<ReturnType<typeof buildSupportRequestSchema>>
export type SupportRequestSubmitValues = z.output<ReturnType<typeof buildSupportRequestSchema>>
