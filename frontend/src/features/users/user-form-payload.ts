import { buildCustomFieldsCreate, buildCustomFieldsUpdate } from '@/features/custom-fields/custom-fields-payload'
import {
  cardToDraft,
  draftToPayload,
  emptyPersonalDataDraft,
  omitNonEditableFields,
} from '@/features/personal-data/drafts'
import type {
  PersonalDataDraft,
  PersonalDataFieldPermissionResolver,
} from '@/features/personal-data/types'
import type {
  CreateUserPayload,
  EmploymentPayload,
  EmploymentProductLineInput,
  UpdateUserPayload,
  UserDetailWithPermissions,
} from '@/features/users/types'
import { editDefaults } from '@/features/users/user-form-defaults'
import type { EmploymentFormValues } from '@/features/users/user-schema'
import type { UserFormValues } from '@/features/users/use-user-form'

/**
 * Maps the employment form values to the nested wire payload (spec 0015).
 * Text/date `''` sentinels become `null`; `reports_to_ids` is force-emptied
 * client-side when `is_manager` is true, mirroring the server-side rule
 * (spec 0166 D-5) as defense in depth.
 */
function buildEmploymentPayload(values: EmploymentFormValues): EmploymentPayload {
  return {
    is_manager: values.is_manager,
    job_role_ids: values.job_role_ids,
    reports_to_ids: values.is_manager ? [] : values.reports_to_ids,
    relationship_type: values.relationship_type,
    company_id: values.company_id,
    primary_operational_site_id: values.primary_operational_site_id,
    remote_operational_site_ids: values.remote_operational_site_ids,
    is_assignable: values.is_assignable,
    covers_all_product_categories: values.covers_all_product_categories,
    // Spec 0129 D-2: the flag and the rows are ONE state — defense in depth
    // behind the UI already hiding/clearing the editor while it is on, so a
    // stale row never reaches the server alongside a true flag (422 there).
    product_lines: values.covers_all_product_categories ? [] : completeProductLines(values.product_lines),
    qualification_type: values.qualification_type,
    hired_at: values.hired_at || null,
    terminated_at: values.terminated_at || null,
    standard_daily_minutes: values.standard_daily_minutes,
    break_daily_minutes: values.break_daily_minutes,
  }
}

/**
 * Drops any row still missing its function, or missing BOTH a category and
 * the "all categories" checkbox. The schema already refuses the submit on an
 * incomplete row (`buildEmploymentSchema`), so this never actually drops one
 * in practice — it exists because the form value stays nullable-per-id at the
 * type level (each row is inline-editable), exactly as in the
 * opportunity/project payload builders. A checked "all categories" row (spec
 * 0129 D-3) serializes its category as `null`.
 */
function completeProductLines(
  rows: EmploymentFormValues['product_lines'],
): EmploymentProductLineInput[] {
  return rows
    .filter(
      (row) =>
        row.business_function_id !== null && (row.product_category_id !== null || row.all_categories === true),
    )
    .map((row) => ({
      business_function_id: row.business_function_id as number,
      product_category_id: row.all_categories === true ? null : row.product_category_id,
    }))
}

/**
 * Builds the create payload, including password + confirmation only when a
 * temporary password was typed (empty = invite mode, spec 0177), plus the
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
    ...(values.password !== ''
      ? { password: values.password, password_confirmation: values.password_confirmation }
      : {}),
    personal_data: omitNonEditableFields(draftToPayload(profileDraft), fieldPermission),
    employment: buildEmploymentPayload(values.employment),
    ...(Object.keys(customFields).length > 0 ? { custom_fields: customFields } : {}),
  }
}

/**
 * Builds a partial PATCH payload carrying only what changed from the original
 * user (spec 0222 D-3): the in-place detail saves one row at a time. Password
 * is included only when a new one was typed. The nested `employment` object is
 * an upsert of the whole profile, so it travels whole — but only when one of
 * its fields changed. The `personal_data` tree is included only when the
 * buffered card differs from the persisted one (`draftToPayload` builds its
 * object with a fixed key order, so a `JSON.stringify` comparison is a
 * reliable deep-equal), omitting any field `fieldPermission` marks
 * non-editable (spec 0008 D2).
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

  const employment = buildEmploymentPayload(values.employment)
  const originalEmployment = buildEmploymentPayload(editDefaults(original, {}).employment)
  if (JSON.stringify(employment) !== JSON.stringify(originalEmployment)) {
    payload.employment = employment
  }

  const originalDraft = original.personal_data ? cardToDraft(original.personal_data) : emptyPersonalDataDraft()
  const nextCard = draftToPayload(profileDraft)
  if (JSON.stringify(nextCard) !== JSON.stringify(draftToPayload(originalDraft))) {
    payload.personal_data = omitNonEditableFields(nextCard, fieldPermission)
  }

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
