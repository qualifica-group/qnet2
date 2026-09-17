import { z } from 'zod'
import type { TFunction } from 'i18next'

/** Phone-shaped value, mirroring the backend ContactTypeEnum::valueRules regex. */
const PHONE_PATTERN = /^\+?[0-9 ().-]{6,20}$/

/** Contact types whose value must validate as an email (email + PEC). */
const EMAIL_TYPES = new Set(['email', 'pec'])
/** Contact types whose value must validate as a phone number. */
const PHONE_TYPES = new Set(['phone', 'fax'])

/**
 * Zod schema for the contact form, built as a factory for localized messages.
 * The `value` is validated per `type`, mirroring the backend single-source-of-
 * truth rules (email/PEC → email, website → url, phone/fax → pattern).
 * The list of valid types itself comes from the server (config enum), so this
 * only checks that one was chosen and that the value matches its shape.
 */
export function buildContactSchema(t: TFunction) {
  return z
    .object({
      type: z.string().min(1, t('personalData.contacts.typeRequired')),
      value: z.string().min(1, t('personalData.contacts.valueRequired')),
      label: z.string().optional(),
      is_primary: z.boolean(),
    })
    .superRefine((values, ctx) => {
      if (!values.value) {
        return
      }

      if (EMAIL_TYPES.has(values.type) && !isEmail(values.value)) {
        ctx.addIssue({
          code: 'custom',
          path: ['value'],
          message: t('personalData.contacts.valueEmail'),
        })
        return
      }

      if (values.type === 'website' && !isUrl(values.value)) {
        ctx.addIssue({
          code: 'custom',
          path: ['value'],
          message: t('personalData.contacts.valueUrl'),
        })
        return
      }

      if (PHONE_TYPES.has(values.type) && !PHONE_PATTERN.test(values.value)) {
        ctx.addIssue({
          code: 'custom',
          path: ['value'],
          message: t('personalData.contacts.valuePhone'),
        })
      }
    })
}

function isEmail(value: string): boolean {
  return z.string().email().safeParse(value).success
}

function isUrl(value: string): boolean {
  return z.string().url().safeParse(value).success
}

export type ContactFormValues = z.infer<ReturnType<typeof buildContactSchema>>
