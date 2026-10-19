import { useState } from 'react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchCompliance } from '@/features/staff-trainings/api'
import { staffTrainingKeys } from '@/features/staff-trainings/query-keys'
import type { Compliance, ComplianceParams, ComplianceStatus } from '@/features/staff-trainings/types'

/** Filters and data of the "Situazione" tab: status, business function and course. */
export function useStaffTrainingCompliance() {
  const [status, setStatus] = useState<ComplianceStatus | null>(null)
  const [businessFunctionId, setBusinessFunctionId] = useState<number | null>(null)
  const [staffCourseId, setStaffCourseId] = useState<number | null>(null)

  const params: ComplianceParams = {
    ...(status === null ? {} : { status }),
    ...(businessFunctionId === null ? {} : { business_function_id: businessFunctionId }),
    ...(staffCourseId === null ? {} : { staff_course_id: staffCourseId }),
  }

  const query = useQuery<Compliance, AxiosError>({
    queryKey: staffTrainingKeys.compliance(params),
    queryFn: () => fetchCompliance(params),
    placeholderData: keepPreviousData,
  })

  return { status, setStatus, businessFunctionId, setBusinessFunctionId, staffCourseId, setStaffCourseId, query }
}
