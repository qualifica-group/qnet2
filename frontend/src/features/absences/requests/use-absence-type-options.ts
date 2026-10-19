import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchAbsenceTypeOptions } from '@/features/absences/api'
import { absenceKeys } from '@/features/absences/query-keys'
import type { AbsenceTypeOption } from '@/features/absences/types'

/** Active absence types for the request form. */
export function useAbsenceTypeOptions() {
  return useQuery<AbsenceTypeOption[], AxiosError>({
    queryKey: absenceKeys.typeOptions,
    queryFn: fetchAbsenceTypeOptions,
    staleTime: 5 * 60 * 1000,
  })
}
