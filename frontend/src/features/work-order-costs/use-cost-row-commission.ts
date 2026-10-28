import { useSupplierCommissions } from '@/features/products/use-supplier-commissions'
import { useCostRowSuppliers } from '@/features/work-order-costs/use-cost-row-suppliers'
import type { WorkOrderCostRowValues } from '@/features/work-order-costs/work-order-costs-schema'

/**
 * The commission lookup of a real-cost row (spec 0320 rev.3 D-23): over the
 * suppliers of the row's product, one cached request per product. A product
 * without suppliers has nothing to look up (the free supplier picker applies).
 */
export function useCostRowCommission(row: WorkOrderCostRowValues) {
  const suppliers = useCostRowSuppliers(row)

  return useSupplierCommissions({
    productId: row.product_id,
    categoryId: null,
    supplierIds: suppliers.map((supplier) => supplier.supplier_id),
  })
}
