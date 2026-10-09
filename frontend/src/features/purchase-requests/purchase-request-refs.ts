import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { RelationFieldName } from '@/features/purchase-requests/purchase-request-fields'
import type { PurchaseRequest } from '@/features/purchase-requests/types'

export type RequestRefs = Record<RelationFieldName, RelationFieldRef | null>

const EMPTY_REFS: RequestRefs = {
  requester_id: null,
  function_manager_id: null,
  customer_id: null,
  supplier_id: null,
  work_order_id: null,
  company_id: null,
  company_site_id: null,
  operational_site_id: null,
  business_function_id: null,
}

/**
 * Hydrated `{id, name}` projections of the loaded RDA, so each relation
 * picker shows its label before (or without) the for-select lookup.
 */
export function requestRefs(request: PurchaseRequest | undefined): RequestRefs {
  if (!request) {
    return EMPTY_REFS
  }
  return {
    requester_id: request.requester,
    function_manager_id: request.function_manager,
    customer_id: request.customer,
    supplier_id: request.supplier,
    work_order_id: request.work_order
      ? { id: request.work_order.id, name: [request.work_order.code, request.work_order.title].filter(Boolean).join(' - ') }
      : null,
    company_id: request.company,
    company_site_id: request.company_site,
    operational_site_id: request.operational_site,
    business_function_id: request.business_function,
  }
}
