import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import {
  importOutboundEmailAttachments,
  removeOutboundEmailAttachment,
  uploadOutboundEmailAttachment,
  outboundEmailQueryKey,
  outboundEmailsListQueryKey,
} from '@/features/outbound-emails/api'
import type { EmailOwnerRef, ImportAttachmentsPayload, OutboundEmail } from '@/features/outbound-emails/types'

/**
 * Upload / import / remove mutations for one draft's attachments (D-7).
 * Every endpoint returns the WHOLE updated `OutboundEmail` — the caller never
 * diffs the attachment array itself, it just replaces the cached detail with
 * the server's own answer (also keeps `attachments_total_size` correct).
 */
export function useOutboundEmailAttachments(owner: EmailOwnerRef, emailId: number) {
  const queryClient = useQueryClient()
  const detailKey = outboundEmailQueryKey(owner, emailId)

  const applyUpdate = (email: OutboundEmail) => {
    queryClient.setQueryData(detailKey, email)
    void queryClient.invalidateQueries({ queryKey: outboundEmailsListQueryKey(owner) })
  }

  const upload = useMutation<OutboundEmail, AxiosError, File>({
    mutationFn: (file) => uploadOutboundEmailAttachment(owner, emailId, file),
    onSuccess: applyUpdate,
  })

  const importAttachments = useMutation<OutboundEmail, AxiosError, ImportAttachmentsPayload>({
    mutationFn: (payload) => importOutboundEmailAttachments(owner, emailId, payload),
    onSuccess: applyUpdate,
  })

  const remove = useMutation<OutboundEmail, AxiosError, number>({
    mutationFn: (attachmentId) => removeOutboundEmailAttachment(owner, emailId, attachmentId),
    onSuccess: applyUpdate,
  })

  return { upload, importAttachments, remove }
}
