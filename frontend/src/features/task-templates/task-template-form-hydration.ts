import { uploadAttachment } from '@/features/attachments/api'
import { DOCUMENTS_COLLECTION } from '@/features/attachments/types'
import { TASK_TEMPLATE_ITEM_ATTACHABLE_ALIAS } from '@/features/task-templates/types'
import type {
  TaskTemplateDetail,
  TaskTemplateItemFormRow,
  TaskTemplateStageFormRow,
} from '@/features/task-templates/types'

/** A brand-new, still-untitled row appended by "Aggiungi riga" — lands in "Senza fase" until dragged into one. */
export function newEmptyItemRow(id: string): TaskTemplateItemFormRow {
  return {
    id,
    title: '',
    description: null,
    estimated_minutes: null,
    task_status_id: null,
    due_offset_days: 0,
    stage_key: null,
  }
}

/** The client `stages.*.key` a persisted `task_template_stage_id` resolves to (spec 0146 D-2), `null` for "Senza fase". */
export function stageKeyOf(taskTemplateStageId: number | null): string | null {
  return taskTemplateStageId === null ? null : `stage-${taskTemplateStageId}`
}

/** Hydrates the editable rows from a persisted template (already ordered by `sort_order`). */
export function itemRowsFromDetail(taskTemplate: TaskTemplateDetail): TaskTemplateItemFormRow[] {
  return taskTemplate.items.map((item) => ({
    id: String(item.id),
    itemId: item.id,
    title: item.title,
    description: item.description,
    estimated_minutes: item.estimated_minutes,
    task_status_id: item.task_status_id,
    due_offset_days: item.due_offset_days,
    stage_key: stageKeyOf(item.task_template_stage_id),
  }))
}

/** Hydrates the editable stage rows from a persisted template (already ordered by `sort_order`, spec 0146 D-2). */
export function stageRowsFromDetail(taskTemplate: TaskTemplateDetail): TaskTemplateStageFormRow[] {
  return taskTemplate.stages.map((stage) => ({
    id: stageKeyOf(stage.id) as string,
    stageId: stage.id,
    name: stage.name,
  }))
}

/**
 * Uploads every staged file of a row against the item id the server just
 * assigned it, one request at a time (mirrors `useTaskForm`'s own sequential
 * upload: the endpoint takes one file per request). Never throws: a rejected
 * file is reported back by name so the caller can still succeed the save
 * (the template — and its rows — are already persisted).
 */
export async function uploadStagedRowAttachments(itemId: number, files: File[]): Promise<string[]> {
  const failed: string[] = []
  for (const file of files) {
    try {
      await uploadAttachment({
        resource: TASK_TEMPLATE_ITEM_ATTACHABLE_ALIAS,
        id: itemId,
        collection: DOCUMENTS_COLLECTION,
        file,
      })
    } catch {
      failed.push(file.name)
    }
  }
  return failed
}
