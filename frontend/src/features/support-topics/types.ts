/**
 * Support topics ("Tipi di problema") CRUD types. Source of truth: spec 0334
 * frozen `data_contract`. The generic table types live in `features/table/types.ts`.
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/** Minimal `{id, name}` projection the resource exposes for each relation. */
export interface SupportTopicRef {
  id: number
  name: string
}

/** Single support topic returned by GET/POST/PATCH /support-topics (envelope `data`). */
export interface SupportTopicDetail {
  id: number
  name: string
  description: string | null
  /** Curated lucide name or null when unset. */
  icon: string | null
  sort_order: number
  is_active: boolean
  task_type: SupportTopicRef | null
  task_category: SupportTopicRef | null
  task_priority: SupportTopicRef | null
  urgent_task_priority: SupportTopicRef | null
  task_importance: SupportTopicRef | null
  due_days: number
  urgent_due_days: number | null
  is_private: boolean
  requires_closure_feedback: boolean
  assignees: SupportTopicRef[]
  watchers: SupportTopicRef[]
  created_at: string | null
  updated_at: string | null
}

/** A detail carrying the actor's authorization metadata (`show`). */
export interface SupportTopicDetailWithPermissions extends SupportTopicDetail {
  permissions: ResourcePermissions
}

/** Payload for POST /support-topics. */
export interface CreateSupportTopicPayload {
  name: string
  description: string | null
  icon: string | null
  sort_order: number
  is_active: boolean
  task_type_id: number | null
  task_category_id: number | null
  task_priority_id: number | null
  urgent_task_priority_id: number | null
  task_importance_id: number | null
  due_days: number
  urgent_due_days: number | null
  is_private: boolean
  requires_closure_feedback: boolean
  assignee_ids: number[]
  watcher_ids: number[]
}

/** Payload for PATCH /support-topics/{id}: only the changed keys. */
export type UpdateSupportTopicPayload = Partial<CreateSupportTopicPayload>

export type SupportTopicFormMode =
  | { type: 'create' }
  | { type: 'edit'; supportTopic: SupportTopicDetailWithPermissions }
