import type { TFunction } from 'i18next'
import type {
  TaskTemplateItemErrors,
  TaskTemplateItemFormRow,
  TaskTemplateItemRowPatch,
} from '@/features/task-templates/types'

/** Backend `task_template_items.title` column limit (`max:191`). */
export const ITEM_TITLE_MAX_LENGTH = 191
/** Backend `items.*.due_offset_days` ceiling (`max:3650`). */
export const ITEM_DUE_OFFSET_MAX_DAYS = 3650
/** Backend `items.*.estimated_minutes` ceiling (`max:1000000`). */
export const ITEM_ESTIMATED_MINUTES_MAX = 1_000_000
/** Backend `items` array floor (`min:1`). */
export const ITEMS_MIN_COUNT = 1
/** Backend `items` array ceiling (`max:100`). */
export const ITEMS_MAX_COUNT = 100

export interface TaskTemplateItemsValidationResult {
  errors: TaskTemplateItemErrors
  /** Form-level message (e.g. the row COUNT itself is invalid), or `null` when the array size is fine. */
  formError: string | null
}

/**
 * Client-side mirror of the backend `items[]` validation (spec 0124
 * `data_contract`). Rows are local state, not an RHF field array, so they are
 * validated here rather than through the Zod resolver (mirrors
 * `useQuoteWorkflowForm`'s `validateStatusRows`). Returns per-row field
 * errors, keyed by the row's local `id` for `<TaskTemplateStagesEditor>`'s
 * aria-describedby wiring (frontend.md §10).
 */
export function validateTaskTemplateItemRows(
  rows: TaskTemplateItemFormRow[],
  t: TFunction,
): TaskTemplateItemsValidationResult {
  const errors: TaskTemplateItemErrors = {}

  if (rows.length < ITEMS_MIN_COUNT) {
    return { errors, formError: t('taskTemplates.form.items.required') }
  }
  if (rows.length > ITEMS_MAX_COUNT) {
    return { errors, formError: t('taskTemplates.form.items.tooMany', { max: ITEMS_MAX_COUNT }) }
  }

  for (const row of rows) {
    const rowErrors: TaskTemplateItemErrors[string] = {}

    if (row.title.trim() === '') {
      rowErrors.title = t('taskTemplates.form.items.titleRequired')
    } else if (row.title.length > ITEM_TITLE_MAX_LENGTH) {
      rowErrors.title = t('taskTemplates.form.items.titleMax')
    }

    if (row.due_offset_days < 0 || row.due_offset_days > ITEM_DUE_OFFSET_MAX_DAYS) {
      rowErrors.due_offset_days = t('taskTemplates.form.items.dueOffsetInvalid', {
        max: ITEM_DUE_OFFSET_MAX_DAYS,
      })
    }

    if (
      row.estimated_minutes !== null &&
      (row.estimated_minutes < 0 || row.estimated_minutes > ITEM_ESTIMATED_MINUTES_MAX)
    ) {
      rowErrors.estimated_minutes = t('taskTemplates.form.items.estimatedMinutesInvalid')
    }

    if (Object.keys(rowErrors).length > 0) {
      errors[row.id] = rowErrors
    }
  }

  return {
    errors,
    formError: Object.keys(errors).length > 0 ? t('taskTemplates.form.items.hasErrors') : null,
  }
}

/** Matches a backend `items.N.field` validation key. */
const ITEM_ERROR_KEY_PATTERN = /^items\.(\d+)\.(\w+)$/

/**
 * Maps a 422 response's flat `items.N.field` keys onto the row that occupied
 * position `N` in the array actually sent (`orderedRowIds`, positional —
 * the request and the response walk the same array). Any other error key
 * (e.g. `name`) is ignored here; the caller applies those separately via
 * `applyServerValidationErrors`.
 */
export function extractItemServerErrors(
  errors: Record<string, string[]> | undefined,
  orderedRowIds: string[],
): TaskTemplateItemErrors {
  const result: TaskTemplateItemErrors = {}
  if (!errors) {
    return result
  }

  for (const [key, messages] of Object.entries(errors)) {
    const match = ITEM_ERROR_KEY_PATTERN.exec(key)
    if (!match || messages.length === 0) {
      continue
    }
    const index = Number(match[1])
    const field = match[2] as keyof TaskTemplateItemRowPatch
    const rowId = orderedRowIds[index]
    if (!rowId) {
      continue
    }
    result[rowId] = { ...result[rowId], [field]: messages[0] }
  }

  return result
}
