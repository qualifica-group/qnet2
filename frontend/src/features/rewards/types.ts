/**
 * Shared reward types (spec 0059 MT-6). This module holds ONLY the shapes
 * consumed by the presentational components in `features/rewards/` — the
 * chip, the chip list and the master/detail card — so both the
 * `rewarded-referents` module and the Opportunity/Gestione Richiesta forms
 * read the exact same contract. No fetching, no domain logic here.
 * Source of truth: spec 0059 `data_contract`.
 */

import type { OpportunityStatusSummary } from '@/features/opportunities/types'

/** A reward type's identity as embedded in a reward: name + palette token color. */
export interface RewardTypeRef {
  id: number
  name: string
  /** Palette token (e.g. "blue"); resolved to classes via `badgeColorClass`. */
  color: string
}

/**
 * A reward status's identity as embedded in a reward: name + palette token
 * color (spec 0060 §3 `resource_change`). Distinct from `RewardTypeRef`
 * (same shape, different domain) so each ref stays self-documenting at the
 * call site.
 */
export interface RewardStatusRef {
  id: number
  name: string
  color: string
}

/**
 * The polymorphic origin of a reward, resolved server-side to a display name
 * and a direct link. `type` is the morph-map alias (e.g. "opportunity"),
 * never a FQCN.
 */
export interface RewardSourceRef {
  type: string
  id: number
  name: string
  path: string
}

/**
 * The origin's live commercial context (spec 0059 D-1/D-2): read from the
 * Opportunity's current relations, never persisted on `rewards`. Every field
 * is independently nullable.
 */
export interface RewardContext {
  registry: { id: number; name: string } | null
  product_categories: { id: number; name: string }[]
  /** Spec 0082: the origin Opportunity's COMPUTED status (its quotes' statuses). */
  status: OpportunityStatusSummary
  workflow_status: { id: number; name: string; color: string | null } | null
  operator: { id: number; name: string; avatar_url: string | null } | null
}

/**
 * A single item of `GET /api/referents/{referent}/rewards` (the master/detail
 * lazy endpoint). `source`/`context` are null when the origin was deleted.
 */
export interface RewardDetailItem {
  id: number
  /** ISO date. */
  assigned_at: string
  notes: string | null
  reward_type: RewardTypeRef
  source: RewardSourceRef | null
  context: RewardContext | null
  /**
   * The persisted status of the reward itself (spec 0060 D-1/D-5), NOT
   * derived from `context`. Null only transiently — at steady state every
   * reward carries one (backfilled to the system row `pending`).
   */
  reward_status: RewardStatusRef | null
}

/**
 * The `rewards` block embedded in `OpportunityResource` (create/update
 * response), used to hydrate the abbinamento control in edit mode.
 */
export interface RewardAssignmentRef {
  id: number
  reward_type: RewardTypeRef
  /** ISO date. */
  assigned_at: string
  notes: string | null
}
