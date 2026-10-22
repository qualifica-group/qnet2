import { useQuery } from '@tanstack/react-query'
import { getAbsenceSummary } from '@/features/absences/api'
import { absenceKeys } from '@/features/absences/query-keys'
import { useAbilities } from '@/features/auth/use-abilities'
import type { AbsenceSummary } from '@/features/absences/types'
import type { AxiosError } from 'axios'

const SUMMARY_STALE_MS = 60_000

/** Counters, next absences and who is out today; no request at all without `absence-requests.viewAny`. */
export function useAbsenceSummary() {
  const { can } = useAbilities()

  return useQuery<AbsenceSummary, AxiosError>({
    queryKey: absenceKeys.summary,
    queryFn: getAbsenceSummary,
    staleTime: SUMMARY_STALE_MS,
    enabled: can('absence-requests.viewAny'),
  })
}
