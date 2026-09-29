import { z } from 'zod'
import type { TFunction } from 'i18next'
import { EMAIL_TEMPLATE_MODULES } from '@/features/email-templates/types'

/**
 * Zod schema for the email template create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function. Mirrors the
 * frozen backend contract (spec 0175 `data_contract`) 1:1. `module` keeps the
 * same shape in both create and edit schemas: on edit it renders disabled
 * (the backend field-permission ceiling reports it readonly outside create,
 * `EmailTemplatesAuthorization`) and `buildUpdatePayload` never emits it —
 * same pattern as `document-layouts`'s `code`/`module`. `body` stays typed
 * `string | null` (never transformed to a bare `string`) so it matches
 * `RichTextEditor`'s own `onChange` signature 1:1; "required" is enforced by
 * `refine` instead, which validates content without narrowing the type.
 */

/** Backend `name` column limit (`max:191`, unique per module). */
const NAME_MAX_LENGTH = 191
/** Backend `subject` column limit (`max:255`, data_contract). */
const SUBJECT_MAX_LENGTH = 255
/** `description` column limit, same convention as every other lookup module (e.g. `task-importances`). */
const DESCRIPTION_MAX_LENGTH = 500

function isFilledRichText(value: string | null): boolean {
  return typeof value === 'string' && value.trim().length > 0
}

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('emailTemplates.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('emailTemplates.form.nameMax')),
    module: z.enum(EMAIL_TEMPLATE_MODULES),
    subject: z
      .string()
      .min(1, t('emailTemplates.form.subjectRequired'))
      .max(SUBJECT_MAX_LENGTH, t('emailTemplates.form.subjectMax')),
    body: z.string().nullable().refine(isFilledRichText, t('emailTemplates.form.bodyRequired')),
    description: z
      .string()
      .max(DESCRIPTION_MAX_LENGTH, t('emailTemplates.form.descriptionMax'))
      .nullable(),
    is_active: z.boolean(),
  }
}

/** Create schema. */
export function buildCreateEmailTemplateSchema(t: TFunction) {
  return z.object(baseFields(t))
}

/** Edit schema (same shape; `module` renders disabled and is never diffed into the PATCH payload). */
export function buildUpdateEmailTemplateSchema(t: TFunction) {
  return z.object(baseFields(t))
}

export type CreateEmailTemplateFormValues = z.infer<ReturnType<typeof buildCreateEmailTemplateSchema>>
export type UpdateEmailTemplateFormValues = z.infer<ReturnType<typeof buildUpdateEmailTemplateSchema>>
