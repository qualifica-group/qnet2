import {
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { getInstallment, installmentKeys, updateInstallment } from '@/features/invoice-installments/api'
import type { InstallmentDetail, InstallmentUpdatePayload } from '@/features/invoice-installments/types'

/** One installment for the edit dialog; always refetched on open (a collection may have landed meanwhile). */
export function useInstallment(id: number | null): UseQueryResult<InstallmentDetail, AxiosError> {
  return useQuery<InstallmentDetail, AxiosError>({
    queryKey: installmentKeys.detail(id ?? 0),
    queryFn: () => getInstallment(id as number),
    enabled: id !== null,
    gcTime: 0,
  })
}

export function useUpdateInstallment(
  id: number,
): UseMutationResult<InstallmentDetail, AxiosError, InstallmentUpdatePayload> {
  const queryClient = useQueryClient()
  return useMutation<InstallmentDetail, AxiosError, InstallmentUpdatePayload>({
    mutationFn: (payload) => updateInstallment(id, payload),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: installmentKeys.all }),
  })
}
