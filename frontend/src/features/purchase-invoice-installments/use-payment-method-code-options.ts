import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchForSelect } from '@/features/for-select/api'
import type { ForSelectItem } from '@/features/for-select/types'
import { PAYMENT_METHODS_FOR_SELECT_RESOURCE } from '@/features/payment-methods/for-select-api'

/** Page size of the one-shot options fetch (the for-select server cap). */
const PAYMENT_METHOD_OPTIONS_LIMIT = 100

/** Payment methods for-select item; `meta.payment_method_code` is the fiscal code the installment stores. */
interface PaymentMethodSelectItem extends ForSelectItem {
  meta?: { payment_days?: number; payment_method_code?: string | null }
}

export interface PaymentMethodCodeOption {
  /** Fiscal code stored on the installment (`payment_method_code`). */
  code: string
  label: string
}

const paymentMethodCodeOptionsKey = ['purchase-installment-lookups', 'payment-method-codes'] as const

async function fetchPaymentMethodCodeOptions(): Promise<PaymentMethodCodeOption[]> {
  const { items } = await fetchForSelect<PaymentMethodSelectItem>(PAYMENT_METHODS_FOR_SELECT_RESOURCE, {
    limit: PAYMENT_METHOD_OPTIONS_LIMIT,
    includeTotal: false,
  })
  const seen = new Set<string>()
  const options: PaymentMethodCodeOption[] = []
  for (const item of items) {
    const code = item.meta?.payment_method_code
    if (code && !seen.has(code)) {
      seen.add(code)
      options.push({ code, label: `${item.label} (${code})` })
    }
  }
  return options
}

/** Active payment methods that carry a fiscal code, as options of the installment method select. */
export function usePaymentMethodCodeOptions(enabled: boolean) {
  return useQuery<PaymentMethodCodeOption[], AxiosError>({
    queryKey: paymentMethodCodeOptionsKey,
    queryFn: fetchPaymentMethodCodeOptions,
    enabled,
    staleTime: Infinity,
  })
}
