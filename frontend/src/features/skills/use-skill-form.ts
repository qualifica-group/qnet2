import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { fieldError } from '@/features/absences/requests/absence-server-errors'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createSkill, skillKeys, updateSkill } from '@/features/skills/api'
import { buildCreatePayload, buildUpdatePayload, skillFormValues } from '@/features/skills/skill-form-payload'
import { buildSkillSchema, type SkillFormValues } from '@/features/skills/skill-schema'
import type { Skill, SkillFormMode } from '@/features/skills/types'

export type { SkillFormValues }

/** Server-side field names mapped onto the form for 422 handling (`job_roles.N.*` is mapped apart onto `job_roles`). */
const SERVER_ERROR_FIELDS: Path<SkillFormValues>[] = ['name', 'category', 'description', 'is_active', 'job_roles']

const CREATE_DEFAULTS: SkillFormValues = {
  name: '',
  category: null,
  description: null,
  is_active: true,
  job_roles: [],
}

/** First 422 message of a nested job role row (`job_roles.0.id`, `job_roles.1.min_level`), or null. */
function jobRoleRowError(error: unknown): string | null {
  for (let index = 0; index < SKILL_ROW_ERROR_SCAN_LIMIT; index += 1) {
    const message = fieldError(error, `job_roles.${index}.id`) ?? fieldError(error, `job_roles.${index}.min_level`)
    if (message !== null) {
      return message
    }
  }
  return null
}

/** Rows scanned for a nested 422: far above any realistic number of job roles on one skill. */
const SKILL_ROW_ERROR_SCAN_LIMIT = 50

interface UseSkillFormArgs {
  mode: SkillFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (skill: Skill) => void
}

/**
 * Owns every non-render concern of the skill form: RHF/Zod wiring, defaults, 422 mapping, submit.
 * Create backs the create form; edit backs the detail's in-place rows (diff PATCH).
 */
export function useSkillForm({ mode, onSuccess }: UseSkillFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)
  const isEdit = mode.type === 'edit'

  const schema = useMemo(() => buildSkillSchema(t), [t])
  const defaultValues = useMemo<SkillFormValues>(
    () => (mode.type === 'edit' ? skillFormValues(mode.skill) : CREATE_DEFAULTS),
    [mode],
  )

  // Edit re-syncs on a saved record; `keepDirtyValues` spares a row still open over a background refetch.
  const form = useForm<SkillFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: { keepDirtyValues: true },
  })

  const onSubmit = async (values: SkillFormValues) => {
    setServerError(null)
    try {
      if (mode.type === 'edit') {
        const saved = await updateSkill(mode.skill.id, buildUpdatePayload(values, mode.skill))
        queryClient.setQueryData(skillKeys.detail(mode.skill.id), saved)
        toast.success(t('skills.form.updated'))
        onSuccess(saved)
        return
      }
      const created = await createSkill(buildCreatePayload(values))
      toast.success(t('skills.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, SERVER_ERROR_FIELDS)) {
        setServerError(t('skills.form.genericError'))
        return
      }
      const rowError = jobRoleRowError(error)
      if (rowError !== null) {
        form.setError('job_roles', { message: rowError })
      }
    }
  }

  return { form, serverError, clearServerError: () => setServerError(null), onSubmit }
}
