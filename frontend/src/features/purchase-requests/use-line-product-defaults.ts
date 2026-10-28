import { useCallback } from 'react'
import type { UseFormSetValue } from 'react-hook-form'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { fetchProduct, productDetailQueryKey } from '@/features/products/api'
import { defaultProductSupplierOf, isProductSupplier, productCostFor } from '@/features/products/product-suppliers'
import type { PurchaseRequestFormValues } from '@/features/purchase-requests/purchase-request-schema'

const PRODUCT_STALE_TIME_MS = 60_000
const WRITE_OPTIONS = { shouldDirty: true, shouldValidate: true } as const

function useCachedProduct() {
  const queryClient = useQueryClient()

  return useCallback(
    (productId: number) =>
      queryClient.fetchQuery({
        queryKey: productDetailQueryKey(productId),
        queryFn: () => fetchProduct(productId),
        staleTime: PRODUCT_STALE_TIME_MS,
      }),
    [queryClient],
  )
}

/**
 * Proposes a line's values from the picked (or just quick-created) product
 * (spec 0208 D-13, 0209 D-8): description = name, U.m., price and VAT rate.
 * A product with suppliers (spec 0320 D-10) proposes its default supplier and
 * that supplier's cost as price; without suppliers the price stays the
 * product's own. They stay editable; a missing value keeps what is typed.
 */
export function useLineProductDefaults(index: number, setValue: UseFormSetValue<PurchaseRequestFormValues>) {
  const { t } = useTranslation()
  const loadProduct = useCachedProduct()

  return useCallback(
    async (productId: number) => {
      try {
        const product = await loadProduct(productId)
        const supplier = defaultProductSupplierOf(product)
        setValue(`lines.${index}.description`, product.name, WRITE_OPTIONS)
        setValue(`lines.${index}.unit_of_measure_id`, product.unit_of_measure_id, WRITE_OPTIONS)
        const price = supplier ? Number(supplier.cost) : product.price
        if (price !== null) {
          setValue(`lines.${index}.unit_price`, Number(price), WRITE_OPTIONS)
        }
        setValue(`lines.${index}.vat_rate_id`, product.vat_rate_id, WRITE_OPTIONS)
        setValue(`lines.${index}.vat_rate_percent`, product.vat_rate ? Number(product.vat_rate.rate) : null)
        if (supplier) {
          setValue(`lines.${index}.supplier_id`, supplier.supplier_id, WRITE_OPTIONS)
          setValue(`lines.${index}.supplier_ref`, { id: supplier.supplier_id, name: supplier.name }, WRITE_OPTIONS)
        }
      } catch {
        toast.error(t('purchaseRequests.lines.productLoadError'))
      }
    },
    [index, loadProduct, setValue, t],
  )
}

/**
 * Re-prices a line when its supplier changes (spec 0320 D-10): only a supplier
 * of the line's product brings its own cost; any other supplier leaves the
 * price as typed.
 */
export function useLineSupplierCost(index: number, setValue: UseFormSetValue<PurchaseRequestFormValues>) {
  const { t } = useTranslation()
  const loadProduct = useCachedProduct()

  return useCallback(
    async (productId: number | null, supplierId: number | null) => {
      if (productId === null || supplierId === null) {
        return
      }
      try {
        const product = await loadProduct(productId)
        const cost = isProductSupplier(product, supplierId) ? productCostFor(product, supplierId) : null
        if (cost !== null) {
          setValue(`lines.${index}.unit_price`, cost, WRITE_OPTIONS)
        }
      } catch {
        toast.error(t('purchaseRequests.lines.productLoadError'))
      }
    },
    [index, loadProduct, setValue, t],
  )
}
