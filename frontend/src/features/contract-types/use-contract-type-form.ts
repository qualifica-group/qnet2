import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { contractTypeKeys, createContractType, updateContractType } from '@/features/contract-types/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/contract-types/contract-type-form-payload'
import { buildContractTypeSchema, type ContractTypeFormValues } from '@/features/contract-types/contract-type-schema'
import type { ContractType, ContractTypeFormMode } from '@/features/contract-types/types'

export type { ContractTypeFormValues }

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS: Path<ContractTypeFormValues>[] = [
  'name',
  'is_active',
  'sort_order',
]

const CREATE_DEFAULTS: ContractTypeFormValues = {
  name: '',
  is_active: true,
  sort_order: 0,
}

interface UseContractTypeFormArgs {
  mode: ContractTypeFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (contractType: ContractType) => void
}

/**
 * Owns every non-render concern of the contract type form: RHF/Zod wiring, defaults,
 * 422 mapping, submit. Create backs the create form; edit backs the detail's
 * in-place rows (diff PATCH).
 */
export function useContractTypeForm({ mode, onSuccess }: UseContractTypeFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)
  const isEdit = mode.type === 'edit'

  const schema = useMemo(() => buildContractTypeSchema(t), [t])

  const defaultValues = useMemo<ContractTypeFormValues>(() => {
    if (mode.type === 'edit') {
      const { name, is_active, sort_order } = mode.contractType
      return { name, is_active, sort_order }
    }
    return CREATE_DEFAULTS
  }, [mode])

  // Edit re-syncs on a saved record; `keepDirtyValues` spares a row still open over a background refetch.
  const form = useForm<ContractTypeFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: { keepDirtyValues: true },
  })

  const onSubmit = async (values: ContractTypeFormValues) => {
    setServerError(null)
    try {
      if (mode.type === 'edit') {
        const saved = await updateContractType(mode.contractType.id, buildUpdatePayload(values, mode.contractType))
        queryClient.setQueryData(contractTypeKeys.detail(mode.contractType.id), saved)
        toast.success(t('contractTypes.form.updated'))
        onSuccess(saved)
        return
      }
      const created = await createContractType(buildCreatePayload(values))
      toast.success(t('contractTypes.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, SERVER_ERROR_FIELDS)) {
        setServerError(t('contractTypes.form.genericError'))
      }
    }
  }

  return { form, serverError, clearServerError: () => setServerError(null), onSubmit }
}
