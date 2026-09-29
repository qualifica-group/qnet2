import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import {
  createWorkOrderEmailDraft,
  deleteWorkOrderEmail,
  fetchWorkOrderEmail,
  sendWorkOrderEmail,
  updateWorkOrderEmail,
  workOrderEmailQueryKey,
  workOrderEmailsListQueryKey,
} from '@/features/work-order-emails/api'
import type { OutboundEmail, OutboundEmailPayload } from '@/features/work-order-emails/types'

/** "Nuova email": creates an empty draft, the panel opens the composer on the id the server returns. */
export function useCreateWorkOrderEmailDraft(workOrderId: number) {
  const queryClient = useQueryClient()
  return useMutation<OutboundEmail, AxiosError>({
    mutationFn: () => createWorkOrderEmailDraft(workOrderId),
    onSuccess: (email) => {
      queryClient.setQueryData(workOrderEmailQueryKey(workOrderId, email.id), email)
      void queryClient.invalidateQueries({ queryKey: workOrderEmailsListQueryKey(workOrderId) })
    },
  })
}

/** Single email detail, shared by the composer (draft/failed) and the read-only dialog (queued/sent/failed). */
export function useWorkOrderEmail(workOrderId: number, emailId: number) {
  return useQuery<OutboundEmail, AxiosError>({
    queryKey: workOrderEmailQueryKey(workOrderId, emailId),
    queryFn: () => fetchWorkOrderEmail(workOrderId, emailId),
  })
}

/** Invalidates the list on every mutation below: status/subject/recipients/attachment count all surface as list columns. */
function useInvalidateWorkOrderEmailsList(workOrderId: number) {
  const queryClient = useQueryClient()
  return () => queryClient.invalidateQueries({ queryKey: workOrderEmailsListQueryKey(workOrderId) })
}

/** PATCH `.../emails/{email}` ("Salva bozza" and the save-before-send step, D-2: draft only). */
export function useSaveWorkOrderEmailDraft(workOrderId: number, emailId: number) {
  const queryClient = useQueryClient()
  const invalidateList = useInvalidateWorkOrderEmailsList(workOrderId)
  return useMutation<OutboundEmail, AxiosError, OutboundEmailPayload>({
    mutationFn: (payload) => updateWorkOrderEmail(workOrderId, emailId, payload),
    onSuccess: (email) => {
      queryClient.setQueryData(workOrderEmailQueryKey(workOrderId, emailId), email)
      void invalidateList()
    },
  })
}

/** DELETE `.../emails/{email}` ("Elimina bozza" and the silent cleanup of an untouched fresh draft, D-2). */
export function useDeleteWorkOrderEmailDraft(workOrderId: number, emailId: number) {
  const queryClient = useQueryClient()
  const invalidateList = useInvalidateWorkOrderEmailsList(workOrderId)
  return useMutation<void, AxiosError>({
    mutationFn: () => deleteWorkOrderEmail(workOrderId, emailId),
    onSuccess: () => {
      queryClient.removeQueries({ queryKey: workOrderEmailQueryKey(workOrderId, emailId) })
      void invalidateList()
    },
  })
}

/** POST `.../emails/{email}/send` (draft -> queued, or `failed` -> queued on resend, AC-012/AC-014). */
export function useSendWorkOrderEmail(workOrderId: number, emailId: number) {
  const queryClient = useQueryClient()
  const invalidateList = useInvalidateWorkOrderEmailsList(workOrderId)
  return useMutation<OutboundEmail, AxiosError>({
    mutationFn: () => sendWorkOrderEmail(workOrderId, emailId),
    onSuccess: (email) => {
      queryClient.setQueryData(workOrderEmailQueryKey(workOrderId, emailId), email)
      void invalidateList()
    },
  })
}
