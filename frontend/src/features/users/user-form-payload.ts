import { buildCustomFieldsCreate, buildCustomFieldsUpdate } from '@/features/custom-fields/custom-fields-payload'
import { draftToPayload, omitNonEditableFields } from '@/features/personal-data/drafts'
import type {
  PersonalDataDraft,
  PersonalDataFieldPermissionResolver,
} from '@/features/personal-data/types'
import type {
  CreateUserPayload,
  EmploymentPayload,
  UpdateUserPayload,
  UserDetailWithPermissions,
} from '@/features/users/types'
import type { EmploymentFormValues } from '@/features/users/user-schema'
import type { UserFormValues } from '@/features/users/use-user-form'

/**
 * Maps the employment form values to the nested wire payload (spec 0015).
 * Text/date `''` sentinels become `null`; `reports_to_id` is force-nulled
 * client-side when `is_manager` is true, mirroring the server-side rule
 * (AC-003) as defense in depth.
 */
function buildEmploymentPayload(values: EmploymentFormValues): EmploymentPayload {
  return {
    is_manager: values.is_manager,
    job_description: values.job_description || null,
    reports_to_id: values.is_manager ? null : values.reports_to_id,
    business_function_id: values.business_function_id,
    relationship_type: values.relationship_type,
    company_id: values.company_id,
    primary_operational_site_id: values.primary_operational_site_id,
    remote_operational_site_ids: values.remote_operational_site_ids,
    product_category_ids: values.product_category_ids,
    qualification_type: values.qualification_type,
    hired_at: values.hired_at || null,
    terminated_at: values.terminated_at || null,
    standard_daily_minutes: values.standard_daily_minutes,
    break_daily_minutes: values.break_daily_minutes,
  }
}

/**
 * Builds the create payload, always including password + confirmation, plus the
 * nested `personal_data` tree when a card was entered (ADR 0012). The tree omits
 * any scalar field/section `fieldPermission` marks non-editable (spec 0008 D2,
 * defense in depth alongside the backend's CHANGE-based guard).
 */
export function buildCreatePayload(
  values: UserFormValues,
  profileDraft: PersonalDataDraft,
  fieldPermission?: PersonalDataFieldPermissionResolver,
): CreateUserPayload {
  const customFields = buildCustomFieldsCreate(values.custom_fields)
  return {
    email: values.email,
    locale: values.locale,
    is_active: values.is_active,
    roles: values.roles,
    password: values.password,
    password_confirmation: values.password_confirmation,
    personal_data: omitNonEditableFields(draftToPayload(profileDraft), fieldPermission),
    employment: buildEmploymentPayload(values.employment),
    ...(Object.keys(customFields).length > 0 ? { custom_fields: customFields } : {}),
  }
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original user. Password is included only when a new one was typed. The nested
 * `personal_data` tree is always sent when a card is present (authoritative sync),
 * so children added/edited/removed in the buffer persist in the one request; it
 * omits any scalar field/section `fieldPermission` marks non-editable (spec 0008 D2).
 */
export function buildUpdatePayload(
  values: UserFormValues,
  original: UserDetailWithPermissions,
  profileDraft: PersonalDataDraft,
  fieldPermission?: PersonalDataFieldPermissionResolver,
): UpdateUserPayload {
  const payload: UpdateUserPayload = {}

  if (values.email !== original.email) {
    payload.email = values.email
  }
  if (values.locale !== original.locale) {
    payload.locale = values.locale
  }
  if (values.is_active !== original.is_active) {
    payload.is_active = values.is_active
  }
  if (!sameRoles(values.roles, original.roles.map((role) => role.id))) {
    payload.roles = values.roles
  }
  if (values.password !== '') {
    payload.password = values.password
    payload.password_confirmation = values.password_confirmation
  }
  payload.personal_data = omitNonEditableFields(draftToPayload(profileDraft), fieldPermission)
  payload.employment = buildEmploymentPayload(values.employment)

  const customFields = buildCustomFieldsUpdate(values.custom_fields, original.custom_fields ?? {})
  if (Object.keys(customFields).length > 0) {
    payload.custom_fields = customFields
  }

  return payload
}

/** Order-insensitive comparison of two role-id lists. */
function sameRoles(a: number[], b: number[]): boolean {
  if (a.length !== b.length) {
    return false
  }
  const set = new Set(b)
  return a.every((id) => set.has(id))
}
