import type {
  EditablePurchaseInstallmentField,
  PurchaseInstallmentDetail,
} from '@/features/purchase-invoice-installments/types'

export interface PurchaseFieldAccess {
  visible: boolean
  editable: boolean
}

/**
 * Whether the actor sees / can edit one field: the per-field permission wins;
 * a field the server did not describe falls back to the row-level `update`
 * ability (visible, editable only with it).
 */
export function resolvePurchaseFieldAccess(
  detail: PurchaseInstallmentDetail,
  field: EditablePurchaseInstallmentField,
): PurchaseFieldAccess {
  const permission = detail.field_permissions[field]
  if (!permission) {
    return { visible: true, editable: detail.abilities.update }
  }
  return {
    visible: permission.visible,
    editable: permission.visible && permission.editable && detail.abilities.update,
  }
}
