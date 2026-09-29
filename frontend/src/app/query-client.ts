import { QueryClient } from '@tanstack/react-query'
import { recordUnavailableReason } from '@/lib/record-unavailable-reason'

const MAX_QUERY_RETRIES = 1

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      // A 404/403 is the server's final answer: retrying only delays the
      // "record unavailable" state the screen shows for it.
      retry: (failureCount, error) =>
        recordUnavailableReason(error) === null && failureCount < MAX_QUERY_RETRIES,
      refetchOnWindowFocus: false,
    },
  },
})
