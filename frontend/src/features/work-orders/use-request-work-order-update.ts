import { useMutation } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import type { ApiErrorResponse } from '@/api/types'
import { requestWorkOrderUpdate } from '@/features/work-orders/api'
import type { RequestWorkOrderUpdatePayload } from '@/features/work-orders/types'

/** Nothing is invalidated: the request writes nothing on the work order (spec 0352 D-6). */
export function useRequestWorkOrderUpdate(workOrderId: number) {
  return useMutation<void, AxiosError<ApiErrorResponse>, RequestWorkOrderUpdatePayload>({
    mutationFn: (payload) => requestWorkOrderUpdate(workOrderId, payload),
  })
}
