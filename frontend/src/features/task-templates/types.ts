/**
 * Task templates CRUD types (spec 0124). Source of truth: the frozen backend
 * contract (`data_contract` shapes `TaskTemplate`/`TaskTemplateItem`). A
 * template's rows are edited only as part of the header payload (D-1): there
 * is no separate item endpoint, so create/update always carry the full
 * `items[]` array.
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/** Polymorphic owner alias for a template row's attachments (`config/attachments.php`), D-6. */
export const TASK_TEMPLATE_ITEM_ATTACHABLE_ALIAS = 'task_template_item'

/** One attachment of a template item, as embedded in `TaskTemplateItem.attachments`. */
export interface TaskTemplateItemAttachment {
  id: number
  original_name: string
  mime_type: string
  extension: string | null
  size: number
  created_at: string
}

/** The task status a row seeds the generated task with (D-4), projected for display. */
export interface TaskTemplateItemStatusRef {
  id: number
  name: string
  color: string
  group: string
}

/** One row of a template, as returned by GET/POST/PUT/PATCH (ordered by `sort_order`). */
export interface TaskTemplateItem {
  id: number
  title: string
  description: string | null
  estimated_minutes: number | null
  task_status_id: number | null
  task_status: TaskTemplateItemStatusRef | null
  due_offset_days: number
  sort_order: number
  attachments: TaskTemplateItemAttachment[]
}

/** Full template detail, as returned by GET/POST/PUT/PATCH /task-templates(/{id}). */
export interface TaskTemplateDetail {
  id: number
  name: string
  description: string | null
  is_active: boolean
  items_count: number
  items: TaskTemplateItem[]
  created_at: string
  updated_at: string
}

/** A `TaskTemplateDetail` carrying the actor's authorization metadata for this instance (spec 0004). */
export interface TaskTemplateDetailWithPermissions extends TaskTemplateDetail {
  permissions: ResourcePermissions
}

/** One `items[]` entry accepted by POST (create): never carries an `id` (D-1). */
export interface CreateTaskTemplateItemPayload {
  title: string
  description: string | null
  estimated_minutes: number | null
  task_status_id: number | null
  due_offset_days: number
}

/**
 * One `items[]` entry accepted by PUT/PATCH (update): `id` present = update
 * an existing row, absent = a new row. A persisted row missing from the
 * array is deleted server-side (D-1/AC-004) — `items` is always sent as the
 * full authoritative sync, never a sparse diff.
 */
export interface UpdateTaskTemplateItemPayload extends CreateTaskTemplateItemPayload {
  id?: number
}

/** Payload for POST /task-templates. */
export interface CreateTaskTemplatePayload {
  name: string
  description: string | null
  is_active: boolean
  items: CreateTaskTemplateItemPayload[]
}

/** Payload for PUT/PATCH /task-templates/{id} (sparse per-field; `items`, when present, is an authoritative sync). */
export interface UpdateTaskTemplatePayload {
  name?: string
  description?: string | null
  is_active?: boolean
  items?: UpdateTaskTemplateItemPayload[]
}

/** Discriminated form mode shared by the form hook/meta-resolver and `TaskTemplateForm`. */
export type TaskTemplateFormMode =
  | { type: 'create' }
  | { type: 'edit'; taskTemplate: TaskTemplateDetailWithPermissions }

/**
 * One row as edited locally by `<TaskTemplateItemsEditor>` (a SortableList-
 * driven local array, not an RHF field array — mirrors quote-workflows'
 * `WorkflowStatusFormRow`). `id` is the STRING identity `<SortableList>`
 * requires; `itemId` is the persisted backend id, or `undefined` for a row
 * not yet created (a freshly-added row — the server assigns it a real id,
 * returned in the same visual order, D-9).
 */
export interface TaskTemplateItemFormRow {
  id: string
  itemId?: number
  title: string
  description: string | null
  estimated_minutes: number | null
  task_status_id: number | null
  due_offset_days: number
}

/** The row fields the editor may patch (everything but the row identity). */
export type TaskTemplateItemRowPatch = Partial<
  Pick<
    TaskTemplateItemFormRow,
    'title' | 'description' | 'estimated_minutes' | 'task_status_id' | 'due_offset_days'
  >
>

/** Per-row validation errors, keyed by the row's local `id`, then by field name. */
export type TaskTemplateItemErrors = Record<string, Partial<Record<keyof TaskTemplateItemRowPatch, string>>>
