import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchAbsenceTypeOptions } from '@/features/absences/api'
import { absenceKeys } from '@/features/absences/query-keys'
import type { AbsenceTypeOption } from '@/features/absences/types'

/** Active absence types, plus the saved one in edit mode even when it is no longer active. */
export function useAbsenceTypeOptions(savedTypeId: number | null = null) {
  return useQuery<AbsenceTypeOption[], AxiosError>({
    queryKey: absenceKeys.typeOptions(savedTypeId),
    queryFn: () => fetchAbsenceTypeOptions(savedTypeId),
    staleTime: 5 * 60 * 1000,
  })
}
