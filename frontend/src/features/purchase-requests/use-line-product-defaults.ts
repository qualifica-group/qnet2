import { useCallback } from 'react'
import type { UseFormSetValue } from 'react-hook-form'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { fetchProduct, productDetailQueryKey } from '@/features/products/api'
import type { PurchaseRequestFormValues } from '@/features/purchase-requests/purchase-request-schema'

const PRODUCT_STALE_TIME_MS = 60_000
const WRITE_OPTIONS = { shouldDirty: true, shouldValidate: true } as const

/**
 * Proposes a line's values from the picked (or just quick-created) product
 * (spec 0208 D-13): description = name, U.m., price and VAT rate. They stay
 * editable; a product without price keeps the typed one.
 */
export function useLineProductDefaults(index: number, setValue: UseFormSetValue<PurchaseRequestFormValues>) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  return useCallback(
    async (productId: number) => {
      try {
        const product = await queryClient.fetchQuery({
          queryKey: productDetailQueryKey(productId),
          queryFn: () => fetchProduct(productId),
          staleTime: PRODUCT_STALE_TIME_MS,
        })
        setValue(`lines.${index}.description`, product.name, WRITE_OPTIONS)
        setValue(`lines.${index}.unit_of_measure_id`, product.unit_of_measure_id, WRITE_OPTIONS)
        if (product.price !== null) {
          setValue(`lines.${index}.unit_price`, Number(product.price), WRITE_OPTIONS)
        }
        setValue(`lines.${index}.vat_rate_id`, product.vat_rate_id, WRITE_OPTIONS)
        setValue(`lines.${index}.vat_rate_percent`, product.vat_rate ? Number(product.vat_rate.rate) : null)
      } catch {
        toast.error(t('purchaseRequests.lines.productLoadError'))
      }
    },
    [index, queryClient, setValue, t],
  )
}
