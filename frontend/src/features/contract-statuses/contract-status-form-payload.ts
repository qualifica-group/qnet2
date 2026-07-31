import type {
  ContractStatusDetail,
  CreateContractStatusPayload,
  UpdateContractStatusPayload,
} from '@/features/contract-statuses/types'
import type { ContractStatusFormValues } from '@/features/contract-statuses/use-contract-status-form'

/** Maps the form's `color` (empty string = unset) to the backend's nullable value. */
function colorValue(color: string): string | null {
  return color === '' ? null : color
}

/** Builds the create payload: every field but `sort_order`/`system_key` (server-managed). */
export function buildCreatePayload(
  values: ContractStatusFormValues,
): CreateContractStatusPayload {
  return {
    name: values.name,
    description: values.description,
    color: colorValue(values.color),
    group: values.group,
    is_active: values.is_active,
    is_default: values.is_default,
  }
}

/**
 * Builds a partial PATCH payload carrying only the fields that actually
 * changed from the original contract status.
 */
export function buildUpdatePayload(
  values: ContractStatusFormValues,
  original: ContractStatusDetail,
): UpdateContractStatusPayload {
  const payload: UpdateContractStatusPayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.description !== original.description) {
    payload.description = values.description
  }
  if (colorValue(values.color) !== original.color) {
    payload.color = colorValue(values.color)
  }
  if (values.group !== original.group) {
    payload.group = values.group
  }
  if (values.is_active !== original.is_active) {
    payload.is_active = values.is_active
  }
  if (values.is_default !== original.is_default) {
    payload.is_default = values.is_default
  }

  return payload
}
