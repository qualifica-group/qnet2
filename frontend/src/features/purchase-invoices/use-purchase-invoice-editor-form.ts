import { useCallback, useMemo, useState } from 'react'
import { useFieldArray, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { useVatRefs } from '@/features/invoices/use-vat-refs'
import {
  amountsOf,
  applyPurchaseInvoiceServerErrors,
  buildWritePayload,
  newLine,
} from '@/features/purchase-invoices/purchase-invoice-editor-lines'
import type { EditorSource } from '@/features/purchase-invoices/purchase-invoice-editor-source'
import {
  buildPurchaseInvoiceWriteSchema,
  type PurchaseInvoiceLineFormValues,
  type PurchaseInvoiceWriteFormValues,
} from '@/features/purchase-invoices/purchase-invoice-schema'
import type { PurchaseInvoice } from '@/features/purchase-invoices/types'
import {
  withholdingsCents,
  withholdingsWithinTotal,
} from '@/features/purchase-invoices/purchase-invoice-withholdings'
import { useAddOrderToInvoice } from '@/features/purchase-invoices/use-purchase-invoice-editor-orders'
import { usePurchaseInvoiceEditorPreview } from '@/features/purchase-invoices/use-purchase-invoice-editor-preview'
import { useCreatePurchaseInvoice } from '@/features/purchase-invoices/use-purchase-invoice-editor-queries'
import { serverMessage } from '@/features/purchase-requests/purchase-request-server-errors'

interface UsePurchaseInvoiceEditorFormArgs {
  source: EditorSource
  onSaved: (invoice: PurchaseInvoice) => void
}

/**
 * State and actions of the create editor: RHF + Zod form, the document rows
 * (field array), the live totals/installment preview, ODA linking and the
 * save with 422 errors put on their inputs. A saved document is edited in
 * place on its record (spec 0263).
 */
export function usePurchaseInvoiceEditorForm({ source, onSaved }: UsePurchaseInvoiceEditorFormArgs) {
  const { t } = useTranslation()
  const schema = useMemo(() => buildPurchaseInvoiceWriteSchema(t), [t])
  const form = useForm<PurchaseInvoiceWriteFormValues>({
    resolver: zodResolver(schema),
    defaultValues: source.initialValues,
  })
  const { control, setError, handleSubmit, getValues } = form
  const lineArray = useFieldArray({ control, name: 'lines' })
  const { append } = lineArray

  const { vatRefs, rememberVat } = useVatRefs(source.vatRefs)
  const [generalErrors, setGeneralErrors] = useState<string[]>([])

  const lines = useWatch({ control, name: 'lines' })
  const documentDate = useWatch({ control, name: 'document_date' })
  const paymentMethodId = useWatch({ control, name: 'payment_method_id' })
  const companyId = useWatch({ control, name: 'company_id' })
  const supplierId = useWatch({ control, name: 'supplier_id' })
  const documentType = useWatch({ control, name: 'document_type' })
  const orderIds = useWatch({ control, name: 'purchase_order_ids' })
  const hasOrderLines = lines.some((line) => line.purchase_order_line_id != null)
  const amounts = useMemo(() => amountsOf(lines, vatRefs), [lines, vatRefs])
  const totals = amounts.totals
  const withholdingAmount = useWatch({ control, name: 'withholding_amount' })
  const enasarcoAmount = useWatch({ control, name: 'enasarco_amount' })
  const deductionsCents = withholdingsCents(withholdingAmount, enasarcoAmount)
  const preview = usePurchaseInvoiceEditorPreview({
    documentDate,
    paymentMethodId,
    totals,
    withholdingAmount,
    enasarcoAmount,
  })

  /** Appends a catalog or free row; the header cost type and work order are only proposed. */
  const addLine = useCallback(
    (fields: Partial<PurchaseInvoiceLineFormValues> & Pick<PurchaseInvoiceLineFormValues, 'description'>) => {
      append(
        newLine(fields, {
          cost_type_id: getValues('cost_type_id'),
          work_order_id: getValues('work_order_id'),
        }),
      )
    },
    [append, getValues],
  )

  const orders = useAddOrderToInvoice({ form, lineArray, rememberVat })

  const mutation = useCreatePurchaseInvoice()

  const submit = handleSubmit((values) => {
    setGeneralErrors([])
    if (!withholdingsWithinTotal(deductionsCents, totals.total)) {
      setError('withholding_amount', { message: t('purchaseInvoices.editor.errors.withholdingsAboveTotal') })
      return
    }
    mutation.mutate(buildWritePayload(values), {
      onSuccess: (invoice) => {
        toast.success(t('purchaseInvoices.editor.messages.created'))
        onSaved(invoice)
      },
      onError: (error) => {
        const general = applyPurchaseInvoiceServerErrors(error, setError)
        if (general === null) {
          toast.error(serverMessage(error) ?? t('purchaseInvoices.editor.messages.genericError'))
          return
        }
        setGeneralErrors(general)
      },
    })
  })

  return {
    form,
    lineArray,
    rememberVat,
    companyId,
    supplierId,
    documentType,
    showReceiptNotice: documentType === 'invoice' && hasOrderLines,
    orderIds,
    totals,
    deductionsCents,
    lineAmounts: amounts.lines,
    preview,
    generalErrors,
    addLine,
    addOrder: orders.addOrder,
    ordersNotice: orders.notice,
    isAddingOrder: orders.isAdding,
    submit,
    isSaving: mutation.isPending,
  }
}
