import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import type { ApiErrorResponse } from '@/api/types'
import {
  createApiClient,
  deleteApiClient,
  downloadApiDoc,
  fetchApiClient,
  rotateApiClientKey,
  updateApiClient,
} from '@/features/api-integrations/api'
import { apiIntegrationsKeys } from '@/features/api-integrations/query-keys'
import type {
  ApiClient,
  ApiClientPayload,
  ApiClientWithKey,
  ApiDocDownloadResult,
  ApiDocKind,
} from '@/features/api-integrations/types'

type ApiError = AxiosError<ApiErrorResponse>

export function useApiClient(id: number | null) {
  return useQuery<ApiClient, ApiError>({
    queryKey: apiIntegrationsKeys.detail(id ?? 0),
    queryFn: () => fetchApiClient(id as number),
    enabled: id !== null,
  })
}

/**
 * `gcTime: 0` on the two key-bearing mutations: the MutationCache would
 * otherwise keep the plain-text key for minutes. Callers also `reset()` right
 * after copying the result into their local state (spec 0209 AC-021).
 */
export function useCreateApiClient() {
  const queryClient = useQueryClient()
  return useMutation<ApiClientWithKey, ApiError, ApiClientPayload>({
    mutationFn: (payload) => createApiClient(payload),
    gcTime: 0,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: apiIntegrationsKeys.all }),
  })
}

export function useUpdateApiClient(id: number) {
  const queryClient = useQueryClient()
  return useMutation<ApiClient, ApiError, ApiClientPayload>({
    mutationFn: (payload) => updateApiClient(id, payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: apiIntegrationsKeys.all }),
  })
}

export function useRotateApiClientKey() {
  const queryClient = useQueryClient()
  return useMutation<ApiClientWithKey, ApiError, number>({
    mutationFn: (id) => rotateApiClientKey(id),
    gcTime: 0,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: apiIntegrationsKeys.all }),
  })
}

export function useDeleteApiClient() {
  const queryClient = useQueryClient()
  return useMutation<void, ApiError, number>({
    mutationFn: (id) => deleteApiClient(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: apiIntegrationsKeys.all }),
  })
}

export function useApiDocDownload() {
  return useMutation<ApiDocDownloadResult, ApiError, ApiDocKind>({ mutationFn: (kind) => downloadApiDoc(kind) })
}
