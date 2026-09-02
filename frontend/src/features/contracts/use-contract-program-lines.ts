import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import { fetchContractProgrammableLines } from '@/features/contracts/api'
import type { ContractProgrammableLine } from '@/features/contracts/types'

/** Query key of one contract's programmable lines (spec 0095 D-6). */
export function contractProgrammableLinesQueryKey(contractId: number) {
  return ['contracts', 'programmable-lines', contractId] as const
}

/**
 * Fetches `GET /contracts/{id}/programmable-lines`, only while the
 * `ContractProgramDialog` is open (`enabled`): the dialog is a rare action,
 * not part of the detail's own load, so there is nothing to prefetch.
 */
export function useContractProgrammableLines(
  contractId: number,
  enabled: boolean,
): UseQueryResult<ContractProgrammableLine[]> {
  return useQuery({
    queryKey: contractProgrammableLinesQueryKey(contractId),
    queryFn: () => fetchContractProgrammableLines(contractId),
    enabled,
  })
}
