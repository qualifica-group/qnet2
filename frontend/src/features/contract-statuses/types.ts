/**
 * Contract statuses CRUD types. The generic table types (columns/filters/
 * actions/rows) live in `features/table/types.ts`; this file holds only what
 * is genuinely contract-statuses-specific — the resource and its
 * create/update payloads. Source of truth: spec 0072 frozen `data_contract`.
 * Structurally the closest sibling is `quote-statuses` (same status
 * configurator shape), widened with `description`, `is_active` and the
 * exclusive `is_default` (BR-5, same invariant as
 * `DocumentLayoutDefaultManager`).
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { ContractStatusGroupValue, SystemStatusKey } from '@/features/status-reorder/types'

/**
 * Single contract status detail returned by GET/POST/PATCH
 * /contract-statuses (envelope `data`). Matches `ContractStatusResource`.
 * `sort_order` is server-managed: read-only, never accepted on write.
 * `system_key` marks the four system rows (D-2: "Da validare" HEAD,
 * "Sospeso"/"Annullato"/"Disdetto" TAIL), whose delete/reorder are
 * server-blocked and whose only writable fields are `name`/`color`.
 */
export interface ContractStatusDetail {
  id: number
  name: string
  description: string | null
  /** Palette token (e.g. "blue"), or null when unset. */
  color: string | null
  sort_order: number
  is_active: boolean
  /** Exclusive across the whole table (BR-5): at most one row is ever true. */
  is_default: boolean
  system_key: SystemStatusKey
  group: ContractStatusGroupValue
  created_at: string
  updated_at: string
}

/**
 * A `ContractStatusDetail` carrying the actor's authorization metadata for
 * this instance (spec 0004), as returned by `GET /contract-statuses/{id}`
 * (`show`). Used to seed the edit form's `ResourcePermissionsProvider`
 * without a second request.
 */
export interface ContractStatusDetailWithPermissions extends ContractStatusDetail {
  permissions: ResourcePermissions
}

/**
 * Payload for POST /contract-statuses (create). `sort_order` and
 * `system_key` are NOT accepted: placement is automatic (always last among
 * the customs) and a custom row never carries a system key.
 */
export interface CreateContractStatusPayload {
  name: string
  description?: string | null
  color?: string | null
  group: ContractStatusGroupValue
  is_active?: boolean
  is_default?: boolean
}

/**
 * Payload for PATCH /contract-statuses/{id} (partial update). Every field is
 * optional so the request only carries what actually changed.
 */
export type UpdateContractStatusPayload = Partial<CreateContractStatusPayload>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `ContractStatusForm` component.
 */
export type ContractStatusFormMode =
  | { type: 'create' }
  | { type: 'edit'; contractStatus: ContractStatusDetailWithPermissions }
