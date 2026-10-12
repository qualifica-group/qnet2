import { useCallback, useMemo, useState } from 'react'
import { useFieldArray, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { createPurchaseOrder, purchaseOrderKeys, updatePurchaseOrder } from '@/features/purchase-orders/api'
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
import { isConflict, serverMessage } from '@/features/purchase-requests/purchase-request-server-errors'

interface UsePurchaseOrderFormArgs {
  /** The loaded ODA (edit); absent while creating. */
  order?: PurchaseOrder
  /** `createdIds` holds every created order (more than one when copies were requested). */
  onSaved: (saved: PurchaseOrder, createdIds: number[]) => void
}

interface SaveResult {
  saved: PurchaseOrder
  createdIds: number[]
  failedUploads: string[]
}

/**
 * State and actions of the ODA form: RHF + Zod with the lines as a field
 * array, the save (create with copies or update, then the queued documents)
 * and the mapping of the server's 422 onto inputs.
 */
export function usePurchaseOrderForm({ order, onSaved }: UsePurchaseOrderFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const schema = useMemo(() => buildPurchaseOrderSchema(t), [t])
  const form = useForm<PurchaseOrderFormValues>({
    resolver: zodResolver(schema),
    defaultValues: order ? toOrderFormValues(order) : emptyOrderFormValues(),
  })
  const { control, setError, handleSubmit, getValues, setValue } = form
  const lineArray = useFieldArray({ control, name: 'lines' })
  const { append } = lineArray
  const lines = useWatch({ control, name: 'lines' })
  const companyId = useWatch({ control, name: 'company_id' })
  const supplierId = useWatch({ control, name: 'supplier_id' })
  const supplierQualified = useWatch({ control, name: 'supplier_qualified' })
  const [formErrors, setFormErrors] = useState<string[]>([])

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
      // Step 1: persist the ODA (the server recomputes every amount)
      const saved = order
        ? { saved: await updatePurchaseOrder(order.id, buildOrderPayload(values, 'update')), createdIds: [order.id] }
        : await createPurchaseOrder(buildOrderPayload(values, 'create')).then(({ order: first, createdIds }) => ({
            saved: first,
            createdIds,
          }))
      // Step 2: upload the documents queued while the owner did not exist yet
      const failedUploads = await uploadOrderFiles(saved.saved, values)
      return { ...saved, failedUploads }
    },
    onSuccess: ({ saved, createdIds, failedUploads }) => {
      queryClient.setQueryData(purchaseOrderKeys.detail(saved.id), saved)
      void queryClient.invalidateQueries({ queryKey: purchaseOrderKeys.all })
      void queryClient.invalidateQueries({ queryKey: ['attachments'] })
      toast.success(
        order
          ? t('purchaseOrderForm.messages.updated')
          : createdIds.length > 1
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
      toast.error(
        serverMessage(error) ?? t(isConflict(error) ? 'purchaseOrderForm.messages.conflict' : 'purchaseOrderForm.messages.saveError'),
      )
    },
  })

  const submit = handleSubmit((values) => {
    setFormErrors([])
    mutation.mutate(values)
  })

  return {
    form,
    lineArray,
    lines,
    companyId,
    supplierId,
    supplierQualified,
    formErrors,
    addLine,
    proposeWorkOrder,
    submit,
    isSaving: mutation.isPending,
  }
}
