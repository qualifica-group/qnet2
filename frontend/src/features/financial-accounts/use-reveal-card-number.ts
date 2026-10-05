import { useMutation } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { revealCardNumber } from '@/features/financial-accounts/api'

/**
 * Reveals the full card number on explicit user action. A mutation (not a
 * query): every reveal is audited server-side, so it must never be cached,
 * prefetched or refetched in the background.
 */
export function useRevealCardNumber(accountId: number) {
  return useMutation<string, AxiosError>({
    mutationFn: () => revealCardNumber(accountId),
  })
}
