/**
 * Reward statuses CRUD types. The generic table types (columns/filters/
 * actions/rows) live in `features/table/types.ts`; this file holds only what
 * is genuinely reward-statuses-specific — the resource and its create/update
 * payloads. Source of truth: spec 0060 frozen `data_contract`, amended by
 * spec 0073. Cloned from `opportunity-statuses` with two extra fields,
 * `description` (nullable) and `is_active` (boolean); `color` is REQUIRED
 * (D-4), unlike the optional `color` of the template. `group` (spec 0073)
 * carries the four-value phase of the quote/contract configurators, NOT the
 * three-value `StatusGroup`. `system_key` marks one of the four system rows,
 * whose delete/reorder are server-blocked and whose `group`/`description`/
 * `is_active`/`sort_order` are locked. This module has no custom fields
 * (spec 0060 scope: out).
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { RewardStatusGroupValue } from '@/features/status-reorder/types'

/**
 * Marks one of the four system-managed rows — "Aperto" (`new`), "In attesa"
 * (`pending`), "Chiuso positivo" (`won`), "Chiuso negativo" (`lost`), spec
 * 0073 D-6; `null` on an ordinary custom row.
 */
export type RewardStatusSystemKey = 'new' | 'pending' | 'won' | 'lost' | null

/**
 * Single reward status detail returned by GET/POST/PATCH /reward-statuses
 * (envelope `data`). Matches `RewardStatusResource`. `sort_order` is
 * server-managed (D-3): read-only, never accepted on write.
 */
export interface RewardStatusDetail {
  id: number
  name: string
  description: string | null
  /** Palette token (e.g. "blue"), always set (D-4). */
  color: string
  /** The phase driving the lifecycle automation (spec 0073). */
  group: RewardStatusGroupValue
  sort_order: number
  is_active: boolean
  system_key: RewardStatusSystemKey
  created_at: string
  updated_at: string
}

/**
 * A `RewardStatusDetail` carrying the actor's authorization metadata for
 * this instance (spec 0004), as returned by `GET /reward-statuses/{id}`
 * (`show`). Used to seed the edit form's `ResourcePermissionsProvider`
 * without a second request.
 */
export interface RewardStatusDetailWithPermissions extends RewardStatusDetail {
  permissions: ResourcePermissions
}

/**
 * Payload for POST /reward-statuses (create). `sort_order` and `system_key`
 * are NOT accepted (D-3/D-2): placement and system marking are automatic.
 */
export interface CreateRewardStatusPayload {
  name: string
  description?: string | null
  color: string
  /** REQUIRED on create (spec 0073): there is no safe phase to infer. */
  group: RewardStatusGroupValue
  is_active?: boolean
}

/**
 * Payload for PATCH /reward-statuses/{id} (partial update). Every field is
 * optional so the request only carries what actually changed.
 */
export type UpdateRewardStatusPayload = Partial<CreateRewardStatusPayload>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `RewardStatusForm` component.
 */
export type RewardStatusFormMode =
  | { type: 'create' }
  | { type: 'edit'; rewardStatus: RewardStatusDetailWithPermissions }
