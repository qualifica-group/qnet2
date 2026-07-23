import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createRewardType, updateRewardType } from '@/features/reward-types/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/reward-types/reward-type-form-payload'
import {
  buildCreateRewardTypeSchema,
  buildUpdateRewardTypeSchema,
  type CreateRewardTypeFormValues,
  type UpdateRewardTypeFormValues,
} from '@/features/reward-types/reward-type-schema'
import type { RewardTypeDetail, RewardTypeFormMode } from '@/features/reward-types/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'color'] as const

export type RewardTypeFormValues = CreateRewardTypeFormValues & UpdateRewardTypeFormValues

interface UseRewardTypeFormArgs {
  mode: RewardTypeFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (rewardType: RewardTypeDetail) => void
}

/**
 * Owns every non-render concern of `RewardTypeForm`: RHF/Zod wiring, default
 * values, server 422 mapping and the create/update submit. The component
 * stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useRewardTypeForm({ mode, onSuccess }: UseRewardTypeFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateRewardTypeSchema(t) : buildCreateRewardTypeSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<RewardTypeFormValues>(() => {
    if (mode.type === 'edit') {
      return { name: mode.rewardType.name, color: mode.rewardType.color }
    }
    return { name: '', color: '' }
  }, [mode])

  const form = useForm<RewardTypeFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: RewardTypeFormValues) => {
    setServerError(null)
    const errorFields: Path<RewardTypeFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateRewardType(
          mode.rewardType.id,
          buildUpdatePayload(values, mode.rewardType),
        )
        queryClient.setQueryData(['reward-types', 'detail', mode.rewardType.id], saved)
        toast.success(t('rewardTypes.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createRewardType(buildCreatePayload(values))
      toast.success(t('rewardTypes.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('rewardTypes.form.genericError'))
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
