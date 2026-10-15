import { useMemo, useState } from 'react'
import { useForm, useWatch, type Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  approveSettlement,
  commissionSettlementKeys,
  getApprovalDraft,
  postSettlementSupplier,
} from '@/features/commission-settlements/api'
import {
  buildApprovalDefaults,
  buildApprovalSchema,
  buildApprovePayload,
  CREATE_SUPPLIER_CHOICE,
  type ApprovalFormValues,
  type ApprovalSchemaOutput,
} from '@/features/commission-settlements/settlement-approval-schema'
import { settlementErrorMessage, validationMessageFor } from '@/features/commission-settlements/settlement-errors'
import type { ApprovalDraft, SettlementDetail } from '@/features/commission-settlements/types'
import { useApplySettlementDetail } from '@/features/commission-settlements/use-apply-settlement-detail'
import type { PurchaseInstallmentPreviewPayload } from '@/features/purchase-invoices/types'
import { usePurchaseInstallmentPreview } from '@/features/purchase-invoices/use-purchase-invoice-editor-queries'

const SERVER_ERROR_FIELDS: Path<ApprovalFormValues>[] = ['payment_method_id', 'cost_type_id', 'vat_rate_id', 'financial_account_id']
/** Server keys of the supplier step; the approve step reports a missing supplier under `supplier`. */
const SUPPLIER_ERROR_KEYS = ['registry_id', 'supplier']
/** Errors the dialog has no input for (duplicated invoice number) are shown as a message above the buttons. */
const UNMAPPED_ERROR_KEY = 'document_number'

/** Supplier state and defaults of the approval dialog; idle while the dialog is closed. */
export function useApprovalDraft(settlementId: number, enabled: boolean) {
  return useQuery<ApprovalDraft, AxiosError>({
    queryKey: commissionSettlementKeys.approvalDraft(settlementId),
    queryFn: () => getApprovalDraft(settlementId),
    enabled,
  })
}

/** Installment plan the approval would create: net of withholding and ENASARCO, as the server computes it. */
function useApprovalPreview(settlement: SettlementDetail, paymentMethodId: number | null) {
  const document = settlement.document
  const payload = useMemo((): (PurchaseInstallmentPreviewPayload & { withholding_amount: number; enasarco_amount: number }) | null => {
    if (
      paymentMethodId === null ||
      document?.taxable_amount === undefined ||
      document.vat_amount === undefined ||
      document.total_amount === undefined ||
      document.withholding_amount === undefined ||
      document.enasarco_amount === undefined
    ) {
      return null
    }
    return {
      document_date: document.date,
      payment_method_id: paymentMethodId,
      net_amount: Number(document.taxable_amount),
      vat_amount: Number(document.vat_amount),
      total_amount: Number(document.total_amount),
      withholding_amount: Number(document.withholding_amount),
      enasarco_amount: Number(document.enasarco_amount),
    }
  }, [document, paymentMethodId])
  return usePurchaseInstallmentPreview(payload)
}

/**
 * RHF/Zod wiring of "Approva". Submit = Step 1 link/create the supplier (only
 * while the agent has none), Step 2 approve. A 422 lands on the field the
 * server names; a 409 (state changed meanwhile) is a toast and the dialog closes.
 */
export function useSettlementApproval(
  settlement: SettlementDetail,
  draft: ApprovalDraft,
  onChanged: () => void,
  onDone: () => void,
) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const apply = useApplySettlementDetail(settlement.id, onChanged)
  const [serverError, setServerError] = useState<string | null>(null)
  const needsSupplier = draft.supplier === null

  const schema = useMemo(() => buildApprovalSchema(t, needsSupplier), [t, needsSupplier])
  const form = useForm<ApprovalFormValues, unknown, ApprovalSchemaOutput>({
    resolver: zodResolver(schema),
    defaultValues: buildApprovalDefaults(draft),
  })
  const paymentMethodId = useWatch({ control: form.control, name: 'payment_method_id' })
  const preview = useApprovalPreview(settlement, paymentMethodId)

  const linkSupplier = useMutation({
    mutationFn: (choice: number | typeof CREATE_SUPPLIER_CHOICE) =>
      postSettlementSupplier(settlement.id, { registry_id: choice === CREATE_SUPPLIER_CHOICE ? null : choice }),
  })
  const approve = useMutation({
    mutationFn: (values: ApprovalSchemaOutput) => {
      const payload = buildApprovePayload(values)
      return payload === null ? Promise.reject(new Error('incomplete approval form')) : approveSettlement(settlement.id, payload)
    },
  })

  const closeOnConflict = (error: unknown) => {
    toast.error(settlementErrorMessage(error, t) ?? t('commissionSettlements.actions.genericError'))
    void queryClient.invalidateQueries({ queryKey: commissionSettlementKeys.detail(settlement.id) })
    onDone()
  }

  const onSupplierError = (error: unknown) => {
    const message = SUPPLIER_ERROR_KEYS.map((key) => validationMessageFor(error, key)).find((item) => item !== null)
    if (message !== undefined) {
      form.setError('supplier_choice', { message })
      return
    }
    closeOnConflict(error)
  }

  const onApproveError = (error: unknown) => {
    const supplierMessage = validationMessageFor(error, 'supplier')
    if (supplierMessage !== null) {
      form.setError('supplier_choice', { message: supplierMessage })
    }
    const duplicate = validationMessageFor(error, UNMAPPED_ERROR_KEY)
    if (duplicate !== null) {
      setServerError(duplicate)
    }
    if (!applyServerValidationErrors(error, form.setError, SERVER_ERROR_FIELDS)) {
      closeOnConflict(error)
    }
  }

  const submit = form.handleSubmit(async (values) => {
    setServerError(null)
    // Step 1: the supplier registry must exist before the invoice can be addressed to it
    if (needsSupplier && values.supplier_choice !== null) {
      try {
        await linkSupplier.mutateAsync(values.supplier_choice)
      } catch (error) {
        onSupplierError(error)
        return
      }
      await queryClient.invalidateQueries({ queryKey: commissionSettlementKeys.approvalDraft(settlement.id) })
    }
    // Step 2: create the passive invoice and move the statement to APPROVED
    approve.mutate(values, {
      onSuccess: (data) => {
        apply(data)
        toast.success(t('commissionSettlements.actions.approved'))
        onDone()
      },
      onError: onApproveError,
    })
  })

  return { form, submit, preview, serverError, isPending: linkSupplier.isPending || approve.isPending }
}
