import { z } from 'zod'
import type { TFunction } from 'i18next'
import { todayIsoDate } from '@/features/work-order-costs/work-order-costs-schema'
import type { CreateDocumentRequestPayload, UpdateDocumentRequestPayload } from '@/features/document-requests/types'

/** Spec 0428: `message` max 2000, `recipient_email` mandatory email max 255 (AC-021), `due_date` today or later. */
export const REQUEST_MESSAGE_MAX_LENGTH = 2000
export const REQUEST_EMAIL_MAX_LENGTH = 255

function buildBaseSchema(t: TFunction) {
  return z.object({
    document_checklist_id: z.number().nullable(),
    due_date: z
      .string()
      .refine((value) => value === '' || value >= todayIsoDate(), t('documentRequests.validation.dueDatePast')),
    recipient_email: z
      .string()
      .trim()
      .min(1, t('documentRequests.validation.emailRequired'))
      .max(REQUEST_EMAIL_MAX_LENGTH, t('documentRequests.validation.emailMax'))
      .refine((value) => value === '' || z.email().safeParse(value).success, t('documentRequests.validation.emailInvalid')),
    message: z.string().max(REQUEST_MESSAGE_MAX_LENGTH, t('documentRequests.validation.messageMax')),
    send: z.boolean(),
  })
}

/** Create form: the checklist is mandatory. */
export function buildCreateDocumentRequestSchema(t: TFunction) {
  return buildBaseSchema(t).refine((values) => values.document_checklist_id !== null, {
    path: ['document_checklist_id'],
    message: t('documentRequests.validation.checklistRequired'),
  })
}

/** Edit form: same fields, the checklist is never sent (a request keeps it for life). */
export function buildEditDocumentRequestSchema(t: TFunction) {
  return buildBaseSchema(t)
}

export type DocumentRequestFormValues = z.infer<ReturnType<typeof buildBaseSchema>>

export function toUpdateDocumentRequestPayload(values: DocumentRequestFormValues): UpdateDocumentRequestPayload {
  return {
    due_date: values.due_date === '' ? null : values.due_date,
    recipient_email: values.recipient_email.trim(),
    message: values.message.trim() === '' ? null : values.message,
    send: values.send,
  }
}

/** The schema guarantees a checklist on the create form. */
export function toCreateDocumentRequestPayload(values: DocumentRequestFormValues): CreateDocumentRequestPayload {
  return { ...toUpdateDocumentRequestPayload(values), document_checklist_id: values.document_checklist_id as number }
}
