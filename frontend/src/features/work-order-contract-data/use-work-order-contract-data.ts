import { useMemo } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import type { ApiErrorResponse } from '@/api/types'
import { useForSelect, flattenForSelectPages } from '@/features/for-select/use-for-select'
import type { ForSelectItem } from '@/features/for-select/types'
import { fetchWorkOrderContractData, updateContractDataLine } from '@/features/work-order-contract-data/api'
import { workOrderContractDataKeys } from '@/features/work-order-contract-data/query-keys'
import type {
  ContractDataLine,
  ContractPaymentStatusRef,
  PaymentStatusOption,
  UpdateContractLinePayload,
  WorkOrderContractData,
} from '@/features/work-order-contract-data/types'

/** Resource segment of the payment statuses for-select. */
export const PAYMENT_STATUSES_RESOURCE = 'work-order-payment-statuses'

/** Flat for-select projection of a payment status (spec 0201): `label` carries the name. */
interface PaymentStatusForSelectItem extends ForSelectItem {
  color: string
  allows_delivery: boolean
}

export function useWorkOrderContractData(workOrderId: number) {
  return useQuery<WorkOrderContractData, AxiosError<ApiErrorResponse>>({
    queryKey: workOrderContractDataKeys.detail(workOrderId),
    queryFn: () => fetchWorkOrderContractData(workOrderId),
  })
}

interface UpdateLineVariables {
  quoteLineId: number
  payload: UpdateContractLinePayload
}

/**
 * The PATCH answers with the refreshed line: it replaces that line in the
 * cached payload, so the table updates with no refetch. Payment data never
 * enters the totals, so those stay as cached.
 */
export function useUpdateContractDataLine(workOrderId: number) {
  const queryClient = useQueryClient()
  return useMutation<ContractDataLine, AxiosError<ApiErrorResponse>, UpdateLineVariables>({
    mutationFn: ({ quoteLineId, payload }) => updateContractDataLine(workOrderId, quoteLineId, payload),
    onSuccess: (line) => {
      queryClient.setQueryData<WorkOrderContractData>(workOrderContractDataKeys.detail(workOrderId), (current) =>
        current
          ? { ...current, lines: current.lines.map((row) => (row.quote_line_id === line.quote_line_id ? line : row)) }
          : current,
      )
    },
  })
}

/**
 * Active payment statuses for the editor's select. The status already on the
 * line is appended when it is no longer active (it stays saveable unchanged).
 */
export function usePaymentStatusOptions(current: ContractPaymentStatusRef | null): PaymentStatusOption[] {
  const { data } = useForSelect({ resource: PAYMENT_STATUSES_RESOURCE, search: '' })

  return useMemo(() => {
    const items = flattenForSelectPages(data?.pages) as PaymentStatusForSelectItem[]
    const options: PaymentStatusOption[] = items.map((item) => ({
      id: item.id,
      name: item.label,
      color: item.color,
      allows_delivery: item.allows_delivery,
    }))
    if (current && !options.some((option) => option.id === current.id)) {
      return [...options, current]
    }
    return options
  }, [data, current])
}
