/**
 * TaskPriorities CRUD types. The generic table types (columns/filters/actions/rows)
 * live in `features/table/types.ts`; this file holds only what is genuinely
 * task-priorities-specific — the resource and its create/update payloads. Source of
 * truth: spec 0101 frozen `data_contract` (D-4).
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Single task priority detail returned by GET/POST/PATCH /task-priorities
 * (envelope `data`). `sort_order` is server-managed: read-only, never
 * accepted on write. `color` is a palette TOKEN (never a hex) and `icon` a
 * curated kebab-case lucide name, both validated against a server-side
 * allow-list (`App\Support\BadgeTokens`).
 */
export interface TaskPriorityDetail {
  id: number
  name: string
  description: string | null
  /** Palette token of `BADGE_COLOR_TOKENS`, required by the backend. */
  color: string
  /** Curated lucide name of `ICON_NAMES`, or null when unset. */
  icon: string | null
  sort_order: number
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

/**
 * A `TaskPriorityDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /task-priorities/{id}` (`show`). Used
 * to seed the edit form's `ResourcePermissionsProvider` without a second
 * request.
 */
export interface TaskPriorityDetailWithPermissions extends TaskPriorityDetail {
  permissions: ResourcePermissions
}

/**
 * Payload for POST /task-priorities (create). `sort_order` is NOT
 * accepted by the backend: placement is server-managed.
 */
export interface CreateTaskPriorityPayload {
  name: string
  color: string
  icon?: string | null
  description?: string | null
  is_active?: boolean
}

/**
 * Payload for PATCH /task-priorities/{id} (partial update). Every field is optional
 * so the request only carries what actually changed.
 */
export type UpdateTaskPriorityPayload = Partial<CreateTaskPriorityPayload>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `TaskPriorityForm` component.
 */
export type TaskPriorityFormMode =
  | { type: 'create' }
  | { type: 'edit'; taskPriority: TaskPriorityDetailWithPermissions }
