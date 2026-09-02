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
 *
 * `required` is the UNION of the two sources, never the permission alone: the
 * per-type required-ness of the card (an individual's name, a company's
 * company name) is validation-layer logic the authorization ceiling
 * deliberately does NOT carry — `personalDataFieldPermissions()` emits
 * `required: false` for every `personal_data.*` key — so reading only the
 * permission would strip the asterisk off the very fields the schema refuses
 * to save without. The permission can still ADD required-ness (a role matrix
 * marking an optional field mandatory); the fallback only applies where the
 * actor may actually type.
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
  const disabled = permission.disabled || !permission.editable
  return {
    visible: permission.visible,
    disabled,
    readOnly: permission.readonly,
    required: permission.required || (fallbackRequired && !disabled),
  }
}
