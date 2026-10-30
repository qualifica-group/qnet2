import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchSupportRequestTopics } from '@/features/support-requests/api'
import { supportRequestKeys } from '@/features/support-requests/query-keys'
import type { SupportRequestTopic } from '@/features/support-requests/types'

/** Active problem types; fetched only while the dialog is open. */
export function useSupportRequestTopics(enabled: boolean) {
  return useQuery<SupportRequestTopic[], AxiosError>({
    queryKey: supportRequestKeys.topics,
    queryFn: fetchSupportRequestTopics,
    enabled,
  })
}
