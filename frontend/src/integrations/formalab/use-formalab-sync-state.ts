import { useQuery, useQueryClient } from '@tanstack/react-query'
import { fetchFormalabSyncState } from '@/integrations/formalab/api'
import { formalabKeys } from '@/integrations/formalab/query-keys'
import type { FormalabSyncState } from '@/integrations/formalab/types'

/** While a sync runs its state is reread, so the page sees it end without a reload. */
export const SYNC_POLL_INTERVAL_MS = 5000

/**
 * Whether a catalogue sync is running, from the cheap `sync-state` endpoint.
 * When a running sync ends, the full status (counters, last run) is reread once.
 */
export function useFormalabSyncState() {
  const queryClient = useQueryClient()

  return useQuery({
    queryKey: formalabKeys.syncState,
    queryFn: async () => {
      const previous = queryClient.getQueryData<FormalabSyncState>(formalabKeys.syncState)
      const next = await fetchFormalabSyncState()
      if (previous?.sync_in_progress && !next.sync_in_progress) {
        void queryClient.invalidateQueries({ queryKey: formalabKeys.status })
      }
      return next
    },
    retry: false,
    refetchInterval: (query) => (query.state.data?.sync_in_progress ? SYNC_POLL_INTERVAL_MS : false),
  })
}
