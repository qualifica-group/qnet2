import { useMemo } from 'react'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { PRODUCTS_FOR_SELECT_RESOURCE } from '@/features/products/for-select-api'
import { productSuppliersOf, type ProductSupplierOption } from '@/features/products/product-suppliers'
import type { QuoteProductForSelectItem } from '@/features/quotes/quote-product-select'
import type { WorkOrderCostRowValues } from '@/features/work-order-costs/work-order-costs-schema'

/**
 * The suppliers a cost row may choose from (spec 0320 D-9): the list the pick
 * left on the row, or, for a persisted row that carries none, the product's
 * for-select entry looked up by id (same ids-keyed query the pickers use).
 * Empty = the product has no suppliers: the free supplier picker applies.
 */
export function useCostRowSuppliers(row: WorkOrderCostRowValues): ProductSupplierOption[] {
  const known = row.display.product_suppliers
  const ids = useMemo(() => (known === undefined && row.product_id !== null ? [row.product_id] : []), [known, row.product_id])
  const fetched = useForSelectLabels({ resource: PRODUCTS_FOR_SELECT_RESOURCE, ids })

  if (known !== undefined) {
    return known
  }
  return row.product_id === null ? [] : productSuppliersOf((fetched.get(row.product_id) as QuoteProductForSelectItem | undefined)?.meta)
}
