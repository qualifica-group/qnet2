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

/** One "Fase" of a template (spec 0146 D-2), as returned by GET/POST/PUT/PATCH (ordered by `sort_order`). */
export interface TaskTemplateStage {
  id: number
  name: string
  sort_order: number
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
  /** The "Fase" this row sits in (spec 0146 D-2), `null` for "Senza fase". */
  task_template_stage_id: number | null
  attachments: TaskTemplateItemAttachment[]
}

/** Full template detail, as returned by GET/POST/PUT/PATCH /task-templates(/{id}). */
export interface TaskTemplateDetail {
  id: number
  name: string
  description: string | null
  is_active: boolean
  items_count: number
  /** In `sort_order` (spec 0146 D-2). */
  stages: TaskTemplateStage[]
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
  /** The owning `stages.*.key` of the SAME request, `null` for "Senza fase" (spec 0146 D-2/AC-003). */
  stage_key: string | null
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

/**
 * One `stages[]` entry accepted by POST (create): never carries an `id`
 * (spec 0146 D-2, mirrors `CreateTaskTemplateItemPayload`). `key` is the
 * CLIENT-assigned identity `items.*.stage_key` resolves against within the
 * SAME request — never persisted as-is.
 */
export interface CreateTaskTemplateStagePayload {
  key: string
  name: string
}

/**
 * One `stages[]` entry accepted by PUT/PATCH (update): `id` present = update
 * an existing stage, absent = a new one. A persisted stage missing from the
 * array is deleted server-side, its items falling back to "Senza fase"
 * (spec 0146 D-2/AC-005) — `stages` is always sent as the full authoritative
 * sync, never a sparse diff (mirrors `UpdateTaskTemplateItemPayload`).
 */
export interface UpdateTaskTemplateStagePayload extends CreateTaskTemplateStagePayload {
  id?: number
}

/** Payload for POST /task-templates. */
export interface CreateTaskTemplatePayload {
  name: string
  description: string | null
  is_active: boolean
  items: CreateTaskTemplateItemPayload[]
  stages: CreateTaskTemplateStagePayload[]
}

/** Payload for PUT/PATCH /task-templates/{id} (sparse per-field; `items`/`stages`, when present, are an authoritative sync). */
export interface UpdateTaskTemplatePayload {
  name?: string
  description?: string | null
  is_active?: boolean
  items?: UpdateTaskTemplateItemPayload[]
  stages?: UpdateTaskTemplateStagePayload[]
}

/** Discriminated form mode shared by the form hook/meta-resolver and `TaskTemplateForm`. */
export type TaskTemplateFormMode =
  | { type: 'create' }
  | { type: 'edit'; taskTemplate: TaskTemplateDetailWithPermissions }

/**
 * One row as edited locally by `<TaskTemplateStagesEditor>` (a local array,
 * not an RHF field array — mirrors quote-workflows' `WorkflowStatusFormRow`).
 * `id` is the STRING identity the drag board requires; `itemId` is the
 * persisted backend id, or `undefined` for a row not yet created (a
 * freshly-added row — the server assigns it a real id, returned in the same
 * visual order, D-9).
 *
 * `stage_key` (spec 0146 D-2) is the owning `TaskTemplateStageFormRow.id`,
 * `null` for "Senza fase" — the SAME string a stage row's `id` already is, so
 * moving a row between stages is a plain reassignment, never a lookup by
 * backend id (a new stage has none yet).
 */
export interface TaskTemplateItemFormRow {
  id: string
  itemId?: number
  title: string
  description: string | null
  estimated_minutes: number | null
  task_status_id: number | null
  due_offset_days: number
  stage_key: string | null
}

/** The row fields the editor may patch (everything but the row identity). */
export type TaskTemplateItemRowPatch = Partial<
  Pick<
    TaskTemplateItemFormRow,
    'title' | 'description' | 'estimated_minutes' | 'task_status_id' | 'due_offset_days' | 'stage_key'
  >
>

/** Per-row validation errors, keyed by the row's local `id`, then by field name. */
export type TaskTemplateItemErrors = Record<string, Partial<Record<keyof TaskTemplateItemRowPatch, string>>>

/**
 * One "Fase" row as edited locally by `<TaskTemplateStagesEditor>` (spec 0146
 * D-2), mirrors `TaskTemplateItemFormRow`'s own local-state shape: `id` is
 * BOTH the drag board's identity AND the `stages.*.key` sent to the server —
 * `stage-<id>` for a persisted row (`stageId` present) so item rows can
 * resolve their own `stage_key` back to it, or a fresh `new-stage-<n>` for
 * one not yet created (`stageId` absent, mirrors a new item row's `id`).
 */
export interface TaskTemplateStageFormRow {
  id: string
  stageId?: number
  name: string
}

/** The stage row fields the editor may patch (everything but the row identity). */
export type TaskTemplateStageRowPatch = Partial<Pick<TaskTemplateStageFormRow, 'name'>>

/** Per-stage validation errors, keyed by the row's local `id`, then by field name. */
export type TaskTemplateStageErrors = Record<string, Partial<Record<'name' | 'id', string>>>
