import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { fieldError } from '@/features/absences/requests/absence-server-errors'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createStaffCourse, staffCourseKeys, updateStaffCourse } from '@/features/staff-courses/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
  staffCourseFormValues,
} from '@/features/staff-courses/staff-course-form-payload'
import { buildStaffCourseSchema, type StaffCourseFormValues } from '@/features/staff-courses/staff-course-schema'
import type { StaffCourse, StaffCourseFormMode } from '@/features/staff-courses/types'

export type { StaffCourseFormValues }

/** Server-side field names mapped onto the form for 422 handling (`duration_minutes` is mapped apart onto the hours field). */
const SERVER_ERROR_FIELDS: Path<StaffCourseFormValues>[] = [
  'name',
  'description',
  'duration_hours',
  'validity_months',
  'is_active',
  'job_role_ids',
]

const CREATE_DEFAULTS: StaffCourseFormValues = {
  name: '',
  description: null,
  duration_hours: '',
  validity_months: null,
  is_active: true,
  job_role_ids: [],
}

interface UseStaffCourseFormArgs {
  mode: StaffCourseFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (course: StaffCourse) => void
}

/**
 * Owns every non-render concern of the course form: RHF/Zod wiring, defaults, 422 mapping, submit.
 * Create backs the create form; edit backs the detail's in-place rows (diff PATCH).
 */
export function useStaffCourseForm({ mode, onSuccess }: UseStaffCourseFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)
  const isEdit = mode.type === 'edit'

  const schema = useMemo(() => buildStaffCourseSchema(t), [t])
  const defaultValues = useMemo<StaffCourseFormValues>(
    () => (mode.type === 'edit' ? staffCourseFormValues(mode.course) : CREATE_DEFAULTS),
    [mode],
  )

  // Edit re-syncs on a saved record; `keepDirtyValues` spares a row still open over a background refetch.
  const form = useForm<StaffCourseFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: { keepDirtyValues: true },
  })

  const onSubmit = async (values: StaffCourseFormValues) => {
    setServerError(null)
    try {
      if (mode.type === 'edit') {
        const saved = await updateStaffCourse(mode.course.id, buildUpdatePayload(values, mode.course))
        queryClient.setQueryData(staffCourseKeys.detail(mode.course.id), saved)
        toast.success(t('staffCourses.form.updated'))
        onSuccess(saved)
        return
      }
      const created = await createStaffCourse(buildCreatePayload(values))
      toast.success(t('staffCourses.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, SERVER_ERROR_FIELDS)) {
        setServerError(t('staffCourses.form.genericError'))
        return
      }
      const durationError = fieldError(error, 'duration_minutes')
      if (durationError !== null) {
        form.setError('duration_hours', { message: durationError })
      }
    }
  }

  return { form, serverError, clearServerError: () => setServerError(null), onSubmit }
}
