import { useResourcePermissions } from '@/features/authorization/permissions'

/** The record action the server derives from `quotes.viewCosts` (spec 0412). */
const VIEW_COSTS_ACTION = 'view_costs'

/**
 * Whether the actor sees the costs and margins of the offer in context (spec
 * 0412, D-5): the `view_costs` action of the enclosing
 * `ResourcePermissionsProvider` (the record's own on the detail, the
 * `GET /meta/quotes` one on create, where the permission alone decides).
 */
export function useQuoteCostsVisible(): boolean {
  return useResourcePermissions().canAction(VIEW_COSTS_ACTION)
}
