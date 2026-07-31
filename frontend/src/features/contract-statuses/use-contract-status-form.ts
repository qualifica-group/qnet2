import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  createContractStatus,
  updateContractStatus,
} from '@/features/contract-statuses/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/contract-statuses/contract-status-form-payload'
import {
  buildCreateContractStatusSchema,
  buildUpdateContractStatusSchema,
  type CreateContractStatusFormValues,
  type UpdateContractStatusFormValues,
} from '@/features/contract-statuses/contract-status-schema'
import type {
  ContractStatusDetail,
  ContractStatusFormMode,
} from '@/features/contract-statuses/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'name',
  'description',
  'color',
  'group',
  'is_active',
  'is_default',
] as const

export type ContractStatusFormValues = CreateContractStatusFormValues &
  UpdateContractStatusFormValues

interface UseContractStatusFormArgs {
  mode: ContractStatusFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (contractStatus: ContractStatusDetail) => void
}

/**
 * Owns every non-render concern of `ContractStatusForm`: RHF/Zod wiring,
 * default values, server 422 mapping and the create/update submit. The
 * component stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useContractStatusForm({ mode, onSuccess }: UseContractStatusFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateContractStatusSchema(t) : buildCreateContractStatusSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<ContractStatusFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.contractStatus.name,
        description: mode.contractStatus.description,
        color: mode.contractStatus.color ?? '',
        group: mode.contractStatus.group,
        is_active: mode.contractStatus.is_active,
        is_default: mode.contractStatus.is_default,
      }
    }
    return { name: '', description: null, color: '', group: 'open', is_active: true, is_default: false }
  }, [mode])

  const form = useForm<ContractStatusFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: ContractStatusFormValues) => {
    setServerError(null)
    const errorFields: Path<ContractStatusFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateContractStatus(
          mode.contractStatus.id,
          buildUpdatePayload(values, mode.contractStatus),
        )
        queryClient.setQueryData(['contract-statuses', 'detail', mode.contractStatus.id], saved)
        toast.success(t('contractStatuses.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createContractStatus(buildCreatePayload(values))
      toast.success(t('contractStatuses.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('contractStatuses.form.genericError'))
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
