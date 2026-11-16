import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import type { ApiErrorResponse } from '@/api/types'
import { fetchApiDocsStatus, fetchOpenApiDocument, refreshApiDocs } from '@/features/api-integrations/api'
import { apiIntegrationsKeys } from '@/features/api-integrations/query-keys'
import type { OpenApiDocument } from '@/features/api-integrations/openapi-types'
import type { ApiDocsStatus } from '@/features/api-integrations/types'

type ApiError = AxiosError<ApiErrorResponse>

/** Poll interval of the status while a regeneration runs; the server drops a stuck flag on its own. */
export const GENERATING_POLL_MS = 5000

export interface OpenApiDocumentState {
  document: OpenApiDocument | null
  status: ApiDocsStatus['status'] | null
  isLoading: boolean
  isError: boolean
  retry: () => void
}

/** Status of the generated documentation, polled only while a regeneration runs. */
export function useApiDocsStatus() {
  return useQuery<ApiDocsStatus, ApiError>({
    queryKey: apiIntegrationsKeys.docsStatus(),
    queryFn: fetchApiDocsStatus,
    refetchInterval: ({ state }) => (state.data?.status === 'generating' ? GENERATING_POLL_MS : false),
  })
}

/** Starts a background regeneration; the returned status switches the status query to polling. */
export function useRefreshApiDocs() {
  const queryClient = useQueryClient()
  return useMutation<ApiDocsStatus, ApiError>({
    mutationFn: refreshApiDocs,
    onSuccess: (status) => queryClient.setQueryData(apiIntegrationsKeys.docsStatus(), status),
  })
}

/**
 * The last generated OpenAPI document. Never triggers a generation: the document
 * is fetched once per `generated_at`, and the previous one stays on screen while
 * a regenerated one loads.
 */
export function useOpenApiDocument(): OpenApiDocumentState {
  const queryClient = useQueryClient()
  const statusQuery = useApiDocsStatus()
  const generatedAt = statusQuery.data?.generated_at ?? null
  const documentQuery = useQuery<OpenApiDocument, ApiError>({
    queryKey: apiIntegrationsKeys.openApi(generatedAt ?? ''),
    queryFn: fetchOpenApiDocument,
    enabled: generatedAt !== null,
    placeholderData: keepPreviousData,
    staleTime: Infinity,
  })

  return {
    document: documentQuery.data ?? null,
    status: statusQuery.data?.status ?? null,
    isLoading: statusQuery.isLoading || documentQuery.isLoading,
    isError: statusQuery.isError || documentQuery.isError,
    retry: () => void queryClient.resetQueries({ queryKey: apiIntegrationsKeys.docs() }),
  }
}
