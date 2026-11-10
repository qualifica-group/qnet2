import { useEffect } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { clearMaintenance } from '@/api/maintenance-state'
import { fetchAvailability } from '@/features/maintenance/api'
import { maintenanceKeys } from '@/features/maintenance/query-keys'

const AVAILABILITY_POLL_INTERVAL_MS = 15_000

/** Polls while the maintenance screen is up and closes it once the API is back. */
export function useMaintenanceRecovery() {
  const queryClient = useQueryClient()
  const { isSuccess, isFetching, refetch } = useQuery({
    queryKey: maintenanceKeys.availability,
    queryFn: fetchAvailability,
    refetchInterval: AVAILABILITY_POLL_INTERVAL_MS,
    retry: false,
    // A success cached from the previous maintenance would close the next one at once.
    gcTime: 0,
  })

  useEffect(() => {
    if (isSuccess) {
      clearMaintenance()
      void queryClient.invalidateQueries()
    }
  }, [isSuccess, queryClient])

  return { isChecking: isFetching, checkNow: () => void refetch() }
}
