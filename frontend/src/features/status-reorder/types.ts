/**
 * Shared types for status reordering (spec 0039 D-4/D-5), consumed by
 * `pipeline-statuses` and `opportunity-statuses` — the modules that keep a
 * "system status" concept and a
 * custom-only drag & drop reorder sheet.
 */

/**
 * Marks a system-managed status row; `null` on an ordinary custom row.
 * `lost` is opportunity-statuses only (spec 0043 D-2): "Persa", closed, the
 * fixed last row of the tail. `suspended`/`cancelled`/`terminated` are
 * contract-statuses only (spec 0072 D-2): the TAIL trio ("Sospeso"/
 * "Annullato"/"Disdetto"), in that declared order.
 */
export type SystemStatusKey =
  | 'new'
  | 'won'
  | 'lost'
  | 'closed'
  | 'suspended'
  | 'cancelled'
  | 'terminated'
  | null

/** Fixed enum of status groups, replacing the former "status groups" lookup module. */
export const STATUS_GROUPS = ['open', 'pending', 'closed'] as const

/** One of the three fixed status group values. */
export type StatusGroupValue = (typeof STATUS_GROUPS)[number]

/**
 * Contract statuses classify on their OWN enum (backend `App\Enums\
 * ContractStatusGroup`, spec 0072 D-5): a dedicated vocabulary — coupling it
 * to another module's enum was rejected for the same reason
 * `WorkflowStatusGroup` was split off the shared `StatusGroup`.
 */
export const CONTRACT_STATUS_GROUPS = ['open', 'pending', 'closed_won', 'closed_lost'] as const

/** One of the four fixed contract status group values. */
export type ContractStatusGroupValue = (typeof CONTRACT_STATUS_GROUPS)[number]

/**
 * Reward statuses classify on their OWN enum (backend `App\Enums\
 * RewardStatusGroup`, spec 0073 D-5), same anti-coupling rule as the two
 * above — but on THREE values, not four (user directive 2026-08-03): a buono
 * waits for a decision (`pending`, the phase it is born on and the default of
 * a new custom row) or has one (`closed_won` "Approvato" / `closed_lost`
 * "Negato"). The `open` phase does not exist here.
 */
export const REWARD_STATUS_GROUPS = ['pending', 'closed_won', 'closed_lost'] as const

/** One of the three fixed reward status group values. */
export type RewardStatusGroupValue = (typeof REWARD_STATUS_GROUPS)[number]

/** One row as reordered in the sheet: id, display name and its pin state. */
export interface StatusReorderItem {
  id: number
  name: string
  systemKey: SystemStatusKey
  /**
   * `false` for a row the module has deactivated, `undefined` for a resource
   * whose for-select projects no `meta.is_active` — every module except the
   * five Task configurators (spec 0101). The sheet marks ONLY the explicit
   * `false`, so those resources stay visually untouched; a tri-state is the
   * point here, not a boolean with a default.
   */
  isActive?: boolean
}

/**
 * A single entry of the fresh, full list returned by `POST /{resource}/reorder`.
 * `system_key` is optional defensively (spec 0068 D-5): every current backend
 * always emits it, but a resource that omitted the key would otherwise
 * resolve to `undefined`, which `isPinned={(row) => row.systemKey !== null}`
 * (`StatusReorderSheet`) would treat as pinned for every row. The consuming
 * hook falls back to `null` (see `use-status-reorder.ts`).
 */
export interface ReorderedStatusEntry {
  id: number
  sort_order: number
  system_key?: SystemStatusKey
}
