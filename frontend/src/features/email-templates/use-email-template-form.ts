import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  createEmailTemplate,
  emailTemplateDetailQueryKey,
  updateEmailTemplate,
} from '@/features/email-templates/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/email-templates/email-template-form-payload'
import {
  buildCreateEmailTemplateSchema,
  buildUpdateEmailTemplateSchema,
  type CreateEmailTemplateFormValues,
  type UpdateEmailTemplateFormValues,
} from '@/features/email-templates/email-template-schema'
import type { EmailTemplate, EmailTemplateFormMode } from '@/features/email-templates/types'

/** Server-side field names mapped onto the form for 422 handling. `module` is never diffed on update but can still 422 on create. */
const SERVER_ERROR_FIELDS = ['name', 'module', 'subject', 'body', 'description', 'is_active'] as const

export type EmailTemplateFormValues = CreateEmailTemplateFormValues & UpdateEmailTemplateFormValues

interface UseEmailTemplateFormArgs {
  mode: EmailTemplateFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (emailTemplate: EmailTemplate) => void
}

/**
 * Owns every non-render concern of `EmailTemplateForm`: RHF/Zod wiring,
 * default values, server 422 mapping and the create/update submit. Create
 * backs `EmailTemplateForm`; edit backs the detail's in-place rows (diff
 * PATCH). The component stays UI-only; this hook is the orchestration point
 * (`onSubmit`).
 */
export function useEmailTemplateForm({ mode, onSuccess }: UseEmailTemplateFormArgs) {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateEmailTemplateSchema(t) : buildCreateEmailTemplateSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<EmailTemplateFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.emailTemplate.name,
        module: mode.emailTemplate.module,
        subject: mode.emailTemplate.subject,
        body: mode.emailTemplate.body,
        description: mode.emailTemplate.description,
        is_active: mode.emailTemplate.is_active,
      }
    }
    return {
      name: '',
      module: 'work_orders',
      subject: '',
      body: null,
      description: null,
      is_active: true,
    }
  }, [mode])

  // Edit backs the detail's in-place rows: a saved record re-syncs the form,
  // `keepDirtyValues` spares a row still open over a background refetch.
  const form = useForm<EmailTemplateFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: { keepDirtyValues: true },
  })

  const onSubmit = async (values: EmailTemplateFormValues) => {
    setServerError(null)
    const errorFields: Path<EmailTemplateFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateEmailTemplate(
          mode.emailTemplate.id,
          buildUpdatePayload(values, mode.emailTemplate),
        )
        queryClient.setQueryData(emailTemplateDetailQueryKey(mode.emailTemplate.id), saved)
        toast.success(t('emailTemplates.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createEmailTemplate(buildCreatePayload(values))
      toast.success(t('emailTemplates.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(apiErrorMessage(error, t('emailTemplates.form.genericError'), i18n))
      }
    }
  }

  return {
    form,
    serverError,
    clearServerError: () => setServerError(null),
    onSubmit,
  }
}
