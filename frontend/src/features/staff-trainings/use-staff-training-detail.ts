import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchStaffTraining } from '@/features/staff-trainings/api'
import { staffTrainingKeys } from '@/features/staff-trainings/query-keys'
import type { StaffTraining } from '@/features/staff-trainings/types'

/** One training (`GET /staff-trainings/{id}`); 404 = out of visibility, never retried. */
export function useStaffTrainingDetail(id: number | null) {
  return useQuery<StaffTraining, AxiosError>({
    queryKey: staffTrainingKeys.detail(id ?? 0),
    queryFn: () => fetchStaffTraining(id ?? 0),
    enabled: id !== null,
    retry: false,
  })
}
