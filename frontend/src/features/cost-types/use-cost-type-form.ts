import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { costTypeKeys, createCostType, updateCostType } from '@/features/cost-types/api'
import { buildCostTypePayload } from '@/features/cost-types/cost-type-form-payload'
import { buildCostTypeSchema, type CostTypeFormValues } from '@/features/cost-types/cost-type-schema'
import type { CostTypeDetail, CostTypeFormMode } from '@/features/cost-types/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'description', 'sort_order', 'is_active'] as const

const CREATE_DEFAULTS: CostTypeFormValues = { name: '', description: null, sort_order: 0, is_active: true }

interface UseCostTypeFormArgs {
  mode: CostTypeFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (costType: CostTypeDetail) => void
}

/** Owns every non-render concern of `CostTypeForm`: RHF/Zod wiring, defaults, 422 mapping, submit. */
export function useCostTypeForm({ mode, onSuccess }: UseCostTypeFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const schema = useMemo(() => buildCostTypeSchema(t), [t])

  const defaultValues = useMemo<CostTypeFormValues>(() => {
    if (mode.type === 'edit') {
      const { name, description, sort_order, is_active } = mode.costType
      return { name, description, sort_order, is_active }
    }
    return CREATE_DEFAULTS
  }, [mode])

  const form = useForm<CostTypeFormValues>({ resolver: zodResolver(schema), defaultValues })

  const onSubmit = async (values: CostTypeFormValues) => {
    setServerError(null)
    const errorFields: Path<CostTypeFormValues>[] = [...SERVER_ERROR_FIELDS]
    const payload = buildCostTypePayload(values)
    try {
      if (mode.type === 'edit') {
        const saved = await updateCostType(mode.costType.id, payload)
        queryClient.setQueryData(costTypeKeys.detail(mode.costType.id), saved)
        toast.success(t('costTypes.form.updated'))
        onSuccess(saved)
        return
      }
      const created = await createCostType(payload)
      toast.success(t('costTypes.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('costTypes.form.genericError'))
      }
    }
  }

  return { form, serverError, onSubmit }
}
