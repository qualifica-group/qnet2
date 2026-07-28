import type { PersonalDataFieldPermissionResolver } from '@/features/personal-data/types'

/** How one card field renders once its permission (spec 0008) is resolved. */
export interface FieldGate {
  visible: boolean
  disabled: boolean
  readOnly: boolean
  required: boolean
}

/**
 * Resolves one field's render gating. Without a resolver, every field stays
 * visible/editable and `required` falls back to the caller's own (schema-driven)
 * default, matching the ungated behaviour of the self-service profile (AC-013).
 */
export function resolveGate(
  fieldPermission: PersonalDataFieldPermissionResolver | undefined,
  key: string,
  fallbackRequired: boolean,
): FieldGate {
  if (!fieldPermission) {
    return { visible: true, disabled: false, readOnly: false, required: fallbackRequired }
  }
  const permission = fieldPermission(key)
  return {
    visible: permission.visible,
    disabled: permission.disabled || !permission.editable,
    readOnly: permission.readonly,
    required: permission.required,
  }
}
