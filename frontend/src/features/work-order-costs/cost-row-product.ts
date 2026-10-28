import { defaultProductSupplierOf, hasProductSuppliers, productSuppliersOf } from '@/features/products/product-suppliers'
import { DEFAULT_LINE_QUANTITY, lineValuesFromProduct } from '@/features/quotes/use-quote-lines-field'
import { productCostTypeOf } from '@/features/products/for-select-api'
import type { QuoteProductForSelectItem } from '@/features/quotes/quote-product-select'
import type { WorkOrderCostRowValues } from '@/features/work-order-costs/work-order-costs-schema'

/**
 * The values a picked COST product precompiles on a row: the product's VAT
 * rate and unit, quantity defaulting to 1 only when still empty. A product
 * with suppliers sets the default supplier and its cost as unit price (spec
 * 0320 D-9, amends 0190 D-8); one without keeps `products.cost` as unit price
 * and leaves the row's supplier untouched. The product's supplier options ride
 * the row's display for the select. The cost type follows the product's
 * default (spec 0251 D-3) when it has one; a product without one keeps the
 * type already on the row.
 */
export function costRowPatchFromProduct(
  item: QuoteProductForSelectItem,
  row: WorkOrderCostRowValues,
): Partial<WorkOrderCostRowValues> {
  const values = lineValuesFromProduct(item, 'cost')
  const costType = productCostTypeOf(item)
  const defaultSupplier = hasProductSuppliers(item.meta) ? defaultProductSupplierOf(item.meta) : null
  return {
    product_id: values.product_id,
    unit_price: values.unit_price,
    ...(defaultSupplier === null ? {} : { supplier_id: defaultSupplier.supplier_id }),
    vat_rate_id: values.vat_rate_id,
    quantity: row.quantity ?? DEFAULT_LINE_QUANTITY,
    ...(costType === null ? {} : { cost_type_id: costType.id }),
    display: {
      ...row.display,
      ...(defaultSupplier === null ? {} : { supplier_name: defaultSupplier.name }),
      product_suppliers: productSuppliersOf(item.meta),
      ...(costType === null ? {} : { cost_type_name: costType.name, cost_type_color: costType.color }),
      product_code: item.meta.code,
      product_name: item.label,
      product_category: item.subtitle ?? null,
      unit_symbol: item.meta.unit_of_measure?.symbol ?? null,
      vat_name: item.meta.vat_rate_name,
      vat_percent: item.meta.vat_rate === null ? null : Number(item.meta.vat_rate),
    },
  }
}
