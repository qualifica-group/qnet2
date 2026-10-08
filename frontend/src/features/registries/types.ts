/**
 * Registries CRUD types. The generic table types (columns/filters/actions/
 * rows) live in `features/table/types.ts`; this file holds only what is
 * genuinely registries-specific — the resource and its create/update
 * payloads. Source of truth: spec 0020 frozen `data_contract`. The nested
 * `personal_data` sub-contract is reused unchanged from `features/personal-data`.
 */

import type { PersonalDataCard } from '@/features/personal-data/types'
import type { PersonalDataPayload } from '@/features/personal-data/drafts'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { PrimaryContact } from '@/features/table/types'

/** Convention status of a registry (AgreementStatusEnum). */
export const AGREEMENT_STATUSES = ['negotiating', 'rejected', 'agreed'] as const
export type AgreementStatus = (typeof AGREEMENT_STATUSES)[number]

/** Size class of a registry (SizeClassEnum). */
export const SIZE_CLASSES = ['micro', 'small', 'medium', 'large'] as const
export type SizeClass = (typeof SIZE_CLASSES)[number]

/** List tabs over the `registry_type` column (PersonalDataTypeEnum values + "all"). */
export const REGISTRY_TYPE_TABS = ['all', 'individual', 'company'] as const
export type RegistryTypeTab = (typeof REGISTRY_TYPE_TABS)[number]

/** A hydrated relation reference ({id, name}), as returned by the registry resource. */
export interface ReferenceRef {
  id: number
  name: string
  /**
   * The person's PRIMARY contacts (one per type), present on the person refs
   * (supervisor/commercial/reporter, referents, managers). Empty when the
   * person has no primary contact; absent on plain refs (source/sectors/...).
   */
  primary_contacts?: PrimaryContact[]
}

/**
 * A manager ref carrying its static "G.A. n" `position` (1-based) on top of the
 * person ref. Managers are always returned ordered by position.
 */
export interface ManagerRef extends ReferenceRef {
  position: number
}

/**
 * Single registry detail returned by GET/POST/PATCH /registries (envelope
 * `data`). Matches `RegistryResource`.
 */
export interface RegistryDetail {
  id: number
  name: string
  source_id: number | null
  /** Hydrates the "Source" single-select control. */
  source: ReferenceRef | null
  sector_ids: number[]
  /** Hydrates the "Sectors" multiselect control. */
  sectors: ReferenceRef[]
  referent_ids: number[]
  /** Hydrates the "Referents" multiselect control. */
  referents: ReferenceRef[]
  /** Filled manager ids, ordered by G.A. position. */
  manager_ids: number[]
  /** Managers as ordered "G.A. n" cards (name + position + primary contacts). */
  managers: ManagerRef[]
  /**
   * Gap-aware slot array: index+1 = G.A. number, `null` = empty slot. Length =
   * highest occupied position. Seeds the form's slot editor and the detail's
   * G.A. cards directly.
   */
  manager_slots: (number | null)[]
  supervisor_id: number | null
  supervisor: ReferenceRef | null
  commercial_id: number | null
  commercial: ReferenceRef | null
  reporter_id: number | null
  reporter: ReferenceRef | null
  vat_group: string | null
  is_supplier: boolean
  /** Only meaningful when `is_supplier` is true; the server normalizes it otherwise. */
  is_qualified_supplier: boolean
  agreement_status: AgreementStatus | null
  agreement_notes: string | null
  general_notes: string | null
  size_class: SizeClass | null
  employee_count: number | null
  /**
   * The registry's personal-data card (with its contacts and addresses).
   * `null` only in the pathological case of a registry with no card yet.
   */
  personal_data: PersonalDataCard | null
  created_at: string
  /** Custom field values keyed by their raw (un-namespaced) key (spec 0021). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * A `RegistryDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /registries/{id}` (`show`). Used
 * to seed the edit form's `ResourcePermissionsProvider` — and the anagraphic
 * draft, via `cardToDraft(personal_data)` — without a second request.
 */
export interface RegistryDetailWithPermissions extends RegistryDetail {
  permissions: ResourcePermissions
}

/**
 * Payload for POST /registries (create). `personal_data` is REQUIRED: the
 * registry's `name` is derived server-side from the card (ADR 0012 pattern,
 * reused here via `RegistryProfileWriter`).
 */
export interface CreateRegistryPayload {
  source_id?: number | null
  sector_ids?: number[]
  referent_ids?: number[]
  /** Ordered, gap-aware G.A. slots: index+1 = G.A. n, `null` = empty slot. */
  manager_slots?: (number | null)[]
  supervisor_id?: number | null
  commercial_id?: number | null
  reporter_id?: number | null
  vat_group?: string | null
  is_supplier: boolean
  is_qualified_supplier?: boolean
  agreement_status?: AgreementStatus | null
  agreement_notes?: string | null
  general_notes?: string | null
  size_class?: SizeClass | null
  employee_count?: number | null
  personal_data: PersonalDataPayload
  /** All valued custom fields, keyed by raw key (spec 0021, create = full set). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * Payload for PATCH /registries/{id} (partial update). Every field is
 * optional so the request only carries what actually changed; the pivot
 * arrays are a full-replace sync when present; `personal_data`, when
 * present, is a full-replace sync of contacts/addresses.
 */
export interface UpdateRegistryPayload {
  source_id?: number | null
  sector_ids?: number[]
  referent_ids?: number[]
  /** Ordered, gap-aware G.A. slots: index+1 = G.A. n, `null` = empty slot. */
  manager_slots?: (number | null)[]
  supervisor_id?: number | null
  commercial_id?: number | null
  reporter_id?: number | null
  vat_group?: string | null
  is_supplier?: boolean
  is_qualified_supplier?: boolean
  agreement_status?: AgreementStatus | null
  agreement_notes?: string | null
  general_notes?: string | null
  size_class?: SizeClass | null
  employee_count?: number | null
  personal_data?: PersonalDataPayload
  /** Only the custom fields that changed, keyed by raw key (spec 0021, sparse diff). */
  custom_fields?: Record<string, CustomFieldValue>
}

/** Discriminated form mode shared by the form hook/meta-resolver and `RegistryForm`. */
export type RegistryFormMode =
  | { type: 'create' }
  | { type: 'edit'; registry: RegistryDetailWithPermissions }
