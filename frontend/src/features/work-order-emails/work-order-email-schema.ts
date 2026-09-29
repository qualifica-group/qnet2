import { z } from 'zod'
import type { TFunction } from 'i18next'
import type { OutboundEmail, OutboundEmailPayload } from '@/features/work-order-emails/types'

/** Mirrors `subject varchar(255)` (data_model). */
const SUBJECT_MAX_LENGTH = 255

export interface ComposerFormValues {
  email_template_id: number | null
  to: string[]
  cc: string[]
  bcc: string[]
  subject: string
  body: string | null
}

export const EMPTY_COMPOSER_VALUES: ComposerFormValues = {
  email_template_id: null,
  to: [],
  cc: [],
  bcc: [],
  subject: '',
  body: null,
}

/**
 * Draft resolver (D-2): every field is optional/lenient — a bozza is valid
 * empty. This is the RHF `resolver`, so it MUST accept the empty state or
 * "Salva bozza" could never save a fresh draft untouched.
 */
export function buildComposerSchema() {
  return z.object({
    email_template_id: z.number().nullable(),
    to: z.array(z.string()),
    cc: z.array(z.string()),
    bcc: z.array(z.string()),
    subject: z.string().max(SUBJECT_MAX_LENGTH),
    body: z.string().nullable(),
  })
}

/**
 * Send-time refine (AC-020), run manually on "Invia" — deliberately NOT the
 * RHF resolver: it must reject an empty draft, which the resolver above must
 * NOT (D-2 vs the send-time requirement in the `/send` errors list).
 */
export function buildSendSchema(t: TFunction) {
  return buildComposerSchema().extend({
    to: z.array(z.string()).min(1, t('workOrderEmails.composer.recipientsRequired')),
    subject: z.string().trim().min(1, t('workOrderEmails.composer.subjectRequired')).max(SUBJECT_MAX_LENGTH),
    // Stays `.nullable()` (unlike `to`/`subject`) because the RichTextEditor's
    // own empty state IS `null` (rich-text-editor.tsx `toChangeValue`): a plain
    // `z.string().min(1)` would reject that `null` with zod's generic
    // "invalid type" issue instead of this field's own required message.
    body: z
      .string()
      .nullable()
      .refine((value): value is string => !!value && value.trim().length > 0, {
        message: t('workOrderEmails.composer.bodyRequired'),
      }),
  })
}

export function mapEmailToFormValues(email: OutboundEmail): ComposerFormValues {
  return {
    email_template_id: email.email_template_id,
    to: email.to,
    cc: email.cc,
    bcc: email.bcc,
    subject: email.subject ?? '',
    body: email.body,
  }
}

export function mapFormValuesToPayload(values: ComposerFormValues): OutboundEmailPayload {
  return {
    email_template_id: values.email_template_id,
    to: values.to,
    cc: values.cc,
    bcc: values.bcc,
    subject: values.subject.trim() === '' ? null : values.subject,
    body: values.body,
  }
}

/** Whether a draft carries nothing worth keeping (D-2: gates the silent-delete-on-close). */
export function isEmptyDraft(email: OutboundEmail | undefined): boolean {
  if (!email) {
    return false
  }
  return (
    email.to.length === 0 &&
    email.cc.length === 0 &&
    email.bcc.length === 0 &&
    !email.subject?.trim() &&
    !email.body?.trim() &&
    email.attachments.length === 0
  )
}
