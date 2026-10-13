import { useMemo } from 'react'
import { useForm, type Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { commissionSettlementKeys, createAdjustment, updateAdjustment } from '@/features/commission-settlements/api'
import {
  buildAdjustmentCreatePayload,
  buildAdjustmentDefaults,
  buildAdjustmentUpdatePayload,
} from '@/features/commission-settlements/adjustment-payload'
import {
  buildAdjustmentSchema,
  type AdjustmentFormValues,
  type AdjustmentSchemaOutput,
} from '@/features/commission-settlements/adjustment-schema'
import { settlementErrorMessage } from '@/features/commission-settlements/settlement-errors'
import type { AdjustmentMode } from '@/features/commission-settlements/types'

const SERVER_ERROR_FIELDS: Path<AdjustmentFormValues>[] = ['referent_id', 'company_id', 'amount', 'accrued_on', 'notes']

/**
 * RHF/Zod wiring and submit of the adjustment form for its three modes
 * (standalone, inside a draft, edit of an existing adjustment). A 409 (the
 * statement was issued meanwhile) is surfaced as a toast with the server text.
 */
export function useCommissionAdjustmentForm(mode: AdjustmentMode, onSaved: () => void) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const schema = useMemo(() => buildAdjustmentSchema(t, mode.type === 'standalone'), [t, mode.type])

  const mutation = useMutation({
    mutationFn: (values: AdjustmentSchemaOutput) => {
      if (mode.type === 'edit') {
        return updateAdjustment(mode.adjustmentId, buildAdjustmentUpdatePayload(values))
      }
      return createAdjustment(buildAdjustmentCreatePayload(values, mode.type === 'draft' ? mode.settlementId : null))
    },
  })

  const form = useForm<AdjustmentFormValues, unknown, AdjustmentSchemaOutput>({
    resolver: zodResolver(schema),
    defaultValues: buildAdjustmentDefaults(mode),
  })

  const submit = form.handleSubmit((values) =>
    mutation.mutate(values, {
      onSuccess: () => {
        void queryClient.invalidateQueries({ queryKey: commissionSettlementKeys.all })
        toast.success(t(mode.type === 'edit' ? 'commissionSettlements.adjustment.updated' : 'commissionSettlements.adjustment.created'))
        onSaved()
      },
      onError: (error) => {
        if (applyServerValidationErrors(error, form.setError, SERVER_ERROR_FIELDS)) {
          return
        }
        toast.error(settlementErrorMessage(error, t) ?? t('commissionSettlements.adjustment.genericError'))
      },
    }),
  )

  return { form, submit, isPending: mutation.isPending }
}
