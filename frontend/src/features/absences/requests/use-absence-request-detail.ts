import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchAbsenceRequest } from '@/features/absences/api'
import { absenceKeys } from '@/features/absences/query-keys'
import type { AbsenceRequest } from '@/features/absences/types'

/** One request (`GET /absence-requests/{id}`); 404 = not visible, never retried. */
export function useAbsenceRequestDetail(id: number | null) {
  return useQuery<AbsenceRequest, AxiosError>({
    queryKey: absenceKeys.detail(id ?? 0),
    queryFn: () => fetchAbsenceRequest(id ?? 0),
    enabled: id !== null,
    retry: false,
  })
}
