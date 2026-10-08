import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the work-order-payment-statuses for-select endpoint. */
export const WORK_ORDER_PAYMENT_STATUSES_FOR_SELECT_RESOURCE = 'work-order-payment-statuses'

/**
 * Fetches a page of work order payment status options from
 * `GET /api/work-order-payment-statuses/for-select`. Thin wrapper over the generic
 * for-select fetcher, bound to the `work-order-payment-statuses` resource. Only active
 * statuses are returned, ordered `sort_order,name,id` (BR-5).
 */
export function fetchWorkOrderPaymentStatusesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(WORK_ORDER_PAYMENT_STATUSES_FOR_SELECT_RESOURCE, params)
}

interface UseWorkOrderPaymentStatusesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a work order payment status single-select: debounced server
 * search, offset pagination and `ids[]` hydration, bound to the
 * `work-order-payment-statuses` resource.
 */
export function useWorkOrderPaymentStatusesForSelect({
  search,
  ids,
  enabled,
}: UseWorkOrderPaymentStatusesForSelectOptions) {
  return useForSelect({
    resource: WORK_ORDER_PAYMENT_STATUSES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
