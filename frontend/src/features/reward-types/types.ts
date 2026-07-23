/**
 * Reward types CRUD types. The generic table types (columns/filters/actions/
 * rows) live in `features/table/types.ts`; this file holds only what is
 * genuinely reward-types-specific — the resource and its create/update
 * payloads. Source of truth: spec 0058 frozen `data_contract`. Unlike its
 * template `opportunity-statuses`, this module has no `group`/`sort_order`/
 * `system_key` (D-1, no system rows to order) and `color` is REQUIRED, never
 * null (D-5). This module has no custom fields (spec 0058 scope: out).
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Single reward type detail returned by GET/POST/PATCH /reward-types
 * (envelope `data`). Matches `RewardTypeResource`.
 */
export interface RewardTypeDetail {
  id: number
  name: string
  /** Palette token (e.g. "blue"), always set (D-5). */
  color: string
  created_at: string
  updated_at: string
}

/**
 * A `RewardTypeDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /reward-types/{id}` (`show`).
 * Used to seed the edit form's `ResourcePermissionsProvider` without a second
 * request.
 */
export interface RewardTypeDetailWithPermissions extends RewardTypeDetail {
  permissions: ResourcePermissions
}

/** Payload for POST /reward-types (create). Both fields are required (D-5). */
export interface CreateRewardTypePayload {
  name: string
  color: string
}

/**
 * Payload for PATCH /reward-types/{id} (partial update). Every field is
 * optional so the request only carries what actually changed — but when
 * present, `color` can never be blanked (D-5, BR-2).
 */
export type UpdateRewardTypePayload = Partial<CreateRewardTypePayload>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `RewardTypeForm` component.
 */
export type RewardTypeFormMode =
  | { type: 'create' }
  | { type: 'edit'; rewardType: RewardTypeDetailWithPermissions }
