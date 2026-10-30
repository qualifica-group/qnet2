import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { submitSupportRequest } from '@/features/support-requests/api'
import {
  buildSupportRequestFormData,
  readSupportRequestContext,
} from '@/features/support-requests/support-request-payload'
import {
  buildSupportRequestSchema,
  SUPPORT_REQUEST_MAX_ATTACHMENTS,
  type SupportRequestFormValues,
  type SupportRequestSubmitValues,
} from '@/features/support-requests/support-request-schema'
import type { SupportRequestResult } from '@/features/support-requests/types'

const DEFAULT_VALUES: SupportRequestFormValues = {
  support_topic_id: null,
  description: '',
  is_urgent: false,
  attachments: [],
}

/** Server-side field names mapped onto the form on a 422. */
const SERVER_ERROR_FIELDS: (keyof SupportRequestFormValues)[] = [
  'support_topic_id',
  'description',
  'is_urgent',
  'attachments',
]

/**
 * Owns every non-render concern of the support request dialog: RHF/Zod wiring,
 * the attachments list (max 5), the multipart submit and the 422 mapping (the
 * shared toast fires inside `applyServerValidationErrors`; the dialog stays
 * open with the data entered).
 */
export function useSupportRequestForm() {
  const { t } = useTranslation()
  const [result, setResult] = useState<SupportRequestResult | null>(null)
  const [serverError, setServerError] = useState<string | null>(null)

  const schema = useMemo(() => buildSupportRequestSchema(t), [t])
  const form = useForm<SupportRequestFormValues, unknown, SupportRequestSubmitValues>({
    resolver: zodResolver(schema),
    defaultValues: DEFAULT_VALUES,
  })

  const mutation = useMutation<SupportRequestResult, unknown, SupportRequestSubmitValues>({
    mutationFn: (values) => submitSupportRequest(buildSupportRequestFormData(values, readSupportRequestContext())),
  })

  const onSubmit = async (values: SupportRequestSubmitValues) => {
    setServerError(null)
    try {
      setResult(await mutation.mutateAsync(values))
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, SERVER_ERROR_FIELDS)) {
        setServerError(t('supportRequests.genericError'))
      }
    }
  }

  const addFiles = (picked: File[]) => {
    const merged = [...form.getValues('attachments'), ...picked]
    const kept = merged.slice(0, SUPPORT_REQUEST_MAX_ATTACHMENTS)
    form.setValue('attachments', kept, { shouldDirty: true })
    if (merged.length > kept.length) {
      form.setError('attachments', { message: t('supportRequests.attachmentsMax', { max: SUPPORT_REQUEST_MAX_ATTACHMENTS }) })
    } else {
      form.clearErrors('attachments')
    }
  }

  const removeFile = (index: number) => {
    form.setValue(
      'attachments',
      form.getValues('attachments').filter((_, position) => position !== index),
      { shouldDirty: true },
    )
    form.clearErrors('attachments')
  }

  return {
    form,
    result,
    serverError,
    isSubmitting: mutation.isPending,
    submit: form.handleSubmit(onSubmit),
    addFiles,
    removeFile,
  }
}
