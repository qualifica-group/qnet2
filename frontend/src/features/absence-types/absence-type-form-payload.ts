import type { AbsenceTypeFormValues } from '@/features/absence-types/absence-type-schema'
import type { AbsenceType, AbsenceTypePayload, UpdateAbsenceTypePayload } from '@/features/absence-types/types'

/** Builds the create payload: every field the form owns. */
export function buildCreatePayload(values: AbsenceTypeFormValues): AbsenceTypePayload {
  return { ...values, name: values.name.trim() }
}

/** Builds a partial PATCH payload with only the fields that changed. */
export function buildUpdatePayload(values: AbsenceTypeFormValues, original: AbsenceType): UpdateAbsenceTypePayload {
  const next = { ...values, name: values.name.trim() }
  const payload: UpdateAbsenceTypePayload = {}
  if (next.name !== original.name) payload.name = next.name
  if (next.deducts_balance !== original.deducts_balance) payload.deducts_balance = next.deducts_balance
  if (next.requires_approval !== original.requires_approval) payload.requires_approval = next.requires_approval
  if (next.is_active !== original.is_active) payload.is_active = next.is_active
  if (next.sort_order !== original.sort_order) payload.sort_order = next.sort_order
  return payload
}
