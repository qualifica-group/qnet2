import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'
import type { PurchaseRequest, RequestFieldPermission } from '@/features/purchase-requests/types'

/** Field keys of the header and footer, equal to the write-payload names (spec 0208 `FieldDefinition`). */
export const REQUEST_FIELD_KEYS = [
  'subject',
  'requested_at',
  'priority',
  'requester_id',
  'function_manager_id',
  'customer_id',
  'supplier_id',
  'work_order_id',
  'company_id',
  'company_site_id',
  'operational_site_id',
  'business_function_id',
  'notes',
  'delivery_terms',
  'procurement_plan',
  'technical_requirements',
  'special_conditions',
] as const

const OPEN_FIELD: RequestFieldPermission = { visible: true, editable: true, required: false }

function toFieldPermission(permission: RequestFieldPermission, locked: boolean): FieldPermission {
  const editable = permission.editable && !locked
  return {
    visible: permission.visible,
    hidden: !permission.visible,
    editable,
    readonly: permission.visible && !editable,
    required: permission.required,
    disabled: false,
  }
}

/**
 * Adapts the `field_permissions` of the detail payload to the shared
 * `ResourcePermissions` shape the metadata-driven fields read. A closed RDA
 * is read-only as a whole (D-10), and so is one the actor cannot update: every
 * known field is locked, whatever the role matrix says.
 */
export function toResourcePermissions(request: PurchaseRequest): ResourcePermissions {
  const locked = request.status === 'closed' || !request.abilities.update
  const fields = Object.fromEntries(
    REQUEST_FIELD_KEYS.map((key) => [key, toFieldPermission(request.field_permissions[key] ?? OPEN_FIELD, locked)]),
  )
  return {
    resource: {
      view: true,
      create: false,
      update: !locked,
      delete: request.abilities.delete,
      export: false,
      import: false,
    },
    fields,
    actions: {},
  }
}
