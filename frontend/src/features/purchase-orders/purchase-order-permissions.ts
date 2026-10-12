import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'
import {
  EDITABLE_STATUSES,
  type OrderFieldPermission,
  type PurchaseOrder,
} from '@/features/purchase-orders/types'

/** Field keys of the header, equal to the write-payload names (spec 0210 field catalogue). */
export const ORDER_FIELD_KEYS = [
  'supplier_id',
  'company_id',
  'company_site_id',
  'operational_site_id',
  'order_date',
  'description',
  'general_notes',
  'payment_method_id',
  'is_internal_user',
  'customer_id',
  'work_order_id',
  'quote_id',
  'opportunity_id',
  'sector_ids',
  'sector_notes',
  'copies',
] as const

const OPEN_FIELD: OrderFieldPermission = { visible: true, editable: true, required: false }

/** An order outside draft/in_validation (D-10), or one the actor cannot update, is read-only as a whole. */
export function isOrderReadOnly(order: PurchaseOrder): boolean {
  return !EDITABLE_STATUSES.includes(order.status) || !order.abilities.update
}

function toFieldPermission(permission: OrderFieldPermission, locked: boolean): FieldPermission {
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
 * `ResourcePermissions` shape the metadata-driven fields read; a read-only
 * order locks every known field whatever the role matrix says.
 */
export function toOrderResourcePermissions(order: PurchaseOrder): ResourcePermissions {
  const locked = isOrderReadOnly(order)
  const fields = Object.fromEntries(
    ORDER_FIELD_KEYS.map((key) => [key, toFieldPermission(order.field_permissions[key] ?? OPEN_FIELD, locked)]),
  )
  return {
    resource: {
      view: true,
      create: false,
      update: !locked,
      delete: order.abilities.delete,
      export: false,
      import: false,
    },
    fields,
    actions: {},
  }
}
