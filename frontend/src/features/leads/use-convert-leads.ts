import { useMutation } from '@tanstack/react-query'
import { convertLeadsToOpportunities } from '@/features/leads/api'
import type { ConvertLeadsPayload, ConvertLeadsResult } from '@/features/leads/types'

interface UseConvertLeadsOptions {
  /** Ran after the batch converted; the caller drives its own refresh/toast (spec 0071). */
  onSuccess?: (result: ConvertLeadsResult) => void
}

/**
 * Thin `useMutation` wrapper over `convertLeadsToOpportunities` (spec 0071),
 * mirroring `useAssignOperators`: it neither invalidates a query nor toasts,
 * so the caller owns its own post-success refresh and feedback.
 */
export function useConvertLeads({ onSuccess }: UseConvertLeadsOptions = {}) {
  return useMutation({
    mutationFn: (payload: ConvertLeadsPayload) => convertLeadsToOpportunities(payload),
    onSuccess,
  })
}
