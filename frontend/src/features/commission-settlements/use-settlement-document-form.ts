import { useMemo } from 'react'
import { useForm, type Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { postSettlementDocument } from '@/features/commission-settlements/api'
import {
  buildDocumentDefaults,
  buildDocumentPayload,
  buildDocumentSchema,
  DOCUMENT_AMOUNT_FIELDS,
  type DocumentFormValues,
  type DocumentSchemaOutput,
} from '@/features/commission-settlements/settlement-document-schema'
import { settlementErrorMessage } from '@/features/commission-settlements/settlement-errors'
import type { SettlementDetail } from '@/features/commission-settlements/types'
import { useApplySettlementDetail } from '@/features/commission-settlements/use-apply-settlement-detail'

const SERVER_ERROR_FIELDS: Path<DocumentFormValues>[] = ['document_number', 'document_date', ...DOCUMENT_AMOUNT_FIELDS]

/**
 * RHF/Zod wiring of "Registra documento". A 422 lands on the field the server
 * names (including a difference over the tolerance); a 409 (the statement is no
 * longer ISSUED) is a toast and the dialog closes, since there is nothing to fix.
 */
export function useSettlementDocumentForm(settlement: SettlementDetail, onChanged: () => void, onDone: () => void) {
  const { t } = useTranslation()
  const apply = useApplySettlementDetail(settlement.id, onChanged)
  const schema = useMemo(() => buildDocumentSchema(t), [t])
  const form = useForm<DocumentFormValues, unknown, DocumentSchemaOutput>({
    resolver: zodResolver(schema),
    defaultValues: buildDocumentDefaults(settlement),
  })

  const mutation = useMutation({
    mutationFn: (values: DocumentSchemaOutput) => postSettlementDocument(settlement.id, buildDocumentPayload(values)),
  })

  const submit = form.handleSubmit((values) =>
    mutation.mutate(values, {
      onSuccess: (data) => {
        apply(data)
        toast.success(t('commissionSettlements.actions.documentReceived'))
        onDone()
      },
      onError: (error) => {
        if (applyServerValidationErrors(error, form.setError, SERVER_ERROR_FIELDS)) {
          return
        }
        toast.error(settlementErrorMessage(error, t) ?? t('commissionSettlements.actions.genericError'))
        onDone()
      },
    }),
  )

  return { form, submit, isPending: mutation.isPending }
}
