import { useState } from 'react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchCoverage } from '@/features/user-skills/api'
import { userSkillKeys } from '@/features/user-skills/query-keys'
import type { Coverage, CoverageParams, CoverageStatus } from '@/features/user-skills/types'

/** Filters and data of the "Copertura" tab: status, business function and skill. */
export function useUserSkillCoverage() {
  const [status, setStatus] = useState<CoverageStatus | null>(null)
  const [businessFunctionId, setBusinessFunctionId] = useState<number | null>(null)
  const [skillId, setSkillId] = useState<number | null>(null)

  const params: CoverageParams = {
    ...(status === null ? {} : { status }),
    ...(businessFunctionId === null ? {} : { business_function_id: businessFunctionId }),
    ...(skillId === null ? {} : { skill_id: skillId }),
  }

  const query = useQuery<Coverage, AxiosError>({
    queryKey: userSkillKeys.coverage(params),
    queryFn: () => fetchCoverage(params),
    placeholderData: keepPreviousData,
  })

  return { status, setStatus, businessFunctionId, setBusinessFunctionId, skillId, setSkillId, query }
}
