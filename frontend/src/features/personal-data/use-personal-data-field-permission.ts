import { useResourcePermissions } from '@/features/authorization/permissions'
import type { PersonalDataFieldPermission } from '@/features/personal-data/types'

/**
 * Adapts the resolved authorization metadata to the personal-data domain's
 * own gating shape (spec 0008 D3): the shared PersonalDataCardForm/
 * ContactsManager/AddressesManager stay decoupled from `@/features/authorization`.
 */
export function usePersonalDataFieldPermission(): (key: string) => PersonalDataFieldPermission {
  const { field: fieldPermission } = useResourcePermissions()
  return (key: string) => {
    const permission = fieldPermission(key)
    return {
      visible: permission.visible,
      editable: permission.editable,
      required: permission.required,
      disabled: permission.disabled,
      readonly: permission.readonly,
    }
  }
}
