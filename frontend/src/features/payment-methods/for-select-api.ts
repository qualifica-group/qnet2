import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the payment-methods for-select endpoint. */
export const PAYMENT_METHODS_FOR_SELECT_RESOURCE = 'payment-methods'

/**
 * Fetches a page of payment method options from
 * `GET /api/payment-methods/for-select`. Thin wrapper over the generic
 * for-select fetcher, bound to the `payment-methods` resource. Only active
 * methods are returned, ordered `sort_order,name,id`.
 */
export function fetchPaymentMethodsForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(PAYMENT_METHODS_FOR_SELECT_RESOURCE, params)
}

interface UsePaymentMethodsForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a payment method single-select: debounced server
 * search, offset pagination and `ids[]` hydration, bound to the
 * `payment-methods` resource. No consumer wires this in yet (spec 0068,
 * out of scope): kept ready for the first module that references
 * `payment_method_id`.
 */
export function usePaymentMethodsForSelect({
  search,
  ids,
  enabled,
}: UsePaymentMethodsForSelectOptions) {
  return useForSelect({
    resource: PAYMENT_METHODS_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
