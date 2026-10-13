import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { costTypeKeys, createCostType, updateCostType } from '@/features/cost-types/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/cost-types/cost-type-form-payload'
import { buildCostTypeSchema, type CostTypeFormValues } from '@/features/cost-types/cost-type-schema'
import type { CostTypeDetail, CostTypeFormMode } from '@/features/cost-types/types'

export type { CostTypeFormValues }

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'description', 'nature', 'color', 'is_active'] as const

/** Defaults of a new cost type: the palette's neutral token and the commonest nature. */
const CREATE_DEFAULTS: CostTypeFormValues = {
  name: '',
  description: null,
  nature: 'direct',
  color: 'slate',
  is_active: true,
}

interface UseCostTypeFormArgs {
  mode: CostTypeFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (costType: CostTypeDetail) => void
}

/**
 * Owns every non-render concern of the cost type form: RHF/Zod wiring,
 * defaults, 422 mapping, submit. Create backs the create form; edit backs the
 * detail's in-place rows (diff PATCH).
 */
export function useCostTypeForm({ mode, onSuccess }: UseCostTypeFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)
  const isEdit = mode.type === 'edit'

  const schema = useMemo(() => buildCostTypeSchema(t), [t])

  const defaultValues = useMemo<CostTypeFormValues>(() => {
    if (mode.type === 'edit') {
      const { name, description, nature, color, is_active } = mode.costType
      return { name, description, nature, color, is_active }
    }
    return CREATE_DEFAULTS
  }, [mode])

  // Edit re-syncs on a saved record; `keepDirtyValues` spares a row still open over a background refetch.
  const form = useForm<CostTypeFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: { keepDirtyValues: true },
  })

  const onSubmit = async (values: CostTypeFormValues) => {
    setServerError(null)
    const errorFields: Path<CostTypeFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateCostType(mode.costType.id, buildUpdatePayload(values, mode.costType))
        queryClient.setQueryData(costTypeKeys.detail(mode.costType.id), saved)
        toast.success(t('costTypes.form.updated'))
        onSuccess(saved)
        return
      }
      const created = await createCostType(buildCreatePayload(values))
      toast.success(t('costTypes.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('costTypes.form.genericError'))
      }
    }
  }

  return { form, serverError, clearServerError: () => setServerError(null), onSubmit }
}
