import type {
  CreateSectorPayload,
  SectorDetail,
  UpdateSectorPayload,
} from '@/features/sectors/types'
import type { SectorFormValues } from '@/features/sectors/use-sector-form'
import { buildCustomFieldsCreate, buildCustomFieldsUpdate } from '@/features/custom-fields/custom-fields-payload'

/** A blank code input means "no code" (null), never an empty string. */
function codeValue(code: string): string | null {
  const trimmed = code.trim()
  return trimmed === '' ? null : trimmed
}

/** Builds the create payload: `code` + `name` + `parent_id` (null = root sector) + `is_active`. */
export function buildCreatePayload(values: SectorFormValues): CreateSectorPayload {
  const customFields = buildCustomFieldsCreate(values.custom_fields)
  return {
    code: codeValue(values.code),
    name: values.name,
    parent_id: values.parent_id,
    is_active: values.is_active,
    ...(Object.keys(customFields).length > 0 ? { custom_fields: customFields } : {}),
  }
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original sector (spec 0018 AC-019).
 */
export function buildUpdatePayload(
  values: SectorFormValues,
  original: SectorDetail,
): UpdateSectorPayload {
  const payload: UpdateSectorPayload = {}

  if (codeValue(values.code) !== original.code) {
    payload.code = codeValue(values.code)
  }
  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.parent_id !== original.parent_id) {
    payload.parent_id = values.parent_id
  }
  if (values.is_active !== original.is_active) {
    payload.is_active = values.is_active
  }

  const customFields = buildCustomFieldsUpdate(values.custom_fields, original.custom_fields ?? {})
  if (Object.keys(customFields).length > 0) {
    payload.custom_fields = customFields
  }

  return payload
}
