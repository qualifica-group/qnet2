import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { PurchaseOrder } from '@/features/purchase-orders/types'

export type OrderRelationFieldName =
  | 'supplier_id'
  | 'company_id'
  | 'company_site_id'
  | 'operational_site_id'
  | 'payment_method_id'
  | 'customer_id'
  | 'work_order_id'
  | 'quote_id'
  | 'opportunity_id'

export type OrderRefs = Record<OrderRelationFieldName, RelationFieldRef | null>

const EMPTY_REFS: OrderRefs = {
  supplier_id: null,
  company_id: null,
  company_site_id: null,
  operational_site_id: null,
  payment_method_id: null,
  customer_id: null,
  work_order_id: null,
  quote_id: null,
  opportunity_id: null,
}

/** Hydrated `{id, name}` projections of the loaded ODA, so each picker shows its label without a lookup. */
export function orderRefs(order: PurchaseOrder | undefined): OrderRefs {
  if (!order) {
    return EMPTY_REFS
  }
  return {
    supplier_id: order.supplier,
    company_id: order.company,
    company_site_id: order.company_site,
    operational_site_id: order.operational_site,
    payment_method_id: order.payment_method,
    customer_id: order.customer,
    work_order_id: order.work_order
      ? { id: order.work_order.id, name: [order.work_order.code, order.work_order.title].filter(Boolean).join(' - ') }
      : null,
    quote_id: order.quote,
    opportunity_id: order.opportunity,
  }
}
