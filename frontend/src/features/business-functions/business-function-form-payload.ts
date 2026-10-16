import type {
  BusinessFunctionDetailWithPermissions,
  CreateBusinessFunctionPayload,
  UpdateBusinessFunctionPayload,
} from '@/features/business-functions/types'
import type { BusinessFunctionFormValues } from '@/features/business-functions/use-business-function-form'
import { buildCustomFieldsCreate, buildCustomFieldsUpdate } from '@/features/custom-fields/custom-fields-payload'

/** Builds the create payload: `{name, type, manager_id, users, parent_id, operational_sites}` (spec 0010 AC-019). */
export function buildCreatePayload(
  values: BusinessFunctionFormValues,
): CreateBusinessFunctionPayload {
  const customFields = buildCustomFieldsCreate(values.custom_fields)
  return {
    name: values.name,
    type: values.type,
    manager_id: values.manager_id,
    users: values.users,
    parent_id: values.parent_id,
    operational_sites: values.operational_sites,
    company_ids: values.company_ids,
    ...(Object.keys(customFields).length > 0 ? { custom_fields: customFields } : {}),
  }
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original business function (spec 0010 AC-019). `users`/`operational_sites`
 * are compared as an order-insensitive id set and, when different, sent as a
 * full replacement; `manager_id: null` is sent whenever the responsabile was
 * cleared; `type`/`parent_id` are sent whenever the selection changed
 * (including to/from `null`).
 */
export function buildUpdatePayload(
  values: BusinessFunctionFormValues,
  original: BusinessFunctionDetailWithPermissions,
): UpdateBusinessFunctionPayload {
  const payload: UpdateBusinessFunctionPayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.type !== original.type) {
    payload.type = values.type
  }
  if (values.manager_id !== original.manager_id) {
    payload.manager_id = values.manager_id
  }
  if (!sameIdSet(values.users, original.user_ids)) {
    payload.users = values.users
  }
  if (values.parent_id !== original.parent_id) {
    payload.parent_id = values.parent_id
  }
  if (!sameIdSet(values.operational_sites, original.operational_site_ids)) {
    payload.operational_sites = values.operational_sites
  }
  if (!sameIdSet(values.company_ids, original.company_ids)) {
    payload.company_ids = values.company_ids
  }

  const customFields = buildCustomFieldsUpdate(values.custom_fields, original.custom_fields ?? {})
  if (Object.keys(customFields).length > 0) {
    payload.custom_fields = customFields
  }

  return payload
}

/** Order-insensitive comparison of two id lists. */
function sameIdSet(a: number[], b: number[]): boolean {
  if (a.length !== b.length) {
    return false
  }
  const set = new Set(b)
  return a.every((id) => set.has(id))
}
