import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  createCommissionConfiguration,
  updateCommissionConfiguration,
} from './api'
import {
  buildCommissionConfigurationSchema,
  type CommissionConfigurationFormValues,
} from './commission-configuration-schema'
import type {
  CommissionConfigurationDetail,
  CommissionConfigurationFormMode,
  CommissionConfigurationPayload,
} from './types'

interface Args {
  mode: CommissionConfigurationFormMode
  onSuccess: (configuration: CommissionConfigurationDetail) => void
}

function payload(values: CommissionConfigurationFormValues): CommissionConfigurationPayload {
  return {
    ...values,
    product_category_id:
      values.application_scope === 'PRODUCT_CATEGORY' ? values.product_category_id : null,
    product_id: values.application_scope === 'PRODUCT' ? values.product_id : null,
    valid_until: values.valid_until || null,
    internal_note: values.internal_note || null,
  }
}

export function useCommissionConfigurationForm({ mode, onSuccess }: Args) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)
  const schema = useMemo(() => buildCommissionConfigurationSchema(t), [t])
  const form = useForm<CommissionConfigurationFormValues>({
    resolver: zodResolver(schema),
    defaultValues:
      mode.type === 'edit'
        ? {
            name: mode.configuration.name,
            recipient_role: mode.configuration.recipient_role,
            application_scope: mode.configuration.application_scope,
            product_category_id: mode.configuration.product_category_id,
            product_id: mode.configuration.product_id,
            commission_type: mode.configuration.commission_type,
            value: Number(mode.configuration.value),
            priority: mode.configuration.priority,
            valid_from: mode.configuration.valid_from,
            valid_until: mode.configuration.valid_until,
            status: mode.configuration.status,
            internal_note: mode.configuration.internal_note,
          }
        : {
            name: '',
            recipient_role: 'COMMERCIAL',
            application_scope: 'PRODUCT_CATEGORY',
            product_category_id: null,
            product_id: null,
            commission_type: 'PERCENTAGE',
            value: 0,
            priority: 0,
            valid_from: new Date().toISOString().slice(0, 10),
            valid_until: null,
            status: 'ACTIVE',
            internal_note: null,
          },
  })

  const onSubmit = async (values: CommissionConfigurationFormValues) => {
    setServerError(null)
    try {
      const saved =
        mode.type === 'edit'
          ? await updateCommissionConfiguration(mode.configuration.id, payload(values))
          : await createCommissionConfiguration(payload(values))
      queryClient.setQueryData(['commission-configurations', 'detail', saved.id], saved)
      toast.success(
        t(
          mode.type === 'edit'
            ? 'commissionConfigurations.form.updated'
            : 'commissionConfigurations.form.created',
        ),
      )
      onSuccess(saved)
    } catch (error) {
      if (
        !applyServerValidationErrors(error, form.setError, [
          'name',
          'recipient_role',
          'application_scope',
          'product_category_id',
          'product_id',
          'commission_type',
          'value',
          'priority',
          'valid_from',
          'valid_until',
          'status',
          'internal_note',
        ])
      ) {
        setServerError(t('commissionConfigurations.form.genericError'))
      }
    }
  }

  return { form, serverError, onSubmit }
}
