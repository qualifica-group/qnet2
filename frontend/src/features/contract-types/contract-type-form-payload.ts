import type { ContractTypeFormValues } from '@/features/contract-types/contract-type-schema'
import type { ContractType, ContractTypePayload, UpdateContractTypePayload } from '@/features/contract-types/types'

/** Builds the create payload: every field the form owns. */
export function buildCreatePayload(values: ContractTypeFormValues): ContractTypePayload {
  return { ...values, name: values.name.trim() }
}

/** Builds a partial PATCH payload with only the fields that changed. */
export function buildUpdatePayload(values: ContractTypeFormValues, original: ContractType): UpdateContractTypePayload {
  const next = { ...values, name: values.name.trim() }
  const payload: UpdateContractTypePayload = {}
  if (next.name !== original.name) payload.name = next.name
  if (next.is_active !== original.is_active) payload.is_active = next.is_active
  if (next.sort_order !== original.sort_order) payload.sort_order = next.sort_order
  return payload
}
