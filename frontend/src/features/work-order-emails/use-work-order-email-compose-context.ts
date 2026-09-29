import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import {
  fetchWorkOrderEmailComposeContext,
  workOrderEmailComposeContextQueryKey,
} from '@/features/work-order-emails/api'
import type { ComposeContext } from '@/features/work-order-emails/types'

/**
 * Sender identity, recipient/document pools, quote-PDF availability and the
 * attachment size cap (D-5..D-7) — fetched once per composer session
 * (`enabled` gates it to when the dialog is open) and reused by every section
 * of the composer, so the picker/import flows never re-derive visibility
 * scoping on their own.
 */
export function useWorkOrderEmailComposeContext(workOrderId: number, enabled: boolean) {
  return useQuery<ComposeContext, AxiosError>({
    queryKey: workOrderEmailComposeContextQueryKey(workOrderId),
    queryFn: () => fetchWorkOrderEmailComposeContext(workOrderId),
    enabled,
    staleTime: 60_000,
  })
}
