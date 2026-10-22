import type { AbsenceTypeFormValues } from '@/features/absence-types/absence-type-schema'
import type { AbsenceType, AbsenceTypePayload, UpdateAbsenceTypePayload } from '@/features/absence-types/types'

/** Builds the create payload: every field the form owns. */
export function buildCreatePayload(values: AbsenceTypeFormValues): AbsenceTypePayload {
  return { ...values, name: values.name.trim(), default_annual_days: effectiveDefaultDays(values) }
}

/** The default entitlement only exists for types that deduct a balance. */
function effectiveDefaultDays(values: AbsenceTypeFormValues): number | null {
  return values.deducts_balance ? values.default_annual_days : null
}

/** Builds a partial PATCH payload with only the fields that changed. */
export function buildUpdatePayload(values: AbsenceTypeFormValues, original: AbsenceType): UpdateAbsenceTypePayload {
  const next = { ...values, name: values.name.trim(), default_annual_days: effectiveDefaultDays(values) }
  const payload: UpdateAbsenceTypePayload = {}
  if (next.name !== original.name) payload.name = next.name
  if (next.deducts_balance !== original.deducts_balance) payload.deducts_balance = next.deducts_balance
  if (next.requires_approval !== original.requires_approval) payload.requires_approval = next.requires_approval
  if (next.is_active !== original.is_active) payload.is_active = next.is_active
  if (next.sort_order !== original.sort_order) payload.sort_order = next.sort_order
  if (next.allows_full_day !== original.allows_full_day) payload.allows_full_day = next.allows_full_day
  if (next.allows_half_day !== original.allows_half_day) payload.allows_half_day = next.allows_half_day
  if (next.allows_hourly !== original.allows_hourly) payload.allows_hourly = next.allows_hourly
  if (next.min_notice_days !== original.min_notice_days) payload.min_notice_days = next.min_notice_days
  if (next.color !== original.color) payload.color = next.color
  if (next.default_annual_days !== original.default_annual_days) payload.default_annual_days = next.default_annual_days
  return payload
}
