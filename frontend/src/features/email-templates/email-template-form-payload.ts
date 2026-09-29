import type {
  CreateEmailTemplatePayload,
  EmailTemplate,
  UpdateEmailTemplatePayload,
} from '@/features/email-templates/types'
import type { EmailTemplateFormValues } from '@/features/email-templates/use-email-template-form'

/** Builds the create payload: every field the form owns, `body` coerced to a plain string (schema already validated it non-empty). */
export function buildCreatePayload(values: EmailTemplateFormValues): CreateEmailTemplatePayload {
  return {
    name: values.name,
    module: values.module,
    subject: values.subject,
    body: values.body ?? '',
    description: values.description,
    is_active: values.is_active,
  }
}

/**
 * Builds a partial PATCH payload carrying only the fields that actually
 * changed from the original email template. `module` is NEVER included
 * (data_contract: immutable after create, the backend `prohibited`-validates
 * its mere presence).
 */
export function buildUpdatePayload(
  values: EmailTemplateFormValues,
  original: EmailTemplate,
): UpdateEmailTemplatePayload {
  const payload: UpdateEmailTemplatePayload = {}
  const body = values.body ?? ''

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.subject !== original.subject) {
    payload.subject = values.subject
  }
  if (body !== original.body) {
    payload.body = body
  }
  if (values.description !== original.description) {
    payload.description = values.description
  }
  if (values.is_active !== original.is_active) {
    payload.is_active = values.is_active
  }

  return payload
}
