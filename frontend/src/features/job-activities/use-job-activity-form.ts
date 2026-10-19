import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createJobActivity, jobActivityKeys, updateJobActivity } from '@/features/job-activities/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/job-activities/job-activity-form-payload'
import { buildJobActivitySchema, type JobActivityFormValues } from '@/features/job-activities/job-activity-schema'
import type { JobActivityDetail, JobActivityFormMode } from '@/features/job-activities/types'

export type { JobActivityFormValues }

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'description'] as const

const CREATE_DEFAULTS: JobActivityFormValues = { name: '', description: null }

interface UseJobActivityFormArgs {
  mode: JobActivityFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (jobActivity: JobActivityDetail) => void
}

/**
 * Owns every non-render concern of the activity form: RHF/Zod wiring,
 * defaults, 422 mapping, submit. Create backs the create form; edit backs the
 * detail's in-place rows (diff PATCH).
 */
export function useJobActivityForm({ mode, onSuccess }: UseJobActivityFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)
  const isEdit = mode.type === 'edit'

  const schema = useMemo(() => buildJobActivitySchema(t), [t])

  const defaultValues = useMemo<JobActivityFormValues>(() => {
    if (mode.type === 'edit') {
      const { name, description } = mode.jobActivity
      return { name, description }
    }
    return CREATE_DEFAULTS
  }, [mode])

  // Edit re-syncs on a saved record; `keepDirtyValues` spares a row still open over a background refetch.
  const form = useForm<JobActivityFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: { keepDirtyValues: true },
  })

  const onSubmit = async (values: JobActivityFormValues) => {
    setServerError(null)
    const errorFields: Path<JobActivityFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateJobActivity(mode.jobActivity.id, buildUpdatePayload(values, mode.jobActivity))
        queryClient.setQueryData(jobActivityKeys.detail(mode.jobActivity.id), saved)
        toast.success(t('jobActivities.form.updated'))
        onSuccess(saved)
        return
      }
      const created = await createJobActivity(buildCreatePayload(values))
      toast.success(t('jobActivities.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('jobActivities.form.genericError'))
      }
    }
  }

  return { form, serverError, clearServerError: () => setServerError(null), onSubmit }
}
