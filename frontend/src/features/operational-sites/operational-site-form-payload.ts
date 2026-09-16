import type {
  CreateOperationalSitePayload,
  OperationalSiteDetailWithPermissions,
  UpdateOperationalSitePayload,
} from '@/features/operational-sites/types'
import type { OperationalSiteFormValues } from '@/features/operational-sites/use-operational-site-form'
import { buildCustomFieldsCreate, buildCustomFieldsUpdate } from '@/features/custom-fields/custom-fields-payload'

/**
 * Builds the create payload `{alias, line1, postal_code, country_id, state_id,
 * province_id, city_id, is_active}` (spec 0011 AC-019). `city_id` is cast to `number`:
 * the Zod schema's `refine` guarantees it is non-null by the time RHF calls
 * `onSubmit`, so this never carries `null` over the wire despite the wider
 * `number | null` form type shared with the (nullable) geo cascade.
 */
export function buildCreatePayload(
  values: OperationalSiteFormValues,
): CreateOperationalSitePayload {
  const customFields = buildCustomFieldsCreate(values.custom_fields)
  return {
    alias: values.alias || null,
    line1: values.line1,
    postal_code: values.postal_code || null,
    country_id: values.country_id,
    state_id: values.state_id,
    province_id: values.province_id,
    city_id: values.city_id as number,
    is_active: values.is_active,
    ...(Object.keys(customFields).length > 0 ? { custom_fields: customFields } : {}),
  }
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original operational site (spec 0011 AC-019). `postal_code: null` is sent
 * whenever the CAP was cleared.
 */
export function buildUpdatePayload(
  values: OperationalSiteFormValues,
  original: OperationalSiteDetailWithPermissions,
): UpdateOperationalSitePayload {
  const payload: UpdateOperationalSitePayload = {}

  const alias = values.alias || null
  if (alias !== original.alias) {
    payload.alias = alias
  }
  if (values.line1 !== original.line1) {
    payload.line1 = values.line1
  }
  const postalCode = values.postal_code || null
  if (postalCode !== original.postal_code) {
    payload.postal_code = postalCode
  }
  if (values.country_id !== original.country_id) {
    payload.country_id = values.country_id
  }
  if (values.state_id !== original.state_id) {
    payload.state_id = values.state_id
  }
  if (values.province_id !== original.province_id) {
    payload.province_id = values.province_id
  }
  if (values.city_id !== original.city_id) {
    payload.city_id = values.city_id as number
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
