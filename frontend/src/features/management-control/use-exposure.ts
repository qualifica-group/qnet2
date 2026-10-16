import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchExposure, managementControlKeys, toExposureParams } from '@/features/management-control/api'
import type { Exposure, ExposureFilters } from '@/features/management-control/exposure-types'

/** Schedule and exposure for the given filters; refetches when any filter changes. */
export function useExposure(filters: ExposureFilters) {
  const params = useMemo(() => toExposureParams(filters), [filters])

  return useQuery<Exposure, AxiosError>({
    queryKey: managementControlKeys.exposure(params),
    queryFn: () => fetchExposure(params),
  })
}
