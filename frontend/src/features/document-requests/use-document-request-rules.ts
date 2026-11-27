import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import type { ApiErrorResponse } from '@/api/types'
import {
  fetchChecklistOpenRequestsCount,
  refreshChecklistOpenRequests,
  refreshDocumentRequestRules,
  revalidateDocumentRequestItems,
  stopDocumentRequestItemValidation,
} from '@/features/document-requests/api'
import { documentRequestKeys } from '@/features/document-requests/query-keys'

type RequestError = AxiosError<ApiErrorResponse>

/** The first reason of a 422 (status, item, document...), or the envelope message: shown as is. */
export function actionErrorReason(error: RequestError): string | undefined {
  const errors = error.response?.data?.errors
  const first = errors ? Object.values(errors)[0]?.[0] : undefined
  return first ?? error.response?.data?.message
}

/** Every action re-reads the list, where the queued operation shows, and the sync log. */
function useInvalidateRequest(workOrderId: number, requestId: number) {
  const queryClient = useQueryClient()
  return () =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: documentRequestKeys.list(workOrderId) }),
      queryClient.invalidateQueries({ queryKey: documentRequestKeys.syncLogs(requestId) }),
    ])
}

export function useRefreshDocumentRequestRules(workOrderId: number, requestId: number) {
  const invalidate = useInvalidateRequest(workOrderId, requestId)
  return useMutation<void, RequestError, void>({
    mutationFn: () => refreshDocumentRequestRules(requestId),
    onSuccess: invalidate,
  })
}

export function useRevalidateDocumentRequestItems(workOrderId: number, requestId: number) {
  const invalidate = useInvalidateRequest(workOrderId, requestId)
  return useMutation<void, RequestError, number[]>({
    mutationFn: (itemIds) => revalidateDocumentRequestItems(requestId, itemIds),
    onSuccess: invalidate,
  })
}

export function useStopDocumentRequestItemValidation(workOrderId: number, requestId: number) {
  const invalidate = useInvalidateRequest(workOrderId, requestId)
  return useMutation<void, RequestError, number | string>({
    mutationFn: (itemId) => stopDocumentRequestItemValidation(requestId, itemId),
    onSuccess: invalidate,
  })
}

/** Open requests of a checklist, read once after a save; `null` = do not ask. */
export function useChecklistOpenRequestsCount(checklistId: number | null) {
  return useQuery<number, RequestError>({
    queryKey: documentRequestKeys.checklistOpenRequests(checklistId ?? 0),
    queryFn: () => fetchChecklistOpenRequestsCount(checklistId ?? 0),
    enabled: checklistId !== null,
    staleTime: 0,
  })
}

export function useRefreshChecklistOpenRequests(checklistId: number) {
  const queryClient = useQueryClient()
  return useMutation<number, RequestError, void>({
    mutationFn: () => refreshChecklistOpenRequests(checklistId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: documentRequestKeys.lists }),
  })
}
