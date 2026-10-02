import { DEFAULT_LINE_QUANTITY, lineValuesFromProduct } from '@/features/quotes/use-quote-lines-field'
import type { QuoteProductForSelectItem } from '@/features/quotes/quote-product-select'
import type { WorkOrderCostRowValues } from '@/features/work-order-costs/work-order-costs-schema'

/**
 * The values a picked COST product precompiles on a row (D-8): `products.cost`
 * as unit price, the product's VAT rate and unit, quantity defaulting to 1
 * only when still empty. The supplier is never touched.
 */
export function costRowPatchFromProduct(
  item: QuoteProductForSelectItem,
  row: WorkOrderCostRowValues,
): Partial<WorkOrderCostRowValues> {
  const values = lineValuesFromProduct(item, 'cost')
  return {
    product_id: values.product_id,
    unit_price: values.unit_price,
    vat_rate_id: values.vat_rate_id,
    quantity: row.quantity ?? DEFAULT_LINE_QUANTITY,
    display: {
      ...row.display,
      product_code: item.meta.code,
      product_name: item.label,
      product_category: item.subtitle ?? null,
      unit_symbol: item.meta.unit_of_measure?.symbol ?? null,
      vat_name: item.meta.vat_rate_name,
      vat_percent: item.meta.vat_rate === null ? null : Number(item.meta.vat_rate),
    },
  }
}
