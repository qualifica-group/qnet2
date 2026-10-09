import { useCallback, useMemo, useState } from 'react'
import { useFieldArray, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import {
  createPurchaseRequest,
  purchaseRequestKeys,
  updatePurchaseRequest,
} from '@/features/purchase-requests/api'
import {
  buildPayload,
  emptyFormValues,
  newLineValues,
  toFormValues,
} from '@/features/purchase-requests/purchase-request-payload'
import {
  buildPurchaseRequestSchema,
  type PurchaseRequestFormValues,
} from '@/features/purchase-requests/purchase-request-schema'
import {
  applyServerErrors,
  isConflict,
  serverMessage,
} from '@/features/purchase-requests/purchase-request-server-errors'
import { uploadQueuedFiles } from '@/features/purchase-requests/purchase-request-uploads'
import type { PurchaseRequest } from '@/features/purchase-requests/types'

interface UsePurchaseRequestFormArgs {
  /** The loaded RDA (edit); absent while creating. */
  request?: PurchaseRequest
  /** Default requester of a new RDA: the signed-in user. */
  requesterId: number | null
  onSaved: (saved: PurchaseRequest) => void
}

interface SaveResult {
  saved: PurchaseRequest
  failedUploads: string[]
}

/**
 * State and actions of the RDA form: RHF + Zod with the lines as a field
 * array, the save (create or update, then the queued documents) and the
 * mapping of the server's 422 onto inputs.
 */
export function usePurchaseRequestForm({ request, requesterId, onSaved }: UsePurchaseRequestFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const schema = useMemo(() => buildPurchaseRequestSchema(t), [t])
  const form = useForm<PurchaseRequestFormValues>({
    resolver: zodResolver(schema),
    defaultValues: request ? toFormValues(request) : emptyFormValues(requesterId),
  })
  const { control, setError, handleSubmit, getValues } = form
  const lineArray = useFieldArray({ control, name: 'lines' })
  const { append } = lineArray
  const lines = useWatch({ control, name: 'lines' })
  const companyId = useWatch({ control, name: 'company_id' })
  const [formErrors, setFormErrors] = useState<string[]>([])

  /** A new row inherits the VAT of the last one, like the invoice editor. */
  const addLine = useCallback(() => {
    const last = getValues('lines').at(-1)
    append(newLineValues({ vat_rate_id: last?.vat_rate_id ?? null, vat_rate_percent: last?.vat_rate_percent ?? null }))
  }, [append, getValues])

  const mutation = useMutation<SaveResult, unknown, PurchaseRequestFormValues>({
    mutationFn: async (values) => {
      // Step 1: persist the RDA (the server recomputes every amount)
      const payload = buildPayload(values)
      const saved = request
        ? await updatePurchaseRequest(request.id, payload)
        : await createPurchaseRequest(payload)
      // Step 2: upload the documents queued while the owner did not exist yet
      const failedUploads = await uploadQueuedFiles(saved, values)
      return { saved, failedUploads }
    },
    onSuccess: ({ saved, failedUploads }) => {
      queryClient.setQueryData(purchaseRequestKeys.detail(saved.id), saved)
      void queryClient.invalidateQueries({ queryKey: ['attachments'] })
      toast.success(t(request ? 'purchaseRequests.messages.updated' : 'purchaseRequests.messages.created'))
      if (failedUploads.length > 0) {
        toast.warning(t('purchaseRequests.messages.uploadsFailed', { names: failedUploads.join(', ') }))
      }
      onSaved(saved)
    },
    onError: (error) => {
      const unmapped = applyServerErrors(error, setError)
      if (unmapped !== null) {
        setFormErrors(unmapped)
        return
      }
      toast.error(serverMessage(error) ?? t(isConflict(error) ? 'purchaseRequests.messages.conflict' : 'purchaseRequests.messages.saveError'))
    },
  })

  const submit = handleSubmit((values) => {
    setFormErrors([])
    mutation.mutate(values)
  })

  return { form, lineArray, lines, companyId, formErrors, addLine, submit, isSaving: mutation.isPending }
}
