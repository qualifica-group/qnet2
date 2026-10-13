import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  commissionConfigurationDetailKey,
  createCommissionConfiguration,
  updateCommissionConfiguration,
} from './api'
import {
  buildCreatePayload,
  buildUpdatePayload,
  createDefaults,
  toFormValues,
} from './commission-configuration-form-payload'
import {
  buildCommissionConfigurationSchema,
  type CommissionConfigurationFormValues,
} from './commission-configuration-schema'
import type { CommissionConfigurationDetail, CommissionConfigurationFormMode } from './types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'name',
  'recipient_role',
  'application_scope',
  'product_category_id',
  'product_id',
  'recipient_type',
  'recipient_id',
  'commission_type',
  'value',
  'priority',
  'valid_from',
  'valid_until',
  'status',
  'internal_note',
] as const

interface Args {
  mode: CommissionConfigurationFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (configuration: CommissionConfigurationDetail) => void
}

/**
 * Owns every non-render concern of the commission configuration form:
 * RHF/Zod wiring, defaults, 422 mapping and the submit. Create backs the
 * create form; edit backs the detail's in-place rows (diff PATCH).
 */
export function useCommissionConfigurationForm({ mode, onSuccess }: Args) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)
  const schema = useMemo(() => buildCommissionConfigurationSchema(t), [t])
  const isEdit = mode.type === 'edit'
  const defaultValues = useMemo(
    () => (mode.type === 'edit' ? toFormValues(mode.configuration) : createDefaults(mode.supplierRegistryId)),
    [mode],
  )

  // Edit backs the detail's in-place rows: a saved record re-syncs the form,
  // `keepDirtyValues` spares a row still open over a background refetch.
  const form = useForm<CommissionConfigurationFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: { keepDirtyValues: true },
  })

  const onSubmit = async (values: CommissionConfigurationFormValues) => {
    setServerError(null)
    try {
      if (mode.type === 'edit') {
        const saved = await updateCommissionConfiguration(
          mode.configuration.id,
          buildUpdatePayload(values, toFormValues(mode.configuration)),
        )
        queryClient.setQueryData(commissionConfigurationDetailKey(mode.configuration.id), saved)
        toast.success(t('commissionConfigurations.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createCommissionConfiguration(buildCreatePayload(values))
      toast.success(t('commissionConfigurations.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])) {
        setServerError(t('commissionConfigurations.form.genericError'))
      }
    }
  }

  return { form, serverError, clearServerError: () => setServerError(null), onSubmit }
}
