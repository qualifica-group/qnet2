import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { absenceTypeKeys, createAbsenceType, updateAbsenceType } from '@/features/absence-types/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/absence-types/absence-type-form-payload'
import { buildAbsenceTypeSchema, type AbsenceTypeFormValues } from '@/features/absence-types/absence-type-schema'
import type { AbsenceType, AbsenceTypeFormMode } from '@/features/absence-types/types'

export type { AbsenceTypeFormValues }

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS: Path<AbsenceTypeFormValues>[] = [
  'name',
  'deducts_balance',
  'requires_approval',
  'is_active',
  'sort_order',
  'allows_full_day',
  'allows_half_day',
  'allows_hourly',
  'min_notice_days',
  'color',
  'default_annual_days',
]

const CREATE_DEFAULTS: AbsenceTypeFormValues = {
  name: '',
  deducts_balance: false,
  requires_approval: true,
  is_active: true,
  sort_order: 0,
  allows_full_day: true,
  allows_half_day: true,
  allows_hourly: true,
  min_notice_days: null,
  color: 'slate',
  default_annual_days: null,
}

interface UseAbsenceTypeFormArgs {
  mode: AbsenceTypeFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (absenceType: AbsenceType) => void
}

/**
 * Owns every non-render concern of the absence type form: RHF/Zod wiring, defaults,
 * 422 mapping, submit. Create backs the create form; edit backs the detail's
 * in-place rows (diff PATCH).
 */
export function useAbsenceTypeForm({ mode, onSuccess }: UseAbsenceTypeFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)
  const isEdit = mode.type === 'edit'

  const schema = useMemo(() => buildAbsenceTypeSchema(t), [t])

  const defaultValues = useMemo<AbsenceTypeFormValues>(() => {
    if (mode.type === 'edit') {
      const a = mode.absenceType
      return {
        name: a.name,
        deducts_balance: a.deducts_balance,
        requires_approval: a.requires_approval,
        is_active: a.is_active,
        sort_order: a.sort_order,
        allows_full_day: a.allows_full_day,
        allows_half_day: a.allows_half_day,
        allows_hourly: a.allows_hourly,
        min_notice_days: a.min_notice_days,
        color: a.color,
        default_annual_days: a.default_annual_days,
      }
    }
    return CREATE_DEFAULTS
  }, [mode])

  // Edit re-syncs on a saved record; `keepDirtyValues` spares a row still open over a background refetch.
  const form = useForm<AbsenceTypeFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: { keepDirtyValues: true },
  })

  const onSubmit = async (values: AbsenceTypeFormValues) => {
    setServerError(null)
    try {
      if (mode.type === 'edit') {
        const saved = await updateAbsenceType(mode.absenceType.id, buildUpdatePayload(values, mode.absenceType))
        queryClient.setQueryData(absenceTypeKeys.detail(mode.absenceType.id), saved)
        toast.success(t('absenceTypes.form.updated'))
        onSuccess(saved)
        return
      }
      const created = await createAbsenceType(buildCreatePayload(values))
      toast.success(t('absenceTypes.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, SERVER_ERROR_FIELDS)) {
        setServerError(t('absenceTypes.form.genericError'))
      }
    }
  }

  return { form, serverError, clearServerError: () => setServerError(null), onSubmit }
}
