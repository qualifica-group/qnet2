/**
 * Company Sites CRUD types. The generic table types (columns/filters/actions/
 * rows) live in `features/table/types.ts`; this file holds only what is
 * genuinely company-sites-specific — the resource, its embedded personal-data
 * card (identity + contacts + single address), its inline banks collection and
 * its create/update payloads. Source of truth: the frozen spec 0020
 * `data_contract`; the nested `personal_data` sub-contract is reused unchanged
 * from `features/personal-data` (mirrors the Registries module).
 */

import type { PersonalDataCard } from '@/features/personal-data/types'
import type { PersonalDataPayload } from '@/features/personal-data/drafts'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldValue } from '@/features/custom-fields/types'

/** A single bank of the site's inline 1→N banks collection. */
export interface CompanySiteBank {
  id: number
  name: string
  iban: string | null
  notes: string | null
  /** The site's preferred bank (single-primary, mirrors contacts/addresses). */
  is_primary: boolean
}

/**
 * Single company-site detail returned by GET/POST/PATCH /company-sites
 * (envelope `data`). Matches `CompanySiteResource`.
 */
export interface CompanySiteDetail {
  id: number
  name: string
  notes: string | null
  is_default: boolean
  logo_url: string | null
  /**
   * The site's personal-data card (identity + contacts + at most one address).
   * `null` only in the pathological case of a site with no card yet.
   */
  personal_data: PersonalDataCard | null
  banks: CompanySiteBank[]
  /** The company (società) this site belongs to, editable in the Impostazioni tab. */
  company: { id: number; label: string } | null
  created_at: string | null
  /** Custom field values keyed by their raw (un-namespaced) key (spec 0021). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * A `CompanySiteDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /company-sites/{companySite}`
 * (`show`). Used to seed the edit form's `ResourcePermissionsProvider`
 * without a second request.
 */
export interface CompanySiteDetailWithPermissions extends CompanySiteDetail {
  permissions: ResourcePermissions
}

/** A single bank row accepted by POST/PATCH /company-sites (`banks[]`). */
export interface CreateCompanySiteBankPayload {
  /** Present = existing row to update; absent = new row to create. */
  id?: number
  name: string
  iban?: string | null
  notes?: string | null
  is_primary: boolean
}

/**
 * Payload for POST /company-sites (create). `name` is the site's own required
 * scalar (NOT derived from the card); `personal_data` is REQUIRED and always
 * carries `type: 'company'` with at most one address (spec 0020, mirrors the
 * Registries `personal_data` envelope).
 */
export interface CreateCompanySitePayload {
  name: string
  notes?: string | null
  personal_data: PersonalDataPayload
  banks?: CreateCompanySiteBankPayload[]
  company_id?: number | null
  /** All valued custom fields, keyed by raw key (spec 0021, create = full set). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * Payload for PATCH /company-sites/{id} (partial update). Every field is
 * optional so the request only carries what actually changed; a present
 * `personal_data` is a full-replace sync of the card + contacts + single
 * address, a present `banks` is the AUTHORITATIVE list (add/update/delete
 * diff, resolved server-side by `BankService::sync`). "Altro" fields and
 * `is_default` are never sent (read-only / dedicated `set-default` action).
 */
export interface UpdateCompanySitePayload {
  name?: string
  notes?: string | null
  personal_data?: PersonalDataPayload
  banks?: CreateCompanySiteBankPayload[]
  company_id?: number | null
  /** Only the custom fields that changed, keyed by raw key (spec 0021, sparse diff). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * A buffered bank row awaiting submission inside the site payload (mirrors
 * `ContactDraft`): an optional `id` (present = existing row to update, absent
 * = new row to create) plus a stable client-only `_key` for list rendering.
 */
export interface BankDraft {
  /** Stable client-only key for list rendering (never sent to the server). */
  _key: string
  id?: number
  name: string
  iban: string | null
  notes: string | null
  /** The site's preferred bank (single-primary across the list). */
  is_primary: boolean
}
