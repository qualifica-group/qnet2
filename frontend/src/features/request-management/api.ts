import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  AssignRequestOperatorsPayload,
  AssignRequestOperatorsResult,
  CreateRequestPayload,
  ManagerLabels,
  RequestManagementProductCategory,
  RequestWorkPanel,
  RequestWorkPanelWithPermissions,
  TransferRequestsPayload,
  TransferRequestsResult,
  UpdateRequestWorkPayload,
} from '@/features/request-management/types'

/**
 * Creates a request (spec 0057, frozen contract): the record created IS an
 * Opportunity (D-1), gated server-side by this module's OWN
 * `request-management.create`. Unlike GET/PATCH, the 201 response carries no
 * `permissions` envelope sibling (the caller navigates away/closes the sheet
 * right after, never renders the work panel from this response).
 */
export async function createRequest(payload: CreateRequestPayload): Promise<RequestWorkPanel> {
  const { data } = await apiClient.post<ApiResponse<RequestWorkPanel>>('/request-management', payload)
  return data.data
}

/**
 * Fetches the work panel of a single opportunity together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchRequestWorkPanel(
  opportunityId: number,
): Promise<RequestWorkPanelWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<RequestWorkPanel, ResourcePermissions>
  >(`/request-management/${opportunityId}`)
  return { ...data.data, permissions: data.permissions }
}

/**
 * Partially updates the work panel (PATCH, sparse diff). Returns the updated
 * panel together with the actor's authorization metadata.
 */
export async function updateRequestWork(
  opportunityId: number,
  payload: UpdateRequestWorkPayload,
): Promise<RequestWorkPanelWithPermissions> {
  const { data } = await apiClient.patch<
    ApiResponseWithPermissions<RequestWorkPanel, ResourcePermissions>
  >(`/request-management/${opportunityId}`, payload)
  return { ...data.data, permissions: data.permissions }
}

/**
 * Deletes a request (user directive 2026-07-23). The record removed IS the
 * Opportunity (D-1); the endpoint is gated by this module's OWN
 * `request-management.delete` plus its D-3 scope, never `opportunities.*`.
 */
export async function deleteRequest(opportunityId: number): Promise<void> {
  await apiClient.delete(`/request-management/${opportunityId}`)
}

/**
 * Bulk operator assignment (user directive 2026-07-23, "come nei lead"):
 * assigns `operational_site_id` to every request in `request_ids`, plus
 * either a single `operator_id` (mode `'single'`) or a load-balanced split
 * across the Sede's operators (mode `'balanced'`). Returns how many requests
 * were actually written — ids outside the actor's scope are skipped.
 */
export async function assignRequestOperators(
  payload: AssignRequestOperatorsPayload,
): Promise<AssignRequestOperatorsResult> {
  const { data } = await apiClient.post<ApiResponse<AssignRequestOperatorsResult>>(
    '/request-management/assign-operators',
    payload,
  )
  return data.data
}

/**
 * Contact transfer to another operational Sede + Operatore (spec 0079): the
 * one endpoint behind both the row action and the bulk action, invoked with a
 * single-element `request_ids` for the former. Returns how many requests were
 * actually written — ids outside the actor's scope are skipped, same D-3 rule
 * as `assignRequestOperators`.
 */
export async function transferRequests(
  payload: TransferRequestsPayload,
): Promise<TransferRequestsResult> {
  const { data } = await apiClient.post<ApiResponse<TransferRequestsResult>>(
    '/request-management/transfer',
    payload,
  )
  return data.data
}

/**
 * Fetches the Product Category tab strip (spec 0064): only categories with at
 * least one request in the actor's own scope (`request-management.viewAny`),
 * ordered by name.
 */
export async function fetchRequestManagementCategories(): Promise<RequestManagementProductCategory[]> {
  const { data } = await apiClient.get<ApiResponse<{ categories: RequestManagementProductCategory[] }>>(
    '/request-management/product-categories',
  )
  return data.data.categories
}

/**
 * Resolves a Product Category's effective G.A. labels (spec 0080, frozen
 * contract owned by `features/product-categories`). Called directly here,
 * not imported from that feature (out of this module's ownership): the
 * create form has no persisted request yet to carry its own `manager_labels`
 * (unlike the work panel, whose `fetchRequestWorkPanel` already resolves it
 * per-request), so it relabels the GA2 "Operatore" from the active category
 * tab instead (`useActiveCategoryManagerLabels`).
 */
export async function fetchCategoryManagerLabels(categoryId: number): Promise<ManagerLabels> {
  const { data } = await apiClient.get<ApiResponse<{ manager_labels: ManagerLabels }>>(
    `/product-categories/${categoryId}/effective-manager-labels`,
  )
  return data.data.manager_labels
}
