import { useCallback } from 'react'
import type { UseFormSetValue } from 'react-hook-form'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { fetchProduct, productDetailQueryKey } from '@/features/products/api'
import { defaultProductSupplierOf, isProductSupplier, productCostFor } from '@/features/products/product-suppliers'
import type { PurchaseOrderFormValues } from '@/features/purchase-orders/purchase-order-schema'

const PRODUCT_STALE_TIME_MS = 60_000
const WRITE_OPTIONS = { shouldDirty: true, shouldValidate: true } as const

/**
 * Proposes a line's values from the picked (or just quick-created) product
 * (spec 0210 D-13): description = name, U.m., purchase price and VAT rate.
 * The price (spec 0320 D-10) is the cost of the order's supplier when it is
 * one of the product's suppliers, else of the product's default supplier,
 * else the product's standard cost. They stay editable; no cost at all keeps
 * the price already typed. Changing the order's supplier never re-prices
 * lines already entered. The cost type follows the product's default
 * (spec 0251 D-4) only while the line has none: a chosen type is never overwritten.
 */
export function useOrderLineProductDefaults(
  index: number,
  setValue: UseFormSetValue<PurchaseOrderFormValues>,
  currentCostTypeId: number | null,
  orderSupplierId: number | null,
) {
  const { t, i18n } = useTranslation()
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
        const supplierId = isProductSupplier(product, orderSupplierId)
          ? orderSupplierId
          : (defaultProductSupplierOf(product)?.supplier_id ?? null)
        const cost = productCostFor(product, supplierId)
        if (cost !== null) {
          setValue(`lines.${index}.unit_price`, cost, WRITE_OPTIONS)
        }
        if (currentCostTypeId === null && product.cost_type_id != null) {
          setValue(`lines.${index}.cost_type_id`, product.cost_type_id, WRITE_OPTIONS)
        }
        setValue(`lines.${index}.vat_rate_id`, product.vat_rate_id, WRITE_OPTIONS)
        setValue(`lines.${index}.vat_rate_percent`, product.vat_rate ? Number(product.vat_rate.rate) : null)
      } catch (error) {
        toast.error(apiErrorMessage(error, t('purchaseOrderForm.lines.productLoadError'), i18n))
      }
    },
    [index, currentCostTypeId, orderSupplierId, queryClient, setValue, t, i18n],
  )
}
