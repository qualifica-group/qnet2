import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createRewardStatus, updateRewardStatus } from '@/features/reward-statuses/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/reward-statuses/reward-status-form-payload'
import {
  buildCreateRewardStatusSchema,
  buildUpdateRewardStatusSchema,
  type CreateRewardStatusFormValues,
  type UpdateRewardStatusFormValues,
} from '@/features/reward-statuses/reward-status-schema'
import type {
  RewardStatusDetail,
  RewardStatusFormMode,
} from '@/features/reward-statuses/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'description', 'color', 'group', 'is_active'] as const

export type RewardStatusFormValues = CreateRewardStatusFormValues & UpdateRewardStatusFormValues

interface UseRewardStatusFormArgs {
  mode: RewardStatusFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (rewardStatus: RewardStatusDetail) => void
}

/**
 * Owns every non-render concern of `RewardStatusForm`: RHF/Zod wiring,
 * default values, server 422 mapping and the create/update submit. The
 * component stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useRewardStatusForm({ mode, onSuccess }: UseRewardStatusFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateRewardStatusSchema(t) : buildCreateRewardStatusSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<RewardStatusFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.rewardStatus.name,
        description: mode.rewardStatus.description,
        color: mode.rewardStatus.color,
        group: mode.rewardStatus.group,
        is_active: mode.rewardStatus.is_active,
      }
    }
    // A brand-new buono status starts on the open phase, the same default the
    // backend column carries (spec 0073).
    return { name: '', description: null, color: '', group: 'open', is_active: true }
  }, [mode])

  const form = useForm<RewardStatusFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: RewardStatusFormValues) => {
    setServerError(null)
    const errorFields: Path<RewardStatusFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateRewardStatus(
          mode.rewardStatus.id,
          buildUpdatePayload(values, mode.rewardStatus),
        )
        queryClient.setQueryData(['reward-statuses', 'detail', mode.rewardStatus.id], saved)
        toast.success(t('rewardStatuses.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createRewardStatus(buildCreatePayload(values))
      toast.success(t('rewardStatuses.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('rewardStatuses.form.genericError'))
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
