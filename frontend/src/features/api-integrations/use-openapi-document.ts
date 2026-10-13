import { useQuery, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import type { ApiErrorResponse } from '@/api/types'
import { fetchOpenApiDocument } from '@/features/api-integrations/api'
import { apiIntegrationsKeys } from '@/features/api-integrations/query-keys'
import type { OpenApiDocument, OpenApiFetchResult } from '@/features/api-integrations/openapi-types'

/** Longest wait for the server to build the document before giving up and offering a retry. */
export const MAX_GENERATING_WAIT_MS = 5 * 60 * 1000

export interface OpenApiDocumentState {
  document: OpenApiDocument | null
  isLoading: boolean
  /** The server is building the document (202); the query polls on its own. */
  isGenerating: boolean
  /** Request failure, or the generation outlasted `MAX_GENERATING_WAIT_MS`. */
  isError: boolean
  retry: () => void
}

function hasWaitedTooLong(result: OpenApiFetchResult | undefined, pollCount: number): boolean {
  return result?.status === 'generating' && pollCount * result.retryAfterSeconds * 1000 >= MAX_GENERATING_WAIT_MS
}

/** Loads the OpenAPI document, polling at the server's `Retry-After` while it answers 202. */
export function useOpenApiDocument(): OpenApiDocumentState {
  const queryClient = useQueryClient()
  const query = useQuery<OpenApiFetchResult, AxiosError<ApiErrorResponse>>({
    queryKey: apiIntegrationsKeys.openApi(),
    queryFn: fetchOpenApiDocument,
    refetchInterval: ({ state }) =>
      state.data?.status === 'generating' && !hasWaitedTooLong(state.data, state.dataUpdateCount)
        ? state.data.retryAfterSeconds * 1000
        : false,
  })
  // Reading `dataUpdatedAt` subscribes this render to every poll, even when the 202 body is identical
  const pollCount =
    query.dataUpdatedAt === 0 ? 0 : (queryClient.getQueryState(apiIntegrationsKeys.openApi())?.dataUpdateCount ?? 0)
  const timedOut = hasWaitedTooLong(query.data, pollCount)

  return {
    document: query.data?.status === 'ready' ? query.data.document : null,
    isLoading: query.isLoading,
    isGenerating: query.data?.status === 'generating' && !timedOut,
    isError: query.isError || timedOut,
    retry: () => void queryClient.resetQueries({ queryKey: apiIntegrationsKeys.openApi() }),
  }
}
