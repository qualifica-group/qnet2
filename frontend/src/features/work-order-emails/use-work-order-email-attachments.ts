import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import {
  importWorkOrderEmailAttachments,
  removeWorkOrderEmailAttachment,
  uploadWorkOrderEmailAttachment,
  workOrderEmailQueryKey,
  workOrderEmailsListQueryKey,
} from '@/features/work-order-emails/api'
import type { ImportAttachmentsPayload, OutboundEmail } from '@/features/work-order-emails/types'

/**
 * Upload / import / remove mutations for one draft's attachments (D-7).
 * Every endpoint returns the WHOLE updated `OutboundEmail` — the caller never
 * diffs the attachment array itself, it just replaces the cached detail with
 * the server's own answer (also keeps `attachments_total_size` correct).
 */
export function useWorkOrderEmailAttachments(workOrderId: number, emailId: number) {
  const queryClient = useQueryClient()
  const detailKey = workOrderEmailQueryKey(workOrderId, emailId)

  const applyUpdate = (email: OutboundEmail) => {
    queryClient.setQueryData(detailKey, email)
    void queryClient.invalidateQueries({ queryKey: workOrderEmailsListQueryKey(workOrderId) })
  }

  const upload = useMutation<OutboundEmail, AxiosError, File>({
    mutationFn: (file) => uploadWorkOrderEmailAttachment(workOrderId, emailId, file),
    onSuccess: applyUpdate,
  })

  const importAttachments = useMutation<OutboundEmail, AxiosError, ImportAttachmentsPayload>({
    mutationFn: (payload) => importWorkOrderEmailAttachments(workOrderId, emailId, payload),
    onSuccess: applyUpdate,
  })

  const remove = useMutation<OutboundEmail, AxiosError, number>({
    mutationFn: (attachmentId) => removeWorkOrderEmailAttachment(workOrderId, emailId, attachmentId),
    onSuccess: applyUpdate,
  })

  return { upload, importAttachments, remove }
}
