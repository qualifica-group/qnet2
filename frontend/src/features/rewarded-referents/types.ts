/**
 * `rewarded-referents` module types (spec 0059 MT-7). The generic table types
 * (columns/filters/actions/rows) live in `features/table/types.ts`; this file
 * holds only what is genuinely specific to this domain: the SSRM row shape and
 * the master/detail lazy-load response. Source of truth: spec 0059 frozen
 * `data_contract` §2/§3. The shared reward shapes (`RewardDetailItem` and its
 * nested refs) live in `features/rewards/types.ts` (MT-6) and are reused
 * as-is, never re-declared here.
 */

import type { RewardDetailItem } from '@/features/rewards/types'

/**
 * One row of `POST /api/tables/rewarded-referents/rows` — a Referent with at
 * least one reward, with aggregated counters (D-2) and the fields the generic
 * table needs (`actions`/`editable`) are already covered by the shared
 * `TableRow` index signature, not repeated here.
 */
export interface RewardedReferentRow {
  id: number
  name: string
  full_name: string | null
  registries: string | null
  email: string | null
  phone: string | null
  rewards_count: number
  /** Buoni whose OWN status sits in the `pending` group (user directive 2026-08-31). */
  pending_rewards_count: number
  /** Buoni whose OWN status sits in the `closed_won` ("Approvato") group. */
  approved_rewards_count: number
  /** ISO date, or null when never assigned (unreachable in practice: every listed referent has ≥1 reward). */
  last_assigned_at: string | null
}

/** Response of the master/detail lazy endpoint `GET /api/referents/{referent}/rewards` (envelope `data`). */
export interface ReferentRewardsResponse {
  items: RewardDetailItem[]
}
