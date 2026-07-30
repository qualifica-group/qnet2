/**
 * Shared types for status reordering (spec 0039 D-4/D-5), consumed by
 * `pipeline-statuses` and `opportunity-statuses` — the modules that keep a
 * "system status" concept and a
 * custom-only drag & drop reorder sheet.
 */

/**
 * Marks a system-managed status row; `null` on an ordinary custom row.
 * `lost` is opportunity-statuses only (spec 0043 D-2): "Persa", closed, the
 * fixed last row of the tail.
 */
export type SystemStatusKey = 'new' | 'won' | 'lost' | 'closed' | null

/** Fixed enum of status groups, replacing the former "status groups" lookup module. */
export const STATUS_GROUPS = ['open', 'pending', 'closed'] as const

/** One of the three fixed status group values. */
export type StatusGroupValue = (typeof STATUS_GROUPS)[number]

/** One row as reordered in the sheet: id, display name and its pin state. */
export interface StatusReorderItem {
  id: number
  name: string
  systemKey: SystemStatusKey
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
