import { useQuery } from '@tanstack/react-query'
import { fetchFormalabStatus } from '@/integrations/formalab/api'
import { formalabKeys } from '@/integrations/formalab/query-keys'

/** While a sync is running the status is reread, so the page sees it end without a reload. */
export const SYNC_POLL_INTERVAL_MS = 5000

export function useFormalabStatus() {
  return useQuery({
    queryKey: formalabKeys.status,
    queryFn: fetchFormalabStatus,
    retry: false,
    refetchInterval: (query) => (query.state.data?.sync_in_progress ? SYNC_POLL_INTERVAL_MS : false),
  })
}
