import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchLedger, ledgerKeys, toLedgerParams } from '@/features/ledger/api'
import type { Ledger, LedgerFilters } from '@/features/ledger/types'

/** Ledger of one registry for the given filters; refetches when any filter changes. */
export function useLedger(registryId: number, filters: LedgerFilters) {
  const params = useMemo(() => toLedgerParams(filters), [filters])

  return useQuery<Ledger, AxiosError>({
    queryKey: ledgerKeys.detail(registryId, params),
    queryFn: () => fetchLedger(registryId, params),
  })
}
