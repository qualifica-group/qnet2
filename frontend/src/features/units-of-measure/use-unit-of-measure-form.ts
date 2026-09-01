import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createUnitOfMeasure, updateUnitOfMeasure } from '@/features/units-of-measure/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/units-of-measure/unit-of-measure-form-payload'
import {
  buildCreateUnitOfMeasureSchema,
  buildUpdateUnitOfMeasureSchema,
  type CreateUnitOfMeasureFormValues,
  type UpdateUnitOfMeasureFormValues,
} from '@/features/units-of-measure/unit-of-measure-schema'
import type {
  UnitOfMeasureDetail,
  UnitOfMeasureFormMode,
} from '@/features/units-of-measure/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'symbol', 'code', 'description'] as const

export type UnitOfMeasureFormValues = CreateUnitOfMeasureFormValues & UpdateUnitOfMeasureFormValues

interface UseUnitOfMeasureFormArgs {
  mode: UnitOfMeasureFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (unitOfMeasure: UnitOfMeasureDetail) => void
}

/**
 * Owns every non-render concern of `UnitOfMeasureForm`: RHF/Zod wiring,
 * default values, server 422 mapping and the create/update submit. The
 * component stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useUnitOfMeasureForm({ mode, onSuccess }: UseUnitOfMeasureFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateUnitOfMeasureSchema(t) : buildCreateUnitOfMeasureSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<UnitOfMeasureFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.unitOfMeasure.name,
        symbol: mode.unitOfMeasure.symbol,
        code: mode.unitOfMeasure.code,
        description: mode.unitOfMeasure.description,
      }
    }
    return { name: '', symbol: '', code: '', description: null }
  }, [mode])

  const form = useForm<UnitOfMeasureFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: UnitOfMeasureFormValues) => {
    setServerError(null)
    const errorFields: Path<UnitOfMeasureFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateUnitOfMeasure(
          mode.unitOfMeasure.id,
          buildUpdatePayload(values, mode.unitOfMeasure),
        )
        queryClient.setQueryData(['units-of-measure', 'detail', mode.unitOfMeasure.id], saved)
        toast.success(t('unitsOfMeasure.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createUnitOfMeasure(buildCreatePayload(values))
      toast.success(t('unitsOfMeasure.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('unitsOfMeasure.form.genericError'))
      }
    }
  }

  return {
    form,
    isEdit,
    serverError,
    onSubmit,
  }
}
