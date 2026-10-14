import { useMemo, useState, type FormEvent } from 'react'
import { useFieldArray, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import {
  buildReceiptPayload,
  buildReceiptSchema,
  emptyReceiptValues,
  formatQuantity,
  type ReceiptFormValues,
} from '@/features/purchase-orders/purchase-order-receipt-schema'
import { applyReceiptServerErrors } from '@/features/purchase-orders/purchase-order-receipt-server-errors'
import type { PurchaseOrder } from '@/features/purchase-orders/types'
import { useCreateReceipt } from '@/features/purchase-orders/use-purchase-order-fulfillment'
import { serverMessage } from '@/features/purchase-requests/purchase-request-server-errors'

/**
 * State of the "Registra ricezione" dialog: RHF + Zod with one row per open
 * line (quantity prefilled to the residual), "receive everything" and the
 * submit that maps the server's 422 onto the right row and shows any other
 * failure (409 included) as a form-level alert.
 */
export function usePurchaseOrderReceiptForm(order: PurchaseOrder, onDone: () => void) {
  const { t } = useTranslation()
  const schema = useMemo(() => buildReceiptSchema(t), [t])
  const form = useForm<ReceiptFormValues>({
    resolver: zodResolver(schema),
    defaultValues: emptyReceiptValues(order.lines),
  })
  const { fields } = useFieldArray({ control: form.control, name: 'rows' })
  const create = useCreateReceipt(order.id)
  const [formErrors, setFormErrors] = useState<string[]>([])

  const receiveAll = () => {
    form.getValues('rows').forEach((row, index) => {
      form.setValue(`rows.${index}.quantity`, formatQuantity(row.residual), { shouldDirty: true })
    })
    form.clearErrors('rows')
  }

  const save = form.handleSubmit(async (values) => {
    // Step 1: only the lines with a quantity are sent
    const { payload, rowIndexes } = buildReceiptPayload(values)
    setFormErrors([])
    try {
      // Step 2: register; success refreshes the order and closes the dialog
      await create.mutateAsync(payload)
      onDone()
    } catch (error) {
      // Step 3: 422 on the inputs, anything else (409 included) as a form alert
      const unmapped = applyReceiptServerErrors(error, form.setError, rowIndexes)
      setFormErrors(unmapped ?? [serverMessage(error) ?? t('purchaseOrderActions.receipts.error')])
    }
  })

  /** The dialog lives inside the order form in the React tree: its submit must not reach the outer one. */
  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.stopPropagation()
    return save(event)
  }

  return {
    form,
    fields,
    formErrors,
    rowsError: form.formState.errors.rows?.message ?? form.formState.errors.rows?.root?.message,
    receiveAll,
    submit,
    isSaving: create.isPending,
  }
}
