import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import {
  createOutboundEmailDraft,
  createOutboundEmailReminder,
  deleteOutboundEmail,
  fetchOutboundEmail,
  sendOutboundEmail,
  updateOutboundEmail,
  outboundEmailQueryKey,
  outboundEmailsListQueryKey,
} from '@/features/outbound-emails/api'
import type { CreateOutboundEmailPayload, EmailOwnerRef, OutboundEmail, OutboundEmailPayload } from '@/features/outbound-emails/types'

/** "Nuova email": creates an empty draft, the panel opens the composer on the id the server returns. */
export function useCreateOutboundEmailDraft(owner: EmailOwnerRef) {
  const queryClient = useQueryClient()
  return useMutation<OutboundEmail, AxiosError, CreateOutboundEmailPayload | void>({
    mutationFn: (payload) => (payload ? createOutboundEmailDraft(owner, payload) : createOutboundEmailDraft(owner)),
    onSuccess: (email) => {
      queryClient.setQueryData(outboundEmailQueryKey(owner, email.id), email)
      void queryClient.invalidateQueries({ queryKey: outboundEmailsListQueryKey(owner) })
    },
  })
}

/** Single email detail, shared by the composer (draft/failed) and the read-only dialog (queued/sent/failed). */
export function useOutboundEmail(owner: EmailOwnerRef, emailId: number) {
  return useQuery<OutboundEmail, AxiosError>({
    queryKey: outboundEmailQueryKey(owner, emailId),
    queryFn: () => fetchOutboundEmail(owner, emailId),
  })
}

/** Invalidates the list on every mutation below: status/subject/recipients/attachment count all surface as list columns. */
function useInvalidateOutboundEmailsList(owner: EmailOwnerRef) {
  const queryClient = useQueryClient()
  return () => queryClient.invalidateQueries({ queryKey: outboundEmailsListQueryKey(owner) })
}

/** PATCH `.../emails/{email}` ("Salva bozza" and the save-before-send step, D-2: draft only). */
export function useSaveOutboundEmailDraft(owner: EmailOwnerRef, emailId: number) {
  const queryClient = useQueryClient()
  const invalidateList = useInvalidateOutboundEmailsList(owner)
  return useMutation<OutboundEmail, AxiosError, OutboundEmailPayload>({
    mutationFn: (payload) => updateOutboundEmail(owner, emailId, payload),
    onSuccess: (email) => {
      queryClient.setQueryData(outboundEmailQueryKey(owner, emailId), email)
      void invalidateList()
    },
  })
}

/** DELETE `.../emails/{email}` ("Elimina bozza" and the silent cleanup of an untouched fresh draft, D-2). */
export function useDeleteOutboundEmailDraft(owner: EmailOwnerRef, emailId: number) {
  const queryClient = useQueryClient()
  const invalidateList = useInvalidateOutboundEmailsList(owner)
  return useMutation<void, AxiosError>({
    mutationFn: () => deleteOutboundEmail(owner, emailId),
    onSuccess: () => {
      queryClient.removeQueries({ queryKey: outboundEmailQueryKey(owner, emailId) })
      void invalidateList()
    },
  })
}

/** POST `.../emails/{email}/send` (draft -> queued, or `failed` -> queued on resend, AC-012/AC-014). */
export function useSendOutboundEmail(owner: EmailOwnerRef, emailId: number) {
  const queryClient = useQueryClient()
  const invalidateList = useInvalidateOutboundEmailsList(owner)
  return useMutation<OutboundEmail, AxiosError>({
    mutationFn: () => sendOutboundEmail(owner, emailId),
    onSuccess: (email) => {
      queryClient.setQueryData(outboundEmailQueryKey(owner, emailId), email)
      void invalidateList()
    },
  })
}

/** Invoice reminder (D-13): creates the `purpose=reminder` draft; the caller opens the composer on the returned id. */
export function useCreateOutboundEmailReminder(owner: EmailOwnerRef) {
  const queryClient = useQueryClient()
  return useMutation<OutboundEmail, AxiosError, { email_template_id?: number | null } | void>({
    mutationFn: (payload) => (payload ? createOutboundEmailReminder(owner, payload) : createOutboundEmailReminder(owner)),
    onSuccess: (email) => {
      queryClient.setQueryData(outboundEmailQueryKey(owner, email.id), email)
      void queryClient.invalidateQueries({ queryKey: outboundEmailsListQueryKey(owner) })
    },
  })
}
