import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import {
  fetchOutboundEmailComposeContext,
  outboundEmailComposeContextQueryKey,
} from '@/features/outbound-emails/api'
import type { EmailOwnerRef, ComposeContext } from '@/features/outbound-emails/types'

/**
 * Sender identity, recipient/document pools, quote-PDF availability and the
 * attachment size cap (D-5..D-7) — fetched once per composer session
 * (`enabled` gates it to when the dialog is open) and reused by every section
 * of the composer, so the picker/import flows never re-derive visibility
 * scoping on their own.
 */
export function useOutboundEmailComposeContext(owner: EmailOwnerRef, enabled: boolean) {
  return useQuery<ComposeContext, AxiosError>({
    queryKey: outboundEmailComposeContextQueryKey(owner),
    queryFn: () => fetchOutboundEmailComposeContext(owner),
    enabled,
    staleTime: 60_000,
  })
}
