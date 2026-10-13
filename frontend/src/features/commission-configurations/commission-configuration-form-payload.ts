import type { CommissionConfigurationFormValues } from './commission-configuration-schema'
import {
  COMMISSION_ROLE_ALLOWED_RECIPIENT_TYPES,
  type CommissionConfigurationDetail,
  type CommissionConfigurationPayload,
  type CommissionRole,
  type UpdateCommissionConfigurationPayload,
} from './types'

/** A fresh rule: a role-wide COMMERCIAL default, or a personal SUPPLIER rule on `supplierRegistryId` (spec 0204 D-4). */
export function createDefaults(supplierRegistryId: number | null | undefined): CommissionConfigurationFormValues {
  const supplierPreset = supplierRegistryId != null
  const role: CommissionRole = supplierPreset ? 'SUPPLIER' : 'COMMERCIAL'
  return {
    name: '',
    recipient_role: role,
    application_scope: supplierPreset ? 'RECIPIENT' : 'PRODUCT_CATEGORY',
    product_category_id: null,
    product_id: null,
    recipient_type: COMMISSION_ROLE_ALLOWED_RECIPIENT_TYPES[role][0],
    recipient_id: supplierRegistryId ?? null,
    commission_type: 'PERCENTAGE',
    value: 0,
    priority: 0,
    valid_from: new Date().toISOString().slice(0, 10),
    valid_until: null,
    status: 'ACTIVE',
    internal_note: null,
  }
}

/** The saved record as form values: what the edit form re-syncs on and diffs against. */
export function toFormValues(configuration: CommissionConfigurationDetail): CommissionConfigurationFormValues {
  return {
    name: configuration.name,
    recipient_role: configuration.recipient_role,
    application_scope: configuration.application_scope,
    product_category_id: configuration.product_category_id,
    product_id: configuration.product_id,
    // Omitted when not visible for field permission: falls back to the
    // role's default (server-side derivation, spec 0090 D-4).
    recipient_type:
      configuration.recipient_type ?? COMMISSION_ROLE_ALLOWED_RECIPIENT_TYPES[configuration.recipient_role][0],
    recipient_id: configuration.recipient_id ?? null,
    commission_type: configuration.commission_type,
    value: Number(configuration.value),
    priority: configuration.priority,
    valid_from: configuration.valid_from,
    valid_until: configuration.valid_until,
    status: configuration.status,
    internal_note: configuration.internal_note,
  }
}

/** The wire shape: the relation the scope does not use is nulled, cleared inputs are `null`. */
export function buildCreatePayload(values: CommissionConfigurationFormValues): CommissionConfigurationPayload {
  return {
    ...values,
    product_category_id: values.application_scope === 'PRODUCT_CATEGORY' ? values.product_category_id : null,
    product_id: values.application_scope === 'PRODUCT' ? values.product_id : null,
    // recipient_type/recipient_id are orthogonal to application_scope (D-2):
    // sent as-is, whichever scope is active.
    recipient_type: values.recipient_type,
    recipient_id: values.recipient_id,
    valid_until: values.valid_until || null,
    internal_note: values.internal_note || null,
  }
}

/**
 * A partial PATCH carrying only what differs from the saved record, so a row
 * sends its own fields (a grouped row: all of its changed fields together)
 * and never trips a rule on a field the actor did not touch.
 */
export function buildUpdatePayload(
  values: CommissionConfigurationFormValues,
  original: CommissionConfigurationFormValues,
): UpdateCommissionConfigurationPayload {
  const next = buildCreatePayload(values)
  const previous = buildCreatePayload(original)
  const changed: Record<string, unknown> = {}
  for (const key of Object.keys(next) as (keyof CommissionConfigurationPayload)[]) {
    if (next[key] !== previous[key]) {
      changed[key] = next[key]
    }
  }
  return changed as UpdateCommissionConfigurationPayload
}
