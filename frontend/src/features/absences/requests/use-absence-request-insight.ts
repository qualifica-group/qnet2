import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { getAbsenceRequestInsight } from '@/features/absences/api'
import { absenceKeys } from '@/features/absences/query-keys'
import type { AbsenceRequestInsight } from '@/features/absences/types'

/** Decision context of a request (`GET /absence-requests/{id}/insight`); only fetched for who decides. */
export function useAbsenceRequestInsight(id: number, enabled: boolean) {
  return useQuery<AbsenceRequestInsight, AxiosError>({
    queryKey: absenceKeys.insight(id),
    queryFn: () => getAbsenceRequestInsight(id),
    enabled,
    retry: false,
  })
}
