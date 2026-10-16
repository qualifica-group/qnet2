import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createJobRole, jobRoleKeys, updateJobRole } from '@/features/job-roles/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/job-roles/job-role-form-payload'
import { buildJobRoleSchema, type JobRoleFormValues } from '@/features/job-roles/job-role-schema'
import type { JobRoleDetail, JobRoleFormMode } from '@/features/job-roles/types'

export type { JobRoleFormValues }

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'description', 'job_activity_ids'] as const

const CREATE_DEFAULTS: JobRoleFormValues = { name: '', description: null, job_activity_ids: [] }

interface UseJobRoleFormArgs {
  mode: JobRoleFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (jobRole: JobRoleDetail) => void
}

/**
 * Owns every non-render concern of the job role form: RHF/Zod wiring,
 * defaults, 422 mapping, submit. Create backs the create form; edit backs the
 * detail's in-place rows (diff PATCH).
 */
export function useJobRoleForm({ mode, onSuccess }: UseJobRoleFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)
  const isEdit = mode.type === 'edit'

  const schema = useMemo(() => buildJobRoleSchema(t), [t])

  const defaultValues = useMemo<JobRoleFormValues>(() => {
    if (mode.type === 'edit') {
      const { name, description, job_activity_ids } = mode.jobRole
      return { name, description, job_activity_ids }
    }
    return CREATE_DEFAULTS
  }, [mode])

  // Edit re-syncs on a saved record; `keepDirtyValues` spares a row still open over a background refetch.
  const form = useForm<JobRoleFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: { keepDirtyValues: true },
  })

  const onSubmit = async (values: JobRoleFormValues) => {
    setServerError(null)
    const errorFields: Path<JobRoleFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateJobRole(mode.jobRole.id, buildUpdatePayload(values, mode.jobRole))
        queryClient.setQueryData(jobRoleKeys.detail(mode.jobRole.id), saved)
        toast.success(t('jobRoles.form.updated'))
        onSuccess(saved)
        return
      }
      const created = await createJobRole(buildCreatePayload(values))
      toast.success(t('jobRoles.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('jobRoles.form.genericError'))
      }
    }
  }

  return { form, serverError, clearServerError: () => setServerError(null), onSubmit }
}
