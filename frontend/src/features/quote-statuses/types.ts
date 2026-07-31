/**
 * Quote statuses CRUD types. The generic table types (columns/filters/
 * actions/rows) live in `features/table/types.ts`; this file holds only what
 * is genuinely quote-statuses-specific — the resource and its create/update
 * payloads. Source of truth: spec 0065 frozen `data_contract`. This module
 * has no custom fields (spec 0065 scope: out — clone of `opportunity-statuses`,
 * D-2).
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { QuoteStatusGroupValue, SystemStatusKey } from '@/features/status-reorder/types'

/**
 * Single quote status detail returned by GET/POST/PATCH /quote-statuses
 * (envelope `data`). Matches `QuoteStatusResource`. `sort_order` is
 * server-managed: read-only, never accepted on write. `system_key` marks the
 * three system rows ("Bozza"/"Accettata"/"Rifiutata"), whose `group` is fixed
 * and whose delete/reorder are server-blocked.
 */
export interface QuoteStatusDetail {
  id: number
  name: string
  /** Palette token (e.g. "blue"), or null when unset. */
  color: string | null
  sort_order: number
  system_key: SystemStatusKey
  group: QuoteStatusGroupValue
  created_at: string
}

/**
 * A `QuoteStatusDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /quote-statuses/{id}` (`show`).
 * Used to seed the edit form's `ResourcePermissionsProvider` without a second
 * request.
 */
export interface QuoteStatusDetailWithPermissions extends QuoteStatusDetail {
  permissions: ResourcePermissions
}

/**
 * Payload for POST /quote-statuses (create). `sort_order` is NOT accepted:
 * placement is automatic, always last among the customs.
 */
export interface CreateQuoteStatusPayload {
  name: string
  color?: string | null
  group: QuoteStatusGroupValue
}

/**
 * Payload for PATCH /quote-statuses/{id} (partial update). Every field is
 * optional so the request only carries what actually changed.
 */
export type UpdateQuoteStatusPayload = Partial<CreateQuoteStatusPayload>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `QuoteStatusForm` component.
 */
export type QuoteStatusFormMode =
  | { type: 'create' }
  | { type: 'edit'; quoteStatus: QuoteStatusDetailWithPermissions }
