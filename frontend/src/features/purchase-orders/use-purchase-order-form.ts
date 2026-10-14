import { useCallback, useMemo, useState } from 'react'
import { useFieldArray, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { createPurchaseOrder, purchaseOrderKeys } from '@/features/purchase-orders/api'
import {
  buildOrderPayload,
  emptyOrderFormValues,
  newOrderLineValues,
  toOrderFormValues,
} from '@/features/purchase-orders/purchase-order-payload'
import {
  buildPurchaseOrderSchema,
  type PurchaseOrderFormValues,
} from '@/features/purchase-orders/purchase-order-schema'
import { applyOrderServerErrors } from '@/features/purchase-orders/purchase-order-server-errors'
import { uploadOrderFiles } from '@/features/purchase-orders/purchase-order-uploads'
import type { PurchaseOrder } from '@/features/purchase-orders/types'
import { serverMessage } from '@/features/purchase-requests/purchase-request-server-errors'

/**
 * RHF + Zod state of an ODA, shared by the create form and the detail's
 * in-place editing: the lines as a field array, their live values and the
 * header values the pickers depend on.
 */
export function usePurchaseOrderFormState(order?: PurchaseOrder) {
  const { t } = useTranslation()
  const schema = useMemo(() => buildPurchaseOrderSchema(t), [t])
  const form = useForm<PurchaseOrderFormValues>({
    resolver: zodResolver(schema),
    defaultValues: order ? toOrderFormValues(order) : emptyOrderFormValues(),
  })
  const { control, getValues } = form
  const lineArray = useFieldArray({ control, name: 'lines' })
  const { append } = lineArray
  const lines = useWatch({ control, name: 'lines' })
  const companyId = useWatch({ control, name: 'company_id' })
  const supplierId = useWatch({ control, name: 'supplier_id' })
  const supplierQualified = useWatch({ control, name: 'supplier_qualified' })

  /** A new row inherits the VAT of the last one and the header work order (D-13). */
  const addLine = useCallback(() => {
    const last = getValues('lines').at(-1)
    append(
      newOrderLineValues({
        vat_rate_id: last?.vat_rate_id ?? null,
        vat_rate_percent: last?.vat_rate_percent ?? null,
        work_order_id: getValues('work_order_id'),
      }),
    )
  }, [append, getValues])

  return { form, lineArray, lines, companyId, supplierId, supplierQualified, addLine }
}

interface UsePurchaseOrderFormArgs {
  /** `createdIds` holds every created order (more than one when copies were requested). */
  onSaved: (saved: PurchaseOrder, createdIds: number[]) => void
}

interface SaveResult {
  saved: PurchaseOrder
  createdIds: number[]
  failedUploads: string[]
}

/**
 * The create form of an ODA: the shared form state, the header work order
 * proposed to the lines, the create with its monthly copies (then the queued
 * documents) and the mapping of the server's 422 onto inputs. A saved ODA
 * edits in place on the detail instead (spec 0257).
 */
export function usePurchaseOrderForm({ onSaved }: UsePurchaseOrderFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const state = usePurchaseOrderFormState()
  const { setError, handleSubmit, getValues, setValue } = state.form
  const [formErrors, setFormErrors] = useState<string[]>([])

  /** A header work order proposes itself to the new lines that have none yet. */
  const proposeWorkOrder = useCallback(
    (workOrderId: number | null) => {
      getValues('lines').forEach((line, index) => {
        if (line.id === undefined && line.work_order_id === null) {
          setValue(`lines.${index}.work_order_id`, workOrderId, { shouldDirty: true })
        }
      })
    },
    [getValues, setValue],
  )

  const mutation = useMutation<SaveResult, unknown, PurchaseOrderFormValues>({
    mutationFn: async (values) => {
      // Step 1: persist the ODA and its copies (the server recomputes every amount)
      const { order: saved, createdIds } = await createPurchaseOrder(buildOrderPayload(values))
      // Step 2: upload the documents queued while the owner did not exist yet
      const failedUploads = await uploadOrderFiles(saved, values)
      return { saved, createdIds, failedUploads }
    },
    onSuccess: ({ saved, createdIds, failedUploads }) => {
      queryClient.setQueryData(purchaseOrderKeys.detail(saved.id), saved)
      void queryClient.invalidateQueries({ queryKey: purchaseOrderKeys.all })
      void queryClient.invalidateQueries({ queryKey: ['attachments'] })
      toast.success(
        createdIds.length > 1
          ? t('purchaseOrderForm.messages.createdMany', { count: createdIds.length })
          : t('purchaseOrderForm.messages.created'),
      )
      if (failedUploads.length > 0) {
        toast.warning(t('purchaseOrderForm.messages.uploadsFailed', { names: failedUploads.join(', ') }))
      }
      onSaved(saved, createdIds)
    },
    onError: (error) => {
      const unmapped = applyOrderServerErrors(error, setError)
      if (unmapped !== null) {
        setFormErrors(unmapped)
        return
      }
      toast.error(serverMessage(error) ?? t('purchaseOrderForm.messages.saveError'))
    },
  })

  const submit = handleSubmit((values) => {
    setFormErrors([])
    mutation.mutate(values)
  })

  return { ...state, formErrors, proposeWorkOrder, submit, isSaving: mutation.isPending }
}
