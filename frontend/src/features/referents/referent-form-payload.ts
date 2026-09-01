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
  CreateReferentPayload,
  ReferentDetailWithPermissions,
  UpdateReferentPayload,
} from '@/features/referents/types'
import type { ReferentFormValues } from '@/features/referents/use-referent-form'

/**
 * Builds the create payload: the referent-specific scalars plus the nested
 * `personal_data` tree (REQUIRED on create, spec 0016). The tree omits any
 * scalar field/section `fieldPermission` marks non-editable (spec 0008 D2,
 * defense in depth alongside the backend's CHANGE-based guard).
 */
export function buildCreatePayload(
  values: ReferentFormValues,
  profileDraft: PersonalDataDraft,
  fieldPermission?: PersonalDataFieldPermissionResolver,
): CreateReferentPayload {
  const customFields = buildCustomFieldsCreate(values.custom_fields)
  return {
    referent_type_id: values.referent_type_id,
    user_id: values.user_id,
    contact_scope: values.contact_scope,
    notes: values.notes || null,
    personal_data: omitNonEditableFields(draftToPayload(profileDraft), fieldPermission),
    ...(Object.keys(customFields).length > 0 ? { custom_fields: customFields } : {}),
  }
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original referent. Unlike `users` (which always re-sends the card), the
 * nested `personal_data` tree is included ONLY when the buffered draft
 * actually differs from the one seeded from `original.personal_data` (spec
 * 0016 AC-022): `draftToPayload` builds its object with a fixed key order, so
 * a `JSON.stringify` comparison is a reliable, dependency-free deep-equal here.
 */
export function buildUpdatePayload(
  values: ReferentFormValues,
  original: ReferentDetailWithPermissions,
  profileDraft: PersonalDataDraft,
  fieldPermission?: PersonalDataFieldPermissionResolver,
): UpdateReferentPayload {
  const payload: UpdateReferentPayload = {}

  if (values.referent_type_id !== original.referent_type_id) {
    payload.referent_type_id = values.referent_type_id
  }
  // `original.user_id` may be entirely absent (field permission not visible,
  // spec 0090 AC-005): normalize to `null` so an unlinked-and-hidden field
  // never diffs against the form's own unlinked default.
  if (values.user_id !== (original.user_id ?? null)) {
    payload.user_id = values.user_id
  }
  if (values.contact_scope !== original.contact_scope) {
    payload.contact_scope = values.contact_scope
  }
  const notes = values.notes || null
  if (notes !== original.notes) {
    payload.notes = notes
  }

  const originalDraft = original.personal_data
    ? cardToDraft(original.personal_data)
    : emptyPersonalDataDraft()
  const nextPayload = draftToPayload(profileDraft)
  if (JSON.stringify(nextPayload) !== JSON.stringify(draftToPayload(originalDraft))) {
    payload.personal_data = omitNonEditableFields(nextPayload, fieldPermission)
  }

  const customFields = buildCustomFieldsUpdate(values.custom_fields, original.custom_fields ?? {})
  if (Object.keys(customFields).length > 0) {
    payload.custom_fields = customFields
  }

  return payload
}
