/**
 * TaskStatuses CRUD types. The generic table types (columns/filters/actions/rows)
 * live in `features/table/types.ts`; this file holds only what is genuinely
 * task-statuses-specific — the resource and its create/update payloads. Source of
 * truth: spec 0101 frozen `data_contract` (D-4/D-5).
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { TaskStatusGroupValue } from '@/features/status-reorder/types'

/**
 * The three frozen system keys of `task_statuses` (spec 0101 D-5, backend enum
 * `App\Enums\TaskStatusSystemKey`); `null` on an ordinary custom row. The
 * label never enters a condition — this key is the only reference.
 * `in_progress`/`pending`/`in_validation` are NOT keys here: they are phases,
 * carried by `group`, and a row may sit in one of them without being a system
 * row at all.
 */
export type TaskStatusSystemKey = 'open' | 'closed_positive' | 'closed_negative' | null

/**
 * Single task status detail returned by GET/POST/PATCH /task-statuses
 * (envelope `data`). `sort_order` is server-managed: read-only, never
 * accepted on write. `color` is a palette TOKEN (never a hex) and `icon` a
 * curated kebab-case lucide name, both validated against a server-side
 * allow-list (`App\Support\BadgeTokens`).
 */
export interface TaskStatusDetail {
  id: number
  name: string
  description: string | null
  /** Palette token of `BADGE_COLOR_TOKENS`, required by the backend. */
  color: string
  /** Curated lucide name of `ICON_NAMES`, or null when unset. */
  icon: string | null
  sort_order: number
  is_active: boolean
  /** Marks one of the three system rows (D-5): never writable, never sent. */
  system_key: TaskStatusSystemKey
  /** The phase the status belongs to; always present, never null. */
  group: TaskStatusGroupValue
  /** 0-100. The Task's completion percentage is a projection of this (D-6). */
  completion_percentage: number
  created_at: string | null
  updated_at: string | null
}

/**
 * A `TaskStatusDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /task-statuses/{id}` (`show`). Used
 * to seed the edit form's `ResourcePermissionsProvider` without a second
 * request.
 */
export interface TaskStatusDetailWithPermissions extends TaskStatusDetail {
  permissions: ResourcePermissions
}

/**
 * Payload for POST /task-statuses (create). `sort_order` and `system_key` are NOT
 * accepted by the backend: placement is automatic and a custom row never carries a system key.
 */
export interface CreateTaskStatusPayload {
  name: string
  color: string
  icon?: string | null
  description?: string | null
  group: TaskStatusGroupValue
  is_active?: boolean
  completion_percentage: number
}

/**
 * Payload for PATCH /task-statuses/{id} (partial update). Every field is optional
 * so the request only carries what actually changed.
 */
export type UpdateTaskStatusPayload = Partial<CreateTaskStatusPayload>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `TaskStatusForm` component.
 */
export type TaskStatusFormMode =
  | { type: 'create' }
  | { type: 'edit'; taskStatus: TaskStatusDetailWithPermissions }
