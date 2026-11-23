import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'

/** Spec 0404 E: the two flags the Offerte detail and the Richieste/Iscritti work panel carry. */
export interface ContractedQuoteFlags {
  is_contracted?: boolean
  can_update_contracted?: boolean
}

/**
 * Spec 0404 E-D3: fields an offer under contract keeps editable on every
 * channel (the operational ones), mirroring the server guard's own list.
 */
export const CONTRACTED_FREE_FIELDS: readonly string[] = [
  'manager_slots',
  'managers',
  'next_callback_at',
  'operational_site_id',
  'general_notes',
  'note',
]

/** True when the offer is under contract and the actor lacks `quotes.updateContracted`: the frozen fields become read-only. */
export function isContractedFrozen(flags: ContractedQuoteFlags): boolean {
  return flags.is_contracted === true && flags.can_update_contracted !== true
}

/** True when the offer is under contract and the actor may edit it, after a confirmation. */
export function isContractedConfirmRequired(flags: ContractedQuoteFlags): boolean {
  return flags.is_contracted === true && flags.can_update_contracted === true
}

function toReadOnly(permission: FieldPermission): FieldPermission {
  return {
    ...permission,
    editable: false,
    readonly: permission.visible && !permission.disabled,
  }
}

/** The permissions block with every field read-only but the free operational ones. */
export function freezeContractedPermissions(permissions: ResourcePermissions): ResourcePermissions {
  const fields = Object.fromEntries(
    Object.entries(permissions.fields).map(([key, permission]) => [
      key,
      CONTRACTED_FREE_FIELDS.includes(key) ? permission : toReadOnly(permission),
    ]),
  )

  return { ...permissions, fields }
}
