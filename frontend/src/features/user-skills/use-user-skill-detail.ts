import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchUserSkill } from '@/features/user-skills/api'
import { userSkillKeys } from '@/features/user-skills/query-keys'
import type { UserSkill } from '@/features/user-skills/types'

/** One person skill (`GET /user-skills/{id}`); 404 = out of visibility, never retried. */
export function useUserSkillDetail(id: number | null) {
  return useQuery<UserSkill, AxiosError>({
    queryKey: userSkillKeys.detail(id ?? 0),
    queryFn: () => fetchUserSkill(id ?? 0),
    enabled: id !== null,
    retry: false,
  })
}
