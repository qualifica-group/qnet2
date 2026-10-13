import { useCallback, useMemo, useState } from 'react'
import { useFieldArray, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import axios from 'axios'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import type { VatRateRef } from '@/features/invoices/types'
import {
  buildWritePayload,
  mapServerErrors,
  newLine,
  totalsOf,
  type VatRefs,
} from '@/features/purchase-invoices/purchase-invoice-editor-lines'
import type { EditorSource } from '@/features/purchase-invoices/purchase-invoice-editor-source'
import {
  buildPurchaseInvoiceWriteSchema,
  type PurchaseInvoiceLineFormValues,
  type PurchaseInvoiceWriteFormValues,
} from '@/features/purchase-invoices/purchase-invoice-schema'
import type { PurchaseInvoice } from '@/features/purchase-invoices/types'
import { useAddOrderToInvoice } from '@/features/purchase-invoices/use-purchase-invoice-editor-orders'
import { usePurchaseInvoiceEditorPreview } from '@/features/purchase-invoices/use-purchase-invoice-editor-preview'
import {
  useCreatePurchaseInvoice,
  useUpdatePurchaseInvoice,
} from '@/features/purchase-invoices/use-purchase-invoice-editor-queries'

export type PurchaseInvoiceEditorTarget = { mode: 'create' } | { mode: 'edit'; invoiceId: number }

interface UsePurchaseInvoiceEditorFormArgs {
  source: EditorSource
  target: PurchaseInvoiceEditorTarget
  onSaved: (invoice: PurchaseInvoice) => void
}

const UNPROCESSABLE_STATUS = 422

/** The envelope's own message of a rejected save, when it carries one. */
function serverMessage(error: unknown): string | null {
  const message = axios.isAxiosError(error)
    ? (error.response?.data as { message?: unknown } | undefined)?.message
    : undefined
  return typeof message === 'string' && message !== '' ? message : null
}

/**
 * State and actions of the create/edit editor: RHF + Zod form, the document
 * rows (field array), the live totals/installment preview, ODA linking and
 * the save with 422 errors put on their inputs.
 */
export function usePurchaseInvoiceEditorForm({ source, target, onSaved }: UsePurchaseInvoiceEditorFormArgs) {
  const { t } = useTranslation()
  const schema = useMemo(() => buildPurchaseInvoiceWriteSchema(t), [t])
  const form = useForm<PurchaseInvoiceWriteFormValues>({
    resolver: zodResolver(schema),
    defaultValues: source.initialValues,
  })
  const { control, setError, handleSubmit, getValues } = form
  const lineArray = useFieldArray({ control, name: 'lines' })
  const { append } = lineArray

  // UI cache of the percentage per VAT id: the pickers expose ids, the preview needs rates.
  const [vatRefs, setVatRefs] = useState<VatRefs>(source.vatRefs)
  const [generalErrors, setGeneralErrors] = useState<string[]>([])
  const rememberVat = useCallback((ref: VatRateRef | null) => {
    if (ref === null) return
    setVatRefs((current) => (current[ref.id] ? current : { ...current, [ref.id]: ref }))
  }, [])

  const lines = useWatch({ control, name: 'lines' })
  const documentDate = useWatch({ control, name: 'document_date' })
  const paymentMethodId = useWatch({ control, name: 'payment_method_id' })
  const companyId = useWatch({ control, name: 'company_id' })
  const supplierId = useWatch({ control, name: 'supplier_id' })
  const documentType = useWatch({ control, name: 'document_type' })
  const orderIds = useWatch({ control, name: 'purchase_order_ids' })
  const totals = useMemo(() => totalsOf(lines, vatRefs), [lines, vatRefs])
  const isEdit = target.mode === 'edit'
  const preview = usePurchaseInvoiceEditorPreview({
    documentDate,
    paymentMethodId,
    totals,
    invoiceId: target.mode === 'edit' ? target.invoiceId : null,
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

  const create = useCreatePurchaseInvoice()
  const update = useUpdatePurchaseInvoice(target.mode === 'edit' ? target.invoiceId : 0)
  const mutation = isEdit ? update : create

  const submit = handleSubmit((values) => {
    setGeneralErrors([])
    mutation.mutate(buildWritePayload(values, target.mode), {
      onSuccess: (invoice) => {
        toast.success(t(isEdit ? 'purchaseInvoices.editor.messages.saved' : 'purchaseInvoices.editor.messages.created'))
        onSaved(invoice)
      },
      onError: (error) => {
        if (axios.isAxiosError(error) && error.response?.status === UNPROCESSABLE_STATUS) {
          const { fields, general } = mapServerErrors(
            (error.response.data as { errors?: Record<string, string[]> } | undefined)?.errors ?? {},
          )
          fields.forEach(({ path, message }) => setError(path, { message }))
          setGeneralErrors(general)
          if (fields.length > 0 || general.length > 0) {
            return
          }
        }
        toast.error(serverMessage(error) ?? t('purchaseInvoices.editor.messages.genericError'))
      },
    })
  })

  return {
    form,
    lineArray,
    vatRefs,
    rememberVat,
    lines,
    companyId,
    supplierId,
    documentType,
    orderIds,
    totals,
    preview,
    generalErrors,
    addLine,
    addOrder: orders.addOrder,
    isAddingOrder: orders.isAdding,
    submit,
    isSaving: mutation.isPending,
  }
}
