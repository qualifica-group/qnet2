import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  createEmploymentContract,
  deleteEmploymentContract,
  employmentContractsQueryKey,
  fetchEmploymentContracts,
} from '@/features/users/contract-history-api'
import type { CreateEmploymentContractPayload } from '@/features/users/contract-history-types'
import { userDetailQueryKey } from '@/features/users/api'

export function useEmploymentContracts(userId: number) {
  return useQuery({
    queryKey: employmentContractsQueryKey(userId),
    queryFn: () => fetchEmploymentContracts(userId),
  })
}

/** The in-force condition changes with every write: refresh the list AND the user's detail (spec 0272 D-2). */
function useRefreshAfterWrite(userId: number) {
  const queryClient = useQueryClient()
  return async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: employmentContractsQueryKey(userId) }),
      queryClient.invalidateQueries({ queryKey: userDetailQueryKey(userId) }),
    ])
  }
}

export function useCreateEmploymentContract(userId: number) {
  const refresh = useRefreshAfterWrite(userId)
  return useMutation({
    mutationFn: (payload: CreateEmploymentContractPayload) => createEmploymentContract(userId, payload),
    onSuccess: refresh,
  })
}

export function useDeleteEmploymentContract(userId: number) {
  const refresh = useRefreshAfterWrite(userId)
  return useMutation({
    mutationFn: (contractId: number) => deleteEmploymentContract(userId, contractId),
    onSuccess: refresh,
  })
}
