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

/**
 * The lines (and every header field) change only while the order is a draft
 * or in validation (D-10); past approval the server keeps only the service
 * fields open (spec 0259), never the lines.
 */
export function canEditOrderLines(order: PurchaseOrder): boolean {
  return EDITABLE_STATUSES.includes(order.status) && order.abilities.update
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
 * `ResourcePermissions` shape the metadata-driven fields read. The server
 * matrix already follows the status (spec 0259: service fields only past
 * approval, nothing once cancelled); without `update` every field is locked.
 */
export function toOrderResourcePermissions(order: PurchaseOrder): ResourcePermissions {
  const locked = !order.abilities.update
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
