import { useMemo } from 'react'
import { useForm, type Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createCommissionSettlementRun } from '@/features/commission-settlement-runs/api'
import {
  buildCommissionSettlementRunPayload,
  buildDefaultRunValues,
} from '@/features/commission-settlement-runs/commission-settlement-run-payload'
import {
  buildCommissionSettlementRunSchema,
  type CommissionSettlementRunFormValues,
  type CommissionSettlementRunSchemaOutput,
} from '@/features/commission-settlement-runs/commission-settlement-run-schema'
import type { CommissionSettlementRunResult } from '@/features/commission-settlement-runs/types'

const SERVER_ERROR_FIELDS: Path<CommissionSettlementRunFormValues>[] = [
  'period_from',
  'period_to',
  'company_id',
  'referent_ids',
  'recipient_roles',
  'min_amount',
]

/** RHF/Zod wiring and submit of the "Nuovo lancio" form; `onCreated` receives the run outcome. */
export function useCommissionSettlementRunForm(onCreated: (result: CommissionSettlementRunResult) => void) {
  const { t, i18n } = useTranslation()
  const schema = useMemo(() => buildCommissionSettlementRunSchema(t), [t])
  const mutation = useMutation({ mutationFn: createCommissionSettlementRun })

  const form = useForm<CommissionSettlementRunFormValues, unknown, CommissionSettlementRunSchemaOutput>({
    resolver: zodResolver(schema),
    defaultValues: buildDefaultRunValues(),
  })

  const submit = form.handleSubmit((values) =>
    mutation.mutate(buildCommissionSettlementRunPayload(values), {
      onSuccess: onCreated,
      onError: (error) => {
        if (!applyServerValidationErrors(error, form.setError, SERVER_ERROR_FIELDS)) {
          toast.error(apiErrorMessage(error, t('commissionSettlementRuns.form.genericError'), i18n))
        }
      },
    }),
  )

  return { form, submit, isPending: mutation.isPending }
}
