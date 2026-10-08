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
  CreateRegistryPayload,
  RegistryDetailWithPermissions,
  UpdateRegistryPayload,
} from '@/features/registries/types'
import type { RegistryFormValues } from '@/features/registries/use-registry-form'
import { buildCustomFieldsCreate, buildCustomFieldsUpdate } from '@/features/custom-fields/custom-fields-payload'

/**
 * Builds the create payload: the registry-specific scalars/relations plus the
 * nested `personal_data` tree (REQUIRED on create, spec 0020). The tree omits
 * any scalar field/section `fieldPermission` marks non-editable (spec 0008
 * D2, defense in depth alongside the backend's CHANGE-based guard).
 * `is_qualified_supplier` is only meaningful when `is_supplier` is true (the
 * form hides its toggle otherwise): forced `false` here mirrors the server's
 * own normalization so the payload is deterministic regardless of stale RHF
 * state left behind by the hidden field.
 */
export function buildCreatePayload(
  values: RegistryFormValues,
  profileDraft: PersonalDataDraft,
  fieldPermission?: PersonalDataFieldPermissionResolver,
): CreateRegistryPayload {
  const customFields = buildCustomFieldsCreate(values.custom_fields)
  return {
    source_id: values.source_id,
    sector_ids: values.sector_ids,
    referent_ids: values.referent_ids,
    manager_slots: values.manager_slots,
    supervisor_id: values.supervisor_id,
    commercial_id: values.commercial_id,
    reporter_id: values.reporter_id,
    vat_group: values.vat_group || null,
    is_supplier: values.is_supplier,
    is_qualified_supplier: values.is_supplier ? values.is_qualified_supplier : false,
    agreement_status: values.agreement_status,
    agreement_notes: values.agreement_notes || null,
    size_class: values.size_class,
    employee_count: values.employee_count,
    general_notes: values.general_notes || null,
    personal_data: omitNonEditableFields(draftToPayload(profileDraft), fieldPermission),
    ...(Object.keys(customFields).length > 0 ? { custom_fields: customFields } : {}),
  }
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original registry (spec 0020 AC-023): scalars are compared with `!==`, the
 * three pivot arrays with an order-insensitive id-set comparison, and the
 * nested `personal_data` tree is included ONLY when the buffered draft
 * actually differs from the one seeded from `original.personal_data` —
 * `draftToPayload` builds its object with a fixed key order, so a
 * `JSON.stringify` comparison is a reliable, dependency-free deep-equal here
 * (mirrors `referents`).
 */
export function buildUpdatePayload(
  values: RegistryFormValues,
  original: RegistryDetailWithPermissions,
  profileDraft: PersonalDataDraft,
  fieldPermission?: PersonalDataFieldPermissionResolver,
): UpdateRegistryPayload {
  const payload: UpdateRegistryPayload = {}

  if (values.source_id !== original.source_id) {
    payload.source_id = values.source_id
  }
  if (values.supervisor_id !== original.supervisor_id) {
    payload.supervisor_id = values.supervisor_id
  }
  if (values.commercial_id !== original.commercial_id) {
    payload.commercial_id = values.commercial_id
  }
  if (values.reporter_id !== original.reporter_id) {
    payload.reporter_id = values.reporter_id
  }
  const vatGroup = values.vat_group || null
  if (vatGroup !== original.vat_group) {
    payload.vat_group = vatGroup
  }
  if (values.is_supplier !== original.is_supplier) {
    payload.is_supplier = values.is_supplier
  }
  const isQualifiedSupplier = values.is_supplier ? values.is_qualified_supplier : false
  if (isQualifiedSupplier !== original.is_qualified_supplier) {
    payload.is_qualified_supplier = isQualifiedSupplier
  }
  if (values.agreement_status !== original.agreement_status) {
    payload.agreement_status = values.agreement_status
  }
  const agreementNotes = values.agreement_notes || null
  if (agreementNotes !== original.agreement_notes) {
    payload.agreement_notes = agreementNotes
  }
  if (values.size_class !== original.size_class) {
    payload.size_class = values.size_class
  }
  if (values.employee_count !== original.employee_count) {
    payload.employee_count = values.employee_count
  }
  const generalNotes = values.general_notes || null
  if (generalNotes !== original.general_notes) {
    payload.general_notes = generalNotes
  }

  if (!sameIdSet(values.sector_ids, original.sector_ids)) {
    payload.sector_ids = values.sector_ids
  }
  if (!sameIdSet(values.referent_ids, original.referent_ids)) {
    payload.referent_ids = values.referent_ids
  }
  // Manager slots are ORDER- and GAP-sensitive (a slot's G.A. position is
  // meaningful), so compare positionally, not as an unordered set.
  if (!sameSlots(values.manager_slots, original.manager_slots)) {
    payload.manager_slots = values.manager_slots
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

/** Order-insensitive comparison of two id lists. */
function sameIdSet(a: number[], b: number[]): boolean {
  if (a.length !== b.length) {
    return false
  }
  const set = new Set(b)
  return a.every((id) => set.has(id))
}

/** Positional (order- and gap-sensitive) comparison of two G.A. slot arrays. */
function sameSlots(a: (number | null)[], b: (number | null)[]): boolean {
  return a.length === b.length && a.every((slot, index) => slot === b[index])
}
