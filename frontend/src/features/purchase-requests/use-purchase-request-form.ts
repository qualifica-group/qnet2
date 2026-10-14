import { useCallback, useEffect, useMemo, useState } from 'react'
import { useFieldArray, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { useDefaultPurchaseRequestPriorityId } from '@/features/purchase-request-priorities/use-default-purchase-request-priority'
import { createPurchaseRequest, purchaseRequestKeys } from '@/features/purchase-requests/api'
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
import { applyServerErrors, serverMessage } from '@/features/purchase-requests/purchase-request-server-errors'
import { uploadQueuedFiles } from '@/features/purchase-requests/purchase-request-uploads'
import type { PurchaseRequest } from '@/features/purchase-requests/types'

interface UsePurchaseRequestFormStateArgs {
  /** The saved RDA (detail); absent while creating. */
  request?: PurchaseRequest
  /** Default requester of a new RDA: the signed-in user. */
  requesterId: number | null
}

/**
 * RHF + Zod state of an RDA, shared by the create form and the detail's
 * in-place editing: the lines as a field array, their live values and the
 * company the site picker depends on.
 */
export function usePurchaseRequestFormState({ request, requesterId }: UsePurchaseRequestFormStateArgs) {
  const { t } = useTranslation()
  const schema = useMemo(() => buildPurchaseRequestSchema(t), [t])
  const form = useForm<PurchaseRequestFormValues>({
    resolver: zodResolver(schema),
    defaultValues: request ? toFormValues(request) : emptyFormValues(requesterId),
  })
  const { control, getValues } = form
  const lineArray = useFieldArray({ control, name: 'lines' })
  const { append } = lineArray
  const lines = useWatch({ control, name: 'lines' })
  const companyId = useWatch({ control, name: 'company_id' })

  /** A new row inherits the VAT of the last one, like the invoice editor. */
  const addLine = useCallback(() => {
    const last = getValues('lines').at(-1)
    append(newLineValues({ vat_rate_id: last?.vat_rate_id ?? null, vat_rate_percent: last?.vat_rate_percent ?? null }))
  }, [append, getValues])

  return { form, lineArray, lines, companyId, addLine }
}

interface UsePurchaseRequestFormArgs {
  /** Default requester of the new RDA: the signed-in user. */
  requesterId: number | null
  onSaved: (saved: PurchaseRequest) => void
}

interface SaveResult {
  saved: PurchaseRequest
  failedUploads: string[]
}

/**
 * The create form of an RDA: the shared form state, the catalog's default
 * priority, the create (then the queued documents) and the mapping of the
 * server's 422 onto inputs. The detail edits in place instead (spec 0256).
 */
export function usePurchaseRequestForm({ requesterId, onSaved }: UsePurchaseRequestFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const state = usePurchaseRequestFormState({ requesterId })
  const { form } = state
  const { setError, handleSubmit, getValues } = form
  const [formErrors, setFormErrors] = useState<string[]>([])

  // A new RDA starts on the catalog's default priority, like the server would when the field is omitted.
  const defaultPriorityId = useDefaultPurchaseRequestPriorityId(true)
  useEffect(() => {
    if (defaultPriorityId !== null && getValues('purchase_request_priority_id') === null) {
      form.setValue('purchase_request_priority_id', defaultPriorityId, { shouldDirty: true })
    }
  }, [defaultPriorityId, form, getValues])

  const mutation = useMutation<SaveResult, unknown, PurchaseRequestFormValues>({
    mutationFn: async (values) => {
      // Step 1: persist the RDA (the server recomputes every amount)
      const saved = await createPurchaseRequest(buildPayload(values))
      // Step 2: upload the documents queued while the owner did not exist yet
      const failedUploads = await uploadQueuedFiles(saved, values)
      return { saved, failedUploads }
    },
    onSuccess: ({ saved, failedUploads }) => {
      queryClient.setQueryData(purchaseRequestKeys.detail(saved.id), saved)
      void queryClient.invalidateQueries({ queryKey: ['attachments'] })
      toast.success(t('purchaseRequests.messages.created'))
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
      toast.error(serverMessage(error) ?? t('purchaseRequests.messages.saveError'))
    },
  })

  const submit = handleSubmit((values) => {
    setFormErrors([])
    mutation.mutate(values)
  })

  return { ...state, formErrors, submit, isSaving: mutation.isPending }
}
