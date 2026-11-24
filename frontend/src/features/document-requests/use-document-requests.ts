import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import type { ApiErrorResponse } from '@/api/types'
import {
  createDocumentRequest,
  fetchDocumentRequestDefaults,
  fetchDocumentRequests,
  fetchDocumentRequestSyncLogs,
  runDocumentRequestAction,
  updateDocumentRequest,
  type DocumentRequestAction,
} from '@/features/document-requests/api'
import { hasPendingDelivery, PENDING_DELIVERY_REFETCH_MS } from '@/features/document-requests/document-request-status'
import { documentRequestKeys } from '@/features/document-requests/query-keys'
import type {
  CreateDocumentRequestPayload,
  DocumentRequest,
  DocumentRequestDefaults,
  DocumentRequestList,
  DocumentRequestSyncLog,
  UpdateDocumentRequestPayload,
} from '@/features/document-requests/types'

type RequestError = AxiosError<ApiErrorResponse>

/** The work order's requests; refetched on a short period while a call to ArchivioSmart is queued. */
export function useDocumentRequests(workOrderId: number) {
  return useQuery<DocumentRequestList, RequestError>({
    queryKey: documentRequestKeys.list(workOrderId),
    queryFn: () => fetchDocumentRequests(workOrderId),
    refetchInterval: (query) => (hasPendingDelivery(query.state.data?.requests) ? PENDING_DELIVERY_REFETCH_MS : false),
  })
}

export function useDocumentRequestDefaults(workOrderId: number, enabled: boolean) {
  return useQuery<DocumentRequestDefaults, RequestError>({
    queryKey: documentRequestKeys.defaults(workOrderId),
    queryFn: () => fetchDocumentRequestDefaults(workOrderId),
    enabled,
    staleTime: 0,
  })
}

export function useDocumentRequestSyncLogs(requestId: number, enabled: boolean) {
  return useQuery<DocumentRequestSyncLog[], RequestError>({
    queryKey: documentRequestKeys.syncLogs(requestId),
    queryFn: () => fetchDocumentRequestSyncLogs(requestId),
    enabled,
  })
}

export function useCreateDocumentRequest(workOrderId: number) {
  const queryClient = useQueryClient()
  return useMutation<DocumentRequest, RequestError, CreateDocumentRequestPayload>({
    mutationFn: (payload) => createDocumentRequest(workOrderId, payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: documentRequestKeys.list(workOrderId) }),
  })
}

export function useUpdateDocumentRequest(workOrderId: number, requestId: number) {
  const queryClient = useQueryClient()
  return useMutation<DocumentRequest, RequestError, UpdateDocumentRequestPayload>({
    mutationFn: (payload) => updateDocumentRequest(requestId, payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: documentRequestKeys.list(workOrderId) }),
  })
}

/** Remind, cancel and refresh share one mutation: each reloads the list, where the queued state shows. */
export function useDocumentRequestAction(workOrderId: number, requestId: number) {
  const queryClient = useQueryClient()
  return useMutation<void, RequestError, DocumentRequestAction>({
    mutationFn: (action) => runDocumentRequestAction(requestId, action),
    onSuccess: () =>
      Promise.all([
        queryClient.invalidateQueries({ queryKey: documentRequestKeys.list(workOrderId) }),
        queryClient.invalidateQueries({ queryKey: documentRequestKeys.syncLogs(requestId) }),
      ]),
  })
}
