import type {
  EditableInstallmentField,
  InstallmentDetail,
} from '@/features/invoice-installments/types'

export interface InstallmentFieldAccess {
  visible: boolean
  editable: boolean
}

/**
 * Whether the actor sees / can edit one field: the per-field permission wins;
 * a field the server did not describe falls back to the row-level `update`
 * ability (visible, editable only with it).
 */
export function resolveFieldAccess(
  detail: InstallmentDetail,
  field: EditableInstallmentField,
): InstallmentFieldAccess {
  const permission = detail.field_permissions[field]
  if (!permission) {
    return { visible: true, editable: detail.abilities.update }
  }
  return {
    visible: permission.visible,
    editable: permission.visible && permission.editable && detail.abilities.update,
  }
}
